<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

use CertificateGenerator\Core\Config;
use CertificateGenerator\Database\CustomTables;
use CertificateGenerator\Database\UserRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schools admin page — list + CRUD form backed by wp_cg_schools.
 */
class SchoolsPage {

	private string $slug      = 'cg-schools';
	private string $edit_slug = 'cg-school-edit';
	private string $table_key = 'schools';

	public function register(): void {
		add_submenu_page( 'cg-dashboard', 'Schools', 'Schools', 'manage_options', $this->slug, array( $this, 'render_list' ) );
		add_submenu_page( '', 'Add / Edit School', '', 'manage_options', $this->edit_slug, array( $this, 'render_edit' ) );
	}

	// ── List View ────────────────────────────────────────────────────────────

	public function render_list(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		global $wpdb;
		$table   = CustomTables::instance()->get_table( $this->table_key );
		$message = '';

		if ( ! empty( $_POST['cg_delete_school_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cg_delete_school_nonce'] ) ), 'cg_delete_school' ) ) {
			$ids = array_map( 'absint', (array) ( $_POST['school_ids'] ?? array() ) );
			foreach ( $ids as $id ) {
				if ( $id > 0 ) {
					$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
			$message = count( $ids ) . ' school(s) deleted.';
		}

		if ( ! empty( $_GET['action'] ) && $_GET['action'] === 'delete' && ! empty( $_GET['id'] ) ) {
			$del_id = absint( $_GET['id'] );
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cg_delete_school_single_' . $del_id ) ) {
				$wpdb->delete( $table, array( 'id' => $del_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				wp_safe_redirect( admin_url( 'admin.php?page=' . $this->slug . '&deleted=1' ) );
				exit;
			}
		}

		$search   = sanitize_text_field( $_GET['s'] ?? '' );
		$city_f   = sanitize_text_field( $_GET['city_filter'] ?? '' );
		$cert_f   = sanitize_text_field( $_GET['cert_filter'] ?? '' );
		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$per_page = 20;
		$allowed  = array( 'school_name', 'email', 'city', 'state', 'certificate_type', 'issue_date', 'created_at' );
		$orderby  = in_array( $_GET['orderby'] ?? '', $allowed, true ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order    = strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$where  = '1=1';
		$params = array();
		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (school_name LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $city_f ) {
			$where   .= ' AND city = %s';
			$params[] = $city_f; }
		if ( $cert_f ) {
			$where   .= ' AND certificate_type = %s';
			$params[] = $cert_f; }

		$offset  = ( $paged - 1 ) * $per_page;
		$filters = array_filter(
			array(
				'search'           => $search,
				'city'             => $city_f,
				'certificate_type' => $cert_f,
			)
		);

		if ( Config::flag( 'CG_USE_REPOSITORIES' ) ) {
			$repo       = new UserRepository( 'schools' );
			$total      = $repo->count_filtered( $filters );
			$rows       = $repo->find_page( $filters, $orderby, $order, $per_page, $offset );
			$cities     = $repo->distinct_column( 'city' );
			$cert_types = $repo->distinct_column( 'certificate_type' );
		} else {
			$count_sql  = "SELECT COUNT(*) FROM $table WHERE $where"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total      = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data_sql   = "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows       = $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$cities     = $wpdb->get_col( "SELECT DISTINCT city FROM $table WHERE city != '' ORDER BY city" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$cert_types = $wpdb->get_col( "SELECT DISTINCT certificate_type FROM $table WHERE certificate_type != '' ORDER BY certificate_type" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$total_pages = (int) ceil( $total / $per_page );
		$list_url    = admin_url( 'admin.php?page=' . $this->slug );
		$edit_url    = admin_url( 'admin.php?page=' . $this->edit_slug );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Schools</h1>
			<a href="<?php echo esc_url( $edit_url ); ?>" class="page-title-action">Add New</a>

			<?php
			if ( $message ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>

			<form method="get" style="margin:15px 0;">
				<input type="hidden" name="page" value="<?php echo esc_attr( $this->slug ); ?>">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name or email…" style="margin-right:5px;">
				<select name="city_filter">
					<option value="">— All Cities —</option>
					<?php
					foreach ( $cities as $c ) :
						?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $city_f, $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?>
				</select>
				<select name="cert_filter" style="margin-left:5px;">
					<option value="">— All Certificate Types —</option>
					<?php
					foreach ( $cert_types as $c ) :
						?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $cert_f, $c ); ?>><?php echo esc_html( $c ); ?></option><?php endforeach; ?>
				</select>
				<button type="submit" class="button">Filter</button>
				<?php
				if ( $search || $city_f || $cert_f ) :
					?>
					<a href="<?php echo esc_url( $list_url ); ?>" class="button">Clear</a><?php endif; ?>
			</form>

			<form method="post" id="cg-schools-form">
				<?php wp_nonce_field( 'cg_delete_school', 'cg_delete_school_nonce' ); ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_action" id="cg-bulk-action-school"><option value="">Bulk Actions</option><option value="delete">Delete</option></select>
						<button type="submit" class="button action" id="cg-doaction-school">Apply</button>
					</div>
					<div class="tablenav-pages" style="float:right;">
						<span class="displaying-num"><?php echo number_format( $total ); ?> items</span>
						<?php if ( $total_pages > 1 ) : ?>
							<?php
							if ( $paged > 1 ) :
								?>
								<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $list_url ) ); ?>">‹</a><?php endif; ?>
							<span style="margin:0 5px;">Page <?php echo $paged; ?> of <?php echo $total_pages; ?></span>
							<?php
							if ( $paged < $total_pages ) :
								?>
								<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $list_url ) ); ?>">›</a><?php endif; ?>
						<?php endif; ?>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead><tr>
						<td class="manage-column check-column"><input type="checkbox" id="cb-select-all-sc"></td>
						<?php
						$cols = array(
							'school_name'      => 'Name',
							'email'            => 'Email',
							'city'             => 'City',
							'state'            => 'State',
							'principal_name'   => 'Principal',
							'certificate_type' => 'Cert. Type',
							'issue_date'       => 'Issue Date',
							'status'           => 'Status',
						);
						foreach ( $cols as $col_key => $col_label ) :
							$next_order = ( $orderby === $col_key && $order === 'ASC' ) ? 'DESC' : 'ASC';
							$sort_url   = add_query_arg(
								array(
									'orderby' => $col_key,
									'order'   => $next_order,
									'paged'   => 1,
								),
								$list_url
							);
							?>
						<th scope="col" class="<?php echo $orderby === $col_key ? 'sorted ' . strtolower( $order ) : 'sortable desc'; ?>">
							<a href="<?php echo esc_url( $sort_url ); ?>"><span><?php echo esc_html( $col_label ); ?></span></a>
						</th>
						<?php endforeach; ?>
						<th>Actions</th>
					</tr></thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="10"><em>No schools found. <a href="<?php echo esc_url( $edit_url ); ?>">Add one</a>.</em></td></tr>
							<?php
						else :
							foreach ( $rows as $row ) :
								$row_id = (int) $row['id'];
								$issue  = ! empty( $row['issue_date'] ) ? ( date_create( $row['issue_date'] ) ?: null ) : null;
								?>
						<tr>
							<th scope="row" class="check-column"><input class="cb-select-sc" type="checkbox" name="school_ids[]" value="<?php echo $row_id; ?>"></th>
							<td><strong><a href="<?php echo esc_url( add_query_arg( 'id', $row_id, $edit_url ) ); ?>"><?php echo esc_html( $row['school_name'] ); ?></a></strong></td>
							<td><?php echo esc_html( $row['email'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['city'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['state'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['principal_name'] ?? '' ); ?></td>
							<td><?php echo esc_html( $row['certificate_type'] ?? '' ); ?></td>
							<td><?php echo $issue ? esc_html( $issue->format( 'd-m-Y' ) ) : '—'; ?></td>
							<td><?php echo esc_html( ucfirst( $row['status'] ?? 'active' ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( 'id', $row_id, $edit_url ) ); ?>">Edit</a>
								&nbsp;|&nbsp;
								<a href="
								<?php
								echo esc_url(
									wp_nonce_url(
										add_query_arg(
											array(
												'page'   => $this->slug,
												'action' => 'delete',
												'id'     => $row_id,
											),
											admin_url( 'admin.php' )
										),
										'cg_delete_school_single_' . $row_id
									)
								);
								?>
							"
									style="color:#d63638;" onclick="return confirm('Delete this school?');">Delete</a>
							</td>
						</tr>
													<?php
						endforeach;
endif;
						?>
					</tbody>
				</table>
			</form>
		</div>
		<script>
		document.getElementById('cb-select-all-sc').addEventListener('change', function() {
			document.querySelectorAll('.cb-select-sc').forEach(function(cb){ cb.checked = this.checked; }.bind(this));
		});
		document.getElementById('cg-doaction-school').addEventListener('click', function(e) {
			if (document.getElementById('cg-bulk-action-school').value !== 'delete') { e.preventDefault(); return; }
			if (!document.querySelectorAll('.cb-select-sc:checked').length) { e.preventDefault(); alert('Select at least one school.'); return; }
			if (!confirm('Delete selected schools?')) e.preventDefault();
		});
		</script>
		<?php
	}

	// ── Edit / Add Form ──────────────────────────────────────────────────────

	public function render_edit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		global $wpdb;
		$table   = CustomTables::instance()->get_table( $this->table_key );
		$id      = absint( $_GET['id'] ?? 0 );
		$row     = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$extra   = ! empty( $row['extra_fields'] ) ? ( json_decode( $row['extra_fields'], true ) ?: array() ) : array();
		$message = '';
		$errors  = array();

		if ( $_SERVER['REQUEST_METHOD'] === 'POST'
			&& ! empty( $_POST['cg_school_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cg_school_nonce'] ) ), 'cg_save_school' ) ) {

			$name = sanitize_text_field( $_POST['school_name'] ?? '' );
			if ( ! $name ) {
				$errors[] = 'School name is required.';
			}

			if ( empty( $errors ) ) {
				$new_extra = array();
				$ex_keys   = (array) ( $_POST['extra_key'] ?? array() );
				$ex_vals   = (array) ( $_POST['extra_val'] ?? array() );
				foreach ( $ex_keys as $i => $k ) {
					$k = sanitize_key( $k );
					$v = sanitize_text_field( $ex_vals[ $i ] ?? '' );
					if ( $k && $v !== '' ) {
						$new_extra[ $k ] = $v;
					}
				}
				$data = array(
					'school_name'      => $name,
					'email'            => sanitize_email( $_POST['email'] ?? '' ),
					'phone'            => sanitize_text_field( $_POST['phone'] ?? '' ),
					'address'          => sanitize_text_field( $_POST['address'] ?? '' ),
					'city'             => sanitize_text_field( $_POST['city'] ?? '' ),
					'state'            => sanitize_text_field( $_POST['state'] ?? '' ),
					'country'          => sanitize_text_field( $_POST['country'] ?? '' ),
					'postal_code'      => sanitize_text_field( $_POST['postal_code'] ?? '' ),
					'website'          => esc_url_raw( $_POST['website'] ?? '' ),
					'principal_name'   => sanitize_text_field( $_POST['principal_name'] ?? '' ),
					'certificate_type' => sanitize_text_field( $_POST['certificate_type'] ?? '' ),
					'issue_date'       => sanitize_text_field( $_POST['issue_date'] ?? '' ) ?: null,
					'year'             => function_exists( 'cg_year_from_issue_date' ) ? cg_year_from_issue_date( sanitize_text_field( $_POST['issue_date'] ?? '' ) ?: null ) : null,
					'status'           => in_array( $_POST['status'] ?? '', array( 'active', 'inactive' ), true ) ? sanitize_key( $_POST['status'] ) : 'active',
					'extra_fields'     => ! empty( $new_extra ) ? wp_json_encode( $new_extra ) : null,
					'updated_at'       => current_time( 'mysql' ),
				);
				if ( $id && $row ) {
					$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$message = 'School updated.';
					$row     = array_merge( $row, $data );
					$extra   = $new_extra;
				} else {
					$data['created_at'] = current_time( 'mysql' );
					$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$id      = (int) $wpdb->insert_id;
					$row     = array_merge( $data, array( 'id' => $id ) );
					$extra   = $new_extra;
					$message = 'School added.';
				}

				if ( function_exists( 'cg_flush_filter_caches' ) ) {
					cg_flush_filter_caches();
				}

				// Phase 6: sync to WP Dynamic Tags if a wp_post_id is linked
				$wp_post_id = (int) ( $row['wp_post_id'] ?? 0 );
				if ( $wp_post_id > 0 && function_exists( 'cg_sync_to_dynamic_tags' ) ) {
					cg_sync_to_dynamic_tags( $wp_post_id );
				}
			}
		}

		$list_url = admin_url( 'admin.php?page=' . $this->slug );
		?>
		<div class="wrap">
			<h1><?php echo $id ? 'Edit School' : 'Add New School'; ?></h1>
			<a href="<?php echo esc_url( $list_url ); ?>">← Back to Schools</a>

			<?php
			foreach ( $errors as $e ) :
				?>
				<div class="notice notice-error"><p><?php echo esc_html( $e ); ?></p></div><?php endforeach; ?>
			<?php
			if ( $message ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div><?php endif; ?>

			<form method="post" style="max-width:700px; margin-top:20px;">
				<?php wp_nonce_field( 'cg_save_school', 'cg_school_nonce' ); ?>
				<table class="form-table">
					<tr><th><label for="school_name">School Name <span style="color:red">*</span></label></th>
						<td><input type="text" id="school_name" name="school_name" class="regular-text" required value="<?php echo esc_attr( $row['school_name'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="email">Email</label></th>
						<td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr( $row['email'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="phone">Phone</label></th>
						<td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr( $row['phone'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="address">Address</label></th>
						<td><input type="text" id="address" name="address" class="regular-text" value="<?php echo esc_attr( $row['address'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="city">City</label></th>
						<td><input type="text" id="city" name="city" class="regular-text" value="<?php echo esc_attr( $row['city'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="state">State</label></th>
						<td><input type="text" id="state" name="state" class="regular-text" value="<?php echo esc_attr( $row['state'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="country">Country</label></th>
						<td><input type="text" id="country" name="country" class="regular-text" value="<?php echo esc_attr( $row['country'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="postal_code">Postal Code</label></th>
						<td><input type="text" id="postal_code" name="postal_code" class="regular-text" value="<?php echo esc_attr( $row['postal_code'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="website">Website</label></th>
						<td><input type="url" id="website" name="website" class="regular-text" value="<?php echo esc_attr( $row['website'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="principal_name">Principal Name</label></th>
						<td><input type="text" id="principal_name" name="principal_name" class="regular-text" value="<?php echo esc_attr( $row['principal_name'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="certificate_type">Certificate Type</label></th>
						<td><input type="text" id="certificate_type" name="certificate_type" class="regular-text" value="<?php echo esc_attr( $row['certificate_type'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="issue_date">Issue Date</label></th>
						<td><input type="date" id="issue_date" name="issue_date" value="<?php echo esc_attr( $row['issue_date'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="status">Status</label></th>
						<td><select id="status" name="status">
							<option value="active" <?php selected( $row['status'] ?? 'active', 'active' ); ?>>Active</option>
							<option value="inactive" <?php selected( $row['status'] ?? '', 'inactive' ); ?>>Inactive</option>
						</select></td></tr>
				</table>

				<?php if ( ! empty( $row['serial_number'] ) ) : ?>
					<p><strong>Serial Number:</strong> <code><?php echo esc_html( $row['serial_number'] ); ?></code></p>
				<?php endif; ?>

				<h3>Extra Fields</h3>
				<table class="widefat" style="max-width:500px; margin-bottom:8px;">
					<thead><tr><th>Key</th><th>Value</th><th></th></tr></thead>
					<tbody id="cg-extra-tbody-sc">
						<?php foreach ( $extra as $k => $v ) : ?>
						<tr>
							<td><input type="text" name="extra_key[]" value="<?php echo esc_attr( $k ); ?>" class="regular-text"></td>
							<td><input type="text" name="extra_val[]" value="<?php echo esc_attr( $v ); ?>" class="regular-text"></td>
							<td><button type="button" class="button button-small cg-rm-row-sc">✕</button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<button type="button" class="button" id="cg-add-extra-sc">+ Add Field</button>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php echo $id ? 'Update School' : 'Add School'; ?></button>
					<a href="<?php echo esc_url( $list_url ); ?>" class="button">Cancel</a>
				</p>
			</form>
		</div>
		<script>
		document.getElementById('cg-add-extra-sc').addEventListener('click', function() {
			var tbody = document.getElementById('cg-extra-tbody-sc');
			var tr = document.createElement('tr');
			[['extra_key[]','key'],['extra_val[]','value']].forEach(function(c){
				var td = document.createElement('td');
				var inp = document.createElement('input');
				inp.type = 'text'; inp.name = c[0]; inp.className = 'regular-text'; inp.placeholder = c[1];
				td.appendChild(inp); tr.appendChild(td);
			});
			var td3 = document.createElement('td');
			var btn = document.createElement('button');
			btn.type = 'button'; btn.className = 'button button-small'; btn.textContent = '✕';
			btn.addEventListener('click', function(){ tr.parentNode.removeChild(tr); });
			td3.appendChild(btn); tr.appendChild(td3);
			tbody.appendChild(tr);
		});
		document.querySelectorAll('.cg-rm-row-sc').forEach(function(btn){
			btn.addEventListener('click', function(){ this.closest('tr').remove(); });
		});
		</script>
		<?php
	}
}
