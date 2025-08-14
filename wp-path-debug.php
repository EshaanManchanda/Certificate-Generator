<?php
/**
 * WordPress Path Debug Tool
 * 
 * This file helps diagnose issues with WordPress paths and includes.
 * It can be accessed directly for debugging purposes.
 */

// Allow direct access for debugging
define('WP_DEBUG', true);

// Try to locate WordPress load
$possible_paths = [
    __DIR__ . '/../../../wp-load.php',
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../../../wp-load.php',
    // Add more possible relative paths if needed
];

$wp_load_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        break;
    }
}

// Output as plain text
header('Content-Type: text/plain');

echo "WordPress Path Debug Tool\n";
echo "=======================\n\n";

echo "Current directory: " . __DIR__ . "\n";
echo "Current file: " . __FILE__ . "\n\n";

if ($wp_load_path) {
    echo "WordPress load.php found at: {$wp_load_path}\n";
    
    // Try to load WordPress
    try {
        require_once $wp_load_path;
        echo "WordPress successfully loaded.\n\n";
        
        // Check for ABSPATH
        echo "ABSPATH: " . (defined('ABSPATH') ? ABSPATH : 'Not defined') . "\n";
        
        // Check for upgrade.php
        $upgrade_path = ABSPATH . 'wp-admin/includes/upgrade.php';
        echo "upgrade.php path: {$upgrade_path}\n";
        echo "upgrade.php exists: " . (file_exists($upgrade_path) ? 'YES' : 'NO') . "\n\n";
        
        // Check for plugin paths
        echo "WP_PLUGIN_DIR: " . (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : 'Not defined') . "\n";
        echo "WPMU_PLUGIN_DIR: " . (defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : 'Not defined') . "\n\n";
        
        // Check for content directory
        echo "WP_CONTENT_DIR: " . (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : 'Not defined') . "\n";
        echo "WP_CONTENT_URL: " . (defined('WP_CONTENT_URL') ? WP_CONTENT_URL : 'Not defined') . "\n\n";
        
        // Check database prefix
        global $wpdb;
        echo "Database prefix: " . (isset($wpdb) ? $wpdb->prefix : 'Not available') . "\n\n";
        
        // List important WordPress directories
        echo "Important WordPress directories:\n";
        $dirs = [
            'ABSPATH' => ABSPATH,
            'wp-admin' => ABSPATH . 'wp-admin',
            'wp-admin/includes' => ABSPATH . 'wp-admin/includes',
            'wp-includes' => ABSPATH . 'wp-includes',
            'wp-content' => WP_CONTENT_DIR,
            'plugins' => WP_PLUGIN_DIR,
        ];
        
        foreach ($dirs as $name => $dir) {
            echo "  {$name}: " . (is_dir($dir) ? 'EXISTS' : 'MISSING') . " ({$dir})\n";
        }
        
    } catch (Exception $e) {
        echo "Error loading WordPress: " . $e->getMessage() . "\n";
    }
} else {
    echo "ERROR: Could not find WordPress wp-load.php file.\n";
    echo "Please make sure this script is placed within a WordPress installation.\n\n";
    
    echo "Searched the following locations:\n";
    foreach ($possible_paths as $path) {
        echo "  {$path} - " . (file_exists($path) ? 'EXISTS' : 'NOT FOUND') . "\n";
    }
}

echo "\n\nServer Information:\n";
echo "PHP Version: " . phpversion() . "\n";
echo "OS: " . PHP_OS . "\n";
echo "Server Software: " . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown') . "\n";
echo "Document Root: " . (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'Unknown') . "\n";

echo "\n\nEnd of Debug Report\n";