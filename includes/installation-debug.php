<?php
/**
 * Installation Debug Controller for Certificate Generator
 * Provides comprehensive debugging and logging for installation issues
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class CertificateGenerator_InstallationDebug {

    private $debug_log_file;
    private $installation_steps;
    private $current_step;
    private $debug_data;

    public function __construct() {
        $this->debug_log_file = WP_CONTENT_DIR . '/certificate-generator-debug.log';
        $this->current_step = 0;
        $this->debug_data = array();
        $this->init_installation_steps();
    }

    /**
     * Initialize installation steps for tracking
     */
    private function init_installation_steps() {
        $this->installation_steps = array(
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
    }

    /**
     * Start installation debugging session
     */
    public function start_debug_session() {
        $this->log_debug('=== Certificate Generator Installation Debug Session Started ===');
        $this->log_debug('Timestamp: ' . current_time('mysql'));
        $this->log_debug('WordPress Version: ' . get_bloginfo('version'));
        $this->log_debug('PHP Version: ' . PHP_VERSION);
        $this->log_debug('Plugin Version: 6.0.1');

        // Collect initial environment data
        $this->collect_environment_data();

        update_option('cert_gen_debug_session_active', true);
        update_option('cert_gen_debug_start_time', time());
        update_option('cert_gen_debug_current_step', 0);
    }

    /**
     * Log installation step progress
     */
    public function log_step($step_number, $status = 'started', $details = '') {
        $this->current_step = $step_number;
        update_option('cert_gen_debug_current_step', $step_number);

        $step_name = isset($this->installation_steps[$step_number]) ?
                    $this->installation_steps[$step_number] :
                    "Unknown Step $step_number";

        $message = "STEP $step_number ($step_name): " . strtoupper($status);
        if ($details) {
            $message .= " - $details";
        }

        $this->log_debug($message);

        // Update step history
        $step_history = get_option('cert_gen_debug_step_history', array());
        $step_history[] = array(
            'step' => $step_number,
            'name' => $step_name,
            'status' => $status,
            'details' => $details,
            'timestamp' => current_time('mysql'),
            'memory_usage' => $this->get_memory_usage()
        );
        update_option('cert_gen_debug_step_history', $step_history);
    }

    /**
     * Log debug information with detailed context
     */
    public function log_debug($message, $level = 'INFO', $context = array()) {
        $timestamp = current_time('mysql');
        $memory_usage = $this->get_memory_usage();
        $step = $this->current_step;

        $log_entry = "[$timestamp] [$level] [STEP:$step] [MEM:$memory_usage] $message";

        if (!empty($context)) {
            $log_entry .= " | Context: " . json_encode($context);
        }

        // Write to debug log file
        $this->write_to_log_file($log_entry);

        // Also log to WordPress error log for critical issues
        if (in_array($level, array('ERROR', 'CRITICAL'))) {
            error_log("Certificate Generator Debug: $message");
        }

        // Store in database for easy retrieval
        $this->store_debug_entry($message, $level, $context);
    }

    /**
     * Log error with stack trace
     */
    public function log_error($message, $exception = null) {
        $error_data = array(
            'message' => $message,
            'file' => '',
            'line' => '',
            'trace' => ''
        );

        if ($exception instanceof Exception) {
            $error_data['file'] = $exception->getFile();
            $error_data['line'] = $exception->getLine();
            $error_data['trace'] = $exception->getTraceAsString();
        } else {
            // Get debug backtrace
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            if (isset($trace[1])) {
                $error_data['file'] = isset($trace[1]['file']) ? $trace[1]['file'] : '';
                $error_data['line'] = isset($trace[1]['line']) ? $trace[1]['line'] : '';
            }
        }

        $this->log_debug($message, 'ERROR', $error_data);

        // Store critical error for immediate attention
        $critical_errors = get_option('cert_gen_debug_critical_errors', array());
        $critical_errors[] = array(
            'message' => $message,
            'data' => $error_data,
            'timestamp' => current_time('mysql'),
            'step' => $this->current_step
        );
        update_option('cert_gen_debug_critical_errors', $critical_errors);
    }

    /**
     * Collect comprehensive environment data
     */
    private function collect_environment_data() {
        $this->debug_data['environment'] = array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => '6.0.1',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'wp_content_dir' => WP_CONTENT_DIR,
            'wp_plugin_dir' => WP_PLUGIN_DIR,
            'abspath' => ABSPATH,
            'current_user' => wp_get_current_user()->user_login ?? 'Unknown',
            'is_multisite' => is_multisite(),
            'active_plugins' => get_option('active_plugins', array()),
            'current_theme' => get_template(),
            'disk_free_space' => disk_free_space(WP_CONTENT_DIR),
            'timezone' => get_option('timezone_string'),
            'permalink_structure' => get_option('permalink_structure')
        );

        // Store environment data
        update_option('cert_gen_debug_environment', $this->debug_data['environment']);

        $this->log_debug('Environment data collected', 'INFO', $this->debug_data['environment']);
    }

    /**
     * Test critical installation requirements
     */
    public function test_installation_requirements() {
        $this->log_step(1, 'started');
        $requirements_passed = true;
        $issues = array();

        // Test 1: PHP Version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $issues[] = "PHP version " . PHP_VERSION . " is too old (minimum: 7.4)";
            $requirements_passed = false;
        }

        // Test 2: Memory Limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        if ($memory_bytes > 0 && $memory_bytes < (128 * 1024 * 1024)) {
            $issues[] = "Memory limit $memory_limit is too low (minimum: 128M)";
            $requirements_passed = false;
        }

        // Test 3: File Uploads
        if (!ini_get('file_uploads')) {
            $issues[] = "File uploads are disabled";
            $requirements_passed = false;
        }

        // Test 4: WordPress Version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $issues[] = "WordPress version " . get_bloginfo('version') . " is too old (minimum: 5.0)";
            $requirements_passed = false;
        }

        if ($requirements_passed) {
            $this->log_step(1, 'passed', 'All basic requirements met');
        } else {
            $this->log_step(1, 'failed', implode(', ', $issues));
            $this->log_error('Installation requirements not met: ' . implode(', ', $issues));
        }

        return $requirements_passed;
    }

    /**
     * Test file system permissions
     */
    public function test_file_permissions() {
        $this->log_step(2, 'started');
        $permissions_ok = true;
        $issues = array();

        // Test plugin directory write permissions
        $plugin_dir = CERTIFICATE_GENERATOR_PATH;
        if (!is_writable($plugin_dir)) {
            $issues[] = "Plugin directory not writable: $plugin_dir";
            $permissions_ok = false;
        }

        // Test wp-content directory permissions
        if (!is_writable(WP_CONTENT_DIR)) {
            $issues[] = "WP Content directory not writable: " . WP_CONTENT_DIR;
            $permissions_ok = false;
        }

        // Test debug log creation
        $test_file = $plugin_dir . 'test-write-permissions.tmp';
        if (!file_put_contents($test_file, 'test')) {
            $issues[] = "Cannot create files in plugin directory";
            $permissions_ok = false;
        } else {
            unlink($test_file);
        }

        if ($permissions_ok) {
            $this->log_step(2, 'passed', 'File system permissions are adequate');
        } else {
            $this->log_step(2, 'failed', implode(', ', $issues));
            $this->log_error('File permission issues: ' . implode(', ', $issues));
        }

        return $permissions_ok;
    }

    /**
     * Test database connectivity and permissions
     */
    public function test_database_connection() {
        $this->log_step(3, 'started');
        global $wpdb;

        try {
            // Test basic connectivity
            $result = $wpdb->get_var("SELECT 1");
            if ($result !== '1') {
                throw new Exception("Database connectivity test failed");
            }

            // Test table creation permissions
            $test_table = $wpdb->prefix . 'cert_gen_test_table';
            $sql = "CREATE TABLE $test_table (id INT AUTO_INCREMENT PRIMARY KEY, test_col VARCHAR(50))";

            $create_result = $wpdb->query($sql);
            if ($create_result === false) {
                throw new Exception("Cannot create database tables - check privileges");
            }

            // Clean up test table
            $wpdb->query("DROP TABLE $test_table");

            $this->log_step(3, 'passed', 'Database connection and permissions verified');
            return true;

        } catch (Exception $e) {
            $this->log_step(3, 'failed', $e->getMessage());
            $this->log_error('Database connection test failed: ' . $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Verify required plugin files exist
     */
    public function verify_required_files() {
        $this->log_step(4, 'started');

        $required_files = array(
            'includes/server-compatibility-checker.php',
            'includes/admin-error-reporting.php',
            'includes/certificate-post-type.php',
            'includes/email-functions.php'
        );

        $missing_files = array();
        foreach ($required_files as $file) {
            $full_path = CERTIFICATE_GENERATOR_PATH . $file;
            if (!file_exists($full_path)) {
                $missing_files[] = $file;
            }
        }

        if (empty($missing_files)) {
            $this->log_step(4, 'passed', 'All required files found');
            return true;
        } else {
            $this->log_step(4, 'failed', 'Missing files: ' . implode(', ', $missing_files));
            $this->log_error('Missing required files: ' . implode(', ', $missing_files));
            return false;
        }
    }

    /**
     * Generate comprehensive debug report
     */
    public function generate_debug_report() {
        $report = array(
            'generated_at' => current_time('mysql'),
            'session_duration' => $this->get_session_duration(),
            'environment' => get_option('cert_gen_debug_environment', array()),
            'step_history' => get_option('cert_gen_debug_step_history', array()),
            'critical_errors' => get_option('cert_gen_debug_critical_errors', array()),
            'current_step' => get_option('cert_gen_debug_current_step', 0),
            'memory_peaks' => $this->get_memory_peaks(),
            'log_entries' => $this->get_recent_log_entries(100)
        );

        return $report;
    }

    /**
     * Export debug report as downloadable file
     */
    public function export_debug_report() {
        $report = $this->generate_debug_report();
        $filename = 'certificate-generator-debug-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen(json_encode($report, JSON_PRETTY_PRINT)));

        echo json_encode($report, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Clear debug data and start fresh
     */
    public function clear_debug_data() {
        delete_option('cert_gen_debug_session_active');
        delete_option('cert_gen_debug_start_time');
        delete_option('cert_gen_debug_current_step');
        delete_option('cert_gen_debug_step_history');
        delete_option('cert_gen_debug_critical_errors');
        delete_option('cert_gen_debug_environment');
        delete_option('cert_gen_debug_log_entries');

        // Clear log file
        if (file_exists($this->debug_log_file)) {
            file_put_contents($this->debug_log_file, '');
        }

        $this->log_debug('Debug data cleared and session reset');
    }

    /**
     * Helper: Get current memory usage formatted
     */
    private function get_memory_usage() {
        if (function_exists('memory_get_usage')) {
            return $this->format_bytes(memory_get_usage(true));
        }
        return 'Unknown';
    }

    /**
     * Helper: Convert memory string to bytes
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
     * Helper: Format bytes for human reading
     */
    private function format_bytes($bytes) {
        if ($bytes >= 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024 * 1024), 2) . 'GB';
        } elseif ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . 'KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Helper: Write to log file
     */
    private function write_to_log_file($entry) {
        $entry .= "\n";
        file_put_contents($this->debug_log_file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Helper: Store debug entry in database
     */
    private function store_debug_entry($message, $level, $context) {
        $entries = get_option('cert_gen_debug_log_entries', array());

        $entries[] = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'step' => $this->current_step,
            'memory' => $this->get_memory_usage()
        );

        // Keep only last 500 entries to prevent database bloat
        if (count($entries) > 500) {
            $entries = array_slice($entries, -500);
        }

        update_option('cert_gen_debug_log_entries', $entries);
    }

    /**
     * Helper: Get session duration
     */
    private function get_session_duration() {
        $start_time = get_option('cert_gen_debug_start_time');
        if ($start_time) {
            return time() - $start_time;
        }
        return 0;
    }

    /**
     * Helper: Get memory usage peaks
     */
    private function get_memory_peaks() {
        $step_history = get_option('cert_gen_debug_step_history', array());
        $peaks = array();

        foreach ($step_history as $step) {
            if (isset($step['memory_usage'])) {
                $peaks[] = array(
                    'step' => $step['step'],
                    'memory' => $step['memory_usage']
                );
            }
        }

        return $peaks;
    }

    /**
     * Helper: Get recent log entries
     */
    private function get_recent_log_entries($limit = 100) {
        $entries = get_option('cert_gen_debug_log_entries', array());
        return array_slice($entries, -$limit);
    }
}

/**
 * Global debug instance
 */
function certificate_generator_debug() {
    static $instance = null;
    if ($instance === null) {
        $instance = new CertificateGenerator_InstallationDebug();
    }
    return $instance;
}

/**
 * Convenience functions for debugging
 */
function cert_gen_debug_log($message, $level = 'INFO', $context = array()) {
    certificate_generator_debug()->log_debug($message, $level, $context);
}

function cert_gen_debug_error($message, $exception = null) {
    certificate_generator_debug()->log_error($message, $exception);
}

function cert_gen_debug_step($step, $status = 'started', $details = '') {
    certificate_generator_debug()->log_step($step, $status, $details);
}
?>