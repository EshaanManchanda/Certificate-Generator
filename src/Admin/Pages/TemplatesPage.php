<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

use CertificateGenerator\Database\CustomTables;

if (!defined('ABSPATH')) exit;

/**
 * Certificate Templates admin page — list + CRUD form backed by wp_cg_certificate_templates.
 */
class TemplatesPage {

    private string $slug       = 'cg-templates';
    private string $edit_slug  = 'cg-template-edit';
    private string $table_key  = 'certificate_templates';

    public function register(): void {
        add_submenu_page(
            'cg-dashboard',
            'Certificate Templates',
            'Templates',
            'manage_options',
            $this->slug,
            [$this, 'render_list']
        );
        add_submenu_page(
            '',
            'Add / Edit Template',
            'Add / Edit Template',
            'manage_options',
            $this->edit_slug,
            [$this, 'render_edit']
        );

        // Fix strip_tags(null): hidden pages (parent='') never get $GLOBALS['title'] set by WP.
        // admin_init fires before admin-header.php is included, so this runs before strip_tags($title).
        add_action('admin_init', function (): void {
            if ((isset($_GET['page']) && $_GET['page'] === $this->edit_slug)
                && empty($GLOBALS['title'])
            ) {
                $GLOBALS['title'] = 'Add / Edit Template';
            }
        });

        // Enqueue jQuery UI draggable on the template edit page
        add_action('admin_enqueue_scripts', function (string $hook): void {
            if ($hook !== 'admin_page_' . $this->edit_slug) return;
            wp_enqueue_script('jquery-ui-draggable');
        });
    }

    // ── List View ────────────────────────────────────────────────────────────

    public function render_list(): void {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions');

        global $wpdb;
        $table = CustomTables::instance()->get_table($this->table_key);
        $message = '';

        if (!empty($_POST['cg_delete_template_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cg_delete_template_nonce'])), 'cg_delete_template')) {
            $ids = array_map('absint', (array) ($_POST['template_ids'] ?? []));
            foreach ($ids as $id) {
                if ($id > 0) $wpdb->delete($table, ['id' => $id], ['%d']);
            }
            $message = count($ids) . ' template(s) deleted.';
        }

        $search   = sanitize_text_field($_GET['s'] ?? '');
        $type_f   = sanitize_text_field($_GET['type_filter'] ?? '');
        $status_f = sanitize_text_field($_GET['status_filter'] ?? '');
        $paged    = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page = 20;
        $allowed_order = ['template_name','certificate_type','event_date','orientation','status','created_at'];
        $orderby  = in_array($_GET['orderby'] ?? '', $allowed_order, true) ? sanitize_key($_GET['orderby']) : 'created_at';
        $order    = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $where = '1=1'; $params = [];
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (template_name LIKE %s OR certificate_type LIKE %s)';
            $params[] = $like; $params[] = $like;
        }
        if ($type_f)   { $where .= ' AND certificate_type = %s'; $params[] = $type_f; }
        if ($status_f) { $where .= ' AND status = %s'; $params[] = $status_f; }

        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
        $total     = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : $wpdb->get_var($count_sql));

        $offset   = ($paged - 1) * $per_page;
        $data_sql = "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $rows     = $wpdb->get_results($wpdb->prepare($data_sql, ...array_merge($params, [$per_page, $offset])), ARRAY_A);

        $cert_types = $wpdb->get_col("SELECT DISTINCT certificate_type FROM $table WHERE certificate_type != '' ORDER BY certificate_type");

        $total_pages = (int) ceil($total / $per_page);
        $list_url    = admin_url('admin.php?page=' . $this->slug);
        $edit_url    = admin_url('admin.php?page=' . $this->edit_slug);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Certificate Templates</h1>
            <a href="<?php echo esc_url($edit_url); ?>" class="page-title-action">Add New</a>

            <?php if ($message): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>

            <form method="get" style="margin:15px 0;">
                <input type="hidden" name="page" value="<?php echo esc_attr($this->slug); ?>">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name or type…" style="margin-right:5px;">
                <select name="type_filter">
                    <option value="">— All Types —</option>
                    <?php foreach ($cert_types as $c): ?>
                        <option value="<?php echo esc_attr($c); ?>" <?php selected($type_f, $c); ?>><?php echo esc_html($c); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status_filter" style="margin-left:5px;">
                    <option value="">— All Statuses —</option>
                    <option value="published" <?php selected($status_f, 'published'); ?>>Published</option>
                    <option value="draft" <?php selected($status_f, 'draft'); ?>>Draft</option>
                    <option value="archived" <?php selected($status_f, 'archived'); ?>>Archived</option>
                </select>
                <button type="submit" class="button">Filter</button>
                <?php if ($search || $type_f || $status_f): ?>
                    <a href="<?php echo esc_url($list_url); ?>" class="button">Clear</a>
                <?php endif; ?>
            </form>

            <form method="post" id="cg-templates-form">
                <?php wp_nonce_field('cg_delete_template', 'cg_delete_template_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value="">Bulk Actions</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="button action" id="doaction">Apply</button>
                    </div>
                    <div class="tablenav-pages" style="float:right;">
                        <span class="displaying-num"><?php echo number_format($total); ?> items</span>
                        <?php if ($total_pages > 1): ?>
                            <?php if ($paged > 1): ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $list_url)); ?>">‹</a><?php endif; ?>
                            <span style="margin:0 5px;">Page <?php echo $paged; ?> of <?php echo $total_pages; ?></span>
                            <?php if ($paged < $total_pages): ?><a class="button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $list_url)); ?>">›</a><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <td class="manage-column check-column"><input type="checkbox" id="cb-select-all"></td>
                        <?php
                        $cols = ['template_name'=>'Name','certificate_type'=>'Type','event_date'=>'Event Date','orientation'=>'Orientation','font_style'=>'Font','status'=>'Status'];
                        foreach ($cols as $col_key => $col_label):
                            $next_order = ($orderby === $col_key && $order === 'ASC') ? 'DESC' : 'ASC';
                            $sort_url   = add_query_arg(['orderby'=>$col_key,'order'=>$next_order,'paged'=>1], $list_url);
                        ?>
                        <th scope="col" class="<?php echo $orderby === $col_key ? 'sorted '.strtolower($order) : 'sortable desc'; ?>">
                            <a href="<?php echo esc_url($sort_url); ?>"><span><?php echo esc_html($col_label); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc"></span><span class="sorting-indicator desc"></span></span></a>
                        </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8"><em>No templates found. <a href="<?php echo esc_url($edit_url); ?>">Add one</a>.</em></td></tr>
                        <?php else: foreach ($rows as $row):
                            $row_id = (int) $row['id'];
                        ?>
                        <tr>
                            <th scope="row" class="check-column"><input class="cb-select" type="checkbox" name="template_ids[]" value="<?php echo $row_id; ?>"></th>
                            <td><strong><a href="<?php echo esc_url(add_query_arg('id', $row_id, $edit_url)); ?>"><?php echo esc_html($row['template_name']); ?></a></strong></td>
                            <td><?php echo esc_html($row['certificate_type']); ?></td>
                            <td><?php echo !empty($row['event_date']) ? esc_html(date_create($row['event_date'])->format('d-m-Y')) : '—'; ?></td>
                            <td><?php echo esc_html(ucfirst($row['orientation'] ?? '')); ?></td>
                            <td><?php echo esc_html($row['font_style'] ?? '—'); ?></td>
                            <td><?php echo esc_html(ucfirst($row['status'] ?? 'draft')); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('id', $row_id, $edit_url)); ?>">Edit</a>
                                &nbsp;|&nbsp;
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page'=>$this->slug,'action'=>'delete','id'=>$row_id], admin_url('admin.php')), 'cg_delete_template_single_'.$row_id)); ?>"
                                   style="color:#d63638;"
                                   onclick="return confirm('Delete this template?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <script>
        document.getElementById('cb-select-all').addEventListener('change', function() {
            document.querySelectorAll('.cb-select').forEach(function(cb){ cb.checked = this.checked; }.bind(this));
        });
        document.getElementById('doaction').addEventListener('click', function(e) {
            var action = document.getElementById('bulk-action-selector-top').value;
            if (action !== 'delete') { e.preventDefault(); return; }
            var checked = document.querySelectorAll('.cb-select:checked');
            if (!checked.length) { e.preventDefault(); alert('Select at least one template.'); return; }
            if (!confirm('Delete selected templates? This cannot be undone.')) e.preventDefault();
        });
        </script>
        <?php

        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
            $del_id    = absint($_GET['id']);
            $nonce_key = 'cg_delete_template_single_' . $del_id;
            if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $nonce_key)) {
                $wpdb->delete($table, ['id' => $del_id], ['%d']);
                wp_safe_redirect(admin_url('admin.php?page=' . $this->slug . '&deleted=1'));
                exit;
            }
        }
    }

    // ── Add / Edit Form ──────────────────────────────────────────────────────

    public function render_edit(): void {
        if (!current_user_can('manage_options')) wp_die('Insufficient permissions');

        global $wpdb;
        $table   = CustomTables::instance()->get_table($this->table_key);
        $id      = absint($_GET['id'] ?? 0);
        $row     = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A) : null;
        $message = ''; $errors = [];

        $font_manager = class_exists('CertificateGenerator_FontManager') ? \CertificateGenerator_FontManager::getInstance() : null;
        $cg_is_business = class_exists('CG_License_Manager') && \CG_License_Manager::is_business();
        $font_options = $font_manager ? $font_manager->get_font_options($cg_is_business) : ['helvetica' => 'Helvetica'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && !empty($_POST['cg_template_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cg_template_nonce'])), 'cg_save_template')) {

            $name = sanitize_text_field($_POST['template_name'] ?? '');
            $type = sanitize_text_field($_POST['certificate_type'] ?? '');
            if (!$name) $errors[] = 'Template name is required.';
            if (!$type) $errors[] = 'Certificate type is required.';

            if (empty($errors)) {
                $event_raw = sanitize_text_field($_POST['event_date'] ?? '');
                $event_date = '';
                if ($event_raw) {
                    $dt = \DateTime::createFromFormat('d-m-Y', $event_raw);
                    $event_date = $dt ? $dt->format('Y-m-d') : null;
                }

                $data = [
                    'template_name'             => $name,
                    'certificate_type'          => $type,
                    'event_date'                => $event_date,
                    'template_url'              => esc_url_raw($_POST['template_url'] ?? ''),
                    'orientation'               => in_array($_POST['orientation'] ?? '', ['portrait','landscape'], true) ? sanitize_key($_POST['orientation']) : 'landscape',
                    'page_size'                 => in_array($_POST['page_size'] ?? '', ['A4','Letter','Legal','Custom'], true) ? sanitize_text_field($_POST['page_size']) : 'A4',
                    'font_style'                => sanitize_key($_POST['font_style'] ?? 'helvetica'),
                    'font_size'                 => absint($_POST['font_size'] ?? 12),
                    'font_color'                => sanitize_hex_color($_POST['font_color'] ?? '#000000') ?: '#000000',
                    'qr_enabled'                => !empty($_POST['qr_enabled']) ? 1 : 0,
                    'qr_size'                   => absint($_POST['qr_size'] ?? 15),
                    'qr_position_x'             => floatval($_POST['qr_position_x'] ?? 250),
                    'qr_position_y'             => floatval($_POST['qr_position_y'] ?? 180),
                    'qr_error_correction'       => in_array($_POST['qr_error_correction'] ?? '', ['L','M','Q','H'], true) ? sanitize_key($_POST['qr_error_correction']) : 'L',
                    'serial_number_display'     => !empty($_POST['serial_number_display']) ? 1 : 0,
                    'serial_number_position_x'  => floatval($_POST['serial_number_position_x'] ?? 105),
                    'serial_number_position_y'  => floatval($_POST['serial_number_position_y'] ?? 200),
                    'serial_number_font_size'   => absint($_POST['serial_number_font_size'] ?? 10),
                    'expiration_period_unit'    => in_array($_POST['expiration_period_unit'] ?? '', ['never','days','months','years'], true) ? sanitize_key($_POST['expiration_period_unit']) : 'never',
                    'expiration_period_value'   => absint($_POST['expiration_period_value'] ?? 0),
                    'status'                    => in_array($_POST['status'] ?? '', ['draft','published','archived'], true) ? sanitize_key($_POST['status']) : 'draft',
                    'updated_at'                => current_time('mysql'),
                ];

                // ── Field positioning → extra_fields JSON ────────────────
                $max_fields   = class_exists('CG_Field_Schema') ? \CG_Field_Schema::MAX_FIELDS : 15;
                $field_count  = max(2, min($max_fields, absint($_POST['template_field_count'] ?? 3)));
                $extra_fields = [];
                // Preserve non-field keys already stored in extra_fields
                if (!empty($row['extra_fields'])) {
                    $existing_extra = json_decode($row['extra_fields'], true);
                    if (is_array($existing_extra)) {
                        foreach ($existing_extra as $ek => $ev) {
                            if (!preg_match('/^field_\d+_/', $ek) && $ek !== 'template_field_count') {
                                $extra_fields[$ek] = $ev;
                            }
                        }
                    }
                }
                $extra_fields['template_field_count'] = $field_count;
                for ($i = 1; $i <= $max_fields; $i++) {
                    $extra_fields["field_{$i}_position_x"] = floatval($_POST["field_{$i}_position_x"] ?? 0);
                    $extra_fields["field_{$i}_position_y"] = floatval($_POST["field_{$i}_position_y"] ?? 0);
                    $extra_fields["field_{$i}_visible"]    = !empty($_POST["field_{$i}_visible"]) ? '1' : '0';
                    $extra_fields["field_{$i}_width"]      = floatval($_POST["field_{$i}_width"] ?? 100);
                    $alignment = sanitize_key($_POST["field_{$i}_alignment"] ?? 'C');
                    $extra_fields["field_{$i}_alignment"]  = in_array($alignment, ['L', 'C', 'R'], true) ? $alignment : 'C';
                }
                $data['extra_fields'] = wp_json_encode($extra_fields);

                if ($id && $row) {
                    $wpdb->update($table, $data, ['id' => $id]);
                    $message = 'Template updated.';
                    $row = array_merge($row, $data);
                } else {
                    $data['created_at'] = current_time('mysql');
                    $wpdb->insert($table, $data);
                    $id = (int) $wpdb->insert_id;
                    $row = array_merge($data, ['id' => $id]);
                    $message = 'Template added.';
                }
            }
        }

        $list_url = admin_url('admin.php?page=' . $this->slug);
        ?>
        <div class="wrap">
            <h1><?php echo $id ? 'Edit Template' : 'Add New Template'; ?></h1>
            <a href="<?php echo esc_url($list_url); ?>">← Back to Templates</a>

            <?php foreach ($errors as $e): ?><div class="notice notice-error"><p><?php echo esc_html($e); ?></p></div><?php endforeach; ?>
            <?php if ($message): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>

            <form method="post" id="cg-template-form" style="margin-top:20px;">
                <?php wp_nonce_field('cg_save_template', 'cg_template_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="template_name">Template Name <span style="color:red">*</span></label></th>
                        <td><input type="text" id="template_name" name="template_name" class="regular-text" required value="<?php echo esc_attr($row['template_name'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="certificate_type">Certificate Type <span style="color:red">*</span></label></th>
                        <td><input type="text" id="certificate_type" name="certificate_type" class="regular-text" required value="<?php echo esc_attr($row['certificate_type'] ?? ''); ?>">
                        <p class="description">Used to match this template when generating certificates.</p></td>
                    </tr>
                    <tr>
                        <th><label for="event_date">Event Date</label></th>
                        <td><input type="text" id="event_date" name="event_date" value="<?php echo esc_attr(!empty($row['event_date']) ? date_create($row['event_date'])->format('d-m-Y') : ''); ?>" placeholder="DD-MM-YYYY">
                        <p class="description">For distinguishing multiple templates of the same type. Format: d-m-Y</p></td>
                    </tr>
                    <tr>
                        <th><label for="template_url">Template Image URL</label></th>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input type="text" id="template_url" name="template_url" class="regular-text" style="flex:1;" value="<?php echo esc_url($row['template_url'] ?? ''); ?>" placeholder="Paste URL or choose from media library →">
                                <button type="button" id="cg_upload_template_btn" class="button button-secondary">📁 Choose from Media Library</button>
                            </div>
                            <div id="cg_template_preview" style="margin-top:10px;<?php echo !empty($row['template_url']) ? '' : 'display:none;'; ?>">
                                <?php if (!empty($row['template_url'])): ?>
                                    <img src="<?php echo esc_url($row['template_url']); ?>" style="max-width:300px;max-height:180px;border:1px solid #ddd;border-radius:4px;display:block;">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="orientation">Orientation</label></th>
                        <td><select id="orientation" name="orientation">
                            <option value="landscape" <?php selected($row['orientation'] ?? 'landscape', 'landscape'); ?>>Landscape (297x210 mm)</option>
                            <option value="portrait" <?php selected($row['orientation'] ?? 'landscape', 'portrait'); ?>>Portrait (210x297 mm)</option>
                        </select></td>
                    </tr>
                    <tr>
                        <th><label for="page_size">Page Size</label></th>
                        <td><select id="page_size" name="page_size">
                            <?php foreach (['A4','Letter','Legal','Custom'] as $s): ?>
                                <option value="<?php echo esc_attr($s); ?>" <?php selected($row['page_size'] ?? 'A4', $s); ?>><?php echo esc_html($s); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th><label for="font_style">Font Style</label></th>
                        <td><select id="font_style" name="font_style">
                            <?php foreach ($font_options as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($row['font_style'] ?? 'helvetica', $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                    <tr>
                        <th><label for="font_size">Font Size</label></th>
                        <td><input type="number" id="font_size" name="font_size" value="<?php echo esc_attr($row['font_size'] ?? 12); ?>" min="6" max="48"></td>
                    </tr>
                    <tr>
                        <th><label for="font_color">Font Color</label></th>
                        <td><input type="color" id="font_color" name="font_color" value="<?php echo esc_attr($row['font_color'] ?? '#000000'); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="status">Status</label></th>
                        <td><select id="status" name="status">
                            <?php foreach (['draft','published','archived'] as $s): ?>
                                <option value="<?php echo esc_attr($s); ?>" <?php selected($row['status'] ?? 'draft', $s); ?>><?php echo esc_html(ucfirst($s)); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                </table>

                <h3>QR Code Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label><input type="checkbox" id="qr_enabled" name="qr_enabled" value="1" <?php checked(!empty($row['qr_enabled'])); ?>> Enable QR Code</label></th>
                        <td></td>
                    </tr>
                    <tr>
                        <th><label for="qr_size">QR Size</label></th>
                        <td><input type="number" id="qr_size" name="qr_size" value="<?php echo esc_attr($row['qr_size'] ?? 15); ?>" min="5" max="50"></td>
                    </tr>
                    <tr>
                        <th><label for="qr_position_x">QR Position X (mm)</label></th>
                        <td><input type="number" step="0.01" id="qr_position_x" name="qr_position_x" value="<?php echo esc_attr($row['qr_position_x'] ?? 250); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="qr_position_y">QR Position Y (mm)</label></th>
                        <td><input type="number" step="0.01" id="qr_position_y" name="qr_position_y" value="<?php echo esc_attr($row['qr_position_y'] ?? 180); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="qr_error_correction">QR Error Correction</label></th>
                        <td><select id="qr_error_correction" name="qr_error_correction">
                            <?php foreach (['L'=>'Low','M'=>'Medium','Q'=>'Quartile','H'=>'High'] as $k => $v): ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($row['qr_error_correction'] ?? 'L', $k); ?>><?php echo esc_html($v); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    </tr>
                </table>

                <h3>Serial Number Display</h3>
                <table class="form-table">
                    <tr>
                        <th><label><input type="checkbox" id="serial_number_display" name="serial_number_display" value="1" <?php checked(!empty($row['serial_number_display'])); ?>> Show Serial Number on Certificate</label></th>
                        <td></td>
                    </tr>
                    <tr>
                        <th><label for="serial_number_position_x">Serial X Position (mm)</label></th>
                        <td><input type="number" step="0.01" id="serial_number_position_x" name="serial_number_position_x" value="<?php echo esc_attr($row['serial_number_position_x'] ?? 105); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="serial_number_position_y">Serial Y Position (mm)</label></th>
                        <td><input type="number" step="0.01" id="serial_number_position_y" name="serial_number_position_y" value="<?php echo esc_attr($row['serial_number_position_y'] ?? 200); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="serial_number_font_size">Serial Font Size</label></th>
                        <td><input type="number" id="serial_number_font_size" name="serial_number_font_size" value="<?php echo esc_attr($row['serial_number_font_size'] ?? 10); ?>" min="6" max="48"></td>
                    </tr>
                </table>

                <h3>Expiration</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="expiration_period_unit">Expiration Period</label></th>
                        <td><select id="expiration_period_unit" name="expiration_period_unit">
                            <?php foreach (['never'=>'Never','days'=>'Days','months'=>'Months','years'=>'Years'] as $k => $v): ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($row['expiration_period_unit'] ?? 'never', $k); ?>><?php echo esc_html($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="expiration_period_value" name="expiration_period_value" value="<?php echo esc_attr($row['expiration_period_value'] ?? 0); ?>" min="0" style="width:80px;margin-left:10px;"></td>
                    </tr>
                </table>

                <?php
                // ── Field Positioning ─────────────────────────────────────
                $max_fields_ui = class_exists('\CG_Field_Schema') ? \CG_Field_Schema::MAX_FIELDS : 15;
                $extra_fields_data = [];
                if (!empty($row['extra_fields'])) {
                    $decoded = json_decode($row['extra_fields'], true);
                    if (is_array($decoded)) $extra_fields_data = $decoded;
                }
                $field_count_val = max(2, min($max_fields_ui, (int) ($extra_fields_data['template_field_count'] ?? 3)));

                $cert_type_val = $row['certificate_type'] ?? '';
                $all_renderable = (class_exists('\CG_Field_Schema') && $cert_type_val)
                    ? \CG_Field_Schema::get_all_renderable_fields($cert_type_val)
                    : [];
                $canvas_labels = [];
                for ($j = 1; $j <= $max_fields_ui; $j++) {
                    $canvas_labels[] = isset($all_renderable[$j - 1])
                        ? \CG_Field_Schema::get_display_label($all_renderable[$j - 1])
                        : "Field {$j}";
                }
                $template_url_val  = $row['template_url'] ?? '';
                $orientation_val   = $row['orientation'] ?? 'landscape';
                ?>

                <h3 style="margin-bottom:4px">Field Positioning</h3>
                <p class="description" style="margin-bottom:14px">Drag handles on the canvas <strong>or</strong> click a row then click the canvas to place it. Coordinates update in real time.</p>

                <style>
                /* ── Layout ── */
                #cg-editor-layout{display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start}
                @media(max-width:1100px){#cg-editor-layout{grid-template-columns:1fr}}

                /* ── Toolbar ── */
                #cg-canvas-toolbar{display:flex;align-items:center;gap:10px;padding:8px 10px;background:#f6f7f8;border:1px solid #ddd;border-radius:6px 6px 0 0;flex-wrap:wrap}
                #cg-canvas-toolbar label{font-size:12px;color:#555;margin:0}
                #cg-zoom-slider{width:90px;accent-color:#0073aa;cursor:pointer}
                #cg-zoom-label{font-size:12px;color:#0073aa;font-weight:700;min-width:36px}
                #cg-grid-toggle{font-size:12px}
                #cg-click-mode-toggle{font-size:12px}
                #cg-coords-display{font-size:11px;color:#666;font-family:monospace;margin-left:auto}

                /* ── Canvas wrapper ── */
                #cg-canvas-outer-scroll{overflow:auto;background:#888;border:1px solid #ccc;border-top:none;border-radius:0 0 6px 6px;padding:20px;display:flex;justify-content:center;min-height:200px;max-height:640px;box-sizing:border-box}
                #cg-canvas-container{position:relative;flex-shrink:0;box-shadow:0 6px 24px rgba(0,0,0,.45);cursor:crosshair}
                #cg-canvas-img{display:block}

                /* ── Grid overlay ── */
                #cg-grid-overlay{position:absolute;inset:0;pointer-events:none;z-index:5;display:none}
                #cg-grid-overlay.active{display:block}

                /* ── Loading spinner ── */
                #cg-canvas-spinner{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.7);z-index:50;border-radius:2px}
                #cg-canvas-spinner .cg-spin{width:36px;height:36px;border:4px solid #ddd;border-top-color:#0073aa;border-radius:50%;animation:cg-rotate .7s linear infinite}
                @keyframes cg-rotate{to{transform:rotate(360deg)}}

                /* ── Drag tooltip ── */
                #cg-drag-tooltip{position:absolute;background:rgba(0,0,0,.78);color:#fff;font-size:11px;font-family:monospace;padding:3px 7px;border-radius:4px;pointer-events:none;z-index:100;display:none;white-space:nowrap}

                /* ── Handles ── */
                .cg-field-handle{position:absolute;box-sizing:border-box;border-radius:4px;color:#fff;padding:4px 8px 3px;font-size:11px;font-weight:700;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.3;white-space:nowrap;box-shadow:0 2px 6px rgba(0,0,0,.45);z-index:10;cursor:grab;user-select:none;transition:box-shadow .15s,outline .15s}
                .cg-field-handle:hover{box-shadow:0 3px 10px rgba(0,0,0,.55);z-index:20}
                .cg-field-handle:active{cursor:grabbing}
                .cg-field-handle.cg-active-handle{outline:3px solid #fff;box-shadow:0 0 0 5px rgba(0,115,170,.6),0 3px 10px rgba(0,0,0,.5);z-index:30}
                .cg-field-handle .cg-width-bar{display:block;height:2px;background:rgba(255,255,255,.5);border-radius:1px;margin-top:3px;min-width:4px}
                .cg-field-handle .cg-handle-num{display:inline-block;background:rgba(0,0,0,.2);border-radius:2px;padding:0 3px;margin-right:4px;font-size:10px}

                /* ── Field panel ── */
                #cg-field-panel{background:#fff;border:1px solid #ddd;border-radius:6px;overflow:hidden}
                #cg-field-panel-header{background:#f6f7f8;padding:10px 14px;border-bottom:1px solid #ddd;display:flex;align-items:center;gap:8px}
                #cg-field-panel-header strong{flex:1;font-size:13px}
                .cg-field-row-card{display:flex;align-items:flex-start;gap:0;border-bottom:1px solid #f0f0f1;transition:background .15s;cursor:pointer}
                .cg-field-row-card:last-child{border-bottom:none}
                .cg-field-row-card:hover{background:#f8f9fa}
                .cg-field-row-card.cg-row-active{background:#e8f4fb}
                .cg-field-color-bar{width:5px;flex-shrink:0;align-self:stretch;border-radius:0}
                .cg-field-row-body{padding:8px 12px;flex:1;min-width:0}
                .cg-field-row-label{font-weight:600;font-size:12px;color:#1d2327;margin-bottom:4px;display:flex;align-items:center;gap:6px}
                .cg-field-slot-badge{font-size:10px;color:#888;font-weight:400}
                .cg-field-row-inputs{display:grid;grid-template-columns:1fr 1fr;gap:4px 8px}
                .cg-field-input-wrap{display:flex;align-items:center;gap:4px;font-size:11px;color:#555}
                .cg-field-input-wrap input[type=number]{width:54px;padding:2px 4px;font-size:12px;border:1px solid #ccc;border-radius:3px}
                .cg-field-input-wrap input[type=number]:focus{border-color:#0073aa;outline:none;box-shadow:0 0 0 1px #0073aa}
                .cg-field-row-footer{display:flex;align-items:center;gap:8px;margin-top:5px;flex-wrap:wrap}
                .cg-field-row-footer select{font-size:11px;padding:1px 2px;border:1px solid #ccc;border-radius:3px}
                .cg-field-row-footer input[type=number]{width:54px;font-size:11px;padding:2px 4px;border:1px solid #ccc;border-radius:3px}
                .cg-field-row-footer label{font-size:11px;color:#555;display:flex;align-items:center;gap:3px}
                .cg-center-btn{font-size:10px;padding:2px 6px;line-height:1.6;margin-left:auto;flex-shrink:0}
                .cg-field-hidden-badge{font-size:10px;color:#c0392b;font-weight:600}

                /* ── Field count control ── */
                #cg-field-count-bar{display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f6f7f8;border-bottom:1px solid #ddd}
                #cg-field-count-bar strong{font-size:12px;color:#555}
                .cg-count-btn{font-size:15px;padding:0 8px;line-height:24px;min-height:0;height:26px}

                /* ── Click-mode banner ── */
                #cg-click-mode-banner{background:#0073aa;color:#fff;padding:5px 12px;font-size:12px;font-weight:600;display:none;align-items:center;gap:8px;border-radius:4px;margin-top:6px}
                #cg-click-mode-banner button{background:rgba(255,255,255,.2);border:none;color:#fff;cursor:pointer;padding:2px 8px;border-radius:3px;font-size:11px}
                </style>

                <!-- Field count + click mode banner outside the 2-col grid -->
                <div style="display:flex;align-items:center;gap:12px;margin:0 0 10px;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:6px">
                        <strong style="font-size:13px">Fields on certificate:</strong>
                        <button type="button" id="cg_field_count_dec" class="button cg-count-btn">−</button>
                        <input type="number" id="template_field_count" name="template_field_count"
                            value="<?php echo esc_attr($field_count_val); ?>"
                            min="2" max="<?php echo esc_attr($max_fields_ui); ?>"
                            style="width:52px;text-align:center;font-size:14px;font-weight:700;padding:2px 4px">
                        <button type="button" id="cg_field_count_inc" class="button cg-count-btn">+</button>
                        <span class="description" style="font-size:11px">max <?php echo esc_html($max_fields_ui); ?></span>
                    </div>
                    <div id="cg-click-mode-banner">
                        <span>Click mode: click canvas to place <strong id="cg-click-mode-label">—</strong></span>
                        <button type="button" id="cg-click-mode-cancel">✕ Cancel</button>
                    </div>
                </div>

                <div id="cg-editor-layout">

                    <!-- LEFT: Canvas -->
                    <div>
                        <!-- Toolbar -->
                        <div id="cg-canvas-toolbar">
                            <label>Zoom <input type="range" id="cg-zoom-slider" min="50" max="200" value="100" step="10"></label>
                            <span id="cg-zoom-label">100%</span>
                            <label style="display:flex;align-items:center;gap:4px">
                                <input type="checkbox" id="cg-grid-toggle"> Grid
                            </label>
                            <span id="cg-coords-display">—</span>
                        </div>
                        <!-- Canvas -->
                        <div id="cg-canvas-outer-scroll" <?php echo $template_url_val ? '' : 'style="display:none"'; ?>>
                            <div id="cg-canvas-container">
                                <img id="cg-canvas-img" src="<?php echo esc_url($template_url_val); ?>" alt="Certificate template preview">
                                <canvas id="cg-grid-overlay"></canvas>
                                <div id="cg-canvas-spinner" style="display:none"><div class="cg-spin"></div></div>
                                <div id="cg-drag-tooltip"></div>
                            </div>
                        </div>
                        <?php if (!$template_url_val): ?>
                        <div id="cg-canvas-placeholder" style="border:2px dashed #ccc;border-radius:6px;padding:40px;text-align:center;color:#888;background:#fafafa">
                            <p style="margin:0;font-size:14px">Set a Template URL above to see the canvas</p>
                        </div>
                        <?php endif; ?>
                        <p class="description" style="margin-top:6px;font-size:11px">Coordinates in mm · Faded = hidden · Click handle then click canvas to reposition</p>
                    </div>

                    <!-- RIGHT: Field panel -->
                    <div id="cg-field-panel">
                        <div id="cg-field-panel-header">
                            <strong>Field Slots</strong>
                            <span class="description" style="font-size:11px">Click row to activate</span>
                        </div>

                        <?php for ($i = 1; $i <= $max_fields_ui; $i++):
                            $px   = $extra_fields_data["field_{$i}_position_x"] ?? 0;
                            $py   = $extra_fields_data["field_{$i}_position_y"] ?? 0;
                            $vis  = ($extra_fields_data["field_{$i}_visible"]    ?? '1') === '1'; // default visible
                            $pw   = $extra_fields_data["field_{$i}_width"]       ?? 100;
                            $pa   = $extra_fields_data["field_{$i}_alignment"]   ?? 'C';
                            $lbl  = $canvas_labels[$i - 1] ?? "Field {$i}";
                            $show = $i <= $field_count_val ? '' : 'display:none;';
                        ?>
                        <div class="cg-field-row-card" data-field-index="<?php echo $i; ?>" id="cg-row-<?php echo $i; ?>" style="<?php echo esc_attr($show); ?>">
                            <div class="cg-field-color-bar" id="cg-colorbar-<?php echo $i; ?>"></div>
                            <div class="cg-field-row-body">
                                <div class="cg-field-row-label">
                                    <span><?php echo esc_html($lbl); ?></span>
                                    <span class="cg-field-slot-badge">slot <?php echo $i; ?></span>
                                    <?php if (!$vis): ?><span class="cg-field-hidden-badge">hidden</span><?php endif; ?>
                                </div>
                                <div class="cg-field-row-inputs">
                                    <div class="cg-field-input-wrap">
                                        <span>X</span>
                                        <input type="number" id="field_<?php echo $i; ?>_position_x" name="field_<?php echo $i; ?>_position_x"
                                            value="<?php echo esc_attr($px); ?>" min="0" max="<?php echo $orientation_val === 'landscape' ? 297 : 210; ?>">
                                        <span style="color:#aaa">mm</span>
                                    </div>
                                    <div class="cg-field-input-wrap">
                                        <span>Y</span>
                                        <input type="number" id="field_<?php echo $i; ?>_position_y" name="field_<?php echo $i; ?>_position_y"
                                            value="<?php echo esc_attr($py); ?>" min="0" max="<?php echo $orientation_val === 'landscape' ? 210 : 297; ?>">
                                        <span style="color:#aaa">mm</span>
                                    </div>
                                </div>
                                <div class="cg-field-row-footer">
                                    <label>W <input type="number" id="field_<?php echo $i; ?>_width" name="field_<?php echo $i; ?>_width" value="<?php echo esc_attr($pw); ?>" min="1" max="297"> mm</label>
                                    <label>Align
                                        <select id="field_<?php echo $i; ?>_alignment" name="field_<?php echo $i; ?>_alignment">
                                            <option value="L" <?php selected($pa, 'L'); ?>>L</option>
                                            <option value="C" <?php selected($pa, 'C'); ?>>C</option>
                                            <option value="R" <?php selected($pa, 'R'); ?>>R</option>
                                        </select>
                                    </label>
                                    <label><input type="checkbox" id="field_<?php echo $i; ?>_visible" name="field_<?php echo $i; ?>_visible" value="1" <?php checked($vis); ?>> Visible</label>
                                    <button type="button" class="button cg-center-btn" data-field="<?php echo $i; ?>" title="Centre on page">⊕ Center</button>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div><!-- /#cg-field-panel -->

                </div><!-- /#cg-editor-layout -->

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $id ? 'Update Template' : 'Add Template'; ?></button>
                    <a href="<?php echo esc_url($list_url); ?>" class="button">Cancel</a>
                    <?php if ($id): ?>
                    <button type="button" id="cg_preview_cert_btn" class="button button-secondary" style="margin-left:8px">Preview Certificate</button>
                    <span id="cg_preview_status" style="margin-left:8px;color:#666;font-style:italic"></span>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        <script>
        (function ($) {
            var MAX_FIELDS   = <?php echo (int) $max_fields_ui; ?>;
            var FIELD_LABELS = <?php echo wp_json_encode(array_values($canvas_labels)); ?>;
            var COLORS = [
                '#c0392b','#2980b9','#27ae60','#8e44ad','#f39c12',
                '#16a085','#d35400','#1abc9c','#e74c3c','#7f8c8d',
                '#0097a7','#e67e22','#3498db','#9b59b6','#2c3e50'
            ];
            var BASE_CANVAS_H = 520;
            var zoomPct       = 100;
            var clickModeField = null; // null = off, number = active field N

            var $canvas  = $('#cg-canvas-container');
            var $img     = $('#cg-canvas-img');
            var $scroll  = $('#cg-canvas-outer-scroll');
            var $tooltip = $('#cg-drag-tooltip');
            var $gridCvs = $('#cg-grid-overlay')[0];

            // ── Helpers ──────────────────────────────────────────────────

            function orientation() { return $('#orientation').val() || 'landscape'; }

            function pageDims() {
                return orientation() === 'landscape' ? { w: 297, h: 210 } : { w: 210, h: 297 };
            }

            function canvasDims() {
                var d  = pageDims();
                var h  = Math.round(BASE_CANVAS_H * zoomPct / 100);
                return { w: Math.round((d.w / d.h) * h), h: h };
            }

            function mmToPx(val, axis) {
                var d = pageDims(), cd = canvasDims();
                return axis === 'x' ? (val / d.w) * cd.w : (val / d.h) * cd.h;
            }

            function pxToMm(val, axis) {
                var d = pageDims(), cd = canvasDims();
                return axis === 'x'
                    ? Math.round((val / cd.w) * d.w * 10) / 10
                    : Math.round((val / cd.h) * d.h * 10) / 10;
            }

            function updateCanvasSize() {
                var cd = canvasDims();
                $canvas.css({ width: cd.w + 'px', height: cd.h + 'px' });
                $img.css({ width: cd.w + 'px', height: cd.h + 'px' });
                if ($gridCvs) {
                    $gridCvs.width  = cd.w;
                    $gridCvs.height = cd.h;
                    $($gridCvs).css({ width: cd.w + 'px', height: cd.h + 'px' });
                }
                drawGrid();
            }

            // ── Grid overlay ─────────────────────────────────────────────

            function drawGrid() {
                if (!$gridCvs) return;
                var ctx = $gridCvs.getContext('2d');
                var cd  = canvasDims(), d = pageDims();
                ctx.clearRect(0, 0, cd.w, cd.h);
                if (!$('#cg-grid-toggle').is(':checked')) return;
                ctx.strokeStyle = 'rgba(0,115,170,0.18)';
                ctx.lineWidth   = 1;
                // 10mm grid
                for (var x = 0; x <= d.w; x += 10) {
                    var px = Math.round(mmToPx(x, 'x')) + 0.5;
                    ctx.beginPath(); ctx.moveTo(px, 0); ctx.lineTo(px, cd.h); ctx.stroke();
                }
                for (var y = 0; y <= d.h; y += 10) {
                    var py = Math.round(mmToPx(y, 'y')) + 0.5;
                    ctx.beginPath(); ctx.moveTo(0, py); ctx.lineTo(cd.w, py); ctx.stroke();
                }
                // Labels every 50mm
                ctx.fillStyle = 'rgba(0,115,170,0.45)';
                ctx.font = '9px monospace';
                for (var xL = 0; xL <= d.w; xL += 50) {
                    ctx.fillText(xL, Math.round(mmToPx(xL, 'x')) + 2, 10);
                }
                for (var yL = 50; yL <= d.h; yL += 50) {
                    ctx.fillText(yL, 2, Math.round(mmToPx(yL, 'y')) - 2);
                }
            }

            // ── Color bars in field panel ─────────────────────────────────

            function applyColors() {
                var count = Math.min(parseInt($('#template_field_count').val(), 10) || 3, MAX_FIELDS);
                for (var i = 1; i <= MAX_FIELDS; i++) {
                    var color = i <= count ? COLORS[(i - 1) % COLORS.length] : '#ddd';
                    $('#cg-colorbar-' + i).css('background', color);
                }
            }

            // ── Active field highlight ────────────────────────────────────

            function setActiveRow(n) {
                $('.cg-field-row-card').removeClass('cg-row-active');
                $('.cg-field-handle').removeClass('cg-active-handle');
                if (n) {
                    $('#cg-row-' + n).addClass('cg-row-active');
                    $('#cg-handle-' + n).addClass('cg-active-handle');
                }
            }

            // ── Handle positioning ────────────────────────────────────────

            function positionHandle(n) {
                var $h = $('#cg-handle-' + n);
                if (!$h.length) return;
                var x_mm = parseFloat($('#field_' + n + '_position_x').val()) || 0;
                var y_mm = parseFloat($('#field_' + n + '_position_y').val()) || 0;
                var w_mm = parseFloat($('#field_' + n + '_width').val())      || 100;
                var cx   = mmToPx(x_mm, 'x');
                var cy   = mmToPx(y_mm, 'y');
                var wPx  = Math.max(4, mmToPx(w_mm, 'x'));
                var hw   = $h.outerWidth()  / 2 || 0;
                var hh   = $h.outerHeight() / 2 || 0;
                $h.css({ left: (cx - hw) + 'px', top: (cy - hh) + 'px' });
                $h.find('.cg-width-bar').css('width', wPx + 'px');
                var visible = $('#field_' + n + '_visible').prop('checked');
                // Canvas is a layout editor — always show & keep interactive.
                // "hidden" only means the field won't render on the PDF.
                // Indicate hidden state with a dashed outline + eye-slash suffix, NOT by disabling.
                $h.css({ opacity: '1', 'pointer-events': '' });
                if (visible) {
                    $h.css({ outline: 'none' }).removeClass('cg-handle-hidden');
                    $h.find('.cg-handle-hidden-tag').remove();
                } else {
                    $h.css({ outline: '2px dashed rgba(255,255,255,0.7)' }).addClass('cg-handle-hidden');
                    if (!$h.find('.cg-handle-hidden-tag').length) {
                        $h.append('<span class="cg-handle-hidden-tag" style="font-size:9px;opacity:.8;margin-left:4px">(hidden)</span>');
                    }
                }
                // Update hidden badge in panel
                var $badge = $('#cg-row-' + n + ' .cg-field-hidden-badge');
                if (!visible) { if (!$badge.length) $('#cg-row-' + n + ' .cg-field-row-label').append('<span class="cg-field-hidden-badge">hidden</span>'); }
                else { $badge.remove(); }
            }

            function positionQRHandle() {
                var $h = $('#cg-handle-qr');
                if (!$h.length) return;
                if (!$('#qr_enabled').is(':checked')) { $h.hide(); return; }
                $h.show();
                var cx  = mmToPx(parseFloat($('#qr_position_x').val())  || 250, 'x');
                var cy  = mmToPx(parseFloat($('#qr_position_y').val())  || 180, 'y');
                var sPx = Math.max(mmToPx(parseFloat($('#qr_size').val()) || 15, 'x'), 24);
                $h.css({ left: cx + 'px', top: cy + 'px', width: sPx + 'px', height: sPx + 'px' });
            }

            function positionSerialHandle() {
                var $h = $('#cg-handle-serial');
                if (!$h.length) return;
                if (!$('#serial_number_display').is(':checked')) { $h.hide(); return; }
                $h.show();
                var cx = mmToPx(parseFloat($('#serial_number_position_x').val()) || 105, 'x');
                var cy = mmToPx(parseFloat($('#serial_number_position_y').val()) || 200, 'y');
                var hw = $h.outerWidth() / 2 || 0, hh = $h.outerHeight() / 2 || 0;
                $h.css({ left: (cx - hw) + 'px', top: (cy - hh) + 'px' });
            }

            function repositionAll() {
                $canvas.find('.cg-field-handle').each(function () {
                    var n = parseInt($(this).data('field'), 10);
                    if (n) positionHandle(n);
                });
                positionQRHandle();
                positionSerialHandle();
            }

            // ── Tooltip helper ────────────────────────────────────────────

            function showTooltip(x, y, text) {
                $tooltip.text(text).css({ left: x + 'px', top: (y - 28) + 'px' }).show();
            }
            function hideTooltip() { $tooltip.hide(); }

            // ── Build all draggable handles ───────────────────────────────

            function buildHandles() {
                $canvas.find('.cg-field-handle').remove();
                var count = Math.min(parseInt($('#template_field_count').val(), 10) || 3, MAX_FIELDS);

                var d = pageDims();
                for (var n = 1; n <= count; n++) {
                    var label = FIELD_LABELS[n - 1] || ('Field ' + n);
                    var color = COLORS[(n - 1) % COLORS.length];
                    var $h = $('<div>')
                        .attr({ id: 'cg-handle-' + n, 'data-field': n })
                        .addClass('cg-field-handle')
                        .css('background', color)
                        .html('<span class="cg-handle-num">' + n + '</span><span class="cg-handle-label">' + $('<span>').text(label).html() + '</span><span class="cg-width-bar"></span>');
                    $canvas.append($h);

                    // If this field has no saved position (0,0), scatter it down the canvas
                    // so unpositioned handles don't pile on top of each other at the corner.
                    var savedX = parseFloat($('#field_' + n + '_position_x').val()) || 0;
                    var savedY = parseFloat($('#field_' + n + '_position_y').val()) || 0;
                    if (savedX === 0 && savedY === 0) {
                        var autoY = Math.round((d.h / (count + 1)) * n);
                        var autoX = Math.round(d.w / 2);
                        $('#field_' + n + '_position_x').val(autoX);
                        $('#field_' + n + '_position_y').val(autoY);
                    }

                    positionHandle(n);

                    (function (fieldN, $handle) {
                        $handle
                            .on('mousedown', function () { setActiveRow(fieldN); cancelClickMode(); })
                            .draggable({
                                containment: '#cg-canvas-container',
                                cursor: 'grabbing',
                                start: function () { setActiveRow(fieldN); },
                                drag: function (e, ui) {
                                    var hw  = $handle.outerWidth() / 2, hh = $handle.outerHeight() / 2;
                                    var cd  = canvasDims(), d = pageDims();
                                    var cx_px = Math.max(0, Math.min(cd.w, ui.position.left + hw));
                                    var cy_px = Math.max(0, Math.min(cd.h, ui.position.top  + hh));
                                    var xMm = Math.max(0, Math.min(d.w, pxToMm(cx_px, 'x')));
                                    var yMm = Math.max(0, Math.min(d.h, pxToMm(cy_px, 'y')));
                                    $('#field_' + fieldN + '_position_x').val(xMm);
                                    $('#field_' + fieldN + '_position_y').val(yMm);
                                    showTooltip(ui.position.left + hw, ui.position.top, 'X:' + xMm + ' Y:' + yMm);
                                    $('#cg-coords-display').text('X: ' + xMm + ' mm  Y: ' + yMm + ' mm');
                                },
                                stop: function () { hideTooltip(); }
                            });
                    })(n, $h);
                }

                // QR handle
                $canvas.find('#cg-handle-qr').remove();
                if ($('#qr_enabled').is(':checked')) {
                    var sPx = Math.max(mmToPx(parseFloat($('#qr_size').val()) || 15, 'x'), 24);
                    var $qr = $('<div>').attr('id', 'cg-handle-qr').addClass('cg-field-handle')
                        .css({ background: '#8e44ad', width: sPx + 'px', height: sPx + 'px', padding: '2px', overflow: 'hidden' })
                        .append($('<span>').addClass('cg-handle-label').css('font-size', '9px').text('QR'));
                    $canvas.append($qr);
                    positionQRHandle();
                    $qr.draggable({ containment: '#cg-canvas-container', cursor: 'grabbing',
                        drag: function (e, ui) {
                            var cd = canvasDims(), d = pageDims();
                            var xMm = Math.max(0, Math.min(d.w, pxToMm(Math.max(0, Math.min(cd.w, ui.position.left)), 'x')));
                            var yMm = Math.max(0, Math.min(d.h, pxToMm(Math.max(0, Math.min(cd.h, ui.position.top)),  'y')));
                            $('#qr_position_x').val(xMm);
                            $('#qr_position_y').val(yMm);
                            showTooltip(ui.position.left, ui.position.top, 'QR X:' + xMm + ' Y:' + yMm);
                        },
                        stop: function () { hideTooltip(); }
                    });
                }

                // Serial handle
                $canvas.find('#cg-handle-serial').remove();
                if ($('#serial_number_display').is(':checked')) {
                    var $sn = $('<div>').attr('id', 'cg-handle-serial').addClass('cg-field-handle')
                        .css({ background: '#16a085' })
                        .append($('<span>').addClass('cg-handle-label').text('# Serial'));
                    $canvas.append($sn);
                    positionSerialHandle();
                    $sn.draggable({ containment: '#cg-canvas-container', cursor: 'grabbing',
                        drag: function (e, ui) {
                            var hw = $sn.outerWidth()/2, hh = $sn.outerHeight()/2;
                            var cd = canvasDims(), d = pageDims();
                            var xMm = Math.max(0, Math.min(d.w, pxToMm(Math.max(0, Math.min(cd.w, ui.position.left + hw)), 'x')));
                            var yMm = Math.max(0, Math.min(d.h, pxToMm(Math.max(0, Math.min(cd.h, ui.position.top  + hh)), 'y')));
                            $('#serial_number_position_x').val(xMm);
                            $('#serial_number_position_y').val(yMm);
                            showTooltip(ui.position.left + hw, ui.position.top, 'Serial X:' + xMm + ' Y:' + yMm);
                        },
                        stop: function () { hideTooltip(); }
                    });
                }

                applyColors();
            }

            // ── Click-to-place mode ───────────────────────────────────────

            function enterClickMode(n) {
                clickModeField = n;
                setActiveRow(n);
                var label = FIELD_LABELS[n - 1] || ('Field ' + n);
                $('#cg-click-mode-label').text(label);
                $('#cg-click-mode-banner').css('display', 'flex');
                $canvas.css('cursor', 'crosshair');
            }

            function cancelClickMode() {
                clickModeField = null;
                $('#cg-click-mode-banner').hide();
                $canvas.css('cursor', 'crosshair');
            }

            $canvas.on('click', function (e) {
                if (!clickModeField) return;
                var off    = $canvas.offset();
                var cx_px  = e.pageX - off.left;
                var cy_px  = e.pageY - off.top;
                var d      = pageDims();
                var cd     = canvasDims();
                var xMm = Math.max(0, Math.min(d.w, pxToMm(cx_px, 'x')));
                var yMm = Math.max(0, Math.min(d.h, pxToMm(cy_px, 'y')));
                $('#field_' + clickModeField + '_position_x').val(xMm);
                $('#field_' + clickModeField + '_position_y').val(yMm);
                positionHandle(clickModeField);
                cancelClickMode();
            });

            // Show live coordinates on mouse move over canvas
            $canvas.on('mousemove', function (e) {
                var off   = $canvas.offset();
                var cx_px = e.pageX - off.left;
                var cy_px = e.pageY - off.top;
                var xMm   = Math.max(0, pxToMm(cx_px, 'x'));
                var yMm   = Math.max(0, pxToMm(cy_px, 'y'));
                $('#cg-coords-display').text('X: ' + xMm + ' mm  Y: ' + yMm + ' mm');
            }).on('mouseleave', function () {
                $('#cg-coords-display').text('—');
            });

            // ── Canvas init / reload ──────────────────────────────────────

            function showSpinner(show) {
                $('#cg-canvas-spinner').toggle(show);
            }

            function initCanvas() {
                var url = $('#template_url').val().trim();
                if (!url) {
                    $scroll.hide();
                    $('#cg-canvas-placeholder').show();
                    return;
                }
                $scroll.show();
                $('#cg-canvas-placeholder').hide();
                updateCanvasSize();
                $img.off('load.cgcanvas error.cgcanvas');
                $img.one('error.cgcanvas', function () {
                    $scroll.html('<p style="color:#c0392b;padding:24px;margin:0;font-weight:600">&#9888; Could not load template image. Verify the URL is publicly accessible.</p>');
                });
                if ($img.attr('src') === url) {
                    showSpinner(false);
                    buildHandles();
                    return;
                }
                showSpinner(true);
                $img.attr('src', url);
                if ($img[0].complete && $img[0].naturalWidth) {
                    showSpinner(false);
                    buildHandles();
                } else {
                    $img.one('load.cgcanvas', function () {
                        showSpinner(false);
                        buildHandles();
                    });
                }
            }

            // ── Field count rows helper ───────────────────────────────────

            function updateFieldRows() {
                var count = parseInt($('#template_field_count').val(), 10);
                $('.cg-field-row-card').each(function () {
                    $(this).toggle(parseInt($(this).data('field-index'), 10) <= count);
                });
                applyColors();
            }

            // ── All event bindings + boot deferred until DOM+scripts ready ──
            // jQuery UI Draggable is a footer script; wrapping in $(function(){})
            // ensures it is loaded before buildHandles() calls .draggable().
            $(function () {

                $('#cg_field_count_dec').on('click', function () {
                    var inp = $('#template_field_count');
                    inp.val(Math.max(parseInt(inp.attr('min'), 10), parseInt(inp.val(), 10) - 1));
                    updateFieldRows();
                    setTimeout(buildHandles, 20);
                });
                $('#cg_field_count_inc').on('click', function () {
                    var inp = $('#template_field_count');
                    inp.val(Math.min(parseInt(inp.attr('max'), 10), parseInt(inp.val(), 10) + 1));
                    updateFieldRows();
                    setTimeout(buildHandles, 20);
                });
                $('#template_field_count').on('change', function () { updateFieldRows(); setTimeout(buildHandles, 20); });

                // ── Event bindings ────────────────────────────────────────────

                $('#template_url').on('change', initCanvas);
                $('#orientation').on('change', function () { updateCanvasSize(); repositionAll(); });
                $('#qr_enabled, #serial_number_display').on('change', buildHandles);
                $('#qr_size,#qr_position_x,#qr_position_y,#serial_number_position_x,#serial_number_position_y').on('change input', function () { positionQRHandle(); positionSerialHandle(); });

                for (var _n = 1; _n <= MAX_FIELDS; _n++) {
                    (function (n) {
                        $('#field_' + n + '_position_x, #field_' + n + '_position_y, #field_' + n + '_width').on('change input', function () { positionHandle(n); });
                        $('#field_' + n + '_visible').on('change', function () { positionHandle(n); });
                    })(_n);
                }

                // ── Media uploader ────────────────────────────────────────────

                $('#cg_upload_template_btn').on('click', function(e) {
                    e.preventDefault();
                    var frame = wp.media({
                        title: 'Select Template Image',
                        button: { text: 'Use this image' },
                        multiple: false
                    });
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#template_url').val(attachment.url).trigger('change');
                        $('#cg_template_preview').html('<img src="' + attachment.url + '" style="max-width:300px;max-height:180px;border:1px solid #ddd;border-radius:4px;display:block;">').show();
                    });
                    frame.open();
                });

                // ── Boot ──────────────────────────────────────────────────────
                updateFieldRows();
                applyColors();
                initCanvas();

                // ── Preview Certificate button ────────────────────────────────
                $('#cg_preview_cert_btn').on('click', function () {
                    var $btn    = $(this);
                    var $status = $('#cg_preview_status');
                    $btn.prop('disabled', true);
                    $status.text('Generating…');
                    var data = $('#cg-template-form').serializeArray();
                    data.push({ name: 'action',      value: 'cg_preview_template' });
                    data.push({ name: 'nonce',       value: '<?php echo wp_create_nonce('cg_admin_preview_nonce'); ?>' });
                    data.push({ name: 'template_id', value: '<?php echo (int) $id; ?>' });
                    var $form = $('<form method="POST" target="_blank" action="' + ajaxurl + '">');
                    $.each(data, function (_, field) {
                        $form.append($('<input type="hidden">').attr('name', field.name).val(field.value));
                    });
                    $('body').append($form);
                    $form.submit().remove();
                    setTimeout(function () { $btn.prop('disabled', false); $status.text(''); }, 1500);
                });

                // Row card click-to-activate (moved inside ready to ensure consistent binding)
                $(document).on('click', '.cg-field-row-card', function (e) {
                    if ($(e.target).is('input, select, button')) return;
                    var n = parseInt($(this).data('field-index'), 10);
                    setActiveRow(n);
                    if ($('#cg-row-' + n).hasClass('cg-row-active') && clickModeField !== n) {
                        enterClickMode(n);
                    }
                });

                $(document).on('click', '.cg-center-btn', function (e) {
                    e.stopPropagation();
                    var n = parseInt($(this).data('field'), 10);
                    var d = pageDims();
                    $('#field_' + n + '_position_x').val(Math.round(d.w / 2));
                    $('#field_' + n + '_position_y').val(Math.round(d.h / 2));
                    positionHandle(n);
                    setActiveRow(n);
                });

                $('#cg-click-mode-cancel').on('click', cancelClickMode);

                $('#cg-zoom-slider').on('input change', function () {
                    zoomPct = parseInt($(this).val(), 10);
                    $('#cg-zoom-label').text(zoomPct + '%');
                    updateCanvasSize();
                    repositionAll();
                });

                $('#cg-grid-toggle').on('change', drawGrid);

            }); // end $(document).ready

        }(jQuery));
        </script>
        <?php
    }
}
