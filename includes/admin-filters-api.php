<?php
/**
 * Admin Filters API
 * Helper functions for filtering and previewing recipients
 *
 * @package Certificate Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get unique school names for a post type
 *
 * @param string|array $post_types Post type(s) to query
 * @return array Array of unique school names
 */
function certificate_generator_get_unique_schools($post_types = ['students', 'teachers', 'schools']) {
    global $wpdb;

    $cache_key = 'cg_unique_schools';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    $query = $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value as school_name
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = 'school_name'
         AND pm.meta_value != ''
         AND p.post_type IN ($placeholders)
         AND p.post_status = 'publish'
         ORDER BY pm.meta_value ASC",
        ...$post_types
    );

    $results = $wpdb->get_col($query);
    $results = array_filter($results);

    set_transient($cache_key, $results, HOUR_IN_SECONDS);

    return $results;
}

/**
 * Get unique certificate types for a post type
 *
 * @param string|array $post_types Post type(s) to query
 * @return array Array of unique certificate types
 */
function certificate_generator_get_unique_certificate_types($post_types = ['students', 'teachers', 'schools']) {
    global $wpdb;

    $cache_key = 'cg_unique_cert_types';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    $query = $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value as certificate_type
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = 'certificate_type'
         AND pm.meta_value != ''
         AND p.post_type IN ($placeholders)
         AND p.post_status = 'publish'
         ORDER BY pm.meta_value ASC",
        ...$post_types
    );

    $results = $wpdb->get_col($query);
    $results = array_filter($results);

    set_transient($cache_key, $results, HOUR_IN_SECONDS);

    return $results;
}

/**
 * Invalidate filter dropdown caches when certificate-related posts are saved
 */
add_action('save_post', function($post_id) {
    if (in_array(get_post_type($post_id), ['students', 'teachers', 'schools', 'certificates'])) {
        delete_transient('cg_unique_schools');
        delete_transient('cg_unique_cert_types');
    }
});

/**
 * Get unique email addresses for a post type
 *
 * @param string|array $post_types Post type(s) to query
 * @return array Array of unique email addresses
 */
function certificate_generator_get_unique_emails($post_types = ['students', 'teachers', 'schools']) {
    global $wpdb;

    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }

    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    $query = $wpdb->prepare(
        "SELECT DISTINCT pm.meta_value as email
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
         WHERE pm.meta_key = 'email'
         AND pm.meta_value != ''
         AND p.post_type IN ($placeholders)
         AND p.post_status = 'publish'
         ORDER BY pm.meta_value ASC",
        ...$post_types
    );

    $results = $wpdb->get_col($query);

    return array_filter($results);
}

/**
 * Get email send status for multiple posts
 *
 * @param array $post_ids Array of post IDs
 * @return array Associative array [post_id => status]
 */
function certificate_generator_get_email_status_for_posts($post_ids) {
    global $wpdb;

    if (empty($post_ids)) {
        return [];
    }

    $table_name = $wpdb->prefix . 'cert_email_logs';
    $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));

    $query = $wpdb->prepare(
        "SELECT certificate_id,
                MAX(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as is_sent,
                MAX(sent_at) as last_sent
         FROM $table_name
         WHERE certificate_id IN ($placeholders)
         GROUP BY certificate_id",
        ...$post_ids
    );

    $results = $wpdb->get_results($query, ARRAY_A);

    $status_map = [];
    foreach ($results as $row) {
        $status_map[$row['certificate_id']] = [
            'sent' => (bool)$row['is_sent'],
            'last_sent' => $row['last_sent']
        ];
    }

    return $status_map;
}

/**
 * Get filtered recipients based on filter criteria
 *
 * @param array $filters Filter criteria
 * @return array Array of recipient data
 */
function certificate_generator_get_filtered_recipients($filters = []) {
    global $wpdb;

    // Default filters
    $defaults = [
        'post_types' => ['students', 'teachers', 'schools'],
        'schools' => [],              // Array of school names
        'certificate_types' => [],    // Array of certificate types
        'email_status' => [],         // Array: 'not_sent', 'sent', 'no_email'
        'emails' => [],               // Array of specific emails or email patterns
        'email_search' => '',         // Email search string
        'skip_already_sent' => true,
        'limit' => 500,
        'offset' => 0
    ];

    $filters = wp_parse_args($filters, $defaults);

    // Build base query
    $query = "SELECT DISTINCT
                p.ID as post_id,
                p.post_title,
                p.post_type,
                pm_email.meta_value as email,
                pm_name.meta_value as name,
                pm_school.meta_value as school_name,
                pm_type.meta_value as certificate_type,
                el.status as email_status,
                el.sent_at as last_sent
              FROM {$wpdb->posts} p";

    // Join meta fields
    $query .= " LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'email'";
    $query .= " LEFT JOIN {$wpdb->postmeta} pm_school ON p.ID = pm_school.post_id AND pm_school.meta_key = 'school_name'";
    $query .= " LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'certificate_type'";

    // Join name meta based on post type
    $query .= " LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id
                AND pm_name.meta_key IN ('student_name', 'teacher_name', 'school_name')";

    // Join email logs
    $table_name = $wpdb->prefix . 'cert_email_logs';
    $query .= " LEFT JOIN (
                    SELECT certificate_id, MAX(sent_at) as sent_at,
                           MAX(CASE WHEN status = 'sent' THEN 'sent' ELSE NULL END) as status
                    FROM $table_name
                    GROUP BY certificate_id
                ) el ON p.ID = el.certificate_id";

    // WHERE clauses
    $where = [];
    $where[] = "p.post_status = 'publish'";

    // Post type filter
    if (!empty($filters['post_types'])) {
        $post_type_placeholders = implode(',', array_fill(0, count($filters['post_types']), '%s'));
        $where[] = $wpdb->prepare("p.post_type IN ($post_type_placeholders)", ...$filters['post_types']);
    }

    // School filter
    if (!empty($filters['schools'])) {
        $school_placeholders = implode(',', array_fill(0, count($filters['schools']), '%s'));
        $where[] = $wpdb->prepare("pm_school.meta_value IN ($school_placeholders)", ...$filters['schools']);
    }

    // Certificate type filter
    if (!empty($filters['certificate_types'])) {
        $type_placeholders = implode(',', array_fill(0, count($filters['certificate_types']), '%s'));
        $where[] = $wpdb->prepare("pm_type.meta_value IN ($type_placeholders)", ...$filters['certificate_types']);
    }

    // Email status filter with skip_already_sent override
    // If skip_already_sent is true, it overrides email_status selections
    if (isset($filters['skip_already_sent']) && $filters['skip_already_sent'] === true) {
        // Skip already sent overrides email status - only show not sent
        $where[] = "(el.status IS NULL OR el.status != 'sent')";
    } elseif (!empty($filters['email_status'])) {
        // Normal email status filtering when skip_already_sent is false
        $status_conditions = [];
        foreach ($filters['email_status'] as $status) {
            switch ($status) {
                case 'sent':
                    $status_conditions[] = "el.status = 'sent'";
                    break;
                case 'not_sent':
                    $status_conditions[] = "(el.status IS NULL OR el.status != 'sent')";
                    break;
                case 'no_email':
                    $status_conditions[] = "(pm_email.meta_value IS NULL OR pm_email.meta_value = '')";
                    break;
            }
        }
        if (!empty($status_conditions)) {
            $where[] = '(' . implode(' OR ', $status_conditions) . ')';
        }
    }

    // Email search filter
    if (!empty($filters['email_search'])) {
        $where[] = $wpdb->prepare("pm_email.meta_value LIKE %s", '%' . $wpdb->esc_like($filters['email_search']) . '%');
    }

    // Specific emails filter
    if (!empty($filters['emails'])) {
        $email_placeholders = implode(',', array_fill(0, count($filters['emails']), '%s'));
        $where[] = $wpdb->prepare("pm_email.meta_value IN ($email_placeholders)", ...$filters['emails']);
    }

    // Add WHERE clauses
    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }

    // Order and limit
    $query .= " ORDER BY p.post_title ASC";
    $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $filters['limit'], $filters['offset']);

    $results = $wpdb->get_results($query, ARRAY_A);

    return $results;
}

/**
 * Count filtered recipients
 *
 * @param array $filters Filter criteria
 * @return int Count of matching recipients
 */
function certificate_generator_count_filtered_recipients($filters = []) {
    global $wpdb;

    // Use same logic as get_filtered_recipients but with COUNT
    $defaults = [
        'post_types' => ['students', 'teachers', 'schools'],
        'schools' => [],
        'certificate_types' => [],
        'email_status' => [],
        'emails' => [],
        'email_search' => '',
        'skip_already_sent' => true
    ];

    $filters = wp_parse_args($filters, $defaults);

    $query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p";

    // Join meta fields
    $query .= " LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'email'";
    $query .= " LEFT JOIN {$wpdb->postmeta} pm_school ON p.ID = pm_school.post_id AND pm_school.meta_key = 'school_name'";
    $query .= " LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'certificate_type'";

    // Join email logs
    $table_name = $wpdb->prefix . 'cert_email_logs';
    $query .= " LEFT JOIN (
                    SELECT certificate_id, MAX(sent_at) as sent_at,
                           MAX(CASE WHEN status = 'sent' THEN 'sent' ELSE NULL END) as status
                    FROM $table_name
                    GROUP BY certificate_id
                ) el ON p.ID = el.certificate_id";

    // WHERE clauses (same as get_filtered_recipients)
    $where = [];
    $where[] = "p.post_status = 'publish'";

    if (!empty($filters['post_types'])) {
        $post_type_placeholders = implode(',', array_fill(0, count($filters['post_types']), '%s'));
        $where[] = $wpdb->prepare("p.post_type IN ($post_type_placeholders)", ...$filters['post_types']);
    }

    if (!empty($filters['schools'])) {
        $school_placeholders = implode(',', array_fill(0, count($filters['schools']), '%s'));
        $where[] = $wpdb->prepare("pm_school.meta_value IN ($school_placeholders)", ...$filters['schools']);
    }

    if (!empty($filters['certificate_types'])) {
        $type_placeholders = implode(',', array_fill(0, count($filters['certificate_types']), '%s'));
        $where[] = $wpdb->prepare("pm_type.meta_value IN ($type_placeholders)", ...$filters['certificate_types']);
    }

    // Email status filter with skip_already_sent override (same logic as get_filtered_recipients)
    if (isset($filters['skip_already_sent']) && $filters['skip_already_sent'] === true) {
        $where[] = "(el.status IS NULL OR el.status != 'sent')";
    } elseif (!empty($filters['email_status'])) {
        $status_conditions = [];
        foreach ($filters['email_status'] as $status) {
            switch ($status) {
                case 'sent':
                    $status_conditions[] = "el.status = 'sent'";
                    break;
                case 'not_sent':
                    $status_conditions[] = "(el.status IS NULL OR el.status != 'sent')";
                    break;
                case 'no_email':
                    $status_conditions[] = "(pm_email.meta_value IS NULL OR pm_email.meta_value = '')";
                    break;
            }
        }
        if (!empty($status_conditions)) {
            $where[] = '(' . implode(' OR ', $status_conditions) . ')';
        }
    }

    if (!empty($filters['email_search'])) {
        $where[] = $wpdb->prepare("pm_email.meta_value LIKE %s", '%' . $wpdb->esc_like($filters['email_search']) . '%');
    }

    if (!empty($filters['emails'])) {
        $email_placeholders = implode(',', array_fill(0, count($filters['emails']), '%s'));
        $where[] = $wpdb->prepare("pm_email.meta_value IN ($email_placeholders)", ...$filters['emails']);
    }

    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }

    $count = $wpdb->get_var($query);

    return (int)$count;
}

/**
 * Parse email list from text input
 * Supports comma-separated, newline-separated, or space-separated emails
 *
 * @param string $email_list_text Raw text input
 * @return array Array of valid email addresses
 */
function certificate_generator_parse_email_list($email_list_text) {
    if (empty($email_list_text)) {
        return [];
    }

    // Split by common delimiters
    $emails = preg_split('/[\s,;]+/', $email_list_text, -1, PREG_SPLIT_NO_EMPTY);

    // Validate and filter
    $valid_emails = [];
    foreach ($emails as $email) {
        $email = trim($email);
        if (is_email($email)) {
            $valid_emails[] = $email;
        }
    }

    return array_unique($valid_emails);
}

/**
 * Calculate statistics for filtered recipients
 * Including email grouping info
 *
 * @param array $filters Filter criteria
 * @return array Statistics array
 */
function certificate_generator_get_filter_statistics($filters = []) {
    $recipients = certificate_generator_get_filtered_recipients($filters);

    $stats = [
        'total_certificates' => count($recipients),
        'unique_emails' => 0,
        'will_send' => 0,
        'will_skip' => 0,
        'no_email' => 0,
        'grouped_sends' => 0,
        'email_groups' => []
    ];

    $email_groups = [];

    foreach ($recipients as $recipient) {
        // Count emails
        if (empty($recipient['email'])) {
            $stats['no_email']++;
            $stats['will_skip']++;
        } else {
            // Group by email
            if (!isset($email_groups[$recipient['email']])) {
                $email_groups[$recipient['email']] = [];
            }
            $email_groups[$recipient['email']][] = $recipient;
        }
    }

    $stats['unique_emails'] = count($email_groups);

    // Calculate grouped sends
    foreach ($email_groups as $email => $certs) {
        $cert_count = count($certs);
        if ($cert_count > 1) {
            $stats['grouped_sends']++;
        }
    }

    $stats['will_send'] = $stats['total_certificates'] - $stats['will_skip'];
    $stats['email_groups'] = $email_groups;

    return $stats;
}
