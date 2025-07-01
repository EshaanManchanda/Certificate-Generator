<?php
/**
 * API Key Database Check
 * 
 * This script directly checks the WordPress database for API key settings.
 * Access this file directly via the browser to see the raw database values.
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly without ABSPATH
}

// Security check - only allow admin users to view this page
// if (!current_user_can('manage_options')) {
//     echo '<div class="notice notice-error is-dismissible"><p>You do not have sufficient permissions to access this page.</p></div>';
//     return;
// }

// Get the API key settings directly from the database
$api_key = get_option('certificate_generator_api_key', 'Not set');
$api_key_enabled = get_option('certificate_generator_api_key_enabled', false);

// Get the raw option values from the database
global $wpdb;
$api_key_raw = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    'certificate_generator_api_key'
));

$api_key_enabled_raw = $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
    'certificate_generator_api_key_enabled'
));

// Output the results
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Key Database Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #fff; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; }
        h1 { color: #23282d; }
        h2 { color: #23282d; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        code { background: #f5f5f5; padding: 2px 5px; border: 1px solid #eee; }
        .success { color: green; }
        .error { color: red; }
        .button { display: inline-block; background: #0073aa; color: white; padding: 8px 12px; text-decoration: none; border-radius: 3px; }
        .button:hover { background: #005177; }
    </style>
</head>
<body>
    <div class="container">
        <h1>API Key Database Check</h1>
        
        <div class="card">
            <h2>API Key Settings</h2>
            <p><strong>API Key (get_option):</strong> <?php echo empty($api_key) || $api_key === 'Not set' ? '<span class="error">Not set</span>' : '<span class="success">Set</span> (' . substr($api_key, 0, 8) . '...' . substr($api_key, -8) . ')'; ?></p>
            <p><strong>Full API Key:</strong> <code><?php echo esc_html($api_key); ?></code></p>
            <p><strong>API Key Enabled (get_option):</strong> <?php echo $api_key_enabled ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></p>
        </div>
        
        <div class="card">
            <h2>Raw Database Values</h2>
            <p><strong>API Key (raw):</strong> <?php echo empty($api_key_raw) ? '<span class="error">Not found in database</span>' : '<span class="success">Found in database</span>'; ?></p>
            <p><strong>API Key Value:</strong> <code><?php echo esc_html($api_key_raw); ?></code></p>
            <p><strong>API Key Enabled (raw):</strong> <?php echo $api_key_enabled_raw === null ? '<span class="error">Not found in database</span>' : '<span class="success">Found in database</span>'; ?></p>
            <p><strong>API Key Enabled Value:</strong> <code><?php echo esc_html($api_key_enabled_raw); ?></code></p>
        </div>
        
        <div class="card">
            <h2>Debug Information</h2>
            <p><strong>WordPress Option Table:</strong> <?php echo $wpdb->options; ?></p>
            <p><strong>API Key Option Name:</strong> certificate_generator_api_key</p>
            <p><strong>API Key Enabled Option Name:</strong> certificate_generator_api_key_enabled</p>
            <p><a href="<?php echo admin_url('options-general.php?page=certificate_generator_settings&tab=api'); ?>" class="button">Go to API Settings</a></p>
            <p><a href="<?php echo admin_url('options-general.php?page=certificate-generator-api-debug'); ?>" class="button">Go to API Debug Page</a></p>
        </div>
    </div>
</body>
</html>