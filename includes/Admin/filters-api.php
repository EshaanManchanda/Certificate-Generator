<?php
/**
 * Admin Filters API
 * Helper functions for filtering and previewing recipients
 *
 * @package Certificate Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get unique school names for a post type
 *
 * @param string|array $post_types Post type(s) to query
 * @return array Array of unique school names
 */
function certificate_generator_get_unique_schools( $post_types = array( 'students', 'teachers', 'schools' ) ) {
	global $wpdb;

	$cache_key = 'cg_unique_schools';
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		return $cached;
	}

	$results = array();

	// SQL-first: union across wp_cg_students, wp_cg_teachers, wp_cg_schools
	if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		$tables = \CertificateGenerator\Database\CustomTables::instance();
		$parts  = array();
		foreach ( array( 'students', 'teachers', 'schools' ) as $entity ) {
			$tbl = $tables->get_table( $entity );
			if ( $tbl && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) {
				$name_col = $entity === 'schools' ? 'school_name' : 'school_name';
				$parts[]  = "SELECT DISTINCT $name_col AS school_name FROM $tbl WHERE $name_col != ''"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		if ( ! empty( $parts ) ) {
			$union   = implode( ' UNION ', $parts ) . ' ORDER BY school_name ASC';
			$results = $wpdb->get_col( $union ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	// CPT fallback if SQL returned nothing
	if ( empty( $results ) ) {
		if ( ! is_array( $post_types ) ) {
			$post_types = array( $post_types );
		}
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$results      = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'school_name' AND pm.meta_value != ''
               AND p.post_type IN ($placeholders) AND p.post_status = 'publish'
             ORDER BY pm.meta_value ASC",
				...$post_types
			)
		);
	}

	$results = array_values( array_filter( $results ) );
	set_transient( $cache_key, $results, HOUR_IN_SECONDS );
	return $results;
}

/**
 * Get unique certificate types for a post type
 *
 * @param string|array $post_types Post type(s) to query
 * @return array Array of unique certificate types
 */
function certificate_generator_get_unique_certificate_types( $post_types = array( 'students', 'teachers', 'schools' ) ) {
	global $wpdb;

	$cache_key = 'cg_unique_cert_types';
	$cached    = get_transient( $cache_key );
	if ( $cached !== false ) {
		return $cached;
	}

	$results = array();

	if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		$tables = \CertificateGenerator\Database\CustomTables::instance();
		$parts  = array();
		foreach ( array( 'students', 'teachers', 'schools' ) as $entity ) {
			$tbl = $tables->get_table( $entity );
			if ( $tbl && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) {
				$parts[] = "SELECT DISTINCT certificate_type FROM $tbl WHERE certificate_type != ''"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		if ( ! empty( $parts ) ) {
			$union   = implode( ' UNION ', $parts ) . ' ORDER BY certificate_type ASC';
			$results = $wpdb->get_col( $union ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	if ( empty( $results ) ) {
		if ( ! is_array( $post_types ) ) {
			$post_types = array( $post_types );
		}
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$results      = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'certificate_type' AND pm.meta_value != ''
               AND p.post_type IN ($placeholders) AND p.post_status = 'publish'
             ORDER BY pm.meta_value ASC",
				...$post_types
			)
		);
	}

	$results = array_values( array_filter( $results ) );
	set_transient( $cache_key, $results, HOUR_IN_SECONDS );
	return $results;
}

/**
 * Invalidate filter dropdown caches when certificate-related posts are saved
 */
add_action(
	'save_post',
	function ( $post_id ) {
		if ( in_array( get_post_type( $post_id ), array( 'students', 'teachers', 'schools', 'certificates' ) ) ) {
			delete_transient( 'cg_unique_schools' );
			delete_transient( 'cg_unique_cert_types' );
		}
	}
);

/**
 * Get unique email addresses for a post type
 *
 * @param string|array $post_types Post type(s) to query
 * @return array Array of unique email addresses
 */
function certificate_generator_get_unique_emails( $post_types = array( 'students', 'teachers', 'schools' ) ) {
	global $wpdb;

	if ( ! is_array( $post_types ) ) {
		$post_types = array( $post_types );
	}

	$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

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

	$results = $wpdb->get_col( $query );

	return array_filter( $results );
}

/**
 * Get email send status for multiple posts
 *
 * @param array $post_ids Array of post IDs
 * @return array Associative array [post_id => status]
 */
function certificate_generator_get_email_status_for_posts( $post_ids ) {
	global $wpdb;

	if ( empty( $post_ids ) ) {
		return array();
	}

	$table_name   = $wpdb->prefix . 'cert_email_logs';
	$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

	$query = $wpdb->prepare(
		"SELECT certificate_id,
                MAX(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as is_sent,
                MAX(sent_at) as last_sent
         FROM $table_name
         WHERE certificate_id IN ($placeholders)
         GROUP BY certificate_id",
		...$post_ids
	);

	$results = $wpdb->get_results( $query, ARRAY_A );

	$status_map = array();
	foreach ( $results as $row ) {
		$status_map[ $row['certificate_id'] ] = array(
			'sent'      => (bool) $row['is_sent'],
			'last_sent' => $row['last_sent'],
		);
	}

	return $status_map;
}

/**
 * Get filtered recipients based on filter criteria
 *
 * @param array $filters Filter criteria
 * @return array Array of recipient data
 */
function certificate_generator_get_filtered_recipients( $filters = array() ) {
	global $wpdb;

	$defaults = array(
		'post_types'        => array( 'students', 'teachers', 'schools' ),
		'schools'           => array(),
		'certificate_types' => array(),
		'email_status'      => array(),
		'emails'            => array(),
		'email_search'      => '',
		'skip_already_sent' => true,
		'limit'             => 500,
		'offset'            => 0,
	);
	$filters  = wp_parse_args( $filters, $defaults );

	// SQL-first path — query wp_cg_* tables with a UNION
	if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		$tables     = \CertificateGenerator\Database\CustomTables::instance();
		$email_logs = $wpdb->prefix . 'cert_email_logs';

		// Build per-entity SELECT, then UNION
		$entity_map = array(
			'students' => array( 'student_name', 'students' ),
			'teachers' => array( 'teacher_name', 'teachers' ),
			'schools'  => array( 'school_name', 'schools' ),
		);

		$parts  = array();
		$params = array();

		foreach ( $entity_map as $type => [$name_col, $entity] ) {
			if ( ! in_array( $type, $filters['post_types'], true ) ) {
				continue;
			}
			$tbl = $tables->get_table( $entity );
			if ( ! $tbl || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
				continue;
			}

			$where = array( '1=1' );

			if ( ! empty( $filters['schools'] ) ) {
				$ph      = implode( ',', array_fill( 0, count( $filters['schools'] ), '%s' ) );
				$where[] = "t.school_name IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params  = array_merge( $params, $filters['schools'] );
			}
			if ( ! empty( $filters['certificate_types'] ) ) {
				$ph      = implode( ',', array_fill( 0, count( $filters['certificate_types'] ), '%s' ) );
				$where[] = "t.certificate_type IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params  = array_merge( $params, $filters['certificate_types'] );
			}
			if ( ! empty( $filters['email_search'] ) ) {
				$where[]  = 't.email LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $filters['email_search'] ) . '%';
			}
			if ( ! empty( $filters['emails'] ) ) {
				$ph      = implode( ',', array_fill( 0, count( $filters['emails'] ), '%s' ) );
				$where[] = "t.email IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params  = array_merge( $params, $filters['emails'] );
			}

			$where_sql = implode( ' AND ', $where );
			$parts[]   = "SELECT t.wp_post_id AS post_id, '$type' AS post_type,
                                t.$name_col AS name, t.email, t.school_name,
                                t.certificate_type, t.issue_date,
                                el.status AS email_status, el.sent_at AS last_sent
                         FROM $tbl t  -- phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                         LEFT JOIN (
                             SELECT certificate_id,
                                    MAX(CASE WHEN status = 'sent' THEN 'sent' ELSE NULL END) AS status,
                                    MAX(sent_at) AS sent_at
                             FROM $email_logs GROUP BY certificate_id -- phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                         ) el ON t.wp_post_id = el.certificate_id
                         WHERE $where_sql"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! empty( $parts ) ) {
			$union = '(' . implode( ') UNION (', $parts ) . ')';

			// Email-status post-filter
			$having = array();
			if ( $filters['skip_already_sent'] ) {
				$having[] = "(email_status IS NULL OR email_status != 'sent')";
			} elseif ( ! empty( $filters['email_status'] ) ) {
				$sc = array();
				foreach ( $filters['email_status'] as $s ) {
					if ( $s === 'sent' ) {
						$sc[] = "email_status = 'sent'";
					}
					if ( $s === 'not_sent' ) {
						$sc[] = "(email_status IS NULL OR email_status != 'sent')";
					}
					if ( $s === 'no_email' ) {
						$sc[] = "(email IS NULL OR email = '')";
					}
				}
				if ( $sc ) {
					$having[] = '(' . implode( ' OR ', $sc ) . ')';
				}
			}

			$having_sql = $having ? ( 'HAVING ' . implode( ' AND ', $having ) ) : '';
			$final      = "SELECT * FROM ($union) AS recipients $having_sql ORDER BY name ASC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$params[]   = $filters['limit'];
			$params[]   = $filters['offset'];

			return $wpdb->get_results( $wpdb->prepare( $final, ...$params ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	// CPT fallback — original query
	$query = "SELECT DISTINCT p.ID as post_id, p.post_title, p.post_type,
                pm_email.meta_value as email, pm_name.meta_value as name,
                pm_school.meta_value as school_name, pm_type.meta_value as certificate_type,
                el.status as email_status, el.sent_at as last_sent
              FROM {$wpdb->posts} p
              LEFT JOIN {$wpdb->postmeta} pm_email  ON p.ID = pm_email.post_id  AND pm_email.meta_key  = 'email'
              LEFT JOIN {$wpdb->postmeta} pm_school ON p.ID = pm_school.post_id AND pm_school.meta_key = 'school_name'
              LEFT JOIN {$wpdb->postmeta} pm_type   ON p.ID = pm_type.post_id   AND pm_type.meta_key   = 'certificate_type'
              LEFT JOIN {$wpdb->postmeta} pm_name   ON p.ID = pm_name.post_id   AND pm_name.meta_key   IN ('student_name','teacher_name','school_name')
              LEFT JOIN (
                  SELECT certificate_id, MAX(sent_at) as sent_at,
                         MAX(CASE WHEN status='sent' THEN 'sent' ELSE NULL END) as status
                  FROM {$wpdb->prefix}cert_email_logs GROUP BY certificate_id
              ) el ON p.ID = el.certificate_id";

	$where      = array( "p.post_status = 'publish'" );
	$cpt_params = array();

	if ( ! empty( $filters['post_types'] ) ) {
		$ph         = implode( ',', array_fill( 0, count( $filters['post_types'] ), '%s' ) );
		$where[]    = "p.post_type IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cpt_params = array_merge( $cpt_params, $filters['post_types'] );
	}
	if ( ! empty( $filters['schools'] ) ) {
		$ph         = implode( ',', array_fill( 0, count( $filters['schools'] ), '%s' ) );
		$where[]    = "pm_school.meta_value IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cpt_params = array_merge( $cpt_params, $filters['schools'] );
	}
	if ( ! empty( $filters['certificate_types'] ) ) {
		$ph         = implode( ',', array_fill( 0, count( $filters['certificate_types'] ), '%s' ) );
		$where[]    = "pm_type.meta_value IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cpt_params = array_merge( $cpt_params, $filters['certificate_types'] );
	}
	if ( $filters['skip_already_sent'] ) {
		$where[] = "(el.status IS NULL OR el.status != 'sent')";
	}
	if ( ! empty( $filters['email_search'] ) ) {
		$where[]      = 'pm_email.meta_value LIKE %s';
		$cpt_params[] = '%' . $wpdb->esc_like( $filters['email_search'] ) . '%'; }
	if ( ! empty( $filters['emails'] ) ) {
		$ph         = implode( ',', array_fill( 0, count( $filters['emails'] ), '%s' ) );
		$where[]    = "pm_email.meta_value IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cpt_params = array_merge( $cpt_params, $filters['emails'] );
	}

	$query       .= ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY p.post_title ASC';
	$cpt_params[] = $filters['limit'];
	$cpt_params[] = $filters['offset'];
	$query       .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $filters['limit'], $filters['offset'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	array_pop( $cpt_params );
	array_pop( $cpt_params ); // already appended via prepare above

	return $wpdb->get_results( $wpdb->prepare( $query, ...$cpt_params ), ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Count filtered recipients
 *
 * @param array $filters Filter criteria
 * @return int Count of matching recipients
 */
function certificate_generator_count_filtered_recipients( $filters = array() ) {
	global $wpdb;

	$defaults = array(
		'post_types'        => array( 'students', 'teachers', 'schools' ),
		'schools'           => array(),
		'certificate_types' => array(),
		'email_status'      => array(),
		'emails'            => array(),
		'email_search'      => '',
		'skip_already_sent' => true,
	);
	$filters  = wp_parse_args( $filters, $defaults );

	// SQL-first path — mirrors UNION from get_filtered_recipients, wrapped in COUNT
	if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		$tables     = \CertificateGenerator\Database\CustomTables::instance();
		$email_logs = $wpdb->prefix . 'cert_email_logs';

		$entity_map = array(
			'students' => array( 'student_name', 'students' ),
			'teachers' => array( 'teacher_name', 'teachers' ),
			'schools'  => array( 'school_name', 'schools' ),
		);

		$parts  = array();
		$params = array();

		foreach ( $entity_map as $type => [$name_col, $entity] ) {
			if ( ! in_array( $type, $filters['post_types'], true ) ) {
				continue;
			}
			$tbl = $tables->get_table( $entity );
			if ( ! $tbl || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
				continue;
			}

			$where = array( '1=1' );

			if ( ! empty( $filters['schools'] ) ) {
				$ph      = implode( ',', array_fill( 0, count( $filters['schools'] ), '%s' ) );
				$where[] = "t.school_name IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params  = array_merge( $params, $filters['schools'] );
			}
			if ( ! empty( $filters['certificate_types'] ) ) {
				$ph      = implode( ',', array_fill( 0, count( $filters['certificate_types'] ), '%s' ) );
				$where[] = "t.certificate_type IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params  = array_merge( $params, $filters['certificate_types'] );
			}
			if ( ! empty( $filters['email_search'] ) ) {
				$where[]  = 't.email LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $filters['email_search'] ) . '%';
			}
			if ( ! empty( $filters['emails'] ) ) {
				$ph      = implode( ',', array_fill( 0, count( $filters['emails'] ), '%s' ) );
				$where[] = "t.email IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$params  = array_merge( $params, $filters['emails'] );
			}

			$where_sql = implode( ' AND ', $where );
			$parts[]   = "SELECT t.wp_post_id AS post_id, '$type' AS post_type,
                                t.email,
                                el.status AS email_status
                         FROM $tbl t  -- phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                         LEFT JOIN (
                             SELECT certificate_id,
                                    MAX(CASE WHEN status = 'sent' THEN 'sent' ELSE NULL END) AS status
                             FROM $email_logs GROUP BY certificate_id -- phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                         ) el ON t.wp_post_id = el.certificate_id
                         WHERE $where_sql"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! empty( $parts ) ) {
			$union = '(' . implode( ') UNION (', $parts ) . ')';

			$having = array();
			if ( $filters['skip_already_sent'] ) {
				$having[] = "(email_status IS NULL OR email_status != 'sent')";
			} elseif ( ! empty( $filters['email_status'] ) ) {
				$sc = array();
				foreach ( $filters['email_status'] as $s ) {
					if ( $s === 'sent' ) {
						$sc[] = "email_status = 'sent'";
					}
					if ( $s === 'not_sent' ) {
						$sc[] = "(email_status IS NULL OR email_status != 'sent')";
					}
					if ( $s === 'no_email' ) {
						$sc[] = "(email IS NULL OR email = '')";
					}
				}
				if ( $sc ) {
					$having[] = '(' . implode( ' OR ', $sc ) . ')';
				}
			}

			$having_sql = $having ? ( 'HAVING ' . implode( ' AND ', $having ) ) : '';
			$count_sql  = "SELECT COUNT(*) FROM ($union) AS recipients $having_sql"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( ! empty( $params ) ) {
				return (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			return (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	// CPT fallback
	$table_name = $wpdb->prefix . 'cert_email_logs';
	$query      = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
              LEFT JOIN {$wpdb->postmeta} pm_email  ON p.ID = pm_email.post_id  AND pm_email.meta_key  = 'email'
              LEFT JOIN {$wpdb->postmeta} pm_school ON p.ID = pm_school.post_id AND pm_school.meta_key = 'school_name'
              LEFT JOIN {$wpdb->postmeta} pm_type   ON p.ID = pm_type.post_id   AND pm_type.meta_key   = 'certificate_type'
              LEFT JOIN (
                  SELECT certificate_id, MAX(CASE WHEN status='sent' THEN 'sent' ELSE NULL END) as status
                  FROM $table_name GROUP BY certificate_id
              ) el ON p.ID = el.certificate_id";

	$where  = array( "p.post_status = 'publish'" );
	$params = array();

	if ( ! empty( $filters['post_types'] ) ) {
		$ph      = implode( ',', array_fill( 0, count( $filters['post_types'] ), '%s' ) );
		$where[] = "p.post_type IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$params  = array_merge( $params, $filters['post_types'] );
	}
	if ( ! empty( $filters['schools'] ) ) {
		$ph      = implode( ',', array_fill( 0, count( $filters['schools'] ), '%s' ) );
		$where[] = "pm_school.meta_value IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$params  = array_merge( $params, $filters['schools'] );
	}
	if ( ! empty( $filters['certificate_types'] ) ) {
		$ph      = implode( ',', array_fill( 0, count( $filters['certificate_types'] ), '%s' ) );
		$where[] = "pm_type.meta_value IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$params  = array_merge( $params, $filters['certificate_types'] );
	}
	if ( $filters['skip_already_sent'] ) {
		$where[] = "(el.status IS NULL OR el.status != 'sent')";
	} elseif ( ! empty( $filters['email_status'] ) ) {
		$sc = array();
		foreach ( $filters['email_status'] as $s ) {
			if ( $s === 'sent' ) {
				$sc[] = "el.status = 'sent'";
			}
			if ( $s === 'not_sent' ) {
				$sc[] = "(el.status IS NULL OR el.status != 'sent')";
			}
			if ( $s === 'no_email' ) {
				$sc[] = "(pm_email.meta_value IS NULL OR pm_email.meta_value = '')";
			}
		}
		if ( $sc ) {
			$where[] = '(' . implode( ' OR ', $sc ) . ')';
		}
	}
	if ( ! empty( $filters['email_search'] ) ) {
		$where[]  = 'pm_email.meta_value LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $filters['email_search'] ) . '%';
	}
	if ( ! empty( $filters['emails'] ) ) {
		$ph      = implode( ',', array_fill( 0, count( $filters['emails'] ), '%s' ) );
		$where[] = "pm_email.meta_value IN ($ph)"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$params  = array_merge( $params, $filters['emails'] );
	}

	$query .= ' WHERE ' . implode( ' AND ', $where );

	if ( ! empty( $params ) ) {
		return (int) $wpdb->get_var( $wpdb->prepare( $query, ...$params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
	return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Parse email list from text input
 * Supports comma-separated, newline-separated, or space-separated emails
 *
 * @param string $email_list_text Raw text input
 * @return array Array of valid email addresses
 */
function certificate_generator_parse_email_list( $email_list_text ) {
	if ( empty( $email_list_text ) ) {
		return array();
	}

	// Split by common delimiters
	$emails = preg_split( '/[\s,;]+/', $email_list_text, -1, PREG_SPLIT_NO_EMPTY );

	// Validate and filter
	$valid_emails = array();
	foreach ( $emails as $email ) {
		$email = trim( $email );
		if ( is_email( $email ) ) {
			$valid_emails[] = $email;
		}
	}

	return array_unique( $valid_emails );
}

/**
 * Calculate statistics for filtered recipients
 * Including email grouping info
 *
 * @param array $filters Filter criteria
 * @return array Statistics array
 */
function certificate_generator_get_filter_statistics( $filters = array() ) {
	$recipients = certificate_generator_get_filtered_recipients( $filters );

	$stats = array(
		'total_certificates' => count( $recipients ),
		'unique_emails'      => 0,
		'will_send'          => 0,
		'will_skip'          => 0,
		'no_email'           => 0,
		'grouped_sends'      => 0,
		'email_groups'       => array(),
	);

	$email_groups = array();

	foreach ( $recipients as $recipient ) {
		// Count emails
		if ( empty( $recipient['email'] ) ) {
			++$stats['no_email'];
			++$stats['will_skip'];
		} else {
			// Group by email
			if ( ! isset( $email_groups[ $recipient['email'] ] ) ) {
				$email_groups[ $recipient['email'] ] = array();
			}
			$email_groups[ $recipient['email'] ][] = $recipient;
		}
	}

	$stats['unique_emails'] = count( $email_groups );

	// Calculate grouped sends
	foreach ( $email_groups as $email => $certs ) {
		$cert_count = count( $certs );
		if ( $cert_count > 1 ) {
			++$stats['grouped_sends'];
		}
	}

	$stats['will_send']    = $stats['total_certificates'] - $stats['will_skip'];
	$stats['email_groups'] = $email_groups;

	return $stats;
}
