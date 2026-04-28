<?php
/**
 * Cost Calculator Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    return;
}

// Get batch handler
$api = new RR_Image_Alt_API();
$batch = new RR_Image_Alt_Batch($api);
$batches = $batch->get_batches(1000); // Get all batches for cost calculation

// Claude API Pricing (as of knowledge cutoff - prices in USD)
// These are approximate prices per million tokens
$pricing = array(
    'claude-haiku-4-5-20251001' => array(
        'input' => 1.00,    // $1 per million input tokens
        'output' => 5.00,   // $5 per million output tokens
        'name' => 'Claude Haiku 4.5'
    ),
    'claude-sonnet-4-5-20250929' => array(
        'input' => 3.00,    // $3 per million input tokens
        'output' => 15.00,  // $15 per million output tokens
        'name' => 'Claude Sonnet 4.5'
    ),
    'claude-opus-4-5-20251101' => array(
        'input' => 15.00,   // $15 per million input tokens
        'output' => 75.00,  // $75 per million output tokens
        'name' => 'Claude Opus 4.5'
    )
);

// Get current settings
$settings = get_option('rr_image_alt_settings');
$current_model = isset($settings['model']) ? $settings['model'] : 'claude-haiku-4-5-20251001';
$use_base64 = isset($settings['use_base64']) ? (bool) $settings['use_base64'] : false;

// Calculate statistics
$total_images = 0;
$completed_images = 0;
$processing_images = 0;
$failed_images = 0;
$total_batches = count($batches);
$completed_batches = 0;

$cost_by_model = array();

foreach ($batches as $batch_item) {
	$attachment_ids = json_decode($batch_item->attachment_ids, true);
	$total_submitted = is_array($attachment_ids) ? count($attachment_ids) : 0;

	// Use succeeded count for completed batches, total for others
	if ($batch_item->status === 'completed' && isset($batch_item->succeeded_count)) {
		$image_count = (int) $batch_item->succeeded_count;
	} else {
		$image_count = $total_submitted;
	}

	$total_images += $image_count;

	if ($batch_item->status === 'completed') {
		$completed_images += $image_count;
		$completed_batches++;
	} elseif ($batch_item->status === 'processing' || $batch_item->status === 'pending') {
		$processing_images += $image_count;
	} else {
		$failed_images += $image_count;
	}
}

// Estimate tokens per image
// URL mode: ~100 tokens input (prompt + image reference), ~30 tokens output (alt text)
// Base64 mode: ~1000-5000 tokens input depending on image size (average ~2500), ~30 tokens output
$estimated_input_tokens_per_image = $use_base64 ? 2500 : 100;
$estimated_output_tokens_per_image = 30;

// Calculate estimated costs
$estimated_cost = 0;
$model_info = isset($pricing[$current_model]) ? $pricing[$current_model] : $pricing['claude-haiku-4-5-20251001'];

// Cost for completed images
$input_cost = ($completed_images * $estimated_input_tokens_per_image / 1000000) * $model_info['input'];
$output_cost = ($completed_images * $estimated_output_tokens_per_image / 1000000) * $model_info['output'];
$estimated_cost = $input_cost + $output_cost;

// Project costs for processing images
$projected_input_cost = ($processing_images * $estimated_input_tokens_per_image / 1000000) * $model_info['input'];
$projected_output_cost = ($processing_images * $estimated_output_tokens_per_image / 1000000) * $model_info['output'];
$projected_cost = $projected_input_cost + $projected_output_cost;

$total_projected_cost = $estimated_cost + $projected_cost;

// Calculate potential savings with URL mode
$savings_if_url = 0;
if ($use_base64 && $completed_images > 0) {
    $url_input_cost = ($completed_images * 100 / 1000000) * $model_info['input'];
    $url_total_cost = $url_input_cost + $output_cost;
    $savings_if_url = $estimated_cost - $url_total_cost;
}
?>

<div class="wrap">
    <h1>💰 Cost Calculator</h1>

    <p>
        This page provides an estimate of your Claude API usage costs based on completed batch jobs.
        Actual costs may vary depending on image complexity and actual token usage.
    </p>

    <div style="background: #fff3cd; padding: 20px; border: 1px solid #ffc107; border-radius: 5px; margin: 20px 0;">
        <h3 style="margin-top: 0;">⚠️ Important Notes</h3>
        <ul style="margin: 5px 0;">
            <li><strong>These are estimates only</strong> - Actual costs may vary based on image size, complexity, and API pricing changes</li>
            <li><strong><?php esc_html_e('Pricing is subject to change', 'rr-image-alt'); ?></strong> <?php echo wp_kses(sprintf(__('Check <a href="%s" target="_blank" rel="noopener noreferrer">Anthropic\'s official pricing page</a> for current rates', 'rr-image-alt'), esc_url('https://www.anthropic.com/pricing')), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></li>
            <li><strong>Token counts are approximate</strong> - Actual token usage depends on image details and response length</li>
            <li><strong>Single image uploads are not tracked</strong> - Only batch jobs are included in these calculations</li>
        </ul>
    </div>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: #e7f3ff; padding: 20px; border: 1px solid #2196f3; border-radius: 5px;">
            <h3 style="margin: 0; color: #2196f3;">Total Images Processed</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #2196f3;"><?php echo number_format($completed_images); ?></p>
            <p style="margin: 0; color: #666; font-size: 14px;">Successfully completed</p>
        </div>

        <div style="background: #fff3e0; padding: 20px; border: 1px solid #ff9800; border-radius: 5px;">
            <h3 style="margin: 0; color: #ff9800;">Processing</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #ff9800;"><?php echo number_format($processing_images); ?></p>
            <p style="margin: 0; color: #666; font-size: 14px;">In progress or pending</p>
        </div>

        <div style="background: #e8f5e9; padding: 20px; border: 1px solid #4caf50; border-radius: 5px;">
            <h3 style="margin: 0; color: #4caf50;">Estimated Cost</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #4caf50;">$<?php echo number_format($estimated_cost, 4); ?></p>
            <p style="margin: 0; color: #666; font-size: 14px;">For completed images</p>
        </div>

        <div style="background: #f3e5f5; padding: 20px; border: 1px solid #9c27b0; border-radius: 5px;">
            <h3 style="margin: 0; color: #9c27b0;">Projected Total</h3>
            <p style="font-size: 36px; font-weight: bold; margin: 10px 0; color: #9c27b0;">$<?php echo number_format($total_projected_cost, 4); ?></p>
            <p style="margin: 0; color: #666; font-size: 14px;">Including processing</p>
        </div>
    </div>

    <!-- Current Configuration -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Current Configuration</h2>
        <table class="wp-list-table widefat fixed striped">
            <tbody>
            <tr>
                <td style="width: 200px;"><strong>Model:</strong></td>
                <td><?php echo esc_html($model_info['name']); ?></td>
            </tr>
            <tr>
                <td><strong>Image Mode:</strong></td>
                <td>
                    <?php if ($use_base64): ?>
                        <span style="color: #ff9800;">Base64 Encoding</span>
                        <small>(~<?php echo number_format($estimated_input_tokens_per_image); ?> input tokens per image)</small>
                    <?php else: ?>
                        <span style="color: #4caf50;">URL Mode</span>
                        <small>(~<?php echo number_format($estimated_input_tokens_per_image); ?> input tokens per image)</small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Input Token Cost:</strong></td>
                <td>$<?php echo number_format($model_info['input'], 2); ?> per million tokens</td>
            </tr>
            <tr>
                <td><strong>Output Token Cost:</strong></td>
                <td>$<?php echo number_format($model_info['output'], 2); ?> per million tokens</td>
            </tr>
            <tr>
                <td><strong>Est. Cost Per Image:</strong></td>
                <td>
                    $<?php
                    $cost_per_image = (($estimated_input_tokens_per_image / 1000000) * $model_info['input']) +
                        (($estimated_output_tokens_per_image / 1000000) * $model_info['output']);
echo number_format($cost_per_image, 6);
?>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <?php if ($savings_if_url > 0): ?>
        <!-- Savings Opportunity -->
        <div style="background: #fff3cd; padding: 20px; border: 1px solid #ffc107; border-radius: 5px; margin: 20px 0;">
            <h2>💡 Potential Savings</h2>
            <p>
                You're currently using Base64 encoding. If your site is publicly accessible and you switched to URL mode,
                you could have saved approximately <strong>$<?php echo number_format($savings_if_url, 4); ?></strong>
                on the <?php echo number_format($completed_images); ?> images already processed!
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rr-image-alt')); ?>" class="button button-primary">
                    <?php esc_html_e('Review Settings', 'rr-image-alt'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <!-- Batch History with Costs -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Batch History with Cost Breakdown</h2>

        <?php if (!empty($batches)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th style="width: 180px;">Date</th>
                    <th style="width: 80px;">Images</th>
                    <th style="width: 150px;">Model</th>
                    <th style="width: 120px;">Status</th>
                    <th style="width: 150px;">Est. Input Cost</th>
                    <th style="width: 150px;">Est. Output Cost</th>
                    <th>Est. Total Cost</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($batches as $batch_item): ?>
                    <?php
$attachment_ids = json_decode($batch_item->attachment_ids, true);
                    $image_count = is_array($attachment_ids) ? count($attachment_ids) : 0;

                    // Estimate cost for this batch
                    if ($batch_item->status === 'completed') {
                        $batch_input_cost = ($image_count * $estimated_input_tokens_per_image / 1000000) * $model_info['input'];
                        $batch_output_cost = ($image_count * $estimated_output_tokens_per_image / 1000000) * $model_info['output'];
                        $batch_total_cost = $batch_input_cost + $batch_output_cost;
                    } else {
                        $batch_input_cost = 0;
                        $batch_output_cost = 0;
                        $batch_total_cost = 0;
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date('M j, Y g:i A', $batch_item->created_at)); ?></td>
                        <td><?php echo intval($image_count); ?></td>
                        <td><small><?php echo esc_html($model_info['name']); ?></small></td>
                        <td>
                            <?php
                            $status_colors = array(
                                'pending' => '#ff9800',
                                'processing' => '#2196f3',
                                'completed' => '#4caf50',
                                'failed' => '#f44336'
                            );
                    $color = isset($status_colors[$batch_item->status]) ? $status_colors[$batch_item->status] : '#999';
                    ?>
                            <span style="color: <?php echo esc_attr($color); ?>; font-weight: bold;">
                                    <?php echo esc_html(ucfirst($batch_item->status)); ?>
                                </span>
                        </td>
                        <td>
                            <?php if ($batch_item->status === 'completed'): ?>
                                $<?php echo number_format($batch_input_cost, 6); ?>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($batch_item->status === 'completed'): ?>
                                $<?php echo number_format($batch_output_cost, 6); ?>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($batch_item->status === 'completed'): ?>
                                <strong>$<?php echo number_format($batch_total_cost, 6); ?></strong>
                            <?php else: ?>
                                <span style="color: #999;">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr style="background: #f0f0f0; font-weight: bold;">
                    <td colspan="4">Total (Completed Only)</td>
                    <td>$<?php echo number_format($input_cost, 6); ?></td>
                    <td>$<?php echo number_format($output_cost, 6); ?></td>
                    <td><strong style="color: #4caf50;">$<?php echo number_format($estimated_cost, 6); ?></strong></td>
                </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #666; padding: 40px;">
                No batch jobs yet. Costs will appear here after you process images.
            </p>
        <?php endif; ?>
    </div>

    <!-- Model Comparison Calculator -->
    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>Model Cost Comparison</h2>
        <p>Compare estimated costs for processing <?php echo number_format($completed_images); ?> images with different models:</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>Model</th>
                <th>URL Mode Cost</th>
                <th>Base64 Mode Cost</th>
                <th>Difference</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pricing as $model_key => $model_pricing): ?>
                <?php
                // URL mode calculation
                $url_input = ($completed_images * 100 / 1000000) * $model_pricing['input'];
                $url_output = ($completed_images * 30 / 1000000) * $model_pricing['output'];
                $url_total = $url_input + $url_output;

                // Base64 mode calculation
                $b64_input = ($completed_images * 2500 / 1000000) * $model_pricing['input'];
                $b64_output = ($completed_images * 30 / 1000000) * $model_pricing['output'];
                $b64_total = $b64_input + $b64_output;

                $difference = $b64_total - $url_total;
                $is_current = ($model_key === $current_model);
                ?>
                <tr <?php if ($is_current) {
                    echo 'style="background: #e7f3ff; font-weight: bold;"';
                } ?>>
                    <td>
                        <?php echo esc_html($model_pricing['name']); ?>
                        <?php if ($is_current): ?>
                            <span style="color: #2196f3; font-size: 12px;">(Current)</span>
                        <?php endif; ?>
                    </td>
                    <td style="color: #4caf50;">$<?php echo number_format($url_total, 4); ?></td>
                    <td style="color: #ff9800;">$<?php echo number_format($b64_total, 4); ?></td>
                    <td style="color: #f44336;">+$<?php echo number_format($difference, 4); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 15px; color: #666; font-size: 14px;">
            <strong>Note:</strong> Base64 mode typically costs 20-25x more than URL mode due to significantly higher token usage.
        </p>
    </div>

    <!-- Cost Optimization Tips -->
    <div style="background: #e7f3ff; padding: 20px; border: 1px solid #2196f3; border-radius: 5px; margin: 20px 0;">
        <h2>💡 Cost Optimization Tips</h2>
        <ol>
            <li>
                <strong>Use URL Mode When Possible:</strong>
                If your WordPress site is publicly accessible, use URL mode instead of Base64 encoding.
                This can reduce costs by up to 95%.
            </li>
            <li>
                <strong>Choose the Right Model:</strong>
                Claude Haiku 4.5 is the most cost-effective and works great for most alt text generation needs.
                Only use Sonnet or Opus if you need higher accuracy for complex images.
            </li>
            <li>
                <strong>Process in Batches:</strong>
                Batch API processing is more efficient than individual requests.
                Select multiple images and process them together.
            </li>
            <li>
                <strong>Review Before Bulk Processing:</strong>
                Check a few sample alt texts first to ensure quality before processing large batches.
            </li>
            <li>
                <strong>Monitor Your Usage:</strong>
                Regularly check this page to track costs and adjust your strategy as needed.
            </li>
        </ol>
    </div>

    <!-- Disclaimer -->
    <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #999; margin: 20px 0;">
        <p style="margin: 0; color: #666; font-size: 13px;">
            <strong>Disclaimer:</strong> These cost estimates are based on approximate token usage and current Claude API pricing
            as of the plugin's release date. Actual costs may vary based on image complexity, response length, and API pricing changes.
            For accurate billing information, always refer to your Anthropic Console dashboard. This plugin does not have access to
            your actual API usage or billing data.
        </p>
    </div>
</div>