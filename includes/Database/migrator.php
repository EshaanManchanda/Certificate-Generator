<?php
if (!defined('ABSPATH')) exit;

class CG_Migrator {
    public static function run() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'certificate_generator';
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
              id mediumint(9) NOT NULL AUTO_INCREMENT,
              student_name varchar(255) NOT NULL,
              certificate_data text NOT NULL,
              created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
              issued_at datetime NULL,
              expires_at datetime NULL,
              updated_at datetime NULL,
              generated_via enum('manual','bulk','api','automatic') DEFAULT 'manual',
              serial_number varchar(50) NULL,
              certificate_type varchar(100) NULL,
              PRIMARY KEY (id),
              INDEX idx_issued_at (issued_at),
              INDEX idx_expires_at (expires_at),
              INDEX idx_serial_number (serial_number),
              INDEX idx_certificate_type (certificate_type)
            ) $charset_collate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        } else {
            $columns = $wpdb->get_col("DESCRIBE $table_name", 0);
            $alter_queries = [];
            
            if (!in_array('issued_at', $columns)) $alter_queries[] = "ALTER TABLE $table_name ADD COLUMN issued_at datetime NULL AFTER created_at";
            if (!in_array('expires_at', $columns)) $alter_queries[] = "ALTER TABLE $table_name ADD COLUMN expires_at datetime NULL AFTER issued_at";
            if (!in_array('updated_at', $columns)) $alter_queries[] = "ALTER TABLE $table_name ADD COLUMN updated_at datetime NULL AFTER expires_at";
            if (!in_array('generated_via', $columns)) $alter_queries[] = "ALTER TABLE $table_name ADD COLUMN generated_via enum('manual','bulk','api','automatic') DEFAULT 'manual' AFTER updated_at";
            if (!in_array('serial_number', $columns)) $alter_queries[] = "ALTER TABLE $table_name ADD COLUMN serial_number varchar(50) NULL AFTER updated_at";
            if (!in_array('certificate_type', $columns)) $alter_queries[] = "ALTER TABLE $table_name ADD COLUMN certificate_type varchar(100) NULL AFTER serial_number";

            $indexes = $wpdb->get_col("SHOW INDEX FROM $table_name WHERE Key_name != 'PRIMARY'", 2);
            if (!in_array('idx_issued_at', $indexes)) $alter_queries[] = "ALTER TABLE $table_name ADD INDEX idx_issued_at (issued_at)";
            if (!in_array('idx_expires_at', $indexes)) $alter_queries[] = "ALTER TABLE $table_name ADD INDEX idx_expires_at (expires_at)";
            if (!in_array('idx_serial_number', $indexes)) $alter_queries[] = "ALTER TABLE $table_name ADD INDEX idx_serial_number (serial_number)";
            if (!in_array('idx_certificate_type', $indexes)) $alter_queries[] = "ALTER TABLE $table_name ADD INDEX idx_certificate_type (certificate_type)";

            foreach ($alter_queries as $query) {
                $wpdb->query($query);
            }
        }

        self::migrate_existing_data($table_name);
    }

    private static function migrate_existing_data($table_name) {
        global $wpdb;
        $wpdb->query("UPDATE $table_name SET issued_at = created_at, updated_at = created_at WHERE issued_at IS NULL");
        $wpdb->query("UPDATE $table_name SET generated_via = 'manual' WHERE generated_via IS NULL OR generated_via = ''");
    }
}