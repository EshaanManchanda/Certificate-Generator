<?php
declare(strict_types=1);

namespace CertificateGenerator\Core;

/**
 * Plugin configuration constants and defaults.
 */
class Config {

    public const VERSION = '7.0.0';
    public const DB_VERSION_OPTION = 'cg_db_version';
    public const DB_TARGET_VERSION = '001';

    public const SERIAL_PREFIX_DEFAULT = 'CERT';
    public const SERIAL_LENGTH_DEFAULT = 8;
    public const SERIAL_SUFFIX_DEFAULT = '';
    public const SERIAL_RESET_PERIOD_DEFAULT = 'none';

    public const QR_SIZE_DEFAULT = 15;
    public const QR_POSITION_X_DEFAULT = 250.0;
    public const QR_POSITION_Y_DEFAULT = 180.0;
    public const QR_ERROR_CORRECTION_DEFAULT = 'L';

    public const EXPIRATION_UNIT_DEFAULT = 'never';
    public const EXPIRATION_VALUE_DEFAULT = 0;

    public const EMAIL_RATE_LIMIT_DEFAULT = 60;
    public const EMAIL_RATE_WINDOW_DEFAULT = 3600;

    public const API_VERIFY_RATE_LIMIT = 60;
    public const API_VERIFY_RATE_WINDOW = 3600;

    public const QR_CLEANUP_DAYS = 7;
    public const ARCHIVE_AFTER_YEARS = 5;

    public const CERTIFICATE_GENERATED_ACTION = 'certificate_generated';

    public const CAPABILITY_MANAGE = 'manage_options';
    public const CAPABILITY_EDIT_POSTS = 'edit_posts';

    public static function get(string $key, $default = null) {
        $constants = [
            'version' => self::VERSION,
            'db_version' => self::DB_TARGET_VERSION,
            'serial_prefix' => self::SERIAL_PREFIX_DEFAULT,
            'serial_length' => self::SERIAL_LENGTH_DEFAULT,
            'qr_size' => self::QR_SIZE_DEFAULT,
            'qr_error_correction' => self::QR_ERROR_CORRECTION_DEFAULT,
            'email_rate_limit' => self::EMAIL_RATE_LIMIT_DEFAULT,
            'api_rate_limit' => self::API_VERIFY_RATE_LIMIT,
        ];

        return $constants[$key] ?? $default;
    }
}
