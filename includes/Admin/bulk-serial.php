<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CG_Bulk_Serial_Generator {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_bulk_serial_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_cg_bulk_generate_serials', array( $this, 'ajax_bulk_generate' ) );
		add_action( 'wp_ajax_cg_bulk_generate_status', array( $this, 'ajax_get_status' ) );
	}

	public function add_bulk_serial_menu() {
		add_submenu_page(
			'cg-dashboard',
			'Bulk Serial Numbers',
			'Bulk Serials',
			'manage_options',
			'cg-bulk-serials',
			array( $this, 'render_bulk_serial_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'cg-bulk-serials' ) === false ) {
			return;
		}

		wp_enqueue_style( 'cg-bulk-css', CERTIFICATE_GENERATOR_URL . 'assets/css/bulk-serial-style.css', array(), '1.0.0' );
		wp_enqueue_script( 'cg-bulk-js', CERTIFICATE_GENERATOR_URL . 'assets/js/bulk-serial-script.js', array( 'jquery' ), '1.0.0', true );

		wp_localize_script(
			'cg-bulk-js',
			'cgBulkSerial',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cg_bulk_serial_nonce' ),
			)
		);
	}

	/**
	 * Get SQL entity table name and check it exists.
	 *
	 * @param string $entity students|teachers|schools
	 * @return string|null Table name or null when unavailable.
	 */
	private function get_entity_table( string $entity ): ?string {
		if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			return null;
		}
		global $wpdb;
		$tbl = \CertificateGenerator\Database\CustomTables::instance()->get_table( $entity );
		if ( ! $tbl || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
			return null;
		}
		return $tbl;
	}

	/**
	 * Aggregate serial stats across cg_students / cg_teachers / cg_schools.
	 *
	 * @return array{ total_with_serial: int, total_without_serial: int, total_posts: int }
	 */
	private function get_serial_stats(): array {
		global $wpdb;
		$stats = array(
			'total_with_serial'    => 0,
			'total_without_serial' => 0,
			'total_posts'          => 0,
		);

		foreach ( array( 'students', 'teachers', 'schools' ) as $entity ) {
			$tbl = $this->get_entity_table( $entity );
			if ( ! $tbl ) {
				continue;
			}
			$with    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE serial_number IS NOT NULL AND serial_number != ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$without = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tbl WHERE serial_number IS NULL OR serial_number = ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$stats['total_with_serial']    += $with;
			$stats['total_without_serial'] += $without;
			$stats['total_posts']          += $with + $without;
		}

		return $stats;
	}

	public function render_bulk_serial_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;

		$stats = $this->get_serial_stats();

		// Recent serials from wp_cg_certificates (recipient_name column).
		$recent_serials = array();
		if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			$cert_table = \CertificateGenerator\Database\CustomTables::instance()->get_table( 'certificates' );
			if ( $cert_table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cert_table ) ) === $cert_table ) {
				$sql            = "SELECT id, recipient_name, serial_number, certificate_type, generated_via, issued_at FROM $cert_table WHERE serial_number IS NOT NULL AND serial_number != '' ORDER BY id DESC LIMIT 50"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recent_serials = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}
		?>
		<div class="wrap cg-bulk-serial-page">
			<h1>Bulk Serial Number Generation</h1>

			<div class="cg-bulk-stats">
				<div class="cg-bulk-stat">
					<span class="cg-bulk-stat-value"><?php echo number_format( $stats['total_posts'] ); ?></span>
					<span class="cg-bulk-stat-label">Total Certificate Records</span>
				</div>
				<div class="cg-bulk-stat">
					<span class="cg-bulk-stat-value"><?php echo number_format( $stats['total_with_serial'] ); ?></span>
					<span class="cg-bulk-stat-label">With Serial Number</span>
				</div>
				<div class="cg-bulk-stat cg-bulk-stat-warning">
					<span class="cg-bulk-stat-value"><?php echo number_format( $stats['total_without_serial'] ); ?></span>
					<span class="cg-bulk-stat-label">Missing Serial Number</span>
				</div>
			</div>

			<div class="cg-bulk-form-section">
				<h2>Generate Serial Numbers</h2>
				<p>Generate unique serial numbers for all existing certificates that don't have one yet.</p>

				<div class="cg-bulk-options">
					<label>
						<input type="checkbox" id="cg-bulk-students" checked> Include Students
					</label>
					<label>
						<input type="checkbox" id="cg-bulk-teachers" checked> Include Teachers
					</label>
					<label>
						<input type="checkbox" id="cg-bulk-schools" checked> Include Schools
					</label>
				</div>

				<button type="button" id="cg-bulk-generate-btn" class="button button-primary button-hero">
					Generate Serial Numbers
				</button>

				<div id="cg-bulk-progress" style="display: none; margin-top: 20px;">
					<div class="cg-bulk-progress-bar">
						<div class="cg-bulk-progress-fill" style="width: 0%;"></div>
					</div>
					<p class="cg-bulk-progress-text">Processing... 0 / 0</p>
					<p class="cg-bulk-progress-detail"></p>
				</div>

				<div id="cg-bulk-results" style="display: none; margin-top: 20px;">
					<div class="notice notice-success">
						<p><strong>Completed!</strong> <span id="cg-bulk-success-count"></span> serial numbers generated.</p>
					</div>
				</div>
			</div>

			<div class="cg-bulk-table-section" style="margin-top: 30px;">
				<h2>Recently Generated Serial Numbers</h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:60px;">ID</th>
							<th>Name</th>
							<th>Certificate Type</th>
							<th>Serial Number</th>
							<th>Generated Via</th>
							<th>Issued At</th>
						</tr>
					</thead>
					<tbody id="cg-bulk-recent-serials">
						<?php if ( empty( $recent_serials ) ) : ?>
							<tr>
								<td colspan="6" class="cg-bulk-empty">No serial numbers found. Generate certificates or run bulk generation.</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $recent_serials as $row ) : ?>
								<tr>
									<td><?php echo esc_html( $row->id ); ?></td>
									<td><?php echo esc_html( $row->recipient_name ); ?></td>
									<td><?php echo esc_html( ! empty( $row->certificate_type ) ? $row->certificate_type : '—' ); ?></td>
									<td><code><?php echo esc_html( $row->serial_number ); ?></code></td>
									<td><?php echo esc_html( ucfirst( ! empty( $row->generated_via ) ? $row->generated_via : 'manual' ) ); ?></td>
									<td><?php echo esc_html( cg_format_date( $row->issued_at, true ) ? cg_format_date( $row->issued_at, true ) : '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: generate serial numbers for all entity rows missing one.
	 *
	 * Reads entity data from wp_cg_students/teachers/schools SQL tables.
	 * Nonce: cg_bulk_serial_nonce.
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_bulk_generate() {
		check_ajax_referer( 'cg_bulk_serial_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$post_types = array();
		if ( ! empty( $_POST['students'] ) ) {
			$post_types[] = 'students';
		}
		if ( ! empty( $_POST['teachers'] ) ) {
			$post_types[] = 'teachers';
		}
		if ( ! empty( $_POST['schools'] ) ) {
			$post_types[] = 'schools';
		}

		if ( empty( $post_types ) ) {
			wp_send_json_error( 'No post types selected' );
		}

		global $wpdb;

		$serial_gen = null;
		if ( class_exists( 'CG_Serial_Number_Generator' ) ) {
			$serial_gen = CG_Serial_Number_Generator::get_instance();
		}

		// Generation results accumulator.
		/** @var array{ success: int, skipped: int, errors: int, details: array<int, array<string,mixed>> } */
		$results = array(
			'success' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'details' => array(),
		);

		// Name column per entity type.
		$name_cols = array(
			'students' => 'student_name',
			'teachers' => 'teacher_name',
			'schools'  => 'school_name',
		);

		foreach ( $post_types as $post_type ) {
			$tbl = $this->get_entity_table( $post_type );
			if ( ! $tbl ) {
				++$results['errors'];
				continue;
			}

			$name_col = $name_cols[ $post_type ];

			// Fetch only rows missing a serial number.
			$sql  = "SELECT id, wp_post_id, $name_col AS entity_name, email, certificate_type FROM $tbl WHERE serial_number IS NULL OR serial_number = ''"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( $rows as $row ) {
				$entity_id  = (int) $row['id'];
				$name       = sanitize_text_field( $row['entity_name'] ?? '' );
				$cert_type  = sanitize_text_field( $row['certificate_type'] ?? '' );
				$email      = sanitize_email( $row['email'] ?? '' );
				$wp_post_id = (int) ( $row['wp_post_id'] ?? 0 );

				// Check if this name+type already has a serial in entity table.
				$serial = '';
				if ( function_exists( 'cg_find_existing_serial' ) && $name && $cert_type ) {
					$serial = cg_find_existing_serial( $name, $cert_type );
				}

				if ( empty( $serial ) ) {
					if ( $serial_gen ) {
						$student_data = array(
							'email'        => $email,
							'student_name' => $name,
						);
						$serial       = $serial_gen->generate( $cert_type, $student_data );
					} else {
						$serial = 'CERT-' . str_pad( (string) wp_rand( 1, 999999 ), 6, '0', STR_PAD_LEFT );
					}
				}

				if ( empty( $serial ) ) {
					++$results['errors'];
					continue;
				}

				// Write serial to the entity SQL row directly (covers wp_post_id=0 rows too).
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$tbl,
					array(
						'serial_number' => $serial,
						'issue_date'    => current_time( 'mysql' ),
					),
					array( 'id' => $entity_id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				// Persist to wp_cg_certificates (and legacy table via cg_insert_certificate_record).
				if ( function_exists( 'cg_insert_certificate_record' ) ) {
					cg_insert_certificate_record(
						array(
							$name_col          => $name,
							'email'            => $email,
							'certificate_type' => $cert_type,
							'recipient_type'   => $post_type,
							'wp_post_id'       => $wp_post_id,
						),
						$serial,
						'bulk'
					);
				}

				++$results['success'];
				$results['details'][] = array(
					'entity_id' => $entity_id,
					'post_type' => $post_type,
					'name'      => $name,
					'serial'    => $serial,
					'issued_at' => current_time( 'mysql' ),
				);
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler: return current with/without serial counts from SQL entity tables.
	 *
	 * Nonce: cg_bulk_serial_nonce.
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function ajax_get_status() {
		check_ajax_referer( 'cg_bulk_serial_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$stats = $this->get_serial_stats();

		wp_send_json_success(
			array(
				'total_without' => $stats['total_without_serial'],
				'total_with'    => $stats['total_with_serial'],
			)
		);
	}
}
