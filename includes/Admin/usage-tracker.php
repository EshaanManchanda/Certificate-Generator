<?php
/**
 * Certificate Generator Usage Tracker
 *
 * Hooks into PDF generation to increment the monthly usage counter.
 * Also enforces the Free plan certificate limit.
 *
 * @package Certificate_Generator
 * @since   7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Called immediately after a certificate PDF is successfully generated.
 * Increments the monthly usage counter in CG_License_Manager.
 *
 * Hook: cg_certificate_generated
 *
 * @param int    $post_id   The WP post ID of the certificate record.
 * @param string $file_path Absolute path to the generated PDF file.
 */
add_action( 'cg_certificate_generated', 'cg_usage_tracker_on_generate', 10, 2 );
function cg_usage_tracker_on_generate( int $post_id, string $file_path ): void {
    CG_License_Manager::increment_usage();
}

/**
 * Check whether the current plan's monthly limit has been reached
 * before allowing certificate generation via AJAX (admin bulk actions).
 *
 * Returns a WP_Error-style JSON error if the limit is exceeded.
 */
add_action( 'wp_ajax_cg_check_usage_limit', 'cg_ajax_check_usage_limit' );
function cg_ajax_check_usage_limit(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'certificate-generator' ) ] );
    }

    if ( CG_License_Manager::is_limit_reached() ) {
        $limit = CG_License_Manager::get_limit();
        wp_send_json_error( [
            'message'     => sprintf(
                /* translators: %d = monthly limit */
                __( 'Monthly limit of %d certificates reached. Upgrade your plan to generate more.', 'certificate-generator' ),
                $limit
            ),
            'upgrade_url' => 'https://eshaanportfolio.vercel.app/',
            'limit'       => $limit,
            'usage'       => CG_License_Manager::get_usage(),
        ] );
    }

    wp_send_json_success( [
        'usage' => CG_License_Manager::get_usage(),
        'limit' => CG_License_Manager::get_limit(),
    ] );
}

/**
 * Attach usage-limit check to the existing generate_certificate_pdf() function.
 *
 * We use a filter added before the function call and optionally block execution.
 * Functions in student-certificate-search.php should fire this filter, but if they
 * don't yet, the tracker still increments via the cg_certificate_generated action
 * fired from admin-columns.php / search handlers.
 */
add_filter( 'cg_pre_generate_certificate', 'cg_usage_limit_gate', 10, 1 );
function cg_usage_limit_gate( $proceed ) {
    if ( CG_License_Manager::is_limit_reached() ) {
        return new WP_Error(
            'usage_limit_reached',
            sprintf(
                __( 'Monthly certificate limit of %d reached. Please upgrade your plan.', 'certificate-generator' ),
                CG_License_Manager::get_limit()
            )
        );
    }
    return $proceed;
}
