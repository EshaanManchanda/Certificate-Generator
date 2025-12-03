<?php
/**
 * Temporary script to create missing email tables
 *
 * IMPORTANT: Delete this file after running it once!
 *
 * To use: Visit this URL in your browser:
 * http://yoursite.local/wp-content/plugins/Certificate-Generator/create-email-tables.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Security check - only allow admin users
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Unauthorized access. Please log in as an administrator.');
}

echo '<h1>Certificate Generator - Create Email Tables</h1>';
echo '<p>Creating missing email tables...</p>';

// Load the required files
require_once(dirname(__FILE__) . '/includes/email-log.php');
require_once(dirname(__FILE__) . '/includes/email-queue.php');

echo '<h2>Step 1: Creating Email Log Table</h2>';
certificate_generator_create_email_log_table();

global $wpdb;
$email_log_table = $wpdb->prefix . 'cert_email_logs';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_log_table)) == $email_log_table) {
    echo '<p style="color: green;">✓ Email log table created successfully!</p>';
} else {
    echo '<p style="color: red;">✗ Email log table creation failed.</p>';
}

echo '<h2>Step 2: Creating Email Queue Table</h2>';
certificate_generator_create_email_queue_table();

$email_queue_table = $wpdb->prefix . 'cert_email_queue';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $email_queue_table)) == $email_queue_table) {
    echo '<p style="color: green;">✓ Email queue table created successfully!</p>';
} else {
    echo '<p style="color: red;">✗ Email queue table creation failed.</p>';
}

echo '<h2>Verification</h2>';
echo '<p>Checking table structure...</p>';

// Verify email log table structure
$log_columns = $wpdb->get_results("SHOW COLUMNS FROM $email_log_table");
echo '<h3>Email Log Table Columns:</h3>';
echo '<ul>';
foreach ($log_columns as $column) {
    echo '<li>' . esc_html($column->Field) . ' (' . esc_html($column->Type) . ')</li>';
}
echo '</ul>';

// Verify email queue table structure
$queue_columns = $wpdb->get_results("SHOW COLUMNS FROM $email_queue_table");
echo '<h3>Email Queue Table Columns:</h3>';
echo '<ul>';
foreach ($queue_columns as $column) {
    echo '<li>' . esc_html($column->Field) . ' (' . esc_html($column->Type) . ')</li>';
}
echo '</ul>';

echo '<hr>';
echo '<h2 style="color: green;">✓ All Done!</h2>';
echo '<p><strong>Next Steps:</strong></p>';
echo '<ol>';
echo '<li>Go to Settings → Bulk Send Certificates and verify recipients are displayed</li>';
echo '<li>DELETE THIS FILE (create-email-tables.php) for security</li>';
echo '</ol>';
echo '<p><a href="' . admin_url('options-general.php?page=certificate-bulk-send') . '" class="button button-primary">Go to Bulk Send Page</a></p>';
