<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

use CertificateGenerator\Database\CustomTables;
use CertificateGenerator\Database\CptToSqlMigration;

/**
 * Complete CPT → SQL Migration admin page.
 * One-click migration with verification and rollback options.
 */
class MigrationPage {

	private string $slug  = 'cg-sql-migration';
	private string $title = 'CPT → SQL Migration';

	public function register(): void {
		add_submenu_page(
			'cg-dashboard',
			$this->title,
			'SQL Migration',
			'manage_options',
			$this->slug,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'certificate-generator' ) );
		}

		$tables       = CustomTables::instance();
		$message      = '';
		$message_type = '';

		// Handle CPT → SQL migration
		if ( isset( $_POST['cg_run_cpt_to_sql'] ) && check_admin_referer( 'cg_run_cpt_to_sql' ) ) {
			$migrator     = new CptToSqlMigration();
			$stats        = $migrator->run();
			$message      = 'CPT → SQL migration completed!';
			$message_type = 'success';
			foreach ( $stats as $entity => $count ) {
				$message .= '<br>' . ucfirst( $entity ) . ': ' . number_format( $count ) . ' records migrated';
			}
		}

		// Handle rollback
		if ( isset( $_POST['cg_rollback_migration'] ) && check_admin_referer( 'cg_rollback_migration' ) ) {
			$this->rollback_migration( $tables );
			$message      = 'Rollback complete. SQL tables cleared. CPT data is intact. You can re-run migration at any time.';
			$message_type = 'warning';
		}

		// Handle table verification
		if ( isset( $_POST['cg_verify_tables'] ) && check_admin_referer( 'cg_verify_tables' ) ) {
			$tables->create_all();
			$message      = 'All tables verified/created successfully!';
			$message_type = 'success';
		}

		// Handle issue_date normalization (one-time fix for d-m-Y → Y-m-d)
		if ( isset( $_POST['cg_fix_issue_dates'] ) && check_admin_referer( 'cg_fix_issue_dates' ) ) {
			$fixed        = $this->fix_issue_dates();
			$message      = "Issue date normalization complete. <strong>{$fixed}</strong> records updated to Y-m-d storage format.";
			$message_type = $fixed > 0 ? 'success' : 'info';
		}

		// Handle CPT cleanup (after verification)
		if ( isset( $_POST['cg_cleanup_cpts'] ) && check_admin_referer( 'cg_cleanup_cpts' ) ) {
			$this->cleanup_cpts();
			$message      = 'CPT data cleaned up. Plugin now uses SQL tables exclusively.';
			$message_type = 'success';
		}

		// Get status
		$table_status = array();
		foreach ( array_keys( $tables->get_all_tables() ) as $name ) {
			$table_status[ $name ] = $tables->table_exists( $name );
		}
		$all_tables_exist = ! in_array( false, $table_status, true );

		global $wpdb;

		$migration_completed = get_option( 'cg_cpt_to_sql_migration_completed', false );
		$migration_stats     = get_option( 'cg_cpt_to_sql_migration_stats', array() );
		$cpts_cleaned        = get_option( 'cg_cpts_cleaned_up', false );

		// Count remaining legacy CPT posts directly from DB (CPTs are no longer registered).
		$legacy_cpt_types = array( 'students', 'teachers', 'schools', 'certificates' );
		$placeholders     = implode( ',', array_fill( 0, count( $legacy_cpt_types ), '%s' ) );
		$remaining_rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_type, COUNT(*) AS cnt FROM {$wpdb->posts} WHERE post_type IN ($placeholders) GROUP BY post_type",
				...$legacy_cpt_types
			)
		);
		$remaining_cpt_counts = array_fill_keys( $legacy_cpt_types, 0 );
		foreach ( $remaining_rows as $row ) {
			$remaining_cpt_counts[ $row->post_type ] = (int) $row->cnt;
		}
		$total_remaining_cpts = array_sum( $remaining_cpt_counts );

		// Reset the cleaned flag if CPT posts crept back.
		if ( $cpts_cleaned && $total_remaining_cpts > 0 ) {
			delete_option( 'cg_cpts_cleaned_up' );
			$cpts_cleaned = false;
		}

		$cpt_counts = array(
			'students'  => $remaining_cpt_counts['students'],
			'teachers'  => $remaining_cpt_counts['teachers'],
			'schools'   => $remaining_cpt_counts['schools'],
			'templates' => $remaining_cpt_counts['certificates'],
		);

		// Count SQL records
		$sql_counts = array();
		foreach ( $tables->get_all_tables() as $name => $table ) {
			if ( in_array( $name, array( 'students', 'teachers', 'schools', 'certificate_templates', 'certificates', 'email_logs' ) ) ) {
				$sql_counts[ $name ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
			}
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->title ); ?></h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
					<p><?php echo wp_kses_post( $message ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Step 1: Table Status -->
			<div class="card" style="min-height: auto; margin-bottom: 20px;">
				<h2>Step 1: Custom Tables Status</h2>
				<table class="widefat">
					<thead><tr><th>Table</th><th>Status</th><th>Records</th></tr></thead>
					<tbody>
						<?php foreach ( $table_status as $name => $exists ) : ?>
							<tr>
								<td><code><?php echo esc_html( $name ); ?></code></td>
								<td><?php echo $exists ? '<span style="color:#00a32a;">✓ Exists</span>' : '<span style="color:#d63638;">✗ Missing</span>'; ?></td>
								<td><?php echo isset( $sql_counts[ $name ] ) ? number_format( $sql_counts[ $name ] ) : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" style="margin-top: 15px;">
					<?php wp_nonce_field( 'cg_verify_tables' ); ?>
					<button type="submit" name="cg_verify_tables" class="button">Verify / Create Tables</button>
				</form>
			</div>

			<!-- Step 2: CPT → SQL Migration -->
			<div class="card" style="min-height: auto; margin-bottom: 20px;">
				<h2>Step 2: Migrate CPT Data to SQL Tables</h2>

				<?php if ( $migration_completed ) : ?>
					<div class="notice notice-success inline">
						<p>✓ Migration completed: <strong><?php echo esc_html( $migration_completed ); ?></strong></p>
					</div>
					<table class="widefat" style="margin-top: 10px;">
						<thead><tr><th>Entity</th><th>CPT Records</th><th>SQL Records</th><th>Status</th></tr></thead>
						<tbody>
							<?php
							$mapping = array(
								'schools'   => array( 'schools', 'schools' ),
								'students'  => array( 'students', 'students' ),
								'teachers'  => array( 'teachers', 'teachers' ),
								'templates' => array( 'certificates', 'certificate_templates' ),
							);
							foreach ( $mapping as $label => [$cpt_key, $sql_key] ) :
								$cpt = $cpt_counts[ $cpt_key ] ?? 0;
								$sql = $sql_counts[ $sql_key ] ?? 0;
								if ( $cpt === 0 && $sql > 0 && $cpts_cleaned ) {
									$status = '<span style="color:#00a32a;">✓ Migrated</span>';
								} elseif ( $cpt === $sql ) {
									$status = '<span style="color:#00a32a;">✓ Match</span>';
								} else {
									$status = '<span style="color:#dba617;">⚠ Mismatch</span>';
								}
								?>
								<tr>
									<td><?php echo esc_html( ucfirst( $label ) ); ?></td>
									<td><?php echo number_format( $cpt ); ?></td>
									<td><?php echo number_format( $sql ); ?></td>
									<td><?php echo $status; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="notice notice-warning inline">
						<p>⚠ Migration has not been run yet.</p>
					</div>
				<?php endif; ?>

				<p style="margin-top: 15px;">
					This copies all data from WordPress Custom Post Types to custom SQL tables.
					<strong>CPT data is NOT deleted.</strong> You can verify and clean up later.
				</p>

				<form method="post" style="display:inline;">
					<?php wp_nonce_field( 'cg_run_cpt_to_sql' ); ?>
					<button type="submit" name="cg_run_cpt_to_sql" class="button button-primary button-hero"
							onclick="return confirm('This will copy all CPT data to SQL tables. CPT data will NOT be deleted. Continue?');">
						Run CPT → SQL Migration
					</button>
				</form>

				<?php if ( $migration_completed && ! $cpts_cleaned ) : ?>
				<form method="post" style="display:inline; margin-left: 10px;">
					<?php wp_nonce_field( 'cg_rollback_migration' ); ?>
					<button type="submit" name="cg_rollback_migration" class="button"
							style="border-color:#d63638; color:#d63638;"
							onclick="return confirm('This will clear all SQL tables. CPT data is NOT deleted — rollback is safe. Continue?');">
						↩ Rollback Migration
					</button>
				</form>
				<?php endif; ?>
			</div>

			<!-- Step 2b: Fix Issue Dates (one-time normalization) -->
			<div class="card" style="min-height: auto; margin-bottom: 20px;">
				<h2>Step 2b: Normalize Issue Dates (One-Time Fix)</h2>
				<p>
					Converts any <code>issue_date</code> values stored in <code>d-m-Y</code> (e.g. <code>23-03-2026</code>)
					to the correct <code>Y-m-d</code> storage format (<code>2026-03-23</code>).
					This fixes <em>"No matching template"</em> errors caused by date format mismatches during template selection.
					<strong>Safe to run multiple times.</strong>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'cg_fix_issue_dates' ); ?>
					<button type="submit" name="cg_fix_issue_dates" class="button button-primary"
							onclick="return confirm('This will update issue_date values across all student/teacher/school posts to Y-m-d format. Safe to run. Continue?');">
						Fix Issue Dates (d-m-Y → Y-m-d)
					</button>
				</form>
			</div>

			<!-- Step 3: Cleanup CPTs (Optional) -->
			<div class="card" style="min-height: auto; margin-bottom: 20px;">
				<h2>Step 3: Clean Up Legacy CPT Data</h2>

				<?php if ( $cpts_cleaned && $total_remaining_cpts === 0 ) : ?>
					<div class="notice notice-success inline">
						<p>✓ All legacy CPT data cleaned up. Plugin uses SQL tables exclusively.</p>
					</div>
				<?php elseif ( $migration_completed || $total_remaining_cpts > 0 ) : ?>
					<?php if ( $total_remaining_cpts > 0 ) : ?>
						<div class="notice notice-warning inline">
							<p>⚠ <strong><?php echo number_format( $total_remaining_cpts ); ?></strong> legacy CPT post(s) still in <code>wp_posts</code>.</p>
							<p>These generate public URLs like <code>/students/name-school/</code> that expose data and return broken pages. Clean up to remove them.</p>
						</div>
						<table class="widefat" style="margin-top: 10px;">
							<thead><tr><th>Post Type</th><th>Remaining Posts</th><th>Public URL Pattern</th></tr></thead>
							<tbody>
								<?php foreach ( $remaining_cpt_counts as $pt => $count ) : ?>
									<?php if ( $count > 0 ) : ?>
									<tr>
										<td><code><?php echo esc_html( $pt ); ?></code></td>
										<td><strong><?php echo number_format( $count ); ?></strong></td>
										<td><code>/<?php echo esc_html( $pt ); ?>/post-slug/</code></td>
									</tr>
									<?php endif; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<div class="notice notice-info inline">
							<p>After verifying all data migrated correctly, click below to remove CPT data.</p>
						</div>
					<?php endif; ?>
					<form method="post" style="margin-top: 15px;">
						<?php wp_nonce_field( 'cg_cleanup_cpts' ); ?>
						<button type="submit" name="cg_cleanup_cpts" class="button button-primary"
								onclick="return confirm('WARNING: This will permanently DELETE all legacy CPT posts (students, teachers, schools, certificates) from wp_posts. SQL table data is unaffected. Continue?');">
							Clean Up All Legacy CPT Data
						</button>
					</form>
				<?php else : ?>
					<p class="description">Complete Step 2 first.</p>
				<?php endif; ?>
			</div>

			<!-- Step 4: Public URL Protection -->
			<div class="card" style="min-height: auto; margin-bottom: 20px;">
				<h2>Step 4: Public URL Protection</h2>
				<?php if ( $total_remaining_cpts === 0 ) : ?>
					<div class="notice notice-success inline">
						<p>✓ No legacy CPT posts remain — no public URLs to worry about.</p>
					</div>
				<?php else : ?>
					<div class="notice notice-info inline">
						<p>While legacy CPT posts exist, the plugin blocks public access to <code>/students/</code>, <code>/teachers/</code>, and <code>/schools/</code> URLs with a 404 response. Clean up CPT data (Step 3) to remove these URLs permanently.</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Orphan Report -->
			<?php if ( $total_remaining_cpts > 0 ) : ?>
			<div class="card" style="min-height: auto; margin-bottom: 20px;">
				<h2>Orphan Report</h2>
				<p class="description">CPT posts in <code>wp_posts</code> with no matching row in the SQL table (by email for students/teachers, school_name for schools, certificate_type+event_date for templates).</p>
				<?php $orphans = $this->detect_orphans( $tables ); ?>
				<?php if ( array_sum( array_column( $orphans, 'cpt_only' ) ) === 0 && array_sum( array_column( $orphans, 'sql_only' ) ) === 0 ) : ?>
					<div class="notice notice-success inline"><p>✓ No orphans detected — all CPT posts have matching SQL rows.</p></div>
				<?php else : ?>
					<table class="widefat">
						<thead><tr><th>Entity</th><th>CPT-only (no SQL match)</th><th>SQL-only (no CPT match)</th></tr></thead>
						<tbody>
							<?php foreach ( $orphans as $entity => $counts ) : ?>
								<tr>
									<td><?php echo esc_html( ucfirst( $entity ) ); ?></td>
									<td><?php echo $counts['cpt_only'] > 0 ? '<strong style="color:#d63638;">' . number_format( $counts['cpt_only'] ) . '</strong>' : '0'; ?></td>
									<td><?php echo $counts['sql_only'] > 0 ? '<strong style="color:#dba617;">' . number_format( $counts['sql_only'] ) . '</strong>' : '0'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description" style="margin-top: 10px;">CPT-only orphans are safe to delete via "Clean Up" above. SQL-only records are expected after cleanup.</p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="card" style="margin-top: 20px;">
				<h2>Migration Process</h2>
				<ol>
					<li><strong>Tables Created:</strong> Custom SQL tables are created automatically on activation.</li>
					<li><strong>Data Copied:</strong> All CPT data is copied to SQL tables (CPT data preserved).</li>
					<li><strong>Verify:</strong> Check record counts match between CPT and SQL.</li>
					<li><strong>Clean Up:</strong> Remove legacy CPT posts from <code>wp_posts</code> — eliminates public URLs and orphaned data.</li>
					<li><strong>URL Protection:</strong> While legacy posts exist, public access is blocked with a 404 response.</li>
				</ol>
			</div>
		</div>
		<?php
	}

	private function rollback_migration( CustomTables $tables ): void {
		global $wpdb;
		$truncate = array( 'students', 'teachers', 'schools', 'certificate_templates', 'certificates' );
		foreach ( $truncate as $name ) {
			$table = $tables->get_table( $name );
			if ( $table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$wpdb->query( "TRUNCATE TABLE $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
		delete_option( 'cg_cpt_to_sql_migration_completed' );
		delete_option( 'cg_cpt_to_sql_migration_stats' );
		delete_option( 'cg_cpt_to_sql_migration_timestamp' );
	}

	/**
	 * Normalize all issue_date values to Y-m-d storage format.
	 * Fixes records imported via CSV that stored dates as d-m-Y.
	 * Fixes both CPT postmeta AND SQL tables.
	 */
	private function fix_issue_dates(): int {
		global $wpdb;
		$fixed = 0;

		// Fix CPT postmeta
		$rows = $wpdb->get_results(
			"SELECT pm.post_id, pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = 'issue_date'
               AND pm.meta_value != ''
               AND p.post_type IN ('students','teachers','schools')"
		);

		foreach ( $rows as $row ) {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row->meta_value ) ) {
				continue;
			}

			$stored = null;
			foreach ( array( 'd-m-Y', 'm/d/Y', 'Y/m/d', 'd/m/Y' ) as $fmt ) {
				$dt = \DateTime::createFromFormat( $fmt, $row->meta_value );
				if ( $dt && $dt->format( $fmt ) === $row->meta_value ) {
					$stored = $dt->format( 'Y-m-d' );
					break;
				}
			}
			if ( ! $stored ) {
				$ts = strtotime( $row->meta_value );
				if ( $ts ) {
					$stored = date( 'Y-m-d', $ts );
				}
			}

			if ( $stored && $stored !== $row->meta_value ) {
				update_post_meta( (int) $row->post_id, 'issue_date', $stored );
				++$fixed;
			}
		}

		// Fix SQL tables using the migration class
		if ( class_exists( 'CertificateGenerator\Database\CptToSqlMigration' ) ) {
			$sql_fixed = \CertificateGenerator\Database\CptToSqlMigration::normalize_issue_dates();
			foreach ( $sql_fixed as $entity => $count ) {
				$fixed += $count;
			}
		}

		return $fixed;
	}

	private function detect_orphans( CustomTables $tables ): array {
		global $wpdb;

		$results = array(
			'students'  => array( 'cpt_only' => 0, 'sql_only' => 0 ),
			'teachers'  => array( 'cpt_only' => 0, 'sql_only' => 0 ),
			'schools'   => array( 'cpt_only' => 0, 'sql_only' => 0 ),
			'templates' => array( 'cpt_only' => 0, 'sql_only' => 0 ),
		);

		$checks = array(
			'students'  => array( 'cpt' => 'students', 'sql' => 'students', 'meta_key' => 'email', 'sql_col' => 'email' ),
			'teachers'  => array( 'cpt' => 'teachers', 'sql' => 'teachers', 'meta_key' => 'email', 'sql_col' => 'email' ),
			'schools'   => array( 'cpt' => 'schools', 'sql' => 'schools', 'meta_key' => 'school_name', 'sql_col' => 'school_name' ),
			'templates' => array( 'cpt' => 'certificates', 'sql' => 'certificate_templates', 'meta_key' => 'certificate_type', 'sql_col' => 'certificate_type' ),
		);

		foreach ( $checks as $entity => $cfg ) {
			$sql_table = $tables->get_table( $cfg['sql'] );
			if ( ! $sql_table || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sql_table ) ) !== $sql_table ) {
				continue;
			}

			$cpt_values = $wpdb->get_col( $wpdb->prepare(
				"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value != ''",
				$cfg['cpt'],
				$cfg['meta_key']
			) );

			$sql_values = $wpdb->get_col( "SELECT {$cfg['sql_col']} FROM $sql_table WHERE {$cfg['sql_col']} != ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$cpt_set = array_flip( $cpt_values );
			$sql_set = array_flip( $sql_values );

			$results[ $entity ]['cpt_only'] = count( array_diff_key( $cpt_set, $sql_set ) );
			$results[ $entity ]['sql_only'] = count( array_diff_key( $sql_set, $cpt_set ) );
		}

		return $results;
	}

	private function cleanup_cpts(): void {
		global $wpdb;

		$post_types   = array( 'students', 'teachers', 'schools', 'certificates' );
		$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($placeholders)",
				...$post_types
			)
		);

		if ( ! empty( $post_ids ) ) {
			$id_list = implode( ',', array_map( 'absint', $post_ids ) );
			$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($id_list)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($id_list)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		update_option( 'cg_cpts_cleaned_up', true );
	}
}
