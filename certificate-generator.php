<?php
/**
 * Plugin Name:       Certificate Generator
 * Plugin URI:        https://github.com/eshaanmanchanda/certificate-generator
 * Description:       A comprehensive plugin for managing, generating, and bulk-sending certificates for students and teachers.
 * Version:           7.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      6.7
 * Author:            Eshaan Manchanda
 * Author URI:        https://www.linkedin.com/in/eshaan-manchanda/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       certificate-generator
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define constants for plugin paths
define( 'CERTIFICATE_GENERATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CERTIFICATE_GENERATOR_URL', plugin_dir_url( __FILE__ ) );
define( 'CG_QUEUE_BATCH_SIZE', 50 );
define( 'CG_QUEUE_STALE_MINUTES', 10 );
define( 'CG_QUEUE_MAX_ATTEMPTS', 3 );
define( 'CG_QUEUE_RUNTIME_BUDGET', 20 );
define( 'CG_ADMIN_EXPORT_ZIP_PART_SIZE', 200 );

// Plugins page action links: Settings | Docs | Get Pro
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( array $links ): array {
		$custom = array(
			'settings' => '<a href="' . admin_url( 'options-general.php?page=certificate_generator_settings' ) . '">Settings</a>',
			'docs'     => '<a href="https://github.com/EshaanManchanda/Certificate-Generator/tree/master" target="_blank">Docs</a>',
			'get_pro'  => '<a href="https://eshaanportfolio.vercel.app/" target="_blank" style="color:#d63638;font-weight:600;">Get Pro</a>',
		);
		return array_merge( $custom, $links );
	}
);


/**
 * Format a MySQL datetime string for user-facing display (dd-mm-yyyy).
 *
 * @param string|null $date_string  MySQL datetime/date string.
 * @param bool        $include_time Include HH:ii in output.
 * @return string Formatted date, or '—' for empty/invalid input.
 */
function cg_format_date( ?string $date_string, bool $include_time = false ): string {
	if ( empty( $date_string ) || $date_string === '0000-00-00 00:00:00' ) {
		return '—';
	}
	$ts = strtotime( $date_string );
	if ( $ts === false ) {
		return $date_string;
	}
	return $include_time ? date( 'd-m-Y H:i', $ts ) : date( 'd-m-Y', $ts );
}

// Enqueue CSS and JS for Admin UI
function custom_admin_assets( $hook ) {
	$is_cg_page = strpos( $hook, 'cg-' ) !== false
		|| strpos( $hook, 'certificate' ) !== false
		|| strpos( $hook, 'students' ) !== false
		|| strpos( $hook, 'teachers' ) !== false
		|| strpos( $hook, 'schools' ) !== false
		|| $hook === 'post.php'
		|| $hook === 'post-new.php';

	if ( ! $is_cg_page ) {
		return;
	}

	wp_enqueue_style( 'custom-admin-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin-style.css' );
	wp_enqueue_script( 'custom-admin-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-script.js', array( 'jquery' ), '7.0.0', true );

	// Shared media uploader — enqueued on all CG admin pages where images may be selected.
	if ( strpos( $hook, 'cg-' ) !== false || strpos( $hook, 'certificate' ) !== false ) {
		wp_enqueue_media();
		wp_enqueue_script( 'cg-media-uploader', plugin_dir_url( __FILE__ ) . 'assets/js/cg-media-uploader.js', array( 'jquery' ), '1.0.0', true );
	}

	// Enqueue filter assets on specific admin pages
	$filter_pages = array( 'settings_page_certificate-bulk-send', 'settings_page_certificate-email-logs', 'edit-students', 'edit-teachers', 'edit-schools' );

	if ( in_array( $hook, $filter_pages ) || strpos( $hook, 'certificate' ) !== false ) {
		wp_enqueue_style( 'cert-filters-css', plugin_dir_url( __FILE__ ) . 'assets/css/admin-filters.css', array(), '1.0.13' );
		wp_enqueue_script( 'cert-filters-js', plugin_dir_url( __FILE__ ) . 'assets/js/admin-filters.js', array( 'jquery' ), '1.0.13', true );

		wp_localize_script(
			'cert-filters-js',
			'certFilterAjax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cert_bulk_send' ),
				'i18n'    => array(
					'confirm_send'  => __( 'Are you sure you want to start sending emails to the filtered recipients?', 'certificate-generator' ),
					'starting'      => __( 'Starting…', 'certificate-generator' ),
					'start_btn'     => __( '🚀 Start Bulk Send', 'certificate-generator' ),
					'error_generic' => __( 'An unexpected error occurred.', 'certificate-generator' ),
					'error_send'    => __( 'Failed to start bulk send. Please try again.', 'certificate-generator' ),
				),
			)
		);
	}

	// Localize AJAX data for student edit page
	if ( strpos( $hook, 'post.php' ) !== false || strpos( $hook, 'post-new.php' ) !== false ) {
		wp_localize_script(
			'custom-admin-js',
			'cgStudentAjax',
			array(
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'schoolNonce' => wp_create_nonce( 'cg_school_autocomplete' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'custom_admin_assets' );

// Include required files with enhanced error handling
$critical_files = array(
	'includes/Core/server-compatibility.php' => 'Server compatibility checker',
	'includes/Core/error-reporting.php'      => 'Error reporting system',
);

$optional_files = array(
	'includes/Core/field-schema.php'                    => 'Field schema manager',
	'includes/Core/post-types.php'                      => 'Certificate post type',
	'includes/Services/certificate-search.php'          => 'Student certificate search',
	'includes/Services/bulk-import.php'                 => 'Bulk import functionality',
	'includes/Services/bulk-export.php'                 => 'Bulk export functionality',
	'includes/Services/bulk-download.php'               => 'Bulk certificate download',
	'includes/Services/background-processor.php'        => 'Background processing',
	'includes/Core/license-manager.php'                 => 'License manager',
	'includes/Admin/usage-tracker.php'                  => 'Usage tracking',
	'includes/Admin/license-tab.php'                    => 'License settings tab',
	'includes/Admin/settings.php'                       => 'Admin settings',
	'includes/Admin/columns.php'                        => 'Admin columns',
	'includes/API/endpoints.php'                        => 'API endpoints',
	'includes/API/payment-endpoints.php'                => 'Payment API endpoints',
	'includes/Email/functions.php'                      => 'Email functions',
	'includes/Email/log.php'                            => 'Email logging',
	'includes/Admin/email-logs.php'                     => 'Admin email logs',
	'includes/Email/queue.php'                          => 'Email queue system',
	'includes/Email/rate-limiter.php'                   => 'Email rate limiter',
	'includes/Services/bulk-email-sender.php'           => 'Bulk email sender',
	'includes/legacy-shims.php'                         => 'v8 anti-corruption shims (frozen, @deprecated v8)',
	'includes/Admin/bulk-email.php'                     => 'Bulk email admin page',
	'includes/Admin/cert-download-admin.php'            => 'Admin certificate download page',
	'includes/Admin/filters-api.php'                    => 'Admin filters API',
	'includes/Admin/integration-dashboard.php'          => 'Integration health dashboard widget',
	'includes/Public/student-template.php'              => 'Student public profile template',
	'includes/Database/migrator.php'                    => 'Database migrator',
	'includes/Database/migration-scheduled-status.php'  => 'Scheduled status migration',
	'includes/Database/migration-send-email-column.php' => 'Send email column migration',
	'includes/Database/migration-queue-columns.php'     => 'Queue last_attempt_at + indexes migration',
	'includes/Services/serial-generator.php'            => 'Serial number generator',
	'includes/Services/qr-generator.php'                => 'QR code generator',
	'includes/Admin/cg-settings.php'                    => 'Admin settings',
	'includes/Admin/analytics.php'                      => 'Analytics dashboard',
	'includes/Admin/bulk-serial.php'                    => 'Bulk serial generator',
	'includes/Public/verification.php'                  => 'Public verification page',
	'includes/Cron/jobs.php'                            => 'Scheduled cron jobs',
	'includes/Admin/documentation.php'                  => 'Documentation & getting started page',
);

$missing_critical_files = array();
$missing_optional_files = array();

// Check and include critical files
foreach ( $critical_files as $file => $description ) {
	$path = CERTIFICATE_GENERATOR_PATH . $file;
	if ( file_exists( $path ) && is_readable( $path ) ) {
		require_once $path;
	} else {
		$missing_critical_files[] = "$description ($file)";
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Certificate Generator Debug - Missing critical file - $file" );
		}
	}
}

// Check and include optional files
foreach ( $optional_files as $file => $description ) {
	$path = CERTIFICATE_GENERATOR_PATH . $file;
	if ( file_exists( $path ) && is_readable( $path ) ) {
		require_once $path;
	} else {
		$missing_optional_files[] = "$description ($file)";
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Certificate Generator Debug - Missing optional file - $file" );
		}
	}
}

// Handle missing critical files
if ( ! empty( $missing_critical_files ) ) {
	$error_message = 'Certificate Generator cannot load due to missing critical files: ' . implode( ', ', $missing_critical_files );

	// Add admin notice instead of breaking the plugin
	add_action(
		'admin_notices',
		function () use ( $error_message ) {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Certificate Generator:</strong> ' . esc_html( $error_message ) . '</p></div>';
		}
	);

	error_log( 'Certificate Generator: Critical files missing - plugin may not function properly' );
}

// Store missing files info for admin display
if ( ! empty( $missing_optional_files ) ) {
	update_option( 'certificate_generator_missing_files', $missing_optional_files );
}

// ── New Architecture: PSR-4 Autoloader ──────────────────────────────────────
$autoloader = CERTIFICATE_GENERATOR_PATH . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
} else {
	// Fallback PSR-4 autoloader — active when composer install hasn't been run.
	// Maps CertificateGenerator\Foo\Bar → src/Foo/Bar.php
	spl_autoload_register(
		function ( string $class ): void {
			$prefix = 'CertificateGenerator\\';
			$len    = strlen( $prefix );
			if ( strncmp( $class, $prefix, $len ) !== 0 ) {
				return;
			}
			$relative = substr( $class, $len );
			$file     = CERTIFICATE_GENERATOR_PATH . 'src/' . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

if ( class_exists( '\CertificateGenerator\Core\Plugin' ) ) {
	$plugin = new \CertificateGenerator\Core\Plugin();

	register_activation_hook( __FILE__, array( \CertificateGenerator\Core\Plugin::class, 'activate' ) );
	register_deactivation_hook( __FILE__, array( \CertificateGenerator\Core\Plugin::class, 'deactivate' ) );

	add_action(
		'plugins_loaded',
		function () use ( $plugin ) {
			$plugin->boot();
		}
	);
}

// ── Legacy Enhancement Classes (keep working while src/ migration continues) ─
if ( class_exists( 'CG_Migrator' ) ) {
	CG_Migrator::run();
}

if ( class_exists( 'CG_Serial_Number_Generator' ) ) {
	$serial_gen = CG_Serial_Number_Generator::get_instance();
	$serial_gen->register_api_endpoints();
}

if ( class_exists( 'CG_QR_Code_Generator' ) ) {
	CG_QR_Code_Generator::get_instance()->register_template_meta_fields();
}

if ( class_exists( 'CG_Admin_Settings' ) ) {
	$admin_settings = new CG_Admin_Settings();
	$admin_settings->init();
}

if ( class_exists( 'CG_Analytics_Dashboard' ) ) {
	CG_Analytics_Dashboard::get_instance()->init();
}

if ( class_exists( 'CG_Bulk_Serial_Generator' ) ) {
	CG_Bulk_Serial_Generator::get_instance()->init();
}

if ( class_exists( 'CG_Public_Verification' ) ) {
	CG_Public_Verification::get_instance()->init();
}

if ( class_exists( 'CG_Cron_Jobs' ) ) {
	CG_Cron_Jobs::init();
}

// ── Custom Tables: Create tables + sync hooks ────────────────────────────────
if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
	$custom_tables = \CertificateGenerator\Database\CustomTables::instance();

	// Create tables on every load (safe - uses IF NOT EXISTS)
	if ( ! $custom_tables->all_tables_exist() ) {
		$custom_tables->create_all();
	}

	// Register all admin pages — centralized menu organization
	add_action(
		'admin_menu',
		function () {
			// Top-level dashboard menu
			add_menu_page(
				'Certificate Generator',
				'Certificate Generator',
				'manage_options',
				'cg-dashboard',
				function () {
					echo '<div class="wrap"><h1>Certificate Generator</h1><p>Use the menu on the left to manage students, teachers, schools, certificates, and settings.</p></div>';
				},
				'dashicons-award',
				25
			);

			// ── Entity Management ──
			if ( class_exists( '\CertificateGenerator\Admin\Pages\StudentsPage' ) ) {
				( new \CertificateGenerator\Admin\Pages\StudentsPage() )->register();
			}
			if ( class_exists( '\CertificateGenerator\Admin\Pages\TeachersPage' ) ) {
				( new \CertificateGenerator\Admin\Pages\TeachersPage() )->register();
			}
			if ( class_exists( '\CertificateGenerator\Admin\Pages\SchoolsPage' ) ) {
				( new \CertificateGenerator\Admin\Pages\SchoolsPage() )->register();
			}
			if ( class_exists( '\CertificateGenerator\Admin\Pages\TemplatesPage' ) ) {
				( new \CertificateGenerator\Admin\Pages\TemplatesPage() )->register();
			}

			// ── Bulk Operations ──
			add_submenu_page( 'cg-dashboard', 'Bulk Import', 'Bulk Import', 'manage_options', 'cg-bulk-import', 'cg_render_bulk_import_page' );
			add_submenu_page( 'cg-dashboard', 'Bulk Export', 'Bulk Export', 'manage_options', 'cg-bulk-export', 'cg_render_bulk_export_page' );
			add_submenu_page( 'cg-dashboard', 'Download Certificates', 'Download Certs', 'manage_options', 'cg-cert-download', 'cg_render_admin_cert_download_page' );
			// cg-bulk-serials registered by CG_Bulk_Serial_Generator::add_bulk_serial_menu() in bulk-serial.php — not duplicated here.

			// ── Email ──
			add_submenu_page( 'cg-dashboard', 'Bulk Send Certificates', 'Bulk Send', 'manage_options', 'certificate-bulk-send', 'cg_render_bulk_send_page' );
			add_submenu_page( 'cg-dashboard', 'Email Logs', 'Email Logs', 'manage_options', 'certificate-email-logs', 'cg_render_email_logs_page' );

			// ── Analytics & Settings ──
			add_submenu_page( 'cg-dashboard', 'Certificate Analytics', 'Analytics', 'manage_options', 'cg-analytics', 'cg_render_analytics_page' );
			add_submenu_page( 'cg-dashboard', 'Serial Number Settings', 'Serial Settings', 'manage_options', 'cg-serial-settings', 'cg_render_serial_settings_page' );

			// ── Migration ──
			if ( class_exists( '\CertificateGenerator\Admin\Pages\MigrationPage' ) ) {
				( new \CertificateGenerator\Admin\Pages\MigrationPage() )->register();
			}

			// ── Documentation & Getting Started (always last) ──
			add_submenu_page(
				'cg-dashboard',
				'Documentation',
				'📖 Documentation',
				'manage_options',
				'cg-documentation',
				'cg_render_documentation_page'
			);
		},
		10
	);



}

// Plugin activation hook
function certificate_generator_activate() {
	try {
		// Run compatibility check first
		if ( function_exists( 'certificate_generator_quick_compatibility_check' ) ) {
			$compatibility = certificate_generator_quick_compatibility_check();

			if ( ! $compatibility['compatible'] ) {
				$error_message = 'Certificate Generator cannot be activated due to server compatibility issues: ' .
								implode( ', ', $compatibility['errors'] );

				update_option( 'certificate_generator_activation_error', $error_message );
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( $error_message . '<br><br><a href="' . admin_url( 'plugins.php' ) . '">Return to Plugins</a>' );
			}

			update_option( 'certificate_generator_compatibility', $compatibility );
		}

		global $wpdb;

		// Verify required WordPress functions exist
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		// Create the primary database table
		$table_name      = $wpdb->prefix . 'certificate_generator';
		$charset_collate = $wpdb->get_charset_collate();

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

		$result = dbDelta( $sql );

		// Check if table was created successfully
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) != $table_name ) {
			// Try alternative method
			$wpdb->query( $sql );

			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) != $table_name ) {
				throw new Exception( 'Failed to create required database table. Please check database permissions.' );
			}
		}

		// Create email log table
		if ( function_exists( 'certificate_generator_create_email_log_table' ) ) {
			certificate_generator_create_email_log_table();
		}

		// Create email queue table
		if ( function_exists( 'certificate_generator_create_email_queue_table' ) ) {
			certificate_generator_create_email_queue_table();
		}

		// Set plugin version
		update_option( 'certificate_generator_version', '7.0.0' );

		// Set activation timestamp
		update_option( 'certificate_generator_activated_at', current_time( 'timestamp' ) );

		// Show welcome banner on next admin load
		delete_option( 'cg_welcome_dismissed' );
		set_transient( 'cg_activation_redirect', 1, 30 );

		// Determine installation mode based on server capabilities
		$installation_mode = 'minimal'; // Safe default
		if ( function_exists( 'certificate_generator_get_installation_recommendation' ) ) {
			$recommendation    = certificate_generator_get_installation_recommendation();
			$installation_mode = $recommendation['mode'] === 'not_compatible' ? 'minimal' : $recommendation['mode'];
		}

		// Set initial settings with safe defaults
		$default_settings = array(
			'installation_mode'    => $installation_mode,
			'max_memory_usage'     => '64M',
			'enable_error_logging' => true,
			'font_loading_mode'    => 'on_demand',
			'debug_mode'           => false,
		);

		if ( ! get_option( 'certificate_generator_settings' ) ) {
			update_option( 'certificate_generator_settings', $default_settings );
		}

		// Schedule license heartbeat + monthly usage reset cron.
		if ( class_exists( 'CG_License_Manager' ) ) {
			CG_License_Manager::schedule_cron();
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Clear any previous activation errors
		delete_option( 'certificate_generator_activation_error' );

		error_log( 'Certificate Generator: Plugin activated successfully.' );

	} catch ( Exception $e ) {
		error_log( 'Certificate Generator Activation Error: ' . $e->getMessage() );

		update_option(
			'certificate_generator_activation_error',
			array(
				'message'      => $e->getMessage(),
				'timestamp'    => current_time( 'mysql' ),
				'php_version'  => PHP_VERSION,
				'wp_version'   => get_bloginfo( 'version' ),
				'memory_limit' => ini_get( 'memory_limit' ),
			)
		);

		deactivate_plugins( plugin_basename( __FILE__ ) );

		$error_message  = 'Certificate Generator could not be activated: ' . $e->getMessage();
		$error_message .= '<br>• PHP Version: ' . PHP_VERSION;
		$error_message .= '<br>• WordPress Version: ' . get_bloginfo( 'version' );
		$error_message .= '<br>• Memory Limit: ' . ini_get( 'memory_limit' );
		$error_message .= '<br><br><a href="' . admin_url( 'plugins.php' ) . '" class="button">Return to Plugins</a>';

		wp_die( $error_message );
	}
}

// Register the activation hook
register_activation_hook( __FILE__, 'certificate_generator_activate' );

// Redirect to Getting Started page after activation (fires once, then clears)
add_action(
	'admin_init',
	function () {
		if ( ! get_transient( 'cg_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'cg_activation_redirect' );
		if ( isset( $_GET['activate-multi'] ) ) {
			return; // skip on bulk activate
		}
		wp_safe_redirect( admin_url( 'admin.php?page=cg-documentation&tab=getting-started' ) );
		exit;
	}
);

// Plugin deactivation hook
function certificate_generator_deactivate() {
	flush_rewrite_rules();
	wp_clear_scheduled_hook( 'certificate_generator_cleanup_logs' );
	if ( class_exists( 'CG_Cron_Jobs' ) ) {
		CG_Cron_Jobs::deactivate();
	}
	if ( class_exists( 'CG_License_Manager' ) ) {
		CG_License_Manager::unschedule_cron();
	}
}
register_deactivation_hook( __FILE__, 'certificate_generator_deactivate' );

// Plugin uninstall hook
function certificate_generator_uninstall() {
	// If the user chose to keep data, stop here — all tables and options are preserved.
	if ( get_option( 'cg_keep_data_on_uninstall', '1' ) === '1' ) {
		return;
	}

	global $wpdb;

	// Drop legacy tables.
	$legacy_tables = array(
		$wpdb->prefix . 'certificate_generator',
		$wpdb->prefix . 'cert_email_logs',
		$wpdb->prefix . 'cert_email_queue',
	);

	// Drop new cg_* custom tables.
	$cg_prefix = $wpdb->prefix . 'cg_';
	$cg_tables = array(
		$cg_prefix . 'students',
		$cg_prefix . 'teachers',
		$cg_prefix . 'schools',
		$cg_prefix . 'certificate_templates',
		$cg_prefix . 'certificates',
		$cg_prefix . 'email_logs',
		$cg_prefix . 'email_queue',
		$cg_prefix . 'student_certificates',
		$cg_prefix . 'teacher_certificates',
		$cg_prefix . 'settings',
		$cg_prefix . 'migrations',
	);

	foreach ( array_merge( $legacy_tables, $cg_tables ) as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `$table`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// Delete all plugin options.
	$options = array(
		'certificate_generator_version',
		'certificate_generator_activated_at',
		'certificate_generator_activation_error',
		'certificate_generator_compatibility',
		'certificate_generator_missing_files',
		'certificate_generator_rate_limits',
		'certificate_generator_settings_email',
		'cg_custom_tables_version',
		'cg_db_version',
		'cg_license_key',
		'cg_license_status',
		'cg_license_server_url',
		'cg_gema_api_key',
		'cg_migration_v7_done',
		'cg_migration_scheduled_status_done',
		'cg_keep_data_on_uninstall',
		'cg_welcome_dismissed',
		'cg_email_transport',
		'cg_email_from_name',
		'cg_email_from_email',
		'cg_email_subject',
		'cg_email_body',
		'cg_smtp_host',
		'cg_smtp_port',
		'cg_smtp_username',
		'cg_smtp_password',
		'cg_smtp_encryption',
		'cg_serial_prefix',
		'cg_serial_length',
		'cg_serial_suffix',
		'cg_serial_reset_period',
		'cg_serial_include_date',
	);
	foreach ( $options as $opt ) {
		delete_option( $opt );
	}

	// Remove scheduled cron events.
	wp_clear_scheduled_hook( 'certificate_generator_process_email_queue' );
	wp_clear_scheduled_hook( 'cg_bulk_generate_serials' );
	wp_clear_scheduled_hook( 'cg_publish_scheduled_templates' );
	wp_clear_scheduled_hook( 'cg_cleanup_qr_codes' );
	wp_clear_scheduled_hook( 'cg_check_expiring_certificates' );
	wp_clear_scheduled_hook( 'cg_cleanup_old_certificates' );
}
register_uninstall_hook( __FILE__, 'certificate_generator_uninstall' );

// ── Block public access to legacy CPT slugs (students/teachers/schools) ──────
add_action(
	'template_redirect',
	function () {
		if ( is_singular( array( 'students', 'teachers', 'schools', 'certificates' ) ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);

// ── Plugins-page modal: ask "Keep data?" before deletion ─────────────────────
add_action(
	'admin_footer-plugins.php',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plugin_file = plugin_basename( __FILE__ );
		$nonce       = wp_create_nonce( 'cg_set_keep_data' );
		?>
	<style>
	#cg-uninstall-modal-backdrop {
		display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:100000;
	}
	#cg-uninstall-modal {
		position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
		background:#fff; border-radius:8px; padding:32px 36px; max-width:420px; width:90%;
		box-shadow:0 8px 40px rgba(0,0,0,.2); z-index:100001; text-align:center;
	}
	#cg-uninstall-modal h2 { margin:0 0 12px; font-size:20px; color:#1d2327; }
	#cg-uninstall-modal p  { color:#50575e; margin:0 0 24px; line-height:1.6; }
	#cg-uninstall-modal .cg-modal-btns { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
	#cg-uninstall-modal .cg-modal-btns button { padding:10px 22px; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; border:none; }
	#cg-btn-keep   { background:#2271b1; color:#fff; }
	#cg-btn-delete { background:#d63638; color:#fff; }
	#cg-btn-cancel { background:#f0f0f1; color:#2c3338; }
	</style>

	<div id="cg-uninstall-modal-backdrop">
		<div id="cg-uninstall-modal">
			<h2><?php esc_html_e( 'Uninstalling Certificate Generator', 'certificate-generator' ); ?></h2>
			<p><?php esc_html_e( 'Do you want to keep your certificate data (students, templates, email logs)?', 'certificate-generator' ); ?><br>
			<small><?php esc_html_e( 'If you keep the data and reinstall the plugin, everything will still be there.', 'certificate-generator' ); ?></small></p>
			<div class="cg-modal-btns">
				<button id="cg-btn-keep"><?php esc_html_e( 'Yes, Keep Data', 'certificate-generator' ); ?></button>
				<button id="cg-btn-delete"><?php esc_html_e( 'No, Delete Everything', 'certificate-generator' ); ?></button>
				<button id="cg-btn-cancel"><?php esc_html_e( 'Cancel', 'certificate-generator' ); ?></button>
			</div>
		</div>
	</div>

	<script>
	(function($) {
		var pluginFile = <?php echo wp_json_encode( $plugin_file ); ?>;
		var nonce      = <?php echo wp_json_encode( $nonce ); ?>;
		var deleteHref = null;
		var backdrop   = $('#cg-uninstall-modal-backdrop');

		// Find and intercept the Delete link for this plugin.
		$('tr[data-plugin="' + pluginFile + '"] .delete a, ' +
			'tr[data-slug="certificate-generator-v7"] .delete a').on('click', function(e) {
			e.preventDefault();
			deleteHref = this.href;
			backdrop.fadeIn(150);
		});

		function proceed(keepData) {
			$.post(ajaxurl, {
				action : 'cg_set_keep_data',
				keep   : keepData ? '1' : '0',
				nonce  : nonce
			}).always(function() {
				window.location.href = deleteHref;
			});
		}

		$('#cg-btn-keep').on('click',   function() { proceed(true);  });
		$('#cg-btn-delete').on('click', function() { proceed(false); });
		$('#cg-btn-cancel, #cg-uninstall-modal-backdrop').on('click', function(e) {
			if (e.target === this) { backdrop.fadeOut(150); deleteHref = null; }
		});
	})(jQuery);
	</script>
		<?php
	}
);

// AJAX: store the keep-data preference before WP proceeds with deletion.
add_action(
	'wp_ajax_cg_set_keep_data',
	function () {
		check_ajax_referer( 'cg_set_keep_data', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		$keep = ( sanitize_text_field( $_POST['keep'] ?? '1' ) === '0' ) ? '0' : '1';
		update_option( 'cg_keep_data_on_uninstall', $keep );
		wp_send_json_success();
	}
);

// Plugin update logic + one-time v7 migration
function certificate_generator_update_check() {
	$current_version = get_option( 'certificate_generator_version', '' );
	$new_version     = '7.0.0';

	if ( $current_version !== $new_version ) {
		update_option( 'certificate_generator_version', $new_version );
	}

	// v7 migration: copy legacy gema API URL → license server URL (runs once).
	if ( ! get_option( 'cg_migration_v7_done' ) ) {
		if ( ! get_option( 'cg_license_server_url' ) ) {
			$legacy = get_option( 'cg_gema_api_url', '' );
			if ( $legacy ) {
				update_option( 'cg_license_server_url', rtrim( $legacy, '/' ) );
			}
		}
		update_option( 'cg_migration_v7_done', true );
	}
}
add_action( 'plugins_loaded', 'certificate_generator_update_check' );

// Add admin notices for compatibility and missing files
function certificate_generator_admin_notices() {
	// Check for compatibility warnings
	$compatibility = get_option( 'certificate_generator_compatibility' );
	if ( $compatibility && ! empty( $compatibility['warnings'] ) ) {
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>Certificate Generator Warnings:</strong></p>';
		echo '<ul>';
		foreach ( $compatibility['warnings'] as $warning ) {
			echo '<li>' . esc_html( $warning ) . '</li>';
		}
		echo '</ul>';
		if ( ! empty( $compatibility['recommendations'] ) ) {
			echo '<p><strong>Recommendations:</strong></p>';
			echo '<ul>';
			foreach ( $compatibility['recommendations'] as $recommendation ) {
				echo '<li>' . esc_html( $recommendation ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	// Check for missing optional files
	$missing_files = get_option( 'certificate_generator_missing_files' );
	if ( ! empty( $missing_files ) ) {
		echo '<div class="notice notice-info is-dismissible">';
		echo '<p><strong>Certificate Generator:</strong> Some optional features are unavailable due to missing files:</p>';
		echo '<ul>';
		foreach ( $missing_files as $file ) {
			echo '<li>' . esc_html( $file ) . '</li>';
		}
		echo '</ul>';
		echo '<p>You can still use the plugin, but some features may be limited. Please re-upload the complete plugin files if you need these features.</p>';
		echo '</div>';
	}

	// Show installation mode notice
	$settings = get_option( 'certificate_generator_settings' );
	if ( $settings && isset( $settings['installation_mode'] ) && $settings['installation_mode'] === 'minimal' ) {
		echo '<div class="notice notice-info">';
		echo '<p><strong>Certificate Generator:</strong> Running in minimal mode due to server limitations. ';
		echo 'Some advanced features are disabled to ensure compatibility with your hosting environment.</p>';
		echo '</div>';
	}
}
add_action( 'admin_notices', 'certificate_generator_admin_notices' );

// Add memory usage monitoring
function certificate_generator_check_memory_usage() {
	if ( function_exists( 'memory_get_usage' ) && function_exists( 'memory_get_peak_usage' ) ) {
		$current_memory = memory_get_usage( true );
		$peak_memory    = memory_get_peak_usage( true );
		$memory_limit   = ini_get( 'memory_limit' );

		// Convert memory limit to bytes for comparison
		$memory_limit_bytes = certificate_generator_convert_to_bytes( $memory_limit );

		// Log if memory usage is getting high (80% of limit)
		if ( $memory_limit_bytes > 0 && $current_memory > ( $memory_limit_bytes * 0.8 ) ) {
			error_log(
				sprintf(
					'Certificate Generator: High memory usage detected. Current: %s, Peak: %s, Limit: %s',
					certificate_generator_format_bytes( $current_memory ),
					certificate_generator_format_bytes( $peak_memory ),
					$memory_limit
				)
			);
		}
	}
}

// Helper function to convert memory string to bytes
function certificate_generator_convert_to_bytes( $size_str ) {
	if ( empty( $size_str ) || $size_str === '-1' ) {
		return -1; // Unlimited
	}

	$size_str  = trim( $size_str );
	$last_char = strtolower( $size_str[ strlen( $size_str ) - 1 ] );
	$size      = (int) $size_str;

	switch ( $last_char ) {
		case 'g':
			$size *= 1024;
		case 'm':
			$size *= 1024;
		case 'k':
			$size *= 1024;
	}

	return $size;
}

// Helper function to format bytes for human reading
function certificate_generator_format_bytes( $bytes ) {
	if ( $bytes == -1 ) {
		return 'Unlimited';
	}

	if ( $bytes >= 1024 * 1024 * 1024 ) {
		return round( $bytes / ( 1024 * 1024 * 1024 ), 1 ) . 'GB';
	} elseif ( $bytes >= 1024 * 1024 ) {
		return round( $bytes / ( 1024 * 1024 ), 1 ) . 'MB';
	} elseif ( $bytes >= 1024 ) {
		return round( $bytes / 1024, 1 ) . 'KB';
	} else {
		return $bytes . ' bytes';
	}
}

// Helper function to get current memory usage formatted
function certificate_generator_get_memory_usage() {
	if ( function_exists( 'memory_get_usage' ) ) {
		return certificate_generator_format_bytes( memory_get_usage( true ) );
	}
	return 'Unknown';
}

// Global debug logging — delegates to error_log when WP_DEBUG is on.
function certificate_generator_log_debug( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[Certificate Generator] ' . $message );
	}
}

// Add memory monitoring to admin pages
add_action( 'admin_init', 'certificate_generator_check_memory_usage' );

// Redirect removed standalone pages to the main settings page.
add_action(
	'admin_init',
	function () {
		if ( ! current_user_can( 'manage_options' ) || empty( $_GET['page'] ) ) {
			return;
		}
		$page = $_GET['page'];
		if ( $page === 'cg-email-settings' ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=certificate_generator_settings&tab=templates' ) );
			exit;
		}
		if ( $page === 'cert-gen-debug-dashboard' || $page === 'cert-gen-recovery' ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=certificate_generator_settings' ) );
			exit;
		}
	}
);

// ── Centralized page renderers ──

function cg_render_bulk_import_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions' );
	}
	require_once CERTIFICATE_GENERATOR_PATH . 'includes/Services/bulk-import.php';
	$tab  = sanitize_key( $_GET['tab'] ?? 'students' );
	$tabs = array(
		'students'     => 'Students',
		'teachers'     => 'Teachers',
		'schools'      => 'Schools',
		'certificates' => 'Certificates',
	);
	$base = admin_url( 'admin.php?page=cg-bulk-import' );
	?>
	<div class="wrap">
		<h1>Bulk Import</h1>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base ) ); ?>"
					class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>
		<div style="margin-top: 20px;">
			<?php
			switch ( $tab ) {
				case 'students':
					bulk_import_students();
					break;
				case 'teachers':
					bulk_import_teachers();
					break;
				case 'schools':
					bulk_import_schools();
					break;
				case 'certificates':
					bulk_import_certificates();
					break;
				default:
					bulk_import_students();
			}
			?>
		</div>
	</div>
	<?php
}

function cg_render_bulk_export_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions' );
	}
	require_once CERTIFICATE_GENERATOR_PATH . 'includes/Services/bulk-export.php';
	$tab  = sanitize_key( $_GET['tab'] ?? 'students' );
	$tabs = array(
		'students'     => 'Students',
		'teachers'     => 'Teachers',
		'schools'      => 'Schools',
		'certificates' => 'Certificates',
	);
	$base = admin_url( 'admin.php?page=cg-bulk-export' );
	?>
	<div class="wrap">
		<h1>Bulk Export</h1>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base ) ); ?>"
					class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>
		<div style="margin-top: 20px;">
			<?php
			switch ( $tab ) {
				case 'students':
					render_bulk_export_students_page();
					break;
				case 'teachers':
					render_bulk_export_teachers_page();
					break;
				case 'schools':
					render_bulk_export_schools_page();
					break;
				case 'certificates':
					render_bulk_export_certificates_page();
					break;
				default:
					render_bulk_export_students_page();
			}
			?>
		</div>
	</div>
	<?php
}

function cg_render_bulk_serials_page(): void {
	$instance = CG_Bulk_Serial_Generator::get_instance();
	$instance->render_bulk_serial_page();
}

function cg_render_bulk_send_page(): void {
	if ( function_exists( 'certificate_generator_bulk_send_page' ) ) {
		certificate_generator_bulk_send_page();
	} else {
		echo '<div class="wrap"><h1>Bulk Send Certificates</h1><p>Bulk send functionality is not available.</p></div>';
	}
}

function cg_render_email_logs_page(): void {
	if ( function_exists( 'certificate_generator_email_logs_page' ) ) {
		certificate_generator_email_logs_page();
	} else {
		echo '<div class="wrap"><h1>Email Logs</h1><p>Email logs functionality is not available.</p></div>';
	}
}

function cg_render_analytics_page(): void {
	if ( class_exists( 'CG_Analytics_Dashboard' ) ) {
		$instance = CG_Analytics_Dashboard::get_instance();
		$instance->render_analytics_page();
	} else {
		echo '<div class="wrap"><h1>Analytics</h1><p>Analytics functionality is not available.</p></div>';
	}
}

function cg_render_serial_settings_page(): void {
	if ( class_exists( 'CG_Admin_Settings' ) ) {
		$instance = new CG_Admin_Settings();
		$instance->render_serial_settings_page();
	} else {
		echo '<div class="wrap"><h1>Serial Settings</h1><p>Serial settings functionality is not available.</p></div>';
	}
}
?>