<?php
/**
 * About Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}
?>

<div class="wrap">
    <h1>About Image Alt Generator</h1>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>What This Plugin Does</h2>
        <p>
            <strong>Image Alt Generator</strong> automatically generates descriptive, SEO-friendly
            alt text for your images using Claude AI's advanced vision capabilities. Alt text improves
            accessibility for visually impaired users and helps search engines understand your images.
        </p>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Key Features</h2>
        <ul style="font-size: 15px; line-height: 1.8;">
            <li><strong>✨ Automatic Generation:</strong> Alt text is generated automatically when you upload images</li>
            <li><strong>⚡ Bulk Processing:</strong> Process hundreds of existing images at once using batch API</li>
            <li><strong>🎯 AI-Powered:</strong> Uses Claude 4.5's vision model for accurate, contextual descriptions</li>
            <li><strong>💰 Cost-Effective:</strong> URL-based image processing by default (minimal token usage)</li>
            <li><strong>🔒 Background:</strong> Batches run in the background; status is checked every minute when an admin tab is open</li>
            <li><strong>📊 Tracking:</strong> Monitor all batch jobs and their progress</li>
            <li><strong>⚙️ Flexible:</strong> Choose between different Claude models based on your needs</li>
            <li><strong>🔐 Private Site Support:</strong> Base64 encoding option for non-public sites</li>
        </ul>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>How It Works</h2>

        <h3>Automatic Mode (Single Images)</h3>
        <ol>
            <li>Upload an image to your Media Library</li>
            <li>The plugin automatically sends it to Claude AI</li>
            <li>Claude analyzes the image and generates descriptive alt text</li>
            <li>The alt text is saved to your image metadata</li>
        </ol>

        <h3>Bulk Mode (Multiple Images)</h3>
        <ol>
            <li>Select multiple images in Media Library (List View) or use the Bulk Generate page</li>
            <li>Choose "Generate Alt Text with Claude AI" and apply</li>
            <li>The plugin submits a batch to Claude's Batch API; you can leave the page</li>
            <li>Status is checked every minute when you have an admin tab open</li>
            <li>When complete, all images are automatically updated with their alt text</li>
        </ol>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Getting Started</h2>
        <ol>
            <li><strong><?php esc_html_e('Get a Claude API Key:', 'rr-image-alt'); ?></strong> <?php echo wp_kses(sprintf(__('Visit <a href="%s" target="_blank" rel="noopener noreferrer">Anthropic Console</a> to create an account and get your API key', 'rr-image-alt'), esc_url('https://console.anthropic.com/')), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></li>
            <li><strong><?php esc_html_e('Configure Settings:', 'rr-image-alt'); ?></strong> <?php echo wp_kses(sprintf(__('Go to <a href="%s">Settings</a> and enter your API key', 'rr-image-alt'), esc_url(admin_url('admin.php?page=rr-image-alt'))), array('a' => array('href' => array()))); ?></li>
            <li><strong><?php esc_html_e('Start Generating:', 'rr-image-alt'); ?></strong> <?php esc_html_e('Upload images or use bulk actions; batches run in the background.', 'rr-image-alt'); ?></li>
        </ol>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Cost Considerations</h2>
        <p><strong>URL Mode (Default - Recommended):</strong></p>
        <ul>
            <li>✅ Lower token usage</li>
            <li>✅ Faster processing</li>
            <li>✅ Minimal bandwidth</li>
            <li>⚠️ Requires publicly accessible images</li>
        </ul>

        <p><strong>Base64 Mode (For Private Sites):</strong></p>
        <ul>
            <li>⚠️ Higher token usage (increased cost)</li>
            <li>⚠️ Slower processing</li>
            <li>⚠️ More bandwidth required</li>
            <li>✅ Works with password-protected sites</li>
            <li>✅ Works with local/staging environments</li>
        </ul>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Model Comparison</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>Model</th>
                <th>Best For</th>
                <th>Speed</th>
                <th>Cost</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><strong>Claude Haiku 4.5</strong> (Recommended)</td>
                <td>Most use cases, bulk processing</td>
                <td>⚡⚡⚡ Fastest</td>
                <td>💰 Most economical</td>
            </tr>
            <tr>
                <td><strong>Claude Sonnet 4.5</strong></td>
                <td>Balanced performance</td>
                <td>⚡⚡ Fast</td>
                <td>💰💰 Moderate</td>
            </tr>
            <tr>
                <td><strong>Claude Opus 4.5</strong></td>
                <td>Complex images, high accuracy needs</td>
                <td>⚡ Slower</td>
                <td>💰💰💰 Premium</td>
            </tr>
            </tbody>
        </table>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Plugin Information</h2>
        <ul>
            <li><strong><?php esc_html_e('Version:', 'rr-image-alt'); ?></strong> <?php echo esc_html(RR_IMAGE_ALT_VERSION); ?></li>
            <li><strong>Developer:</strong> Yonatan Ran <yonatan.ran@gmail.com></li>
            <li><strong><?php esc_html_e('Website:', 'rr-image-alt'); ?></strong> <a href="<?php echo esc_url('https://github.com/YonatanRan/Image-alt-generator/'); ?>" target="_blank" rel="noopener noreferrer">https://github.com/YonatanRan/Image-alt-generator/</a></li>
            <li><strong>Support:</strong> For questions or issues, visit the github repo</li>
        </ul>
    </div>

    <div style="background: #e7f3ff; padding: 20px; border: 1px solid #2196f3; border-radius: 5px; margin: 20px 0;">
        <h2>Why Alt Text Matters</h2>
        <ul>
            <li><strong>Accessibility:</strong> Screen readers use alt text to describe images to visually impaired users</li>
            <li><strong>SEO:</strong> Search engines use alt text to understand and index your images</li>
            <li><strong>User Experience:</strong> Alt text displays when images fail to load</li>
            <li><strong>Context:</strong> Provides context for images in social media sharing</li>
        </ul>
    </div>
</div>