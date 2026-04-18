<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

use CertificateGenerator\Exception\CertificateGenerationException;

/**
 * QR Code generation service.
 */
class QRCodeService {

    private string $qr_dir;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->qr_dir = trailingslashit($upload_dir['basedir']) . 'cg-qr-codes/';

        if (!file_exists($this->qr_dir)) {
            wp_mkdir_p($this->qr_dir);
        }
    }

    public function generate_data(array $cert_data, string $serial_number): string {
        $verify_url = home_url('/wp-json/certificate-generator/v1/verify/' . $serial_number);

        return wp_json_encode([
            'v' => 1,
            'sn' => $serial_number,
            'url' => $verify_url,
            'type' => $cert_data['certificate_type'] ?? '',
            'issued' => $cert_data['issued_at'] ?? current_time('mysql'),
        ]);
    }

    public function generate_image(string $data, int $size = 300, string $error_correction = 'L'): string {
        $qr_lib_path = CERTIFICATE_GENERATOR_PATH . 'lib/phpqrcode/qrlib.php';

        if (!file_exists($qr_lib_path)) {
            return $this->generate_image_fallback($data, $size);
        }

        require_once $qr_lib_path;

        $ec_levels = [
            'L' => QR_ECLEVEL_L,
            'M' => QR_ECLEVEL_M,
            'Q' => QR_ECLEVEL_Q,
            'H' => QR_ECLEVEL_H,
        ];
        $ec_level = $ec_levels[$error_correction] ?? QR_ECLEVEL_L;

        $filename = 'qr_' . wp_hash($data . wp_salt()) . '.png';
        $filepath = $this->qr_dir . sanitize_file_name($filename);

        QRcode::png($data, $filepath, $ec_level, 10, 2);

        if (!file_exists($filepath)) {
            throw new CertificateGenerationException('Failed to generate QR code image');
        }

        return $filepath;
    }

    public function add_to_pdf($pdf, string $qr_data, float $pos_x, float $pos_y, float $size_mm = 15): bool {
        $qr_image = $this->generate_image($qr_data);

        if (!file_exists($qr_image)) {
            return false;
        }

        $size_mm = max(10.0, min(50.0, $size_mm));
        $pdf->Image($qr_image, $pos_x, $pos_y, $size_mm, $size_mm);

        return true;
    }

    public function add_serial_to_pdf($pdf, string $serial, float $pos_x, float $pos_y, int $font_size = 10, string $font_style = 'helvetica'): bool {
        if ($serial === '') {
            return false;
        }

        $pdf->SetFont($font_style, 'B', $font_size);
        $pdf->SetTextColor(0, 0, 0);

        $text = 'Serial: ' . $serial;
        $text_width = $pdf->GetStringWidth($text);
        $pdf->Text($pos_x - ($text_width / 2), $pos_y, $text);

        return true;
    }

    public function cleanup_old(int $days = 7): int {
        $files = glob($this->qr_dir . '*.png');
        if (!$files) {
            return 0;
        }

        $cutoff = time() - ($days * 86400);
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (wp_delete_file($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function generate_image_fallback(string $data, int $size): string {
        $api_url = 'https://api.qrserver.com/v1/create-qr-code/';
        $api_url .= '?size=' . $size . 'x' . $size;
        $api_url .= '&data=' . urlencode($data);
        $api_url .= '&ecc=L';

        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            throw new CertificateGenerationException('QR code API request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $filename = 'qr_' . wp_hash($data . wp_salt()) . '.png';
        $filepath = $this->qr_dir . sanitize_file_name($filename);

        file_put_contents($filepath, $body);

        if (!file_exists($filepath)) {
            throw new CertificateGenerationException('Failed to save QR code image');
        }

        return $filepath;
    }
}
