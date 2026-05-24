<?php
/**
 * Security Helper Class
 *
 * Provides security utilities and hardening measures for the Certificate Generator plugin
 *
 * @package Certificate Generator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Certificate Generator Security Helper
 */
class CertificateGenerator_SecurityHelper {

	/**
	 * Rate limiting data
	 */
	private static $rate_limits = array();

	/**
	 * Initialize security measures
	 */
	public static function init() {
		// Add security headers
		add_action( 'admin_head', array( __CLASS__, 'add_security_headers' ) );

		// Sanitize admin inputs
		add_action( 'admin_init', array( __CLASS__, 'sanitize_admin_inputs' ) );

		// Add file upload security
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'secure_file_uploads' ) );
	}

	/**
	 * Add security headers for admin pages
	 */
	public static function add_security_headers() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only add headers for our plugin pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'certificate' ) === false ) {
			return;
		}

		// Content Security Policy for admin pages
		header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';" );

		// Prevent MIME type sniffing
		header( 'X-Content-Type-Options: nosniff' );

		// XSS Protection
		header( 'X-XSS-Protection: 1; mode=block' );

		// Frame options
		header( 'X-Frame-Options: SAMEORIGIN' );
	}

	/**
	 * Validate and sanitize all admin inputs
	 */
	public static function sanitize_admin_inputs() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Sanitize POST data
		if ( $_POST ) {
			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'certificate_' ) === 0 ) {
					$_POST[ $key ] = self::deep_sanitize( $value );
				}
			}
		}

		// Sanitize GET data
		if ( $_GET ) {
			foreach ( $_GET as $key => $value ) {
				if ( strpos( $key, 'certificate_' ) === 0 ) {
					$_GET[ $key ] = self::deep_sanitize( $value );
				}
			}
		}
	}

	/**
	 * Deep sanitization for nested arrays
	 */
	private static function deep_sanitize( $data ) {
		if ( is_array( $data ) ) {
			return array_map( array( __CLASS__, 'deep_sanitize' ), $data );
		}

		if ( is_string( $data ) ) {
			// Remove null bytes and normalize line endings
			$data = str_replace( array( "\0", "\r\n", "\r" ), array( '', "\n", "\n" ), $data );

			// Basic sanitization
			return sanitize_text_field( $data );
		}

		return $data;
	}

	/**
	 * Secure file upload validation
	 */
	public static function secure_file_uploads( $file ) {
		// Only process if this is a certificate-related upload
		if ( ! isset( $_POST['action'] ) || strpos( $_POST['action'], 'certificate' ) === false ) {
			return $file;
		}

		// Check file size (max 10MB)
		if ( $file['size'] > 10 * 1024 * 1024 ) {
			$file['error'] = 'File size too large. Maximum 10MB allowed.';
			return $file;
		}

		// Validate file type
		$allowed_types  = array( 'csv', 'txt', 'pdf', 'jpg', 'jpeg', 'png' );
		$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_extension, $allowed_types ) ) {
			$file['error'] = 'Invalid file type. Only CSV, TXT, PDF, and image files are allowed.';
			return $file;
		}

		// Check for suspicious content
		if ( self::contains_suspicious_content( $file['tmp_name'] ) ) {
			$file['error'] = 'File contains suspicious content and was rejected.';
			return $file;
		}

		return $file;
	}

	/**
	 * Check for suspicious content in uploaded files
	 */
	private static function contains_suspicious_content( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return true;
		}

		// Read first 1KB of file to check for suspicious patterns
		$content = file_get_contents( $file_path, false, null, 0, 1024 );

		// Check for PHP tags, script tags, and other suspicious patterns
		$suspicious_patterns = array(
			'/<\?php/i',
			'/<script/i',
			'/eval\s*\(/i',
			'/exec\s*\(/i',
			'/system\s*\(/i',
			'/shell_exec/i',
			'/base64_decode/i',
		);

		foreach ( $suspicious_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Rate limiting for API endpoints
	 */
	public static function check_rate_limit( $action, $limit = 10, $window = 60 ) {
		$user_id    = get_current_user_id();
		$ip_address = self::get_client_ip();
		$key        = $action . '_' . $user_id . '_' . $ip_address;

		$current_time = time();

		// Clean old entries
		if ( isset( self::$rate_limits[ $key ] ) ) {
			self::$rate_limits[ $key ] = array_filter(
				self::$rate_limits[ $key ],
				function ( $timestamp ) use ( $current_time, $window ) {
					return ( $current_time - $timestamp ) < $window;
				}
			);
		} else {
			self::$rate_limits[ $key ] = array();
		}

		// Check if limit exceeded
		if ( count( self::$rate_limits[ $key ] ) >= $limit ) {
			return false;
		}

		// Add current request
		self::$rate_limits[ $key ][] = $current_time;

		return true;
	}

	/**
	 * Get client IP address safely
	 */
	private static function get_client_ip() {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = $_SERVER[ $header ];
				// Validate IP address
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1';
	}

	/**
	 * Validate admin capability for specific actions
	 */
	public static function validate_admin_capability( $action = 'manage_options' ) {
		if ( ! is_admin() || ! current_user_can( $action ) ) {
			wp_die( 'Unauthorized access attempt detected.', 'Security Error', array( 'response' => 403 ) );
		}

		// Additional nonce validation for POST requests
		if ( $_POST && ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', $action ) ) {
			wp_die( 'Security token mismatch. Please try again.', 'Security Error', array( 'response' => 403 ) );
		}

		return true;
	}

	/**
	 * Secure log function with sanitization
	 */
	public static function secure_log( $message, $level = 'INFO' ) {
		if ( ! is_string( $message ) ) {
			$message = print_r( $message, true );
		}

		// Sanitize log message
		$message = self::deep_sanitize( $message );

		// Limit log message length
		if ( strlen( $message ) > 1000 ) {
			$message = substr( $message, 0, 1000 ) . '... [truncated]';
		}

		$log_entry = sprintf(
			'[%s] Certificate Generator - %s: %s',
			date( 'Y-m-d H:i:s' ),
			$level,
			$message
		);

		error_log( $log_entry );
	}

	/**
	 * Generate secure random token
	 */
	public static function generate_secure_token( $length = 32 ) {
		if ( function_exists( 'random_bytes' ) ) {
			return bin2hex( random_bytes( $length / 2 ) );
		} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
			return bin2hex( openssl_random_pseudo_bytes( $length / 2 ) );
		} else {
			// Fallback for older PHP versions
			return substr( md5( uniqid( mt_rand(), true ) ), 0, $length );
		}
	}
}

// Initialize security helper
CertificateGenerator_SecurityHelper::init();
