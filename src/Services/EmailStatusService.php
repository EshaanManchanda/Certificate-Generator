<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

/**
 * Merged badge-status source: latest event across cert_email_logs + cert_email_queue.
 * Replaces the old logs-only, status='sent'-only lookup so the badge reflects the
 * true current state (Sent / Failed / Sending / Queued / NotSent).
 */
class EmailStatusService {

	const STATUS_SENT     = 'Sent';
	const STATUS_FAILED   = 'Failed';
	const STATUS_SENDING  = 'Sending';
	const STATUS_QUEUED   = 'Queued';
	const STATUS_NOT_SENT = 'NotSent';

	/**
	 * Batched status lookup — single query pair, no N+1.
	 *
	 * @param string[] $emails Unique email addresses for the current page.
	 * @return array<string, array{status:string, last_error:string, attempts:int}>
	 *         Keyed by email address.
	 */
	public static function getBadgeStatuses( array $emails ): array {
		global $wpdb;

		$emails = array_values( array_unique( array_filter( $emails ) ) );
		if ( empty( $emails ) ) {
			return array();
		}

		$result = array_fill_keys(
			$emails,
			array( 'status' => self::STATUS_NOT_SENT, 'last_error' => '', 'attempts' => 0 )
		);

		$log_table   = $wpdb->prefix . 'cert_email_logs';
		$queue_table = $wpdb->prefix . 'cert_email_queue';
		$ph          = implode( ',', array_fill( 0, count( $emails ), '%s' ) );

		// Latest log entry per email (ORDER BY so first occurrence per email = most recent).
		$log_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				"SELECT recipient_email, status, error_message, created_at FROM $log_table WHERE recipient_email IN ($ph) ORDER BY created_at DESC",
				...$emails
			),
			ARRAY_A
		);

		$log_latest = array();
		foreach ( $log_rows as $r ) {
			if ( ! isset( $log_latest[ $r['recipient_email'] ] ) ) {
				$log_latest[ $r['recipient_email'] ] = $r;
			}
		}

		// Latest queue entry per email.
		$queue_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				"SELECT recipient_email, status, error_message, attempts, updated_at FROM $queue_table WHERE recipient_email IN ($ph) ORDER BY updated_at DESC",
				...$emails
			),
			ARRAY_A
		);

		$queue_latest = array();
		foreach ( $queue_rows as $r ) {
			if ( ! isset( $queue_latest[ $r['recipient_email'] ] ) ) {
				$queue_latest[ $r['recipient_email'] ] = $r;
			}
		}

		foreach ( $emails as $email ) {
			$log   = $log_latest[ $email ]   ?? null;
			$queue = $queue_latest[ $email ] ?? null;

			if ( ! $log && ! $queue ) {
				continue;
			}

			$log_ts   = $log   ? (int) strtotime( $log['created_at'] )   : 0;
			$queue_ts = $queue ? (int) strtotime( $queue['updated_at'] ) : 0;
			$use_q    = $queue_ts >= $log_ts;

			if ( $use_q && $queue ) {
				switch ( $queue['status'] ) {
					case 'sent':
						$result[ $email ]['status'] = self::STATUS_SENT;
						break;
					case 'failed':
						$result[ $email ]['status']     = self::STATUS_FAILED;
						$result[ $email ]['last_error'] = $queue['error_message'] ?? '';
						$result[ $email ]['attempts']   = (int) ( $queue['attempts'] ?? 0 );
						break;
					case 'sending':
						$result[ $email ]['status'] = self::STATUS_SENDING;
						break;
					default:
						$result[ $email ]['status'] = self::STATUS_QUEUED;
						break;
				}
			} elseif ( $log ) {
				switch ( $log['status'] ) {
					case 'sent':
						$result[ $email ]['status'] = self::STATUS_SENT;
						break;
					case 'failed':
					case 'bounced':
						$result[ $email ]['status']     = self::STATUS_FAILED;
						$result[ $email ]['last_error'] = $log['error_message'] ?? '';
						break;
					case 'queued':
						$result[ $email ]['status'] = self::STATUS_QUEUED;
						break;
					default:
						break;
				}
			}
		}

		return $result;
	}
}
