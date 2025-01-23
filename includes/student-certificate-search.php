<?php

// Include FPDF library (add this library in your plugin directory)
require_once __DIR__ . '/fpdf/fpdf.php';

// Shortcode to search for teacher certificates
add_shortcode('teacher_search', 'teacher_search_shortcode');
function teacher_search_shortcode()
{
    if (isset($_GET['teacher_name']) && isset($_GET['school_name'])) {
        $name = sanitize_text_field($_GET['teacher_name']);
        $school = sanitize_text_field($_GET['school_name']);

        $args = [
            'post_type' => 'teachers',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'teacher_name',
                    'value' => $name,
                    'compare' => 'LIKE',
                ],
                [
                    'relation' => 'OR', // Match either full name or abbreviation
                    [
                        'key' => 'school_name',
                        'value' => $school,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key' => 'school_abbreviation', // Check abbreviation
                        'value' => $school,
                        'compare' => 'LIKE',
                    ],
                ],
            ],
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $output = '<ul style="max-width: 600px; margin: 20px auto; padding: 0; list-style: none; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $fields = ['teacher_name', 'school_name', 'issue_date'];
                $file_url = generate_certificate_pdf($post_id,$fields);
                $output .= '<li style="padding: 15px 20px; border-bottom: 1px solid #ddd; font-size: 14px; display: flex; flex-direction: column; gap: 8px;">';
                $output .= '<strong>' . get_the_title() . '</strong><br>';
                $output .= '<span>School: ' . get_post_meta(get_the_ID(), 'school_name', true) . '</span>';
                $output .= '<span>Certificate: ' . get_post_meta(get_the_ID(), 'certificate_type', true) . '</span>';
                $output .= '<span>Issued On: ' . get_post_meta(get_the_ID(), 'issue_date', true) . '</span>';
                if ($file_url) {
                    $output .= '<a href="' . esc_url($file_url) . '" target="_blank" style="text-decoration: none; color: #0073aa; font-weight: bold; transition: color 0.3s ease;">Download Certificate</a>';
                }
                $output .= '</li>';
            }
            $output .= '</ul>';
        } else {
            $output = '<p style="text-align: center; color: #666;">No certificates found for the provided name and school.</p>';
        }
        wp_reset_postdata();
    } else {
        $output = '<form method="get" style="max-width: 600px; margin: 40px auto; padding: 20px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
        $output .= '<label for="teacher_name" style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #333;">Enter your name:</label>';
        $output .= '<input type="text" name="teacher_name" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">';
        $output .= '<label for="school_name" style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #333;">Enter your school name:</label>';
        $output .= '<input type="text" name="school_name" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">';
        $output .= '<button type="submit" style="display: block; width: 100%; padding: 12px; background: linear-gradient(to right, #0073aa, #005f8d); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; text-align: center; transition: background 0.3s ease;">Search</button>';
        $output .= '</form>';
    }

    return $output;
}

// Shortcode to search for school certificates
add_shortcode('school_search', 'school_search_shortcode');
function school_search_shortcode() {
    if (isset($_GET['school_name']) && isset($_GET['place'])) {
        $school_name = sanitize_text_field($_GET['school_name']);
        $place = sanitize_text_field($_GET['place']);

        $args = [
            'post_type' => 'schools',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'place',
                    'value' => $place,
                    'compare' => 'LIKE',
                ],
                [
                    'relation' => 'OR', // Match either place or abbreviation
                    [
                        'key' => 'school_name',
                        'value' => $school_name,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key' => 'school_abbreviation',
                        'value' => $school_name,
                        'compare' => 'LIKE',
                    ],
                ],
            ],
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $output = '<ul style="max-width: 600px; margin: 20px auto; padding: 0; list-style: none; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $fields = ['school_name', 'issue_date'];
                $file_url = generate_certificate_pdf($post_id,$fields);
                $output .= '<li style="padding: 15px 20px; border-bottom: 1px solid #ddd; font-size: 14px; display: flex; flex-direction: column; gap: 8px;">';
                $output .= '<strong style="font-size: 16px; color: #333;">' . get_the_title() . '</strong>';
                $output .= '<span style="color: #555;">School: ' . get_post_meta(get_the_ID(), 'school_name', true) . '</span>';
                $output .= '<span style="color: #555;">Place: ' . get_post_meta(get_the_ID(), 'place', true) . '</span>';
                $output .= '<span style="color: #555;">Issued On: ' . get_post_meta(get_the_ID(), 'issue_date', true) . '</span>';
                if ($file_url) {
                    $output .= '<a href="' . esc_url($file_url) . '" target="_blank" style="text-decoration: none; color: #0073aa; font-weight: bold; transition: color 0.3s ease;">Download Certificate</a>';
                }
                $output .= '</li>';
            }
            $output .= '</ul>';
        } else {
            $output = '<p style="text-align: center; color: #666;">No certificates found for the provided school name and place.</p>';
        }
        wp_reset_postdata();
    } else {
        $output = '<form method="get" style="max-width: 600px; margin: 40px auto; padding: 20px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
        $output .= '<label for="school_name" style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #333;">Enter school name:</label>';
        $output .= '<input type="text" name="school_name" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">';
        $output .= '<label for="place" style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #333;">Enter place:</label>';
        $output .= '<input type="text" name="place" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">';
        $output .= '<button type="submit" style="display: block; width: 100%; padding: 12px; background: linear-gradient(to right, #0073aa, #005f8d); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; text-align: center; transition: background 0.3s ease;">Search</button>';
        $output .= '</form>';
    }

    return $output;
}



// Function to generate the certificate PDF with custom data
function validate_template_url($template_url) {
    // Step 1: Check if the URL is valid
    if (!filter_var($template_url, FILTER_VALIDATE_URL)) {
        error_log("Invalid URL format: $template_url");
        return '<p>Template URL is invalid.</p>';
    }

    // Step 2: Check if the URL is accessible
    $response = wp_remote_head($template_url);
    if (is_wp_error($response)) {
        error_log("Error accessing template URL: $template_url - " . $response->get_error_message());
        return '<p>Template URL is inaccessible.</p>';
    }

    // Step 3: Check the HTTP status code
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log("Template URL returned status code $status_code: $template_url");
        return '<p>Template URL is inaccessible.</p>';
    }

    // Step 4: Validate the content type
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    if (strpos($content_type, 'image/') !== 0) {
        error_log("Invalid content type for template URL: $template_url - Content-Type: $content_type");
        return '<p>Template URL is not a valid image file.</p>';
    }

    return true; // URL is valid and accessible
}


function generate_certificate_pdf_with_data($post_data) {
    // Fetch the certificate template based on certificate_type
    $certificate_query = new WP_Query([
        'post_type' => 'certificates',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'certificate_type',
                'value' => $post_data['certificate_type'],
                'compare' => '='
            ]
        ]
    ]);

    if (!$certificate_query->have_posts()) {
        echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
        return false; // No matching certificate template found
    }

    $certificate_template = $certificate_query->posts[0];
    $template_url = get_post_meta($certificate_template->ID, 'template_url', true);

    // Fetch dynamic fields
    $template_orientation = get_post_meta($certificate_template->ID, 'template_orientation', true) ?: 'portrait';
    $font_size = get_post_meta($certificate_template->ID, 'font_size', true) ?: 12;
    $font_color = get_post_meta($certificate_template->ID, 'font_color', true) ?: '#000000';
    $font_style = get_post_meta($certificate_template->ID, 'font_style', true) ?: 'Arial';

    $validation_result = validate_template_url($template_url);

    if ($validation_result !== true) {
        // Validation failed
        echo $validation_result;
        return;
    }

    // Fetch field positions dynamically
    $fields = ['student_name', 'school_name', 'issue_date', 'certificate_type'];
    $field_positions = [];

    foreach ($fields as $index => $field) {
        $field_key = $index + 1;
        $field_positions[$field] = [
            'x' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_x", true),
            'y' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_y", true),
            'visible' => get_post_meta($certificate_template->ID, "field_{$field_key}_visible", true),
        ];
    }

    // Generate PDF
    $pdf = new FPDF($template_orientation, 'mm', 'A4');
    $pdf->AddPage();

    switch ($font_style) {
        case 'Times New Roman':
            $pdf->AddFont('Times New Roman', 'B', 'Times New Roman Bold.php');
            $pdf->AddFont('Times New Roman', '', 'Times New Roman.php');
            break;
        case 'Helvetica':
            $pdf->AddFont('Helvetica', 'B', 'helveticab.php');
            $pdf->AddFont('Helvetica', '', 'helvetica.php');
            break;
        case 'Courier New':
            $pdf->AddFont('Courier New', 'B', 'cour.php');
            $pdf->AddFont('Courier New', '', 'cour.php');
            break;
        case 'Verdana':
            $pdf->AddFont('Verdana', 'B', 'Verdanab.php');
            $pdf->AddFont('Verdana', '', 'Verdana.php');
            break;
        case 'Palatino':
            $pdf->AddFont('Palatino', 'B', 'Palatino Font.php');
            $pdf->AddFont('Palatino', '', 'Palatino Font.php');
            break;
        case 'Garamond':
            $pdf->AddFont('Garamond', 'B', 'garmond.php');
            $pdf->AddFont('Garamond', '', 'Garamond Regular.php');
            break;
        default:
            # code...
            break;
    }

    // Add template background
    if($template_orientation== 'landscape')
    {
        $pdf->Image($template_url, 0, 0, 297, 210); // A4 size
    }
    else{

        $pdf->Image($template_url, 0, 0, 210, 297); // A4 size
    }

    // Set font color
    $font_color_rgb = sscanf($font_color, "#%02x%02x%02x");
    $pdf->SetTextColor($font_color_rgb[0], $font_color_rgb[1], $font_color_rgb[2]);

    // Add dynamic text fields
    foreach ($field_positions as $field => $position) {
        if ($position['visible']) {
            if (!empty($post_data[$field])) {
                // Check if the X and Y positions are valid
                if (is_numeric($position['x']) && is_numeric($position['y'])) {
                    $pdf->SetXY($position['x'], $position['y']);
                    $pdf->SetFont($font_style, 'B', $font_size);
                    $pdf->Cell(0, 10, $post_data[$field], 0, 1, 'C');
                } else {
                    error_log("Invalid position for $field: X={$position['x']}, Y={$position['y']}");
                }
            } else {
                error_log("Data missing for $field");
            }
        } else {
            error_log("Field $field is not visible");
        }
    }

    // Output PDF
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['path'] . "/certificate_preview.pdf";
    $pdf->Output('F', $pdf_path);

    return $upload_dir['url'] . "/certificate_preview.pdf";
}


// function generate_certificate_pdf($post_id) {
//     // Get the certificate type from the current post
//     $certificate_type = get_post_meta($post_id, 'certificate_type', true);

//     if (!$certificate_type) {
//         return '<p>Certificate type is missing for this post.</p>';
//     }

//     // Fetch certificate template details
//     $certificate_query = new WP_Query([
//         'post_type' => 'certificates',
//         'meta_query' => [
//             [
//                 'key' => 'certificate_type',
//                 'value' => $certificate_type,
//                 'compare' => '='
//             ]
//         ]
//     ]);

//     if (!$certificate_query->have_posts()) {
//         return false; // No matching certificate template found
//     }

//     $certificate_template = $certificate_query->posts[0];
//     $template_url = get_post_meta($certificate_template->ID, 'template_url', true);

//     // Fetch dynamic fields
//     $template_orientation = get_post_meta($certificate_template->ID, 'template_orientation', true) ?: 'portrait';
//     $font_size = get_post_meta($certificate_template->ID, 'font_size', true) ?: 12;
//     $font_color = get_post_meta($certificate_template->ID, 'font_color', true) ?: '#000000';
//     $font_style = get_post_meta($certificate_template->ID, 'font_style', true) ?: 'Arial';

//     // Fetch field positions dynamically
//     $fields = ['student_name', 'school_name', 'issue_date'];
//     $field_positions = [];

//     foreach ($fields as $index => $field) {
//         $field_key = $index + 1;
//         $field_positions[$field] = [
//             'x' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_x", true),
//             'y' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_y", true),
//             'visible' => get_post_meta($certificate_template->ID, "field_{$field_key}_visible", true),
//         ];
//     }

//     // Fetch post data (student)
//     $issue_date_raw = get_post_meta($post_id, 'issue_date', true);
//     $post_data = [
//         'student_name' => get_post_meta($post_id, 'student_name', true),
//         'school_name' => get_post_meta($post_id, 'school_name', true),
//         'issue_date' => date('d-m-Y', strtotime($issue_date_raw)),
//     ];

//     // Generate PDF
//     $pdf = new FPDF($template_orientation, 'mm', 'A4');
//     $pdf->AddPage();

//     // Add template background
//     if($template_orientation== 'landscape')
//     {
//         $pdf->Image($template_url, 0, 0, 297, 210); // A4 size
//     }
//     else{

//         $pdf->Image($template_url, 0, 0, 210, 297); // A4 size
//     }

//     // Set font color
//     $font_color_rgb = sscanf($font_color, "#%02x%02x%02x");
//     $pdf->SetTextColor($font_color_rgb[0], $font_color_rgb[1], $font_color_rgb[2]);

//     // Add dynamic text fields with debugging
//     foreach ($field_positions as $field => $position) {
//         if ($position['visible']) {
//             if (!empty($post_data[$field])) {
//                 // Check if the X and Y positions are valid
//                 if (is_numeric($position['x']) && is_numeric($position['y'])) {
//                     $pdf->SetXY($position['x'], $position['y']);
//                     $pdf->SetFont($font_style, 'B', $font_size);
//                     $pdf->Cell(0, 10, $post_data[$field], 0, 1, 'C');
//                 } else {
//                     error_log("Invalid position for $field: X={$position['x']}, Y={$position['y']}");
//                 }
//             } else {
//                 error_log("Data missing for $field");
//             }
//         } else {
//             error_log("Field $field is not visible");
//         }
//     }

//     // Output PDF
//     $upload_dir = wp_upload_dir();
//     $pdf_path = $upload_dir['path'] . "/certificate_$post_id.pdf";
//     $pdf->Output('F', $pdf_path);

//     return $upload_dir['url'] . "/certificate_$post_id.pdf";
// }

function generate_certificate_pdf($post_id,$fields) {
    // Get the certificate type from the current post
    $certificate_type = get_post_meta($post_id, 'certificate_type', true);

    if (!$certificate_type) {
        echo '<div class="notice notice-error"><p>Certificate type is missing for this post.</p></div>';
        return false;
    }

    // Fetch certificate template details
    $certificate_query = new WP_Query([
        'post_type' => 'certificates',
        'meta_query' => [
            [
                'key' => 'certificate_type',
                'value' => $certificate_type,
                'compare' => '='
            ]
        ]
    ]);

    if (!$certificate_query->have_posts()) {
        echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
        return false; // No matching certificate template found
    }

    $certificate_template = $certificate_query->posts[0];
    $template_url = get_post_meta($certificate_template->ID, 'template_url', true);

    // Fetch dynamic fields
    $template_orientation = get_post_meta($certificate_template->ID, 'template_orientation', true) ?: 'portrait';
    $font_size = get_post_meta($certificate_template->ID, 'font_size', true) ?: 12;
    $font_color = get_post_meta($certificate_template->ID, 'font_color', true) ?: '#000000';
    $font_style = get_post_meta($certificate_template->ID, 'font_style', true) ?: 'Arial';

    $validation_result = validate_template_url($template_url);

    if ($validation_result !== true) {
        // Validation failed
        echo $validation_result;
        return;
    }

    // Fetch field positions dynamically
    $field_positions = [];

    foreach ($fields as $index => $field) {
        $field_key = $index + 1;
        $field_positions[$field] = [
            'x' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_x", true),
            'y' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_y", true),
            'visible' => get_post_meta($certificate_template->ID, "field_{$field_key}_visible", true),
        ];
    }

    // Fetch post data dynamically
    $post_data = [];
    foreach ($fields as $field) {
        $post_data[$field] = get_post_meta($post_id, $field, true);
    }

    // Generate PDF
    $pdf = new FPDF($template_orientation, 'mm', 'A4');
    $pdf->AddPage();

    switch ($font_style) {
        case 'Times New Roman':
            $pdf->AddFont('Times New Roman', 'B', 'Times New Roman Bold.php');
            $pdf->AddFont('Times New Roman', '', 'Times New Roman.php');
            break;
        case 'Helvetica':
            $pdf->AddFont('Helvetica', 'B', 'helveticab.php');
            $pdf->AddFont('Helvetica', '', 'helvetica.php');
            break;
        case 'Courier New':
            $pdf->AddFont('Courier New', 'B', 'cour.php');
            $pdf->AddFont('Courier New', '', 'cour.php');
            break;
        case 'Verdana':
            $pdf->AddFont('Verdana', 'B', 'Verdanab.php');
            $pdf->AddFont('Verdana', '', 'Verdana.php');
            break;
        case 'Palatino':
            $pdf->AddFont('Palatino', 'B', 'Palatino Font.php');
            $pdf->AddFont('Palatino', '', 'Palatino Font.php');
            break;
        case 'Garamond':
            $pdf->AddFont('Garamond', 'B', 'garmond.php');
            $pdf->AddFont('Garamond', '', 'Garamond Regular.php');
            break;
        default:
            # code...
            break;
    }

    // Add template background
    if($template_orientation== 'landscape')
    {
        $pdf->Image($template_url, 0, 0, 297, 210); // A4 size
    }
    else{

        $pdf->Image($template_url, 0, 0, 210, 297); // A4 size
    }

    // Set font color
    $font_color_rgb = sscanf($font_color, "#%02x%02x%02x");
    $pdf->SetTextColor($font_color_rgb[0], $font_color_rgb[1], $font_color_rgb[2]);

    // Add dynamic text fields with debugging
    foreach ($field_positions as $field => $position) {
        if ($position['visible']) {
            if (!empty($post_data[$field])) {
                // Check if the X and Y positions are valid
                if (is_numeric($position['x']) && is_numeric($position['y'])) {
                    $pdf->SetXY($position['x'], $position['y']);
                    $pdf->SetFont($font_style, 'B', $font_size);
                    $pdf->Cell(0, 10, $post_data[$field], 0, 1, 'C');
                } else {
                    error_log("Invalid position for $field: X={$position['x']}, Y={$position['y']}");
                }
            } else {
                error_log("Data missing for $field");
            }
        } else {
            error_log("Field $field is not visible");
        }
    }

    // Output PDF
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['path'] . "/certificate_$post_id.pdf";
    $pdf->Output('F', $pdf_path);

    return $upload_dir['url'] . "/certificate_$post_id.pdf";
}


add_shortcode('student_search', 'scs_student_search_shortcode');
function scs_student_search_shortcode()
{
    if (isset($_GET['student_name']) && isset($_GET['student_email'])) {
        $name = sanitize_text_field($_GET['student_name']);
        $email = sanitize_email($_GET['student_email']);

        $args = [
            'post_type' => 'students',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'student_name',
                    'value' => $name,
                    'compare' => 'LIKE',
                ],
                [
                    'key' => 'email',
                    'value' => $email,
                    'compare' => '=',
                ],
            ],
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $output = '<ul style="max-width: 600px; margin: 20px auto; padding: 0; list-style: none; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $fields = ['student_name', 'school_name', 'issue_date'];
                $file_url = generate_certificate_pdf($post_id,$fields);
                $output .= '<li style="padding: 15px 20px; border-bottom: 1px solid #ddd; font-size: 14px; display: flex; flex-direction: column; gap: 8px;">';
                $output .= '<strong style="font-size: 16px; color: #333;">' . get_the_title() . '</strong>';
                $output .= '<span style="color: #555;">School: ' . get_post_meta(get_the_ID(), 'school_name', true) . '</span>';
                $output .= '<span style="color: #555;">Certificate: ' . get_post_meta(get_the_ID(), 'certificate_type', true) . '</span>';
                $output .= '<span style="color: #555;">Issued On: ' . get_post_meta(get_the_ID(), 'issue_date', true) . '</span>';
                if ($file_url) {
                    $output .= '<a href="' . esc_url($file_url) . '" target="_blank" style="text-decoration: none; color: #0073aa; font-weight: bold; transition: color 0.3s ease;">Download Certificate</a>';
                }
                $output .= '</li>';
            }
            $output .= '</ul>';
        } else {
            $output = '<p style="text-align: center; color: #666;">No certificates found for the provided name and email.</p>';
        }
        wp_reset_postdata();
    } else {
        $output = '<form method="get" style="max-width: 600px; margin: 40px auto; padding: 20px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">';
        $output .= '<label for="student_name" style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #333;">Enter your name:</label>';
        $output .= '<input type="text" name="student_name" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">';
        $output .= '<label for="student_email" style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: bold; color: #333;">Enter your email:</label>';
        $output .= '<input type="email" name="student_email" required style="width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">';
        $output .= '<button type="submit" style="display: block; width: 100%; padding: 12px; background: linear-gradient(to right, #0073aa, #005f8d); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; text-align: center; transition: background 0.3s ease;">Search</button>';
        $output .= '</form>';
    }

    return $output;
}


?>