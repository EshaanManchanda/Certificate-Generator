<?php
/**
 * Plugin Admin Settings
 *
 * @package Certificate Generator
 */

// Add admin menu item
add_action('admin_menu', 'certificate_generator_add_admin_menu');
function certificate_generator_add_admin_menu() {
    add_options_page(
        __('Certificate Generator Settings', 'certificate-generator'),
        __('Certificate Generator', 'certificate-generator'),
        'manage_options',
        'certificate_generator_settings',
        'certificate_generator_settings_page'
    );
}

// Initialize settings
add_action('admin_init', 'certificate_generator_settings_init');
// AJAX handler for saving API key
add_action('wp_ajax_certificate_generator_save_api_key', 'certificate_generator_save_api_key_ajax');
// AJAX handler for deleting API key
add_action('wp_ajax_certificate_generator_delete_api_key', 'certificate_generator_delete_api_key_ajax');

function certificate_generator_save_api_key_ajax() {
    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('API Key AJAX handler triggered');
        error_log('POST data: ' . print_r($_POST, true));
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_api_key_nonce')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('API Key AJAX: Invalid nonce');
        }
        wp_send_json_error('Invalid nonce');
    }

    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('API Key AJAX: Insufficient permissions');
        }
        wp_send_json_error('Insufficient permissions');
    }

    // Get and sanitize the API key
    $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    if (empty($api_key)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('API Key AJAX: Empty API key');
        }
        wp_send_json_error('API key is required');
    }

    // Save the API key and enable API access
    $key_updated = update_option('certificate_generator_api_key', $api_key);
    $access_updated = update_option('certificate_generator_api_key_enabled', true);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('API Key AJAX: Key updated: ' . ($key_updated ? 'true' : 'false'));
        error_log('API Key AJAX: Access updated: ' . ($access_updated ? 'true' : 'false'));
    }

    if ($key_updated && $access_updated) {
        wp_send_json_success('API key saved and API access enabled successfully');
    } else {
        wp_send_json_error('Failed to save API key or enable API access');
    }
}

// AJAX handler for deleting API key
function certificate_generator_delete_api_key_ajax() {
    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Delete API Key AJAX handler triggered');
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'delete_api_key_nonce')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Delete API Key AJAX: Invalid nonce');
        }
        wp_send_json_error('Invalid nonce');
    }

    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Delete API Key AJAX: Insufficient permissions');
        }
        wp_send_json_error('Insufficient permissions');
    }

    // Delete the API key and disable API access
    $key_deleted = delete_option('certificate_generator_api_key');
    $access_disabled = update_option('certificate_generator_api_key_enabled', false);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Delete API Key AJAX: Key deleted: ' . ($key_deleted ? 'true' : 'false'));
        error_log('Delete API Key AJAX: Access disabled: ' . ($access_disabled ? 'true' : 'false'));
    }

    if ($key_deleted || $access_disabled) {
        wp_send_json_success('API key deleted and API access disabled successfully');
    } else {
        wp_send_json_error('Failed to delete API key or disable API access');
    }
}

// Initialize settings
function certificate_generator_settings_init() {
    register_setting('certificate_generator_settings', 'certificate_generator_settings_email', [
        'sanitize_callback' => 'certificate_generator_sanitize_settings',
    ]);
    register_setting('certificate_generator_settings', 'certificate_generator_api_key', [
        'sanitize_callback' => 'sanitize_text_field',
        'type' => 'string',
        'show_in_rest' => false,
    ]);
    register_setting('certificate_generator_settings', 'certificate_generator_api_key_enabled', [
        'sanitize_callback' => 'absint',
        'type' => 'boolean',
        'show_in_rest' => false,
    ]);

    // Rate limit settings
    register_setting('certificate_generator_settings', 'certificate_generator_rate_limits', [
        'sanitize_callback' => 'certificate_generator_sanitize_rate_limits',
        'type' => 'array',
    ]);

    // General Settings Section
    add_settings_section(
        'certificate_generator_settings_section',
        __('General Settings', 'certificate-generator'),
        'certificate_generator_settings_section_callback',
        'certificate_generator_settings'
    );

    add_settings_field(
        'certificate_generator_email',
        __('Contact Email', 'certificate-generator'),
        'certificate_generator_email_render',
        'certificate_generator_settings',
        'certificate_generator_settings_section'
    );

    add_settings_field(
        'certificate_generator_card_styles',
        __('Card Styling', 'certificate-generator'),
        'certificate_generator_card_styles_render',
        'certificate_generator_settings',
        'certificate_generator_settings_section'
    );

    // Add Email Template Settings Section
    add_settings_section(
        'certificate_generator_email_templates_section',
        __('Email Templates', 'certificate-generator'),
        'certificate_generator_email_templates_section_callback',
        'certificate_generator_settings'
    );

    // Add auto-send settings
    add_settings_field(
        'certificate_generator_auto_send_enabled',
        __('Auto-Send Emails', 'certificate-generator'),
        'certificate_generator_auto_send_render',
        'certificate_generator_settings_email',
        'certificate_generator_email_templates_section'
    );

    // Add email logo setting
    add_settings_field(
        'certificate_generator_email_logo',
        __('Email Logo', 'certificate-generator'),
        'certificate_generator_email_logo_render',
        'certificate_generator_settings_email',
        'certificate_generator_email_templates_section'
    );

    // Add email template settings for each post type
    $post_types = ['students', 'teachers', 'schools'];

    foreach ($post_types as $post_type) {
        add_settings_field(
            'certificate_generator_' . $post_type . '_email_template',
            __(ucfirst($post_type) . ' Email Template', 'certificate-generator'),
            'certificate_generator_email_template_render',
            'certificate_generator_settings',
            'certificate_generator_email_templates_section',
            ['post_type' => $post_type]
        );
    }

    // Add API Configuration section
    add_settings_section(
        'certificate_generator_api_section',
        'API Configuration',
        'certificate_generator_api_section_callback',
        'certificate_generator_settings'
    );

    // API Key field
    add_settings_field(
        'certificate_generator_api_key',
        'API Key',
        'certificate_generator_api_key_render',
        'certificate_generator_settings',
        'certificate_generator_api_section'
    );

    // API Key enabled field
    add_settings_field(
        'certificate_generator_api_key_enabled',
        'Enable API Access',
        'certificate_generator_api_key_enabled_render',
        'certificate_generator_settings',
        'certificate_generator_api_section'
    );

    // Add Rate Limit Settings Section
    add_settings_section(
        'certificate_generator_rate_limit_section',
        __('Email Rate Limits', 'certificate-generator'),
        'certificate_generator_rate_limit_section_callback',
        'certificate_generator_settings'
    );

    add_settings_field(
        'certificate_generator_rate_limits',
        __('Rate Limit Configuration', 'certificate-generator'),
        'certificate_generator_rate_limits_render',
        'certificate_generator_settings',
        'certificate_generator_rate_limit_section'
    );

    // Add SMTP Settings Section
    add_settings_section(
        'certificate_generator_smtp_section',
        __('SMTP Settings', 'certificate-generator'),
        'certificate_generator_smtp_section_callback',
        'certificate_generator_settings'
    );

    // Add SMTP fields
    add_settings_field(
        'certificate_generator_smtp_settings',
        __('SMTP Configuration', 'certificate-generator'),
        'certificate_generator_smtp_settings_render',
        'certificate_generator_settings',
        'certificate_generator_smtp_section'
    );
}

function cg_encrypt_smtp_password( $plaintext ) {
    if ( empty( $plaintext ) ) return '';
    $key    = substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 32 );
    $iv_len = openssl_cipher_iv_length( 'AES-256-CBC' );
    $iv     = openssl_random_pseudo_bytes( $iv_len );
    $cipher = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, 0, $iv );
    return base64_encode( $iv . $cipher );
}

function cg_decrypt_smtp_password( $stored ) {
    if ( empty( $stored ) ) return '';
    $key     = substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 32 );
    $iv_len  = openssl_cipher_iv_length( 'AES-256-CBC' );
    $decoded = base64_decode( $stored );
    $iv      = substr( $decoded, 0, $iv_len );
    $cipher  = substr( $decoded, $iv_len );
    $plain   = openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
    return $plain === false ? '' : $plain;
}

// Sanitize settings input
function certificate_generator_sanitize_settings($input) {
    if ( ! is_array( $input ) ) {
        $input = [];
    }

    // Start from existing saved data — prevents data loss when only one tab is submitted
    $existing = get_option('certificate_generator_settings_email', []);
    $output   = is_array($existing) ? $existing : [];

    // Detect which tab's form was submitted
    $is_general_tab   = array_key_exists('email', $input)      || array_key_exists('card_bg', $input);
    $is_templates_tab = array_key_exists('email_logo', $input) || array_key_exists('auto_send_enabled', $input)
                        || array_key_exists('smtp_host', $input);

    if ($is_general_tab) {
        // Validate email
        if (isset($input['email']) && filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $output['email'] = sanitize_email($input['email']);
        } elseif (array_key_exists('email', $input)) {
            $output['email'] = ''; // allow clearing
        }

        // Validate colors
        foreach (['card_bg', 'title_color', 'text_color', 'btn_start', 'btn_end'] as $color_field) {
            if (isset($input[$color_field])) {
                $output[$color_field] = sanitize_hex_color($input[$color_field]);
            }
        }

        // Validate numeric values
        foreach (['hover_effect', 'border_radius'] as $numeric_field) {
            if (isset($input[$numeric_field])) {
                $output[$numeric_field] = absint($input[$numeric_field]);
            }
        }
    }

    if ($is_templates_tab) {
        // Checkbox: always set so unchecking saves false
        $output['auto_send_enabled'] = !empty($input['auto_send_enabled']);

        // Email logo URL
        if (isset($input['email_logo'])) {
            $output['email_logo'] = esc_url_raw($input['email_logo']);
        }

        // Email template fields for each post type
        $post_types = ['students', 'teachers', 'schools'];
        foreach ($post_types as $post_type) {
            $prefix = $post_type . '_email_';

            foreach (['subject', 'title', 'reply_to', 'cc', 'bcc'] as $field) {
                $key = $prefix . $field;
                if (isset($input[$key])) {
                    $output[$key] = sanitize_text_field($input[$key]);
                }
            }

            $message_key = $prefix . 'message';
            if (isset($input[$message_key])) {
                $output[$message_key] = wp_kses_post($input[$message_key]);
            }

            // Checkbox: always set for this tab
            $checkbox_key = $prefix . 'attach_certificate';
            $output[$checkbox_key] = isset($input[$checkbox_key]) ? '1' : '0';
        }

        // SMTP settings
        foreach (['host', 'port', 'username', 'password', 'encryption'] as $smtp_field) {
            $key = 'smtp_' . $smtp_field;
            if (isset($input[$key])) {
                if ( $smtp_field === 'password' ) {
                    $plain = sanitize_text_field( wp_unslash( $input[$key] ) );
                    if ( $plain !== '' ) {
                        $output[$key] = cg_encrypt_smtp_password( $plain );
                    }
                    // else: leave existing encrypted value untouched (already in $output from $existing)
                } else {
                    $output[$key] = sanitize_text_field($input[$key]);
                }
            }
        }
    }

    return $output;
}

// General settings section description
// function certificate_generator_settings_section_callback() {
//     echo '<p>' . esc_html__('Configure general settings for the Certificate Generator plugin.', 'certificate-generator') . '</p>';
// }

// Email templates section description
function certificate_generator_email_templates_section_callback() {
    echo '<p>' . esc_html__('Configure email templates and auto-send settings for sending certificates to recipients.', 'certificate-generator') . '</p>';
}

// Auto-send setting render function
function certificate_generator_auto_send_render() {
    $options = get_option('certificate_generator_settings_email', []);
    $auto_send_enabled = isset($options['auto_send_enabled']) ? $options['auto_send_enabled'] : false;

    echo '<label>';
    echo '<input type="checkbox" name="certificate_generator_settings_email[auto_send_enabled]" value="1" ' . checked(1, $auto_send_enabled, false) . ' />';
    echo ' ' . __('Automatically send certificate emails when certificates are found/generated during search', 'certificate-generator');
    echo '</label>';
    echo '<p class="description">' . __('When enabled, emails will be sent automatically when users search for and find their certificates, but only if they haven\'t been sent before.', 'certificate-generator') . '</p>';
}

// Email logo setting render function
function certificate_generator_email_logo_render() {
    $options = get_option('certificate_generator_settings_email', []);
    $email_logo = isset($options['email_logo']) ? $options['email_logo'] : '';

    echo '<input type="url" name="certificate_generator_settings_email[email_logo]" value="' . esc_attr($email_logo) . '" class="regular-text" placeholder="https://example.com/logo.png" />';
    echo '<p class="description">' . __('Enter the URL of the logo to include in email headers. Leave empty to use no logo.', 'certificate-generator') . '</p>';

    if (!empty($email_logo)) {
        echo '<div style="margin-top: 10px;">';
        echo '<img src="' . esc_url($email_logo) . '" alt="Email Logo Preview" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px;" />';
        echo '</div>';
    }
}

// SMTP section description
function certificate_generator_smtp_section_callback() {
    echo '<p>' . esc_html__('Configure SMTP settings for sending emails. Leave blank to use the default WordPress mail function.', 'certificate-generator') . '</p>';
}

// Render email template fields
function certificate_generator_email_template_render($args) {
    $post_type = $args['post_type'];
    $options = get_option('certificate_generator_settings_email');
    $prefix = $post_type . '_email_';

    $fields = [
        'subject' => [
            'label' => __('Email Subject', 'certificate-generator'),
            'type' => 'text',
            'default' => sprintf(__('Your %s Certificate', 'certificate-generator'), ucfirst(rtrim($post_type, 's'))),
            'desc' => __('Subject line for the email', 'certificate-generator')
        ],
        'title' => [
            'label' => __('Email Title', 'certificate-generator'),
            'type' => 'text',
            'default' => sprintf(__('Your %s Certificate is Ready', 'certificate-generator'), ucfirst(rtrim($post_type, 's'))),
            'desc' => __('Title displayed at the top of the email', 'certificate-generator')
        ],
        'message' => [
            'label' => __('Email Message', 'certificate-generator'),
            'type' => 'textarea',
            'default' => sprintf(__('Dear {name},\n\nPlease find attached your %s certificate.\n\nThank you!', 'certificate-generator'), rtrim($post_type, 's')),
            'desc' => __('Message body. Available placeholders: {name} (recipient name), {certificate_title} (certificate type), {result_link} (result page URL), {zip_link} (ZIP download URL if available), {certificate_count} (number of certificates), {email} (recipient email)', 'certificate-generator')
        ],
        'attach_certificate' => [
            'label' => __('Attach Certificate', 'certificate-generator'),
            'type' => 'checkbox',
            'default' => '1',
            'desc' => __('Attach the generated certificate PDF to the email', 'certificate-generator')
        ],
        'reply_to' => [
            'label' => __('Reply-To Email', 'certificate-generator'),
            'type' => 'email',
            'default' => '',
            'desc' => __('Email address for replies (leave blank to use Contact Email)', 'certificate-generator')
        ],
        'cc' => [
            'label' => __('CC', 'certificate-generator'),
            'type' => 'text',
            'default' => '',
            'desc' => __('Carbon copy recipients (comma-separated emails)', 'certificate-generator')
        ],
        'bcc' => [
            'label' => __('BCC', 'certificate-generator'),
            'type' => 'text',
            'default' => '',
            'desc' => __('Blind carbon copy recipients (comma-separated emails)', 'certificate-generator')
        ],
    ];

    echo '<div class="email-template-section" style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; margin-bottom: 20px;">';
    echo '<h3>' . esc_html(ucfirst($post_type)) . ' ' . esc_html__('Email Template', 'certificate-generator') . '</h3>';

    foreach ($fields as $field => $config) {
        $key = $prefix . $field;
        $value = isset($options[$key]) ? $options[$key] : $config['default'];

        echo '<div class="email-field" style="margin-bottom: 15px;">';
        echo '<label style="display: block; margin-bottom: 5px;"><strong>' . esc_html($config['label']) . '</strong></label>';

        if ($config['type'] === 'textarea') {
            // Use WordPress editor for HTML support
            wp_editor($value, 'certificate_generator_settings_email_' . $key, array(
                'textarea_name' => 'certificate_generator_settings_email[' . esc_attr($key) . ']',
                'media_buttons' => false,
                'textarea_rows' => 4,
                'teeny' => true,
                'tinymce' => array(
                    'toolbar1' => 'bold,italic,underline,link,unlink,forecolor,backcolor,removeformat',
                    'toolbar2' => ''
                )
            ));
        } elseif ($config['type'] === 'checkbox') {
            $checked = !empty($value) ? 'checked' : '';
            echo '<input type="checkbox" name="certificate_generator_settings_email[' . esc_attr($key) . ']" value="1" ' . $checked . '>';
        } else {
            echo '<input type="' . esc_attr($config['type']) . '" name="certificate_generator_settings_email[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" style="width: 100%;">';
        }

        echo '<p class="description" style="margin-top: 5px; color: #666; font-style: italic;">' . esc_html($config['desc']) . '</p>';
        echo '</div>';
    }

    echo '</div>';
}

// Render SMTP settings fields
function certificate_generator_smtp_settings_render() {
    $options = get_option('certificate_generator_settings_email');

    // Get email status information
    $email_status = certificate_generator_get_email_status();
    $wp_mail_smtp_active = $email_status['smtp_configured'];

    // Display current email delivery status
    echo '<div class="smtp-settings" style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">';

    // Email delivery status section
    echo '<div class="email-status-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">';
    echo '<h4 style="margin-top: 0;">' . esc_html__('Current Email Delivery Method', 'certificate-generator') . '</h4>';
    echo '<table class="form-table" style="margin: 0;">';
    echo '<tr><td style="padding: 5px 0;"><strong>' . esc_html__('Method:', 'certificate-generator') . '</strong></td><td style="padding: 5px 0;">' . esc_html($email_status['method']) . '</td></tr>';
    echo '<tr><td style="padding: 5px 0;"><strong>' . esc_html__('From Email:', 'certificate-generator') . '</strong></td><td style="padding: 5px 0;">' . esc_html($email_status['from_email']) . '</td></tr>';
    echo '<tr><td style="padding: 5px 0;"><strong>' . esc_html__('From Name:', 'certificate-generator') . '</strong></td><td style="padding: 5px 0;">' . esc_html($email_status['from_name']) . '</td></tr>';
    echo '</table>';
    echo '</div>';

    if ($wp_mail_smtp_active) {
        echo '<div class="notice notice-success inline" style="margin: 0 0 15px; padding: 10px;">';
        echo '<p><strong>' . esc_html__('WP Mail SMTP Active!', 'certificate-generator') . '</strong></p>';
        echo '<p>' . esc_html__('WP Mail SMTP plugin is active and configured. All emails will be sent through your SMTP settings for better deliverability.', 'certificate-generator') . '</p>';
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=wp-mail-smtp')) . '" class="button">' . esc_html__('Configure WP Mail SMTP', 'certificate-generator') . '</a></p>';
        echo '</div>';
    } else {
        $php_mail_disabled = !function_exists('mail');
        if ($php_mail_disabled) {
            echo '<div class="notice notice-error inline" style="margin: 0 0 15px; padding: 10px;">';
            echo '<p><strong>&#9888; ' . esc_html__('Email Sending Will FAIL — PHP mail() Disabled', 'certificate-generator') . '</strong></p>';
            echo '<p>' . esc_html__('Your server has PHP\'s mail() function disabled. Emails cannot be sent until you configure WP Mail SMTP with a real SMTP provider (e.g. Gmail, Mailgun, SendGrid).', 'certificate-generator') . '</p>';
            echo '<p><a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" class="button button-primary">' . esc_html__('Get WP Mail SMTP', 'certificate-generator') . '</a>';
            if ( is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') || is_plugin_active('wp-mail-smtp-pro/wp_mail_smtp.php') ) {
                echo ' &nbsp; <a href="' . esc_url(admin_url('admin.php?page=wp-mail-smtp')) . '" class="button">' . esc_html__('Configure WP Mail SMTP', 'certificate-generator') . '</a>';
            }
            echo '</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-info inline" style="margin: 0 0 15px; padding: 10px;">';
            echo '<p><strong>' . esc_html__('Fallback Email System Active', 'certificate-generator') . '</strong></p>';
            echo '<p>' . esc_html__('Emails will be sent using WordPress default method (PHP mail) with a fallback "noreply@yourdomain.com" sender address, similar to how Forminator works. This works on most shared hosting providers.', 'certificate-generator') . '</p>';
            echo '<p><strong>' . esc_html__('For better deliverability:', 'certificate-generator') . '</strong> ' . esc_html__('Install WP Mail SMTP plugin to use proper SMTP configuration.', 'certificate-generator') . '</p>';
            echo '<p><a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" class="button button-primary">' . esc_html__('Get WP Mail SMTP (Recommended)', 'certificate-generator') . '</a></p>';
            echo '</div>';
        }
    }

    echo '<p style="color: #666; font-style: italic;">' . esc_html__('Note: The SMTP settings below are deprecated and will be removed in a future version. Please use WP Mail SMTP plugin for SMTP configuration.', 'certificate-generator') . '</p>';

    $fields = [
        'host' => [
            'label' => __('SMTP Host', 'certificate-generator'),
            'type' => 'text',
            'default' => 'smtp.gmail.com',
            'desc' => __('SMTP server address (e.g., smtp.gmail.com)', 'certificate-generator')
        ],
        'port' => [
            'label' => __('SMTP Port', 'certificate-generator'),
            'type' => 'number',
            'default' => '587',
            'desc' => __('SMTP port (usually 587 for TLS, 465 for SSL)', 'certificate-generator')
        ],
        'encryption' => [
            'label' => __('Encryption', 'certificate-generator'),
            'type' => 'select',
            'options' => [
                '' => __('None', 'certificate-generator'),
                'ssl' => __('SSL', 'certificate-generator'),
                'tls' => __('TLS', 'certificate-generator'),
            ],
            'default' => 'tls',
            'desc' => __('Type of encryption to use', 'certificate-generator')
        ],
        'username' => [
            'label' => __('SMTP Username', 'certificate-generator'),
            'type' => 'text',
            'default' => '',
            'desc' => __('SMTP account username', 'certificate-generator')
        ],
        'password' => [
            'label' => __('SMTP Password', 'certificate-generator'),
            'type' => 'password',
            'default' => '',
            'desc' => __('SMTP account password', 'certificate-generator')
        ],
    ];

    foreach ($fields as $field => $config) {
        $key = 'smtp_' . $field;
        $value = isset($options[$key]) ? $options[$key] : $config['default'];
        if ( $field === 'password' ) {
            $value = cg_decrypt_smtp_password( $value );
        }

        echo '<div class="smtp-field" style="margin-bottom: 15px;">';
        echo '<label style="display: block; margin-bottom: 5px;"><strong>' . esc_html($config['label']) . '</strong></label>';

        if ($config['type'] === 'select') {
            echo '<select name="certificate_generator_settings_email[' . esc_attr($key) . ']" style="width: 100%;">';
            foreach ($config['options'] as $option_value => $option_label) {
                echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="' . esc_attr($config['type']) . '" name="certificate_generator_settings_email[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" style="width: 100%;">';
        }

        echo '<p class="description" style="margin-top: 5px; color: #666; font-style: italic;">' . esc_html($config['desc']) . '</p>';
        echo '</div>';
    }

    echo '<p>' . esc_html__('Note: Password is stored encrypted in the database.', 'certificate-generator') . '</p>';
    echo '</div>';
}

// Render email input field
function certificate_generator_email_render() {
    $options = get_option('certificate_generator_settings_email');
    ?>
    <input type="email" name="certificate_generator_settings_email[email]"
           value="<?php echo esc_attr($options['email'] ?? ''); ?>"
           required>
    <?php
}

// Get contact email from settings
function certificate_generator_get_contact_email() {
    $options = get_option('certificate_generator_settings_email');
    return $options['email'] ?? '';
}

// Render card styling fields
function certificate_generator_card_styles_render() {
    $options = get_option('certificate_generator_settings_email');
    $fields = [
        'card_bg' => [
            'label' => __('Card Background Color', 'certificate-generator'),
            'default' => '#f9f9f9',
            'desc' => __('Background color for the certificate results container', 'certificate-generator')
        ],
        'title_color' => [
            'label' => __('Title Color', 'certificate-generator'),
            'default' => '#2c3e50',
            'desc' => __('Color for certificate titles and headings', 'certificate-generator')
        ],
        'text_color' => [
            'label' => __('Text Color', 'certificate-generator'),
            'default' => '#7f8c8d',
            'desc' => __('Color for regular text in certificates', 'certificate-generator')
        ],
        'btn_start' => [
            'label' => __('Button Gradient Start', 'certificate-generator'),
            'default' => '#3498db',
            'desc' => __('Starting color for button gradients', 'certificate-generator')
        ],
        'btn_end' => [
            'label' => __('Button Gradient End', 'certificate-generator'),
            'default' => '#2980b9',
            'desc' => __('Ending color for button gradients', 'certificate-generator')
        ],
        'hover_effect' => [
            'label' => __('Hover Effect Intensity', 'certificate-generator'),
            'default' => '5',
            'desc' => __('Card lift effect on hover (pixels)', 'certificate-generator'),
            'type' => 'range'
        ],
        'border_radius' => [
            'label' => __('Border Radius', 'certificate-generator'),
            'default' => '12',
            'desc' => __('Rounded corners for cards and buttons (pixels)', 'certificate-generator'),
            'type' => 'range'
        ],
    ];

    echo '<div class="certificate-styling-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">';

    foreach ($fields as $field => $config) {
        $type = $config['type'] ?? 'color';
        $default = $config['default'];
        $value = $options[$field] ?? $default;

        echo '<div class="style-option" style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">';
        echo '<label style="display: block; margin-bottom: 8px;"><strong>' . $config['label'] . '</strong></label>';

        if ($type === 'color') {
            echo '<div style="display: flex; align-items: center;">';
            echo '<input type="color" id="' . $field . '" name="certificate_generator_settings_email[' . $field . ']" value="' . esc_attr($value) . '" style="margin-right: 10px;">';
            echo '<input type="text" value="' . esc_attr($value) . '" id="' . $field . '_text" style="width: 80px;" readonly>';
            echo '</div>';
        } else if ($type === 'range') {
            echo '<div style="display: flex; align-items: center;">';
            echo '<input type="range" id="' . $field . '" name="certificate_generator_settings_email[' . $field . ']" min="0" max="30" value="' . esc_attr($value) . '" style="flex-grow: 1; margin-right: 10px;">';
            echo '<input type="number" value="' . esc_attr($value) . '" id="' . $field . '_number" style="width: 60px;" min="0" max="30">';
            echo '</div>';
        }

        echo '<p class="description" style="margin-top: 8px; color: #666; font-style: italic;">' . $config['desc'] . '</p>';
        echo '</div>';
    }

    echo '</div>';

    // Add JavaScript to sync color inputs with text fields
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Sync color inputs with text fields
        $('input[type="color"]').on('input', function() {
            $('#' + $(this).attr('id') + '_text').val($(this).val());
        });

        // Sync range inputs with number fields
        $('input[type="range"]').on('input', function() {
            $('#' + $(this).attr('id') + '_number').val($(this).val());
        });

        $('input[type="number"]').on('input', function() {
            const id = $(this).attr('id').replace('_number', '');
            $('#' + id).val($(this).val());
        });
    });
    </script>
    <?php
}

// Settings section description
function certificate_generator_settings_section_callback() {
    echo '<p>' . esc_html__('Configure the email address and visual styling options used in certificate search results.', 'certificate-generator') . '</p>';
}

// Rate limit section description
function certificate_generator_rate_limit_section_callback() {
    echo '<p>' . esc_html__('Configure how many emails can be sent per hour and per minute. These limits prevent hitting your SMTP provider\'s rate limits.', 'certificate-generator') . '</p>';
}

// Render rate limit fields
function certificate_generator_rate_limits_render() {
    $config = certificate_generator_get_rate_limit_config();
    ?>
    <table class="form-table" style="margin:0;">
        <tr>
            <th style="padding:4px 10px 4px 0;font-weight:normal;"><?php esc_html_e('Emails per hour', 'certificate-generator'); ?></th>
            <td style="padding:4px 0;">
                <input type="number" name="certificate_generator_rate_limits[emails_per_hour]"
                       value="<?php echo esc_attr($config['emails_per_hour']); ?>"
                       min="10" max="300" step="1" style="width:80px;">
                <span class="description"><?php esc_html_e('Recommended: 80 (Hostinger safe limit)', 'certificate-generator'); ?></span>
            </td>
        </tr>
        <tr>
            <th style="padding:4px 10px 4px 0;font-weight:normal;"><?php esc_html_e('Emails per minute', 'certificate-generator'); ?></th>
            <td style="padding:4px 0;">
                <input type="number" name="certificate_generator_rate_limits[emails_per_minute]"
                       value="<?php echo esc_attr($config['emails_per_minute']); ?>"
                       min="1" max="50" step="1" style="width:80px;">
                <span class="description"><?php esc_html_e('Burst limit to prevent flooding', 'certificate-generator'); ?></span>
            </td>
        </tr>
        <tr>
            <th style="padding:4px 10px 4px 0;font-weight:normal;"><?php esc_html_e('Batch size', 'certificate-generator'); ?></th>
            <td style="padding:4px 0;">
                <input type="number" name="certificate_generator_rate_limits[batch_size]"
                       value="<?php echo esc_attr($config['batch_size']); ?>"
                       min="1" max="50" step="1" style="width:80px;">
                <span class="description"><?php esc_html_e('Number of emails per WP-Cron batch', 'certificate-generator'); ?></span>
            </td>
        </tr>
        <tr>
            <th style="padding:4px 10px 4px 0;font-weight:normal;"><?php esc_html_e('Batch delay (seconds)', 'certificate-generator'); ?></th>
            <td style="padding:4px 0;">
                <input type="number" name="certificate_generator_rate_limits[batch_delay]"
                       value="<?php echo esc_attr($config['batch_delay']); ?>"
                       min="10" max="3600" step="1" style="width:80px;">
                <span class="description"><?php esc_html_e('Seconds to wait between batches', 'certificate-generator'); ?></span>
            </td>
        </tr>
        <tr>
            <th style="padding:4px 10px 4px 0;font-weight:normal;"><?php esc_html_e('Rate limiting enabled', 'certificate-generator'); ?></th>
            <td style="padding:4px 0;">
                <label>
                    <input type="checkbox" name="certificate_generator_rate_limits[enabled]" value="1"
                           <?php checked($config['enabled'], true); ?>>
                    <?php esc_html_e('Enable rate limiting', 'certificate-generator'); ?>
                </label>
            </td>
        </tr>
    </table>
    <?php
}

// Sanitize rate limit settings
function certificate_generator_sanitize_rate_limits($input) {
    if (!is_array($input)) {
        return [];
    }
    return [
        'emails_per_hour'  => max(10, min(300, (int)($input['emails_per_hour'] ?? 80))),
        'emails_per_minute' => max(1, min(50, (int)($input['emails_per_minute'] ?? 10))),
        'batch_size'       => max(1, min(50, (int)($input['batch_size'] ?? 10))),
        'batch_delay'      => max(10, min(3600, (int)($input['batch_delay'] ?? 480))),
        'enabled'          => !empty($input['enabled']),
    ];
}

// AJAX handler for clearing cache
add_action('wp_ajax_certificate_generator_clear_cache', 'certificate_generator_clear_cache_ajax');
function certificate_generator_clear_cache_ajax() {
    // if (!current_user_can('manage_options')) {
    //     wp_send_json_error(__('Unauthorized user', 'certificate-generator'));
    // }

    check_ajax_referer('certificate_generator_clear_cache', 'nonce');

    $result = certificate_generator_clear_cache();

    if ($result) {
        wp_send_json_success(__('Cache and certificate files cleared successfully!', 'certificate-generator'));
    } else {
        wp_send_json_error(__('Error clearing cache.', 'certificate-generator'));
    }
}

// Function to clear cache and certificate files
function certificate_generator_clear_cache() {
    global $wpdb;

    try {
        // ── 1. Delete all plugin transients ───────────────────────────────────
        $transient_patterns = [
            '_transient_cert_search_%',
            '_transient_timeout_cert_search_%',
            '_transient_cg_unique_%',
            '_transient_timeout_cg_unique_%',
            '_transient_cg_duplicate_template_warning',
            '_transient_timeout_cg_duplicate_template_warning',
            '_transient_certificate_progress_%',
            '_transient_timeout_certificate_progress_%',
            '_transient_cert_batch_%',
            '_transient_timeout_cert_batch_%',
            '_transient_cg_event_date_invalid_%',
            '_transient_timeout_cg_event_date_invalid_%',
        ];

        foreach ($transient_patterns as $pattern) {
            if (substr($pattern, -1) === '%') {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                ));
            } else {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name = %s",
                    $pattern
                ));
            }
        }

        // ── 2. Delete certificate PDFs across all upload subdirectories ───────
        // Files are saved to wp_upload_dir()['path'] (year/month dirs),
        // NOT to a fixed 'certificates/' subfolder.
        $upload_dir = wp_upload_dir();
        $base       = trailingslashit($upload_dir['basedir']);

        // Build list of dirs to scan: root + every year/month subdir
        $scan_dirs = [$base];
        foreach (glob($base . '[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR) as $year_dir) {
            foreach (glob(trailingslashit($year_dir) . '[0-9][0-9]', GLOB_ONLYDIR) as $month_dir) {
                $scan_dirs[] = trailingslashit($month_dir);
            }
        }

        foreach ($scan_dirs as $dir) {
            // Matches: certificate_preview.pdf, certificate_preview_123.pdf, certificate_456.pdf
            $pdfs = glob($dir . 'certificate_*.pdf');
            if ($pdfs) {
                foreach ($pdfs as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }

        // ── 3. Delete bulk-download ZIP archives at uploads root ──────────────
        $zips = glob($base . '*certificates*.zip');
        if ($zips) {
            foreach ($zips as $zip) {
                if (is_file($zip)) {
                    @unlink($zip);
                }
            }
        }

        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

// Render settings page
function certificate_generator_settings_page() {
    // Add CSS for API key styling
    ?>
    <style>
        .api-key-container {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        #certificate_generator_api_key_display {
            background: #fff;
            padding: 8px;
            font-size: 14px;
            line-height: 1.4;
        }
        .api-key-actions {
            margin-top: 10px;
        }
        .api-key-container, .api-key-actions {
            display: none;
        }
        .api-key-container.visible, .api-key-actions.visible {
            display: block;
        }
    </style>
    <?php
    //Get and validate current tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    $valid_tabs = array('general', 'templates', 'api', 'tools', 'license');

    if (!in_array($active_tab, $valid_tabs)) {
        $active_tab = 'general';
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Certificate Generator Settings', 'certificate-generator'); ?></h1>

        <?php
        // Compute plan state once for use in tabs and locks
        $cg_lm_active  = class_exists('CG_License_Manager');
        $cg_is_free     = $cg_lm_active ? CG_License_Manager::is_free()     : false;
        $cg_is_pro      = $cg_lm_active ? CG_License_Manager::is_pro()      : true;
        $cg_is_business = $cg_lm_active ? CG_License_Manager::is_business() : false;
        $cg_plan_label  = $cg_lm_active ? CG_License_Manager::get_plan_label() : 'Free';

        $tab_base = admin_url('options-general.php?page=certificate_generator_settings&tab=');
        $license_tab_url = $tab_base . 'license';
        ?>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url($tab_base . 'general'); ?>" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                <?php _e('General', 'certificate-generator'); ?>
            </a>
            <a href="<?php echo esc_url($tab_base . 'templates'); ?>" class="nav-tab <?php echo $active_tab == 'templates' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Email Templates', 'certificate-generator'); ?>
                <?php if ($cg_is_free) echo '<span style="font-size:11px;opacity:.7;margin-left:3px;">🔒</span>'; ?>
            </a>
            <a href="<?php echo esc_url($tab_base . 'api'); ?>" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">
                <?php _e('API Settings', 'certificate-generator'); ?>
                <?php if ($cg_is_free) echo '<span style="font-size:11px;opacity:.7;margin-left:3px;">🔒</span>'; ?>
            </a>
            <a href="<?php echo esc_url($tab_base . 'tools'); ?>" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Tools', 'certificate-generator'); ?>
            </a>
            <a href="<?php echo esc_url($license_tab_url); ?>" class="nav-tab <?php echo $active_tab == 'license' ? 'nav-tab-active' : ''; ?>"
               style="color:<?php echo $cg_is_free ? '#92400e' : ($cg_is_business ? '#5b21b6' : '#1d4ed8'); ?>;font-weight:700;">
                🔑 <?php _e('License', 'certificate-generator'); ?>
                <span style="font-size:11px;font-weight:600;margin-left:3px;padding:1px 6px;border-radius:10px;background:<?php echo $cg_is_free ? '#ffedd5' : ($cg_is_business ? '#ede9fe' : '#dbeafe'); ?>;color:<?php echo $cg_is_free ? '#92400e' : ($cg_is_business ? '#5b21b6' : '#1d4ed8'); ?>;"><?php echo esc_html($cg_plan_label); ?></span>
            </a>
        </h2>

        <style>
        .cg-plan-gate {
            background: #fff;
            border: 2px dashed #e5e7eb;
            border-radius: 10px;
            padding: 48px 40px;
            text-align: center;
            max-width: 640px;
            margin: 30px 0;
        }
        .cg-plan-gate .cg-gate-icon { font-size: 48px; line-height: 1; margin-bottom: 16px; }
        .cg-plan-gate h2 { font-size: 20px; margin: 0 0 10px; color: #111827; }
        .cg-plan-gate p  { color: #6b7280; font-size: 14px; margin: 0 0 20px; }
        .cg-plan-gate ul { list-style: none; padding: 0; margin: 0 0 24px; text-align: left; display: inline-block; }
        .cg-plan-gate ul li { font-size: 14px; color: #374151; padding: 4px 0; }
        .cg-plan-gate ul li::before { content: "✓ "; color: #22c55e; font-weight: 700; }
        .cg-gate-btn {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff !important;
            font-weight: 600;
            font-size: 14px;
            padding: 11px 28px;
            border-radius: 8px;
            text-decoration: none;
            margin-right: 10px;
        }
        .cg-gate-btn:hover { opacity: .9; }
        .cg-gate-secondary { font-size: 13px; color: #6b7280; vertical-align: middle; }
        </style>

        <?php
        // Debug information for tab content
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<!-- Active Tab: ' . esc_html($active_tab) . ' -->';
        }
        ?>

        <?php if ($active_tab == 'general') : ?>
            <!-- ── GENERAL TAB: Contact Email + Card Styling only ── -->
            <form action="options.php" method="post">
                <?php settings_fields('certificate_generator_settings'); ?>

                <h2><?php esc_html_e('General Settings', 'certificate-generator'); ?></h2>
                <?php certificate_generator_settings_section_callback(); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label><?php esc_html_e('Contact Email', 'certificate-generator'); ?></label>
                        </th>
                        <td><?php certificate_generator_email_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Card Styling', 'certificate-generator'); ?></th>
                        <td><?php certificate_generator_card_styles_render(); ?></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

        <?php elseif ($active_tab == 'templates') : ?>
            <?php if ($cg_is_free) : ?>
            <!-- FREE PLAN LOCK: Email Templates -->
            <div class="cg-plan-gate">
                <div class="cg-gate-icon">📧</div>
                <h2><?php esc_html_e('Email Templates — Pro Feature', 'certificate-generator'); ?></h2>
                <p><?php esc_html_e('Customise the emails sent with every certificate. Upgrade to Pro or Business to unlock:', 'certificate-generator'); ?></p>
                <ul>
                    <li><?php esc_html_e('Per-type email subject, body & logo (Students / Teachers / Schools)', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('Auto-send certificates when recipients are found', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('Dynamic placeholders: {name}, {certificate_title}, {site_name} and more', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('WP shortcode support inside email body', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('CC / BCC / Reply-To per email type', 'certificate-generator'); ?></li>
                </ul>
                <a href="<?php echo esc_url('https://eshaanportfolio.vercel.app/'); ?>" target="_blank" class="cg-gate-btn">
                    <?php esc_html_e('Upgrade to Pro →', 'certificate-generator'); ?>
                </a>
                <a href="<?php echo esc_url($license_tab_url); ?>" class="cg-gate-secondary">
                    <?php esc_html_e('Enter license key', 'certificate-generator'); ?>
                </a>
            </div>
            <?php else : ?>
            <!-- ── TEMPLATES TAB: Email settings + Rate limits + SMTP status ── -->
            <form action="options.php" method="post">
                <?php settings_fields('certificate_generator_settings'); ?>

                <h2><?php esc_html_e('Email Templates', 'certificate-generator'); ?></h2>
                <?php certificate_generator_email_templates_section_callback(); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Send Emails', 'certificate-generator'); ?></th>
                        <td><?php certificate_generator_auto_send_render(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Email Logo', 'certificate-generator'); ?></th>
                        <td><?php certificate_generator_email_logo_render(); ?></td>
                    </tr>
                </table>

                <?php
                foreach (['students', 'teachers', 'schools'] as $_pt) {
                    certificate_generator_email_template_render(['post_type' => $_pt]);
                }
                ?>

                <h2 style="margin-top:28px;"><?php esc_html_e('Email Rate Limits', 'certificate-generator'); ?></h2>
                <?php certificate_generator_rate_limit_section_callback(); ?>
                <div style="background:#fff;border:1px solid #e0e0e0;border-radius:6px;padding:16px 20px;margin-top:8px;">
                    <?php certificate_generator_rate_limits_render(); ?>
                </div>

                <h2 style="margin-top:28px;"><?php esc_html_e('SMTP Status', 'certificate-generator'); ?></h2>
                <?php certificate_generator_smtp_section_callback(); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('SMTP Configuration', 'certificate-generator'); ?></th>
                        <td><?php certificate_generator_smtp_settings_render(); ?></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
            <?php endif; ?>

            <?php /* ── Shortcode & Placeholder Reference Panel ── */ ?>
            <style>
                .cg-ref-wrap { margin-top: 30px; }
                .cg-ref-wrap h2 { font-size: 16px; margin-bottom: 4px; }
                .cg-ref-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; margin-top: 12px; }
                .cg-ref-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px 18px; }
                .cg-ref-card h3 { margin: 0 0 10px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #444; display: flex; align-items: center; gap: 6px; }
                .cg-ref-card h3 .cg-badge { display: inline-block; border-radius: 3px; padding: 1px 7px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
                .badge-blue   { background:#dbeafe; color:#1e40af; }
                .badge-green  { background:#dcfce7; color:#166534; }
                .badge-purple { background:#ede9fe; color:#5b21b6; }
                .badge-orange { background:#ffedd5; color:#92400e; }
                .cg-ref-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
                .cg-ref-table td { padding: 4px 6px; border-bottom: 1px solid #f3f3f3; vertical-align: top; }
                .cg-ref-table tr:last-child td { border-bottom: none; }
                .cg-ref-table td:first-child { white-space: nowrap; }
                .cg-tag { display: inline-block; font-family: monospace; font-size: 12px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 3px; padding: 1px 6px; cursor: pointer; transition: background .15s; }
                .cg-tag:hover { background: #e5e7eb; }
                .cg-tag.blue   { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
                .cg-tag.green  { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
                .cg-tag.purple { background: #f5f3ff; border-color: #ddd6fe; color: #6d28d9; }
                .cg-tag.orange { background: #fff7ed; border-color: #fed7aa; color: #c2410c; }
                .cg-ref-note  { font-size: 11.5px; color: #777; margin-top: 10px; padding-top: 8px; border-top: 1px solid #f0f0f0; }
                .cg-ref-note a { color: #2271b1; text-decoration: none; }
                .cg-copy-notice { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #333; color: #fff; padding: 8px 18px; border-radius: 4px; font-size: 13px; pointer-events: none; opacity: 0; transition: opacity .2s; z-index: 9999; }
                .cg-copy-notice.show { opacity: 1; }
            </style>
            <div class="cg-ref-wrap">
                <h2><?php esc_html_e('Available Shortcodes &amp; Placeholders', 'certificate-generator'); ?></h2>
                <p style="color:#555;margin-top:2px;font-size:13px;">
                    <?php esc_html_e('Click any tag to copy it. Paste directly into Email Subject or Email Body fields above.', 'certificate-generator'); ?>
                </p>
                <div class="cg-ref-grid">

                    <?php /* Card 1: Built-in CG Placeholders */ ?>
                    <div class="cg-ref-card">
                        <h3><?php esc_html_e('Certificate Generator', 'certificate-generator'); ?> <span class="cg-badge badge-blue">Built-in</span></h3>
                        <table class="cg-ref-table">
                            <tr><td><span class="cg-tag blue" onclick="cgCopyTag(this)">{name}</span></td><td><?php esc_html_e('Recipient name', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag blue" onclick="cgCopyTag(this)">{certificate_title}</span></td><td><?php esc_html_e('Certificate title / type', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag blue" onclick="cgCopyTag(this)">{zip_link}</span></td><td><?php esc_html_e('ZIP download URL (bulk emails)', 'certificate-generator'); ?></td></tr>
                        </table>
                        <p class="cg-ref-note"><?php esc_html_e('Always available. Replaced before shortcode processing.', 'certificate-generator'); ?></p>
                    </div>

                    <?php /* Card 2: Site / WP Dynamic Tags Placeholders */ ?>
                    <div class="cg-ref-card">
                        <h3><?php esc_html_e('Site &amp; User', 'certificate-generator'); ?> <span class="cg-badge badge-green">{placeholders}</span></h3>
                        <table class="cg-ref-table">
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{site_name}</span></td><td><?php esc_html_e('Site title', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{site_url}</span></td><td><?php esc_html_e('Site URL', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{admin_email}</span></td><td><?php esc_html_e('Admin email address', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{current_date}</span></td><td><?php esc_html_e('Today\'s date', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{current_year}</span></td><td><?php esc_html_e('Current year', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{current_month}</span></td><td><?php esc_html_e('Current month name', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{user_name}</span></td><td><?php esc_html_e('Logged-in user login', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{user_display_name}</span></td><td><?php esc_html_e('Logged-in user display name', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">{user_email}</span></td><td><?php esc_html_e('Logged-in user email', 'certificate-generator'); ?></td></tr>
                        </table>
                        <p class="cg-ref-note">
                            <?php
                            if (class_exists('WP_Dynamic_Tags_Plugin')) {
                                esc_html_e('✓ WP Dynamic Tags active — all placeholders available.', 'certificate-generator');
                            } else {
                                echo '<span style="color:#b91c1c;">⚠ Requires <strong>WP Dynamic Tags</strong> plugin to be active.</span>';
                            }
                            ?>
                        </p>
                    </div>

                    <?php /* Card 3: Chatbot Event Placeholders */ ?>
                    <div class="cg-ref-card">
                        <h3><?php esc_html_e('Event Data', 'certificate-generator'); ?> <span class="cg-badge badge-purple">{placeholders}</span></h3>
                        <table class="cg-ref-table">
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_title}</span></td><td><?php esc_html_e('Upcoming event name', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_fees}</span></td><td><?php esc_html_e('Registration fee', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_dis_fees}</span></td><td><?php esc_html_e('Discounted fee', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_currency}</span></td><td><?php esc_html_e('Currency symbol / code', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_dates}</span></td><td><?php esc_html_e('Exam dates summary', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_registration_deadline}</span></td><td><?php esc_html_e('Last date to register', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_email}</span></td><td><?php esc_html_e('Contact email', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_phone}</span></td><td><?php esc_html_e('Contact phone', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag purple" onclick="cgCopyTag(this)">{event_whatsapp}</span></td><td><?php esc_html_e('WhatsApp number', 'certificate-generator'); ?></td></tr>
                        </table>
                        <p class="cg-ref-note">
                            <?php
                            if (class_exists('AI_Chatbot_Widget') || class_exists('AI_Chatbot_API')) {
                                esc_html_e('✓ Chatbot active — event placeholders available after sync.', 'certificate-generator');
                            } else {
                                echo '<span style="color:#b91c1c;">⚠ Requires <strong>chatbot-by-eshaan</strong> plugin.</span>';
                            }
                            ?>
                        </p>
                    </div>

                    <?php /* Card 4: Event Dynamic Tag Shortcodes */ ?>
                    <div class="cg-ref-card">
                        <h3><?php esc_html_e('Event Data', 'certificate-generator'); ?> <span class="cg-badge badge-orange">[shortcodes]</span></h3>
                        <table class="cg-ref-table">
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_fees]</span></td><td><?php esc_html_e('Registration fee', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_dis_fees]</span></td><td><?php esc_html_e('Discounted fee', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_currency]</span></td><td><?php esc_html_e('Currency', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_email]</span></td><td><?php esc_html_e('Contact email', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_phone]</span></td><td><?php esc_html_e('Contact phone', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_whatsapp]</span></td><td><?php esc_html_e('WhatsApp number', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_exam_date1]</span></td><td><?php esc_html_e('Exam date 1', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_exam_date2]</span></td><td><?php esc_html_e('Exam date 2', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_last_date1]</span></td><td><?php esc_html_e('Last registration date 1', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_last_date2]</span></td><td><?php esc_html_e('Last registration date 2', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_mock_date1]</span></td><td><?php esc_html_e('Mock exam date/time 1', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[event_mock_date2]</span></td><td><?php esc_html_e('Mock exam date/time 2', 'certificate-generator'); ?></td></tr>
                        </table>
                        <p class="cg-ref-note"><?php esc_html_e('Written to WP Dynamic Tags on every chatbot event sync. Available once sync runs.', 'certificate-generator'); ?></p>
                    </div>

                    <?php /* Card 5: Certificate Data Shortcodes */ ?>
                    <div class="cg-ref-card">
                        <h3><?php esc_html_e('Certificate Data', 'certificate-generator'); ?> <span class="cg-badge badge-orange">[shortcodes]</span></h3>
                        <table class="cg-ref-table">
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[cg_last_student_name]</span></td><td><?php esc_html_e('Most recently saved student', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[cg_last_school_name]</span></td><td><?php esc_html_e('Most recently saved school', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[cg_students_name_N]</span></td><td><?php esc_html_e('Student name by post ID', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[cg_students_email_N]</span></td><td><?php esc_html_e('Student email by post ID', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[cg_school_name_N]</span></td><td><?php esc_html_e('School name by post ID', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[cg_cert_type_N]</span></td><td><?php esc_html_e('Certificate type by post ID', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag orange" onclick="cgCopyTag(this)">[cg_issue_date_N]</span></td><td><?php esc_html_e('Issue date by post ID', 'certificate-generator'); ?></td></tr>
                        </table>
                        <p class="cg-ref-note"><?php esc_html_e('Replace N with the WordPress post ID. Written automatically when a student/teacher/school is saved.', 'certificate-generator'); ?></p>
                    </div>

                    <?php /* Card 6: Additional Site Shortcodes */ ?>
                    <div class="cg-ref-card">
                        <h3><?php esc_html_e('Site Info', 'certificate-generator'); ?> <span class="cg-badge badge-green">[shortcodes]</span></h3>
                        <table class="cg-ref-table">
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">[site_name]</span></td><td><?php esc_html_e('Site title', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">[site_url]</span></td><td><?php esc_html_e('Site URL', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">[site_description]</span></td><td><?php esc_html_e('Site tagline', 'certificate-generator'); ?></td></tr>
                            <tr><td><span class="cg-tag green" onclick="cgCopyTag(this)">[admin_email]</span></td><td><?php esc_html_e('Admin email', 'certificate-generator'); ?></td></tr>
                        </table>
                        <p class="cg-ref-note">
                            <?php
                            if (class_exists('WP_Dynamic_Tags_Plugin')) {
                                printf(
                                    '<a href="%s">%s</a>',
                                    esc_url(admin_url('admin.php?page=wp-dynamic-tags')),
                                    esc_html__('View all tags in WP Dynamic Tags →', 'certificate-generator')
                                );
                            } else {
                                echo '<span style="color:#b91c1c;">⚠ Requires <strong>WP Dynamic Tags</strong> plugin.</span>';
                            }
                            ?>
                        </p>
                    </div>

                </div><!-- .cg-ref-grid -->
            </div><!-- .cg-ref-wrap -->
            <div class="cg-copy-notice" id="cg-copy-notice"><?php esc_html_e('Copied!', 'certificate-generator'); ?></div>
            <script>
            function cgCopyTag(el) {
                const text = el.textContent.trim();
                navigator.clipboard.writeText(text).then(function() {
                    const notice = document.getElementById('cg-copy-notice');
                    notice.classList.add('show');
                    setTimeout(function() { notice.classList.remove('show'); }, 1500);
                });
            }
            </script>

        <?php elseif ($active_tab == 'api') : ?>
            <?php if ($cg_is_free) : ?>
            <!-- FREE PLAN LOCK: API Settings -->
            <div class="cg-plan-gate">
                <div class="cg-gate-icon">🔌</div>
                <h2><?php esc_html_e('REST API Access — Pro Feature', 'certificate-generator'); ?></h2>
                <p><?php esc_html_e('Connect external apps, AI agents, and automation tools to generate certificates via REST API. Upgrade to unlock:', 'certificate-generator'); ?></p>
                <ul>
                    <li><?php esc_html_e('Generate & issue certificates via REST API', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('Secure API key authentication (Bearer token)', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('3 REST endpoints: /issue-certificate, /health, /validate-key', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('Enable / disable access per-site', 'certificate-generator'); ?></li>
                    <li><?php esc_html_e('Business plan: unlimited API calls', 'certificate-generator'); ?></li>
                </ul>
                <a href="<?php echo esc_url('https://eshaanportfolio.vercel.app/'); ?>" target="_blank" class="cg-gate-btn">
                    <?php esc_html_e('Upgrade to Pro →', 'certificate-generator'); ?>
                </a>
                <a href="<?php echo esc_url($license_tab_url); ?>" class="cg-gate-secondary">
                    <?php esc_html_e('Enter license key', 'certificate-generator'); ?>
                </a>
            </div>
            <?php else : ?>
            <form action="options.php" method="post" id="certificate_generator_settings">
                <?php
                settings_fields('certificate_generator_settings');
                echo '<h2>' . esc_html__('API Configuration', 'certificate-generator') . '</h2>';
                certificate_generator_api_section_callback();
                echo '<table class="form-table" role="presentation">';
                echo '<tr><th scope="row">' . esc_html__('API Key', 'certificate-generator') . '</th><td>';
                certificate_generator_api_key_render();
                echo '</td></tr>';
                echo '<tr><th scope="row">' . esc_html__('Enable API Access', 'certificate-generator') . '</th><td>';
                certificate_generator_api_key_enabled_render();
                echo '</td></tr>';
                echo '</table>';
                submit_button();
                ?>
            </form>
            <?php endif; ?>

            <?php /* ── API Endpoint Reference ── */ ?>
            <?php
            $base_url   = get_rest_url(null, 'certificate-generator/v1');
            $api_key    = get_option('certificate_generator_api_key', '');
            $api_active = get_option('certificate_generator_api_key_enabled', false);
            $plan_ok    = !class_exists('CG_License_Manager') || CG_License_Manager::is_pro();
            ?>
            <style>
                .cg-api-wrap { margin-top: 30px; }
                .cg-api-wrap h2 { font-size: 16px; margin-bottom: 4px; }
                .cg-endpoint { background:#fff; border:1px solid #e0e0e0; border-radius:6px; margin-bottom:16px; overflow:hidden; }
                .cg-endpoint-head { display:flex; align-items:center; gap:12px; padding:12px 16px; background:#f9f9f9; border-bottom:1px solid #e8e8e8; }
                .cg-method { display:inline-block; font-family:monospace; font-size:11px; font-weight:700; padding:3px 8px; border-radius:3px; min-width:40px; text-align:center; }
                .cg-method.get  { background:#dcfce7; color:#15803d; }
                .cg-method.post { background:#dbeafe; color:#1e40af; }
                .cg-endpoint-url { font-family:monospace; font-size:13px; color:#333; flex:1; word-break:break-all; }
                .cg-endpoint-body { padding:14px 16px; }
                .cg-endpoint-body p { margin:0 0 8px; font-size:13px; color:#555; }
                .cg-code-block { background:#1e1e2e; color:#cdd6f4; font-family:monospace; font-size:12px; border-radius:4px; padding:12px 14px; overflow-x:auto; white-space:pre; line-height:1.6; position:relative; margin:8px 0; }
                .cg-code-copy { position:absolute; top:8px; right:8px; background:#313244; color:#cdd6f4; border:none; border-radius:3px; padding:3px 9px; font-size:11px; cursor:pointer; }
                .cg-code-copy:hover { background:#45475a; }
                .cg-params-table { width:100%; border-collapse:collapse; font-size:12.5px; margin:8px 0; }
                .cg-params-table th { text-align:left; padding:5px 8px; background:#f3f4f6; border:1px solid #e5e7eb; font-weight:600; }
                .cg-params-table td { padding:5px 8px; border:1px solid #e5e7eb; vertical-align:top; }
                .cg-required { color:#dc2626; font-size:11px; font-weight:600; }
                .cg-optional { color:#6b7280; font-size:11px; }
                .cg-api-status { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:4px; font-size:13px; font-weight:600; margin-bottom:16px; }
                .cg-api-status.active   { background:#dcfce7; color:#15803d; }
                .cg-api-status.inactive { background:#fee2e2; color:#991b1b; }
            </style>
            <div class="cg-api-wrap">
                <h2><?php esc_html_e('REST API Endpoints', 'certificate-generator'); ?></h2>
                <p style="color:#555;font-size:13px;margin-top:2px;">
                    <?php esc_html_e('Base URL:', 'certificate-generator'); ?>
                    <code style="font-size:12px;"><?php echo esc_url($base_url); ?></code>
                </p>

                <?php if (!$api_active): ?>
                <div class="cg-api-status inactive">⚠ <?php esc_html_e('API access is currently disabled. Enable it above to use these endpoints.', 'certificate-generator'); ?></div>
                <?php elseif (!$plan_ok): ?>
                <div class="cg-api-status inactive">🔒 <?php esc_html_e('REST API requires Pro or Business plan. Upgrade your license.', 'certificate-generator'); ?></div>
                <?php else: ?>
                <div class="cg-api-status active">✓ <?php esc_html_e('API is active and accepting requests.', 'certificate-generator'); ?></div>
                <?php endif; ?>

                <?php /* Endpoint 1: health */ ?>
                <div class="cg-endpoint">
                    <div class="cg-endpoint-head">
                        <span class="cg-method get">GET</span>
                        <span class="cg-endpoint-url"><?php echo esc_html(get_rest_url(null, 'certificate-generator/v1/health')); ?></span>
                    </div>
                    <div class="cg-endpoint-body">
                        <p><?php esc_html_e('Health check. No authentication required. Use to verify the API is reachable.', 'certificate-generator'); ?></p>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Example Request', 'certificate-generator'); ?></strong>
                        <div class="cg-code-block" id="cg-code-health"><?php echo esc_html("curl -X GET \\\n  \"" . get_rest_url(null, 'certificate-generator/v1/health') . "\""); ?><button class="cg-code-copy" onclick="cgCopyCode('cg-code-health')">Copy</button></div>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Response', 'certificate-generator'); ?></strong>
                        <div class="cg-code-block">{"success": true, "message": "Certificate Generator API is running", "version": "6.1.0"}</div>
                    </div>
                </div>

                <?php /* Endpoint 2: issue-certificate */ ?>
                <div class="cg-endpoint">
                    <div class="cg-endpoint-head">
                        <span class="cg-method post">POST</span>
                        <span class="cg-endpoint-url"><?php echo esc_html(get_rest_url(null, 'certificate-generator/v1/issue-certificate')); ?></span>
                        <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:3px;font-weight:600;">🔑 <?php esc_html_e('API Key required', 'certificate-generator'); ?></span>
                    </div>
                    <div class="cg-endpoint-body">
                        <p><?php esc_html_e('Generate and email a certificate to a student. Requires Pro or Business plan.', 'certificate-generator'); ?></p>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Parameters', 'certificate-generator'); ?></strong>
                        <table class="cg-params-table">
                            <tr>
                                <th><?php esc_html_e('Field', 'certificate-generator'); ?></th>
                                <th><?php esc_html_e('Type', 'certificate-generator'); ?></th>
                                <th><?php esc_html_e('Required', 'certificate-generator'); ?></th>
                                <th><?php esc_html_e('Description', 'certificate-generator'); ?></th>
                            </tr>
                            <tr>
                                <td><code>student_email</code></td>
                                <td>string</td>
                                <td><span class="cg-required"><?php esc_html_e('Required', 'certificate-generator'); ?></span></td>
                                <td><?php esc_html_e('Email address of the student to issue certificate to', 'certificate-generator'); ?></td>
                            </tr>
                            <tr>
                                <td><code>Authorization</code></td>
                                <td>header</td>
                                <td><span class="cg-required"><?php esc_html_e('Required', 'certificate-generator'); ?></span></td>
                                <td><?php esc_html_e('Bearer YOUR_API_KEY', 'certificate-generator'); ?></td>
                            </tr>
                        </table>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Example Request', 'certificate-generator'); ?></strong>
                        <div class="cg-code-block" id="cg-code-issue"><?php
                        $example_key = $api_key ? esc_html($api_key) : 'YOUR_API_KEY';
                        echo esc_html("curl -X POST \\\n  \"" . get_rest_url(null, 'certificate-generator/v1/issue-certificate') . "\" \\\n  -H \"Authorization: Bearer " . $example_key . "\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"student_email\": \"student@example.com\"}'");
                        ?><button class="cg-code-copy" onclick="cgCopyCode('cg-code-issue')">Copy</button></div>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Success Response', 'certificate-generator'); ?></strong>
                        <div class="cg-code-block">{"success": true, "message": "Certificate issued and emailed successfully.", "certificate_id": 42}</div>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Error Response', 'certificate-generator'); ?></strong>
                        <div class="cg-code-block">{"code": "student_not_found", "message": "No student found with that email address.", "data": {"status": 404}}</div>
                    </div>
                </div>

                <?php /* Endpoint 3: validate-key */ ?>
                <div class="cg-endpoint">
                    <div class="cg-endpoint-head">
                        <span class="cg-method post">POST</span>
                        <span class="cg-endpoint-url"><?php echo esc_html(get_rest_url(null, 'certificate-generator/v1/validate-key')); ?></span>
                        <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:3px;font-weight:600;">🔑 <?php esc_html_e('API Key required', 'certificate-generator'); ?></span>
                    </div>
                    <div class="cg-endpoint-body">
                        <p><?php esc_html_e('Validate that your API key is correct and check the current plan and usage.', 'certificate-generator'); ?></p>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Example Request', 'certificate-generator'); ?></strong>
                        <div class="cg-code-block" id="cg-code-validate"><?php
                        echo esc_html("curl -X POST \\\n  \"" . get_rest_url(null, 'certificate-generator/v1/validate-key') . "\" \\\n  -H \"Authorization: Bearer " . $example_key . "\"");
                        ?><button class="cg-code-copy" onclick="cgCopyCode('cg-code-validate')">Copy</button></div>
                        <strong style="font-size:12px;text-transform:uppercase;color:#444;"><?php esc_html_e('Success Response', 'certificate-generator'); ?></strong>
                        <div class="cg-code-block">{"success": true, "plan": "business", "usage": 47, "limit": 0, "message": "API key is valid."}</div>
                    </div>
                </div>

                <script>
                function cgCopyCode(id) {
                    const block = document.getElementById(id);
                    const text = block.childNodes[0].textContent.trim();
                    navigator.clipboard.writeText(text).then(function() {
                        const btn = block.querySelector('.cg-code-copy');
                        const orig = btn.textContent;
                        btn.textContent = '✓ Copied';
                        setTimeout(function() { btn.textContent = orig; }, 1500);
                    });
                }
                if (typeof cgCopyTag === 'undefined') {
                    function cgCopyTag(el) {
                        navigator.clipboard.writeText(el.textContent.trim());
                    }
                }
                </script>
            </div><!-- .cg-api-wrap -->
        <?php elseif ($active_tab == 'license') : ?>
            <?php
            if (function_exists('cg_render_license_tab')) {
                cg_render_license_tab();
            } else {
                echo '<p>' . esc_html__('License manager is not available.', 'certificate-generator') . '</p>';
            }
            ?>
        <?php elseif ($active_tab == 'tools') : ?>
            <div id="certificate-generator-cache-section" style="margin-top: 30px;">
                <h3><?php _e('Clear Cache &amp; Certificates', 'certificate-generator'); ?></h3>
                <p><?php _e('Click the button below to clear all cached results and generated certificate files.', 'certificate-generator'); ?></p>
                <button id="clear-cache-btn" class="button button-secondary">
                    <?php _e('Clear Cache & Certificates', 'certificate-generator'); ?>
                </button>
                <div id="clear-cache-message"></div>
            </div>

            <!-- Email Logs Link -->
            <div style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h3><?php _e('Email Logs', 'certificate-generator'); ?></h3>
                <p><?php _e('View and manage email delivery logs for certificate notifications.', 'certificate-generator'); ?></p>
                <a href="<?php echo admin_url('options-general.php?page=certificate-email-logs'); ?>" class="button button-primary">
                    <?php _e('View Email Logs', 'certificate-generator'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        (function($) {
            $('#clear-cache-btn').on('click', function() {
                const $messageDiv = $('#clear-cache-message');
                $messageDiv.empty().removeClass('notice notice-success notice-error');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'certificate_generator_clear_cache',
                        nonce: '<?php echo wp_create_nonce('certificate_generator_clear_cache'); ?>'
                    },
                    success: function(response) {
                        $messageDiv.addClass('notice ' + (response.success ? 'notice-success' : 'notice-error'))
                            .text(response.data || '<?php _e('Operation completed.', 'certificate-generator'); ?>');
                    },
                    error: function() {
                        $messageDiv.addClass('notice notice-error')
                            .text('<?php _e('Error occurred during the operation.', 'certificate-generator'); ?>');
                    }
                });
            });
        })(jQuery);
    </script>
    <?php
}


/**
 * API Configuration section callback
 */
function certificate_generator_api_section_callback() {
    echo '<p>' . esc_html__('Configure API access for external integrations and AI agents. The API allows secure certificate generation through REST endpoints.', 'certificate-generator') . '</p>';

    $api_enabled = get_option('certificate_generator_api_key_enabled', false);
    if ($api_enabled) {
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('API Endpoints:', 'certificate-generator') . '</strong><br>';
        echo esc_html__('Certificate Issuance:', 'certificate-generator') . ' <code>' . rest_url('certificate-generator/v1/issue-certificate') . '</code><br>';
        echo esc_html__('Health Check:', 'certificate-generator') . ' <code>' . rest_url('certificate-generator/v1/health') . '</code><br>';
        echo esc_html__('Key Validation:', 'certificate-generator') . ' <code>' . rest_url('certificate-generator/v1/validate-key') . '</code></p></div>';
    }
}

/**
 * API Key enabled field render
 */
function certificate_generator_api_key_enabled_render() {
    $enabled = get_option('certificate_generator_api_key_enabled', false);
    echo '<input type="checkbox" id="certificate_generator_api_key_enabled" name="certificate_generator_api_key_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
    echo '<label for="certificate_generator_api_key_enabled">' . esc_html__('Enable REST API access for certificate generation', 'certificate-generator') . '</label>';
    echo '<p class="description">' . esc_html__('When enabled, external applications can generate certificates using the REST API with proper authentication.', 'certificate-generator') . '</p>';
}

/**
 * API Key field render
 */
function certificate_generator_api_key_render() {
    $api_key = get_option('certificate_generator_api_key', '');
    $api_enabled = get_option('certificate_generator_api_key_enabled', false);

    echo '<div id="api-key-section">';

    if (empty($api_key)) {
        echo '<button type="button" id="generate-api-key" class="button button-primary">' . esc_html__('Generate API Key', 'certificate-generator') . '</button>';
        echo '<p class="description">' . esc_html__('Click to generate a secure API key for external integrations.', 'certificate-generator') . '</p>';
    } else {
        echo '<div class="api-key-container visible" style="margin-bottom: 10px;">';
        echo '<input type="text" id="certificate_generator_api_key_display" value="' . esc_attr($api_key) . '" readonly style="width: 100%; max-width: 500px; font-family: monospace;" />';
        echo '<input type="hidden" id="certificate_generator_api_key" name="certificate_generator_api_key" value="' . esc_attr($api_key) . '" />';
        echo '</div>';

        echo '<div class="api-key-actions visible" style="margin-bottom: 15px;">';
        echo '<button type="button" id="regenerate-api-key" class="button button-secondary">' . esc_html__('Regenerate', 'certificate-generator') . '</button>';
        echo '<button type="button" id="copy-api-key" class="button button-secondary" style="margin-left: 10px;">' . esc_html__('Copy', 'certificate-generator') . '</button>';
        echo '<button type="button" id="show-api-key" class="button button-secondary" style="margin-left: 10px;">' . esc_html__('Show Full Key', 'certificate-generator') . '</button>';
        echo '<button type="button" id="delete-api-key" class="button button-secondary" style="margin-left: 10px; color: #dc3232;">' . esc_html__('Delete', 'certificate-generator') . '</button>';
        echo '</div>';

        echo '<p class="description">' . esc_html__('Keep this API key secure. It provides full access to certificate generation.', 'certificate-generator') . '</p>';

        if ($api_enabled) {
            echo '<div class="notice notice-warning inline" style="margin-top: 10px;"><p><strong>' . esc_html__('Security Notice:', 'certificate-generator') . '</strong> ' . esc_html__('API access is currently enabled. Ensure your API key is kept secure and only shared with trusted applications.', 'certificate-generator') . '</p></div>';
        }
    }

    echo '</div>';

    // Add JavaScript for API key management
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#generate-api-key, #regenerate-api-key').on('click', function() {
            console.log('API key button clicked: ' + $(this).attr('id'));

            if ($(this).attr('id') === 'regenerate-api-key') {
                if (!confirm('<?php echo esc_js(__('Are you sure you want to regenerate the API key? This will invalidate the current key.', 'certificate-generator')); ?>')) {
                    return;
                }
            }

            // Generate a secure random API key
            generateSecureApiKey().then(function(apiKey) {
                console.log('Generated API key: ' + apiKey.substring(0, 5) + '...');
                console.log('Form exists: ' + ($('form#certificate_generator_settings').length > 0 ? 'Yes' : 'No'));

                // Save the API key via AJAX first
                console.log('Sending AJAX request to save API key');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'certificate_generator_save_api_key',
                        api_key: apiKey,
                        nonce: '<?php echo wp_create_nonce("save_api_key_nonce"); ?>'
                    },
                    success: function(response) {
                        console.log('AJAX response:', response);

                        if (response.success) {
                            // Update the UI only after successful save
                            $('#certificate_generator_api_key').val(apiKey);
                            $('#certificate_generator_api_key_display').val(apiKey);
                            $('#generate-api-key').hide();
                            $('.api-key-container, .api-key-actions').show();

                            // Enable API access by default when generating new key
                            $('#certificate_generator_api_key_enabled').prop('checked', true);
                            console.log('API access checkbox checked');

                            // Save settings and reload
                            alert('<?php echo esc_js(__('API key generated successfully! The page will now reload.', 'certificate-generator')); ?>');

                            console.log('Reloading page in 500ms to reflect changes');
                            setTimeout(function() {
                                location.reload();
                            }, 500);
                        } else {
                            alert('<?php echo esc_js(__('Failed to save API key. Please try again or contact support.', 'certificate-generator')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Failed to save API key. Please try again or contact support.', 'certificate-generator')); ?>');
                    }
                });
            });
        });

        $('#show-api-key').on('click', function() {
            const $input = $('#certificate_generator_api_key_display');
            const fullKey = $('#certificate_generator_api_key').val();

            if ($(this).text() === '<?php echo esc_js(__('Show Full Key', 'certificate-generator')); ?>') {
                $input.val(fullKey);
                $(this).text('<?php echo esc_js(__('Hide Key', 'certificate-generator')); ?>');
            } else {
                $input.val(fullKey.substring(0, 8) + '...' + fullKey.substring(fullKey.length - 8));
                $(this).text('<?php echo esc_js(__('Show Full Key', 'certificate-generator')); ?>');
            }
        });

        // Initialize key display with masked version
        if ($('#certificate_generator_api_key').val()) {
            const fullKey = $('#certificate_generator_api_key').val();
            $('#certificate_generator_api_key_display').val(fullKey);
        }

        $('#copy-api-key').on('click', function() {
             const apiKey = $('#certificate_generator_api_key').val();
             const $button = $(this);
             const originalText = $button.text();

             navigator.clipboard.writeText(apiKey).then(function() {
                 $button.text('<?php echo esc_js(__('Copied!', 'certificate-generator')); ?>');
                 setTimeout(function() {
                     $button.text(originalText);
                 }, 2000);
             }).catch(function() {
                 // Fallback for older browsers
                 const textArea = document.createElement('textarea');
                 textArea.value = apiKey;
                 document.body.appendChild(textArea);
                 textArea.select();
                 document.execCommand('copy');
                 document.body.removeChild(textArea);
                 $button.text('<?php echo esc_js(__('Copied!', 'certificate-generator')); ?>');
                 setTimeout(function() {
                     $button.text(originalText);
                 }, 2000);
             });
         });

         // Delete API key handler
         $('#delete-api-key').on('click', function() {
             if (!confirm('<?php echo esc_js(__('Are you sure you want to delete the API key? This will permanently remove the key and disable API access.', 'certificate-generator')); ?>')) {
                 return;
             }

             console.log('Deleting API key');
             $.ajax({
                 url: ajaxurl,
                 type: 'POST',
                 data: {
                     action: 'certificate_generator_delete_api_key',
                     nonce: '<?php echo wp_create_nonce("delete_api_key_nonce"); ?>'
                 },
                 success: function(response) {
                     console.log('Delete AJAX response:', response);

                     if (response.success) {
                         // Clear the UI
                         $('#certificate_generator_api_key').val('');
                         $('#certificate_generator_api_key_display').val('');
                         $('.api-key-container, .api-key-actions').hide().removeClass('visible');
                         $('#generate-api-key').show();

                         // Disable API access checkbox
                         $('#certificate_generator_api_key_enabled').prop('checked', false);

                         alert('<?php echo esc_js(__('API key deleted successfully! The page will now reload.', 'certificate-generator')); ?>');

                         // Reload page to reflect changes
                         setTimeout(function() {
                             location.reload();
                         }, 500);
                     } else {
                         alert('<?php echo esc_js(__('Failed to delete API key. Please try again or contact support.', 'certificate-generator')); ?>');
                     }
                 },
                 error: function() {
                     alert('<?php echo esc_js(__('Failed to delete API key. Please try again or contact support.', 'certificate-generator')); ?>');
                 }
             });
         });

         // Show API key containers if key exists
        if ($('#certificate_generator_api_key').val()) {
            $('.api-key-container, .api-key-actions').addClass('visible');
            $('#generate-api-key').hide();
        } else {
            // If no API key exists, show the generate button and hide containers
            $('.api-key-container, .api-key-actions').removeClass('visible');
            $('#generate-api-key').show();
        }

        async function generateSecureApiKey() {
            const array = new Uint8Array(32);
            window.crypto.getRandomValues(array);
            return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
        }
    });
    </script>
    <?php
}

/**
 * Check if WP Mail SMTP plugin is active AND properly configured
 *
 * @return bool Whether WP Mail SMTP is active and configured
 */
function certificate_generator_is_wp_mail_smtp_active() {
    // Make sure the function exists before using it
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Check if plugin is active
    $is_active = is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') ||
                 is_plugin_active('wp-mail-smtp-pro/wp_mail_smtp.php');

    if (!$is_active) {
        return false;
    }

    // Check if WP Mail SMTP is properly configured
    // Method 1: Check WP Mail SMTP Free version
    if (function_exists('wp_mail_smtp')) {
        $options = get_option('wp_mail_smtp', []);

        // Check if a mailer is configured (not 'mail')
        if (isset($options['mail']['mailer']) && $options['mail']['mailer'] !== 'mail') {
            error_log('Certificate Generator: WP Mail SMTP is active and configured with mailer: ' . $options['mail']['mailer']);
            return true;
        }
    }

    // Method 2: Check WP Mail SMTP Pro version
    if (class_exists('WPMailSMTP\\Options')) {
        try {
            $mailer = \WPMailSMTP\Options::init()->get('mail', 'mailer');
            if ($mailer && $mailer !== 'mail') {
                error_log('Certificate Generator: WP Mail SMTP Pro is active and configured with mailer: ' . $mailer);
                return true;
            }
        } catch (Exception $e) {
            error_log('Certificate Generator: Error checking WP Mail SMTP Pro configuration: ' . $e->getMessage());
        }
    }

    // Plugin is active but not properly configured
    error_log('Certificate Generator: WP Mail SMTP is active but NOT properly configured. Mailer is set to default PHP mail()');
    return false;
}

/**
 * API Debug Dashboard Function
 * Provides comprehensive debugging information for API functionality
 */
function certificate_generator_api_debug_dashboard() {
    // Check if user is logged in and has admin capabilities
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get current API settings
    $api_key = get_option('certificate_generator_api_key', '');
    $api_enabled = get_option('certificate_generator_api_key_enabled', false);

    // Get raw database values
    global $wpdb;
    $api_key_raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'certificate_generator_api_key'));
    $api_enabled_raw = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'certificate_generator_api_key_enabled'));

    ob_start();
    ?>
    <div class="wrap">
        <h1>API Debug Dashboard</h1>
        <p>This dashboard provides a central location for all API debugging tools and information.</p>

        <div class="notice notice-warning">
            <p><strong>Warning:</strong> These debug tools are for development and troubleshooting purposes only. They should be removed in production environments.</p>
        </div>

        <h2>API Status Overview</h2>
        <div class="dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); grid-gap: 20px;">
            <div class="postbox">
                <h3 class="hndle">API Access</h3>
                <div class="inside">
                    <?php if ($api_enabled): ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>Enabled</strong></p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Disabled</strong></p>
                        <p>API access is currently disabled. Enable it in the settings.</p>
                    <?php endif; ?>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate_generator_settings&tab=api')); ?>" class="button button-secondary">Configure</a></p>
                </div>
            </div>

            <div class="postbox">
                <h3 class="hndle">API Key</h3>
                <div class="inside">
                    <?php if ($api_key): ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>Generated</strong></p>
                        <p>Key: <code><?php echo esc_html(substr($api_key, 0, 8) . '...' . substr($api_key, -8)); ?></code></p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Not Generated</strong></p>
                        <p>No API key has been generated yet.</p>
                    <?php endif; ?>
                    <p><a href="<?php echo esc_url(admin_url('options-general.php?page=certificate_generator_settings&tab=api')); ?>" class="button button-secondary">Manage Key</a></p>
                </div>
            </div>

            <div class="postbox">
                <h3 class="hndle">REST API</h3>
                <div class="inside">
                    <?php
                    $rest_available = site_url('/wp-json/') ? true : false;
                    if ($rest_available):
                    ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>Available</strong></p>
                        <p>REST API is properly configured.</p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Unavailable</strong></p>
                        <p>REST API appears to be disabled or misconfigured.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="postbox">
                <h3 class="hndle">Database Status</h3>
                <div class="inside">
                    <?php
                    $db_status_ok = ($api_key_raw !== null && ($api_enabled_raw !== null || $api_enabled_raw === '0'));
                    if ($db_status_ok):
                    ?>
                        <p><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <strong>OK</strong></p>
                        <p>Database options are properly stored.</p>
                    <?php else: ?>
                        <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <strong>Issues Detected</strong></p>
                        <p>There may be issues with the database options.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="postbox">
            <h3 class="hndle">API Settings Details</h3>
            <div class="inside">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>Value</th>
                            <th>Raw Database Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>API Access Enabled</td>
                            <td><?php echo $api_enabled ? 'Yes' : 'No'; ?></td>
                            <td><code><?php echo esc_html(var_export($api_enabled_raw, true)); ?></code></td>
                        </tr>
                        <tr>
                            <td>API Key</td>
                            <td>
                                <?php if ($api_key): ?>
                                    <div class="api-key" style="font-family: monospace; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px; word-break: break-all;">
                                        <?php echo esc_html($api_key); ?>
                                    </div>
                                <?php else: ?>
                                    <em>No API key has been generated</em>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo $api_key_raw ? esc_html(strlen($api_key_raw) > 20 ? substr($api_key_raw, 0, 10) . '...' . substr($api_key_raw, -10) : $api_key_raw) : 'NULL'; ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="postbox">
            <h3 class="hndle">API Endpoints</h3>
            <div class="inside">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Method</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code><?php echo esc_url(site_url('/wp-json/certificate-generator/v1/health-check')); ?></code></td>
                            <td>GET</td>
                            <td>Health check endpoint to verify API connectivity</td>
                        </tr>
                        <tr>
                            <td><code><?php echo esc_url(site_url('/wp-json/certificate-generator/v1/issue-certificate')); ?></code></td>
                            <td>POST</td>
                            <td>Issue a new certificate</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="postbox">
            <h3 class="hndle">Troubleshooting Information</h3>
            <div class="inside">
                <h4>Common Issues</h4>
                <ol>
                    <li><strong>API Key Not Generating:</strong> Check for JavaScript errors in the browser console. The new API key fix script should resolve this issue.</li>
                    <li><strong>API Key Not Saving:</strong> Verify that the form is submitting correctly and that the database is functioning properly.</li>
                    <li><strong>API Access Not Working:</strong> Ensure that both the API key is generated and API access is enabled.</li>
                    <li><strong>REST API Errors:</strong> Check for security plugins that might be blocking REST API access.</li>
                </ol>

                <h4>JavaScript Console Commands</h4>
                <p>You can use these commands in your browser's developer console to debug API key issues:</p>
                <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 3px; overflow: auto; max-height: 400px;">
                    // Check if API key field exists


                    // Check if API key enabled field exists
                    console.log('API Key Enabled Field:', document.getElementById('certificate_generator_api_key_enabled'));

                    // Generate a test API key
                    function generateTestKey() {
                        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                        let result = '';
                        for (let i = 0; i < 64; i++) {
                            result += chars.charAt(Math.floor(Math.random() * chars.length));
                        }
                        console.log('Generated Test Key:', result);
                        return result;
                    }
                    generateTestKey();
                </pre>

                <h4>PHP Debugging</h4>
                <p>Add this code to your theme's functions.php file for additional debugging:</p>
                <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 3px; overflow: auto; max-height: 400px;">
                    // Debug API key settings
                    add_action('admin_footer', function() {
                        if (is_admin()) {
                            echo '&lt;div style="display:none;"&gt;';
                            echo 'API Key: ' . esc_html(get_option('certificate_generator_api_key', 'Not set')) . '&lt;br&gt;';
                            echo 'API Enabled: ' . esc_html(var_export(get_option('certificate_generator_api_key_enabled', false), true)) . '&lt;br&gt;';
                            echo '&lt;/div&gt;';
                        }
                    });
                </pre>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>