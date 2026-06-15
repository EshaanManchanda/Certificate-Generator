<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Email;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::cg_is_local_email
 */
class LocalEmailHelperTest extends TestCase {

    public static function setUpBeforeClass(): void {
        // Load the procedural helper once — no WP functions needed.
        require_once dirname(__DIR__, 3) . '/includes/Email/functions.php';
    }

    /** @dataProvider local_email_provider */
    public function test_cg_is_local_email(string $email, bool $expected): void {
        $this->assertSame($expected, cg_is_local_email($email));
    }

    public static function local_email_provider(): array {
        return [
            'single-label localhost'    => ['noreply@localhost', true],
            'single-label custom host'  => ['admin@mysite', true],
            'real email'                => ['user@example.com', false],
            'subdomain email'           => ['user@mail.example.com', false],
            'no at-sign'                => ['notanemail', false],
            'empty string'              => ['', false],
        ];
    }
}
