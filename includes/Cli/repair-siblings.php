<?php
/**
 * WP-CLI one-time repair for the sibling wrong-certificate / dropped-certificate bug.
 *
 * `wp cg repair-siblings --email=<addr>` finds every certificate record for an
 * email, reports the current state (serials, cached PDF paths, send-log block),
 * and — only with --confirm — clears stale caches and (with --force-resend)
 * re-triggers certificate_generator_send_email() so each sibling is regenerated
 * and redelivered under the Phase 1/2 identity fixes.
 *
 * Default is always dry-run. Nothing is written without --confirm.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The class declaration must stay INSIDE this if-block, not after an early
// return — PHP hoists unconditional top-level class declarations at compile
// time, before any runtime statement (including `return`) executes, so a
// bare `if (...) return;` guard does NOT actually prevent the class from
// being declared on every normal web request.
if ( defined( 'WP_CLI' ) && WP_CLI ) {

class CG_CLI_Repair_Siblings {

	/**
	 * Report and (optionally) repair sibling certificate records sharing one email.
	 *
	 * ## OPTIONS
	 *
	 * --email=<address>
	 * : The recipient email address to repair.
	 *
	 * [--confirm]
	 * : Actually write changes. Without this flag the command only reports.
	 *
	 * [--force-resend]
	 * : Clear the blocking "already sent" log entry and re-send the email.
	 * : Requires --confirm.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cg repair-siblings --email=parent@example.com
	 *     wp cg repair-siblings --email=parent@example.com --confirm --force-resend
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$email = isset( $assoc_args['email'] ) ? sanitize_email( $assoc_args['email'] ) : '';
		if ( empty( $email ) || ! is_email( $email ) ) {
			WP_CLI::error( 'Pass a valid --email=<address>.' );
			return;
		}

		$confirm      = ! empty( $assoc_args['confirm'] );
		$force_resend = ! empty( $assoc_args['force-resend'] );

		if ( $force_resend && ! $confirm ) {
			WP_CLI::error( '--force-resend requires --confirm.' );
			return;
		}

		if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			WP_CLI::error( 'CustomTables class not available — plugin not fully loaded.' );
			return;
		}

		WP_CLI::log( $confirm ? "Repairing certificates for: {$email}" : "DRY RUN — no writes will occur. Repairing certificates for: {$email}" );

		// ── 1. Find entity rows (SQL-first source of truth) ──────────────────
		$tables      = \CertificateGenerator\Database\CustomTables::instance();
		$entity_rows = array();
		$entity_type = '';
		foreach ( array( 'students', 'teachers', 'schools' ) as $ent ) {
			$tbl = $tables->get_table( $ent );
			if ( empty( $tbl ) || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
				continue;
			}
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM $tbl WHERE email = %s ORDER BY id ASC", $email ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
			if ( ! empty( $rows ) ) {
				$entity_rows = $rows;
				$entity_type = $ent;
				break;
			}
		}

		if ( empty( $entity_rows ) ) {
			WP_CLI::error( "No student/teacher/school records found for {$email}." );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d %s record(s):', count( $entity_rows ), $entity_type ) );

		// ── 2. Legacy anchor row (certificate_generator_send_email() needs its id) ──
		$cg_table   = $wpdb->prefix . 'certificate_generator';
		$legacy_row = null;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cg_table ) ) === $cg_table ) {
			$legacy_row = $wpdb->get_row(
				$wpdb->prepare( "SELECT id FROM $cg_table WHERE email = %s ORDER BY id ASC LIMIT 1", $email ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
		}
		$anchor_cg_id = $legacy_row ? (int) $legacy_row['id'] : 0;

		// ── 3. Per-row report ──────────────────────────────────────────────
		$cert_table    = $tables->get_table( 'certificates' );
		$has_cert_tbl  = $cert_table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cert_table ) ) === $cert_table;
		$stale_paths   = array();
		$report_lines  = array();
		$name_col      = $entity_type === 'teachers' ? 'teacher_name' : ( $entity_type === 'schools' ? 'school_name' : 'student_name' );

		foreach ( $entity_rows as $row ) {
			$row['entity_type'] = $entity_type;
			$post_id             = (int) ( $row['wp_post_id'] ?? 0 );
			$canonical            = function_exists( 'cg_canonical_pdf_basename' )
				? cg_canonical_pdf_basename( $post_id, $row )
				: '(helper unavailable)';

			$legacy_pdf_path = '';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cg_table ) ) === $cg_table ) {
				$legacy_pdf_path = (string) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT pdf_path FROM $cg_table WHERE student_name = %s AND certificate_type = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$row[ $name_col ] ?? '',
						$row['certificate_type'] ?? ''
					)
				);
			}

			$is_stale = $legacy_pdf_path && basename( $legacy_pdf_path ) !== $canonical;
			if ( $is_stale ) {
				$stale_paths[] = $legacy_pdf_path;
			}

			$report_lines[] = array(
				'id'               => $row['id'],
				'name'             => $row[ $name_col ] ?? '',
				'certificate_type' => $row['certificate_type'] ?? '',
				'serial_number'    => $row['serial_number'] ?? '',
				'canonical_pdf'    => $canonical,
				'cached_pdf_path'  => $legacy_pdf_path ?: '(none)',
				'stale'            => $is_stale ? 'YES — will regenerate' : 'no',
			);
		}

		WP_CLI\Utils\format_items( 'table', $report_lines, array( 'id', 'name', 'certificate_type', 'serial_number', 'canonical_pdf', 'cached_pdf_path', 'stale' ) );

		// Duplicate-serial detection — the exact symptom that caused the original complaint.
		$serials = array_filter( wp_list_pluck( $entity_rows, 'serial_number' ) );
		$dupes   = array_diff_assoc( $serials, array_unique( $serials ) );
		if ( ! empty( $dupes ) ) {
			WP_CLI::warning( 'Duplicate serial numbers detected across siblings: ' . implode( ', ', array_unique( $dupes ) ) . ' — these rows previously shared one identity.' );
		}

		// ── 4. Email-log block check ──────────────────────────────────────
		$log_table   = $wpdb->prefix . 'cert_email_logs';
		$has_log_tbl = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) ) === $log_table;
		$is_blocked  = false;
		if ( $has_log_tbl && $anchor_cg_id > 0 ) {
			$is_blocked = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $log_table WHERE certificate_id = %d AND recipient_email = %s AND status = 'sent'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$anchor_cg_id,
					$email
				)
			);
		}
		WP_CLI::log( sprintf(
			'Anchor cg_id: %s | Resend blocked by "already sent" log: %s',
			$anchor_cg_id ?: '(none — will be created)',
			$is_blocked ? 'YES' : 'no'
		) );

		if ( ! $confirm ) {
			WP_CLI::log( "\nDry run complete. Re-run with --confirm to apply, add --force-resend to redeliver." );
			return;
		}

		// ── 5. Apply: clear stale caches ────────────────────────────────────
		foreach ( $stale_paths as $path ) {
			$wpdb->update(
				$cg_table,
				array( 'pdf_path' => null ),
				array( 'pdf_path' => $path ),
				array( '%s' ),
				array( '%s' )
			);
			if ( $has_cert_tbl ) {
				$wpdb->update(
					$cert_table,
					array(
						'pdf_path' => null,
						'pdf_url'  => null,
					),
					array( 'pdf_path' => $path ),
					array( '%s', '%s' ),
					array( '%s' )
				);
			}
			WP_CLI::log( "Cleared stale cached path: {$path} (file left on disk; not auto-deleted)" );
		}

		if ( ! $force_resend ) {
			WP_CLI::success( 'Stale caches cleared. Re-run with --force-resend to redeliver the email.' );
			return;
		}

		// ── 6. Force resend: unblock + re-send ──────────────────────────────
		if ( $anchor_cg_id <= 0 ) {
			WP_CLI::error( 'No legacy wp_certificate_generator anchor row found for this email — cannot resend. Create/sync one first.' );
			return;
		}

		if ( $has_log_tbl ) {
			$wpdb->delete(
				$log_table,
				array(
					'certificate_id'  => $anchor_cg_id,
					'recipient_email' => $email,
					'status'          => 'sent',
				),
				array( '%d', '%s', '%s' )
			);
			WP_CLI::log( "Cleared blocking send-log entries for cg_id={$anchor_cg_id}." );
		}

		if ( ! function_exists( 'certificate_generator_send_email' ) ) {
			WP_CLI::error( 'certificate_generator_send_email() not available.' );
			return;
		}

		$sent = certificate_generator_send_email( $anchor_cg_id, true );

		if ( $sent ) {
			WP_CLI::success( "Resent {$email} — " . count( $entity_rows ) . ' certificate(s), each with its own identity.' );
		} else {
			WP_CLI::error( "Resend failed for {$email} — check error_log for '[CG Email]' entries." );
		}
	}
}

WP_CLI::add_command( 'cg repair-siblings', 'CG_CLI_Repair_Siblings' );

}
