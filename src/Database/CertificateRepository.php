<?php
declare(strict_types=1);

namespace CertificateGenerator\Database;

/**
 * Repository for wp_certificate_generator — the live cert monolith table.
 *
 * PK is `id`; it is externally referenced as `cg_id` by email/queue tables
 * via their `certificate_id` FK column.
 */
class CertificateRepository extends Repository {

	public function __construct( ?object $db = null ) {
		parent::__construct( 'certificate_generator', $db );
	}

	public function find_by_email( string $email ): array {
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE email = %s ORDER BY {$this->pk} DESC",
				$email
			),
			\ARRAY_A
		) ?: [];
	}

	public function find_by_email_and_type( string $email, string $cert_type ): array {
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE email = %s AND certificate_type = %s ORDER BY {$this->pk} DESC",
				$email,
				$cert_type
			),
			\ARRAY_A
		) ?: [];
	}

	public function find_by_type( string $cert_type, int $limit = 100, int $offset = 0 ): array {
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE certificate_type = %s ORDER BY {$this->pk} DESC LIMIT %d OFFSET %d",
				$cert_type,
				$limit,
				$offset
			),
			\ARRAY_A
		) ?: [];
	}

	public function count_by_type( string $cert_type ): int {
		return (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE certificate_type = %s",
				$cert_type
			)
		);
	}
}
