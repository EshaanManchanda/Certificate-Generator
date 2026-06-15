<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Services\SettingsService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Services\SettingsService
 */
class SettingsServiceTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── Password encryption round-trip ────────────────────────────────────────

    public function test_password_survives_encrypt_decrypt_round_trip(): void {
        $plaintext = 'my-smtp-secret-password';

        // Invoke via save_email_settings and get_smtp_config using mocked WP options.
        $stored = null;

        Functions\when('get_option')->alias(function ($key, $default = false) use (&$stored) {
            if ($key === 'cg_smtp_password') return $stored ?? $default;
            if ($key === 'certificate_generator_settings_email') return [];
            return $default;
        });

        Functions\when('update_option')->alias(function ($key, $value) use (&$stored) {
            if ($key === 'cg_smtp_password') $stored = $value;
            return true;
        });

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('sanitize_key')->returnArg();
        Functions\when('absint')->alias('intval');
        Functions\when('is_email')->justReturn(false); // not testing email here
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('get_bloginfo')->justReturn('Test Site');

        SettingsService::save_email_settings([
            'cg_email_transport'  => 'smtp',
            'cg_email_from_name'  => 'Test',
            'cg_email_from_email' => '',
            'cg_email_subject'    => 'Test',
            'cg_email_body'       => '',
            'cg_smtp_host'        => 'smtp.example.com',
            'cg_smtp_port'        => '587',
            'cg_smtp_username'    => 'user@example.com',
            'cg_smtp_password'    => $plaintext,
            'cg_smtp_encryption'  => 'tls',
        ]);

        // The stored value must NOT be the plaintext.
        $this->assertNotEquals($plaintext, $stored, 'Password must be encrypted at rest.');

        // Round-trip via get_smtp_config() must recover the original plaintext.
        $config = SettingsService::get_smtp_config();
        $this->assertSame($plaintext, $config['password'], 'Decrypted password must match original.');
    }

    public function test_blank_password_is_not_overwritten(): void {
        $stored = 'existing-encrypted-value';

        Functions\when('get_option')->alias(function ($key, $default = false) use (&$stored) {
            if ($key === 'cg_smtp_password') return $stored;
            if ($key === 'certificate_generator_settings_email') return [];
            return $default;
        });

        Functions\when('update_option')->alias(function ($key, $value) use (&$stored) {
            if ($key === 'cg_smtp_password') $stored = $value;
            return true;
        });

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('sanitize_key')->returnArg();
        Functions\when('absint')->alias('intval');
        Functions\when('is_email')->justReturn(false);
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('get_bloginfo')->justReturn('Test Site');

        SettingsService::save_email_settings([
            'cg_smtp_password' => '', // blank — must not overwrite
            'cg_email_transport' => 'smtp',
            'cg_email_from_name' => '',
            'cg_email_from_email' => '',
            'cg_email_subject' => '',
            'cg_email_body' => '',
            'cg_smtp_host' => '',
            'cg_smtp_port' => '587',
            'cg_smtp_username' => '',
            'cg_smtp_encryption' => 'tls',
        ]);

        $this->assertSame('existing-encrypted-value', $stored, 'Blank password submit must not overwrite stored value.');
    }

    // ── Transport validation ──────────────────────────────────────────────────

    public function test_invalid_transport_falls_back_to_wp_mail(): void {
        $saved_transport = null;

        Functions\when('get_option')->justReturn(false);
        Functions\when('update_option')->alias(function ($key, $value) use (&$saved_transport) {
            if ($key === 'cg_email_transport') $saved_transport = $value;
            return true;
        });
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('sanitize_key')->returnArg();
        Functions\when('absint')->alias('intval');
        Functions\when('is_email')->justReturn(false);
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('wp_unslash')->returnArg();
        Functions\when('get_bloginfo')->justReturn('Test Site');

        SettingsService::save_email_settings([
            'cg_email_transport'  => 'sendgrid', // invalid
            'cg_email_from_name'  => '',
            'cg_email_from_email' => '',
            'cg_email_subject'    => '',
            'cg_email_body'       => '',
            'cg_smtp_host'        => '',
            'cg_smtp_port'        => '587',
            'cg_smtp_username'    => '',
            'cg_smtp_password'    => '',
            'cg_smtp_encryption'  => 'tls',
        ]);

        $this->assertSame('wp_mail', $saved_transport, 'Unknown transport must default to wp_mail.');
    }
}
