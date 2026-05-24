<?php
/**
 * Certificate Generator License Manager
 *
 * Self-contained plan/feature-gating layer.
 * Plans: 'free' | 'pro' | 'business'
 *
 * To upgrade a site for testing:
 *   wp option update cg_plan pro
 *   wp option update cg_plan business
 *
 * @package Certificate_Generator
 * @since   7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CG_License_Manager {

	// -----------------------------------------------------------------------
	// Constants
	// -----------------------------------------------------------------------

	const OPTION_PLAN        = 'cg_plan';
	const OPTION_LICENSE_KEY = 'cg_license_key';
	const OPTION_EXPIRY      = 'cg_license_expiry';
	const OPTION_USAGE       = 'cg_monthly_usage';
	const OPTION_USAGE_MONTH = 'cg_usage_month';
	const CRON_HOOK          = 'cg_reset_monthly_usage';
	const CRON_HEARTBEAT     = 'cg_license_heartbeat';

	const PLAN_FREE     = 'free';
	const PLAN_PRO      = 'pro';
	const PLAN_BUSINESS = 'business';

	/**
	 * Per-plan monthly certificate limits.
	 * 0 = unlimited.
	 */
	const LIMITS = array(
		self::PLAN_FREE     => 100,
		self::PLAN_PRO      => 1000,
		self::PLAN_BUSINESS => 0,
	);

	/** @var self|null */
	private static $instance = null;

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	// -----------------------------------------------------------------------
	// Plan helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns the current plan slug: 'free', 'pro', or 'business'.
	 */
	public static function get_plan(): string {
		$plan  = (string) get_option( self::OPTION_PLAN, self::PLAN_FREE );
		$valid = array( self::PLAN_FREE, self::PLAN_PRO, self::PLAN_BUSINESS );
		return in_array( $plan, $valid, true ) ? $plan : self::PLAN_FREE;
	}

	public static function is_pro(): bool {
		return in_array( self::get_plan(), array( self::PLAN_PRO, self::PLAN_BUSINESS ), true );
	}

	public static function is_business(): bool {
		return self::get_plan() === self::PLAN_BUSINESS;
	}

	public static function is_free(): bool {
		return self::get_plan() === self::PLAN_FREE;
	}

	/**
	 * Returns a display-friendly plan name.
	 */
	public static function get_plan_label(): string {
		$labels = array(
			self::PLAN_FREE     => __( 'Free', 'certificate-generator' ),
			self::PLAN_PRO      => __( 'Pro', 'certificate-generator' ),
			self::PLAN_BUSINESS => __( 'Business', 'certificate-generator' ),
		);
		return $labels[ self::get_plan() ] ?? 'Free';
	}

	// -----------------------------------------------------------------------
	// License key / expiry
	// -----------------------------------------------------------------------

	public static function get_license_key(): string {
		return (string) get_option( self::OPTION_LICENSE_KEY, '' );
	}

	public static function get_expiry(): string {
		return (string) get_option( self::OPTION_EXPIRY, '' );
	}

	/**
	 * Activate a license key.
	 *
	 * Currently a local-only stub — stores the key and upgrades the plan.
	 * Replace the body of this method with a remote license-server call
	 * (Freemius, WooCommerce, custom API, etc.) when payments are ready.
	 *
	 * @param string $key
	 * @return array { success: bool, message: string }
	 */
	/**
	 * Returns the configured remote license server URL (no trailing slash).
	 * Defaults to CG_LICENSE_SERVER constant or 'cg_license_server_url' option.
	 */
	public static function get_license_server(): string {
		if ( defined( 'CG_LICENSE_SERVER' ) && CG_LICENSE_SERVER ) {
			return rtrim( (string) CG_LICENSE_SERVER, '/' );
		}
		$opt = (string) get_option( 'cg_license_server_url', '' );
		return rtrim( $opt, '/' );
	}

	/**
	 * Validate a license key against the remote portfolio backend.
	 *
	 * POST {server}/api/payments/activate-remote
	 * Body: { license_key, site_url }
	 * Expected response: { success, valid, plan, expiry }
	 *
	 * @param string $key
	 * @return array|WP_Error
	 */
	public static function remote_validate( string $key ) {
		$server = self::get_license_server();
		if ( empty( $server ) ) {
			return new WP_Error( 'no_server', 'License server not configured' );
		}

		$response = wp_remote_post(
			$server . '/api/payments/activate-remote',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key' => $key,
						'site_url'    => home_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'bad_response', 'Invalid response from license server' );
		}
		return $body;
	}

	public static function activate_license( string $key ): array {
		$key = strtoupper( sanitize_text_field( $key ) );

		if ( empty( $key ) ) {
			return array(
				'success' => false,
				'message' => __( 'License key cannot be empty.', 'certificate-generator' ),
			);
		}

		// 1. Remote validation (portfolio backend) — preferred when configured.
		if ( self::get_license_server() ) {
			$remote = self::remote_validate( $key );

			if ( is_wp_error( $remote ) ) {
				return array(
					'success' => false,
					'message' => $remote->get_error_message(),
				);
			}
			if ( empty( $remote['valid'] ) ) {
				return array(
					'success' => false,
					'message' => $remote['message'] ?? __( 'Invalid license key.', 'certificate-generator' ),
				);
			}

			$plan   = sanitize_text_field( $remote['plan'] );
			$expiry = sanitize_text_field( $remote['expiry'] ?? gmdate( 'Y-m-d', strtotime( '+1 year' ) ) );

			$valid_plans = array( self::PLAN_PRO, self::PLAN_BUSINESS );
			if ( ! in_array( $plan, $valid_plans, true ) ) {
				return array(
					'success' => false,
					'message' => __( 'Unknown plan returned by server.', 'certificate-generator' ),
				);
			}

			update_option( self::OPTION_LICENSE_KEY, $key );
			update_option( self::OPTION_PLAN, $plan );
			update_option( self::OPTION_EXPIRY, $expiry );

			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s = plan label */
					__( 'License activated! Your plan is now %s.', 'certificate-generator' ),
					ucfirst( $plan )
				),
				'plan'    => $plan,
				'expiry'  => $expiry,
			);
		}

		// 2. Local-only fallback: derive plan from key prefix (dev / offline).
		$is_lifetime = str_ends_with( $key, '-LIFETIME' ) || str_ends_with( $key, '-LOCAL' );

		if ( str_starts_with( $key, 'BIZ-' ) ) {
			$plan   = self::PLAN_BUSINESS;
			$expiry = $is_lifetime ? '2099-12-31' : gmdate( 'Y-m-d', strtotime( '+1 year' ) );
		} elseif ( str_starts_with( $key, 'PRO-' ) ) {
			$plan   = self::PLAN_PRO;
			$expiry = $is_lifetime ? '2099-12-31' : gmdate( 'Y-m-d', strtotime( '+1 year' ) );
		} else {
			return array(
				'success' => false,
				'message' => __( 'Invalid license key. Keys must start with PRO- or BIZ-.', 'certificate-generator' ),
			);
		}

		update_option( self::OPTION_LICENSE_KEY, $key );
		update_option( self::OPTION_PLAN, $plan );
		update_option( self::OPTION_EXPIRY, $expiry );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s = plan label */
				__( 'License activated! Your plan is now %s.', 'certificate-generator' ),
				ucfirst( $plan )
			),
			'plan'    => $plan,
			'expiry'  => $expiry,
		);
	}

	/**
	 * Deactivate license: notify portfolio backend then clear local state.
	 * Called from the admin License tab (user-initiated).
	 */
	public static function deactivate_license(): void {
		$key = self::get_license_key();
		if ( $key && self::get_license_server() ) {
			require_once CERTIFICATE_GENERATOR_PATH . 'includes/Payment/backend-api.php';
			CG_Backend_API::instance()->deactivate_on_server( $key, home_url() );
		}
		delete_option( self::OPTION_LICENSE_KEY );
		delete_option( self::OPTION_EXPIRY );
		update_option( self::OPTION_PLAN, self::PLAN_FREE );
		delete_option( 'cg_last_heartbeat' );
	}

	/**
	 * Heartbeat: re-validate stored key against the portfolio backend.
	 * Idempotent — safe to call from cron or admin_init.
	 * Downgrades to Free if server reports invalid / expired / revoked.
	 * Skips silently when no key or no server is configured.
	 */
	public static function heartbeat(): void {
		$key = self::get_license_key();
		if ( empty( $key ) || empty( self::get_license_server() ) ) {
			return;
		}

		$result = self::remote_validate( $key );

		if ( is_wp_error( $result ) ) {
			set_transient( 'cg_heartbeat_error', $result->get_error_message(), DAY_IN_SECONDS );
			return;
		}

		$status = $result['status'] ?? ( empty( $result['valid'] ) ? 'invalid' : 'active' );

		if ( empty( $result['valid'] ) || in_array( $status, array( 'invalid', 'expired', 'revoked' ), true ) ) {
			delete_option( self::OPTION_LICENSE_KEY );
			delete_option( self::OPTION_EXPIRY );
			update_option( self::OPTION_PLAN, self::PLAN_FREE );
		} else {
			if ( ! empty( $result['plan'] ) ) {
				update_option( self::OPTION_PLAN, sanitize_text_field( $result['plan'] ) );
			}
			if ( ! empty( $result['expiry'] ) ) {
				update_option( self::OPTION_EXPIRY, sanitize_text_field( $result['expiry'] ) );
			}
		}

		update_option( 'cg_last_heartbeat', gmdate( 'Y-m-d H:i:s' ) );
		delete_transient( 'cg_heartbeat_error' );
	}

	// -----------------------------------------------------------------------
	// Usage tracking
	// -----------------------------------------------------------------------

	/**
	 * Returns the current month's certificate generation count.
	 */
	public static function get_usage(): int {
		self::maybe_reset_usage();
		return (int) get_option( self::OPTION_USAGE, 0 );
	}

	/**
	 * Returns the monthly limit for the current plan (0 = unlimited).
	 */
	public static function get_limit(): int {
		return self::LIMITS[ self::get_plan() ] ?? 100;
	}

	/**
	 * Returns true when the site has used up its monthly allowance.
	 */
	public static function is_limit_reached(): bool {
		$limit = self::get_limit();
		if ( $limit === 0 ) {
			return false; // unlimited
		}
		return self::get_usage() >= $limit;
	}

	/**
	 * Increment usage counter by 1.
	 * Fires a throttled fire-and-forget report to the portfolio backend (at most once per 30 min).
	 */
	public static function increment_usage(): void {
		self::maybe_reset_usage();
		$current = (int) get_option( self::OPTION_USAGE, 0 );
		$new     = $current + 1;
		update_option( self::OPTION_USAGE, $new );

		if ( ! get_transient( 'cg_usage_report_lock' ) ) {
			set_transient( 'cg_usage_report_lock', 1, 30 * MINUTE_IN_SECONDS );
			$key = self::get_license_key();
			if ( $key && self::get_license_server() ) {
				require_once CERTIFICATE_GENERATOR_PATH . 'includes/Payment/backend-api.php';
				CG_Backend_API::instance()->report_usage( $key, $new );
			}
		}
	}

	/**
	 * Reset usage to zero and record the current month.
	 * Called by WP Cron on the 1st of each month.
	 */
	public static function reset_monthly_usage(): void {
		update_option( self::OPTION_USAGE, 0 );
		update_option( self::OPTION_USAGE_MONTH, gmdate( 'Y-m' ) );
	}

	/**
	 * If the stored month doesn't match the current month, auto-reset.
	 */
	private static function maybe_reset_usage(): void {
		$stored_month  = (string) get_option( self::OPTION_USAGE_MONTH, '' );
		$current_month = gmdate( 'Y-m' );
		if ( $stored_month !== $current_month ) {
			self::reset_monthly_usage();
		}
	}

	// -----------------------------------------------------------------------
	// Plan feature matrix
	// -----------------------------------------------------------------------

	/**
	 * Returns an array describing what each plan includes.
	 * Used by the License tab to render the comparison table.
	 */
	public static function get_plan_features(): array {
		return array(
			'basic_pdf'          => array(
				'label'    => __( 'Basic PDF Generation', 'certificate-generator' ),
				'free'     => true,
				'pro'      => true,
				'business' => true,
			),
			'manual_issue'       => array(
				'label'    => __( 'Manual Certificate Issue', 'certificate-generator' ),
				'free'     => true,
				'pro'      => true,
				'business' => true,
			),
			'search_shortcode'   => array(
				'label'    => __( 'Search Shortcode', 'certificate-generator' ),
				'free'     => true,
				'pro'      => true,
				'business' => true,
			),
			'bulk_import'        => array(
				'label'    => __( 'CSV Bulk Import', 'certificate-generator' ),
				'free'     => false,
				'pro'      => true,
				'business' => true,
			),
			'bulk_zip'           => array(
				'label'    => __( 'Bulk ZIP Download', 'certificate-generator' ),
				'free'     => false,
				'pro'      => true,
				'business' => true,
			),
			'email_templates'    => array(
				'label'    => __( 'Email Templates', 'certificate-generator' ),
				'free'     => false,
				'pro'      => true,
				'business' => true,
			),
			'api_access'         => array(
				'label'    => __( 'REST API Access', 'certificate-generator' ),
				'free'     => false,
				'pro'      => true,
				'business' => true,
			),
			'smtp_tools'         => array(
				'label'    => __( 'SMTP Diagnostics', 'certificate-generator' ),
				'free'     => false,
				'pro'      => true,
				'business' => true,
			),
			'priority_support'   => array(
				'label'    => __( 'Priority Support', 'certificate-generator' ),
				'free'     => false,
				'pro'      => true,
				'business' => true,
			),
			'full_font_library'  => array(
				'label'    => __( '19,000+ Font Library', 'certificate-generator' ),
				'free'     => false,
				'pro'      => false,
				'business' => true,
			),
			'unlimited_api'      => array(
				'label'    => __( 'Unlimited API Calls', 'certificate-generator' ),
				'free'     => false,
				'pro'      => false,
				'business' => true,
			),
			'multisite'          => array(
				'label'    => __( 'Multisite Support', 'certificate-generator' ),
				'free'     => false,
				'pro'      => false,
				'business' => true,
			),
			'remove_branding'    => array(
				'label'    => __( 'Remove Plugin Branding', 'certificate-generator' ),
				'free'     => false,
				'pro'      => false,
				'business' => true,
			),
			'commercial_license' => array(
				'label'    => __( 'Commercial / Resale License', 'certificate-generator' ),
				'free'     => false,
				'pro'      => false,
				'business' => true,
			),
		);
	}

	// -----------------------------------------------------------------------
	// Cron
	// -----------------------------------------------------------------------

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$next_month = mktime( 0, 0, 0, (int) gmdate( 'n' ) + 1, 1, (int) gmdate( 'Y' ) );
			wp_schedule_event( $next_month, 'monthly', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::CRON_HEARTBEAT ) ) {
			wp_schedule_event( time(), 'twicedaily', self::CRON_HEARTBEAT );
		}
	}

	public static function unschedule_cron(): void {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
		$ts = wp_next_scheduled( self::CRON_HEARTBEAT );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HEARTBEAT );
		}
	}
}

// Hook cron reset
add_action( CG_License_Manager::CRON_HOOK, array( 'CG_License_Manager', 'reset_monthly_usage' ) );
add_action( CG_License_Manager::CRON_HEARTBEAT, array( 'CG_License_Manager', 'heartbeat' ) );

// Fallback for dormant sites where WP-Cron may not fire: run heartbeat once per 12 h on admin_init.
add_action(
	'admin_init',
	static function (): void {
		if ( get_transient( 'cg_heartbeat_lock' ) ) {
			return;
		}
		set_transient( 'cg_heartbeat_lock', 1, 12 * HOUR_IN_SECONDS );
		CG_License_Manager::heartbeat();
	}
);

// ── Multisite: show network-admin notice if activated network-wide without Business plan ──
add_action( 'network_admin_notices', 'cg_multisite_plan_notice' );
add_action( 'admin_notices', 'cg_multisite_plan_notice' );
function cg_multisite_plan_notice(): void {
	if ( ! is_multisite() ) {
		return;
	}
	// Only warn when the plugin is network-activated
	if ( ! is_plugin_active_for_network( 'Certificate-Generator-v7/certificate-generator.php' ) ) {
		return;
	}
	if ( CG_License_Manager::is_business() ) {
		return; // Business plan — multisite is allowed
	}
	$upgrade_url = admin_url( 'options-general.php?page=certificate_generator_settings&tab=license' );
	echo '<div class="notice notice-warning is-dismissible">'
		. '<p><strong>Certificate Generator:</strong> '
		. esc_html__( 'Multisite (network) support requires the Business plan. Usage is currently tracked per-site independently. ', 'certificate-generator' )
		. '<a href="' . esc_url( $upgrade_url ) . '">'
		. esc_html__( 'Upgrade to Business →', 'certificate-generator' )
		. '</a></p></div>';
}

// Register monthly cron interval if not already defined
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once a Month', 'certificate-generator' ),
			);
		}
		return $schedules;
	}
);
