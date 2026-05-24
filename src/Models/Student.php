<?php
declare(strict_types=1);

namespace CertificateGenerator\Models;

use CertificateGenerator\Database\Repository;

class Student extends Repository {

	protected string $table = 'cg_students';

	public function find_by_email( string $email ): ?array {
		return $this->find_by( 'email', $email );
	}

	public function find_by_serial( string $serial ): ?array {
		return $this->find_by( 'serial_number', $serial );
	}

	public function find_by_school( int $schoolId ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE school_id = %d ORDER BY student_name ASC",
				$schoolId
			),
			ARRAY_A
		);
	}

	public function find_by_status( string $status ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC",
				$status
			),
			ARRAY_A
		);
	}

	public function find_active(): array {
		return $this->find_by_status( 'active' );
	}

	public function all_by_type( string $certType, int $limit = 100 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE certificate_type = %s ORDER BY student_name ASC LIMIT %d",
				$certType,
				$limit
			),
			ARRAY_A
		);
	}

	public function count_by_type( string $certType ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE certificate_type = %s",
				$certType
			)
		);
	}

	public function search( string $term ): array {
		global $wpdb;
		$term = '%' . $wpdb->esc_like( $term ) . '%';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE student_name LIKE %s OR email LIKE %s ORDER BY student_name ASC LIMIT 50",
				$term,
				$term
			),
			ARRAY_A
		);
	}
}
