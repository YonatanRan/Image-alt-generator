
<?php
/**
 * How Processing Works Information Page
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!current_user_can('manage_options')) {
	return;
}
?>

<div class="wrap">
    <h1>🔄 How Image Processing Works</h1>

    <p style="font-size: 16px;">
        This plugin now uses direct API calls for reliable, immediate processing instead of external batch systems.
        Here's how the new system works.
    </p>

    <div style="background: #e7f3ff; padding: 20px; border: 1px solid #2196f3; border-radius: 5px; margin: 20px 0;">
        <h2>The New Processing Flow</h2>
        <ol style="font-size: 15px; line-height: 1.8;">
            <li>
                <strong>You select images</strong> - Choose images from Media Library or use Bulk Generate page
            </li>
            <li>
                <strong>Local batch created</strong> - A processing record is created in your WordPress database
            </li>
            <li>
                <strong>Click to start processing</strong> - You control when processing begins
            </li>
            <li>
                <strong>Images processed immediately</strong> - Each image is sent to Claude API individually
            </li>
            <li>
                <strong>Real-time updates</strong> - Watch progress with live updates and progress bar
            </li>
            <li>
                <strong>Instant results</strong> - Alt text is applied to images as soon as it's generated
            </li>
        </ol>
    </div>

    <div style="background: #e8f5e9; padding: 20px; border: 1px solid #4caf50; border-radius: 5px; margin: 20px 0;">
        <h2>✅ Advantages of Direct Processing</h2>
        <ul style="font-size: 15px; line-height: 1.8;">
            <li><strong>No Waiting:</strong> Images are processed immediately when you click the button</li>
            <li><strong>Real-time Feedback:</strong> See exactly what's happening with live progress updates</li>
            <li><strong>No Dependencies:</strong> Doesn't rely on wp-cron, external batch systems, or polling</li>
            <li><strong>Better Reliability:</strong> Direct API calls are more reliable than batch processing</li>
            <li><strong>Immediate Results:</strong> Alt text appears on your images right away</li>
            <li><strong>Better Error Handling:</strong> See exactly which images succeed or fail</li>
            <li><strong>You Control Timing:</strong> Start processing exactly when you want</li>
        </ul>
    </div>

    <div style="background: #fff3e0; padding: 20px; border: 1px solid #ff9800; border-radius: 5px; margin: 20px 0;">
        <h2>⚡ Processing Speed</h2>
        <p>How fast does processing work now?</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>Number of Images</th>
                <th>Processing Time</th>
                <th>Notes</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>1-10 images</td>
                <td>30-60 seconds</td>
                <td>Very fast, almost instant</td>
            </tr>
            <tr>
                <td>10-50 images</td>
                <td>2-8 minutes</td>
                <td>Processed in chunks of 5</td>
            </tr>
            <tr>
                <td>50-200 images</td>
                <td>8-30 minutes</td>
                <td>Stay on the page during processing</td>
            </tr>
            <tr>
                <td>200+ images</td>
                <td>30+ minutes</td>
                <td>Consider processing in smaller batches</td>
            </tr>
            </tbody>
        </table>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>📊 How to Use the System</h2>

        <h3>From Media Library:</h3>
        <ol>
            <li>Go to <strong>Media → Library</strong> (List View)</li>
            <li>Select checkboxes next to images you want to process</li>
            <li>From <strong>Bulk Actions</strong> dropdown, select <strong>"Generate Alt Text with Claude AI"</strong></li>
            <li>Click <strong>Apply</strong></li>
            <li>Click <strong>Start Processing</strong> when prompted</li>
            <li>Watch the progress and wait for completion</li>
        </ol>

        <h3>From Bulk Generate Page:</h3>
        <ol>
            <li>Go to <a href="<?php echo admin_url('admin.php?page=rr-image-alt-bulk'); ?>">Alt Generator → Bulk Generate</a></li>
            <li>Choose processing mode (Missing Only or Regenerate All)</li>
            <li>Click <strong>Start Bulk Generation</strong></li>
            <li>Click <strong>Start Processing Now</strong></li>
            <li>Watch the live progress bar</li>
            <li>Processing completes automatically</li>
        </ol>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>📋 Monitoring Your Batches</h2>
        <p>You can track all your processing jobs:</p>
        <ul>
            <li>Visit the <a href="<?php echo admin_url('admin.php?page=rr-image-alt-batches'); ?>">Batch Jobs page</a></li>
            <li>See status: Pending, Processing, Completed, or Failed</li>
            <li>View processing times and image counts</li>
            <li>Track which images were processed successfully</li>
        </ul>
    </div>

    <div style="background: #f5f5f5; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin: 20px 0;">
        <h2>🔧 Technical Details</h2>

        <h3>AJAX Processing</h3>
        <p>
            The system uses WordPress AJAX to process images in the background while showing real-time progress.
            This ensures the browser doesn't time out during long processing jobs.
        </p>

        <h3>Chunked Processing</h3>
        <p>
            Images are processed in chunks of 5 at a time to balance speed with reliability.
            This prevents memory issues and provides regular progress updates.
        </p>

        <h3>Direct API Calls</h3>
        <p>
            Each image is sent individually to Claude's standard Messages API (not the Batch API).
            This provides immediate results and better error handling.
        </p>
    </div>

    <div style="background: #fff3cd; padding: 20px; border: 1px solid #ffc107; border-radius: 5px; margin: 20px 0;">
        <h2>💡 Tips for Best Results</h2>
        <ul>
            <li><strong>Stay on the page:</strong> Don't navigate away while processing is running</li>
            <li><strong>Process in smaller batches:</strong> For 200+ images, consider doing multiple smaller batches</li>
            <li><strong>Check your internet:</strong> Stable internet connection ensures reliable processing</li>
            <li><strong>Monitor API usage:</strong> Each image processed uses your Claude API credits</li>
            <li><strong>Test first:</strong> Try a small batch before processing your entire library</li>
        </ul>
    </div>
</div>