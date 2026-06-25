<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Tests\Unit\Database\WpdbFake;
use PHPUnit\Framework\TestCase;

/**
 * @covers ::cg_export_filter_where
 */
class ExportFilterWhereTest extends TestCase {

    private WpdbFake $wpdb;

    public static function setUpBeforeClass(): void {
        // filters-api must be loaded first so cg_build_recipient_filter_sql exists
        if ( ! function_exists( 'cg_build_recipient_filter_sql' ) ) {
            require_once dirname( __DIR__, 3 ) . '/includes/Admin/filters-api.php';
        }
        if ( ! function_exists( 'cg_export_filter_where' ) ) {
            require_once dirname( __DIR__, 3 ) . '/includes/Services/bulk-export.php';
        }
    }

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $this->wpdb      = new WpdbFake();
        $GLOBALS['wpdb'] = $this->wpdb;
        $_POST           = array(); // reset superglobal
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'wp_nonce_field' )->justReturn( '' );
        Functions\when( 'esc_html_e' )->justReturn( null );
        Functions\when( 'esc_html__' )->returnArg();
        Functions\when( 'esc_attr' )->returnArg();
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( 'delete_transient' )->justReturn( true );
    }

    protected function tearDown(): void {
        $_POST = array();
        Monkey\tearDown();
        unset( $GLOBALS['wpdb'] );
        parent::tearDown();
    }

    // ── Empty POST ────────────────────────────────────────────────────────────

    public function test_empty_post_returns_empty_where_and_no_params(): void {
        $_POST = array();

        [ $where, $params ] = cg_export_filter_where();

        $this->assertSame( '', $where );
        $this->assertSame( array(), $params );
    }

    // ── Year filter ───────────────────────────────────────────────────────────

    public function test_year_in_post_produces_where_with_year_fragment(): void {
        $_POST = array( 'filter_year' => array( '2024' ) );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertStringStartsWith( ' WHERE ', $where );
        $this->assertStringContainsString( 'year IN', $where );
        $this->assertSame( 2024, $params[0] );
    }

    public function test_multiple_years_in_post(): void {
        $_POST = array( 'filter_year' => array( '2023', '2024', '2025' ) );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertStringContainsString( 'year IN', $where );
        $this->assertCount( 3, $params );
        $this->assertSame( array( 2023, 2024, 2025 ), $params );
    }

    public function test_year_not_array_is_ignored(): void {
        $_POST = array( 'filter_year' => '2024' ); // string, not array

        [ $where, $params ] = cg_export_filter_where();

        $this->assertSame( '', $where );
        $this->assertSame( array(), $params );
    }

    // ── School filter ─────────────────────────────────────────────────────────

    public function test_school_in_post_produces_school_fragment(): void {
        $_POST = array( 'filter_school' => array( 'Sunrise Academy' ) );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertStringContainsString( 'school_name IN', $where );
        $this->assertSame( array( 'Sunrise Academy' ), $params );
    }

    public function test_school_not_array_is_ignored(): void {
        $_POST = array( 'filter_school' => 'Sunrise Academy' );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertSame( '', $where );
        $this->assertSame( array(), $params );
    }

    public function test_multiple_schools(): void {
        $_POST = array( 'filter_school' => array( 'School A', 'School B' ) );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertCount( 2, $params );
    }

    // ── Cert type filter ──────────────────────────────────────────────────────

    public function test_cert_type_in_post_produces_cert_type_fragment(): void {
        $_POST = array( 'filter_cert_type' => array( 'Olympiad Winner' ) );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertStringContainsString( 'certificate_type IN', $where );
        $this->assertSame( array( 'Olympiad Winner' ), $params );
    }

    public function test_cert_type_not_array_is_ignored(): void {
        $_POST = array( 'filter_cert_type' => 'Winner' );

        [ $where, ] = cg_export_filter_where();

        $this->assertSame( '', $where );
    }

    // ── Date range filters ────────────────────────────────────────────────────

    public function test_valid_date_from_is_included(): void {
        $_POST = array( 'filter_date_from' => '2024-01-01' );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertStringContainsString( 'issue_date >=', $where );
        $this->assertSame( array( '2024-01-01' ), $params );
    }

    public function test_valid_date_to_is_included(): void {
        $_POST = array( 'filter_date_to' => '2024-12-31' );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertStringContainsString( 'issue_date <=', $where );
        $this->assertSame( array( '2024-12-31' ), $params );
    }

    public function test_date_from_and_date_to_both_included(): void {
        $_POST = array(
            'filter_date_from' => '2024-04-01',
            'filter_date_to'   => '2024-04-30',
        );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertStringContainsString( 'issue_date >=', $where );
        $this->assertStringContainsString( 'issue_date <=', $where );
        $this->assertCount( 2, $params );
    }

    public function test_invalid_date_from_slash_format_is_rejected(): void {
        $_POST = array( 'filter_date_from' => '2024/01/01' );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertSame( '', $where );
        $this->assertSame( array(), $params );
    }

    public function test_invalid_date_to_non_date_string_is_rejected(): void {
        $_POST = array( 'filter_date_to' => 'not-a-date' );

        [ $where, $params ] = cg_export_filter_where();

        $this->assertSame( '', $where );
        $this->assertSame( array(), $params );
    }

    public function test_date_with_single_digit_month_rejected(): void {
        // Regex requires \d{4}-\d{2}-\d{2}
        $_POST = array( 'filter_date_from' => '2024-1-1' );

        [ $where, ] = cg_export_filter_where();

        $this->assertSame( '', $where );
    }

    public function test_date_with_extra_chars_rejected(): void {
        $_POST = array( 'filter_date_from' => '2024-01-01T00:00:00' );

        [ $where, ] = cg_export_filter_where();

        $this->assertSame( '', $where );
    }

    // ── Alias: default empty alias → no dot prefix ────────────────────────────

    public function test_default_empty_alias_produces_no_dot_prefix_in_where(): void {
        $_POST = array( 'filter_school' => array( 'Test School' ) );

        [ $where, ] = cg_export_filter_where(); // default alias = ''

        $this->assertStringNotContainsString( '.school_name', $where );
        $this->assertStringContainsString( 'school_name IN', $where );
    }

    public function test_explicit_alias_t_prefixes_columns(): void {
        $_POST = array( 'filter_school' => array( 'Test School' ) );

        [ $where, ] = cg_export_filter_where( 't' );

        $this->assertStringContainsString( 't.school_name IN', $where );
    }

    // ── WHERE string format ───────────────────────────────────────────────────

    public function test_where_string_starts_with_space_where_space(): void {
        $_POST = array( 'filter_year' => array( '2024' ) );

        [ $where, ] = cg_export_filter_where();

        $this->assertStringStartsWith( ' WHERE ', $where );
    }

    public function test_multiple_active_filters_joined_with_and(): void {
        $_POST = array(
            'filter_school'    => array( 'School A' ),
            'filter_cert_type' => array( 'Winner' ),
        );

        [ $where, ] = cg_export_filter_where();

        $this->assertStringContainsString( ' AND ', $where );
    }

    // ── Combined all active ───────────────────────────────────────────────────

    public function test_all_filters_combined_produces_correct_param_count(): void {
        $_POST = array(
            'filter_school'    => array( 'School A' ),
            'filter_cert_type' => array( 'Winner' ),
            'filter_year'      => array( '2024' ),
            'filter_date_from' => '2024-04-01',
            'filter_date_to'   => '2024-04-30',
        );

        [ $where, $params ] = cg_export_filter_where();

        // school(1) + cert_type(1) + year(1) + date_from(1) + date_to(1) = 5 params
        $this->assertCount( 5, $params );
        $this->assertStringContainsString( 'AND', $where );
    }

    public function test_all_filters_combined_correct_param_values(): void {
        $_POST = array(
            'filter_school'    => array( 'School A' ),
            'filter_cert_type' => array( 'Winner' ),
            'filter_year'      => array( '2024' ),
            'filter_date_from' => '2024-04-01',
            'filter_date_to'   => '2024-04-30',
        );

        [ , $params ] = cg_export_filter_where();

        $this->assertSame( 'School A', $params[0] );
        $this->assertSame( 'Winner', $params[1] );
        $this->assertSame( 2024, $params[2] );
        $this->assertSame( '2024-04-01', $params[3] );
        $this->assertSame( '2024-04-30', $params[4] );
    }
}
