<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Listeners;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Listeners\AnalyticsListener;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Listeners\AnalyticsListener
 */
class AnalyticsListenerTest extends TestCase {

	protected function setUp(): void {
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_handle_increments_sent_counter(): void {
		$data = array( 'status' => 'sent', 'certificate_type' => 'Student' );
		$set  = array();

		Functions\when( 'sanitize_key' )->alias( fn( $v ) => strtolower( (string) $v ) );
		Functions\when( 'get_transient' )->justReturn( 4 );
		Functions\when( 'set_transient' )->alias( function ( string $k, int $v ) use ( &$set ) {
			$set[ $k ] = $v;
		} );

		( new AnalyticsListener() )->handle( $data );

		$this->assertSame( 5, $set['cg_analytics_sent_student'] );
	}

	public function test_handle_increments_failed_counter(): void {
		$data = array( 'status' => 'failed', 'certificate_type' => 'Teacher' );
		$set  = array();

		Functions\when( 'sanitize_key' )->alias( fn( $v ) => strtolower( (string) $v ) );
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->alias( function ( string $k, int $v ) use ( &$set ) {
			$set[ $k ] = $v;
		} );

		( new AnalyticsListener() )->handle( $data );

		$this->assertSame( 1, $set['cg_analytics_failed_teacher'] );
	}

	public function test_handle_ignores_unknown_status(): void {
		$data = array( 'status' => 'queued', 'certificate_type' => 'Student' );

		Functions\when( 'sanitize_key' )->alias( fn( $v ) => strtolower( (string) $v ) );
		Functions\when( 'get_transient' )->alias( fn() => $this->fail( 'get_transient must not be called' ) );
		Functions\when( 'set_transient' )->alias( fn() => $this->fail( 'set_transient must not be called' ) );

		( new AnalyticsListener() )->handle( $data );
		$this->addToAssertionCount( 1 ); // reached here ⇒ no transient calls
	}

	public function test_handle_defaults_to_unknown_type_when_missing(): void {
		$data = array( 'status' => 'sent' ); // no certificate_type key
		$set  = array();

		Functions\when( 'sanitize_key' )->alias( fn( $v ) => strtolower( (string) $v ) );
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->alias( function ( string $k, int $v ) use ( &$set ) {
			$set[ $k ] = $v;
		} );

		( new AnalyticsListener() )->handle( $data );

		$this->assertSame( 1, $set['cg_analytics_sent_unknown'] );
	}
}
