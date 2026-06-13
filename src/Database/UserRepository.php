<?php
declare(strict_types=1);

namespace CertificateGenerator\Database;

/**
 * Repository for student/teacher/school entity lookups.
 *
 * TODO(Phase 3B — table reconciliation): Confirm the live student source by
 * row count (wp_cg_students vs CPT posts). Retarget $table and reconcile
 * column names before routing any callers through this repo.
 *
 * Currently points at wp_cg_students (scaffold, effectively empty) to
 * establish the interface without touching live data.
 */
class UserRepository extends Repository {

	public function __construct( ?object $db = null ) {
		parent::__construct( 'cg_students', $db );
	}

	public function find_by_email( string $email ): ?array {
		return $this->find_by( 'email', $email );
	}

	public function find_by_school( int $school_id ): array {
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE school_id = %d ORDER BY student_name ASC",
				$school_id
			),
			\ARRAY_A
		) ?: [];
	}

	public function search( string $term ): array {
		$like = '%' . $this->db->esc_like( $term ) . '%';
		return $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE student_name LIKE %s OR email LIKE %s ORDER BY student_name ASC LIMIT 50",
				$like,
				$like
			),
			\ARRAY_A
		) ?: [];
	}
}
