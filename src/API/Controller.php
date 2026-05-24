<?php
declare(strict_types=1);

namespace CertificateGenerator\API;

/**
 * Base REST controller with common middleware.
 */
abstract class Controller {

	protected string $namespace = 'certificate-generator/v1';
	protected string $base;

	public function __construct( string $base ) {
		$this->base = $base;
	}

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	public function check_permission(): bool {
		return true;
	}

	protected function success( $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	protected function error( string $message, int $status = 400 ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			$status
		);
	}

	abstract public function get_items( $request );
}
