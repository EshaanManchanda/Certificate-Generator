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


// Enqueue CSS and JS for Admin UI
function custom_admin_assets() {
    wp_enqueue_style('custom-admin-css', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
    wp_enqueue_script('custom-admin-js', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', ['jquery'], null, true);
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
];

foreach ($required_files as $file) {
    $path = CERTIFICATE_GENERATOR_PATH . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("Certificate Generator: Missing required file - $file");
    }
}

// Plugin activation hook
function certificate_generator_activate() {
    register_custom_post_types(); // Register the custom post type
    flush_rewrite_rules(); // Flush rewrite rules for permalinks

    // Create default database table (if needed)
    global $wpdb;
    $table_name = $wpdb->prefix . 'certificate_generator';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            student_name varchar(255) NOT NULL,
            certificate_data text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
register_activation_hook(__FILE__, 'certificate_generator_activate');

// Plugin deactivation hook
function certificate_generator_deactivate() {
    flush_rewrite_rules(); // Flush rewrite rules on deactivation
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