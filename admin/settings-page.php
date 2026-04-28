<?php
/**
 * Settings Page Template with Tabs
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

// Check if settings were saved
if (isset($_GET['settings-updated'])) {
    add_settings_error(
        'rr_image_alt_messages',
        'rr_image_alt_message',
        __('Settings saved.', 'rr-image-alt'),
        'updated'
    );
}

settings_errors('rr_image_alt_messages');

// Get current tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

// Get settings for security status
$settings = get_option('rr_image_alt_settings');
$has_api_key = !empty($settings['api_key']);
$is_https = is_ssl();
$settings_page_url = esc_url(admin_url('admin.php?page=rr-image-alt&tab=settings'));
$how_it_works_url = esc_url(admin_url('admin.php?page=rr-image-alt-how-it-works'));
$bulk_url = esc_url(admin_url('admin.php?page=rr-image-alt-bulk'));
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url(admin_url('admin.php?page=rr-image-alt&tab=settings')); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Settings', 'rr-image-alt'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=rr-image-alt&tab=quick-start')); ?>" class="nav-tab <?php echo $active_tab === 'quick-start' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Quick Start Guide', 'rr-image-alt'); ?>
        </a>
    </h2>

    <!-- Tab Content -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-left: none; border-top: none; margin-top: 0;">

        <?php if ($active_tab === 'settings'): ?>
            <!-- Settings Tab -->
            <form action="options.php" method="post">
                <?php
                settings_fields('rr_image_alt_settings_group');
                do_settings_sections('rr-image-alt-settings');
                submit_button(__('Save Settings', 'rr-image-alt'));
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Security Status', 'rr-image-alt'); ?></h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: <?php echo $is_https ? '#e8f5e9' : '#ffebee'; ?>; padding: 15px; border-radius: 5px; border: 2px solid <?php echo $is_https ? '#4caf50' : '#f44336'; ?>;">
                    <h3 style="margin: 0; font-size: 14px; color: <?php echo $is_https ? '#4caf50' : '#f44336'; ?>;">
                        <?php echo $is_https ? '✅' : '❌'; ?> HTTPS
                    </h3>
                    <p style="margin: 5px 0 0 0; font-size: 13px;">
                        <?php echo $is_https ? esc_html__('Your site uses HTTPS', 'rr-image-alt') : esc_html__('Your site should use HTTPS', 'rr-image-alt'); ?>
                    </p>
                </div>

                <div style="background: <?php echo $has_api_key ? '#e8f5e9' : '#ffebee'; ?>; padding: 15px; border-radius: 5px; border: 2px solid <?php echo $has_api_key ? '#4caf50' : '#f44336'; ?>;">
                    <h3 style="margin: 0; font-size: 14px; color: <?php echo $has_api_key ? '#4caf50' : '#f44336'; ?>;">
                        <?php echo $has_api_key ? '✅' : '❌'; ?> <?php esc_html_e('API Key', 'rr-image-alt'); ?>
                    </h3>
                    <p style="margin: 5px 0 0 0; font-size: 13px;">
                        <?php echo $has_api_key ? esc_html__('API key configured', 'rr-image-alt') : esc_html__('API key not configured', 'rr-image-alt'); ?>
                    </p>
                </div>
            </div>

        <?php elseif ($active_tab === 'quick-start'): ?>
            <!-- Quick Start Guide Tab -->

            <h2 style="margin-top: 0;">🚀 Quick Start Guide</h2>
            <p style="font-size: 16px;">
                Welcome! Follow these steps to get started with automatic alt text generation for your images.
            </p>

            <div style="background: #e7f3ff; padding: 20px; border-left: 4px solid #2196f3; margin: 20px 0;">
                <h3 style="margin-top: 0;">Step 1: Get Your Claude API Key</h3>
                <ol style="margin: 10px 0;">
                    <li>Visit <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a></li>
                    <li>Create an account or sign in</li>
                    <li>Go to API Keys section</li>
                    <li>Create a new API key</li>
                    <li>Copy the API key (starts with "sk-ant-api03-")</li>
                </ol>
                <p>
                    <a href="<?php echo $settings_page_url; ?>" class="button button-primary">
                        <?php esc_html_e('Go to Settings to Enter API Key', 'rr-image-alt'); ?>
                    </a>
                </p>
            </div>

            <div style="background: #fff3e0; padding: 20px; border-left: 4px solid #ff9800; margin: 20px 0;">
                <h3 style="margin-top: 0;">Step 2: Choose Your Model</h3>
                <p>Select which Claude model to use for generating alt text:</p>
                <table class="wp-list-table widefat fixed striped" style="margin: 15px 0;">
                    <thead>
                    <tr>
                        <th>Model</th>
                        <th>Best For</th>
                        <th>Cost</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr style="background: #e8f5e9;">
                        <td><strong>Claude Haiku 4.5</strong> ⭐ Recommended</td>
                        <td>Most use cases, bulk processing</td>
                        <td>💰 Most economical</td>
                    </tr>
                    <tr>
                        <td><strong>Claude Sonnet 4.5</strong></td>
                        <td>Balanced performance</td>
                        <td>💰💰 Moderate</td>
                    </tr>
                    <tr>
                        <td><strong>Claude Opus 4.5</strong></td>
                        <td>Complex images, highest accuracy</td>
                        <td>💰💰💰 Premium</td>
                    </tr>
                    </tbody>
                </table>
                <p>
                    <strong>Recommendation:</strong> Start with Claude Haiku 4.5 - it's fast, accurate, and cost-effective for alt text generation.
                </p>
            </div>

            <div style="background: #e8f5e9; padding: 20px; border-left: 4px solid #4caf50; margin: 20px 0;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Step 3: Understand How Batches Work', 'rr-image-alt'); ?></h3>
                <p><?php esc_html_e('When you use bulk generation:', 'rr-image-alt'); ?></p>
                <ul style="margin: 10px 0;">
                    <li>✅ <?php esc_html_e('Batch requests are submitted to Claude\'s API', 'rr-image-alt'); ?></li>
                    <li>✅ <?php esc_html_e('The plugin automatically checks status every minute', 'rr-image-alt'); ?></li>
                    <li>✅ <?php esc_html_e('When complete, all images are updated automatically', 'rr-image-alt'); ?></li>
                    <li>✅ <?php esc_html_e('No webhook or external setup needed!', 'rr-image-alt'); ?></li>
                </ul>
                <p>
                    <a href="<?php echo $how_it_works_url; ?>" class="button button-secondary">
                        <?php esc_html_e('Learn How Batch Processing Works', 'rr-image-alt'); ?>
                    </a>
                </p>
            </div>

            <hr>

            <h2>How to Use the Plugin</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">

                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #2196f3;">📤 Automatic on Upload</h3>
                    <p>The easiest way - just upload images normally:</p>
                    <ol>
                        <li>Go to Media → Add New</li>
                        <li>Upload your image</li>
                        <li>Alt text is generated automatically!</li>
                    </ol>
                    <p style="color: #666; font-size: 13px;">
                        <strong>Perfect for:</strong> New images as you add them
                    </p>
                </div>

                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #ff9800;">⚡ Bulk Selection</h3>
                    <p>Process multiple selected images:</p>
                    <ol>
                        <li>Go to Media → Library (List View)</li>
                        <li>Select specific images</li>
                        <li>Bulk Actions → "Generate Alt Text with Claude AI"</li>
                        <li>Click Apply</li>
                    </ol>
                    <p style="color: #666; font-size: 13px;">
                        <strong>Perfect for:</strong> Specific groups of images
                    </p>
                </div>

                <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #4caf50;">🚀 Bulk Generate</h3>
                    <p>Process your entire media library:</p>
                    <ol>
                        <li><?php echo wp_kses(sprintf(__('Go to <a href="%s">Bulk Generate</a>', 'rr-image-alt'), $bulk_url), array('a' => array('href' => array()))); ?></li>
                        <li>Choose "Missing Only" or "Regenerate All"</li>
                        <li>Click "Start Bulk Generation"</li>
                        <li><?php esc_html_e('The plugin checks status every minute and updates images when ready', 'rr-image-alt'); ?></li>
                    </ol>
                    <p style="color: #666; font-size: 13px;">
                        <strong>Perfect for:</strong> Large media libraries
                    </p>
                </div>

            </div>

            <hr>

            <h2>💡 Best Practices</h2>

            <div style="columns: 2; column-gap: 30px;">
                <div style="break-inside: avoid; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;">✅ Do This</h3>
                    <ul>
                        <li>Start with Claude Haiku 4.5 model</li>
                        <li>Test on a few images first</li>
                        <li>Use "Missing Only" mode for bulk generation</li>
                        <li><?php esc_html_e('Ensure WordPress cron can run (visit site or use server cron)', 'rr-image-alt'); ?></li>
                    </ul>
                </div>

                <div style="break-inside: avoid; margin-bottom: 20px;">
                    <h3 style="margin-top: 0;">❌ Avoid This</h3>
                    <ul>
                        <li>Don't use "Regenerate All" without testing first</li>
                        <li>Don't run multiple bulk jobs simultaneously</li>
                        <li>Don't forget to backup before large operations</li>
                    </ul>
                </div>
            </div>

            <hr>

            <h2>Need Help?</h2>

            <div style="background: #f5f5f5; padding: 20px; border-radius: 5px;">
                <p><strong>Common Issues:</strong></p>
                <ul>
                    <li>
                        <strong>Alt text not generating?</strong>
                        <?php echo wp_kses(sprintf(__('Check that your API key is correct on the <a href="%s">Settings tab</a>', 'rr-image-alt'), $settings_page_url), array('a' => array('href' => array()))); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Batch not completing?', 'rr-image-alt'); ?></strong>
                        <?php echo wp_kses(sprintf(__('See <a href="%s">How It Works</a> for polling and cron tips', 'rr-image-alt'), $how_it_works_url), array('a' => array('href' => array()))); ?>
                    </li>
                </ul>

                <p style="margin-top: 20px;">
                    <strong><?php esc_html_e('Still need help?', 'rr-image-alt'); ?></strong> <?php echo wp_kses(sprintf(__('Visit <a href="%s" target="_blank" rel="noopener noreferrer">https://github.com/YonatanRan/Image-alt-generator/</a> for support.', 'rr-image-alt'), esc_url('https://github.com/YonatanRan/Image-alt-generator/')), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?>
                </p>
            </div>

            <div style="background: #e7f3ff; padding: 20px; border-radius: 5px; margin-top: 20px; text-align: center;">
                <h3 style="margin-top: 0;">Ready to Get Started?</h3>
                <p style="font-size: 16px;">Configure your API key and start generating alt text!</p>
                <p>
                    <a href="<?php echo $settings_page_url; ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Go to Settings', 'rr-image-alt'); ?>
                    </a>
                </p>
            </div>

        <?php endif; ?>

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