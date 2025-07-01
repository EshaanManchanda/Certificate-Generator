<?php
/**
 * Email Log Functionality for Certificate Generator
 *
 * @package Certificate Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create email log table on plugin activation
 */
function certificate_generator_create_email_log_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cert_email_logs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cert_id INT NOT NULL,
        post_type VARCHAR(50) NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        recipient_name VARCHAR(255),
        certificate_type VARCHAR(255),
        email_subject VARCHAR(500),
        status ENUM('sent', 'failed') DEFAULT 'sent',
        error_message TEXT,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cert_id (cert_id),
        INDEX idx_post_type (post_type),
        INDEX idx_recipient_email (recipient_email),
        INDEX idx_sent_at (sent_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Log email send attempt
 *
 * @param int $cert_id Certificate post ID
 * @param string $recipient_email Recipient email address
 * @param string $recipient_name Recipient name
 * @param string $certificate_type Certificate type
 * @param string $email_subject Email subject
 * @param bool $success Whether email was sent successfully
 * @param string $error_message Error message if failed
 * @return int|false Log entry ID on success, false on failure
 */
function certificate_generator_log_email($cert_id, $recipient_email, $recipient_name = '', $certificate_type = '', $email_subject = '', $success = true, $error_message = '') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cert_email_logs';
    $post_type = get_post_type($cert_id);
    
    $data = [
        'cert_id' => $cert_id,
        'post_type' => $post_type,
        'recipient_email' => $recipient_email,
        'recipient_name' => $recipient_name,
        'certificate_type' => $certificate_type,
        'email_subject' => $email_subject,
        'status' => $success ? 'sent' : 'failed',
        'error_message' => $error_message
    ];
    
    $result = $wpdb->insert($table_name, $data);
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Get email logs with pagination and filtering
 *
 * @param array $args Query arguments
 * @return array Array containing logs and total count
 */
function certificate_generator_get_email_logs($args = []) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cert_email_logs';
    
    $defaults = [
        'per_page' => 20,
        'page' => 1,
        'post_type' => '',
        'status' => '',
        'search' => '',
        'date_from' => '',
        'date_to' => '',
        'orderby' => 'sent_at',
        'order' => 'DESC',
        'cert_id' => 0 // Add cert_id parameter for filtering by certificate
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    $where_conditions = ['1=1'];
    $where_values = [];
    
    // Filter by post type
    if (!empty($args['post_type'])) {
        $where_conditions[] = 'post_type = %s';
        $where_values[] = $args['post_type'];
    }
    
    // Filter by certificate ID
    if (!empty($args['cert_id'])) {
        $where_conditions[] = 'cert_id = %d';
        $where_values[] = $args['cert_id'];
    }
    
    // Filter by status
    if (!empty($args['status'])) {
        $where_conditions[] = 'status = %s';
        $where_values[] = $args['status'];
    }
    
    // Search in email or name
    if (!empty($args['search'])) {
        $where_conditions[] = '(recipient_email LIKE %s OR recipient_name LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    // Date range filter
    if (!empty($args['date_from'])) {
        $where_conditions[] = 'sent_at >= %s';
        $where_values[] = $args['date_from'] . ' 00:00:00';
    }
    
    if (!empty($args['date_to'])) {
        $where_conditions[] = 'sent_at <= %s';
        $where_values[] = $args['date_to'] . ' 23:59:59';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
    if (!empty($where_values)) {
        $count_sql = $wpdb->prepare($count_sql, $where_values);
    }
    $total_count = $wpdb->get_var($count_sql);
    
    // Get logs with pagination
    $offset = ($args['page'] - 1) * $args['per_page'];
    $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
    
    $logs_sql = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby LIMIT %d OFFSET %d";
    $logs_values = array_merge($where_values, [$args['per_page'], $offset]);
    
    $logs = $wpdb->get_results($wpdb->prepare($logs_sql, $logs_values));
    
    return [
        'logs' => $logs,
        'total' => $total_count,
        'pages' => ceil($total_count / $args['per_page'])
    ];
}

/**
 * Get email log statistics
 *
 * @return array Statistics array
 */
function certificate_generator_get_email_stats() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cert_email_logs';
    
    $stats = [];
    
    // Total emails sent
    $stats['total_sent'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'sent'");
    
    // Total emails failed
    $stats['total_failed'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
    
    // Emails sent today
    $stats['today'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE status = 'sent' AND DATE(sent_at) = %s",
        current_time('Y-m-d')
    ));
    
    // Emails sent this week
    $stats['this_week'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE status = 'sent' AND sent_at >= %s",
        date('Y-m-d', strtotime('monday this week'))
    ));
    
    // Emails sent this month
    $stats['this_month'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE status = 'sent' AND MONTH(sent_at) = %d AND YEAR(sent_at) = %d",
        date('n'),
        date('Y')
    ));
    
    // Success rate
    $total_emails = $stats['total_sent'] + $stats['total_failed'];
    $stats['success_rate'] = $total_emails > 0 ? round(($stats['total_sent'] / $total_emails) * 100, 2) : 0;
    
    return $stats;
}

/**
 * Check if email was already sent for a certificate
 *
 * @param int $cert_id Certificate post ID
 * @param string $email Recipient email
 * @return bool Whether email was already sent
 */
function certificate_generator_email_already_sent($cert_id, $email) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cert_email_logs';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE cert_id = %d AND recipient_email = %s AND status = 'sent'",
        $cert_id,
        $email
    ));
    
    return $count > 0;
}

/**
 * Delete old email logs (cleanup function)
 *
 * @param int $days_to_keep Number of days to keep logs
 * @return int Number of deleted records
 */
function certificate_generator_cleanup_email_logs($days_to_keep = 90) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cert_email_logs';
    
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
    
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE sent_at < %s",
        $cutoff_date
    ));
    
    return $deleted;
}

// Create table on plugin activation
// Table creation is now handled in the main plugin activation hook

// Schedule cleanup task
add_action('wp', 'certificate_generator_schedule_log_cleanup');
function certificate_generator_schedule_log_cleanup() {
    if (!wp_next_scheduled('certificate_generator_cleanup_logs')) {
        wp_schedule_event(time(), 'weekly', 'certificate_generator_cleanup_logs');
    }
}

// Cleanup hook
add_action('certificate_generator_cleanup_logs', function() {
    certificate_generator_cleanup_email_logs(90); // Keep logs for 90 days
});