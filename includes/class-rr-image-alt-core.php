<?php
/**
 * Core Plugin Class
 *
 * Handles plugin initialization and coordination between components
 */

if (!defined('ABSPATH')) {
    exit;
}

class RR_Image_Alt_Core {

    private $api;
    private $batch;
    private $admin;

    public function __construct() {
        $this->api = new RR_Image_Alt_API();
        $this->batch = new RR_Image_Alt_Batch($this->api);
        $this->admin = new RR_Image_Alt_Admin($this->batch);
    }

    public function init() {
        // Hook into attachment upload for automatic alt text generation
        add_action('add_attachment', array($this, 'generate_alt_text_on_upload'));

        // Initialize admin interface
        $this->admin->init();

        // Initialize batch processing
        $this->batch->init();
    }

	/**
	 * Generate alt text when image is uploaded
	 */
	public function generate_alt_text_on_upload($attachment_id) {
		// Check if it's an image
		if (!wp_attachment_is_image($attachment_id)) {
			return;
		}

		// Check if it's a supported image type
		$mime_type = get_post_mime_type($attachment_id);
		$supported_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');

		if (!in_array($mime_type, $supported_types, true)) {
			error_log('RR Image Alt Generator: Skipping unsupported image type: ' . $mime_type . ' for attachment ID: ' . $attachment_id);
			return;
		}

		// Get settings
		$settings = get_option('rr_image_alt_settings');

		// Check if API key is set
		if (empty($settings['api_key'])) {
			error_log('RR Image Alt Generator: API key not configured');
			return;
		}

		// Get image URL
		$image_url = wp_get_attachment_url($attachment_id);

		if (!$image_url) {
			error_log('RR Image Alt Generator: Could not get image URL for attachment ' . $attachment_id);
			return;
		}

		// Generate alt text using API
		$alt_text = $this->api->generate_alt_text($image_url);

		if ($alt_text) {
			// Update the alt text
			update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
			error_log('RR Image Alt Generator: Successfully generated alt text for attachment ' . $attachment_id);
		}
	}

    /**
     * Plugin activation - create database table
     */
    public static function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rr_alt_batches';
        $charset_collate = $wpdb->get_charset_collate();

	    $sql = "CREATE TABLE $table_name (
	        id bigint(20) NOT NULL AUTO_INCREMENT,
	        batch_id varchar(255) NOT NULL,
	        attachment_ids longtext NOT NULL,
	        model varchar(50) NOT NULL,
	        status varchar(20) NOT NULL DEFAULT 'pending',
	        created_at datetime NOT NULL,
	        completed_at datetime DEFAULT NULL,
	        error_message text DEFAULT NULL,
	        results longtext DEFAULT NULL,
	        succeeded_count int(11) DEFAULT 0,
	        errored_count int(11) DEFAULT 0,
	        PRIMARY KEY (id),
	        KEY batch_id (batch_id),
	        KEY status (status)
    ) $charset_collate;";



	    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Plugin deactivation - clear scheduled cron event
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled('rr_check_batch_status');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rr_check_batch_status');
        }
        wp_clear_scheduled_hook('rr_check_batch_status');
    }
}