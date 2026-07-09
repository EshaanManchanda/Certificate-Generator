<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Placeholder for real WordPress-integration smoke tests.
 *
 * The current suite (tests/Unit) runs against Brain\Monkey stubs, not a real
 * WordPress core + DB, so it cannot catch: activation-time fatals, missing
 * table creation on activate, admin pages actually rendering, hooks actually
 * firing through WP's real hook system, or plugin-header validity as WP core
 * itself parses it.
 *
 * To make this real, wire in wp-env or the WP PHPUnit test scaffold
 * (WP_UnitTestCase) — https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/ —
 * point it at this plugin, then replace the skip below with actual assertions:
 *   - activate_plugin() does not throw / produce no PHP errors
 *   - CustomTables::instance()->all_tables_exist() is true post-activation
 *   - key admin pages (edit-students, cg-bulk-serials, etc.) render without fatal
 *   - do_action('save_post_students') triggers cg_sync_to_dynamic_tags()
 */
class PluginActivationSmokeTest extends TestCase {

    public function test_real_wp_activation_smoke_not_yet_wired(): void {
        $this->markTestSkipped(
            'Real WP-integration smoke tests need wp-env/WP_UnitTestCase — not yet installed. '
            . 'See class docblock for what to test once wired up. '
            . 'Until then, tests/Unit/Smoke covers the fast, stub-based checks.'
        );
    }
}
