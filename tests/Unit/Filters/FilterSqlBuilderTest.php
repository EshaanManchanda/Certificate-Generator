<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Filters;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Tests\Unit\Database\WpdbFake;
use PHPUnit\Framework\TestCase;

/**
 * @covers ::cg_build_recipient_filter_sql
 * @covers ::cg_flush_filter_caches
 */
class FilterSqlBuilderTest extends TestCase {

    private WpdbFake $wpdb;

    public static function setUpBeforeClass(): void {
        if ( ! function_exists( 'cg_build_recipient_filter_sql' ) ) {
            require_once dirname( __DIR__, 3 ) . '/includes/Admin/filters-api.php';
        }
    }

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $this->wpdb          = new WpdbFake();
        $GLOBALS['wpdb']     = $this->wpdb;
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        unset( $GLOBALS['wpdb'] );
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns a fully-empty filters array (the $defaults shape). */
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

    // ── Empty input ───────────────────────────────────────────────────────────

    public function test_all_empty_filters_returns_no_fragments_and_no_params(): void {
        [ $frags, $params ] = cg_build_recipient_filter_sql( $this->empty_filters(), 't' );

        $this->assertSame( array(), $frags );
        $this->assertSame( array(), $params );
    }

    // ── Schools ───────────────────────────────────────────────────────────────

    public function test_single_school_produces_one_fragment_one_param(): void {
        $filters             = $this->empty_filters();
        $filters['schools']  = array( 'Sunrise Academy' );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 1, $frags );
        $this->assertStringContainsString( 't.school_name IN', $frags[0] );
        $this->assertStringContainsString( '%s', $frags[0] );
        $this->assertSame( array( 'Sunrise Academy' ), $params );
    }

    public function test_multiple_schools_produce_correct_placeholder_count(): void {
        $filters            = $this->empty_filters();
        $filters['schools'] = array( 'School A', 'School B', 'School C' );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 1, $frags );
        $this->assertSame( 3, substr_count( $frags[0], '%s' ) );
        $this->assertCount( 3, $params );
    }

    // ── Certificate types ─────────────────────────────────────────────────────

    public function test_single_cert_type(): void {
        $filters                      = $this->empty_filters();
        $filters['certificate_types'] = array( 'Olympiad Winner' );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 1, $frags );
        $this->assertStringContainsString( 't.certificate_type IN', $frags[0] );
        $this->assertSame( array( 'Olympiad Winner' ), $params );
    }

    public function test_multiple_cert_types(): void {
        $filters                      = $this->empty_filters();
        $filters['certificate_types'] = array( 'Winner', 'Participation' );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertSame( 2, substr_count( $frags[0], '%s' ) );
        $this->assertCount( 2, $params );
    }

    // ── Year ──────────────────────────────────────────────────────────────────

    public function test_single_year_uses_integer_placeholder(): void {
        $filters          = $this->empty_filters();
        $filters['year']  = array( 2024 );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 1, $frags );
        $this->assertStringContainsString( 't.year IN', $frags[0] );
        $this->assertStringContainsString( '%d', $frags[0] );
        $this->assertSame( array( 2024 ), $params );
    }

    public function test_multiple_years(): void {
        $filters         = $this->empty_filters();
        $filters['year'] = array( 2023, 2024, 2025 );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertSame( 3, substr_count( $frags[0], '%d' ) );
        $this->assertSame( array( 2023, 2024, 2025 ), $params );
    }

    public function test_year_values_are_cast_to_int(): void {
        $filters         = $this->empty_filters();
        $filters['year'] = array( '2024', '2025' ); // strings from POST

        [ , $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertSame( 2024, $params[0] );
        $this->assertSame( 2025, $params[1] );
    }

    // ── Date range ────────────────────────────────────────────────────────────

    public function test_date_from_produces_gte_fragment(): void {
        $filters              = $this->empty_filters();
        $filters['date_from'] = '2024-01-01';

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 1, $frags );
        $this->assertStringContainsString( 't.issue_date >= %s', $frags[0] );
        $this->assertSame( array( '2024-01-01' ), $params );
    }

    public function test_date_to_produces_lte_fragment(): void {
        $filters            = $this->empty_filters();
        $filters['date_to'] = '2024-12-31';

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 1, $frags );
        $this->assertStringContainsString( 't.issue_date <= %s', $frags[0] );
        $this->assertSame( array( '2024-12-31' ), $params );
    }

    public function test_date_from_and_date_to_together_produce_two_fragments(): void {
        $filters              = $this->empty_filters();
        $filters['date_from'] = '2024-04-01';
        $filters['date_to']   = '2024-04-30';

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 2, $frags );
        $this->assertSame( array( '2024-04-01', '2024-04-30' ), $params );
    }

    // ── Email search ──────────────────────────────────────────────────────────

    public function test_email_search_wraps_value_in_like_wildcards(): void {
        $filters                 = $this->empty_filters();
        $filters['email_search'] = 'gmail';

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertCount( 1, $frags );
        $this->assertStringContainsString( 't.email LIKE %s', $frags[0] );
        $this->assertStringStartsWith( '%', $params[0] );
        $this->assertStringEndsWith( '%', $params[0] );
        $this->assertStringContainsString( 'gmail', $params[0] );
    }

    public function test_email_search_escapes_like_special_chars(): void {
        $filters                 = $this->empty_filters();
        $filters['email_search'] = '50%off';

        [ , $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        // WpdbFake::esc_like uses addcslashes on _%\\ — % becomes \%
        $this->assertStringContainsString( '\%', $params[0] );
    }

    // ── Explicit email list ───────────────────────────────────────────────────

    public function test_emails_list_single(): void {
        $filters           = $this->empty_filters();
        $filters['emails'] = array( 'a@example.com' );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertStringContainsString( 't.email IN', $frags[0] );
        $this->assertSame( array( 'a@example.com' ), $params );
    }

    public function test_emails_list_multiple(): void {
        $filters           = $this->empty_filters();
        $filters['emails'] = array( 'a@example.com', 'b@example.com' );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        $this->assertSame( 2, substr_count( $frags[0], '%s' ) );
        $this->assertCount( 2, $params );
    }

    // ── Alias handling ────────────────────────────────────────────────────────

    public function test_empty_alias_produces_no_dot_prefix(): void {
        $filters            = $this->empty_filters();
        $filters['schools'] = array( 'Test School' );

        [ $frags, ] = cg_build_recipient_filter_sql( $filters, '' );

        $this->assertStringStartsWith( 'school_name', $frags[0] );
        $this->assertStringNotContainsString( '.school_name', $frags[0] );
    }

    public function test_alias_t_prefixes_all_column_references(): void {
        $filters              = $this->empty_filters();
        $filters['schools']   = array( 'A' );
        $filters['year']      = array( 2024 );
        $filters['date_from'] = '2024-01-01';

        [ $frags, ] = cg_build_recipient_filter_sql( $filters, 't' );

        foreach ( $frags as $frag ) {
            $this->assertMatchesRegularExpression( '/\bt\./', $frag );
        }
    }

    public function test_custom_alias_s_is_used(): void {
        $filters                      = $this->empty_filters();
        $filters['certificate_types'] = array( 'Winner' );

        [ $frags, ] = cg_build_recipient_filter_sql( $filters, 's' );

        $this->assertStringContainsString( 's.certificate_type', $frags[0] );
    }

    // ── Param ordering (combined) ─────────────────────────────────────────────

    public function test_combined_filters_produce_correct_fragment_count_and_param_order(): void {
        $filters = array(
            'schools'           => array( 'School A' ),
            'certificate_types' => array( 'Winner' ),
            'year'              => array( 2024 ),
            'date_from'         => '2024-01-01',
            'date_to'           => '2024-12-31',
            'email_search'      => 'test',
            'emails'            => array( 'x@y.com' ),
        );

        [ $frags, $params ] = cg_build_recipient_filter_sql( $filters, 't' );

        // 7 filter types → 7 fragments
        $this->assertCount( 7, $frags );

        // Params in order: school, cert_type, year, date_from, date_to, email_search, email
        $this->assertSame( 'School A', $params[0] );
        $this->assertSame( 'Winner', $params[1] );
        $this->assertSame( 2024, $params[2] );
        $this->assertSame( '2024-01-01', $params[3] );
        $this->assertSame( '2024-12-31', $params[4] );
        $this->assertStringContainsString( 'test', $params[5] );
        $this->assertSame( 'x@y.com', $params[6] );
    }

    // ── cg_flush_filter_caches ────────────────────────────────────────────────

    public function test_flush_deletes_schools_transient(): void {
        $deleted = array();
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
            $deleted[] = $key;
            return true;
        } );

        cg_flush_filter_caches();

        $this->assertContains( 'cg_unique_schools', $deleted );
    }

    public function test_flush_deletes_cert_types_transient(): void {
        $deleted = array();
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
            $deleted[] = $key;
            return true;
        } );

        cg_flush_filter_caches();

        $this->assertContains( 'cg_unique_cert_types', $deleted );
    }

    public function test_flush_deletes_years_transient(): void {
        $deleted = array();
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
            $deleted[] = $key;
            return true;
        } );

        cg_flush_filter_caches();

        $this->assertContains( 'cg_unique_years', $deleted );
    }

    public function test_flush_deletes_all_three_transients_in_one_call(): void {
        $deleted = array();
        Functions\when( 'delete_transient' )->alias( function( $key ) use ( &$deleted ) {
            $deleted[] = $key;
            return true;
        } );

        cg_flush_filter_caches();

        $this->assertCount( 3, $deleted );
        $this->assertContains( 'cg_unique_schools', $deleted );
        $this->assertContains( 'cg_unique_cert_types', $deleted );
        $this->assertContains( 'cg_unique_years', $deleted );
    }
}
