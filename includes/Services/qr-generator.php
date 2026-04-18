<?php
if (!defined('ABSPATH')) exit;

class CG_QR_Code_Generator {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function generate_qr_data($certificate_data, $serial_number = '') {
        $base_url = $this->get_verify_page_url();
        return add_query_arg('serial_number', $serial_number, $base_url);
    }

    private function get_verify_page_url(): string {
        $cached = get_transient('cg_verify_page_url');
        if ($cached) return $cached;

        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish' AND post_type = 'page'
             AND post_content LIKE '%[cg_verify_certificate%'
             LIMIT 1"
        );

        $url = $page_id ? get_permalink($page_id) : home_url('/');
        set_transient('cg_verify_page_url', $url, HOUR_IN_SECONDS);
        return $url;
    }

    public function generate_qr_image($data, $size = 150, $error_correction = 'L') {
        $qr_lib_path = CERTIFICATE_GENERATOR_PATH . 'lib/phpqrcode/qrlib.php';
        
        if (!file_exists($qr_lib_path)) {
            return $this->generate_qr_fallback($data, $size);
        }

        require_once $qr_lib_path;

        $ec_levels = ['L' => QR_ECLEVEL_L, 'M' => QR_ECLEVEL_M, 'Q' => QR_ECLEVEL_Q, 'H' => QR_ECLEVEL_H];
        $ec_level = $ec_levels[$error_correction] ?? QR_ECLEVEL_L;

        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/cg-qr-codes/';
        
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }

        $filename = 'qr_' . md5($data . time()) . '.png';
        $filepath = $qr_dir . $filename;

        // Suppress PHP 8.x deprecation notices from legacy phpqrcode library
        $prev_error = error_reporting();
        error_reporting($prev_error & ~E_DEPRECATED);
        QRcode::png($data, $filepath, $ec_level, 10, 2);
        error_reporting($prev_error);

        if (file_exists($filepath)) {
            return $filepath;
        }

        return false;
    }

    private function generate_qr_fallback($data, $size) {
        $api_url = 'https://api.qrserver.com/v1/create-qr-code/';
        $api_url .= '?size=' . $size . 'x' . $size;
        $api_url .= '&data=' . urlencode($data);
        $api_url .= '&ecc=L';

        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/cg-qr-codes/';
        
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }

        $filename = 'qr_' . md5($data . time()) . '.png';
        $filepath = $qr_dir . $filename;

        file_put_contents($filepath, $body);

        return file_exists($filepath) ? $filepath : false;
    }

    public function add_qr_to_pdf($pdf, $qr_data, $position_x, $position_y, $size_mm = 15) {
        $qr_image_path = $this->generate_qr_image($qr_data, 300);
        
        if (!$qr_image_path || !file_exists($qr_image_path)) {
            return false;
        }

        $size_mm = max(10, min(50, $size_mm));
        
        $pdf->Image($qr_image_path, $position_x, $position_y, $size_mm, $size_mm);

        return true;
    }

    public function add_serial_to_pdf($pdf, $serial_number, $position_x, $position_y, $font_size = 10, $font_style = 'helvetica') {
        if (empty($serial_number)) {
            return false;
        }

        // Normalize font style - convert invalid fonts to valid ones
        $valid_fonts = ['helvetica', 'times', 'courier', 'arial', 'helveticab', 'timesb', 'courierb'];
        if (!in_array($font_style, $valid_fonts)) {
            $font_style = 'helvetica';
        }
        
        // Use FontManager to properly load fonts
        $font_manager = CertificateGenerator_FontManager::getInstance();
        $loaded_font = $font_manager->add_font_to_pdf($pdf, $font_style, 'B', $font_size);
        
        $pdf->SetTextColor(0, 0, 0);
        
        $text = 'Serial: ' . $serial_number;
        $text_width = $pdf->GetStringWidth($text);
        
        $pdf->Text($position_x - ($text_width / 2), $position_y, $text);

        return true;
    }

    public function register_template_meta_fields() {
        add_action('add_meta_boxes', function() {
            add_meta_box(
                'cg_qr_serial_settings',
                'QR Code & Serial Number Settings',
                [$this, 'render_qr_serial_meta_box'],
                'certificates',
                'normal',
                'high'
            );

            add_meta_box(
                'cg_expiration_settings',
                'Certificate Expiration Settings',
                [$this, 'render_expiration_meta_box'],
                'certificates',
                'side',
                'default'
            );

            add_meta_box(
                'cg_position_preview',
                'QR & Serial Position Preview',
                [$this, 'render_position_preview'],
                'certificates',
                'normal',
                'default'
            );
        });

        add_action('save_post_certificates', [$this, 'save_qr_serial_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_draggable_assets']);
    }

    public function enqueue_draggable_assets($hook) {
        global $post;
        if ($hook === 'post.php' && $post && $post->post_type === 'certificates') {
            wp_enqueue_style('cg-draggable-css', CERTIFICATE_GENERATOR_URL . 'assets/css/cg-draggable.css', [], '1.0.0');
            wp_enqueue_script('cg-draggable-js', CERTIFICATE_GENERATOR_URL . 'assets/js/cg-draggable.js', ['jquery'], '1.0.0', true);
            wp_localize_script('cg-draggable-js', 'cgDraggable', ['enabled' => true]);
        }
    }

    public function render_position_preview($post) {
        ?>
        <div id="cg-certificate-preview" style="position: relative; width: 100%; height: 400px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #646970; font-size: 14px; text-align: center; pointer-events: none;">
                <p>Draggable QR Code & Serial Number elements will appear here<br>when enabled in settings above.</p>
                <p style="font-size: 12px; color: #999;">Scale: 1mm ≈ 3.78px</p>
            </div>
        </div>
        <p class="description" style="margin-top: 8px;">Drag the QR code or serial number elements to adjust their position on the certificate. Values update automatically.</p>
        <?php
    }

    public function render_qr_serial_meta_box($post) {
        wp_nonce_field('cg_qr_serial_nonce', 'cg_qr_serial_nonce');

        $qr_enabled = get_post_meta($post->ID, 'qr_enabled', true);
        $qr_size = get_post_meta($post->ID, 'qr_size', true) ?: 15;
        $qr_position_x = get_post_meta($post->ID, 'qr_position_x', true) ?: 250;
        $qr_position_y = get_post_meta($post->ID, 'qr_position_y', true) ?: 180;
        $qr_error_correction = get_post_meta($post->ID, 'qr_error_correction', true) ?: 'L';
        $qr_data_fields = get_post_meta($post->ID, 'qr_data_fields', true) ?: '';

        $serial_display = get_post_meta($post->ID, 'serial_number_display', true);
        $serial_position_x = get_post_meta($post->ID, 'serial_number_position_x', true) ?: 105;
        $serial_position_y = get_post_meta($post->ID, 'serial_number_position_y', true) ?: 200;
        $serial_font_size = get_post_meta($post->ID, 'serial_number_font_size', true) ?: 10;
        ?>
        <div class="cg-settings-grid">
            <div class="cg-settings-section">
                <h3>QR Code Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="qr_enabled">Enable QR Code</label></th>
                        <td>
                            <input type="checkbox" id="qr_enabled" name="qr_enabled" value="1" <?php checked($qr_enabled, '1'); ?>>
                            <p class="description">Add a QR code to certificates generated from this template</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qr_size">QR Size (mm)</label></th>
                        <td>
                            <input type="number" id="qr_size" name="qr_size" value="<?php echo esc_attr($qr_size); ?>" min="10" max="50" step="1" class="small-text">
                            <p class="description">Size of QR code on the certificate (10-50mm)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qr_position_x">QR Position X (mm)</label></th>
                        <td>
                            <input type="number" id="qr_position_x" name="qr_position_x" value="<?php echo esc_attr($qr_position_x); ?>" step="0.1" class="regular-text">
                            <p class="description">Horizontal position from left edge</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qr_position_y">QR Position Y (mm)</label></th>
                        <td>
                            <input type="number" id="qr_position_y" name="qr_position_y" value="<?php echo esc_attr($qr_position_y); ?>" step="0.1" class="regular-text">
                            <p class="description">Vertical position from top edge</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qr_error_correction">Error Correction</label></th>
                        <td>
                            <select id="qr_error_correction" name="qr_error_correction">
                                <option value="L" <?php selected($qr_error_correction, 'L'); ?>>Low (7%)</option>
                                <option value="M" <?php selected($qr_error_correction, 'M'); ?>>Medium (15%)</option>
                                <option value="Q" <?php selected($qr_error_correction, 'Q'); ?>>Quartile (25%)</option>
                                <option value="H" <?php selected($qr_error_correction, 'H'); ?>>High (30%)</option>
                            </select>
                            <p class="description">Higher correction = more robust but larger QR code</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="qr_data_fields">QR Data Fields</label></th>
                        <td>
                            <textarea id="qr_data_fields" name="qr_data_fields" rows="3" class="large-text" placeholder="student_name,certificate_type,issue_date"><?php echo esc_textarea($qr_data_fields); ?></textarea>
                            <p class="description">Comma-separated field names to include in QR data. Leave empty for default verification URL.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="cg-settings-section">
                <h3>Serial Number Display</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="serial_number_display">Show Serial on Certificate</label></th>
                        <td>
                            <input type="checkbox" id="serial_number_display" name="serial_number_display" value="1" <?php checked($serial_display, '1'); ?>>
                            <p class="description">Display the serial number text on the generated certificate</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="serial_position_x">Serial Position X (mm)</label></th>
                        <td>
                            <input type="number" id="serial_position_x" name="serial_position_x" value="<?php echo esc_attr($serial_position_x); ?>" step="0.1" class="regular-text">
                            <p class="description">Horizontal position from left edge</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="serial_position_y">Serial Position Y (mm)</label></th>
                        <td>
                            <input type="number" id="serial_position_y" name="serial_position_y" value="<?php echo esc_attr($serial_position_y); ?>" step="0.1" class="regular-text">
                            <p class="description">Vertical position from top edge</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="serial_font_size">Serial Font Size</label></th>
                        <td>
                            <input type="number" id="serial_font_size" name="serial_number_font_size" value="<?php echo esc_attr($serial_font_size); ?>" min="6" max="24" class="small-text">
                            <p class="description">Font size for serial number text</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <style>
            .cg-settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            .cg-settings-section { background: #f9f9f9; padding: 15px; border-radius: 4px; }
            .cg-settings-section h3 { margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
            .form-table th { width: 200px; }
        </style>
        <?php
    }

    public function render_expiration_meta_box($post) {
        wp_nonce_field('cg_expiration_nonce', 'cg_expiration_nonce');

        $exp_value = get_post_meta($post->ID, 'expiration_period_value', true) ?: '';
        $exp_unit = get_post_meta($post->ID, 'expiration_period_unit', true) ?: 'never';
        ?>
        <table class="form-table">
            <tr>
                <th><label for="expiration_period_unit">Validity Period</label></th>
                <td>
                    <select id="expiration_period_unit" name="expiration_period_unit" style="width: 100%;">
                        <option value="never" <?php selected($exp_unit, 'never'); ?>>Never Expires</option>
                        <option value="days" <?php selected($exp_unit, 'days'); ?>>Days</option>
                        <option value="months" <?php selected($exp_unit, 'months'); ?>>Months</option>
                        <option value="years" <?php selected($exp_unit, 'years'); ?>>Years</option>
                    </select>
                </td>
            </tr>
            <tr id="cg-exp-value-row" style="<?php echo $exp_unit === 'never' ? 'display:none;' : ''; ?>">
                <th><label for="expiration_period_value">Duration</label></th>
                <td>
                    <input type="number" id="expiration_period_value" name="expiration_period_value" value="<?php echo esc_attr($exp_value); ?>" min="1" class="small-text">
                    <p class="description">Certificate validity duration</p>
                </td>
            </tr>
        </table>

        <script>
            jQuery(document).ready(function($) {
                $('#expiration_period_unit').on('change', function() {
                    if ($(this).val() === 'never') {
                        $('#cg-exp-value-row').hide();
                    } else {
                        $('#cg-exp-value-row').show();
                    }
                });
            });
        </script>
        <?php
    }

    public function save_qr_serial_settings($post_id) {
        if (!isset($_POST['cg_qr_serial_nonce']) || !wp_verify_nonce($_POST['cg_qr_serial_nonce'], 'cg_qr_serial_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = [
            'qr_enabled', 'qr_size', 'qr_position_x', 'qr_position_y', 'qr_error_correction', 'qr_data_fields',
            'serial_number_display', 'serial_number_position_x', 'serial_number_position_y', 'serial_number_font_size'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        if (!isset($_POST['cg_expiration_nonce']) || !wp_verify_nonce($_POST['cg_expiration_nonce'], 'cg_expiration_nonce')) {
            return;
        }

        if (isset($_POST['expiration_period_unit'])) {
            update_post_meta($post_id, 'expiration_period_unit', sanitize_text_field($_POST['expiration_period_unit']));
        }
        if (isset($_POST['expiration_period_value'])) {
            update_post_meta($post_id, 'expiration_period_value', absint($_POST['expiration_period_value']));
        }
    }
}