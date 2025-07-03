<?php
/**
 * Certificate Generator - Quick Activation Status Checker
 * Run this file directly to check plugin activation status
 */

// Basic WordPress bootstrap for standalone execution
if (!defined('ABSPATH')) {
    // Try to find WordPress root
    $wp_root_candidates = [
        dirname(dirname(dirname(dirname(__FILE__)))), // Standard plugin location
        dirname(dirname(dirname(dirname(dirname(__FILE__))))), // Alternative location
    ];
    
    foreach ($wp_root_candidates as $candidate) {
        if (file_exists($candidate . '/wp-config.php')) {
            define('WP_USE_THEMES', false);
            require_once $candidate . '/wp-blog-header.php';
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die('WordPress not found. Please run this from within WordPress admin or ensure WordPress is properly installed.');
    }
}

/**
 * Quick activation status check
 */
function cert_gen_quick_status_check() {
    global $wpdb;
    
    $status = [
        'timestamp' => current_time('mysql'),
        'environment' => 'unknown',
        'wordpress_loaded' => defined('ABSPATH'),
        'database_connected' => false,
        'plugin_activated' => false,
        'tables_exist' => false,
        'post_types_registered' => false,
        'admin_menu_exists' => false,
        'issues' => [],
        'recommendations' => []
    ];
    
    // Detect environment
    if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || 
        strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
        strpos($_SERVER['SERVER_NAME'] ?? '', '.local') !== false) {
        $status['environment'] = 'local';
    } elseif (strpos($_SERVER['HTTP_HOST'] ?? '', 'hostinger') !== false ||
              strpos($_SERVER['HTTP_HOST'] ?? '', '.hostinger.') !== false) {
        $status['environment'] = 'hostinger';
    } else {
        $status['environment'] = 'production';
    }
    
    // Check database connection
    if (is_object($wpdb)) {
        try {
            $test_result = $wpdb->get_var("SELECT 1");
            $status['database_connected'] = ($test_result == 1);
            
            if (!$status['database_connected']) {
                $status['issues'][] = 'Database connection test failed';
                if ($status['environment'] === 'local') {
                    $status['recommendations'][] = 'Start your local MySQL service (XAMPP/WAMP/Local by Flywheel)';
                } else {
                    $status['recommendations'][] = 'Check database credentials in wp-config.php';
                }
            }
        } catch (Exception $e) {
            $status['database_connected'] = false;
            $status['issues'][] = 'Database error: ' . $e->getMessage();
            
            if (strpos($e->getMessage(), 'actively refused') !== false) {
                $status['recommendations'][] = 'MySQL service is not running - start your database server';
            } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
                $status['recommendations'][] = 'Database credentials are incorrect - check wp-config.php';
            }
        }
    } else {
        $status['issues'][] = 'WordPress database object not available';
        $status['recommendations'][] = 'WordPress may not be properly initialized';
    }
    
    // Check plugin activation status
    $status['plugin_activated'] = (bool) get_option('certificate_generator_activated', false);
    
    if (!$status['plugin_activated']) {
        $status['issues'][] = 'Plugin activation option not set';
        $status['recommendations'][] = 'Try reactivating the plugin from WordPress admin';
    }
    
    // Check if tables exist
    if ($status['database_connected']) {
        $main_table = $wpdb->prefix . 'certificate_generator';
        $email_table = $wpdb->prefix . 'cert_email_logs';
        
        $main_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $main_table)) == $main_table);
        $email_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_table)) == $email_table);
        
        $status['tables_exist'] = $main_exists && $email_exists;
        $status['main_table_exists'] = $main_exists;
        $status['email_table_exists'] = $email_exists;
        
        if (!$main_exists) {
            $status['issues'][] = 'Main certificate table missing';
            $status['recommendations'][] = 'Run plugin activation or create tables manually';
        }
        
        if (!$email_exists) {
            $status['issues'][] = 'Email log table missing';
            $status['recommendations'][] = 'Run plugin activation or create tables manually';
        }
    }
    
    // Check if post types are registered
    $status['post_types_registered'] = post_type_exists('certificate');
    
    if (!$status['post_types_registered']) {
        $status['issues'][] = 'Certificate post type not registered';
        $status['recommendations'][] = 'Ensure plugin files are loaded correctly';
    }
    
    // Check if admin menu exists (only in admin context)
    if (is_admin() && function_exists('menu_page_url')) {
        global $menu;
        $status['admin_menu_exists'] = false;
        
        if (is_array($menu)) {
            foreach ($menu as $menu_item) {
                if (isset($menu_item[2]) && strpos($menu_item[2], 'certificate') !== false) {
                    $status['admin_menu_exists'] = true;
                    break;
                }
            }
        }
        
        if (!$status['admin_menu_exists']) {
            $status['issues'][] = 'Admin menu not found';
            $status['recommendations'][] = 'Check if plugin is properly activated and files are loaded';
        }
    }
    
    return $status;
}

/**
 * Display status in HTML format
 */
function cert_gen_display_status_html($status) {
    $overall_status = empty($status['issues']) ? 'GOOD' : 'ISSUES FOUND';
    $status_color = empty($status['issues']) ? '#46b450' : '#dc3232';
    
    echo "<!DOCTYPE html>
<html>
<head>
    <title>Certificate Generator - Activation Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .status-header { text-align: center; padding: 20px; margin-bottom: 20px; border-radius: 5px; color: white; }
        .good { background-color: #46b450; }
        .issues { background-color: #dc3232; }
        .check-item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; }
        .check-name { font-weight: bold; }
        .check-result.pass { color: #46b450; }
        .check-result.fail { color: #dc3232; }
        .issues-section, .recommendations-section { margin-top: 20px; }
        .issue, .recommendation { padding: 8px; margin: 5px 0; border-left: 4px solid #dc3232; background: #ffeaea; }
        .recommendation { border-left-color: #ffb900; background: #fff8e5; }
        .timestamp { text-align: center; color: #666; margin-top: 20px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='status-header " . (empty($status['issues']) ? 'good' : 'issues') . "'>
            <h1>Certificate Generator Status: {$overall_status}</h1>
            <p>Environment: {$status['environment']}</p>
        </div>
        
        <h2>System Checks</h2>";
    
    $checks = [
        'WordPress Loaded' => $status['wordpress_loaded'],
        'Database Connected' => $status['database_connected'],
        'Plugin Activated' => $status['plugin_activated'],
        'Tables Exist' => $status['tables_exist'],
        'Post Types Registered' => $status['post_types_registered']
    ];
    
    if (isset($status['admin_menu_exists'])) {
        $checks['Admin Menu Exists'] = $status['admin_menu_exists'];
    }
    
    foreach ($checks as $check_name => $result) {
        $result_text = $result ? '✓ PASS' : '✗ FAIL';
        $result_class = $result ? 'pass' : 'fail';
        
        echo "<div class='check-item'>
            <span class='check-name'>{$check_name}</span>
            <span class='check-result {$result_class}'>{$result_text}</span>
        </div>";
    }
    
    if (!empty($status['issues'])) {
        echo "<div class='issues-section'>
            <h2>Issues Found</h2>";
        
        foreach ($status['issues'] as $issue) {
            echo "<div class='issue'>" . htmlspecialchars($issue) . "</div>";
        }
        
        echo "</div>";
    }
    
    if (!empty($status['recommendations'])) {
        echo "<div class='recommendations-section'>
            <h2>Recommendations</h2>";
        
        foreach ($status['recommendations'] as $recommendation) {
            echo "<div class='recommendation'>" . htmlspecialchars($recommendation) . "</div>";
        }
        
        echo "</div>";
    }
    
    echo "<div class='timestamp'>Last checked: {$status['timestamp']}</div>
    </div>
</body>
</html>";
}

/**
 * Display status in CLI format
 */
function cert_gen_display_status_cli($status) {
    $overall_status = empty($status['issues']) ? 'GOOD' : 'ISSUES FOUND';
    
    echo "\n=== Certificate Generator Activation Status ===\n";
    echo "Overall Status: {$overall_status}\n";
    echo "Environment: {$status['environment']}\n";
    echo "Timestamp: {$status['timestamp']}\n\n";
    
    echo "System Checks:\n";
    echo "- WordPress Loaded: " . ($status['wordpress_loaded'] ? 'PASS' : 'FAIL') . "\n";
    echo "- Database Connected: " . ($status['database_connected'] ? 'PASS' : 'FAIL') . "\n";
    echo "- Plugin Activated: " . ($status['plugin_activated'] ? 'PASS' : 'FAIL') . "\n";
    echo "- Tables Exist: " . ($status['tables_exist'] ? 'PASS' : 'FAIL') . "\n";
    echo "- Post Types Registered: " . ($status['post_types_registered'] ? 'PASS' : 'FAIL') . "\n";
    
    if (isset($status['admin_menu_exists'])) {
        echo "- Admin Menu Exists: " . ($status['admin_menu_exists'] ? 'PASS' : 'FAIL') . "\n";
    }
    
    if (!empty($status['issues'])) {
        echo "\nIssues Found:\n";
        foreach ($status['issues'] as $issue) {
            echo "- {$issue}\n";
        }
    }
    
    if (!empty($status['recommendations'])) {
        echo "\nRecommendations:\n";
        foreach ($status['recommendations'] as $recommendation) {
            echo "- {$recommendation}\n";
        }
    }
    
    echo "\n";
}

// Main execution
if (!defined('DOING_AJAX') && !defined('XMLRPC_REQUEST')) {
    $status = cert_gen_quick_status_check();
    
    // Determine output format
    if (php_sapi_name() === 'cli') {
        cert_gen_display_status_cli($status);
    } else {
        cert_gen_display_status_html($status);
    }
}

?>