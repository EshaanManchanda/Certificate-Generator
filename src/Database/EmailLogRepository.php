<?php
declare(strict_types=1);

namespace CertificateGenerator\Database;

/**
 * Repository for wp_cert_email_logs — the live email log table.
 *
 * `certificate_id` column is the FK referencing wp_certificate_generator.id.
 */
class EmailLogRepository extends Repository {

	public function __construct( ?object $db = null ) {
		parent::__construct( 'cert_email_logs', $db );
	}

	public function find_by_certificate_id( int $cg_id ): array {
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE certificate_id = %d ORDER BY sent_at DESC",
				$cg_id
			),
			\ARRAY_A
		) ?: [];
	}

	public function find_by_email( string $email, int $limit = 50, int $offset = 0 ): array {
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE recipient_email = %s ORDER BY sent_at DESC LIMIT %d OFFSET %d",
				$email,
				$limit,
				$offset
			),
			\ARRAY_A
		) ?: [];
	}

	public function find_by_status( string $status, int $limit = 50, int $offset = 0 ): array {
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY sent_at DESC LIMIT %d OFFSET %d",
				$status,
				$limit,
				$offset
			),
			\ARRAY_A
		) ?: [];
	}

	public function count_by_status( string $status ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
				$status
			)
		);
	}

	public function log_send( array $data ): int {
		if ( ! isset( $data['sent_at'] ) ) {
			$data['sent_at'] = current_time( 'mysql' );
		}
		return $this->insert( $data );
	}

	public function mark_sent( int $id ): bool {
		return $this->update( $id, array( 'status' => 'sent' ) );
	}

	public function mark_failed( int $id, string $error = '' ): bool {
		return $this->update( $id, array( 'status' => 'failed', 'error_message' => $error ) );
	}
}
