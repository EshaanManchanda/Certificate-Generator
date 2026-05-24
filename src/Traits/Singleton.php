<?php
declare(strict_types=1);

namespace CertificateGenerator\Traits;

/**
 * Trait for singleton pattern.
 */
trait Singleton {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->boot();
	}

	private function __clone() {}

	public function __wakeup() {
		throw new \LogicException( 'Cannot unserialize singleton' );
	}

	protected function boot(): void {}
}
