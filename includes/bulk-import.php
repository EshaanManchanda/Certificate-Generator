<?php

function bulk_import_students() {
    // Check for file upload and nonce validation
    if (isset($_POST['submit_students']) && isset($_FILES['students_csv'])) {
        if (check_admin_referer('bulk_import_students_nonce', '_wpnonce_bulk_import')) {
            $file = $_FILES['students_csv']['tmp_name'];
            $file_error = $_FILES['students_csv']['error'];

            // Error handling for file upload
            if ($file_error !== UPLOAD_ERR_OK) {
                echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
                return;
            }

            if (($handle = fopen($file, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ','); // Read the first row as the header

                // Validate headers match the required fields
                $required_fields = ['student_name', 'email', 'school_name', 'issue_date', 'certificate_type'];
                if ($header !== $required_fields) {
                    echo '<div class="notice notice-error"><p>Invalid CSV format. Please ensure the headers are: ' . implode(', ', $required_fields) . '</p></div>';
                    fclose($handle);
                    return;
                }

                // Process each row
                $imported_count = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $student_data = array_combine($header, $data);

                    // Generate school abbreviation
                    $school_name = sanitize_text_field($student_data['school_name']);
                    $words = explode(' ', $school_name);
                    $school_abbreviation = '';
                    foreach ($words as $word) {
                        if (!empty($word)) {
                            $school_abbreviation .= strtoupper($word[0]); // Get the first letter of each word
                        }
                    }
                    if (empty($school_abbreviation)) {
                        $school_abbreviation = 'UNK'; // Default abbreviation if school name is empty
                    }

                    // Generate post title (student_name + school_abbreviation)
                    $post_title = sanitize_text_field($student_data['student_name']) . ' (' . $school_abbreviation . ')';

                    // Insert student as a custom post
                    $post_id = wp_insert_post([
                        'post_type'   => 'students',
                        'post_title'  => $post_title,
                        'post_status' => 'publish',
                    ]);

                    if ($post_id) {
                        // Update custom fields
                        if (function_exists('update_field')) {
                            update_field('field_student_name', sanitize_text_field($student_data['student_name']), $post_id);
                            update_field('field_email', sanitize_email($student_data['email']), $post_id);
                            update_field('field_school_name', $school_name, $post_id);
                            update_field('field_issue_date', sanitize_text_field($student_data['issue_date']), $post_id);
                            update_field('field_certificate_type', sanitize_text_field($student_data['certificate_type']), $post_id);
                            update_field('field_school_abbreviation', $school_abbreviation, $post_id);
                        } else {
                            // Use post meta as a fallback
                            add_post_meta($post_id, 'student_name', sanitize_text_field($student_data['student_name']));
                            add_post_meta($post_id, 'email', sanitize_email($student_data['email']));
                            add_post_meta($post_id, 'school_name', $school_name);
                            add_post_meta($post_id, 'issue_date', sanitize_text_field($student_data['issue_date']));
                            add_post_meta($post_id, 'certificate_type', sanitize_text_field($student_data['certificate_type']));
                            add_post_meta($post_id, 'school_abbreviation', $school_abbreviation);
                        }
                        $imported_count++;
                    }
                }
                fclose($handle);

                echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' students!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
            }
        }
    }

    // Display the import form
    echo '<div class="wrap">';
    echo '<h1>Bulk Import Students</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('bulk_import_students_nonce', '_wpnonce_bulk_import');
    echo '<table class="form-table">';
    echo '<tr><th><label for="students_csv">Upload CSV File</label></th>';
    echo '<td><input type="file" name="students_csv" id="students_csv" accept=".csv" required /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_students" value="Import Students" class="button button-primary" />';
    echo '</form>';
    echo '</div>';
}

// Add Bulk Import Submenu to Students Menu
function add_bulk_import_submenu() {
    add_submenu_page(
        'edit.php?post_type=students', // Parent slug (the "Students" menu)
        'Bulk Import Students',       // Page title
        'Bulk Import',                // Menu title
        'manage_options',             // Capability required to access
        'bulk-import-students',       // Menu slug
        'bulk_import_students'        // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_import_submenu');



function bulk_import_teachers() {
    // Check for file upload and nonce validation
    if (isset($_POST['submit_teachers']) && isset($_FILES['teachers_csv'])) {
        if (check_admin_referer('bulk_import_teachers_nonce', '_wpnonce_bulk_import')) {
            $file = $_FILES['teachers_csv']['tmp_name'];
            $file_error = $_FILES['teachers_csv']['error'];

            // Error handling for file upload
            if ($file_error !== UPLOAD_ERR_OK) {
                echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
                return;
            }

            if (($handle = fopen($file, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ','); // Read the first row as the header

                // Validate headers match the required fields
                $required_fields = [
                    'teacher_name',
                    'email',
                    'school_name',
                    'issue_date',
                    'certificate_type'
                ];

                if ($header !== $required_fields) {
                    echo '<div class="notice notice-error"><p>Invalid CSV format. Please ensure the headers are: ' . implode(', ', $required_fields) . '</p></div>';
                    fclose($handle);
                    return;
                }

                // Process each row
                $imported_count = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $teacher_data = array_combine($header, $data);

                    // Generate school abbreviation
                    $school_name = sanitize_text_field($teacher_data['school_name']);
                    $words = explode(' ', $school_name);
                    $school_abbreviation = '';
                    foreach ($words as $word) {
                        if (!empty($word)) {
                            $school_abbreviation .= strtoupper($word[0]); // Get the first letter of each word
                        }
                    }
                    if (empty($school_abbreviation)) {
                        $school_abbreviation = 'UNK'; // Default abbreviation if school name is empty
                    }

                    // Generate post title (teacher_name + school_abbreviation)
                    $post_title = sanitize_text_field($teacher_data['teacher_name']) . ' (' . $school_abbreviation . ')';

                    // Check if a post with this title already exists
                    $existing_query = new WP_Query([
                        'post_type' => 'teachers',
                        'title' => $post_title,
                        'posts_per_page' => 1,
                        'fields' => 'ids'
                    ]);
                    if ($existing_query->have_posts()) {
                        continue; // Skip duplicate
                    }

                    // Insert teacher as a custom post
                    $post_id = wp_insert_post([
                        'post_type'   => 'teachers',
                        'post_title'  => $post_title,
                        'post_status' => 'publish',
                    ]);

                    if ($post_id) {
                        // Update custom fields
                        if (function_exists('update_field')) {
                            update_field('field_teacher_name', sanitize_text_field($teacher_data['teacher_name']), $post_id);
                            update_field('field_email_teacher', sanitize_email($teacher_data['email']), $post_id);
                            update_field('field_school_name_teacher', $school_name, $post_id);
                            update_field('field_school_abbreviation_teacher', $school_abbreviation, $post_id);
                            update_field('field_issue_date_teacher', sanitize_text_field($teacher_data['issue_date']), $post_id);
                            update_field('field_certificate_type', sanitize_text_field($teacher_data['certificate_type']), $post_id);
                        } else {
                            // Use post meta as a fallback
                            add_post_meta($post_id, 'teacher_name', sanitize_text_field($teacher_data['teacher_name']));
                            add_post_meta($post_id, 'email', sanitize_email($teacher_data['email']));
                            add_post_meta($post_id, 'school_name', $school_name);
                            add_post_meta($post_id, 'school_abbreviation', $school_abbreviation);
                            add_post_meta($post_id, 'issue_date', sanitize_text_field($teacher_data['issue_date']));
                            add_post_meta($post_id, 'certificate_type', sanitize_text_field($teacher_data['certificate_type']));
                        }
                        $imported_count++;
                    }
                }
                fclose($handle);

                echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' teachers!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
            }
        }

        // Prevent duplicate processing of the request
        exit;
    }

    // Display the import form
    echo '<div class="wrap">';
    echo '<h1>Bulk Import Teachers</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('bulk_import_teachers_nonce', '_wpnonce_bulk_import');
    echo '<table class="form-table">';
    echo '<tr><th><label for="teachers_csv">Upload CSV File</label></th>';
    echo '<td><input type="file" name="teachers_csv" id="teachers_csv" accept=".csv" required /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_teachers" value="Import Teachers" class="button button-primary" />';
    echo '</form>';
    echo '</div>';
}

// Add Bulk Import Submenu to Teachers Menu
function add_bulk_import_teachers_submenu() {
    add_submenu_page(
        'edit.php?post_type=teachers', // Parent slug (the "Teachers" menu)
        'Bulk Import Teachers',       // Page title
        'Bulk Import',                // Menu title
        'manage_options',             // Capability required to access
        'bulk-import-teachers',       // Menu slug
        'bulk_import_teachers'        // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_import_teachers_submenu');




function bulk_import_schools() {
    // Check for file upload and nonce validation
    if (isset($_POST['submit_schools']) && isset($_FILES['schools_csv'])) {
        if (check_admin_referer('bulk_import_schools_nonce', '_wpnonce_bulk_import')) {
            $file = $_FILES['schools_csv']['tmp_name'];
            $file_error = $_FILES['schools_csv']['error'];

            // Error handling for file upload
            if ($file_error !== UPLOAD_ERR_OK) {
                echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
                return;
            }

            if (($handle = fopen($file, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ','); // Read the first row as the header

                // Validate headers match the required fields
                $required_fields = ['school_name', 'place', 'issue_date', 'certificate_type'];
                if ($header !== $required_fields) {
                    echo '<div class="notice notice-error"><p>Invalid CSV format. Please ensure the headers are: ' . implode(', ', $required_fields) . '</p></div>';
                    fclose($handle);
                    return;
                }

                // Process each row
                $imported_count = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $school_data = array_combine($header, $data);

                    // Generate school abbreviation
                    $school_name = sanitize_text_field($school_data['school_name']);
                    $words = explode(' ', $school_name);
                    $school_abbreviation = '';
                    foreach ($words as $word) {
                        if (!empty($word)) {
                            $school_abbreviation .= strtoupper($word[0]); // Get the first letter of each word
                        }
                    }
                    if (empty($school_abbreviation)) {
                        $school_abbreviation = 'UNK'; // Default abbreviation if school name is empty
                    }

                    // Generate post title (school_name + school_abbreviation)
                    $post_title = $school_name . ' (' . $school_abbreviation . ')';

                    // Check if a post with this title already exists
                    $existing_query = new WP_Query([
                        'post_type' => 'schools',
                        'title' => $post_title,
                        'posts_per_page' => 1,
                        'fields' => 'ids'
                    ]);
                    if ($existing_query->have_posts()) {
                        continue; // Skip duplicate
                    }

                    // Insert school as a custom post
                    $post_id = wp_insert_post([
                        'post_type'   => 'schools',
                        'post_title'  => $post_title,
                        'post_status' => 'publish',
                    ]);

                    if ($post_id) {
                        // Update custom fields
                        if (function_exists('update_field')) {
                            update_field('field_school_name_schools', $school_name, $post_id);
                            update_field('field_school_abbreviation_schools', $school_abbreviation, $post_id);
                            update_field('field_place', sanitize_text_field($school_data['place']), $post_id);
                            update_field('field_issue_date_schools', sanitize_text_field($school_data['issue_date']), $post_id);
                            update_field('field_certificate_type', sanitize_text_field($school_data['certificate_type']), $post_id);
                        } else {
                            // Use post meta as a fallback
                            add_post_meta($post_id, 'school_name', $school_name);
                            add_post_meta($post_id, 'school_abbreviation', $school_abbreviation);
                            add_post_meta($post_id, 'place', sanitize_text_field($school_data['place']));
                            add_post_meta($post_id, 'issue_date', sanitize_text_field($school_data['issue_date']));
                            add_post_meta($post_id, 'certificate_type', sanitize_text_field($school_data['certificate_type']));
                        }
                        $imported_count++;
                    }
                }
                fclose($handle);

                echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' schools!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
            }
        }

        // Prevent duplicate processing of the request
        exit;
    }

    // Display the import form
    echo '<div class="wrap">';
    echo '<h1>Bulk Import Schools</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('bulk_import_schools_nonce', '_wpnonce_bulk_import');
    echo '<table class="form-table">';
    echo '<tr><th><label for="schools_csv">Upload CSV File</label></th>';
    echo '<td><input type="file" name="schools_csv" id="schools_csv" accept=".csv" required /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_schools" value="Import Schools" class="button button-primary" />';
    echo '</form>';
    echo '</div>';
}

// Add Bulk Import Submenu to Schools Menu
function add_bulk_import_schools_submenu() {
    add_submenu_page(
        'edit.php?post_type=schools', // Parent slug (the "Schools" menu)
        'Bulk Import Schools',       // Page title
        'Bulk Import',                // Menu title
        'manage_options',             // Capability required to access
        'bulk-import-schools',        // Menu slug
        'bulk_import_schools'         // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_import_schools_submenu');




function bulk_import_certificates() {
    // Check for file upload and nonce validation
    if (isset($_POST['submit_certificates']) && isset($_FILES['certificates_csv'])) {
        if (check_admin_referer('bulk_import_certificates_nonce', '_wpnonce_bulk_import')) {
            $file = $_FILES['certificates_csv']['tmp_name'];
            $file_error = $_FILES['certificates_csv']['error'];

            // Error handling for file upload
            if ($file_error !== UPLOAD_ERR_OK) {
                echo '<div class="notice notice-error"><p>File upload error. Please try again.</p></div>';
                return;
            }

            if (($handle = fopen($file, 'r')) !== false) {
                $header = fgetcsv($handle, 1000, ','); // Read the first row as the header

                // Validate headers match the required fields
                $required_fields = [
                    'certificate_type',
                    'template_url',
                    'template_orientation',
                    'font_size',
                    'font_color',
                    'font_style',
                    'field_1_position_x',
                    'field_1_position_y',
                    'field_1_visible',
                    'field_1_width',
                    'field_1_alignment',
                    'field_2_position_x',
                    'field_2_position_y',
                    'field_2_visible',
                    'field_2_width',
                    'field_2_alignment',
                    'field_3_position_x',
                    'field_3_position_y',
                    'field_3_visible',
                    'field_3_width',
                    'field_3_alignment',
                    'width'
                ];

                if ($header !== $required_fields) {
                    echo '<div class="notice notice-error"><p>Invalid CSV format. Please ensure the headers are: ' . implode(', ', $required_fields) . '</p></div>';
                    fclose($handle);
                    return;
                }

                // Process each row
                $imported_count = 0;
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $certificate_data = array_combine($header, $data);
                    
                    // Generate certificate title for duplicate checking
                    $certificate_type = sanitize_text_field($certificate_data['certificate_type']);
                    $template_url = esc_url($certificate_data['template_url']);
                    $new_title = ($certificate_type ?: 'Certificate') . ' - ' . ($template_url ? parse_url($template_url, PHP_URL_HOST) : 'No Template URL');
                    
                    // Check if a certificate with this template URL and type already exists
                    $existing_query = new WP_Query([
                        'post_type' => 'certificates',
                        'title' => $new_title,
                        'meta_query' => [
                            'relation' => 'AND',
                            [
                                'key' => 'template_url',
                                'value' => $certificate_data['template_url'],
                                'compare' => '='
                            ],
                            [
                                'key' => 'certificate_type',
                                'value' => $certificate_data['certificate_type'],
                                'compare' => '='
                            ]
                        ],
                        'posts_per_page' => 1,
                        'fields' => 'ids'
                    ]);
                    if ($existing_query->have_posts()) {
                        continue; // Skip duplicate
                    }

                    // Insert certificate as a custom post
                    $post_id = wp_insert_post([
                        'post_type'   => 'certificates',
                        'post_status' => 'publish',
                    ]);

                    if ($post_id) {
                        // Update custom fields
                        foreach ($certificate_data as $key => $value) {
                            // Force saving `1` or `0` as string
                            if (in_array($key, ['field_1_visible', 'field_2_visible', 'field_3_visible'])) {
                                $value = $value ? '1' : '0'; // Explicitly convert boolean to string
                            }
                            update_post_meta($post_id, $key, $value);
                        }

                        // Auto-generate the title for the certificate
                        $certificate_type = sanitize_text_field($certificate_data['certificate_type']);
                        $template_url = esc_url($certificate_data['template_url']);

                        $new_title = ($certificate_type ?: 'Certificate') . ' - ' . ($template_url ? parse_url($template_url, PHP_URL_HOST) : 'No Template URL');
                        $new_slug = sanitize_title($new_title);

                        // Update the post with the generated title and slug
                        wp_update_post([
                            'ID' => $post_id,
                            'post_title' => $new_title,
                            'post_name' => $new_slug,
                        ]);

                        $imported_count++;
                    }
                }
                fclose($handle);

                echo '<div class="notice notice-success"><p>Successfully imported ' . $imported_count . ' certificates!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
            }
        }

        // Prevent duplicate processing of the request
        exit;
    }


    // Display the import form
    echo '<div class="wrap">';
    echo '<h1>Bulk Import Certificates</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field('bulk_import_certificates_nonce', '_wpnonce_bulk_import');
    echo '<table class="form-table">';
    echo '<tr><th><label for="certificates_csv">Upload CSV File</label></th>';
    echo '<td><input type="file" name="certificates_csv" id="certificates_csv" accept=".csv" required /></td></tr>';
    echo '</table>';
    echo '<input type="submit" name="submit_certificates" value="Import Certificates" class="button button-primary" />';
    echo '</form>';
    echo '</div>';
}

// Add Bulk Import Submenu to Certificates Menu
function add_bulk_import_certificates_submenu() {
    add_submenu_page(
        'edit.php?post_type=certificates', // Parent slug (the "Certificates" menu)
        'Bulk Import Certificates',       // Page title
        'Bulk Import',                    // Menu title
        'manage_options',                 // Capability required to access
        'bulk-import-certificates',       // Menu slug
        'bulk_import_certificates'        // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_import_certificates_submenu');




?>