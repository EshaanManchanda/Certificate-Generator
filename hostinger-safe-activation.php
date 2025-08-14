<?php
/**
 * Hostinger-Safe Activation Script
 * A minimal, production-safe activation function for Hostinger hosting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

/**
 * Ultra-safe activation function for Hostinger hosting
 * This function uses minimal WordPress dependencies and maximum error handling
 */
function certificate_generator_hostinger_safe_activate() {
    // Initialize error tracking
    $errors = [];
    $success_steps = [];
    
    try {
        // Step 1: Verify basic WordPress environment
        if (!defined('ABSPATH') || !function_exists('add_option')) {
            throw new Exception('WordPress environment not properly loaded');
        }
        $success_steps[] = 'WordPress environment verified';
        
        // Step 2: Check database connection
        global $wpdb;
        if (!$wpdb || !is_object($wpdb)) {
            throw new Exception('Database connection not available');
        }
        
        // Test database with simple query
        $test_result = $wpdb->get_var("SELECT 1");
        if ($test_result != 1) {
            throw new Exception('Database test query failed');
        }
        $success_steps[] = 'Database connection verified';
        
        // Step 3: Create main table with minimal SQL
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
                $success_steps[] = 'Main table created successfully';
            }
        } else {
            $success_steps[] = 'Main table already exists';
        }
        
        // Step 4: Create email log table with minimal SQL
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
                $success_steps[] = 'Email log table created successfully';
            }
        } else {
            $success_steps[] = 'Email log table already exists';
        }
        
        // Step 5: Set essential options only
        $essential_options = [
            'certificate_generator_version' => '1.0.0',
            'certificate_generator_activated' => current_time('mysql'),
            'certificate_generator_auto_send_enabled' => '0'
        ];
        
        foreach ($essential_options as $option_name => $option_value) {
            try {
                if (get_option($option_name) === false) {
                    add_option($option_name, $option_value, '', 'no');
                    $success_steps[] = "Option {$option_name} set";
                }
            } catch (Exception $e) {
                $errors[] = "Failed to set option {$option_name}: " . $e->getMessage();
            }
        }
        
        // Step 6: Register custom post type (if function exists)
        if (function_exists('register_custom_post_types')) {
            try {
                register_custom_post_types();
                $success_steps[] = 'Custom post types registered';
            } catch (Exception $e) {
                $errors[] = 'Failed to register custom post types: ' . $e->getMessage();
            }
        }
        
        // Step 7: Flush rewrite rules (safely)
        if (function_exists('flush_rewrite_rules')) {
            try {
                flush_rewrite_rules(false); // Don't hard flush to avoid issues
                $success_steps[] = 'Rewrite rules flushed';
            } catch (Exception $e) {
                $errors[] = 'Failed to flush rewrite rules: ' . $e->getMessage();
            }
        }
        
        // Log successful activation
        $log_message = 'Certificate Generator activated successfully on Hostinger. Steps completed: ' . implode(', ', $success_steps);
        if (!empty($errors)) {
            $log_message .= ' Errors encountered: ' . implode(', ', $errors);
        }
        error_log($log_message);
        
        // Store activation status
        update_option('certificate_generator_activation_status', [
            'success' => true,
            'timestamp' => current_time('mysql'),
            'steps_completed' => $success_steps,
            'errors' => $errors,
            'hosting_environment' => 'hostinger'
        ]);
        
        return true;
        
    } catch (Exception $e) {
        // Log the main error
        $error_message = 'Certificate Generator activation failed on Hostinger: ' . $e->getMessage();
        if (!empty($success_steps)) {
            $error_message .= ' Steps completed before failure: ' . implode(', ', $success_steps);
        }
        error_log($error_message);
        
        // Store failure status
        update_option('certificate_generator_activation_status', [
            'success' => false,
            'timestamp' => current_time('mysql'),
            'error' => $e->getMessage(),
            'steps_completed' => $success_steps,
            'errors' => $errors,
            'hosting_environment' => 'hostinger'
        ]);
        
        // Don't throw the exception to prevent activation failure
        // Instead, log it and continue
        return false;
    }
}

/**
 * Get activation status for debugging
 */
function certificate_generator_get_activation_status() {
    return get_option('certificate_generator_activation_status', [
        'success' => false,
        'error' => 'No activation attempt recorded'
    ]);
}

/**
 * Display activation status in admin
 */
function certificate_generator_show_activation_status() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $status = certificate_generator_get_activation_status();
    
    echo '<div class="wrap">';
    echo '<h1>Certificate Generator - Activation Status</h1>';
    
    if ($status['success']) {
        echo '<div class="notice notice-success"><p><strong>Plugin activated successfully!</strong></p></div>';
    } else {
        echo '<div class="notice notice-error"><p><strong>Plugin activation encountered issues.</strong></p></div>';
    }
    
    echo '<table class="widefat">';
    echo '<thead><tr><th>Property</th><th>Value</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($status as $key => $value) {
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

// Add admin menu for activation status
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Certificate Generator Status',
        'Cert Gen Status',
        'manage_options',
        'cert-gen-activation-status',
        'certificate_generator_show_activation_status'
    );
});

?>