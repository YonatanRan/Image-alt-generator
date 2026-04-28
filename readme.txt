=== Image Alt Generator ===

Contributors: Yonatan Ran
Tags: alt text, accessibility, images, claude, ai, seo, media library
Requires at least: 5.9
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates descriptive alt text for images using Claude AI. Improves accessibility and SEO.

== Description ==

 Image Alt Generator uses Claude AI's vision capabilities to automatically generate accurate, concise alt text for your images. Alt text helps screen readers describe images to visually impaired users and helps search engines understand your content.

**Features:**

* **Automatic generation** – Alt text is generated when you upload images
* **Bulk processing** – Process many images at once via the Media Library bulk actions
* **Claude AI** – Uses Claude 4.5 models (Haiku, Sonnet, or Opus) for accurate descriptions
* **Cost-effective** – URL-based image sending by default (minimal token usage)
* **Automatic polling** – Plugin checks batch status every minute via WordPress cron; no webhook setup needed
* **Batch tracking** – Monitor all batch jobs and their status
* **Custom prompts** – Optional custom prompt for alt text generation
* **Private sites** – Base64 encoding option for non-public sites

**Requirements:**

* A Claude API key from Anthropic (https://console.anthropic.com/)
* No webhook or external setup needed—batch status is checked automatically every minute

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/-image-alt-generator/` or install through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen.
3. Go to **Alt Generator → Settings** and enter your Claude API key.
4. Upload images or use **Media → Library** bulk action "Generate Alt Text with Claude AI". Batch status is checked automatically every minute.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. Get a Claude API key from https://console.anthropic.com/ and enter it in Alt Generator → Settings.

= Is bulk processing free? =

Claude API usage is billed by Anthropic. The plugin does not charge you; you pay Anthropic for API usage. Check the Cost Calculator page in the plugin for estimates.

== Screenshots ==

1. Settings page – API key and model configuration
2. Bulk Generate – Process many images at once
3. Batch Jobs – Monitor batch processing status

== Changelog ==

= 1.0.0 =
* Initial release.
* Automatic alt text on upload.
* Bulk generation via Media Library and Bulk Generate page.
* Automatic polling (WordPress cron) for batch status—no webhook required.
* Cost calculator and batch history.

= 1.0.1 =
Removed company name


== Upgrade Notice ==

= 1.0.0 =
Initial release of Image Alt Generator.

= 1.0.1 =
Removed company name.
