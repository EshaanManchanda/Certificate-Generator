<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Smoke test: the plugin's core files load cleanly (no fatal) under a minimal
 * WP stub environment, and the functions/classes other code and other plugins
 * (chatbot-by-eshaan's ai_chatbot_cert_status, WP Dynamic Tags' save_post_*
 * hooks) depend on are still present and named correctly after any refactor.
 */
class CoreSymbolsSmokeTest extends TestCase {

    public static function setUpBeforeClass(): void {
        if (!defined('CERTIFICATE_GENERATOR_PATH')) {
            define('CERTIFICATE_GENERATOR_PATH', dirname(__DIR__, 3) . '/');
        }
        if (!defined('CG_USE_NEW_PDF')) {
            // Keep the legacy generate_certificate_pdf() wrapper out of scope —
            // this suite only asserts symbols exist, it doesn't invoke them.
            define('CG_USE_NEW_PDF', true);
        }

        require_once dirname(__DIR__, 3) . '/includes/Email/functions.php';
        require_once dirname(__DIR__, 3) . '/includes/Services/certificate-search.php';
        require_once dirname(__DIR__, 3) . '/includes/Services/serial-generator.php';
    }

    /** @dataProvider critical_function_provider */
    public function test_critical_function_exists(string $function): void {
        $this->assertTrue(function_exists($function), "Expected function {$function}() to exist after loading core files.");
    }

    public static function critical_function_provider(): array {
        return [
            // Email pipeline — the sibling-delivery bug lives here.
            'send email entry point'      => ['certificate_generator_send_email'],
            'legacy certs-by-email'       => ['cg_get_certs_by_email'],
            'per-row PDF generator'       => ['cg_generate_pdf_from_row'],
            'already-sent guard'          => ['certificate_generator_email_already_sent'],
            // PDF naming/generation — RC2 fix lives here.
            'canonical basename helper'   => ['cg_canonical_pdf_basename'],
            'human-readable zip filename' => ['cg_certificate_pdf_filename'],
            'certs upload dir'            => ['cg_certificates_dir'],
            'certs upload url'            => ['cg_certificates_url'],
            // Serial lookup — Cause E fix lives here.
            'existing-serial lookup'      => ['cg_find_existing_serial'],
            'insert certificate record'   => ['cg_insert_certificate_record'],
        ];
    }

    /** @dataProvider critical_class_provider */
    public function test_critical_class_exists(string $class): void {
        $this->assertTrue(class_exists($class), "Expected class {$class} to exist after loading core files.");
    }

    public static function critical_class_provider(): array {
        return [
            'serial number generator' => ['CG_Serial_Number_Generator'],
        ];
    }

    public function test_serial_generator_singleton_and_generate_method_exist(): void {
        $this->assertTrue(method_exists('CG_Serial_Number_Generator', 'get_instance'));
        $this->assertTrue(method_exists('CG_Serial_Number_Generator', 'generate'));
    }
}
