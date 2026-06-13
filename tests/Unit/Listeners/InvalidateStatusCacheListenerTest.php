<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Listeners;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Listeners\InvalidateStatusCacheListener;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Listeners\InvalidateStatusCacheListener
 */
class InvalidateStatusCacheListenerTest extends TestCase {

	protected function setUp(): void {
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_handle_deletes_cache_entry_for_recipient(): void {
		$deleted = array();

		Functions\when( 'wp_cache_delete' )->alias(
			function ( string $key, string $group ) use ( &$deleted ) {
				$deleted[] = array( $key, $group );
				return true;
			}
		);

		( new InvalidateStatusCacheListener() )->handle( array( 'recipient_email' => 'User@Example.COM' ) );

		$this->assertCount( 1, $deleted );
		$this->assertSame( array( 'user@example.com', 'cg_email_status' ), $deleted[0] );
	}

	public function test_handle_does_nothing_when_email_missing(): void {
		Functions\when( 'wp_cache_delete' )->alias( fn() => $this->fail( 'wp_cache_delete must not be called' ) );

		( new InvalidateStatusCacheListener() )->handle( array() );
		$this->addToAssertionCount( 1 );
	}

	public function test_handle_does_nothing_when_email_empty_string(): void {
		Functions\when( 'wp_cache_delete' )->alias( fn() => $this->fail( 'wp_cache_delete must not be called' ) );

		( new InvalidateStatusCacheListener() )->handle( array( 'recipient_email' => '   ' ) );
		$this->addToAssertionCount( 1 );
	}
}
