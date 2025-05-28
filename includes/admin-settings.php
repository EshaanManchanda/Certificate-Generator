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
        'certificate-generator-settings',
        'certificate_generator_settings_page'
    );
}

// Initialize settings
add_action('admin_init', 'certificate_generator_settings_init');
function certificate_generator_settings_init() {
    register_setting('certificate_generator_settings', 'certificate_generator_settings_email', [
        'sanitize_callback' => 'certificate_generator_sanitize_settings',
    ]);

    add_settings_section(
        'certificate_generator_settings_section',
        __('Certificate Generator Settings', 'certificate-generator'),
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
    
    return $output;
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
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized user', 'certificate-generator'));
    }

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
    ?>
    <div class="wrap">
        <h1><?php _e('Certificate Generator Settings', 'certificate-generator'); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('certificate_generator_settings');
            do_settings_sections('certificate_generator_settings');
            submit_button();
            ?>
        </form>

        <div id="certificate-generator-cache-section" style="margin-top: 30px;">
            <h3><?php _e('Clear Cache & Certificates', 'certificate-generator'); ?></h3>
            <p><?php _e('Click the button below to clear all cached results and generated certificate files.', 'certificate-generator'); ?></p>
            <button id="clear-cache-btn" class="button button-secondary">
                <?php _e('Clear Cache & Certificates', 'certificate-generator'); ?>
            </button>
            <div id="clear-cache-message"></div>
        </div>
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