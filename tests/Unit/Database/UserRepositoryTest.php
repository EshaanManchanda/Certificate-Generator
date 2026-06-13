<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Database;

use CertificateGenerator\Database\UserRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Database\UserRepository
 * @covers \CertificateGenerator\Database\Repository
 */
class UserRepositoryTest extends TestCase {

	private WpdbFake $db;

	protected function setUp(): void {
		$this->db = new WpdbFake();
	}

	// ── Constructor / table routing ───────────────────────────────────────────

	public function test_students_entity_maps_to_correct_table(): void {
		$repo = new UserRepository( 'students', $this->db );
		$this->assertSame( 'wp_cg_students', $repo->get_table() );
	}

	public function test_teachers_entity_maps_to_correct_table(): void {
		$repo = new UserRepository( 'teachers', $this->db );
		$this->assertSame( 'wp_cg_teachers', $repo->get_table() );
	}

	public function test_schools_entity_maps_to_correct_table(): void {
		$repo = new UserRepository( 'schools', $this->db );
		$this->assertSame( 'wp_cg_schools', $repo->get_table() );
	}

	public function test_unknown_entity_falls_back_to_students(): void {
		$repo = new UserRepository( 'aliens', $this->db );
		$this->assertSame( 'wp_cg_students', $repo->get_table() );
	}

	// ── count_filtered ────────────────────────────────────────────────────────

	public function test_count_filtered_no_filters_returns_all_count(): void {
		$this->db->return_value = '42';
		$repo  = new UserRepository( 'students', $this->db );
		$total = $repo->count_filtered( array() );
		$this->assertSame( 42, $total );
		$this->assertStringContainsString( '1=1', $this->db->last_query );
	}

	public function test_count_filtered_search_adds_like_clause(): void {
		$this->db->return_value = '5';
		$repo = new UserRepository( 'students', $this->db );
		$repo->count_filtered( array( 'search' => 'Alice' ) );
		$this->assertStringContainsString( 'student_name LIKE', $this->db->last_query );
		$this->assertStringContainsString( 'email LIKE', $this->db->last_query );
	}

	public function test_count_filtered_school_name_adds_exact_clause(): void {
		$this->db->return_value = '3';
		$repo = new UserRepository( 'students', $this->db );
		$repo->count_filtered( array( 'school_name' => 'GEMA School' ) );
		$this->assertStringContainsString( 'school_name', $this->db->last_query );
	}

	public function test_count_filtered_cert_type_adds_exact_clause(): void {
		$this->db->return_value = '7';
		$repo = new UserRepository( 'teachers', $this->db );
		$repo->count_filtered( array( 'certificate_type' => 'Teacher' ) );
		$this->assertStringContainsString( 'certificate_type', $this->db->last_query );
	}

	public function test_count_filtered_city_adds_exact_clause(): void {
		$this->db->return_value = '2';
		$repo = new UserRepository( 'schools', $this->db );
		$repo->count_filtered( array( 'city' => 'Mumbai' ) );
		$this->assertStringContainsString( 'city', $this->db->last_query );
	}

	// ── find_page ─────────────────────────────────────────────────────────────

	public function test_find_page_includes_orderby_limit_offset(): void {
		$this->db->return_value = array( array( 'id' => 1 ) );
		$repo = new UserRepository( 'students', $this->db );
		$rows = $repo->find_page( array(), 'created_at', 'DESC', 20, 40 );
		$this->assertCount( 1, $rows );
		$this->assertStringContainsString( 'ORDER BY', $this->db->last_query );
		$this->assertStringContainsString( 'LIMIT', $this->db->last_query );
		$this->assertStringContainsString( 'OFFSET', $this->db->last_query );
	}

	public function test_find_page_returns_empty_when_none(): void {
		$this->db->return_value = null;
		$repo = new UserRepository( 'students', $this->db );
		$this->assertSame( array(), $repo->find_page( array(), 'created_at', 'DESC', 20, 0 ) );
	}

	// ── distinct_column ───────────────────────────────────────────────────────

	public function test_distinct_column_queries_correct_column(): void {
		$this->db->return_value = array( 'GEMA School', 'Other School' );
		$repo   = new UserRepository( 'students', $this->db );
		$values = $repo->distinct_column( 'school_name' );
		$this->assertSame( array( 'GEMA School', 'Other School' ), $values );
		$this->assertStringContainsString( 'school_name', $this->db->last_query );
		$this->assertStringContainsString( 'DISTINCT', $this->db->last_query );
	}

	public function test_distinct_column_returns_empty_when_none(): void {
		$this->db->return_value = null;
		$repo = new UserRepository( 'students', $this->db );
		$this->assertSame( array(), $repo->distinct_column( 'school_name' ) );
	}

	// ── Teacher name_col used in search ──────────────────────────────────────

	public function test_teachers_search_uses_teacher_name_column(): void {
		$this->db->return_value = '1';
		$repo = new UserRepository( 'teachers', $this->db );
		$repo->count_filtered( array( 'search' => 'Bob' ) );
		$this->assertStringContainsString( 'teacher_name LIKE', $this->db->last_query );
	}

	public function test_schools_search_uses_school_name_column(): void {
		$this->db->return_value = '1';
		$repo = new UserRepository( 'schools', $this->db );
		$repo->count_filtered( array( 'search' => 'GEMA' ) );
		$this->assertStringContainsString( 'school_name LIKE', $this->db->last_query );
	}
}
