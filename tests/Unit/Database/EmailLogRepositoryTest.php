<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Database\EmailLogRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Database\EmailLogRepository
 * @covers \CertificateGenerator\Database\Repository
 */
class EmailLogRepositoryTest extends TestCase {

	private WpdbFake $db;
	private EmailLogRepository $repo;

	protected function setUp(): void {
		Monkey\setUp();
		$this->db   = new WpdbFake();
		$this->repo = new EmailLogRepository( $this->db );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_sets_live_table_name(): void {
		$this->assertSame( 'wp_cert_email_logs', $this->repo->get_table() );
	}

	public function test_find_by_certificate_id_queries_correct_column(): void {
		$this->db->return_value = array( array( 'id' => 1, 'certificate_id' => 7 ) );
		$rows = $this->repo->find_by_certificate_id( 7 );
		$this->assertCount( 1, $rows );
		$this->assertStringContainsString( 'certificate_id', $this->db->last_query );
	}

	public function test_find_by_certificate_id_returns_empty_when_none(): void {
		$this->db->return_value = null;
		$this->assertSame( array(), $this->repo->find_by_certificate_id( 999 ) );
	}

	public function test_find_by_email_passes_limit_offset(): void {
		$this->db->return_value = array();
		$this->repo->find_by_email( 'a@b.com', 10, 5 );
		$this->assertStringContainsString( 'recipient_email', $this->db->last_query );
		$this->assertStringContainsString( 'LIMIT', $this->db->last_query );
	}

	public function test_count_by_status_returns_integer(): void {
		$this->db->return_value = '12';
		$this->assertSame( 12, $this->repo->count_by_status( 'sent' ) );
	}

	public function test_count_by_status_returns_zero_when_null(): void {
		$this->db->return_value = null;
		$this->assertSame( 0, $this->repo->count_by_status( 'failed' ) );
	}

	public function test_mark_sent_updates_status_column(): void {
		$this->db->return_value = 1;
		$result = $this->repo->mark_sent( 3 );
		$this->assertTrue( $result );
		$this->assertSame( array( 'status' => 'sent' ), $this->db->last_update['data'] );
	}

	public function test_mark_failed_sets_status_and_error_message(): void {
		$this->db->return_value = 1;
		$result = $this->repo->mark_failed( 3, 'SMTP timeout' );
		$this->assertTrue( $result );
		$this->assertSame( 'failed', $this->db->last_update['data']['status'] );
		$this->assertSame( 'SMTP timeout', $this->db->last_update['data']['error_message'] );
	}

	public function test_log_send_injects_sent_at_when_missing(): void {
		Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		$this->db->return_value = 5;
		$id = $this->repo->log_send( array( 'recipient_email' => 'a@b.com', 'status' => 'sent' ) );
		$this->assertSame( 5, $id );
		$this->assertSame( '2026-01-01 12:00:00', $this->db->last_insert['data']['sent_at'] );
	}

	public function test_find_by_emails_returns_rows_for_all_addresses(): void {
		$expected               = array(
			array( 'recipient_email' => 'a@b.com', 'status' => 'sent' ),
			array( 'recipient_email' => 'c@d.com', 'status' => 'failed' ),
		);
		$this->db->return_value = $expected;
		$rows = $this->repo->find_by_emails( array( 'a@b.com', 'c@d.com' ) );
		$this->assertSame( $expected, $rows );
		$this->assertStringContainsString( 'recipient_email IN', $this->db->last_query );
	}

	public function test_find_by_emails_returns_empty_for_empty_input(): void {
		$this->assertSame( array(), $this->repo->find_by_emails( array() ) );
	}

	public function test_log_send_preserves_caller_supplied_sent_at(): void {
		$this->db->return_value = 6;
		$id = $this->repo->log_send( array(
			'recipient_email' => 'a@b.com',
			'status'          => 'sent',
			'sent_at'         => '2025-06-01 09:00:00',
		) );
		$this->assertSame( 6, $id );
		$this->assertSame( '2025-06-01 09:00:00', $this->db->last_insert['data']['sent_at'] );
	}
}
