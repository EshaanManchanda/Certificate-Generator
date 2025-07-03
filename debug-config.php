<?php
/**
 * Debug Configuration Manager for Certificate Generator
 *
 * This file provides functions to safely manage WordPress debug settings
 * and email configuration. It helps maintain secure debug practices while
 * allowing necessary troubleshooting capabilities.
 *
 * @package Certificate Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress environment is loaded
if (!function_exists('add_action')) {
    return;
}

/**
 * Configure debug settings based on environment
 */
function certificate_generator_configure_debug() {
    // Check if we're in a development environment
    $is_development = (
        isset($_SERVER['REMOTE_ADDR']) && 
        in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) ||
        (isset($_SERVER['HTTP_HOST']) && (
            strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
            strpos($_SERVER['HTTP_HOST'], '.test') !== false ||
            strpos($_SERVER['HTTP_HOST'], '.dev') !== false ||
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
        )) ||
        (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development')
    );

    if ($is_development) {
        // Development environment settings
        if (!defined('WP_DEBUG')) define('WP_DEBUG', true);
        if (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', true);
        if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', true);
    } else {
        // Production environment settings
        if (!defined('WP_DEBUG')) define('WP_DEBUG', false);
        if (!defined('WP_DEBUG_LOG')) define('WP_DEBUG_LOG', true); // Keep logging but don't display
        if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', false);
    }
}

/**
 * Check and configure email settings
 */
function certificate_generator_check_email_config() {
    // Check if WP Mail SMTP is active and configured
    $wp_mail_smtp_active = false;
    $smtp_settings_configured = false;

    if (function_exists('is_plugin_active')) {
        $wp_mail_smtp_active = is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') || 
                               is_plugin_active('wp-mail-smtp-pro/wp_mail_smtp.php');
        
        // Check if SMTP settings are configured
        if ($wp_mail_smtp_active) {
            $smtp_settings = get_option('wp_mail_smtp', array());
            $smtp_settings_configured = !empty($smtp_settings['mail']) && 
                                      $smtp_settings['mail']['mailer'] !== 'mail' && 
                                      !empty($smtp_settings['mail']['from_email']);

        }
    }

    // Show admin notice if SMTP is not active or not properly configured
    if ((!$wp_mail_smtp_active || !$smtp_settings_configured) && 
        function_exists('get_transient') && 
        function_exists('set_transient') && 
        function_exists('add_action') && 
        function_exists('current_user_can') && 
        function_exists('admin_url') && 
        !get_transient('cert_gen_smtp_notice_shown')) {
        
        set_transient('cert_gen_smtp_notice_shown', true, DAY_IN_SECONDS);
        add_action('admin_notices', function() use ($wp_mail_smtp_active) {
            if (function_exists('current_user_can') && current_user_can('manage_options')) {
                $message = $wp_mail_smtp_active
                    ? 'Please complete the WP Mail SMTP configuration for reliable certificate delivery. '
                    . '<a href="' . admin_url('admin.php?page=wp-mail-smtp') . '">Configure now</a>.'
                    : 'For reliable email delivery, please install and configure '
                    . '<a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">'
                    . 'WP Mail SMTP</a>. This will help ensure certificates are delivered successfully.';
                
                echo '<div class="notice notice-warning is-dismissible">'
                    . '<p><strong>Certificate Generator:</strong> ' . $message . '</p>'
                    . '</div>';
            }
        });
    }
}

/**
 * Initialize debug and email configuration
 */
function certificate_generator_init_config() {
    // Configure debug settings
    certificate_generator_configure_debug();
    
    // Check email configuration
    certificate_generator_check_email_config();
    
    // Set error handling for production
    if (!WP_DEBUG) {
        error_reporting(0);
        @ini_set('display_errors', 0);
    }
}

// Initialize configuration
add_action('plugins_loaded', 'certificate_generator_init_config');