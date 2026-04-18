<?php
/**
 * Email Queue Management for Certificate Generator
 * Handles bulk email sending with rate limiting
 *
 * @package Certificate Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create email queue table
 */
function certificate_generator_create_email_queue_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cert_email_queue';
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    error_log('Certificate Generator: Email queue table created/verified');
}

// Table is now created during plugin activation in certificate-generator.php
// Keeping function available for manual calls if needed

/**
 * Add email to queue
 *
 * @param int $certificate_id Certificate post ID
 * @param string $recipient_email Recipient email address
 * @param array $options Additional options
 * @return int|false Queue ID or false on failure
 */
function certificate_generator_queue_email($certificate_id, $recipient_email, $options = []) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cert_email_queue';

    // Get post info
    $post_type = get_post_type($certificate_id);
    if (!$post_type) {
        error_log("Certificate Generator: Cannot queue email - invalid post ID: $certificate_id");
        return false;
    }

    // Get recipient name
    $recipient_name = '';
    switch ($post_type) {
        case 'students':
            $recipient_name = get_post_meta($certificate_id, 'student_name', true);
            break;
        case 'teachers':
            $recipient_name = get_post_meta($certificate_id, 'teacher_name', true);
            break;
        case 'schools':
            $recipient_name = get_post_meta($certificate_id, 'school_name', true);
            break;
    }

    $certificate_type = get_post_meta($certificate_id, 'certificate_type', true);

    // Check if already in queue
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name
         WHERE certificate_id = %d
         AND recipient_email = %s
         AND status IN ('pending', 'sending')",
        $certificate_id,
        $recipient_email
    ));

    if ($existing) {
        error_log("Certificate Generator: Email already in queue for certificate $certificate_id");
        return $existing;
    }

    // Calculate scheduled time (immediate or delayed)
    $scheduled_time = isset($options['scheduled_time']) ? $options['scheduled_time'] : current_time('mysql');
    $priority = isset($options['priority']) ? $options['priority'] : 5;

    // Insert into queue
    $result = $wpdb->insert(
        $table_name,
        [
            'certificate_id' => $certificate_id,
            'recipient_email' => $recipient_email,
            'recipient_name' => $recipient_name,
            'post_type' => $post_type,
            'certificate_type' => $certificate_type,
            'status' => 'pending',
            'scheduled_time' => $scheduled_time,
            'priority' => $priority
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
    );

    if ($result) {
        $queue_id = $wpdb->insert_id;
        error_log("Certificate Generator: Email queued (ID: $queue_id) for certificate $certificate_id to $recipient_email");
        return $queue_id;
    }

    error_log("Certificate Generator: Failed to queue email for certificate $certificate_id");
    return false;
}

/**
 * Get next batch of emails to send
 *
 * @param int $limit Number of emails to get
 * @return array Array of queue items
 */
function certificate_generator_get_next_batch($limit = 10) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cert_email_queue';
    $current_time = current_time('mysql');

    $emails = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name
         WHERE status = 'pending'
         AND scheduled_time <= %s
         AND attempts < 3
         ORDER BY priority DESC, scheduled_time ASC, id ASC
         LIMIT %d",
        $current_time,
        $limit
    ));

    return $emails;
}

/**
 * Update queue item status
 *
 * @param int $queue_id Queue item ID
 * @param string $status New status (pending/sending/sent/failed)
 * @param string $error_message Optional error message
 * @return bool Success
 */
function certificate_generator_update_queue_status($queue_id, $status, $error_message = null) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cert_email_queue';

    $data = ['status' => $status];
    $format = ['%s'];

    if ($status === 'sent') {
        $data['sent_at'] = current_time('mysql');
        $format[] = '%s';
    }

    if ($error_message !== null) {
        $data['error_message'] = $error_message;
        $format[] = '%s';
    }

    // Increment attempts if sending or failed
    if (in_array($status, ['sending', 'failed'])) {
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET attempts = attempts + 1 WHERE id = %d",
            $queue_id
        ));
    }

    $result = $wpdb->update(
        $table_name,
        $data,
        ['id' => $queue_id],
        $format,
        ['%d']
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

    $stats = [
        'total' => 0,
        'pending' => 0,
        'sending' => 0,
        'sent' => 0,
        'failed' => 0,
        'oldest_pending' => null,
        'newest_sent' => null
    ];

    // Get counts by status
    $counts = $wpdb->get_results(
        "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
        OBJECT_K
    );

    foreach ($counts as $status => $row) {
        $stats[$status] = (int) $row->count;
        $stats['total'] += (int) $row->count;
    }

    // Get oldest pending
    $oldest_pending = $wpdb->get_var(
        "SELECT created_at FROM $table_name WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1"
    );
    if ($oldest_pending) {
        $stats['oldest_pending'] = $oldest_pending;
    }

    // Get newest sent
    $newest_sent = $wpdb->get_var(
        "SELECT sent_at FROM $table_name WHERE status = 'sent' ORDER BY sent_at DESC LIMIT 1"
    );
    if ($newest_sent) {
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
function certificate_generator_cleanup_queue($days = 30) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cert_email_queue';

    $date = date('Y-m-d H:i:s', strtotime("-$days days"));

    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE status = 'sent' AND sent_at < %s",
        $date
    ));

    if ($deleted) {
        error_log("Certificate Generator: Cleaned up $deleted old queue items");
    }

    return $deleted;
}

/**
 * Retry failed emails
 *
 * @param int $max_attempts Maximum attempts before giving up
 * @return int Number of emails reset to pending
 */
function certificate_generator_retry_failed_emails($max_attempts = 3) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cert_email_queue';

    $reset = $wpdb->query($wpdb->prepare(
        "UPDATE $table_name
         SET status = 'pending', error_message = NULL, scheduled_time = %s
         WHERE status = 'failed'
         AND attempts < %d",
        current_time('mysql'),
        $max_attempts
    ));

    if ($reset) {
        error_log("Certificate Generator: Reset $reset failed emails to pending");
    }

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
function certificate_generator_bulk_queue_emails($post_type, $post_ids = [], $skip_already_sent = false) {
    $results = [
        'queued' => 0,
        'skipped' => 0,
        'errors' => [],
        'unique_emails' => 0,
        'grouped_emails' => 0
    ];

    // Query posts
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids'
    ];

    if (!empty($post_ids)) {
        $args['post__in'] = array_map('intval', $post_ids);
    }

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        $results['errors'][] = 'No posts found';
        return $results;
    }

    // NEW: Group posts by email address
    $posts_by_email = [];

    foreach ($query->posts as $post_id) {
        // Get email
        $email = get_post_meta($post_id, 'email', true);

        if (empty($email) || !is_email($email)) {
            $results['skipped']++;
            continue;
        }

        // Check if already sent (only if skip_already_sent is true)
        if ($skip_already_sent && certificate_generator_email_already_sent($post_id, $email)) {
            $results['skipped']++;
            continue;
        }

        // Group by email
        if (!isset($posts_by_email[$email])) {
            $posts_by_email[$email] = [];
        }
        $posts_by_email[$email][] = $post_id;
    }

    // NEW: Queue one entry per unique email address
    // The certificate_generator_send_email function will handle grouping when processing
    foreach ($posts_by_email as $email => $certificate_ids) {
        $cert_count = count($certificate_ids);

        // Queue using the first certificate ID
        // When processed, certificate_generator_send_email will find and group all certificates for this email
        $first_cert_id = $certificate_ids[0];
        $queue_id = certificate_generator_queue_email($first_cert_id, $email);

        if ($queue_id) {
            $results['queued'] += $cert_count;  // Count all certificates
            $results['unique_emails']++;

            if ($cert_count > 1) {
                $results['grouped_emails']++;
                error_log("Certificate Generator Queue: Grouped {$cert_count} certificates for {$email} into queue entry {$queue_id}");
            }
        } else {
            $results['errors'][] = "Failed to queue certificates for $email (IDs: " . implode(', ', $certificate_ids) . ")";
        }
    }

    error_log("Certificate Generator: Bulk queued {$results['queued']} certificates via {$results['unique_emails']} unique emails ({$results['grouped_emails']} grouped), skipped {$results['skipped']}");

    return $results;
}
?>
