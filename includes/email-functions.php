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
 * Send certificate email for a specific post
 *
 * @param int $post_id The post ID (student, teacher, or school)
 * @param bool $log_email Whether to log the email attempt (default: true)
 * @return bool Whether the email was sent successfully
 */
function certificate_generator_send_email($post_id, $log_email = true) {
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
    
    // Replace placeholders
    $subject = str_replace(['{name}', '{certificate_title}'], [$recipient_name, $certificate_type], $subject);
    $title = str_replace(['{name}', '{certificate_title}'], [$recipient_name, $certificate_type], $title);
    $message = str_replace(['{name}', '{certificate_title}'], [$recipient_name, $certificate_type], $message);
    
    // Get additional email settings
    $reply_to = isset($options[$prefix . 'reply_to']) ? $options[$prefix . 'reply_to'] : '';
    $cc = isset($options[$prefix . 'cc']) ? array_map('trim', explode(',', $options[$prefix . 'cc'])) : [];
    $bcc = isset($options[$prefix . 'bcc']) ? array_map('trim', explode(',', $options[$prefix . 'bcc'])) : [];
    
    // Determine fields based on post type
    $fields = [];
    switch ($post_type) {
        case 'students':
            $fields = [];
            if (get_post_meta($post_id, 'field_1_visible', true) !== '0') {
                $fields[] = 'student_name';
            }
            if (get_post_meta($post_id, 'field_2_visible', true) !== '0') {
                $fields[] = 'school_name';
            }
            if (get_post_meta($post_id, 'field_3_visible', true) !== '0') {
                $fields[] = 'issue_date';
            }
            break;
        case 'teachers':
            $fields = ['teacher_name', 'school_name'];
            if (get_post_meta($post_id, 'field_3_visible', true) !== '0') {
                $fields[] = 'issue_date';
            }
            break;
        case 'schools':
            $fields = ['school_name'];
            if (get_post_meta($post_id, 'field_3_visible', true) !== '0') {
                $fields[] = 'issue_date';
            }
            break;
    }
    
    // Generate certificate PDF using the email-compatible function
    try {
        // Log the fields being used for certificate generation
        error_log("Generating certificate for email with fields: " . print_r($fields, true));
        
        // Create email options array with additional information
        $email_options = [
            'recipient_email' => $recipient_email,
            'recipient_name' => $recipient_name,
            'certificate_type' => $certificate_type,
            'post_type' => $post_type
        ];
        
        // Generate the certificate PDF with explicit fields and options
        $certificate_result = generate_certificate_pdf_email($post_id, $fields, $email_options);
        
        if (!$certificate_result || !isset($certificate_result['path'])) {
            error_log("Certificate generation failed for email. Result: " . print_r($certificate_result, true));
            if ($log_email) {
                certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, 'Certificate generation failed');
            }
            return false;
        }
        $certificate_path = $certificate_result['path'];
        error_log("Certificate generated successfully for email. Path: {$certificate_path}");
    } catch (Exception $e) {
        error_log('Certificate generation failed: ' . $e->getMessage());
        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, 'Certificate generation error: ' . $e->getMessage());
        }
        return false;
    }
    
    // Verify certificate file exists
    if (empty($certificate_path) || !file_exists($certificate_path)) {
        if ($log_email) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, 'Certificate file not found');
        }
        return false;
    }
    
    // Format HTML email
    $html_message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
    $html_message .= '<h1 style="color: #2c3e50; margin-bottom: 20px;">' . esc_html($title) . '</h1>';
    $html_message .= '<div style="line-height: 1.6; color: #333;">' . nl2br(esc_html($message)) . '</div>';
    $html_message .= '</div>';
    
    // Set up email headers with fallback system
    $headers = [];
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    
    // Use fallback email system like Forminator
    $from_name = get_bloginfo('name');
    
    // Always use fallback system for free operation (like Forminator)
    $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : parse_url(home_url(), PHP_URL_HOST);
    $from_email = 'noreply@' . $server_name;
    
    // Fallback to admin email if server name is not available
    if (empty($server_name) || $server_name === 'localhost') {
        $from_email = get_option('admin_email');
    }
    
    $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
    
    if (!empty($reply_to)) {
        $headers[] = 'Reply-To: ' . $reply_to;
    }
    
    // Add CC recipients
    foreach ($cc as $cc_email) {
        $cc_email = trim($cc_email);
        if (!empty($cc_email) && is_email($cc_email)) {
            $headers[] = 'Cc: ' . $cc_email;
        }
    }
    
    // Add BCC recipients
    foreach ($bcc as $bcc_email) {
        $bcc_email = trim($bcc_email);
        if (!empty($bcc_email) && is_email($bcc_email)) {
            $headers[] = 'Bcc: ' . $bcc_email;
        }
    }
    
    // Set up attachments based on settings
    $attachments = [];
    
    // Check if certificate should be attached
    $attach_certificate = isset($options[$prefix . 'attach_certificate']) ? $options[$prefix . 'attach_certificate'] : '1';
    if ($attach_certificate === '1') {
        $attachments[] = $certificate_path;
    }
    
    // Send email using WordPress mail function
    // WP Mail SMTP will automatically handle the SMTP configuration if it's installed
    $email_sent = wp_mail($recipient_email, $subject, $html_message, $headers, $attachments);

    // Extended Debugging for wp_mail
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[Certificate Generator] wp_mail attempt to ' . esc_html($recipient_email) . '. Subject: ' . esc_html($subject));
        error_log('[Certificate Generator] wp_mail result: ' . ($email_sent ? 'true' : 'false'));
        if (!$email_sent) {
            global $ts_mail_errors; // Some plugins use this global to track errors
            global $phpmailer; // WordPress's global PHPMailer object
            
            if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                error_log('[Certificate Generator] PHPMailer ErrorInfo: ' . esc_html($phpmailer->ErrorInfo));
            } elseif (isset($ts_mail_errors) && is_array($ts_mail_errors) && !empty($ts_mail_errors)) {
                error_log('[Certificate Generator] ts_mail_errors: ' . print_r($ts_mail_errors, true));
            } else {
                error_log('[Certificate Generator] No specific PHPMailer error information available. Check server mail logs.');
            }
            // Capture last PHP error, in case it's related
            $last_error = error_get_last();
            if ($last_error) {
                error_log('[Certificate Generator] Last PHP error: ' . print_r($last_error, true));
            }
        }
    }

    // Log email attempt if logging is enabled
    if ($log_email) {
        if ($email_sent) {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, true);
        } else {
            certificate_generator_log_email($post_id, $recipient_email, $recipient_name, $certificate_type, $subject, false, 'wp_mail() function failed');
        }
    }
    
    return $email_sent;
}

/**
 * Send certificate emails in bulk to all entries of a specific post type
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
    $results = ['success' => 0, 'failure' => 0, 'skipped' => 0, 'errors' => [], 'total' => 0];
    
    if ($query->have_posts()) {
        $results['total'] = $query->found_posts;
        
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // Get post title for error reporting
            $post_title = get_the_title($post_id);
            
            // Check if post has email address
            $email = get_post_meta($post_id, 'email', true);
            if (empty($email)) {
                $results['skipped']++;
                $results['errors'][] = sprintf(__('Skipped %s: No email address', 'certificate-generator'), $post_title);
                continue;
            }
            
            // Check if email was already sent and skip if requested
            if ($skip_already_sent && certificate_generator_email_already_sent($post_id, $email)) {
                $results['skipped']++;
                $results['errors'][] = sprintf(__('Skipped %s: Email already sent', 'certificate-generator'), $post_title);
                continue;
            }
            
            // Send email for this post
            $success = certificate_generator_send_email($post_id);
            
            if ($success) {
                $results['success']++;
            } else {
                $results['failure']++;
                $results['errors'][] = sprintf(__('Failed to send email to %s (%s)', 'certificate-generator'), $post_title, $email);
            }
        }
        wp_reset_postdata();
    }
    
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
    $html_message .= '<div style="line-height: 1.6; color: #333;">' . nl2br($message) . '</div>';
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
    add_filter('wp_mail_content_type', function () { return 'text/html'; });
    
    // Send mail
    $result = wp_mail($to, $subject, nl2br($message), $headers, $attachment ? [$attachment] : []);
    
    // Remove content-type filter
    remove_filter('wp_mail_content_type', function () { return 'text/html'; });
    
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

?>