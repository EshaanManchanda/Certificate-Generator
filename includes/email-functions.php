<?php
/**
 * Email Functions for Certificate Generator
 *
 * @package Certificate Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 
 * Create ZIP file containing multiple certificates for an email address
 *
 * @param array $certificates_data Array of certificate data with 'path', 'filename', and 'post_id' keys
 * @param string $recipient_email Recipient email address for ZIP filename
 * @return array|false Array with 'zip_path', 'zip_url', 'certificate_count' on success, false on failure
 */
function certificate_generator_create_zip_for_email($certificates_data, $recipient_email) {
    // Validate inputs
    if (empty($certificates_data) || !is_array($certificates_data)) {
        error_log('Certificate Generator: Cannot create ZIP - no certificate data provided');
        return false;
    }

    if (empty($recipient_email)) {
        error_log('Certificate Generator: Cannot create ZIP - no recipient email provided');
        return false;
    }

    // Check if ZipArchive class is available
    if (!class_exists('ZipArchive')) {
        error_log('Certificate Generator: ZipArchive class not available on this server');
        return false;
    }

    // Get upload directory
    $upload_dir = wp_upload_dir();
    if (!$upload_dir || !isset($upload_dir['basedir']) || !isset($upload_dir['baseurl'])) {
        error_log('Certificate Generator: Could not get WordPress upload directory');
        return false;
    }

    // Create ZIP filename
    $timestamp = current_time('timestamp');
    $email_sanitized = sanitize_file_name(str_replace(['@', '.'], '_', $recipient_email));
    $zip_filename = 'certificates_' . $email_sanitized . '_' . $timestamp . '.zip';
    $zip_path = $upload_dir['basedir'] . '/' . $zip_filename;
    $zip_url = $upload_dir['baseurl'] . '/' . $zip_filename;

    // Normalize path for Windows compatibility
    $zip_path = wp_normalize_path($zip_path);

    // Remove old ZIP file if it exists
    if (file_exists($zip_path)) {
        @unlink($zip_path);
    }

    // Create ZIP archive
    $zip = new ZipArchive();
    $zip_opened = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($zip_opened !== true) {
        error_log("Certificate Generator: Failed to create ZIP file at: {$zip_path} (Error code: {$zip_opened})");
        return false;
    }

    $added_count = 0;
    $failed_files = [];

    foreach ($certificates_data as $certificate) {
        // Validate certificate data structure
        if (!isset($certificate['path']) || !isset($certificate['filename'])) {
            error_log('Certificate Generator: Invalid certificate data structure in ZIP creation');
            continue;
        }

        $cert_path = wp_normalize_path($certificate['path']);
        $cert_filename = $certificate['filename'];

        // Verify file exists and is readable
        if (!file_exists($cert_path)) {
            error_log("Certificate Generator: Certificate file does not exist: {$cert_path}");
            $failed_files[] = $cert_filename;
            continue;
        }

        if (!is_readable($cert_path)) {
            error_log("Certificate Generator: Certificate file is not readable: {$cert_path}");
            $failed_files[] = $cert_filename;
            continue;
        }

        // Add file to ZIP with unique name to prevent overwriting
        $add_result = $zip->addFile($cert_path, $cert_filename);

        if ($add_result) {
            $added_count++;
            error_log("Certificate Generator: Added to ZIP: {$cert_filename}");
        } else {
            error_log("Certificate Generator: Failed to add file to ZIP: {$cert_path}");
            $failed_files[] = $cert_filename;
        }
    }

    // Close ZIP archive
    $zip->close();

    // Verify ZIP was created successfully
    if ($added_count === 0) {
        error_log('Certificate Generator: No files were added to ZIP');
        @unlink($zip_path); // Clean up empty ZIP
        return false;
    }

    if (!file_exists($zip_path)) {
        error_log("Certificate Generator: ZIP file was not created: {$zip_path}");
        return false;
    }

    $zip_size = filesize($zip_path);
    if ($zip_size === false || $zip_size < 100) {
        error_log("Certificate Generator: ZIP file appears to be corrupted or empty (size: {$zip_size})");
        @unlink($zip_path);
        return false;
    }

    // Log success
    error_log("Certificate Generator: Successfully created ZIP with {$added_count} certificates at: {$zip_path}");

    if (!empty($failed_files)) {
        error_log('Certificate Generator: Failed to add ' . count($failed_files) . ' files to ZIP: ' . implode(', ', $failed_files));
    }

    return [
        'zip_path' => $zip_path,
        'zip_url' => $zip_url,
        'certificate_count' => $added_count,
        'failed_count' => count($failed_files),
        'failed_files' => $failed_files
    ];
}

/**
 * Send certificate email for a specific post
 * Now with support for grouping multiple certificates by email address
 *
 * @param int $post_id The post ID (student, teacher, or school)
 * @param bool $log_email Whether to log the email attempt (default: true)
 * @return bool Whether the email was sent successfully
 */
function certificate_generator_send_email($post_id, $log_email = true) {
    // Add timeout protection for background processing (WP-Cron)
    if (defined('DOING_CRON') && DOING_CRON) {
        @set_time_limit(0); // Unlimited execution time for cron jobs
        @ini_set('memory_limit', '512M'); // Increase memory for large batches
        @ignore_user_abort(true); // Continue even if connection drops
    }

    // Get post type to determine email template prefix
    $post_type = get_post_type($post_id);
    if (!in_array($post_type, ['students', 'teachers', 'schools'])) {
        return false;
    }

    $prefix = $post_type . '_email_';

    // Get email settings
    $options = get_option('certificate_generator_settings_email');

    // Get recipient information based on post type
    $recipient_email = get_post_meta($post_id, 'email', true);
    if (empty($recipient_email)) {
        if ($log_email) {
            certificate_generator_log_email($post_id, '', '', '', '', false, 'No email address found');
        }
        return false;
    }

    // NEW: Check for other certificates with the same email address
    global $wpdb;
    $table_name = $wpdb->prefix . 'cert_email_logs';

    $other_certificates = [];

    // Query for all posts with the same email address, excluding already sent ones
    // OPTIMIZATION: Added limit to prevent timeout on large datasets
    $query_args = [
        'post_type' => ['students', 'teachers', 'schools'], // Check all post types
        'posts_per_page' => 500, // Increased limit to handle large batches (was 100)
        'post_status' => 'publish',
        'fields' => 'ids', // Only get IDs for better performance
        'meta_query' => [
            [
                'key' => 'email',
                'value' => $recipient_email,
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($query_args);

    // Since we used 'fields' => 'ids', $query->posts contains IDs directly
    $all_certificate_ids = !empty($query->posts) ? $query->posts : [];

    // Log what we found
    error_log("Certificate Generator: Found " . count($all_certificate_ids) . " certificates for email: {$recipient_email}");

    // Determine if we need to group certificates or send individually
    $use_zip = count($all_certificate_ids) > 1;

    // Get recipient name based on post type
    $recipient_name = '';
    switch ($post_type) {
        case 'students':
            $recipient_name = get_post_meta($post_id, 'student_name', true);
            break;
        case 'teachers':
            $recipient_name = get_post_meta($post_id, 'teacher_name', true);
            break;
        case 'schools':
            $recipient_name = get_post_meta($post_id, 'school_name', true);
            break;
    }

    // Get certificate type
    $certificate_type = get_post_meta($post_id, 'certificate_type', true);

    // Get email template settings
    $subject = isset($options[$prefix . 'subject']) ? $options[$prefix . 'subject'] : sprintf(__('Your %s Certificate', 'certificate-generator'), ucfirst(rtrim($post_type, 's')));
    $title = isset($options[$prefix . 'title']) ? $options[$prefix . 'title'] : sprintf(__('Your %s Certificate is Ready', 'certificate-generator'), ucfirst(rtrim($post_type, 's')));
    $message = isset($options[$prefix . 'message']) ? $options[$prefix . 'message'] : sprintf(__('Dear {name},\n\nPlease find attached your %s certificate.\n\nThank you!', 'certificate-generator'), rtrim($post_type, 's'));

    // Generate result page URL
    $result_page_url = home_url('/result/?student_email=' . urlencode($recipient_email));

    // Initialize ZIP link (will be populated later if ZIP is created)
    $zip_download_url = '';

    // Count certificates for this email
    $certificate_count = count($all_certificate_ids);

    // Replace placeholders (initial replacement - {zip_link} will be replaced again later if ZIP is created)
    $placeholders = [
        '{name}' => $recipient_name,
        '{certificate_title}' => $certificate_type,
        '{result_link}' => $result_page_url,
        '{zip_link}' => '', // Empty for now, populated later if ZIP exists
        '{certificate_count}' => $certificate_count,
        '{email}' => $recipient_email
    ];

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $title = str_replace(array_keys($placeholders), array_values($placeholders), $title);
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);

    // Get additional email settings
    $reply_to = isset($options[$prefix . 'reply_to']) ? $options[$prefix . 'reply_to'] : '';
    $cc = isset($options[$prefix . 'cc']) ? array_map('trim', explode(',', $options[$prefix . 'cc'])) : [];
    $bcc = isset($options[$prefix . 'bcc']) ? array_map('trim', explode(',', $options[$prefix . 'bcc'])) : [];

    // NEW: Generate certificates for all unsent certificates with this email
    $certificates_data = [];
    $certificate_path = null; // For single certificate case
    $generated_certificate_ids = []; // Track which certificates were generated

    // Check if generate_certificate_pdf_email function exists
    if (!function_exists('generate_certificate_pdf_email')) {
        $error_msg = 'Certificate generation function not found';
        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    }

    try {
        // Generate certificates for all IDs found
        $total_certs = count($all_certificate_ids);
        error_log("Certificate Generator: Generating {$total_certs} certificates for {$recipient_email}");

        foreach ($all_certificate_ids as $index => $cert_id) {
            // Log progress every 10 PDFs to monitor large batches
            if ($index % 10 === 0 && $index > 0) {
                error_log("Certificate Generator: Progress - Generated " . $index . " of {$total_certs} PDFs");
            }

            $cert_post_type = get_post_type($cert_id);
            $cert_name = '';

            // Get name based on post type
            switch ($cert_post_type) {
                case 'students':
                    $cert_name = get_post_meta($cert_id, 'student_name', true);
                    break;
                case 'teachers':
                    $cert_name = get_post_meta($cert_id, 'teacher_name', true);
                    break;
                case 'schools':
                    $cert_name = get_post_meta($cert_id, 'school_name', true);
                    break;
            }

            // Determine fields for this certificate
            $fields = [];
            switch ($cert_post_type) {
                case 'students':
                    if (get_post_meta($cert_id, 'field_1_visible', true) !== '0') {
                        $fields[] = 'student_name';
                    }
                    if (get_post_meta($cert_id, 'field_2_visible', true) !== '0') {
                        $fields[] = 'school_name';
                    }
                    if (get_post_meta($cert_id, 'field_3_visible', true) !== '0') {
                        $fields[] = 'issue_date';
                    }
                    break;
                case 'teachers':
                    $fields = ['teacher_name', 'school_name'];
                    if (get_post_meta($cert_id, 'field_3_visible', true) !== '0') {
                        $fields[] = 'issue_date';
                    }
                    break;
                case 'schools':
                    $fields = ['school_name'];
                    if (get_post_meta($cert_id, 'field_3_visible', true) !== '0') {
                        $fields[] = 'issue_date';
                    }
                    break;
            }

            $cert_type = get_post_meta($cert_id, 'certificate_type', true);

            // Create email options
            $email_options = [
                'recipient_email' => $recipient_email,
                'recipient_name' => $cert_name,
                'certificate_type' => $cert_type,
                'post_type' => $cert_post_type
            ];

            // Generate the certificate PDF
            $certificate_result = generate_certificate_pdf_email($cert_id, $fields, $email_options);

            if (!$certificate_result) {
                error_log("Certificate Generator: Failed to generate certificate for post ID {$cert_id}");
                continue;
            }

            // Handle different return formats
            $cert_path = null;
            if (is_array($certificate_result) && isset($certificate_result['path'])) {
                $cert_path = $certificate_result['path'];
            } elseif (is_string($certificate_result)) {
                if (strpos($certificate_result, 'http') === 0) {
                    $upload_dir = wp_upload_dir();
                    $cert_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $certificate_result);
                } else {
                    $cert_path = $certificate_result;
                }
            }

            // Fallback to stored path if generation failed
            if (empty($cert_path) || !file_exists($cert_path)) {
                $stored_path = get_post_meta($cert_id, 'certificate_file_path', true);
                if (!empty($stored_path) && file_exists($stored_path)) {
                    $cert_path = $stored_path;
                }
            }

            if (empty($cert_path) || !file_exists($cert_path)) {
                error_log("Certificate Generator: Certificate file not found for post ID {$cert_id}");
                continue;
            }

            // Normalize path
            $cert_path = wp_normalize_path($cert_path);

            // Create filename for ZIP
            $cert_filename = basename($cert_path);

            // Store certificate data
            $certificates_data[] = [
                'path' => $cert_path,
                'filename' => $cert_filename,
                'post_id' => $cert_id,
                'name' => $cert_name,
                'type' => $cert_type
            ];

            $generated_certificate_ids[] = $cert_id;

            error_log("Certificate Generator: Successfully generated certificate for post ID {$cert_id}: {$cert_path}");
        }

    } catch (Exception $e) {
        $error_msg = 'Certificate generation exception: ' . $e->getMessage();
        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    } catch (Error $e) {
        $error_msg = 'Certificate generation fatal error: ' . $e->getMessage();
        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    }

    // Verify we generated at least one certificate
    if (empty($certificates_data)) {
        $error_msg = 'No certificates could be generated';
        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    }

    error_log("Certificate Generator: Generated " . count($certificates_data) . " certificates for email: {$recipient_email}");

    // NEW: Decide whether to use ZIP or single PDF
    if ($use_zip) {
        // Multiple certificates - create ZIP file
        error_log("Certificate Generator: Creating ZIP file for {$recipient_email} with " . count($certificates_data) . " certificates");

        $zip_result = certificate_generator_create_zip_for_email($certificates_data, $recipient_email);

        if ($zip_result === false) {
            $error_msg = 'Failed to create ZIP file for multiple certificates';
            if ($log_email) {
                certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
            }
            return false;
        }

        // Use ZIP file as attachment
        $certificate_path = $zip_result['zip_path'];

        // Store ZIP URL for {zip_link} placeholder
        $zip_download_url = isset($zip_result['zip_url']) ? $zip_result['zip_url'] : '';

        // Update subject and message to indicate multiple certificates
        $cert_count = count($certificates_data);
        $subject = str_replace(
            ['{certificate_count}', 'Certificate', 'certificate'],
            [$cert_count, $cert_count . ' Certificates', $cert_count . ' certificates'],
            $subject
        );

        // Add count to title and message if not already present
        if (strpos($title, $cert_count) === false) {
            $title = str_replace('Certificate', $cert_count . ' Certificates', $title);
        }
        if (strpos($message, $cert_count) === false) {
            $message = str_replace(
                ['attached your certificate', 'find attached your'],
                ['attached your ' . $cert_count . ' certificates', 'find attached your ' . $cert_count],
                $message
            );
        }

        error_log("Certificate Generator: ZIP file created successfully: " . $zip_result['zip_path']);

    } else {
        // Single certificate - use PDF directly
        $certificate_path = $certificates_data[0]['path'];
        error_log("Certificate Generator: Using single PDF for {$recipient_email}: {$certificate_path}");
    }

    // Verify final attachment file exists
    if (empty($certificate_path) || !file_exists($certificate_path)) {
        $error_msg = "Final attachment file not found: {$certificate_path}";
        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    }

    // Replace {zip_link} placeholder now that we know if ZIP was created
    $message = str_replace('{zip_link}', $zip_download_url, $message);
    $subject = str_replace('{zip_link}', $zip_download_url, $subject);
    $title = str_replace('{zip_link}', $zip_download_url, $title);

    // Check if {result_link} placeholder was used in the original template
    $original_message_template = isset($options[$prefix . 'message']) ? $options[$prefix . 'message'] : '';
    $result_link_used = strpos($original_message_template, '{result_link}') !== false;

    // Format HTML email
    $html_message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
    $html_message .= '<h1 style="color: #2c3e50; margin-bottom: 20px;">' . esc_html($title) . '</h1>';
    $html_message .= '<div style="line-height: 1.6; color: #333;">' . wpautop($message) . '</div>';

    // Auto-add result page button if {result_link} placeholder was not used
    if (!$result_link_used && !empty($result_page_url)) {
        $html_message .= '<div style="text-align: center; margin: 30px 0 20px 0;">';
        $html_message .= '<a href="' . esc_url($result_page_url) . '" style="display: inline-block; padding: 12px 28px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">View Your Results Online</a>';
        $html_message .= '</div>';
    }

    $html_message .= '</div>';

    // Set up email headers with fallback system
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    // Use enhanced email system with better SMTP detection
    $from_name = get_bloginfo('name');

    // Check if WP Mail SMTP is properly configured
    if (certificate_generator_is_wp_mail_smtp_active()) {
        // Use admin email when SMTP is configured
        $from_email = get_option('admin_email');

    } else {
        // Use noreply@domain.com as fallback (like Forminator)
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : parse_url(home_url(), PHP_URL_HOST);

        // Clean server name and validate
        if (empty($server_name) || $server_name === 'localhost' || filter_var($server_name, FILTER_VALIDATE_IP)) {
            // If server name is localhost or an IP, use admin email
            $from_email = get_option('admin_email');

        } else {
            $from_email = 'noreply@' . $server_name;

        }
    }

    // Validate from email address
    if (!is_email($from_email)) {

        $from_email = get_option('admin_email');
    }

    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

    if (!empty($reply_to)) {
        if (is_email($reply_to)) {
            $headers[] = 'Reply-To: ' . $reply_to;
        } else {

        }
    }

    // Add CC recipients with validation
    foreach ($cc as $cc_email) {
        $cc_email = trim($cc_email);
        if (!empty($cc_email)) {
            if (is_email($cc_email)) {
                $headers[] = 'Cc: ' . $cc_email;
            } else {

            }
        }
    }

    // Add BCC recipients with validation
    foreach ($bcc as $bcc_email) {
        $bcc_email = trim($bcc_email);
        if (!empty($bcc_email)) {
            if (is_email($bcc_email)) {
                $headers[] = 'Bcc: ' . $bcc_email;
            } else {

            }
        }
    }

    // Set up attachments based on settings
    $attachments = [];

    // For ZIP files (multiple certificates), ALWAYS attach or provide download link
    if ($use_zip && !empty($certificate_path) && file_exists($certificate_path)) {
        // Check ZIP file size to determine if we should attach or provide download link
        $zip_size = filesize($certificate_path);
        $max_attachment_size = 25 * 1024 * 1024; // 25MB limit for email attachments

        if ($zip_size <= $max_attachment_size) {
            // ZIP is small enough - attach it
            $attachments[] = $certificate_path;
            error_log(sprintf(
                "Certificate Generator: Attaching ZIP file (%.2f MB) to email for %s",
                $zip_size / 1024 / 1024,
                $recipient_email
            ));
        } else {
            // ZIP is too large - provide download link instead
            $download_url = isset($zip_result['zip_url']) ? $zip_result['zip_url'] : '';
            if (!empty($download_url)) {
                // Update zip_download_url for {zip_link} placeholder (if not already set)
                if (empty($zip_download_url)) {
                    $zip_download_url = $download_url;
                }

                $size_mb = round($zip_size / 1024 / 1024, 1);
                $message .= "\n\n<div style='margin-top: 20px; padding: 15px; background-color: #f0f0f0; border-left: 4px solid #0073aa;'>";
                $message .= "<p style='margin: 0; font-weight: bold;'>Your certificates are ready for download:</p>";
                $message .= "<p style='margin: 10px 0 0 0;'><a href='{$download_url}' style='color: #0073aa; text-decoration: none; font-weight: bold;'>Download Certificates ZIP ({$size_mb} MB)</a></p>";
                $message .= "</div>";

                error_log(sprintf(
                    "Certificate Generator: ZIP file too large (%.2f MB), providing download link instead: %s",
                    $zip_size / 1024 / 1024,
                    $download_url
                ));
            }
        }
    } else {
        // Single PDF - respect the attach_certificate setting
        $attach_certificate = isset($options[$prefix . 'attach_certificate']) ? $options[$prefix . 'attach_certificate'] : '1';
        if ($attach_certificate === '1' && !empty($certificate_path) && file_exists($certificate_path)) {
            $attachments[] = $certificate_path;
        }
    }

    // Pre-send validation
    if (!is_email($recipient_email)) {
        $error_msg = "Invalid recipient email address: {$recipient_email}";

        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    }

    if (empty($subject)) {
        $error_msg = "Empty email subject";

        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    }

    if (empty($html_message)) {
        $error_msg = "Empty email message";

        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, $error_msg);
        }
        return false;
    }

    // Send email using WordPress mail function with retry mechanism
    // WP Mail SMTP will automatically handle the SMTP configuration if it's installed
    $email_sent = false;
    $max_retries = 3;
    $retry_delay = 1; // seconds
    $last_error = '';
    $all_errors = []; // Track all errors from all attempts

    // Log email configuration for debugging
    $smtp_configured = certificate_generator_is_wp_mail_smtp_active();

    // Get WP Mail SMTP configuration if available
    $smtp_settings = [];
    if ($smtp_configured) {
        $options = get_option('wp_mail_smtp', []);
        if (!empty($options['mail'])) {
            $smtp_settings = [
                'mailer' => isset($options['mail']['mailer']) ? $options['mail']['mailer'] : 'unknown',
                'from_email' => isset($options['mail']['from_email']) ? $options['mail']['from_email'] : 'not set',
                'from_email_force' => isset($options['mail']['from_email_force']) ? $options['mail']['from_email_force'] : false,
                'from_name_force' => isset($options['mail']['from_name_force']) ? $options['mail']['from_name_force'] : false,
            ];

            // Add SMTP-specific settings if using SMTP/Other SMTP
            if (isset($options['smtp'])) {
                $smtp_settings['smtp_host'] = isset($options['smtp']['host']) ? $options['smtp']['host'] : 'not set';
                $smtp_settings['smtp_port'] = isset($options['smtp']['port']) ? $options['smtp']['port'] : 'not set';
                $smtp_settings['smtp_encryption'] = isset($options['smtp']['encryption']) ? $options['smtp']['encryption'] : 'none';
            }
        }
    }

    $email_config = [
        'to' => $recipient_email,
        'from' => $from_email,
        'smtp_active' => $smtp_configured,
        'smtp_settings' => $smtp_settings,
        'php_mail_available' => function_exists('mail'),
        'attachment_count' => count($attachments),
        'attachment_paths' => $attachments,
        'server' => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'unknown'
    ];
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Certificate Generator Debug - Email configuration: ' . print_r($email_config, true));
    }

    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        error_log("Certificate Generator: Email attempt {$attempt}/{$max_retries} for {$recipient_email}");

        // Reset PHP mail errors before attempt
        $last_php_error = error_get_last();

        // Attempt to send email
        $email_sent = wp_mail($recipient_email, $subject, $html_message, $headers, $attachments);

        if ($email_sent) {
            error_log("Certificate Generator: Email sent successfully on attempt {$attempt} to {$recipient_email}");
            break;
        } else {
            // Capture error details
            global $phpmailer;
            $current_error = '';
            $error_details = [];

            if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $current_error = 'PHPMailer: ' . $phpmailer->ErrorInfo;
                $error_details['phpmailer_error'] = $phpmailer->ErrorInfo;

                // Get more PHPMailer details if available
                if (isset($phpmailer->Mailer)) {
                    $error_details['mailer_type'] = $phpmailer->Mailer; // smtp, mail, sendmail
                }
                if (isset($phpmailer->Host)) {
                    $error_details['smtp_host'] = $phpmailer->Host;
                }
            }

            // Check for new PHP errors
            $new_php_error = error_get_last();
            if ($new_php_error && $new_php_error !== $last_php_error) {
                $php_error_msg = $new_php_error['message'];
                $current_error .= ($current_error ? ' | ' : '') . 'PHP: ' . $php_error_msg;
                $error_details['php_error'] = $php_error_msg;
                $error_details['php_error_file'] = $new_php_error['file'];
                $error_details['php_error_line'] = $new_php_error['line'];
            }

            if (empty($current_error)) {
                $current_error = 'wp_mail() returned false with no specific error';
                $error_details['general'] = 'No specific error returned';
            }

            $last_error = $current_error;
            $all_errors[] = "Attempt {$attempt}: {$current_error}";

            // Log detailed error for this attempt
            error_log("Certificate Generator: Email attempt {$attempt} failed: {$current_error}");
            error_log("Certificate Generator: Error details: " . print_r($error_details, true));

            // Don't retry on certain permanent failures
            $permanent_failures = [
                'Invalid address',
                'Recipient address rejected',
                'Domain not found',
                'Authentication failed',
                'Invalid credentials',
                'Could not authenticate',
                'SMTP connect() failed'
            ];

            $is_permanent_failure = false;
            foreach ($permanent_failures as $failure_type) {
                if (stripos($current_error, $failure_type) !== false) {
                    $is_permanent_failure = true;
                    error_log("Certificate Generator: Permanent failure detected: {$failure_type}. Stopping retries.");
                    break;
                }
            }

            if ($is_permanent_failure || $attempt >= $max_retries) {
                break;
            }

            // Wait before retry
            error_log("Certificate Generator: Waiting {$retry_delay}s before retry {$attempt} of {$max_retries}");
            sleep($retry_delay);
            $retry_delay *= 2; // Exponential backoff
        }
    }

    // Log final result with all errors
    if (!$email_sent && $log_email) {
        error_log('Certificate Generator: All email attempts failed. Errors: ' . implode(' | ', $all_errors));
    }

    // Log email attempt if logging is enabled
    // NEW: Log all certificate IDs that were sent together
    if ($log_email) {
        if ($email_sent) {
            // NOTE: We log each certificate individually for detailed audit tracking,
            // but the rate limiter counts UNIQUE emails per minute (not individual certificates).
            // Example: 182 certificates to same email at 12:57 = 182 log entries but counted as 1 email.
            // This allows detailed tracking while maintaining accurate rate limiting.

            // Log each certificate ID as sent
            foreach ($generated_certificate_ids as $cert_id) {
                $cert_post_type = get_post_type($cert_id);
                $cert_name = '';
                $cert_type = get_post_meta($cert_id, 'certificate_type', true);

                switch ($cert_post_type) {
                    case 'students':
                        $cert_name = get_post_meta($cert_id, 'student_name', true);
                        break;
                    case 'teachers':
                        $cert_name = get_post_meta($cert_id, 'teacher_name', true);
                        break;
                    case 'schools':
                        $cert_name = get_post_meta($cert_id, 'school_name', true);
                        break;
                }

                $log_subject = $subject;
                if (count($generated_certificate_ids) > 1) {
                    $log_subject .= sprintf(' [%d of %d certificates]', array_search($cert_id, $generated_certificate_ids) + 1, count($generated_certificate_ids));
                }

                certificate_generator_log_email($cert_id, $recipient_email, $cert_name, $cert_type, $log_subject, true);
            }

            error_log("Certificate Generator: Successfully logged " . count($generated_certificate_ids) . " certificate(s) as sent to {$recipient_email}");

        } else {
            $detailed_error = "wp_mail() failed after {$attempt} attempts";
            if (!empty($last_error)) {
                $detailed_error .= ": {$last_error}";
            }

            // Log failure for all certificates
            foreach ($generated_certificate_ids as $cert_id) {
                $cert_post_type = get_post_type($cert_id);
                $cert_name = '';
                $cert_type = get_post_meta($cert_id, 'certificate_type', true);

                switch ($cert_post_type) {
                    case 'students':
                        $cert_name = get_post_meta($cert_id, 'student_name', true);
                        break;
                    case 'teachers':
                        $cert_name = get_post_meta($cert_id, 'teacher_name', true);
                        break;
                    case 'schools':
                        $cert_name = get_post_meta($cert_id, 'school_name', true);
                        break;
                }

                certificate_generator_log_email($cert_id, $recipient_email, $cert_name, $cert_type, $subject, false, $detailed_error);
            }
        }
    }

    return $email_sent;
}

/**
 * Send certificate emails in bulk to all entries of a specific post type
 * NOW WITH EMAIL GROUPING: Groups certificates by email address
 *
 * @param string $post_type The post type (students, teachers, or schools)
 * @param bool $skip_already_sent Whether to skip certificates that were already emailed
 * @param array $specific_post_ids Optional array of specific post IDs to process
 * @return array Array with counts of success and failure
 */
function certificate_generator_send_bulk_emails($post_type, $skip_already_sent = true, $specific_post_ids = []) {
    // Check if post type is supported
    if (!in_array($post_type, ['students', 'teachers', 'schools'])) {
        return ['success' => 0, 'failure' => 0, 'skipped' => 0, 'errors' => []];
    }

    // Query posts - either specific IDs or all posts of the type
    $args = [
        'post_type' => $post_type,
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];

    // If specific post IDs are provided, use them
    if (!empty($specific_post_ids)) {
        $args['post__in'] = array_map('intval', $specific_post_ids);
    }

    $query = new WP_Query($args);
    $results = [
        'success' => 0,          // Number of certificates sent
        'failure' => 0,          // Number of certificates failed
        'skipped' => 0,          // Number of certificates skipped
        'errors' => [],          // Error messages
        'total' => 0,            // Total certificates processed
        'emails_sent' => 0,      // Number of unique emails sent
        'grouped_sends' => 0     // Number of grouped sends (ZIP files)
    ];

    // NEW: Group posts by email address
    $posts_by_email = [];
    $skipped_posts = [];

    if ($query->have_posts()) {
        $results['total'] = $query->found_posts;

        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $post_title = get_the_title($post_id);

            // Check if post has email address
            $email = get_post_meta($post_id, 'email', true);
            if (empty($email)) {
                $results['skipped']++;
                $skipped_posts[] = $post_id;
                $results['errors'][] = sprintf(__('Skipped %s: No email address', 'certificate-generator'), $post_title);
                continue;
            }

            // Check if email was already sent and skip if requested
            if ($skip_already_sent && certificate_generator_email_already_sent($post_id, $email)) {
                $results['skipped']++;
                $skipped_posts[] = $post_id;
                $results['errors'][] = sprintf(__('Skipped %s: Email already sent', 'certificate-generator'), $post_title);
                continue;
            }

            // Group by email address
            if (!isset($posts_by_email[$email])) {
                $posts_by_email[$email] = [];
            }
            $posts_by_email[$email][] = $post_id;
        }
        wp_reset_postdata();
    }

    // NEW: Send one email per unique email address
    error_log("Certificate Generator Bulk: Found " . count($posts_by_email) . " unique email addresses to process");

    foreach ($posts_by_email as $email => $post_ids) {
        $cert_count = count($post_ids);

        error_log("Certificate Generator Bulk: Processing {$cert_count} certificate(s) for {$email}");

        // Use the first post ID to trigger the send (the function will find and group all)
        $first_post_id = $post_ids[0];
        $success = certificate_generator_send_email($first_post_id);

        if ($success) {
            $results['success'] += $cert_count;  // Count all certificates as successful
            $results['emails_sent']++;

            if ($cert_count > 1) {
                $results['grouped_sends']++;
                error_log("Certificate Generator Bulk: Successfully sent grouped email with {$cert_count} certificates to {$email}");
            } else {
                error_log("Certificate Generator Bulk: Successfully sent single certificate to {$email}");
            }

        } else {
            $results['failure'] += $cert_count;  // Count all certificates as failed
            $post_titles = [];
            foreach ($post_ids as $pid) {
                $post_titles[] = get_the_title($pid);
            }
            $results['errors'][] = sprintf(
                __('Failed to send %d certificate(s) to %s: %s', 'certificate-generator'),
                $cert_count,
                $email,
                implode(', ', $post_titles)
            );

            error_log("Certificate Generator Bulk: Failed to send to {$email}");
        }
    }

    error_log("Certificate Generator Bulk: Completed. Sent {$results['success']} certificates via {$results['emails_sent']} emails ({$results['grouped_sends']} grouped)");

    return $results;
}

/**
 * Check if email was already sent for a specific post
 *
 * @param int $post_id The post ID
 * @param string $email The email address
 * @return bool Whether email was already sent
 */
function certificate_generator_email_already_sent($post_id, $email) {
    global $wpdb;

    // Check if email log table exists (assuming it's created elsewhere)
    $table_name = $wpdb->prefix . 'cert_email_logs';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return false; // Table doesn't exist, so email hasn't been sent
    }

    // Query the email logs to see if this email was already sent successfully
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name
         WHERE certificate_id = %d
         AND recipient_email = %s
         AND status = 'sent'",
        $post_id,
        $email
    ));

    return $result > 0;
}

/**
 * Auto-send certificate email when certificate is generated or found
 *
 * @param int $post_id The post ID
 * @param bool $force_send Whether to send even if auto-send is disabled
 * @return bool Whether email was sent
 */
function certificate_generator_auto_send_email($post_id, $force_send = false) {
    // Check if auto-send is enabled (you can add this setting to admin)
    $auto_send_enabled = get_option('certificate_generator_auto_send_enabled', false);

    if (!$auto_send_enabled && !$force_send) {
        return false;
    }

    // Check if email was already sent to avoid duplicates
    $email = get_post_meta($post_id, 'email', true);
    if (!empty($email) && certificate_generator_email_already_sent($post_id, $email)) {
        return false; // Already sent
    }

    // Send the email
    return certificate_generator_send_email($post_id);
}

/**
 * Enhanced email sending with template customization
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $name Recipient name
 * @param string $certificate_title Certificate title
 * @param string $pdf_path Path to certificate PDF
 * @param array $options Additional options
 * @return bool Whether email was sent successfully
 */
function certificate_generator_send_custom_email($to, $subject, $message, $name = '', $certificate_title = '', $pdf_path = '', $options = []) {
    // Get email logo if configured
    $logo_url = get_option('certificate_generator_email_logo', '');

    // Replace placeholders
    $subject = str_replace(['{name}', '{certificate_title}'], [$name, $certificate_title], $subject);
    $message = str_replace(['{name}', '{certificate_title}'], [$name, $certificate_title], $message);

    // Add logo to message if configured
    if (!empty($logo_url)) {
        $message = "<img src='$logo_url' style='max-width:150px; margin-bottom: 20px;'><br><br>" . $message;
    }

    // Format HTML email
    $html_message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
    $html_message .= '<div style="line-height: 1.6; color: #333;">' . wpautop($message) . '</div>';
    $html_message .= '</div>';

    // Set up email headers with fallback system
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    // Use fallback email system like Forminator
    $from_name = get_bloginfo('name');

    // Check if WP Mail SMTP is active
    if (certificate_generator_is_wp_mail_smtp_active()) {
        // Use admin email when SMTP is configured
        $from_email = get_option('admin_email');
    } else {
        // Use noreply@domain.com as fallback (like Forminator)
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : parse_url(home_url(), PHP_URL_HOST);
        $from_email = 'noreply@' . $server_name;
    }

    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

    // Add reply-to if specified
    if (!empty($options['reply_to'])) {
        $headers[] = 'Reply-To: ' . $options['reply_to'];
    }

    // Add CC and BCC if specified
    if (!empty($options['cc'])) {
        foreach ((array)$options['cc'] as $cc_email) {
            $cc_email = trim($cc_email);
            if (!empty($cc_email) && is_email($cc_email)) {
                $headers[] = 'Cc: ' . $cc_email;
            }
        }
    }

    if (!empty($options['bcc'])) {
        foreach ((array)$options['bcc'] as $bcc_email) {
            $bcc_email = trim($bcc_email);
            if (!empty($bcc_email) && is_email($bcc_email)) {
                $headers[] = 'Bcc: ' . $bcc_email;
            }
        }
    }

    // Set up attachments
    $attachments = [];
    if (!empty($pdf_path) && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    // Send email
    $sent = wp_mail($to, $subject, $html_message, $headers, $attachments);

    // Log email activity
    if (function_exists('certificate_generator_log_email')) {
        certificate_generator_log_email(
            $to,
            $subject,
            $sent ? 'success' : 'failed',
            $sent ? null : 'Email sending failed',
            [
                'student_name' => $student_name,
                'has_attachment' => !empty($attachments)
            ]
        );
    }

    return $sent;
}

/**
 * Check if SMTP settings are configured
 *
 * @deprecated This function is kept for backward compatibility but is no longer used
 * @return bool Always returns false to use wp_mail() which is enhanced by WP Mail SMTP
 */
function certificate_generator_use_smtp() {
    // Always return false to use wp_mail() which is enhanced by WP Mail SMTP if installed
    return false;
}

/**
 * Send email using SMTP
 *
 * @deprecated This function is kept for backward compatibility but is no longer used
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @param array $headers Email headers
 * @param array $attachments Email attachments
 * @return bool Whether the email was sent successfully
 */
function certificate_generator_send_smtp_email($to, $subject, $message, $headers = [], $attachments = []) {
    // This function is deprecated. Use wp_mail() instead which is enhanced by WP Mail SMTP if installed
    return wp_mail($to, $subject, $message, $headers, $attachments);
}

/**
 * Enhanced email sending function with fallback system like Forminator
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $attachment Path to attachment file
 * @param string $reply_to Reply-to email address
 * @param string $cc CC recipients (comma-separated)
 * @param string $bcc BCC recipients (comma-separated)
 * @return bool Whether the email was sent successfully
 */
function certificate_generator_send_fallback_email($to, $subject, $message, $attachment = '', $reply_to = '', $cc = '', $bcc = '') {
    // Prepare headers with fallback system
    $headers = [];

    // Use fallback email system like Forminator
    $from_name = get_bloginfo('name');

    // Check if WP Mail SMTP is active
    if (certificate_generator_is_wp_mail_smtp_active()) {
        // Use admin email when SMTP is configured
        $from_email = get_option('admin_email');
    } else {
        // Use noreply@domain.com as fallback (like Forminator)
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : parse_url(home_url(), PHP_URL_HOST);
        $from_email = 'noreply@' . $server_name;
    }

    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

    if (!empty($reply_to)) {
        $headers[] = 'Reply-To: ' . sanitize_email($reply_to);
    }

    if (!empty($cc)) {
        $headers[] = 'Cc: ' . sanitize_text_field($cc);
    }

    if (!empty($bcc)) {
        $headers[] = 'Bcc: ' . sanitize_text_field($bcc);
    }

    // Convert message to HTML
    // Store the function reference so we can properly remove it later
    $content_type_filter = function () { return 'text/html'; };
    add_filter('wp_mail_content_type', $content_type_filter);

    // Send mail
    $result = wp_mail($to, $subject, wpautop($message), $headers, $attachment ? [$attachment] : []);

    // Remove content-type filter (using the same function reference)
    remove_filter('wp_mail_content_type', $content_type_filter);

    // Log email activity
    if (function_exists('certificate_generator_log_email')) {
        certificate_generator_log_email(
            $to,
            $subject,
            $result ? 'success' : 'failed',
            $result ? null : 'Email sending failed',
            [
                'has_attachment' => !empty($attachment)
            ]
        );
    }

    return $result;
}

/**
 * Get the appropriate from email address based on SMTP configuration
 *
 * @return string From email address
 */
function certificate_generator_get_from_email() {
    // Check if WP Mail SMTP is active
    if (certificate_generator_is_wp_mail_smtp_active()) {
        // Use admin email when SMTP is configured
        return get_option('admin_email');
    } else {
        // Use noreply@domain.com as fallback (like Forminator)
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : parse_url(home_url(), PHP_URL_HOST);
        return 'noreply@' . $server_name;
    }
}

/**
 * Get email delivery method status
 *
 * @return array Status information about email delivery method
 */
function certificate_generator_get_email_status() {
    $wp_mail_smtp_active = certificate_generator_is_wp_mail_smtp_active();

    return [
        'method' => $wp_mail_smtp_active ? 'WP Mail SMTP' : 'WordPress Default (PHP mail)',
        'from_email' => certificate_generator_get_from_email(),
        'from_name' => get_bloginfo('name'),
        'smtp_configured' => $wp_mail_smtp_active,
        'fallback_active' => !$wp_mail_smtp_active
    ];
}

/**
 * Comprehensive email system health check
 *
 * @return array Detailed health check results
 */
function certificate_generator_email_health_check() {
    $results = [
        'overall_status' => 'healthy',
        'issues' => [],
        'recommendations' => [],
        'details' => []
    ];

    // Check basic WordPress mail functionality
    $results['details']['wp_mail_available'] = function_exists('wp_mail');
    if (!$results['details']['wp_mail_available']) {
        $results['issues'][] = 'wp_mail() function is not available';
        $results['overall_status'] = 'critical';
    }

    // Check SMTP configuration
    $wp_mail_smtp_active = certificate_generator_is_wp_mail_smtp_active();
    $results['details']['smtp_plugin_active'] = $wp_mail_smtp_active;

    if (!$wp_mail_smtp_active) {
        $results['recommendations'][] = 'Consider installing WP Mail SMTP plugin for better email reliability';
    }

    // Check from email configuration
    $from_email = certificate_generator_get_from_email();
    $results['details']['from_email'] = $from_email;
    $results['details']['from_email_valid'] = is_email($from_email);

    if (!is_email($from_email)) {
        $results['issues'][] = "Invalid from email address: {$from_email}";
        $results['overall_status'] = 'warning';
    }

    // Check server environment
    $results['details']['php_version'] = PHP_VERSION;
    $results['details']['mail_function_available'] = function_exists('mail');
    $results['details']['sendmail_path'] = ini_get('sendmail_path') ?: 'Not set';

    if (!function_exists('mail')) {
        $results['issues'][] = 'PHP mail() function is not available on this server';
        $results['overall_status'] = 'critical';
    }

    // Check WordPress debug settings
    $results['details']['wp_debug'] = defined('WP_DEBUG') && WP_DEBUG;
    $results['details']['wp_debug_log'] = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

    if (!$results['details']['wp_debug_log']) {
        $results['recommendations'][] = 'Enable WP_DEBUG_LOG to get detailed email error logs';
    }

    // Check certificate generation dependencies
    $results['details']['fpdf_available'] = file_exists(plugin_dir_path(__FILE__) . 'fpdf/fpdf.php');
    $results['details']['font_manager_available'] = class_exists('CertificateGenerator_FontManager');
    $results['details']['certificate_function_available'] = function_exists('generate_certificate_pdf_email');

    if (!$results['details']['fpdf_available']) {
        $results['issues'][] = 'FPDF library not found - certificate generation will fail';
        $results['overall_status'] = 'critical';
    }

    if (!$results['details']['certificate_function_available']) {
        $results['issues'][] = 'Certificate generation function not available';
        $results['overall_status'] = 'critical';
    }

    // Check upload directory permissions
    $upload_dir = wp_upload_dir();
    $results['details']['upload_dir'] = $upload_dir['basedir'];
    $results['details']['upload_dir_writable'] = wp_is_writable($upload_dir['basedir']);

    if (!$results['details']['upload_dir_writable']) {
        $results['issues'][] = 'WordPress uploads directory is not writable - certificate storage will fail';
        $results['overall_status'] = 'critical';
    }

    // Check email log table
    global $wpdb;
    $table_name = $wpdb->prefix . 'cert_email_logs';
    $results['details']['email_log_table_exists'] = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

    if (!$results['details']['email_log_table_exists']) {
        $results['issues'][] = 'Email log table does not exist - email tracking will not work';
        $results['overall_status'] = 'warning';
        $results['recommendations'][] = 'Deactivate and reactivate the plugin to create the email log table';
    }

    // Final status determination
    if (!empty($results['issues'])) {
        $critical_issues = array_filter($results['issues'], function($issue) {
            return strpos(strtolower($issue), 'critical') !== false ||
                   strpos(strtolower($issue), 'not available') !== false ||
                   strpos(strtolower($issue), 'not found') !== false;
        });

        if (!empty($critical_issues)) {
            $results['overall_status'] = 'critical';
        } elseif ($results['overall_status'] !== 'critical') {
            $results['overall_status'] = 'warning';
        }
    }

    return $results;
}

/**
 * Hook into wp_mail_failed to log detailed error information
 * This hook is called when wp_mail() fails
 *
 * @param WP_Error $error The error object from wp_mail()
 */
function certificate_generator_log_wp_mail_error($error) {
    if (is_wp_error($error)) {
        $error_data = [
            'error_code' => $error->get_error_code(),
            'error_message' => $error->get_error_message(),
            'error_data' => $error->get_error_data(),
            'timestamp' => current_time('mysql'),
            'server' => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'unknown'
        ];

        // Log to WordPress error log (only in debug mode for detailed info)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Certificate Generator Debug - wp_mail_failed hook triggered');
            error_log('Certificate Generator Debug - WP_Error details: ' . print_r($error_data, true));
        }

        // Also log to debug.log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = sprintf(
                "[%s] Certificate Generator Email Failure\nCode: %s\nMessage: %s\nData: %s\n",
                current_time('Y-m-d H:i:s'),
                $error->get_error_code(),
                $error->get_error_message(),
                print_r($error->get_error_data(), true)
            );
            error_log($log_message);
        }
    }
}
add_action('wp_mail_failed', 'certificate_generator_log_wp_mail_error', 10, 1);

/**
 * Display admin notice with email health check status
 */
function certificate_generator_email_health_admin_notice() {
    // Only show on certificate generator related pages
    $screen = get_current_screen();
    if (!$screen || (
        strpos($screen->id, 'certificate') === false &&
        strpos($screen->id, 'students') === false &&
        strpos($screen->id, 'teachers') === false &&
        strpos($screen->id, 'schools') === false
    )) {
        return;
    }

    // Run health check
    $health = certificate_generator_email_health_check();

    // Only show notice if there are issues or warnings
    if ($health['overall_status'] === 'healthy') {
        return;
    }

    $notice_class = $health['overall_status'] === 'critical' ? 'notice-error' : 'notice-warning';

    ?>
    <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
        <h3>Certificate Generator - Email Configuration Status</h3>

        <?php if (!empty($health['issues'])): ?>
            <h4>Issues Detected:</h4>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($health['issues'] as $issue): ?>
                    <li><?php echo esc_html($issue); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($health['recommendations'])): ?>
            <h4>Recommendations:</h4>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($health['recommendations'] as $recommendation): ?>
                    <li><?php echo esc_html($recommendation); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h4>Current Configuration:</h4>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><strong>SMTP Plugin:</strong> <?php echo $health['details']['smtp_plugin_active'] ? 'Active' : 'Not Active'; ?></li>
            <li><strong>From Email:</strong> <?php echo esc_html($health['details']['from_email']); ?></li>
            <li><strong>PHP mail() Available:</strong> <?php echo $health['details']['mail_function_available'] ? 'Yes' : 'No'; ?></li>
            <?php if (isset($health['details']['sendmail_path'])): ?>
                <li><strong>Sendmail Path:</strong> <?php echo esc_html($health['details']['sendmail_path']); ?></li>
            <?php endif; ?>
        </ul>

        <?php if (!$health['details']['smtp_plugin_active']): ?>
            <p><strong>Action Required:</strong> Install and configure
                <a href="<?php echo admin_url('plugin-install.php?s=wp+mail+smtp&tab=search&type=term'); ?>" target="_blank">WP Mail SMTP</a>
                plugin for reliable email delivery on shared hosting.</p>
        <?php endif; ?>

        <p style="font-size: 12px; color: #666;">
            <em>Check your server's error logs and wp-content/debug.log for detailed email error messages.</em>
        </p>
    </div>
    <?php
}
add_action('admin_notices', 'certificate_generator_email_health_admin_notice');

/**
 * Test email send function with comprehensive error reporting
 *
 * @param string $test_email Test recipient email
 * @param array $options Optional test parameters
 * @return array Test results
 */
function certificate_generator_test_email_send($test_email, $options = []) {
    $defaults = [
        'subject' => 'Certificate Generator Email Test',
        'message' => 'This is a test email from the Certificate Generator plugin.',
        'attach_sample_pdf' => false
    ];

    $options = wp_parse_args($options, $defaults);

    $results = [
        'success' => false,
        'message' => '',
        'details' => [],
        'errors' => []
    ];

    // Validate test email
    if (!is_email($test_email)) {
        $results['errors'][] = 'Invalid test email address';
        $results['message'] = 'Test failed: Invalid email address';
        return $results;
    }

    // Run health check first
    $health_check = certificate_generator_email_health_check();
    $results['details']['health_check'] = $health_check;

    if ($health_check['overall_status'] === 'critical') {
        $results['errors'] = array_merge($results['errors'], $health_check['issues']);
        $results['message'] = 'Test failed: Critical system issues detected';
        return $results;
    }

    // Attempt to send test email
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $html_message = '<p>' . esc_html($options['message']) . '</p>';
    $attachments = [];

    // Add sample PDF if requested
    if ($options['attach_sample_pdf']) {
        // Create a simple test PDF
        $test_pdf_path = wp_upload_dir()['basedir'] . '/cert-test.pdf';
        if (file_exists($test_pdf_path)) {
            $attachments[] = $test_pdf_path;
        }
    }

    $start_time = microtime(true);
    $email_sent = wp_mail($test_email, $options['subject'], $html_message, $headers, $attachments);
    $send_time = microtime(true) - $start_time;

    $results['details']['send_time'] = round($send_time, 3);
    $results['success'] = $email_sent;

    if ($email_sent) {
        $results['message'] = 'Test email sent successfully';
    } else {
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            $results['errors'][] = 'PHPMailer Error: ' . $phpmailer->ErrorInfo;
        }

        $last_error = error_get_last();
        if ($last_error) {
            $results['errors'][] = 'PHP Error: ' . $last_error['message'];
        }

        $results['message'] = 'Test email failed to send';
    }

    return $results;
}

/**
 * Queue bulk emails asynchronously instead of sending immediately
 * This is a wrapper that initiates batched processing to prevent timeouts
 *
 * @param string $post_type Post type (students/teachers/schools)
 * @param bool $skip_already_sent Whether to skip already sent emails
 * @param array $post_ids Array of post IDs to process
 * @return array Result with 'success', 'total_posts', 'batch_id', 'requires_batching'
 */
function certificate_generator_queue_bulk_emails_async($post_type, $skip_already_sent = true, $post_ids = []) {
    // Initialize results
    $result = [
        'success' => false,
        'total_posts' => 0,
        'batch_id' => '',
        'requires_batching' => false,
        'errors' => []
    ];

    // Validate post type
    if (!in_array($post_type, ['students', 'teachers', 'schools'])) {
        $result['errors'][] = 'Invalid post type';
        return $result;
    }

    // Count total posts to determine if batching is needed
    $query_args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids'
    ];

    if (!empty($post_ids)) {
        $query_args['post__in'] = $post_ids;
        $query_args['posts_per_page'] = -1;
    }

    $query = new WP_Query($query_args);
    $total_posts = $query->found_posts;

    if ($total_posts === 0) {
        $result['errors'][] = 'No posts found to process';
        return $result;
    }

    // Generate unique batch ID for tracking
    $batch_id = 'batch_' . time() . '_' . wp_generate_password(8, false);
    $result['batch_id'] = $batch_id;
    $result['total_posts'] = $total_posts;
    $result['success'] = true;

    // If more than 50 posts, use batched processing
    if ($total_posts > 50) {
        $result['requires_batching'] = true;

        // Store batch metadata for processing
        set_transient('cert_batch_' . $batch_id, [
            'post_type' => $post_type,
            'skip_already_sent' => $skip_already_sent,
            'post_ids' => $post_ids,
            'total_posts' => $total_posts,
            'processed' => 0
        ], 3600); // 1 hour expiry

        return $result;
    }

    // For small batches (<= 50 posts), process immediately
    return certificate_generator_queue_bulk_emails_batch($post_type, $skip_already_sent, $post_ids, 0, 50, $batch_id);
}

/**
 * Process a batch of posts for queueing (optimized to prevent N+1 queries)
 *
 * @param string $post_type Post type
 * @param bool $skip_already_sent Skip already sent
 * @param array $post_ids Specific post IDs (optional)
 * @param int $offset Offset for pagination
 * @param int $limit Number of posts to process
 * @param string $batch_id Batch identifier
 * @return array Result with queued/skipped counts
 */
function certificate_generator_queue_bulk_emails_batch($post_type, $skip_already_sent, $post_ids, $offset, $limit, $batch_id) {
    global $wpdb;

    $result = [
        'success' => false,
        'queued' => 0,
        'skipped' => 0,
        'batch_id' => $batch_id,
        'errors' => []
    ];

    // Get email meta field name based on post type
    $email_field_map = [
        'students' => 'student_email',
        'teachers' => 'teacher_email',
        'schools' => 'school_email'
    ];
    $email_field = $email_field_map[$post_type];
    $name_field = str_replace('_email', '_name', $email_field);

    // Query posts with LIMIT to prevent timeout
    $query_args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC'
    ];

    if (!empty($post_ids)) {
        $query_args['post__in'] = $post_ids;
    }

    $query = new WP_Query($query_args);
    $batch_post_ids = $query->posts;

    if (empty($batch_post_ids)) {
        $result['success'] = true;
        return $result;
    }

    // OPTIMIZATION: Load ALL meta for these posts in ONE query (eliminates N+1 problem)
    $post_ids_str = implode(',', array_map('intval', $batch_post_ids));
    $meta_data = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, meta_key, meta_value
         FROM {$wpdb->postmeta}
         WHERE post_id IN ($post_ids_str)
         AND meta_key IN (%s, %s, %s, %s)
         ORDER BY post_id",
        $email_field,
        $name_field,
        'certificate_type',
        '_certificate_email_sent'
    ), ARRAY_A);

    // Build meta cache array
    $meta_cache = [];
    foreach ($meta_data as $row) {
        $post_id = $row['post_id'];
        if (!isset($meta_cache[$post_id])) {
            $meta_cache[$post_id] = [];
        }
        $meta_cache[$post_id][$row['meta_key']] = $row['meta_value'];
    }

    // Group posts by email
    $posts_by_email = [];
    foreach ($batch_post_ids as $post_id) {
        $email = isset($meta_cache[$post_id][$email_field]) ? $meta_cache[$post_id][$email_field] : '';

        if (empty($email) || !is_email($email)) {
            continue;
        }

        // Check if already sent
        if ($skip_already_sent) {
            $already_sent = isset($meta_cache[$post_id]['_certificate_email_sent']) ? $meta_cache[$post_id]['_certificate_email_sent'] : '';
            if ($already_sent) {
                $result['skipped']++;
                continue;
            }
        }

        if (!isset($posts_by_email[$email])) {
            $posts_by_email[$email] = [];
        }
        $posts_by_email[$email][] = $post_id;
    }

    // Queue emails using existing queue system
    $queued_count = 0;
    $table_name = $wpdb->prefix . 'cert_email_queue';

    foreach ($posts_by_email as $email => $certificate_ids) {
        // For each unique email, queue the first certificate
        // The send function will automatically group all certificates for this email
        $first_cert_id = $certificate_ids[0];

        // Get recipient name from cached meta (NO additional queries!)
        $recipient_name = isset($meta_cache[$first_cert_id][$name_field]) ? $meta_cache[$first_cert_id][$name_field] : '';
        $certificate_type = isset($meta_cache[$first_cert_id]['certificate_type']) ? $meta_cache[$first_cert_id]['certificate_type'] : '';

        // Check if already in queue (avoid duplicates)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name
             WHERE certificate_id = %d
             AND recipient_email = %s
             AND status IN ('pending', 'sending')",
            $first_cert_id,
            $email
        ));

        if ($existing) {
            $result['skipped']++;
            continue;
        }

        // Insert into queue
        $inserted = $wpdb->insert(
            $table_name,
            [
                'certificate_id' => $first_cert_id,
                'recipient_email' => $email,
                'recipient_name' => $recipient_name,
                'post_type' => $post_type,
                'certificate_type' => $certificate_type,
                'status' => 'pending',
                'attempts' => 0,
                'scheduled_time' => current_time('mysql'),
                'priority' => 5,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );

        if ($inserted) {
            $queued_count++;

            // Store batch_id as meta for tracking
            $queue_id = $wpdb->insert_id;
            update_option('cert_queue_' . $queue_id . '_batch', $batch_id);
        }
    }

    $result['queued'] = $queued_count;
    $result['success'] = $queued_count > 0;

    // Trigger immediate queue processing using existing queue system
    if ($queued_count > 0) {
        // The existing queue system in bulk-email-sender.php will handle processing
        // Trigger it immediately by calling the existing function
        if (function_exists('certificate_generator_process_queue_now')) {
            // Process one batch immediately (non-blocking)
            wp_remote_post(
                admin_url('admin-ajax.php'),
                [
                    'timeout' => 0.01,
                    'blocking' => false,
                    'body' => [
                        'action' => 'cert_trigger_queue_processing'
                    ]
                ]
            );
        }
    }

    error_log(sprintf(
        'Certificate Generator: Queued %d emails, skipped %d (batch: %s)',
        $queued_count,
        $result['skipped'],
        $batch_id
    ));

    return $result;
}

/**
 * AJAX handler to trigger existing queue processing (wrapper)
 */
add_action('wp_ajax_cert_trigger_queue_processing', 'certificate_generator_trigger_queue_processing');
function certificate_generator_trigger_queue_processing() {
    // Trigger the existing queue processor from bulk-email-sender.php
    if (function_exists('certificate_generator_process_queue_now')) {
        certificate_generator_process_queue_now(1);
    }
    wp_die();
}

/**
 * AJAX handler for processing email queue batches (optimized for large datasets)
 */
add_action('wp_ajax_certificate_generator_process_batch', 'certificate_generator_process_batch_ajax');
function certificate_generator_process_batch_ajax() {
    // Check nonce
    check_ajax_referer('certificate_generator_bulk_email', 'nonce');

    // Check permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'certificate-generator')]);
    }

    // Get batch parameters
    $batch_id = isset($_POST['batch_id']) ? sanitize_text_field($_POST['batch_id']) : '';
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;

    if (empty($batch_id)) {
        wp_send_json_error(['message' => __('Invalid batch ID', 'certificate-generator')]);
    }

    // Get batch metadata from transient
    $batch_data = get_transient('cert_batch_' . $batch_id);

    if (!$batch_data) {
        wp_send_json_error(['message' => __('Batch data expired or not found', 'certificate-generator')]);
    }

    // Process this batch
    $result = certificate_generator_queue_bulk_emails_batch(
        $batch_data['post_type'],
        $batch_data['skip_already_sent'],
        $batch_data['post_ids'],
        $offset,
        $limit,
        $batch_id
    );

    // Update progress
    $batch_data['processed'] = $offset + $limit;
    set_transient('cert_batch_' . $batch_id, $batch_data, 3600);

    // Calculate progress percentage
    $progress_percent = min(100, round(($batch_data['processed'] / $batch_data['total_posts']) * 100));

    // Check if complete
    $is_complete = $batch_data['processed'] >= $batch_data['total_posts'];

    // Prepare response
    $response = [
        'success' => $result['success'],
        'queued' => $result['queued'],
        'skipped' => $result['skipped'],
        'processed' => $batch_data['processed'],
        'total' => $batch_data['total_posts'],
        'progress' => $progress_percent,
        'complete' => $is_complete,
        'batch_id' => $batch_id
    ];

    // Clean up transient if complete
    if ($is_complete) {
        delete_transient('cert_batch_' . $batch_id);
    }

    wp_send_json_success($response);
}
?>