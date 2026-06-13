<?php

/**
 * Helper: render an upgrade notice for gated bulk-import pages.
 */
function cg_bulk_import_plan_gate_notice(): bool {
	if ( ! class_exists( 'CG_License_Manager' ) || CG_License_Manager::is_pro() ) {
		return false; // allowed
	}
	// No wrapper <div class="wrap"> — the page renderer already provides it.
	echo '<div class="notice notice-warning" style="padding:16px;border-left:4px solid #f59e0b;">';
	echo '<h2 style="margin:0 0 8px;">&#x1F512; Pro Feature</h2>';
	echo '<p>Bulk CSV import requires the <strong>Pro</strong> or <strong>Business</strong> plan.</p>';
	echo '<p><a href="https://eshaanportfolio.vercel.app/" class="button button-primary" target="_blank">Upgrade Now &rarr;</a></p>';
	echo '</div>';
	return true; // blocked
}

function bulk_import_students() {
	if ( cg_bulk_import_plan_gate_notice() ) {
		return;
	}

	// Check for file upload and nonce validation
	if ( isset( $_POST['submit_students'] ) && isset( $_FILES['students_csv'] ) ) {
		if ( check_admin_referer( 'bulk_import_students_nonce', '_wpnonce_bulk_import' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
			}
			$file       = $_FILES['students_csv']['tmp_name'];
			$file_error = $_FILES['students_csv']['error'];

			// Error handling for file upload
			if ( $file_error !== UPLOAD_ERR_OK ) {
				echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
				return;
			}

			if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {
				// Read and strip BOM if present (UTF-8 BOM: EF BB BF)
				$bom = fread( $handle, 3 );
				if ( $bom !== "\xEF\xBB\xBF" ) {
					// No BOM found, rewind to beginning
					rewind( $handle );
				}

				// Read the first row as the header, skip empty lines
				$header = false;
				while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows (rows with only null or empty values)
					if ( array_filter(
						$row,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						$header = $row;
						break;
					}
				}

				if ( $header === false ) {
					echo '<div class="notice notice-error"><p>CSV file is empty or invalid.</p></div>';
					fclose( $handle );
					return;
				}

				// Trim whitespace and normalize header keys (preserve underscores — sanitize_key strips them!)
				$header      = array_map(
					function ( $val ) {
						return trim( (string) $val );
					},
					$header
				);
				$header_keys = array_map(
					function ( $key ) {
						return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
					},
					$header
				);

				// Check only for missing required fields — never reject extra columns
				$required_fields = array( 'student_name', 'email', 'school_name', 'issue_date', 'certificate_type' );
				$missing_fields  = array_diff( $required_fields, $header_keys );
				$extra_columns   = array_values( array_filter( array_diff( $header_keys, $required_fields ) ) );

				if ( ! empty( $missing_fields ) ) {
					$error_msg  = '<strong>Invalid CSV format.</strong><br><br>';
					$error_msg .= '<strong>Missing required fields:</strong> ' . implode( ', ', $missing_fields ) . '<br>';
					$error_msg .= '<br><strong>Required fields:</strong> ' . implode( ', ', $required_fields );
					echo '<div class="notice notice-error"><p>' . $error_msg . '</p></div>';
					fclose( $handle );
					return;
				}

				if ( ! empty( $extra_columns ) ) {
					echo '<div class="notice notice-info"><p>';
					echo '<strong>Extra fields detected:</strong> ' . esc_html( implode( ', ', $extra_columns ) );
					echo ' — these will be auto-registered for each row\'s certificate type.</p></div>';
				}

				// Process each row
				$imported_count      = 0;
				$registered_per_type = array(); // track [cert_type_key => [slugs]] to avoid redundant DB writes

				while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows
					if ( ! array_filter(
						$data,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						continue;
					}

					// Ensure data array has same number of elements as header
					$data = array_pad( $data, count( $header_keys ), '' );

					// Trim all data values
					$data = array_map(
						function ( $val ) {
							return trim( (string) $val );
						},
						$data
					);

					$student_data = array_combine( $header_keys, $data );
					$cert_type    = sanitize_text_field( $student_data['certificate_type'] );

					// Register extra fields for this certificate type (runs once per type+slug)
					if ( ! empty( $extra_columns ) && class_exists( 'CG_Field_Schema' ) && ! empty( $cert_type ) ) {
						$type_key = CG_Field_Schema::cert_type_to_key( $cert_type );
						foreach ( $extra_columns as $slug ) {
							if ( ! isset( $registered_per_type[ $type_key ][ $slug ] ) ) {
								$ok                                        = CG_Field_Schema::register_field( $cert_type, $slug );
								$registered_per_type[ $type_key ][ $slug ] = $ok ? 'registered' : 'data_only';
							}
						}
					}

					// Generate school abbreviation
					$school_name         = sanitize_text_field( $student_data['school_name'] );
					$words               = explode( ' ', $school_name );
					$school_abbreviation = '';
					foreach ( $words as $word ) {
						if ( ! empty( $word ) ) {
							$school_abbreviation .= strtoupper( $word[0] );
						}
					}
					if ( empty( $school_abbreviation ) ) {
						$school_abbreviation = 'UNK';
					}

					// Build extra fields from extra columns
					$extra_fields = array();
					if ( ! empty( $extra_columns ) ) {
						foreach ( $extra_columns as $slug ) {
							$val = $student_data[ $slug ] ?? '';
							if ( $val !== '' ) {
								$extra_fields[ $slug ] = sanitize_text_field( $val );
							}
						}
					}

					// Accept both 'phone' (SQL export) and 'phone_number' (legacy export)
					$phone = sanitize_text_field( $student_data['phone'] ?? $student_data['phone_number'] ?? '' );

					// Insert student directly into custom SQL table
					if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
						$tables        = \CertificateGenerator\Database\CustomTables::instance();
						$student_table = $tables->get_table( 'students' );
						$school_table  = $tables->get_table( 'schools' );

						// Ensure school exists in SQL table
						$school_id = $GLOBALS['wpdb']->get_var(
							$GLOBALS['wpdb']->prepare(
								"SELECT id FROM $school_table WHERE school_name = %s LIMIT 1",
								$school_name
							)
						);
						if ( ! $school_id ) {
							$GLOBALS['wpdb']->insert(
								$school_table,
								array(
									'school_name' => $school_name,
									'status'      => 'active',
									'created_at'  => current_time( 'mysql' ),
								)
							);
							$school_id = $GLOBALS['wpdb']->insert_id;
						}

						$_issue_raw    = sanitize_text_field( $student_data['issue_date'] );
						$_issue_stored = class_exists( '\CertificateGenerator\Helpers\DateHelper' )
							? ( \CertificateGenerator\Helpers\DateHelper::to_storage( $_issue_raw ) ?? $_issue_raw )
							: $_issue_raw;

						// send_email: optional CSV column. false/0/no → 0 (opt-out), anything else → 1 (default).
						$_se_raw    = strtolower( trim( $student_data['send_email'] ?? '1' ) );
						$send_email = in_array( $_se_raw, array( 'false', '0', 'no' ), true ) ? 0 : 1;

						$insert_data = array(
							'student_name'     => sanitize_text_field( $student_data['student_name'] ),
							'email'            => sanitize_email( $student_data['email'] ),
							'phone'            => $phone,
							'school_id'        => $school_id,
							'school_name'      => $school_name,
							'certificate_type' => $cert_type,
							'issue_date'       => $_issue_stored ?: null,
							'status'           => 'active',
							'send_email'       => $send_email,
							'created_at'       => current_time( 'mysql' ),
							'updated_at'       => current_time( 'mysql' ),
						);
						if ( ! empty( $extra_fields ) ) {
							$insert_data['extra_fields'] = wp_json_encode( $extra_fields );
						}

						// Upsert by email + student_name when email present; always insert when email empty.
						// Deduping on email alone fails when multiple students share a school/parent email.
						$existing  = null;
						$email_val = sanitize_email( $student_data['email'] );
						if ( $email_val !== '' ) {
							$existing = $GLOBALS['wpdb']->get_var(
								$GLOBALS['wpdb']->prepare(
									"SELECT id FROM $student_table WHERE email = %s AND student_name = %s LIMIT 1",
									$email_val,
									sanitize_text_field( $student_data['student_name'] )
								)
							);
						}
						if ( $existing ) {
							$GLOBALS['wpdb']->update( $student_table, $insert_data, array( 'id' => $existing ) );
						} else {
							$GLOBALS['wpdb']->insert( $student_table, $insert_data );
						}

						++$imported_count;
					} else {
						error_log( '[CertGen] bulk_import_students: CustomTables class not found — row skipped.' );
					}
				}
				fclose( $handle );

				$extra_note = '';
				if ( ! empty( $extra_columns ) && class_exists( 'CG_Field_Schema' ) ) {
					$registered_slugs = array();
					$data_only_slugs  = array();
					foreach ( $registered_per_type as $type_data ) {
						foreach ( $type_data as $slug => $status ) {
							if ( $status === 'registered' && ! in_array( $slug, $registered_slugs, true ) ) {
								$registered_slugs[] = $slug;
							} elseif ( $status === 'data_only' && ! in_array( $slug, $data_only_slugs, true ) ) {
								$data_only_slugs[] = $slug;
							}
						}
					}
					if ( ! empty( $registered_slugs ) ) {
						$extra_note .= ' Extra fields registered for template: ' . implode( ', ', $registered_slugs ) . '.';
					}
					if ( ! empty( $data_only_slugs ) ) {
						$extra_note .= ' Additional fields saved (data only, beyond template limit): ' . implode( ', ', $data_only_slugs ) . '.';
					}
				}
				echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' students!' . esc_html( $extra_note ) . '</p></div>';
				return; // Stop here — don't re-render the form after a successful import.
			} else {
				echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
			}
		}
	}

	// Display the import form
	echo '<div class="wrap">';
	echo '<h1>Bulk Import Students</h1>';
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field( 'bulk_import_students_nonce', '_wpnonce_bulk_import' );
	echo '<table class="form-table">';
	echo '<tr><th><label for="students_csv">Upload CSV File</label></th>';
	echo '<td><input type="file" name="students_csv" id="students_csv" accept=".csv" required /></td></tr>';
	echo '</table>';
	echo '<input type="submit" name="submit_students" value="Import Students" class="button button-primary" />';
	echo '</form>';
	echo '</div>';
}



function bulk_import_teachers() {
	if ( cg_bulk_import_plan_gate_notice() ) {
		return;
	}

	// Check for file upload and nonce validation
	if ( isset( $_POST['submit_teachers'] ) && isset( $_FILES['teachers_csv'] ) ) {
		if ( check_admin_referer( 'bulk_import_teachers_nonce', '_wpnonce_bulk_import' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
			}
			$file       = $_FILES['teachers_csv']['tmp_name'];
			$file_error = $_FILES['teachers_csv']['error'];

			// Error handling for file upload
			if ( $file_error !== UPLOAD_ERR_OK ) {
				echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
				return;
			}

			if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {
				// Read and strip BOM if present (UTF-8 BOM: EF BB BF)
				$bom = fread( $handle, 3 );
				if ( $bom !== "\xEF\xBB\xBF" ) {
					// No BOM found, rewind to beginning
					rewind( $handle );
				}

				// Read the first row as the header, skip empty lines
				$header = false;
				while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows (rows with only null or empty values)
					if ( array_filter(
						$row,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						$header = $row;
						break;
					}
				}

				if ( $header === false ) {
					echo '<div class="notice notice-error"><p>CSV file is empty or invalid.</p></div>';
					fclose( $handle );
					return;
				}

				// Trim whitespace from all header values, handle null values
				$header      = array_map(
					function ( $val ) {
						return trim( (string) $val );
					},
					$header
				);
				$header_keys = array_map(
					function ( $key ) {
						return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
					},
					$header
				);

				// Validate headers match the required fields
				$required_fields = array(
					'teacher_name',
					'email',
					'school_name',
					'issue_date',
					'certificate_type',
				);

				// Check only for missing required fields — never reject extra columns
				$missing_fields = array_diff( $required_fields, $header_keys );
				$extra_columns  = array_values( array_filter( array_diff( $header_keys, $required_fields ) ) );

				if ( ! empty( $missing_fields ) ) {
					$error_msg  = '<strong>Invalid CSV format.</strong><br><br>';
					$error_msg .= '<strong>Missing required fields:</strong> ' . implode( ', ', $missing_fields ) . '<br>';
					$error_msg .= '<br><strong>Required fields:</strong> ' . implode( ', ', $required_fields );
					echo '<div class="notice notice-error"><p>' . $error_msg . '</p></div>';
					fclose( $handle );
					return;
				}

				if ( ! empty( $extra_columns ) ) {
					echo '<div class="notice notice-info"><p>';
					echo '<strong>Extra fields detected:</strong> ' . esc_html( implode( ', ', $extra_columns ) );
					echo ' — these will be saved to extra_fields for each teacher.</p></div>';
				}

				// Process each row
				$imported_count = 0;
				while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows
					if ( ! array_filter(
						$data,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						continue;
					}

					$data         = array_pad( $data, count( $header_keys ), '' );
					$data         = array_map(
						function ( $val ) {
							return trim( (string) $val );
						},
						$data
					);
					$teacher_data = array_combine( $header_keys, $data );

					$school_name = sanitize_text_field( $teacher_data['school_name'] );
					$cert_type   = sanitize_text_field( $teacher_data['certificate_type'] );

					$_t_issue_raw    = sanitize_text_field( $teacher_data['issue_date'] );
					$_t_issue_stored = class_exists( '\CertificateGenerator\Helpers\DateHelper' )
						? ( \CertificateGenerator\Helpers\DateHelper::to_storage( $_t_issue_raw ) ?? $_t_issue_raw )
						: $_t_issue_raw;

					if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
						error_log( '[CertGen] bulk_import_teachers: CustomTables class not found — row skipped.' );
						continue;
					}

					$tables        = \CertificateGenerator\Database\CustomTables::instance();
					$teacher_table = $tables->get_table( 'teachers' );
					$school_table  = $tables->get_table( 'schools' );

					// Ensure school exists
					$school_id = $GLOBALS['wpdb']->get_var(
						$GLOBALS['wpdb']->prepare(
							"SELECT id FROM $school_table WHERE school_name = %s LIMIT 1",
							$school_name
						)
					);
					if ( ! $school_id ) {
						$GLOBALS['wpdb']->insert(
							$school_table,
							array(
								'school_name' => $school_name,
								'status'      => 'active',
								'created_at'  => current_time( 'mysql' ),
							)
						);
						$school_id = $GLOBALS['wpdb']->insert_id;
					}

					// Build extra fields from extra columns
					$extra_fields = array();
					if ( ! empty( $extra_columns ) ) {
						foreach ( $extra_columns as $slug ) {
							$val = $teacher_data[ $slug ] ?? '';
							if ( $val !== '' ) {
								$extra_fields[ $slug ] = sanitize_text_field( $val );
							}
						}
					}

					// Accept both 'phone' (SQL export) and 'phone_number' (legacy export)
					$phone = sanitize_text_field( $teacher_data['phone'] ?? $teacher_data['phone_number'] ?? '' );

					// send_email: optional CSV column. false/0/no → 0 (opt-out), anything else → 1 (default).
					$_se_raw_t    = strtolower( trim( $teacher_data['send_email'] ?? '1' ) );
					$send_email_t = in_array( $_se_raw_t, array( 'false', '0', 'no' ), true ) ? 0 : 1;

					$insert_data = array(
						'teacher_name'     => sanitize_text_field( $teacher_data['teacher_name'] ),
						'email'            => sanitize_email( $teacher_data['email'] ),
						'phone'            => $phone,
						'school_id'        => $school_id,
						'school_name'      => $school_name,
						'certificate_type' => $cert_type,
						'issue_date'       => $_t_issue_stored ?: null,
						'status'           => 'active',
						'send_email'       => $send_email_t,
						'created_at'       => current_time( 'mysql' ),
						'updated_at'       => current_time( 'mysql' ),
					);
					if ( ! empty( $extra_fields ) ) {
						$insert_data['extra_fields'] = wp_json_encode( $extra_fields );
					}

					// Upsert by email
					$existing = $GLOBALS['wpdb']->get_var(
						$GLOBALS['wpdb']->prepare(
							"SELECT id FROM $teacher_table WHERE email = %s LIMIT 1",
							sanitize_email( $teacher_data['email'] )
						)
					);
					if ( $existing ) {
						$GLOBALS['wpdb']->update( $teacher_table, $insert_data, array( 'id' => $existing ) );
					} else {
						$GLOBALS['wpdb']->insert( $teacher_table, $insert_data );
					}

					++$imported_count;
				}
				fclose( $handle );

				echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' teachers!</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
			}
		}

		// Prevent duplicate processing of the request
		exit;
	}

	// Display the import form
	echo '<div class="wrap">';
	echo '<h1>Bulk Import Teachers</h1>';
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field( 'bulk_import_teachers_nonce', '_wpnonce_bulk_import' );
	echo '<table class="form-table">';
	echo '<tr><th><label for="teachers_csv">Upload CSV File</label></th>';
	echo '<td><input type="file" name="teachers_csv" id="teachers_csv" accept=".csv" required /></td></tr>';
	echo '</table>';
	echo '<input type="submit" name="submit_teachers" value="Import Teachers" class="button button-primary" />';
	echo '</form>';
	echo '</div>';
}




function bulk_import_schools() {
	if ( cg_bulk_import_plan_gate_notice() ) {
		return;
	}

	// Check for file upload and nonce validation
	if ( isset( $_POST['submit_schools'] ) && isset( $_FILES['schools_csv'] ) ) {
		if ( check_admin_referer( 'bulk_import_schools_nonce', '_wpnonce_bulk_import' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
			}
			$file       = $_FILES['schools_csv']['tmp_name'];
			$file_error = $_FILES['schools_csv']['error'];

			// Error handling for file upload
			if ( $file_error !== UPLOAD_ERR_OK ) {
				echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
				return;
			}

			if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {
				// Read and strip BOM if present (UTF-8 BOM: EF BB BF)
				$bom = fread( $handle, 3 );
				if ( $bom !== "\xEF\xBB\xBF" ) {
					// No BOM found, rewind to beginning
					rewind( $handle );
				}

				// Read the first row as the header, skip empty lines
				$header = false;
				while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows (rows with only null or empty values)
					if ( array_filter(
						$row,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						$header = $row;
						break;
					}
				}

				if ( $header === false ) {
					echo '<div class="notice notice-error"><p>CSV file is empty or invalid.</p></div>';
					fclose( $handle );
					return;
				}

				// Trim whitespace from all header values, handle null values
				$header      = array_map(
					function ( $val ) {
						return trim( (string) $val );
					},
					$header
				);
				$header_keys = array_map(
					function ( $key ) {
						return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) );
					},
					$header
				);

				// Validate headers — accept 'place' (current export) or 'city' (old SQL export) for the city field
				$has_place = in_array( 'place', $header_keys, true );
				$has_city  = in_array( 'city', $header_keys, true );
				$required_fields = array(
					'school_name',
					( $has_place || ! $has_city ) ? 'place' : 'city',
					'issue_date',
					'certificate_type',
				);

				// Check only for missing required fields — never reject extra columns
				// Also treat the unused city/place alias as an allowed extra.
				$known_optional  = array( 'status', 'send_email', $has_place ? 'city' : 'place' );
				$missing_fields  = array_diff( $required_fields, $header_keys );
				$extra_columns   = array_values( array_filter( array_diff( $header_keys, array_merge( $required_fields, $known_optional ) ) ) );

				if ( ! empty( $missing_fields ) ) {
					$error_msg  = '<strong>Invalid CSV format.</strong><br><br>';
					$error_msg .= '<strong>Missing required fields:</strong> ' . implode( ', ', $missing_fields ) . '<br>';
					$error_msg .= '<br><strong>Required fields:</strong> ' . implode( ', ', $required_fields );
					echo '<div class="notice notice-error"><p>' . $error_msg . '</p></div>';
					fclose( $handle );
					return;
				}

				if ( ! empty( $extra_columns ) ) {
					echo '<div class="notice notice-info"><p>';
					echo '<strong>Extra fields detected:</strong> ' . esc_html( implode( ', ', $extra_columns ) );
					echo ' — these will be saved to extra_fields for each school.</p></div>';
				}

				// Process each row
				$imported_count = 0;
				while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows
					if ( ! array_filter(
						$data,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						continue;
					}

					$data        = array_pad( $data, count( $header_keys ), '' );
					$data        = array_map(
						function ( $val ) {
							return trim( (string) $val );
						},
						$data
					);
					$school_data = array_combine( $header_keys, $data );

					$school_name = sanitize_text_field( $school_data['school_name'] );
					$cert_type   = sanitize_text_field( $school_data['certificate_type'] );

					$_s_issue_raw    = sanitize_text_field( $school_data['issue_date'] );
					$_s_issue_stored = class_exists( '\CertificateGenerator\Helpers\DateHelper' )
						? ( \CertificateGenerator\Helpers\DateHelper::to_storage( $_s_issue_raw ) ?? $_s_issue_raw )
						: $_s_issue_raw;

					if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
						error_log( '[CertGen] bulk_import_schools: CustomTables class not found — row skipped.' );
						continue;
					}

					$tables       = \CertificateGenerator\Database\CustomTables::instance();
					$school_table = $tables->get_table( 'schools' );

					// Build extra fields from extra columns
					$extra_fields = array();
					if ( ! empty( $extra_columns ) ) {
						foreach ( $extra_columns as $slug ) {
							$val = $school_data[ $slug ] ?? '';
							if ( $val !== '' ) {
								$extra_fields[ $slug ] = sanitize_text_field( $val );
							}
						}
					}

					// send_email: optional CSV column. false/0/no → 0 (opt-out), anything else → 1 (default).
					$_se_raw_s    = strtolower( trim( $school_data['send_email'] ?? '1' ) );
					$send_email_s = in_array( $_se_raw_s, array( 'false', '0', 'no' ), true ) ? 0 : 1;

					$insert_data = array(
						'school_name'      => $school_name,
						'city'             => sanitize_text_field( $school_data['place'] ?? $school_data['city'] ?? '' ),
						'certificate_type' => $cert_type,
						'issue_date'       => $_s_issue_stored ?: null,
						'status'           => 'active',
						'send_email'       => $send_email_s,
						'created_at'       => current_time( 'mysql' ),
						'updated_at'       => current_time( 'mysql' ),
					);
					if ( ! empty( $extra_fields ) ) {
						$insert_data['extra_fields'] = wp_json_encode( $extra_fields );
					}

					// Upsert by school_name
					$existing = $GLOBALS['wpdb']->get_var(
						$GLOBALS['wpdb']->prepare(
							"SELECT id FROM $school_table WHERE school_name = %s LIMIT 1",
							$school_name
						)
					);
					if ( $existing ) {
						$GLOBALS['wpdb']->update( $school_table, $insert_data, array( 'id' => $existing ) );
					} else {
						$GLOBALS['wpdb']->insert( $school_table, $insert_data );
					}

					++$imported_count;
				}
				fclose( $handle );

				echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' schools!</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
			}
		}

		// Prevent duplicate processing of the request
		exit;
	}

	// Display the import form
	echo '<div class="wrap">';
	echo '<h1>Bulk Import Schools</h1>';
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field( 'bulk_import_schools_nonce', '_wpnonce_bulk_import' );
	echo '<table class="form-table">';
	echo '<tr><th><label for="schools_csv">Upload CSV File</label></th>';
	echo '<td><input type="file" name="schools_csv" id="schools_csv" accept=".csv" required /></td></tr>';
	echo '</table>';
	echo '<input type="submit" name="submit_schools" value="Import Schools" class="button button-primary" />';
	echo '</form>';
	echo '</div>';
}




function bulk_import_certificates() {
	if ( cg_bulk_import_plan_gate_notice() ) {
		return;
	}

	// Check for file upload and nonce validation
	if ( isset( $_POST['submit_certificates'] ) && isset( $_FILES['certificates_csv'] ) ) {
		if ( check_admin_referer( 'bulk_import_certificates_nonce', '_wpnonce_bulk_import' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
			}
			$file       = $_FILES['certificates_csv']['tmp_name'];
			$file_error = $_FILES['certificates_csv']['error'];

			// Error handling for file upload
			if ( $file_error !== UPLOAD_ERR_OK ) {
				echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
				return;
			}

			if ( ( $handle = fopen( $file, 'r' ) ) !== false ) {
				// Read and strip BOM if present (UTF-8 BOM: EF BB BF)
				$bom = fread( $handle, 3 );
				if ( $bom !== "\xEF\xBB\xBF" ) {
					// No BOM found, rewind to beginning
					rewind( $handle );
				}

				// Read the first row as the header, skip empty lines
				$header = false;
				while ( ( $row = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows (rows with only null or empty values)
					if ( array_filter(
						$row,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						$header = $row;
						break;
					}
				}

				if ( $header === false ) {
					echo '<div class="notice notice-error"><p>CSV file is empty or invalid.</p></div>';
					fclose( $handle );
					return;
				}

				// Trim whitespace from all header values, handle null values
				$header = array_map(
					function ( $val ) {
						return trim( (string) $val );
					},
					$header
				);

				// Validate headers match the required fields
				// Accept both 'orientation' (export format) and 'template_orientation' (legacy)
				$has_orientation          = in_array( 'orientation', $header, true );
				$has_template_orientation = in_array( 'template_orientation', $header, true );
				$required_fields          = array(
					'certificate_type',
					'event_date',           // Y-m-d or empty — used for date-based template matching
					'template_url',
					$has_orientation && ! $has_template_orientation ? 'orientation' : 'template_orientation',
					'font_size',
					'font_color',
					'font_style',
					'field_1_position_x',
					'field_1_position_y',
					'field_1_visible',
					'field_1_width',
					'field_1_alignment',
					'field_2_position_x',
					'field_2_position_y',
					'field_2_visible',
					'field_2_width',
					'field_2_alignment',
					'field_3_position_x',
					'field_3_position_y',
					'field_3_visible',
					'field_3_width',
					'field_3_alignment',
				);

				// Build allowed optional columns: export-format extras + template_field_count + field_4..MAX_FIELDS slots
				$max_fields             = class_exists( 'CG_Field_Schema' ) ? CG_Field_Schema::MAX_FIELDS : 15;
				$optional_field_columns = array(
					'template_field_count',
					'template_name',
					'page_size',
					'qr_enabled',
					'serial_number_display',
					'status',
					// Accept whichever orientation alias wasn't chosen as required
					$has_orientation && ! $has_template_orientation ? 'template_orientation' : 'orientation',
				);
				for ( $i = 4; $i <= $max_fields; $i++ ) {
					foreach ( array( 'position_x', 'position_y', 'visible', 'width', 'alignment' ) as $prop ) {
						$optional_field_columns[] = "field_{$i}_{$prop}";
					}
				}

				// Only error on missing required fields; extra field_N columns are allowed
				$missing_fields = array_diff( $required_fields, $header );
				$extra_fields   = array_diff( $header, array_merge( $required_fields, $optional_field_columns ) );

				if ( ! empty( $missing_fields ) || ! empty( $extra_fields ) ) {
					$error_msg = '<strong>Invalid CSV format.</strong><br><br>';
					if ( ! empty( $missing_fields ) ) {
						$error_msg .= '<strong>Missing fields:</strong> ' . implode( ', ', $missing_fields ) . '<br>';
					}
					if ( ! empty( $extra_fields ) ) {
						$error_msg .= '<strong>Extra/incorrect fields:</strong> ' . implode( ', ', $extra_fields ) . '<br>';
					}
					$error_msg .= '<br><strong>Required fields:</strong> ' . implode( ', ', $required_fields );
					echo '<div class="notice notice-error"><p>' . $error_msg . '</p></div>';
					fclose( $handle );
					return;
				}

				// Resolve SQL table
				global $wpdb;
				$tpl_table = null;
				$use_sql   = false;
				if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
					$tpl_table = \CertificateGenerator\Database\CustomTables::instance()->get_table( 'certificate_templates' );
					$use_sql   = $tpl_table
						&& $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tpl_table ) ) === $tpl_table;
				}

				$max_fields_cnt = class_exists( 'CG_Field_Schema' ) ? CG_Field_Schema::MAX_FIELDS : 15;

				// Process each row
				$imported_count = 0;
				while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
					// Skip empty rows
					if ( ! array_filter(
						$data,
						function ( $val ) {
							return $val !== null && $val !== ''; }
					) ) {
						continue;
					}

					// Ensure data array has same number of elements as header
					$data = array_pad( $data, count( $header ), '' );

					// Trim all data values
					$data = array_map(
						function ( $val ) {
							return trim( (string) $val );
						},
						$data
					);

					$certificate_data = array_combine( $header, $data );

					// ── Sanitise core fields ──────────────────────────────────
					$certificate_type = sanitize_text_field( $certificate_data['certificate_type'] ?? '' );
					if ( empty( $certificate_type ) ) {
						continue;
					}

					$template_url = esc_url_raw( $certificate_data['template_url'] ?? '' );

					// Validate / normalise event_date (Y-m-d or empty)
					$event_date = '';
					$raw_date   = sanitize_text_field( $certificate_data['event_date'] ?? '' );
					if ( $raw_date !== '' ) {
						if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date )
							&& ( $parsed = DateTime::createFromFormat( 'Y-m-d', $raw_date ) )
							&& $parsed->format( 'Y-m-d' ) === $raw_date
						) {
							$event_date = $raw_date;
						}
						// d-m-Y fallback (from older exports)
						elseif ( $parsed = DateTime::createFromFormat( 'd-m-Y', $raw_date ) ) {
							$event_date = $parsed->format( 'Y-m-d' );
						}
					}

					// ── Build extra_fields JSON: field positions + template_field_count ──
					$extra_fields_json = array();

					// Derive template_field_count: use explicit column if present, else auto-detect
					$tfc = (int) ( $certificate_data['template_field_count'] ?? 0 );
					if ( $tfc < 1 ) {
						for ( $i = $max_fields_cnt; $i >= 1; $i-- ) {
							foreach ( array( 'position_x', 'position_y', 'width', 'alignment' ) as $prop ) {
								if ( ! empty( $certificate_data[ "field_{$i}_{$prop}" ] ?? '' ) ) {
									$tfc = $i;
									break 2;
								}
							}
						}
					}
					$extra_fields_json['template_field_count'] = max( 2, $tfc ?: 3 );

					for ( $i = 1; $i <= $max_fields_cnt; $i++ ) {
						$extra_fields_json[ "field_{$i}_position_x" ] = floatval( $certificate_data[ "field_{$i}_position_x" ] ?? 0 );
						$extra_fields_json[ "field_{$i}_position_y" ] = floatval( $certificate_data[ "field_{$i}_position_y" ] ?? 0 );
						$extra_fields_json[ "field_{$i}_visible" ]    = ( $certificate_data[ "field_{$i}_visible" ] ?? '0' ) ? '1' : '0';
						$extra_fields_json[ "field_{$i}_width" ]      = floatval( $certificate_data[ "field_{$i}_width" ] ?? 100 );
						$alignment                                    = strtoupper( sanitize_key( $certificate_data[ "field_{$i}_alignment" ] ?? 'C' ) );
						$extra_fields_json[ "field_{$i}_alignment" ]  = in_array( $alignment, array( 'L', 'C', 'R' ), true ) ? $alignment : 'C';
					}

					// ── Write to SQL table (primary) ──────────────────────────
					if ( $use_sql ) {
						// Skip duplicate: same certificate_type + event_date already exists
						$dup_check_sql = "SELECT id FROM $tpl_table WHERE certificate_type = %s AND event_date " .
							( $event_date ? '= %s' : 'IS NULL' );
						$dup_args      = $event_date
							? array( $certificate_type, $event_date )
							: array( $certificate_type );
						if ( $wpdb->get_var( $wpdb->prepare( $dup_check_sql, ...$dup_args ) ) ) {
							continue; // Skip duplicate
						}

						$orientation = sanitize_key( $certificate_data['template_orientation'] ?? $certificate_data['orientation'] ?? 'landscape' );
						if ( ! in_array( $orientation, array( 'portrait', 'landscape' ), true ) ) {
							$orientation = 'landscape';
						}

						$page_size_raw = $certificate_data['page_size'] ?? 'A4';
						$page_size     = in_array( $page_size_raw, array( 'A4', 'Letter', 'Legal', 'Custom' ), true ) ? $page_size_raw : 'A4';

						$qr_raw              = strtolower( trim( $certificate_data['qr_enabled'] ?? '0' ) );
						$qr_enabled          = ! in_array( $qr_raw, array( '', '0', 'false', 'no' ), true ) ? 1 : 0;
						$sn_raw              = strtolower( trim( $certificate_data['serial_number_display'] ?? '0' ) );
						$serial_number_disp  = ! in_array( $sn_raw, array( '', '0', 'false', 'no' ), true ) ? 1 : 0;

						$host          = $template_url ? parse_url( $template_url, PHP_URL_HOST ) : null;
						$date_suffix   = $event_date ? ' (' . $event_date . ')' : '';
						$auto_name     = ( $certificate_type ?: 'Certificate' ) . $date_suffix . ( $host ? ' — ' . $host : '' );
						$template_name = sanitize_text_field( $certificate_data['template_name'] ?? '' ) ?: $auto_name;

						$wpdb->insert(
							$tpl_table,
							array(
								'template_name'         => $template_name,
								'certificate_type'      => $certificate_type,
								'event_date'            => $event_date ?: null,
								'template_url'          => $template_url,
								'orientation'           => $orientation,
								'page_size'             => $page_size,
								'font_size'             => absint( $certificate_data['font_size'] ?? 12 ),
								'font_color'            => sanitize_hex_color( $certificate_data['font_color'] ?? '#000000' ) ?: '#000000',
								'font_style'            => sanitize_key( $certificate_data['font_style'] ?? 'helvetica' ),
								'qr_enabled'            => $qr_enabled,
								'serial_number_display' => $serial_number_disp,
								'status'                => 'published',
								'extra_fields'          => wp_json_encode( $extra_fields_json ),
								'created_at'            => current_time( 'mysql' ),
								'updated_at'            => current_time( 'mysql' ),
							)
						);

						++$imported_count;

					} else {
						// ── CPT fallback when SQL table unavailable ───────────
						$existing_query = new WP_Query(
							array(
								'post_type'      => 'certificates',
								'meta_query'     => array(
									array(
										'key'   => 'certificate_type',
										'value' => $certificate_type,
									),
									array(
										'key'   => 'event_date',
										'value' => $event_date,
									),
								),
								'posts_per_page' => 1,
								'fields'         => 'ids',
							)
						);
						if ( $existing_query->have_posts() ) {
							continue;
						}

						$post_id = wp_insert_post(
							array(
								'post_type'   => 'certificates',
								'post_status' => 'publish',
							)
						);
						if ( ! $post_id ) {
							continue;
						}

						// Flat post meta (legacy format)
						foreach ( $extra_fields_json as $mk => $mv ) {
							update_post_meta( $post_id, $mk, $mv );
						}
						update_post_meta( $post_id, 'certificate_type', $certificate_type );
						update_post_meta( $post_id, 'event_date', $event_date );
						update_post_meta( $post_id, 'template_url', $template_url );
						update_post_meta( $post_id, 'template_orientation', sanitize_key( $certificate_data['template_orientation'] ?? $certificate_data['orientation'] ?? 'landscape' ) );
						update_post_meta( $post_id, 'font_size', absint( $certificate_data['font_size'] ?? 12 ) );
						update_post_meta( $post_id, 'font_color', sanitize_hex_color( $certificate_data['font_color'] ?? '#000000' ) ?: '#000000' );
						update_post_meta( $post_id, 'font_style', sanitize_key( $certificate_data['font_style'] ?? 'helvetica' ) );

						$host = $template_url ? parse_url( $template_url, PHP_URL_HOST ) : null;
						wp_update_post(
							array(
								'ID'         => $post_id,
								'post_title' => ( $certificate_type ?: 'Certificate' ) . ( $event_date ? ' (' . $event_date . ')' : '' ) . ( $host ? ' - ' . $host : '' ),
							)
						);

						++$imported_count;
					}
				}
				fclose( $handle );

				echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' certificate templates!</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
			}
		}

		// Prevent duplicate processing of the request
		exit;
	}

	// Display the import form
	echo '<div class="wrap">';
	echo '<h1>Bulk Import Certificates</h1>';
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field( 'bulk_import_certificates_nonce', '_wpnonce_bulk_import' );
	echo '<table class="form-table">';
	echo '<tr><th><label for="certificates_csv">Upload CSV File</label></th>';
	echo '<td><input type="file" name="certificates_csv" id="certificates_csv" accept=".csv" required /></td></tr>';
	echo '</table>';
	echo '<input type="submit" name="submit_certificates" value="Import Certificates" class="button button-primary" />';
	echo '</form>';
	echo '</div>';
}
