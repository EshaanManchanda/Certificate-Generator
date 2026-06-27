<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include required libraries
require_once CERTIFICATE_GENERATOR_PATH . 'lib/fpdf/fpdf.php';

// Helper function to find certificate type from various possible meta keys
function find_certificate_type( $post_id ) {
	// List of possible meta keys that might store certificate type
	$possible_keys = array(
		'certificate_type',
		'certificate-type',
		'certificatetype',
		'cert_type',
		'cert-type',
		'certificate',
		'type',
	);

	foreach ( $possible_keys as $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( ! empty( $value ) ) {
			if ( $key !== 'certificate_type' ) {
				update_post_meta( $post_id, 'certificate_type', $value );
			}
			return $value;
		}
	}

	// If still not found, try to get all meta and search for anything that might be a certificate type
	$all_meta = get_post_meta( $post_id );
	foreach ( $all_meta as $meta_key => $meta_value ) {
		// Look for keys that might contain 'certificate' or 'type'
		if ( strpos( strtolower( $meta_key ), 'certificate' ) !== false || strpos( strtolower( $meta_key ), 'type' ) !== false ) {
			if ( is_array( $meta_value ) && ! empty( $meta_value[0] ) ) {
				update_post_meta( $post_id, 'certificate_type', $meta_value[0] );
				return $meta_value[0];
			}
		}
	}

	return false;
}

// Register AJAX handlers for certificate generation progress
add_action( 'wp_ajax_certificate_generation_progress', 'certificate_generation_progress_handler' );

// Handle individual certificate download
add_action( 'init', 'handle_individual_certificate_download' );
function handle_individual_certificate_download() {
	if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'download_certificate' || ! isset( $_GET['student_id'] ) ) {
		return;
	}

	// Auth gate: must be logged in with edit capability
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'You must be logged in to download certificates.', 'Forbidden', array( 'response' => 403 ) );
	}

	$student_id = intval( $_GET['student_id'] );

	// Nonce check
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cg_download_cert_' . $student_id ) ) {
		wp_die( 'Security check failed.', 'Forbidden', array( 'response' => 403 ) );
	}

	// Verify student exists
	$student = get_post( $student_id );
	if ( ! $student || $student->post_type !== 'students' ) {
		wp_die( 'Invalid student ID' );
	}

	$student_name = get_post_meta( $student_id, 'student_name', true );

	// Generate or get existing certificate
	$cert_type_dl = get_post_meta( $student_id, 'certificate_type', true );
	$fields       = class_exists( 'CG_Field_Schema' )
		? CG_Field_Schema::get_all_renderable_fields( $cert_type_dl )
		: array( 'student_name', 'school_name', 'issue_date' );
	$file_url     = generate_certificate_pdf( $student_id, $fields );

	if ( $file_url ) {
		// Try to get the file path from post meta (more reliable)
		$file_path = get_post_meta( $student_id, 'certificate_file_path', true );

		// If no stored path, fall back to URL conversion
		if ( empty( $file_path ) ) {
			$upload_dir = wp_upload_dir();
			if ( strpos( $file_url, $upload_dir['url'] ) !== false ) {
				$file_path = str_replace( $upload_dir['url'], $upload_dir['basedir'], $file_url );
			} else {
				$file_path = $upload_dir['basedir'] . '/' . basename( $file_url );
			}
		}

		if ( file_exists( $file_path ) ) {
			$filename = function_exists( 'cg_certificate_pdf_filename' )
				? cg_certificate_pdf_filename( $student_name, '', (string) $student_id )
				: sanitize_file_name( $student_name . '_' . $student_id . '_certificate.pdf' );
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			header( 'Cache-Control: no-cache, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			readfile( $file_path );
			exit;
		} else {
			wp_die( 'Certificate file not found. Please contact the administrator.' );
		}
	} else {
		wp_die( 'Failed to generate certificate. Please contact the administrator.' );
	}
}

// AJAX handler for progress updates
function certificate_generation_progress_handler() {
	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'certificate_generation_progress_nonce' ) ) {
		wp_send_json_error( 'Invalid security token' );
		wp_die();
	}

	// Get session ID from request
	$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );

	// Get progress data from transient
	$progress_data = get_transient( 'certificate_progress_' . $session_id );

	if ( $progress_data ) {
		wp_send_json_success( $progress_data );
	} else {
		wp_send_json_error( 'No progress data found' );
	}

	wp_die();
}

// Add shortcode for bulk certificate download
add_shortcode( 'school_bulk_certificate_download', 'school_bulk_certificate_download_shortcode' );
function school_bulk_certificate_download_shortcode() {
	// ── Plan gate: Bulk ZIP download requires Pro or Business ─────────────
	if ( class_exists( 'CG_License_Manager' ) && ! CG_License_Manager::is_pro() ) {
		return '<div style="padding:20px;border:1px solid #f59e0b;border-radius:8px;background:#fffbeb;">'
			. '<h3 style="margin:0 0 8px;color:#92400e;">🔒 Pro Feature — Bulk Certificate Download</h3>'
			. '<p style="margin:0 0 12px;color:#78350f;">Bulk ZIP download requires the <strong>Pro</strong> or <strong>Business</strong> plan.</p>'
			. '<a href="https://eshaanportfolio.vercel.app/" class="button button-primary" target="_blank">Upgrade Now →</a>'
			. '</div>';
	}

	// Enqueue required scripts and styles
	wp_enqueue_script( 'jquery' );

	// Add custom script for progress bar
	wp_enqueue_script(
		'certificate-progress-js',
		plugin_dir_url( __FILE__ ) . '../assets/js/certificate-progress.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);

	// Add custom styles for progress bar
	wp_enqueue_style(
		'certificate-progress-css',
		plugin_dir_url( __FILE__ ) . '../assets/css/certificate-progress.css',
		array(),
		'1.0.0'
	);

	// Generate a unique session ID for this certificate generation process
	$session_id = uniqid( 'cert_' );

	// Pass data to JavaScript
	wp_localize_script(
		'certificate-progress-js',
		'certificateProgress',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'certificate_generation_progress_nonce' ),
			'sessionId' => $session_id,
		)
	);

	// Check if form is submitted
	if ( isset( $_GET['school_name'] ) ) {
		$school_name = sanitize_text_field( wp_unslash( $_GET['school_name'] ) );

		// Verify school exists - enhanced matching for better reliability
		$school_args = array(
			'post_type'      => 'schools',
			'posts_per_page' => -1, // Get all schools to ensure we find a match
			'meta_query'     => array(
				'relation' => 'OR', // Match either school name or abbreviation
				// Try exact match first
				array(
					'key'     => 'school_name',
					'value'   => $school_name,
					'compare' => '=',
				),
				array(
					'key'     => 'school_abbreviation',
					'value'   => $school_name,
					'compare' => '=',
				),
				// Then try case-insensitive partial match
				array(
					'key'     => 'school_name',
					'value'   => $school_name,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'school_abbreviation',
					'value'   => $school_name,
					'compare' => 'LIKE',
				),
				// Also try matching against stored abbreviations when searching by name
				array(
					'key'     => 'school_name',
					'value'   => get_post_meta( get_the_ID(), 'school_abbreviation', true ),
					'compare' => '=',
				),
				array(
					'key'     => 'school_abbreviation',
					'value'   => get_post_meta( get_the_ID(), 'school_name', true ),
					'compare' => '=',
				),
			),
		);

		$school_query = new WP_Query( $school_args );

		if ( $school_query->have_posts() ) {
			$school_query->the_post();
			$school_id           = get_the_ID();
			$school_name         = get_post_meta( $school_id, 'school_name', true );
			$school_abbreviation = get_post_meta( $school_id, 'school_abbreviation', true );
			wp_reset_postdata();

			// Find all students from this school with required fields - improved query
			$students_args = array(
				'post_type'      => 'students',
				'posts_per_page' => -1, // Get all students
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'relation' => 'OR',
						array(
							'key'     => 'school_name',
							'value'   => $school_name,
							'compare' => '=',  // Exact match for better reliability
						),
						array(
							'key'     => 'school_abbreviation',
							'value'   => $school_abbreviation,
							'compare' => '=',  // Exact match for better reliability
						),
					),
					// Ensure required fields exist and are not empty
					array(
						'key'     => 'student_name',
						'value'   => '',
						'compare' => '!=',
						'type'    => 'CHAR',
					),
					// Improved issue_date validation - only check that it exists and isn't empty
					array(
						'key'     => 'issue_date',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'issue_date',
						'value'   => '',
						'compare' => '!=',
					),
					// Add certificate_type validation
					array(
						'key'     => 'certificate_type',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'certificate_type',
						'value'   => '',
						'compare' => '!=',
					),
				),
				'orderby'        => 'meta_value',
				'meta_key'       => 'student_name',
				'order'          => 'ASC',
			);

			// First, let's check if we need to display student certificates or process bulk download
			$display_certificates  = isset( $_GET['display_certificates'] ) && $_GET['display_certificates'] === 'true';
			$process_bulk_download = isset( $_GET['process_bulk_download'] ) && $_GET['process_bulk_download'] === 'true';

			// If neither display nor process is set, default to display
			if ( ! $display_certificates && ! $process_bulk_download ) {
				$display_certificates = true;
			}

			$students_query = new WP_Query( $students_args );

			if ( $students_query->have_posts() ) {
				$total_students = $students_query->post_count;

				// If we're just displaying certificates, show the list with details
				if ( $display_certificates ) {
					$output  = '<div class="certificate-list-container" style="max-width: 900px; margin: 30px auto; padding: 25px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
					$output .= '<h3 style="color: #0073aa; margin-top: 0; font-size: 22px;">Student Certificates for ' . esc_html( $school_name ) . '</h3>';
					$output .= '<p style="margin-bottom: 20px; color: #555;">Total certificates available: ' . $total_students . '</p>';

					// Add bulk download button if more than 3 certificates
					if ( $total_students > 3 ) {
						$bulk_download_url = add_query_arg(
							array(
								'school_name'           => $school_name,
								'process_bulk_download' => 'true',
							)
						);
						$output           .= '<div style="text-align: center; margin-bottom: 30px;">';
						$output           .= '<a href="' . esc_url( $bulk_download_url ) . '" class="button button-primary" style="display: inline-block; padding: 12px 24px; background: linear-gradient(to right, #0073aa, #005f8d); color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">Bulk Certificate Download</a>';
						$output           .= '</div>';
					}

					// Create table for certificate details
					$output .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
					$output .= '<thead style="background-color: #f0f6fc;">';
					$output .= '<tr>';
					$output .= '<th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd;">Student Name</th>';
					$output .= '<th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd;">Certificate Type</th>';
					$output .= '<th style="padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd;">Issue Date</th>';
					$output .= '<th style="padding: 12px 15px; text-align: center; border-bottom: 1px solid #ddd;">Action</th>';
					$output .= '</tr>';
					$output .= '</thead>';
					$output .= '<tbody>';

					while ( $students_query->have_posts() ) {
						$students_query->the_post();
						$student_id       = get_the_ID();
						$student_name     = get_post_meta( $student_id, 'student_name', true );
						$issue_date       = get_post_meta( $student_id, 'issue_date', true );
						$certificate_type = get_post_meta( $student_id, 'certificate_type', true );

						// If certificate_type is empty, try to find it
						if ( empty( $certificate_type ) ) {
							$certificate_type = find_certificate_type( $student_id ) ?: 'Unknown';
						}

						// Format the issue date if it exists
						$formatted_date = ! empty( $issue_date ) ? cg_format_date( $issue_date ) : 'Unknown';

						// Generate individual certificate URL (nonce required by handler).
						$certificate_url = wp_nonce_url(
							add_query_arg(
								array(
									'student_id' => $student_id,
									'action'     => 'download_certificate',
								)
							),
							'cg_download_cert_' . $student_id
						);

						$output .= '<tr style="border-bottom: 1px solid #f0f0f0;">';
						$output .= '<td style="padding: 12px 15px;">' . esc_html( $student_name ) . '</td>';
						$output .= '<td style="padding: 12px 15px;">' . esc_html( $certificate_type ) . '</td>';
						$output .= '<td style="padding: 12px 15px;">' . esc_html( $formatted_date ) . '</td>';
						$output .= '<td style="padding: 12px 15px; text-align: center;">';
						$output .= '<a href="' . esc_url( $certificate_url ) . '" class="button" style="display: inline-block; padding: 8px 12px; background: #f1f1f1; color: #0073aa; text-decoration: none; border-radius: 4px; font-size: 14px;">Download</a>';
						$output .= '</td>';
						$output .= '</tr>';
					}

					$output .= '</tbody>';
					$output .= '</table>';

					// Back button
					$output .= '<p style="margin-top: 30px; text-align: center;">';
					$output .= '<a href="' . esc_url( remove_query_arg( array( 'display_certificates', 'school_name' ) ) ) . '" class="button" style="display: inline-block; padding: 10px 15px; background: #f1f1f1; color: #0073aa; text-decoration: none; border-radius: 4px;">← Back to search form</a>';
					$output .= '</p>';

					$output .= '</div>';

					wp_reset_postdata();
					return $output;
				}

				// If we're processing bulk download, continue with the original functionality
				// Create a temporary directory for certificates with secure permissions
				$upload_dir = wp_upload_dir();
				$temp_dir   = $upload_dir['basedir'] . '/temp_certificates_' . time() . '_' . wp_generate_password( 8, false );

				if ( ! file_exists( $temp_dir ) ) {
					if ( ! mkdir( $temp_dir, 0755, true ) ) {
						error_log( "Failed to create temporary directory: {$temp_dir}" );
						return '<div class="notice notice-error"><p>Error: Could not create temporary directory for certificate generation. Please try again later.</p></div>';
					}
					// Ensure directory is not accessible via web
					$htaccess = $temp_dir . '/.htaccess';
					file_put_contents( $htaccess, 'Deny from all' );
				}

				$certificate_files = array();
				$student_count     = 0;

				// Initialize progress data
				$progress_data = array(
					'total'           => $total_students,
					'processed'       => 0,
					'current_student' => '',
					'success_count'   => 0,
					'status'          => 'processing',
				);

				// Store initial progress in transient
				set_transient( 'certificate_progress_' . $session_id, $progress_data, 3600 );

				// Add progress bar container before processing
				$progress_html  = '<div id="certificate-progress-container" data-session-id="' . esc_attr( $session_id ) . '">';
				$progress_html .= '<h3>Generating Certificates</h3>';
				$progress_html .= '<div class="progress-bar-wrapper"><div class="progress-bar" style="width: 0%;"></div></div>';
				$progress_html .= '<div class="progress-status">Preparing to generate certificates...</div>';
				$progress_html .= '<div class="progress-details">Processing: <span class="current-student">-</span></div>';
				$progress_html .= '<div class="progress-counter">0 of ' . $total_students . ' certificates processed</div>';
				$progress_html .= '</div>';

				echo $progress_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				// Flush output buffer to show progress bar immediately
				if ( ob_get_level() > 0 ) {
					ob_flush();
					flush();
				}

				// Generate certificates for each student
				while ( $students_query->have_posts() ) {
					$students_query->the_post();
					$student_id = get_the_ID();

					// SQL-first — post meta only exists for legacy CPT records.
					$_sql_row     = function_exists( 'cg_get_sql_row_for_post_cached' )
						? cg_get_sql_row_for_post_cached( $student_id, 'students' )
						: null;
					$student_name = ( $_sql_row && ! empty( $_sql_row['student_name'] ) )
						? $_sql_row['student_name']
						: (string) get_post_meta( $student_id, 'student_name', true );
					$_sq_email    = ( $_sql_row && ! empty( $_sql_row['email'] ) )
						? $_sql_row['email']
						: (string) get_post_meta( $student_id, 'email', true );
					global $wpdb;
					$_cg_tbl = $wpdb->prefix . 'certificate_generator';
					$_cg_id  = $_sq_email
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
						? (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $_cg_tbl WHERE email = %s ORDER BY id DESC LIMIT 1", $_sq_email ) )
						: 0;

					// Update progress data
					++$progress_data['processed'];
					$progress_data['current_student'] = $student_name;
					set_transient( 'certificate_progress_' . $session_id, $progress_data, 3600 );

					// Fields to include in certificate
					$cert_type_bulk = ( $_sql_row && ! empty( $_sql_row['certificate_type'] ) )
						? $_sql_row['certificate_type']
						: (string) get_post_meta( $student_id, 'certificate_type', true );
					$fields         = class_exists( 'CG_Field_Schema' )
						? CG_Field_Schema::get_all_renderable_fields( $cert_type_bulk )
						: array( 'student_name', 'school_name', 'issue_date' );

					// Validate student data before generating certificate
					$student_data_valid = true;
					$missing_fields     = array();

					// Validate student data before generating certificate
					foreach ( $fields as $field ) {
						$field_value = get_post_meta( $student_id, $field, true );

						// Special handling for certificate_type which might be stored differently
						if ( $field === 'certificate_type' && empty( $field_value ) ) {
							// Use our helper function to find certificate type from various possible meta keys
							$found_type = find_certificate_type( $student_id );
							if ( $found_type ) {
								$field_value = $found_type;
							}
						}

						if ( empty( $field_value ) ) {
							$student_data_valid = false;
							$missing_fields[]   = $field;
						}
					}

					if ( ! $student_data_valid ) {
						error_log( "Certificate generation skipped for student ID: {$student_id}. Missing fields: " . implode( ', ', $missing_fields ) );
						continue;
					}

					// Generate certificate PDF with error handling
					$file_url = generate_certificate_pdf( $student_id, $fields );

					if ( $file_url ) {
						// Try to get the file path from post meta (more reliable)
						$file_path = get_post_meta( $student_id, 'certificate_file_path', true );

						// If no stored path, fall back to URL conversion with improved path handling
						if ( empty( $file_path ) ) {
							error_log( "No stored certificate path found for student ID: {$student_id}, falling back to URL conversion" );
							// Handle both URL formats (with or without domain)
							if ( strpos( $file_url, $upload_dir['url'] ) !== false ) {
								$file_path = str_replace( $upload_dir['url'], $upload_dir['basedir'], $file_url );
							} else {
								// Handle relative URLs
								$file_path = $upload_dir['basedir'] . '/' . basename( $file_url );
							}
						}

						// Create a sanitized filename with student name and ID
						$safe_name = function_exists( 'cg_certificate_pdf_filename' )
							? cg_certificate_pdf_filename( $student_name, $cert_type_bulk, $_cg_id ?: $student_id )
							: sanitize_file_name( $student_name . '_' . $cert_type_bulk . '_' . ( $_cg_id ?: $student_id ) . '.pdf' );
						$dest_path = $temp_dir . '/' . $safe_name;

						// Check if file exists before attempting to copy
						if ( file_exists( $file_path ) ) {
							// Copy the file to temp directory with a readable name
							if ( copy( $file_path, $dest_path ) ) {
								$certificate_files[] = $dest_path;
								++$student_count;
								// Update success count in progress data
								++$progress_data['success_count'];
								set_transient( 'certificate_progress_' . $session_id, $progress_data, 3600 );
							} else {
								error_log( "Failed to copy certificate file from {$file_path} to {$dest_path}" );
							}
						} else {
							error_log( "Certificate file not found at: {$file_path}" );
							// Try multiple alternative paths with improved Windows path handling
							$alt_paths = array(
								// Alternative 1: Windows path conversion fix
								str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['basedir'] ) . DIRECTORY_SEPARATOR . basename( $file_url ),
								// Alternative 2: Direct basename approach
								$upload_dir['basedir'] . '/' . basename( $file_url ),
								// Alternative 3: Try with wp-content/uploads path
								WP_CONTENT_DIR . '/uploads/' . basename( $file_url ),
							);

							$copied = false;
							foreach ( $alt_paths as $index => $alt_file_path ) {
								if ( file_exists( $alt_file_path ) ) {
									if ( copy( $alt_file_path, $dest_path ) ) {
										$certificate_files[] = $dest_path;
										++$student_count;
										++$progress_data['success_count'];
										set_transient( 'certificate_progress_' . $session_id, $progress_data, 3600 );
										$copied = true;
										break;
									} else {
										error_log( "Certificate Generator: Failed to copy certificate for student {$student_id} (alt path {$index})" );
									}
								}
							}

							// Last resort: Try to regenerate the certificate if not copied yet
							if ( ! $copied ) {
								$new_file_url  = generate_certificate_pdf( $student_id, $fields );
								$new_file_path = get_post_meta( $student_id, 'certificate_file_path', true );

								if ( ! empty( $new_file_path ) && file_exists( $new_file_path ) ) {
									if ( copy( $new_file_path, $dest_path ) ) {
										$certificate_files[] = $dest_path;
										++$student_count;
										++$progress_data['success_count'];
										set_transient( 'certificate_progress_' . $session_id, $progress_data, 3600 );
									} else {
										error_log( "Certificate Generator: Failed to copy regenerated certificate for student {$student_id}" );
									}
								} else {
									error_log( "Certificate Generator: Failed to regenerate certificate for student {$student_id}" );
								}
							}
						}
					}
				}

				wp_reset_postdata();

				// Mark progress as completed
				$progress_data['status'] = 'completed';
				set_transient( 'certificate_progress_' . $session_id, $progress_data, 3600 );

				// Create ZIP file if we have certificates
				if ( ! empty( $certificate_files ) ) {
					$certs_for_zip = array();
					foreach ( $certificate_files as $file ) {
						$certs_for_zip[] = array(
							'path'     => $file,
							'filename' => basename( $file ),
						);
					}

					$zip_result = function_exists( 'certificate_generator_create_zip_for_email' )
						? certificate_generator_create_zip_for_email( $certs_for_zip, $school_name )
						: false;

					// Clean up temp files regardless of ZIP outcome.
					foreach ( $certificate_files as $file ) {
						@unlink( $file );
					}
					@rmdir( $temp_dir );

					if ( $zip_result && $zip_result['certificate_count'] > 0 ) {
						// Display success message with download link and progress info
						$output  = '<div style="max-width: 600px; margin: 40px auto; padding: 20px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
						$output .= '<h3 style="color: #0073aa; margin-top: 0;">Certificates Generated Successfully</h3>';
						$output .= '<div class="generation-summary" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">';
						$output .= '<p style="margin: 5px 0;"><strong>School:</strong> ' . esc_html( $school_name ) . '</p>';
						$output .= '<p style="margin: 5px 0;"><strong>Total Students:</strong> ' . $students_query->post_count . '</p>';
						$output .= '<p style="margin: 5px 0;"><strong>Certificates Generated:</strong> ' . $student_count . '</p>';

						// Add warning if some certificates couldn't be generated
						if ( $student_count < $students_query->post_count ) {
							$missing_count = $students_query->post_count - $student_count;
							$output       .= '<div style="margin-top: 10px; padding: 10px; background: #fff8e5; border-left: 4px solid #ffb900; border-radius: 3px;">';
							$output       .= '<p style="margin: 5px 0;"><strong>Note:</strong> ' . $missing_count . ' certificate(s) could not be generated. This might be due to missing data or file access issues.</p>';
							$output       .= '</div>';
						}

						$output .= '</div>';
						$output .= '<a href="' . esc_url( $zip_result['zip_url'] ) . '" class="button" style="display: inline-block; padding: 12px 20px; background: linear-gradient(to right, #0073aa, #005f8d); color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; margin-bottom: 15px;">Download All Certificates (ZIP)</a>';

						// Add instructions for after download
						$output .= '<div style="margin-top: 15px; padding: 15px; background: #f0f6fc; border-radius: 5px; font-size: 14px;">';
						$output .= '<p><strong>After downloading:</strong></p>';
						$output .= '<ol style="margin-left: 20px;">';
						$output .= '<li>Extract the ZIP file to view all certificates</li>';
						$output .= '<li>Each certificate is named after the student</li>';
						$output .= '<li>All certificates are in PDF format</li>';
						$output .= '</ol>';
						$output .= '</div>';

						$output .= '<p style="margin-top: 20px;"><a href="' . esc_url( remove_query_arg( array( 'school_name', 'place' ) ) ) . '" style="color: #0073aa;">← Back to search form</a></p>';
						$output .= '</div>';

						// Schedule cleanup — handler registered in CG_Cron_Jobs::init().
						wp_schedule_single_event( time() + 3600, 'cleanup_certificate_zip', array( $zip_result['zip_path'] ) );

						return $output;
					} else {
						return '<div class="notice notice-error"><p>Error creating ZIP file. Please try again.</p></div>';
					}
				} else {
					// Log the query parameters for debugging
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						cg_debug_log( "No certificates generated for school: {$school_name}. Query parameters: " . print_r( $students_args, true ) );
					}

					// Get all students from this school to check which ones are missing data
					$all_students_args = array(
						'post_type'      => 'students',
						'posts_per_page' => -1,
						'meta_query'     => array(
							'relation' => 'OR',
							array(
								'key'     => 'school_name',
								'value'   => $school_name,
								'compare' => '=',
							),
							array(
								'key'     => 'school_abbreviation',
								'value'   => $school_abbreviation,
								'compare' => '=',
							),
						),
					);

					$all_students         = new WP_Query( $all_students_args );
					$missing_data         = array();
					$students_with_issues = array();

					// Log the query for debugging
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						cg_debug_log( "All students query for school {$school_name}: " . print_r( $all_students_args, true ) );
						cg_debug_log( "Total students found: {$all_students->post_count}" );
					}

					while ( $all_students->have_posts() ) {
						$all_students->the_post();
						$student_id       = get_the_ID();
						$student_name     = get_post_meta( $student_id, 'student_name', true ) ?: 'Unknown';
						$issue_date       = get_post_meta( $student_id, 'issue_date', true );
						$certificate_type = get_post_meta( $student_id, 'certificate_type', true );

						// Enhanced logging with raw data values
						error_log( "Student ID {$student_id} data - Name: '{$student_name}', Issue Date: '{$issue_date}', Certificate Type: '{$certificate_type}'" );

						$student_issues = array();

						if ( empty( $student_name ) || $student_name === 'Unknown' ) {
							$student_issues[] = 'missing student name';
						}
						if ( empty( $issue_date ) ) {
							$student_issues[] = 'missing issue date';
						}

						// Try to find certificate type using our helper function if the primary one is empty
						if ( empty( $certificate_type ) ) {
							$found_type = find_certificate_type( $student_id );
							if ( $found_type ) {
								$certificate_type = $found_type;
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									cg_debug_log( "Successfully found certificate type using helper function: '{$certificate_type}'" );
								}
							} else {
								$student_issues[] = 'missing certificate type';
								// Log all meta for this student to help diagnose the issue
								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									$all_meta = get_post_meta( $student_id );
									cg_debug_log( "All meta for student ID {$student_id}: " . print_r( $all_meta, true ) );
								}

								// Check if there are any certificate templates in the system
								$cert_templates = new WP_Query(
									array(
										'post_type'      => 'certificates',
										'posts_per_page' => -1,
									)
								);

								if ( $cert_templates->have_posts() ) {
									error_log( 'Available certificate templates: ' . $cert_templates->post_count );
									while ( $cert_templates->have_posts() ) {
										$cert_templates->the_post();
										$template_id   = get_the_ID();
										$template_type = get_post_meta( $template_id, 'certificate_type', true );
										error_log( "Template ID {$template_id} has certificate_type: '{$template_type}'" );
									}
									wp_reset_postdata();
								} else {
									error_log( 'No certificate templates found in the system' );
								}
							}
						}

						if ( ! empty( $student_issues ) ) {
							$missing_data[]                      = "Student ID {$student_id} ({$student_name}): " . implode( ', ', $student_issues );
							$students_with_issues[ $student_id ] = array(
								'name'   => $student_name,
								'issues' => $student_issues,
							);
						}
					}
					wp_reset_postdata();

					// Log missing data details
					if ( ! empty( $missing_data ) ) {
						error_log( "Missing data details for school {$school_name}: " . implode( ', ', $missing_data ) );
					}

					$output  = '<div class="notice notice-error" style="padding: 20px; margin: 20px 0; border-left-color: #dc3232;">';
					$output .= '<h3 style="margin-top: 0; color: #dc3232;">Certificate Generation Failed</h3>';
					$output .= '<p><strong>No certificates could be generated due to missing information.</strong></p>';
					$output .= '<p>Total students found: ' . $all_students->post_count . '</p>';

					// Add a more detailed explanation of what might be wrong
					$output .= '<div style="background-color: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 5px;">';
					$output .= '<p><strong>Troubleshooting Tips:</strong></p>';
					$output .= '<ul style="margin-left: 20px;">';
					$output .= '<li>Check that all student records have the required fields properly filled out</li>';
					$output .= '<li>Verify that certificate types match exactly between student records and certificate templates</li>';
					$output .= '<li>If you recently imported data, ensure all field names match the expected format</li>';
					$output .= '</ul>';
					$output .= '</div>';

					if ( ! empty( $missing_data ) ) {
						// Group issues by type for a better summary
						$missing_name_count = 0;
						$missing_date_count = 0;
						$missing_type_count = 0;

						foreach ( $students_with_issues as $student ) {
							foreach ( $student['issues'] as $issue ) {
								if ( strpos( $issue, 'student name' ) !== false ) {
									++$missing_name_count;
								}
								if ( strpos( $issue, 'issue date' ) !== false ) {
									++$missing_date_count;
								}
								if ( strpos( $issue, 'certificate type' ) !== false ) {
									++$missing_type_count;
								}
							}
						}

						$output .= '<div style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">';
						$output .= '<p><strong>Summary of Issues:</strong></p>';
						$output .= '<ul style="list-style-type: disc; margin-left: 20px;">';
						if ( $missing_name_count > 0 ) {
							$output .= '<li>' . $missing_name_count . ' students missing name</li>';
						}
						if ( $missing_date_count > 0 ) {
							$output .= '<li>' . $missing_date_count . ' students missing issue date</li>';
						}
						if ( $missing_type_count > 0 ) {
							$output .= '<li>' . $missing_type_count . ' students missing certificate type</li>';
						}
						$output .= '</ul>';
						$output .= '</div>';

						$output .= '<details style="margin-top: 15px;">';
						$output .= '<summary style="cursor: pointer; padding: 10px; background: #f1f1f1; border-radius: 5px;"><strong>Show Detailed Issues</strong></summary>';
						$output .= '<div style="margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 5px;">';
						$output .= '<ul style="list-style-type: disc; margin-left: 20px;">';
						foreach ( $missing_data as $error ) {
							$output .= '<li>' . esc_html( $error ) . '</li>';
						}
						$output .= '</ul>';
						$output .= '</div>';
						$output .= '</details>';
					}

					$output .= '<div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-radius: 5px;">';
					$output .= '<p><strong>Required Information for Certificate Generation:</strong></p>';
					$output .= '<ul style="list-style-type: disc; margin-left: 20px;">';
					$output .= '<li>Student Name</li>';
					$output .= '<li>School Name</li>';
					$output .= '<li>Issue Date</li>';
					$output .= '<li>Certificate Type</li>';
					$output .= '</ul>';
					$output .= '</div>';

					// Add detailed information about specific students with issues
					if ( ! empty( $students_with_issues ) && count( $students_with_issues ) <= 10 ) {
						$output .= '<div style="background-color: #fff8e5; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #ffb900;">';
						$output .= '<h4 style="margin-top: 0;">Students with Missing Information:</h4>';
						$output .= '<ul style="margin-left: 20px;">';

						foreach ( $students_with_issues as $student_id => $student_data ) {
							$output .= '<li><strong>' . esc_html( $student_data['name'] ) . '</strong> - Missing: ' .
										esc_html( implode( ', ', $student_data['issues'] ) ) . '</li>';
						}

						$output .= '</ul>';
						$output .= '</div>';
					} elseif ( ! empty( $students_with_issues ) ) {
						// Too many students with issues to list individually
						$output .= '<div style="background-color: #fff8e5; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #ffb900;">';
						$output .= '<p><strong>Note:</strong> ' . count( $students_with_issues ) . ' students have missing information. Check the error logs for details.</p>';
						$output .= '</div>';
					}

					$output .= '<p style="margin-top: 15px;">Contact your administrator if you believe this is an error.</p>';
					$output .= '<p><a href="' . esc_url( remove_query_arg( array( 'school_name', 'place' ) ) ) . '" class="button" style="display: inline-block; margin-top: 10px; padding: 8px 12px; background: #f1f1f1; color: #0073aa; text-decoration: none; border-radius: 5px;">← Back to search form</a></p>';
					$output .= '</div>';
					return $output;
				}
			} else {
				return '<div class="notice notice-error"><p>No students found for this school.</p></div>';
			}
		} else {
			return '<div class="notice notice-error"><p>School not found. Please check the school name and place.</p></div>';
		}
	} else {
		// Display search form with improved instructions and styling
		$output  = '<form method="get" style="max-width: 600px; margin: 40px auto; padding: 25px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
		$output .= '<h3 style="color: #0073aa; margin-top: 0; font-size: 22px;">Bulk Certificate Download for Schools</h3>';
		$output .= '<p style="margin-bottom: 20px; color: #555;">Enter your school name to view and download certificates for all students.</p>';

		$output .= '<div style="margin-bottom: 20px; padding: 15px; background: #f0f6fc; border-radius: 5px;">';
		$output .= '<p style="margin: 0 0 10px 0; font-weight: bold; color: #0073aa;">Instructions:</p>';
		$output .= '<ul style="margin: 0; padding-left: 20px; color: #555;">';
		$output .= '<li>Enter the exact school name as registered in the system</li>';
		$output .= '<li>You can view all student certificates and download them individually</li>';
		$output .= '<li>If there are more than 3 certificates, you can download them all at once</li>';
		$output .= '</ul>';
		$output .= '</div>';

		$output .= '<div class="custom-form-group">';
		$output .= '<label for="school_name" style="display: block; margin-bottom: 8px; font-size: 15px; font-weight: bold; color: #333;">School Name:</label>';
		$output .= '<input type="text" id="school_name" name="school_name" placeholder="Enter full school name or abbreviation" required style="width: 100%; padding: 12px 15px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">';
		$output .= '<input type="hidden" name="display_certificates" value="true">';
		$output .= '</div>';
		$output .= '<button type="submit" style="display: block; width: 100%; padding: 14px; background: linear-gradient(to right, #0073aa, #005f8d); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; text-align: center; transition: all 0.3s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">View Certificates</button>';
		$output .= '</form>';

		return $output;
	}

		return $output;
}
