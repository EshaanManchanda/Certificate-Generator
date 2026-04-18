<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

/**
 * Serial number generation service.
 */
class SerialNumberService {

    private string $prefix;
    private int $length;
    private string $suffix;
    private bool $include_date;
    private string $reset_period;

    public function __construct() {
        $this->prefix = get_option('cg_serial_prefix', 'CERT');
        $this->length = (int) get_option('cg_serial_length', 8);
        $this->suffix = get_option('cg_serial_suffix', '');
        $this->include_date = (bool) get_option('cg_serial_include_date', false);
        $this->reset_period = get_option('cg_serial_reset_period', 'none');
    }

    public function generate(string $certificate_type = ''): string {
        $date_part = $this->get_date_part();
        $sequence = $this->get_next_sequence($certificate_type);
        $padded = str_pad((string) $sequence, $this->length, '0', STR_PAD_LEFT);

        $serial = $this->prefix . '-' . $date_part . $padded;
        if ($this->suffix !== '') {
            $serial .= '-' . $this->suffix;
        }

        return $serial;
    }

    public function verify(string $serial): array {
        global $wpdb;
        $table = $wpdb->prefix . 'certificate_generator';

        $cert = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE serial_number = %s",
            sanitize_text_field($serial)
        ), ARRAY_A);

        if (!$cert) {
            return ['valid' => false, 'message' => 'Certificate not found', 'data' => null];
        }

        $expired = false;
        if (!empty($cert['expires_at'])) {
            $expired = strtotime($cert['expires_at']) < time();
        }

        return [
            'valid' => true,
            'expired' => $expired,
            'message' => $expired ? 'Certificate has expired' : 'Certificate is valid',
            'data' => [
                'id' => $cert['id'],
                'student_name' => $cert['student_name'],
                'certificate_type' => $cert['certificate_type'] ?? '',
                'issued_at' => $cert['issued_at'],
                'expires_at' => $cert['expires_at'],
                'serial_number' => $cert['serial_number'],
                'created_at' => $cert['created_at'],
            ],
        ];
    }

    private function get_date_part(): string {
        if (!$this->include_date) {
            return '';
        }

        switch ($this->reset_period) {
            case 'daily':
                return date('Ymd') . '-';
            case 'monthly':
                return date('Ym') . '-';
            case 'yearly':
                return date('Y') . '-';
            default:
                return '';
        }
    }

    private function get_next_sequence(string $certificate_type): int {
        global $wpdb;

        // Build a per-cert-type, per-period option key (max 191 chars for utf8mb4 index).
        $safe_type     = substr(sanitize_key($certificate_type ?: 'default'), 0, 40);
        $period_suffix = '';
        if ($this->reset_period === 'daily')   $period_suffix = '_' . date('Ymd');
        if ($this->reset_period === 'monthly') $period_suffix = '_' . date('Ym');
        if ($this->reset_period === 'yearly')  $period_suffix = '_' . date('Y');
        $option_key = 'cg_serial_seq_' . $safe_type . $period_suffix;

        // Atomically increment a persistent counter in wp_options.
        // ON DUPLICATE KEY UPDATE is a single atomic MySQL operation — safe across concurrent requests.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
             VALUES (%s, 1, 'no')
             ON DUPLICATE KEY UPDATE option_value = option_value + 1",
            $option_key
        ));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            $option_key
        ));
    }

    public function register_api_routes(): void {
        add_action('rest_api_init', function() {
            register_rest_route('certificate-generator/v1', '/verify/(?P<serial>[a-zA-Z0-9\-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'api_verify'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function api_verify($request): \WP_REST_Response {
        $serial = $request->get_param('serial');
        $result = $this->verify($serial);
        return new \WP_REST_Response($result, $result['valid'] ? 200 : 404);
    }
}
