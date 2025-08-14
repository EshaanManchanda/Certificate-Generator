<?php
/**
 * Certificate Generator - Activation Diagnostic Tool
 * This script helps diagnose and fix activation issues
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

/**
 * Run comprehensive activation diagnostics
 */
function certificate_generator_run_diagnostics() {
    $diagnostics = [];
    $issues = [];
    $fixes = [];
    
    // 1. Environment Detection
    $environment = certificate_generator_detect_environment();
    $diagnostics['environment'] = $environment;
    
    // 2. WordPress Environment Check
    $diagnostics['wordpress_loaded'] = defined('ABSPATH');
    $diagnostics['wp_functions_available'] = function_exists('add_option') && function_exists('get_option');
    
    if (!$diagnostics['wordpress_loaded']) {
        $issues[] = 'WordPress environment not properly loaded';
        $fixes[] = 'Ensure this script is called within WordPress context';
    }
    
    // 3. Database Connection Check
    global $wpdb;
    $diagnostics['db_object_exists'] = is_object($wpdb);
    
    if ($diagnostics['db_object_exists']) {
        try {
            $test_query = $wpdb->get_var("SELECT 1");
            $diagnostics['db_connection_working'] = ($test_query == 1);
            
            if (!$diagnostics['db_connection_working']) {
                $issues[] = 'Database connection test failed';
                if ($environment === 'local') {
                    $fixes[] = 'Start your local MySQL service (XAMPP, WAMP, Local by Flywheel)';
                } else {
                    $fixes[] = 'Check database credentials in wp-config.php';
                }
            }
        } catch (Exception $e) {
            $diagnostics['db_connection_working'] = false;
            $diagnostics['db_error'] = $e->getMessage();
            $issues[] = 'Database connection error: ' . $e->getMessage();
            
            if (strpos($e->getMessage(), 'actively refused') !== false) {
                $fixes[] = 'MySQL service is not running. Start your database server.';
            } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
                $fixes[] = 'Database credentials are incorrect. Check wp-config.php';
            }
        }
    } else {
        $issues[] = 'WordPress database object not available';
        $fixes[] = 'WordPress may not be properly initialized';
    }
    
    // 4. Plugin Files Check
    $required_files = [
        'enhanced-activation.php',
        'hostinger-safe-activation.php',
        'includes/certificate-post-type.php',
        'includes/email-log.php'
    ];
    
    $diagnostics['missing_files'] = [];
    foreach ($required_files as $file) {
        $file_path = CERTIFICATE_GENERATOR_PATH . $file;
        if (!file_exists($file_path)) {
            $diagnostics['missing_files'][] = $file;
            $issues[] = "Missing required file: {$file}";
            $fixes[] = "Ensure all plugin files are uploaded correctly";
        }
    }
    
    // 5. Function Availability Check
    $required_functions = [
        'certificate_generator_smart_activate',
        'certificate_generator_hostinger_safe_activate',
        'register_custom_post_types'
    ];
    
    $diagnostics['missing_functions'] = [];
    foreach ($required_functions as $func) {
        if (!function_exists($func)) {
            $diagnostics['missing_functions'][] = $func;
        }
    }
    
    // 6. Table Existence Check
    if ($diagnostics['db_connection_working']) {
        $main_table = $wpdb->prefix . 'certificate_generator';
        $email_table = $wpdb->prefix . 'cert_email_logs';
        
        $diagnostics['main_table_exists'] = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $main_table)) == $main_table);
        $diagnostics['email_table_exists'] = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_table)) == $email_table);
        
        if (!$diagnostics['main_table_exists']) {
            $issues[] = 'Main certificate table does not exist';
            $fixes[] = 'Run table creation manually or reactivate plugin';
        }
        
        if (!$diagnostics['email_table_exists']) {
            $issues[] = 'Email log table does not exist';
            $fixes[] = 'Run table creation manually or reactivate plugin';
        }
    }
    
    // 7. Plugin Options Check
    $diagnostics['plugin_activated_option'] = get_option('certificate_generator_activated', false);
    $diagnostics['plugin_version_option'] = get_option('certificate_generator_version', false);
    
    // 8. Permissions Check
    $diagnostics['wp_content_writable'] = is_writable(WP_CONTENT_DIR);
    $diagnostics['plugin_dir_writable'] = is_writable(CERTIFICATE_GENERATOR_PATH);
    
    if (!$diagnostics['wp_content_writable']) {
        $issues[] = 'wp-content directory is not writable';
        $fixes[] = 'Set proper file permissions (755 for directories, 644 for files)';
    }
    
    return [
        'diagnostics' => $diagnostics,
        'issues' => array_unique($issues),
        'fixes' => array_unique($fixes),
        'environment' => $environment
    ];
}

/**
 * Attempt to fix common activation issues
 */
function certificate_generator_auto_fix() {
    $fixes_applied = [];
    $errors = [];
    
    try {
        global $wpdb;
        
        // Fix 1: Create missing tables
        if ($wpdb && is_object($wpdb)) {
            $main_table = $wpdb->prefix . 'certificate_generator';
            $email_table = $wpdb->prefix . 'cert_email_logs';
            
            // Create main table
            $main_sql = "CREATE TABLE IF NOT EXISTS `{$main_table}` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `student_name` varchar(255) NOT NULL,
                `certificate_data` text NOT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $result = $wpdb->query($main_sql);
            if ($result !== false) {
                $fixes_applied[] = 'Created main certificate table';
            } else {
                $errors[] = 'Failed to create main table: ' . $wpdb->last_error;
            }
            
            // Create email log table
            $email_sql = "CREATE TABLE IF NOT EXISTS `{$email_table}` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `recipient_email` varchar(255) NOT NULL,
                `recipient_name` varchar(255) NOT NULL,
                `certificate_id` mediumint(9) NOT NULL,
                `post_type` varchar(50) NOT NULL,
                `certificate_type` varchar(255) NOT NULL,
                `email_subject` varchar(500) NOT NULL,
                `email_body` text NOT NULL,
                `attachment_path` varchar(500) DEFAULT '',
                `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `status` varchar(20) DEFAULT 'pending',
                `error_message` text DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `recipient_email` (`recipient_email`),
                KEY `certificate_id` (`certificate_id`),
                KEY `sent_at` (`sent_at`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $result = $wpdb->query($email_sql);
            if ($result !== false) {
                $fixes_applied[] = 'Created email log table';
            } else {
                $errors[] = 'Failed to create email log table: ' . $wpdb->last_error;
            }
        }
        
        // Fix 2: Set essential options
        $essential_options = [
            'certificate_generator_version' => '4.0.1',
            'certificate_generator_activated' => current_time('mysql'),
            'certificate_generator_auto_send_enabled' => false
        ];
        
        foreach ($essential_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value, '', 'no');
                $fixes_applied[] = "Set option: {$option_name}";
            }
        }
        
        // Fix 3: Register post types if function exists
        if (function_exists('register_custom_post_types')) {
            register_custom_post_types();
            $fixes_applied[] = 'Registered custom post types';
        }
        
        // Fix 4: Flush rewrite rules
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
            $fixes_applied[] = 'Flushed rewrite rules';
        }
        
    } catch (Exception $e) {
        $errors[] = 'Auto-fix error: ' . $e->getMessage();
    }
    
    return [
        'fixes_applied' => $fixes_applied,
        'errors' => $errors
    ];
}

/**
 * Display diagnostic results in admin
 */
function certificate_generator_show_diagnostics() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $results = certificate_generator_run_diagnostics();
    
    echo '<div class="wrap">';
    echo '<h1>Certificate Generator - Activation Diagnostics</h1>';
    
    // Environment info
    echo '<div class="notice notice-info"><p><strong>Detected Environment:</strong> ' . esc_html($results['environment']) . '</p></div>';
    
    // Issues found
    if (!empty($results['issues'])) {
        echo '<div class="notice notice-error"><h3>Issues Found:</h3><ul>';
        foreach ($results['issues'] as $issue) {
            echo '<li>' . esc_html($issue) . '</li>';
        }
        echo '</ul></div>';
        
        echo '<div class="notice notice-warning"><h3>Suggested Fixes:</h3><ul>';
        foreach ($results['fixes'] as $fix) {
            echo '<li>' . esc_html($fix) . '</li>';
        }
        echo '</ul></div>';
        
        echo '<p><a href="' . wp_nonce_url(admin_url('admin-post.php?action=cert_gen_auto_fix'), 'cert_gen_auto_fix') . '" class="button button-primary">Attempt Auto-Fix</a></p>';
    } else {
        echo '<div class="notice notice-success"><p><strong>No issues found!</strong> Plugin should be working correctly.</p></div>';
    }
    
    // Detailed diagnostics
    echo '<h2>Detailed Diagnostics</h2>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>Check</th><th>Result</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($results['diagnostics'] as $key => $value) {
        echo '<tr>';
        echo '<td><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</strong></td>';
        echo '<td>';
        
        if (is_bool($value)) {
            echo $value ? '<span style="color: green;">✓ Yes</span>' : '<span style="color: red;">✗ No</span>';
        } elseif (is_array($value)) {
            if (empty($value)) {
                echo '<span style="color: green;">✓ None</span>';
            } else {
                echo '<span style="color: red;">✗ ' . esc_html(implode(', ', $value)) . '</span>';
            }
        } else {
            echo esc_html($value);
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
}

// Handle auto-fix action
add_action('admin_post_cert_gen_auto_fix', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'], 'cert_gen_auto_fix')) {
        wp_die('Unauthorized');
    }
    
    $results = certificate_generator_auto_fix();
    
    $redirect_url = admin_url('tools.php?page=cert-gen-diagnostics');
    $redirect_url = add_query_arg('auto_fix', 'completed', $redirect_url);
    $redirect_url = add_query_arg('fixes_count', count($results['fixes_applied']), $redirect_url);
    $redirect_url = add_query_arg('errors_count', count($results['errors']), $redirect_url);
    
    wp_redirect($redirect_url);
    exit;
});

// Add diagnostics page to admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Certificate Generator Diagnostics',
        'Cert Gen Diagnostics',
        'manage_options',
        'cert-gen-diagnostics',
        function() {
            if (isset($_GET['auto_fix']) && $_GET['auto_fix'] === 'completed') {
                $fixes_count = intval($_GET['fixes_count'] ?? 0);
                $errors_count = intval($_GET['errors_count'] ?? 0);
                
                if ($fixes_count > 0) {
                    echo '<div class="notice notice-success"><p>Auto-fix completed! Applied ' . $fixes_count . ' fixes.</p></div>';
                }
                
                if ($errors_count > 0) {
                    echo '<div class="notice notice-error"><p>Auto-fix encountered ' . $errors_count . ' errors. Check error logs for details.</p></div>';
                }
            }
            
            certificate_generator_show_diagnostics();
        }
    );
});

// Add quick diagnostic to admin bar
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $results = certificate_generator_run_diagnostics();
    $issues_count = count($results['issues']);
    
    $wp_admin_bar->add_node([
        'id' => 'cert-gen-diagnostics',
        'title' => 'Cert Gen: ' . ($issues_count > 0 ? $issues_count . ' issues' : 'OK'),
        'href' => admin_url('tools.php?page=cert-gen-diagnostics'),
        'meta' => [
            'class' => $issues_count > 0 ? 'cert-gen-issues' : 'cert-gen-ok'
        ]
    ]);
}, 100);

// Add CSS for admin bar indicator
add_action('admin_head', function() {
    echo '<style>
    #wp-admin-bar-cert-gen-diagnostics .cert-gen-issues { background-color: #dc3232 !important; }
    #wp-admin-bar-cert-gen-diagnostics .cert-gen-ok { background-color: #46b450 !important; }
    </style>';
});

?>