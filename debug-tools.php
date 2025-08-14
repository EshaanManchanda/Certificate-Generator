<?php
/**
 * Debug Tools Loader
 * 
 * Loads temporary debugging tools for the Certificate Generator plugin.
 * This file should be removed after debugging is complete.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the API key debug tools
require_once plugin_dir_path(__FILE__) . 'api-key-debug.php';

// Add menu items for the API Key debug tools
function certificate_generator_add_api_debug_menus() {
    // Add debug tools under Certificate Generator menu
    add_submenu_page(
        'edit.php?post_type=certificate',
        'API Debug Dashboard',
        'API Debug Tools',
        'manage_options',
        'certificate-generator-api-debug',
        function() {
            require_once plugin_dir_path(__FILE__) . 'api-debug-dashboard.php';
            if (function_exists('certificate_generator_api_debug_dashboard')) {
                echo certificate_generator_api_debug_dashboard();
            }
        });
    
    // Hidden submenu pages - use the same parent menu
    add_submenu_page(
        'edit.php?post_type=certificate',
        'API Key Generator',
        'API Key Generator',
        'manage_options',
        'certificate-generator-api-key-generator',
        'certificate_generator_api_key_generator_page',
        null
    );
    
    add_submenu_page(
        'edit.php?post_type=certificate',
        'API Key Testing',
        'API Key Testing',
        'manage_options',
        'certificate-generator-api-key-testing',
        'certificate_generator_api_key_testing_page',
        null
    );
}
add_action('admin_menu', 'certificate_generator_add_api_debug_menus');

// Callback function for the API Key Generator page
function certificate_generator_api_key_generator_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    require_once plugin_dir_path(__FILE__) . 'api-key-generate.php';
    if (function_exists('certificate_generator_api_key_generator')) {
        echo certificate_generator_api_key_generator();
    }
}

// Callback function for the API Key Testing page
function certificate_generator_api_key_testing_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    require_once plugin_dir_path(__FILE__) . 'api-key-test.php';
    if (function_exists('certificate_generator_api_test_page')) {
        echo certificate_generator_api_test_page();
    }
}
