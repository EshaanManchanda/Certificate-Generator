<?php
/**
 * Certificate Generator API Endpoints
 * 
 * Provides REST API endpoints for AI integration and external certificate generation requests.
 * Includes secure API key authentication and comprehensive error handling.
 * 
 * @package Certificate_Generator
 * @since 3.3.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes for certificate generation
 */
add_action('rest_api_init', 'certificate_generator_register_api_routes');

function certificate_generator_register_api_routes() {
    // Issue certificate endpoint
    register_rest_route('certificate-generator/v1', '/issue-certificate', array(
        'methods'             => 'POST',
        'callback'            => 'certificate_generator_issue_certificate_callback',
        'permission_callback' => 'certificate_generator_api_permission_check',
        'args'                => array(
            'student_email' => array(
                'required'          => true,
                'type'             => 'string',
                'format'           => 'email',
                'sanitize_callback' => 'sanitize_email',
                'description'       => 'Email address of the student',
            ),
        ),
    ));

    // Health check endpoint
    register_rest_route('certificate-generator/v1', '/health', array(
        'methods'             => 'GET',
        'callback'            => 'certificate_generator_health_check_callback',
        'permission_callback' => '__return_true',
    ));

    // API key validation endpoint
    register_rest_route('certificate-generator/v1', '/validate-key', array(
        'methods'             => 'POST',
        'callback'            => 'certificate_generator_validate_key_callback',
        'permission_callback' => 'certificate_generator_api_permission_check',
    ));
}

/**
 * Permission callback for API endpoints - validates API key
 */
function certificate_generator_api_permission_check($request) {
    // Check if API access is enabled
    $api_enabled = get_option('certificate_generator_api_key_enabled', false);
    if (!$api_enabled) {
        return new WP_Error(
            'api_disabled',
            'API access is currently disabled. Please enable it in the plugin settings.',
            array('status' => 403)
        );
    }

    // Get API key from Authorization header
    $auth_header = $request->get_header('authorization');
    
    if (empty($auth_header)) {
        return new WP_Error(
            'missing_auth_header',
            'Authorization header is required',
            array('status' => 401)
        );
    }

    // Extract Bearer token
    if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        return new WP_Error(
            'invalid_auth_format',
            'Authorization header must be in format: Bearer YOUR_API_KEY',
            array('status' => 401)
        );
    }

    $provided_key = trim($matches[1]);
    
    // Get stored API key from settings
    $stored_key = get_option('certificate_generator_api_key', '');
    
    if (empty($stored_key)) {
        return new WP_Error(
            'api_key_not_configured',
            'API key not configured. Please set up API key in plugin settings.',
            array('status' => 500)
        );
    }

    // Compare API keys (using constant time comparison for security)
    if (!hash_equals($stored_key, $provided_key)) {
        // Log failed authentication attempt
        certificate_generator_log_api_activity('FAILED_AUTH', array(
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => current_time('mysql')
        ));
        
        return new WP_Error(
            'invalid_api_key',
            'Invalid API key provided',
            array('status' => 401)
        );
    }

    return true;
}

/**
 * Main callback for certificate issuance
 */
function certificate_generator_issue_certificate_callback($request) {
    // Log API request
    certificate_generator_log_api_activity('CERTIFICATE_REQUEST', array(
        'student_email' => $request->get_param('student_email'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => current_time('mysql')
    ));

    try {
        $email = sanitize_email($request->get_param('student_email'));

        // Find student by email using WP_Query
        $args = [
            'post_type' => 'students',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => 'email',
                    'value' => $email,
                    'compare' => '=',
                ],
            ],
        ];

        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            return new WP_Error(
                'student_not_found',
                'No student found with the provided email address.',
                array('status' => 404)
            );
        }

        $certificates = [];
        $processed_certificates = []; // Track processed certificates to avoid duplicates

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $fields = ['student_name', 'school_name', 'issue_date'];
            $file_url = generate_certificate_pdf($post_id, $fields);

            if ($file_url) {
                $file_path = get_post_meta($post_id, 'certificate_file_path', true);
                $filename = basename($file_path);
                $certificates[] = array(
                    'path' => $file_path,
                    'url' => $file_url,
                    'filename' => $filename
                );
            }
        }
        wp_reset_postdata();

        if (empty($certificates)) {
            return new WP_Error(
                'certificate_generation_failed',
                'Failed to generate certificates.',
                array('status' => 500)
            );
        }

        // Create ZIP file if there are multiple certificates
        if (count($certificates) > 3 && class_exists('ZipArchive')) {
            $upload_dir = wp_upload_dir();
            $timestamp = current_time('timestamp');
            $zip_filename = 'certificates_' . $timestamp . '.zip';
            $zip_path = $upload_dir['basedir'] . '/' . $zip_filename;
            $zip_url = $upload_dir['baseurl'] . '/' . $zip_filename;

            // Remove old ZIP file if it exists
            if (file_exists($zip_path)) {
                @unlink($zip_path);
            }

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                $success = true;
                foreach ($certificates as $certificate) {
                    if (file_exists($certificate['path'])) {
                        if (!$zip->addFile($certificate['path'], $certificate['filename'])) {
                            error_log('Failed to add file to ZIP: ' . $certificate['path']);
                            $success = false;
                        }
                    }
                }
                $zip->close();

                if ($success && file_exists($zip_path)) {
                    // Log successful certificate generation
                    certificate_generator_log_api_activity('CERTIFICATES_ZIPPED', array(
                        'student_email' => $email,
                        'download_url' => $zip_url,
                        'timestamp' => current_time('mysql')
                    ));

                    return new WP_REST_Response(array(
                        'status' => 'success',
                        'message' => 'Certificates generated and zipped successfully.',
                        'download_url' => $zip_url,
                        'certificate_count' => count($certificates)
                    ), 200);
                }
            }
        }

        // Return single certificate URL if no ZIP was created
        certificate_generator_log_api_activity('CERTIFICATE_GENERATED', array(
            'student_email' => $email,
            'download_url' => $certificates[0]['url'],
            'timestamp' => current_time('mysql')
        ));

        return new WP_REST_Response(array(
            'status' => 'success',
            'message' => 'Certificate generated successfully.',
            'download_url' => $certificates[0]['url']
        ), 200);

    } catch (Exception $e) {
        certificate_generator_log_api_activity('EXCEPTION', array(
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'timestamp' => current_time('mysql')
        ));

        return new WP_Error(
            'internal_error',
            'An internal error occurred while processing the request.',
            array('status' => 500)
        );
    }
}

/**
 * Health check callback
 */
function certificate_generator_health_check_callback($request) {
    return new WP_REST_Response(array(
        'status' => 'healthy',
        'plugin_version' => '3.3.1',
        'wordpress_version' => get_bloginfo('version'),
        'timestamp' => current_time('c')
    ), 200);
}

/**
 * API key validation callback
 */
function certificate_generator_validate_key_callback($request) {
    return new WP_REST_Response(array(
        'status' => 'valid',
        'message' => 'API key is valid',
        'timestamp' => current_time('c')
    ), 200);
}

/**
 * Get certificate post by type
 */
function certificate_generator_get_certificate_by_type($certificate_type) {
    $certificates = get_posts(array(
        'post_type' => 'certificate',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => 'certificate_type',
                'value' => $certificate_type,
                'compare' => '='
            )
        ),
        'posts_per_page' => 1
    ));

    return !empty($certificates) ? $certificates[0] : false;
}

/**
 * Get or create student record
 */
function certificate_generator_get_or_create_student($email, $name = '', $school_name = '') {
    // First, try to find existing student by email
    $existing_students = get_posts(array(
        'post_type' => 'student',
        'meta_query' => array(
            array(
                'key' => 'student_email',
                'value' => $email,
                'compare' => '='
            )
        ),
        'posts_per_page' => 1
    ));

    if (!empty($existing_students)) {
        return $existing_students[0]->ID;
    }

    // Create new student if not found
    $student_data = array(
        'post_title' => $name ?: $email,
        'post_type' => 'student',
        'post_status' => 'publish',
        'meta_input' => array(
            'student_email' => $email,
            'student_name' => $name,
            'school_name' => $school_name
        )
    );

    $student_id = wp_insert_post($student_data);
    
    if (is_wp_error($student_id)) {
        return new WP_Error(
            'student_creation_failed',
            'Failed to create student record: ' . $student_id->get_error_message(),
            array('status' => 500)
        );
    }

    return $student_id;
}

/**
 * Check for existing certificate
 */
function certificate_generator_check_existing_certificate($student_id, $certificate_id) {
    // This would depend on how you track issued certificates
    // You might have a custom table or use post meta
    // For now, returning false to allow certificate generation
    return false;
}

/**
 * Generate certificate PDF for API requests
 */
function certificate_generator_generate_certificate_api($student_id, $certificate_id, $issue_date) {
    // Include the existing certificate generation functions
    if (!function_exists('generate_certificate_pdf')) {
        require_once plugin_dir_path(__FILE__) . 'student-certificate-search.php';
    }

    $student = get_post($student_id);
    $certificate = get_post($certificate_id);
    
    if (!$student || !$certificate) {
        return new WP_Error(
            'invalid_records',
            'Student or certificate record not found',
            array('status' => 404)
        );
    }

    // Get student data
    $student_email = get_post_meta($student_id, 'student_email', true);
    $student_name = get_post_meta($student_id, 'student_name', true) ?: $student->post_title;
    $school_name = get_post_meta($student_id, 'school_name', true);

    // Generate unique filename
    $filename = 'certificate_' . $student_id . '_' . $certificate_id . '_' . time() . '.pdf';
    $upload_dir = wp_upload_dir();
    $certificates_dir = $upload_dir['basedir'] . '/certificates/';
    
    // Create directory if it doesn't exist
    if (!file_exists($certificates_dir)) {
        wp_mkdir_p($certificates_dir);
    }
    
    $file_path = $certificates_dir . $filename;
    $download_url = $upload_dir['baseurl'] . '/certificates/' . $filename;

    // Generate PDF using existing function
    try {
        $pdf_generated = generate_certificate_pdf(
            $student_name,
            $student_email,
            $school_name,
            $issue_date,
            $certificate->post_title,
            $file_path,
            $certificate_id
        );

        if (!$pdf_generated) {
            return new WP_Error(
                'pdf_generation_failed',
                'Failed to generate PDF certificate',
                array('status' => 500)
            );
        }

        return array(
            'file_path' => $file_path,
            'download_url' => $download_url,
            'certificate_id' => $certificate_id,
            'filename' => $filename
        );

    } catch (Exception $e) {
        return new WP_Error(
            'pdf_generation_error',
            'Error generating PDF: ' . $e->getMessage(),
            array('status' => 500)
        );
    }
}

/**
 * Log API activity
 */
function certificate_generator_log_api_activity($action, $data = array()) {
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'action' => $action,
        'data' => $data
    );

    // Log to WordPress debug log if enabled
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('Certificate Generator API: ' . json_encode($log_entry));
    }

    // Store in database for admin viewing
    $existing_logs = get_option('certificate_generator_api_logs', array());
    $existing_logs[] = $log_entry;
    
    // Keep only last 100 log entries
    if (count($existing_logs) > 100) {
        $existing_logs = array_slice($existing_logs, -100);
    }
    
    update_option('certificate_generator_api_logs', $existing_logs);
}

/**
 * Send certificate email
 */
function certificate_generator_send_certificate_email($email, $file_path, $certificate_type) {
    if (!function_exists('certificate_generator_send_custom_email')) {
        require_once plugin_dir_path(__FILE__) . 'email-functions.php';
    }
    
    $email_options = [
        'subject' => 'Your ' . $certificate_type . ' Certificate',
        'message' => 'Dear Student,\n\nPlease find attached your certificate.\n\nThank you!',
        'attach_certificate' => true,
        'reply_to' => get_option('admin_email')
    ];
    
    return certificate_generator_send_custom_email(
        $email,
        'Student',
        $email_options,
        $file_path
    );
}