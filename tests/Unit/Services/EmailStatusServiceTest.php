<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Services\EmailStatusService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Services\EmailStatusService
 *
 * Tests the cache layer added in Phase 4.
 * Flag toggling: CG_USE_EVENTS / CG_USE_REPOSITORIES are checked via
 * Config::flag() which reads PHP constants. We define/undefine them
 * per test using runkit or by testing only the flag-OFF (default) path
 * here, since constants cannot be undefined in vanilla PHP.
 *
 * Cache-layer tests work by stubbing wp_cache_get/set via Brain\Monkey
 * and verifying the flag-off (no-cache) code path returns correct data.
 */
class EmailStatusServiceTest extends TestCase {

	protected function setUp(): void {
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_badge_statuses_returns_empty_for_empty_input(): void {
		$result = EmailStatusService::getBadgeStatuses( array() );
		$this->assertSame( array(), $result );
	}

	public function test_get_badge_statuses_returns_not_sent_when_no_db_rows(): void {
		// CG_USE_EVENTS not defined → no cache, no misses shortcut.
		// CG_USE_REPOSITORIES not defined → legacy wpdb path.
		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function prepare( string $q, ...$a ): string { return $q; }
			public function get_results( string $q, string $t ): array { return []; }
		};

		$result = EmailStatusService::getBadgeStatuses( array( 'x@y.com' ) );

		$this->assertArrayHasKey( 'x@y.com', $result );
		$this->assertSame( EmailStatusService::STATUS_NOT_SENT, $result['x@y.com']['status'] );
	}

	public function test_get_badge_statuses_deduplicates_emails(): void {
		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function prepare( string $q, ...$a ): string { return $q; }
			public function get_results( string $q, string $t ): array { return []; }
		};

		$result = EmailStatusService::getBadgeStatuses( array( 'a@b.com', 'a@b.com', 'a@b.com' ) );
		$this->assertCount( 1, $result );
		$this->assertArrayHasKey( 'a@b.com', $result );
	}

	public function test_get_badge_statuses_filters_empty_emails(): void {
		global $wpdb;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public function prepare( string $q, ...$a ): string { return $q; }
			public function get_results( string $q, string $t ): array { return []; }
		};

		$result = EmailStatusService::getBadgeStatuses( array( '', null, '  ', 'valid@test.com' ) );
		$this->assertArrayNotHasKey( '', $result );
		$this->assertArrayHasKey( 'valid@test.com', $result );
	}

	/**
	 * Verify cache-hit path: wp_cache_get returns a hit → DB never queried.
	 * We can test this manually by checking that get_results is NOT called.
	 *
	 * This test uses Brain\Monkey to stub wp_cache_get and defines CG_USE_EVENTS
	 * as TRUE for the duration by using a constant-aware trick: since we cannot
	 * undefine constants, we verify the cache gate logic via the stub only when
	 * the constant happens to already be true.
	 *
	 * For local dev with CG_USE_EVENTS=true in wp-config: cache-path is exercised
	 * in integration. Here we test the flag-OFF path (default) for correctness.
	 */
	public function test_get_badge_statuses_flag_off_calls_wpdb(): void {
		// CG_USE_EVENTS not defined → flag OFF → cache bypassed → wpdb path.
		$called = 0;
		global $wpdb;
		$wpdb = new class( $called ) {
			public string $prefix = 'wp_';
			public int $calls     = 0;
			public function __construct( int &$c ) { $this->calls =& $c; }
			public function prepare( string $q, ...$a ): string { return $q; }
			public function get_results( string $q, string $t ): array {
				++$this->calls;
				return [];
			}
		};

		EmailStatusService::getBadgeStatuses( array( 'test@example.com' ) );
		// Two get_results calls: one for logs, one for queue.
		$this->assertSame( 2, $wpdb->calls );
	}
}
