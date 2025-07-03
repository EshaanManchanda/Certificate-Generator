<?php
/**
 * Enhanced Activation Handler for Certificate Generator
 * Handles both local development and live server environments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

/**
 * Smart activation function that detects environment and uses appropriate method
 */
function certificate_generator_smart_activate() {
    // Detect environment type
    $environment = certificate_generator_detect_environment();
    
    error_log("Certificate Generator: Detected environment - {$environment}");
    
    switch ($environment) {
        case 'local':
            return certificate_generator_local_safe_activate();
        case 'hostinger':
            return certificate_generator_hostinger_safe_activate();
        case 'production':
        default:
            return certificate_generator_production_safe_activate();
    }
}

/**
 * Detect the hosting environment
 */
function certificate_generator_detect_environment() {
    // Check for local development indicators
    $local_indicators = [
        isset($_SERVER['HTTP_HOST']) && (
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
            strpos($_SERVER['HTTP_HOST'], '.test') !== false ||
            strpos($_SERVER['HTTP_HOST'], '.dev') !== false
        ),
        isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']),
        defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development',
        defined('WP_LOCAL_DEV') && WP_LOCAL_DEV
    ];
    
    if (array_filter($local_indicators)) {
        return 'local';
    }
    
    // Check for Hostinger indicators
    $hostinger_indicators = [
        isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'hostinger') !== false,
        isset($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], 'hostinger') !== false,
        isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false
    ];
    
    if (array_filter($hostinger_indicators)) {
        return 'hostinger';
    }
    
    return 'production';
}

/**
 * Local development environment activation
 */
function certificate_generator_local_safe_activate() {
    $errors = [];
    $success_steps = [];
    
    try {
        // Step 1: Basic WordPress check
        if (!defined('ABSPATH') || !function_exists('add_option')) {
            throw new Exception('WordPress environment not loaded');
        }
        $success_steps[] = 'WordPress environment verified';
        
        // Step 2: Database connection with retry logic
        global $wpdb;
        if (!$wpdb || !is_object($wpdb)) {
            throw new Exception('Database object not available');
        }
        
        // Test database connection with multiple attempts
        $connection_attempts = 0;
        $max_attempts = 3;
        $connection_successful = false;
        
        while ($connection_attempts < $max_attempts && !$connection_successful) {
            try {
                $test_result = $wpdb->get_var("SELECT 1");
                if ($test_result == 1) {
                    $connection_successful = true;
                    $success_steps[] = 'Database connection verified';
                } else {
                    $connection_attempts++;
                    if ($connection_attempts < $max_attempts) {
                        sleep(1); // Wait 1 second before retry
                    }
                }
            } catch (Exception $e) {
                $connection_attempts++;
                if ($connection_attempts >= $max_attempts) {
                    $errors[] = 'Database connection failed after ' . $max_attempts . ' attempts: ' . $e->getMessage();
                    // Continue with activation but log the issue
                }
            }
        }
        
        // Step 3: Create tables (with fallback if DB connection failed)
        if ($connection_successful) {
            certificate_generator_create_tables($wpdb, $success_steps, $errors);
        } else {
            $errors[] = 'Skipping table creation due to database connection issues';
        }
        
        // Step 4: Set essential options
        certificate_generator_set_default_options($success_steps, $errors);
        
        // Step 5: Register post types
        certificate_generator_register_post_types($success_steps, $errors);
        
        // Step 6: Flush rewrite rules
        certificate_generator_flush_rewrite_rules($success_steps, $errors);
        
        // Log results
        $log_message = 'Certificate Generator local activation completed. Steps: ' . implode(', ', $success_steps);
        if (!empty($errors)) {
            $log_message .= ' Errors: ' . implode(', ', $errors);
        }
        error_log($log_message);
        
        return empty($errors) || count($success_steps) > count($errors);
        
    } catch (Exception $e) {
        error_log('Certificate Generator local activation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Production environment activation (generic)
 */
function certificate_generator_production_safe_activate() {
    $errors = [];
    $success_steps = [];
    
    try {
        // Step 1: WordPress environment check
        if (!defined('ABSPATH') || !function_exists('add_option')) {
            throw new Exception('WordPress environment not loaded');
        }
        $success_steps[] = 'WordPress environment verified';
        
        // Step 2: Database check
        global $wpdb;
        if (!$wpdb || !is_object($wpdb)) {
            throw new Exception('Database not available');
        }
        
        $test_result = $wpdb->get_var("SELECT 1");
        if ($test_result != 1) {
            throw new Exception('Database test failed');
        }
        $success_steps[] = 'Database connection verified';
        
        // Step 3: Create tables
        certificate_generator_create_tables($wpdb, $success_steps, $errors);
        
        // Step 4: Set options
        certificate_generator_set_default_options($success_steps, $errors);
        
        // Step 5: Register post types
        certificate_generator_register_post_types($success_steps, $errors);
        
        // Step 6: Flush rewrite rules
        certificate_generator_flush_rewrite_rules($success_steps, $errors);
        
        // Log success
        error_log('Certificate Generator production activation completed. Steps: ' . implode(', ', $success_steps));
        
        return true;
        
    } catch (Exception $e) {
        error_log('Certificate Generator production activation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to create database tables
 */
function certificate_generator_create_tables($wpdb, &$success_steps, &$errors) {
    // Main table
    $main_table = $wpdb->prefix . 'certificate_generator';
    $main_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $main_table));
    
    if ($main_table_exists != $main_table) {
        $main_sql = "CREATE TABLE IF NOT EXISTS `{$main_table}` (
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `student_name` varchar(255) NOT NULL,
            `certificate_data` text NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $result = $wpdb->query($main_sql);
        if ($result === false) {
            $errors[] = 'Failed to create main table: ' . $wpdb->last_error;
        } else {
            $success_steps[] = 'Main table created';
        }
    } else {
        $success_steps[] = 'Main table exists';
    }
    
    // Email log table
    $email_table = $wpdb->prefix . 'cert_email_logs';
    $email_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_table));
    
    if ($email_table_exists != $email_table) {
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
        if ($result === false) {
            $errors[] = 'Failed to create email log table: ' . $wpdb->last_error;
        } else {
            $success_steps[] = 'Email log table created';
        }
    } else {
        $success_steps[] = 'Email log table exists';
    }
}

/**
 * Helper function to set default options
 */
function certificate_generator_set_default_options(&$success_steps, &$errors) {
    $default_options = [
        'certificate_generator_version' => '4.0.1',
        'certificate_generator_activated' => current_time('mysql'),
        'certificate_generator_auto_send_enabled' => false,
        'certificate_generator_email_subject' => 'Your Certificate is Ready',
        'certificate_generator_email_message' => 'Dear {name},\n\nYour certificate is ready for download.\n\nBest regards,\nThe Certificate Team'
    ];
    
    foreach ($default_options as $option_name => $option_value) {
        try {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value, '', 'no');
            }
        } catch (Exception $e) {
            $errors[] = "Failed to set option {$option_name}: " . $e->getMessage();
        }
    }
    
    $success_steps[] = 'Default options set';
}

/**
 * Helper function to register post types
 */
function certificate_generator_register_post_types(&$success_steps, &$errors) {
    if (function_exists('register_custom_post_types')) {
        try {
            register_custom_post_types();
            $success_steps[] = 'Custom post types registered';
        } catch (Exception $e) {
            $errors[] = 'Failed to register post types: ' . $e->getMessage();
        }
    } else {
        $errors[] = 'register_custom_post_types function not available';
    }
}

/**
 * Helper function to flush rewrite rules
 */
function certificate_generator_flush_rewrite_rules(&$success_steps, &$errors) {
    if (function_exists('flush_rewrite_rules')) {
        try {
            flush_rewrite_rules(false);
            $success_steps[] = 'Rewrite rules flushed';
        } catch (Exception $e) {
            $errors[] = 'Failed to flush rewrite rules: ' . $e->getMessage();
        }
    }
}

/**
 * Get activation status for any environment
 */
function certificate_generator_get_enhanced_activation_status() {
    $status = get_option('certificate_generator_activation_status', []);
    $status['environment'] = certificate_generator_detect_environment();
    $status['last_check'] = current_time('mysql');
    
    return $status;
}

/**
 * Force reactivation if needed
 */
function certificate_generator_force_reactivation() {
    if (current_user_can('activate_plugins')) {
        // Clear any existing activation status
        delete_option('certificate_generator_activation_status');
        
        // Run smart activation
        $result = certificate_generator_smart_activate();
        
        // Store result
        update_option('certificate_generator_activation_status', [
            'success' => $result,
            'timestamp' => current_time('mysql'),
            'method' => 'force_reactivation',
            'environment' => certificate_generator_detect_environment()
        ]);
        
        return $result;
    }
    
    return false;
}

// Hook the smart activation to WordPress activation
register_activation_hook(CERTIFICATE_GENERATOR_PATH . 'certificate-generator.php', 'certificate_generator_smart_activate');

// Add admin notice for activation status
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        $status = certificate_generator_get_enhanced_activation_status();
        
        if (isset($status['success']) && !$status['success']) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Certificate Generator:</strong> Plugin activation encountered issues. ';
            echo '<a href="' . admin_url('tools.php?page=cert-gen-activation-status') . '">View details</a> or ';
            echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=cert_gen_force_reactivation'), 'cert_gen_reactivate') . '">Force reactivation</a>.</p>';
            echo '</div>';
        }
    }
});

// Handle force reactivation
add_action('admin_post_cert_gen_force_reactivation', function() {
    if (!current_user_can('activate_plugins') || !wp_verify_nonce($_GET['_wpnonce'], 'cert_gen_reactivate')) {
        wp_die('Unauthorized');
    }
    
    $result = certificate_generator_force_reactivation();
    
    $redirect_url = admin_url('tools.php?page=cert-gen-activation-status');
    $redirect_url = add_query_arg('reactivation', $result ? 'success' : 'failed', $redirect_url);
    
    wp_redirect($redirect_url);
    exit;
});

// Add activation status page
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Certificate Generator Activation Status',
        'Cert Gen Activation',
        'manage_options',
        'cert-gen-activation-status',
        function() {
            $status = certificate_generator_get_enhanced_activation_status();
            
            echo '<div class="wrap">';
            echo '<h1>Certificate Generator - Activation Status</h1>';
            
            if (isset($_GET['reactivation'])) {
                if ($_GET['reactivation'] === 'success') {
                    echo '<div class="notice notice-success"><p>Reactivation completed successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Reactivation failed. Check error logs for details.</p></div>';
                }
            }
            
            echo '<table class="widefat">';
            echo '<thead><tr><th>Property</th><th>Value</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($status as $key => $value) {
                echo '<tr>';
                echo '<td><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</strong></td>';
                echo '<td>' . esc_html(is_array($value) ? json_encode($value) : $value) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            
            echo '<p style="margin-top: 20px;">';
            echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=cert_gen_force_reactivation'), 'cert_gen_reactivate') . '" class="button button-primary">Force Reactivation</a>';
            echo '</p>';
            
            echo '</div>';
        }
    );
});
?>