<?php
/**
 * Batch Processing Handler
 *
 * Submits batches to Claude Batch API; completion is detected via client-side
 * polling (one check on admin load, then every 1 min if batches in progress).
 */

if (!defined('ABSPATH')) {
	exit;
}

class RR_Image_Alt_Batch {

	const MAX_BATCH_SIZE = 100;

	private $api;
	private $table_name;

	public function __construct( $api ) {
		global $wpdb;
		$this->api        = $api;
		$this->table_name = $wpdb->prefix . 'rr_alt_batches';
	}

	public function init() {
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_admin_notice' ) );

		add_action( 'wp_ajax_rr_any_batches_in_progress', array( $this, 'ajax_any_batches_in_progress' ) );
		add_action( 'wp_ajax_rr_check_and_process_batches', array( $this, 'ajax_check_and_process_batches' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_batch_poller' ) );
	}


	/**
	 * Enqueue batch status poller on all admin pages (one check on load; interval only if batches in progress).
	 */
	public function enqueue_batch_poller() {
		$path = 'admin/js/batch-status-poller.js';
		$url  = RR_IMAGE_ALT_PLUGIN_URL . $path;
		$ver  = RR_IMAGE_ALT_VERSION;

		wp_register_script(
			'rr-batch-status-poller',
			$url,
			array(),
			$ver,
			true
		);
		wp_localize_script( 'rr-batch-status-poller', 'rrBatchPoller', array(
			'nonce'     => wp_create_nonce( 'rr_batch_check' ),
			'onAllDone' => 'reload',
		) );
		wp_enqueue_script( 'rr-batch-status-poller' );
	}

	/**
	 * Register bulk action
	 */
	public function register_bulk_action( $bulk_actions ) {
		$bulk_actions['rr_generate_alt_text'] = __( 'Generate Alt Text with Claude AI', 'rr-image-alt' );

		return $bulk_actions;
	}


	/**
	 * Handle bulk action: submit to Claude Batch API and redirect
	 */
	public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( $action !== 'rr_generate_alt_text' ) {
			return $redirect_to;
		}

		$image_ids     = array();
		$skipped_count = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $this->is_supported_image( $post_id ) ) {
				$image_ids[] = (int) $post_id;
			} else {
				$skipped_count ++;
			}
		}

		if ( empty( $image_ids ) ) {
			$error_message = $skipped_count > 0
				? 'no_supported_images'
				: 'no_images';
			$redirect_to   = add_query_arg( 'rr_alt_error', $error_message, $redirect_to );

			return $redirect_to;
		}

		$batch_ids = $this->submit_batches_to_api( $image_ids );

		if ( $batch_ids ) {
			$redirect_to = add_query_arg( 'rr_alt_batch', $batch_ids[0], $redirect_to );
			$redirect_to = add_query_arg( 'rr_alt_count', count( $image_ids ), $redirect_to );
			$redirect_to = add_query_arg( 'rr_alt_batch_count', count( $batch_ids ), $redirect_to );
			if ( $skipped_count > 0 ) {
				$redirect_to = add_query_arg( 'rr_alt_skipped', $skipped_count, $redirect_to );
			}
		} else {
			$redirect_to = add_query_arg( 'rr_alt_error', 'batch_failed', $redirect_to );
		}

		return $redirect_to;
	}

	/**
	 * Build Batch API requests and submit to Anthropic; store batch_id in DB.
	 *
	 * @param int[] $attachment_ids
	 *
	 * @return string|false Anthropic batch ID or false
	 */
	public function submit_batch_to_api( $attachment_ids ) {
		$settings = get_option( 'rr_image_alt_settings' );
		if ( empty( $settings['api_key'] ) ) {
			error_log( 'RR Image Alt Generator: API key not configured' );

			return false;
		}

		$model        = ! empty( $settings['model'] ) ? $settings['model'] : 'claude-haiku-4-5-20251001';
		$requests     = array();
		$valid_images = array();

		foreach ( $attachment_ids as $attachment_id ) {
			// Double-check that this is still a supported image
			if ( ! $this->is_supported_image( $attachment_id ) ) {
				error_log( 'RR Image Alt Generator: Skipping non-image attachment ID: ' . $attachment_id );
				continue;
			}

			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				error_log( 'RR Image Alt Generator: No URL found for attachment ID: ' . $attachment_id );
				continue;
			}

			$image_content = $this->api->prepare_image_content( $image_url );
			if ( ! $image_content ) {
				error_log( 'RR Image Alt Generator: Failed to prepare image content for attachment ID: ' . $attachment_id );
				continue;
			}

			$requests[] = array(
				'custom_id' => 'attachment_' . $attachment_id,
				'params'    => array(
					'model'      => $model,
					'max_tokens' => 100,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => array(
								$image_content,
								array(
									'type' => 'text',
									'text' => $this->api->get_generation_prompt(),
								),
							),
						),
					),
				),
			);

			$valid_images[] = $attachment_id;
		}

		if ( empty( $requests ) ) {
			error_log( 'RR Image Alt Generator: No valid images to submit' );

			return false;
		}

		$batch_id = $this->api->create_batch_request( $requests );
		if ( ! $batch_id ) {
			return false;
		}

		global $wpdb;
		$result = $wpdb->insert(
			$this->table_name,
			array(
				'batch_id'       => $batch_id,
				'attachment_ids' => json_encode( $valid_images ), // Store only valid images
				'model'          => $model,
				'status'         => 'processing',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			error_log( 'RR Image Alt Generator: Failed to store batch record' );

			return false;
		}

		return $batch_id;
	}

	/**
	 * Split attachment IDs into chunks of MAX_BATCH_SIZE and submit each as a separate batch.
	 *
	 * @param int[] $attachment_ids
	 *
	 * @return string[]|false Array of Anthropic batch IDs, or false if all chunks failed.
	 */
	public function submit_batches_to_api( $attachment_ids ) {
		$chunks    = array_chunk( $attachment_ids, self::MAX_BATCH_SIZE );
		$batch_ids = array();

		foreach ( $chunks as $chunk ) {
			$batch_id = $this->submit_batch_to_api( $chunk );
			if ( $batch_id ) {
				$batch_ids[] = $batch_id;
			}
		}

		return ! empty( $batch_ids ) ? $batch_ids : false;
	}

	/**
	 * AJAX: Return whether any batches are pending/processing (lightweight, no API calls).
	 */
	public function ajax_any_batches_in_progress() {
		check_ajax_referer( 'rr_batch_check', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'rr-image-alt' ) ) );
		}

		global $wpdb;
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE status IN ('pending', 'processing')"
		);

		wp_send_json_success( array(
			'in_progress' => $count > 0,
			'count'       => $count,
		) );
	}

	/**
	 * AJAX: Check all pending/processing batches; fetch results and update if ended.
	 * Return still_in_progress so client can stop the interval when false.
	 */
	public function ajax_check_and_process_batches() {
		check_ajax_referer( 'rr_batch_check', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'rr-image-alt' ) ) );
		}

		global $wpdb;
		$pending = $wpdb->get_results(
			"SELECT batch_id FROM {$this->table_name} WHERE status IN ('pending', 'processing')"
		);

		$still_in_progress = false;
		$updated           = array();
		$debug_info        = array(); // For debugging

		foreach ( $pending as $row ) {
			$debug_info[ $row->batch_id ] = array( 'step' => 'start' );
			$status_data                  = $this->api->get_batch_status( $row->batch_id );
			if ( ! $status_data ) {
				$debug_info[ $row->batch_id ]['step'] = 'api_failed';
				$still_in_progress                    = true;
				error_log( 'RR Image Alt Generator: Failed to get status for batch ' . $row->batch_id );
				continue;
			}

			$status                                 = isset( $status_data['processing_status'] ) ? $status_data['processing_status'] : '';
			$debug_info[ $row->batch_id ]['status'] = $status;

			if ( $status !== 'ended' ) {
				$debug_info[ $row->batch_id ]['step'] = 'still_processing';
				$still_in_progress                    = true;
				error_log( 'RR Image Alt Generator: Batch ' . $row->batch_id . ' status: ' . $status );
				continue;
			}

			// Batch has ended, try to process results
			$results_url                                 = isset( $status_data['results_url'] ) ? $status_data['results_url'] : '';
			$results_url                                 = esc_url_raw( $results_url );
			$debug_info[ $row->batch_id ]['results_url'] = $results_url;

			if ( empty( $results_url ) || strpos( $results_url, 'https://api.anthropic.com/' ) !== 0 ) {
				$debug_info[ $row->batch_id ]['step'] = 'invalid_results_url';
				error_log( 'RR Image Alt Generator: Batch ' . $row->batch_id . ' ended but no valid results_url: ' . $results_url );

				// Mark this batch as failed in database so we don't keep checking it
				$wpdb->update(
					$this->table_name,
					array(
						'status'        => 'failed',
						'error_message' => 'No valid results URL provided',
						'completed_at'  => current_time( 'mysql' )
					),
					array( 'batch_id' => $row->batch_id ),
					array( '%s', '%s', '%s' ),
					array( '%s' )
				);
				continue;
			}

			// Try to process the results
			$ok = $this->process_batch_results( $row->batch_id, $results_url );
			if ( $ok ) {
				$debug_info[ $row->batch_id ]['step'] = 'completed';
				$updated[]                            = $row->batch_id;
				error_log( 'RR Image Alt Generator: Successfully processed batch ' . $row->batch_id );
			} else {
				$debug_info[ $row->batch_id ]['step'] = 'process_failed';
				error_log( 'RR Image Alt Generator: Failed to process results for batch ' . $row->batch_id );

				// Don't set still_in_progress = true here - the batch is ended, we just couldn't process it
				// The process_batch_results method should have already marked it as failed in the database
			}
		}

		error_log( 'RR Image Alt Generator: Batch check complete. Still in progress: ' . ( $still_in_progress ? 'yes' : 'no' ) . ', Updated: ' . count( $updated ) );

		wp_send_json_success( array(
			'still_in_progress' => $still_in_progress,
			'updated'           => $updated,
			'debug'             => $debug_info // Remove this in production
		) );
	}


	/**
	 * Fetch batch results from API, parse JSONL, update attachment alt text, mark batch completed.
	 *
	 * @param string $batch_id Our DB batch_id (Anthropic ID).
	 * @param string $results_url Valid Anthropic results URL.
	 *
	 * @return bool
	 */
	public function process_batch_results( $batch_id, $results_url ) {
		global $wpdb;

		// First, get the batch status data to extract success/error counts
		$status_data     = $this->api->get_batch_status( $batch_id );
		$succeeded_count = 0;
		$errored_count   = 0;

		if ( $status_data && isset( $status_data['request_counts'] ) ) {
			$succeeded_count = (int) $status_data['request_counts']['succeeded'];
			$errored_count   = (int) $status_data['request_counts']['errored'];
		}

		error_log( 'RR Image Alt Generator: Processing results for batch ' . $batch_id . ' from URL: ' . $results_url );
		error_log( 'RR Image Alt Generator: Batch stats - Succeeded: ' . $succeeded_count . ', Errored: ' . $errored_count );

		$results_body = $this->api->fetch_batch_results( $results_url );
		if ( $results_body === false || $results_body === '' ) {
			error_log( 'RR Image Alt Generator: Failed to fetch results body for batch ' . $batch_id );
			$wpdb->update(
				$this->table_name,
				array(
					'status'        => 'failed',
					'error_message' => 'Failed to fetch results from URL',
					'completed_at'  => current_time( 'mysql' )
				),
				array( 'batch_id' => $batch_id ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);

			return false;
		}

		$lines     = array_filter( explode( "\n", trim( $results_body ) ) );
		$processed = 0;
		$failed    = 0;

		error_log( 'RR Image Alt Generator: Processing ' . count( $lines ) . ' result lines for batch ' . $batch_id );

		foreach ( $lines as $line ) {
			$result = json_decode( $line, true );
			if ( ! isset( $result['custom_id'], $result['result'] ) ) {
				continue;
			}

			if ( ! preg_match( '/attachment_(\d+)/', $result['custom_id'], $m ) ) {
				continue;
			}

			$attachment_id = (int) $m[1];
			$res           = $result['result'];

			if ( isset( $res['type'] ) && $res['type'] === 'succeeded' && ! empty( $res['message']['content'][0]['text'] ) ) {
				$alt_text = trim( $res['message']['content'][0]['text'] );
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
				update_post_meta( $attachment_id, '_rr_alt_generated', '1' );
				$processed ++;
				error_log( 'RR Image Alt Generator: Updated alt text for attachment ' . $attachment_id . ': ' . $alt_text );
			} else {
				$failed ++;
				error_log( 'RR Image Alt Generator: Failed to process attachment ' . $attachment_id . ' - result: ' . json_encode( $res ) );
			}
		}

		// Update with success/error counts
		$wpdb->update(
			$this->table_name,
			array(
				'status'          => 'completed',
				'completed_at'    => current_time( 'mysql' ),
				'results'         => $results_body,
				'succeeded_count' => $succeeded_count,
				'errored_count'   => $errored_count,
			),
			array( 'batch_id' => $batch_id ),
			array( '%s', '%s', '%s', '%d', '%d' ),
			array( '%s' )
		);

		error_log( 'RR Image Alt Generator: Batch processing completed for ' . $batch_id . '. API reported - Succeeded: ' . $succeeded_count . ', Errored: ' . $errored_count . '. Local processing - Processed: ' . $processed . ', Failed: ' . $failed );

		return true;
	}

	/**
	 * Create bulk generation (Bulk Generate page): get attachment IDs then submit to API.
	 *
	 * @param string $mode 'all' or 'missing'
	 *
	 * @return string[]|false Array of Anthropic batch IDs, or false if no images found or all batches failed.
	 */
	public function create_bulk_generation_request( $mode = 'missing' ) {
		global $wpdb;

		// Define supported image mime types
		$supported_types   = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' );
		$mime_placeholders = implode( ',', array_fill( 0, count( $supported_types ), '%s' ) );

		if ( $mode === 'all' ) {
			$attachment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND post_mime_type IN ($mime_placeholders)",
					...$supported_types
				)
			);
		} else {
			$attachment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                WHERE p.post_type = 'attachment' 
                AND p.post_mime_type IN ($mime_placeholders)
                AND (pm.meta_value IS NULL OR pm.meta_value = '')",
					...$supported_types
				)
			);
		}

		if ( empty( $attachment_ids ) ) {
			error_log( 'RR Image Alt Generator: No supported images found for bulk generation mode: ' . $mode );

			return false;
		}

		return $this->submit_batches_to_api( array_map( 'intval', $attachment_ids ) );
	}

	/**
	 * Get all batches for display.
	 */
	public function get_batches( $limit = 50 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}


	/**
	 * Admin notice after bulk action: no Start button, just message + link to Batch Jobs.
	 */
	public function bulk_action_admin_notice() {
		if ( ! empty( $_REQUEST['rr_alt_batch'] ) ) {
			$batch_id    = sanitize_text_field( wp_unslash( $_REQUEST['rr_alt_batch'] ) );
			$count       = ! empty( $_REQUEST['rr_alt_count'] ) ? (int) $_REQUEST['rr_alt_count'] : 0;
			$skipped     = ! empty( $_REQUEST['rr_alt_skipped'] ) ? (int) $_REQUEST['rr_alt_skipped'] : 0;
			$batch_count = ! empty( $_REQUEST['rr_alt_batch_count'] ) ? (int) $_REQUEST['rr_alt_batch_count'] : 1;

			if ( $batch_count > 1 ) {
				$message = sprintf(
					__( '%1$d image(s) submitted across %2$d batches (max %3$d per batch). Processing runs in the background. Status is checked every minute while you have an admin tab open, or use "Check status now" on the Batch Processing page.', 'rr-image-alt' ),
					$count,
					$batch_count,
					self::MAX_BATCH_SIZE
				);
			} else {
				$message = sprintf(
					__( 'Batch submitted for %1$d image(s). Processing runs in the background. Status is checked every minute while you have an admin tab open, or use "Check status now" on the Batch Processing page.', 'rr-image-alt' ),
					$count
				);
			}

			if ( $skipped > 0 ) {
				$message .= ' ' . sprintf(
						__( '%1$d files were skipped because they are not supported image types (only JPG, PNG, GIF, and WebP images are supported).', 'rr-image-alt' ),
						$skipped
					);
			}

			$url = admin_url( 'admin.php?page=rr-image-alt-batch&tab=batch-progress' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <a href="%s" class="button button-secondary">%s</a></p></div>',
				esc_html( $message ),
				esc_url( $url ),
				esc_html__( 'View Batch Progress Tracker', 'rr-image-alt' )
			);
		}

		if ( ! empty( $_REQUEST['rr_alt_error'] ) ) {
			$error    = sanitize_text_field( wp_unslash( $_REQUEST['rr_alt_error'] ) );
			$messages = array(
				'no_images'           => __( 'No files selected. Please select image files.', 'rr-image-alt' ),
				'no_supported_images' => __( 'No supported image files selected. Only JPG, PNG, GIF, and WebP images are supported.', 'rr-image-alt' ),
				'batch_failed'        => __( 'Failed to create batch. Check your API key and try again.', 'rr-image-alt' ),
			);
			$message  = isset( $messages[ $error ] ) ? $messages[ $error ] : __( 'An error occurred.', 'rr-image-alt' );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * Check if attachment is a supported image type
	 *
	 * @param int $attachment_id
	 *
	 * @return bool
	 */
	private function is_supported_image( $attachment_id ) {
		// First check if WordPress considers it an image
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		// Get the mime type
		$mime_type = get_post_mime_type( $attachment_id );

		// Define supported image types
		$supported_types = array(
			'image/jpeg',
			'image/jpg',
			'image/png',
			'image/gif',
			'image/webp'
		);

		return in_array( $mime_type, $supported_types, true );
	}

	/**
	 * Get count of all supported images in media library
	 *
	 * @return int
	 */
	public function get_total_image_count() {
		global $wpdb;

		// Define supported image mime types
		$supported_types   = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' );
		$mime_placeholders = implode( ',', array_fill( 0, count( $supported_types ), '%s' ) );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ($mime_placeholders)",
				...$supported_types
			)
		);
	}


	/**
	 * Get table name for external access
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->table_name;
	}

	/**
	 * Get API instance for external access
	 *
	 * @return RR_Image_Alt_API
	 */
	public function get_api() {
		return $this->api;
	}


	/**
	 * Get image statistics for display
	 *
	 * @return array
	 */
	public function get_image_statistics() {
		global $wpdb;

		// Define supported image mime types
		$supported_types   = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' );
		$mime_placeholders = implode( ',', array_fill( 0, count( $supported_types ), '%s' ) );

		// Total supported images
		$total_images = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ($mime_placeholders)",
				...$supported_types
			)
		);

		// Supported images with alt text
		$images_with_alt = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type IN ($mime_placeholders)
            AND pm.meta_value != ''",
				...$supported_types
			)
		);

		// Supported images without alt text
		$images_without_alt = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type IN ($mime_placeholders)
            AND (pm.meta_value IS NULL OR pm.meta_value = '')",
				...$supported_types
			)
		);

		return array(
			'total'       => $total_images,
			'with_alt'    => $images_with_alt,
			'without_alt' => $images_without_alt
		);
	}


	/**
	 * Manual batch status check and processing (called from admin button)
	 * This simulates what the AJAX method does but for direct calls
	 */
	public function manual_check_and_process_batches() {
		global $wpdb;

		// Get pending/processing batches
		$pending_batches = $wpdb->get_results(
			"SELECT batch_id, status FROM {$this->table_name} WHERE status IN ('pending', 'processing')"
		);

		$updated_count = 0;
		$errors = array();

		foreach ($pending_batches as $batch) {
			try {
				// Check batch status with Claude API
				$status_data = $this->api->get_batch_status($batch->batch_id);

				if ($status_data && isset($status_data['processing_status'])) {
					if ($status_data['processing_status'] === 'ended') {
						// Batch completed - process results
						$results_url = isset($status_data['results_url']) ? $status_data['results_url'] : '';
						if (!empty($results_url)) {
							$success = $this->process_batch_results($batch->batch_id, $results_url);
							if ($success) {
								$updated_count++;
							} else {
								$errors[] = "Failed to process results for batch " . substr($batch->batch_id, -8);
							}
						}
					} elseif ($status_data['processing_status'] === 'failed') {
						// Mark batch as failed
						$error_message = isset($status_data['error']) ? $status_data['error'] : 'Unknown error';
						$wpdb->update(
							$this->table_name,
							array(
								'status' => 'failed',
								'error_message' => $error_message,
								'completed_at' => current_time('mysql')
							),
							array('batch_id' => $batch->batch_id)
						);
						$updated_count++;
					}
					// If status is still 'in_progress' or other, leave it as is
				}
			} catch (Exception $e) {
				$errors[] = "Error checking batch " . substr($batch->batch_id, -8) . ": " . $e->getMessage();
			}
		}

		return array(
			'updated' => $updated_count,
			'errors' => $errors,
			'total_checked' => count($pending_batches)
		);
	}

}