<?php
/**
 * Email Fallback System Example
 * 
 * This file demonstrates how the fallback email system works
 * similar to Forminator's approach.
 *
 * @package Certificate Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example usage of the fallback email system
 * 
 * This function shows how emails are automatically sent using:
 * - WP Mail SMTP if configured
 * - WordPress default (PHP mail) with noreply@domain.com fallback if not
 */
function certificate_generator_email_fallback_example() {
    // Get current email status
    $email_status = certificate_generator_get_email_status();
    
    echo "<h3>Current Email Configuration:</h3>";
    echo "<ul>";
    echo "<li><strong>Method:</strong> " . esc_html($email_status['method']) . "</li>";
    echo "<li><strong>From Email:</strong> " . esc_html($email_status['from_email']) . "</li>";
    echo "<li><strong>From Name:</strong> " . esc_html($email_status['from_name']) . "</li>";
    echo "<li><strong>SMTP Configured:</strong> " . ($email_status['smtp_configured'] ? 'Yes' : 'No') . "</li>";
    echo "<li><strong>Fallback Active:</strong> " . ($email_status['fallback_active'] ? 'Yes' : 'No') . "</li>";
    echo "</ul>";
    
    if ($email_status['fallback_active']) {
        echo "<div class='notice notice-info'>";
        echo "<p><strong>Fallback System Active:</strong> Emails will be sent using WordPress default method (PHP mail) with automatic noreply@domain.com sender address.</p>";
        echo "<p>This works on most shared hosting providers without requiring SMTP configuration.</p>";
        echo "</div>";
    } else {
        echo "<div class='notice notice-success'>";
        echo "<p><strong>SMTP Configured:</strong> Emails will be sent through your configured SMTP settings for better deliverability.</p>";
        echo "</div>";
    }
}

/**
 * Example of sending an email using the fallback system
 */
function certificate_generator_send_test_email_example($recipient_email) {
    $subject = 'Test Email from Certificate Generator';
    $message = 'This is a test email to verify the fallback email system is working.';
    
    // Use the enhanced fallback email function
    $result = certificate_generator_send_fallback_email(
        $recipient_email,
        $subject,
        $message,
        '', // no attachment
        '', // no reply-to
        '', // no CC
        ''  // no BCC
    );
    
    if ($result) {
        echo "<div class='notice notice-success'><p>Test email sent successfully!</p></div>";
    } else {
        echo "<div class='notice notice-error'><p>Failed to send test email.</p></div>";
    }
    
    return $result;
}

/**
 * Display email delivery information for debugging
 */
function certificate_generator_debug_email_info() {
    $email_status = certificate_generator_get_email_status();
    
    echo "<h4>Email Delivery Debug Information:</h4>";
    echo "<pre>";
    print_r($email_status);
    echo "</pre>";
    
    // Check if wp_mail function is available
    if (function_exists('wp_mail')) {
        echo "<p>✅ wp_mail() function is available</p>";
    } else {
        echo "<p>❌ wp_mail() function is NOT available</p>";
    }
    
    // Check if WP Mail SMTP is detected
    if (certificate_generator_is_wp_mail_smtp_active()) {
        echo "<p>✅ WP Mail SMTP plugin detected and active</p>";
    } else {
        echo "<p>ℹ️ WP Mail SMTP plugin not detected - using fallback system</p>";
    }
    
    // Show server information
    $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'unknown';
    $home_url_host = parse_url(home_url(), PHP_URL_HOST);
    
    echo "<p><strong>Server Name:</strong> " . esc_html($server_name) . "</p>";
    echo "<p><strong>Home URL Host:</strong> " . esc_html($home_url_host) . "</p>";
    echo "<p><strong>Fallback Email:</strong> noreply@" . esc_html($server_name ?: $home_url_host) . "</p>";
}

?>