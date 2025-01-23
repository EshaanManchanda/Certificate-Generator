<?php
// Add Export Students Submenu to Admin Menu
function add_bulk_export_students_menu() {
    add_submenu_page(
        'edit.php?post_type=students', // Parent slug (Students custom post type menu)
        'Export Students',            // Page title
        'Export Students',            // Menu title
        'manage_options',             // Capability required to access the menu
        'bulk-export-students',       // Menu slug
        'render_bulk_export_students_page' // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_export_students_menu');

// Render the Export Students Page
function render_bulk_export_students_page() {
    echo '<div class="wrap">';
    echo '<h1>Export Students</h1>';
    echo '<form method="post">';
    echo '<input type="hidden" name="export_students" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export
function bulk_export_students() {
    if (isset($_POST['export_students'])) {
        // Ensure no output conflicts
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Retrieve all student posts
        $args = [
            'post_type'   => 'students',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $students = get_posts($args);

        // If no students exist, show an admin notice
        if (empty($students)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No students found for export.</p></div>';
            });
            return;
        }

        // Set headers for file download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=students_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream for writing
        $output = fopen('php://output', 'w');

        // Add CSV column headers
        $headers = [
            'student_name',
            'email',
            'school_name',
            'issue_date',
            'certificate_type',
        ];
        fputcsv($output, $headers);

        // Add student data to the CSV
        foreach ($students as $student) {
            $row = [
                get_post_meta($student->ID, 'student_name', true),
                get_post_meta($student->ID, 'email', true),
                get_post_meta($student->ID, 'school_name', true),
                get_post_meta($student->ID, 'issue_date', true),
                get_post_meta($student->ID, 'certificate_type', true),
            ];

            fputcsv($output, $row);
        }

        // Close output stream and stop further execution
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_students');



// Add Export Schools Submenu to Admin Menu
function add_bulk_export_schools_menu() {
    add_submenu_page(
        'edit.php?post_type=schools', // Parent slug (Schools custom post type menu)
        'Export Schools',            // Page title
        'Export Schools',            // Menu title
        'manage_options',            // Capability required to access the menu
        'bulk-export-schools',       // Menu slug
        'render_bulk_export_schools_page' // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_export_schools_menu');

// Render the Export Schools Page
function render_bulk_export_schools_page() {
    echo '<div class="wrap">';
    echo '<h1>Export Schools</h1>';
    echo '<form method="post">';
    echo '<input type="hidden" name="export_schools" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export for Schools
function bulk_export_schools() {
    if (isset($_POST['export_schools'])) {
        // Ensure no output conflicts
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Retrieve all school posts
        $args = [
            'post_type'   => 'schools',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $schools = get_posts($args);

        // If no schools exist, show an admin notice
        if (empty($schools)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No schools found for export.</p></div>';
            });
            return;
        }

        // Set headers for file download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=schools_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream for writing
        $output = fopen('php://output', 'w');

        // Add CSV column headers
        $headers = [
            'school_name',
            'school_abbreviation',
            'place',
            'issue_date',
            'certificate_type',
        ];
        fputcsv($output, $headers);

        // Add school data to the CSV
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

        // Close output stream and stop further execution
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_schools');



// Add Export Teachers Submenu to Admin Menu
function add_bulk_export_teachers_menu() {
    add_submenu_page(
        'edit.php?post_type=teachers', // Parent slug (Teachers custom post type menu)
        'Export Teachers',            // Page title
        'Export Teachers',            // Menu title
        'manage_options',             // Capability required to access the menu
        'bulk-export-teachers',       // Menu slug
        'render_bulk_export_teachers_page' // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_export_teachers_menu');

// Render the Export Teachers Page
function render_bulk_export_teachers_page() {
    echo '<div class="wrap">';
    echo '<h1>Export Teachers</h1>';
    echo '<form method="post">';
    echo '<input type="hidden" name="export_teachers" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export for Teachers
function bulk_export_teachers() {
    if (isset($_POST['export_teachers'])) {
        // Ensure no output conflicts
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Retrieve all teacher posts
        $args = [
            'post_type'   => 'teachers',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $teachers = get_posts($args);

        // If no teachers exist, show an admin notice
        if (empty($teachers)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No teachers found for export.</p></div>';
            });
            return;
        }

        // Set headers for file download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=teachers_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream for writing
        $output = fopen('php://output', 'w');

        // Add CSV column headers
        $headers = [
            'teacher_name',
            'email',
            'school_name',
            'school_abbreviation',
            'issue_date',
            'certificate_type',
        ];
        fputcsv($output, $headers);

        // Add teacher data to the CSV
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

        // Close output stream and stop further execution
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_teachers');


// Add Export Certificates Submenu to Admin Menu
function add_bulk_export_certificates_menu() {
    add_submenu_page(
        'edit.php?post_type=certificates', // Parent slug (Certificates custom post type menu)
        'Export Certificates',            // Page title
        'Export Certificates',            // Menu title
        'manage_options',                 // Capability required to access the menu
        'bulk-export-certificates',       // Menu slug
        'render_bulk_export_certificates_page' // Callback function to render the page
    );
}
add_action('admin_menu', 'add_bulk_export_certificates_menu');

// Render the Export Certificates Page
function render_bulk_export_certificates_page() {
    echo '<div class="wrap">';
    echo '<h1>Export Certificates</h1>';
    echo '<form method="post">';
    echo '<input type="hidden" name="export_certificates" value="1" />';
    echo '<p><button type="submit" class="button button-primary">Export to CSV</button></p>';
    echo '</form>';
    echo '</div>';
}

// Handle CSV Export for Certificates
function bulk_export_certificates() {
    if (isset($_POST['export_certificates'])) {
        // Ensure no output conflicts
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Retrieve all certificate posts
        $args = [
            'post_type'   => 'certificates',
            'post_status' => 'publish',
            'numberposts' => -1,
        ];
        $certificates = get_posts($args);

        // If no certificates exist, show an admin notice
        if (empty($certificates)) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning"><p>No certificates found for export.</p></div>';
            });
            return;
        }

        // Set headers for file download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=certificates_export.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream for writing
        $output = fopen('php://output', 'w');

        // Add CSV column headers
        $headers = [
            'certificate_type',
            'template_url',
            'template_orientation',
            'font_size',
            'font_color',
            'font_style',
            'field_1_position_x',
            'field_1_position_y',
            'field_1_visible',
            'field_2_position_x',
            'field_2_position_y',
            'field_2_visible',
            'field_3_position_x',
            'field_3_position_y',
            'field_3_visible',
        ];
        fputcsv($output, $headers);

        // Add certificate data to the CSV
        foreach ($certificates as $certificate) {
            $row = [
                get_post_meta($certificate->ID, 'certificate_type', true),
                get_post_meta($certificate->ID, 'template_url', true),
                get_post_meta($certificate->ID, 'template_orientation', true),
                get_post_meta($certificate->ID, 'font_size', true),
                get_post_meta($certificate->ID, 'font_color', true),
                get_post_meta($certificate->ID, 'font_style', true),
                get_post_meta($certificate->ID, 'field_1_position_x', true),
                get_post_meta($certificate->ID, 'field_1_position_y', true),
                get_post_meta($certificate->ID, 'field_1_visible', true),
                get_post_meta($certificate->ID, 'field_2_position_x', true),
                get_post_meta($certificate->ID, 'field_2_position_y', true),
                get_post_meta($certificate->ID, 'field_2_visible', true),
                get_post_meta($certificate->ID, 'field_3_position_x', true),
                get_post_meta($certificate->ID, 'field_3_position_y', true),
                get_post_meta($certificate->ID, 'field_3_visible', true),
            ];

            fputcsv($output, $row);
        }

        // Close output stream and stop further execution
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'bulk_export_certificates');



?>