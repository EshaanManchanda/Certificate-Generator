<?php
/**
 * Legacy Shims — Certificate Generator v8
 *
 * This file is the ANTI-CORRUPTION BOUNDARY for v8.
 *
 *   ✅ Allowed: thin one-line wrappers that delegate to the new src/ service layer.
 *   ❌ Forbidden: new features, new business logic, new DB queries.
 *
 * These functions are @deprecated v8.
 * They survive the entire v8 release line to keep ~17 call sites untouched.
 * They will be DELETED in v9 once all callers have been updated.
 *
 * When a phase flag is flipped ON, the shim routes to the new service.
 * When a flag is OFF (or the flag constant is not defined), the shim calls the
 * legacy function that still lives in includes/Services/certificate-search.php or
 * includes/Email/functions.php. This means ZERO behavior change until the flag flips.
 *
 * See docs/compatibility.md for the full old→new mapping table.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Phase 1: PDF shims ────────────────────────────────────────────────────────
// When CG_USE_NEW_PDF=true, certificate-search.php skips defining the public
// wrappers, and this block provides them — routing through PdfGenerator.
// When CG_USE_NEW_PDF=false (default), certificate-search.php provides the
// functions directly and this block is skipped.
//
// generate_certificate_pdf_email is NOT overridden here — it already calls
// generate_certificate_pdf() which is shimmed below, so it inherits the new path.

if ( class_exists( '\CertificateGenerator\Core\Config' )
	&& \CertificateGenerator\Core\Config::flag( 'CG_USE_NEW_PDF' )
	&& ! function_exists( 'generate_certificate_pdf' ) ) {

	/**
	 * @deprecated v8 Use \CertificateGenerator\Services\PdfGenerator::make() instead.
	 */
	function generate_certificate_pdf( $post_id, $fields = array(), $student_data = null ) {
		return \CertificateGenerator\Services\PdfGenerator::make(
			(int) $post_id,
			(array) $fields,
			is_array( $student_data ) ? $student_data : null
		);
	}

	/**
	 * @deprecated v8 Use \CertificateGenerator\Services\PdfGenerator::makeFromArray() instead.
	 */
	function generate_certificate_pdf_with_data( $post_data ) {
		return \CertificateGenerator\Services\PdfGenerator::makeFromArray( (array) $post_data );
	}
}

// ── Phase 2: ZIP shims ────────────────────────────────────────────────────────
/*
 * @deprecated v8 Use \CertificateGenerator\Services\ZipService instead.
 * Uncomment block when CG_USE_NEW_ZIP is ready (Phase 2).
 *
if ( \CertificateGenerator\Core\Config::flag( 'CG_USE_NEW_ZIP' )
    && ! function_exists( '_cg_zip_shim_active' ) ) {

    function _cg_zip_shim_active() {}

    function cg_build_certificate_zip( $cg_id ) {
        return \CertificateGenerator\Services\ZipService::forEmail( $cg_id );
    }
}
*/

// ── Phase 4: Email shims ─────────────────────────────────────────────────────
// certificate_generator_send_email() is already delegated from
// src/Services/EmailService::sendById() (Phase-1 bug-fix). Once CG_USE_EVENTS
// is flipped in Phase 4, the send funnel fires do_action('cg_email_sent').
// No shim override needed here — EmailService is the shim.

// ── Phase 1 complete — PDF shims active above ─────────────────────────────────
// Phases 2–4 shims will be added below as each phase completes.
