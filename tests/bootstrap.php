<?php
declare(strict_types=1);

// Autoload Composer dependencies (includes the plugin's src/ PSR-4 map).
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    echo "Run `composer install` first.\n";
    exit(1);
}
require_once $autoloader;

// Brain\Monkey setUp/tearDown is called per-test in each TestCase.

// Define SECURE_AUTH_KEY so SettingsService password encryption works in tests.
if (!defined('SECURE_AUTH_KEY')) {
    define('SECURE_AUTH_KEY', 'test-secret-key-for-unit-tests-only-32-chars');
}
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wp/');
}

// Minimal WP function stubs so procedural includes/ files can be loaded outside WordPress.
// These are no-ops — they do not emulate real WP behaviour.
if (!function_exists('add_action')) {
    function add_action(): void {}
}
if (!function_exists('add_filter')) {
    function add_filter(): void {}
}
if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed { return $value; }
}
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(): false { return false; }
}
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(): void {}
}

// WP output constants used by wpdb methods.
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
