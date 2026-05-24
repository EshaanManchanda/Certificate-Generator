<?php
declare(strict_types=1);

namespace CertificateGenerator\Database\Migrations;

/**
 * Manages and runs database migrations in order.
 */
class MigrationRunner {

	private string $option_key      = 'cg_db_version';
	private string $current_version = '001';
	private array $migrations       = array();

	public function __construct() {
		$this->migrations = array(
			'001' => new Migration001_AddTimeColumns(),
		);
	}

	public function run(): void {
		$installed_version = get_option( $this->option_key, '000' );

		foreach ( $this->migrations as $version => $migration ) {
			if ( version_compare( $version, $installed_version, '>' ) ) {
				$migration->up();
				update_option( $this->option_key, $version );
			}
		}
	}

	public function rollback( string $target_version = '000' ): void {
		$installed_version = get_option( $this->option_key, '000' );

		$versions = array_reverse( array_keys( $this->migrations ) );
		foreach ( $versions as $version ) {
			if ( version_compare( $version, $installed_version, '<=' ) && version_compare( $version, $target_version, '>' ) ) {
				$this->migrations[ $version ]->down();
				update_option( $this->option_key, $target_version );
			}
		}
	}

	public function get_current_version(): string {
		return get_option( $this->option_key, '000' );
	}

	public function get_target_version(): string {
		return $this->current_version;
	}

	public function needs_migration(): bool {
		return version_compare( $this->get_current_version(), $this->current_version, '<' );
	}
}
