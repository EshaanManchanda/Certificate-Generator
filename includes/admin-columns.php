<?php
if (!defined('ABSPATH')) {
    exit;
}

function add_custom_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        if ($key === 'title') {
            $new_columns[$key] = $value;
            $new_columns['email'] = __('Email', 'certificate-generator');
            $new_columns['school_name'] = __('School Name', 'certificate-generator');
            $new_columns['certificate_type'] = __('Certificate Type', 'certificate-generator');
            $new_columns['issue_date'] = __('Issue Date', 'certificate-generator');
        } else {
            $new_columns[$key] = $value;
        }
    }
    return $new_columns;
}

function populate_custom_columns($column, $post_id) {
    switch ($column) {
        case 'email':
            $email = get_post_meta($post_id, 'email', true);
            echo $email ? esc_html($email) : '—';
            break;
        case 'school_name':
            $school = get_post_meta($post_id, 'school_name', true);
            echo $school ? esc_html($school) : '—';
            break;
        case 'certificate_type':
            $type = get_post_meta($post_id, 'certificate_type', true);
            echo $type ? esc_html($type) : '—';
            break;
        case 'issue_date':
            $date = get_post_meta($post_id, 'issue_date', true);
            echo $date ? esc_html($date) : '—';
            break;
    }
}

function make_custom_columns_sortable($columns) {
    $columns['email'] = 'email';
    $columns['school_name'] = 'school_name';
    $columns['certificate_type'] = 'certificate_type';
    $columns['issue_date'] = 'issue_date';
    return $columns;
}

function custom_search_query($query) {
    if (!is_admin()) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['students', 'teachers', 'schools'])) {
        return;
    }

    // Handle sorting
    $orderby = $query->get('orderby');
    if (in_array($orderby, ['email', 'school_name', 'certificate_type', 'issue_date'])) {
        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value');
    }

    // Handle searching
    $search_term = $query->get('s');
    if (empty($search_term)) {
        return;
    }

    $query->set('s', '');

    $meta_query = array(
        'relation' => 'OR',
        array(
            'key' => 'email',
            'value' => $search_term,
            'compare' => 'LIKE'
        ),
        array(
            'key' => 'student_name',
            'value' => $search_term,
            'compare' => 'LIKE'
        ),
        array(
            'key' => 'teacher_name',
            'value' => $search_term,
            'compare' => 'LIKE'
        ),
        array(
            'key' => 'school_name',
            'value' => $search_term,
            'compare' => 'LIKE'
        ),
        array(
            'key' => 'certificate_type',
            'value' => $search_term,
            'compare' => 'LIKE'
        )
    );

    $existing_meta_query = $query->get('meta_query');
    if (!empty($existing_meta_query)) {
        $meta_query = array(
            'relation' => 'AND',
            $existing_meta_query,
            $meta_query
        );
    }

    $query->set('meta_query', $meta_query);
}

// Add filters for students
add_filter('manage_students_posts_columns', 'add_custom_columns');
add_action('manage_students_posts_custom_column', 'populate_custom_columns', 10, 2);
add_filter('manage_edit-students_sortable_columns', 'make_custom_columns_sortable');

// Add filters for teachers
add_filter('manage_teachers_posts_columns', 'add_custom_columns');
add_action('manage_teachers_posts_custom_column', 'populate_custom_columns', 10, 2);
add_filter('manage_edit-teachers_sortable_columns', 'make_custom_columns_sortable');

// Add filters for schools
add_filter('manage_schools_posts_columns', 'add_custom_columns');
add_action('manage_schools_posts_custom_column', 'populate_custom_columns', 10, 2);
add_filter('manage_edit-schools_sortable_columns', 'make_custom_columns_sortable');

// Add search and sort functionality
add_action('pre_get_posts', 'custom_search_query');
