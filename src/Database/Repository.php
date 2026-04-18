<?php
declare(strict_types=1);

namespace CertificateGenerator\Database;

/**
 * Base repository providing common database operations.
 */
abstract class Repository {
    protected string $table;

    public function __construct(string $table) {
        global $wpdb;
        $this->table = $wpdb->prefix . $table;
    }

    protected function get_wpdb(): \wpdb {
        global $wpdb;
        return $wpdb;
    }

    private function fetch_array($result): ?array {
        global $wpdb;
        return $wpdb->fetch_array($result);
    }

    public function find(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $id
        ), \ARRAY_A);

        return $row ?: null;
    }

    public function find_by(string $column, $value): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$column} = %s",
            $value
        ), \ARRAY_A);

        return $row ?: null;
    }

    public function all(int $limit = 100, int $offset = 0): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), \ARRAY_A);
    }

    public function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    public function delete(int $id): bool {
        global $wpdb;
        $deleted = $wpdb->delete($this->table, ['id' => $id], ['%d']);
        return $deleted > 0;
    }

    public function insert(array $data): int {
        global $wpdb;
        $wpdb->insert($this->table, $data);
        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;
        $updated = $wpdb->update($this->table, $data, ['id' => $id], null, ['%d']);
        return $updated !== false;
    }
}
