<?php
/**
 * License Verification REST Endpoint
 *
 * Exposes a single admin-only endpoint for verifying a license key against
 * the portfolio backend. All payment/subscription routes removed in v7.0.0.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'cg_register_license_endpoints' );

function cg_register_license_endpoints(): void {
	register_rest_route(
		'cg/v1',
		'/verify-license',
		array(
			'methods'             => 'POST',
			'callback'            => 'cg_rest_verify_license',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}

function cg_rest_verify_license( WP_REST_Request $request ): array|WP_Error {
	$rate_key = 'cg_verify_rate_' . md5( home_url() );
	$calls    = (int) get_transient( $rate_key );
	if ( $calls >= 5 ) {
		return new WP_Error( 'rate_limited', 'Too many verification requests. Try again in 60 seconds.', array( 'status' => 429 ) );
	}
	set_transient( $rate_key, $calls + 1, 60 );

	$key = sanitize_text_field( $request->get_param( 'license_key' ) );
	if ( empty( $key ) ) {
		return new WP_Error( 'missing_key', 'license_key is required', array( 'status' => 400 ) );
	}

	require_once CERTIFICATE_GENERATOR_PATH . 'includes/Core/license-manager.php';

	$result = CG_License_Manager::remote_validate( $key );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return $result;
}
