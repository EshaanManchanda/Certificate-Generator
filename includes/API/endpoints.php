<?php
/**
 * Certificate Generator API Endpoints
 *
 * Provides REST API endpoints for AI integration and external certificate generation requests.
 * Includes secure API key authentication and comprehensive error handling.
 *
 * @package Certificate_Generator
 * @since 3.3.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function certificate_generator_using_custom_tables(): bool {
	static $result = null;
	if ( $result !== null ) {
		return $result;
	}
	global $wpdb;
	$table  = $wpdb->prefix . 'cg_students';
	$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	return $result;
}

/**
 * Register REST API routes for certificate generation
 */
add_action( 'rest_api_init', 'certificate_generator_register_api_routes' );

function certificate_generator_register_api_routes() {
	// Issue certificate endpoint
	register_rest_route(
		'certificate-generator/v1',
		'/issue-certificate',
		array(
			'methods'             => 'POST',
			'callback'            => 'certificate_generator_issue_certificate_callback',
			'permission_callback' => 'certificate_generator_api_permission_check',
			'args'                => array(
				'student_email' => array(
					'required'          => true,
					'type'              => 'string',
					'format'            => 'email',
					'sanitize_callback' => 'sanitize_email',
					'description'       => 'Email address of the student',
				),
			),
		)
	);

	// Health check endpoint
	register_rest_route(
		'certificate-generator/v1',
		'/health',
		array(
			'methods'             => 'GET',
			'callback'            => 'certificate_generator_health_check_callback',
			'permission_callback' => '__return_true',
		)
	);

	// API key validation endpoint
	register_rest_route(
		'certificate-generator/v1',
		'/validate-key',
		array(
			'methods'             => 'POST',
			'callback'            => 'certificate_generator_validate_key_callback',
			'permission_callback' => 'certificate_generator_api_permission_check',
		)
	);

	// Read-only: fetch existing certificates for an email (no PDF regeneration)
	register_rest_route(
		'certificate-generator/v1',
		'/certificates-by-email',
		array(
			'methods'             => 'GET',
			'callback'            => 'certificate_generator_get_certificates_by_email_callback',
			'permission_callback' => 'certificate_generator_api_permission_check',
			'args'                => array(
				'email' => array(
					'required'          => true,
					'type'              => 'string',
					'format'            => 'email',
					'sanitize_callback' => 'sanitize_email',
					'description'       => 'Student email address to look up',
				),
			),
		)
	);
}

/**
 * Permission callback for API endpoints - validates API key
 */
function certificate_generator_api_permission_check( $request ) {
	// Check if API access is enabled
	$api_enabled = get_option( 'certificate_generator_api_key_enabled', false );
	if ( ! $api_enabled ) {
		return new WP_Error(
			'api_disabled',
			'API access is currently disabled. Please enable it in the plugin settings.',
			array( 'status' => 403 )
		);
	}

	// ── Plan gate: API requires Pro or Business ──────────────────────────
	if ( class_exists( 'CG_License_Manager' ) && ! CG_License_Manager::is_pro() ) {
		return new WP_Error(
			'plan_required',
			'REST API access requires the Pro or Business plan. Upgrade at https://eshaanportfolio.vercel.app/',
			array( 'status' => 403 )
		);
	}

	// Get API key from Authorization header
	$auth_header = $request->get_header( 'authorization' );

	if ( empty( $auth_header ) ) {
		return new WP_Error(
			'missing_auth_header',
			'Authorization header is required',
			array( 'status' => 401 )
		);
	}

	// Extract Bearer token
	if ( ! preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
		return new WP_Error(
			'invalid_auth_format',
			'Authorization header must be in format: Bearer YOUR_API_KEY',
			array( 'status' => 401 )
		);
	}

	$provided_key = trim( $matches[1] );

	// Get stored API key from settings
	$stored_key = get_option( 'certificate_generator_api_key', '' );

	if ( empty( $stored_key ) ) {
		return new WP_Error(
			'api_key_not_configured',
			'API key not configured. Please set up API key in plugin settings.',
			array( 'status' => 500 )
		);
	}

	// Compare API keys (using constant time comparison for security)
	if ( ! hash_equals( $stored_key, $provided_key ) ) {
		// Log failed authentication attempt
		certificate_generator_log_api_activity(
			'FAILED_AUTH',
			array(
				'ip'         => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
				'timestamp'  => current_time( 'mysql' ),
			)
		);

		return new WP_Error(
			'invalid_api_key',
			'Invalid API key provided',
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Main callback for certificate issuance
 */
function certificate_generator_issue_certificate_callback( $request ) {
	certificate_generator_log_api_activity(
		'CERTIFICATE_REQUEST',
		array(
			'student_email' => $request->get_param( 'student_email' ),
			'ip'            => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
			'timestamp'     => current_time( 'mysql' ),
		)
	);

	try {
		$email = sanitize_email( $request->get_param( 'student_email' ) );

		if ( certificate_generator_using_custom_tables() ) {
			return certificate_generator_issue_from_table( $email, $request );
		}

		return certificate_generator_issue_from_cpt( $email, $request );
	} catch ( Exception $e ) {
		certificate_generator_log_api_activity(
			'EXCEPTION',
			array(
				'error'     => $e->getMessage(),
				'trace'     => $e->getTraceAsString(),
				'timestamp' => current_time( 'mysql' ),
			)
		);

		return new WP_Error(
			'internal_error',
			'An internal error occurred while processing the request.',
			array( 'status' => 500 )
		);
	}
}

function certificate_generator_issue_from_table( string $email, $request ): WP_REST_Response {
	global $wpdb;
	$studentsTable     = $wpdb->prefix . 'cg_students';
	$certificatesTable = $wpdb->prefix . 'cg_certificates';

	$students = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$studentsTable} WHERE email = %s",
			$email
		),
		ARRAY_A
	);

	if ( empty( $students ) ) {
		return new WP_Error(
			'student_not_found',
			'No student found with the provided email address.',
			array( 'status' => 404 )
		);
	}

	$certificates = array();
	foreach ( $students as $student ) {
		$studentId = (int) $student['id'];
		$certs     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$certificatesTable} WHERE student_id = %d ORDER BY issued_at DESC",
				$studentId
			),
			ARRAY_A
		);

		foreach ( $certs as $cert ) {
			if ( ! empty( $cert['pdf_path'] ) && file_exists( $cert['pdf_path'] ) ) {
				$certificates[] = array(
					'path'     => $cert['pdf_path'],
					'url'      => $cert['pdf_url'],
					'filename' => basename( $cert['pdf_path'] ),
				);
			}
		}
	}

	if ( empty( $certificates ) ) {
		return new WP_Error(
			'certificate_generation_failed',
			'Failed to generate certificates.',
			array( 'status' => 500 )
		);
	}

	if ( count( $certificates ) > 3 && function_exists( 'certificate_generator_create_zip_for_email' ) ) {
		$zip_result = certificate_generator_create_zip_for_email( $certificates, $email );
		if ( $zip_result && $zip_result['certificate_count'] > 0 ) {
			certificate_generator_log_api_activity(
				'CERTIFICATES_ZIPPED',
				array(
					'student_email' => $email,
					'download_url'  => $zip_result['zip_url'],
					'timestamp'     => current_time( 'mysql' ),
				)
			);
			return new WP_REST_Response(
				array(
					'status'            => 'success',
					'message'           => 'Certificates generated and zipped successfully.',
					'download_url'      => $zip_result['zip_url'],
					'certificate_count' => $zip_result['certificate_count'],
				),
				200
			);
		}
	}

	certificate_generator_log_api_activity(
		'CERTIFICATE_GENERATED',
		array(
			'student_email' => $email,
			'download_url'  => $certificates[0]['url'],
			'timestamp'     => current_time( 'mysql' ),
		)
	);

	return new WP_REST_Response(
		array(
			'status'       => 'success',
			'message'      => 'Certificate generated successfully.',
			'download_url' => $certificates[0]['url'],
		),
		200
	);
}

function certificate_generator_issue_from_cpt( string $email, $request ) {
	$args = array(
		'post_type'      => 'students',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'     => 'email',
				'value'   => $email,
				'compare' => '=',
			),
		),
	);

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return new WP_Error(
			'student_not_found',
			'No student found with the provided email address.',
			array( 'status' => 404 )
		);
	}

	$certificates = array();

	while ( $query->have_posts() ) {
		$query->the_post();
		$post_id       = get_the_ID();
		$cert_type_api = get_post_meta( $post_id, 'certificate_type', true );
		$fields        = class_exists( 'CG_Field_Schema' )
			? CG_Field_Schema::get_all_renderable_fields( $cert_type_api )
			: array( 'student_name', 'school_name', 'issue_date' );
		$file_url      = generate_certificate_pdf( $post_id, $fields );

		if ( $file_url ) {
			$file_path      = get_post_meta( $post_id, 'certificate_file_path', true );
			$filename       = basename( $file_path );
			$certificates[] = array(
				'path'     => $file_path,
				'url'      => $file_url,
				'filename' => $filename,
			);
		}
	}
	wp_reset_postdata();

	if ( empty( $certificates ) ) {
		return new WP_Error(
			'certificate_generation_failed',
			'Failed to generate certificates.',
			array( 'status' => 500 )
		);
	}

	if ( count( $certificates ) > 3 && function_exists( 'certificate_generator_create_zip_for_email' ) ) {
		$zip_result = certificate_generator_create_zip_for_email( $certificates, $email );
		if ( $zip_result && $zip_result['certificate_count'] > 0 ) {
			certificate_generator_log_api_activity(
				'CERTIFICATES_ZIPPED',
				array(
					'student_email' => $email,
					'download_url'  => $zip_result['zip_url'],
					'timestamp'     => current_time( 'mysql' ),
				)
			);
			return new WP_REST_Response(
				array(
					'status'            => 'success',
					'message'           => 'Certificates generated and zipped successfully.',
					'download_url'      => $zip_result['zip_url'],
					'certificate_count' => $zip_result['certificate_count'],
				),
				200
			);
		}
	}

	certificate_generator_log_api_activity(
		'CERTIFICATE_GENERATED',
		array(
			'student_email' => $email,
			'download_url'  => $certificates[0]['url'],
			'timestamp'     => current_time( 'mysql' ),
		)
	);

	return new WP_REST_Response(
		array(
			'status'       => 'success',
			'message'      => 'Certificate generated successfully.',
			'download_url' => $certificates[0]['url'],
		),
		200
	);
}

/**
 * Health check callback
 */
function certificate_generator_health_check_callback( $request ) {
	return new WP_REST_Response(
		array(
			'status'            => 'healthy',
			'plugin_version'    => '3.3.1',
			'wordpress_version' => get_bloginfo( 'version' ),
			'timestamp'         => current_time( 'c' ),
		),
		200
	);
}

/**
 * API key validation callback
 */
function certificate_generator_validate_key_callback( $request ) {
	return new WP_REST_Response(
		array(
			'status'    => 'valid',
			'message'   => 'API key is valid',
			'timestamp' => current_time( 'c' ),
		),
		200
	);
}

/**
 * Get certificate post by type
 */
function certificate_generator_get_certificate_by_type( $certificate_type ) {
	$certificates = get_posts(
		array(
			'post_type'      => 'certificate',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => 'certificate_type',
					'value'   => $certificate_type,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
		)
	);

	return ! empty( $certificates ) ? $certificates[0] : false;
}

/**
 * Get or create student record
 */
function certificate_generator_get_or_create_student( $email, $name = '', $school_name = '' ) {
	// First, try to find existing student by email
	$existing_students = get_posts(
		array(
			'post_type'      => 'student',
			'meta_query'     => array(
				array(
					'key'     => 'student_email',
					'value'   => $email,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
		)
	);

	if ( ! empty( $existing_students ) ) {
		return $existing_students[0]->ID;
	}

	// Create new student if not found
	$student_data = array(
		'post_title'  => $name ?: $email,
		'post_type'   => 'student',
		'post_status' => 'publish',
		'meta_input'  => array(
			'student_email' => $email,
			'student_name'  => $name,
			'school_name'   => $school_name,
		),
	);

	$student_id = wp_insert_post( $student_data );

	if ( is_wp_error( $student_id ) ) {
		return new WP_Error(
			'student_creation_failed',
			'Failed to create student record: ' . $student_id->get_error_message(),
			array( 'status' => 500 )
		);
	}

	return $student_id;
}

/**
 * Check for existing certificate
 */
function certificate_generator_check_existing_certificate( $student_id, $certificate_id ) {
	// This would depend on how you track issued certificates
	// You might have a custom table or use post meta
	// For now, returning false to allow certificate generation
	return false;
}

/**
 * Generate certificate PDF for API requests
 */
function certificate_generator_generate_certificate_api( $student_id, $certificate_id, $issue_date ) {
	// Include the existing certificate generation functions
	if ( ! function_exists( 'generate_certificate_pdf' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '../Services/certificate-search.php';
	}

	$student     = get_post( $student_id );
	$certificate = get_post( $certificate_id );

	if ( ! $student || ! $certificate ) {
		return new WP_Error(
			'invalid_records',
			'Student or certificate record not found',
			array( 'status' => 404 )
		);
	}

	// Get student data
	$student_email = get_post_meta( $student_id, 'student_email', true );
	$student_name  = get_post_meta( $student_id, 'student_name', true ) ?: $student->post_title;
	$school_name   = get_post_meta( $student_id, 'school_name', true );

	// Generate unique filename
	$filename         = function_exists( 'cg_certificate_pdf_filename' )
		? cg_certificate_pdf_filename( $student_name, '', $student_id . '_' . $certificate_id )
		: 'certificate_' . $student_id . '_' . $certificate_id . '_' . time() . '.pdf';
	$upload_dir       = wp_upload_dir();
	$certificates_dir = trailingslashit( function_exists( 'cg_certificates_dir' ) ? cg_certificates_dir() : $upload_dir['basedir'] . '/cg_certificates' );

	// Create directory if it doesn't exist
	if ( ! file_exists( $certificates_dir ) ) {
		wp_mkdir_p( $certificates_dir );
	}

	$file_path    = $certificates_dir . $filename;
	$download_url = ( function_exists( 'cg_certificates_url' ) ? trailingslashit( cg_certificates_url() ) : $upload_dir['baseurl'] . '/cg_certificates/' ) . $filename;

	// Generate PDF using existing function
	try {
		$pdf_generated = generate_certificate_pdf(
			$student_name,
			$student_email,
			$school_name,
			$issue_date,
			$certificate->post_title,
			$file_path,
			$certificate_id
		);

		if ( ! $pdf_generated ) {
			return new WP_Error(
				'pdf_generation_failed',
				'Failed to generate PDF certificate',
				array( 'status' => 500 )
			);
		}

		return array(
			'file_path'      => $file_path,
			'download_url'   => $download_url,
			'certificate_id' => $certificate_id,
			'filename'       => $filename,
		);

	} catch ( Exception $e ) {
		return new WP_Error(
			'pdf_generation_error',
			'Error generating PDF: ' . $e->getMessage(),
			array( 'status' => 500 )
		);
	}
}

/**
 * Log API activity
 */
function certificate_generator_log_api_activity( $action, $data = array() ) {
	$log_entry = array(
		'timestamp' => current_time( 'mysql' ),
		'action'    => $action,
		'data'      => $data,
	);

	// Log to WordPress debug log if enabled
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( 'Certificate Generator API: ' . json_encode( $log_entry ) );
	}

	// Store in database for admin viewing
	$existing_logs   = get_option( 'certificate_generator_api_logs', array() );
	$existing_logs[] = $log_entry;

	// Keep only last 100 log entries
	if ( count( $existing_logs ) > 100 ) {
		$existing_logs = array_slice( $existing_logs, -100 );
	}

	update_option( 'certificate_generator_api_logs', $existing_logs );
}

/**
 * Read-only lookup: return existing certificates for a student email without regenerating PDFs.
 *
 * Response shape:
 *   { status, site_name, certificates: [ { title, certificate_type, pdf_url, issued_at, serial_number } ] }
 */
function certificate_generator_get_certificates_by_email_callback( $request ) {
	$email = sanitize_email( $request->get_param( 'email' ) );

	if ( empty( $email ) ) {
		return new WP_Error( 'invalid_email', 'A valid email is required.', array( 'status' => 400 ) );
	}

	if ( certificate_generator_using_custom_tables() ) {
		return certificate_generator_get_certs_by_email_table( $email );
	}

	return certificate_generator_get_certs_by_email_cpt( $email );
}

function certificate_generator_get_certs_by_email_table( string $email ): WP_REST_Response {
	global $wpdb;
	$certsTable    = $wpdb->prefix . 'cg_certificates';
	$studentsTable = $wpdb->prefix . 'cg_students';

	$certs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$certsTable} WHERE recipient_email = %s ORDER BY issued_at DESC",
			$email
		),
		ARRAY_A
	);

	$certificates = array();
	foreach ( $certs as $cert ) {
		$certificates[] = certificate_generator_build_cert_data_from_table( $cert );
	}

	return new WP_REST_Response(
		array(
			'status'       => 'success',
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'email'        => $email,
			'certificates' => $certificates,
			'count'        => count( $certificates ),
		),
		200
	);
}

function certificate_generator_get_certs_by_email_cpt( string $email ): WP_REST_Response {
	$certificates  = array();
	$student_query = new WP_Query(
		array(
			'post_type'      => 'students',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'post_status'    => 'any',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'email',
					'value'   => $email,
					'compare' => '=',
				),
				array(
					'key'     => 'student_email',
					'value'   => $email,
					'compare' => '=',
				),
			),
		)
	);

	$student_ids = array();
	while ( $student_query->have_posts() ) {
		$student_query->the_post();
		$student_ids[] = get_the_ID();
	}
	wp_reset_postdata();

	if ( ! empty( $student_ids ) ) {
		$cert_query = new WP_Query(
			array(
				'post_type'      => 'certificates',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'post_status'    => 'any',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'student_id',
						'value'   => $student_ids,
						'compare' => 'IN',
					),
					array(
						'key'     => 'student',
						'value'   => $student_ids,
						'compare' => 'IN',
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		while ( $cert_query->have_posts() ) {
			$cert_query->the_post();
			$certificates[] = certificate_generator_build_api_cert_data( get_the_ID() );
		}
		wp_reset_postdata();
	}

	if ( empty( $certificates ) ) {
		$cert_query = new WP_Query(
			array(
				'post_type'      => 'certificates',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'post_status'    => 'any',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'student_email',
						'value'   => $email,
						'compare' => '=',
					),
					array(
						'key'     => 'email',
						'value'   => $email,
						'compare' => '=',
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		while ( $cert_query->have_posts() ) {
			$cert_query->the_post();
			$certificates[] = certificate_generator_build_api_cert_data( get_the_ID() );
		}
		wp_reset_postdata();
	}

	if ( empty( $certificates ) ) {
		global $wpdb;
		$log_table = $wpdb->prefix . 'cert_email_logs';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) ) === $log_table ) {
			$log_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT certificate_id, certificate_type, sent_at, status
                 FROM {$log_table}
                 WHERE recipient_email = %s AND status = 'sent'
                 ORDER BY sent_at DESC LIMIT 20",
					$email
				),
				ARRAY_A
			);

			$seen = array();
			foreach ( $log_rows as $row ) {
				$post_id = (int) $row['certificate_id'];
				if ( ! $post_id || isset( $seen[ $post_id ] ) ) {
					continue;
				}
				$seen[ $post_id ] = true;
				$cert             = certificate_generator_build_api_cert_data( $post_id, $row );
				if ( $cert ) {
					$certificates[] = $cert;
				}
			}
		}
	}

	return new WP_REST_Response(
		array(
			'status'       => 'success',
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'email'        => $email,
			'certificates' => $certificates,
			'count'        => count( $certificates ),
		),
		200
	);
}

/**
 * Build a normalized certificate data array for the API response.
 *
 * @param int        $post_id WP post ID (certificates CPT or any post with cert meta).
 * @param array|null $log_row Optional wp_cert_email_logs row for fallback data.
 * @return array|null
 */
function certificate_generator_build_api_cert_data( $post_id, $log_row = null ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return null;
	}

	$pdf_url = get_post_meta( $post_id, 'certificate_file_url', true )
			?: get_post_meta( $post_id, 'pdf_url', true )
			?: get_post_meta( $post_id, 'certificate_url', true )
			?: get_post_meta( $post_id, 'download_url', true );

	$cert_type = get_post_meta( $post_id, 'certificate_type', true )
				?: ( $log_row['certificate_type'] ?? '' );

	$serial = get_post_meta( $post_id, 'certificate_serial_number', true )
			?: get_post_meta( $post_id, 'serial_number', true );

	$issued_at = get_post_meta( $post_id, 'issue_date', true )
				?: get_post_meta( $post_id, 'issued_at', true )
				?: ( $log_row['sent_at'] ?? $post->post_date );

	$status = get_post_meta( $post_id, 'status', true )
			?: ( $log_row['status'] ?? 'issued' );

	return array(
		'title'            => $post->post_title,
		'certificate_type' => sanitize_text_field( $cert_type ),
		'pdf_url'          => $pdf_url ? esc_url_raw( $pdf_url ) : '',
		'issued_at'        => $issued_at ? date( 'Y-m-d', strtotime( $issued_at ) ) : '',
		'serial_number'    => sanitize_text_field( $serial ),
		'status'           => sanitize_text_field( $status ),
	);
}


function certificate_generator_find_students_by_email_table( string $email ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'cg_students';
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE email = %s",
			$email
		),
		ARRAY_A
	);
}

function certificate_generator_find_certificates_by_student_table( int $studentId ): array {
	global $wpdb;
	$certsTable = $wpdb->prefix . 'cg_certificates';
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$certsTable} WHERE student_id = %d ORDER BY issued_at DESC",
			$studentId
		),
		ARRAY_A
	);
}

function certificate_generator_find_certificates_by_email_table( string $email ): array {
	global $wpdb;
	$certsTable = $wpdb->prefix . 'cg_certificates';
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$certsTable} WHERE recipient_email = %s ORDER BY issued_at DESC",
			$email
		),
		ARRAY_A
	);
}

function certificate_generator_build_cert_data_from_table( array $row ): array {
	return array(
		'title'            => $row['recipient_name'] ?? '',
		'certificate_type' => $row['certificate_type'] ?? '',
		'pdf_url'          => $row['pdf_url'] ?? '',
		'issued_at'        => $row['issued_at'] ? date( 'Y-m-d', strtotime( $row['issued_at'] ) ) : '',
		'serial_number'    => $row['serial_number'] ?? '',
		'status'           => $row['status'] ?? 'issued',
	);
}
