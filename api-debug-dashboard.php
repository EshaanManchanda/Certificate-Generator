<?php
/**
 * API Debug Dashboard
 * 
 * This file provides a central dashboard for all API debugging tools.
 * IMPORTANT: This file should be removed in production environments.
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function certificate_generator_api_debug_dashboard() {
    // Check if user is logged in and has admin capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get current API settings
    $api_key = get_option('certificate_generator_api_key', '');
    $api_enabled = get_option('certificate_generator_api_key_enabled', false);

    // Get raw database values
    global $wpdb;
    $api_key_raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'certificate_generator_api_key'");
    $api_enabled_raw = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'certificate_generator_api_key_enabled'");

    ob_start();
    ?>
    <div class="wrap">
        <h1>API Debug Dashboard</h1>
        <p>This dashboard provides a central location for all API debugging tools and information.</p>
        
        <div class="notice notice-warning">
            <p><strong>Warning:</strong> These debug tools are for development and troubleshooting purposes only. They should be removed in production environments.</p>
        </div>
        
        <h2>API Status Overview</h2>
        <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); grid-gap: 20px;">
            <div class="postbox">
                <h3 class="hndle">API Access</h3>
                <div class="inside">
                    <?php if ($api_enabled): ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>Enabled</strong></p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Disabled</strong></p>
                        <p>API access is currently disabled. Enable it in the settings.</p>
                    <?php endif; ?>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate_generator_settings&tab=api')); ?>" class="button button-secondary">Configure</a></p>
                </div>
            </div>
            
            <div class="postbox">
                <h3 class="hndle">API Key</h3>
                <div class="inside">
                    <?php if ($api_key): ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>Generated</strong></p>
                        <p>Key: <code><?php echo esc_html(substr($api_key, 0, 8) . '...' . substr($api_key, -8)); ?></code></p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Not Generated</strong></p>
                        <p>No API key has been generated yet.</p>
                    <?php endif; ?>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-key-generator')); ?>" class="button button-secondary">Manage Key</a></p>
                </div>
            </div>
            
            <div class="postbox">
                <h3 class="hndle">REST API</h3>
                <div class="inside">
                    <?php 
                    $rest_available = site_url('/wp-json/') ? true : false;
                    if ($rest_available): 
                    ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>Available</strong></p>
                        <p>REST API is properly configured.</p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Unavailable</strong></p>
                        <p>REST API appears to be disabled or misconfigured.</p>
                    <?php endif; ?>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-key-testing')); ?>" class="button button-secondary">Test API</a></p>
                </div>
            </div>
            
            <div class="postbox">
                <h3 class="hndle">Database Status</h3>
                <div class="inside">
                    <?php 
                    $db_status_ok = ($api_key_raw !== null && ($api_enabled_raw !== null || $api_enabled_raw === '0'));
                    if ($db_status_ok): 
                    ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>OK</strong></p>
                        <p>Database options are properly stored.</p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Issues Detected</strong></p>
                        <p>There may be issues with the database options.</p>
                    <?php endif; ?>
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=certificate-generator-api-key-check')); ?>" class="button button-secondary">Check DB</a></p>
                </div>
            </div>
        </div>
        
        <div class="postbox">
            <h3 class="hndle">API Settings Details</h3>
            <div class="inside">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Value</th>
                            <th>Raw Database Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>API Access Enabled</td>
                            <td><?php echo $api_enabled ? 'Yes' : 'No'; ?></td>
                            <td><code><?php echo esc_html(var_export($api_enabled_raw, true)); ?></code></td>
                        </tr>
                        <tr>
                            <td>API Key</td>
                            <td>
                                <?php if ($api_key): ?>
                                    <div class="api-key" style="font-family: monospace; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px; word-break: break-all;">
                                        <?php echo esc_html($api_key); ?>
                                    </div>
                                <?php else: ?>
                                    <em>No API key has been generated</em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo $api_key_raw ? esc_html(strlen($api_key_raw) > 20 ? substr($api_key_raw, 0, 10) . '...' . substr($api_key_raw, -10) : $api_key_raw) : 'NULL'; ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="postbox">
            <h3 class="hndle">API Endpoints</h3>
            <div class="inside">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Method</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code><?php echo esc_url(site_url('/wp-json/certificate-generator/v1/health-check')); ?></code></td>
                            <td>GET</td>
                            <td>Health check endpoint to verify API connectivity</td>
                        </tr>
                        <tr>
                            <td><code><?php echo esc_url(site_url('/wp-json/certificate-generator/v1/issue-certificate')); ?></code></td>
                            <td>POST</td>
                            <td>Issue a new certificate</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="postbox">
            <h3 class="hndle">Debug Tools</h3>
            <div class="inside">
                <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); grid-gap: 20px;">
                    <div class="card">
                        <h4>API Key Generator</h4>
                        <p>Generate or regenerate API keys directly.</p>
                        <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-key-generator')); ?>" class="button">Open Tool</a></p>
                    </div>
                    
                    <div class="card">
                        <h4>API Key Testing</h4>
                        <p>Test the API endpoints with your current API key.</p>
                        <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-key-testing')); ?>" class="button">Open Tool</a></p>
                    </div>
                    
                    <div class="card">
                        <h4>API Key Check</h4>
                        <p>Check the raw database values for API settings.</p>
                        <p><a href="<?php echo esc_url(admin_url('admin.php?page=certificate-generator-api-key-check')); ?>" class="button">Open Tool</a></p>
                    </div>
                    
                    <div class="card">
                        <h4>API Settings</h4>
                        <p>Configure API settings in the plugin settings page.</p>
                        <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate_generator_settings&tab=api')); ?>" class="button">Open Settings</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="postbox">
            <h3 class="hndle">Troubleshooting Information</h3>
            <div class="inside">
                <h4>Common Issues</h4>
                <ol>
                    <li><strong>API Key Not Generating:</strong> Check for JavaScript errors in the browser console. The new API key fix script should resolve this issue.</li>
                    <li><strong>API Key Not Saving:</strong> Verify that the form is submitting correctly and that the database is functioning properly.</li>
                    <li><strong>API Access Not Working:</strong> Ensure that both the API key is generated and API access is enabled.</li>
                    <li><strong>REST API Errors:</strong> Check for security plugins that might be blocking REST API access.</li>
                </ol>
                
                <h4>JavaScript Console Commands</h4>
                <p>You can use these commands in your browser's developer console to debug API key issues:</p>
                <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 3px; overflow: auto; max-height: 400px;">
// Check if API key field exists
console.log('API Key Field:', document.getElementById('certificate_generator_api_key'));

// Check if API key enabled field exists
console.log('API Key Enabled Field:', document.getElementById('certificate_generator_api_key_enabled'));

// Generate a test API key
function generateTestKey() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < 64; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    console.log('Generated Test Key:', result);
    return result;
}
generateTestKey();
                </pre>
                
                <h4>PHP Debugging</h4>
                <p>Add this code to your theme's functions.php file for additional debugging:</p>
                <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 3px; overflow: auto; max-height: 400px;">
// Debug API key settings
add_action('admin_footer', function() {
    if (is_admin()) {
        echo '&lt;div style="display:none;"&gt;';
        echo 'API Key: ' . esc_html(get_option('certificate_generator_api_key', 'Not set')) . '&lt;br&gt;';
        echo 'API Enabled: ' . esc_html(var_export(get_option('certificate_generator_api_key_enabled', false), true)) . '&lt;br&gt;';
        echo '&lt;/div&gt;';
    }
});
                </pre>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}