<?php
declare(strict_types=1);

namespace CertificateGenerator\Cron;

/**
 * Registers and manages all scheduled cron jobs for the plugin.
 */
class Scheduler {

	private array $schedules = array();

	public function __construct( array $schedules = array() ) {
		$this->schedules = $schedules;
	}

	public function register(): void {
		foreach ( $this->schedules as $schedule ) {
			if ( ! wp_next_scheduled( $schedule['hook'] ) ) {
				wp_schedule_event( time(), $schedule['interval'], $schedule['hook'] );
			}
		}
	}

	public function deregister(): void {
		foreach ( $this->schedules as $schedule ) {
			wp_clear_scheduled_hook( $schedule['hook'] );
		}
	}
}
