<?php
/**
 * Admin Interface Handler
 *
 * Handles all WordPress admin pages and settings
 */

if (!defined('ABSPATH')) {
	exit;
}

class RR_Image_Alt_Admin
{
	private $batch;
	private $option_name = 'rr_image_alt_settings';

	public function __construct($batch)
	{
		$this->batch = $batch;
	}

	public function init()
	{
		// Admin menu
		add_action('admin_menu', array($this, 'add_admin_menu'));

		// Register settings
		add_action('admin_init', array($this, 'register_settings'));

		// Handle batch-page form submissions before any output is sent.
		add_action('admin_init', array($this, 'handle_batch_form_submissions'));
	}

	/**
	 * Handle POSTs from the Batch Processing page.
	 *
	 * Runs on admin_init so wp_safe_redirect() can issue a clean 302 before
	 * any HTML has been streamed to the browser.
	 */
	public function handle_batch_form_submissions()
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		// Bulk generate submission.
		if (isset($_POST['rr_bulk_generate'])) {
			check_admin_referer('rr_bulk_generate_action', 'rr_bulk_generate_nonce');

			$mode = isset($_POST['generation_mode']) && in_array($_POST['generation_mode'], array('all', 'missing'), true)
				? sanitize_text_field(wp_unslash($_POST['generation_mode']))
				: 'missing';

			$batch_ids = $this->batch->create_bulk_generation_request($mode);

			if ($batch_ids) {
				$stats = $this->batch->get_image_statistics();
				$count = ($mode === 'all') ? $stats['total'] : $stats['without_alt'];

				$redirect_url = add_query_arg(
					array(
						'page'          => 'rr-image-alt-batch',
						'tab'           => 'batch-progress',
						'batch_created' => 1,
						'count'         => $count,
						'batch_count'   => count($batch_ids),
					),
					admin_url('admin.php')
				);
			} else {
				$redirect_url = add_query_arg(
					array(
						'page'         => 'rr-image-alt-batch',
						'tab'          => 'batch-alt-text',
						'batch_failed' => 1,
					),
					admin_url('admin.php')
				);
			}

			wp_safe_redirect($redirect_url);
			exit;
		}

		// Manual "Check Status Now" submission.
		if (isset($_POST['rr_check_batches'])) {
			check_admin_referer('rr_check_batches_action', 'rr_check_batches_nonce');

			global $wpdb;
			$table_name = $this->batch->get_table_name();
			$pending = $wpdb->get_results(
				"SELECT batch_id FROM {$table_name} WHERE status IN ('pending', 'processing')"
			);

			$updated_count = 0;
			foreach ($pending as $row) {
				$status_data = $this->batch->get_api()->get_batch_status($row->batch_id);
				if ($status_data && isset($status_data['processing_status']) && $status_data['processing_status'] === 'ended') {
					$results_url = isset($status_data['results_url']) ? $status_data['results_url'] : '';
					if (!empty($results_url)) {
						$success = $this->batch->process_batch_results($row->batch_id, $results_url);
						if ($success) {
							$updated_count++;
						}
					}
				}
			}

			$redirect_url = add_query_arg(
				array(
					'page'    => 'rr-image-alt-batch',
					'tab'     => 'batch-progress',
					'checked' => 1,
					'updated' => $updated_count,
				),
				admin_url('admin.php')
			);

			wp_safe_redirect($redirect_url);
			exit;
		}
	}


	/**
	 * Add admin menu
	 */
	public function add_admin_menu()
	{
		// Main menu page
		add_menu_page(
			__('Image Alt Generator', 'rr-image-alt'),
			__('Alt Generator', 'rr-image-alt'),
			'manage_options',
			'rr-image-alt',
			array($this, 'render_settings_page'),
			'dashicons-images-alt2',
			30
		);

		// Settings submenu (default)
		add_submenu_page(
			'rr-image-alt',
			__('Settings', 'rr-image-alt'),
			__('Settings', 'rr-image-alt'),
			'manage_options',
			'rr-image-alt',
			array($this, 'render_settings_page')
		);

		// Combined Batch Processing submenu
		add_submenu_page(
			'rr-image-alt',
			__('Batch Processing', 'rr-image-alt'),
			__('Batch Processing', 'rr-image-alt'),
			'manage_options',
			'rr-image-alt-batch',
			array($this, 'render_batch_page')
		);

		// Cost Calculator submenu — hidden from the menu but render method preserved.
		// add_submenu_page(
		// 	'rr-image-alt',
		// 	__('Cost Calculator', 'rr-image-alt'),
		// 	__('Cost Calculator', 'rr-image-alt'),
		// 	'manage_options',
		// 	'rr-image-alt-costs',
		// 	array($this, 'render_costs_page')
		// );


		// How It Works submenu
		add_submenu_page(
			'rr-image-alt',
			__('How Image Processing Works', 'rr-image-alt'),
			__('How It Works', 'rr-image-alt'),
			'manage_options',
			'rr-image-alt-how-it-works',
			array($this, 'render_how_it_works_page')
		);

		// About submenu
		add_submenu_page(
			'rr-image-alt',
			__('About', 'rr-image-alt'),
			__('About', 'rr-image-alt'),
			'manage_options',
			'rr-image-alt-about',
			array($this, 'render_about_page')
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings()
	{
		register_setting(
			'rr_image_alt_settings_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array($this, 'sanitize_settings'),
			)
		);

		add_settings_section(
			'rr_image_alt_main_section',
			__('Claude API Configuration', 'rr-image-alt'),
			array($this, 'section_callback'),
			'rr-image-alt-settings'
		);

		add_settings_field(
			'api_key',
			__('Claude API Key', 'rr-image-alt'),
			array($this, 'api_key_callback'),
			'rr-image-alt-settings',
			'rr_image_alt_main_section'
		);

		add_settings_field(
			'model',
			__('Claude Model', 'rr-image-alt'),
			array($this, 'model_callback'),
			'rr-image-alt-settings',
			'rr_image_alt_main_section'
		);

		add_settings_field(
			'custom_prompt',
			__('Custom Prompt', 'rr-image-alt'),
			array($this, 'custom_prompt_callback'),
			'rr-image-alt-settings',
			'rr_image_alt_main_section'
		);
	}

	/**
	 * Sanitize settings
	 */

	public function sanitize_settings($input) {
		$existing = get_option($this->option_name, array());
		if (!is_array($existing)) {
			$existing = array();
		}

		$sanitized = array();

		if (isset($input['api_key'])) {
			$api_key = sanitize_text_field($input['api_key']);

			// Validate API key format
			if (!empty($api_key) && !preg_match('/^sk-ant-api03-/', $api_key)) {
				add_settings_error(
					'rr_image_alt_settings',
					'invalid_api_key',
					__('Invalid API key format. Claude API keys should start with "sk-ant-api03-"', 'rr-image-alt'),
					'error'
				);
				$api_key = '';
			}

			$sanitized['api_key'] = $api_key;
		} else {
			$sanitized['api_key'] = isset($existing['api_key']) ? $existing['api_key'] : '';
		}

		if (isset($input['model'])) {
			$allowed_models = array(
				'claude-haiku-4-5-20251001',
				'claude-sonnet-4-5-20250929',
				'claude-opus-4-5-20251101',
			);
			$sanitized['model'] = in_array($input['model'], $allowed_models, true)
				? $input['model']
				: 'claude-haiku-4-5-20251001';
		} else {
			$sanitized['model'] = isset($existing['model']) ? $existing['model'] : 'claude-haiku-4-5-20251001';
		}

		$sanitized['use_base64'] = isset($input['use_base64']) && $input['use_base64'];

		if (isset($input['custom_prompt'])) {
			$sanitized['custom_prompt'] = sanitize_textarea_field($input['custom_prompt']);
		} else {
			$sanitized['custom_prompt'] = isset($existing['custom_prompt']) ? $existing['custom_prompt'] : '';
		}

		return $sanitized;
	}



	/**
	 * Section callback
	 */
	public function section_callback()
	{
		echo '<p>' . esc_html__('Configure your Claude API settings to enable automatic alt text generation for uploaded images.', 'rr-image-alt') . '</p>';
	}

	/**
	 * API Key field
	 */
	public function api_key_callback()
	{
		$settings = get_option($this->option_name);
		$api_key = isset($settings['api_key']) ? $settings['api_key'] : '';
		?>
		<input type="text"
		       name="<?php echo esc_attr($this->option_name); ?>[api_key]"
		       value="<?php echo esc_attr($api_key); ?>"
		       class="regular-text"
		       placeholder="sk-ant-api03-...">
		<p class="description"><?php echo wp_kses(sprintf(__('Enter your Claude API key from <a href="%s" target="_blank" rel="noopener noreferrer">Anthropic Console</a>', 'rr-image-alt'), esc_url('https://console.anthropic.com/')), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))); ?></p>
		<?php
	}

	/**
	 * Model selection field
	 */
	public function model_callback()
	{
		$settings = get_option($this->option_name);
		$current_model = isset($settings['model']) ? $settings['model'] : 'claude-haiku-4-5-20251001';

		$models = array(
			'claude-haiku-4-5-20251001' => __('Claude Haiku 4.5 (Recommended - Fast & Cost-effective)', 'rr-image-alt'),
			'claude-sonnet-4-5-20250929' => __('Claude Sonnet 4.5 (Balanced)', 'rr-image-alt'),
			'claude-opus-4-5-20251101' => __('Claude Opus 4.5 (Most Capable)', 'rr-image-alt'),
		);
		?>
		<select name="<?php echo esc_attr($this->option_name); ?>[model]">
			<?php foreach ($models as $value => $label): ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($current_model, $value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html__('Select which Claude model to use for generating alt text', 'rr-image-alt'); ?></p>
		<?php
	}

	/**
	 * Base64 encoding checkbox
	 */
	public function use_base64_callback()
	{
		$settings = get_option($this->option_name);
		$use_base64 = isset($settings['use_base64']) ? (bool) $settings['use_base64'] : false;
		?>
		<label>
			<input type="checkbox"
			       id="use_base64"
			       name="<?php echo esc_attr($this->option_name); ?>[use_base64]"
			       value="1"
				<?php checked($use_base64, true); ?>>
			<?php echo esc_html__('Enable Base64 encoding for non-publicly accessible images', 'rr-image-alt'); ?>
		</label>

		<div id="base64-warning" style="display: none; margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
			<strong><?php echo esc_html__('Important Cost Warning:', 'rr-image-alt'); ?></strong>
			<p style="margin: 5px 0;">
				<?php echo esc_html__('Using Base64 encoding will significantly increase your API costs because:', 'rr-image-alt'); ?>
			</p>
			<ul style="margin: 5px 0 5px 20px;">
				<li><strong><?php echo esc_html__('Higher token usage:', 'rr-image-alt'); ?></strong> <?php echo esc_html__('Base64-encoded images consume more tokens than URL-based images', 'rr-image-alt'); ?></li>
				<li><strong><?php echo esc_html__('Increased bandwidth:', 'rr-image-alt'); ?></strong> <?php echo esc_html__('Larger request payloads require more data transfer', 'rr-image-alt'); ?></li>
				<li><strong><?php echo esc_html__('Slower processing:', 'rr-image-alt'); ?></strong> <?php echo esc_html__('Encoding and transmitting larger data takes more time', 'rr-image-alt'); ?></li>
			</ul>
			<p style="margin: 5px 0;">
				<strong><?php echo esc_html__('Only enable this option if:', 'rr-image-alt'); ?></strong>
				<?php echo esc_html__("Your WordPress site is password-protected, behind a firewall, on a local/staging environment, or otherwise not publicly accessible to Claude's servers.", 'rr-image-alt'); ?>
			</p>
		</div>

		<p class="description">
			<strong><?php echo esc_html__('Default (Recommended):', 'rr-image-alt'); ?></strong> <?php echo esc_html__('Images are sent via URL (lower cost, faster processing).', 'rr-image-alt'); ?><br>
			<strong><?php echo esc_html__('When to enable:', 'rr-image-alt'); ?></strong> <?php echo esc_html__('Only if your site is not publicly accessible and Claude cannot fetch images via URL.', 'rr-image-alt'); ?>
		</p>
		<?php
	}

	/**
	 * Custom prompt field
	 */
	public function custom_prompt_callback()
	{
		$settings = get_option($this->option_name);
		$custom_prompt = isset($settings['custom_prompt']) ? $settings['custom_prompt'] : '';
		$default_prompt = __('Describe this image in 10 words or fewer. Respond with only the description, no additional text.', 'rr-image-alt');
		?>
		<textarea
			name="<?php echo esc_attr($this->option_name); ?>[custom_prompt]"
			rows="4"
			class="large-text"
			placeholder="<?php echo esc_attr($default_prompt); ?>"><?php echo esc_textarea($custom_prompt); ?></textarea>
		<p class="description">
			<?php echo esc_html__('Customize the prompt sent to Claude AI. Leave empty to use the default prompt.', 'rr-image-alt'); ?><br>
			<strong><?php echo esc_html__('Default:', 'rr-image-alt'); ?></strong> "<?php echo esc_html($default_prompt); ?>"
		</p>
		<?php
	}

	/**
	 * Render batch processing page (combines bulk generate + batch jobs)
	 */
	public function render_batch_page()
	{
		require_once RR_IMAGE_ALT_PLUGIN_DIR . 'admin/batch-processing-page.php';
	}

	/**
	 * Render pages
	 */
	public function render_settings_page()
	{
		require_once RR_IMAGE_ALT_PLUGIN_DIR . 'admin/settings-page.php';
	}

	public function render_how_it_works_page()
	{
		require_once RR_IMAGE_ALT_PLUGIN_DIR . 'admin/how-it-works-page.php';
	}

	public function render_costs_page()
	{
		require_once RR_IMAGE_ALT_PLUGIN_DIR . 'admin/costs-page.php';
	}

	public function render_about_page()
	{
		require_once RR_IMAGE_ALT_PLUGIN_DIR . 'admin/about-page.php';
	}

}