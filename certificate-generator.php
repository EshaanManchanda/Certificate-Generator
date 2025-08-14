<?php
/**
 * Plugin Name: Certificate Generator
 * Description: A plugin for managing and generating certificates for students.
 * Version: 4.0.1
 * Author: Eshaan Manchanda
 * Author URI: https://www.linkedin.com/in/eshaan-manchanda/
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants for plugin paths
define('CERTIFICATE_GENERATOR_PATH', plugin_dir_path(__FILE__));
define('CERTIFICATE_GENERATOR_URL', plugin_dir_url(__FILE__));

// Include debug configuration
require_once CERTIFICATE_GENERATOR_PATH . 'debug-config.php';

// Initialize debug configuration early
certificate_generator_init_config();


// Enqueue CSS and JS for Admin UI
function custom_admin_assets() {
    wp_enqueue_style('custom-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
    
    // Enqueue admin scripts
    add_action('admin_enqueue_scripts', function($hook) {
        // Always load the main admin script
        wp_enqueue_script('custom-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', ['jquery'], null, true);
        
        // Only load debug scripts if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // Check if we're on the settings page
            if (strpos($hook, 'certificate_generator_settings') !== false) {
                wp_enqueue_script('api-debug-js', plugin_dir_url(__FILE__) . 'assets/js/api-debug.js', ['jquery'], time(), true);
                wp_enqueue_script('tab-debug-js', plugin_dir_url(__FILE__) . 'assets/js/tab-debug.js', ['jquery'], time(), true);
                wp_enqueue_script('api-key-fix-js', plugin_dir_url(__FILE__) . 'assets/js/api-key-fix.js', ['jquery'], time(), true);
            }
        }
    });
}
add_action('admin_enqueue_scripts', 'custom_admin_assets');

// Include required files
$required_files = [
    'includes/certificate-post-type.php',    // Handles the custom post type for certificates
    'includes/student-certificate-search.php',          // Search Student
    'includes/bulk-import.php',             // Bulk import functionality for CSV uploads
    'includes/bulk-export.php',             // Bulk export functionality for CSV download
    'includes/bulk-certificate-download.php', // Bulk certificate download for schools
    'includes/class-certificate-background-processor.php', // Background processor for certificate generation
    'includes/admin-settings.php',          // Admin settings page
    'includes/admin-columns.php',           // Admin columns
    'includes/api-endpoints.php',           // API endpoints for AI integration
    'includes/email-functions.php',         // Email functionality
    'includes/email-log.php',              // Email logging functionality
    'includes/admin-email-logs.php',        // Admin interface for email logs
    'hostinger-debug.php',                  // Hostinger-specific debug tools
    'hostinger-safe-activation.php',        // Hostinger-specific safe activation
    'enhanced-activation.php',              // Enhanced activation system for all environments
    'activation-diagnostic.php'             // Activation diagnostic tools
];

// Only load debug tools in development environments
if (defined('WP_DEBUG') && WP_DEBUG) {
    $required_files[] = 'debug-tools.php';
}

foreach ($required_files as $file) {
    $path = CERTIFICATE_GENERATOR_PATH . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("Certificate Generator: Missing required file - $file");
    }
}

// Plugin activation hook - now uses enhanced activation system
function certificate_generator_activate() {
    // Use the enhanced activation system that detects environment
    if (function_exists('certificate_generator_smart_activate')) {
        return certificate_generator_smart_activate();
    } else {
        // Fallback to Hostinger safe activation if enhanced system not loaded
        if (function_exists('certificate_generator_hostinger_safe_activate')) {
            return certificate_generator_hostinger_safe_activate();
        } else {
            error_log('Certificate Generator: No activation function available');
            
            // Last resort - try to create minimal setup
            try {
                global $wpdb;
                if ($wpdb && is_object($wpdb)) {
                    $table_name = $wpdb->prefix . 'certificate_generator';
                    $wpdb->query("CREATE TABLE IF NOT EXISTS $table_name (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        student_name varchar(255) NOT NULL,
                        certificate_data text NOT NULL,
                        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                        PRIMARY KEY  (id)
                    )");
                    error_log('Certificate Generator: Minimal setup completed');
                    return true;
                }
            } catch (Exception $fallback_error) {
                error_log('Certificate Generator: All activation methods failed - ' . $fallback_error->getMessage());
            }
            
            return false;
        }
    }
}

// Register the activation hook
register_activation_hook(__FILE__, 'certificate_generator_activate');

// Plugin deactivation hook
function certificate_generator_deactivate() {
    flush_rewrite_rules(); // Flush rewrite rules on deactivation
    
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('certificate_generator_cleanup_logs');
}
register_deactivation_hook(__FILE__, 'certificate_generator_deactivate');

// Plugin uninstall hook
function certificate_generator_uninstall() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'certificate_generator';

    // Drop the custom database table
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'certificate_generator_uninstall');

// Plugin update logic (optional)
function certificate_generator_update_check() {
    $current_version = get_option('certificate_generator_version', '');
    $new_version = '3.0.0'; // Update with your plugin's current version

    if ($current_version !== $new_version) {
        // Perform update-related tasks here, e.g., modifying database structure
        global $wpdb;
        $table_name = $wpdb->prefix . 'certificate_generator';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER created_at");
        }

        // Update the version in the options table
        update_option('certificate_generator_version', $new_version);
    }
}
add_action('plugins_loaded', 'certificate_generator_update_check');

?>