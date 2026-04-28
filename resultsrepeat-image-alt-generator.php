<?php
/**
 * Plugin Name: ResultsRepeat Image Alt Generator
 * Plugin URI: https://resultsrepeat.com
 * Description: Automatically generates alt text for images using Claude AI API
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: ResultsRepeat
 * Author URI: https://resultsrepeat.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rr-image-alt
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RR_IMAGE_ALT_VERSION', '1.0.0');
define('RR_IMAGE_ALT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RR_IMAGE_ALT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require core files
require_once RR_IMAGE_ALT_PLUGIN_DIR . 'includes/class-rr-image-alt-core.php';
require_once RR_IMAGE_ALT_PLUGIN_DIR . 'includes/class-rr-image-alt-api.php';
require_once RR_IMAGE_ALT_PLUGIN_DIR . 'includes/class-rr-image-alt-batch.php';
require_once RR_IMAGE_ALT_PLUGIN_DIR . 'includes/class-rr-image-alt-admin.php';

// Initialize the plugin
function rr_image_alt_init() {
    load_plugin_textdomain(
        'rr-image-alt',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    $core = new RR_Image_Alt_Core();
    $core->init();
}
add_action('plugins_loaded', 'rr_image_alt_init');

// Activation hook
register_activation_hook(__FILE__, 'rr_image_alt_activate');

function rr_image_alt_activate( $network_wide ) {
    if ( is_multisite() && $network_wide ) {
        // Network activation: create the table for every existing site.
        $sites = get_sites( array( 'number' => 0 ) );
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            RR_Image_Alt_Core::activate();
            restore_current_blog();
        }
    } else {
        RR_Image_Alt_Core::activate();
    }
}

// When a new site is added to the network, create its batch table.
add_action( 'wp_initialize_site', 'rr_image_alt_new_site_init', 10, 1 );

function rr_image_alt_new_site_init( $new_site ) {
    if ( is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
        switch_to_blog( $new_site->blog_id );
        RR_Image_Alt_Core::activate();
        restore_current_blog();
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, array('RR_Image_Alt_Core', 'deactivate'));