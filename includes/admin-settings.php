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
        foreach (['subject', 'title', 'message', 'reply_to', 'cc', 'bcc'] as $field) {
            $key = $prefix . $field;
            if (isset($input[$key])) {
                $output[$key] = sanitize_text_field($input[$key]);
            }
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
            'desc' => __('Message body. Use {name} as placeholder for recipient name.', 'certificate-generator')
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
            echo '<textarea name="certificate_generator_settings_email[' . esc_attr($key) . ']" rows="4" style="width: 100%;">' . esc_textarea($value) . '</textarea>';
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
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cert_search_%'");

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
    // Get and validate current tab
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
            <form action="options.php" method="post">
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
        echo '<button type="button" id="generate-api-key" class="button button-secondary">' . esc_html__('Generate API Key', 'certificate-generator') . '</button>';
        echo '<p class="description">' . esc_html__('Click to generate a secure API key for external integrations.', 'certificate-generator') . '</p>';
    } else {
        echo '<input type="text" id="certificate_generator_api_key_display" value="' . esc_attr(substr($api_key, 0, 8) . '...' . substr($api_key, -8)) . '" readonly style="width: 300px;" />';
        echo '<input type="hidden" id="certificate_generator_api_key" name="certificate_generator_api_key" value="' . esc_attr($api_key) . '" />';
        echo '<button type="button" id="show-api-key" class="button button-secondary" style="margin-left: 10px;">' . esc_html__('Show Full Key', 'certificate-generator') . '</button>';
        echo '<button type="button" id="regenerate-api-key" class="button button-secondary" style="margin-left: 10px;">' . esc_html__('Regenerate', 'certificate-generator') . '</button>';
        echo '<button type="button" id="copy-api-key" class="button button-secondary" style="margin-left: 10px;">' . esc_html__('Copy', 'certificate-generator') . '</button>';
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
            if ($(this).attr('id') === 'regenerate-api-key') {
                if (!confirm('<?php echo esc_js(__('Are you sure you want to regenerate the API key? This will invalidate the current key.', 'certificate-generator')); ?>')) {
                    return;
                }
            }
            
            // Generate a secure random API key
            const apiKey = generateSecureApiKey();
            $('#certificate_generator_api_key').val(apiKey);
            $('#certificate_generator_api_key_display').val(apiKey.substring(0, 8) + '...' + apiKey.substring(apiKey.length - 8));
            
            // Update the UI
            $('#generate-api-key').hide();
            $('#certificate_generator_api_key_display, #show-api-key, #regenerate-api-key, #copy-api-key').show();
            
            alert('<?php echo esc_js(__('API key generated successfully! Remember to save your settings.', 'certificate-generator')); ?>');
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
        
        $('#copy-api-key').on('click', function() {
            const apiKey = $('#certificate_generator_api_key').val();
            navigator.clipboard.writeText(apiKey).then(function() {
                alert('<?php echo esc_js(__('API key copied to clipboard!', 'certificate-generator')); ?>');
            }).catch(function() {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = apiKey;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('<?php echo esc_js(__('API key copied to clipboard!', 'certificate-generator')); ?>');
            });
        });
        
        function generateSecureApiKey() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            for (let i = 0; i < 64; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        }
    });
    </script>
    <?php
}

/**
 * Check if WP Mail SMTP plugin is active
 *
 * @return bool Whether WP Mail SMTP is active
 */
function certificate_generator_is_wp_mail_smtp_active() {
    // Make sure the function exists before using it
    if (!function_exists('is_plugin_active')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    return is_plugin_active('wp-mail-smtp/wp_mail_smtp.php') || is_plugin_active('wp-mail-smtp-pro/wp_mail_smtp.php');
}

