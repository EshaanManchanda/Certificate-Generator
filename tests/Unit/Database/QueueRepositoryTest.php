<?php
declare(strict_types=1);

namespace CertificateGenerator\Tests\Unit\Database;

use Brain\Monkey;
use Brain\Monkey\Functions;
use CertificateGenerator\Database\QueueRepository;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CertificateGenerator\Database\QueueRepository
 * @covers \CertificateGenerator\Database\Repository
 */
class QueueRepositoryTest extends TestCase {

	private WpdbFake $db;
	private QueueRepository $repo;

	protected function setUp(): void {
		Monkey\setUp();
		$this->db   = new WpdbFake();
		$this->repo = new QueueRepository( $this->db );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constructor_sets_live_table_name(): void {
		$this->assertSame( 'wp_cert_email_queue', $this->repo->get_table() );
	}

	public function test_find_pending_filters_by_status_and_scheduled_time(): void {
		Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		$this->db->return_value = array( array( 'id' => 1, 'status' => 'pending' ) );
		$rows = $this->repo->find_pending( 25 );
		$this->assertCount( 1, $rows );
		$this->assertStringContainsString( 'pending', $this->db->last_query );
		$this->assertStringContainsString( 'scheduled_time', $this->db->last_query );
	}

	public function test_find_pending_returns_empty_when_none(): void {
		Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		$this->db->return_value = null;
		$this->assertSame( array(), $this->repo->find_pending() );
	}

	public function test_find_by_certificate_id_queries_correct_column(): void {
		$this->db->return_value = array( array( 'id' => 2, 'certificate_id' => 10 ) );
		$rows = $this->repo->find_by_certificate_id( 10 );
		$this->assertCount( 1, $rows );
		$this->assertStringContainsString( 'certificate_id', $this->db->last_query );
	}

	public function test_has_pending_or_sending_returns_true_when_count_positive(): void {
		$this->db->return_value = '2';
		$this->assertTrue( $this->repo->has_pending_or_sending( 5, 'a@b.com' ) );
		$this->assertStringContainsString( "status IN ('pending','sending')", $this->db->last_query );
	}

	public function test_has_pending_or_sending_returns_false_when_count_zero(): void {
		$this->db->return_value = '0';
		$this->assertFalse( $this->repo->has_pending_or_sending( 5, 'a@b.com' ) );
	}

	public function test_mark_sending_sets_status(): void {
		$this->db->return_value = 1;
		$result = $this->repo->mark_sending( 4 );
		$this->assertTrue( $result );
		$this->assertSame( array( 'status' => 'sending' ), $this->db->last_update['data'] );
	}

	public function test_mark_sent_sets_status_and_sent_at(): void {
		Functions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		$this->db->return_value = 1;
		$this->repo->mark_sent( 4 );
		$this->assertSame( 'sent', $this->db->last_update['data']['status'] );
		$this->assertSame( '2026-01-01 12:00:00', $this->db->last_update['data']['sent_at'] );
	}

	public function test_mark_failed_sets_status_and_error(): void {
		$this->db->return_value = 1;
		$this->repo->mark_failed( 4, 'rate limit' );
		$this->assertSame( 'failed', $this->db->last_update['data']['status'] );
		$this->assertSame( 'rate limit', $this->db->last_update['data']['error_message'] );
	}

	public function test_increment_attempts_issues_update_query(): void {
		$this->db->return_value = true;
		$result = $this->repo->increment_attempts( 4 );
		$this->assertTrue( $result );
		$this->assertStringContainsString( 'attempts = attempts + 1', $this->db->last_query );
	}

	public function test_count_by_status_returns_integer(): void {
		$this->db->return_value = '3';
		$this->assertSame( 3, $this->repo->count_by_status( 'pending' ) );
	}

	public function test_find_by_emails_returns_rows_for_all_addresses(): void {
		$expected               = array(
			array( 'recipient_email' => 'a@b.com', 'status' => 'sending', 'attempts' => 1 ),
		);
		$this->db->return_value = $expected;
		$rows = $this->repo->find_by_emails( array( 'a@b.com' ) );
		$this->assertSame( $expected, $rows );
		$this->assertStringContainsString( 'recipient_email IN', $this->db->last_query );
	}

	public function test_find_by_emails_returns_empty_for_empty_input(): void {
		$this->assertSame( array(), $this->repo->find_by_emails( array() ) );
	}
}
