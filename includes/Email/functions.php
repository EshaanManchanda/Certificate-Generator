<?php
/**
 * Email Functions for Certificate Generator
 *
 * @package Certificate Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gate-kept debug log — only writes when WP_DEBUG is on.
 */
function cg_email_debug_log( string $msg ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[CG Email] ' . $msg );
	}
}

/**
 * Accept email addresses with local/dev hostnames (e.g. noreply@localhost).
 * is_email() rejects single-label domains, which is correct for production
 * but produces false positives in Local/dev environments.
 */
function cg_is_local_email( string $email ): bool {
	if ( strpos( $email, '@' ) === false ) {
		return false;
	}
	[, $domain] = explode( '@', $email, 2 );
	return strpos( $domain, '.' ) === false; // single-label = local domain
}

/**
 * Return all rows from wp_certificate_generator that share the given email.
 *
 * @return array[]
 */
function cg_get_certs_by_email( string $email ): array {
	static $cache = array();
	if ( isset( $cache[ $email ] ) ) {
		return $cache[ $email ];
	}

	global $wpdb;
	$table           = $wpdb->prefix . 'certificate_generator';
	$cache[ $email ] = $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $table WHERE email = %s ORDER BY id ASC", $email ),
		ARRAY_A
	) ?: array();
	return $cache[ $email ];
}

/**
 * Generate (or retrieve) a PDF for a certificate row.
 *
 * $row must be used AS PASSED — never re-queried by email+certificate_type,
 * which collapses same-email siblings onto one row (the cause of siblings
 * receiving each other's names on their certificates).
 *
 * Returns the filesystem path on success, null on failure.
 */
function cg_generate_pdf_from_row( array $row ): ?string {
	global $wpdb;

	// SQL-first rows (built in certificate_generator_send_email() from wp_cg_*) carry
	// 'entity_type'. Legacy rows (from cg_get_certs_by_email()) predate that linkage —
	// their 'id' maps to wp_certificate_generator, not an entity table.
	$is_legacy_row = ! isset( $row['entity_type'] );

	// Trust a cached path only when it still matches this row's canonical filename —
	// a stale/collided path (e.g. a shared certificate_0.pdf) must be regenerated.
	if ( $is_legacy_row && ! empty( $row['pdf_path'] ) && file_exists( $row['pdf_path'] ) ) {
		$canonical = function_exists( 'cg_canonical_pdf_basename' )
			? cg_canonical_pdf_basename( (int) ( $row['wp_post_id'] ?? 0 ), $row )
			: basename( $row['pdf_path'] );
		if ( basename( $row['pdf_path'] ) === $canonical ) {
			return $row['pdf_path'];
		}
	}

	// Bulk SQL rows MUST carry their own id — without it we cannot key a unique
	// filename and would risk overwriting a sibling's PDF. Fail loudly instead.
	if ( ! $is_legacy_row && empty( $row['id'] ) ) {
		error_log( '[CG Email] cg_generate_pdf_from_row: SQL row missing id (email=' . ( $row['email'] ?? '?' ) . ') — refusing to generate to avoid sibling collision' );
		return null;
	}

	if ( function_exists( 'generate_certificate_pdf' ) && ! empty( $row['certificate_type'] ) ) {
		$post_id   = (int) ( $row['wp_post_id'] ?? 0 );
		$cert_type = $row['certificate_type'];
		$fields    = class_exists( 'CG_Field_Schema' )
			? CG_Field_Schema::get_all_renderable_fields( $cert_type )
			: array( 'student_name', 'school_name', 'issue_date' );
		// Pass $row directly — it already IS this record's own entity data.
		$file_url = generate_certificate_pdf( $post_id, $fields, $row );

		if ( $file_url ) {
			// Derive filesystem path from URL — don't rely on postmeta which
			// may not be saved when wp_post_id = 0.
			$path = wp_normalize_path(
				str_replace(
					cg_certificates_url(),
					cg_certificates_dir(),
					$file_url
				)
			);
			if ( ! file_exists( $path ) && $post_id > 0 ) {
				// Fallback: try postmeta in case file landed elsewhere.
				$path = wp_normalize_path( (string) get_post_meta( $post_id, 'certificate_file_path', true ) );
			}
			if ( $path && file_exists( $path ) ) {
				// wp_certificate_generator only has a matching PK for legacy rows —
				// an SQL-first row's 'id' belongs to an entity table, not this one.
				if ( $is_legacy_row && ! empty( $row['id'] ) ) {
					$wpdb->update(
						$wpdb->prefix . 'certificate_generator',
						array( 'pdf_path' => $path ),
						array( 'id' => $row['id'] ),
						array( '%s' ),
						array( '%d' )
					);
				}
				return $path;
			}
		}
	}

	// ── Fallback: generate from stored JSON blob (legacy rows only) ──────────
	if ( ! $is_legacy_row || ! function_exists( 'generate_certificate_pdf_with_data' ) ) {
		return null;
	}

	$data = ! empty( $row['certificate_data'] ) ? json_decode( $row['certificate_data'], true ) : null;
	if ( ! is_array( $data ) || empty( $row['certificate_type'] ) ) {
		return null;
	}

	$post_data = array_merge( $data, array( 'certificate_type' => $row['certificate_type'] ) );
	$result    = generate_certificate_pdf_with_data( $post_data );

	$path = null;
	if ( is_array( $result ) && ! empty( $result['path'] ) ) {
		$path = $result['path'];
	} elseif ( is_string( $result ) && $result !== '' ) {
		if ( strpos( $result, 'http' ) === 0 ) {
			$upload_dir = wp_upload_dir();
			$result     = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $result );
		}
		if ( file_exists( $result ) ) {
			$path = $result;
		}
	}

	if ( $path && ! empty( $row['id'] ) ) {
		$wpdb->update(
			$wpdb->prefix . 'certificate_generator',
			array( 'pdf_path' => $path ),
			array( 'id' => $row['id'] ),
			array( '%s' ),
			array( '%d' )
		);
	}

	return $path ?: null;
}

/**
 * Fetch a flattened entity row from a SQL table by wp_post_id.
 * Different name from cg_get_sql_row_for_post() in columns.php to avoid
 * fatal on duplicate function declaration (both files load on every admin request).
 * Returns null if CustomTables unavailable, table missing, or no row found.
 */
function cg_email_get_sql_row( int $post_id, string $post_type ): ?array {
	if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		return null;
	}
	global $wpdb;
	$tables = \CertificateGenerator\Database\CustomTables::instance();
	$table  = $tables->get_table( $post_type );
	if ( empty( $table ) || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE wp_post_id = %d LIMIT 1", $post_id ), ARRAY_A );
	if ( empty( $row ) ) {
		return null;
	}
	$extra = ! empty( $row['extra_fields'] ) ? ( json_decode( $row['extra_fields'], true ) ?: array() ) : array();
	unset( $row['extra_fields'] );
	return array_merge( $row, $extra );
}

/**
 * Create ZIP file containing multiple certificates for an email address.
 *
 * @param array  $certificates_data Array of ['path', 'filename'] descriptors.
 * @param string $recipient_email   Recipient email address for ZIP filename.
 * @return array|false Array with 'zip_path', 'zip_url', 'certificate_count' on success, false on failure.
 *
 * @internal Renamed from certificate_generator_create_zip_for_email in v8 Phase 2.
 *           Call certificate_generator_create_zip_for_email() or ZipService::make() instead.
 */
function _cg_create_zip_impl( $certificates_data, $recipient_email ) {
	// Validate inputs
	if ( empty( $certificates_data ) || ! is_array( $certificates_data ) ) {
		error_log( 'Certificate Generator: Cannot create ZIP - no certificate data provided' );
		return false;
	}

	if ( empty( $recipient_email ) ) {
		error_log( 'Certificate Generator: Cannot create ZIP - no recipient email provided' );
		return false;
	}

	// Check if ZipArchive class is available
	if ( ! class_exists( 'ZipArchive' ) ) {
		error_log( 'Certificate Generator: ZipArchive class not available on this server' );
		return false;
	}

	// Get upload directory
	$upload_dir = wp_upload_dir();
	if ( ! $upload_dir || ! isset( $upload_dir['basedir'] ) || ! isset( $upload_dir['baseurl'] ) ) {
		error_log( 'Certificate Generator: Could not get WordPress upload directory' );
		return false;
	}

	// Build ZIP file path + URL.
	$zip_name = function_exists( 'cg_certificate_zip_filename' ) ? cg_certificate_zip_filename( $recipient_email ) : 'certificates_' . preg_replace( '/[^a-z0-9]/', '_', strtolower( $recipient_email ) ) . '_' . time() . '.zip';
	$zip_path = cg_certificates_dir() . '/' . $zip_name;
	$zip_url  = cg_certificates_url() . '/' . $zip_name;

	$zip = new ZipArchive();
	if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
		error_log( "Certificate Generator: Could not create ZIP file at {$zip_path}" );
		return false;
	}

	$added_count  = 0;
	$failed_files = array();

	foreach ( $certificates_data as $cert ) {
		$file_path = $cert['path'] ?? '';
		$file_name = $cert['filename'] ?? basename( $file_path );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$failed_files[] = $file_name ?: $file_path;
			continue;
		}

		if ( $zip->addFile( $file_path, $file_name ) ) {
			++$added_count;
		} else {
			$failed_files[] = $file_name;
		}
	}

	// Close ZIP archive.
	$zip->close();

	// Verify ZIP was created successfully
	if ( $added_count === 0 ) {
		error_log( 'Certificate Generator: No files were added to ZIP' );
		@unlink( $zip_path ); // Clean up empty ZIP
		return false;
	}

	if ( ! file_exists( $zip_path ) ) {
		error_log( "Certificate Generator: ZIP file was not created: {$zip_path}" );
		return false;
	}

	$zip_size = filesize( $zip_path );
	if ( $zip_size === false || $zip_size < 100 ) {
		error_log( "Certificate Generator: ZIP file appears to be corrupted or empty (size: {$zip_size})" );
		@unlink( $zip_path );
		return false;
	}

	if ( ! empty( $failed_files ) ) {
		error_log( '[CG ZIP] ' . count( $failed_files ) . ' file(s) could not be added: ' . implode( ', ', $failed_files ) );
	}

	return array(
		'zip_path'          => $zip_path,
		'zip_url'           => $zip_url,
		'certificate_count' => $added_count,
		'failed_count'      => count( $failed_files ),
		'failed_files'      => $failed_files,
	);
}

// ── Public shim for certificate_generator_create_zip_for_email ───────────────
// When CG_USE_NEW_ZIP=true, legacy-shims.php provides this function and routes
// it through ZipService::make(). When the flag is off (default), this wrapper
// keeps all callers working unchanged.
if ( ! class_exists( '\CertificateGenerator\Core\Config' )
	|| ! \CertificateGenerator\Core\Config::flag( 'CG_USE_NEW_ZIP' ) ) {
	/**
	 * @deprecated v8 Use \CertificateGenerator\Services\ZipService::make() instead.
	 */
	function certificate_generator_create_zip_for_email( $certificates_data, $recipient_email ) {
		return _cg_create_zip_impl( $certificates_data, $recipient_email );
	}
}

/**
 * Send certificate email for a wp_certificate_generator row.
 *
 * @param int  $cg_id     Row ID in wp_certificate_generator.
 * @param bool $log_email Whether to log the attempt.
 * @return bool
 */
function certificate_generator_send_email( $cg_id, $log_email = true ) {
	global $wpdb;

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		@set_time_limit( 0 );
		$needed  = wp_convert_hr_to_bytes( '512M' );
		$current = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $current > 0 && $current < $needed ) {
			ini_set( 'memory_limit', '512M' );
		}
		@ignore_user_abort( true );
	}

	$cg_table = $wpdb->prefix . 'certificate_generator';
	$anchor   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $cg_table WHERE id = %d LIMIT 1", $cg_id ), ARRAY_A );

	if ( ! $anchor || empty( $anchor['email'] ) ) {
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, '', '', '', '', false, 'No certificate record or email found' );
		}
		return false;
	}

	$recipient_email  = $anchor['email'];
	$recipient_name   = ''; // resolved from SQL table below; legacy anchor is last resort
	$certificate_type = $anchor['certificate_type'] ?? '';

	$options = get_option( 'certificate_generator_settings_email' );

	// SQL-first: collect certs from wp_cg_students/teachers/schools — the same source
	// the shortcode uses. wp_certificate_generator can have duplicates; wp_cg_* cannot.
	$all_rows    = array();
	$entity_type = 'students'; // default; overridden below when we find the table
	if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		$tables = \CertificateGenerator\Database\CustomTables::instance();
		foreach ( array( 'students', 'teachers', 'schools' ) as $_ent ) {
			$_tbl = $tables->get_table( $_ent );
			if ( empty( $_tbl ) || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $_tbl ) ) !== $_tbl ) {
				continue;
			}
			$_rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $_tbl WHERE email = %s ORDER BY id ASC", $recipient_email ),
				ARRAY_A
			);
			if ( ! empty( $_rows ) ) {
				// Normalize field names cg_generate_pdf_from_row / cert building expect.
				foreach ( $_rows as &$_r ) {
					if ( ! isset( $_r['student_name'] ) ) {
						$_r['student_name'] = $_r['teacher_name'] ?? $_r['school_name'] ?? '';
					}
					$_r['entity_type'] = $_ent;
				}
				unset( $_r );
				$all_rows    = $_rows;
				$entity_type = $_ent;
				break; // one entity type per email address
			}
		}
	}
	// Fallback to legacy table only when SQL tables are missing/empty.
	if ( empty( $all_rows ) ) {
		$all_rows = cg_get_certs_by_email( $recipient_email );
	}
	cg_email_debug_log( 'Found ' . count( $all_rows ) . " certs for {$recipient_email} (entity: {$entity_type})" );

	// SQL table is authoritative; legacy anchor is last resort.
	$recipient_name = ! empty( $all_rows[0]['student_name'] )
		? $all_rows[0]['student_name']
		: ( $anchor['student_name'] ?? '' );

	$prefix  = $entity_type . '_email_';
	$use_zip = count( $all_rows ) > 1;

	// Priority: per-entity-type template → global cg_email_* (via SettingsService for defaults).
	$_ss     = class_exists( '\CertificateGenerator\Services\SettingsService' );
	$subject = ! empty( $options[ $prefix . 'subject' ] )
		? $options[ $prefix . 'subject' ]
		: ( $_ss ? \CertificateGenerator\Services\SettingsService::get( 'cg_email_subject' ) : get_option( 'cg_email_subject', '' ) );
	$title   = ! empty( $options[ $prefix . 'title' ] )
		? $options[ $prefix . 'title' ]
		: get_option( 'cg_email_title', '' );
	$message = ! empty( $options[ $prefix . 'message' ] )
		? $options[ $prefix . 'message' ]
		: ( $_ss ? \CertificateGenerator\Services\SettingsService::get( 'cg_email_body' ) : get_option( 'cg_email_body', '' ) );

	// Generate result page URL
	$result_page_url = home_url( '/result/?student_email=' . urlencode( $recipient_email ) );
	$verify_page_url = home_url( '/verify-certificate/' );

	// Initialize ZIP link (will be populated later if ZIP is created)
	$zip_download_url = '';

	// Count certificates for this email
	$certificate_count = count( $all_rows );

	// Replace placeholders (initial replacement - {zip_link} will be replaced again later if ZIP is created)
	$serial_number = $anchor['serial_number'] ?? '';
	$expires_at    = $anchor['expires_at'] ?? '';

	$placeholders = array(
		'{name}'              => $recipient_name,
		'{certificate_title}' => $certificate_type,
		'{result_link}'       => $result_page_url,
		'{zip_link}'          => '', // Empty for now, populated later if ZIP exists
		'{certificate_count}' => $certificate_count,
		'{email}'             => $recipient_email,
		'{serial_number}'     => $serial_number ?: 'N/A',
		'{expires_at}'        => $expires_at ?: 'Never',
		'{verify_link}'       => $verify_page_url,
	);

	$subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $subject );
	$title   = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $title );
	$message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $message );

	// Get additional email settings
	$reply_to = isset( $options[ $prefix . 'reply_to' ] ) ? $options[ $prefix . 'reply_to' ] : '';
	$cc       = isset( $options[ $prefix . 'cc' ] ) ? array_map( 'trim', explode( ',', $options[ $prefix . 'cc' ] ) ) : array();
	$bcc      = isset( $options[ $prefix . 'bcc' ] ) ? array_map( 'trim', explode( ',', $options[ $prefix . 'bcc' ] ) ) : array();

	// Collect PDF paths for all certs belonging to this email.
	$certificates_data         = array();
	$certificate_path          = null;
	$generated_certificate_ids = array();
	$failed_certificate_ids    = array();

	$total_certs = count( $all_rows );
	cg_email_debug_log( "Generating {$total_certs} PDFs for {$recipient_email}" );

	try {
		foreach ( $all_rows as $index => $cert_row ) {
			if ( $index % 10 === 0 && $index > 0 ) {
				cg_email_debug_log( "Progress — {$index}/{$total_certs} PDFs" );
			}

			$cert_path = cg_generate_pdf_from_row( $cert_row );

			if ( empty( $cert_path ) || ! file_exists( $cert_path ) ) {
				$failed_certificate_ids[] = (int) ( $cert_row['id'] ?? 0 );
				// Genuine failure — a sibling's certificate silently dropping is the exact
				// bug this fixes, so this must not be gated behind WP_DEBUG.
				error_log(
					sprintf(
						'[CG Email] PDF generation FAILED — cg_id=%d name=%s email=%s type=%s (sibling will NOT be delivered)',
						(int) ( $cert_row['id'] ?? 0 ),
						$cert_row['student_name'] ?? '',
						$recipient_email,
						$cert_row['certificate_type'] ?? ''
					)
				);
				continue;
			}

			$cert_path = wp_normalize_path( $cert_path );

			$_pdf_name                   = function_exists( 'cg_certificate_pdf_filename' )
				? cg_certificate_pdf_filename( $cert_row['student_name'], $cert_row['certificate_type'] ?? '', (string) $cert_row['id'] )
				: basename( $cert_path );
			$certificates_data[]         = array(
				'path'     => $cert_path,
				'filename' => $_pdf_name,
				'cg_id'    => (int) $cert_row['id'],
				'name'     => $cert_row['student_name'],
				'type'     => $cert_row['certificate_type'] ?? '',
			);
			$generated_certificate_ids[] = (int) $cert_row['id'];
		}
	} catch ( Exception $e ) {
		$error_msg = 'Certificate generation exception: ' . $e->getMessage();
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
		}
		return false;
	} catch ( Error $e ) {
		$error_msg = 'Certificate generation fatal error: ' . $e->getMessage();
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
		}
		return false;
	}

	if ( empty( $certificates_data ) ) {
		$error_msg = 'No certificates could be generated';
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
		}
		return false;
	}

	cg_email_debug_log( 'Generated ' . count( $certificates_data ) . " certs for {$recipient_email}" );

	if ( ! empty( $failed_certificate_ids ) ) {
		error_log(
			sprintf(
				'[CG Email] %d of %d certificates FAILED to generate for %s (cg_ids: %s) — partial delivery',
				count( $failed_certificate_ids ),
				$total_certs,
				$recipient_email,
				implode( ',', $failed_certificate_ids )
			)
		);
	}

	if ( $use_zip ) {
		cg_email_debug_log( 'Creating ZIP for ' . count( $certificates_data ) . " certs → {$recipient_email}" );

		$zip_result = certificate_generator_create_zip_for_email( $certificates_data, $recipient_email );

		if ( $zip_result === false ) {
			$error_msg = 'Failed to create ZIP file for multiple certificates';
			if ( $log_email ) {
				certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
			}
			return false;
		}

		// Use ZIP file as attachment
		$certificate_path = $zip_result['zip_path'];

		// Store ZIP URL for {zip_link} placeholder
		$zip_download_url = isset( $zip_result['zip_url'] ) ? $zip_result['zip_url'] : '';

		// Update subject and message to indicate multiple certificates
		$cert_count = count( $certificates_data );
		$subject    = str_replace(
			array( '{certificate_count}', 'Certificate', 'certificate' ),
			array( $cert_count, $cert_count . ' Certificates', $cert_count . ' certificates' ),
			$subject
		);

		// Add count to title and message if not already present
		if ( strpos( $title, $cert_count ) === false ) {
			$title = str_replace( 'Certificate', $cert_count . ' Certificates', $title );
		}
		if ( strpos( $message, $cert_count ) === false ) {
			$message = str_replace(
				array( 'attached your certificate', 'find attached your' ),
				array( 'attached your ' . $cert_count . ' certificates', 'find attached your ' . $cert_count ),
				$message
			);
		}

		cg_email_debug_log( 'ZIP created: ' . $zip_result['zip_path'] );

	} else {
		$certificate_path = $certificates_data[0]['path'];
		cg_email_debug_log( "Single PDF for {$recipient_email}: {$certificate_path}" );
	}

	if ( empty( $certificate_path ) || ! file_exists( $certificate_path ) ) {
		$error_msg = "Final attachment file not found: {$certificate_path}";
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
		}
		return false;
	}

	// Replace {zip_link} placeholder now that we know if ZIP was created
	$message = str_replace( '{zip_link}', $zip_download_url, $message );
	$subject = str_replace( '{zip_link}', $zip_download_url, $subject );
	$title   = str_replace( '{zip_link}', $zip_download_url, $title );

	// Process WordPress shortcodes in email templates (e.g. [site_name], [event_fees] from wp-dynamic-tags)
	$subject = do_shortcode( $subject );
	$message = do_shortcode( $message );
	$title   = do_shortcode( $title );

	// Check final $message (covers per-type + global template) for result_link usage.
	$result_link_used = strpos( $message, $result_page_url ) !== false
		|| strpos( $message, '{result_link}' ) !== false;

	// Title falls back to certificate type when no explicit email title is set.
	$display_title = $title ?: $certificate_type;

	// Format HTML email
	$html_message  = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
	$html_message .= '<h1 style="color: #2c3e50; margin-bottom: 20px;">' . esc_html( $display_title ) . '</h1>';
	$html_message .= '<div style="line-height: 1.6; color: #333;">' . wpautop( $message ) . '</div>';

	// Auto-add result page button if {result_link} placeholder was not used
	if ( ! $result_link_used && ! empty( $result_page_url ) ) {
		$html_message .= '<div style="text-align: center; margin: 30px 0 20px 0;">';
		$html_message .= '<a href="' . esc_url( $result_page_url ) . '" style="display: inline-block; padding: 12px 28px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">View Your Results Online</a>';
		$html_message .= '</div>';
	}

	$html_message .= '</div>';

	// Set up email headers with fallback system
	$headers   = array();
	$headers[] = 'Content-Type: text/html; charset=UTF-8';

	// Use enhanced email system with better SMTP detection
	$from_name = get_bloginfo( 'name' );

	// Check if WP Mail SMTP is properly configured
	if ( certificate_generator_is_wp_mail_smtp_active() ) {
		// Use admin email when SMTP is configured
		$from_email = get_option( 'admin_email' );

	} else {
		// Use noreply@domain.com as fallback (like Forminator)
		$server_name = parse_url( home_url(), PHP_URL_HOST );

		// Clean server name and validate
		if ( empty( $server_name ) || $server_name === 'localhost' || filter_var( $server_name, FILTER_VALIDATE_IP ) ) {
			// If server name is localhost or an IP, use admin email
			$from_email = get_option( 'admin_email' );

		} else {
			$from_email = 'noreply@' . $server_name;

		}
	}

	// Validate from email address
	if ( ! is_email( $from_email ) ) {

		$from_email = get_option( 'admin_email' );
	}

	$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

	if ( ! empty( $reply_to ) ) {
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		} else {

		}
	}

	// Add CC recipients with validation
	foreach ( $cc as $cc_email ) {
		$cc_email = trim( $cc_email );
		if ( ! empty( $cc_email ) ) {
			if ( is_email( $cc_email ) ) {
				$headers[] = 'Cc: ' . $cc_email;
			} else {

			}
		}
	}

	// Add BCC recipients with validation
	foreach ( $bcc as $bcc_email ) {
		$bcc_email = trim( $bcc_email );
		if ( ! empty( $bcc_email ) ) {
			if ( is_email( $bcc_email ) ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			} else {

			}
		}
	}

	// Set up attachments based on settings
	$attachments = array();

	// For ZIP files (multiple certificates), ALWAYS attach or provide download link
	if ( $use_zip && ! empty( $certificate_path ) && file_exists( $certificate_path ) ) {
		// Check ZIP file size to determine if we should attach or provide download link
		$zip_size            = filesize( $certificate_path );
		$max_attachment_size = 25 * 1024 * 1024; // 25MB limit for email attachments

		if ( $zip_size <= $max_attachment_size ) {
			$attachments[] = $certificate_path;
			cg_email_debug_log( sprintf( 'Attaching ZIP %.2f MB to %s', $zip_size / 1024 / 1024, $recipient_email ) );
		} else {
			$download_url = $zip_result['zip_url'] ?? '';
			if ( ! empty( $download_url ) ) {
				if ( empty( $zip_download_url ) ) {
					$zip_download_url = $download_url;
				}
				$size_mb       = round( $zip_size / 1024 / 1024, 1 );
				$html_message .= "\n\n<div style='margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-left: 4px solid #0073aa;'>";
				$html_message .= "<p style='margin: 0; font-weight: bold;'>Your certificates are ready for download:</p>";
				$html_message .= "<p style='margin: 10px 0 0 0;'><a href='" . esc_url( $download_url ) . "' style='color: #0073aa; text-decoration: none; font-weight: bold;'>Download Certificates ZIP (" . esc_html( $size_mb ) . ' MB)</a></p>';
				$html_message .= '</div>';
			}
		}
	} else {
		// Single PDF - respect the attach_certificate setting
		$attach_certificate = isset( $options[ $prefix . 'attach_certificate' ] ) ? $options[ $prefix . 'attach_certificate' ] : '1';
		if ( $attach_certificate === '1' && ! empty( $certificate_path ) && file_exists( $certificate_path ) ) {
			$attachments[] = $certificate_path;
		}
	}

	// Pre-send validation
	if ( ! is_email( $recipient_email ) ) {
		$error_msg = "Invalid recipient email address: {$recipient_email}";
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
		}
		return false;
	}

	if ( empty( $subject ) ) {
		$error_msg = 'Empty email subject';
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
		}
		return false;
	}

	if ( empty( $html_message ) ) {
		$error_msg = 'Empty email message';
		if ( $log_email ) {
			certificate_generator_log_email( $cg_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg );
		}
		return false;
	}

	// Send email using WordPress mail function with retry mechanism
	// WP Mail SMTP will automatically handle the SMTP configuration if it's installed
	$email_sent  = false;
	$max_retries = 3;
	$retry_delay = 1; // seconds
	$last_error  = '';
	$all_errors  = array(); // Track all errors from all attempts

	// Log email configuration for debugging
	$smtp_configured = certificate_generator_is_wp_mail_smtp_active();

	// Get WP Mail SMTP configuration if available
	$smtp_settings = array();
	if ( $smtp_configured ) {
		$smtp_options = get_option( 'wp_mail_smtp', array() );
		if ( ! empty( $smtp_options['mail'] ) ) {
			$smtp_settings = array(
				'mailer'           => isset( $smtp_options['mail']['mailer'] ) ? $smtp_options['mail']['mailer'] : 'unknown',
				'from_email'       => isset( $smtp_options['mail']['from_email'] ) ? $smtp_options['mail']['from_email'] : 'not set',
				'from_email_force' => isset( $smtp_options['mail']['from_email_force'] ) ? $smtp_options['mail']['from_email_force'] : false,
				'from_name_force'  => isset( $smtp_options['mail']['from_name_force'] ) ? $smtp_options['mail']['from_name_force'] : false,
			);

			// Add SMTP-specific settings if using SMTP/Other SMTP
			if ( isset( $smtp_options['smtp'] ) ) {
				$smtp_settings['smtp_host']       = isset( $smtp_options['smtp']['host'] ) ? $smtp_options['smtp']['host'] : 'not set';
				$smtp_settings['smtp_port']       = isset( $smtp_options['smtp']['port'] ) ? $smtp_options['smtp']['port'] : 'not set';
				$smtp_settings['smtp_encryption'] = isset( $smtp_options['smtp']['encryption'] ) ? $smtp_options['smtp']['encryption'] : 'none';
			}
		}
	}

	$email_config = array(
		'to'                 => $recipient_email,
		'from'               => $from_email,
		'smtp_active'        => $smtp_configured,
		'smtp_settings'      => $smtp_settings,
		'php_mail_available' => function_exists( 'mail' ),
		'attachment_count'   => count( $attachments ),
		'attachment_paths'   => $attachments,
		'server'             => parse_url( home_url(), PHP_URL_HOST ),
	);
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		cg_debug_log( 'Email configuration: ' . print_r( $email_config, true ) );
	}

	for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
		cg_email_debug_log( "Attempt {$attempt}/{$max_retries} → {$recipient_email}" );
		$last_php_error = error_get_last();

		// Attempt to send email
		$email_sent = wp_mail( $recipient_email, $subject, $html_message, $headers, $attachments );

		if ( $email_sent ) {
			cg_email_debug_log( "Sent on attempt {$attempt} → {$recipient_email}" );
			break;
		} else {
			// Capture error details
			global $phpmailer;
			$current_error = '';
			$error_details = array();

			if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
				$current_error                    = 'PHPMailer: ' . $phpmailer->ErrorInfo;
				$error_details['phpmailer_error'] = $phpmailer->ErrorInfo;

				// Get more PHPMailer details if available
				if ( isset( $phpmailer->Mailer ) ) {
					$error_details['mailer_type'] = $phpmailer->Mailer; // smtp, mail, sendmail
				}
				if ( isset( $phpmailer->Host ) ) {
					$error_details['smtp_host'] = $phpmailer->Host;
				}
			}

			// Check for new PHP errors
			$new_php_error = error_get_last();
			if ( $new_php_error && $new_php_error !== $last_php_error ) {
				$php_error_msg                   = $new_php_error['message'];
				$current_error                  .= ( $current_error ? ' | ' : '' ) . 'PHP: ' . $php_error_msg;
				$error_details['php_error']      = $php_error_msg;
				$error_details['php_error_file'] = $new_php_error['file'];
				$error_details['php_error_line'] = $new_php_error['line'];
			}

			if ( empty( $current_error ) ) {
				$current_error            = 'wp_mail() returned false with no specific error';
				$error_details['general'] = 'No specific error returned';
			}

			$last_error   = $current_error;
			$all_errors[] = "Attempt {$attempt}: {$current_error}";

			// Log detailed error for this attempt
			error_log( "Certificate Generator: Email attempt {$attempt} failed: {$current_error}" );
			error_log( 'Certificate Generator: Error details: ' . print_r( $error_details, true ) );

			// Don't retry on certain permanent failures
			$permanent_failures = array(
				'Invalid address',
				'Recipient address rejected',
				'Domain not found',
				'Authentication failed',
				'Invalid credentials',
				'Could not authenticate',
				'SMTP connect() failed',
				'Could not instantiate mail function', // PHP mail() is disabled on this server
			);

			$is_permanent_failure = false;
			foreach ( $permanent_failures as $failure_type ) {
				if ( stripos( $current_error, $failure_type ) !== false ) {
					$is_permanent_failure = true;
					error_log( "Certificate Generator: Permanent failure detected: {$failure_type}. Stopping retries." );
					break;
				}
			}

			if ( $is_permanent_failure || $attempt >= $max_retries ) {
				break;
			}

			cg_email_debug_log( "Waiting {$retry_delay}s before retry" );
			sleep( $retry_delay );
			$retry_delay *= 2; // Exponential backoff
		}
	}

	// Log final result with all errors
	if ( ! $email_sent && $log_email ) {
		error_log( 'Certificate Generator: All email attempts failed. Errors: ' . implode( ' | ', $all_errors ) );
	}

	// Log each certificate individually for audit trail.
	if ( $log_email ) {
		$cert_count = count( $generated_certificate_ids );
		foreach ( $generated_certificate_ids as $idx => $logged_cg_id ) {
			$cert_row_ref = null;
			foreach ( $all_rows as $r ) {
				if ( (int) $r['id'] === $logged_cg_id ) {
					$cert_row_ref = $r;
					break; }
			}
			$cert_name   = $cert_row_ref['student_name'] ?? '';
			$cert_type   = $cert_row_ref['certificate_type'] ?? '';
			$log_subject = $subject . ( $cert_count > 1 ? sprintf( ' [%d of %d]', $idx + 1, $cert_count ) : '' );
			if ( $email_sent ) {
				certificate_generator_log_email( $logged_cg_id, $recipient_email, $cert_name, $cert_type, $log_subject, true );
			} else {
				$detailed_error = 'wp_mail() failed after ' . min( $attempt - 1, $max_retries ) . ' attempts' . ( $last_error ? ": {$last_error}" : '' );
				certificate_generator_log_email( $logged_cg_id, $recipient_email, $cert_name, $cert_type, $log_subject, false, $detailed_error );
			}
		}
		if ( $email_sent ) {
			cg_email_debug_log( "Logged {$cert_count} cert(s) as sent → {$recipient_email}" );
		}
	}

	return $email_sent;
}

/**
 * Send certificate emails in bulk to all entries of a specific post type
 * NOW WITH EMAIL GROUPING: Groups certificates by email address
 *
 * @param string $post_type The post type (students, teachers, or schools)
 * @param bool   $skip_already_sent Whether to skip certificates that were already emailed
 * @param array  $specific_post_ids Optional array of specific post IDs to process
 * @return array Array with counts of success and failure
 */
function certificate_generator_send_bulk_emails( $post_type, $skip_already_sent = true, $specific_post_ids = array() ) {
	global $wpdb;
	$cg_table = $wpdb->prefix . 'certificate_generator';

	$results = array(
		'success'       => 0,
		'failure'       => 0,
		'skipped'       => 0,
		'errors'        => array(),
		'total'         => 0,
		'emails_sent'   => 0,
		'grouped_sends' => 0,
	);

	if ( ! empty( $specific_post_ids ) ) {
		// Bridge: caller supplied CPT post IDs — resolve emails via post_meta, then find cg rows.
		$by_email = array();
		foreach ( $specific_post_ids as $post_id ) {
			$email = get_post_meta( (int) $post_id, 'email', true );
			if ( is_email( $email ) ) {
				$by_email[ $email ] = array();
			}
		}
		if ( empty( $by_email ) ) {
			$results['errors'][] = 'No valid email addresses in provided post IDs';
			return $results;
		}
		foreach ( array_keys( $by_email ) as $email ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM $cg_table WHERE email = %s ORDER BY id ASC",
					$email
				)
			);
			if ( $ids ) {
				$by_email[ $email ] = array_map( 'intval', $ids );
			}
		}
		$by_email = array_filter( $by_email );
	} else {
		// Full table scan grouped by email.
		$rows     = $wpdb->get_results(
			"SELECT id, email FROM $cg_table WHERE email != '' ORDER BY email ASC, id ASC",
			ARRAY_A
		);
		$by_email = array();
		foreach ( $rows as $row ) {
			if ( ! is_email( $row['email'] ) ) {
				continue;
			}
			$by_email[ $row['email'] ][] = (int) $row['id'];
		}
	}

	$results['total'] = array_sum( array_map( 'count', $by_email ) );
	cg_email_debug_log( 'Bulk: ' . count( $by_email ) . ' unique email addresses to process' );

	foreach ( $by_email as $email => $cg_ids ) {
		$cert_count = count( $cg_ids );

		if ( $skip_already_sent && certificate_generator_email_already_sent( (int) $cg_ids[0], $email ) ) {
			$results['skipped'] += $cert_count;
			$results['errors'][] = "Skipped {$email}: already sent";
			continue;
		}

		cg_email_debug_log( "Bulk: processing {$cert_count} cert(s) for {$email}" );

		$success = certificate_generator_send_email( (int) $cg_ids[0] );

		if ( $success ) {
			$results['success'] += $cert_count;
			++$results['emails_sent'];
			if ( $cert_count > 1 ) {
				++$results['grouped_sends'];
			}
		} else {
			$results['failure'] += $cert_count;
			$results['errors'][] = "Failed {$cert_count} cert(s) → {$email}";
		}
	}

	cg_email_debug_log( "Bulk complete. Sent {$results['success']} certs via {$results['emails_sent']} emails ({$results['grouped_sends']} grouped)" );

	return $results;
}

/**
 * Check if email was already sent for a specific post
 *
 * @param int    $post_id The post ID
 * @param string $email The email address
 * @return bool Whether email was already sent
 */
function certificate_generator_email_already_sent( $cg_id, $email ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'cert_email_logs';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
		return false;
	}
	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $table_name WHERE certificate_id = %d AND recipient_email = %s AND status = 'sent'",
			$cg_id,
			$email
		)
	);
}

/**
 * Auto-send certificate email when certificate is generated or found
 *
 * @param int  $post_id The post ID
 * @param bool $force_send Whether to send even if auto-send is disabled
 * @return bool Whether email was sent
 */
function certificate_generator_auto_send_email( $post_id, $force_send = false ) {
	// Check if auto-send is enabled (you can add this setting to admin)
	$auto_send_enabled = get_option( 'certificate_generator_auto_send_enabled', false );

	if ( ! $auto_send_enabled && ! $force_send ) {
		return false;
	}

	// Check if email was already sent to avoid duplicates.
	global $wpdb;
	$cg_table = $wpdb->prefix . 'certificate_generator';
	$cg_row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, email FROM $cg_table WHERE id = %d LIMIT 1", $post_id ), ARRAY_A );
	$email    = $cg_row['email'] ?? '';
	if ( ! empty( $email ) && certificate_generator_email_already_sent( (int) $post_id, $email ) ) {
		return false;
	}

	// Send the email
	return certificate_generator_send_email( $post_id );
}

/**
 * Get the appropriate from email address based on SMTP configuration
 *
 * @return string From email address
 */
function certificate_generator_get_from_email() {
	// Check if WP Mail SMTP is active
	if ( certificate_generator_is_wp_mail_smtp_active() ) {
		// Use admin email when SMTP is configured
		return get_option( 'admin_email' );
	} else {
		// Use noreply@domain.com as fallback (like Forminator)
		$server_name = parse_url( home_url(), PHP_URL_HOST );
		return 'noreply@' . $server_name;
	}
}

/**
 * Get email delivery method status
 *
 * @return array Status information about email delivery method
 */
function certificate_generator_get_email_status() {
	$wp_mail_smtp_active = certificate_generator_is_wp_mail_smtp_active();

	return array(
		'method'          => $wp_mail_smtp_active ? 'WP Mail SMTP' : 'WordPress Default (PHP mail)',
		'from_email'      => certificate_generator_get_from_email(),
		'from_name'       => get_bloginfo( 'name' ),
		'smtp_configured' => $wp_mail_smtp_active,
		'fallback_active' => ! $wp_mail_smtp_active,
	);
}

/**
 * Comprehensive email system health check
 *
 * @return array Detailed health check results
 */
function certificate_generator_email_health_check() {
	$results = array(
		'overall_status'  => 'healthy',
		'issues'          => array(),
		'recommendations' => array(),
		'details'         => array(),
	);

	// Check basic WordPress mail functionality
	$results['details']['wp_mail_available'] = function_exists( 'wp_mail' );
	if ( ! $results['details']['wp_mail_available'] ) {
		$results['issues'][]       = 'wp_mail() function is not available';
		$results['overall_status'] = 'critical';
	}

	// Check SMTP configuration
	$wp_mail_smtp_active                      = certificate_generator_is_wp_mail_smtp_active();
	$results['details']['smtp_plugin_active'] = $wp_mail_smtp_active;

	if ( ! $wp_mail_smtp_active ) {
		$results['recommendations'][] = 'Consider installing WP Mail SMTP plugin for better email reliability';
	}

	// Check from email configuration
	$from_email                             = certificate_generator_get_from_email();
	$email_valid                            = is_email( $from_email ) || cg_is_local_email( $from_email );
	$results['details']['from_email']       = $from_email;
	$results['details']['from_email_valid'] = $email_valid;

	if ( ! $email_valid ) {
		$results['issues'][]       = "Invalid from email address: {$from_email}";
		$results['overall_status'] = 'warning';
	}

	// Check server environment
	$results['details']['php_version']             = PHP_VERSION;
	$results['details']['mail_function_available'] = function_exists( 'mail' );
	$sendmail_path                                 = ini_get( 'sendmail_path' ) ?: 'Not set';
	$results['details']['sendmail_path']           = $sendmail_path;

	// Detect local mail catchers (Mailpit / MailHog) — suppress WP Mail SMTP nag.
	$has_local_catcher = stripos( $sendmail_path, 'mailpit' ) !== false
						|| stripos( $sendmail_path, 'mailhog' ) !== false;
	if ( $has_local_catcher && ! $wp_mail_smtp_active ) {
		// Replace the recommendation with a note instead of a warning.
		$results['recommendations']               = array_filter(
			$results['recommendations'],
			fn( $r ) => stripos( $r, 'WP Mail SMTP' ) === false
		);
		$results['details']['smtp_plugin_active'] = 'mailpit';
	}

	if ( ! function_exists( 'mail' ) ) {
		$results['issues'][]       = 'PHP mail() function is not available on this server';
		$results['overall_status'] = 'critical';
	}

	// Check WordPress debug settings
	$results['details']['wp_debug']     = defined( 'WP_DEBUG' ) && WP_DEBUG;
	$results['details']['wp_debug_log'] = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

	if ( ! $results['details']['wp_debug_log'] ) {
		$results['recommendations'][] = 'Enable WP_DEBUG_LOG to get detailed email error logs';
	}

	// Check certificate generation dependencies
	$results['details']['fpdf_available']                 = file_exists( CERTIFICATE_GENERATOR_PATH . 'lib/fpdf/fpdf.php' );
	$results['details']['font_manager_available']         = class_exists( 'CertificateGenerator_FontManager' );
	$results['details']['certificate_function_available'] = function_exists( 'generate_certificate_pdf_email' );

	if ( ! $results['details']['fpdf_available'] ) {
		$results['issues'][]       = 'FPDF library not found - certificate generation will fail';
		$results['overall_status'] = 'critical';
	}

	if ( ! $results['details']['certificate_function_available'] ) {
		$results['issues'][]       = 'Certificate generation function not available';
		$results['overall_status'] = 'critical';
	}

	// Check upload directory permissions
	$upload_dir                                = wp_upload_dir();
	$results['details']['upload_dir']          = $upload_dir['basedir'];
	$results['details']['upload_dir_writable'] = wp_is_writable( $upload_dir['basedir'] );

	if ( ! $results['details']['upload_dir_writable'] ) {
		$results['issues'][]       = 'WordPress uploads directory is not writable - certificate storage will fail';
		$results['overall_status'] = 'critical';
	}

	// Check email log table
	global $wpdb;
	$table_name                                   = $wpdb->prefix . 'cert_email_logs';
	$results['details']['email_log_table_exists'] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;

	if ( ! $results['details']['email_log_table_exists'] ) {
		$results['issues'][]          = 'Email log table does not exist - email tracking will not work';
		$results['overall_status']    = 'warning';
		$results['recommendations'][] = 'Deactivate and reactivate the plugin to create the email log table';
	}

	// Final status determination
	if ( ! empty( $results['issues'] ) ) {
		$critical_issues = array_filter(
			$results['issues'],
			function ( $issue ) {
				return strpos( strtolower( $issue ), 'critical' ) !== false ||
					strpos( strtolower( $issue ), 'not available' ) !== false ||
					strpos( strtolower( $issue ), 'not found' ) !== false;
			}
		);

		if ( ! empty( $critical_issues ) ) {
			$results['overall_status'] = 'critical';
		} elseif ( $results['overall_status'] !== 'critical' ) {
			$results['overall_status'] = 'warning';
		}
	}

	return $results;
}

/**
 * Hook into wp_mail_failed to log detailed error information
 * This hook is called when wp_mail() fails
 *
 * @param WP_Error $error The error object from wp_mail()
 */
function certificate_generator_log_wp_mail_error( $error ) {
	if ( is_wp_error( $error ) ) {
		$error_data = array(
			'error_code'    => $error->get_error_code(),
			'error_message' => $error->get_error_message(),
			'error_data'    => $error->get_error_data(),
			'timestamp'     => current_time( 'mysql' ),
			'server'        => parse_url( home_url(), PHP_URL_HOST ),
		);

		// Log to WordPress error log (only in debug mode for detailed info)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			cg_debug_log( 'wp_mail_failed hook triggered' );
			cg_debug_log( 'WP_Error details: ' . print_r( $error_data, true ) );
		}

		// Also log to debug.log if WP_DEBUG_LOG is enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_message = sprintf(
				"[%s] Certificate Generator Email Failure\nCode: %s\nMessage: %s\nData: %s\n",
				current_time( 'Y-m-d H:i:s' ),
				$error->get_error_code(),
				$error->get_error_message(),
				print_r( $error->get_error_data(), true )
			);
			error_log( $log_message );
		}
	}
}
add_action( 'wp_mail_failed', 'certificate_generator_log_wp_mail_error', 10, 1 );

/**
 * Display admin notice with email health check status
 */
function certificate_generator_email_health_admin_notice() {
	// Only show on certificate generator related pages
	$screen = get_current_screen();
	if ( ! $screen || (
		strpos( $screen->id, 'certificate' ) === false &&
		strpos( $screen->id, 'students' ) === false &&
		strpos( $screen->id, 'teachers' ) === false &&
		strpos( $screen->id, 'schools' ) === false
	) ) {
		return;
	}

	// Run health check
	$health = certificate_generator_email_health_check();

	// Only show notice if there are issues or warnings
	if ( $health['overall_status'] === 'healthy' ) {
		return;
	}

	$notice_class = $health['overall_status'] === 'critical' ? 'notice-error' : 'notice-warning';

	?>
	<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
		<h3>Certificate Generator - Email Configuration Status</h3>

		<?php if ( ! empty( $health['issues'] ) ) : ?>
			<h4>Issues Detected:</h4>
			<ul style="list-style: disc; margin-left: 20px;">
				<?php foreach ( $health['issues'] as $issue ) : ?>
					<li><?php echo esc_html( $issue ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $health['recommendations'] ) ) : ?>
			<h4>Recommendations:</h4>
			<ul style="list-style: disc; margin-left: 20px;">
				<?php foreach ( $health['recommendations'] as $recommendation ) : ?>
					<li><?php echo esc_html( $recommendation ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h4>Current Configuration:</h4>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong>SMTP Plugin:</strong> <?php echo $health['details']['smtp_plugin_active'] ? 'Active' : 'Not Active'; ?></li>
			<li><strong>From Email:</strong> <?php echo esc_html( $health['details']['from_email'] ); ?></li>
			<li><strong>PHP mail() Available:</strong> <?php echo $health['details']['mail_function_available'] ? 'Yes' : 'No'; ?></li>
			<?php if ( isset( $health['details']['sendmail_path'] ) ) : ?>
				<li><strong>Sendmail Path:</strong> <?php echo esc_html( $health['details']['sendmail_path'] ); ?></li>
			<?php endif; ?>
		</ul>

		<?php if ( ! $health['details']['smtp_plugin_active'] ) : ?>
			<p><strong>Action Required:</strong> Install and configure
				<a href="<?php echo admin_url( 'plugin-install.php?s=wp+mail+smtp&tab=search&type=term' ); ?>" target="_blank">WP Mail SMTP</a>
				plugin for reliable email delivery on shared hosting.</p>
		<?php endif; ?>

		<p style="font-size: 12px; color: #666;">
			<em>Check your server's error logs and wp-content/debug.log for detailed email error messages.</em>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'certificate_generator_email_health_admin_notice' );

/**
 * Test email send function with comprehensive error reporting
 *
 * @param string $test_email Test recipient email
 * @param array  $options Optional test parameters
 * @return array Test results
 */
function certificate_generator_test_email_send( $test_email, $options = array() ) {
	$defaults = array(
		'subject'           => 'Certificate Generator Email Test',
		'message'           => 'This is a test email from the Certificate Generator plugin.',
		'attach_sample_pdf' => false,
	);

	$options = wp_parse_args( $options, $defaults );

	$results = array(
		'success' => false,
		'message' => '',
		'details' => array(),
		'errors'  => array(),
	);

	// Validate test email
	if ( ! is_email( $test_email ) ) {
		$results['errors'][] = 'Invalid test email address';
		$results['message']  = 'Test failed: Invalid email address';
		return $results;
	}

	// Run health check first
	$health_check                       = certificate_generator_email_health_check();
	$results['details']['health_check'] = $health_check;

	if ( $health_check['overall_status'] === 'critical' ) {
		$results['errors']  = array_merge( $results['errors'], $health_check['issues'] );
		$results['message'] = 'Test failed: Critical system issues detected';
		return $results;
	}

	// Attempt to send test email
	$headers      = array( 'Content-Type: text/html; charset=UTF-8' );
	$html_message = '<p>' . esc_html( $options['message'] ) . '</p>';
	$attachments  = array();

	// Add sample PDF if requested
	if ( $options['attach_sample_pdf'] ) {
		// Create a simple test PDF
		$test_pdf_path = wp_upload_dir()['basedir'] . '/cert-test.pdf';
		if ( file_exists( $test_pdf_path ) ) {
			$attachments[] = $test_pdf_path;
		}
	}

	$start_time = microtime( true );
	$email_sent = wp_mail( $test_email, $options['subject'], $html_message, $headers, $attachments );
	$send_time  = microtime( true ) - $start_time;

	$results['details']['send_time'] = round( $send_time, 3 );
	$results['success']              = $email_sent;

	if ( $email_sent ) {
		$results['message'] = 'Test email sent successfully';
	} else {
		global $phpmailer;
		if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
			$results['errors'][] = 'PHPMailer Error: ' . $phpmailer->ErrorInfo;
		}

		$last_error = error_get_last();
		if ( $last_error ) {
			$results['errors'][] = 'PHP Error: ' . $last_error['message'];
		}

		$results['message'] = 'Test email failed to send';
	}

	return $results;
}

/**
 * Queue bulk emails asynchronously instead of sending immediately
 * This is a wrapper that initiates batched processing to prevent timeouts
 *
 * @param string $post_type Post type (students/teachers/schools)
 * @param bool   $skip_already_sent Whether to skip already sent emails
 * @param array  $post_ids Array of post IDs to process
 * @return array Result with 'success', 'total_posts', 'batch_id', 'requires_batching'
 */
function certificate_generator_queue_bulk_emails_async( $post_type, $skip_already_sent = true, $post_ids = array() ) {
	global $wpdb;
	$cg_table = $wpdb->prefix . 'certificate_generator';

	$result = array(
		'success'           => false,
		'total_posts'       => 0,
		'batch_id'          => '',
		'requires_batching' => false,
		'errors'            => array(),
	);

	// Resolve CPT post_ids → emails → cg_ids (bridge from caller-supplied CPT IDs).
	$cg_ids = array();
	if ( ! empty( $post_ids ) ) {
		$emails = array();
		foreach ( $post_ids as $pid ) {
			$e = get_post_meta( (int) $pid, 'email', true );
			if ( is_email( $e ) ) {
				$emails[] = $e;
			}
		}
		if ( ! empty( $emails ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$cg_ids = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare( "SELECT MIN(id) FROM $cg_table WHERE email IN ($placeholders) GROUP BY email ORDER BY MIN(id) ASC", $emails )
				)
			);
		}
		if ( empty( $cg_ids ) ) {
			$result['errors'][] = 'No cg records found for provided post IDs';
			return $result;
		}
	} else {
		$cg_ids = array_map(
			'intval',
			$wpdb->get_col(
				"SELECT MIN(id) FROM $cg_table WHERE email != '' GROUP BY email ORDER BY MIN(id) ASC"
			)
		);
		if ( empty( $cg_ids ) ) {
			$result['errors'][] = 'No certificate records with email found';
			return $result;
		}
	}

	$total    = count( $cg_ids );
	$batch_id = 'batch_' . time() . '_' . wp_generate_password( 8, false );

	$result['batch_id']    = $batch_id;
	$result['total_posts'] = $total;
	$result['success']     = true;

	if ( $total > 50 ) {
		$result['requires_batching'] = true;
		set_transient(
			'cert_batch_' . $batch_id,
			array(
				'post_type'         => $post_type,
				'skip_already_sent' => $skip_already_sent,
				'cg_ids'            => $cg_ids,
				'total_posts'       => $total,
				'processed'         => 0,
			),
			3600
		);
		return $result;
	}

	return certificate_generator_queue_bulk_emails_batch( $post_type, $skip_already_sent, $cg_ids, 0, 50, $batch_id );
}

/**
 * Process a batch of posts for queueing (optimized to prevent N+1 queries)
 *
 * @param string $post_type Post type
 * @param bool   $skip_already_sent Skip already sent
 * @param array  $post_ids Specific post IDs (optional)
 * @param int    $offset Offset for pagination
 * @param int    $limit Number of posts to process
 * @param string $batch_id Batch identifier
 * @return array Result with queued/skipped counts
 */
function certificate_generator_queue_bulk_emails_batch( $post_type, $skip_already_sent, $cg_ids, $offset, $limit, $batch_id ) {
	$result = array(
		'success'  => false,
		'queued'   => 0,
		'skipped'  => 0,
		'batch_id' => $batch_id,
		'errors'   => array(),
	);

	$slice = array_slice( (array) $cg_ids, $offset, $limit );
	if ( empty( $slice ) ) {
		$result['success'] = true;
		return $result;
	}

	$queue_result = certificate_generator_bulk_queue_emails( $post_type, $slice, $skip_already_sent );

	$result['queued']  = $queue_result['queued'];
	$result['skipped'] = $queue_result['skipped'];
	$result['errors']  = $queue_result['errors'];
	$result['success'] = $queue_result['queued'] > 0 || empty( $queue_result['errors'] );

	if ( $result['queued'] > 0 && function_exists( 'certificate_generator_process_queue_batch' ) ) {
		certificate_generator_process_queue_batch();
	}

	return $result;
}

/**
 * AJAX handler for processing email queue batches (optimized for large datasets)
 */
add_action( 'wp_ajax_certificate_generator_process_batch', 'certificate_generator_process_batch_ajax' );
function certificate_generator_process_batch_ajax() {
	// Check nonce
	check_ajax_referer( 'certificate_generator_bulk_email', 'nonce' );

	// Check permissions
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'certificate-generator' ) ) );
	}

	// Get batch parameters
	$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';
	$offset   = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
	$limit    = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100;

	if ( empty( $batch_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid batch ID', 'certificate-generator' ) ) );
	}

	// Get batch metadata from transient
	$batch_data = get_transient( 'cert_batch_' . $batch_id );

	if ( ! $batch_data ) {
		wp_send_json_error( array( 'message' => __( 'Batch data expired or not found', 'certificate-generator' ) ) );
	}

	// Process this batch
	$result = certificate_generator_queue_bulk_emails_batch(
		$batch_data['post_type'],
		$batch_data['skip_already_sent'],
		$batch_data['cg_ids'] ?? array(),
		$offset,
		$limit,
		$batch_id
	);

	// Update progress
	$batch_data['processed'] = $offset + $limit;
	set_transient( 'cert_batch_' . $batch_id, $batch_data, 3600 );

	// Calculate progress percentage
	$progress_percent = min( 100, round( ( $batch_data['processed'] / $batch_data['total_posts'] ) * 100 ) );

	// Check if complete
	$is_complete = $batch_data['processed'] >= $batch_data['total_posts'];

	// Prepare response
	$response = array(
		'success'   => $result['success'],
		'queued'    => $result['queued'],
		'skipped'   => $result['skipped'],
		'processed' => $batch_data['processed'],
		'total'     => $batch_data['total_posts'],
		'progress'  => $progress_percent,
		'complete'  => $is_complete,
		'batch_id'  => $batch_id,
		'errors'    => $result['errors'] ?? array(),
	);

	// Clean up transient if complete
	if ( $is_complete ) {
		delete_transient( 'cert_batch_' . $batch_id );
	}

	// If no emails were queued in this batch, return as error so UI can display diagnostics
	if ( empty( $response['queued'] ) ) {
		wp_send_json_error( $response );
	}

	wp_send_json_success( $response );
}
?>