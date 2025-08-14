<?php
/**
 * API Key Debug Tool
 * 
 * Provides debugging tools for API key functionality.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add API Key Debug page to admin menu
 */
function certificate_generator_add_debug_page() {
    add_submenu_page(
        'options-general.php',
        'API Key Debug',
        'API Key Debug',
        'manage_options',
        'certificate-generator-api-debug',
        'certificate_generator_api_debug_page'
    );
}
add_action('admin_menu', 'certificate_generator_add_debug_page');

/**
 * Render the API Key Debug page
 */
function certificate_generator_api_debug_page() {
    // Check user capabilities
    // if (!current_user_can('manage_options')) {
    //     return;
    // }
    
    // Handle form submissions
    if (isset($_POST['action']) && $_POST['action'] === 'generate_api_key') {
        // Generate a new API key
        $api_key = certificate_generator_generate_api_key();
        update_option('certificate_generator_api_key', $api_key);
        echo '<div class="notice notice-success"><p>API key generated successfully!</p></div>';
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_api_access') {
        $enabled = isset($_POST['api_enabled']) ? 1 : 0;
        update_option('certificate_generator_api_key_enabled', $enabled);
        echo '<div class="notice notice-success"><p>API access ' . ($enabled ? 'enabled' : 'disabled') . '.</p></div>';
    }
    
    // Get current values
    $api_key = get_option('certificate_generator_api_key', '');
    $api_enabled = get_option('certificate_generator_api_key_enabled', false);
    
    // Output the debug page
    ?>
    <div class="wrap">
        <h1>API Key Debug</h1>
        
        <div class="card">
            <h2>Current API Key Settings</h2>
            <p><strong>API Key:</strong> <?php echo empty($api_key) ? 'Not set' : esc_html($api_key); ?></p>
            <p><strong>API Enabled:</strong> <?php echo $api_enabled ? 'Yes' : 'No'; ?></p>
            
            <h3>Generate New API Key</h3>
            <form method="post">
                <input type="hidden" name="action" value="generate_api_key">
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Generate New API Key">
                </p>
            </form>
            
            <h3>Toggle API Access</h3>
            <form method="post">
                <input type="hidden" name="action" value="toggle_api_access">
                <p>
                    <label>
                        <input type="checkbox" name="api_enabled" value="1" <?php checked(1, $api_enabled); ?>>
                        Enable API Access
                    </label>
                </p>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save API Access Setting">
                </p>
            </form>
        </div>
        
        <div class="card">
            <h2>Debug Information</h2>
            <p><strong>Settings Page URL:</strong> <a href="<?php echo admin_url('options-general.php?page=certificate_generator_settings&tab=api'); ?>" target="_blank">Open API Settings Tab</a></p>
            <p><strong>API Settings Section ID:</strong> certificate_generator_api_section</p>
            <p><strong>API Key Option Name:</strong> certificate_generator_api_key</p>
            <p><strong>API Enabled Option Name:</strong> certificate_generator_api_key_enabled</p>
            
            <h3>Database Values</h3>
            <pre><?php 
                echo "certificate_generator_api_key: " . esc_html(get_option('certificate_generator_api_key', 'Not set')) . "\n";
                echo "certificate_generator_api_key_enabled: " . (get_option('certificate_generator_api_key_enabled', false) ? 'true' : 'false') . "\n";
            ?></pre>
        </div>
    </div>
    <?php
}

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

/**
 * AJAX handler for retrieving API key options
 */
function certificate_generator_debug_get_options() {
    // Check user capabilities
    // if (!current_user_can('manage_options')) {
    //     wp_send_json_error('Unauthorized');
    //     return;
    // }
    
    $options = [
        'certificate_generator_api_key' => get_option('certificate_generator_api_key', ''),
        'certificate_generator_api_key_enabled' => get_option('certificate_generator_api_key_enabled', false),
        'option_exists_api_key' => get_option('certificate_generator_api_key', null) !== null,
        'option_exists_api_enabled' => get_option('certificate_generator_api_key_enabled', null) !== null,
    ];
    
    wp_send_json_success($options);
}
add_action('wp_ajax_certificate_generator_debug_get_options', 'certificate_generator_debug_get_options');

/**
 * Add a debug notice to the API settings tab
 */
function certificate_generator_add_api_debug_notice() {
    $screen = get_current_screen();
    if ($screen->id === 'settings_page_certificate_generator_settings' && isset($_GET['tab']) && $_GET['tab'] === 'api') {
        echo '<div class="notice notice-info"><p>API Debug Tools are active. Visit the <a href="' . admin_url('options-general.php?page=certificate-generator-api-debug') . '">API Key Debug</a> page for more information.</p></div>';
    }
}
add_action('admin_notices', 'certificate_generator_add_api_debug_notice');