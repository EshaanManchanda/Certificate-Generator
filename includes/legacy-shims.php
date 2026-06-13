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
// Populated in Phase 1. Until then, the functions defined in
// includes/Services/certificate-search.php remain the live implementations.
// This file is loaded AFTER certificate-search.php via optional_files, so these
// overrides will take precedence once un-commented.

/*
 * @deprecated v8 Use \CertificateGenerator\Services\PdfGenerator::create() instead.
 * Uncomment block when CG_USE_NEW_PDF is ready (Phase 1).
 *
if ( \CertificateGenerator\Core\Config::flag( 'CG_USE_NEW_PDF' )
    && ! function_exists( '_cg_pdf_shim_active' ) ) {

    function _cg_pdf_shim_active() {} // sentinel

    function generate_certificate_pdf( $post_id, $fields = array(), $student_data = null ) {
        return \CertificateGenerator\Services\PdfGenerator::create(
            \CertificateGenerator\Services\PdfGenerator::resolve( $post_id, $fields, $student_data )
        );
    }

    function generate_certificate_pdf_with_data( $post_data ) {
        return \CertificateGenerator\Services\PdfGenerator::createFromArray( $post_data );
    }

    function generate_certificate_pdf_email( $post_id, $fields, $email_options = null ) {
        return generate_certificate_pdf( $post_id, $fields ); // same path in v8
    }
}
*/

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

// ── Placeholder (remove this comment when first shim block is un-commented) ──
// This file intentionally contains only comments until Phase 1 ships.
