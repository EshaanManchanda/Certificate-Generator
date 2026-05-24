<?php
/**
 * Email Queue Management for Certificate Generator
 * Handles bulk email sending with rate limiting
 *
 * @package Certificate Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create email queue table
 */
function certificate_generator_create_email_queue_table() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'cert_email_queue';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        certificate_id bigint(20) NOT NULL,
        recipient_email varchar(255) NOT NULL,
        recipient_name varchar(255) NOT NULL,
        post_type varchar(50) NOT NULL,
        certificate_type varchar(100),
        status varchar(20) DEFAULT 'pending',
        attempts int(11) DEFAULT 0,
        scheduled_time datetime DEFAULT NULL,
        sent_at datetime DEFAULT NULL,
        error_message text,
        priority int(11) DEFAULT 5,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY certificate_id (certificate_id),
        KEY status (status),
        KEY scheduled_time (scheduled_time),
        KEY recipient_email (recipient_email)
    ) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

// Table is now created during plugin activation in certificate-generator.php
// Keeping function available for manual calls if needed

/**
 * Add a wp_certificate_generator row to the email queue.
 *
 * @param int    $cg_id           Row ID in wp_certificate_generator.
 * @param string $recipient_email Recipient email address.
 * @param array  $options         Optional: scheduled_time, priority.
 * @return int|false Queue ID or false on failure.
 */
function certificate_generator_queue_email( $cg_id, $recipient_email, $options = array() ) {
	global $wpdb;

	$queue_table = $wpdb->prefix . 'cert_email_queue';
	$cg_table    = $wpdb->prefix . 'certificate_generator';

	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT id, student_name, certificate_type FROM $cg_table WHERE id = %d LIMIT 1", $cg_id ),
		ARRAY_A
	);

	if ( ! $row ) {
		cg_email_debug_log( "Cannot queue: no cg record for ID $cg_id" );
		return false;
	}

	$recipient_name   = $row['student_name'] ?? '';
	$certificate_type = $row['certificate_type'] ?? '';

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM $queue_table WHERE certificate_id = %d AND recipient_email = %s AND status IN ('pending','sending')",
			$cg_id,
			$recipient_email
		)
	);
	if ( $existing ) {
		return (int) $existing;
	}

	$scheduled_time = $options['scheduled_time'] ?? current_time( 'mysql' );
	$priority       = $options['priority'] ?? 5;

	$result = $wpdb->insert(
		$queue_table,
		array(
			'certificate_id'   => $cg_id,
			'recipient_email'  => $recipient_email,
			'recipient_name'   => $recipient_name,
			'post_type'        => '',
			'certificate_type' => $certificate_type,
			'status'           => 'pending',
			'scheduled_time'   => $scheduled_time,
			'priority'         => $priority,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
	);

	if ( $result ) {
		cg_email_debug_log( "Queued cg_id $cg_id → $recipient_email (queue #{$wpdb->insert_id})" );
		return $wpdb->insert_id;
	}

	cg_email_debug_log( "Failed to queue cg_id $cg_id" );
	return false;
}

/**
 * Get next batch of emails to send
 *
 * @param int $limit Number of emails to get
 * @return array Array of queue items
 */
function certificate_generator_get_next_batch( $limit = 10 ) {
	global $wpdb;

	$table_name   = $wpdb->prefix . 'cert_email_queue';
	$current_time = current_time( 'mysql' );

	$emails = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $table_name
         WHERE status = 'pending'
         AND scheduled_time <= %s
         AND attempts < 3
         ORDER BY priority DESC, scheduled_time ASC, id ASC
         LIMIT %d",
			$current_time,
			$limit
		)
	);

	return $emails;
}

/**
 * Update queue item status
 *
 * @param int    $queue_id Queue item ID
 * @param string $status New status (pending/sending/sent/failed)
 * @param string $error_message Optional error message
 * @return bool Success
 */
function certificate_generator_update_queue_status( $queue_id, $status, $error_message = null ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'cert_email_queue';

	$data   = array( 'status' => $status );
	$format = array( '%s' );

	if ( $status === 'sent' ) {
		$data['sent_at'] = current_time( 'mysql' );
		$format[]        = '%s';
	}

	if ( $error_message !== null ) {
		$data['error_message'] = $error_message;
		$format[]              = '%s';
	}

	// Increment attempts if sending or failed
	if ( in_array( $status, array( 'sending', 'failed' ) ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name SET attempts = attempts + 1 WHERE id = %d",
				$queue_id
			)
		);
	}

	$result = $wpdb->update(
		$table_name,
		$data,
		array( 'id' => $queue_id ),
		$format,
		array( '%d' )
	);

	return $result !== false;
}

/**
 * Get queue statistics
 *
 * @return array Queue stats
 */
function certificate_generator_get_queue_stats() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'cert_email_queue';

	$stats = array(
		'total'          => 0,
		'pending'        => 0,
		'sending'        => 0,
		'sent'           => 0,
		'failed'         => 0,
		'oldest_pending' => null,
		'newest_sent'    => null,
	);

	// Get counts by status
	$counts = $wpdb->get_results(
		"SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
		OBJECT_K
	);

	foreach ( $counts as $status => $row ) {
		$stats[ $status ] = (int) $row->count;
		$stats['total']  += (int) $row->count;
	}

	// Get oldest pending
	$oldest_pending = $wpdb->get_var(
		"SELECT created_at FROM $table_name WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1"
	);
	if ( $oldest_pending ) {
		$stats['oldest_pending'] = $oldest_pending;
	}

	// Get newest sent
	$newest_sent = $wpdb->get_var(
		"SELECT sent_at FROM $table_name WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 1"
	);
	if ( $newest_sent ) {
		$stats['newest_sent'] = $newest_sent;
	}

	return $stats;
}

/**
 * Clear completed queue items older than specified days
 *
 * @param int $days Number of days to keep
 * @return int Number of items deleted
 */
function certificate_generator_cleanup_queue( $days = 30 ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'cert_email_queue';

	$date = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

	$deleted = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name WHERE status = 'sent' AND sent_at < %s",
			$date
		)
	);

	return $deleted;
}

/**
 * Retry failed emails
 *
 * @param int $max_attempts Maximum attempts before giving up
 * @return int Number of emails reset to pending
 */
function certificate_generator_retry_failed_emails( $max_attempts = 3 ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'cert_email_queue';

	$reset = $wpdb->query(
		$wpdb->prepare(
			"UPDATE $table_name
         SET status = 'pending', error_message = NULL, scheduled_time = %s
         WHERE status = 'failed'
         AND attempts < %d",
			current_time( 'mysql' ),
			$max_attempts
		)
	);

	return $reset;
}

/**
 * Add bulk emails to queue
 * NOW WITH EMAIL GROUPING: Groups certificates by email before queuing
 *
 * @param string $post_type Post type (students/teachers/schools)
 * @param array $post_ids Optional specific post IDs
 * @param bool $skip_already_sent Whether to skip certificates that were already sent
 * @return array Result array with counts
 */
/**
 * Queue one email per unique recipient in wp_certificate_generator.
 *
 * @param string $post_type        Ignored — kept for backward-compat call sites.
 * @param array  $cg_ids           Optional subset of wp_certificate_generator IDs to process.
 * @param bool   $skip_already_sent Skip cg_ids already logged as sent.
 * @return array{queued:int,skipped:int,errors:string[],unique_emails:int,grouped_emails:int}
 */
function certificate_generator_bulk_queue_emails( $post_type = '', $cg_ids = array(), $skip_already_sent = false ) {
	global $wpdb;

	$results  = array(
		'queued'         => 0,
		'skipped'        => 0,
		'errors'         => array(),
		'unique_emails'  => 0,
		'grouped_emails' => 0,
	);
	$cg_table = $wpdb->prefix . 'certificate_generator';

	if ( ! empty( $cg_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $cg_ids ), '%d' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, email, student_name FROM $cg_table WHERE email != '' AND id IN ($placeholders) ORDER BY id ASC",
				array_map( 'intval', $cg_ids )
			),
			ARRAY_A
		);
	} else {
		$rows = $wpdb->get_results(
			"SELECT id, email, student_name FROM $cg_table WHERE email != '' ORDER BY id ASC",
			ARRAY_A
		);
	}

	if ( empty( $rows ) ) {
		$results['errors'][] = 'No certificate records with email found';
		return $results;
	}

	// Group by email.
	$by_email = array();
	foreach ( $rows as $row ) {
		if ( ! is_email( $row['email'] ) ) {
			++$results['skipped'];
			$results['errors'][] = "CG #{$row['id']} skipped: invalid email '{$row['email']}'";
			continue;
		}
		if ( $skip_already_sent && certificate_generator_email_already_sent( (int) $row['id'], $row['email'] ) ) {
			++$results['skipped'];
			continue;
		}
		$by_email[ $row['email'] ][] = (int) $row['id'];
	}

	// One queue entry per unique email (first cg_id as anchor; send_email will group all).
	foreach ( $by_email as $email => $ids ) {
		$queue_id = certificate_generator_queue_email( $ids[0], $email );
		if ( $queue_id ) {
			$results['queued'] += count( $ids );
			++$results['unique_emails'];
			if ( count( $ids ) > 1 ) {
				++$results['grouped_emails'];
				cg_email_debug_log( 'Grouped ' . count( $ids ) . " certs for $email → queue #$queue_id" );
			}
		} else {
			$results['errors'][] = "Failed to queue for $email (IDs: " . implode( ',', $ids ) . ')';
		}
	}

	cg_email_debug_log( "Bulk queued {$results['queued']} via {$results['unique_emails']} unique emails ({$results['grouped_emails']} grouped), skipped {$results['skipped']}" );
	return $results;
}
