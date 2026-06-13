<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Listeners;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Database\EmailLogRepository;
use CertificateGenerator\Listeners\LogEmailListener;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Listeners\LogEmailListener
 */
class LogEmailListenerTest extends TestCase {

	protected function setUp(): void {
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_payload( string $status = 'sent', string $email = 'a@b.com' ): array {
		return array(
			'certificate_id'   => 7,
			'post_type'        => '',
			'recipient_email'  => $email,
			'recipient_name'   => 'Test User',
			'certificate_type' => 'Student',
			'email_subject'    => 'Your certificate',
			'email_body'       => '',
			'status'           => $status,
			'error_message'    => '',
		);
	}

	public function test_handle_calls_log_send_with_payload(): void {
		$data = $this->make_payload( 'sent' );

		$repo = $this->createMock( EmailLogRepository::class );
		$repo->expects( $this->once() )
			->method( 'log_send' )
			->with( $data )
			->willReturn( 5 );

		Functions\when( 'delete_transient' )->justReturn( true );

		$listener = new LogEmailListener( $repo );
		$listener->handle( $data );
	}

	public function test_handle_busts_transients_on_sent(): void {
		$data   = $this->make_payload( 'sent' );
		$busted = array();

		$repo = $this->createMock( EmailLogRepository::class );
		$repo->method( 'log_send' )->willReturn( 1 );

		// Capture which transients get busted — withConsecutive removed in Mockery 2.
		Functions\when( 'delete_transient' )->alias( function ( string $key ) use ( &$busted ) {
			$busted[] = $key;
			return true;
		} );

		( new LogEmailListener( $repo ) )->handle( $data );

		$this->assertContains( 'cg_sent_count_3600', $busted );
		$this->assertContains( 'cg_sent_count_60', $busted );
	}

	public function test_handle_does_not_bust_transients_on_failed(): void {
		$data = $this->make_payload( 'failed' );

		$repo = $this->createMock( EmailLogRepository::class );
		$repo->method( 'log_send' )->willReturn( 2 );

		Functions\when( 'delete_transient' )->alias( function () {
			$this->fail( 'delete_transient must not be called on failed send' );
		} );

		( new LogEmailListener( $repo ) )->handle( $data );
		$this->addToAssertionCount( 1 ); // verify we reached here without delete_transient
	}

	public function test_handle_does_not_bust_transients_on_queued(): void {
		$data = $this->make_payload( 'queued' );

		$repo = $this->createMock( EmailLogRepository::class );
		$repo->method( 'log_send' )->willReturn( 3 );

		Functions\when( 'delete_transient' )->alias( function () {
			$this->fail( 'delete_transient must not be called on queued status' );
		} );

		( new LogEmailListener( $repo ) )->handle( $data );
		$this->addToAssertionCount( 1 );
	}
}
