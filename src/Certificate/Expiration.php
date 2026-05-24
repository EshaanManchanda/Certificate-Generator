<?php
declare(strict_types=1);

namespace CertificateGenerator\Certificate;

/**
 * Calculates expiration dates based on certificate template settings.
 */
class Expiration {

	/**
	 * Calculate expiration date from issue date.
	 *
	 * @param string      $unit 'days', 'months', 'years', or 'never'
	 * @param int         $value Duration value
	 * @param string|null $issued_at MySQL datetime string (defaults to now)
	 * @return string|null MySQL datetime string or null if never expires
	 */
	public function calculate( string $unit, int $value, ?string $issued_at = null ): ?string {
		if ( $unit === 'never' || $value <= 0 ) {
			return null;
		}

		$date = new \DateTime( $issued_at ?: 'now' );

		switch ( $unit ) {
			case 'days':
				$date->modify( "+{$value} days" );
				break;
			case 'months':
				$date->modify( "+{$value} months" );
				break;
			case 'years':
				$date->modify( "+{$value} years" );
				break;
			default:
				return null;
		}

		return $date->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Check if a certificate has expired.
	 *
	 * @param string|null $expires_at MySQL datetime string
	 * @return bool
	 */
	public function is_expired( ?string $expires_at ): bool {
		if ( $expires_at === null ) {
			return false;
		}

		return strtotime( $expires_at ) < time();
	}

	/**
	 * Check if a certificate expires within the given number of days.
	 *
	 * @param string|null $expires_at MySQL datetime string
	 * @param int         $days Number of days to check
	 * @return bool
	 */
	public function expires_within( ?string $expires_at, int $days ): bool {
		if ( $expires_at === null ) {
			return false;
		}

		$expiry = strtotime( $expires_at );
		$cutoff = time() + ( $days * 86400 );

		return $expiry > time() && $expiry <= $cutoff;
	}
}
