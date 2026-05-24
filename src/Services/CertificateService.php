<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

use CertificateGenerator\Certificate\Expiration;
use CertificateGenerator\Exception\CertificateGenerationException;

class CertificateService {

	private Expiration $expiration;

	public function __construct( Expiration $expiration ) {
		$this->expiration = $expiration;
	}

	public function generate( int $templateId, array $studentData, string $source = 'manual' ): array {
		$template = get_post( $templateId );
		if ( ! $template || $template->post_type !== 'certificates' ) {
			throw new CertificateGenerationException( "Invalid template ID: {$templateId}" );
		}

		$tmplMeta  = get_post_meta( $templateId );
		$expUnit   = $tmplMeta['expiration_period_unit'][0] ?? 'never';
		$expValue  = (int) ( $tmplMeta['expiration_period_value'][0] ?? 0 );
		$expiresAt = $this->expiration->calculate( $expUnit, $expValue );

		return array(
			'template_id'   => $templateId,
			'template_name' => $template->post_title,
			'student_data'  => $studentData,
			'source'        => $source,
			'issued_at'     => current_time( 'mysql' ),
			'expires_at'    => $expiresAt,
		);
	}

	public function verify( string $serialNumber ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'certificate_generator';

		$cert = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE serial_number = %s",
				$serialNumber
			),
			ARRAY_A
		);

		if ( ! $cert ) {
			return array(
				'valid'   => false,
				'message' => 'Certificate not found',
			);
		}

		$expired = $this->expiration->is_expired( $cert['expires_at'] ?? null );

		return array(
			'valid'   => true,
			'expired' => $expired,
			'message' => $expired ? 'Certificate has expired' : 'Certificate is valid',
			'data'    => $cert,
		);
	}
}
