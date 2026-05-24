<?php
declare(strict_types=1);

namespace CertificateGenerator\Models;

use CertificateGenerator\Database\Repository;

class EmailLog extends Repository {

	public function __construct( string $table = 'cg_email_logs' ) {
		parent::__construct( $table );
	}

	public function find_by_email( string $email, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE recipient_email = %s ORDER BY sent_at DESC LIMIT %d OFFSET %d",
				$email,
				$limit,
				$offset
			),
			ARRAY_A
		) ?: array();
	}

	public function find_failed( int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'failed' ORDER BY sent_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		) ?: array();
	}

	public function count_by_status( string $status ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
				$status
			)
		);
	}

	public function log_entry( array $data ): int {
		$data['sent_at'] = current_time( 'mysql' );
		return $this->insert( $data );
	}
}
