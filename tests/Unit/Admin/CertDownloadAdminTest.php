<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Tests\Unit\Database\WpdbFake;
use PHPUnit\Framework\TestCase;

/**
 * @covers ::cg_admin_cert_read_filters
 * @covers ::cg_admin_cert_query
 * @covers ::cg_handle_admin_cert_zip_download
 * @covers ::cg_admin_cert_count
 * @covers ::cg_handle_admin_individual_cert_download
 */
class CertDownloadAdminTest extends TestCase {

	private WpdbFake $wpdb;

	public static function setUpBeforeClass(): void {
		if ( ! function_exists( 'cg_build_recipient_filter_sql' ) ) {
			require_once dirname( __DIR__, 3 ) . '/includes/Admin/filters-api.php';
		}
		if ( ! function_exists( 'cg_admin_cert_read_filters' ) ) {
			require_once dirname( __DIR__, 3 ) . '/includes/Admin/cert-download-admin.php';
		}
	}

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->wpdb          = new WpdbFake();
		$GLOBALS['wpdb']     = $this->wpdb;
		$_GET                = array();
		$_POST               = array();
		$_REQUEST            = array();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	protected function tearDown(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		Monkey\tearDown();
		unset( $GLOBALS['wpdb'] );

		$ref  = new \ReflectionClass( \CertificateGenerator\Database\CustomTables::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		parent::tearDown();
	}

	// ── Helper: sequential wpdb ──────────────────────────────────────────────

	private function useSequentialWpdb( mixed ...$returns ): SequentialWpdbFake {
		$db = new SequentialWpdbFake();
		$db->queue_returns( ...$returns );
		$GLOBALS['wpdb'] = $db;
		return $db;
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_cert_read_filters()
	// ═══════════════════════════════════════════════════════════════════════════

	// ── Defaults ─────────────────────────────────────────────────────────────

	public function test_read_filters_empty_request_returns_all_defaults(): void {
		$r = cg_admin_cert_read_filters();

		$this->assertSame( array(), $r['schools'] );
		$this->assertSame( array(), $r['certificate_types'] );
		$this->assertSame( array(), $r['year'] );
		$this->assertSame( '', $r['date_from'] );
		$this->assertSame( '', $r['date_to'] );
		$this->assertSame( '', $r['email_search'] );
		$this->assertSame( array(), $r['emails'] );
	}

	public function test_read_filters_returns_exactly_seven_keys(): void {
		$r = cg_admin_cert_read_filters();

		$this->assertCount( 7, $r );
	}

	// ── Schools ──────────────────────────────────────────────────────────────

	public function test_read_filters_schools_array_is_sanitized(): void {
		$_REQUEST['filter_school'] = array( 'School A', 'School B' );

		$r = cg_admin_cert_read_filters();

		$this->assertSame( array( 'School A', 'School B' ), $r['schools'] );
	}

	public function test_read_filters_schools_string_is_ignored(): void {
		$_REQUEST['filter_school'] = 'School A';

		$this->assertSame( array(), cg_admin_cert_read_filters()['schools'] );
	}

	// ── Certificate types ────────────────────────────────────────────────────

	public function test_read_filters_cert_types_array_is_sanitized(): void {
		$_REQUEST['filter_cert_type'] = array( 'Winner', 'Participation' );

		$this->assertSame(
			array( 'Winner', 'Participation' ),
			cg_admin_cert_read_filters()['certificate_types']
		);
	}

	public function test_read_filters_cert_types_string_is_ignored(): void {
		$_REQUEST['filter_cert_type'] = 'Winner';

		$this->assertSame( array(), cg_admin_cert_read_filters()['certificate_types'] );
	}

	// ── Year ─────────────────────────────────────────────────────────────────

	public function test_read_filters_year_array_values_cast_to_int(): void {
		$_REQUEST['filter_year'] = array( '2024', '2025' );

		$this->assertSame( array( 2024, 2025 ), cg_admin_cert_read_filters()['year'] );
	}

	public function test_read_filters_year_string_is_ignored(): void {
		$_REQUEST['filter_year'] = '2024';

		$this->assertSame( array(), cg_admin_cert_read_filters()['year'] );
	}

	// ── Date from ────────────────────────────────────────────────────────────

	public function test_read_filters_valid_date_from_is_kept(): void {
		$_REQUEST['filter_date_from'] = '2024-01-15';

		$this->assertSame( '2024-01-15', cg_admin_cert_read_filters()['date_from'] );
	}

	public function test_read_filters_date_from_slash_format_rejected(): void {
		$_REQUEST['filter_date_from'] = '2024/01/15';

		$this->assertSame( '', cg_admin_cert_read_filters()['date_from'] );
	}

	public function test_read_filters_date_from_non_date_string_rejected(): void {
		$_REQUEST['filter_date_from'] = 'not-a-date';

		$this->assertSame( '', cg_admin_cert_read_filters()['date_from'] );
	}

	public function test_read_filters_date_from_single_digit_month_rejected(): void {
		$_REQUEST['filter_date_from'] = '2024-1-1';

		$this->assertSame( '', cg_admin_cert_read_filters()['date_from'] );
	}

	public function test_read_filters_date_from_with_time_suffix_rejected(): void {
		$_REQUEST['filter_date_from'] = '2024-01-01T00:00:00';

		$this->assertSame( '', cg_admin_cert_read_filters()['date_from'] );
	}

	public function test_read_filters_date_from_extra_digits_rejected(): void {
		$_REQUEST['filter_date_from'] = '20240-01-01';

		$this->assertSame( '', cg_admin_cert_read_filters()['date_from'] );
	}

	// ── Date to ──────────────────────────────────────────────────────────────

	public function test_read_filters_valid_date_to_is_kept(): void {
		$_REQUEST['filter_date_to'] = '2024-12-31';

		$this->assertSame( '2024-12-31', cg_admin_cert_read_filters()['date_to'] );
	}

	public function test_read_filters_date_to_non_date_string_rejected(): void {
		$_REQUEST['filter_date_to'] = 'bad-date';

		$this->assertSame( '', cg_admin_cert_read_filters()['date_to'] );
	}

	// ── Email search ─────────────────────────────────────────────────────────

	public function test_read_filters_email_search_is_sanitized(): void {
		$_REQUEST['filter_email'] = 'gmail.com';

		$this->assertSame( 'gmail.com', cg_admin_cert_read_filters()['email_search'] );
	}

	public function test_read_filters_email_absent_returns_empty_string(): void {
		$this->assertSame( '', cg_admin_cert_read_filters()['email_search'] );
	}

	// ── Emails key ───────────────────────────────────────────────────────────

	public function test_read_filters_emails_is_always_empty_array(): void {
		$_REQUEST['emails'] = array( 'a@b.com' );

		$this->assertSame( array(), cg_admin_cert_read_filters()['emails'] );
	}

	// ── Source routing ───────────────────────────────────────────────────────

	public function test_read_filters_source_get_reads_get_superglobal(): void {
		$_GET['filter_email']  = 'from_get';
		$_POST['filter_email'] = 'from_post';

		$this->assertSame( 'from_get', cg_admin_cert_read_filters( 'GET' )['email_search'] );
	}

	public function test_read_filters_source_post_reads_post_superglobal(): void {
		$_GET['filter_email']  = 'from_get';
		$_POST['filter_email'] = 'from_post';

		$this->assertSame( 'from_post', cg_admin_cert_read_filters( 'POST' )['email_search'] );
	}

	public function test_read_filters_default_source_reads_request(): void {
		$_REQUEST['filter_email'] = 'from_request';

		$this->assertSame( 'from_request', cg_admin_cert_read_filters()['email_search'] );
	}

	public function test_read_filters_explicit_request_source_reads_request(): void {
		$_REQUEST['filter_email'] = 'from_request';

		$this->assertSame( 'from_request', cg_admin_cert_read_filters( 'REQUEST' )['email_search'] );
	}

	// ── Combined ─────────────────────────────────────────────────────────────

	public function test_read_filters_all_fields_combined(): void {
		$_REQUEST = array(
			'filter_school'    => array( 'School A', 'School B' ),
			'filter_cert_type' => array( 'Winner' ),
			'filter_year'      => array( '2024' ),
			'filter_date_from' => '2024-04-01',
			'filter_date_to'   => '2024-04-30',
			'filter_email'     => 'test@',
		);

		$r = cg_admin_cert_read_filters();

		$this->assertSame( array( 'School A', 'School B' ), $r['schools'] );
		$this->assertSame( array( 'Winner' ), $r['certificate_types'] );
		$this->assertSame( array( 2024 ), $r['year'] );
		$this->assertSame( '2024-04-01', $r['date_from'] );
		$this->assertSame( '2024-04-30', $r['date_to'] );
		$this->assertSame( 'test@', $r['email_search'] );
		$this->assertSame( array(), $r['emails'] );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_cert_query()
	// ═══════════════════════════════════════════════════════════════════════════

	private function empty_filters(): array {
		return array(
			'schools'           => array(),
			'certificate_types' => array(),
			'year'              => array(),
			'date_from'         => '',
			'date_to'           => '',
			'email_search'      => '',
			'emails'            => array(),
		);
	}

	// ── Early returns ────────────────────────────────────────────────────────

	public function test_query_returns_empty_when_table_not_found(): void {
		$this->useSequentialWpdb( null );

		$this->assertSame( array(), cg_admin_cert_query( $this->empty_filters() ) );
	}

	public function test_query_returns_empty_when_get_results_is_null(): void {
		$this->useSequentialWpdb( 'wp_cg_students', null );

		$this->assertSame( array(), cg_admin_cert_query( $this->empty_filters() ) );
	}

	public function test_query_returns_empty_when_get_results_is_empty(): void {
		$this->useSequentialWpdb( 'wp_cg_students', array() );

		$this->assertSame( array(), cg_admin_cert_query( $this->empty_filters() ) );
	}

	// ── Row processing ───────────────────────────────────────────────────────

	public function test_query_returns_rows_and_strips_empty_extra_fields(): void {
		$rows = array(
			array( 'id' => 1, 'student_name' => 'Alice', 'extra_fields' => '' ),
		);
		$this->useSequentialWpdb( 'wp_cg_students', $rows );

		$result = cg_admin_cert_query( $this->empty_filters() );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Alice', $result[0]['student_name'] );
		$this->assertArrayNotHasKey( 'extra_fields', $result[0] );
	}

	public function test_query_merges_extra_fields_json_into_row(): void {
		$rows = array(
			array(
				'id'           => 1,
				'student_name' => 'Bob',
				'extra_fields' => '{"rank":"1st","score":"95"}',
			),
		);
		$this->useSequentialWpdb( 'wp_cg_students', $rows );

		$result = cg_admin_cert_query( $this->empty_filters() );

		$this->assertSame( '1st', $result[0]['rank'] );
		$this->assertSame( '95', $result[0]['score'] );
		$this->assertArrayNotHasKey( 'extra_fields', $result[0] );
	}

	public function test_query_handles_invalid_extra_fields_json(): void {
		$rows = array(
			array( 'id' => 1, 'student_name' => 'Charlie', 'extra_fields' => 'not-json' ),
		);
		$this->useSequentialWpdb( 'wp_cg_students', $rows );

		$result = cg_admin_cert_query( $this->empty_filters() );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Charlie', $result[0]['student_name'] );
		$this->assertArrayNotHasKey( 'extra_fields', $result[0] );
	}

	public function test_query_handles_null_extra_fields(): void {
		$rows = array(
			array( 'id' => 1, 'student_name' => 'Dave', 'extra_fields' => null ),
		);
		$this->useSequentialWpdb( 'wp_cg_students', $rows );

		$result = cg_admin_cert_query( $this->empty_filters() );

		$this->assertCount( 1, $result );
		$this->assertArrayNotHasKey( 'extra_fields', $result[0] );
	}

	public function test_query_processes_multiple_rows(): void {
		$rows = array(
			array( 'id' => 1, 'student_name' => 'Alice', 'extra_fields' => '' ),
			array( 'id' => 2, 'student_name' => 'Bob', 'extra_fields' => '{"rank":"1st"}' ),
			array( 'id' => 3, 'student_name' => 'Charlie', 'extra_fields' => null ),
		);
		$this->useSequentialWpdb( 'wp_cg_students', $rows );

		$result = cg_admin_cert_query( $this->empty_filters() );

		$this->assertCount( 3, $result );
		foreach ( $result as $row ) {
			$this->assertArrayNotHasKey( 'extra_fields', $row );
		}
		$this->assertSame( '1st', $result[1]['rank'] );
		$this->assertArrayNotHasKey( 'rank', $result[0] );
		$this->assertArrayNotHasKey( 'rank', $result[2] );
	}

	// ── SQL construction ─────────────────────────────────────────────────────

	public function test_query_no_where_clause_with_empty_filters(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $this->empty_filters() );

		$this->assertStringNotContainsString( 'WHERE', $db->last_query );
	}

	public function test_query_sql_contains_limit_and_offset(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $this->empty_filters(), 100, 50 );

		$this->assertStringContainsString( 'LIMIT %d OFFSET %d', $db->last_query );
	}

	public function test_query_applies_school_filter_to_sql(): void {
		$filters             = $this->empty_filters();
		$filters['schools']  = array( 'Test School' );
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $filters );

		$this->assertStringContainsString( 'WHERE', $db->last_query );
		$this->assertStringContainsString( 'school_name IN', $db->last_query );
	}

	public function test_query_applies_year_filter_to_sql(): void {
		$filters         = $this->empty_filters();
		$filters['year'] = array( 2024 );
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $filters );

		$this->assertStringContainsString( 'year IN', $db->last_query );
	}

	public function test_query_applies_date_range_to_sql(): void {
		$filters              = $this->empty_filters();
		$filters['date_from'] = '2024-01-01';
		$filters['date_to']   = '2024-12-31';
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $filters );

		$this->assertStringContainsString( 'issue_date >= %s', $db->last_query );
		$this->assertStringContainsString( 'issue_date <= %s', $db->last_query );
	}

	public function test_query_applies_combined_filters_to_sql(): void {
		$filters = array(
			'schools'           => array( 'School A' ),
			'certificate_types' => array( 'Winner' ),
			'year'              => array( 2024 ),
			'date_from'         => '2024-04-01',
			'date_to'           => '',
			'email_search'      => '',
			'emails'            => array(),
		);
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $filters );

		$this->assertStringContainsString( 'school_name IN', $db->last_query );
		$this->assertStringContainsString( 'certificate_type IN', $db->last_query );
		$this->assertStringContainsString( 'year IN', $db->last_query );
		$this->assertStringContainsString( 'issue_date >= %s', $db->last_query );
		$this->assertStringContainsString( 'AND', $db->last_query );
	}

	public function test_query_uses_empty_alias_in_column_references(): void {
		$filters            = $this->empty_filters();
		$filters['schools'] = array( 'A' );
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $filters );

		$this->assertStringContainsString( 'school_name IN', $db->last_query );
		$this->assertStringNotContainsString( '.school_name', $db->last_query );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_cert_count()
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_count_returns_zero_when_table_not_found(): void {
		$this->useSequentialWpdb( null );

		$this->assertSame( 0, cg_admin_cert_count( $this->empty_filters() ) );
	}

	public function test_count_returns_integer_with_empty_filters(): void {
		$this->useSequentialWpdb( 'wp_cg_students', '42' );

		$this->assertSame( 42, cg_admin_cert_count( $this->empty_filters() ) );
	}

	public function test_count_returns_zero_when_no_rows(): void {
		$this->useSequentialWpdb( 'wp_cg_students', null );

		$this->assertSame( 0, cg_admin_cert_count( $this->empty_filters() ) );
	}

	public function test_count_sql_contains_count_star(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', '0' );

		cg_admin_cert_count( $this->empty_filters() );

		$this->assertStringContainsString( 'SELECT COUNT(*)', $db->last_query );
	}

	public function test_count_applies_school_filter(): void {
		$filters            = $this->empty_filters();
		$filters['schools'] = array( 'Test School' );
		$db = $this->useSequentialWpdb( 'wp_cg_students', '5' );

		cg_admin_cert_count( $filters );

		$this->assertStringContainsString( 'WHERE', $db->last_query );
		$this->assertStringContainsString( 'school_name IN', $db->last_query );
	}

	public function test_count_no_where_with_empty_filters(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', '10' );

		cg_admin_cert_count( $this->empty_filters() );

		$this->assertStringNotContainsString( 'WHERE', $db->last_query );
	}

	public function test_count_has_no_limit_or_offset(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', '10' );

		cg_admin_cert_count( $this->empty_filters() );

		$this->assertStringNotContainsString( 'LIMIT', $db->last_query );
		$this->assertStringNotContainsString( 'OFFSET', $db->last_query );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_handle_admin_cert_zip_download()
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_zip_handler_returns_early_without_post_var(): void {
		$_POST = array();

		cg_handle_admin_cert_zip_download();

		$this->assertTrue( true );
	}

	public function test_zip_handler_calls_nonce_check_when_post_var_set(): void {
		$_POST['cg_download_zip'] = '1';

		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'cg_admin_cert_zip', '_wpnonce_cg_zip' )
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		Functions\when( 'wp_die' )->alias( function () {
			throw new \RuntimeException( 'wp_die' );
		} );
		Functions\when( 'esc_html__' )->returnArg();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'wp_die' );

		cg_handle_admin_cert_zip_download();
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_handle_admin_individual_cert_download()
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_individual_handler_returns_early_without_action(): void {
		$_GET = array();

		cg_handle_admin_individual_cert_download();

		$this->assertTrue( true );
	}

	public function test_individual_handler_returns_early_with_wrong_action(): void {
		$_GET['action'] = 'something_else';

		cg_handle_admin_individual_cert_download();

		$this->assertTrue( true );
	}

	public function test_individual_handler_checks_nonce_and_permission(): void {
		$_GET['action'] = 'cg_admin_download_cert';
		$_GET['sql_id'] = '42';

		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'cg_admin_dl_cert_42' )
			->andReturn( 1 );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );

		Functions\when( 'wp_die' )->alias( function () {
			throw new \RuntimeException( 'wp_die' );
		} );
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'absint' )->alias( function ( $v ) {
			return abs( (int) $v );
		} );

		$this->expectException( \RuntimeException::class );

		cg_handle_admin_individual_cert_download();
	}

	public function test_individual_handler_dies_when_student_not_found(): void {
		$_GET['action'] = 'cg_admin_download_cert';
		$_GET['sql_id'] = '99';

		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'absint' )->alias( function ( $v ) {
			return abs( (int) $v );
		} );

		// get_var for table check, then get_row returns null
		$this->useSequentialWpdb( null );

		Functions\when( 'wp_die' )->alias( function ( $msg ) {
			throw new \RuntimeException( $msg );
		} );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Student not found.' );

		cg_handle_admin_individual_cert_download();
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_cert_part_plan()
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_part_plan_zero_total_returns_zero_parts(): void {
		$plan = cg_admin_cert_part_plan( 0, 200 );

		$this->assertSame( 0, $plan['num_parts'] );
		$this->assertSame( 200, $plan['part_size'] );
	}

	public function test_part_plan_200_returns_one_part(): void {
		$plan = cg_admin_cert_part_plan( 200, 200 );

		$this->assertSame( 1, $plan['num_parts'] );
	}

	public function test_part_plan_201_returns_two_parts(): void {
		$plan = cg_admin_cert_part_plan( 201, 200 );

		$this->assertSame( 2, $plan['num_parts'] );
	}

	public function test_part_plan_850_returns_five_parts(): void {
		$plan = cg_admin_cert_part_plan( 850, 200 );

		// ceil(850/200) = ceil(4.25) = 5
		$this->assertSame( 5, $plan['num_parts'] );
	}

	public function test_part_plan_exact_multiple_returns_correct(): void {
		$plan = cg_admin_cert_part_plan( 400, 200 );

		$this->assertSame( 2, $plan['num_parts'] );
	}

	public function test_part_plan_part_size_floored_to_one(): void {
		$plan = cg_admin_cert_part_plan( 5, 0 );

		$this->assertSame( 1, $plan['part_size'] );
		$this->assertSame( 5, $plan['num_parts'] );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_parse_memory_mb()
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_parse_memory_handles_megabytes(): void {
		$this->assertSame( 256, cg_admin_parse_memory_mb( '256M' ) );
	}

	public function test_parse_memory_handles_gigabytes(): void {
		$this->assertSame( 1024, cg_admin_parse_memory_mb( '1G' ) );
	}

	public function test_parse_memory_handles_kilobytes(): void {
		// 131072K / 1024 = 128 MB
		$this->assertSame( 128, cg_admin_parse_memory_mb( '131072K' ) );
	}

	public function test_parse_memory_handles_unlimited(): void {
		$this->assertSame( -1, cg_admin_parse_memory_mb( '-1' ) );
	}

	public function test_parse_memory_handles_bare_bytes(): void {
		// 1048576 bytes = exactly 1 MB
		$this->assertSame( 1, cg_admin_parse_memory_mb( '1048576' ) );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_cert_query() — stable ORDER BY
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_query_uses_stable_order_by_with_id_tiebreaker(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $this->empty_filters() );

		$this->assertStringContainsString( 'ORDER BY student_name ASC, id ASC', $db->last_query );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// Part boundaries (stable pagination)
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_part_boundary_part_zero_uses_offset_zero(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $this->empty_filters(), 200, 0 );

		$args = $db->last_prepare_args;
		$this->assertSame( 200, $args[ count( $args ) - 2 ] ); // limit
		$this->assertSame( 0,   $args[ count( $args ) - 1 ] ); // offset
	}

	public function test_part_boundary_part_one_uses_correct_offset(): void {
		$db = $this->useSequentialWpdb( 'wp_cg_students', array() );

		cg_admin_cert_query( $this->empty_filters(), 200, 200 );

		$args = $db->last_prepare_args;
		$this->assertSame( 200, $args[ count( $args ) - 2 ] ); // limit
		$this->assertSame( 200, $args[ count( $args ) - 1 ] ); // offset
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_cert_zip_label()
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_zip_label_single_part_has_no_part_suffix(): void {
		Functions\when( 'sanitize_title' )->returnArg();

		$label = cg_admin_cert_zip_label( $this->empty_filters(), 0, 1 );

		$this->assertStringNotContainsString( 'part', $label );
	}

	public function test_zip_label_multi_part_includes_part_info(): void {
		Functions\when( 'sanitize_title' )->returnArg();

		$label = cg_admin_cert_zip_label( $this->empty_filters(), 0, 5 );

		$this->assertStringContainsString( 'part1of5', $label );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// cg_admin_cert_zip_download_name()
	// ═══════════════════════════════════════════════════════════════════════════

	public function test_zip_download_name_uses_filter_year(): void {
		Functions\when( 'sanitize_title' )->returnArg();
		Functions\when( 'sanitize_file_name' )->returnArg();

		$filters         = $this->empty_filters();
		$filters['year'] = array( 2026 );

		$name = cg_admin_cert_zip_download_name( $filters, 0, 1 );

		$this->assertStringContainsString( '2026', $name );
	}

	public function test_zip_download_name_ends_with_dot_zip(): void {
		Functions\when( 'sanitize_title' )->returnArg();
		Functions\when( 'sanitize_file_name' )->returnArg();

		$name = cg_admin_cert_zip_download_name( $this->empty_filters(), 0, 1 );

		$this->assertStringEndsWith( '.zip', $name );
	}
}

// ── Test double: WpdbFake with queued return values ──────────────────────────

class SequentialWpdbFake extends WpdbFake {

	/** @var list<mixed> */
	private array $queue = array();

	/** Last args passed to prepare() — lets tests assert LIMIT/OFFSET values. */
	public array $last_prepare_args = array();

	public function prepare( string $query, mixed ...$args ): string {
		$this->last_query        = $query;
		$this->last_prepare_args = $args;
		return $query;
	}

	public function queue_returns( mixed ...$values ): void {
		$this->queue = $values;
	}

	private function next(): mixed {
		if ( ! empty( $this->queue ) ) {
			return array_shift( $this->queue );
		}
		return $this->return_value;
	}

	public function get_var( string $sql ): mixed {
		$this->last_query = $sql;
		return $this->next();
	}

	public function get_results( string $sql, string $output = OBJECT ): mixed {
		$this->last_query = $sql;
		return $this->next() ?? array();
	}
}
