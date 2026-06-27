<?php
/**
 * Backend API Client — license-only
 *
 * Handles communication with the portfolio license server.
 * Payment/subscription methods removed in v7.0.0.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CG_Backend_API {

	private string $base_url;
	private string $api_key;

	private static ?self $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->base_url = rtrim( (string) get_option( 'cg_license_server_url', '' ), '/' );
		$this->api_key  = (string) get_option( 'cg_gema_api_key', '' );
	}

	public function is_configured(): bool {
		return ! empty( $this->base_url );
	}

	public function get_base_url(): string {
		return $this->base_url;
	}

	public function verify_license( string $license_key ): array {
		return $this->request(
			'POST',
			'/api/payments/verify-license',
			array(
				'license_key'     => $license_key,
				'product'         => CG_License_Manager::PRODUCT_SLUG,
				'installation_id' => CG_License_Manager::get_installation_id(),
			)
		);
	}

	public function deactivate_on_server( string $license_key, string $site_url, string $installation_id = '' ): array {
		return $this->request(
			'POST',
			'/api/payments/deactivate-remote',
			array(
				'license_key'     => $license_key,
				'site_url'        => $site_url,
				'product'         => CG_License_Manager::PRODUCT_SLUG,
				'installation_id' => $installation_id ?: CG_License_Manager::get_installation_id(),
			)
		);
	}

	public function report_usage( string $license_key, int $count ): void {
		$this->request(
			'POST',
			'/api/payments/usage',
			array(
				'license_key'     => $license_key,
				'count'           => $count,
				'site_url'        => home_url(),
				'product'         => CG_License_Manager::PRODUCT_SLUG,
				'installation_id' => CG_License_Manager::get_installation_id(),
			),
			blocking: false
		);
	}

	private function request( string $method, string $path, array $data = array(), bool $blocking = true ): array {
		$url = $this->base_url . $path;

		$args = array(
			'method'   => $method,
			'blocking' => $blocking,
			'timeout'  => $blocking ? 15 : 1,
			'headers'  => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( ! empty( $this->api_key ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->api_key;
		}

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		} elseif ( ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		}

		$response = wp_remote_request( $url, $args );

		if ( ! $blocking ) {
			return array();
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( $code >= 400 ) {
			return array(
				'success' => false,
				'message' => $decoded['message'] ?? 'Request failed',
			);
		}

		return $decoded ?: array( 'success' => true );
	}
}
