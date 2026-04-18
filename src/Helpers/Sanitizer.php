<?php
declare(strict_types=1);

namespace CertificateGenerator\Helpers;

/**
 * Sanitization and validation helpers.
 */
class Sanitizer {

    public static function text(string $value): string {
        return sanitize_text_field($value);
    }

    public static function email(string $value): string {
        return sanitize_email($value);
    }

    public static function url(string $value): string {
        return esc_url_raw($value);
    }

    public static function int($value): int {
        return absint($value);
    }

    public static function key(string $value): string {
        return sanitize_key($value);
    }

    public static function html(string $value): string {
        return wp_kses_post($value);
    }

    public static function filename(string $value): string {
        return sanitize_file_name($value);
    }

    public static function is_valid_email(string $email): bool {
        return is_email($email) !== false;
    }

    public static function is_valid_serial(string $serial): bool {
        return preg_match('/^[A-Z0-9\-]+$/', $serial) === 1;
    }
}
