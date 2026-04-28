<?php

/**
 * Claude API Handler
 *
 * Handles all communication with Claude API
 */

if (!defined('ABSPATH')) {
    exit;
}

class RR_Image_Alt_API
{
    private $settings;

    public function __construct()
    {
        $this->settings = get_option('rr_image_alt_settings');
    }

    /**
     * Log messages consistently
     */
    private function log_message($message, $level = 'info')
    {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf('[%s] RR Image Alt Generator: %s', strtoupper($level), $message));
        }
    }


    /**
     * Refresh settings (useful after settings update)
     */
    public function refresh_settings()
    {
        $this->settings = get_option('rr_image_alt_settings');
    }

    /**
     * Generate alt text for a single image
     */
    public function generate_alt_text($image_url)
    {
        $this->refresh_settings();

        if (empty($this->settings['api_key'])) {
            $this->log_message('RR Image Alt Generator: API key not configured');
            return false;
        }

        // Validate image URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            $this->log_message('Invalid image URL: ' . $image_url, 'error');
            return false;
        }


        $model = !empty($this->settings['model']) ? $this->settings['model'] : 'claude-haiku-4-5-20251001';
        $use_base64 = !empty($this->settings['use_base64']);

        // Prepare image content based on setting
        if ($use_base64) {
            $image_data = $this->get_image_base64($image_url);

            if (!$image_data) {
                error_log('RR Image Alt Generator: Failed to get image data from URL: ' . $image_url);
                return false;
            }

            $image_content = array(
                'type' => 'image',
                'source' => array(
                    'type' => 'base64',
                    'media_type' => $image_data['mime_type'],
                    'data' => $image_data['base64']
                )
            );
        } else {
            $image_content = array(
                'type' => 'image',
                'source' => array(
                    'type' => 'url',
                    'url' => $image_url
                )
            );
        }

        // Prepare API request
        $body = array(
            'model' => $model,
            'max_tokens' => 100,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        $image_content,
                        array(
                            'type' => 'text',
                            'text' => $this->get_prompt()  // Use custom prompt
                        )
                    )
                )
            )
        );

        // Make API request
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->settings['api_key'],
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        // Check for errors
        if (is_wp_error($response)) {
            error_log('RR Image Alt Generator: API request failed - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('RR Image Alt Generator: API returned error code ' . $response_code . ' - ' . $response_body);
            return false;
        }

        // Parse response
        $data = json_decode($response_body, true);

        if (isset($data['content'][0]['text'])) {
            return trim($data['content'][0]['text']);
        }

        error_log('RR Image Alt Generator: Unexpected API response format');
        return false;
    }

    /**
     * Get the prompt (public method for batch class)
     */
    public function get_generation_prompt()
    {
        return $this->get_prompt();
    }

    /**
     * Create batch request
     */
    public function create_batch_request($requests_array)
    {
        $this->refresh_settings();

        if (empty($this->settings['api_key'])) {
            error_log('RR Image Alt Generator: API key not configured');
            return false;
        }

        // Make batch API request
        $response = wp_remote_post('https://api.anthropic.com/v1/messages/batches', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->settings['api_key'],
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode(array('requests' => $requests_array)),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            error_log('RR Image Alt Generator: Batch request failed - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            error_log('RR Image Alt Generator: Batch API returned error code ' . $response_code . ' - ' . $response_body);
            return false;
        }

        $data = json_decode($response_body, true);

        if (!isset($data['id'])) {
            error_log('RR Image Alt Generator: Invalid batch response format');
            return false;
        }

        return $data['id'];
    }

    /**
     * Fetch batch results from results URL
     */
    public function fetch_batch_results($results_url)
    {
        $this->refresh_settings();

        if (empty($this->settings['api_key'])) {
            error_log('RR Image Alt Generator: API key not configured');
            return false;
        }

        $response = wp_remote_get($results_url, array(
            'headers' => array(
                'x-api-key' => $this->settings['api_key'],
                'anthropic-version' => '2023-06-01'
            ),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            error_log('RR Image Alt Generator: Failed to fetch batch results - ' . $response->get_error_message());
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Get image as base64
     */
    public function get_image_base64($image_url)
    {
        $response = wp_remote_get($image_url, array('timeout' => 30));

        if (is_wp_error($response)) {
            return false;
        }

        $image_data = wp_remote_retrieve_body($response);
        $mime_type = wp_remote_retrieve_header($response, 'content-type');

        // Validate mime type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($mime_type, $allowed_types)) {
            // Try to detect from image data
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->buffer($image_data);
        }

        if (!in_array($mime_type, $allowed_types)) {
            error_log('RR Image Alt Generator: Unsupported image type: ' . $mime_type);
            return false;
        }

        return array(
            'base64' => base64_encode($image_data),
            'mime_type' => $mime_type
        );
    }

    /**
     * Get the prompt to use for alt text generation
     */
    private function get_prompt()
    {
        $this->refresh_settings();

        $custom_prompt = isset($this->settings['custom_prompt']) ? trim($this->settings['custom_prompt']) : '';
        $default_prompt = 'Describe this image in 10 words or fewer. Respond with only the description, no additional text.';

        return !empty($custom_prompt) ? $custom_prompt : $default_prompt;
    }

	/**
	 * Prepare image content for API request
	 */
	public function prepare_image_content($image_url) {
		$this->refresh_settings();

		// Validate that this looks like an image URL
		$path_info = pathinfo(parse_url($image_url, PHP_URL_PATH));
		$extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';
		$supported_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');

		if (!in_array($extension, $supported_extensions, true)) {
			error_log('RR Image Alt Generator: Unsupported file extension: ' . $extension . ' for URL: ' . $image_url);
			return false;
		}

		$use_base64 = !empty($this->settings['use_base64']);

		if ($use_base64) {
			$image_data = $this->get_image_base64($image_url);

			if (!$image_data) {
				return false;
			}

			return array(
				'type' => 'image',
				'source' => array(
					'type' => 'base64',
					'media_type' => $image_data['mime_type'],
					'data' => $image_data['base64']
				)
			);
		} else {
			return array(
				'type' => 'image',
				'source' => array(
					'type' => 'url',
					'url' => $image_url
				)
			);
		}
	}

    /**
     * Get batch status
     */
    public function get_batch_status($batch_id)
    {
        $this->refresh_settings();

        if (empty($this->settings['api_key'])) {
            error_log('RR Image Alt Generator: API key not configured');
            return false;
        }

        if (empty($batch_id)) {
            error_log('RR Image Alt Generator: Invalid batch ID for status check');
            return false;
        }

        $response = wp_remote_get('https://api.anthropic.com/v1/messages/batches/' . $batch_id, array(
            'headers' => array(
                'x-api-key' => $this->settings['api_key'],
                'anthropic-version' => '2023-06-01'
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('RR Image Alt Generator: Failed to get batch status - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 200) {
            error_log('RR Image Alt Generator: Batch status API returned error code ' . $response_code . ' - ' . $response_body);
            return false;
        }

        return json_decode($response_body, true);
    }
}
