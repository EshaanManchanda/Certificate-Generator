<?php
// Minimal stubs for PHPStan so it can resolve global WP functions used in src/.
// This file is NOT loaded at test runtime — only during static analysis.
if (!defined('SECURE_AUTH_KEY')) {
    define('SECURE_AUTH_KEY', '');
}
if (!defined('CERTIFICATE_GENERATOR_PATH')) {
    define('CERTIFICATE_GENERATOR_PATH', '');
}
if (!defined('CERTIFICATE_GENERATOR_URL')) {
    define('CERTIFICATE_GENERATOR_URL', '');
}
