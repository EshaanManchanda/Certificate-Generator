<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Database;

use CertificateGenerator\Database\CertificateRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Database\CertificateRepository
 * @covers \CertificateGenerator\Database\Repository
 */
class CertificateRepositoryTest extends TestCase {

	private WpdbFake $db;
	private CertificateRepository $repo;

	protected function setUp(): void {
		$this->db   = new WpdbFake();
		$this->repo = new CertificateRepository( $this->db );
	}

	public function test_constructor_sets_live_table_name(): void {
		$this->assertSame( 'wp_certificate_generator', $this->repo->get_table() );
	}

	public function test_find_returns_row_by_pk(): void {
		$this->db->return_value = array( 'id' => 5, 'email' => 'a@b.com' );
		$row = $this->repo->find( 5 );
		$this->assertSame( array( 'id' => 5, 'email' => 'a@b.com' ), $row );
		$this->assertStringContainsString( 'id', $this->db->last_query );
	}

	public function test_find_returns_null_when_not_found(): void {
		$this->db->return_value = null;
		$this->assertNull( $this->repo->find( 99 ) );
	}

	public function test_find_by_email_returns_all_matching_rows(): void {
		$expected               = array(
			array( 'id' => 1, 'email' => 'x@y.com' ),
			array( 'id' => 2, 'email' => 'x@y.com' ),
		);
		$this->db->return_value = $expected;
		$rows = $this->repo->find_by_email( 'x@y.com' );
		$this->assertSame( $expected, $rows );
		$this->assertStringContainsString( 'email', $this->db->last_query );
	}

	public function test_find_by_email_returns_empty_array_when_none(): void {
		$this->db->return_value = null;
		$this->assertSame( array(), $this->repo->find_by_email( 'nobody@x.com' ) );
	}

	public function test_find_by_email_and_type_queries_both_columns(): void {
		$this->db->return_value = array( array( 'id' => 3 ) );
		$this->repo->find_by_email_and_type( 'a@b.com', 'Student' );
		$this->assertStringContainsString( 'email', $this->db->last_query );
		$this->assertStringContainsString( 'certificate_type', $this->db->last_query );
	}

	public function test_find_by_type_includes_limit_and_offset(): void {
		$this->db->return_value = array();
		$this->repo->find_by_type( 'Teacher', 10, 20 );
		$this->assertStringContainsString( 'certificate_type', $this->db->last_query );
		$this->assertStringContainsString( 'LIMIT', $this->db->last_query );
		$this->assertStringContainsString( 'OFFSET', $this->db->last_query );
	}

	public function test_count_by_type_returns_integer(): void {
		$this->db->return_value = '7';
		$this->assertSame( 7, $this->repo->count_by_type( 'Student' ) );
	}

	public function test_count_returns_zero_when_table_empty(): void {
		$this->db->return_value = null;
		$this->assertSame( 0, $this->repo->count() );
	}

	public function test_insert_returns_new_id(): void {
		$this->db->return_value = 42;
		$id = $this->repo->insert( array( 'email' => 'new@x.com' ) );
		$this->assertSame( 42, $id );
		$this->assertSame( 'wp_certificate_generator', $this->db->last_insert['table'] );
	}

	public function test_delete_uses_pk_column(): void {
		$this->db->return_value = 1;
		$result = $this->repo->delete( 5 );
		$this->assertTrue( $result );
		$this->assertArrayHasKey( 'id', $this->db->last_delete['where'] );
	}

	public function test_update_uses_pk_column(): void {
		$this->db->return_value = 1;
		$result = $this->repo->update( 5, array( 'email' => 'new@x.com' ) );
		$this->assertTrue( $result );
		$this->assertSame( array( 'email' => 'new@x.com' ), $this->db->last_update['data'] );
		$this->assertArrayHasKey( 'id', $this->db->last_update['where'] );
	}
}
