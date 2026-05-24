<?php
declare(strict_types=1);

namespace CertificateGenerator\Models;

use CertificateGenerator\Database\Repository;

class Certificate extends Repository {

	protected string $table = 'cg_certificates';

	public function find_by_serial( string $serial ): ?array {
		return $this->find_by( 'serial_number', $serial );
	}

	public function find_expired( int $limit = 100, int $offset = 0 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at < %s ORDER BY expires_at ASC LIMIT %d OFFSET %d",
				current_time( 'mysql' ),
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	public function find_expiring_soon( int $days = 30, int $limit = 100 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at BETWEEN %s AND DATE_ADD(%s, INTERVAL %d DAY) ORDER BY expires_at ASC LIMIT %d",
				current_time( 'mysql' ),
				current_time( 'mysql' ),
				$days,
				$limit
			),
			ARRAY_A
		);
	}

	public function count_by_type( string $type ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE certificate_type = %s",
				$type
			)
		);
	}

	public function count_with_serial(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE serial_number IS NOT NULL AND serial_number != ''"
		);
	}

	public function count_without_serial(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE serial_number IS NULL OR serial_number = ''"
		);
	}

	public function monthly_counts( int $months = 12 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(issued_at, '%%Y-%%m') as month, COUNT(*) as count 
             FROM {$this->table} 
             WHERE issued_at IS NOT NULL 
             GROUP BY DATE_FORMAT(issued_at, '%%Y-%%m') 
             ORDER BY month DESC 
             LIMIT %d",
				$months
			),
			ARRAY_A
		);
	}
}
