<?php
/**
 * Admin Email Logs Page for Certificate Generator
 *
 * @package Certificate Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Render email logs page
function certificate_generator_email_logs_page() {
	// Handle bulk actions
	if ( isset( $_POST['action'] ) && $_POST['action'] === 'delete_logs' && isset( $_POST['log_ids'] ) ) {
		check_admin_referer( 'bulk_delete_logs' );
		certificate_generator_delete_email_logs( $_POST['log_ids'] );
		echo '<div class="notice notice-success"><p>' . __( 'Selected logs deleted successfully.', 'certificate-generator' ) . '</p></div>';
	}

	// Get filter parameters
	$current_page     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$per_page         = 20;
	$post_type_filter = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '';
	$status_filter    = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
	$search           = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
	$date_from        = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
	$date_to          = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

	// Get logs
	$logs_data = certificate_generator_get_email_logs(
		array(
			'page'      => $current_page,
			'per_page'  => $per_page,
			'post_type' => $post_type_filter,
			'status'    => $status_filter,
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
		)
	);

	$logs        = $logs_data['logs'];
	$total_pages = $logs_data['pages'];
	$total_count = $logs_data['total'];

	// Get statistics
	$stats = certificate_generator_get_email_stats();

	?>
	<div class="wrap">
		<h1><?php _e( 'Certificate Email Logs', 'certificate-generator' ); ?></h1>

		<!-- Statistics Cards -->
		<div class="email-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
			<div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: center;">
				<h3 style="margin: 0 0 10px; color: #2c3e50;"><?php echo number_format( $stats['total_sent'] ); ?></h3>
				<p style="margin: 0; color: #7f8c8d;"><?php _e( 'Total Sent', 'certificate-generator' ); ?></p>
			</div>
			<div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: center;">
				<h3 style="margin: 0 0 10px; color: #e74c3c;"><?php echo number_format( $stats['total_failed'] ); ?></h3>
				<p style="margin: 0; color: #7f8c8d;"><?php _e( 'Failed', 'certificate-generator' ); ?></p>
			</div>
			<div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: center;">
				<h3 style="margin: 0 0 10px; color: #27ae60;"><?php echo $stats['success_rate']; ?>%</h3>
				<p style="margin: 0; color: #7f8c8d;"><?php _e( 'Success Rate', 'certificate-generator' ); ?></p>
			</div>
			<div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: center;">
				<h3 style="margin: 0 0 10px; color: #3498db;"><?php echo number_format( $stats['today'] ); ?></h3>
				<p style="margin: 0; color: #7f8c8d;"><?php _e( 'Today', 'certificate-generator' ); ?></p>
			</div>
			<div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: center;">
				<h3 style="margin: 0 0 10px; color: #9b59b6;"><?php echo number_format( $stats['this_week'] ); ?></h3>
				<p style="margin: 0; color: #7f8c8d;"><?php _e( 'This Week', 'certificate-generator' ); ?></p>
			</div>
			<div class="stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: center;">
				<h3 style="margin: 0 0 10px; color: #f39c12;"><?php echo number_format( $stats['this_month'] ); ?></h3>
				<p style="margin: 0; color: #7f8c8d;"><?php _e( 'This Month', 'certificate-generator' ); ?></p>
			</div>
		</div>

		<!-- Filters -->
		<div class="tablenav top">
			<form method="get" style="display: inline-block;">
				<input type="hidden" name="page" value="certificate-email-logs">

				<select name="post_type">
					<option value=""><?php _e( 'All Post Types', 'certificate-generator' ); ?></option>
					<option value="students" <?php selected( $post_type_filter, 'students' ); ?>><?php _e( 'Students', 'certificate-generator' ); ?></option>
					<option value="teachers" <?php selected( $post_type_filter, 'teachers' ); ?>><?php _e( 'Teachers', 'certificate-generator' ); ?></option>
					<option value="schools" <?php selected( $post_type_filter, 'schools' ); ?>><?php _e( 'Schools', 'certificate-generator' ); ?></option>
				</select>

				<select name="status">
					<option value=""><?php _e( 'All Statuses', 'certificate-generator' ); ?></option>
					<option value="sent" <?php selected( $status_filter, 'sent' ); ?>><?php _e( 'Sent', 'certificate-generator' ); ?></option>
					<option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php _e( 'Failed', 'certificate-generator' ); ?></option>
				</select>

				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php _e( 'From Date', 'certificate-generator' ); ?>">
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php _e( 'To Date', 'certificate-generator' ); ?>">

				<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php _e( 'Search email or name...', 'certificate-generator' ); ?>">

				<input type="submit" class="button" value="<?php _e( 'Filter', 'certificate-generator' ); ?>">

				<?php if ( $post_type_filter || $status_filter || $search || $date_from || $date_to ) : ?>
					<a href="<?php echo admin_url( 'admin.php?page=certificate-email-logs' ); ?>" class="button"><?php _e( 'Clear Filters', 'certificate-generator' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="alignright">
				<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=certificate-email-logs&action=export' ), 'export_logs' ); ?>" class="button"><?php _e( 'Export CSV', 'certificate-generator' ); ?></a>
			</div>
		</div>

		<!-- Logs Table -->
		<form method="post">
			<?php wp_nonce_field( 'bulk_delete_logs' ); ?>
			<input type="hidden" name="action" value="delete_logs">

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-1">
						</td>
						<th><?php _e( 'Date', 'certificate-generator' ); ?></th>
						<th><?php _e( 'Recipient', 'certificate-generator' ); ?></th>
						<th><?php _e( 'Certificate Type', 'certificate-generator' ); ?></th>
						<th><?php _e( 'Post Type', 'certificate-generator' ); ?></th>
						<th><?php _e( 'Subject', 'certificate-generator' ); ?></th>
						<th><?php _e( 'Status', 'certificate-generator' ); ?></th>
						<th><?php _e( 'Actions', 'certificate-generator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="8" style="text-align: center; padding: 40px;">
								<?php _e( 'No email logs found.', 'certificate-generator' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<th class="check-column">
									<input type="checkbox" name="log_ids[]" value="<?php echo esc_attr( $log->id ); ?>">
								</th>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->sent_at ) ) ); ?></td>
								<td>
									<strong><?php echo esc_html( $log->recipient_name ?: $log->recipient_email ); ?></strong>
									<?php if ( $log->recipient_name ) : ?>
										<br><small><?php echo esc_html( $log->recipient_email ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log->certificate_type ); ?></td>
								<td>
									<span class="post-type-badge" style="background: #0073aa; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">
										<?php echo esc_html( ucfirst( $log->post_type ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log->email_subject ); ?></td>
								<td>
									<?php if ( $log->status === 'sent' ) : ?>
										<span class="status-sent" style="color: #27ae60; font-weight: bold;">✓ <?php _e( 'Sent', 'certificate-generator' ); ?></span>
									<?php else : ?>
										<span class="status-failed" style="color: #e74c3c; font-weight: bold;">✗ <?php _e( 'Failed', 'certificate-generator' ); ?></span>
										<?php if ( $log->error_message ) : ?>
											<br><small style="color: #666;" title="<?php echo esc_attr( $log->error_message ); ?>"><?php echo esc_html( wp_trim_words( $log->error_message, 5 ) ); ?></small>
										<?php endif; ?>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo admin_url( 'post.php?post=' . $log->certificate_id . '&action=edit' ); ?>" class="button button-small"><?php _e( 'View Certificate', 'certificate-generator' ); ?></a>
									<?php if ( $log->status === 'failed' ) : ?>
										<button type="button" class="button button-small resend-email" data-post-id="<?php echo esc_attr( $log->certificate_id ); ?>"><?php _e( 'Resend', 'certificate-generator' ); ?></button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $logs ) ) : ?>
				<div class="tablenav bottom">
					<div class="alignleft actions">
						<input type="submit" class="button action" value="<?php _e( 'Delete Selected', 'certificate-generator' ); ?>" onclick="return confirm('<?php _e( 'Are you sure you want to delete the selected logs?', 'certificate-generator' ); ?>')">
					</div>

					<?php
					// Pagination
					if ( $total_pages > 1 ) {
						$pagination_args = array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $current_page,
						);
						echo '<div class="tablenav-pages">';
						echo paginate_links( $pagination_args );
						echo '</div>';
					}
					?>
				</div>
			<?php endif; ?>
		</form>
	</div>

	<script>
	jQuery(document).ready(function($) {
		// Select all checkbox functionality
		$('#cb-select-all-1').on('change', function() {
			$('input[name="log_ids[]"]').prop('checked', this.checked);
		});

		// Resend email functionality
		$('.resend-email').on('click', function() {
			var button = $(this);
			var postId = button.data('post-id');

			button.prop('disabled', true).text('<?php _e( 'Sending...', 'certificate-generator' ); ?>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'certificate_generator_send_single_email',
					post_id: postId,
					nonce: '<?php echo wp_create_nonce( 'certificate_generator_send_email' ); ?>'
				},
				success: function(response) {
					if (response.success) {
						button.text('<?php _e( 'Sent!', 'certificate-generator' ); ?>').css('color', '#27ae60');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						button.prop('disabled', false).text('<?php _e( 'Resend', 'certificate-generator' ); ?>');
						alert('<?php _e( 'Failed to send email: ', 'certificate-generator' ); ?>' + (response.data.message || '<?php _e( 'Unknown error', 'certificate-generator' ); ?>'));
					}
				},
				error: function() {
					button.prop('disabled', false).text('<?php _e( 'Resend', 'certificate-generator' ); ?>');
					alert('<?php _e( 'An error occurred. Please try again.', 'certificate-generator' ); ?>');
				}
			});
		});
	});
	</script>
	<?php
}

// Handle CSV export
add_action( 'admin_init', 'certificate_generator_handle_export_logs' );
function certificate_generator_handle_export_logs() {
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'export' && isset( $_GET['page'] ) && $_GET['page'] === 'certificate-email-logs' ) {
		check_admin_referer( 'export_logs' );

		// Get all logs for export
		$logs_data = certificate_generator_get_email_logs(
			array(
				'per_page'  => -1,
				'post_type' => isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '',
				'status'    => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
				'search'    => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
				'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '',
				'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '',
			)
		);

		$logs = $logs_data['logs'];

		// Set headers for CSV download
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="certificate-email-logs-' . date( 'd-m-Y' ) . '.csv"' );

		// Create CSV output
		$output = fopen( 'php://output', 'w' );

		// CSV headers
		fputcsv(
			$output,
			array(
				'Date',
				'Recipient Name',
				'Recipient Email',
				'Certificate Type',
				'Post Type',
				'Subject',
				'Status',
				'Error Message',
			)
		);

		// CSV data
		foreach ( $logs as $log ) {
			fputcsv(
				$output,
				array(
					$log->sent_at,
					$log->recipient_name,
					$log->recipient_email,
					$log->certificate_type,
					$log->post_type,
					$log->email_subject,
					$log->status,
					$log->error_message,
				)
			);
		}

		fclose( $output );
		exit;
	}
}

// Delete email logs
function certificate_generator_delete_email_logs( $log_ids ) {
	global $wpdb;

	if ( empty( $log_ids ) || ! is_array( $log_ids ) ) {
		return false;
	}

	$table_name   = $wpdb->prefix . 'cert_email_logs';
	$log_ids      = array_map( 'intval', $log_ids );
	$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );

	return $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $table_name WHERE id IN ($placeholders)",
			$log_ids
		)
	);
}

// Handle single email resend via AJAX
add_action( 'wp_ajax_certificate_generator_send_single_email', 'certificate_generator_handle_single_email_resend' );
function certificate_generator_handle_single_email_resend() {
	// Verify nonce
	if ( ! wp_verify_nonce( $_POST['nonce'], 'certificate_generator_send_email' ) ) {
		wp_die( 'Security check failed' );
	}

	// Check permissions
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Insufficient permissions' );
	}

	$post_id = intval( $_POST['post_id'] );

	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => 'Invalid post ID' ) );
	}

	// Include email functions if not already loaded
	if ( ! function_exists( 'certificate_generator_send_email' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '../Email/functions.php';
	}

	// Send the email
	$result = certificate_generator_send_email( $post_id, true );

	if ( $result ) {
		wp_send_json_success( array( 'message' => 'Email sent successfully' ) );
	} else {
		wp_send_json_error( array( 'message' => 'Failed to send email' ) );
	}
}
?>