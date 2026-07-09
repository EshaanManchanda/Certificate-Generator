<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Tests\Unit\Database\WpdbFake;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for Cause F — CG_Serial_Number_Generator::update_student_serial()
 * used to UPDATE ... WHERE email = %s, clobbering every sibling sharing that address
 * with whichever serial was generated last. Must now key on an exact row id/wp_post_id.
 *
 * @covers ::CG_Serial_Number_Generator
 */
class SerialGeneratorTest extends TestCase {

    private WpdbFake $db;

    public static function setUpBeforeClass(): void {
        require_once dirname(__DIR__, 3) . '/includes/Services/serial-generator.php';
    }

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        Functions\when('get_option')->alias(fn($key, $default = false) => $default);
        Functions\when('current_time')->justReturn('2026-07-09 00:00:00');
        Functions\when('sanitize_key')->alias(function (string $key): string {
            $key = strtolower($key);
            return preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '';
        });

        global $wpdb;
        $this->db               = new WpdbFake();
        $this->db->return_value = 'wp_cg_students'; // table-exists SHOW TABLES check
        $this->db->options      = 'wp_options';
        $wpdb                   = $this->db;
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_row_with_id_updates_by_id_not_email(): void {
        $gen = \CG_Serial_Number_Generator::get_instance();
        $gen->generate('Participation', [
            'id'           => 80,
            'email'        => 'shwetakapur85@yahoo.co.in',
            'student_name' => 'Anahita Arora',
        ]);

        $this->assertSame(['id' => 80], $this->db->last_update['where']);
        $this->assertArrayNotHasKey('email', $this->db->last_update['where']);
    }

    public function test_row_with_only_wp_post_id_updates_by_wp_post_id_not_email(): void {
        $gen = \CG_Serial_Number_Generator::get_instance();
        $gen->generate('Participation', [
            'wp_post_id'   => 555,
            'email'        => 'shwetakapur85@yahoo.co.in',
            'student_name' => 'Shivaay Arora',
        ]);

        $this->assertSame(['wp_post_id' => 555], $this->db->last_update['where']);
        $this->assertArrayNotHasKey('email', $this->db->last_update['where']);
    }

    public function test_row_with_no_identity_never_mutates_by_email_or_name(): void {
        $gen = \CG_Serial_Number_Generator::get_instance();
        $gen->generate('Participation', [
            'email'        => 'shwetakapur85@yahoo.co.in',
            'student_name' => 'Younger Sibling',
        ]);

        // No id/wp_post_id => must skip the write entirely rather than guess by
        // email/name, which would hit every sibling sharing that identity.
        $this->assertSame([], $this->db->last_update);
    }

    public function test_two_siblings_same_email_get_independently_targeted_updates(): void {
        $gen = \CG_Serial_Number_Generator::get_instance();

        $gen->generate('Participation', ['id' => 80, 'email' => 'shwetakapur85@yahoo.co.in']);
        $first_where = $this->db->last_update['where'];

        $gen->generate('Participation', ['id' => 94, 'email' => 'shwetakapur85@yahoo.co.in']);
        $second_where = $this->db->last_update['where'];

        $this->assertNotSame($first_where, $second_where);
        $this->assertSame(80, $first_where['id']);
        $this->assertSame(94, $second_where['id']);
    }
}
