<?php
/**
 * API Key Testing Tool
 * 
 * This file provides a direct way to test the API key with the REST API endpoints.
 * IMPORTANT: This file should be removed in production environments.
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function certificate_generator_api_test_page() {
    // Check if user is logged in and has admin capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get current API settings
    $api_key = get_option('certificate_generator_api_key', '');
    $api_enabled = get_option('certificate_generator_api_key_enabled', false);
    
    // Process test request if submitted
    $test_result = null;
    $test_error = null;
    
    if (isset($_POST['action']) && $_POST['action'] === 'test_api') {
        check_admin_referer('certificate_generator_api_test', 'api_test_nonce');
        // Get the site URL for the REST API
        $rest_url = site_url('/wp-json/certificate-generator/v1/health-check');
        
        // Set up the request arguments
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout' => 15,
        );
        
        // Make the request
        $response = wp_remote_get($rest_url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $test_error = $response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            // Format the response
            $test_result = array(
                'status_code' => $status_code,
                'body' => $body,
                'headers' => wp_remote_retrieve_headers($response),
            );
        }
    }

    ob_start();
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
            pre {
                background: #f5f5f5;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 3px;
                overflow: auto;
                max-height: 400px;
            }
        </style>
        <div class="card">
            <h1>API Key Testing Tool</h1>
            <p>This tool allows you to test the API key with the REST API endpoints for the Certificate Generator plugin.</p>
            
            <h2>Current API Settings</h2>
            <table class="form-table">
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
            
            <?php if (!$api_key): ?>
                <div class="notice notice-error">
                    <p><strong>Warning:</strong> No API key has been generated. Please generate an API key before testing.</p>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-key-generator')); ?>" class="button">Generate API Key</a></p>
                </div>
            <?php elseif (!$api_enabled): ?>
                <div class="notice notice-error">
                    <p><strong>Warning:</strong> API access is currently disabled. Please enable API access before testing.</p>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-key-generator')); ?>" class="button">Enable API Access</a></p>
                </div>
            <?php else: ?>
                <h2>Test API Endpoint</h2>
                <p>Click the button below to test the API health check endpoint using your current API key.</p>
                
                <form method="post">
                    <?php wp_nonce_field('certificate_generator_api_test', 'api_test_nonce'); ?>
                    <input type="hidden" name="action" value="test_api">
                    <button type="submit" class="button">Test API Endpoint</button>
                </form>
                
                <?php if ($test_error): ?>
                    <div class="notice notice-error">
                        <p><strong>Error:</strong> <?php echo esc_html($test_error); ?></p>
                    </div>
                <?php elseif ($test_result): ?>
                    <h3>Test Results</h3>
                    
                    <h4>Status Code: <?php echo esc_html($test_result['status_code']); ?></h4>
                    
                    <?php if ($test_result['status_code'] === 200): ?>
                        <div class="notice notice-success">
                            <p><strong>Success!</strong> The API key is working correctly.</p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-error">
                            <p><strong>Error:</strong> The API request failed with status code <?php echo esc_html($test_result['status_code']); ?>.</p>
                        </div>
                    <?php endif; ?>
                    
                    <h4>Response Body:</h4>
                    <pre><?php echo esc_html($test_result['body']); ?></pre>
                    
                    <h4>Response Headers:</h4>
                    <pre><?php echo esc_html(print_r($test_result['headers'], true)); ?></pre>
                <?php endif; ?>
            <?php endif; ?>
            
            <h2>API Endpoints</h2>
            <p>The following REST API endpoints are available:</p>
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
            
            <h2>Navigation</h2>
            <p>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=certificate_generator_settings&tab=api')); ?>" class="button button-secondary">Go to API Settings</a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-debug')); ?>" class="button button-secondary">Go to API Debug Page</a>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=certificate-generator-api-key-generator')); ?>" class="button button-secondary">Go to API Key Generator</a>
            </p>
        </div>
        
        <div class="card">
            <h2>Troubleshooting Information</h2>
            <p>If you're experiencing issues with the API, check the following:</p>
            <ol>
                <li>Ensure API access is enabled in the settings</li>
                <li>Verify that your API key is correctly generated and saved</li>
                <li>Check that the Authorization header is correctly formatted as <code>Bearer YOUR_API_KEY</code></li>
                <li>Verify that the REST API is accessible and not blocked by security plugins</li>
                <li>Check for any errors in the WordPress debug log</li>
            </ol>
            <p><strong>Warning:</strong> This debug tool should be removed in production environments.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Only execute if we're on the right page
if (isset($_GET['page']) && $_GET['page'] === 'certificate-generator-api-key-testing') {
    echo certificate_generator_api_test_page();
}