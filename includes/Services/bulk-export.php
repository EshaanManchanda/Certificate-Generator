<?php
/**
 * Render a compact Year / School / Cert-type / Date-range filter bar for export pages.
 * Echoes HTML form fields only — caller wraps in <form>.
 */
function cg_render_export_filter_fields() {
	$years      = function_exists( 'certificate_generator_get_unique_years' ) ? certificate_generator_get_unique_years() : array();
	$schools    = function_exists( 'certificate_generator_get_unique_schools' ) ? certificate_generator_get_unique_schools() : array();
	$cert_types = function_exists( 'certificate_generator_get_unique_certificate_types' ) ? certificate_generator_get_unique_certificate_types() : array();
	?>
	<table class="form-table" style="max-width:700px;">
		<tr>
			<th><label><?php esc_html_e( 'Year', 'certificate-generator' ); ?></label></th>
			<td>
				<select name="filter_year[]" multiple size="3" style="min-width:120px;">
					<?php foreach ( $years as $y ) : ?>
						<option value="<?php echo esc_attr( $y ); ?>"><?php echo esc_html( $y ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple. Leave empty for all years.', 'certificate-generator' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label><?php esc_html_e( 'School', 'certificate-generator' ); ?></label></th>
			<td>
				<select name="filter_school[]" multiple size="3" style="min-width:200px;">
					<?php foreach ( $schools as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Leave empty for all schools.', 'certificate-generator' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label><?php esc_html_e( 'Certificate Type', 'certificate-generator' ); ?></label></th>
			<td>
				<select name="filter_cert_type[]" multiple size="3" style="min-width:200px;">
					<?php foreach ( $cert_types as $c ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Leave empty for all types.', 'certificate-generator' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label><?php esc_html_e( 'Issue Date From', 'certificate-generator' ); ?></label></th>
			<td><input type="date" name="filter_date_from" value="" style="width:150px;"></td>
		</tr>
		<tr>
			<th><label><?php esc_html_e( 'Issue Date To', 'certificate-generator' ); ?></label></th>
			<td><input type="date" name="filter_date_to" value="" style="width:150px;"></td>
		</tr>
	</table>
	<?php
}

/**
 * Build SQL WHERE + params from export filter POST fields using cg_build_recipient_filter_sql.
 * Returns [ $where_sql_suffix, $params[] ] where $where_sql_suffix is either '' or ' WHERE ...'.
 */
function cg_export_filter_where( string $alias = '' ): array {
	$filters = array(
		'schools'           => isset( $_POST['filter_school'] ) && is_array( $_POST['filter_school'] )
			? array_map( 'sanitize_text_field', $_POST['filter_school'] )
			: array(),
		'certificate_types' => isset( $_POST['filter_cert_type'] ) && is_array( $_POST['filter_cert_type'] )
			? array_map( 'sanitize_text_field', $_POST['filter_cert_type'] )
			: array(),
		'year'              => isset( $_POST['filter_year'] ) && is_array( $_POST['filter_year'] )
			? array_map( 'intval', $_POST['filter_year'] )
			: array(),
		'date_from'         => isset( $_POST['filter_date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['filter_date_from'] )
			? sanitize_text_field( $_POST['filter_date_from'] )
			: '',
		'date_to'           => isset( $_POST['filter_date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_POST['filter_date_to'] )
			? sanitize_text_field( $_POST['filter_date_to'] )
			: '',
		'email_search'      => '',
		'emails'            => array(),
	);

	if ( ! function_exists( 'cg_build_recipient_filter_sql' ) ) {
		return array( '', array() );
	}

	[ $where_fragments, $params ] = cg_build_recipient_filter_sql( $filters, $alias );

	if ( empty( $where_fragments ) ) {
		return array( '', array() );
	}

	return array( ' WHERE ' . implode( ' AND ', $where_fragments ), $params );
}

// Render the Export Students Page
function render_bulk_export_students_page() {
	echo '<div class="wrap">';
	echo '<h1>Export Students</h1>';
	echo '<form method="post">';
	echo wp_nonce_field( 'cg_export_students', '_wpnonce_cg_export', true, false );
	echo '<input type="hidden" name="export_students" value="1" />';
	cg_render_export_filter_fields();
	echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
	echo '</form>';
	echo '</div>';
}

// Handle CSV Export
function bulk_export_students() {
	if ( isset( $_POST['export_students'] ) ) {
		check_admin_referer( 'cg_export_students', '_wpnonce_cg_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
		}
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Try SQL tables first, fall back to CPTs
		if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			$tables        = \CertificateGenerator\Database\CustomTables::instance();
			$student_table = $tables->get_table( 'students' );
			$table_exists  = $GLOBALS['wpdb']->get_var(
				$GLOBALS['wpdb']->prepare(
					'SHOW TABLES LIKE %s',
					$student_table
				)
			) === $student_table;

			if ( $table_exists ) {
				// Export from SQL tables (with optional filters)
				[ $where_sql, $where_params ] = cg_export_filter_where();
				$sql                          = "SELECT * FROM $student_table" . $where_sql . ' ORDER BY id ASC'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$rows                         = empty( $where_params )
					? $GLOBALS['wpdb']->get_results( $sql, ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					: $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( $sql, ...$where_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( empty( $rows ) ) {
					add_action(
						'admin_notices',
						function () {
							echo '<div class="notice notice-warning"><p>No students found for export.</p></div>';
						}
					);
					return;
				}

				// Discover extra field keys from JSON
				$extra_keys = array();
				foreach ( $rows as $row ) {
					if ( ! empty( $row['extra_fields'] ) ) {
						$extra = json_decode( $row['extra_fields'], true );
						if ( is_array( $extra ) ) {
							$extra_keys = array_merge( $extra_keys, array_keys( $extra ) );
						}
					}
				}
				$extra_keys = array_values( array_unique( $extra_keys ) );

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=students_export.csv' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$output  = fopen( 'php://output', 'w' );
				$headers = array( 'student_name', 'email', 'phone', 'school_name', 'certificate_type', 'issue_date', 'year', 'status', 'send_email' );
				$headers = array_merge( $headers, $extra_keys );
				fputcsv( $output, $headers );

				foreach ( $rows as $row ) {
					$extra = ! empty( $row['extra_fields'] ) ? json_decode( $row['extra_fields'], true ) : array();
					if ( ! is_array( $extra ) ) {
						$extra = array();
					}
					$csv_row = array(
						$row['student_name'],
						$row['email'],
						$row['phone'] ?? '',
						$row['school_name'],
						$row['certificate_type'] ?? '',
						$row['issue_date'] ?? '',
						$row['year'] ?? '',
						$row['status'] ?? 'active',
						isset( $row['send_email'] ) && ! $row['send_email'] ? 'false' : 'true',
					);
					foreach ( $extra_keys as $key ) {
						$csv_row[] = $extra[ $key ] ?? '';
					}
					fputcsv( $output, $csv_row );
				}

				fclose( $output );
				exit;
			}
		}

		// Fallback: CPT-based export
		$args     = array(
			'post_type'   => 'students',
			'post_status' => 'publish',
			'numberposts' => -1,
		);
		$students = get_posts( $args );

		if ( empty( $students ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-warning"><p>No students found for export.</p></div>';
				}
			);
			return;
		}

		$extra_slugs = array();
		if ( class_exists( 'CG_Field_Schema' ) ) {
			$seen_types = array();
			foreach ( $students as $student ) {
				$cert_type = get_post_meta( $student->ID, 'certificate_type', true );
				$type_key  = CG_Field_Schema::cert_type_to_key( $cert_type );
				if ( $cert_type && ! isset( $seen_types[ $type_key ] ) ) {
					$seen_types[ $type_key ] = true;
					foreach ( CG_Field_Schema::get_extra_fields( $cert_type ) as $slug ) {
						if ( ! in_array( $slug, $extra_slugs, true ) ) {
							$extra_slugs[] = $slug;
						}
					}
				}
			}
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=students_export.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output  = fopen( 'php://output', 'w' );
		$headers = array_merge(
			array( 'student_name', 'email', 'phone', 'school_name', 'certificate_type', 'issue_date', 'status', 'send_email' ),
			$extra_slugs
		);
		fputcsv( $output, $headers );

		foreach ( $students as $student ) {
			$send = get_post_meta( $student->ID, 'send_email', true );
			$row  = array(
				get_post_meta( $student->ID, 'student_name', true ),
				get_post_meta( $student->ID, 'email', true ),
				get_post_meta( $student->ID, 'phone', true ),
				get_post_meta( $student->ID, 'school_name', true ),
				get_post_meta( $student->ID, 'certificate_type', true ),
				get_post_meta( $student->ID, 'issue_date', true ),
				get_post_meta( $student->ID, 'status', true ) ?: 'active',
				( $send !== '' && ! $send ) ? 'false' : 'true',
			);
			foreach ( $extra_slugs as $slug ) {
				$row[] = get_post_meta( $student->ID, $slug, true );
			}
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
add_action( 'admin_init', 'bulk_export_students' );



// Render the Export Schools Page
function render_bulk_export_schools_page() {
	echo '<div class="wrap">';
	echo '<h1>Export Schools</h1>';
	echo '<form method="post">';
	echo wp_nonce_field( 'cg_export_schools', '_wpnonce_cg_export', true, false );
	echo '<input type="hidden" name="export_schools" value="1" />';
	cg_render_export_filter_fields();
	echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
	echo '</form>';
	echo '</div>';
}

// Handle CSV Export for Schools
function bulk_export_schools() {
	if ( isset( $_POST['export_schools'] ) ) {
		check_admin_referer( 'cg_export_schools', '_wpnonce_cg_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
		}
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Try SQL tables first, fall back to CPTs
		if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			$tables       = \CertificateGenerator\Database\CustomTables::instance();
			$school_table = $tables->get_table( 'schools' );
			$table_exists = $GLOBALS['wpdb']->get_var(
				$GLOBALS['wpdb']->prepare(
					'SHOW TABLES LIKE %s',
					$school_table
				)
			) === $school_table;

			if ( $table_exists ) {
				[ $where_sql, $where_params ] = cg_export_filter_where();
				$sql                          = "SELECT * FROM $school_table" . $where_sql . ' ORDER BY id ASC'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$rows                         = empty( $where_params )
					? $GLOBALS['wpdb']->get_results( $sql, ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					: $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( $sql, ...$where_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( empty( $rows ) ) {
					add_action(
						'admin_notices',
						function () {
							echo '<div class="notice notice-warning"><p>No schools found for export.</p></div>';
						}
					);
					return;
				}

				$extra_keys = array();
				foreach ( $rows as $row ) {
					if ( ! empty( $row['extra_fields'] ) ) {
						$extra = json_decode( $row['extra_fields'], true );
						if ( is_array( $extra ) ) {
							$extra_keys = array_merge( $extra_keys, array_keys( $extra ) );
						}
					}
				}
				$extra_keys = array_values( array_unique( $extra_keys ) );

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=schools_export.csv' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$output  = fopen( 'php://output', 'w' );
				$headers = array( 'school_name', 'place', 'certificate_type', 'issue_date', 'year', 'status', 'send_email' );
				$headers = array_merge( $headers, $extra_keys );
				fputcsv( $output, $headers );

				foreach ( $rows as $row ) {
					$extra = ! empty( $row['extra_fields'] ) ? json_decode( $row['extra_fields'], true ) : array();
					if ( ! is_array( $extra ) ) {
						$extra = array();
					}
					$csv_row = array(
						$row['school_name'],
						$row['city'] ?? '',
						$row['certificate_type'] ?? '',
						$row['issue_date'] ?? '',
						$row['year'] ?? '',
						$row['status'] ?? 'active',
						isset( $row['send_email'] ) && ! $row['send_email'] ? 'false' : 'true',
					);
					foreach ( $extra_keys as $key ) {
						$csv_row[] = $extra[ $key ] ?? '';
					}
					fputcsv( $output, $csv_row );
				}

				fclose( $output );
				exit;
			}
		}

		// Fallback: CPT-based export
		$args    = array(
			'post_type'   => 'schools',
			'post_status' => 'publish',
			'numberposts' => -1,
		);
		$schools = get_posts( $args );

		if ( empty( $schools ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-warning"><p>No schools found for export.</p></div>';
				}
			);
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=schools_export.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output  = fopen( 'php://output', 'w' );
		$headers = array( 'school_name', 'place', 'certificate_type', 'issue_date', 'status', 'send_email' );
		fputcsv( $output, $headers );

		foreach ( $schools as $school ) {
			$send = get_post_meta( $school->ID, 'send_email', true );
			$row  = array(
				get_post_meta( $school->ID, 'school_name', true ),
				get_post_meta( $school->ID, 'place', true ),
				get_post_meta( $school->ID, 'certificate_type', true ),
				get_post_meta( $school->ID, 'issue_date', true ),
				get_post_meta( $school->ID, 'status', true ) ?: 'active',
				( $send !== '' && ! $send ) ? 'false' : 'true',
			);
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
add_action( 'admin_init', 'bulk_export_schools' );



// Render the Export Teachers Page
function render_bulk_export_teachers_page() {
	echo '<div class="wrap">';
	echo '<h1>Export Teachers</h1>';
	echo '<form method="post">';
	echo wp_nonce_field( 'cg_export_teachers', '_wpnonce_cg_export', true, false );
	echo '<input type="hidden" name="export_teachers" value="1" />';
	cg_render_export_filter_fields();
	echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
	echo '</form>';
	echo '</div>';
}

// Handle CSV Export for Teachers
function bulk_export_teachers() {
	if ( isset( $_POST['export_teachers'] ) ) {
		check_admin_referer( 'cg_export_teachers', '_wpnonce_cg_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
		}
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Try SQL tables first, fall back to CPTs
		if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			$tables        = \CertificateGenerator\Database\CustomTables::instance();
			$teacher_table = $tables->get_table( 'teachers' );
			$table_exists  = $GLOBALS['wpdb']->get_var(
				$GLOBALS['wpdb']->prepare(
					'SHOW TABLES LIKE %s',
					$teacher_table
				)
			) === $teacher_table;

			if ( $table_exists ) {
				[ $where_sql, $where_params ] = cg_export_filter_where();
				$sql                          = "SELECT * FROM $teacher_table" . $where_sql . ' ORDER BY id ASC'; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$rows                         = empty( $where_params )
					? $GLOBALS['wpdb']->get_results( $sql, ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					: $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( $sql, ...$where_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( empty( $rows ) ) {
					add_action(
						'admin_notices',
						function () {
							echo '<div class="notice notice-warning"><p>No teachers found for export.</p></div>';
						}
					);
					return;
				}

				$extra_keys = array();
				foreach ( $rows as $row ) {
					if ( ! empty( $row['extra_fields'] ) ) {
						$extra = json_decode( $row['extra_fields'], true );
						if ( is_array( $extra ) ) {
							$extra_keys = array_merge( $extra_keys, array_keys( $extra ) );
						}
					}
				}
				$extra_keys = array_values( array_unique( $extra_keys ) );

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=teachers_export.csv' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$output  = fopen( 'php://output', 'w' );
				$headers = array( 'teacher_name', 'email', 'phone', 'school_name', 'certificate_type', 'issue_date', 'year', 'status', 'send_email' );
				$headers = array_merge( $headers, $extra_keys );
				fputcsv( $output, $headers );

				foreach ( $rows as $row ) {
					$extra = ! empty( $row['extra_fields'] ) ? json_decode( $row['extra_fields'], true ) : array();
					if ( ! is_array( $extra ) ) {
						$extra = array();
					}
					$csv_row = array(
						$row['teacher_name'],
						$row['email'],
						$row['phone'] ?? '',
						$row['school_name'],
						$row['certificate_type'],
						$row['issue_date'] ?? '',
						$row['year'] ?? '',
						$row['status'] ?? 'active',
						isset( $row['send_email'] ) && ! $row['send_email'] ? 'false' : 'true',
					);
					foreach ( $extra_keys as $key ) {
						$csv_row[] = $extra[ $key ] ?? '';
					}
					fputcsv( $output, $csv_row );
				}

				fclose( $output );
				exit;
			}
		}

		// Fallback: CPT-based export
		$args     = array(
			'post_type'   => 'teachers',
			'post_status' => 'publish',
			'numberposts' => -1,
		);
		$teachers = get_posts( $args );

		if ( empty( $teachers ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-warning"><p>No teachers found for export.</p></div>';
				}
			);
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=teachers_export.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output  = fopen( 'php://output', 'w' );
		$headers = array(
			'teacher_name',
			'email',
			'school_name',
			'school_abbreviation',
			'issue_date',
			'certificate_type',
		);
		fputcsv( $output, $headers );

		foreach ( $teachers as $teacher ) {
			$row = array(
				get_post_meta( $teacher->ID, 'teacher_name', true ),
				get_post_meta( $teacher->ID, 'email', true ),
				get_post_meta( $teacher->ID, 'school_name', true ),
				get_post_meta( $teacher->ID, 'school_abbreviation', true ),
				get_post_meta( $teacher->ID, 'issue_date', true ),
				get_post_meta( $teacher->ID, 'certificate_type', true ),
			);

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
add_action( 'admin_init', 'bulk_export_teachers' );


// Render the Export Certificates Page
function render_bulk_export_certificates_page() {
	echo '<div class="wrap">';
	echo '<h1>Export Certificates</h1>';
	echo '<form method="post">';
	echo wp_nonce_field( 'cg_export_certificates', '_wpnonce_cg_export', true, false );
	echo '<input type="hidden" name="export_certificates" value="1" />';
	echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
	echo '</form>';
	echo '</div>';
}

// Handle CSV Export for Certificates
function bulk_export_certificates() {
	if ( isset( $_POST['export_certificates'] ) ) {
		check_admin_referer( 'cg_export_certificates', '_wpnonce_cg_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ), 403 );
		}
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Try SQL tables first, fall back to CPTs
		if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			$tables         = \CertificateGenerator\Database\CustomTables::instance();
			$template_table = $tables->get_table( 'certificate_templates' );
			$table_exists   = $GLOBALS['wpdb']->get_var(
				$GLOBALS['wpdb']->prepare(
					'SHOW TABLES LIKE %s',
					$template_table
				)
			) === $template_table;

			if ( $table_exists ) {
				$rows = $GLOBALS['wpdb']->get_results( "SELECT * FROM $template_table ORDER BY id ASC", ARRAY_A );

				if ( empty( $rows ) ) {
					add_action(
						'admin_notices',
						function () {
							echo '<div class="notice notice-warning"><p>No certificate templates found for export.</p></div>';
						}
					);
					return;
				}

				$max_fields = class_exists( 'CG_Field_Schema' ) ? CG_Field_Schema::MAX_FIELDS : 15;
				$headers    = array(
					'template_name',
					'certificate_type',
					'event_date',
					'template_url',
					'orientation',
					'page_size',
					'font_style',
					'font_size',
					'font_color',
					'qr_enabled',
					'serial_number_display',
					'status',
				);
				for ( $i = 1; $i <= $max_fields; $i++ ) {
					foreach ( array( 'position_x', 'position_y', 'visible', 'width', 'alignment' ) as $prop ) {
						$headers[] = "field_{$i}_{$prop}";
					}
				}

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=certificates_export.csv' );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );

				$output = fopen( 'php://output', 'w' );
				fputcsv( $output, $headers );

				foreach ( $rows as $row ) {
					// Field positions are stored as flat keys in extra_fields (e.g. field_1_position_x).
					// field_config is a legacy column that may be empty — always prefer extra_fields.
					$extra_data = array();
					if ( ! empty( $row['extra_fields'] ) ) {
						$decoded = json_decode( $row['extra_fields'], true );
						if ( is_array( $decoded ) ) {
							$extra_data = $decoded;
						}
					}
					// Fall back to nested field_config for rows migrated from the old format.
					$field_config = array();
					if ( empty( $extra_data ) && ! empty( $row['field_config'] ) ) {
						$decoded = json_decode( $row['field_config'], true );
						if ( is_array( $decoded ) ) {
							$field_config = $decoded;
						}
					}
					$csv_row = array(
						$row['template_name'],
						$row['certificate_type'],
						$row['event_date'] ?? '',
						$row['template_url'] ?? '',
						$row['orientation'] ?? '',
						$row['page_size'] ?? 'A4',
						$row['font_style'] ?? '',
						$row['font_size'] ?? 12,
						$row['font_color'] ?? '#000000',
						$row['qr_enabled'] ?? 0,
						$row['serial_number_display'] ?? 0,
						$row['status'] ?? 'draft',
					);
					for ( $i = 1; $i <= $max_fields; $i++ ) {
						foreach ( array( 'position_x', 'position_y', 'visible', 'width', 'alignment' ) as $prop ) {
							// Prefer flat extra_fields key, fall back to nested field_config.
							$csv_row[] = $extra_data[ "field_{$i}_{$prop}" ] ?? $field_config[ $i ][ $prop ] ?? '';
						}
					}
					fputcsv( $output, $csv_row );
				}

				fclose( $output );
				exit;
			}
		}

		// Fallback: CPT-based export
		$args         = array(
			'post_type'   => 'certificates',
			'post_status' => 'publish',
			'numberposts' => -1,
		);
		$certificates = get_posts( $args );

		if ( empty( $certificates ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-warning"><p>No certificates found for export.</p></div>';
				}
			);
			return;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=certificates_export.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output     = fopen( 'php://output', 'w' );
		$max_fields = class_exists( 'CG_Field_Schema' ) ? CG_Field_Schema::MAX_FIELDS : 15;
		$headers    = array(
			'certificate_type',
			'event_date',
			'template_url',
			'template_orientation',
			'font_size',
			'font_color',
			'font_style',
			'template_field_count',
		);
		for ( $i = 1; $i <= $max_fields; $i++ ) {
			foreach ( array( 'position_x', 'position_y', 'visible', 'width', 'alignment' ) as $prop ) {
				$headers[] = "field_{$i}_{$prop}";
			}
		}
		fputcsv( $output, $headers );

		foreach ( $certificates as $certificate ) {
			$row = array(
				get_post_meta( $certificate->ID, 'certificate_type', true ),
				get_post_meta( $certificate->ID, 'event_date', true ),
				get_post_meta( $certificate->ID, 'template_url', true ),
				get_post_meta( $certificate->ID, 'template_orientation', true ),
				get_post_meta( $certificate->ID, 'font_size', true ),
				get_post_meta( $certificate->ID, 'font_color', true ),
				get_post_meta( $certificate->ID, 'font_style', true ),
				get_post_meta( $certificate->ID, 'template_field_count', true ),
			);
			for ( $i = 1; $i <= $max_fields; $i++ ) {
				foreach ( array( 'position_x', 'position_y', 'visible', 'width', 'alignment' ) as $prop ) {
					$row[] = get_post_meta( $certificate->ID, "field_{$i}_{$prop}", true );
				}
			}

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}
}
add_action( 'admin_init', 'bulk_export_certificates' );
