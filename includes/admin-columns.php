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
            $new_columns['email_status'] = __('Email Status', 'certificate-generator');
            $new_columns['school_name'] = __('School Name', 'certificate-generator');
            $new_columns['certificate_type'] = __('Certificate Type', 'certificate-generator');
            $new_columns['issue_date'] = __('Issue Date', 'certificate-generator');
            $new_columns['send_email'] = __('Send Email', 'certificate-generator');
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
        case 'email_status':
            $email = get_post_meta($post_id, 'email', true);
            if (!empty($email)) {
                $email_sent = certificate_generator_email_already_sent($post_id, $email);
                if ($email_sent) {
                    echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__('Email sent successfully', 'certificate-generator') . '"></span> ';
                    echo '<span style="color: #46b450;">' . __('Sent', 'certificate-generator') . '</span>';
                } else {
                    echo '<span class="dashicons dashicons-email-alt" style="color: #ffba00;" title="' . esc_attr__('Email not sent yet', 'certificate-generator') . '"></span> ';
                    echo '<span style="color: #ffba00;">' . __('Pending', 'certificate-generator') . '</span>';
                }
            } else {
                echo '<span class="dashicons dashicons-warning" style="color: #d63638;" title="' . esc_attr__('No email address available', 'certificate-generator') . '"></span> ';
                echo '<span style="color: #d63638;">' . __('No Email', 'certificate-generator') . '</span>';
            }
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
        case 'send_email':
            $email = get_post_meta($post_id, 'email', true);
            if (!empty($email)) {
                echo '<button type="button" class="button send-certificate-email" data-post-id="' . esc_attr($post_id) . '" data-email="' . esc_attr($email) . '">' . __('Send Email', 'certificate-generator') . '</button>';
                echo '<span class="email-status" id="email-status-' . esc_attr($post_id) . '"></span>';
            } else {
                echo '<span class="dashicons dashicons-no-alt" style="color: #d63638;" title="' . esc_attr__('No email address available', 'certificate-generator') . '"></span>';
            }
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

    if (!function_exists('get_current_screen')) {
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

// Add AJAX handler for sending emails
add_action('wp_ajax_certificate_generator_send_single_email', 'certificate_generator_send_single_email_ajax');
function certificate_generator_send_single_email_ajax() {
    // Check nonce for security
    check_ajax_referer('certificate_generator_send_email', 'nonce');
    
    // Check if user has permission
    // if (!current_user_can('edit_posts')) {
    //     wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'certificate-generator')));
    //     wp_die();
    // }
    
    // Get post ID from request
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Invalid post ID.', 'certificate-generator')));
        wp_die();
    }
    
    // Check if post has an email address
    $email = get_post_meta($post_id, 'email', true);
    if (empty($email)) {
        wp_send_json_error(array('message' => __('No email address found for this entry.', 'certificate-generator')));
        wp_die();
    }
    
    // Check if certificate type exists
    $certificate_type = get_post_meta($post_id, 'certificate_type', true);
    if (empty($certificate_type)) {
        wp_send_json_error(array('message' => __('Certificate type is missing. Cannot generate certificate.', 'certificate-generator')));
        wp_die();
    }
    
    // Check if certificate template exists
    $certificate_query = new WP_Query([
        'post_type' => 'certificates',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'certificate_type',
                'value' => $certificate_type,
                'compare' => '='
            ]
        ]
    ]);
    
    $template_id = null;
    $template_url = '';
    
    if (!$certificate_query->have_posts()) {
        // Try case-insensitive match as a fallback
        $found = false;
        $all_certificates = new WP_Query([
            'post_type' => 'certificates',
            'posts_per_page' => -1,
        ]);
        
        if ($all_certificates->have_posts()) {
            while ($all_certificates->have_posts()) {
                $all_certificates->the_post();
                $template_type = get_post_meta(get_the_ID(), 'certificate_type', true);
                
                if (strtolower(trim($template_type)) === strtolower(trim($certificate_type))) {
                    $template_id = get_the_ID();
                    $found = true;
                    break;
                }
            }
            wp_reset_postdata();
        }
        
        if (!$found) {
            wp_send_json_error(array('message' => sprintf(__('No certificate template found for type: %s', 'certificate-generator'), esc_html($certificate_type))));
            wp_die();
        }
    } else {
        $template_id = $certificate_query->posts[0]->ID;
    }
    
    // Check if template has a valid URL before attempting to generate certificate
    if (!$template_id) {
        // If we somehow got here without a template ID, return an error
        wp_send_json_error(array('message' => __('Certificate template ID is missing. Cannot generate certificate.', 'certificate-generator')));
        wp_die();
    }
    
    $template_url = get_post_meta($template_id, 'template_url', true);
    
    if (empty($template_url)) {
        wp_send_json_error(array('message' => __('Certificate template is missing a background image URL.', 'certificate-generator')));
        wp_die();
    }
    
    // Check if template has field positions configured
    $post_type = get_post_type($post_id);
    $fields = [];
    switch ($post_type) {
        case 'students':
            $fields = ['student_name', 'school_name', 'issue_date'];
            break;
        case 'teachers':
            $fields = ['teacher_name', 'school_name', 'issue_date'];
            break;
        case 'schools':
            $fields = ['school_name', 'issue_date'];
            break;
    }
    
    // Debug log the fields and post type
    error_log("Post type: {$post_type}, Fields: " . print_r($fields, true));
    error_log("Template ID: {$template_id}, Template URL: {$template_url}");
    
    // Check if the template has at least the minimum number of field positions configured
    $field_count = count($fields);
    $missing_positions = [];
    $has_enough_fields = true;
    
    // First check if we have enough field positions defined in the template
    for ($i = 1; $i <= $field_count; $i++) {
        // Skip checking if field is not visible
        $is_visible = get_post_meta($template_id, "field_{$i}_visible", true);
        if ($is_visible === '0') {
            continue;
        }
        
        $position_x = get_post_meta($template_id, "field_{$i}_position_x", true);
        $position_y = get_post_meta($template_id, "field_{$i}_position_y", true);
        
        // Debug log to check what's happening
        error_log("Checking field position for field_{$i} (X): " . ($position_x ? $position_x : 'empty'));
        error_log("Checking field position for field_{$i} (Y): " . ($position_y ? $position_y : 'empty'));
        
        if (empty($position_x) || empty($position_y)) {
            $has_enough_fields = false;
            $missing_positions[] = $fields[$i-1]; // Map field number to field name
            // Don't break, collect all missing fields
        }
    }
    
    // If we don't have enough field positions, return an error with instructions
    if (!$has_enough_fields) {
        error_log("Certificate template is missing field positions. Template ID: {$template_id}");
        
        $missing_fields = implode(', ', $missing_positions);
        $error_message = sprintf(__('Certificate template is missing field positions for: %s', 'certificate-generator'), esc_html($missing_fields));
        $error_message .= '<br><br>' . __('To fix this issue:', 'certificate-generator');
        $error_message .= '<ol>';
        $error_message .= '<li>' . __('Edit the certificate template (ID: ', 'certificate-generator') . esc_html($template_id) . ')</li>';
        $error_message .= '<li>' . __('Make sure all field positions (Field 1, Field 2, Field 3) have both X and Y coordinates defined', 'certificate-generator') . '</li>';
        $error_message .= '<li>' . __('Field 1 maps to student_name/teacher_name, Field 2 maps to school_name, and Field 3 maps to issue_date', 'certificate-generator') . '</li>';
        $error_message .= '</ol>';
        
        wp_send_json_error(array('message' => $error_message));
        wp_die();
    }
    
    // Send the email with the correct fields based on post type
    // We need to explicitly pass the fields to ensure the certificate is generated correctly
    error_log("Sending email for post ID: {$post_id} with fields: " . print_r($fields, true));
    
    // Check if certificate file already exists
    $certificate_path = get_post_meta($post_id, 'certificate_file_path', true);
    $certificate_exists = !empty($certificate_path) && file_exists($certificate_path);
    
    if (!$certificate_exists) {
        error_log("Certificate file does not exist or path is empty. Generating new certificate.");
        // Try to generate the certificate first to ensure it exists
        $certificate_result = generate_certificate_pdf_email($post_id, $fields);
        if (!$certificate_result) {
            wp_send_json_error(array('message' => __('Failed to generate certificate. Please check certificate template settings.', 'certificate-generator')));
            wp_die();
        }
    } else {
        error_log("Certificate file already exists at: {$certificate_path}");
    }
    
    // Now send the email
    $success = certificate_generator_send_email($post_id, 'Certificate Email');
    
    if ($success) {
        wp_send_json_success(array('message' => __('Email sent successfully!', 'certificate-generator')));
        wp_die();
    } 
    else {
        // Get certificate file path to check if generation succeeded
        $certificate_path = get_post_meta($post_id, 'certificate_file_path', true);
        
        if (empty($certificate_path) || !file_exists($certificate_path)) {
            // Check for specific issues with certificate generation
            if (empty($template_url)) {
                wp_send_json_error(array('message' => __('Certificate template URL is missing. Please check the template settings.', 'certificate-generator')));
                wp_die();
            }
            
            // Validate template URL if function exists
            if (function_exists('validate_template_url')) {
                $validation_result = validate_template_url($template_url);
                if ($validation_result !== true) {
                    // Extract the error message from the HTML
                    $error_message = strip_tags($validation_result);
                    // Clean up the error message
                    $error_message = str_replace('Error:', '', $error_message);
                    $error_message = trim($error_message);
                    wp_send_json_error(array('message' => __('Template URL error: ', 'certificate-generator') . esc_html($error_message)));
                    wp_die();
                }
            }
            
            // Check for missing field positions using the correct meta key format
            $missing_positions = [];
            $field_count = count($fields);
            
            for ($i = 1; $i <= $field_count; $i++) {
                $position_x = get_post_meta($template_id, "field_{$i}_position_x", true);
                $position_y = get_post_meta($template_id, "field_{$i}_position_y", true);
                
                if (empty($position_x) || empty($position_y)) {
                    $missing_positions[] = $fields[$i-1]; // Map field number to field name
                }
            }
            
            if (!empty($missing_positions)) {
                $missing_fields = implode(', ', $missing_positions);
                
                $error_message = sprintf(__('Certificate template is missing field definitions for: %s', 'certificate-generator'), esc_html($missing_fields));
                $error_message .= '<br><br>' . __('To fix this issue:', 'certificate-generator') . '<ol>';
                $error_message .= '<li>' . __('Edit the certificate template (ID: ', 'certificate-generator') . esc_html($template_id) . ')</li>';
                $error_message .= '<li>' . __('Make sure all field positions (Field 1, Field 2, Field 3) have both X and Y coordinates defined', 'certificate-generator') . '</li>';
                $error_message .= '<li>' . __('Field 1 maps to student_name, Field 2 maps to school_name, and Field 3 maps to issue_date', 'certificate-generator') . '</li>';
                $error_message .= '</ol>';
                
                wp_send_json_error(array('message' => $error_message));
                wp_die();
            }
            
            // If no specific issue was found, return a general error
            wp_send_json_error(array('message' => __('Failed to generate certificate. Please check certificate template settings.', 'certificate-generator')));
            wp_die();
        } 
        else {
            wp_send_json_error(array('message' => __('Failed to send email. Please check the email settings and try again.', 'certificate-generator')));
            wp_die();
        }
    }
    wp_die();
}

// Add JavaScript to handle the email sending button
add_action('admin_footer', 'certificate_generator_admin_footer_js');
function certificate_generator_admin_footer_js() {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['students', 'teachers', 'schools'])) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Individual email sending functionality
        $('.send-certificate-email').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            var statusSpan = $('#email-status-' + postId);
            
            button.prop('disabled', true);
            statusSpan.html('<span class="spinner is-active" style="float: none; margin: 0 0 0 8px;"></span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'certificate_generator_send_single_email',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('certificate_generator_send_email'); ?>'
                },
                success: function(response) {
                    button.prop('disabled', false);
                    if (response.success) {
                        statusSpan.html('<div style="color: green; margin-left: 8px;">' + response.data.message + '</div>');
                    } else {
                        var errorContainer = $('<div style="color: red; margin-left: 8px; max-width: 500px;"></div>');
                        errorContainer.text(response.data.message);
                        statusSpan.empty().append(errorContainer);
                    }
                    
                    setTimeout(function() {
                        statusSpan.html('');
                    }, 5000);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    button.prop('disabled', false);
                    var errorMessage = '<?php echo esc_js(__('An error occurred. Please try again.', 'certificate-generator')); ?>';
                    if (textStatus === 'parsererror') {
                        errorMessage = '<?php echo esc_js(__('Server returned an invalid response. Please check server logs for errors.', 'certificate-generator')); ?>';
                        if (jqXHR.responseText) {
                            errorMessage += '\nRaw Response: ' + jqXHR.responseText.substring(0, 200);
                        }
                    } else if (jqXHR.status) {
                        errorMessage += ' (' + jqXHR.status + ' ' + errorThrown + ')';
                    }
                    
                    statusSpan.html('<span style="color: red; margin-left: 8px;">' + errorMessage + '</span>');
                    
                    setTimeout(function() {
                        statusSpan.html('');
                    }, 5000);
                }
            });
        });

        // Bulk email functionality
        var bulkEmailModal = $('#bulk-email-modal');
        var selectedPostIds = [];
        var currentPostType = '<?php echo get_current_screen()->post_type; ?>';

        // Intercept bulk action form submission
        $('#posts-filter').on('submit', function(e) {
            var bulkAction = $('#bulk-action-selector-top').val() || $('#bulk-action-selector-bottom').val();
            
            if (bulkAction === 'send_bulk_emails') {
                e.preventDefault();
                
                // Get selected post IDs
                selectedPostIds = [];
                $('input[name="post[]"]:checked').each(function() {
                    selectedPostIds.push($(this).val());
                });
                
                if (selectedPostIds.length === 0) {
                    alert('<?php echo esc_js(__('Please select at least one item.', 'certificate-generator')); ?>');
                    return false;
                }
                
                // Show modal
                bulkEmailModal.show();
                resetModal();
            }
        });

        // Modal functionality
        function resetModal() {
            $('.bulk-email-progress').hide();
            $('.bulk-email-results').hide();
            $('#bulk-email-confirm').prop('disabled', false).show();
            $('#bulk-email-cancel').text('<?php echo esc_js(__('Cancel', 'certificate-generator')); ?>');
            $('.progress-fill').css('width', '0%');
            $('.progress-text').text('<?php echo esc_js(__('Processing...', 'certificate-generator')); ?>');
        }

        // Close modal
        $('.bulk-email-modal-close, #bulk-email-cancel').on('click', function() {
            bulkEmailModal.hide();
        });

        // Close modal when clicking outside
        bulkEmailModal.on('click', function(e) {
            if (e.target === this) {
                bulkEmailModal.hide();
            }
        });

        // Confirm bulk email sending
        $('#bulk-email-confirm').on('click', function() {
            var skipAlreadySent = $('#skip-already-sent').is(':checked');
            
            // Show progress
            $('.bulk-email-progress').show();
            $('#bulk-email-confirm').prop('disabled', true);
            
            // Start progress animation
            $('.progress-fill').css('width', '10%');
            $('.progress-text').text('<?php echo esc_js(__('Initializing...', 'certificate-generator')); ?>');
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'certificate_generator_bulk_email_progress',
                    post_ids: selectedPostIds,
                    post_type: currentPostType,
                    skip_already_sent: skipAlreadySent,
                    nonce: '<?php echo wp_create_nonce('certificate_generator_bulk_email'); ?>'
                },
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    // Simulate progress for better UX
                    var progressInterval = setInterval(function() {
                        var currentWidth = parseInt($('.progress-fill').css('width'));
                        var containerWidth = $('.progress-bar').width();
                        var currentPercent = (currentWidth / containerWidth) * 100;
                        
                        if (currentPercent < 90) {
                            $('.progress-fill').css('width', (currentPercent + 5) + '%');
                        }
                    }, 500);
                    
                    xhr.progressInterval = progressInterval;
                    return xhr;
                },
                success: function(response, textStatus, xhr) {
                    // Clear progress interval
                    if (xhr.progressInterval) {
                        clearInterval(xhr.progressInterval);
                    }
                    
                    $('.progress-fill').css('width', '100%');
                    $('.progress-text').text('<?php echo esc_js(__('Complete!', 'certificate-generator')); ?>');
                    
                    if (response.success) {
                        showResults(response.data.message, response.data.results);
                    } else {
                        showResults(response.data.message, null, 'error');
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    // Clear progress interval
                    if (xhr.progressInterval) {
                        clearInterval(xhr.progressInterval);
                    }
                    
                    $('.progress-fill').css('width', '100%');
                    $('.progress-text').text('<?php echo esc_js(__('Error occurred', 'certificate-generator')); ?>');
                    
                    var errorMessage = '<?php echo esc_js(__('An error occurred while sending bulk emails.', 'certificate-generator')); ?>';
                    if (xhr.status) {
                        errorMessage += ' (' + xhr.status + ' ' + errorThrown + ')';
                    }
                    
                    showResults(errorMessage, null, 'error');
                }
            });
        });

        function showResults(message, results, type) {
            var resultsDiv = $('.bulk-email-results');
            var resultType = 'success';
            
            if (type === 'error') {
                resultType = 'error';
            } else if (results && results.failure > 0 && results.success === 0) {
                resultType = 'error';
            } else if (results && results.failure > 0) {
                resultType = 'warning';
            }
            
            resultsDiv.removeClass('success warning error').addClass(resultType);
            resultsDiv.html('<strong>' + message + '</strong>');
            
            if (results && results.errors && results.errors.length > 0) {
                var errorList = '<ul style="margin-top: 10px; margin-bottom: 0;">';
                results.errors.slice(0, 10).forEach(function(error) { // Show only first 10 errors
                    errorList += '<li>' + error + '</li>';
                });
                if (results.errors.length > 10) {
                    errorList += '<li><em>... and ' + (results.errors.length - 10) + ' more errors</em></li>';
                }
                errorList += '</ul>';
                resultsDiv.append(errorList);
            }
            
            resultsDiv.show();
            $('#bulk-email-confirm').hide();
            $('#bulk-email-cancel').text('<?php echo esc_js(__('Close', 'certificate-generator')); ?>');
            
            // Auto-reload page after 3 seconds for successful operations
            if (resultType === 'success' || resultType === 'warning') {
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            }
        }
    });
    </script>
    <?php
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

// Add bulk actions for sending emails
function certificate_generator_add_bulk_actions($bulk_actions) {
    $bulk_actions['send_bulk_emails'] = __('Send Bulk Emails', 'certificate-generator');
    return $bulk_actions;
}

// Handle bulk actions
function certificate_generator_handle_bulk_actions($redirect_to, $action, $post_ids) {
    if ($action !== 'send_bulk_emails') {
        return $redirect_to;
    }
    
    if (empty($post_ids)) {
        return $redirect_to;
    }
    
    // Get the post type from the current screen
    $screen = get_current_screen();
    $post_type = $screen->post_type;
    
    // Validate post type
    if (!in_array($post_type, ['students', 'teachers', 'schools'])) {
        $redirect_to = add_query_arg('bulk_email_error', 'invalid_post_type', $redirect_to);
        return $redirect_to;
    }
    
    // Process bulk emails
    $results = certificate_generator_send_bulk_emails($post_type, true, $post_ids);
    
    // Add results to redirect URL
    $redirect_to = add_query_arg([
        'bulk_emails_sent' => $results['success'],
        'bulk_emails_failed' => $results['failure'],
        'bulk_emails_skipped' => $results['skipped'],
        'bulk_emails_total' => $results['total']
    ], $redirect_to);
    
    return $redirect_to;
}

// Display bulk action notices
function certificate_generator_bulk_action_notices() {
    if (!empty($_REQUEST['bulk_emails_sent']) || !empty($_REQUEST['bulk_emails_failed']) || !empty($_REQUEST['bulk_emails_skipped'])) {
        $sent = isset($_REQUEST['bulk_emails_sent']) ? intval($_REQUEST['bulk_emails_sent']) : 0;
        $failed = isset($_REQUEST['bulk_emails_failed']) ? intval($_REQUEST['bulk_emails_failed']) : 0;
        $skipped = isset($_REQUEST['bulk_emails_skipped']) ? intval($_REQUEST['bulk_emails_skipped']) : 0;
        $total = isset($_REQUEST['bulk_emails_total']) ? intval($_REQUEST['bulk_emails_total']) : 0;
        
        $message = sprintf(
            __('Bulk email results: %d sent, %d failed, %d skipped out of %d total.', 'certificate-generator'),
            $sent, $failed, $skipped, $total
        );
        
        $notice_type = 'success';
        if ($failed > 0 && $sent === 0) {
            $notice_type = 'error';
        } elseif ($failed > 0) {
            $notice_type = 'warning';
        }
        
        echo '<div class="notice notice-' . esc_attr($notice_type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
    
    if (!empty($_REQUEST['bulk_email_error'])) {
        $error = sanitize_text_field($_REQUEST['bulk_email_error']);
        $message = __('Bulk email error: Invalid post type.', 'certificate-generator');
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }
}

// Add bulk actions to post types
add_filter('bulk_actions-edit-students', 'certificate_generator_add_bulk_actions');
add_filter('bulk_actions-edit-teachers', 'certificate_generator_add_bulk_actions');
add_filter('bulk_actions-edit-schools', 'certificate_generator_add_bulk_actions');

// Handle bulk actions for post types
add_filter('handle_bulk_actions-edit-students', 'certificate_generator_handle_bulk_actions', 10, 3);
add_filter('handle_bulk_actions-edit-teachers', 'certificate_generator_handle_bulk_actions', 10, 3);
add_filter('handle_bulk_actions-edit-schools', 'certificate_generator_handle_bulk_actions', 10, 3);

// Add admin notices
add_action('admin_notices', 'certificate_generator_bulk_action_notices');

// AJAX handler for bulk email progress
add_action('wp_ajax_certificate_generator_bulk_email_progress', 'certificate_generator_bulk_email_progress_ajax');
function certificate_generator_bulk_email_progress_ajax() {
    // Check nonce for security
    check_ajax_referer('certificate_generator_bulk_email', 'nonce');
    
    // Check if user has permission
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'certificate-generator')));
        wp_die();
    }
    
    // Get parameters from request
    $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    $skip_already_sent = isset($_POST['skip_already_sent']) ? (bool)$_POST['skip_already_sent'] : true;
    
    if (empty($post_ids) || empty($post_type)) {
        wp_send_json_error(array('message' => __('Invalid parameters.', 'certificate-generator')));
        wp_die();
    }
    
    // Validate post type
    if (!in_array($post_type, ['students', 'teachers', 'schools'])) {
        wp_send_json_error(array('message' => __('Invalid post type.', 'certificate-generator')));
        wp_die();
    }
    
    // Process bulk emails
    $results = certificate_generator_send_bulk_emails($post_type, $skip_already_sent, $post_ids);
    
    // Return results
    wp_send_json_success([
        'message' => sprintf(
            __('Bulk email completed: %d sent, %d failed, %d skipped out of %d total.', 'certificate-generator'),
            $results['success'], $results['failure'], $results['skipped'], $results['total']
        ),
        'results' => $results
    ]);
    
    wp_die();
}

// Add enhanced bulk email modal and JavaScript
add_action('admin_footer', 'certificate_generator_bulk_email_modal');
function certificate_generator_bulk_email_modal() {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['students', 'teachers', 'schools'])) {
        return;
    }
    ?>
    <!-- Bulk Email Modal -->
    <div id="bulk-email-modal" style="display: none;">
        <div class="bulk-email-modal-content">
            <div class="bulk-email-modal-header">
                <h3><?php _e('Send Bulk Emails', 'certificate-generator'); ?></h3>
                <span class="bulk-email-modal-close">&times;</span>
            </div>
            <div class="bulk-email-modal-body">
                <p><?php _e('Send certificate emails to selected entries?', 'certificate-generator'); ?></p>
                <div class="bulk-email-options">
                    <label>
                        <input type="checkbox" id="skip-already-sent" checked>
                        <?php _e('Skip entries that have already been emailed', 'certificate-generator'); ?>
                    </label>
                </div>
                <div class="bulk-email-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-text"><?php _e('Processing...', 'certificate-generator'); ?></div>
                </div>
                <div class="bulk-email-results" style="display: none;"></div>
            </div>
            <div class="bulk-email-modal-footer">
                <button type="button" class="button button-secondary" id="bulk-email-cancel"><?php _e('Cancel', 'certificate-generator'); ?></button>
                <button type="button" class="button button-primary" id="bulk-email-confirm"><?php _e('Send Emails', 'certificate-generator'); ?></button>
            </div>
        </div>
    </div>

    <style>
    #bulk-email-modal {
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .bulk-email-modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 0;
        border: 1px solid #888;
        width: 500px;
        max-width: 90%;
        border-radius: 4px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .bulk-email-modal-header {
        background: #f1f1f1;
        padding: 15px 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .bulk-email-modal-header h3 {
        margin: 0;
        font-size: 16px;
    }
    
    .bulk-email-modal-close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }
    
    .bulk-email-modal-close:hover {
        color: #000;
    }
    
    .bulk-email-modal-body {
        padding: 20px;
    }
    
    .bulk-email-options {
        margin: 15px 0;
    }
    
    .bulk-email-options label {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .bulk-email-progress {
        margin: 15px 0;
    }
    
    .progress-bar {
        width: 100%;
        height: 20px;
        background-color: #f0f0f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    
    .progress-fill {
        height: 100%;
        background-color: #0073aa;
        width: 0%;
        transition: width 0.3s ease;
        border-radius: 10px;
    }
    
    .progress-text {
        text-align: center;
        font-size: 14px;
        color: #666;
    }
    
    .bulk-email-results {
        margin: 15px 0;
        padding: 10px;
        border-radius: 4px;
    }
    
    .bulk-email-results.success {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    
    .bulk-email-results.warning {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }
    
    .bulk-email-results.error {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    
    .bulk-email-modal-footer {
        background: #f1f1f1;
        padding: 15px 20px;
        border-top: 1px solid #ddd;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    </style>
    <?php
}
