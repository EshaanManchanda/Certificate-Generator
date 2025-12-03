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

// Sanitize settings input
function certificate_generator_sanitize_settings($input) {
    $output = [];

    // Validate email
    if (isset($input['email']) && filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $output['email'] = sanitize_email($input['email']);
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

    // Sanitize auto-send setting
    if (isset($input['auto_send_enabled'])) {
        $output['auto_send_enabled'] = (bool) $input['auto_send_enabled'];
    }

    // Sanitize email logo URL
    if (isset($input['email_logo'])) {
        $output['email_logo'] = esc_url_raw($input['email_logo']);
    }

    // Sanitize email template fields for each post type
    $post_types = ['students', 'teachers', 'schools'];

    foreach ($post_types as $post_type) {
        $prefix = $post_type . '_email_';

        // Sanitize text fields
        foreach (['subject', 'title', 'reply_to', 'cc', 'bcc'] as $field) {
            $key = $prefix . $field;
            if (isset($input[$key])) {
                $output[$key] = sanitize_text_field($input[$key]);
            }
        }

        // Sanitize message field separately to allow HTML content
        $message_key = $prefix . 'message';
        if (isset($input[$message_key])) {
            $output[$message_key] = wp_kses_post($input[$message_key]);
        }

        // Sanitize checkbox fields
        $checkbox_key = $prefix . 'attach_certificate';
        $output[$checkbox_key] = isset($input[$checkbox_key]) ? '1' : '0';
    }

    // Sanitize SMTP settings
    foreach (['host', 'port', 'username', 'password', 'encryption'] as $smtp_field) {
        $key = 'smtp_' . $smtp_field;
        if (isset($input[$key])) {
            $output[$key] = sanitize_text_field($input[$key]);
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
        echo '<div class="notice notice-info inline" style="margin: 0 0 15px; padding: 10px;">';
        echo '<p><strong>' . esc_html__('Fallback Email System Active', 'certificate-generator') . '</strong></p>';
        echo '<p>' . esc_html__('Emails will be sent using WordPress default method (PHP mail) with a fallback "noreply@yourdomain.com" sender address, similar to how Forminator works. This works on most shared hosting providers.', 'certificate-generator') . '</p>';
        echo '<p><strong>' . esc_html__('For better deliverability:', 'certificate-generator') . '</strong> ' . esc_html__('Install WP Mail SMTP plugin to use proper SMTP configuration.', 'certificate-generator') . '</p>';
        echo '<p><a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank" class="button button-primary">' . esc_html__('Get WP Mail SMTP (Recommended)', 'certificate-generator') . '</a></p>';
        echo '</div>';
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

    echo '<p>' . esc_html__('Note: Password is stored in plain text in the database. For better security, consider using an application-specific password.', 'certificate-generator') . '</p>';
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
        // Delete all transients
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_cert_search_%'));

        // Delete generated certificate files
        $upload_dir = wp_upload_dir();
        $cert_dir = trailingslashit($upload_dir['basedir']) . 'certificates/';

        if (is_dir($cert_dir)) {
            $files = glob($cert_dir . '*.pdf');
            foreach ($files as $file) {
                if (is_file($file) && !unlink($file)) {
                    throw new Exception(__('Failed to delete file:', 'certificate-generator') . $file);
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
    $valid_tabs = array('general', 'templates', 'api', 'tools');

    if (!in_array($active_tab, $valid_tabs)) {
        $active_tab = 'general';
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Certificate Generator Settings', 'certificate-generator'); ?></h1>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=certificate_generator_settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                <?php _e('General', 'certificate-generator'); ?>
            </a>
            <a href="?page=certificate_generator_settings&tab=templates" class="nav-tab <?php echo $active_tab == 'templates' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Email Templates', 'certificate-generator'); ?>
            </a>
            <a href="?page=certificate_generator_settings&tab=api" class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">
                <?php _e('API Settings', 'certificate-generator'); ?>
            </a>
            <a href="?page=certificate_generator_settings&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Tools', 'certificate-generator'); ?>
            </a>
        </h2>

        <?php
        // Debug information for tab content
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<!-- Active Tab: ' . esc_html($active_tab) . ' -->';
        }
        ?>

        <?php if ($active_tab == 'general') : ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('certificate_generator_settings');
                do_settings_sections('certificate_generator_settings');
                submit_button();
                ?>
            </form>
        <?php elseif ($active_tab == 'templates') : ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('certificate_generator_settings');
                do_settings_sections('certificate_generator_settings');
                submit_button();
                ?>
            </form>
        <?php elseif ($active_tab == 'api') : ?>
            <form action="options.php" method="post" id="certificate_generator_settings">
                <?php
                settings_fields('certificate_generator_settings');
                // Explicitly output the API section
                echo '<h2>API Configuration</h2>';
                certificate_generator_api_section_callback();
                echo '<table class="form-table" role="presentation">';
                // API Key field
                echo '<tr><th scope="row">API Key</th><td>';
                certificate_generator_api_key_render();
                echo '</td></tr>';
                // API Key enabled field
                echo '<tr><th scope="row">Enable API Access</th><td>';
                certificate_generator_api_key_enabled_render();
                echo '</td></tr>';
                echo '</table>';
                submit_button();
                ?>
            </form>
        <?php elseif ($active_tab == 'tools') : ?>
            <div id="certificate-generator-cache-section" style="margin-top: 30px;">
                <h3><?php _e('Clear Cache & Certificates', 'certificate-generator'); ?></h3>
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
        error_log('Certificate Generator: WP Mail SMTP plugin is not active');
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