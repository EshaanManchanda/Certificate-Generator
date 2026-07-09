<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Email;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for RC1 (identity-discarding re-query) and RC3 (stale
 * pdf_path reuse) — the root causes of siblings receiving each other's names.
 *
 * generate_certificate_pdf() / generate_certificate_pdf_with_data() are left
 * undefined in this suite (function_exists()-gated in the source), so these
 * tests exercise the identity/trust guards that run BEFORE either is called —
 * no WP core or filesystem-writing stubs are needed for that slice of logic.
 *
 * @covers ::cg_generate_pdf_from_row
 */
class GeneratePdfFromRowTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if (!defined('CERTIFICATE_GENERATOR_PATH')) {
            define('CERTIFICATE_GENERATOR_PATH', dirname(__DIR__, 3) . '/');
        }
        // Flip the (default-off) v8 flag so certificate-search.php's legacy
        // generate_certificate_pdf() wrapper is left undefined (Config::flag()
        // short-circuits it) — keeps generation itself out of scope for this
        // suite while still loading the REAL cg_canonical_pdf_basename().
        if (!defined('CG_USE_NEW_PDF')) {
            define('CG_USE_NEW_PDF', true);
        }
        require_once dirname(__DIR__, 3) . '/includes/Services/certificate-search.php';
        require_once dirname(__DIR__, 3) . '/includes/Email/functions.php';
    }

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when('sanitize_key')->alias(function (string $key): string {
            $key = strtolower($key);
            return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
        });
        Functions\when('sanitize_file_name')->alias(function (string $name): string {
            return preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name) ?? '';
        });
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_sql_first_row_missing_id_fails_loudly_returns_null(): void {
        // No 'id' — this is exactly the shape that used to trigger a LIMIT-1
        // re-query collapsing siblings onto one row. Must now refuse outright.
        $row = [
            'entity_type'      => 'students',
            'email'            => 'shwetakapur85@yahoo.co.in',
            'certificate_type' => 'Participation',
            'student_name'     => 'Shivaay Arora',
        ];
        $this->assertNull(cg_generate_pdf_from_row($row));
    }

    public function test_sql_first_row_never_trusts_cached_pdf_path(): void {
        // Even if a (possibly sibling-collided) pdf_path is present and the file
        // exists on disk, an SQL-first row (has entity_type) must not short-circuit
        // on it — RC3 fix: only legacy rows may trust a cached path, and only when
        // its basename matches this row's own canonical name.
        $tmp = tempnam(sys_get_temp_dir(), 'cg_test_');
        $row = [
            'id'               => 80,
            'entity_type'      => 'students',
            'email'            => 'shwetakapur85@yahoo.co.in',
            'certificate_type' => 'Participation',
            'student_name'     => 'Anahita Arora',
            'wp_post_id'       => 0,
            'pdf_path'         => $tmp,
        ];
        // generate_certificate_pdf() is undefined in this suite, so the function
        // falls through to null instead of ever returning the stale cached path.
        $this->assertNull(cg_generate_pdf_from_row($row));
        unlink($tmp);
    }

    public function test_legacy_row_trusts_cached_path_only_when_basename_matches_canonical(): void {
        $dir = sys_get_temp_dir() . '/cg_test_' . uniqid();
        mkdir($dir);
        $path = $dir . '/certificate_123.pdf';
        file_put_contents($path, 'fake pdf bytes');

        // Legacy row (no 'entity_type'), wp_post_id=123 => canonical basename is
        // certificate_123.pdf, which matches — cached path should be trusted.
        $row = [
            'id'         => 1,
            'wp_post_id' => 123,
            'pdf_path'   => $path,
        ];
        $this->assertSame($path, cg_generate_pdf_from_row($row));

        unlink($path);
        rmdir($dir);
    }

    public function test_legacy_row_ignores_stale_mismatched_cached_path(): void {
        $dir = sys_get_temp_dir() . '/cg_test_' . uniqid();
        mkdir($dir);
        // File named certificate_0.pdf (the classic collision artifact) but this
        // row's own wp_post_id is 456 => canonical is certificate_456.pdf, mismatch.
        $stale_path = $dir . '/certificate_0.pdf';
        file_put_contents($stale_path, 'stale sibling-collided bytes');

        $row = [
            'id'         => 2,
            'wp_post_id' => 456,
            'pdf_path'   => $stale_path,
        ];
        // Must NOT return the stale path. Falls through to null since the real
        // generator functions are undefined in this suite.
        $this->assertNotSame($stale_path, cg_generate_pdf_from_row($row));
        $this->assertNull(cg_generate_pdf_from_row($row));

        unlink($stale_path);
        rmdir($dir);
    }

    public function test_row_is_used_directly_no_email_type_requery(): void {
        // Historically this function re-queried `WHERE email=%s AND certificate_type=%s
        // LIMIT 1`, silently substituting a DIFFERENT sibling's data. With no DB
        // wired up in this suite, any such re-query would fatal on `global $wpdb`
        // usage — reaching a clean null return proves no query path was taken for
        // an SQL-first row lacking 'id', confirming $row is trusted as-is.
        $row = [
            'entity_type'      => 'students',
            'email'            => 'shwetakapur85@yahoo.co.in',
            'certificate_type' => 'Participation',
        ];
        $this->assertNull(cg_generate_pdf_from_row($row));
    }
}
