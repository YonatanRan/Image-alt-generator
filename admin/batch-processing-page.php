<?php
/**
 * Combined Batch Processing Page with Tabs
 * Includes both bulk generation and batch job monitoring
 *
 * Note: POST submissions on this page (rr_bulk_generate, rr_check_batches) are
 * handled in RR_Image_Alt_Admin::handle_batch_form_submissions() on admin_init,
 * so wp_safe_redirect() can fire before any HTML output.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!current_user_can('manage_options')) {
	return;
}

// Determine active tab
$active_tab = 'batch-alt-text'; // default tab
if (isset($_GET['tab']) && $_GET['tab'] === 'batch-progress') {
	$active_tab = 'batch-progress';
}

// Show success message if redirected from batch creation
if (isset($_GET['batch_created']) && isset($_GET['count'])) {
	$count = (int) $_GET['count'];
	$batch_count = isset($_GET['batch_count']) ? (int) $_GET['batch_count'] : 1;
	echo '<div class="notice notice-success is-dismissible"><p>';
	echo '<strong>' . esc_html__('Batch submitted successfully!', 'rr-image-alt') . '</strong> ';
	if ($batch_count > 1) {
		echo sprintf(
			esc_html__('Processing %1$d images across %2$d batches in the background. Check the status below.', 'rr-image-alt'),
			$count,
			$batch_count
		);
	} else {
		echo sprintf(
			esc_html__('Processing %1$d images in the background. Check the status below.', 'rr-image-alt'),
			$count
		);
	}
	echo '</p></div>';
}

// Show error message if batch creation failed
if (isset($_GET['batch_failed'])) {
	echo '<div class="notice notice-error is-dismissible"><p>';
	echo esc_html__('Failed to create batch request. Please check your API key and try again.', 'rr-image-alt');
	echo '</p></div>';
}

// Show result of manual "Check Status Now" run
if (isset($_GET['checked'])) {
	$updated_count = isset($_GET['updated']) ? (int) $_GET['updated'] : 0;
	if ($updated_count > 0) {
		echo '<div class="notice notice-success is-dismissible"><p>' .
		     sprintf(esc_html__('Updated %d completed batch(es).', 'rr-image-alt'), $updated_count) .
		     '</p></div>';
	} else {
		echo '<div class="notice notice-info is-dismissible"><p>' .
		     esc_html__('No completed batches found to update. Status is current.', 'rr-image-alt') .
		     '</p></div>';
	}
}

// Get statistics for supported images only
$stats = $this->batch->get_image_statistics();
$total_images = $stats['total'];
$images_with_alt = $stats['with_alt'];
$images_without_alt = $stats['without_alt'];

// Get all media for comparison
global $wpdb;
$all_media_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
$unsupported_count = $all_media_count - $total_images;

// Get settings
$settings = get_option('rr_image_alt_settings');
$api_configured = !empty($settings['api_key']);

// Get recent batches
$batches = $this->batch->get_batches(20);
?>

<div class="wrap">
    <h1>⚡ Batch Processing</h1>

    <p>
		<?php esc_html_e('Bulk generate alt text for multiple images and monitor processing status.', 'rr-image-alt'); ?>
    </p>

    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=rr-image-alt-batch&tab=batch-alt-text')); ?>"
           class="nav-tab <?php echo $active_tab === 'batch-alt-text' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e('Batch Alt Text', 'rr-image-alt'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=rr-image-alt-batch&tab=batch-progress')); ?>"
           class="nav-tab <?php echo $active_tab === 'batch-progress' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e('Batch Progress Tracker', 'rr-image-alt'); ?>
        </a>
    </h2>

    <!-- Tab Content -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-left: none; border-top: none; margin-top: 0;">

		<?php if ($active_tab === 'batch-alt-text'): ?>
            <!-- BATCH ALT TEXT TAB -->

			<?php if ($unsupported_count > 0): ?>
                <div style="background: #e1f5fe; padding: 15px; border-left: 4px solid #039be5; margin: 20px 0;">
                    <p style="margin: 0;">
                        <strong><?php esc_html_e('Note:', 'rr-image-alt'); ?></strong>
						<?php printf(
							esc_html__('Found %1$d supported images (JPG, PNG, GIF, WebP) and %2$d other media files. Only supported images will be processed.', 'rr-image-alt'),
							number_format($total_images),
							number_format($unsupported_count)
						); ?>
                    </p>
                </div>
			<?php endif; ?>

            <h2>🚀 Start New Batch</h2>

			<?php if (!$api_configured): ?>
                <div style="background: #fff3cd; padding: 20px; border: 1px solid #ffc107; border-radius: 5px; margin: 20px 0;">
                    <h3>⚠️ API Key Required</h3>
                    <p>Configure your Claude API key before using batch generation.</p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rr-image-alt')); ?>" class="button button-primary">
							<?php esc_html_e('Configure API Key', 'rr-image-alt'); ?>
                        </a>
                    </p>
                </div>
			<?php else: ?>

                <!-- Statistics -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="background: #e7f3ff; padding: 20px; border: 1px solid #2196f3; border-radius: 5px; text-align: center;">
                        <h4 style="margin: 0; color: #2196f3; font-size: 14px;">Supported Images</h4>
                        <p style="font-size: 48px; font-weight: bold; margin: 10px 0; color: #2196f3;"><?php echo number_format($total_images); ?></p>
                        <p style="margin: 0; color: #666; font-size: 12px;">JPG, PNG, GIF, WebP</p>
                    </div>

                    <div style="background: #e8f5e9; padding: 20px; border: 1px solid #4caf50; border-radius: 5px; text-align: center;">
                        <h4 style="margin: 0; color: #4caf50; font-size: 14px;">With Alt Text</h4>
                        <p style="font-size: 48px; font-weight: bold; margin: 10px 0; color: #4caf50;"><?php echo number_format($images_with_alt); ?></p>
                        <p style="margin: 0; color: #666; font-size: 14px;">
							<?php echo $total_images > 0 ? round(($images_with_alt / $total_images) * 100, 1) : 0; ?>% complete
                        </p>
                    </div>

                    <div style="background: #fff3e0; padding: 20px; border: 1px solid #ff9800; border-radius: 5px; text-align: center;">
                        <h4 style="margin: 0; color: #ff9800; font-size: 14px;">Missing Alt Text</h4>
                        <p style="font-size: 48px; font-weight: bold; margin: 10px 0; color: #ff9800;"><?php echo number_format($images_without_alt); ?></p>
                        <p style="margin: 0; color: #666; font-size: 14px;">
							<?php echo $total_images > 0 ? round(($images_without_alt / $total_images) * 100, 1) : 0; ?>% need alt text
                        </p>
                    </div>
                </div>

				<?php if ($total_images == 0): ?>
                    <div style="background: #f5f5f5; padding: 40px; text-align: center; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                        <p style="font-size: 16px; color: #666; margin: 0;">
                            No supported images found in your media library. Upload some JPG, PNG, GIF, or WebP images first!
                        </p>
                    </div>
				<?php else: ?>

                    <!-- Bulk Generation Form -->
                    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
                        <h3>Select Generation Mode</h3>

                        <form method="post" action="">
							<?php wp_nonce_field('rr_bulk_generate_action', 'rr_bulk_generate_nonce'); ?>

                            <div style="margin: 20px 0;">
                                <label style="display: block; padding: 15px; background: #f9f9f9; border: 2px solid #ddd; border-radius: 5px; margin-bottom: 15px; cursor: pointer;">
                                    <input type="radio" name="generation_mode" value="missing" checked>
                                    <strong style="font-size: 16px; margin-left: 10px;">Generate for Missing Only</strong>
                                    <p style="margin: 10px 0 0 32px; color: #666;">
                                        Only process supported images that don't have alt text (<?php echo number_format($images_without_alt); ?> images).
                                        <br><strong>Recommended:</strong> This won't overwrite existing alt text.
                                    </p>
                                </label>

                                <label style="display: block; padding: 15px; background: #f9f9f9; border: 2px solid #ddd; border-radius: 5px; cursor: pointer;">
                                    <input type="radio" name="generation_mode" value="all">
                                    <strong style="font-size: 16px; margin-left: 10px;">Regenerate All</strong>
                                    <p style="margin: 10px 0 0 32px; color: #666;">
                                        Process all supported images in your media library (<?php echo number_format($total_images); ?> images).
                                        <br><strong>Warning:</strong> This will overwrite existing alt text, including manually-entered descriptions.
                                    </p>
                                </label>
                            </div>

                            <p>
                                <button type="submit" name="rr_bulk_generate" class="button button-primary button-hero"
                                        onclick="return confirm('Are you sure you want to start bulk generation? This will process multiple images and incur API costs.');">
                                    🚀 Start Batch Generation
                                </button>
                            </p>
                        </form>
                    </div>

				<?php endif; ?>
			<?php endif; ?>

		<?php else: ?>
            <!-- BATCH PROGRESS TRACKER TAB -->

            <div style="margin-bottom: 15px;">
                <form method="post" action="" style="display: inline;">
					<?php wp_nonce_field('rr_check_batches_action', 'rr_check_batches_nonce'); ?>
                    <button type="submit" name="rr_check_batches" class="button button-secondary">
                        🔄 Check Status Now
                    </button>
                </form>
                <small style="color: #666; margin-left: 10px;">
                    Status is also checked every minute while admin tabs are open
                </small>
            </div>

			<?php if (empty($batches)): ?>
                <div style="background: #f5f5f5; padding: 40px; text-align: center; border: 1px solid #ccc; border-radius: 5px;">
                    <p style="font-size: 16px; color: #666; margin: 0;">
                        No batch jobs found. <a href="<?php echo esc_url(admin_url('admin.php?page=rr-image-alt-batch&tab=batch-alt-text')); ?>">Create your first batch</a> using the Batch Alt Text tab.
                    </p>
                </div>
			<?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                    <tr>
                        <th>Status</th>
                        <th>Images</th>
                        <th>Results</th>
                        <th>Created</th>
                        <th>Completed</th>
                        <th>Batch ID</th>
                        <th>Model</th>
                    </tr>
                    </thead>
                    <tbody>
					<?php foreach ($batches as $batch): ?>
						<?php
						$attachment_ids = json_decode($batch->attachment_ids, true);
						$image_count = is_array($attachment_ids) ? count($attachment_ids) : 0;

						// Status styling
						$status_colors = array(
							'pending' => '#ff9800',
							'processing' => '#2196f3',
							'completed' => '#4caf50',
							'failed' => '#f44336'
						);
						$status_color = isset($status_colors[$batch->status]) ? $status_colors[$batch->status] : '#666';

						$status_icons = array(
							'pending' => '⏳',
							'processing' => '🔄',
							'completed' => '✅',
							'failed' => '❌'
						);
						$status_icon = isset($status_icons[$batch->status]) ? $status_icons[$batch->status] : '❓';
						?>
                        <tr>
                            <td>
                                    <span style="display: inline-block; background: <?php echo esc_attr($status_color); ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                                        <?php echo esc_html($status_icon); ?> <?php echo esc_html(strtoupper($batch->status)); ?>
                                    </span>
                            </td>
                            <td>
                                <strong><?php echo esc_html($image_count); ?></strong>
                            </td>
                            <td>
								<?php if ($batch->status === 'completed'): ?>
                                    <div style="font-size: 12px;">
										<?php if ($batch->succeeded_count || $batch->errored_count): ?>
                                            <span style="color: #4caf50;">✅ <?php echo (int) $batch->succeeded_count; ?> success</span>
											<?php if ($batch->errored_count > 0): ?>
                                                <br><span style="color: #f44336;">❌ <?php echo (int) $batch->errored_count; ?> errors</span>
											<?php endif; ?>
										<?php else: ?>
                                            <span style="color: #999;">No results</span>
										<?php endif; ?>
                                    </div>
								<?php elseif ($batch->status === 'failed' && !empty($batch->error_message)): ?>
                                    <div style="font-size: 12px; color: #f44336;">
										<?php echo esc_html(substr($batch->error_message, 0, 50)); ?>
										<?php if (strlen($batch->error_message) > 50): ?>...<?php endif; ?>
                                    </div>
								<?php else: ?>
                                    <span style="color: #999; font-style: italic;">Processing...</span>
								<?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 12px;"><?php echo esc_html(date('M j, Y', strtotime($batch->created_at))); ?></span>
                                <br><span style="font-size: 11px; color: #666;"><?php echo esc_html(date('g:i A', strtotime($batch->created_at))); ?></span>
                            </td>
                            <td>
		                        <?php if ($batch->completed_at): ?>
                                    <span style="font-size: 12px;"><?php echo esc_html(date('M j, Y', strtotime($batch->completed_at))); ?></span>
                                    <br><span style="font-size: 11px; color: #666;"><?php echo esc_html(date('g:i A', strtotime($batch->completed_at))); ?></span>
		                        <?php else: ?>
                                    <span style="color: #999;">—</span>
		                        <?php endif; ?>
                            </td>
                            <td>
                                <code style="font-size: 11px;">#<?php echo esc_html(substr($batch->batch_id, -8)); ?></code>
                            </td>

                            <td>
                                <span style="font-size: 12px;"><?php echo esc_html($batch->model); ?></span>
                            </td>
                        </tr>
					<?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 15px; color: #666; font-size: 12px;">
                    Showing last <?php echo count($batches); ?> batches • Status auto-refreshes every minute
                </p>
			<?php endif; ?>

		<?php endif; ?>
    </div>

    <!-- Info Section (shown on both tabs) -->
    <div style="background: #e7f3ff; padding: 20px; border-left: 4px solid #2196f3; margin: 30px 0;">
        <h3 style="margin-top: 0;">💡 How Batch Processing Works</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4>⚡ Fast & Reliable</h4>
                <ul style="margin: 5px 0; font-size: 14px;">
                    <li>Uses Claude's Batch API for efficiency</li>
                    <li>Processes 5-200+ images at once</li>
                    <li>Automatic background processing</li>
                    <li>Real-time status monitoring</li>
                </ul>
            </div>
            <div>
                <h4>💰 Cost Effective</h4>
                <ul style="margin: 5px 0; font-size: 14px;">
                    <li>50% discount on batch API costs</li>
                    <li>Only processes supported image types</li>
                    <li>No duplicate processing of existing alt text</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
    /* Tab styling */
    .nav-tab-wrapper {
        border-bottom: 1px solid #ccc;
        margin: 20px 0 0 0;
        padding: 0;
    }
    .nav-tab {
        font-size: 14px;
    }
</style>