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

    const OPTION_PLAN          = 'cg_plan';
    const OPTION_LICENSE_KEY   = 'cg_license_key';
    const OPTION_EXPIRY        = 'cg_license_expiry';
    const OPTION_USAGE         = 'cg_monthly_usage';
    const OPTION_USAGE_MONTH   = 'cg_usage_month';
    const CRON_HOOK            = 'cg_reset_monthly_usage';

    const PLAN_FREE     = 'free';
    const PLAN_PRO      = 'pro';
    const PLAN_BUSINESS = 'business';

    /**
     * Per-plan monthly certificate limits.
     * 0 = unlimited.
     */
    const LIMITS = [
        self::PLAN_FREE     => 100,
        self::PLAN_PRO      => 1000,
        self::PLAN_BUSINESS => 0,
    ];

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
        $plan = (string) get_option( self::OPTION_PLAN, self::PLAN_FREE );
        $valid = [ self::PLAN_FREE, self::PLAN_PRO, self::PLAN_BUSINESS ];
        return in_array( $plan, $valid, true ) ? $plan : self::PLAN_FREE;
    }

    public static function is_pro(): bool {
        return in_array( self::get_plan(), [ self::PLAN_PRO, self::PLAN_BUSINESS ], true );
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
        $labels = [
            self::PLAN_FREE     => __( 'Free', 'certificate-generator' ),
            self::PLAN_PRO      => __( 'Pro', 'certificate-generator' ),
            self::PLAN_BUSINESS => __( 'Business', 'certificate-generator' ),
        ];
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
    public static function activate_license( string $key ): array {
        $key = sanitize_text_field( $key );

        if ( empty( $key ) ) {
            return [ 'success' => false, 'message' => __( 'License key cannot be empty.', 'certificate-generator' ) ];
        }

        // --- Future: replace this block with a remote API call ---
        // $response = wp_remote_post( 'https://yoursite.com/wp-json/cg-licenses/v1/activate', [
        //     'body' => [ 'license_key' => $key, 'site_url' => home_url() ],
        //     'timeout' => 15,
        // ] );
        // $body = json_decode( wp_remote_retrieve_body( $response ), true );
        // if ( empty( $body['plan'] ) ) {
        //     return [ 'success' => false, 'message' => $body['message'] ?? 'Invalid response from license server.' ];
        // }
        // $plan   = sanitize_text_field( $body['plan'] );
        // $expiry = sanitize_text_field( $body['expiry'] ?? '' );
        // --- End future block ---

        // Stub: derive plan from key prefix for local testing.
        // Keys ending in -LIFETIME (e.g. BIZ-DEV-LIFETIME) get a 2099 expiry.
        $is_lifetime = str_ends_with( $key, '-LIFETIME' ) || str_ends_with( $key, '-LOCAL' );

        if ( str_starts_with( $key, 'BIZ-' ) ) {
            $plan   = self::PLAN_BUSINESS;
            $expiry = $is_lifetime ? '2099-12-31' : gmdate( 'Y-m-d', strtotime( '+1 year' ) );
        } elseif ( str_starts_with( $key, 'PRO-' ) ) {
            $plan   = self::PLAN_PRO;
            $expiry = $is_lifetime ? '2099-12-31' : gmdate( 'Y-m-d', strtotime( '+1 year' ) );
        } else {
            return [ 'success' => false, 'message' => __( 'Invalid license key. Keys must start with PRO- or BIZ-.', 'certificate-generator' ) ];
        }

        update_option( self::OPTION_LICENSE_KEY, $key );
        update_option( self::OPTION_PLAN, $plan );
        update_option( self::OPTION_EXPIRY, $expiry );

        return [
            'success' => true,
            'message' => sprintf(
                /* translators: %s = plan label */
                __( 'License activated! Your plan is now %s.', 'certificate-generator' ),
                ucfirst( $plan )
            ),
            'plan'   => $plan,
            'expiry' => $expiry,
        ];
    }

    /**
     * Deactivate / remove the license key and revert to Free.
     */
    public static function deactivate_license(): void {
        delete_option( self::OPTION_LICENSE_KEY );
        delete_option( self::OPTION_EXPIRY );
        update_option( self::OPTION_PLAN, self::PLAN_FREE );
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
     */
    public static function increment_usage(): void {
        self::maybe_reset_usage();
        $current = (int) get_option( self::OPTION_USAGE, 0 );
        update_option( self::OPTION_USAGE, $current + 1 );
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
        $stored_month = (string) get_option( self::OPTION_USAGE_MONTH, '' );
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
        return [
            'basic_pdf'          => [ 'label' => __( 'Basic PDF Generation', 'certificate-generator' ),      'free' => true,  'pro' => true,  'business' => true  ],
            'manual_issue'       => [ 'label' => __( 'Manual Certificate Issue', 'certificate-generator' ),  'free' => true,  'pro' => true,  'business' => true  ],
            'search_shortcode'   => [ 'label' => __( 'Search Shortcode', 'certificate-generator' ),          'free' => true,  'pro' => true,  'business' => true  ],
            'bulk_import'        => [ 'label' => __( 'CSV Bulk Import', 'certificate-generator' ),           'free' => false, 'pro' => true,  'business' => true  ],
            'bulk_zip'           => [ 'label' => __( 'Bulk ZIP Download', 'certificate-generator' ),         'free' => false, 'pro' => true,  'business' => true  ],
            'email_templates'    => [ 'label' => __( 'Email Templates', 'certificate-generator' ),           'free' => false, 'pro' => true,  'business' => true  ],
            'api_access'         => [ 'label' => __( 'REST API Access', 'certificate-generator' ),           'free' => false, 'pro' => true,  'business' => true  ],
            'smtp_tools'         => [ 'label' => __( 'SMTP Diagnostics', 'certificate-generator' ),         'free' => false, 'pro' => true,  'business' => true  ],
            'priority_support'   => [ 'label' => __( 'Priority Support', 'certificate-generator' ),          'free' => false, 'pro' => true,  'business' => true  ],
            'full_font_library'  => [ 'label' => __( '19,000+ Font Library', 'certificate-generator' ),     'free' => false, 'pro' => false, 'business' => true  ],
            'unlimited_api'      => [ 'label' => __( 'Unlimited API Calls', 'certificate-generator' ),       'free' => false, 'pro' => false, 'business' => true  ],
            'multisite'          => [ 'label' => __( 'Multisite Support', 'certificate-generator' ),         'free' => false, 'pro' => false, 'business' => true  ],
            'remove_branding'    => [ 'label' => __( 'Remove Plugin Branding', 'certificate-generator' ),    'free' => false, 'pro' => false, 'business' => true  ],
            'commercial_license' => [ 'label' => __( 'Commercial / Resale License', 'certificate-generator' ), 'free' => false, 'pro' => false, 'business' => true ],
        ];
    }

    // -----------------------------------------------------------------------
    // Cron
    // -----------------------------------------------------------------------

    public static function schedule_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            // Schedule for the 1st of next month at 00:00 UTC
            $next_month = mktime( 0, 0, 0, (int) gmdate( 'n' ) + 1, 1, (int) gmdate( 'Y' ) );
            wp_schedule_event( $next_month, 'monthly', self::CRON_HOOK );
        }
    }

    public static function unschedule_cron(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}

// Hook cron reset
add_action( CG_License_Manager::CRON_HOOK, [ 'CG_License_Manager', 'reset_monthly_usage' ] );

// ── Multisite: show network-admin notice if activated network-wide without Business plan ──
add_action( 'network_admin_notices', 'cg_multisite_plan_notice' );
add_action( 'admin_notices',         'cg_multisite_plan_notice' );
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
add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['monthly'] ) ) {
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => __( 'Once a Month', 'certificate-generator' ),
        ];
    }
    return $schedules;
} );
