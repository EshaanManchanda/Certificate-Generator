<?php
declare(strict_types=1);

namespace CertificateGenerator\Services;

use CertificateGenerator\Database\CustomTables;

/**
 * Bulk import/export service that works with custom tables.
 * Handles splitting core fields and extra fields into JSON.
 * Compatible with existing CSV format - no changes needed to CSV files.
 */
class BulkIO {

    private CustomTables $tables;

    public function __construct() {
        $this->tables = CustomTables::instance();
    }

    /**
     * Import students from CSV data.
     * Auto-splits core fields vs extra fields.
     *
     * @param array $headers CSV headers
     * @param array $rows CSV data rows
     * @return array{imported: int, skipped: int, errors: array}
     */
    public function import_students(array $headers, array $rows): array {
        $table = $this->tables->get_table('students');
        if (empty($table)) return ['imported' => 0, 'skipped' => 0, 'errors' => ['Table not found']];

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $data = array_combine($headers, $row);
                if ($data === false) {
                    $errors[] = "Row $index: Column count mismatch";
                    $skipped++;
                    continue;
                }

                // Clean data
                $data = array_map('sanitize_text_field', $data);
                $data = array_filter($data, fn($v) => $v !== '');

                if (empty($data['student_name'])) {
                    $errors[] = "Row $index: student_name is required";
                    $skipped++;
                    continue;
                }

                // Split into core + extra
                $prepared = FieldManager::prepare_for_db('students', $data);

                // Check for existing by email
                if (!empty($data['email'])) {
                    $existing = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                        "SELECT id FROM $table WHERE email = %s",
                        $data['email']
                    ));
                    if ($existing) {
                        // Update existing
                        $GLOBALS['wpdb']->update($table, $prepared, ['id' => $existing]);
                    } else {
                        // Insert new
                        $GLOBALS['wpdb']->insert($table, $prepared);
                    }
                } else {
                    $GLOBALS['wpdb']->insert($table, $prepared);
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row $index: " . $e->getMessage();
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import teachers from CSV data.
     */
    public function import_teachers(array $headers, array $rows): array {
        $table = $this->tables->get_table('teachers');
        if (empty($table)) return ['imported' => 0, 'skipped' => 0, 'errors' => ['Table not found']];

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $data = array_combine($headers, $row);
                if ($data === false) {
                    $errors[] = "Row $index: Column count mismatch";
                    $skipped++;
                    continue;
                }

                $data = array_map('sanitize_text_field', $data);
                $data = array_filter($data, fn($v) => $v !== '');

                if (empty($data['teacher_name'])) {
                    $errors[] = "Row $index: teacher_name is required";
                    $skipped++;
                    continue;
                }

                $prepared = FieldManager::prepare_for_db('teachers', $data);

                if (!empty($data['email'])) {
                    $existing = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                        "SELECT id FROM $table WHERE email = %s",
                        $data['email']
                    ));
                    if ($existing) {
                        $GLOBALS['wpdb']->update($table, $prepared, ['id' => $existing]);
                    } else {
                        $GLOBALS['wpdb']->insert($table, $prepared);
                    }
                } else {
                    $GLOBALS['wpdb']->insert($table, $prepared);
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row $index: " . $e->getMessage();
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import schools from CSV data.
     */
    public function import_schools(array $headers, array $rows): array {
        $table = $this->tables->get_table('schools');
        if (empty($table)) return ['imported' => 0, 'skipped' => 0, 'errors' => ['Table not found']];

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $data = array_combine($headers, $row);
                if ($data === false) {
                    $errors[] = "Row $index: Column count mismatch";
                    $skipped++;
                    continue;
                }

                $data = array_map('sanitize_text_field', $data);
                $data = array_filter($data, fn($v) => $v !== '');

                if (empty($data['school_name'])) {
                    $errors[] = "Row $index: school_name is required";
                    $skipped++;
                    continue;
                }

                $prepared = FieldManager::prepare_for_db('schools', $data);

                $existing = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                    "SELECT id FROM $table WHERE school_name = %s",
                    $data['school_name']
                ));
                if ($existing) {
                    $GLOBALS['wpdb']->update($table, $prepared, ['id' => $existing]);
                } else {
                    $GLOBALS['wpdb']->insert($table, $prepared);
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Row $index: " . $e->getMessage();
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Export students to CSV format.
     * Flattens extra_fields JSON into columns.
     *
     * @param array $filters Optional filters
     * @return array{headers: array, rows: array}
     */
    public function export_students(array $filters = []): array {
        global $wpdb;
        $table = $this->tables->get_table('students');
        if (empty($table)) return ['headers' => [], 'rows' => []];

        $where = '1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['school_name'])) {
            $where .= ' AND school_name = %s';
            $params[] = $filters['school_name'];
        }

        $sql = "SELECT * FROM $table WHERE $where ORDER BY id ASC";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        // Discover all extra field keys
        $extra_keys = FieldManager::get_all_extra_keys('students');

        // Build headers: core fields + discovered extra fields
        $core_headers = ['id', 'student_name', 'email', 'phone', 'school_name', 'status'];
        $headers = array_merge($core_headers, $extra_keys);

        // Build rows
        $csv_rows = [];
        foreach ($rows as $row) {
            $flat = FieldManager::prepare_for_display($row);
            $csv_row = [];
            foreach ($headers as $h) {
                $csv_row[] = $flat[$h] ?? '';
            }
            $csv_rows[] = $csv_row;
        }

        return ['headers' => $headers, 'rows' => $csv_rows];
    }

    /**
     * Export teachers to CSV format.
     */
    public function export_teachers(array $filters = []): array {
        global $wpdb;
        $table = $this->tables->get_table('teachers');
        if (empty($table)) return ['headers' => [], 'rows' => []];

        $where = '1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }

        $sql = "SELECT * FROM $table WHERE $where ORDER BY id ASC";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        $extra_keys = FieldManager::get_all_extra_keys('teachers');

        $core_headers = ['id', 'teacher_name', 'email', 'phone', 'school_name', 'department', 'status'];
        $headers = array_merge($core_headers, $extra_keys);

        $csv_rows = [];
        foreach ($rows as $row) {
            $flat = FieldManager::prepare_for_display($row);
            $csv_row = [];
            foreach ($headers as $h) {
                $csv_row[] = $flat[$h] ?? '';
            }
            $csv_rows[] = $csv_row;
        }

        return ['headers' => $headers, 'rows' => $csv_rows];
    }

    /**
     * Export schools to CSV format.
     */
    public function export_schools(array $filters = []): array {
        global $wpdb;
        $table = $this->tables->get_table('schools');
        if (empty($table)) return ['headers' => [], 'rows' => []];

        $where = '1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }

        $sql = "SELECT * FROM $table WHERE $where ORDER BY id ASC";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);
        $extra_keys = FieldManager::get_all_extra_keys('schools');

        $core_headers = ['id', 'school_name', 'address', 'city', 'state', 'country', 'postal_code', 'phone', 'email', 'website', 'principal_name', 'status'];
        $headers = array_merge($core_headers, $extra_keys);

        $csv_rows = [];
        foreach ($rows as $row) {
            $flat = FieldManager::prepare_for_display($row);
            $csv_row = [];
            foreach ($headers as $h) {
                $csv_row[] = $flat[$h] ?? '';
            }
            $csv_rows[] = $csv_row;
        }

        return ['headers' => $headers, 'rows' => $csv_rows];
    }
}
