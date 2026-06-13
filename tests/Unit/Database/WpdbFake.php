<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Database;

/**
 * Minimal wpdb stand-in for repository unit tests.
 *
 * Usage:
 *   $db = new WpdbFake();
 *   $db->return_value = [['id' => 1, 'email' => 'a@b.com']];
 *   $repo = new CertificateRepository($db);
 *   $results = $repo->find_by_email('a@b.com');
 *   $this->assertStringContainsString('email', $db->last_query);
 */
class WpdbFake {
	public string $prefix    = 'wp_';
	public int    $insert_id = 0;

	public mixed $return_value = null;
	public string $last_query  = '';
	public array  $last_insert = [];
	public array  $last_update = [];
	public array  $last_delete = [];

	public function prepare( string $query, mixed ...$args ): string {
		$this->last_query = $query;
		return $query;
	}

	public function get_row( string $sql, string $output = OBJECT ): mixed {
		$this->last_query = $sql;
		return $this->return_value;
	}

	public function get_results( string $sql, string $output = OBJECT ): mixed {
		$this->last_query = $sql;
		return $this->return_value ?? [];
	}

	public function get_var( string $sql ): mixed {
		$this->last_query = $sql;
		return $this->return_value;
	}

	public function query( string $sql ): mixed {
		$this->last_query = $sql;
		return $this->return_value ?? true;
	}

	public function insert( string $table, array $data, mixed $format = null ): int|false {
		$this->last_insert = array( 'table' => $table, 'data' => $data );
		$this->insert_id   = (int) ( $this->return_value ?? 1 );
		return $this->insert_id;
	}

	public function update( string $table, array $data, array $where, mixed $format = null, mixed $where_format = null ): int|false {
		$this->last_update = array( 'table' => $table, 'data' => $data, 'where' => $where );
		return (int) ( $this->return_value ?? 1 );
	}

	public function delete( string $table, array $where, mixed $where_format = null ): int|false {
		$this->last_delete = array( 'table' => $table, 'where' => $where );
		return (int) ( $this->return_value ?? 1 );
	}

	public function get_col( string $sql, int $col_offset = 0 ): array {
		$this->last_query = $sql;
		return is_array( $this->return_value ) ? $this->return_value : [];
	}

	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
}
