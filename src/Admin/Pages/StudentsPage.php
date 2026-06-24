<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

use function add_submenu_page;
use function current_user_can;
use function wp_die;
use function wp_verify_nonce;
use function sanitize_text_field;
use function wp_unslash;
use function sanitize_email;
use function sanitize_key;
use function admin_url;
use function esc_url;
use function esc_html;
use function esc_attr;
use function selected;
use function number_format;
use function wp_nonce_field;
use function wp_safe_redirect;
use function add_query_arg;
use function wp_nonce_url;

use CertificateGenerator\Core\Config;
use CertificateGenerator\Database\CustomTables;
use CertificateGenerator\Database\UserRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Students admin page — list + CRUD form backed by wp_cg_students.
 */
class StudentsPage {

	private string $slug      = 'cg-students';
	private string $edit_slug = 'cg-student-edit';
	private string $table_key = 'students';

	public function register(): void {
		\add_submenu_page(
			'cg-dashboard',
			'Students',
			'Students',
			'manage_options',
			$this->slug,
			array( $this, 'render_list' )
		);
		// Hidden from menu — accessed via ?page=cg-student-edit
		\add_submenu_page(
			'',
			'Add / Edit Student',
			'',
			'manage_options',
			$this->edit_slug,
			array( $this, 'render_edit' )
		);
		\add_action( 'wp_ajax_cg_student_send_email', array( $this, 'send_email_ajax' ) );
	}

	// ── List View ────────────────────────────────────────────────────────────

	public function render_list(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( 'Insufficient permissions' );
		}

		global $wpdb;
		$table   = CustomTables::instance()->get_table( $this->table_key );
		$message = '';

		// Handle bulk actions via POST
		if ( ! empty( $_POST['cg_bulk_action_nonce'] )
			&& \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['cg_bulk_action_nonce'] ) ), 'cg_bulk_action' ) ) {
			$action = \sanitize_text_field( $_POST['bulk_action'] ?? '' );
			$ids    = array_map( 'absint', (array) ( $_POST['student_ids'] ?? array() ) );
			if ( $action === 'delete' ) {
				foreach ( $ids as $id ) {
					if ( $id > 0 ) {
						$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					}
				}
				$message = count( $ids ) . ' student(s) deleted.';
			} elseif ( $action === 'bulk_edit' ) {
				// Gather bulk edit fields (optional)
				$new_school = \sanitize_text_field( $_POST['bulk_school_name'] ?? '' );
				$new_email  = \sanitize_email( $_POST['bulk_email'] ?? '' );
				$new_cert   = \sanitize_text_field( $_POST['bulk_certificate_type'] ?? '' );
				$updated    = 0;
				foreach ( $ids as $id ) {
					if ( $id > 0 ) {
						$data = array();
						if ( $new_school !== '' ) {
							$data['school_name'] = $new_school;
						}
						if ( $new_email !== '' ) {
							$data['email'] = $new_email;
						}
						if ( $new_cert !== '' ) {
							$data['certificate_type'] = $new_cert;
						}
						if ( ! empty( $data ) ) {
							$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							++$updated;
						}
					}
				}
				$message = $updated . ' student(s) updated via bulk edit.';
			} elseif ( $action === 'empty_all' ) {
				// Delete all rows
				$wpdb->query( "DELETE FROM $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$message = 'All students have been removed.';
			}
		}

		$search        = \sanitize_text_field( $_GET['s'] ?? '' );
		$school_f      = \sanitize_text_field( $_GET['school_filter'] ?? '' );
		$cert_f        = \sanitize_text_field( $_GET['cert_filter'] ?? '' );
		$paged         = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$per_page      = 20;
		$allowed_order = array( 'student_name', 'email', 'school_name', 'certificate_type', 'issue_date', 'created_at', 'status' );
		$orderby       = in_array( $_GET['orderby'] ?? '', $allowed_order, true ) ? \sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order         = strtoupper( $_GET['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$where  = '1=1';
		$params = array();
		if ( $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where   .= ' AND (student_name LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $school_f ) {
			$where   .= ' AND school_name = %s';
			$params[] = $school_f; }
		if ( $cert_f ) {
			$where   .= ' AND certificate_type = %s';
			$params[] = $cert_f; }

		$offset  = ( $paged - 1 ) * $per_page;
		$filters = array_filter(
			array(
				'search'           => $search,
				'school_name'      => $school_f,
				'certificate_type' => $cert_f,
			)
		);

		if ( Config::flag( 'CG_USE_REPOSITORIES' ) ) {
			$repo       = new UserRepository( 'students' );
			$total      = $repo->count_filtered( $filters );
			$rows       = $repo->find_page( $filters, $orderby, $order, $per_page, $offset );
			$schools    = $repo->distinct_column( 'school_name' );
			$cert_types = $repo->distinct_column( 'certificate_type' );
		} else {
			$count_sql  = "SELECT COUNT(*) FROM $table WHERE $where"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total      = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$data_sql   = "SELECT * FROM $table WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows       = $wpdb->get_results( $wpdb->prepare( $data_sql, ...array_merge( $params, array( $per_page, $offset ) ) ), \ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$schools    = $wpdb->get_col( "SELECT DISTINCT school_name FROM $table WHERE school_name != '' ORDER BY school_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$cert_types = $wpdb->get_col( "SELECT DISTINCT certificate_type FROM $table WHERE certificate_type != '' ORDER BY certificate_type" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Batch-fetch email status for this page's rows via EmailStatusService (merged logs+queue).
		$email_statuses = array();
		if ( ! empty( $rows ) ) {
			$page_emails = array_unique( array_filter( array_column( $rows, 'email' ) ) );
			if ( ! empty( $page_emails ) && class_exists( '\CertificateGenerator\Services\EmailStatusService' ) ) {
				$email_statuses = \CertificateGenerator\Services\EmailStatusService::getBadgeStatuses( $page_emails );
			}
		}

		$total_pages = (int) ceil( $total / $per_page );
		$list_url    = \admin_url( 'admin.php?page=' . $this->slug );
		$edit_url    = \admin_url( 'admin.php?page=' . $this->edit_slug );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Students</h1>
			<a href="<?php echo \esc_url( $edit_url ); ?>" class="page-title-action">Add New</a>

			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo \esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<form method="get" style="margin:15px 0;">
				<input type="hidden" name="page" value="<?php echo \esc_attr( $this->slug ); ?>">
				<input type="search" name="s" value="<?php echo \esc_attr( $search ); ?>" placeholder="Search name or email…" style="margin-right:5px;">
				<select name="school_filter">
					<option value="">— All Schools —</option>
					<?php foreach ( $schools as $s ) : ?>
						<option value="<?php echo \esc_attr( $s ); ?>" <?php \selected( $school_f, $s ); ?>><?php echo \esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="cert_filter" style="margin-left:5px;">
					<option value="">— All Certificate Types —</option>
					<?php foreach ( $cert_types as $c ) : ?>
						<option value="<?php echo \esc_attr( $c ); ?>" <?php \selected( $cert_f, $c ); ?>><?php echo \esc_html( $c ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button">Filter</button>
				<?php if ( $search || $school_f || $cert_f ) : ?>
					<a href="<?php echo \esc_url( $list_url ); ?>" class="button">Clear</a>
				<?php endif; ?>
			</form>

			<form method="post" id="cg-students-form">
				<?php \wp_nonce_field( 'cg_bulk_action', 'cg_bulk_action_nonce' ); ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="bulk_action" id="bulk-action-selector-top">
							<option value="">Bulk Actions</option>
							<option value="delete">Delete</option>
							<option value="bulk_edit">Bulk Edit</option>
							<option value="empty_all">Empty Table</option>
						</select>
						<div id="bulk-edit-fields" style="display:none; margin-top:5px;">
							<label style='margin-right:5px;'>School Name: <input type='text' name='bulk_school_name' class='regular-text'></label>
							<label style='margin-right:5px;'>Email: <input type='email' name='bulk_email' class='regular-text'></label>
							<label>Certificate Type: <input type='text' name='bulk_certificate_type' class='regular-text'></label>
						</div>
						<button type="submit" class="button action" id="doaction">Apply</button>
					</div>
					<div class="tablenav-pages" style="float:right;">
						<span class="displaying-num"><?php echo \number_format( $total ); ?> items</span>
						<?php if ( $total_pages > 1 ) : ?>
							<?php
							if ( $paged > 1 ) :
								?>
								<a class="button" href="<?php echo \esc_url( \add_query_arg( 'paged', $paged - 1, $list_url ) ); ?>">‹</a><?php endif; ?>
							<span style="margin:0 5px;">Page <?php echo $paged; ?> of <?php echo $total_pages; ?></span>
							<?php
							if ( $paged < $total_pages ) :
								?>
								<a class="button" href="<?php echo \esc_url( \add_query_arg( 'paged', $paged + 1, $list_url ) ); ?>">›</a><?php endif; ?>
						<?php endif; ?>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead><tr>
						<td class="manage-column check-column"><input type="checkbox" id="cb-select-all"></td>
						<?php
						$cols = array(
							'student_name'     => 'Name',
							'email'            => 'Email',
							'school_name'      => 'School',
							'certificate_type' => 'Cert. Type',
							'issue_date'       => 'Issue Date',
							'serial_number'    => 'Serial #',
							'status'           => 'Status',
						);
						foreach ( $cols as $col_key => $col_label ) :
							$next_order = ( $orderby === $col_key && $order === 'ASC' ) ? 'DESC' : 'ASC';
							$sort_url   = \add_query_arg(
								array(
									'orderby' => $col_key,
									'order'   => $next_order,
									'paged'   => 1,
								),
								$list_url
							);
							?>
						<th scope="col" class="<?php echo $orderby === $col_key ? 'sorted ' . strtolower( $order ) : 'sortable desc'; ?>">
							<a href="<?php echo \esc_url( $sort_url ); ?>"><span><?php echo \esc_html( $col_label ); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc"></span><span class="sorting-indicator desc"></span></span></a>
						</th>
						<?php endforeach; ?>
						<th scope="col">Email Status</th>
						<th>Actions</th>
					</tr></thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="10"><em>No students found. <a href="<?php echo \esc_url( $edit_url ); ?>">Add one</a>.</em></td></tr>
							<?php
						else :
							foreach ( $rows as $row ) :
								$row_id = (int) $row['id'];
								$issue  = ! empty( $row['issue_date'] ) ? ( date_create( $row['issue_date'] ) ?: null ) : null;
								?>
						<tr>
							<th scope="row" class="check-column"><input class="cb-select" type="checkbox" name="student_ids[]" value="<?php echo $row_id; ?>"></th>
							<td><strong><a href="<?php echo \esc_url( \add_query_arg( 'id', $row_id, $edit_url ) ); ?>"><?php echo \esc_html( $row['student_name'] ); ?></a></strong></td>
							<td><?php echo \esc_html( $row['email'] ); ?></td>
							<td><?php echo \esc_html( $row['school_name'] ); ?></td>
							<td><?php echo \esc_html( $row['certificate_type'] ); ?></td>
							<td><?php echo $issue ? \esc_html( $issue->format( 'd-m-Y' ) ) : '—'; ?></td>
							<td><code><?php echo \esc_html( $row['serial_number'] ?? '' ); ?></code></td>
							<td><?php echo \esc_html( ucfirst( $row['status'] ?? 'active' ) ); ?></td>
							<td class="cg-email-status-cell" data-row="<?php echo \absint( $row_id ); ?>">
								<?php
								if ( empty( $row['email'] ) ) {
									echo '<span style="color:#888;">No Email</span>';
								} else {
									$badge     = $email_statuses[ $row['email'] ] ?? array(
										'status'     => 'NotSent',
										'last_error' => '',
										'attempts'   => 0,
									);
									$status    = $badge['status'];
									$err_title = ! empty( $badge['last_error'] ) ? ' title="' . \esc_attr( $badge['last_error'] ) . '"' : '';
									switch ( $status ) {
										case 'Sent':
											echo '<span class="cg-email-badge cg-email-sent" style="color:#46b450;">&#10003; Sent</span>';
											break;
										case 'Failed':
											echo '<span class="cg-email-badge cg-email-failed" style="color:#d63638;"' . $err_title . '>&#10007; Failed</span>';
											break;
										case 'Sending':
											echo '<span class="cg-email-badge cg-email-sending" style="color:#0073aa;">&#8635; Sending</span>';
											break;
										case 'Queued':
											echo '<span class="cg-email-badge cg-email-queued" style="color:#0073aa;">&#8635; Queued</span>';
											break;
										default:
											echo '<span class="cg-email-badge cg-email-pending" style="color:#ffb900;">Pending</span>';
											break;
									}
								}
								?>
							</td>
							<td>
								<a href="<?php echo \esc_url( \add_query_arg( 'id', $row_id, $edit_url ) ); ?>">Edit</a>
								&nbsp;|&nbsp;
								<a href="
								<?php
								echo \esc_url(
									\wp_nonce_url(
										\add_query_arg(
											array(
												'page'   => $this->slug,
												'action' => 'delete',
												'id'     => $row_id,
											),
											\admin_url( 'admin.php' )
										),
										'cg_delete_student_single_' . $row_id
									)
								);
								?>
								"
									style="color:#d63638;"
									onclick="return confirm('Delete this student?');">Delete</a>
								<?php if ( ! empty( $row['email'] ) ) : ?>
									&nbsp;|&nbsp;
									<button type="button"
										class="button-link cg-send-email-btn"
										data-id="<?php echo $row_id; ?>"
										style="color:#0073aa; cursor:pointer;">Send Email</button>
								<?php endif; ?>
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
		var cgStudentSendNonce = '<?php echo \esc_js( \wp_create_nonce( 'cg_student_send_email' ) ); ?>';
		var cgStudentAjaxUrl  = '<?php echo \esc_js( \admin_url( 'admin-ajax.php' ) ); ?>';

		document.getElementById('cb-select-all').addEventListener('change', function() {
			document.querySelectorAll('.cb-select').forEach(function(cb){ cb.checked = this.checked; }.bind(this));
		});
		document.getElementById('bulk-action-selector-top').addEventListener('change', function() {
			var bulkEditFields = document.getElementById('bulk-edit-fields');
			if (this.value === 'bulk_edit') {
				bulkEditFields.style.display = 'block';
			} else {
				bulkEditFields.style.display = 'none';
			}
		});
		document.getElementById('doaction').addEventListener('click', function(e) {
			var action = document.getElementById('bulk-action-selector-top').value;
			var checked = document.querySelectorAll('.cb-select:checked');
			if (!checked.length) { e.preventDefault(); alert('Select at least one student.'); return; }
			if (action === 'delete') {
				if (!confirm('Delete selected students? This cannot be undone.')) e.preventDefault();
			} else if (action === 'empty_all') {
				if (!confirm('Delete ALL students from the table? This cannot be undone.')) e.preventDefault();
			} else if (action === 'bulk_edit') {
				var school = document.querySelector('input[name="bulk_school_name"]').value;
				var email = document.querySelector('input[name="bulk_email"]').value;
				var cert = document.querySelector('input[name="bulk_certificate_type"]').value;
				if (!school && !email && !cert) {
					e.preventDefault(); alert('Enter at least one field to update.'); return;
				}
				if (!confirm('Update selected students with these values?')) e.preventDefault();
			} else if (!action) {
				e.preventDefault();
			}
		});

		// Per-student Send Email
		document.querySelectorAll('.cg-send-email-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var studentId = btn.getAttribute('data-id');
				var originalText = btn.textContent;
				btn.textContent = 'Sending…';
				btn.disabled = true;

				var data = new URLSearchParams();
				data.append('action', 'cg_student_send_email');
				data.append('nonce', cgStudentSendNonce);
				data.append('id', studentId);

				fetch(cgStudentAjaxUrl, { method: 'POST', body: data })
					.then(function(r){ return r.json(); })
					.then(function(resp) {
						if (resp.success) {
							// Flip badge in same row
							var statusCell = document.querySelector('.cg-email-status-cell[data-row="' + studentId + '"]');
							if (statusCell) {
								statusCell.innerHTML = '<span class="cg-email-badge cg-email-sent" style="color:#46b450;">&#10003; Sent</span>';
							}
							btn.textContent = 'Sent ✓';
							btn.style.color = '#46b450';
						} else {
							alert('Send failed: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
							btn.textContent = originalText;
							btn.disabled = false;
						}
					})
					.catch(function() {
						alert('Request failed. Check network and try again.');
						btn.textContent = originalText;
						btn.disabled = false;
					});
			});
		});
		</script>
		<?php
		// Handle single-row delete via GET link
		if ( ! empty( $_GET['action'] ) && $_GET['action'] === 'delete' && ! empty( $_GET['id'] ) ) {
			$del_id    = absint( $_GET['id'] );
			$nonce_key = 'cg_delete_student_single_' . $del_id;
			if ( isset( $_GET['_wpnonce'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) ), $nonce_key ) ) {
				$wpdb->delete( $table, array( 'id' => $del_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				\wp_safe_redirect( \admin_url( 'admin.php?page=' . $this->slug . '&deleted=1' ) );
				exit;
			}
		}
	}

	// ── Add / Edit Form ──────────────────────────────────────────────────────

	public function render_edit(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( 'Insufficient permissions' );
		}

		global $wpdb;
		$table   = CustomTables::instance()->get_table( $this->table_key );
		$id      = absint( $_GET['id'] ?? 0 );
		$row     = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), \ARRAY_A ) : null; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$extra   = ! empty( $row['extra_fields'] ) ? ( json_decode( $row['extra_fields'], true ) ?: array() ) : array();
		$message = '';
		$errors  = array();

		if ( $_SERVER['REQUEST_METHOD'] === 'POST'
			&& ! empty( $_POST['cg_student_nonce'] )
			&& \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['cg_student_nonce'] ) ), 'cg_save_student' ) ) {

			$name  = \sanitize_text_field( $_POST['student_name'] ?? '' );
			$email = \sanitize_email( $_POST['email'] ?? '' );
			if ( ! $name ) {
				$errors[] = 'Student name is required.';
			}
			if ( ! $email ) {
				$errors[] = 'Email is required.';
			}

			if ( empty( $errors ) ) {
				$new_extra = array();
				$ex_keys   = (array) ( $_POST['extra_key'] ?? array() );
				$ex_vals   = (array) ( $_POST['extra_val'] ?? array() );
				foreach ( $ex_keys as $i => $k ) {
					$k = \sanitize_key( $k );
					$v = \sanitize_text_field( $ex_vals[ $i ] ?? '' );
					if ( $k && $v !== '' ) {
						$new_extra[ $k ] = $v;
					}
				}

				$data = array(
					'student_name'     => $name,
					'email'            => $email,
					'phone'            => \sanitize_text_field( $_POST['phone'] ?? '' ),
					'school_name'      => \sanitize_text_field( $_POST['school_name'] ?? '' ),
					'certificate_type' => \sanitize_text_field( $_POST['certificate_type'] ?? '' ),
					'issue_date'       => \sanitize_text_field( $_POST['issue_date'] ?? '' ) ?: null,
					'year'             => function_exists( 'cg_year_from_issue_date' ) ? cg_year_from_issue_date( \sanitize_text_field( $_POST['issue_date'] ?? '' ) ?: null ) : null,
					'enrollment_date'  => \sanitize_text_field( $_POST['enrollment_date'] ?? '' ) ?: null,
					'graduation_date'  => \sanitize_text_field( $_POST['graduation_date'] ?? '' ) ?: null,
					'status'           => in_array( $_POST['status'] ?? '', array( 'active', 'graduated', 'transferred', 'dropped' ), true ) ? \sanitize_key( $_POST['status'] ) : 'active',
					'extra_fields'     => ! empty( $new_extra ) ? wp_json_encode( $new_extra ) : null,
					'updated_at'       => current_time( 'mysql' ),
				);

				if ( $id && $row ) {
					$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$message = 'Student updated.';
					$row     = array_merge( $row, $data );
					$extra   = $new_extra;
				} else {
					$data['created_at'] = current_time( 'mysql' );
					$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$id      = (int) $wpdb->insert_id;
					$row     = array_merge( $data, array( 'id' => $id ) );
					$extra   = $new_extra;
					$message = 'Student added.';
				}

				// Phase 6: sync to WP Dynamic Tags if a wp_post_id is linked
				$wp_post_id = (int) ( $row['wp_post_id'] ?? 0 );
				if ( $wp_post_id > 0 && function_exists( 'cg_sync_to_dynamic_tags' ) ) {
					cg_sync_to_dynamic_tags( $wp_post_id );
				}
			}
		}

		$list_url = \admin_url( 'admin.php?page=' . $this->slug );
		?>
		<div class="wrap">
			<h1><?php echo $id ? 'Edit Student' : 'Add New Student'; ?></h1>
			<a href="<?php echo \esc_url( $list_url ); ?>">← Back to Students</a>

			<?php
			foreach ( $errors as $e ) :
				?>
				<div class="notice notice-error"><p><?php echo \esc_html( $e ); ?></p></div><?php endforeach; ?>
			<?php
			if ( $message ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php echo \esc_html( $message ); ?></p></div><?php endif; ?>

			<form method="post" style="max-width:700px; margin-top:20px;">
				<?php \wp_nonce_field( 'cg_save_student', 'cg_student_nonce' ); ?>
				<table class="form-table">
					<tr><th><label for="student_name">Name <span style="color:red">*</span></label></th>
						<td><input type="text" id="student_name" name="student_name" class="regular-text" required value="<?php echo \esc_attr( $row['student_name'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="email">Email <span style="color:red">*</span></label></th>
						<td><input type="email" id="email" name="email" class="regular-text" required value="<?php echo \esc_attr( $row['email'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="phone">Phone</label></th>
						<td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo \esc_attr( $row['phone'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="school_name">School Name</label></th>
						<td><input type="text" id="school_name" name="school_name" class="regular-text" value="<?php echo \esc_attr( $row['school_name'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="certificate_type">Certificate Type</label></th>
						<td><input type="text" id="certificate_type" name="certificate_type" class="regular-text" value="<?php echo \esc_attr( $row['certificate_type'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="issue_date">Issue Date</label></th>
						<td><input type="date" id="issue_date" name="issue_date" value="<?php echo \esc_attr( $row['issue_date'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="enrollment_date">Enrollment Date</label></th>
						<td><input type="date" id="enrollment_date" name="enrollment_date" value="<?php echo \esc_attr( $row['enrollment_date'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="graduation_date">Graduation Date</label></th>
						<td><input type="date" id="graduation_date" name="graduation_date" value="<?php echo \esc_attr( $row['graduation_date'] ?? '' ); ?>"></td></tr>
					<tr><th><label for="status">Status</label></th>
						<td><select id="status" name="status">
							<?php foreach ( array( 'active', 'graduated', 'transferred', 'dropped' ) as $s ) : ?>
								<option value="<?php echo \esc_attr( $s ); ?>" <?php \selected( $row['status'] ?? 'active', $s ); ?>><?php echo \esc_html( ucfirst( $s ) ); ?></option>
							<?php endforeach; ?>
						</select></td></tr>
				</table>

				<?php if ( ! empty( $row['serial_number'] ) ) : ?>
					<p><strong>Serial Number:</strong> <code><?php echo \esc_html( $row['serial_number'] ); ?></code></p>
				<?php endif; ?>

				<h3>Extra Fields</h3>
				<p class="description">Custom key-value pairs (e.g. grade, parent_name). Stored as JSON.</p>
				<table class="widefat" id="cg-extra-fields-table" style="max-width:500px; margin-bottom:8px;">
					<thead><tr><th>Key</th><th>Value</th><th></th></tr></thead>
					<tbody id="cg-extra-tbody">
						<?php foreach ( $extra as $k => $v ) : ?>
						<tr>
							<td><input type="text" name="extra_key[]" value="<?php echo \esc_attr( $k ); ?>" class="regular-text"></td>
							<td><input type="text" name="extra_val[]" value="<?php echo \esc_attr( $v ); ?>" class="regular-text"></td>
							<td><button type="button" class="button button-small cg-remove-row">✕</button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<button type="button" class="button" id="cg-add-extra-row">+ Add Field</button>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php echo $id ? 'Update Student' : 'Add Student'; ?></button>
					<a href="<?php echo \esc_url( $list_url ); ?>" class="button">Cancel</a>
				</p>
			</form>
		</div>
		<script>
		document.getElementById('cg-add-extra-row').addEventListener('click', function() {
			var tbody = document.getElementById('cg-extra-tbody');
			var tr = document.createElement('tr');
			var td1 = document.createElement('td');
			var td2 = document.createElement('td');
			var td3 = document.createElement('td');
			var inp1 = document.createElement('input');
			inp1.type = 'text'; inp1.name = 'extra_key[]'; inp1.className = 'regular-text'; inp1.placeholder = 'key';
			var inp2 = document.createElement('input');
			inp2.type = 'text'; inp2.name = 'extra_val[]'; inp2.className = 'regular-text'; inp2.placeholder = 'value';
			var btn = document.createElement('button');
			btn.type = 'button'; btn.className = 'button button-small'; btn.textContent = '✕';
			btn.addEventListener('click', function(){ tr.parentNode.removeChild(tr); });
			td1.appendChild(inp1); td2.appendChild(inp2); td3.appendChild(btn);
			tr.appendChild(td1); tr.appendChild(td2); tr.appendChild(td3);
			tbody.appendChild(tr);
		});
		document.querySelectorAll('.cg-remove-row').forEach(function(btn){
			btn.addEventListener('click', function(){ this.closest('tr').remove(); });
		});
		</script>
		<?php
	}

	// ── AJAX: per-student send email. ───────────────────────────────────────

	/**
	 * AJAX handler: send a certificate email to a single student by cg_students.id.
	 *
	 * Nonce: cg_student_send_email. Capability: manage_options.
	 * Bypasses the send_email opt-out flag (admin-initiated send).
	 *
	 * @return void Outputs JSON and exits.
	 */
	public function send_email_ajax(): void {
		\check_ajax_referer( 'cg_student_send_email', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
		}

		$id = \absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			\wp_send_json_error( array( 'message' => 'Invalid student ID.' ) );
		}

		global $wpdb;
		$table = CustomTables::instance()->get_table( $this->table_key );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d LIMIT 1", $id ), \ARRAY_A );

		if ( ! $row ) {
			\wp_send_json_error( array( 'message' => 'Student not found.' ) );
		}

		$email     = \sanitize_email( $row['email'] ?? '' );
		$name      = \sanitize_text_field( $row['student_name'] ?? '' );
		$cert_type = \sanitize_text_field( $row['certificate_type'] ?? '' );

		if ( ! \is_email( $email ) ) {
			\wp_send_json_error( array( 'message' => 'Student has no valid email address.' ) );
		}

		// Resolve legacy anchor row — certificate_generator_send_email() expects its id.
		$cg_table = $wpdb->prefix . 'certificate_generator';
		$cg_id    = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT id FROM $cg_table WHERE email = %s ORDER BY id DESC LIMIT 1", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $cg_id ) {
			// No legacy anchor — insert a minimal row so the send function can resolve the email.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$cg_table,
				array(
					'email'            => $email,
					'student_name'     => $name,
					'certificate_type' => $cert_type,
					'issued_at'        => \current_time( 'mysql' ),
					'generated_via'    => 'manual',
				)
			);
			$cg_id = (int) $wpdb->insert_id;
		}

		if ( ! $cg_id ) {
			\wp_send_json_error( array( 'message' => 'Could not create certificate record. Check database permissions.' ) );
		}

		if ( ! function_exists( 'certificate_generator_send_email' ) ) {
			\wp_send_json_error( array( 'message' => 'Email send function unavailable.' ) );
		}

		$success = certificate_generator_send_email( $cg_id );

		if ( $success ) {
			\wp_send_json_success( array( 'message' => 'Email sent successfully.' ) );
		} else {
			\wp_send_json_error( array( 'message' => 'Email send failed. Check server email configuration and error logs.' ) );
		}
	}
}
