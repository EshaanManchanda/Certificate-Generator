<?php
/**
 * Plugin Name:       Certificate Generator
 * Plugin URI:        https://github.com/eshaanmanchanda/certificate-generator
 * Description:       A comprehensive plugin for managing, generating, and bulk-sending certificates for students and teachers.
 * Version:           6.1.0
 * Author:            Eshaan Manchanda
 * Author URI:        https://www.linkedin.com/in/eshaan-manchanda/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       certificate-generator
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants for plugin paths
define('CERTIFICATE_GENERATOR_PATH', plugin_dir_path(__FILE__));
define('CERTIFICATE_GENERATOR_URL', plugin_dir_url(__FILE__));


// Enqueue CSS and JS for Admin UI
function custom_admin_assets($hook) {
    wp_enqueue_style('custom-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
    wp_enqueue_script('custom-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', ['jquery'], '6.0.1', true);

    // Enqueue filter assets on specific admin pages
    $filter_pages = ['settings_page_certificate-bulk-send', 'settings_page_certificate-email-logs', 'edit-students', 'edit-teachers', 'edit-schools'];

    if (in_array($hook, $filter_pages) || strpos($hook, 'certificate') !== false) {
        wp_enqueue_style('cert-filters-css', plugin_dir_url(__FILE__) . 'assets/css/admin-filters.css', [], '1.0.13');
        wp_enqueue_script('cert-filters-js', plugin_dir_url(__FILE__) . 'assets/js/admin-filters.js', ['jquery'], '1.0.13', true);

        // Localize script for AJAX
        wp_localize_script('cert-filters-js', 'certFilterAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cert_bulk_send')
        ]);
    }
}
add_action('admin_enqueue_scripts', 'custom_admin_assets');

// Include required files with enhanced error handling
$critical_files = [
    'includes/server-compatibility-checker.php' => 'Server compatibility checker',
    'includes/admin-error-reporting.php' => 'Error reporting system',
    'includes/installation-debug.php' => 'Installation debug system',
    'includes/server-diagnostic.php' => 'Server diagnostic tools'
];

$optional_files = [
    'includes/certificate-post-type.php' => 'Certificate post type',
    'includes/student-certificate-search.php' => 'Student certificate search',
    'includes/bulk-import.php' => 'Bulk import functionality',
    'includes/bulk-export.php' => 'Bulk export functionality',
    'includes/bulk-certificate-download.php' => 'Bulk certificate download',
    'includes/class-certificate-background-processor.php' => 'Background processing',
    'includes/admin-settings.php' => 'Admin settings',
    'includes/admin-columns.php' => 'Admin columns',
    'includes/api-endpoints.php' => 'API endpoints',
    'includes/email-functions.php' => 'Email functions',
    'includes/email-log.php' => 'Email logging',
    'includes/admin-email-logs.php' => 'Admin email logs',
    'includes/email-queue.php' => 'Email queue system',
    'includes/email-rate-limiter.php' => 'Email rate limiter',
    'includes/bulk-email-sender.php' => 'Bulk email sender',
    'includes/admin-bulk-email.php' => 'Bulk email admin page',
    'includes/admin-filters-api.php' => 'Admin filters API',
    'includes/debug-dashboard.php' => 'Debug dashboard interface',
    'includes/installation-recovery.php' => 'Installation recovery tools'
];

$missing_critical_files = [];
$missing_optional_files = [];

// Check and include critical files
foreach ($critical_files as $file => $description) {
    $path = CERTIFICATE_GENERATOR_PATH . $file;
    if (file_exists($path) && is_readable($path)) {
        require_once $path;
    } else {
        $missing_critical_files[] = "$description ($file)";
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Certificate Generator Debug - Missing critical file - $file");
        }
    }
}

// Check and include optional files
foreach ($optional_files as $file => $description) {
    $path = CERTIFICATE_GENERATOR_PATH . $file;
    if (file_exists($path) && is_readable($path)) {
        require_once $path;
    } else {
        $missing_optional_files[] = "$description ($file)";
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Certificate Generator Debug - Missing optional file - $file");
        }
    }
}

// Handle missing critical files
if (!empty($missing_critical_files)) {
    $error_message = 'Certificate Generator cannot load due to missing critical files: ' . implode(', ', $missing_critical_files);

    // Add admin notice instead of breaking the plugin
    add_action('admin_notices', function() use ($error_message) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Certificate Generator:</strong> ' . esc_html($error_message) . '</p></div>';
    });

    error_log("Certificate Generator: Critical files missing - plugin may not function properly");
}

// Store missing files info for admin display
if (!empty($missing_optional_files)) {
    update_option('certificate_generator_missing_files', $missing_optional_files);
}

// Plugin activation hook
function certificate_generator_activate() {
    try {
        // Initialize debug system for activation tracking
        if (function_exists('certificate_generator_debug')) {
            $debug = certificate_generator_debug();
            $debug->start_debug_session();
            $debug->log_step(1, 'started', 'Plugin activation initiated');
        }

        // Run compatibility check first
        if (function_exists('certificate_generator_quick_compatibility_check')) {
            cert_gen_debug_log('Running server compatibility check', 'INFO');
            $compatibility = certificate_generator_quick_compatibility_check();

            if (!$compatibility['compatible']) {
                $error_message = 'Certificate Generator cannot be activated due to server compatibility issues: ' .
                               implode(', ', $compatibility['errors']);

                cert_gen_debug_error('Activation failed: ' . $error_message);
                cert_gen_debug_step(1, 'failed', $error_message);

                // Store error for admin display
                update_option('certificate_generator_activation_error', $error_message);

                // Deactivate the plugin and show error
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die($error_message . '<br><br><a href="' . admin_url('plugins.php') . '">Return to Plugins</a>');
            }

            cert_gen_debug_step(1, 'passed', 'Server compatibility verified');
            // Store compatibility results for admin notices
            update_option('certificate_generator_compatibility', $compatibility);
        }

        global $wpdb;

        // Step 2: File system permissions check
        cert_gen_debug_step(2, 'started', 'Checking file system permissions');
        if (function_exists('certificate_generator_debug')) {
            $debug = certificate_generator_debug();
            if (!$debug->test_file_permissions()) {
                cert_gen_debug_step(2, 'warning', 'File permission issues detected');
            } else {
                cert_gen_debug_step(2, 'passed', 'File permissions adequate');
            }
        }

        // Step 3: Database connectivity test
        cert_gen_debug_step(3, 'started', 'Testing database connectivity');
        if (function_exists('certificate_generator_debug')) {
            $debug = certificate_generator_debug();
            if (!$debug->test_database_connection()) {
                throw new Exception('Database connectivity test failed');
            }
            cert_gen_debug_step(3, 'passed', 'Database connection verified');
        }

        // Verify required WordPress functions exist
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        // Step 8: Create database table with error handling
        cert_gen_debug_step(8, 'started', 'Creating database table');
        $table_name = $wpdb->prefix . 'certificate_generator';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            student_name varchar(255) NOT NULL,
            certificate_data text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $result = dbDelta($sql);

        // Check if table was created successfully
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            cert_gen_debug_error('Failed to create database table with dbDelta, trying alternative method');

            // Try alternative method
            $wpdb->query($sql);

            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
                cert_gen_debug_step(8, 'failed', 'Database table creation failed');
                throw new Exception('Failed to create required database table. Please check database permissions.');
            }
        }

        cert_gen_debug_step(8, 'passed', 'Database table created successfully');

        // Step 8.1: Create email log table
        cert_gen_debug_step('8.1', 'started', 'Creating email log table');
        if (function_exists('certificate_generator_create_email_log_table')) {
            certificate_generator_create_email_log_table();

            // Verify table was created
            $email_log_table = $wpdb->prefix . 'cert_email_logs';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_log_table)) == $email_log_table) {
                cert_gen_debug_step('8.1', 'passed', 'Email log table created successfully');
            } else {
                cert_gen_debug_step('8.1', 'warning', 'Email log table creation uncertain');
            }
        } else {
            cert_gen_debug_step('8.1', 'warning', 'Email log table creation function not available');
        }

        // Step 8.2: Create email queue table
        cert_gen_debug_step('8.2', 'started', 'Creating email queue table');
        if (function_exists('certificate_generator_create_email_queue_table')) {
            certificate_generator_create_email_queue_table();

            // Verify table was created
            $email_queue_table = $wpdb->prefix . 'cert_email_queue';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_queue_table)) == $email_queue_table) {
                cert_gen_debug_step('8.2', 'passed', 'Email queue table created successfully');
            } else {
                cert_gen_debug_step('8.2', 'warning', 'Email queue table creation uncertain');
            }
        } else {
            cert_gen_debug_step('8.2', 'warning', 'Email queue table creation function not available');
        }

        // Step 9: Initial settings setup
        cert_gen_debug_step(9, 'started', 'Setting up initial configuration');

        // Set plugin version
        update_option('certificate_generator_version', '6.0.1');

        // Set activation timestamp
        update_option('certificate_generator_activated_at', current_time('timestamp'));

        // Determine installation mode based on server capabilities
        $installation_mode = 'minimal'; // Safe default
        if (function_exists('certificate_generator_get_installation_recommendation')) {
            $recommendation = certificate_generator_get_installation_recommendation();
            $installation_mode = $recommendation['mode'] === 'not_compatible' ? 'minimal' : $recommendation['mode'];
        }

        // Set initial settings with safe defaults
        $default_settings = array(
            'installation_mode' => $installation_mode,
            'max_memory_usage' => '64M',
            'enable_error_logging' => true,
            'font_loading_mode' => 'on_demand',
            'debug_mode' => true // Enable debug mode for new installations
        );

        if (!get_option('certificate_generator_settings')) {
            update_option('certificate_generator_settings', $default_settings);
        }

        cert_gen_debug_step(9, 'passed', "Initial settings configured (mode: $installation_mode)");

        // Step 11: Admin interface setup
        cert_gen_debug_step(11, 'started', 'Setting up admin interface');

        // Flush rewrite rules
        flush_rewrite_rules();

        cert_gen_debug_step(11, 'passed', 'Admin interface setup completed');

        // Step 12: Final verification
        cert_gen_debug_step(12, 'started', 'Running final verification');

        // Clear any previous activation errors
        delete_option('certificate_generator_activation_error');

        // Log successful activation
        cert_gen_debug_log('Plugin activation completed successfully', 'INFO');
        cert_gen_debug_step(12, 'passed', 'Plugin activation completed successfully');
        error_log('Certificate Generator: Plugin activated successfully');

    } catch (Exception $e) {
        // Log the error with debug system
        cert_gen_debug_error('Plugin activation failed: ' . $e->getMessage(), $e);
        error_log('Certificate Generator Activation Error: ' . $e->getMessage());

        // Store detailed error information for admin display
        $error_details = array(
            'message' => $e->getMessage(),
            'timestamp' => current_time('mysql'),
            'step' => get_option('cert_gen_debug_current_step', 'unknown'),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'debug_data_available' => true
        );

        update_option('certificate_generator_activation_error', $error_details);

        // Deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Show enhanced error message with debug information
        $debug_url = admin_url('admin.php?page=cert-gen-debug-dashboard');
        $error_message = 'Certificate Generator could not be activated: ' . $e->getMessage();
        $error_message .= '<br><br><strong>Debug Information Available:</strong>';
        $error_message .= '<br>• PHP Version: ' . PHP_VERSION;
        $error_message .= '<br>• WordPress Version: ' . get_bloginfo('version');
        $error_message .= '<br>• Memory Limit: ' . ini_get('memory_limit');
        $error_message .= '<br>• Failed at Step: ' . get_option('cert_gen_debug_current_step', 'Unknown');
        $error_message .= '<br><br><a href="' . $debug_url . '" class="button button-primary">View Debug Dashboard</a> ';
        $error_message .= '<a href="' . admin_url('plugins.php') . '" class="button">Return to Plugins</a>';

        wp_die($error_message);
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

    // Drop all custom database tables
    $tables = [
        $wpdb->prefix . 'certificate_generator',
        $wpdb->prefix . 'cert_email_logs',
        $wpdb->prefix . 'cert_email_queue'
    ];

    foreach ($tables as $table_name) {
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_name));
    }
}
register_uninstall_hook(__FILE__, 'certificate_generator_uninstall');

// Plugin update logic
function certificate_generator_update_check() {
    $current_version = get_option('certificate_generator_version', '');
    $new_version = '6.0.1';

    if ($current_version !== $new_version) {
        update_option('certificate_generator_version', $new_version);
    }
}
add_action('plugins_loaded', 'certificate_generator_update_check');

// Add admin notices for compatibility and missing files
function certificate_generator_admin_notices() {
    // Check for compatibility warnings
    $compatibility = get_option('certificate_generator_compatibility');
    if ($compatibility && !empty($compatibility['warnings'])) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Certificate Generator Warnings:</strong></p>';
        echo '<ul>';
        foreach ($compatibility['warnings'] as $warning) {
            echo '<li>' . esc_html($warning) . '</li>';
        }
        echo '</ul>';
        if (!empty($compatibility['recommendations'])) {
            echo '<p><strong>Recommendations:</strong></p>';
            echo '<ul>';
            foreach ($compatibility['recommendations'] as $recommendation) {
                echo '<li>' . esc_html($recommendation) . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    // Check for missing optional files
    $missing_files = get_option('certificate_generator_missing_files');
    if (!empty($missing_files)) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>Certificate Generator:</strong> Some optional features are unavailable due to missing files:</p>';
        echo '<ul>';
        foreach ($missing_files as $file) {
            echo '<li>' . esc_html($file) . '</li>';
        }
        echo '</ul>';
        echo '<p>You can still use the plugin, but some features may be limited. Please re-upload the complete plugin files if you need these features.</p>';
        echo '</div>';
    }

    // Show installation mode notice
    $settings = get_option('certificate_generator_settings');
    if ($settings && isset($settings['installation_mode']) && $settings['installation_mode'] === 'minimal') {
        echo '<div class="notice notice-info">';
        echo '<p><strong>Certificate Generator:</strong> Running in minimal mode due to server limitations. ';
        echo 'Some advanced features are disabled to ensure compatibility with your hosting environment.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'certificate_generator_admin_notices');

// Add memory usage monitoring
function certificate_generator_check_memory_usage() {
    if (function_exists('memory_get_usage') && function_exists('memory_get_peak_usage')) {
        $current_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        $memory_limit = ini_get('memory_limit');

        // Convert memory limit to bytes for comparison
        $memory_limit_bytes = certificate_generator_convert_to_bytes($memory_limit);

        // Log if memory usage is getting high (80% of limit)
        if ($memory_limit_bytes > 0 && $current_memory > ($memory_limit_bytes * 0.8)) {
            error_log(sprintf(
                'Certificate Generator: High memory usage detected. Current: %s, Peak: %s, Limit: %s',
                certificate_generator_format_bytes($current_memory),
                certificate_generator_format_bytes($peak_memory),
                $memory_limit
            ));
        }
    }
}

// Helper function to convert memory string to bytes
function certificate_generator_convert_to_bytes($size_str) {
    if (empty($size_str) || $size_str === '-1') {
        return -1; // Unlimited
    }

    $size_str = trim($size_str);
    $last_char = strtolower($size_str[strlen($size_str) - 1]);
    $size = (int) $size_str;

    switch ($last_char) {
        case 'g':
            $size *= 1024;
        case 'm':
            $size *= 1024;
        case 'k':
            $size *= 1024;
    }

    return $size;
}

// Helper function to format bytes for human reading
function certificate_generator_format_bytes($bytes) {
    if ($bytes == -1) {
        return 'Unlimited';
    }

    if ($bytes >= 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024 * 1024), 1) . 'GB';
    } elseif ($bytes >= 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . 'MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . 'KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Add memory monitoring to admin pages
add_action('admin_init', 'certificate_generator_check_memory_usage');
?>