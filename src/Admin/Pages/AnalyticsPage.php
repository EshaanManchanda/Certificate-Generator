<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

use CertificateGenerator\Database\CustomTables;

/**
 * Analytics admin page — real-time stats from SQL tables.
 */
class AnalyticsPage extends Page {

    public function __construct() {
        parent::__construct('cg-analytics', 'Analytics');
    }

    public function render(): void {
        if (!$this->check_permission()) {
            wp_die(__('Insufficient permissions', 'certificate-generator'));
        }

        global $wpdb;
        $tables = CustomTables::instance();

        // ── Counts ───────────────────────────────────────────────────────────
        $student_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables->get_table('students')}");
        $teacher_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables->get_table('teachers')}");
        $school_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables->get_table('schools')}");
        $cert_table     = $tables->get_table('certificates');
        $cert_count     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $cert_table");
        $cert_last30    = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $cert_table WHERE created_at >= %s", gmdate('Y-m-d H:i:s', strtotime('-30 days')))
        );

        // ── Email stats ───────────────────────────────────────────────────────
        $log_table  = $tables->get_table('email_logs');
        $email_sent = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'sent'");
        $email_fail = (int) $wpdb->get_var("SELECT COUNT(*) FROM $log_table WHERE status = 'failed'");

        // ── Top certificate types ─────────────────────────────────────────────
        $top_types = $wpdb->get_results(
            "SELECT certificate_type, COUNT(*) AS cnt
             FROM $cert_table
             WHERE certificate_type != ''
             GROUP BY certificate_type
             ORDER BY cnt DESC
             LIMIT 8"
        );

        // ── Last 30 days — daily chart data ──────────────────────────────────
        $daily_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
                 FROM $cert_table
                 WHERE created_at >= %s
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC",
                gmdate('Y-m-d H:i:s', strtotime('-29 days'))
            )
        );
        $daily_map = [];
        foreach ($daily_raw as $row) $daily_map[$row->day] = (int) $row->cnt;
        $chart_labels = [];
        $chart_values = [];
        for ($d = 29; $d >= 0; $d--) {
            $day = gmdate('Y-m-d', strtotime("-{$d} days"));
            $chart_labels[] = gmdate('M j', strtotime($day));
            $chart_values[] = $daily_map[$day] ?? 0;
        }
        $chart_max = max(1, max($chart_values));

        // ── Recent certificates ───────────────────────────────────────────────
        $recent = $wpdb->get_results(
            "SELECT recipient_name, certificate_type, serial_number, status, created_at
             FROM $cert_table
             ORDER BY created_at DESC
             LIMIT 10"
        );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->title); ?></h1>

            <style>
            .cg-stat-cards{display:flex;gap:16px;flex-wrap:wrap;margin:20px 0}
            .cg-stat-card{background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px 24px;min-width:140px;flex:1;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.06)}
            .cg-stat-card .cg-stat-num{font-size:2.4em;font-weight:700;color:#0073aa;line-height:1.1}
            .cg-stat-card .cg-stat-lbl{color:#666;font-size:13px;margin-top:4px}
            .cg-chart-wrap{background:#fff;border:1px solid #ddd;border-radius:6px;padding:20px;margin-bottom:20px}
            .cg-chart-wrap h3{margin-top:0}
            .cg-bar-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:12px}
            .cg-bar-row .cg-bar-lbl{width:36px;text-align:right;color:#888;flex-shrink:0}
            .cg-bar-track{flex:1;background:#f0f0f1;border-radius:3px;height:18px;position:relative}
            .cg-bar-fill{background:#0073aa;height:100%;border-radius:3px;transition:width .3s}
            .cg-bar-val{position:absolute;right:6px;top:0;line-height:18px;color:#fff;font-weight:700;font-size:11px}
            .cg-bar-val-out{margin-left:4px;color:#333}
            .cg-two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}
            @media(max-width:900px){.cg-two-col{grid-template-columns:1fr}}
            .cg-recent-table{width:100%;border-collapse:collapse;font-size:13px}
            .cg-recent-table th{background:#f0f0f1;padding:8px 12px;text-align:left;border-bottom:2px solid #ddd}
            .cg-recent-table td{padding:7px 12px;border-bottom:1px solid #eee}
            .cg-status-pill{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;text-transform:uppercase}
            .cg-status-generated,.cg-status-sent{background:#d4edda;color:#155724}
            .cg-status-pending{background:#fff3cd;color:#856404}
            .cg-status-failed{background:#f8d7da;color:#721c24}
            </style>

            <!-- Stat cards -->
            <div class="cg-stat-cards">
                <div class="cg-stat-card">
                    <div class="cg-stat-num"><?php echo esc_html(number_format($student_count)); ?></div>
                    <div class="cg-stat-lbl">Students</div>
                </div>
                <div class="cg-stat-card">
                    <div class="cg-stat-num"><?php echo esc_html(number_format($teacher_count)); ?></div>
                    <div class="cg-stat-lbl">Teachers</div>
                </div>
                <div class="cg-stat-card">
                    <div class="cg-stat-num"><?php echo esc_html(number_format($school_count)); ?></div>
                    <div class="cg-stat-lbl">Schools</div>
                </div>
                <div class="cg-stat-card">
                    <div class="cg-stat-num"><?php echo esc_html(number_format($cert_count)); ?></div>
                    <div class="cg-stat-lbl">Certificates Total</div>
                </div>
                <div class="cg-stat-card">
                    <div class="cg-stat-num"><?php echo esc_html(number_format($cert_last30)); ?></div>
                    <div class="cg-stat-lbl">Generated (30d)</div>
                </div>
                <div class="cg-stat-card">
                    <div class="cg-stat-num" style="color:#27ae60"><?php echo esc_html(number_format($email_sent)); ?></div>
                    <div class="cg-stat-lbl">Emails Sent</div>
                </div>
                <div class="cg-stat-card">
                    <div class="cg-stat-num" style="color:#c0392b"><?php echo esc_html(number_format($email_fail)); ?></div>
                    <div class="cg-stat-lbl">Emails Failed</div>
                </div>
            </div>

            <!-- Two-column charts -->
            <div class="cg-two-col">

                <!-- Daily generation bar chart (last 30 days) -->
                <div class="cg-chart-wrap">
                    <h3>Certificates Generated — Last 30 Days</h3>
                    <?php if ($cert_last30 === 0): ?>
                        <p style="color:#888">No certificates generated in the last 30 days.</p>
                    <?php else: ?>
                        <?php
                        // Show every 3rd day label to avoid crowding
                        foreach ($chart_labels as $i => $lbl):
                            $val = $chart_values[$i];
                            $pct = $chart_max > 0 ? round(($val / $chart_max) * 100) : 0;
                            $show_lbl = ($i % 3 === 0 || $i === count($chart_labels) - 1);
                        ?>
                        <div class="cg-bar-row">
                            <span class="cg-bar-lbl"><?php echo $show_lbl ? esc_html($lbl) : ''; ?></span>
                            <div class="cg-bar-track">
                                <div class="cg-bar-fill" style="width:<?php echo esc_attr($pct); ?>%">
                                    <?php if ($val > 0): ?><span class="cg-bar-val"><?php echo esc_html($val); ?></span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Top certificate types -->
                <div class="cg-chart-wrap">
                    <h3>Top Certificate Types</h3>
                    <?php if (empty($top_types)): ?>
                        <p style="color:#888">No data yet.</p>
                    <?php else:
                        $type_max = (int) $top_types[0]->cnt;
                        foreach ($top_types as $t):
                            $pct = $type_max > 0 ? round(($t->cnt / $type_max) * 100) : 0;
                    ?>
                    <div class="cg-bar-row" style="margin-bottom:10px">
                        <div style="flex:1">
                            <div style="font-size:12px;margin-bottom:3px;color:#333"><?php echo esc_html($t->certificate_type); ?></div>
                            <div class="cg-bar-track">
                                <div class="cg-bar-fill" style="width:<?php echo esc_attr($pct); ?>%;background:#27ae60">
                                    <?php if ((int)$t->cnt > 0): ?><span class="cg-bar-val"><?php echo esc_html($t->cnt); ?></span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Recent certificates table -->
            <div class="cg-chart-wrap">
                <h3>Recent Certificates</h3>
                <?php if (empty($recent)): ?>
                    <p style="color:#888">No certificates generated yet.</p>
                <?php else: ?>
                <table class="cg-recent-table">
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Certificate Type</th>
                            <th>Serial</th>
                            <th>Status</th>
                            <th>Generated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?php echo esc_html($r->recipient_name ?: '—'); ?></td>
                            <td><?php echo esc_html($r->certificate_type ?: '—'); ?></td>
                            <td><code><?php echo esc_html($r->serial_number ?: '—'); ?></code></td>
                            <td>
                                <span class="cg-status-pill cg-status-<?php echo esc_attr($r->status ?: 'pending'); ?>">
                                    <?php echo esc_html($r->status ?: 'pending'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($r->created_at ? wp_date('M j, Y g:i a', strtotime($r->created_at)) : '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:10px"><a href="<?php echo esc_url(admin_url('admin.php?page=cg-students')); ?>">View all students →</a></p>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }
}
