<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CG_Serial_Number_Generator {
	private static $instance = null;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function generate( $certificate_type = '', $student_data = null ) {
		$prefix       = get_option( 'cg_serial_prefix', 'CERT' );
		$length       = (int) get_option( 'cg_serial_length', 8 );
		$suffix       = get_option( 'cg_serial_suffix', '' );
		$reset_period = get_option( 'cg_serial_reset_period', 'none' );
		$use_date     = get_option( 'cg_serial_include_date', false );

		$date_part = '';
		if ( $use_date ) {
			switch ( $reset_period ) {
				case 'daily':
					$date_part = date( 'Ymd' ) . '-';
					break;
				case 'monthly':
					$date_part = date( 'Ym' ) . '-';
					break;
				case 'yearly':
					$date_part = date( 'Y' ) . '-';
					break;
			}
		}

		$sequence   = $this->get_next_sequence( $certificate_type, $reset_period );
		$padded_seq = str_pad( $sequence, $length, '0', STR_PAD_LEFT );

		$serial = $prefix . '-' . $date_part . $padded_seq;
		if ( $suffix ) {
			$serial .= '-' . $suffix;
		}

		// Update student table if student_data is provided
		if ( ! empty( $student_data ) ) {
			$this->update_student_serial( $student_data, $serial, $certificate_type );
		}

		return $serial;
	}

	/**
	 * Update student table with generated serial number
	 */
	private function update_student_serial( $student_data, $serial, $certificate_type ) {
		global $wpdb;

		if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			return;
		}

		$tables        = \CertificateGenerator\Database\CustomTables::instance();
		$student_table = $tables->get_table( 'students' );

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $student_table ) ) !== $student_table ) {
			return;
		}

		// Identify student by email or name + certificate_type
		$email = $student_data['email'] ?? '';
		$name  = $student_data['student_name'] ?? $student_data['teacher_name'] ?? '';

		$update_data = array(
			'serial_number'    => $serial,
			'certificate_type' => $certificate_type,
			'updated_at'       => current_time( 'mysql' ),
		);

		if ( ! empty( $email ) ) {
			// Update by email
			$wpdb->update(
				$student_table,
				$update_data,
				array( 'email' => $email ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);
		} elseif ( ! empty( $name ) && ! empty( $certificate_type ) ) {
			// Update by name + certificate_type
			$wpdb->update(
				$student_table,
				$update_data,
				array(
					'student_name'     => $name,
					'certificate_type' => $certificate_type,
				),
				array( '%s', '%s', '%s' ),
				array( '%s', '%s' )
			);
		}
	}

	private function get_next_sequence( $certificate_type, $reset_period ) {
		global $wpdb;

		// Build a per-cert-type, per-period option key (max 191 chars for utf8mb4 index).
		$safe_type     = substr( sanitize_key( $certificate_type ?: 'default' ), 0, 40 );
		$period_suffix = '';
		if ( $reset_period === 'daily' ) {
			$period_suffix = '_' . date( 'Ymd' );
		}
		if ( $reset_period === 'monthly' ) {
			$period_suffix = '_' . date( 'Ym' );
		}
		if ( $reset_period === 'yearly' ) {
			$period_suffix = '_' . date( 'Y' );
		}
		$option_key = 'cg_serial_seq_' . $safe_type . $period_suffix;

		// Atomically increment a persistent counter in wp_options.
		// ON DUPLICATE KEY UPDATE is a single atomic MySQL operation — safe across concurrent requests.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
             VALUES (%s, 1, 'no')
             ON DUPLICATE KEY UPDATE option_value = option_value + 1",
				$option_key
			)
		);

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				$option_key
			)
		);
	}

	public function verify( $serial_number ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'certificate_generator';

		$cert = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE serial_number = %s",
				sanitize_text_field( $serial_number )
			)
		);

		if ( ! $cert ) {
			return array(
				'valid'   => false,
				'message' => 'Certificate not found',
				'data'    => null,
			);
		}

		$is_expired = false;
		$expires_at = null;
		if ( $cert->expires_at ) {
			$expires_at = $cert->expires_at;
			$is_expired = strtotime( $cert->expires_at ) < time();
		}

		return array(
			'valid'   => true,
			'expired' => $is_expired,
			'message' => $is_expired ? 'Certificate has expired' : 'Certificate is valid',
			'data'    => array(
				'id'               => $cert->id,
				'student_name'     => $cert->student_name,
				'certificate_type' => $cert->certificate_type ?? '',
				'issued_at'        => $cert->issued_at,
				'expires_at'       => $expires_at,
				'serial_number'    => $cert->serial_number,
				'created_at'       => $cert->created_at,
			),
		);
	}

	public function register_api_endpoints() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'certificate-generator/v1',
					'/verify/(?P<serial>[a-zA-Z0-9\-]+)',
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'api_verify' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
	}

	public function api_verify( $request ) {
		$serial = $request->get_param( 'serial' );
		$result = $this->verify( $serial );

		return new WP_REST_Response( $result, $result['valid'] ? 200 : 404 );
	}
}
