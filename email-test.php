<?php
/**
 * Enhanced Email Testing Script for Certificate Generator
 *
 * This script helps diagnose email sending issues by providing detailed error reporting
 * and testing different email configurations. Specifically designed to catch 'silent failures'
 * where wp_mail() returns true but emails aren't delivered.
 *
 * Usage: Access this file directly in your browser after adding it to your plugin directory.
 * IMPORTANT: Delete this file after troubleshooting is complete for security reasons.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Enable WordPress debug mode for this session
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// Security check - only allow admin users
// if (!current_user_can('manage_options')) {
//     wp_die('Unauthorized access');
// }

// Include necessary files
require_once(plugin_dir_path(__FILE__) . 'includes/email-functions.php');
require_once(plugin_dir_path(__FILE__) . 'includes/email-log.php');

// Set up variables
$test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
$test_name = isset($_POST['test_name']) ? sanitize_text_field($_POST['test_name']) : 'Test User';
$test_subject = isset($_POST['test_subject']) ? sanitize_text_field($_POST['test_subject']) : 'Certificate Generator Test Email';
$test_message = isset($_POST['test_message']) ? sanitize_textarea_field($_POST['test_message']) : "This is a test email from Certificate Generator to verify email functionality.\n\nIf you received this email, it means the email system is working correctly.";
$test_method = isset($_POST['test_method']) ? sanitize_text_field($_POST['test_method']) : 'wp_mail';

// Get email status
$email_status = certificate_generator_get_email_status();

// Process form submission
$result = null;
$error_details = '';
$phpmailer_errors = null;
$mail_debug_info = array();
$smtp_debug_output = '';

if (isset($_POST['send_test']) && !empty($test_email)) {
    // Enhanced error capturing for PHPMailer
    add_action('wp_mail_failed', function($wp_error) use (&$phpmailer_errors) {
        $phpmailer_errors = $wp_error;
    });
    
    // Capture PHPMailer debug information
    add_action('phpmailer_init', function($phpmailer) use (&$mail_debug_info, &$smtp_debug_output) {
        // Enable SMTP debug output
        $phpmailer->SMTPDebug = 2;
        $phpmailer->Debugoutput = function($str, $level) use (&$smtp_debug_output) {
            $smtp_debug_output .= "Level $level: $str\n";
        };
        
        // Capture mail configuration
        $mail_debug_info['mailer'] = $phpmailer->Mailer;
        $mail_debug_info['host'] = $phpmailer->Host;
        $mail_debug_info['port'] = $phpmailer->Port;
        $mail_debug_info['smtp_secure'] = $phpmailer->SMTPSecure;
        $mail_debug_info['smtp_auth'] = $phpmailer->SMTPAuth;
        $mail_debug_info['username'] = $phpmailer->Username;
        $mail_debug_info['from'] = $phpmailer->From;
        $mail_debug_info['from_name'] = $phpmailer->FromName;
    });
    
    // Capture PHP errors
    $old_error_reporting = error_reporting(E_ALL);
    $old_display_errors = ini_get('display_errors');
    ini_set('display_errors', 1);
    
    // Start output buffering to capture any errors
    ob_start();
    
    // Set up headers
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // Format HTML message
    $html_message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
    $html_message .= '<h1 style="color: #2c3e50; margin-bottom: 20px;">Certificate Generator Test Email</h1>';
    $html_message .= '<div style="line-height: 1.6; color: #333;">' . nl2br(esc_html($test_message)) . '</div>';
    $html_message .= '</div>';
    
    // Send test email based on selected method
    switch ($test_method) {
        case 'wp_mail':
            // Use standard wp_mail function
            $result = wp_mail($test_email, $test_subject, $html_message, $headers);
            break;
            
        case 'fallback':
            // Use the plugin's fallback system
            $result = certificate_generator_send_fallback_email($test_email, $test_subject, $html_message);
            break;
            
        case 'custom':
            // Use the plugin's custom email function
            $result = certificate_generator_send_custom_email($test_email, $test_subject, $test_message, $test_name);
            break;
    }
    
    // Capture any output/errors
    $error_output = ob_get_clean();
    
    // Restore error settings
    error_reporting($old_error_reporting);
    ini_set('display_errors', $old_display_errors);
    
    // Collect detailed error and debug information
    $error_details = "<strong>Detailed Diagnostic Information:</strong><br>";
    
    // Always show mail configuration
    if (!empty($mail_debug_info)) {
        $error_details .= "<strong>Mail Configuration:</strong><br>";
        $error_details .= "Mailer: " . esc_html($mail_debug_info['mailer'] ?? 'Unknown') . "<br>";
        $error_details .= "Host: " . esc_html($mail_debug_info['host'] ?? 'Not set') . "<br>";
        $error_details .= "Port: " . esc_html($mail_debug_info['port'] ?? 'Not set') . "<br>";
        $error_details .= "SMTP Secure: " . esc_html($mail_debug_info['smtp_secure'] ?? 'Not set') . "<br>";
        $error_details .= "SMTP Auth: " . ($mail_debug_info['smtp_auth'] ? 'Yes' : 'No') . "<br>";
        $error_details .= "Username: " . esc_html($mail_debug_info['username'] ?? 'Not set') . "<br>";
        $error_details .= "From: " . esc_html($mail_debug_info['from'] ?? 'Not set') . "<br>";
        $error_details .= "From Name: " . esc_html($mail_debug_info['from_name'] ?? 'Not set') . "<br><br>";
    }
    
    // Show SMTP debug output if available
    if (!empty($smtp_debug_output)) {
        $error_details .= "<strong>SMTP Debug Output:</strong><br>";
        $error_details .= "<pre style='background:#f5f5f5;padding:10px;border-radius:4px;overflow-x:auto;'>" . esc_html($smtp_debug_output) . "</pre><br>";
    }
    
    if (!$result) {
        $error_details .= "<strong>wp_mail() returned FALSE - Email sending failed:</strong><br>";
        
        // Check for PHPMailer errors
        if (!empty($phpmailer_errors) && is_wp_error($phpmailer_errors)) {
            $error_details .= "<strong>PHPMailer Error:</strong> " . esc_html($phpmailer_errors->get_error_message()) . "<br>";
        }
        
        // Check for PHP errors
        if (!empty($error_output)) {
            $error_details .= "<strong>PHP Output/Errors:</strong><br>" . nl2br(esc_html($error_output)) . "<br>";
        }
        
        // Get last PHP error
        $last_error = error_get_last();
        if ($last_error) {
            $error_details .= "<strong>Last PHP Error:</strong><br>" . nl2br(esc_html(print_r($last_error, true))) . "<br>";
        }
        
        // Check global PHPMailer object
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $error_details .= "<strong>Global PHPMailer ErrorInfo:</strong> " . esc_html($phpmailer->ErrorInfo) . "<br>";
        }
    } else {
        $error_details .= "<strong>wp_mail() returned TRUE - Email was accepted for delivery</strong><br>";
        $error_details .= "<em>Note: This means WordPress successfully handed the email to the mail system, but it doesn't guarantee delivery to the recipient's inbox. The email could still be:</em><br>";
        $error_details .= "• Rejected by the recipient's mail server<br>";
        $error_details .= "• Marked as spam/junk<br>";
        $error_details .= "• Blocked by email filters<br>";
        $error_details .= "• Lost due to server configuration issues<br><br>";
        
        // Additional checks for silent failures
        if (empty($mail_debug_info['host']) || $mail_debug_info['mailer'] === 'mail') {
            $error_details .= "<strong>⚠️ Potential Issue Detected:</strong><br>";
            $error_details .= "You're using PHP's mail() function instead of SMTP. This often causes delivery issues on shared hosting.<br>";
            $error_details .= "<strong>Recommendation:</strong> Install and configure WP Mail SMTP plugin with proper SMTP settings.<br><br>";
        }
    }
}

// Get email logs for display
$email_logs = certificate_generator_get_email_logs(array('limit' => 10));

// HTML output
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Generator - Email Test</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .container { display: flex; flex-wrap: wrap; gap: 20px; }
        .panel { flex: 1; min-width: 300px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
        .success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .error { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .info { background-color: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        form label { display: block; margin-bottom: 5px; font-weight: bold; }
        form input[type="text"], form input[type="email"], form textarea, form select { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        form textarea { height: 100px; }
        button { background-color: #3498db; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #2980b9; }
        .code { font-family: monospace; background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Certificate Generator - Email Test</h1>
    
    <div class="info">
        <p><strong>Warning:</strong> This is a diagnostic tool. Delete this file after troubleshooting for security reasons.</p>
    </div>
    
    <div class="container">
        <div class="panel">
            <h2>Email Configuration Status</h2>
            <table>
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Delivery Method</td>
                    <td><?php echo esc_html($email_status['method']); ?></td>
                </tr>
                <tr>
                    <td>From Email</td>
                    <td><?php echo esc_html($email_status['from_email']); ?></td>
                </tr>
                <tr>
                    <td>From Name</td>
                    <td><?php echo esc_html($email_status['from_name']); ?></td>
                </tr>
                <tr>
                    <td>WP Mail SMTP Active</td>
                    <td><?php echo $email_status['smtp_configured'] ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td>Fallback System Active</td>
                    <td><?php echo $email_status['fallback_active'] ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td>WordPress Version</td>
                    <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><?php echo esc_html(phpversion()); ?></td>
                </tr>
                <tr>
                    <td>Server Software</td>
                    <td><?php echo esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td>
                </tr>
            </table>
            
            <h3>PHP Mail Configuration</h3>
            <div class="code">
                <?php 
                $mail_config = ini_get('sendmail_path') ? 'Sendmail Path: ' . ini_get('sendmail_path') : 'PHP mail() function';
                echo esc_html($mail_config);
                ?>
            </div>
        </div>
        
        <div class="panel">
            <h2>Send Test Email</h2>
            <form method="post" action="">
                <label for="test_email">Recipient Email:</label>
                <input type="email" id="test_email" name="test_email" value="<?php echo esc_attr($test_email); ?>" required>
                
                <label for="test_name">Recipient Name:</label>
                <input type="text" id="test_name" name="test_name" value="<?php echo esc_attr($test_name); ?>">
                
                <label for="test_subject">Subject:</label>
                <input type="text" id="test_subject" name="test_subject" value="<?php echo esc_attr($test_subject); ?>">
                
                <label for="test_message">Message:</label>
                <textarea id="test_message" name="test_message"><?php echo esc_textarea($test_message); ?></textarea>
                
                <label for="test_method">Sending Method:</label>
                <select id="test_method" name="test_method">
                    <option value="wp_mail" <?php selected($test_method, 'wp_mail'); ?>>WordPress wp_mail()</option>
                    <option value="fallback" <?php selected($test_method, 'fallback'); ?>>Plugin Fallback System</option>
                    <option value="custom" <?php selected($test_method, 'custom'); ?>>Plugin Custom Email</option>
                </select>
                
                <button type="submit" name="send_test">Send Test Email</button>
            </form>
            
            <?php if ($result !== null): ?>
                <?php if ($result): ?>
                    <div class="success">
                        <p><strong>Success!</strong> The test email was sent successfully.</p>
                        <p>Note: This only means the email was accepted for delivery. It does not guarantee the email will be delivered to the inbox.</p>
                    </div>
                <?php else: ?>
                    <div class="error">
                        <p><strong>Failed!</strong> The test email could not be sent.</p>
                        <?php echo wp_kses_post($error_details); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Server Environment Information -->
            <h3>Server Environment</h3>
            <div class="info">
                <p><strong>Server Information:</strong><br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                Server Software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?><br>
                Operating System: <?php echo PHP_OS; ?><br>
                Mail Function Available: <?php echo function_exists('mail') ? 'Yes' : 'No'; ?><br>
                Sendmail Path: <?php echo ini_get('sendmail_path') ?: 'Not set'; ?><br>
                SMTP Setting: <?php echo ini_get('SMTP') ?: 'Not set'; ?><br>
                SMTP Port: <?php echo ini_get('smtp_port') ?: 'Not set'; ?><br>
                </p>
            </div>
            
            <!-- WordPress Mail Configuration -->
            <h3>WordPress Mail Status</h3>
            <div class="info">
                <p>
                <?php
                // Check if WP Mail SMTP is active
                $wp_mail_smtp_active = false;
                if (function_exists('is_plugin_active')) {
                    $wp_mail_smtp_active = is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') || is_plugin_active('wp-mail-smtp-pro/wp_mail_smtp.php');
                }
                
                echo '<strong>WP Mail SMTP Plugin:</strong> ' . ($wp_mail_smtp_active ? 'Active' : 'Not Active') . '<br>';
                
                // Get WordPress mail settings
                if ($wp_mail_smtp_active && function_exists('wp_mail_smtp')) {
                    $smtp_options = get_option('wp_mail_smtp', array());
                    echo '<strong>SMTP Configured:</strong> ' . (!empty($smtp_options['mail']['mailer']) ? 'Yes (' . $smtp_options['mail']['mailer'] . ')' : 'No') . '<br>';
                }
                
                // Check for common email issues
                echo '<strong>WordPress Debug Mode:</strong> ' . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . '<br>';
                echo '<strong>WordPress Debug Log:</strong> ' . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled' : 'Disabled') . '<br>';
                ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="panel">
        <h2>Recent Email Logs</h2>
        <?php if (empty($email_logs['logs'])): ?>
            <p>No email logs found.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Certificate ID</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Error</th>
                    <th>Date</th>
                </tr>
                <?php foreach ($email_logs['logs'] as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td><?php echo esc_html($log->certificate_id); ?></td>
                        <td><?php echo esc_html($log->recipient_email); ?></td>
                        <td><?php echo esc_html($log->subject); ?></td>
                        <td><?php echo $log->status ? '<span style="color:green">Success</span>' : '<span style="color:red">Failed</span>'; ?></td>
                        <td><?php echo esc_html($log->error_message); ?></td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="panel">
        <h2>Troubleshooting Tips</h2>
        <ol>
            <li><strong>Check SMTP Configuration:</strong> If using WP Mail SMTP, verify your settings are correct.</li>
            <li><strong>Test with Different Email Addresses:</strong> Try sending to Gmail, Outlook, and other providers.</li>
            <li><strong>Check Spam Folders:</strong> Emails might be delivered but marked as spam.</li>
            <li><strong>Server Limitations:</strong> Some hosts restrict the PHP mail() function or require specific configurations.</li>
            <li><strong>Email Authentication:</strong> Ensure SPF, DKIM, and DMARC records are properly set up for your domain.</li>
            <li><strong>Try a Different Method:</strong> If wp_mail() fails, try the plugin's fallback system or a dedicated SMTP plugin.</li>
            <li><strong>Check Server Logs:</strong> Your hosting provider may have logs showing email delivery attempts.</li>
            <li><strong>Use a Mail Testing Service:</strong> Services like Mailtrap can help diagnose delivery issues.</li>
        </ol>
    </div>
</body>
</html>