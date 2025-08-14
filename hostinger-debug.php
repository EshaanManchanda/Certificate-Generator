<?php
/**
 * Hostinger Production Environment Debug Script
 * This file helps diagnose WordPress plugin activation issues on Hostinger hosting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

function certificate_generator_hostinger_debug() {
    $debug_info = [];
    
    try {
        // Basic WordPress Environment Check
        $debug_info['wordpress_loaded'] = defined('ABSPATH') ? 'Yes' : 'No';
        $debug_info['wp_version'] = get_bloginfo('version');
        $debug_info['php_version'] = phpversion();
        $debug_info['mysql_version'] = $GLOBALS['wpdb']->db_version();
        
        // Server Environment
        $debug_info['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $debug_info['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';
        $debug_info['script_filename'] = $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown';
        $debug_info['http_host'] = $_SERVER['HTTP_HOST'] ?? 'Unknown';
        
        // WordPress Paths
        $debug_info['abspath'] = ABSPATH;
        $debug_info['wp_content_dir'] = WP_CONTENT_DIR;
        $debug_info['wp_plugin_dir'] = WP_PLUGIN_DIR;
        $debug_info['wp_content_url'] = WP_CONTENT_URL;
        
        // Database Connection
        global $wpdb;
        $debug_info['db_connected'] = is_object($wpdb) ? 'Yes' : 'No';
        $debug_info['db_prefix'] = $wpdb->prefix ?? 'Unknown';
        $debug_info['db_charset'] = $wpdb->charset ?? 'Unknown';
        $debug_info['db_collate'] = $wpdb->collate ?? 'Unknown';
        
        // Check for upgrade.php in multiple locations
        $upgrade_paths = [
            'ABSPATH' => ABSPATH . 'wp-admin/includes/upgrade.php',
            'dirname(ABSPATH)' => dirname(ABSPATH) . '/wp-admin/includes/upgrade.php',
            'WP_CONTENT_DIR' => WP_CONTENT_DIR . '/../wp-admin/includes/upgrade.php',
            'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] . '/wp-admin/includes/upgrade.php',
            'SCRIPT_FILENAME' => dirname($_SERVER['SCRIPT_FILENAME']) . '/wp-admin/includes/upgrade.php'
        ];
        
        foreach ($upgrade_paths as $label => $path) {
            $debug_info["upgrade_php_{$label}"] = [
                'path' => $path,
                'exists' => file_exists($path) ? 'Yes' : 'No',
                'readable' => is_readable($path) ? 'Yes' : 'No',
                'size' => file_exists($path) ? filesize($path) : 0
            ];
        }
        
        // WordPress Functions Availability
        $wp_functions = [
            'wp_get_current_user',
            'add_option',
            'get_option',
            'flush_rewrite_rules',
            'wp_next_scheduled',
            'wp_schedule_event',
            'dbDelta'
        ];
        
        foreach ($wp_functions as $func) {
            $debug_info["function_{$func}"] = function_exists($func) ? 'Available' : 'Missing';
        }
        
        // Plugin-specific function checks
        $plugin_functions = [
            'register_custom_post_types',
            'certificate_generator_create_email_log_table'
        ];
        
        foreach ($plugin_functions as $func) {
            $debug_info["plugin_function_{$func}"] = function_exists($func) ? 'Available' : 'Missing';
        }
        
        // Memory and execution limits
        $debug_info['memory_limit'] = ini_get('memory_limit');
        $debug_info['max_execution_time'] = ini_get('max_execution_time');
        $debug_info['memory_usage'] = memory_get_usage(true);
        $debug_info['memory_peak'] = memory_get_peak_usage(true);
        
        // File permissions check
        $important_dirs = [
            'wp-content' => WP_CONTENT_DIR,
            'plugins' => WP_PLUGIN_DIR,
            'uploads' => wp_upload_dir()['basedir']
        ];
        
        foreach ($important_dirs as $label => $dir) {
            $debug_info["permissions_{$label}"] = [
                'path' => $dir,
                'exists' => is_dir($dir) ? 'Yes' : 'No',
                'writable' => is_writable($dir) ? 'Yes' : 'No',
                'readable' => is_readable($dir) ? 'Yes' : 'No'
            ];
        }
        
        // Test database operations
        try {
            $test_query = $wpdb->get_var("SELECT 1");
            $debug_info['db_test_query'] = $test_query == 1 ? 'Success' : 'Failed';
        } catch (Exception $e) {
            $debug_info['db_test_query'] = 'Error: ' . $e->getMessage();
        }
        
        // Check if tables can be created
        try {
            $test_table = $wpdb->prefix . 'cert_gen_test_' . time();
            $create_result = $wpdb->query("CREATE TABLE IF NOT EXISTS $test_table (id INT AUTO_INCREMENT PRIMARY KEY, test_col VARCHAR(50))");
            $debug_info['db_create_test'] = $create_result !== false ? 'Success' : 'Failed: ' . $wpdb->last_error;
            
            // Clean up test table
            $wpdb->query("DROP TABLE IF EXISTS $test_table");
        } catch (Exception $e) {
            $debug_info['db_create_test'] = 'Error: ' . $e->getMessage();
        }
        
        // Hostinger-specific checks
        $debug_info['is_hostinger'] = (strpos($_SERVER['HTTP_HOST'], 'hostinger') !== false || 
                                     strpos($_SERVER['SERVER_NAME'], 'hostinger') !== false) ? 'Yes' : 'Unknown';
        
        // Error log location
        $debug_info['error_log'] = ini_get('error_log');
        $debug_info['log_errors'] = ini_get('log_errors') ? 'Enabled' : 'Disabled';
        
    } catch (Exception $e) {
        $debug_info['debug_error'] = $e->getMessage();
    }
    
    return $debug_info;
}

// Function to display debug info in admin
function certificate_generator_show_hostinger_debug() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $debug_info = certificate_generator_hostinger_debug();
    
    echo '<div class="wrap">';
    echo '<h1>Certificate Generator - Hostinger Debug Info</h1>';
    echo '<div class="notice notice-info"><p>This debug information can help identify activation issues on Hostinger hosting.</p></div>';
    
    echo '<table class="widefat" style="margin-top: 20px;">';
    echo '<thead><tr><th>Check</th><th>Result</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($debug_info as $key => $value) {
        echo '<tr>';
        echo '<td><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</strong></td>';
        echo '<td>';
        
        if (is_array($value)) {
            echo '<pre>' . esc_html(print_r($value, true)) . '</pre>';
        } else {
            echo esc_html($value);
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
}

// Add admin menu for debug info
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Certificate Generator Debug',
        'Cert Gen Debug',
        'manage_options',
        'cert-gen-hostinger-debug',
        'certificate_generator_show_hostinger_debug'
    );
});

// Log debug info to error log
function certificate_generator_log_hostinger_debug() {
    $debug_info = certificate_generator_hostinger_debug();
    error_log('Certificate Generator Hostinger Debug: ' . print_r($debug_info, true));
}

// Auto-run debug on plugin activation issues
register_activation_hook(__FILE__, 'certificate_generator_log_hostinger_debug');
?>