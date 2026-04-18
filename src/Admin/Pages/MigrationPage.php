<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

use CertificateGenerator\Database\CustomTables;
use CertificateGenerator\Database\CptToSqlMigration;

/**
 * Complete CPT → SQL Migration admin page.
 * One-click migration with verification and rollback options.
 */
class MigrationPage {

    private string $slug = 'cg-sql-migration';
    private string $title = 'CPT → SQL Migration';

    public function register(): void {
        add_submenu_page(
            'cg-dashboard',
            $this->title,
            'SQL Migration',
            'manage_options',
            $this->slug,
            [$this, 'render']
        );
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'certificate-generator'));
        }

        $tables = CustomTables::instance();
        $message = '';
        $message_type = '';

        // Handle CPT → SQL migration
        if (isset($_POST['cg_run_cpt_to_sql']) && check_admin_referer('cg_run_cpt_to_sql')) {
            $migrator = new CptToSqlMigration();
            $stats = $migrator->run();
            $message = 'CPT → SQL migration completed!';
            $message_type = 'success';
            foreach ($stats as $entity => $count) {
                $message .= '<br>' . ucfirst($entity) . ': ' . number_format($count) . ' records migrated';
            }
        }

        // Handle rollback
        if (isset($_POST['cg_rollback_migration']) && check_admin_referer('cg_rollback_migration')) {
            $this->rollback_migration($tables);
            $message = 'Rollback complete. SQL tables cleared. CPT data is intact. You can re-run migration at any time.';
            $message_type = 'warning';
        }

        // Handle table verification
        if (isset($_POST['cg_verify_tables']) && check_admin_referer('cg_verify_tables')) {
            $tables->create_all();
            $message = 'All tables verified/created successfully!';
            $message_type = 'success';
        }

        // Handle issue_date normalization (one-time fix for d-m-Y → Y-m-d)
        if (isset($_POST['cg_fix_issue_dates']) && check_admin_referer('cg_fix_issue_dates')) {
            $fixed = $this->fix_issue_dates();
            $message = "Issue date normalization complete. <strong>{$fixed}</strong> records updated to Y-m-d storage format.";
            $message_type = $fixed > 0 ? 'success' : 'info';
        }

        // Handle CPT cleanup (after verification)
        if (isset($_POST['cg_cleanup_cpts']) && check_admin_referer('cg_cleanup_cpts')) {
            $this->cleanup_cpts();
            $message = 'CPT data cleaned up. Plugin now uses SQL tables exclusively.';
            $message_type = 'success';
        }

        // Get status
        $table_status = [];
        foreach (array_keys($tables->get_all_tables()) as $name) {
            $table_status[$name] = $tables->table_exists($name);
        }
        $all_tables_exist = !in_array(false, $table_status, true);

        $migration_completed = get_option('cg_cpt_to_sql_migration_completed', false);
        $migration_stats = get_option('cg_cpt_to_sql_migration_stats', []);
        $cpts_cleaned = get_option('cg_cpts_cleaned_up', false);

        // Count CPT records
        $student_counts = wp_count_posts('students');
        $teacher_counts = wp_count_posts('teachers');
        $school_counts = wp_count_posts('schools');
        $template_counts = wp_count_posts('certificates');
        $cpt_counts = [
            'students' => isset($student_counts->publish) ? (int) $student_counts->publish : 0,
            'teachers' => isset($teacher_counts->publish) ? (int) $teacher_counts->publish : 0,
            'schools' => isset($school_counts->publish) ? (int) $school_counts->publish : 0,
            'templates' => isset($template_counts->publish) ? (int) $template_counts->publish : 0,
        ];

        // Count SQL records
        global $wpdb;
        $sql_counts = [];
        foreach ($tables->get_all_tables() as $name => $table) {
            if (in_array($name, ['students', 'teachers', 'schools', 'certificate_templates', 'certificates', 'email_logs'])) {
                $sql_counts[$name] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->title); ?></h1>

            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo wp_kses_post($message); ?></p>
                </div>
            <?php endif; ?>

            <!-- Step 1: Table Status -->
            <div class="card" style="min-height: auto; margin-bottom: 20px;">
                <h2>Step 1: Custom Tables Status</h2>
                <table class="widefat">
                    <thead><tr><th>Table</th><th>Status</th><th>Records</th></tr></thead>
                    <tbody>
                        <?php foreach ($table_status as $name => $exists): ?>
                            <tr>
                                <td><code><?php echo esc_html($name); ?></code></td>
                                <td><?php echo $exists ? '<span style="color:#00a32a;">✓ Exists</span>' : '<span style="color:#d63638;">✗ Missing</span>'; ?></td>
                                <td><?php echo isset($sql_counts[$name]) ? number_format($sql_counts[$name]) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" style="margin-top: 15px;">
                    <?php wp_nonce_field('cg_verify_tables'); ?>
                    <button type="submit" name="cg_verify_tables" class="button">Verify / Create Tables</button>
                </form>
            </div>

            <!-- Step 2: CPT → SQL Migration -->
            <div class="card" style="min-height: auto; margin-bottom: 20px;">
                <h2>Step 2: Migrate CPT Data to SQL Tables</h2>

                <?php if ($migration_completed): ?>
                    <div class="notice notice-success inline">
                        <p>✓ Migration completed: <strong><?php echo esc_html($migration_completed); ?></strong></p>
                    </div>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead><tr><th>Entity</th><th>CPT Records</th><th>SQL Records</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php
                            $mapping = [
                                'schools' => ['schools', 'schools'],
                                'students' => ['students', 'students'],
                                'teachers' => ['teachers', 'teachers'],
                                'templates' => ['certificates', 'certificate_templates'],
                            ];
                            foreach ($mapping as $label => [$cpt_key, $sql_key]):
                                $cpt = $cpt_counts[$cpt_key] ?? 0;
                                $sql = $sql_counts[$sql_key] ?? 0;
                                if ($cpt === 0 && $sql > 0 && $cpts_cleaned) {
                                    $status = '<span style="color:#00a32a;">✓ Migrated</span>';
                                } elseif ($cpt === $sql) {
                                    $status = '<span style="color:#00a32a;">✓ Match</span>';
                                } else {
                                    $status = '<span style="color:#dba617;">⚠ Mismatch</span>';
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst($label)); ?></td>
                                    <td><?php echo number_format($cpt); ?></td>
                                    <td><?php echo number_format($sql); ?></td>
                                    <td><?php echo $status; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="notice notice-warning inline">
                        <p>⚠ Migration has not been run yet.</p>
                    </div>
                <?php endif; ?>

                <p style="margin-top: 15px;">
                    This copies all data from WordPress Custom Post Types to custom SQL tables.
                    <strong>CPT data is NOT deleted.</strong> You can verify and clean up later.
                </p>

                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('cg_run_cpt_to_sql'); ?>
                    <button type="submit" name="cg_run_cpt_to_sql" class="button button-primary button-hero"
                            onclick="return confirm('This will copy all CPT data to SQL tables. CPT data will NOT be deleted. Continue?');">
                        Run CPT → SQL Migration
                    </button>
                </form>

                <?php if ($migration_completed && !$cpts_cleaned): ?>
                <form method="post" style="display:inline; margin-left: 10px;">
                    <?php wp_nonce_field('cg_rollback_migration'); ?>
                    <button type="submit" name="cg_rollback_migration" class="button"
                            style="border-color:#d63638; color:#d63638;"
                            onclick="return confirm('This will clear all SQL tables. CPT data is NOT deleted — rollback is safe. Continue?');">
                        ↩ Rollback Migration
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Step 2b: Fix Issue Dates (one-time normalization) -->
            <div class="card" style="min-height: auto; margin-bottom: 20px;">
                <h2>Step 2b: Normalize Issue Dates (One-Time Fix)</h2>
                <p>
                    Converts any <code>issue_date</code> values stored in <code>d-m-Y</code> (e.g. <code>23-03-2026</code>)
                    to the correct <code>Y-m-d</code> storage format (<code>2026-03-23</code>).
                    This fixes <em>"No matching template"</em> errors caused by date format mismatches during template selection.
                    <strong>Safe to run multiple times.</strong>
                </p>
                <form method="post">
                    <?php wp_nonce_field('cg_fix_issue_dates'); ?>
                    <button type="submit" name="cg_fix_issue_dates" class="button button-primary"
                            onclick="return confirm('This will update issue_date values across all student/teacher/school posts to Y-m-d format. Safe to run. Continue?');">
                        Fix Issue Dates (d-m-Y → Y-m-d)
                    </button>
                </form>
            </div>

            <!-- Step 3: Cleanup CPTs (Optional) -->
            <div class="card" style="min-height: auto; margin-bottom: 20px;">
                <h2>Step 3: Clean Up CPT Data (Optional)</h2>

                <?php if ($cpts_cleaned): ?>
                    <div class="notice notice-success inline">
                        <p>✓ CPT data has been cleaned up. Plugin uses SQL tables exclusively.</p>
                    </div>
                <?php elseif ($migration_completed): ?>
                    <div class="notice notice-info inline">
                        <p>After verifying all data migrated correctly, click below to remove CPT data.</p>
                    </div>
                    <form method="post" style="margin-top: 15px;">
                        <?php wp_nonce_field('cg_cleanup_cpts'); ?>
                        <button type="submit" name="cg_cleanup_cpts" class="button button-primary"
                                onclick="return confirm('WARNING: This will DELETE all CPT data. Make sure migration is complete. Continue?');">
                            Clean Up CPT Data (Irreversible)
                        </button>
                    </form>
                <?php else: ?>
                    <p class="description">Complete Step 2 first.</p>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top: 20px;">
                <h2>Migration Process</h2>
                <ol>
                    <li><strong>Tables Created:</strong> Custom SQL tables are created automatically.</li>
                    <li><strong>Data Copied:</strong> All CPT data is copied to SQL tables (CPT data preserved).</li>
                    <li><strong>Verify:</strong> Check record counts match between CPT and SQL.</li>
                    <li><strong>Clean Up (Optional):</strong> Remove CPT data once verified.</li>
                    <li><strong>Real-time Sync:</strong> New saves are mirrored to both CPT and SQL until cleanup.</li>
                </ol>
            </div>
        </div>
        <?php
    }

    private function rollback_migration(CustomTables $tables): void {
        global $wpdb;
        $truncate = ['students', 'teachers', 'schools', 'certificate_templates', 'certificates'];
        foreach ($truncate as $name) {
            $table = $tables->get_table($name);
            if ($table && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
                $wpdb->query("TRUNCATE TABLE $table"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
        }
        delete_option('cg_cpt_to_sql_migration_completed');
        delete_option('cg_cpt_to_sql_migration_stats');
        delete_option('cg_cpt_to_sql_migration_timestamp');
    }

    /**
     * Normalize all issue_date post meta values to Y-m-d storage format.
     * Fixes records imported via CSV that stored dates as d-m-Y.
     */
    private function fix_issue_dates(): int {
        global $wpdb;
        $fixed = 0;

        $rows = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = 'issue_date'
               AND pm.meta_value != ''
               AND p.post_type IN ('students','teachers','schools')"
        );

        foreach ($rows as $row) {
            // Already Y-m-d → skip
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $row->meta_value)) continue;

            $stored = null;
            foreach ([
                'd-m-Y', 'm/d/Y', 'Y/m/d', 'd/m/Y',
                'd-m-Y H:i:s', 'Y-m-d H:i:s',
            ] as $fmt) {
                $dt = \DateTime::createFromFormat($fmt, $row->meta_value);
                if ($dt && $dt->format($fmt) === $row->meta_value) {
                    $stored = $dt->format('Y-m-d');
                    break;
                }
            }
            if (!$stored) {
                $ts = strtotime($row->meta_value);
                if ($ts) $stored = date('Y-m-d', $ts);
            }

            if ($stored && $stored !== $row->meta_value) {
                update_post_meta((int) $row->post_id, 'issue_date', $stored);
                $fixed++;
            }
        }

        return $fixed;
    }

    private function cleanup_cpts(): void {
        global $wpdb;

        $post_types = ['students', 'teachers', 'schools', 'certificates'];
        foreach ($post_types as $pt) {
            $post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = %s",
                $pt
            ));
            foreach ($post_ids as $post_id) {
                wp_delete_post($post_id, true);
            }
        }

        update_option('cg_cpts_cleaned_up', true);
    }
}
