<?php
/**
 * Debug Dashboard for Certificate Generator
 * User-friendly admin interface for debugging installation issues
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class CertificateGenerator_DebugDashboard {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_debug_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_debug_assets'));
        add_action('wp_ajax_cert_gen_debug_action', array($this, 'handle_ajax_actions'));
        add_action('admin_init', array($this, 'handle_debug_actions'));
    }

    /**
     * Add debug menu to WordPress admin
     */
    public function add_debug_menu() {
        add_submenu_page(
            'tools.php',
            'Certificate Generator - Debug Dashboard',
            'Cert Gen Debug',
            'manage_options',
            'cert-gen-debug-dashboard',
            array($this, 'render_debug_dashboard')
        );

        add_submenu_page(
            'tools.php',
            'Certificate Generator - Installation Recovery',
            'Cert Gen Recovery',
            'manage_options',
            'cert-gen-recovery',
            array($this, 'render_recovery_page')
        );
    }

    /**
     * Enqueue debug dashboard assets
     */
    public function enqueue_debug_assets($hook) {
        if (strpos($hook, 'cert-gen-debug') === false && strpos($hook, 'cert-gen-recovery') === false) {
            return;
        }

        wp_enqueue_script('jquery');

        // Inline CSS for debug dashboard
        wp_add_inline_style('admin-menu', '
            .cert-gen-debug-dashboard { max-width: 1200px; }
            .cert-gen-status-card {
                background: white;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            }
            .cert-gen-status-success { border-left: 4px solid #46b450; }
            .cert-gen-status-warning { border-left: 4px solid #ffb900; }
            .cert-gen-status-error { border-left: 4px solid #dc3232; }
            .cert-gen-status-info { border-left: 4px solid #00a0d2; }
            .cert-gen-progress-bar {
                width: 100%;
                height: 20px;
                background-color: #f1f1f1;
                border-radius: 10px;
                overflow: hidden;
                margin: 10px 0;
            }
            .cert-gen-progress-fill {
                height: 100%;
                background-color: #0073aa;
                transition: width 0.3s ease;
            }
            .cert-gen-step-list {
                list-style: none;
                padding: 0;
            }
            .cert-gen-step-list li {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
                display: flex;
                align-items: center;
            }
            .cert-gen-step-status {
                margin-right: 10px;
                font-size: 16px;
            }
            .cert-gen-two-column {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .cert-gen-diagnostic-table {
                width: 100%;
                border-collapse: collapse;
            }
            .cert-gen-diagnostic-table th,
            .cert-gen-diagnostic-table td {
                text-align: left;
                padding: 8px;
                border-bottom: 1px solid #ddd;
            }
            .cert-gen-diagnostic-table th {
                background-color: #f1f1f1;
                font-weight: bold;
            }
            .cert-gen-action-buttons {
                margin: 20px 0;
            }
            .cert-gen-action-buttons .button {
                margin-right: 10px;
                margin-bottom: 5px;
            }
            .cert-gen-log-viewer {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                padding: 15px;
                max-height: 400px;
                overflow-y: auto;
                font-family: monospace;
                font-size: 12px;
                line-height: 1.4;
            }
            .cert-gen-log-entry {
                margin-bottom: 5px;
                word-wrap: break-word;
            }
            .cert-gen-log-error { color: #dc3545; }
            .cert-gen-log-warning { color: #fd7e14; }
            .cert-gen-log-info { color: #17a2b8; }
            .cert-gen-log-debug { color: #6c757d; }
        ');

        // Inline JavaScript for dashboard interactivity
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                // Auto-refresh functionality
                var autoRefresh = false;
                $("#cert-gen-auto-refresh").change(function() {
                    autoRefresh = $(this).is(":checked");
                    if (autoRefresh) {
                        startAutoRefresh();
                    }
                });

                function startAutoRefresh() {
                    if (!autoRefresh) return;
                    setTimeout(function() {
                        if (autoRefresh) {
                            location.reload();
                        }
                    }, 10000); // Refresh every 10 seconds
                }

                // AJAX actions
                $(".cert-gen-ajax-action").click(function(e) {
                    e.preventDefault();
                    var action = $(this).data("action");
                    var button = $(this);

                    button.prop("disabled", true).text("Processing...");

                    $.post(ajaxurl, {
                        action: "cert_gen_debug_action",
                        debug_action: action,
                        nonce: "' . wp_create_nonce('cert_gen_debug_nonce') . '"
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert("Error: " + response.data);
                            button.prop("disabled", false).text(button.data("original-text"));
                        }
                    });
                });

                // Store original button text
                $(".cert-gen-ajax-action").each(function() {
                    $(this).data("original-text", $(this).text());
                });

                // Expandable sections
                $(".cert-gen-expandable-header").click(function() {
                    $(this).next(".cert-gen-expandable-content").slideToggle();
                    $(this).find(".cert-gen-expand-icon").text(
                        $(this).find(".cert-gen-expand-icon").text() === "▼" ? "▶" : "▼"
                    );
                });
            });
        ');
    }

    /**
     * Render main debug dashboard
     */
    public function render_debug_dashboard() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Get current debug status
        $debug_active = get_option('cert_gen_debug_session_active', false);
        $current_step = get_option('cert_gen_debug_current_step', 0);
        $step_history = get_option('cert_gen_debug_step_history', array());
        $critical_errors = get_option('cert_gen_debug_critical_errors', array());

        // Get server diagnostic if available
        $server_diagnostic = get_option('cert_gen_server_diagnostic', null);

        // Get recent log entries
        $log_entries = get_option('cert_gen_debug_log_entries', array());
        $recent_logs = array_slice($log_entries, -50); // Last 50 entries

        ?>
        <div class="wrap cert-gen-debug-dashboard">
            <h1>🔍 Certificate Generator - Debug Dashboard</h1>

            <!-- Auto-refresh option -->
            <div style="margin-bottom: 20px;">
                <label>
                    <input type="checkbox" id="cert-gen-auto-refresh">
                    Auto-refresh every 10 seconds
                </label>
            </div>

            <!-- Debug Session Status -->
            <div class="cert-gen-status-card <?php echo $debug_active ? 'cert-gen-status-info' : 'cert-gen-status-warning'; ?>">
                <h2>🎯 Debug Session Status</h2>
                <?php if ($debug_active): ?>
                    <p><strong>Status:</strong> Active debug session running</p>
                    <p><strong>Current Step:</strong> <?php echo $current_step; ?> / 12</p>
                    <p><strong>Session Duration:</strong> <?php echo certificate_generator_get_session_duration(true); ?></p>

                    <!-- Progress bar -->
                    <div class="cert-gen-progress-bar">
                        <div class="cert-gen-progress-fill" style="width: <?php echo ($current_step / 12) * 100; ?>%"></div>
                    </div>
                    <p><?php echo round(($current_step / 12) * 100, 1); ?>% Complete</p>
                <?php else: ?>
                    <p><strong>Status:</strong> No active debug session</p>
                    <p>Start a new debug session to track installation issues.</p>
                <?php endif; ?>

                <div class="cert-gen-action-buttons">
                    <?php if (!$debug_active): ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=start_debug'), 'cert_gen_debug_nonce'); ?>"
                           class="button button-primary">🚀 Start Debug Session</a>
                    <?php else: ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=stop_debug'), 'cert_gen_debug_nonce'); ?>"
                           class="button">⏹️ Stop Debug Session</a>
                    <?php endif; ?>

                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=clear_debug'), 'cert_gen_debug_nonce'); ?>"
                       class="button">🗑️ Clear Debug Data</a>

                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=export_debug'), 'cert_gen_debug_nonce'); ?>"
                       class="button">📁 Export Debug Report</a>
                </div>
            </div>

            <div class="cert-gen-two-column">
                <!-- Installation Steps -->
                <div class="cert-gen-status-card">
                    <h3>📋 Installation Steps Progress</h3>
                    <?php if (!empty($step_history)): ?>
                        <ul class="cert-gen-step-list">
                            <?php
                            $steps = array(
                                1 => 'Server Compatibility Check',
                                2 => 'File System Permissions',
                                3 => 'Database Connection Test',
                                4 => 'Required Files Verification',
                                5 => 'Memory Allocation Test',
                                6 => 'Plugin Dependencies Check',
                                7 => 'WordPress Requirements',
                                8 => 'Database Table Creation',
                                9 => 'Initial Settings Setup',
                                10 => 'Font System Initialization',
                                11 => 'Admin Interface Setup',
                                12 => 'Final Verification'
                            );

                            foreach ($steps as $step_num => $step_name):
                                $step_completed = false;
                                $step_status = 'pending';
                                $step_details = '';

                                foreach ($step_history as $history_item) {
                                    if ($history_item['step'] == $step_num) {
                                        $step_completed = true;
                                        $step_status = $history_item['status'];
                                        $step_details = $history_item['details'];
                                        break;
                                    }
                                }

                                $status_icon = '⏳';
                                if ($step_status === 'passed' || $step_status === 'completed') {
                                    $status_icon = '✅';
                                } elseif ($step_status === 'failed' || $step_status === 'error') {
                                    $status_icon = '❌';
                                } elseif ($step_status === 'started' || $step_status === 'in_progress') {
                                    $status_icon = '🔄';
                                }
                            ?>
                                <li>
                                    <span class="cert-gen-step-status"><?php echo $status_icon; ?></span>
                                    <div>
                                        <strong><?php echo esc_html($step_name); ?></strong>
                                        <?php if ($step_details): ?>
                                            <br><small><?php echo esc_html($step_details); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No installation steps recorded yet. Start a debug session to track progress.</p>
                    <?php endif; ?>
                </div>

                <!-- Critical Errors -->
                <div class="cert-gen-status-card <?php echo !empty($critical_errors) ? 'cert-gen-status-error' : 'cert-gen-status-success'; ?>">
                    <h3>🚨 Critical Issues</h3>
                    <?php if (!empty($critical_errors)): ?>
                        <ul>
                            <?php foreach (array_slice($critical_errors, -10) as $error): ?>
                                <li>
                                    <strong><?php echo esc_html($error['timestamp']); ?>:</strong>
                                    <?php echo esc_html($error['message']); ?>
                                    <?php if (isset($error['step'])): ?>
                                        <small>(Step <?php echo $error['step']; ?>)</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($critical_errors) > 10): ?>
                            <p><em>Showing last 10 of <?php echo count($critical_errors); ?> total errors.</em></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>✅ No critical errors detected!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Server Diagnostic Results -->
            <?php if ($server_diagnostic): ?>
                <div class="cert-gen-status-card">
                    <div class="cert-gen-expandable-header" style="cursor: pointer; user-select: none;">
                        <h3>🖥️ Server Diagnostic Results <span class="cert-gen-expand-icon">▶</span></h3>
                    </div>
                    <div class="cert-gen-expandable-content" style="display: none;">
                        <?php $this->render_server_diagnostic_summary($server_diagnostic); ?>

                        <div class="cert-gen-action-buttons">
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=run_diagnostic'), 'cert_gen_debug_nonce'); ?>"
                               class="button">🔄 Refresh Diagnostic</a>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=export_diagnostic'), 'cert_gen_debug_nonce'); ?>"
                               class="button">📁 Export Diagnostic</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="cert-gen-status-card cert-gen-status-warning">
                    <h3>🖥️ Server Diagnostic</h3>
                    <p>No server diagnostic data available.</p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=run_diagnostic'), 'cert_gen_debug_nonce'); ?>"
                       class="button button-primary">🔍 Run Server Diagnostic</a>
                </div>
            <?php endif; ?>

            <!-- Debug Log Viewer -->
            <div class="cert-gen-status-card">
                <div class="cert-gen-expandable-header" style="cursor: pointer; user-select: none;">
                    <h3>📄 Debug Log Viewer <span class="cert-gen-expand-icon">▶</span></h3>
                </div>
                <div class="cert-gen-expandable-content" style="display: none;">
                    <?php if (!empty($recent_logs)): ?>
                        <div class="cert-gen-log-viewer">
                            <?php foreach ($recent_logs as $log_entry): ?>
                                <div class="cert-gen-log-entry cert-gen-log-<?php echo strtolower($log_entry['level']); ?>">
                                    [<?php echo esc_html($log_entry['timestamp']); ?>]
                                    [<?php echo esc_html($log_entry['level']); ?>]
                                    [STEP:<?php echo esc_html($log_entry['step']); ?>]
                                    [MEM:<?php echo esc_html($log_entry['memory']); ?>]
                                    <?php echo esc_html($log_entry['message']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p><em>Showing last 50 log entries. Total entries: <?php echo count($log_entries); ?></em></p>
                    <?php else: ?>
                        <p>No debug log entries found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="cert-gen-status-card">
                <h3>⚡ Quick Actions</h3>
                <div class="cert-gen-action-buttons">
                    <button class="button cert-gen-ajax-action" data-action="test_requirements">🔧 Test Installation Requirements</button>
                    <button class="button cert-gen-ajax-action" data-action="test_permissions">📁 Test File Permissions</button>
                    <button class="button cert-gen-ajax-action" data-action="test_database">🗄️ Test Database Connection</button>
                    <button class="button cert-gen-ajax-action" data-action="verify_files">📋 Verify Required Files</button>
                    <a href="<?php echo admin_url('admin.php?page=cert-gen-recovery'); ?>" class="button button-secondary">🔧 Recovery Tools</a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render recovery page
     */
    public function render_recovery_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        ?>
        <div class="wrap">
            <h1>🔧 Certificate Generator - Installation Recovery</h1>

            <div class="cert-gen-status-card cert-gen-status-warning">
                <h3>⚠️ Recovery Tools</h3>
                <p>Use these tools to recover from installation issues. <strong>Always backup your site before using recovery tools.</strong></p>
            </div>

            <div class="cert-gen-two-column">
                <div class="cert-gen-status-card">
                    <h3>🗑️ Cleanup Tools</h3>
                    <ul>
                        <li><strong>Clear Plugin Data:</strong> Remove all plugin options and temporary data</li>
                        <li><strong>Reset Database:</strong> Drop and recreate plugin database tables</li>
                        <li><strong>Clear Debug Logs:</strong> Remove all debug and error logs</li>
                        <li><strong>Reset Settings:</strong> Restore default plugin settings</li>
                    </ul>

                    <div class="cert-gen-action-buttons">
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-recovery&action=clear_plugin_data'), 'cert_gen_recovery_nonce'); ?>"
                           class="button" onclick="return confirm('Are you sure? This will remove all plugin data.')">🗑️ Clear Plugin Data</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-recovery&action=reset_database'), 'cert_gen_recovery_nonce'); ?>"
                           class="button" onclick="return confirm('Are you sure? This will reset the database.')">🗄️ Reset Database</a>
                    </div>
                </div>

                <div class="cert-gen-status-card">
                    <h3>🔄 Reinstallation Tools</h3>
                    <ul>
                        <li><strong>Safe Reactivation:</strong> Attempt plugin reactivation with safety checks</li>
                        <li><strong>Minimal Mode:</strong> Force activation in minimal mode</li>
                        <li><strong>File Verification:</strong> Check and repair missing files</li>
                        <li><strong>Permission Fix:</strong> Attempt to fix file permissions</li>
                    </ul>

                    <div class="cert-gen-action-buttons">
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-recovery&action=safe_reactivation'), 'cert_gen_recovery_nonce'); ?>"
                           class="button button-primary">🔄 Safe Reactivation</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-recovery&action=force_minimal'), 'cert_gen_recovery_nonce'); ?>"
                           class="button">⚡ Force Minimal Mode</a>
                    </div>
                </div>
            </div>

            <div class="cert-gen-status-card">
                <h3>📞 Support Information</h3>
                <p>If you continue to experience issues, please provide the following information when contacting support:</p>
                <ul>
                    <li>WordPress Version: <strong><?php echo get_bloginfo('version'); ?></strong></li>
                    <li>PHP Version: <strong><?php echo PHP_VERSION; ?></strong></li>
                    <li>Plugin Version: <strong>6.0.1</strong></li>
                    <li>Active Plugins: <strong><?php echo count(get_option('active_plugins', array())); ?></strong></li>
                    <li>Current Theme: <strong><?php echo wp_get_theme()->get('Name'); ?></strong></li>
                </ul>

                <div class="cert-gen-action-buttons">
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cert-gen-debug-dashboard&action=export_debug'), 'cert_gen_debug_nonce'); ?>"
                       class="button button-primary">📁 Download Support Package</a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle debug actions
     */
    public function handle_debug_actions() {
        if (!current_user_can('manage_options') || !isset($_GET['action'])) {
            return;
        }

        $action = $_GET['action'];
        $nonce_actions = array('start_debug', 'stop_debug', 'clear_debug', 'export_debug', 'run_diagnostic', 'export_diagnostic');
        $recovery_actions = array('clear_plugin_data', 'reset_database', 'safe_reactivation', 'force_minimal');

        if (in_array($action, $nonce_actions) && !wp_verify_nonce($_GET['_wpnonce'], 'cert_gen_debug_nonce')) {
            wp_die('Security check failed');
        }

        if (in_array($action, $recovery_actions) && !wp_verify_nonce($_GET['_wpnonce'], 'cert_gen_recovery_nonce')) {
            wp_die('Security check failed');
        }

        switch ($action) {
            case 'start_debug':
                certificate_generator_debug()->start_debug_session();
                wp_redirect(admin_url('admin.php?page=cert-gen-debug-dashboard&message=debug_started'));
                exit;

            case 'stop_debug':
                update_option('cert_gen_debug_session_active', false);
                wp_redirect(admin_url('admin.php?page=cert-gen-debug-dashboard&message=debug_stopped'));
                exit;

            case 'clear_debug':
                certificate_generator_debug()->clear_debug_data();
                wp_redirect(admin_url('admin.php?page=cert-gen-debug-dashboard&message=debug_cleared'));
                exit;

            case 'export_debug':
                certificate_generator_debug()->export_debug_report();
                break;

            case 'run_diagnostic':
                if (function_exists('certificate_generator_server_diagnostic')) {
                    certificate_generator_server_diagnostic()->run_full_diagnostic();
                }
                wp_redirect(admin_url('admin.php?page=cert-gen-debug-dashboard&message=diagnostic_completed'));
                exit;

            case 'export_diagnostic':
                if (function_exists('certificate_generator_server_diagnostic')) {
                    certificate_generator_server_diagnostic()->export_report();
                }
                break;
        }
    }

    /**
     * Handle AJAX actions
     */
    public function handle_ajax_actions() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'cert_gen_debug_nonce')) {
            wp_die('Security check failed');
        }

        $debug_action = $_POST['debug_action'];
        $debug = certificate_generator_debug();

        switch ($debug_action) {
            case 'test_requirements':
                $result = $debug->test_installation_requirements();
                wp_send_json_success(array('result' => $result));
                break;

            case 'test_permissions':
                $result = $debug->test_file_permissions();
                wp_send_json_success(array('result' => $result));
                break;

            case 'test_database':
                $result = $debug->test_database_connection();
                wp_send_json_success(array('result' => $result));
                break;

            case 'verify_files':
                $result = $debug->verify_required_files();
                wp_send_json_success(array('result' => $result));
                break;

            default:
                wp_send_json_error('Unknown action');
        }
    }

    /**
     * Render server diagnostic summary
     */
    private function render_server_diagnostic_summary($diagnostic) {
        $hosting = $diagnostic['hosting_provider'];
        $critical_issues = $diagnostic['critical_issues'];
        $warnings = $diagnostic['warnings'];
        $recommendations = $diagnostic['recommendations'];

        ?>
        <div style="margin-bottom: 20px;">
            <p><strong>Hosting Provider:</strong> <?php echo esc_html($hosting['name']); ?>
               <small>(<?php echo esc_html($hosting['confidence']); ?> confidence)</small></p>
            <p><strong>Scan Date:</strong> <?php echo esc_html($diagnostic['timestamp']); ?></p>
        </div>

        <?php if (!empty($critical_issues)): ?>
            <div style="background: #ffeaea; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <h4 style="color: #dc3232; margin-top: 0;">🚨 Critical Issues</h4>
                <ul>
                    <?php foreach ($critical_issues as $issue): ?>
                        <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($warnings)): ?>
            <div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <h4 style="color: #856404; margin-top: 0;">⚠️ Warnings</h4>
                <ul>
                    <?php foreach ($warnings as $warning): ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($recommendations)): ?>
            <div style="background: #d1ecf1; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <h4 style="color: #0c5460; margin-top: 0;">💡 Recommendations</h4>
                <ul>
                    <?php foreach ($recommendations as $rec): ?>
                        <li><strong><?php echo esc_html($rec['category']); ?>:</strong> <?php echo esc_html($rec['solution']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Quick stats table -->
        <table class="cert-gen-diagnostic-table">
            <tr>
                <th>PHP Version</th>
                <td><?php echo esc_html($diagnostic['php_configuration']['version']); ?></td>
            </tr>
            <tr>
                <th>Memory Limit</th>
                <td><?php echo esc_html($diagnostic['php_configuration']['memory_limit']); ?></td>
            </tr>
            <tr>
                <th>Execution Time</th>
                <td><?php echo esc_html($diagnostic['php_configuration']['max_execution_time']); ?>s</td>
            </tr>
            <tr>
                <th>File Uploads</th>
                <td><?php echo esc_html($diagnostic['php_configuration']['file_uploads']); ?></td>
            </tr>
            <tr>
                <th>WordPress Version</th>
                <td><?php echo esc_html($diagnostic['wordpress_environment']['version']); ?></td>
            </tr>
            <tr>
                <th>Database Version</th>
                <td><?php echo esc_html($diagnostic['database_analysis']['version']); ?></td>
            </tr>
        </table>
        <?php
    }


}

// Initialize debug dashboard
new CertificateGenerator_DebugDashboard();
?>