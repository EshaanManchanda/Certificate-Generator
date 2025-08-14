<?php
/**
 * API Key Generation Debug Tool
 * 
 * This file provides a direct way to generate and save API keys for debugging purposes.
 * IMPORTANT: This file should be removed in production environments.
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly without ABSPATH
}

function certificate_generator_api_key_generator() {
    ob_start();
    
    // Process API key generation if requested
    $message = '';
    $api_key = get_option('certificate_generator_api_key', '');
    $api_enabled = get_option('certificate_generator_api_key_enabled', false);

    if (isset($_POST['action']) && isset($_POST['api_key_nonce']) && wp_verify_nonce($_POST['api_key_nonce'], 'certificate_generator_api_key_action')) {
        if ($_POST['action'] === 'generate_key') {
            // Generate a new API key
            $api_key = certificate_generator_generate_api_key();
            update_option('certificate_generator_api_key', $api_key);
            $message = 'API key generated successfully!';
        } else if ($_POST['action'] === 'toggle_api') {
            // Toggle API access
            $api_enabled = !$api_enabled;
            update_option('certificate_generator_api_key_enabled', $api_enabled);
            $message = 'API access ' . ($api_enabled ? 'enabled' : 'disabled') . ' successfully!';
        }
    }

// Ensure the function is not redeclared if it's already defined elsewhere (e.g., in api-key-debug.php)
if (!function_exists('certificate_generator_generate_api_key')) {
    /**
     * Generate a secure random API key
     */
    function certificate_generator_generate_api_key() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $key = '';
        for ($i = 0; $i < 64; $i++) {
            $key .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $key;
    }
}

    // Output the HTML
    ?>
    <div class="wrap">
        <style>
            .api-key {
                font-family: monospace;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 3px;
                word-break: break-all;
            }
        </style>
        <div class="card">
            <h1>API Key Generation Debug Tool</h1>
            <p>This tool allows you to directly generate and manage API keys for the Certificate Generator plugin.</p>
            
            <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            
            <h2>Current API Settings</h2>
            <table class="widefat">
                <tr>
                    <th>Setting</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>API Access Enabled</td>
                    <td><?php echo $api_enabled ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td>API Key</td>
                    <td>
                        <?php if ($api_key): ?>
                            <div class="api-key"><?php echo esc_html($api_key); ?></div>
                        <?php else: ?>
                            <em>No API key has been generated</em>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <h2>Actions</h2>
            <form method="post">
                <?php wp_nonce_field('certificate_generator_api_key_action', 'api_key_nonce'); ?>
                <p>
                    <input type="hidden" name="action" value="generate_key">
                    <button type="submit" class="button"><?php echo $api_key ? 'Regenerate API Key' : 'Generate API Key'; ?></button>
                </p>
            </form>
            
            <form method="post">
                <?php wp_nonce_field('certificate_generator_api_key_action', 'api_key_nonce'); ?>
                <p>
                    <input type="hidden" name="action" value="toggle_api">
                    <button type="submit" class="button button-secondary">
                        <?php echo $api_enabled ? 'Disable API Access' : 'Enable API Access'; ?>
                    </button>
                </p>
            </form>
            
            <h2>Database Information</h2>
            <p>Raw database values:</p>
            <pre>
    <?php
    // Get raw database values
    global $wpdb;
    $api_key_raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'certificate_generator_api_key'");
    $api_enabled_raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'certificate_generator_api_key_enabled'");

    echo "certificate_generator_api_key: " . var_export($api_key_raw, true) . "\n";
    echo "certificate_generator_api_key_enabled: " . var_export($api_enabled_raw, true) . "\n";
    ?>
            </pre>
            
            <h2>Navigation</h2>
            <p>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=certificate_generator_settings&tab=api')); ?>" class="button button-secondary">Go to API Settings</a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-debug')); ?>" class="button button-secondary">Go to API Debug Page</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=certificate-generator-api-key-check')); ?>" class="button button-secondary">Go to API Key Check</a>
            </p>
        </div>
        
        <div class="card">
            <h2>Troubleshooting Information</h2>
            <p>If you're experiencing issues with API key generation or storage, check the following:</p>
            <ol>
                <li>Ensure the WordPress options table is functioning correctly</li>
                <li>Check for any JavaScript errors in the browser console</li>
                <li>Verify that the API settings form is submitting correctly</li>
                <li>Check if any security plugins might be interfering with the AJAX requests</li>
            </ol>
            <p><strong>Warning:</strong> This debug tool should be removed in production environments.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}