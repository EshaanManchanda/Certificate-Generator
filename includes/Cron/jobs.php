<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CG_Cron_Jobs {
	public static function init() {
		add_action( 'cg_cleanup_qr_codes', array( __CLASS__, 'cleanup_qr_codes' ) );
		add_action( 'cg_check_expiring_certificates', array( __CLASS__, 'check_expiring_certificates' ) );
		add_action( 'cg_cleanup_old_certificates', array( __CLASS__, 'cleanup_old_certificates' ) );
		add_action( 'cg_publish_scheduled_templates', array( __CLASS__, 'publish_scheduled_templates' ) );

		if ( ! wp_next_scheduled( 'cg_cleanup_qr_codes' ) ) {
			wp_schedule_event( time(), 'daily', 'cg_cleanup_qr_codes' );
		}

		if ( ! wp_next_scheduled( 'cg_check_expiring_certificates' ) ) {
			wp_schedule_event( time(), 'daily', 'cg_check_expiring_certificates' );
		}

		if ( ! wp_next_scheduled( 'cg_cleanup_old_certificates' ) ) {
			wp_schedule_event( time(), 'weekly', 'cg_cleanup_old_certificates' );
		}

		if ( ! wp_next_scheduled( 'cg_publish_scheduled_templates' ) ) {
			wp_schedule_event( time(), 'hourly', 'cg_publish_scheduled_templates' );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'cg_cleanup_qr_codes' );
		wp_clear_scheduled_hook( 'cg_check_expiring_certificates' );
		wp_clear_scheduled_hook( 'cg_cleanup_old_certificates' );
		wp_clear_scheduled_hook( 'cg_publish_scheduled_templates' );
	}

	public static function cleanup_qr_codes() {
		$upload_dir = wp_upload_dir();
		$qr_dir     = $upload_dir['basedir'] . '/cg-qr-codes/';

		if ( ! file_exists( $qr_dir ) ) {
			return;
		}

		$files = glob( $qr_dir . '*.png' );
		if ( ! $files ) {
			return;
		}

		$deleted = 0;
		$cutoff  = time() - ( 7 * DAY_IN_SECONDS );

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				if ( wp_delete_file( $file ) ) {
					++$deleted;
				}
			}
		}

		if ( $deleted > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[CG Cron] Cleaned up $deleted old QR code files" );
		}
	}

	public static function check_expiring_certificates() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'certificate_generator';

		$expiring_soon = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name 
             WHERE expires_at IS NOT NULL 
             AND expires_at BETWEEN %s AND DATE_ADD(%s, INTERVAL 7 DAY)
             AND expires_at > %s",
				current_time( 'mysql' ),
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);

		if ( empty( $expiring_soon ) ) {
			return;
		}

		$notification_email = get_option( 'cg_expiration_notification_email', get_option( 'admin_email' ) );
		$send_notifications = get_option( 'cg_send_expiration_notifications', false );

		if ( ! $send_notifications ) {
			return;
		}

		$subject = 'Certificates Expiring Within 7 Days';
		$message = "The following certificates are expiring within the next 7 days:\n\n";

		foreach ( $expiring_soon as $cert ) {
			$message .= "- {$cert->student_name} (Serial: {$cert->serial_number}) - Expires: {$cert->expires_at}\n";
		}

		$message .= "\n\nTotal: " . count( $expiring_soon ) . " certificates\n";
		$message .= "\nView full report: " . admin_url( 'admin.php?page=cg-analytics' );

		wp_mail( $notification_email, $subject, $message );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[CG Cron] Sent expiration notification for ' . count( $expiring_soon ) . ' certificates' );
		}
	}

	public static function cleanup_old_certificates() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'certificate_generator';

		$archive_after_years = (int) get_option( 'cg_archive_after_years', 5 );
		if ( $archive_after_years <= 0 ) {
			return;
		}

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$archive_after_years} years" ) );

		$expired_old = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, student_name, serial_number, expires_at FROM $table_name 
             WHERE expires_at IS NOT NULL 
             AND expires_at < %s 
             AND created_at < %s",
				$cutoff_date,
				$cutoff_date
			)
		);

		if ( empty( $expired_old ) ) {
			return;
		}

		$archive_table  = $table_name . '_archive';
		$archive_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $archive_table ) ) === $archive_table;

		if ( ! $archive_exists ) {
			$charset_collate = $wpdb->get_charset_collate();
			$wpdb->query( "CREATE TABLE $archive_table LIKE $table_name" );
		}

		$archived = 0;
		foreach ( $expired_old as $cert ) {
			$wpdb->insert( $archive_table, (array) $cert );
			$wpdb->delete( $table_name, array( 'id' => $cert->id ) );
			++$archived;
		}

		if ( $archived > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[CG Cron] Archived $archived old expired certificates" );
		}
	}

	public static function publish_scheduled_templates() {
		if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
			return;
		}

		$tables    = \CertificateGenerator\Database\CustomTables::instance();
		$tpl_table = $tables->get_table( 'certificate_templates' );

		$table_exists = $GLOBALS['wpdb']->get_var(
			$GLOBALS['wpdb']->prepare( 'SHOW TABLES LIKE %s', $tpl_table )
		) === $tpl_table;

		if ( ! $table_exists ) {
			return;
		}

		$today    = current_time( 'Y-m-d' );
		$updated  = $GLOBALS['wpdb']->query(
			$GLOBALS['wpdb']->prepare(
				"UPDATE $tpl_table
				    SET status = 'published', updated_at = NOW()
				  WHERE status = 'scheduled'
				    AND event_date IS NOT NULL
				    AND event_date != '0000-00-00'
				    AND event_date <= %s",
				$today
			)
		);

		if ( $updated && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "[CG Cron] Auto-published $updated scheduled certificate template(s)" );
		}
	}
}
