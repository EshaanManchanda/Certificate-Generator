<?php
declare(strict_types=1);

namespace CertificateGenerator\Logging;

/**
 * Structured file logger for Certificate Generator.
 *
 * Writes to uploads/certificate-generator/logs/cg-YYYY-MM-DD.log.
 * Rotates by day; keeps the last 30 log files.
 *
 * Usage:
 *   Logger::info( 'Email sent', [ 'cg_id' => 42 ] );
 *   Logger::warning( 'Queue stale row reclaimed', [ 'id' => 7 ] );
 *   Logger::error( 'PDF generation failed', [ 'cg_id' => 9, 'error' => $e->getMessage() ] );
 *
 * Debug/profiling writes (only when their flag is enabled):
 *   Logger::debug( 'pdf_time', [ 'ms' => 320 ], 'CG_DEBUG_PDF_TIME' );
 *
 * Rule: do NOT log routine operations — only true errors and opt-in profiling.
 */
class Logger {

	private const MAX_LOG_FILES = 30;
	private const LOG_DIR_NAME  = 'certificate-generator/logs';

	// ── Public API ────────────────────────────────────────────────────────────

	public static function info( string $message, array $context = array() ): void {
		self::write( 'INFO', $message, $context );
	}

	public static function warning( string $message, array $context = array() ): void {
		self::write( 'WARNING', $message, $context );
	}

	public static function error( string $message, array $context = array() ): void {
		self::write( 'ERROR', $message, $context );
	}

	/**
	 * Debug/profiling entry — only written when the named flag constant is truthy.
	 *
	 * @param string $message  Short label (e.g. 'pdf_time').
	 * @param array  $context  Key/value pairs (e.g. ['ms' => 320]).
	 * @param string $flag     Flag constant name, e.g. 'CG_DEBUG_PDF_TIME'.
	 */
	public static function debug( string $message, array $context = array(), string $flag = 'CG_DEBUG_SERVICES' ): void {
		if ( ! defined( $flag ) || ! constant( $flag ) ) {
			return;
		}
		self::write( 'DEBUG', $message, $context, self::debugDir() );
	}

	// ── Internal ──────────────────────────────────────────────────────────────

	private static function write( string $level, string $message, array $context, string $dir = '' ): void {
		$dir = $dir ?: self::logDir();
		if ( ! $dir ) {
			return;
		}

		$file        = $dir . '/cg-' . gmdate( 'Y-m-d' ) . '.log';
		$context_str = $context ? ' ' . wp_json_encode( $context ) : '';
		$line        = sprintf(
			"[%s] [%s] %s%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			$level,
			$message,
			$context_str
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );

		self::rotate( $dir );
	}

	/**
	 * Returns the log directory path, creating it if needed.
	 * Returns empty string if the directory cannot be created or is not writable.
	 */
	private static function logDir(): string {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}
		$dir = trailingslashit( $upload['basedir'] ) . self::LOG_DIR_NAME;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return is_writable( $dir ) ? $dir : '';
	}

	/**
	 * Returns the debug/profiling directory (uploads/cg-debug/).
	 */
	private static function debugDir(): string {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'cg-debug';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return is_writable( $dir ) ? $dir : '';
	}

	/**
	 * Remove old log files beyond MAX_LOG_FILES, oldest first.
	 */
	private static function rotate( string $dir ): void {
		$files = glob( $dir . '/cg-*.log' );
		if ( ! $files || count( $files ) <= self::MAX_LOG_FILES ) {
			return;
		}
		sort( $files ); // oldest first (date-sorted filenames)
		$excess = array_slice( $files, 0, count( $files ) - self::MAX_LOG_FILES );
		foreach ( $excess as $old_file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $old_file );
		}
	}
}
