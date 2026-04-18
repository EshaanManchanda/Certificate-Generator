<?php
/**
 * Enhanced Server Diagnostic for Certificate Generator
 * Comprehensive server environment analysis and troubleshooting
 */

if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

class CertificateGenerator_ServerDiagnostic {

    private $diagnostic_results;
    private $hosting_providers;
    private $debug;

    public function __construct() {
        $this->diagnostic_results = array();
        $this->debug = certificate_generator_debug();
        $this->init_hosting_providers();
    }

    /**
     * Initialize known hosting provider configurations
     */
    private function init_hosting_providers() {
        $this->hosting_providers = array(
            'hostinger' => array(
                'name' => 'Hostinger',
                'identifiers' => array('hostinger', 'litespeed'),
                'common_issues' => array(
                    'memory_limit' => 'Default 128MB limit, can be increased via ini_set',
                    'execution_time' => 'LiteSpeed may have different limits than Apache',
                    'file_uploads' => 'WordPress upload limit may be lower than server limit'
                ),
                'solutions' => array(
                    'memory' => 'Add ini_set("memory_limit", "256M"); to wp-config.php',
                    'execution' => 'Add ini_set("max_execution_time", 300); to wp-config.php',
                    'uploads' => 'Use FTP to upload plugin directly to wp-content/plugins/'
                )
            ),
            'siteground' => array(
                'name' => 'SiteGround',
                'identifiers' => array('siteground', 'sg-cachepress'),
                'common_issues' => array(
                    'caching' => 'Aggressive caching may interfere with plugin activation',
                    'memory_limit' => 'Strict memory limits on shared hosting',
                    'plugin_limits' => 'Some plugin features may be restricted'
                ),
                'solutions' => array(
                    'memory' => 'Contact support to increase memory limit',
                    'caching' => 'Temporarily disable SG Optimizer during installation',
                    'activation' => 'Use SiteGround staging environment for testing'
                )
            ),
            'bluehost' => array(
                'name' => 'Bluehost',
                'identifiers' => array('bluehost', 'cpanel'),
                'common_issues' => array(
                    'memory_limit' => 'Default 64MB on shared hosting',
                    'execution_time' => 'Strict 30-second limit',
                    'file_permissions' => 'May require manual permission adjustments'
                ),
                'solutions' => array(
                    'memory' => 'Upgrade to higher hosting plan or use .htaccess',
                    'execution' => 'Use minimal installation mode',
                    'permissions' => 'Set plugin folder permissions to 755'
                )
            ),
            'godaddy' => array(
                'name' => 'GoDaddy',
                'identifiers' => array('godaddy', 'secureserver'),
                'common_issues' => array(
                    'memory_limit' => 'Very restrictive memory limits',
                    'security' => 'Security restrictions may block some plugin features',
                    'database' => 'Database connection limits'
                ),
                'solutions' => array(
                    'memory' => 'Contact GoDaddy support for memory increase',
                    'security' => 'Whitelist plugin in security settings',
                    'database' => 'Use minimal installation with reduced database operations'
                )
            ),
            'wpengine' => array(
                'name' => 'WP Engine',
                'identifiers' => array('wpengine', 'wpe-'),
                'common_issues' => array(
                    'restrictions' => 'Managed hosting restrictions on certain functions',
                    'caching' => 'Advanced caching may require cache clearing',
                    'staging' => 'Production/staging environment differences'
                ),
                'solutions' => array(
                    'restrictions' => 'Some features may be disabled - contact WP Engine',
                    'caching' => 'Clear all caches after installation',
                    'staging' => 'Test on staging environment first'
                )
            )
        );
    }

    /**
     * Run comprehensive server diagnostic
     */
    public function run_full_diagnostic() {
        $this->debug->log_debug('Starting comprehensive server diagnostic');

        $this->diagnostic_results = array(
            'timestamp' => current_time('mysql'),
            'hosting_provider' => $this->detect_hosting_provider(),
            'server_environment' => $this->analyze_server_environment(),
            'php_configuration' => $this->analyze_php_configuration(),
            'wordpress_environment' => $this->analyze_wordpress_environment(),
            'database_analysis' => $this->analyze_database(),
            'file_system_analysis' => $this->analyze_file_system(),
            'network_connectivity' => $this->test_network_connectivity(),
            'plugin_conflicts' => $this->detect_plugin_conflicts(),
            'resource_usage' => $this->analyze_resource_usage(),
            'security_restrictions' => $this->check_security_restrictions(),
            'recommendations' => array(),
            'critical_issues' => array(),
            'warnings' => array()
        );

        // Generate recommendations based on findings
        $this->generate_recommendations();

        // Store results
        update_option('cert_gen_server_diagnostic', $this->diagnostic_results);

        $this->debug->log_debug('Server diagnostic completed', 'INFO', array(
            'hosting_provider' => $this->diagnostic_results['hosting_provider']['name'],
            'critical_issues' => count($this->diagnostic_results['critical_issues']),
            'warnings' => count($this->diagnostic_results['warnings'])
        ));

        return $this->diagnostic_results;
    }

    /**
     * Detect hosting provider
     */
    private function detect_hosting_provider() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';

        $detection_sources = strtolower($host . ' ' . $server_software . ' ' . $user_agent . ' ' . $document_root);

        foreach ($this->hosting_providers as $provider_key => $provider_data) {
            foreach ($provider_data['identifiers'] as $identifier) {
                if (strpos($detection_sources, $identifier) !== false) {
                    return array(
                        'key' => $provider_key,
                        'name' => $provider_data['name'],
                        'detected_via' => $identifier,
                        'confidence' => 'high'
                    );
                }
            }
        }

        // Generic detection
        if (strpos($detection_sources, 'cpanel') !== false) {
            return array(
                'key' => 'shared_hosting',
                'name' => 'Shared Hosting (cPanel)',
                'detected_via' => 'cpanel',
                'confidence' => 'medium'
            );
        }

        if (strpos($detection_sources, 'localhost') !== false || strpos($detection_sources, '.local') !== false) {
            return array(
                'key' => 'local',
                'name' => 'Local Development',
                'detected_via' => 'localhost',
                'confidence' => 'high'
            );
        }

        return array(
            'key' => 'unknown',
            'name' => 'Unknown Provider',
            'detected_via' => 'fallback',
            'confidence' => 'low'
        );
    }

    /**
     * Analyze server environment
     */
    private function analyze_server_environment() {
        return array(
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
            'https' => is_ssl(),
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'Unknown',
            'gateway_interface' => $_SERVER['GATEWAY_INTERFACE'] ?? 'Unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        );
    }

    /**
     * Analyze PHP configuration
     */
    private function analyze_php_configuration() {
        $config = array(
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
            'max_file_uploads' => ini_get('max_file_uploads'),
            'display_errors' => ini_get('display_errors') ? 'On' : 'Off',
            'log_errors' => ini_get('log_errors') ? 'On' : 'Off',
            'error_log' => ini_get('error_log'),
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled',
            'allow_url_include' => ini_get('allow_url_include') ? 'Enabled' : 'Disabled',
            'auto_prepend_file' => ini_get('auto_prepend_file'),
            'auto_append_file' => ini_get('auto_append_file'),
            'default_socket_timeout' => ini_get('default_socket_timeout'),
            'user_agent' => ini_get('user_agent'),
            'disable_functions' => ini_get('disable_functions'),
            'disable_classes' => ini_get('disable_classes')
        );

        // Add loaded extensions
        $config['loaded_extensions'] = get_loaded_extensions();

        // Check for important extensions
        $important_extensions = array('gd', 'curl', 'zip', 'json', 'mbstring', 'openssl');
        $config['important_extensions'] = array();
        foreach ($important_extensions as $ext) {
            $config['important_extensions'][$ext] = extension_loaded($ext);
        }

        return $config;
    }

    /**
     * Analyze WordPress environment
     */
    private function analyze_wordpress_environment() {
        global $wp_version;

        return array(
            'version' => $wp_version,
            'multisite' => is_multisite(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            'script_debug' => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            'cache' => defined('WP_CACHE') && WP_CACHE,
            'cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'file_edits' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
            'file_mods' => defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS,
            'memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'Not set',
            'max_memory_limit' => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : 'Not set',
            'home_url' => home_url(),
            'site_url' => site_url(),
            'admin_url' => admin_url(),
            'content_dir' => WP_CONTENT_DIR,
            'content_url' => WP_CONTENT_URL,
            'plugin_dir' => WP_PLUGIN_DIR,
            'plugin_url' => WP_PLUGIN_URL,
            'uploads_dir' => wp_upload_dir(),
            'theme' => wp_get_theme()->get('Name'),
            'parent_theme' => wp_get_theme()->parent() ? wp_get_theme()->parent()->get('Name') : 'None',
            'language' => get_locale(),
            'timezone' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'start_of_week' => get_option('start_of_week'),
            'permalink_structure' => get_option('permalink_structure')
        );
    }

    /**
     * Analyze database configuration
     */
    private function analyze_database() {
        global $wpdb;

        $analysis = array(
            'version' => $wpdb->get_var("SELECT VERSION()"),
            'connection_charset' => $wpdb->charset,
            'connection_collate' => $wpdb->collate,
            'table_prefix' => $wpdb->prefix,
            'database_name' => DB_NAME,
            'database_host' => DB_HOST,
            'database_charset' => DB_CHARSET,
            'database_collate' => DB_COLLATE
        );

        // Test database performance
        $start_time = microtime(true);
        $wpdb->get_var("SELECT 1");
        $analysis['query_time'] = round((microtime(true) - $start_time) * 1000, 2) . 'ms';

        // Check database size
        $db_size = $wpdb->get_var("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'Database Size (MB)' FROM information_schema.tables WHERE table_schema='" . DB_NAME . "'");
        $analysis['database_size'] = $db_size ? $db_size . 'MB' : 'Unknown';

        // Check table engine
        $tables = $wpdb->get_results("SHOW TABLE STATUS FROM `" . DB_NAME . "`");
        $analysis['table_engines'] = array();
        foreach ($tables as $table) {
            $analysis['table_engines'][$table->Name] = $table->Engine;
        }

        return $analysis;
    }

    /**
     * Analyze file system
     */
    private function analyze_file_system() {
        $analysis = array(
            'wp_content_writable' => is_writable(WP_CONTENT_DIR),
            'wp_content_permissions' => $this->get_file_permissions(WP_CONTENT_DIR),
            'plugin_dir_writable' => is_writable(WP_PLUGIN_DIR),
            'plugin_dir_permissions' => $this->get_file_permissions(WP_PLUGIN_DIR),
            'uploads_writable' => false,
            'uploads_permissions' => '',
            'disk_free_space' => 'Unknown',
            'disk_total_space' => 'Unknown'
        );

        // Check uploads directory
        $uploads = wp_upload_dir();
        if (isset($uploads['basedir'])) {
            $analysis['uploads_writable'] = is_writable($uploads['basedir']);
            $analysis['uploads_permissions'] = $this->get_file_permissions($uploads['basedir']);
        }

        // Check disk space
        if (function_exists('disk_free_space')) {
            $free_space = disk_free_space(ABSPATH);
            if ($free_space !== false) {
                $analysis['disk_free_space'] = certificate_generator_format_bytes($free_space);
            }
        }

        if (function_exists('disk_total_space')) {
            $total_space = disk_total_space(ABSPATH);
            if ($total_space !== false) {
                $analysis['disk_total_space'] = certificate_generator_format_bytes($total_space);
            }
        }

        return $analysis;
    }

    /**
     * Test network connectivity
     */
    private function test_network_connectivity() {
        $tests = array(
            'wordpress_org' => $this->test_url_connectivity('https://wordpress.org'),
            'google_fonts' => $this->test_url_connectivity('https://fonts.googleapis.com'),
            'plugin_api' => $this->test_url_connectivity('https://api.wordpress.org/plugins/info/1.0/'),
            'outbound_http' => $this->test_url_connectivity('http://httpbin.org/get'),
            'outbound_https' => $this->test_url_connectivity('https://httpbin.org/get')
        );

        return $tests;
    }

    /**
     * Detect potential plugin conflicts
     */
    private function detect_plugin_conflicts() {
        $active_plugins = get_option('active_plugins', array());
        $conflicts = array();

        $problematic_plugins = array(
            'caching' => array('w3-total-cache', 'wp-super-cache', 'wp-rocket', 'autoptimize'),
            'security' => array('wordfence', 'ithemes-security', 'all-in-one-wp-security'),
            'optimization' => array('wp-optimize', 'wp-sweep', 'advanced-database-cleaner'),
            'backup' => array('updraftplus', 'backwpup', 'duplicator')
        );

        foreach ($active_plugins as $plugin) {
            $plugin_slug = dirname($plugin);
            foreach ($problematic_plugins as $category => $plugin_list) {
                foreach ($plugin_list as $problem_plugin) {
                    if (strpos($plugin_slug, $problem_plugin) !== false) {
                        $conflicts[] = array(
                            'plugin' => $plugin,
                            'category' => $category,
                            'potential_issue' => $this->get_plugin_conflict_description($category)
                        );
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Analyze current resource usage
     */
    private function analyze_resource_usage() {
        return array(
            'memory_usage' => certificate_generator_get_memory_usage(),
            'memory_peak' => certificate_generator_get_peak_memory_usage(),
            'memory_limit' => ini_get('memory_limit'),
            'memory_available' => $this->calculate_available_memory(),
            'execution_time' => $this->get_execution_time(),
            'time_limit' => ini_get('max_execution_time'),
            'time_remaining' => $this->calculate_remaining_time()
        );
    }

    /**
     * Check for security restrictions
     */
    private function check_security_restrictions() {
        $restrictions = array(
            'disabled_functions' => explode(',', ini_get('disable_functions')),
            'disabled_classes' => explode(',', ini_get('disable_classes')),
            'open_basedir' => ini_get('open_basedir'),
            'safe_mode' => (function_exists('ini_get') && ini_get('safe_mode')),
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'allow_url_include' => ini_get('allow_url_include'),
            'file_uploads' => ini_get('file_uploads'),
            'exec_functions' => array(
                'exec' => function_exists('exec'),
                'shell_exec' => function_exists('shell_exec'),
                'system' => function_exists('system'),
                'passthru' => function_exists('passthru')
            )
        );

        return $restrictions;
    }

    /**
     * Generate recommendations based on diagnostic results
     */
    private function generate_recommendations() {
        $provider = $this->diagnostic_results['hosting_provider'];

        // Add provider-specific recommendations
        if (isset($this->hosting_providers[$provider['key']])) {
            $provider_info = $this->hosting_providers[$provider['key']];
            foreach ($provider_info['solutions'] as $issue => $solution) {
                $this->diagnostic_results['recommendations'][] = array(
                    'category' => 'hosting',
                    'issue' => $issue,
                    'solution' => $solution,
                    'priority' => 'medium'
                );
            }
        }

        // Memory recommendations
        $memory_limit = certificate_generator_convert_to_bytes($this->diagnostic_results['php_configuration']['memory_limit']);
        if ($memory_limit > 0 && $memory_limit < (256 * 1024 * 1024)) {
            $this->diagnostic_results['critical_issues'][] = 'Memory limit is below recommended 256MB';
            $this->diagnostic_results['recommendations'][] = array(
                'category' => 'memory',
                'issue' => 'Low memory limit',
                'solution' => 'Increase PHP memory_limit to at least 256MB',
                'priority' => 'high'
            );
        }

        // Execution time recommendations
        $max_execution_time = (int) $this->diagnostic_results['php_configuration']['max_execution_time'];
        if ($max_execution_time > 0 && $max_execution_time < 300) {
            $this->diagnostic_results['warnings'][] = 'Execution time limit may be insufficient for large operations';
            $this->diagnostic_results['recommendations'][] = array(
                'category' => 'performance',
                'issue' => 'Low execution time limit',
                'solution' => 'Increase max_execution_time to at least 300 seconds',
                'priority' => 'medium'
            );
        }

        // File upload recommendations
        if (!$this->diagnostic_results['php_configuration']['file_uploads']) {
            $this->diagnostic_results['critical_issues'][] = 'File uploads are disabled';
            $this->diagnostic_results['recommendations'][] = array(
                'category' => 'uploads',
                'issue' => 'File uploads disabled',
                'solution' => 'Enable file_uploads in PHP configuration',
                'priority' => 'high'
            );
        }

        // Plugin conflict recommendations
        if (!empty($this->diagnostic_results['plugin_conflicts'])) {
            foreach ($this->diagnostic_results['plugin_conflicts'] as $conflict) {
                $this->diagnostic_results['warnings'][] = "Potential conflict with {$conflict['category']} plugin";
                $this->diagnostic_results['recommendations'][] = array(
                    'category' => 'conflicts',
                    'issue' => "Plugin conflict: {$conflict['plugin']}",
                    'solution' => $conflict['potential_issue'],
                    'priority' => 'medium'
                );
            }
        }
    }

    /**
     * Helper methods
     */
    private function test_url_connectivity($url) {
        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            return array(
                'status' => 'failed',
                'error' => $response->get_error_message(),
                'response_time' => 0
            );
        }

        return array(
            'status' => 'success',
            'response_code' => wp_remote_retrieve_response_code($response),
            'response_time' => 'Available'
        );
    }

    private function get_file_permissions($path) {
        if (file_exists($path)) {
            return substr(sprintf('%o', fileperms($path)), -4);
        }
        return 'Unknown';
    }

    private function get_plugin_conflict_description($category) {
        $descriptions = array(
            'caching' => 'May interfere with plugin activation or cause cached issues',
            'security' => 'Security restrictions may block plugin functionality',
            'optimization' => 'Database optimization may conflict with plugin tables',
            'backup' => 'Backup processes may lock database during activation'
        );

        return $descriptions[$category] ?? 'May cause conflicts';
    }



    private function calculate_available_memory() {
        $limit = certificate_generator_convert_to_bytes(ini_get('memory_limit'));
        $used = memory_get_usage(true);

        if ($limit == -1) {
            return 'Unlimited';
        }

        return certificate_generator_format_bytes($limit - $used);
    }

    private function get_execution_time() {
        if (function_exists('microtime')) {
            return round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) . 's';
        }
        return 'Unknown';
    }

    private function calculate_remaining_time() {
        $limit = (int) ini_get('max_execution_time');
        if ($limit == 0) {
            return 'Unlimited';
        }

        $elapsed = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $remaining = $limit - $elapsed;

        return round($remaining, 1) . 's';
    }



    /**
     * Get diagnostic results
     */
    public function get_results() {
        return $this->diagnostic_results;
    }

    /**
     * Export diagnostic report
     */
    public function export_report() {
        $report = $this->get_results();
        $filename = 'server-diagnostic-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($report, JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * Global diagnostic instance
 */
function certificate_generator_server_diagnostic() {
    static $instance = null;
    if ($instance === null) {
        $instance = new CertificateGenerator_ServerDiagnostic();
    }
    return $instance;
}
?>