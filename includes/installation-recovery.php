<?php
/**
 * Installation Recovery Tools for Certificate Generator
 * Provides recovery mechanisms for failed installations
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class CertificateGenerator_InstallationRecovery {

    private $debug;
    private $recovery_log;

    public function __construct() {
        $this->debug = certificate_generator_debug();
        $this->recovery_log = array();
        add_action('admin_init', array($this, 'handle_recovery_actions'));
    }

    /**
     * Handle recovery actions from admin
     */
    public function handle_recovery_actions() {
        if (!current_user_can('manage_options') || !isset($_GET['action'])) {
            return;
        }

        $action = $_GET['action'];
        $recovery_actions = array(
            'clear_plugin_data', 'reset_database', 'safe_reactivation',
            'force_minimal', 'fix_permissions', 'verify_and_repair'
        );

        if (!in_array($action, $recovery_actions)) {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'cert_gen_recovery_nonce')) {
            wp_die('Security check failed');
        }

        $this->log_recovery_action("Recovery action started: $action");

        switch ($action) {
            case 'clear_plugin_data':
                $result = $this->clear_plugin_data();
                $message = $result ? 'plugin_data_cleared' : 'plugin_data_failed';
                break;

            case 'reset_database':
                $result = $this->reset_database_tables();
                $message = $result ? 'database_reset' : 'database_failed';
                break;

            case 'safe_reactivation':
                $result = $this->safe_reactivation();
                $message = $result ? 'reactivation_success' : 'reactivation_failed';
                break;

            case 'force_minimal':
                $result = $this->force_minimal_mode();
                $message = $result ? 'minimal_forced' : 'minimal_failed';
                break;

            case 'fix_permissions':
                $result = $this->fix_file_permissions();
                $message = $result ? 'permissions_fixed' : 'permissions_failed';
                break;

            case 'verify_and_repair':
                $result = $this->verify_and_repair_installation();
                $message = $result ? 'repair_success' : 'repair_failed';
                break;

            default:
                $message = 'unknown_action';
        }

        wp_redirect(admin_url('admin.php?page=cert-gen-recovery&message=' . $message));
        exit;
    }

    /**
     * Clear all plugin data (nuclear option)
     */
    public function clear_plugin_data() {
        try {
            $this->log_recovery_action('Starting complete plugin data cleanup');

            // Delete all plugin options
            $plugin_options = array(
                'certificate_generator_version',
                'certificate_generator_settings',
                'certificate_generator_compatibility',
                'certificate_generator_activation_error',
                'certificate_generator_activated_at',
                'certificate_generator_missing_files',
                'cert_gen_debug_session_active',
                'cert_gen_debug_start_time',
                'cert_gen_debug_current_step',
                'cert_gen_debug_step_history',
                'cert_gen_debug_critical_errors',
                'cert_gen_debug_environment',
                'cert_gen_debug_log_entries',
                'cert_gen_server_diagnostic'
            );

            foreach ($plugin_options as $option) {
                delete_option($option);
                $this->log_recovery_action("Deleted option: $option");
            }

            // Clear transients
            delete_transient('certificate_generator_compatibility_check');
            delete_transient('certificate_generator_font_check');

            // Drop database table
            global $wpdb;
            $table_name = $wpdb->prefix . 'certificate_generator';
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
            $this->log_recovery_action("Dropped database table: $table_name");

            // Clear log files
            $log_file = WP_CONTENT_DIR . '/certificate-generator-debug.log';
            if (file_exists($log_file)) {
                unlink($log_file);
                $this->log_recovery_action('Deleted debug log file');
            }

            $this->log_recovery_action('Plugin data cleanup completed successfully');
            return true;

        } catch (Exception $e) {
            $this->log_recovery_action('Plugin data cleanup failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reset database tables
     */
    public function reset_database_tables() {
        try {
            global $wpdb;
            $this->log_recovery_action('Starting database table reset');

            // Drop existing table
            $table_name = $wpdb->prefix . 'certificate_generator';
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
            $this->log_recovery_action("Dropped existing table: $table_name");

            // Recreate table with fresh structure
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                student_name varchar(255) NOT NULL,
                certificate_data text NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);

            // Verify table creation
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                throw new Exception("Failed to create table $table_name");
            }

            $this->log_recovery_action('Database table reset completed successfully');
            return true;

        } catch (Exception $e) {
            $this->log_recovery_action('Database table reset failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Attempt safe reactivation with comprehensive checks
     */
    public function safe_reactivation() {
        try {
            $this->log_recovery_action('Starting safe reactivation process');

            // Step 1: Pre-activation checks
            if (!$this->run_pre_activation_checks()) {
                throw new Exception('Pre-activation checks failed');
            }

            // Step 2: Clear any existing error states
            delete_option('certificate_generator_activation_error');
            $this->log_recovery_action('Cleared existing error states');

            // Step 3: Test compatibility
            if (function_exists('certificate_generator_quick_compatibility_check')) {
                $compatibility = certificate_generator_quick_compatibility_check();
                if (!$compatibility['compatible']) {
                    throw new Exception('Server compatibility check failed: ' . implode(', ', $compatibility['errors']));
                }
                $this->log_recovery_action('Compatibility check passed');
            }

            // Step 4: Test database connectivity
            global $wpdb;
            $test_result = $wpdb->get_var("SELECT 1");
            if ($test_result !== '1') {
                throw new Exception('Database connectivity test failed');
            }
            $this->log_recovery_action('Database connectivity verified');

            // Step 5: Verify required files
            $missing_files = $this->check_required_files();
            if (!empty($missing_files)) {
                $this->log_recovery_action('Warning: Missing files detected: ' . implode(', ', $missing_files));
                // Continue anyway, but log the warning
            }

            // Step 6: Attempt activation
            $this->log_recovery_action('Attempting plugin activation');

            // Simulate activation process
            if (function_exists('certificate_generator_activate')) {
                // Set flag for safe activation
                update_option('certificate_generator_safe_activation', true);

                // Call activation function
                certificate_generator_activate();

                // Remove safe activation flag
                delete_option('certificate_generator_safe_activation');

                $this->log_recovery_action('Plugin activation completed successfully');
                return true;
            } else {
                throw new Exception('Activation function not available');
            }

        } catch (Exception $e) {
            $this->log_recovery_action('Safe reactivation failed: ' . $e->getMessage());
            update_option('certificate_generator_activation_error', $e->getMessage());
            return false;
        }
    }

    /**
     * Force minimal mode activation
     */
    public function force_minimal_mode() {
        try {
            $this->log_recovery_action('Forcing minimal mode activation');

            // Set minimal mode flags
            update_option('certificate_generator_force_minimal_mode', true);

            $minimal_settings = array(
                'installation_mode' => 'minimal',
                'max_memory_usage' => '64M',
                'enable_error_logging' => true,
                'font_loading_mode' => 'essential_only',
                'disable_heavy_features' => true,
                'enable_compatibility_mode' => true
            );

            update_option('certificate_generator_settings', $minimal_settings);
            $this->log_recovery_action('Minimal mode settings applied');

            // Attempt safe activation with minimal settings
            $result = $this->safe_reactivation();

            if ($result) {
                $this->log_recovery_action('Minimal mode activation successful');
                update_option('certificate_generator_activation_mode', 'minimal');
            }

            return $result;

        } catch (Exception $e) {
            $this->log_recovery_action('Minimal mode activation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Attempt to fix file permissions
     */
    public function fix_file_permissions() {
        try {
            $this->log_recovery_action('Starting file permission fixes');

            $directories_to_fix = array(
                CERTIFICATE_GENERATOR_PATH,
                WP_CONTENT_DIR . '/certificate-generator-logs',
                wp_upload_dir()['basedir'] . '/certificate-generator'
            );

            $fixes_applied = 0;
            $fixes_failed = 0;

            foreach ($directories_to_fix as $directory) {
                if (file_exists($directory)) {
                    if ($this->fix_directory_permissions($directory)) {
                        $fixes_applied++;
                        $this->log_recovery_action("Fixed permissions for: $directory");
                    } else {
                        $fixes_failed++;
                        $this->log_recovery_action("Failed to fix permissions for: $directory");
                    }
                } else {
                    // Try to create directory with correct permissions
                    if ($this->create_directory_with_permissions($directory)) {
                        $fixes_applied++;
                        $this->log_recovery_action("Created directory with correct permissions: $directory");
                    } else {
                        $fixes_failed++;
                        $this->log_recovery_action("Failed to create directory: $directory");
                    }
                }
            }

            $this->log_recovery_action("Permission fixes completed. Applied: $fixes_applied, Failed: $fixes_failed");
            return $fixes_applied > 0;

        } catch (Exception $e) {
            $this->log_recovery_action('File permission fixes failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify and repair installation
     */
    public function verify_and_repair_installation() {
        try {
            $this->log_recovery_action('Starting installation verification and repair');

            $issues_found = 0;
            $issues_fixed = 0;

            // Check 1: Database tables
            global $wpdb;
            $table_name = $wpdb->prefix . 'certificate_generator';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $this->log_recovery_action('Database table missing, attempting to recreate');
                if ($this->reset_database_tables()) {
                    $issues_fixed++;
                } else {
                    $issues_found++;
                }
            }

            // Check 2: Required options
            $required_options = array(
                'certificate_generator_version' => '6.0.1',
                'certificate_generator_settings' => array('installation_mode' => 'minimal')
            );

            foreach ($required_options as $option_name => $default_value) {
                if (!get_option($option_name)) {
                    update_option($option_name, $default_value);
                    $this->log_recovery_action("Restored missing option: $option_name");
                    $issues_fixed++;
                }
            }

            // Check 3: Critical files
            $missing_files = $this->check_required_files();
            if (!empty($missing_files)) {
                $this->log_recovery_action('Critical files missing: ' . implode(', ', $missing_files));
                $issues_found += count($missing_files);
                // Note: We can't auto-fix missing files, but we log them
            }

            // Check 4: File permissions
            if (!is_writable(CERTIFICATE_GENERATOR_PATH)) {
                $this->log_recovery_action('Plugin directory not writable, attempting fix');
                if ($this->fix_directory_permissions(CERTIFICATE_GENERATOR_PATH)) {
                    $issues_fixed++;
                } else {
                    $issues_found++;
                }
            }

            // Check 5: WordPress requirements
            if (version_compare(get_bloginfo('version'), '5.0', '<')) {
                $this->log_recovery_action('WordPress version too old (< 5.0)');
                $issues_found++;
            }

            $this->log_recovery_action("Verification completed. Issues found: $issues_found, Issues fixed: $issues_fixed");
            return $issues_fixed > 0 || $issues_found == 0;

        } catch (Exception $e) {
            $this->log_recovery_action('Installation verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create emergency backup before recovery
     */
    public function create_emergency_backup() {
        try {
            $this->log_recovery_action('Creating emergency backup');

            $backup_data = array(
                'timestamp' => current_time('mysql'),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => get_option('certificate_generator_version'),
                'settings' => get_option('certificate_generator_settings'),
                'compatibility' => get_option('certificate_generator_compatibility'),
                'debug_data' => array(
                    'session_active' => get_option('cert_gen_debug_session_active'),
                    'step_history' => get_option('cert_gen_debug_step_history'),
                    'critical_errors' => get_option('cert_gen_debug_critical_errors')
                )
            );

            // Store backup in database
            update_option('certificate_generator_emergency_backup', $backup_data);

            // Also create file backup
            $backup_file = WP_CONTENT_DIR . '/certificate-generator-backup-' . date('Y-m-d-H-i-s') . '.json';
            file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));

            $this->log_recovery_action('Emergency backup created successfully');
            return true;

        } catch (Exception $e) {
            $this->log_recovery_action('Emergency backup failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Restore from emergency backup
     */
    public function restore_from_backup() {
        try {
            $this->log_recovery_action('Attempting restore from emergency backup');

            $backup_data = get_option('certificate_generator_emergency_backup');
            if (!$backup_data) {
                throw new Exception('No emergency backup found');
            }

            // Restore settings
            if (isset($backup_data['settings'])) {
                update_option('certificate_generator_settings', $backup_data['settings']);
                $this->log_recovery_action('Settings restored from backup');
            }

            // Restore version
            if (isset($backup_data['plugin_version'])) {
                update_option('certificate_generator_version', $backup_data['plugin_version']);
                $this->log_recovery_action('Version restored from backup');
            }

            // Restore compatibility data
            if (isset($backup_data['compatibility'])) {
                update_option('certificate_generator_compatibility', $backup_data['compatibility']);
                $this->log_recovery_action('Compatibility data restored from backup');
            }

            $this->log_recovery_action('Restore from backup completed successfully');
            return true;

        } catch (Exception $e) {
            $this->log_recovery_action('Restore from backup failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Run pre-activation checks
     */
    private function run_pre_activation_checks() {
        $this->log_recovery_action('Running pre-activation checks');

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $this->log_recovery_action('Pre-check failed: PHP version too old');
            return false;
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $this->log_recovery_action('Pre-check failed: WordPress version too old');
            return false;
        }

        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        if ($memory_bytes > 0 && $memory_bytes < (64 * 1024 * 1024)) {
            $this->log_recovery_action('Pre-check warning: Memory limit very low');
            // Don't fail, but log warning
        }

        // Check file uploads
        if (!ini_get('file_uploads')) {
            $this->log_recovery_action('Pre-check warning: File uploads disabled');
            // Don't fail, but log warning
        }

        $this->log_recovery_action('Pre-activation checks completed');
        return true;
    }

    /**
     * Check for required files
     */
    private function check_required_files() {
        $required_files = array(
            'includes/server-compatibility-checker.php',
            'includes/admin-error-reporting.php',
            'includes/certificate-post-type.php',
            'includes/email-functions.php',
            'includes/installation-debug.php'
        );

        $missing_files = array();
        foreach ($required_files as $file) {
            $full_path = CERTIFICATE_GENERATOR_PATH . $file;
            if (!file_exists($full_path)) {
                $missing_files[] = $file;
            }
        }

        return $missing_files;
    }

    /**
     * Fix directory permissions
     */
    private function fix_directory_permissions($directory) {
        if (!file_exists($directory)) {
            return false;
        }

        // Try to set directory permissions to 755
        $result = chmod($directory, 0755);

        // If it's a directory, also try to fix files inside
        if ($result && is_dir($directory)) {
            $files = glob($directory . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    chmod($file, 0644);
                } elseif (is_dir($file)) {
                    chmod($file, 0755);
                }
            }
        }

        return $result;
    }

    /**
     * Create directory with correct permissions
     */
    private function create_directory_with_permissions($directory) {
        if (file_exists($directory)) {
            return true;
        }

        return wp_mkdir_p($directory);
    }

    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes($size_str) {
        if (empty($size_str) || $size_str === '-1') {
            return -1;
        }

        $size_str = trim($size_str);
        $last_char = strtolower($size_str[strlen($size_str) - 1]);
        $size = (int) $size_str;

        switch ($last_char) {
            case 'g': $size *= 1024;
            case 'm': $size *= 1024;
            case 'k': $size *= 1024;
        }

        return $size;
    }

    /**
     * Log recovery actions
     */
    private function log_recovery_action($message) {
        $this->recovery_log[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message
        );

        // Also log to debug system if available
        if ($this->debug) {
            $this->debug->log_debug($message, 'RECOVERY');
        }

        // Log to WordPress error log for critical actions
        error_log("Certificate Generator Recovery: $message");
    }

    /**
     * Get recovery log
     */
    public function get_recovery_log() {
        return $this->recovery_log;
    }

    /**
     * Export recovery report
     */
    public function export_recovery_report() {
        $report = array(
            'generated_at' => current_time('mysql'),
            'recovery_log' => $this->recovery_log,
            'current_status' => $this->get_current_status(),
            'system_info' => $this->get_system_info()
        );

        $filename = 'certificate-generator-recovery-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($report, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Get current plugin status
     */
    private function get_current_status() {
        return array(
            'plugin_version' => get_option('certificate_generator_version'),
            'settings_exist' => (bool) get_option('certificate_generator_settings'),
            'database_table_exists' => $this->check_database_table_exists(),
            'critical_files_exist' => empty($this->check_required_files()),
            'directory_writable' => is_writable(CERTIFICATE_GENERATOR_PATH),
            'last_activation_error' => get_option('certificate_generator_activation_error')
        );
    }

    /**
     * Get system information
     */
    private function get_system_info() {
        return array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'current_theme' => wp_get_theme()->get('Name'),
            'active_plugins_count' => count(get_option('active_plugins', array()))
        );
    }

    /**
     * Check if database table exists
     */
    private function check_database_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'certificate_generator';
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    }
}

/**
 * Global recovery instance
 */
function certificate_generator_recovery() {
    static $instance = null;
    if ($instance === null) {
        $instance = new CertificateGenerator_InstallationRecovery();
    }
    return $instance;
}
?>