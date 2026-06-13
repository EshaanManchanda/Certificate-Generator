<?php
declare(strict_types=1);

namespace CertificateGenerator\Core;

use CertificateGenerator\Database\Migrations\MigrationRunner;
use CertificateGenerator\Email\Mailer;
use CertificateGenerator\Services\CertificateService;
use CertificateGenerator\Services\EmailService;
use CertificateGenerator\Services\QRCodeService;
use CertificateGenerator\Services\SerialNumberService;
use CertificateGenerator\Services\SettingsService;
use CertificateGenerator\Certificate\Expiration;
use CertificateGenerator\Integrations\GemaAPI;

/**
 * Main plugin class. Handles lifecycle, service registration, and bootstrapping.
 */
class Plugin {

	private Container $container;

	public function __construct() {
		$this->container = new Container();
	}

	public function boot(): void {
		SettingsService::migrate_legacy();
		if ( get_option( 'cg_defaults_seeded' ) !== '2' ) {
			SettingsService::seed_defaults();
			update_option( 'cg_defaults_seeded', '2', 'no' );
		}
		$this->register_services();
		$this->register_hooks();
	}

	private function register_services(): void {
		$this->container->singleton(
			Container::class,
			function () {
				return $this->container;
			}
		);

		$this->container->singleton(
			Expiration::class,
			function () {
				return new Expiration();
			}
		);

		$this->container->singleton(
			CertificateService::class,
			function ( $c ) {
				return new CertificateService( $c->make( Expiration::class ) );
			}
		);

		$this->container->singleton(
			SerialNumberService::class,
			function () {
				return new SerialNumberService();
			}
		);

		$this->container->singleton(
			QRCodeService::class,
			function () {
				return new QRCodeService();
			}
		);

		$this->container->singleton(
			Mailer::class,
			function () {
				return Mailer::make();
			}
		);

		$this->container->singleton(
			EmailService::class,
			function ( $c ) {
				return new EmailService( $c->make( Mailer::class ) );
			}
		);

		$this->container->singleton(
			GemaAPI::class,
			function () {
				$base_url = get_option( 'cg_gema_api_url', '' );
				$api_key  = get_option( 'cg_gema_api_key', '' );
				return new GemaAPI( $base_url, $api_key );
			}
		);
	}

	private function register_hooks(): void {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );

		if ( Config::flag( 'CG_USE_EVENTS' ) ) {
			$log_listener   = new \CertificateGenerator\Listeners\LogEmailListener();
			$analytics      = new \CertificateGenerator\Listeners\AnalyticsListener();
			$cache_listener = new \CertificateGenerator\Listeners\InvalidateStatusCacheListener();

			// Priority 10: log first (row must exist before analytics reads counters).
			add_action( 'cg_email_sent', array( $log_listener,   'handle' ), 10 );
			add_action( 'cg_email_sent', array( $analytics,      'handle' ), 20 );
			add_action( 'cg_email_sent', array( $cache_listener, 'handle' ), 30 );
		}
	}

	public function init(): void {
		load_plugin_textdomain( 'certificate-generator', false, dirname( plugin_basename( __DIR__ ) ) . '/languages/' );
	}

	public function register_api_routes(): void {
		$serial_service = $this->container->make( SerialNumberService::class );
		$serial_service->register_api_routes();
	}

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( class_exists( '\CertificateGenerator\Database\Migrations\MigrationRunner' ) ) {
			$runner = new MigrationRunner();
			$runner->run();
		} elseif ( class_exists( '\CG_Migrator' ) ) {
			\CG_Migrator::run();
		}

		// Pre-create centralized certificate storage folder.
		wp_mkdir_p( wp_upload_dir()['basedir'] . '/cg_certificates' );

		// Seed default options so email works out of the box.
		SettingsService::seed_defaults();

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( class_exists( '\CG_Cron_Jobs' ) ) {
			\CG_Cron_Jobs::deactivate();
		}

		flush_rewrite_rules();
	}

	public function get_container(): Container {
		return $this->container;
	}
}
