<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Filters;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::cg_year_from_issue_date
 */
class YearHelperTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if ( ! function_exists( 'cg_year_from_issue_date' ) ) {
            require_once dirname( __DIR__, 3 ) . '/includes/Core/field-schema.php';
        }
    }

    // ── Valid dates ───────────────────────────────────────────────────────────

    public function test_standard_date_returns_correct_year(): void {
        $this->assertSame( 2024, cg_year_from_issue_date( '2024-04-10' ) );
    }

    public function test_end_of_year_date(): void {
        $this->assertSame( 2025, cg_year_from_issue_date( '2025-12-31' ) );
    }

    public function test_start_of_year_date(): void {
        $this->assertSame( 2000, cg_year_from_issue_date( '2000-01-01' ) );
    }

    public function test_year_just_above_threshold_1971(): void {
        $this->assertSame( 1971, cg_year_from_issue_date( '1971-06-15' ) );
    }

    // ── Boundary: 1970 is rejected (not > 1970) ───────────────────────────────

    public function test_year_1970_returns_null(): void {
        $this->assertNull( cg_year_from_issue_date( '1970-12-31' ) );
    }

    public function test_year_1969_returns_null(): void {
        $this->assertNull( cg_year_from_issue_date( '1969-01-01' ) );
    }

    // ── Empty / null inputs ───────────────────────────────────────────────────

    public function test_null_input_returns_null(): void {
        $this->assertNull( cg_year_from_issue_date( null ) );
    }

    public function test_empty_string_returns_null(): void {
        $this->assertNull( cg_year_from_issue_date( '' ) );
    }

    public function test_whitespace_string_returns_null(): void {
        $this->assertNull( cg_year_from_issue_date( '   ' ) );
    }

    // ── Malformed inputs ─────────────────────────────────────────────────────

    public function test_zero_date_returns_null(): void {
        $this->assertNull( cg_year_from_issue_date( '0000-00-00' ) );
    }

    public function test_non_numeric_prefix_returns_null(): void {
        // substr(0,4) = 'abcd', intval = 0
        $this->assertNull( cg_year_from_issue_date( 'abcd-01-01' ) );
    }

    public function test_year_only_string_extracts_year(): void {
        // Only 4 chars, no month/day — still extracts the year
        $this->assertSame( 2024, cg_year_from_issue_date( '2024' ) );
    }

    public function test_slash_separated_date_still_extracts_year(): void {
        // substr still grabs first 4 chars
        $this->assertSame( 2023, cg_year_from_issue_date( '2023/06/01' ) );
    }
}
