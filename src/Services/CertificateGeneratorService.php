<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

use CertificateGenerator\Certificate\Expiration;
use CertificateGenerator\Exception\CertificateGenerationException;
use CertificateGenerator\Models\Certificate;

/**
 * Core certificate generation orchestrator.
 *
 * Coordinates template resolution, PDF generation, serial numbers,
 * QR codes, database persistence, and email delivery.
 */
class CertificateGeneratorService {

    private Expiration $expiration;
    private SerialNumberService $serialService;
    private QRCodeService $qrService;
    private EmailService $emailService;
    private Certificate $certModel;

    public function __construct(
        Expiration $expiration,
        SerialNumberService $serialService,
        QRCodeService $qrService,
        EmailService $emailService,
        Certificate $certModel
    ) {
        $this->expiration = $expiration;
        $this->serialService = $serialService;
        $this->qrService = $qrService;
        $this->emailService = $emailService;
        $this->certModel = $certModel;
    }

    /**
     * Generate a certificate from template and student data.
     *
     * @param int $templateId Certificate template post ID
     * @param array $studentData Student field data
     * @param string $source Generation source (manual|bulk|api|automatic)
     * @param bool $send_email Whether to email the certificate
     * @return array{path: string, url: string, serial: string, expires_at: ?string}
     * @throws CertificateGenerationException
     */
    public function generate(int $templateId, array $studentData, string $source = 'manual', bool $send_email = false): array {
        $template = get_post($templateId);
        if (!$template || $template->post_type !== 'certificates') {
            throw new CertificateGenerationException("Invalid template ID: {$templateId}");
        }

        $tmpl_meta = get_post_meta($templateId);
        $certificate_type = $tmpl_meta['certificate_type'][0] ?? '';

        $serial = $this->serialService->generate($certificate_type);
        $issued_at = current_time('mysql');

        $exp_unit = $tmpl_meta['expiration_period_unit'][0] ?? 'never';
        $exp_value = (int) ($tmpl_meta['expiration_period_value'][0] ?? 0);
        $expires_at = $this->expiration->calculate($exp_unit, $exp_value, $issued_at);

        $pdf_path = $this->generate_pdf($templateId, $studentData, $tmpl_meta, $serial);

        $cert_id = $this->certModel->insert([
            'student_name' => $studentData['student_name'] ?? 'Unknown',
            'certificate_data' => wp_json_encode($studentData),
            'issued_at' => $issued_at,
            'expires_at' => $expires_at,
            'updated_at' => $issued_at,
            'generated_via' => $source,
            'serial_number' => $serial,
        ]);

        if ($send_email && !empty($studentData['email'])) {
            try {
                $this->emailService->send_certificate(
                    $studentData['email'],
                    $studentData['student_name'] ?? 'Student',
                    [
                        'certificate_title' => $certificate_type,
                        'serial_number' => $serial,
                        'expires_at' => $expires_at ?: 'Never',
                        'issue_date' => $issued_at,
                    ],
                    [$pdf_path]
                );
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[CG] Email failed: ' . $e->getMessage());
                }
            }
        }

        do_action('certificate_generated', [
            'cert_id' => $cert_id,
            'template_id' => $templateId,
            'serial_number' => $serial,
            'student_name' => $studentData['student_name'] ?? '',
            'pdf_path' => $pdf_path,
        ]);

        return [
            'path' => $pdf_path,
            'url' => str_replace(WP_CONTENT_DIR, content_url(), $pdf_path),
            'serial' => $serial,
            'expires_at' => $expires_at,
        ];
    }

    /**
     * Generate PDF with FPDF.
     *
     * @param int $templateId
     * @param array $studentData
     * @param array $tmpl_meta
     * @param string $serial
     * @return string PDF file path
     * @throws CertificateGenerationException
     */
    private function generate_pdf(int $templateId, array $studentData, array $tmpl_meta, string $serial): string {
        if (!class_exists('FPDF')) {
            $fpdf_path = CERTIFICATE_GENERATOR_PATH . 'lib/fpdf/fpdf.php';
            if (!file_exists($fpdf_path)) {
                throw new CertificateGenerationException('FPDF library not found');
            }
            require_once $fpdf_path;
        }

        $page_size = $tmpl_meta['certificate_page_size'][0] ?? 'A4';
        $orientation = $tmpl_meta['certificate_orientation'][0] ?? 'L';

        $pdf = new \FPDF($orientation, 'mm', $page_size);
        $pdf->AddPage();

        $bg_path = $tmpl_meta['certificate_background'][0] ?? '';
        if ($bg_path && file_exists($bg_path)) {
            $pdf->Image($bg_path, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
        }

        $font_style = $tmpl_meta['certificate_font_style'][0] ?? 'helvetica';
        $font_size = (int) ($tmpl_meta['certificate_font_size'][0] ?? 16);

        foreach ($studentData as $key => $value) {
            $pos_x_meta = "field_{$key}_position_x";
            $pos_y_meta = "field_{$key}_position_y";
            $pos_x = (float) ($tmpl_meta[$pos_x_meta][0] ?? 50);
            $pos_y = (float) ($tmpl_meta[$pos_y_meta][0] ?? 100);
            $f_size = (int) ($tmpl_meta["field_{$key}_font_size"][0] ?? $font_size);

            $pdf->SetFont($font_style, '', $f_size);
            $pdf->SetXY($pos_x, $pos_y);
            $pdf->Cell(0, $f_size + 2, (string) $value, 0, 0, 'C');
        }

        if (!empty($tmpl_meta['qr_enabled'][0]) && $tmpl_meta['qr_enabled'][0] === '1') {
            $qr_size = (float) ($tmpl_meta['qr_size'][0] ?? 15);
            $qr_pos_x = (float) ($tmpl_meta['qr_position_x'][0] ?? 250);
            $qr_pos_y = (float) ($tmpl_meta['qr_position_y'][0] ?? 180);
            $qr_ec = $tmpl_meta['qr_error_correction'][0] ?? 'L';

            $qr_data = $this->qrService->generate_data([
                'certificate_type' => $tmpl_meta['certificate_type'][0] ?? '',
                'issued_at' => current_time('mysql'),
            ], $serial);

            $this->qrService->add_to_pdf($pdf, $qr_data, $qr_pos_x, $qr_pos_y, $qr_size);
        }

        if (!empty($tmpl_meta['serial_number_display'][0]) && $tmpl_meta['serial_number_display'][0] === '1') {
            $sn_pos_x = (float) ($tmpl_meta['serial_number_position_x'][0] ?? 105);
            $sn_pos_y = (float) ($tmpl_meta['serial_number_position_y'][0] ?? 200);
            $sn_font_size = (int) ($tmpl_meta['serial_number_font_size'][0] ?? 10);

            $this->qrService->add_serial_to_pdf($pdf, $serial, $sn_pos_x, $sn_pos_y, $sn_font_size, $font_style);
        }

        $upload_dir = wp_upload_dir();
        $cert_dir = trailingslashit($upload_dir['basedir']) . 'certificates/';
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
        }

        $filename = 'cert_' . sanitize_file_name($studentData['student_name'] ?? 'unknown') . '_' . time() . '.pdf';
        $filepath = $cert_dir . $filename;

        $pdf->Output('F', $filepath);

        if (!file_exists($filepath)) {
            throw new CertificateGenerationException('Failed to save PDF: ' . $filepath);
        }

        return $filepath;
    }
}
