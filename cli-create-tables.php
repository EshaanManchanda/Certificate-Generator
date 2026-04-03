<?php
/**
 * CLI script to create missing email tables
 *
 * Usage: php cli-create-tables.php
 *
 * IMPORTANT: Delete this file after running it once!
 */

// Load WordPress
define('WP_USE_THEMES', false);
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: Cannot find wp-load.php at: $wp_load_path\n");
}
require_once($wp_load_path);

echo "\n===========================================\n";
echo "Certificate Generator - Create Email Tables\n";
echo "===========================================\n\n";

// Load the required files
require_once(dirname(__FILE__) . '/includes/email-log.php');
require_once(dirname(__FILE__) . '/includes/email-queue.php');

echo "Step 1: Creating Email Log Table...\n";
certificate_generator_create_email_log_table();

global $wpdb;
$email_log_table = $wpdb->prefix . 'cert_email_logs';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_log_table)) == $email_log_table) {
    echo "✓ Email log table created successfully!\n\n";
} else {
    echo "✗ Email log table creation failed.\n\n";
}

echo "Step 2: Creating Email Queue Table...\n";
certificate_generator_create_email_queue_table();

$email_queue_table = $wpdb->prefix . 'cert_email_queue';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_queue_table)) == $email_queue_table) {
    echo "✓ Email queue table created successfully!\n\n";
} else {
    echo "✗ Email queue table creation failed.\n\n";
}

echo "===========================================\n";
echo "✓ All Done!\n";
echo "===========================================\n\n";
echo "Next Steps:\n";
echo "1. Go to Settings → Bulk Send Certificates\n";
echo "2. Verify recipients are displayed\n";
echo "3. DELETE THIS FILE (cli-create-tables.php)\n\n";
