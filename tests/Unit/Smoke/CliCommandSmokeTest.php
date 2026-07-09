<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Smoke;

use PHPUnit\Framework\TestCase;

/**
 * Smoke test: includes/Cli/repair-siblings.php must never fatal when loaded
 * on a normal web request (WP_CLI undefined) — it is required unconditionally
 * via the optional_files lazy-loader on every page load, not just wp-cli runs.
 *
 * Runs in a separate process because the file registers a class + WP_CLI::add_command
 * at include time; isolating avoids polluting global state for other smoke tests.
 *
 * @runTestsInSeparateProcesses
 */
class CliCommandSmokeTest extends TestCase {

    public function test_loading_without_wp_cli_defined_does_not_fatal_or_declare_class(): void {
        $file = dirname(__DIR__, 3) . '/includes/Cli/repair-siblings.php';

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wp/');
        }

        require $file;

        $this->assertFalse(
            class_exists('CG_CLI_Repair_Siblings', false),
            'Command class should not be declared when WP_CLI is not defined (this file loads on every normal web request).'
        );
    }
}
