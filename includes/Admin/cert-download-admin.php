<?php
/**
 * Admin: Download Student Certificates
 *
 * Filter page that lets an admin query students by email, school, cert type,
 * year, and date range — then download individual PDFs or bulk ZIPs (auto-split
 * into parts of CG_ADMIN_EXPORT_ZIP_PART_SIZE certificates each).
 *
 * @package Certificate Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Part size — defined in certificate-generator.php; fallback for unit tests.
if ( ! defined( 'CG_ADMIN_EXPORT_ZIP_PART_SIZE' ) ) {
	define( 'CG_ADMIN_EXPORT_ZIP_PART_SIZE', 200 );
}

// ── Action hooks (fire before headers sent) ──────────────────────────────────

add_action( 'admin_init', 'cg_handle_admin_cert_zip_download' );
add_action( 'admin_init', 'cg_handle_admin_individual_cert_download' );

// ── ZIP download handler (partitioned) ───────────────────────────────────────

function cg_handle_admin_cert_zip_download(): void {
	if ( ! isset( $_POST['cg_download_zip'] ) ) {
		return;
	}

	check_admin_referer( 'cg_admin_cert_zip', '_wpnonce_cg_zip' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
	}

	$filters   = cg_admin_cert_read_filters();
	$part_size = CG_ADMIN_EXPORT_ZIP_PART_SIZE;
	$total     = cg_admin_cert_count( $filters );
	$plan      = cg_admin_cert_part_plan( $total, $part_size );
	$part      = absint( $_POST['cg_zip_part'] ?? 0 );

	if ( 0 === $plan['num_parts'] ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'cg-cert-download',
					'cg_error' => 'no_results',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	if ( $part >= $plan['num_parts'] ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'cg-cert-download',
					'cg_error' => 'bad_part',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	$rows = cg_admin_cert_query( $filters, $part_size, $part * $part_size );

	if ( empty( $rows ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'cg-cert-download',
					'cg_error' => 'no_results',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// Raise limits — best-effort; the 200-cert part size is the real safety margin.
	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$cur_mem = cg_admin_parse_memory_mb( (string) ini_get( 'memory_limit' ) );
	if ( -1 !== $cur_mem && $cur_mem < 512 ) {
		@ini_set( 'memory_limit', '512M' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	@ignore_user_abort( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	// Generate PDFs into a secured temp dir.
	$upload_dir = wp_upload_dir();
	$temp_dir   = $upload_dir['basedir'] . '/temp_cg_admin_' . time() . '_' . wp_generate_password( 8, false );

	if ( ! mkdir( $temp_dir, 0755, true ) ) {
		wp_die( esc_html__( 'Could not create temporary directory.', 'certificate-generator' ) );
	}
	file_put_contents( $temp_dir . '/.htaccess', 'Deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	$cert_files    = array();
	$manifest_rows = array();

	foreach ( $rows as $row ) {
		$post_id   = (int) ( $row['wp_post_id'] ?? 0 );
		$cert_type = $row['certificate_type'] ?? '';

		if ( empty( $cert_type ) ) {
			continue;
		}

		$fields = class_exists( 'CG_Field_Schema' )
			? CG_Field_Schema::get_all_renderable_fields( $cert_type )
			: array( 'student_name', 'school_name', 'issue_date' );

		$file_url = function_exists( 'generate_certificate_pdf' )
			? generate_certificate_pdf( $post_id, $fields, $row )
			: null;

		if ( ! $file_url ) {
			continue;
		}

		$file_path = $post_id > 0 ? get_post_meta( $post_id, 'certificate_file_path', true ) : '';
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$file_path = str_replace( $upload_dir['url'], $upload_dir['basedir'], $file_url );
		}

		if ( ! file_exists( $file_path ) ) {
			continue;
		}

		$row_id    = (int) ( $row['id'] ?? 0 );
		$safe_name = function_exists( 'cg_certificate_pdf_filename' )
			? cg_certificate_pdf_filename( $row['student_name'] ?? 'student', $cert_type, (string) $row_id )
			: sanitize_file_name( ( $row['student_name'] ?? 'student' ) . '_' . $row_id . '.pdf' );

		$dest = $temp_dir . '/' . $safe_name;
		if ( copy( $file_path, $dest ) ) {
			$cert_files[]    = array(
				'path'     => $dest,
				'filename' => $safe_name,
			);
			$manifest_rows[] = array(
				$row['student_name'] ?? '',
				$row['email'] ?? '',
				$row['school_name'] ?? '',
				$cert_type,
				(string) $row_id,
				$safe_name,
			);
		}
	}

	if ( empty( $cert_files ) ) {
		@rmdir( $temp_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'cg-cert-download',
					'cg_error' => 'no_pdfs',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// Write manifest.csv into the ZIP.
	$manifest_path = $temp_dir . '/manifest.csv';
	$mfp           = fopen( $manifest_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( $mfp ) {
		fputcsv( $mfp, array( 'name', 'email', 'school', 'cert_type', 'cg_id', 'pdf_filename' ) );
		foreach ( $manifest_rows as $mr ) {
			fputcsv( $mfp, $mr );
		}
		fclose( $mfp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$cert_files[] = array(
			'path'     => $manifest_path,
			'filename' => 'manifest.csv',
		);
	}

	if ( ! function_exists( 'certificate_generator_create_zip_for_email' ) ) {
		foreach ( $cert_files as $f ) {
			@unlink( $f['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		@rmdir( $temp_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		wp_die( esc_html__( 'ZIP service unavailable.', 'certificate-generator' ) );
	}

	$zip_label  = cg_admin_cert_zip_label( $filters, $part, $plan['num_parts'] );
	$zip_result = certificate_generator_create_zip_for_email( $cert_files, $zip_label );

	foreach ( $cert_files as $f ) {
		@unlink( $f['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	@rmdir( $temp_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	if ( ! $zip_result || empty( $zip_result['zip_path'] ) || ! file_exists( $zip_result['zip_path'] ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'cg-cert-download',
					'cg_error' => 'zip_failed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	if ( ob_get_level() ) {
		ob_end_clean();
	}

	$zip_path    = $zip_result['zip_path'];
	$dl_filename = cg_admin_cert_zip_download_name( $filters, $part, $plan['num_parts'] );

	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $dl_filename . '"' );
	header( 'Content-Length: ' . filesize( $zip_path ) );
	header( 'Cache-Control: no-cache, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	readfile( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	@unlink( $zip_path );  // phpcs:ignore WordPress.PHP.NoSilencedErrors
	exit;
}

// ── Individual PDF download handler (admin-side, works with SQL id) ───────────

function cg_handle_admin_individual_cert_download(): void {
	if ( ! isset( $_GET['action'] ) || 'cg_admin_download_cert' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$sql_id = absint( $_GET['sql_id'] ?? 0 );
	check_admin_referer( 'cg_admin_dl_cert_' . $sql_id );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
	}

	if ( ! $sql_id || ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		wp_die( esc_html__( 'Invalid student ID.', 'certificate-generator' ) );
	}

	global $wpdb;
	$tbl = \CertificateGenerator\Database\CustomTables::instance()->get_table( 'students' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", $sql_id ), ARRAY_A );

	if ( ! $row ) {
		wp_die( esc_html__( 'Student not found.', 'certificate-generator' ) );
	}

	$cert_type = $row['certificate_type'] ?? '';
	if ( empty( $cert_type ) ) {
		wp_die( esc_html__( 'No certificate type assigned.', 'certificate-generator' ) );
	}

	if ( ! empty( $row['extra_fields'] ) ) {
		$extra = json_decode( $row['extra_fields'], true );
		if ( is_array( $extra ) ) {
			$row = array_merge( $row, $extra );
		}
	}
	unset( $row['extra_fields'] );

	$fields = class_exists( 'CG_Field_Schema' )
		? CG_Field_Schema::get_all_renderable_fields( $cert_type )
		: array( 'student_name', 'school_name', 'issue_date' );

	$post_id  = (int) ( $row['wp_post_id'] ?? 0 );
	$file_url = function_exists( 'generate_certificate_pdf' )
		? generate_certificate_pdf( $post_id, $fields, $row )
		: null;

	if ( ! $file_url ) {
		wp_die( esc_html__( 'Failed to generate certificate PDF.', 'certificate-generator' ) );
	}

	$upload_dir = wp_upload_dir();
	$file_path  = $post_id > 0 ? get_post_meta( $post_id, 'certificate_file_path', true ) : '';
	if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
		$file_path = str_replace( $upload_dir['url'], $upload_dir['basedir'], $file_url );
	}

	if ( ! file_exists( $file_path ) ) {
		wp_die( esc_html__( 'Certificate PDF file not found.', 'certificate-generator' ) );
	}

	$filename = sanitize_file_name( ( $row['student_name'] ?? 'student' ) . '_certificate.pdf' );

	if ( ob_get_level() ) {
		ob_end_clean();
	}

	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . filesize( $file_path ) );
	header( 'Cache-Control: no-cache, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

// ── Shared helpers ────────────────────────────────────────────────────────────

/**
 * Read and sanitize filter fields from $_GET / $_POST / $_REQUEST.
 *
 * @param string $source 'GET', 'POST', or 'REQUEST' (default).
 * @return array Normalised filter array compatible with cg_build_recipient_filter_sql().
 */
function cg_admin_cert_read_filters( string $source = 'REQUEST' ): array {
	// phpcs:disable WordPress.Security.NonceVerification
	if ( $source === 'POST' ) {
		$bag = $_POST;
	} elseif ( $source === 'GET' ) {
		$bag = $_GET;
	} else {
		$bag = $_REQUEST;
	}

	return array(
		'schools'           => isset( $bag['filter_school'] ) && is_array( $bag['filter_school'] )
			? array_map( 'sanitize_text_field', $bag['filter_school'] )
			: array(),
		'certificate_types' => isset( $bag['filter_cert_type'] ) && is_array( $bag['filter_cert_type'] )
			? array_map( 'sanitize_text_field', $bag['filter_cert_type'] )
			: array(),
		'year'              => isset( $bag['filter_year'] ) && is_array( $bag['filter_year'] )
			? array_map( 'intval', $bag['filter_year'] )
			: array(),
		'date_from'         => isset( $bag['filter_date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bag['filter_date_from'] )
			? sanitize_text_field( $bag['filter_date_from'] )
			: '',
		'date_to'           => isset( $bag['filter_date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $bag['filter_date_to'] )
			? sanitize_text_field( $bag['filter_date_to'] )
			: '',
		'email_search'      => isset( $bag['filter_email'] ) ? sanitize_text_field( $bag['filter_email'] ) : '',
		'emails'            => array(),
	);
	// phpcs:enable WordPress.Security.NonceVerification
}

/**
 * Query students from the SQL custom table using the given filters.
 * Ordered by student_name ASC, id ASC for deterministic pagination.
 *
 * @param array $filters   Normalised filter array from cg_admin_cert_read_filters().
 * @param int   $limit     Max rows to return.
 * @param int   $offset    Offset for pagination.
 * @return array[]         Flat row arrays (extra_fields decoded and merged).
 */
function cg_admin_cert_query( array $filters, int $limit = 200, int $offset = 0 ): array {
	if (
		! class_exists( '\CertificateGenerator\Database\CustomTables' ) ||
		! function_exists( 'cg_build_recipient_filter_sql' )
	) {
		return array();
	}

	global $wpdb;
	$tables = \CertificateGenerator\Database\CustomTables::instance();
	$tbl    = $tables->get_table( 'students' );

	if ( ! $tbl || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
		return array();
	}

	[ $frags, $params ] = cg_build_recipient_filter_sql( $filters, '' );
	$where              = $frags ? ' WHERE ' . implode( ' AND ', $frags ) : '';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$sql      = "SELECT * FROM {$tbl}{$where} ORDER BY student_name ASC, id ASC LIMIT %d OFFSET %d";
	$params[] = $limit;
	$params[] = $offset;

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
	if ( ! $rows ) {
		return array();
	}

	// Merge extra_fields JSON into each row as flat keys.
	foreach ( $rows as &$row ) {
		if ( ! empty( $row['extra_fields'] ) ) {
			$extra = json_decode( $row['extra_fields'], true );
			if ( is_array( $extra ) ) {
				$row = array_merge( $row, $extra );
			}
		}
		unset( $row['extra_fields'] );
	}
	unset( $row );

	return $rows;
}

/**
 * Count total students matching filters (no LIMIT applied).
 */
function cg_admin_cert_count( array $filters ): int {
	if (
		! class_exists( '\CertificateGenerator\Database\CustomTables' ) ||
		! function_exists( 'cg_build_recipient_filter_sql' )
	) {
		return 0;
	}

	global $wpdb;
	$tbl = \CertificateGenerator\Database\CustomTables::instance()->get_table( 'students' );

	if ( ! $tbl || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
		return 0;
	}

	[ $frags, $params ] = cg_build_recipient_filter_sql( $filters, '' );
	$where              = $frags ? ' WHERE ' . implode( ' AND ', $frags ) : '';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$sql = "SELECT COUNT(*) FROM {$tbl}{$where}";

	if ( $params ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}
	return (int) $wpdb->get_var( $sql );
}

// ── Part math + naming helpers ───────────────────────────────────────────────

/**
 * Compute the number of ZIP parts needed.
 *
 * @return array{part_size: int, num_parts: int}
 */
function cg_admin_cert_part_plan( int $total, int $part_size ): array {
	$part_size = max( 1, $part_size );
	if ( $total <= 0 ) {
		return array(
			'part_size' => $part_size,
			'num_parts' => 0,
		);
	}
	return array(
		'part_size' => $part_size,
		'num_parts' => (int) ceil( $total / $part_size ),
	);
}

/**
 * Parse a PHP memory value string ("256M", "1G", "-1") into megabytes.
 * Returns -1 for unlimited.
 */
function cg_admin_parse_memory_mb( string $val ): int {
	$val = trim( $val );
	if ( '-1' === $val ) {
		return -1;
	}
	$unit = strtolower( substr( $val, -1 ) );
	$num  = (int) $val;
	return match ( $unit ) {
		'g'     => $num * 1024,
		'm'     => $num,
		'k'     => (int) ceil( $num / 1024 ),
		default => (int) ceil( $num / 1048576 ), // bare bytes
	};
}

/**
 * Build a slug for the ZIP label (passed to the ZIP builder for internal filename).
 */
function cg_admin_cert_zip_label( array $filters, int $part, int $num_parts ): string {
	$pieces = array( 'admin' );

	if ( ! empty( $filters['year'] ) ) {
		$pieces[] = implode( '-', array_slice( $filters['year'], 0, 2 ) );
	}
	if ( ! empty( $filters['certificate_types'] ) ) {
		$pieces[] = sanitize_title( $filters['certificate_types'][0] );
	}
	if ( $num_parts > 1 ) {
		$pieces[] = 'part' . ( $part + 1 ) . 'of' . $num_parts;
	}
	$pieces[] = gmdate( 'Y-m-d' );

	return implode( '_', $pieces );
}

/**
 * Build the Content-Disposition download filename for the streamed ZIP.
 */
function cg_admin_cert_zip_download_name( array $filters, int $part, int $num_parts ): string {
	$pieces = array( 'certificates' );

	if ( ! empty( $filters['year'] ) ) {
		$pieces[] = implode( '-', array_slice( $filters['year'], 0, 2 ) );
	}
	if ( ! empty( $filters['certificate_types'] ) ) {
		$pieces[] = sanitize_title( $filters['certificate_types'][0] );
	}
	if ( $num_parts > 1 ) {
		$pieces[] = 'part' . ( $part + 1 ) . '_of_' . $num_parts;
	}
	$pieces[] = gmdate( 'Y-m-d' );

	return sanitize_file_name( implode( '_', $pieces ) . '.zip' );
}

// ── Page renderer ─────────────────────────────────────────────────────────────

function cg_render_admin_cert_download_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ) );
	}

	$part_size  = CG_ADMIN_EXPORT_ZIP_PART_SIZE;
	$years      = function_exists( 'certificate_generator_get_unique_years' ) ? certificate_generator_get_unique_years() : array();
	$schools    = function_exists( 'certificate_generator_get_unique_schools' ) ? certificate_generator_get_unique_schools() : array();
	$cert_types = function_exists( 'certificate_generator_get_unique_certificate_types' ) ? certificate_generator_get_unique_certificate_types() : array();

	// Filters come from GET (preview request).
	$active    = cg_admin_cert_read_filters( 'GET' );
	$previewed = isset( $_GET['cg_preview'] ) && '1' === $_GET['cg_preview']; // phpcs:ignore WordPress.Security.NonceVerification
	$total     = $previewed ? cg_admin_cert_count( $active ) : 0;
	$rows      = $previewed ? cg_admin_cert_query( $active, $part_size, 0 ) : array();
	$plan      = cg_admin_cert_part_plan( $total, $part_size );

	$error          = isset( $_GET['cg_error'] ) ? sanitize_key( $_GET['cg_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$error_messages = array(
		'no_results' => __( 'No students matched the selected filters.', 'certificate-generator' ),
		'no_pdfs'    => __( 'No certificate PDFs could be generated. Ensure students have valid certificate templates assigned.', 'certificate-generator' ),
		'zip_failed' => __( 'ZIP file creation failed. Please try again.', 'certificate-generator' ),
		'bad_part'   => __( 'Invalid download part requested. Please try again from the preview.', 'certificate-generator' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Download Student Certificates', 'certificate-generator' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Filter students, preview matches, then download individual PDFs or bulk ZIPs of all certificates.', 'certificate-generator' ); ?></p>

		<?php if ( $error && isset( $error_messages[ $error ] ) ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $error_messages[ $error ] ); ?></p>
			</div>
		<?php endif; ?>

		<?php /* ── Filter form (GET) ── */ ?>
		<form method="get" action="">
			<input type="hidden" name="page" value="cg-cert-download">
			<input type="hidden" name="cg_preview" value="1">

			<div style="background:#fff;border:1px solid #c3c4c7;padding:20px 24px;margin:16px 0;border-radius:4px;max-width:860px;">
				<h2 style="margin-top:0;font-size:15px;"><?php esc_html_e( 'Filter Students', 'certificate-generator' ); ?></h2>

				<table class="form-table" style="max-width:820px;">
					<tr>
						<th style="width:160px;padding:8px 10px 8px 0;">
							<label for="cg_filter_email"><?php esc_html_e( 'Email', 'certificate-generator' ); ?></label>
						</th>
						<td style="padding:8px 0;">
							<input type="text" id="cg_filter_email" name="filter_email"
								value="<?php echo esc_attr( $active['email_search'] ); ?>"
								placeholder="<?php esc_attr_e( 'e.g. gmail.com', 'certificate-generator' ); ?>"
								style="min-width:260px;">
							<p class="description"><?php esc_html_e( 'Partial match — leave empty for all.', 'certificate-generator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px 8px 0;">
							<label for="cg_filter_school"><?php esc_html_e( 'School', 'certificate-generator' ); ?></label>
						</th>
						<td style="padding:8px 0;">
							<?php if ( empty( $schools ) ) : ?>
								<em style="color:#888;"><?php esc_html_e( 'No schools found.', 'certificate-generator' ); ?></em>
							<?php else : ?>
								<select id="cg_filter_school" name="filter_school[]" multiple size="5" style="min-width:260px;">
									<?php foreach ( $schools as $s ) : ?>
										<option value="<?php echo esc_attr( $s ); ?>"
											<?php selected( in_array( $s, $active['schools'], true ) ); ?>>
											<?php echo esc_html( $s ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Ctrl/Cmd+click for multiple. Empty = all schools.', 'certificate-generator' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px 8px 0;">
							<label for="cg_filter_cert_type"><?php esc_html_e( 'Certificate Type', 'certificate-generator' ); ?></label>
						</th>
						<td style="padding:8px 0;">
							<?php if ( empty( $cert_types ) ) : ?>
								<em style="color:#888;"><?php esc_html_e( 'No certificate types found.', 'certificate-generator' ); ?></em>
							<?php else : ?>
								<select id="cg_filter_cert_type" name="filter_cert_type[]" multiple size="4" style="min-width:260px;">
									<?php foreach ( $cert_types as $c ) : ?>
										<option value="<?php echo esc_attr( $c ); ?>"
											<?php selected( in_array( $c, $active['certificate_types'], true ) ); ?>>
											<?php echo esc_html( $c ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Empty = all types.', 'certificate-generator' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px 8px 0;">
							<label for="cg_filter_year"><?php esc_html_e( 'Year', 'certificate-generator' ); ?></label>
						</th>
						<td style="padding:8px 0;">
							<?php if ( empty( $years ) ) : ?>
								<em style="color:#888;"><?php esc_html_e( 'No years found.', 'certificate-generator' ); ?></em>
							<?php else : ?>
								<select id="cg_filter_year" name="filter_year[]" multiple size="3" style="min-width:120px;">
									<?php foreach ( $years as $y ) : ?>
										<option value="<?php echo esc_attr( $y ); ?>"
											<?php selected( in_array( (int) $y, $active['year'], true ) ); ?>>
											<?php echo esc_html( $y ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Empty = all years.', 'certificate-generator' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px 8px 0;">
							<label for="cg_filter_date_from"><?php esc_html_e( 'Issue Date From', 'certificate-generator' ); ?></label>
						</th>
						<td style="padding:8px 0;">
							<input type="date" id="cg_filter_date_from" name="filter_date_from"
								value="<?php echo esc_attr( $active['date_from'] ); ?>" style="width:150px;">
						</td>
					</tr>
					<tr>
						<th style="padding:8px 10px 8px 0;">
							<label for="cg_filter_date_to"><?php esc_html_e( 'Issue Date To', 'certificate-generator' ); ?></label>
						</th>
						<td style="padding:8px 0;">
							<input type="date" id="cg_filter_date_to" name="filter_date_to"
								value="<?php echo esc_attr( $active['date_to'] ); ?>" style="width:150px;">
						</td>
					</tr>
				</table>

				<p style="margin-bottom:0;">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Preview Students', 'certificate-generator' ); ?>
					</button>
					<?php if ( $previewed ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cg-cert-download' ) ); ?>"
							class="button" style="margin-left:8px;">
							<?php esc_html_e( 'Clear Filters', 'certificate-generator' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>
		</form>

		<?php if ( $previewed ) : ?>

			<div style="max-width:1100px;">
				<h2 style="font-size:15px;margin-bottom:12px;">
					<?php
					printf(
						/* translators: %d: number of students */
						esc_html__( 'Results: %d student(s) found', 'certificate-generator' ),
						$total
					);
					?>
				</h2>

				<?php if ( $plan['num_parts'] > 1 ) : ?>
					<div class="notice notice-info inline" style="margin-bottom:12px;">
						<p>
						<?php
						printf(
							/* translators: %1$d: preview count, %2$d: total, %3$d: num parts */
							esc_html__( 'Showing first %1$d of %2$d total students. Use the %3$d download buttons below to get all certificates.', 'certificate-generator' ),
							count( $rows ),
							$total,
							$plan['num_parts']
						);
						?>
						</p>
					</div>
				<?php endif; ?>

				<?php if ( empty( $rows ) ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'No students match the selected filters.', 'certificate-generator' ); ?></p>
					</div>

				<?php else : ?>

					<?php /* ZIP download form — one form, multiple submit buttons share the same nonce + filters */ ?>
					<form method="post" id="cg-zip-form" action="<?php echo esc_url( admin_url( 'admin.php?page=cg-cert-download' ) ); ?>">
						<?php wp_nonce_field( 'cg_admin_cert_zip', '_wpnonce_cg_zip' ); ?>
						<input type="hidden" name="cg_download_zip" value="1">

						<?php /* Re-pass active filters as hidden inputs */ ?>
						<?php foreach ( $active['schools'] as $s ) : ?>
							<input type="hidden" name="filter_school[]" value="<?php echo esc_attr( $s ); ?>">
						<?php endforeach; ?>
						<?php foreach ( $active['certificate_types'] as $c ) : ?>
							<input type="hidden" name="filter_cert_type[]" value="<?php echo esc_attr( $c ); ?>">
						<?php endforeach; ?>
						<?php foreach ( $active['year'] as $y ) : ?>
							<input type="hidden" name="filter_year[]" value="<?php echo esc_attr( (string) $y ); ?>">
						<?php endforeach; ?>
						<?php if ( $active['date_from'] ) : ?>
							<input type="hidden" name="filter_date_from" value="<?php echo esc_attr( $active['date_from'] ); ?>">
						<?php endif; ?>
						<?php if ( $active['date_to'] ) : ?>
							<input type="hidden" name="filter_date_to" value="<?php echo esc_attr( $active['date_to'] ); ?>">
						<?php endif; ?>
						<?php if ( $active['email_search'] ) : ?>
							<input type="hidden" name="filter_email" value="<?php echo esc_attr( $active['email_search'] ); ?>">
						<?php endif; ?>

						<?php /* ── Download buttons ── */ ?>
						<div style="margin-bottom:14px;">
							<?php if ( $plan['num_parts'] <= 1 ) : ?>
								<button type="submit" name="cg_zip_part" value="0" class="button button-primary cg-zip-btn" style="font-size:14px;height:36px;padding:0 18px;">
									&#x2B07; 
									<?php
									printf(
										/* translators: %d: number of certificates */
										esc_html__( 'Download All %d Certificates as ZIP', 'certificate-generator' ),
										$total
									);
									?>
								</button>
								<span style="color:#888;font-size:12px;margin-left:10px;">
									<?php esc_html_e( 'Includes manifest.csv for reference.', 'certificate-generator' ); ?>
								</span>
							<?php else : ?>
								<p style="margin:0 0 10px;font-weight:600;">
									<?php
									printf(
										/* translators: %1$d: total, %2$d: num parts, %3$d: part size */
										esc_html__( '%1$d certificates split into %2$d downloads (%3$d per part). Each ZIP includes a manifest.csv.', 'certificate-generator' ),
										$total,
										$plan['num_parts'],
										$part_size
									);
									?>
								</p>
								<?php
								for ( $i = 0; $i < $plan['num_parts']; $i++ ) :
									$row_start = ( $i * $part_size ) + 1;
									$row_end   = min( ( $i + 1 ) * $part_size, $total );
									?>
									<button type="submit" name="cg_zip_part" value="<?php echo esc_attr( (string) $i ); ?>"
										class="button button-primary cg-zip-btn" style="font-size:13px;height:34px;padding:0 16px;margin:0 8px 8px 0;">
										&#x2B07; 
										<?php
										printf(
											/* translators: %1$d: part number, %2$d: total parts, %3$d: row start, %4$d: row end */
											esc_html__( 'Part %1$d of %2$d (%3$d–%4$d)', 'certificate-generator' ),
											$i + 1,
											$plan['num_parts'],
											$row_start,
											$row_end
										);
										?>
									</button>
								<?php endfor; ?>
							<?php endif; ?>
						</div>

						<?php /* ── Results table (shows part 1 preview) ── */ ?>
						<table class="wp-list-table widefat fixed striped" style="max-width:1100px;border-radius:4px;">
							<thead>
								<tr>
									<th style="width:200px;"><?php esc_html_e( 'Student Name', 'certificate-generator' ); ?></th>
									<th style="width:220px;"><?php esc_html_e( 'Email', 'certificate-generator' ); ?></th>
									<th style="width:175px;"><?php esc_html_e( 'School', 'certificate-generator' ); ?></th>
									<th style="width:150px;"><?php esc_html_e( 'Certificate Type', 'certificate-generator' ); ?></th>
									<th style="width:105px;"><?php esc_html_e( 'Issue Date', 'certificate-generator' ); ?></th>
									<th style="width:55px;"><?php esc_html_e( 'Year', 'certificate-generator' ); ?></th>
									<th style="width:115px;text-align:center;"><?php esc_html_e( 'Download PDF', 'certificate-generator' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $rows as $row ) :
									$sql_id = (int) ( $row['id'] ?? 0 );
									if ( $sql_id > 0 ) {
										$dl_url = wp_nonce_url(
											add_query_arg(
												array(
													'action' => 'cg_admin_download_cert',
													'sql_id' => $sql_id,
												),
												admin_url( 'admin.php' )
											),
											'cg_admin_dl_cert_' . $sql_id
										);
									} else {
										$dl_url = '';
									}
									?>
									<tr>
										<td><?php echo esc_html( $row['student_name'] ?? '—' ); ?></td>
										<td style="word-break:break-all;"><?php echo esc_html( $row['email'] ?? '—' ); ?></td>
										<td><?php echo esc_html( $row['school_name'] ?? '—' ); ?></td>
										<td><?php echo esc_html( $row['certificate_type'] ?? '—' ); ?></td>
										<td><?php echo esc_html( $row['issue_date'] ?? '—' ); ?></td>
										<td><?php echo esc_html( $row['year'] ?? '—' ); ?></td>
										<td style="text-align:center;">
											<?php if ( $dl_url ) : ?>
												<a href="<?php echo esc_url( $dl_url ); ?>"
													class="button button-small"
													target="_blank"
													title="<?php esc_attr_e( 'Download individual PDF', 'certificate-generator' ); ?>">
													&#x1F4C4; <?php esc_html_e( 'PDF', 'certificate-generator' ); ?>
												</a>
											<?php else : ?>
												<span style="color:#aaa;" title="<?php esc_attr_e( 'No SQL record ID — use ZIP download', 'certificate-generator' ); ?>">—</span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

					</form>

				<?php endif; ?>
			</div>

			<?php /* Duplicate-click protection */ ?>
			<script>
			(function(){
				var form = document.getElementById('cg-zip-form');
				if (!form) return;
				form.addEventListener('submit', function(){
					var btns = form.querySelectorAll('.cg-zip-btn');
					for (var i = 0; i < btns.length; i++) {
						btns[i].disabled = true;
						btns[i].style.opacity = '0.6';
					}
					// Re-enable on back-button (bfcache).
					window.addEventListener('pageshow', function(e){
						if (e.persisted) {
							for (var j = 0; j < btns.length; j++) {
								btns[j].disabled = false;
								btns[j].style.opacity = '1';
							}
						}
					});
				});
			})();
			</script>

		<?php endif; ?>
	</div>
	<?php
}
