<?php
// Render the Export Students Page
function render_bulk_export_students_page()
{
    echo '<div class="wrap">';
    echo '<h1>Export Students</h1>';
    echo '<form method="post">';
    echo wp_nonce_field('cg_export_students', '_wpnonce_cg_export', true, false);
    echo '<input type="hidden" name="export_students" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export
function bulk_export_students()
{
    if (isset($_POST['export_students'])) {
        check_admin_referer('cg_export_students', '_wpnonce_cg_export');
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Try SQL tables first, fall back to CPTs
        if (class_exists('\CertificateGenerator\Database\CustomTables')) {
            $tables = \CertificateGenerator\Database\CustomTables::instance();
            $student_table = $tables->get_table('students');
            $table_exists = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                "SHOW TABLES LIKE %s", $student_table
            )) === $student_table;

            if ($table_exists) {
                // Export from SQL tables
                $rows = $GLOBALS['wpdb']->get_results("SELECT * FROM $student_table ORDER BY id ASC", ARRAY_A);

                if (empty($rows)) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-warning"><p>No students found for export.</p></div>';
                    });
                    return;
                }

                // Discover extra field keys from JSON
                $extra_keys = [];
                foreach ($rows as $row) {
                    if (!empty($row['extra_fields'])) {
                        $extra = json_decode($row['extra_fields'], true);
                        if (is_array($extra)) {
                            $extra_keys = array_merge($extra_keys, array_keys($extra));
                        }
                    }
                }
                $extra_keys = array_values(array_unique($extra_keys));

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=students_export.csv');
                header('Pragma: no-cache');
                header('Expires: 0');

                $output = fopen('php://output', 'w');
                $headers = ['student_name', 'email', 'phone', 'school_name', 'status'];
                $headers = array_merge($headers, $extra_keys);
                fputcsv($output, $headers);

                foreach ($rows as $row) {
                    $extra = !empty($row['extra_fields']) ? json_decode($row['extra_fields'], true) : [];
                    if (!is_array($extra)) $extra = [];
                    $csv_row = [
                        $row['student_name'],
                        $row['email'],
                        $row['phone'],
                        $row['school_name'],
                        $row['status'],
                    ];
                    foreach ($extra_keys as $key) {
                        $csv_row[] = $extra[$key] ?? '';
                    }
                    fputcsv($output, $csv_row);
                }

                fclose($output);
                exit;
            }
        }

        // Fallback: CPT-based export
        $args = [
            'post_type' => 'students',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $students = get_posts($args);

        if (empty($students)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No students found for export.</p></div>';
            });
            return;
        }

        $extra_slugs = [];
        if (class_exists('CG_Field_Schema')) {
            $seen_types = [];
            foreach ($students as $student) {
                $cert_type = get_post_meta($student->ID, 'certificate_type', true);
                $type_key  = CG_Field_Schema::cert_type_to_key($cert_type);
                if ($cert_type && !isset($seen_types[$type_key])) {
                    $seen_types[$type_key] = true;
                    foreach (CG_Field_Schema::get_extra_fields($cert_type) as $slug) {
                        if (!in_array($slug, $extra_slugs, true)) {
                            $extra_slugs[] = $slug;
                        }
                    }
                }
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=students_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        $headers = array_merge(
            ['student_name', 'email', 'school_name', 'issue_date', 'certificate_type'],
            $extra_slugs
        );
        fputcsv($output, $headers);

        foreach ($students as $student) {
            $row = [
                get_post_meta($student->ID, 'student_name', true),
                get_post_meta($student->ID, 'email', true),
                get_post_meta($student->ID, 'school_name', true),
                get_post_meta($student->ID, 'issue_date', true),
                get_post_meta($student->ID, 'certificate_type', true),
            ];
            foreach ($extra_slugs as $slug) {
                $row[] = get_post_meta($student->ID, $slug, true);
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_students');



// Render the Export Schools Page
function render_bulk_export_schools_page()
{
    echo '<div class="wrap">';
    echo '<h1>Export Schools</h1>';
    echo '<form method="post">';
    echo wp_nonce_field('cg_export_schools', '_wpnonce_cg_export', true, false);
    echo '<input type="hidden" name="export_schools" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export for Schools
function bulk_export_schools()
{
    if (isset($_POST['export_schools'])) {
        check_admin_referer('cg_export_schools', '_wpnonce_cg_export');
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Try SQL tables first, fall back to CPTs
        if (class_exists('\CertificateGenerator\Database\CustomTables')) {
            $tables = \CertificateGenerator\Database\CustomTables::instance();
            $school_table = $tables->get_table('schools');
            $table_exists = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                "SHOW TABLES LIKE %s", $school_table
            )) === $school_table;

            if ($table_exists) {
                $rows = $GLOBALS['wpdb']->get_results("SELECT * FROM $school_table ORDER BY id ASC", ARRAY_A);

                if (empty($rows)) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-warning"><p>No schools found for export.</p></div>';
                    });
                    return;
                }

                $extra_keys = [];
                foreach ($rows as $row) {
                    if (!empty($row['extra_fields'])) {
                        $extra = json_decode($row['extra_fields'], true);
                        if (is_array($extra)) {
                            $extra_keys = array_merge($extra_keys, array_keys($extra));
                        }
                    }
                }
                $extra_keys = array_values(array_unique($extra_keys));

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=schools_export.csv');
                header('Pragma: no-cache');
                header('Expires: 0');

                $output = fopen('php://output', 'w');
                $headers = ['school_name', 'city', 'status'];
                $headers = array_merge($headers, $extra_keys);
                fputcsv($output, $headers);

                foreach ($rows as $row) {
                    $extra = !empty($row['extra_fields']) ? json_decode($row['extra_fields'], true) : [];
                    if (!is_array($extra)) $extra = [];
                    $csv_row = [
                        $row['school_name'],
                        $row['city'] ?? '',
                        $row['status'] ?? 'active',
                    ];
                    foreach ($extra_keys as $key) {
                        $csv_row[] = $extra[$key] ?? '';
                    }
                    fputcsv($output, $csv_row);
                }

                fclose($output);
                exit;
            }
        }

        // Fallback: CPT-based export
        $args = [
            'post_type' => 'schools',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $schools = get_posts($args);

        if (empty($schools)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No schools found for export.</p></div>';
            });
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=schools_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        $headers = [
            'school_name',
            'school_abbreviation',
            'place',
            'issue_date',
            'certificate_type',
        ];
        fputcsv($output, $headers);

        foreach ($schools as $school) {
            $row = [
                get_post_meta($school->ID, 'school_name', true),
                get_post_meta($school->ID, 'school_abbreviation', true),
                get_post_meta($school->ID, 'place', true),
                get_post_meta($school->ID, 'issue_date', true),
                get_post_meta($school->ID, 'certificate_type', true),
            ];

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_schools');



// Render the Export Teachers Page
function render_bulk_export_teachers_page()
{
    echo '<div class="wrap">';
    echo '<h1>Export Teachers</h1>';
    echo '<form method="post">';
    echo wp_nonce_field('cg_export_teachers', '_wpnonce_cg_export', true, false);
    echo '<input type="hidden" name="export_teachers" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export for Teachers
function bulk_export_teachers()
{
    if (isset($_POST['export_teachers'])) {
        check_admin_referer('cg_export_teachers', '_wpnonce_cg_export');
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Try SQL tables first, fall back to CPTs
        if (class_exists('\CertificateGenerator\Database\CustomTables')) {
            $tables = \CertificateGenerator\Database\CustomTables::instance();
            $teacher_table = $tables->get_table('teachers');
            $table_exists = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                "SHOW TABLES LIKE %s", $teacher_table
            )) === $teacher_table;

            if ($table_exists) {
                $rows = $GLOBALS['wpdb']->get_results("SELECT * FROM $teacher_table ORDER BY id ASC", ARRAY_A);

                if (empty($rows)) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-warning"><p>No teachers found for export.</p></div>';
                    });
                    return;
                }

                $extra_keys = [];
                foreach ($rows as $row) {
                    if (!empty($row['extra_fields'])) {
                        $extra = json_decode($row['extra_fields'], true);
                        if (is_array($extra)) {
                            $extra_keys = array_merge($extra_keys, array_keys($extra));
                        }
                    }
                }
                $extra_keys = array_values(array_unique($extra_keys));

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=teachers_export.csv');
                header('Pragma: no-cache');
                header('Expires: 0');

                $output = fopen('php://output', 'w');
                $headers = ['teacher_name', 'email', 'phone', 'school_name', 'certificate_type', 'issue_date', 'status'];
                $headers = array_merge($headers, $extra_keys);
                fputcsv($output, $headers);

                foreach ($rows as $row) {
                    $extra = !empty($row['extra_fields']) ? json_decode($row['extra_fields'], true) : [];
                    if (!is_array($extra)) $extra = [];
                    $csv_row = [
                        $row['teacher_name'],
                        $row['email'],
                        $row['phone'] ?? '',
                        $row['school_name'],
                        $row['certificate_type'],
                        $row['issue_date'] ?? '',
                        $row['status'] ?? 'active',
                    ];
                    foreach ($extra_keys as $key) {
                        $csv_row[] = $extra[$key] ?? '';
                    }
                    fputcsv($output, $csv_row);
                }

                fclose($output);
                exit;
            }
        }

        // Fallback: CPT-based export
        $args = [
            'post_type' => 'teachers',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $teachers = get_posts($args);

        if (empty($teachers)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No teachers found for export.</p></div>';
            });
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=teachers_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        $headers = [
            'teacher_name',
            'email',
            'school_name',
            'school_abbreviation',
            'issue_date',
            'certificate_type',
        ];
        fputcsv($output, $headers);

        foreach ($teachers as $teacher) {
            $row = [
                get_post_meta($teacher->ID, 'teacher_name', true),
                get_post_meta($teacher->ID, 'email', true),
                get_post_meta($teacher->ID, 'school_name', true),
                get_post_meta($teacher->ID, 'school_abbreviation', true),
                get_post_meta($teacher->ID, 'issue_date', true),
                get_post_meta($teacher->ID, 'certificate_type', true),
            ];

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_teachers');


// Render the Export Certificates Page
function render_bulk_export_certificates_page()
{
    echo '<div class="wrap">';
    echo '<h1>Export Certificates</h1>';
    echo '<form method="post">';
    echo wp_nonce_field('cg_export_certificates', '_wpnonce_cg_export', true, false);
    echo '<input type="hidden" name="export_certificates" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export for Certificates
function bulk_export_certificates()
{
    if (isset($_POST['export_certificates'])) {
        check_admin_referer('cg_export_certificates', '_wpnonce_cg_export');
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Try SQL tables first, fall back to CPTs
        if (class_exists('\CertificateGenerator\Database\CustomTables')) {
            $tables = \CertificateGenerator\Database\CustomTables::instance();
            $template_table = $tables->get_table('certificate_templates');
            $table_exists = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                "SHOW TABLES LIKE %s", $template_table
            )) === $template_table;

            if ($table_exists) {
                $rows = $GLOBALS['wpdb']->get_results("SELECT * FROM $template_table ORDER BY id ASC", ARRAY_A);

                if (empty($rows)) {
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-warning"><p>No certificate templates found for export.</p></div>';
                    });
                    return;
                }

                $max_fields = class_exists('CG_Field_Schema') ? CG_Field_Schema::MAX_FIELDS : 15;
                $headers = ['template_name', 'certificate_type', 'event_date', 'template_url', 'orientation',
                            'page_size', 'font_style', 'font_size', 'font_color', 'qr_enabled',
                            'serial_number_display', 'status'];
                for ($i = 1; $i <= $max_fields; $i++) {
                    foreach (['position_x', 'position_y', 'visible', 'width', 'alignment'] as $prop) {
                        $headers[] = "field_{$i}_{$prop}";
                    }
                }

                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=certificates_export.csv');
                header('Pragma: no-cache');
                header('Expires: 0');

                $output = fopen('php://output', 'w');
                fputcsv($output, $headers);

                foreach ($rows as $row) {
                    $field_config = !empty($row['field_config']) ? json_decode($row['field_config'], true) : [];
                    $csv_row = [
                        $row['template_name'],
                        $row['certificate_type'],
                        $row['event_date'] ?? '',
                        $row['template_url'] ?? '',
                        $row['orientation'] ?? '',
                        $row['page_size'] ?? 'A4',
                        $row['font_style'] ?? '',
                        $row['font_size'] ?? 12,
                        $row['font_color'] ?? '#000000',
                        $row['qr_enabled'] ?? 0,
                        $row['serial_number_display'] ?? 0,
                        $row['status'] ?? 'draft',
                    ];
                    for ($i = 1; $i <= $max_fields; $i++) {
                        foreach (['position_x', 'position_y', 'visible', 'width', 'alignment'] as $prop) {
                            $csv_row[] = $field_config[$i][$prop] ?? '';
                        }
                    }
                    fputcsv($output, $csv_row);
                }

                fclose($output);
                exit;
            }
        }

        // Fallback: CPT-based export
        $args = [
            'post_type' => 'certificates',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $certificates = get_posts($args);

        if (empty($certificates)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No certificates found for export.</p></div>';
            });
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=certificates_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        $max_fields = class_exists('CG_Field_Schema') ? CG_Field_Schema::MAX_FIELDS : 15;
        $headers = ['certificate_type', 'event_date', 'template_url', 'template_orientation',
                    'font_size', 'font_color', 'font_style', 'template_field_count'];
        for ($i = 1; $i <= $max_fields; $i++) {
            foreach (['position_x', 'position_y', 'visible', 'width', 'alignment'] as $prop) {
                $headers[] = "field_{$i}_{$prop}";
            }
        }
        fputcsv($output, $headers);

        foreach ($certificates as $certificate) {
            $row = [
                get_post_meta($certificate->ID, 'certificate_type', true),
                get_post_meta($certificate->ID, 'event_date', true),
                get_post_meta($certificate->ID, 'template_url', true),
                get_post_meta($certificate->ID, 'template_orientation', true),
                get_post_meta($certificate->ID, 'font_size', true),
                get_post_meta($certificate->ID, 'font_color', true),
                get_post_meta($certificate->ID, 'font_style', true),
                get_post_meta($certificate->ID, 'template_field_count', true),
            ];
            for ($i = 1; $i <= $max_fields; $i++) {
                foreach (['position_x', 'position_y', 'visible', 'width', 'alignment'] as $prop) {
                    $row[] = get_post_meta($certificate->ID, "field_{$i}_{$prop}", true);
                }
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_certificates');
?>