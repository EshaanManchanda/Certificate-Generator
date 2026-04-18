<?php
declare(strict_types=1);

namespace CertificateGenerator\Helpers;

/**
 * Formatting helpers for dates, numbers, and strings.
 */
class Formatter {

    public static function date(string $datetime, string $format = 'Y-m-d H:i:s'): string {
        $dt = new \DateTime($datetime);
        return $dt->format($format);
    }

    public static function date_i18n(string $datetime): string {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($datetime));
    }

    public static function human_diff(string $from, string $to = 'now'): string {
        $diff = strtotime($to) - strtotime($from);
        if ($diff < 60) return $diff . ' seconds';
        if ($diff < 3600) return floor($diff / 60) . ' minutes';
        if ($diff < 86400) return floor($diff / 3600) . ' hours';
        return floor($diff / 86400) . ' days';
    }

    public static function file_size(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public static function truncate(string $text, int $length = 50, string $suffix = '...'): string {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }

    public static function mask_email(string $email): string {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $name = $parts[0];
        $domain = $parts[1];
        $masked = substr($name, 0, 2) . '***' . substr($name, -1);
        return $masked . '@' . $domain;
    }
}
