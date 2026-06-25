<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

use CertificateGenerator\Certificate\Expiration;
use CertificateGenerator\Database\CustomTables;
use CertificateGenerator\Exception\CertificateGenerationException;
use CertificateGenerator\Models\Certificate;

/**
 * @deprecated v8 — Zero callers. Live logic is in includes/Services/certificate-search.php and includes/Email/functions.php. Will be deleted in v9.
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
		$this->expiration    = $expiration;
		$this->serialService = $serialService;
		$this->qrService     = $qrService;
		$this->emailService  = $emailService;
		$this->certModel     = $certModel;
	}

	/**
	 * Generate a certificate from template and student data.
	 *
	 * @param int    $templateId Row ID in wp_cg_certificate_templates
	 * @param array  $studentData Student field data
	 * @param string $source Generation source (manual|bulk|api|automatic)
	 * @param bool   $send_email Whether to email the certificate
	 * @return array{path: string, url: string, serial: string, expires_at: ?string}
	 * @throws CertificateGenerationException
	 */
	public function generate( int $templateId, array $studentData, string $source = 'manual', bool $send_email = false ): array {
		global $wpdb;

		$table = CustomTables::instance()->get_table( 'certificate_templates' );
		$tmpl  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $templateId ), ARRAY_A );

		if ( ! $tmpl ) {
			throw new CertificateGenerationException( "Template not found: {$templateId}" );
		}

		$extra_fields = array();
		if ( ! empty( $tmpl['extra_fields'] ) ) {
			$decoded = json_decode( $tmpl['extra_fields'], true );
			if ( is_array( $decoded ) ) {
				$extra_fields = $decoded;
			}
		}

		$certificate_type = $tmpl['certificate_type'] ?? '';
		$serial           = $this->serialService->generate( $certificate_type );
		$issued_at        = current_time( 'mysql' );

		$exp_unit   = $tmpl['expiration_period_unit'] ?? 'never';
		$exp_value  = (int) ( $tmpl['expiration_period_value'] ?? 0 );
		$expires_at = $this->expiration->calculate( $exp_unit, $exp_value, $issued_at );

		$pdf_path = $this->generate_pdf( $tmpl, $extra_fields, $studentData, $serial );

		$cert_id = $this->certModel->insert(
			array(
				'student_name'     => $studentData['student_name'] ?? 'Unknown',
				'certificate_data' => wp_json_encode( $studentData ),
				'email'            => sanitize_email( $studentData['email'] ?? '' ),
				'pdf_path'         => $pdf_path,
				'issued_at'        => $issued_at,
				'expires_at'       => $expires_at,
				'updated_at'       => $issued_at,
				'generated_via'    => $source,
				'serial_number'    => $serial,
			)
		);

		if ( $send_email && ! empty( $studentData['email'] ) ) {
			try {
				$this->emailService->send_certificate(
					$studentData['email'],
					$studentData['student_name'] ?? 'Student',
					array(
						'certificate_title' => $certificate_type,
						'serial_number'     => $serial,
						'expires_at'        => $expires_at ?: 'Never',
						'issue_date'        => $issued_at,
					),
					array( $pdf_path )
				);
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[CG] Email failed: ' . $e->getMessage() );
				}
			}
		}

		do_action(
			'certificate_generated',
			array(
				'cert_id'       => $cert_id,
				'template_id'   => $templateId,
				'serial_number' => $serial,
				'student_name'  => $studentData['student_name'] ?? '',
				'pdf_path'      => $pdf_path,
			)
		);

		return array(
			'path'       => $pdf_path,
			'url'        => str_replace( WP_CONTENT_DIR, content_url(), $pdf_path ),
			'serial'     => $serial,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Generate PDF with FPDF using a custom-table template row.
	 *
	 * @param array  $tmpl Row from wp_cg_certificate_templates
	 * @param array  $extra_fields Decoded extra_fields JSON (field positions etc.)
	 * @param array  $studentData
	 * @param string $serial
	 * @return string PDF file path
	 * @throws CertificateGenerationException
	 */
	private function generate_pdf( array $tmpl, array $extra_fields, array $studentData, string $serial ): string {
		if ( ! class_exists( 'FPDF' ) ) {
			$fpdf_path = CERTIFICATE_GENERATOR_PATH . 'lib/fpdf/fpdf.php';
			if ( ! file_exists( $fpdf_path ) ) {
				throw new CertificateGenerationException( 'FPDF library not found' );
			}
			require_once $fpdf_path;
		}

		$page_size   = $tmpl['page_size'] ?? 'A4';
		$orientation = ( $tmpl['orientation'] ?? 'landscape' ) === 'portrait' ? 'P' : 'L';

		$pdf = new \FPDF( $orientation, 'mm', $page_size );
		$pdf->AddPage();

		// Background image: template_url is a media-library URL; convert to filesystem path.
		$bg_url = $tmpl['template_url'] ?? '';
		if ( $bg_url ) {
			$upload_dir = wp_upload_dir();
			$bg_path    = str_replace(
				trailingslashit( $upload_dir['baseurl'] ),
				trailingslashit( $upload_dir['basedir'] ),
				$bg_url
			);
			if ( file_exists( $bg_path ) ) {
				$pdf->Image( $bg_path, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight() );
			}
		}

		$font_style = $tmpl['font_style'] ?? 'helvetica';
		$font_size  = (int) ( $tmpl['font_size'] ?? 16 );

		// Map field name → numeric slot (1-indexed) from the schema.
		$certificate_type = $tmpl['certificate_type'] ?? '';
		$renderable       = class_exists( 'CG_Field_Schema' )
			? \CG_Field_Schema::get_all_renderable_fields( $certificate_type )
			: array();
		$name_to_slot     = array();
		if ( ! empty( $renderable ) && is_array( $renderable ) ) {
			foreach ( $renderable as $idx => $name ) {
				$name_to_slot[ $name ] = $idx + 1;
			}
		}

		foreach ( $studentData as $key => $value ) {
			if ( ! isset( $name_to_slot[ $key ] ) ) {
				continue;
			}

			$slot    = $name_to_slot[ $key ];
			$visible = ( $extra_fields[ "field_{$slot}_visible" ] ?? '1' ) !== '0';
			if ( ! $visible ) {
				continue;
			}

			$pos_x = (float) ( $extra_fields[ "field_{$slot}_position_x" ] ?? 105 );
			$pos_y = (float) ( $extra_fields[ "field_{$slot}_position_y" ] ?? 60 + $slot * 25 );
			$width = (float) ( $extra_fields[ "field_{$slot}_width" ] ?? 100 );
			$align = $extra_fields[ "field_{$slot}_alignment" ] ?? 'C';
			if ( ! in_array( $align, array( 'L', 'C', 'R' ), true ) ) {
				$align = 'C';
			}

			$pdf->SetFont( $font_style, '', $font_size );
			$pdf->SetXY( $pos_x, $pos_y );
			$pdf->Cell( $width, $font_size + 2, (string) $value, 0, 0, $align );
		}

		if ( ! empty( $tmpl['qr_enabled'] ) ) {
			$qr_size  = (float) ( $tmpl['qr_size'] ?? 15 );
			$qr_pos_x = (float) ( $tmpl['qr_position_x'] ?? 250 );
			$qr_pos_y = (float) ( $tmpl['qr_position_y'] ?? 180 );
			$qr_ec    = $tmpl['qr_error_correction'] ?? 'L';

			$qr_data = $this->qrService->generate_data(
				array(
					'certificate_type' => $certificate_type,
					'issued_at'        => current_time( 'mysql' ),
				),
				$serial
			);

			$this->qrService->add_to_pdf( $pdf, $qr_data, $qr_pos_x, $qr_pos_y, $qr_size );
		}

		if ( ! empty( $tmpl['serial_number_display'] ) ) {
			$sn_pos_x     = (float) ( $tmpl['serial_number_position_x'] ?? 105 );
			$sn_pos_y     = (float) ( $tmpl['serial_number_position_y'] ?? 200 );
			$sn_font_size = (int) ( $tmpl['serial_number_font_size'] ?? 10 );

			$this->qrService->add_serial_to_pdf( $pdf, $serial, $sn_pos_x, $sn_pos_y, $sn_font_size, $font_style );
		}

		$upload_dir = wp_upload_dir();
		$cert_dir   = trailingslashit( $upload_dir['basedir'] ) . 'certificates/';
		if ( ! file_exists( $cert_dir ) ) {
			wp_mkdir_p( $cert_dir );
		}

		$filename = 'cert_' . sanitize_file_name( $studentData['student_name'] ?? 'unknown' ) . '_' . time() . '.pdf';
		$filepath = $cert_dir . $filename;

		$pdf->Output( 'F', $filepath );

		if ( ! file_exists( $filepath ) ) {
			throw new CertificateGenerationException( 'Failed to save PDF: ' . $filepath );
		}

		return $filepath;
	}
}
