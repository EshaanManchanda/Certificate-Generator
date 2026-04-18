<?php
if (!defined('ABSPATH')) exit;

class CG_Bulk_Serial_Generator {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('admin_menu', [$this, 'add_bulk_serial_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_cg_bulk_generate_serials', [$this, 'ajax_bulk_generate']);
        add_action('wp_ajax_cg_bulk_generate_status', [$this, 'ajax_get_status']);
    }

    public function add_bulk_serial_menu() {
        add_submenu_page(
            'cg-dashboard',
            'Bulk Serial Numbers',
            'Bulk Serials',
            'manage_options',
            'cg-bulk-serials',
            [$this, 'render_bulk_serial_page']
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'cg-bulk-serials') === false) return;

        wp_enqueue_style('cg-bulk-css', CERTIFICATE_GENERATOR_URL . 'assets/css/bulk-serial-style.css', [], '1.0.0');
        wp_enqueue_script('cg-bulk-js', CERTIFICATE_GENERATOR_URL . 'assets/js/bulk-serial-script.js', ['jquery'], '1.0.0', true);

        wp_localize_script('cg-bulk-js', 'cgBulkSerial', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cg_bulk_serial_nonce')
        ]);
    }

    public function render_bulk_serial_page() {
        if (!current_user_can('manage_options')) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'certificate_generator';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        $stats = [
            'total_without_serial' => 0,
            'total_with_serial' => 0,
            'total_posts' => 0
        ];

        if ($table_exists) {
            $stats['total_without_serial'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE (serial_number IS NULL OR serial_number = '')");
            $stats['total_with_serial'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE serial_number IS NOT NULL AND serial_number != ''");
        }

        $post_types = ['students', 'teachers', 'schools'];
        foreach ($post_types as $pt) {
            $count = wp_count_posts($pt);
            $stats['total_posts'] += isset($count->publish) ? (int) $count->publish : 0;
        }

        // Count posts that have serial numbers in post meta
        $posts_with_serial = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = 'certificate_serial_number' AND pm.meta_value != ''
             AND p.post_status = 'publish' AND p.post_type IN ('students','teachers','schools')"
        );
        $stats['total_with_serial'] = max($stats['total_with_serial'], $posts_with_serial);
        $stats['total_without_serial'] = max(0, $stats['total_posts'] - $stats['total_with_serial']);

        // Fetch recent serials from DB table
        $recent_serials = [];
        if ($table_exists) {
            $recent_serials = $wpdb->get_results(
                "SELECT id, student_name, serial_number, certificate_type, generated_via, issued_at
                 FROM $table_name
                 WHERE serial_number IS NOT NULL AND serial_number != ''
                 ORDER BY id DESC LIMIT 50"
            );
        }
        ?>
        <div class="wrap cg-bulk-serial-page">
            <h1>Bulk Serial Number Generation</h1>

            <div class="cg-bulk-stats">
                <div class="cg-bulk-stat">
                    <span class="cg-bulk-stat-value"><?php echo number_format($stats['total_posts']); ?></span>
                    <span class="cg-bulk-stat-label">Total Certificate Posts</span>
                </div>
                <div class="cg-bulk-stat">
                    <span class="cg-bulk-stat-value"><?php echo number_format($stats['total_with_serial']); ?></span>
                    <span class="cg-bulk-stat-label">With Serial Number</span>
                </div>
                <div class="cg-bulk-stat cg-bulk-stat-warning">
                    <span class="cg-bulk-stat-value"><?php echo number_format($stats['total_without_serial']); ?></span>
                    <span class="cg-bulk-stat-label">Missing Serial Number</span>
                </div>
            </div>

            <div class="cg-bulk-form-section">
                <h2>Generate Serial Numbers</h2>
                <p>Generate unique serial numbers for all existing certificates that don't have one yet.</p>
                
                <div class="cg-bulk-options">
                    <label>
                        <input type="checkbox" id="cg-bulk-students" checked> Include Students
                    </label>
                    <label>
                        <input type="checkbox" id="cg-bulk-teachers" checked> Include Teachers
                    </label>
                    <label>
                        <input type="checkbox" id="cg-bulk-schools" checked> Include Schools
                    </label>
                </div>

                <button type="button" id="cg-bulk-generate-btn" class="button button-primary button-hero">
                    Generate Serial Numbers
                </button>

                <div id="cg-bulk-progress" style="display: none; margin-top: 20px;">
                    <div class="cg-bulk-progress-bar">
                        <div class="cg-bulk-progress-fill" style="width: 0%;"></div>
                    </div>
                    <p class="cg-bulk-progress-text">Processing... 0 / 0</p>
                    <p class="cg-bulk-progress-detail"></p>
                </div>

                <div id="cg-bulk-results" style="display: none; margin-top: 20px;">
                    <div class="notice notice-success">
                        <p><strong>Completed!</strong> <span id="cg-bulk-success-count"></span> serial numbers generated.</p>
                    </div>
                </div>
            </div>

            <div class="cg-bulk-table-section" style="margin-top: 30px;">
                <h2>Recently Generated Serial Numbers</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:60px;">ID</th>
                            <th>Name</th>
                            <th>Certificate Type</th>
                            <th>Serial Number</th>
                            <th>Generated Via</th>
                            <th>Issued At</th>
                        </tr>
                    </thead>
                    <tbody id="cg-bulk-recent-serials">
                        <?php if (empty($recent_serials)) : ?>
                            <tr>
                                <td colspan="6" class="cg-bulk-empty">No serial numbers found. Generate certificates or run bulk generation.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($recent_serials as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row->id); ?></td>
                                    <td><?php echo esc_html($row->student_name); ?></td>
                                    <td><?php echo esc_html($row->certificate_type ?: '—'); ?></td>
                                    <td><code><?php echo esc_html($row->serial_number); ?></code></td>
                                    <td><?php echo esc_html(ucfirst($row->generated_via ?: 'manual')); ?></td>
                                    <td><?php echo esc_html(cg_format_date($row->issued_at, true) ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function ajax_bulk_generate() {
        check_ajax_referer('cg_bulk_serial_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $post_types = [];
        if (!empty($_POST['students'])) $post_types[] = 'students';
        if (!empty($_POST['teachers'])) $post_types[] = 'teachers';
        if (!empty($_POST['schools'])) $post_types[] = 'schools';

        if (empty($post_types)) {
            wp_send_json_error('No post types selected');
        }

        $serial_gen = null;
        if (class_exists('CG_Serial_Number_Generator')) {
            $serial_gen = CG_Serial_Number_Generator::get_instance();
        }

        $results = ['success' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids'
            ]);

            foreach ($posts as $post_id) {
                $existing_serial = get_post_meta($post_id, 'certificate_serial_number', true);
                if (!empty($existing_serial)) {
                    $results['skipped']++;
                    continue;
                }

                $cert_type = get_post_meta($post_id, 'certificate_type', true);
                $name_key = ($post_type === 'teachers') ? 'teacher_name' : (($post_type === 'schools') ? 'school_name' : 'student_name');
                $name = get_post_meta($post_id, $name_key, true) ?: get_the_title($post_id);

                // Check if this student+type already has a serial in the DB table
                $serial = '';
                if (function_exists('cg_find_existing_serial') && $name && $cert_type) {
                    $serial = cg_find_existing_serial($name, $cert_type);
                }

                if (empty($serial)) {
                    if ($serial_gen) {
                        // Prepare student data for updating student table
                        $student_data_for_serial = [
                            'email' => get_post_meta($post_id, 'email', true) ?: '',
                            'student_name' => $name,
                        ];
                        $serial = $serial_gen->generate($cert_type, $student_data_for_serial);
                    } else {
                        $serial = 'CERT-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    }
                }

                if (!empty($serial)) {
                    update_post_meta($post_id, 'certificate_serial_number', $serial);
                    update_post_meta($post_id, 'certificate_issued_at', current_time('mysql'));

                    // Also persist to certificate_generator table for verification
                    if (function_exists('cg_insert_certificate_record')) {
                        cg_insert_certificate_record([
                            $name_key          => $name,
                            'certificate_type' => $cert_type,
                        ], $serial, 'bulk');
                    }

                    $results['success']++;
                    $results['details'][] = [
                        'post_id' => $post_id,
                        'post_type' => $post_type,
                        'name' => get_the_title($post_id),
                        'serial' => $serial,
                        'issued_at' => current_time('mysql')
                    ];
                } else {
                    $results['errors']++;
                }
            }
        }

        wp_send_json_success($results);
    }

    public function ajax_get_status() {
        check_ajax_referer('cg_bulk_serial_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'certificate_generator';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        $stats = ['total_without' => 0, 'total_with' => 0];
        if ($table_exists) {
            $stats['total_without'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE (serial_number IS NULL OR serial_number = '')");
            $stats['total_with'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE serial_number IS NOT NULL AND serial_number != ''");
        }

        wp_send_json_success($stats);
    }
}
