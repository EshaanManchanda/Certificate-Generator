<?php
declare(strict_types=1);

namespace CertificateGenerator\Database;

/**
 * Migrates data from WordPress CPTs to custom tables.
 * Safe to run multiple times - uses INSERT ... ON DUPLICATE KEY UPDATE pattern.
 * Does NOT delete any CPT data.
 */
class DataMigration {

    private CustomTables $tables;

    public function __construct() {
        $this->tables = CustomTables::instance();
    }

    /**
     * Run all migrations. Safe to call multiple times.
     */
    public function run_all(): array {
        $results = [
            'schools' => $this->migrate_schools(),
            'students' => $this->migrate_students(),
            'teachers' => $this->migrate_teachers(),
            'templates' => $this->migrate_templates(),
            'certificates' => $this->migrate_certificates(),
        ];

        update_option('cg_data_migration_completed', current_time('mysql'));

        return $results;
    }

    /**
     * Migrate schools from CPT to custom table.
     */
    public function migrate_schools(): array {
        global $wpdb;
        $table = $this->tables->get_table('schools');
        if (empty($table)) return ['error' => 'Table not found'];

        $posts = get_posts([
            'post_type' => 'schools',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'all',
        ]);

        if (empty($posts)) return ['migrated' => 0, 'message' => 'No schools found'];

        $migrated = 0;
        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID);

            $wpdb->replace($table, [
                'wp_post_id'    => $post->ID,
                'school_name'   => $post->post_title,
                'address'       => $meta['school_address'][0] ?? '',
                'city'          => $meta['school_city'][0] ?? '',
                'state'         => $meta['school_state'][0] ?? '',
                'country'       => $meta['school_country'][0] ?? '',
                'postal_code'   => $meta['school_postal_code'][0] ?? '',
                'phone'         => $meta['school_phone'][0] ?? $meta['phone'][0] ?? '',
                'email'         => $meta['school_email'][0] ?? $meta['email'][0] ?? '',
                'website'       => $meta['school_website'][0] ?? '',
                'principal_name'   => $meta['school_principal'][0] ?? $meta['principal_name'][0] ?? '',
                'certificate_type' => $meta['certificate_type'][0] ?? '',
                'issue_date'       => $meta['issue_date'][0] ?: null,
                'serial_number'    => $meta['certificate_serial_number'][0] ?? null,
                'status'        => $post->post_status === 'publish' ? 'active' : 'inactive',
                'created_at'    => $post->post_date,
                'updated_at'    => $post->post_modified,
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

            $migrated++;
        }

        return ['migrated' => $migrated, 'message' => "Migrated $migrated schools"];
    }

    /**
     * Migrate students from CPT to custom table.
     */
    public function migrate_students(): array {
        global $wpdb;
        $table = $this->tables->get_table('students');
        if (empty($table)) return ['error' => 'Table not found'];

        $posts = get_posts([
            'post_type' => 'students',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'all',
        ]);

        if (empty($posts)) return ['migrated' => 0, 'message' => 'No students found'];

        $core_keys = ['student_name', 'email', 'phone', 'school_name', 'school_id', 'certificate_type',
                      'issue_date', 'serial_number', 'enrollment_date', 'graduation_date', 'status',
                      'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields'];

        $migrated = 0;
        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID);

            $school_name = $meta['school_name'][0] ?? '';
            $school_id = null;
            if (!empty($school_name)) {
                $school_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->tables->get_table('schools')} WHERE school_name = %s",
                    $school_name
                ));
            }

            $extra = [];
            foreach ($meta as $key => $values) {
                $clean = preg_replace('/^field_/', '', $key);
                if (!in_array($clean, $core_keys, true) && !in_array($key, $core_keys, true) && !empty($values[0])) {
                    $extra[$clean] = $values[0];
                }
            }

            $wpdb->replace($table, [
                'wp_post_id'       => $post->ID,
                'student_name'     => $post->post_title,
                'email'            => $meta['email'][0] ?? '',
                'phone'            => $meta['phone'][0] ?? '',
                'school_id'        => $school_id,
                'school_name'      => $school_name,
                'certificate_type' => $meta['certificate_type'][0] ?? '',
                'issue_date'       => $meta['issue_date'][0] ?: null,
                'serial_number'    => $meta['certificate_serial_number'][0] ?? null,
                'enrollment_date'  => $meta['enrollment_date'][0] ?: null,
                'graduation_date'  => $meta['graduation_date'][0] ?: null,
                'extra_fields'     => !empty($extra) ? wp_json_encode($extra) : null,
                'status'           => $post->post_status === 'publish' ? 'active' : 'inactive',
                'created_at'       => $post->post_date,
                'updated_at'       => $post->post_modified,
            ], ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

            $migrated++;
        }

        return ['migrated' => $migrated, 'message' => "Migrated $migrated students"];
    }

    /**
     * Migrate teachers from CPT to custom table.
     */
    public function migrate_teachers(): array {
        global $wpdb;
        $table = $this->tables->get_table('teachers');
        if (empty($table)) return ['error' => 'Table not found'];

        $posts = get_posts([
            'post_type' => 'teachers',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'all',
        ]);

        if (empty($posts)) return ['migrated' => 0, 'message' => 'No teachers found'];

        $core_keys_t = ['teacher_name', 'email', 'phone', 'school_name', 'school_id', 'department',
                        'certificate_type', 'issue_date', 'serial_number', 'hire_date', 'status',
                        'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields'];

        $migrated = 0;
        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID);

            $school_name = $meta['school_name'][0] ?? '';
            $school_id = null;
            if (!empty($school_name)) {
                $school_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->tables->get_table('schools')} WHERE school_name = %s",
                    $school_name
                ));
            }

            $extra = [];
            foreach ($meta as $key => $values) {
                $clean = preg_replace('/^field_/', '', $key);
                if (!in_array($clean, $core_keys_t, true) && !in_array($key, $core_keys_t, true) && !empty($values[0])) {
                    $extra[$clean] = $values[0];
                }
            }

            $wpdb->replace($table, [
                'wp_post_id'       => $post->ID,
                'teacher_name'     => $post->post_title,
                'email'            => $meta['email'][0] ?? '',
                'phone'            => $meta['phone'][0] ?? '',
                'school_id'        => $school_id,
                'school_name'      => $school_name,
                'department'       => $meta['department'][0] ?? '',
                'certificate_type' => $meta['certificate_type'][0] ?? '',
                'issue_date'       => $meta['issue_date'][0] ?: null,
                'serial_number'    => $meta['certificate_serial_number'][0] ?? null,
                'hire_date'        => $meta['hire_date'][0] ?: null,
                'extra_fields'     => !empty($extra) ? wp_json_encode($extra) : null,
                'status'           => $post->post_status === 'publish' ? 'active' : 'inactive',
                'created_at'       => $post->post_date,
                'updated_at'       => $post->post_modified,
            ], ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

            $migrated++;
        }

        return ['migrated' => $migrated, 'message' => "Migrated $migrated teachers"];
    }

    /**
     * Migrate certificate templates from CPT to custom table.
     */
    public function migrate_templates(): array {
        global $wpdb;
        $table = $this->tables->get_table('certificate_templates');
        if (empty($table)) return ['error' => 'Table not found'];

        $posts = get_posts([
            'post_type' => 'certificates',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'all',
        ]);

        if (empty($posts)) return ['migrated' => 0, 'message' => 'No templates found'];

        $migrated = 0;
        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID);

            $wpdb->replace($table, [
                'wp_post_id' => $post->ID,
                'template_name' => $post->post_title,
                'certificate_type' => $meta['certificate_type'][0] ?? '',
                'event_date' => $meta['event_date'][0] ?? null,
                'template_url' => $meta['template_url'][0] ?? '',
                'orientation' => $meta['template_orientation'][0] ?? 'landscape',
                'page_size' => $meta['certificate_page_size'][0] ?? 'A4',
                'font_style' => $meta['font_style'][0] ?? 'helvetica',
                'font_size' => (int) ($meta['font_size'][0] ?? 12),
                'font_color' => $meta['font_color'][0] ?? '#000000',
                'qr_enabled' => (int) ($meta['qr_enabled'][0] ?? 0),
                'qr_size' => (int) ($meta['qr_size'][0] ?? 15),
                'qr_position_x' => (float) ($meta['qr_position_x'][0] ?? 250.00),
                'qr_position_y' => (float) ($meta['qr_position_y'][0] ?? 180.00),
                'qr_error_correction' => $meta['qr_error_correction'][0] ?? 'L',
                'qr_data_fields' => $meta['qr_data_fields'][0] ?? null,
                'serial_number_display' => (int) ($meta['serial_number_display'][0] ?? 0),
                'serial_number_position_x' => (float) ($meta['serial_number_position_x'][0] ?? 105.00),
                'serial_number_position_y' => (float) ($meta['serial_number_position_y'][0] ?? 200.00),
                'serial_number_font_size' => (int) ($meta['serial_number_font_size'][0] ?? 10),
                'expiration_period_unit' => $meta['expiration_period_unit'][0] ?? 'never',
                'expiration_period_value' => (int) ($meta['expiration_period_value'][0] ?? 0),
                'status' => $post->post_status === 'publish' ? 'published' : 'draft',
                'created_at' => $post->post_date,
                'updated_at' => $post->post_modified,
            ], [
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
                '%d', '%d', '%f', '%f', '%s', '%s', '%d', '%f', '%f', '%d',
                '%s', '%d', '%s', '%s', '%s'
            ]);

            $migrated++;
        }

        return ['migrated' => $migrated, 'message' => "Migrated $migrated templates"];
    }

    /**
     * Migrate existing certificates from wp_certificate_generator to custom table.
     */
    public function migrate_certificates(): array {
        global $wpdb;
        $table = $this->tables->get_table('certificates');
        if (empty($table)) return ['error' => 'Table not found'];

        $old_table = $wpdb->prefix . 'certificate_generator';
        $old_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table)) === $old_table;

        if (!$old_exists) return ['migrated' => 0, 'message' => 'No old certificate table found'];

        $certs = $wpdb->get_results("SELECT * FROM $old_table", ARRAY_A);

        if (empty($certs)) return ['migrated' => 0, 'message' => 'No certificates to migrate'];

        $migrated = 0;
        foreach ($certs as $cert) {
            $cert_data = maybe_unserialize($cert['certificate_data']);

            $wpdb->replace($table, [
                'recipient_name' => $cert['student_name'],
                'certificate_type' => $cert_data['certificate_type'] ?? '',
                'serial_number' => $cert['serial_number'] ?? null,
                'issued_at' => $cert['issued_at'] ?? $cert['created_at'],
                'expires_at' => $cert['expires_at'] ?? null,
                'generated_via' => $cert['generated_via'] ?? 'manual',
                'certificate_data' => is_array($cert_data) ? wp_json_encode($cert_data) : null,
                'status' => 'generated',
                'created_at' => $cert['created_at'],
                'updated_at' => $cert['updated_at'] ?? $cert['created_at'],
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

            $migrated++;
        }

        return ['migrated' => $migrated, 'message' => "Migrated $migrated certificates"];
    }

    /**
     * Sync a single student post to custom table.
     * Called on save_post hook. Captures additional fields into extra_fields JSON.
     */
    public function sync_student(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'students') return;

        global $wpdb;
        $table = $this->tables->get_table('students');
        $meta = get_post_meta($post_id);

        // Core fields
        $core_data = [
            'wp_post_id' => $post_id,
            'student_name' => $post->post_title,
            'email' => $meta['email'][0] ?? '',
            'phone' => $meta['phone'][0] ?? '',
            'school_name' => $meta['school_name'][0] ?? '',
            'status' => $post->post_status === 'publish' ? 'active' : 'inactive',
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
        ];

        // Additional fields → extra_fields JSON
        $core_keys = ['student_name', 'email', 'phone', 'school_name', 'status', 'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields'];
        $extra_fields = [];
        foreach ($meta as $key => $values) {
            $clean_key = preg_replace('/^field_/', '', $key);
            if (!in_array($clean_key, $core_keys, true) && !in_array($key, $core_keys, true)) {
                if (!empty($values[0])) {
                    $extra_fields[$clean_key] = $values[0];
                }
            }
        }
        if (!empty($extra_fields)) {
            $core_data['extra_fields'] = wp_json_encode($extra_fields);
        }

        $wpdb->replace($table, $core_data);
    }

    /**
     * Sync a single teacher post to custom table.
     */
    public function sync_teacher(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'teachers') return;

        global $wpdb;
        $table = $this->tables->get_table('teachers');
        $meta = get_post_meta($post_id);

        $core_data = [
            'wp_post_id' => $post_id,
            'teacher_name' => $post->post_title,
            'email' => $meta['email'][0] ?? '',
            'phone' => $meta['phone'][0] ?? '',
            'school_name' => $meta['school_name'][0] ?? '',
            'department' => $meta['department'][0] ?? '',
            'status' => $post->post_status === 'publish' ? 'active' : 'inactive',
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
        ];

        $core_keys = ['teacher_name', 'email', 'phone', 'school_name', 'department', 'status', 'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields'];
        $extra_fields = [];
        foreach ($meta as $key => $values) {
            $clean_key = preg_replace('/^field_/', '', $key);
            if (!in_array($clean_key, $core_keys, true) && !in_array($key, $core_keys, true)) {
                if (!empty($values[0])) {
                    $extra_fields[$clean_key] = $values[0];
                }
            }
        }
        if (!empty($extra_fields)) {
            $core_data['extra_fields'] = wp_json_encode($extra_fields);
        }

        $wpdb->replace($table, $core_data);
    }

    /**
     * Sync a single school post to custom table.
     */
    public function sync_school(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'schools') return;

        global $wpdb;
        $table = $this->tables->get_table('schools');
        $meta = get_post_meta($post_id);

        $core_data = [
            'wp_post_id' => $post_id,
            'school_name' => $post->post_title,
            'address' => $meta['school_address'][0] ?? '',
            'city' => $meta['school_city'][0] ?? '',
            'state' => $meta['school_state'][0] ?? '',
            'country' => $meta['school_country'][0] ?? '',
            'postal_code' => $meta['school_postal_code'][0] ?? '',
            'phone' => $meta['school_phone'][0] ?? '',
            'email' => $meta['school_email'][0] ?? '',
            'website' => $meta['school_website'][0] ?? '',
            'principal_name' => $meta['school_principal'][0] ?? '',
            'status' => $post->post_status === 'publish' ? 'active' : 'inactive',
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
        ];

        $core_keys = ['school_name', 'address', 'city', 'state', 'country', 'postal_code', 'phone', 'email', 'website', 'principal_name', 'status', 'wp_post_id', 'id', 'created_at', 'updated_at', 'extra_fields'];
        $extra_fields = [];
        foreach ($meta as $key => $values) {
            $clean_key = preg_replace('/^field_/', '', $key);
            if (!in_array($clean_key, $core_keys, true) && !in_array($key, $core_keys, true)) {
                if (!empty($values[0])) {
                    $extra_fields[$clean_key] = $values[0];
                }
            }
        }
        if (!empty($extra_fields)) {
            $core_data['extra_fields'] = wp_json_encode($extra_fields);
        }

        $wpdb->replace($table, $core_data);
    }

    /**
     * Sync a certificate template post to custom table.
     */
    public function sync_template(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'certificates') return;

        global $wpdb;
        $table = $this->tables->get_table('certificate_templates');
        $meta = get_post_meta($post_id);

        $wpdb->replace($table, [
            'wp_post_id' => $post_id,
            'template_name' => $post->post_title,
            'certificate_type' => $meta['certificate_type'][0] ?? '',
            'event_date' => $meta['event_date'][0] ?? null,
            'template_url' => $meta['template_url'][0] ?? '',
            'orientation' => $meta['template_orientation'][0] ?? 'landscape',
            'page_size' => $meta['certificate_page_size'][0] ?? 'A4',
            'font_style' => $meta['font_style'][0] ?? 'helvetica',
            'font_size' => (int) ($meta['font_size'][0] ?? 12),
            'font_color' => $meta['font_color'][0] ?? '#000000',
            'qr_enabled' => (int) ($meta['qr_enabled'][0] ?? 0),
            'qr_size' => (int) ($meta['qr_size'][0] ?? 15),
            'qr_position_x' => (float) ($meta['qr_position_x'][0] ?? 250.00),
            'qr_position_y' => (float) ($meta['qr_position_y'][0] ?? 180.00),
            'qr_error_correction' => $meta['qr_error_correction'][0] ?? 'L',
            'qr_data_fields' => $meta['qr_data_fields'][0] ?? null,
            'serial_number_display' => (int) ($meta['serial_number_display'][0] ?? 0),
            'serial_number_position_x' => (float) ($meta['serial_number_position_x'][0] ?? 105.00),
            'serial_number_position_y' => (float) ($meta['serial_number_position_y'][0] ?? 200.00),
            'serial_number_font_size' => (int) ($meta['serial_number_font_size'][0] ?? 10),
            'expiration_period_unit' => $meta['expiration_period_unit'][0] ?? 'never',
            'expiration_period_value' => (int) ($meta['expiration_period_value'][0] ?? 0),
            'status' => $post->post_status === 'publish' ? 'published' : 'draft',
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
        ], [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
            '%d', '%d', '%f', '%f', '%s', '%s', '%d', '%f', '%f', '%d',
            '%s', '%d', '%s', '%s', '%s'
        ]);
    }

    /**
     * Log a certificate generation to custom table.
     * Called alongside existing certificate generation.
     */
    public function log_certificate(array $data): int {
        global $wpdb;
        $table = $this->tables->get_table('certificates');

        $wpdb->insert($table, [
            'template_id' => $data['template_id'] ?? null,
            'student_id' => $data['student_id'] ?? null,
            'recipient_name' => $data['student_name'] ?? '',
            'recipient_email' => $data['email'] ?? null,
            'recipient_type' => $data['recipient_type'] ?? 'student',
            'certificate_type' => $data['certificate_type'] ?? '',
            'serial_number' => $data['serial_number'] ?? null,
            'issued_at' => $data['issued_at'] ?? current_time('mysql'),
            'expires_at' => $data['expires_at'] ?? null,
            'generated_via' => $data['generated_via'] ?? 'manual',
            'pdf_path' => $data['pdf_path'] ?? null,
            'pdf_url' => $data['pdf_url'] ?? null,
            'certificate_data' => isset($data['certificate_data']) ? wp_json_encode($data['certificate_data']) : null,
            'status' => 'generated',
            'created_at' => current_time('mysql'),
        ], [
            '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Log an email send to custom table.
     */
    public function log_email(array $data): int {
        global $wpdb;
        $table = $this->tables->get_table('email_logs');

        $wpdb->insert($table, [
            'certificate_id' => $data['certificate_id'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? '',
            'recipient_name' => $data['recipient_name'] ?? '',
            'subject' => $data['subject'] ?? '',
            'status' => $data['status'] ?? 'sent',
            'error_message' => $data['error_message'] ?? null,
            'sent_at' => $data['sent_at'] ?? current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }
}
