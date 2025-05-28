<?php
/*
Plugin Name: Custom Post Types with ACF and Meta Boxes
Description: A plugin to create custom post types, advanced custom fields, and custom meta boxes for Students, Teachers, Schools, and Certificates.
Version: 1.3
Author: Your Name
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/student-certificate-search.php';
// Function to Register Custom Post Types
function register_custom_post_type($type, $singular, $plural, $supports = ['title', 'custom-fields'])
{
    register_post_type($type, [
        'labels' => [
            'name' => __($plural),
            'singular_name' => __($singular),
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => &$supports,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
    ]);
}

// Register All Custom Post Types
function register_custom_post_types()
{
    register_custom_post_type('students', 'Student', 'Students');
    register_custom_post_type('teachers', 'Teacher', 'Teachers');
    register_custom_post_type('schools', 'School', 'Schools');
    register_custom_post_type('certificates', 'Certificate', 'Certificates');
}
add_action('init', 'register_custom_post_types');


// Define Fields for Students
function render_students_form($post)
{
    $student_name = get_post_meta($post->ID, 'student_name', true);
    $email = get_post_meta($post->ID, 'email', true);
    $school_name = get_post_meta($post->ID, 'school_name', true);
    $issue_date = get_post_meta($post->ID, 'issue_date', true);
    $certificate_type = get_post_meta($post->ID, 'certificate_type', true);

?>
    <div class="custom-form-wrap">
        <div class="custom-form-group">
            <label for="student_name">Student Name:</label>
            <input type="text" id="student_name" name="student_name" value="<?php echo esc_attr($student_name); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($email); ?>" class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="school_name">School Name:</label>
            <input type="text" id="school_name" name="school_name" value="<?php echo esc_attr($school_name); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="issue_date">Issue Date:</label>
            <input type="date" id="issue_date" name="issue_date" value="<?php echo esc_attr($issue_date); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="certificate_type">Certificate Type:</label>
            <input type="text" id="certificate_type" name="certificate_type"
                value="<?php echo esc_attr($certificate_type); ?>" class="custom-form-input">
        </div>
    </div>
<?php
}

// Add Meta Box for Students Post Type
function add_students_meta_box()
{
    add_meta_box(
        'students_meta_box',
        'Student Details',
        'render_students_form',
        'students',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_students_meta_box');

// Save Students Data and Prevent Infinite Loop
function save_students_data($post_id)
{
    // Prevent recursion
    static $updating = false;
    if ($updating)
        return;

    // Check if this is an autosave or if it's not a 'students' post type
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (get_post_type($post_id) !== 'students') {
        return;
    }

    // Update meta fields
    if (isset($_POST['student_name'])) {
        update_post_meta($post_id, 'student_name', sanitize_text_field($_POST['student_name']));
    }

    if (isset($_POST['email'])) {
        update_post_meta($post_id, 'email', sanitize_email($_POST['email']));
    }

    if (isset($_POST['school_name'])) {
        update_post_meta($post_id, 'school_name', sanitize_text_field($_POST['school_name']));
    }

    if (isset($_POST['issue_date'])) {
        update_post_meta($post_id, 'issue_date', sanitize_text_field($_POST['issue_date']));
    }

    if (isset($_POST['certificate_type'])) {
        update_post_meta($post_id, 'certificate_type', sanitize_text_field($_POST['certificate_type']));
    }

    // Auto-generate the title
    $student_name = get_post_meta($post_id, 'student_name', true);
    $school_name = get_post_meta($post_id, 'school_name', true);

    if ($student_name || $school_name) {
        $updating = true; // Prevent recursive calls
        $new_title = ($student_name ?: 'Student') . ' - ' . ($school_name ?: 'Unknown School');
        $new_slug = sanitize_title($new_title);

        // Update the post title and slug
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $new_title,
            'post_name' => $new_slug,
        ]);
        $updating = false;
    }
}
add_action('save_post', 'save_students_data');


// Define Fields for Teachers
function render_teachers_form($post)
{
    $teacher_name = get_post_meta($post->ID, 'teacher_name', true);
    $email = get_post_meta($post->ID, 'email', true);
    $school_name = get_post_meta($post->ID, 'school_name', true);
    $school_abbreviation = get_post_meta($post->ID, 'school_abbreviation', true);
    $issue_date = get_post_meta($post->ID, 'issue_date', true);
    $certificate_type = get_post_meta($post->ID, 'certificate_type', true);

?>
    <div class="custom-form-wrap">
        <div class="custom-form-group">
            <label for="teacher_name">Teacher Name:</label>
            <input type="text" id="teacher_name" name="teacher_name" value="<?php echo esc_attr($teacher_name); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($email); ?>" class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="school_name">School Name:</label>
            <input type="text" id="school_name" name="school_name" value="<?php echo esc_attr($school_name); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="school_abbreviation">School Abbreviation:</label>
            <input type="text" id="school_abbreviation" name="school_abbreviation"
                value="<?php echo esc_attr($school_abbreviation); ?>" class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="issue_date">Issue Date:</label>
            <input type="date" id="issue_date" name="issue_date" value="<?php echo esc_attr($issue_date); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="certificate_type">Certificate Type:</label>
            <input type="text" id="certificate_type" name="certificate_type"
                value="<?php echo esc_attr($certificate_type); ?>" class="custom-form-input">
        </div>
    </div>
<?php
}

// Add Meta Box for Teachers Post Type
function add_teachers_meta_box()
{
    add_meta_box(
        'teachers_meta_box',
        'Teacher Details',
        'render_teachers_form',
        'teachers',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_teachers_meta_box');

// Save Teachers Data
function save_teachers_data($post_id)
{
    // Check if data is being saved correctly
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ensure this is for the 'teachers' post type
    if (get_post_type($post_id) !== 'teachers') {
        return;
    }

    // Update meta fields if set
    if (isset($_POST['teacher_name'])) {
        update_post_meta($post_id, 'teacher_name', sanitize_text_field($_POST['teacher_name']));
    }

    if (isset($_POST['school_name'])) {
        $school_name = sanitize_text_field($_POST['school_name']);
        update_post_meta($post_id, 'school_name', $school_name);

        // Generate school abbreviation
        $words = explode(' ', $school_name);
        $abbreviation = '';
        foreach ($words as $word) {
            $abbreviation .= strtoupper($word[0]); // Get the first letter of each word
        }
        update_post_meta($post_id, 'school_abbreviation', $abbreviation); // Save abbreviation
    }

    if (isset($_POST['email'])) {
        update_post_meta($post_id, 'email', sanitize_text_field($_POST['email']));
    }
    if (isset($_POST['issue_date'])) {
        update_post_meta($post_id, 'issue_date', sanitize_text_field($_POST['issue_date']));
    }

    if (isset($_POST['certificate_type'])) {
        update_post_meta($post_id, 'certificate_type', sanitize_text_field($_POST['certificate_type']));
    }


    // Auto-generate the title for the teacher
    $teacher_name = get_post_meta($post_id, 'teacher_name', true);
    $school_name = get_post_meta($post_id, 'school_name', true);

    if (!empty($teacher_name) || !empty($school_name)) {
        $new_title = ($teacher_name ?: 'Teacher') . ' - ' . ($school_name ?: 'Unknown School');
        $new_slug = sanitize_title($new_title);

        // Prevent infinite loop by removing and re-adding the save action
        remove_action('save_post', 'save_teachers_data');
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $new_title,
            'post_name' => $new_slug,
        ]);
        add_action('save_post', 'save_teachers_data');
    }
}
add_action('save_post', 'save_teachers_data', 10, 3);


// Define Fields for Schools
function render_schools_form($post)
{
    $school_name = get_post_meta($post->ID, 'school_name', true);
    $school_abbreviation = get_post_meta($post->ID, 'school_abbreviation', true);
    $place = get_post_meta($post->ID, 'place', true);
    $issue_date = get_post_meta($post->ID, 'issue_date', true);
    $certificate_type = get_post_meta($post->ID, 'certificate_type', true);

?>
    <div class="custom-form-wrap">
        <div class="custom-form-group">
            <label for="school_name">School Name:</label>
            <input type="text" id="school_name" name="school_name" value="<?php echo esc_attr($school_name); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="school_abbreviation">School Abbreviation:</label>
            <input type="text" id="school_abbreviation" name="school_abbreviation"
                value="<?php echo esc_attr($school_abbreviation); ?>" class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="place">Place:</label>
            <input type="text" id="place" name="place" value="<?php echo esc_attr($place); ?>" class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="issue_date">Issue Date:</label>
            <input type="date" id="issue_date" name="issue_date" value="<?php echo esc_attr($issue_date); ?>"
                class="custom-form-input">
        </div>
        <div class="custom-form-group">
            <label for="certificate_type">Certificate Type:</label>
            <input type="text" id="certificate_type" name="certificate_type"
                value="<?php echo esc_attr($certificate_type); ?>" class="custom-form-input">
        </div>
    </div>
<?php
}

// Add Meta Box for Schools Post Type
function add_schools_meta_box()
{
    add_meta_box(
        'schools_meta_box',
        'School Details',
        'render_schools_form',
        'schools',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_schools_meta_box');

// Save Schools Data
function save_schools_data($post_id)
{
    // Verify it's not an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ensure this logic applies only to the 'schools' post type
    if (get_post_type($post_id) !== 'schools') {
        return;
    }

    // Update meta fields if set
    if (isset($_POST['school_name'])) {
        $school_name = sanitize_text_field($_POST['school_name']);
        update_post_meta($post_id, 'school_name', $school_name);

        // Generate school abbreviation
        $words = explode(' ', $school_name);
        $abbreviation = '';
        foreach ($words as $word) {
            $abbreviation .= strtoupper($word[0]); // Get the first letter of each word
        }
        update_post_meta($post_id, 'school_abbreviation', $abbreviation); // Save abbreviation
    }
    if (isset($_POST['place'])) {
        update_post_meta($post_id, 'place', sanitize_text_field($_POST['place']));
    }
    if (isset($_POST['issue_date'])) {
        update_post_meta($post_id, 'issue_date', sanitize_text_field($_POST['issue_date']));
    }
    if (isset($_POST['certificate_type'])) {
        update_post_meta($post_id, 'certificate_type', sanitize_text_field($_POST['certificate_type']));
    }

    // Auto-generate the title based on school-specific fields
    $school_name = get_post_meta($post_id, 'school_name', true);
    $place = get_post_meta($post_id, 'place', true);

    if (!empty($school_name) || !empty($place)) {
        $new_title = ($school_name ?: 'School') . ' - ' . ($place ?: 'Unknown Place');
        $new_slug = sanitize_title($new_title);

        // Prevent infinite loop
        remove_action('save_post', 'save_schools_data');
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $new_title,
            'post_name' => $new_slug,
        ]);
        add_action('save_post', 'save_schools_data');
    }
}
add_action('save_post', 'save_schools_data', 10, 3);

// Define Fields for Certificates
function render_certificates_form($post)
{
    $certificate_type = get_post_meta($post->ID, 'certificate_type', true);
    $template_url = get_post_meta($post->ID, 'template_url', true);
    $template_orientation = get_post_meta($post->ID, 'template_orientation', true);
    $font_size = get_post_meta($post->ID, 'font_size', true) ?: '12'; // Default font size
    $font_color = get_post_meta($post->ID, 'font_color', true) ?: '#000000'; // Default black color
    $font_style = get_post_meta($post->ID, 'font_style', true) ?: 'Arial'; // Default font style
    $font_styles_list = ['Arial', 'Helvetica', 'Times New Roman', 'Courier New', 'Verdana', 'Palatino', 'Garamond'];
    // Set paper sizes based on orientation
    $paper_size = ($template_orientation == 'landscape') ? "Landscape (297mm x 210mm)" : "Portrait (210mm x 297mm)";
    $width = get_post_meta($post->ID, 'width', true) ?: '210'; // Default width

?>
    <div class="custom-form-wrap">
        <h2>Certificate Configuration</h2>
        <p><strong>Paper Size:</strong> <?php echo esc_html($paper_size); ?></p>

        <div class="custom-form-group">
            <label for="certificate_type"><strong>Certificate Type:</strong></label>
            <input type="text" id="certificate_type" name="certificate_type"
                value="<?php echo esc_attr($certificate_type); ?>" class="custom-form-input">
        </div>

        <div class="custom-form-group">
            <label for="template_url"><strong>Template URL:</strong></label>
            <input type="url" id="template_url" name="template_url" value="<?php echo esc_attr($template_url); ?>"
                class="custom-form-input">
        </div>

        <div class="custom-form-group">
            <label for="template_orientation"><strong>Template Orientation:</strong></label>
            <select id="template_orientation" name="template_orientation" class="custom-form-input">
                <option value="landscape" <?php selected($template_orientation, 'landscape'); ?>>Landscape (297x210 mm)
                </option>
                <option value="portrait" <?php selected($template_orientation, 'portrait'); ?>>Portrait (210x297 mm)
                </option>
            </select>
        </div>

        <h3>Font Settings</h3>
        <div class="custom-form-group">
            <label for="font_size"><strong>Font Size:</strong></label>
            <input type="number" id="font_size" name="font_size" value="<?php echo esc_attr($font_size); ?>"
                class="custom-form-input" min="6" max="48">
        </div>

        <div class="custom-form-group">
            <label for="font_color"><strong>Font Color:</strong></label>
            <input type="color" id="font_color" name="font_color" value="<?php echo esc_attr($font_color); ?>"
                class="custom-form-input">
        </div>

        <div class="custom-form-group">
            <label for="font_style"><strong>Font Style:</strong></label>
            <select id="font_style" name="font_style" class="custom-form-input">
                <?php foreach ($font_styles_list as $style): ?>
                    <option value="<?php echo esc_attr($style); ?>" <?php selected($font_style, $style); ?>>
                        <?php echo esc_html($style); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <h3>Field Positioning (Based on Selected Orientation)</h3>
        <p><strong>Note:</strong> Adjust X & Y positions based on the paper size above.</p>


        <?php

        for ($i = 1; $i <= 3; $i++) {
            $position_x = get_post_meta($post->ID, "field_{$i}_position_x", true);
            $position_y = get_post_meta($post->ID, "field_{$i}_position_y", true);
            $visibility = get_post_meta($post->ID, "field_{$i}_visible", true) ?: '0';
            $field_width = get_post_meta($post->ID, "field_{$i}_width", true) ?: '100'; // Default width
            $alignment = get_post_meta($post->ID, "field_{$i}_alignment", true) ?: 'C'; // Default center alignment

        ?>
            <div class="custom-form-wrap">
                <h4>Field <?php echo $i; ?></h4>
                <div class="custom-form-group">
                    <label for="field_<?php echo $i; ?>_position_x"><strong>Position X:</strong> (0 to
                        <?php echo ($template_orientation == 'landscape') ? '297' : '210'; ?> mm)</label>
                    <input type="number" id="field_<?php echo $i; ?>_position_x" name="field_<?php echo $i; ?>_position_x"
                        value="<?php echo esc_attr($position_x); ?>" class="custom-form-input" min="0"
                        max="<?php echo ($template_orientation == 'landscape') ? '297' : '210'; ?>">
                </div>
                <div class="custom-form-group">
                    <label for="field_<?php echo $i; ?>_position_y"><strong>Position Y:</strong> (0 to
                        <?php echo ($template_orientation == 'landscape') ? '210' : '297'; ?> mm)</label>
                    <input type="number" id="field_<?php echo $i; ?>_position_y" name="field_<?php echo $i; ?>_position_y"
                        value="<?php echo esc_attr($position_y); ?>" class="custom-form-input" min="0"
                        max="<?php echo ($template_orientation == 'landscape') ? '210' : '297'; ?>">
                </div>
                <div class="custom-form-group">
                    <label for="field_<?php echo $i; ?>_visible"><strong>Visible:</strong></label>
                    <input type="checkbox" id="field_<?php echo $i; ?>_visible" name="field_<?php echo $i; ?>_visible" value="1"
                        <?php checked($visibility, '1'); ?>> Show this field
                </div>
                <div class="custom-form-group">
                    <label for="field_<?php echo $i; ?>_width"><strong>Width:</strong></label>
                    <input type="number" id="field_<?php echo $i; ?>_width" name="field_<?php echo $i; ?>_width"
                        value="<?php echo esc_attr($field_width); ?>" class="custom-form-input" min="0" max="297">
                </div>
                <div class="custom-form-group">
                    <label for="field_<?php echo $i; ?>_alignment"><strong>Alignment:</strong></label>
                    <select id="field_<?php echo $i; ?>_alignment" name="field_<?php echo $i; ?>_alignment" class="custom-form-input">
                        <option value="L" <?php selected($alignment, 'L'); ?>>Left</option>
                        <option value="C" <?php selected($alignment, 'C'); ?>>Center</option>
                        <option value="R" <?php selected($alignment, 'R'); ?>>Right</option>
                    </select>
                </div>
            </div>
        <?php
        }
        ?>
    </div>
<?php
}

// Add Meta Box for Certificates Post Type
function add_certificates_meta_box()
{
    add_meta_box(
        'certificates_meta_box',
        'Certificate Details',
        'render_certificates_form',
        'certificates',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_certificates_meta_box');

// Save Certificates Data
function save_certificates_data($post_id)
{
    // Check if data is being saved correctly
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (isset($_POST['certificate_type'])) {
        update_post_meta($post_id, 'certificate_type', sanitize_text_field($_POST['certificate_type']));
    }

    if (isset($_POST['template_url'])) {
        update_post_meta($post_id, 'template_url', esc_url_raw($_POST['template_url']));
    }
    if (isset($_POST['template_orientation'])) {
        update_post_meta($post_id, 'template_orientation', sanitize_text_field($_POST['template_orientation']));
    }

    if (isset($_POST['font_size'])) {
        update_post_meta($post_id, 'font_size', intval($_POST['font_size']));
    }

    if (isset($_POST['font_color'])) {
        update_post_meta($post_id, 'font_color', sanitize_hex_color($_POST['font_color']));
    }

    if (isset($_POST['font_style'])) {
        update_post_meta($post_id, 'font_style', sanitize_text_field($_POST['font_style']));
    }

    for ($i = 1; $i <= 3; $i++) {
        if (isset($_POST["field_{$i}_position_x"])) {
            update_post_meta($post_id, "field_{$i}_position_x", sanitize_text_field($_POST["field_{$i}_position_x"]));
        }

        if (isset($_POST["field_{$i}_position_y"])) {
            update_post_meta($post_id, "field_{$i}_position_y", sanitize_text_field($_POST["field_{$i}_position_y"]));
        }

        $visibility = isset($_POST["field_{$i}_visible"]) ? '1' : '0';
        update_post_meta($post_id, "field_{$i}_visible", $visibility);

        if (isset($_POST["field_{$i}_width"])) {
            update_post_meta($post_id, "field_{$i}_width", intval($_POST["field_{$i}_width"]));
        }

        if (isset($_POST["field_{$i}_alignment"])) {
            update_post_meta($post_id, "field_{$i}_alignment", sanitize_text_field($_POST["field_{$i}_alignment"]));
        }
    }

    // Auto-generate the title for the certificate
    $certificate_type = get_post_meta($post_id, 'certificate_type', true);
    $template_url = get_post_meta($post_id, 'template_url', true);
    $width = get_post_meta($post_id, 'width', true);

    if ($certificate_type || $template_url) {
        $new_title = ($certificate_type ?: 'Certificate') . ' - ' . ($template_url ? parse_url($template_url, PHP_URL_HOST) : 'No Template URL');
        $new_slug = sanitize_title($new_title);

        // Prevent infinite loop by removing and re-adding the save action
        remove_action('save_post', 'save_certificates_data');
        wp_update_post([
            'ID' => $post_id,
            'post_title' => $new_title,
            'post_name' => $new_slug,
        ]);
        add_action('save_post', 'save_certificates_data');
    }
}
add_action('save_post', 'save_certificates_data');

// Add Preview Certificate Button to Certificates Admin Page
add_action('add_meta_boxes', 'add_preview_certificate_meta_box');
function add_preview_certificate_meta_box()
{
    add_meta_box(
        'preview_certificate_meta_box',
        'Preview Certificate',
        'render_preview_certificate_button',
        'certificates',
        'side',
        'high'
    );
}

function render_preview_certificate_button($post)
{
    $preview_url = add_query_arg(
        ['preview_certificate' => '1', 'post_id' => $post->ID],
        site_url()
    );

    echo '<a href="' . esc_url($preview_url) . '" target="_blank" class="button">Preview Certificate</a>';
}

add_action('init', 'handle_certificate_preview');
function handle_certificate_preview()
{
    if (isset($_GET['preview_certificate']) && $_GET['preview_certificate'] === '1' && isset($_GET['post_id'])) {
        $post_id = intval($_GET['post_id']);

        // Fetch certificate type dynamically or set a default
        $certificate_type = get_post_meta($post_id, 'certificate_type', true);
        if (empty($certificate_type)) {
            $certificate_type = 'Default Certificate Type'; // Set a fallback value
        }

        // Fetch certificate data
        $post_data = [
            'student_name' => 'Jonathan Alexander Christopher Montgomery III',
            'school_name' => 'The Prestigious International Institute of Advanced Technological and Scientific Studies',
            'issue_date' => '01-01-2025',
            'certificate_type' => $certificate_type,
        ];

        // Generate the PDF for preview
        $certificate_url = generate_certificate_pdf_with_data($post_data);
        if ($certificate_url) {
            wp_redirect($certificate_url);
            exit;
        } else {
            wp_die('Failed to generate certificate preview.');
        }
    }
}

// Fields Definitions
$student_fields = [
    [
        'key' => 'field_student_name',
        'label' => 'Student Name',
        'name' => 'student_name',
        'type' => 'text',
    ],
    [
        'key' => 'field_email',
        'label' => 'Email',
        'name' => 'email',
        'type' => 'email',
    ],
    [
        'key' => 'field_school_name',
        'label' => 'School Name',
        'name' => 'school_name',
        'type' => 'text',
    ],
    [
        'key' => 'field_issue_date',
        'label' => 'Issue Date',
        'name' => 'issue_date',
        'type' => 'date_picker',
        'display_format' => 'd-m-Y',
        'return_format' => 'd-m-Y',
        'default_value' => date('d-m-Y'),
    ],
    [
        'key' => 'field_certificate_type',
        'label' => 'Certificate Type',
        'name' => 'certificate_type',
        'type' => 'text',
    ],
];

$teacher_fields = [
    [
        'key' => 'field_teacher_name',
        'label' => 'Teacher Name',
        'name' => 'teacher_name',
        'type' => 'text',
    ],
    [
        'key' => 'field_email_teacher',
        'label' => 'Email',
        'name' => 'email',
        'type' => 'email',
    ],
    [
        'key' => 'field_school_name_teacher',
        'label' => 'School Name',
        'name' => 'school_name',
        'type' => 'text',
    ],
    [
        'key' => 'field_school_abbreviation_teacher',
        'label' => 'School Abbreviation',
        'name' => 'school_abbreviation',
        'type' => 'text',
    ],
    [
        'key' => 'field_issue_date_teacher',
        'label' => 'Issue Date',
        'name' => 'issue_date',
        'type' => 'date_picker',
        'display_format' => 'd-m-Y',
        'return_format' => 'd-m-Y',
        'default_value' => date('d-m-Y'),
    ],
    [
        'key' => 'field_certificate_type',
        'label' => 'Certificate Type',
        'name' => 'certificate_type',
        'type' => 'text',
    ],
];

$school_fields = [
    [
        'key' => 'field_school_name_schools',
        'label' => 'School Name',
        'name' => 'school_name',
        'type' => 'text',
    ],
    [
        'key' => 'field_school_abbreviation_schools',
        'label' => 'School Abbreviation',
        'name' => 'school_abbreviation',
        'type' => 'text',
    ],
    [
        'key' => 'field_place',
        'label' => 'Place',
        'name' => 'place',
        'type' => 'text',
    ],
    [
        'key' => 'field_issue_date_schools',
        'label' => 'Issue Date',
        'name' => 'issue_date',
        'type' => 'date_picker',
        'display_format' => 'd-m-Y',
        'return_format' => 'd-m-Y',
        'default_value' => date('d-m-Y'),
    ],
    [
        'key' => 'field_certificate_type',
        'label' => 'Certificate Type',
        'name' => 'certificate_type',
        'type' => 'text',
    ],
];

// Define Fields for Certificates
$certificate_fields = [
    [
        'key' => 'field_certificate_type',
        'label' => 'Certificate Type',
        'name' => 'certificate_type',
        'type' => 'text',
    ],
    [
        'key' => 'field_template_url',
        'label' => 'Template URL',
        'name' => 'template_url',
        'type' => 'url',
    ],
    [
        'key' => 'field_template_orientation',
        'label' => 'Template Orientation',
        'name' => 'template_orientation',
        'type' => 'select',
        'choices' => [
            'landscape' => 'Landscape',
            'portrait' => 'Portrait',
        ],
    ],
    [
        'key' => 'field_font_size',
        'label' => 'Font Size',
        'name' => 'font_size',
        'type' => 'number',
    ],
    [
        'key' => 'field_font_color',
        'label' => 'Font Color',
        'name' => 'font_color',
        'type' => 'color_picker',
    ],
    [
        'key' => 'field_font_style',
        'label' => 'Font Style',
        'name' => 'font_style',
        'type' => 'select',
        'choices' => [
            'Arial' => 'Arial',
            'Helvetica' => 'Helvetica',
            'Times New Roman' => 'Times New Roman',
            'Courier New' => 'Courier New',
            'Verdana' => 'Verdana',
            'Palatino' => 'Palatino',
            'Garamond' => 'Garamond',
        ],
    ],
    [
        'key' => 'field_field_1_position_x',
        'label' => 'Field 1 Position X',
        'name' => 'field_1_position_x',
        'type' => 'number',
    ],
    [
        'key' => 'field_field_1_position_y',
        'label' => 'Field 1 Position Y',
        'name' => 'field_1_position_y',
        'type' => 'number',
    ],
    [
        'key' => 'field_field_1_visible',
        'label' => 'Field 1 Visible',
        'name' => 'field_1_visible',
        'type' => 'true_false',
        'default_value' => 1,
    ],
    [
        'key' => 'field_field_2_position_x',
        'label' => 'Field 2 Position X',
        'name' => 'field_2_position_x',
        'type' => 'number',
    ],
    [
        'key' => 'field_field_2_position_y',
        'label' => 'Field 2 Position Y',
        'name' => 'field_2_position_y',
        'type' => 'number',
    ],
    [
        'key' => 'field_field_2_visible',
        'label' => 'Field 2 Visible',
        'name' => 'field_2_visible',
        'type' => 'true_false',
        'default_value' => 1,
    ],
    [
        'key' => 'field_field_3_position_x',
        'label' => 'Field 3 Position X',
        'name' => 'field_3_position_x',
        'type' => 'number',
    ],
    [
        'key' => 'field_field_3_position_y',
        'label' => 'Field 3 Position Y',
        'name' => 'field_3_position_y',
        'type' => 'number',
    ],
    [
        'key' => 'field_field_3_visible',
        'label' => 'Field 3 Visible',
        'name' => 'field_3_visible',
        'type' => 'true_false',
        'default_value' => 1,
    ],
];

// Add Advanced Custom Fields
function add_acf_field_group($group_key, $title, $fields, $post_type)
{
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group([
            'key' => $group_key,
            'title' => $title,
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => $post_type,
                    ],
                ]
            ],
        ]);
    }
}

function add_custom_fields()
{
    global $student_fields, $teacher_fields, $school_fields, $certificate_fields;
    add_acf_field_group('group_students', 'Student Fields', $student_fields, 'students');
    add_acf_field_group('group_teachers', 'Teacher Fields', $teacher_fields, 'teachers');
    add_acf_field_group('group_schools', 'School Fields', $school_fields, 'schools');
    add_acf_field_group('group_certificates', 'Certificate Settings', $certificate_fields, 'certificates');
}
add_action('acf/init', 'add_custom_fields');

// Add Admin Page for Data Management
// function custom_post_admin_menu() {
//     add_menu_page(
//         'Custom Post Management',
//         'Post Management',
//         'manage_options',
//         'custom-post-management',
//         'render_custom_post_admin_page',
//         'dashicons-admin-generic',
//         20
//     );
// }
// add_action('admin_menu', 'custom_post_admin_menu');

// Render the Admin Page
function render_custom_post_admin_page()
{
    echo '<div class="wrap">';
    echo '<h1>Manage Custom Post Data</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('custom_post_options_group');
    do_settings_sections('custom-post-management');
    submit_button();
    echo '</form>';
    echo '</div>';
}



?>