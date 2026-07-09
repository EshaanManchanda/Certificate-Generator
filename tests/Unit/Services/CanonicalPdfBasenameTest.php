<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the sibling wrong-certificate / dropped-certificate bug.
 *
 * cg_canonical_pdf_basename() is the fix for RC2: same-email siblings with
 * wp_post_id=0 must never resolve to the same on-disk filename.
 *
 * @covers ::cg_canonical_pdf_basename
 */
class CanonicalPdfBasenameTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if (!defined('CERTIFICATE_GENERATOR_PATH')) {
            define('CERTIFICATE_GENERATOR_PATH', dirname(__DIR__, 3) . '/');
        }
        require_once dirname(__DIR__, 3) . '/includes/Services/certificate-search.php';
    }

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Real WP sanitize_key/sanitize_file_name behaviour, mocked via
        // Brain\Monkey (Patchwork requires these to stay undefined natively).
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

    public function test_post_id_greater_than_zero_uses_legacy_scheme(): void {
        $this->assertSame(
            'certificate_42.pdf',
            cg_canonical_pdf_basename(42, ['id' => 999, 'entity_type' => 'students'])
        );
    }

    public function test_post_id_zero_with_entity_identity_keys_on_entity_id(): void {
        $this->assertSame(
            'certificate_students_80.pdf',
            cg_canonical_pdf_basename(0, ['id' => 80, 'entity_type' => 'students'])
        );
    }

    public function test_three_siblings_same_email_same_type_get_three_distinct_basenames(): void {
        $anahita = cg_canonical_pdf_basename(0, ['id' => 80, 'entity_type' => 'students', 'email' => 'shwetakapur85@yahoo.co.in', 'certificate_type' => 'Participation']);
        $shivaay = cg_canonical_pdf_basename(0, ['id' => 94, 'entity_type' => 'students', 'email' => 'shwetakapur85@yahoo.co.in', 'certificate_type' => 'Participation']);
        $younger = cg_canonical_pdf_basename(0, ['id' => 95, 'entity_type' => 'students', 'email' => 'shwetakapur85@yahoo.co.in', 'certificate_type' => 'Participation']);

        $names = [$anahita, $shivaay, $younger];
        $this->assertSame(3, count(array_unique($names)), 'Siblings sharing email+type must never collide on one filename: ' . implode(', ', $names));
    }

    public function test_entity_type_is_sanitized_against_path_injection(): void {
        $basename = cg_canonical_pdf_basename(0, ['id' => 5, 'entity_type' => '../../etc/passwd']);
        $this->assertStringNotContainsString('/', $basename);
        $this->assertStringNotContainsString('..', $basename);
    }

    public function test_no_entity_id_falls_back_to_content_hash(): void {
        $basename = cg_canonical_pdf_basename(0, [
            'serial_number'    => 'CERT-001',
            'student_name'     => 'Test Student',
            'email'            => 'a@b.com',
            'certificate_type' => 'Participation',
        ]);
        $this->assertMatchesRegularExpression('/^certificate_[a-f0-9]{16}\.pdf$/', $basename);
    }

    public function test_hash_fallback_includes_email_to_reduce_same_name_collisions(): void {
        $a = cg_canonical_pdf_basename(0, ['student_name' => 'John Smith', 'certificate_type' => 'Participation', 'email' => 'parent1@example.com']);
        $b = cg_canonical_pdf_basename(0, ['student_name' => 'John Smith', 'certificate_type' => 'Participation', 'email' => 'parent2@example.com']);
        $this->assertNotSame($a, $b);
    }
}
