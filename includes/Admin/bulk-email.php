<?php
/**
 * Admin Page for Bulk Email Sending
 *
 * @package Certificate Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle AJAX requests
add_action( 'wp_ajax_cert_start_bulk_send', 'certificate_generator_ajax_start_bulk_send' );
add_action( 'wp_ajax_cert_get_queue_progress', 'certificate_generator_ajax_get_queue_progress' );
add_action( 'wp_ajax_cert_pause_queue', 'certificate_generator_ajax_pause_queue' );
add_action( 'wp_ajax_cert_resume_queue', 'certificate_generator_ajax_resume_queue' );
add_action( 'wp_ajax_cert_clear_queue', 'certificate_generator_ajax_clear_queue' );

// NEW: Filter-related AJAX handlers
add_action( 'wp_ajax_cert_get_filter_options', 'certificate_generator_ajax_get_filter_options' );
add_action( 'wp_ajax_cert_preview_recipients', 'certificate_generator_ajax_preview_recipients' );
add_action( 'wp_ajax_cert_send_to_filtered', 'certificate_generator_ajax_send_to_filtered' );

function certificate_generator_ajax_start_bulk_send() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : '';

	if ( ! in_array( $post_type, array( 'students', 'teachers', 'schools' ) ) ) {
		wp_send_json_error( 'Invalid post type' );
	}

	$result = certificate_generator_start_bulk_send( $post_type );

	// If start_bulk_send reports failure (e.g., no emails queued), return JSON error so UI can show details
	if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] === false ) {
		wp_send_json_error( $result );
	}

	wp_send_json_success( $result );
}

function certificate_generator_ajax_get_queue_progress() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	$progress = certificate_generator_get_queue_progress();

	wp_send_json_success( $progress );
}

function certificate_generator_ajax_pause_queue() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	certificate_generator_pause_queue();

	wp_send_json_success( 'Queue paused' );
}

function certificate_generator_ajax_resume_queue() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	certificate_generator_resume_queue();

	wp_send_json_success( 'Queue resumed' );
}

function certificate_generator_ajax_clear_queue() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$cleared = (int) certificate_generator_clear_queue();

	wp_send_json_success(
		array(
			'cleared' => $cleared,
			'message' => sprintf(
				/* translators: %d: number of emails */
				_n( '%d email cleared from queue', '%d emails cleared from queue', $cleared, 'certificate-generator' ),
				$cleared
			),
		)
	);
}

// Render bulk send page
function certificate_generator_bulk_send_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'certificate-generator' ) );
	}

	// Plan gate: bulk email sending requires Pro or Business
	$license_block = ( class_exists( 'CG_License_Manager' ) && CG_License_Manager::is_free() );
	if ( $license_block ) {
		$license_url = admin_url( 'admin.php?page=certificate_generator_settings&tab=license' );
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Bulk Send Certificates', 'certificate-generator' ) . '</h1>';
		echo '<div class="notice notice-warning" style="padding:20px 24px;border-left:4px solid #f59e0b;background:#fffbeb;border-radius:4px;">';
		echo '<h2 style="margin:0 0 8px;color:#92400e;">🔒 ' . esc_html__( 'Pro Feature', 'certificate-generator' ) . '</h2>';
		echo '<p style="margin:0 0 12px;">' . esc_html__( 'Bulk certificate email sending requires the Pro or Business plan.', 'certificate-generator' ) . '</p>';
		echo '<a href="' . esc_url( $license_url ) . '" class="button button-primary">' . esc_html__( 'Upgrade Your License →', 'certificate-generator' ) . '</a>';
		echo '</div>';
		// Do not return: allow viewing queue status and diagnostics even on Free plan (helps debugging/local testing).
	}

	$progress    = function_exists( 'certificate_generator_get_queue_progress' ) ? certificate_generator_get_queue_progress() : array();
	$rate_status = function_exists( 'certificate_generator_get_rate_limit_status' ) ? certificate_generator_get_rate_limit_status() : array();

	// Defensive defaults so missing keys never produce PHP notices or unescaped output.
	$stats     = array_merge(
		array(
			'total'   => 0,
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
		),
		( isset( $progress['stats'] ) && is_array( $progress['stats'] ) ) ? $progress['stats'] : array()
	);
	$pct       = intval( $progress['progress_percentage'] ?? 0 );
	$is_paused = ! empty( $progress['is_paused'] );
	$eta       = isset( $progress['eta'] ) ? (string) $progress['eta'] : '';
	$eta_human = isset( $progress['eta_human'] ) ? (string) $progress['eta_human'] : '';

	$rate_usage  = ( isset( $rate_status['usage'] ) && is_array( $rate_status['usage'] ) ) ? $rate_status['usage'] : array();
	$rate_limits = ( isset( $rate_status['limits'] ) && is_array( $rate_status['limits'] ) ) ? $rate_status['limits'] : array();
	$can_send    = ( isset( $rate_status['can_send'] ) && is_array( $rate_status['can_send'] ) ) ? $rate_status['can_send'] : array();

	$per_hour     = intval( $rate_limits['emails_per_hour'] ?? 80 );
	$last_hour    = intval( $rate_usage['last_hour'] ?? 0 );
	$hourly_pct   = intval( $rate_usage['hourly_percentage'] ?? 0 );
	$remaining    = intval( $rate_usage['hourly_remaining'] ?? $per_hour );
	$can_send_now = ! empty( $can_send['can_send'] );
	$cant_reason  = esc_html( $can_send['reason'] ?? '' );
	$wait_secs    = intval( $can_send['wait_seconds'] ?? 0 );
	$wait_fmt     = function_exists( 'certificate_generator_format_wait_time' ) ? certificate_generator_format_wait_time( $wait_secs ) : '';

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bulk Send Certificates', 'certificate-generator' ); ?></h1>

		<div class="card" style="max-width: 1000px;">
			<h2>📊 <?php esc_html_e( 'Current Queue Status', 'certificate-generator' ); ?></h2>

			<table class="widefat" style="margin-bottom: 20px;">
				<tr>
					<th><?php esc_html_e( 'Total in Queue', 'certificate-generator' ); ?></th>
					<td><strong><?php echo esc_html( number_format_i18n( intval( $stats['total'] ) ) ); ?></strong></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Pending', 'certificate-generator' ); ?></th>
					<td><span style="color: #d63638;"><?php echo esc_html( number_format_i18n( intval( $stats['pending'] ) ) ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Sent', 'certificate-generator' ); ?></th>
					<td><span style="color: #00a32a;"><?php echo esc_html( number_format_i18n( intval( $stats['sent'] ) ) ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Failed', 'certificate-generator' ); ?></th>
					<td><span style="color: #d63638;"><?php echo esc_html( number_format_i18n( intval( $stats['failed'] ) ) ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Progress', 'certificate-generator' ); ?></th>
					<td>
						<div style="background: #f0f0f1; border-radius: 3px; height: 24px; position: relative;">
							<div style="background: #00a32a; height: 100%; width: <?php echo esc_attr( $pct ); ?>%; border-radius: 3px;"></div>
							<span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold;">
								<?php echo esc_html( $pct ); ?>%
							</span>
						</div>
					</td>
				</tr>
				<?php if ( $eta ) : ?>
				<tr>
					<th><?php esc_html_e( 'Estimated Completion', 'certificate-generator' ); ?></th>
					<td>
						<?php echo esc_html( $eta_human ); ?> <?php esc_html_e( 'remaining', 'certificate-generator' ); ?>
						<br><small><?php echo esc_html( $eta ); ?></small>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th><?php esc_html_e( 'Queue Status', 'certificate-generator' ); ?></th>
					<td>
						<?php if ( $is_paused ) : ?>
							<span style="color: #d63638; font-weight: bold;">⏸️ <?php esc_html_e( 'PAUSED', 'certificate-generator' ); ?></span>
						<?php else : ?>
							<span style="color: #00a32a; font-weight: bold;">▶️ <?php esc_html_e( 'RUNNING', 'certificate-generator' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2>⚡ <?php esc_html_e( 'Rate Limiting Status', 'certificate-generator' ); ?></h2>
			<table class="widefat" style="margin-bottom: 20px;">
				<tr>
					<th><?php esc_html_e( 'Emails Sent (Last Hour)', 'certificate-generator' ); ?></th>
					<td>
						<?php echo esc_html( number_format_i18n( $last_hour ) ); ?> / <?php echo esc_html( number_format_i18n( $per_hour ) ); ?>
						(<?php echo esc_html( $hourly_pct ); ?>%)
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Remaining This Hour', 'certificate-generator' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $remaining ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Can Send Now', 'certificate-generator' ); ?></th>
					<td>
						<?php if ( $can_send_now ) : ?>
							<span style="color: #00a32a;">✅ <?php esc_html_e( 'Yes', 'certificate-generator' ); ?></span>
						<?php else : ?>
							<span style="color: #d63638;">❌ <?php esc_html_e( 'No', 'certificate-generator' ); ?>
							<?php
							if ( $cant_reason ) :
								?>
								— <?php echo $cant_reason; ?><?php endif; ?></span>
							<?php if ( $wait_fmt ) : ?>
							<br><small>
								<?php
								echo esc_html(
									sprintf(
									/* translators: %s: formatted wait time */
										__( 'Wait: %s', 'certificate-generator' ),
										$wait_fmt
									)
								);
								?>
							</small>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2>🚀 <?php esc_html_e( 'Start Bulk Send with Advanced Filters', 'certificate-generator' ); ?></h2>
			<p><?php esc_html_e( 'Use filters to precisely target which certificates to send. Preview recipients before sending.', 'certificate-generator' ); ?></p>

			<!-- Filter and Preview Container -->
			<div class="cert-filter-container">
				<!-- Left Panel: Filters -->
				<div class="cert-filter-panel">
					<form id="bulk-send-form">

						<!-- Post Type Filter -->
						<div class="cert-filter-section">
							<h3><?php esc_html_e( 'Post Types', 'certificate-generator' ); ?></h3>
							<select name="post_types[]" id="cert-filter-post-types" class="cert-filter-select" multiple size="3">
								<option value="students" selected><?php esc_html_e( 'Students', 'certificate-generator' ); ?></option>
								<option value="teachers" selected><?php esc_html_e( 'Teachers', 'certificate-generator' ); ?></option>
								<option value="schools" selected><?php esc_html_e( 'Schools', 'certificate-generator' ); ?></option>
							</select>
							<p class="cert-help-text"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple', 'certificate-generator' ); ?></p>
						</div>

						<!-- School Filter -->
						<div class="cert-filter-section">
							<h3><?php esc_html_e( 'Filter by School', 'certificate-generator' ); ?></h3>
							<select name="schools[]" id="cert-filter-schools" class="cert-filter-select" multiple size="5">
								<!-- Populated via AJAX -->
							</select>
							<p class="cert-help-text"><?php esc_html_e( 'Leave empty to include all schools', 'certificate-generator' ); ?></p>
						</div>

						<!-- Certificate Type Filter -->
						<div class="cert-filter-section">
							<h3><?php esc_html_e( 'Filter by Certificate Type', 'certificate-generator' ); ?></h3>
							<select name="certificate_types[]" id="cert-filter-certificate-types" class="cert-filter-select" multiple size="5">
								<!-- Populated via AJAX -->
							</select>
							<p class="cert-help-text"><?php esc_html_e( 'Leave empty to include all types', 'certificate-generator' ); ?></p>
						</div>

						<!-- Year Filter -->
						<div class="cert-filter-section">
							<h3><?php esc_html_e( 'Filter by Year', 'certificate-generator' ); ?></h3>
							<select name="year[]" id="cert-filter-year" class="cert-filter-select" multiple size="4">
								<!-- Populated via AJAX -->
							</select>
							<p class="cert-help-text"><?php esc_html_e( 'Leave empty to include all years', 'certificate-generator' ); ?></p>
						</div>

						<!-- Advanced: Issue Date Range -->
						<div class="cert-filter-section">
							<h3>
								<a href="#" id="cert-toggle-date-range" style="text-decoration:none;">
									&#9654; <?php esc_html_e( 'Advanced: Issue Date Range', 'certificate-generator' ); ?>
								</a>
							</h3>
							<div id="cert-date-range-fields" style="display:none;margin-top:8px;">
								<label style="display:block;margin-bottom:6px;">
									<?php esc_html_e( 'From', 'certificate-generator' ); ?>:
									<input type="date" name="date_from" id="cert-filter-date-from" class="cert-filter-input">
								</label>
								<label style="display:block;">
									<?php esc_html_e( 'To', 'certificate-generator' ); ?>:
									<input type="date" name="date_to" id="cert-filter-date-to" class="cert-filter-input">
								</label>
								<p class="cert-help-text"><?php esc_html_e( 'Filters on issue_date within the selected range', 'certificate-generator' ); ?></p>
							</div>
						</div>
						<script>
						document.getElementById('cert-toggle-date-range').addEventListener('click', function(e){
							e.preventDefault();
							var el = document.getElementById('cert-date-range-fields');
							el.style.display = el.style.display === 'none' ? 'block' : 'none';
							this.firstChild.textContent = el.style.display === 'none' ? '► ' : '▼ ';
						});
						</script>

						<!-- Email Status Filter -->
						<div class="cert-filter-section">
							<h3><?php esc_html_e( 'Email Status', 'certificate-generator' ); ?></h3>
							<div class="cert-checkbox-group">
								<label>
									<input type="checkbox" name="email_status[]" value="not_sent" checked>
									<?php esc_html_e( 'Not Sent Yet', 'certificate-generator' ); ?>
								</label>
								<label>
									<input type="checkbox" name="email_status[]" value="sent" checked>
									<?php esc_html_e( 'Already Sent', 'certificate-generator' ); ?>
								</label>
								<label>
									<input type="checkbox" name="email_status[]" value="no_email" checked>
									<?php esc_html_e( 'No Email Address', 'certificate-generator' ); ?>
								</label>
							</div>
						</div>

						<!-- Email Search Filter -->
						<div class="cert-filter-section">
							<h3><?php esc_html_e( 'Filter by Email', 'certificate-generator' ); ?></h3>
							<label>
								<?php esc_html_e( 'Search Email', 'certificate-generator' ); ?>:
								<input type="text" name="email_search" id="cert-filter-email-search" class="cert-filter-input" placeholder="<?php esc_html_e( 'e.g., @example.com', 'certificate-generator' ); ?>">
							</label>
							<p class="cert-help-text"><?php esc_html_e( 'Search for specific email patterns', 'certificate-generator' ); ?></p>
						</div>

						<!-- Email List Filter -->
						<div class="cert-filter-section">
							<label>
								<?php esc_html_e( 'Or Paste Email List', 'certificate-generator' ); ?>:
								<textarea name="email_list" id="cert-filter-email-list" class="cert-filter-textarea" placeholder="<?php esc_html_e( 'Paste emails (comma or newline separated)', 'certificate-generator' ); ?>"></textarea>
							</label>
							<p class="cert-help-text"><?php esc_html_e( 'One email per line or comma-separated', 'certificate-generator' ); ?></p>
						</div>

						<!-- Skip Already Sent Option -->
						<div class="cert-filter-section">
							<label>
								<input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent">
								<strong><?php esc_html_e( 'Skip certificates already sent', 'certificate-generator' ); ?></strong>
							</label>
							<p class="cert-help-text"><?php esc_html_e( 'Check this to avoid duplicate emails when sending', 'certificate-generator' ); ?></p>
						</div>

						<!-- Action Buttons -->
						<div class="cert-filter-buttons">
							<button type="button" class="cert-btn cert-btn-primary" id="cert-start-bulk-send" disabled>
								🚀 <?php esc_html_e( 'Start Bulk Send', 'certificate-generator' ); ?>
							</button>
							<button type="button" class="cert-btn cert-btn-secondary" id="cert-clear-filters">
								🔄 <?php esc_html_e( 'Clear Filters', 'certificate-generator' ); ?>
							</button>
						</div>

						<div id="bulk-send-result" style="margin-top: 20px;"></div>
					</form>
				</div>

				<!-- Right Panel: Preview -->
				<div class="cert-preview-panel">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
						<h3 style="margin: 0;"><?php esc_html_e( 'Recipients Preview', 'certificate-generator' ); ?></h3>
						<button type="button" class="cert-export-btn" id="cert-export-preview">
							📥 <?php esc_html_e( 'Export CSV', 'certificate-generator' ); ?>
						</button>
					</div>

					<div class="cert-preview-content">
						<div class="cert-loading"><?php esc_html_e( 'Loading preview...', 'certificate-generator' ); ?></div>
					</div>
				</div>
			</div>

			<h2>⚙️ Queue Controls</h2>
			<p>
				<button type="button" class="button" id="pause-queue" <?php echo $progress['is_paused'] ? 'disabled' : ''; ?>>
					⏸️ <?php esc_html_e( 'Pause Queue', 'certificate-generator' ); ?>
				</button>
				<button type="button" class="button" id="resume-queue" <?php echo ! $progress['is_paused'] ? 'disabled' : ''; ?>>
					▶️ <?php esc_html_e( 'Resume Queue', 'certificate-generator' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="refresh-status">
					🔄 <?php esc_html_e( 'Refresh Status', 'certificate-generator' ); ?>
				</button>
				<button type="button" class="button button-link-delete" id="clear-queue">
					🗑️ <?php esc_html_e( 'Clear Queue', 'certificate-generator' ); ?>
				</button>
			</p>
		</div>

		<div class="card" style="max-width: 1000px; margin-top: 20px;">
			<h2>ℹ️ How It Works</h2>
			<ul>
				<li><strong>Rate Limited:</strong> Sends ~80 emails per hour to stay within Hostinger's limits</li>
				<li><strong>Automatic:</strong> Runs in background via WP-Cron every 5 minutes</li>
				<li><strong>Safe:</strong> Retries failed emails up to 3 times</li>
				<li><strong>Unattended:</strong> You can close this page - sending continues automatically</li>
				<li><strong>Time Estimate:</strong> 1000 emails = ~12-14 hours</li>
			</ul>

			<h3>📋 Process:</h3>
			<ol>
				<li>Click "Start Bulk Send" and select post type</li>
				<li>All certificates are added to queue</li>
				<li>System sends them automatically in batches</li>
				<li>Check back here to monitor progress</li>
				<li>Get notified when complete</li>
			</ol>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		var cg_nonce = (typeof certFilterAjax !== 'undefined') ? certFilterAjax.nonce : '';

		function cg_queue_action(action, onSuccess) {
			$.post(ajaxurl, { action: action, nonce: cg_nonce }, function(response) {
				if (response && response.success) {
					onSuccess(response.data);
				} else {
					location.reload();
				}
			}).fail(function() { location.reload(); });
		}

		$('#pause-queue').on('click', function() {
			cg_queue_action('cert_pause_queue', function() { location.reload(); });
		});

		$('#resume-queue').on('click', function() {
			cg_queue_action('cert_resume_queue', function() { location.reload(); });
		});

		$('#clear-queue').on('click', function() {
			if (!confirm(<?php echo wp_json_encode( __( 'Are you sure you want to clear the queue? This cannot be undone.', 'certificate-generator' ) ); ?>)) {
				return;
			}
			cg_queue_action('cert_clear_queue', function(data) {
				var msg = (data && data.message) ? data.message : '';
				if (msg) { alert(msg); }
				location.reload();
			});
		});

		$('#refresh-status').on('click', function() { location.reload(); });

		// Auto-refresh every 30 seconds if queue is active
		<?php if ( intval( $stats['pending'] ) > 0 && ! $is_paused ) : ?>
		setInterval(function() { location.reload(); }, 30000);
		<?php endif; ?>
	});
	</script>
	<?php
}

/**
 * AJAX: Get filter options (schools, certificate types)
 */
function certificate_generator_ajax_get_filter_options() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	$option_type = isset( $_POST['option_type'] ) ? sanitize_text_field( $_POST['option_type'] ) : '';

	$data = array();

	switch ( $option_type ) {
		case 'schools':
			$data = certificate_generator_get_unique_schools();
			break;
		case 'certificate_types':
			$data = certificate_generator_get_unique_certificate_types();
			break;
		case 'years':
			$data = certificate_generator_get_unique_years();
			break;
		case 'emails':
			$data = certificate_generator_get_unique_emails();
			break;
		default:
			wp_send_json_error( array( 'message' => 'Invalid option type' ) );
	}

	wp_send_json_success( $data );
}

/**
 * AJAX: Preview recipients based on filters
 */
function certificate_generator_ajax_preview_recipients() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	// Get filter parameters
	$filters = isset( $_POST['filters'] ) ? $_POST['filters'] : array();

	// Sanitize filters
	$filters['post_types'] = isset( $filters['post_types'] ) && is_array( $filters['post_types'] )
		? array_map( 'sanitize_text_field', $filters['post_types'] )
		: array( 'students', 'teachers', 'schools' );

	$filters['schools'] = isset( $filters['schools'] ) && is_array( $filters['schools'] )
		? array_map( 'sanitize_text_field', $filters['schools'] )
		: array();

	$filters['certificate_types'] = isset( $filters['certificate_types'] ) && is_array( $filters['certificate_types'] )
		? array_map( 'sanitize_text_field', $filters['certificate_types'] )
		: array();

	$filters['email_status'] = isset( $filters['email_status'] ) && is_array( $filters['email_status'] )
		? array_map( 'sanitize_text_field', $filters['email_status'] )
		: array();

	$filters['emails'] = isset( $filters['emails'] ) && is_array( $filters['emails'] )
		? array_map( 'sanitize_email', $filters['emails'] )
		: array();

	$filters['email_search'] = isset( $filters['email_search'] )
		? sanitize_text_field( $filters['email_search'] )
		: '';

	$filters['skip_already_sent'] = filter_var( $filters['skip_already_sent'] ?? false, FILTER_VALIDATE_BOOLEAN );

	$filters['year'] = isset( $filters['year'] ) && is_array( $filters['year'] )
		? array_map( 'intval', $filters['year'] )
		: array();

	$filters['date_from'] = isset( $filters['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'] )
		? sanitize_text_field( $filters['date_from'] )
		: '';

	$filters['date_to'] = isset( $filters['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'] )
		? sanitize_text_field( $filters['date_to'] )
		: '';

	$filters['limit']  = min( 1000, max( 1, isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 100 ) );
	$filters['offset'] = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

	// Get recipients
	$recipients = certificate_generator_get_filtered_recipients( $filters );

	// Get statistics
	$statistics = certificate_generator_get_filter_statistics( $filters );

	wp_send_json_success(
		array(
			'recipients'      => $recipients,
			'statistics'      => $statistics,
			'filters_applied' => $filters,
		)
	);
}

/**
 * AJAX: Start bulk send with filters
 */
function certificate_generator_ajax_send_to_filtered() {
	check_ajax_referer( 'cert_bulk_send', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	// Get filter parameters
	$filters = isset( $_POST['filters'] ) ? $_POST['filters'] : array();

	// Sanitize filters (same as preview)
	$filters['post_types'] = isset( $filters['post_types'] ) && is_array( $filters['post_types'] )
		? array_map( 'sanitize_text_field', $filters['post_types'] )
		: array( 'students', 'teachers', 'schools' );

	$filters['schools'] = isset( $filters['schools'] ) && is_array( $filters['schools'] )
		? array_map( 'sanitize_text_field', $filters['schools'] )
		: array();

	$filters['certificate_types'] = isset( $filters['certificate_types'] ) && is_array( $filters['certificate_types'] )
		? array_map( 'sanitize_text_field', $filters['certificate_types'] )
		: array();

	$filters['email_status'] = isset( $filters['email_status'] ) && is_array( $filters['email_status'] )
		? array_map( 'sanitize_text_field', $filters['email_status'] )
		: array();

	$filters['emails'] = isset( $filters['emails'] ) && is_array( $filters['emails'] )
		? array_map( 'sanitize_email', $filters['emails'] )
		: array();

	$filters['email_search'] = isset( $filters['email_search'] )
		? sanitize_text_field( $filters['email_search'] )
		: '';

	$filters['skip_already_sent'] = filter_var( $filters['skip_already_sent'] ?? false, FILTER_VALIDATE_BOOLEAN );

	$filters['year'] = isset( $filters['year'] ) && is_array( $filters['year'] )
		? array_map( 'intval', $filters['year'] )
		: array();

	$filters['date_from'] = isset( $filters['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'] )
		? sanitize_text_field( $filters['date_from'] )
		: '';

	$filters['date_to'] = isset( $filters['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'] )
		? sanitize_text_field( $filters['date_to'] )
		: '';

	// Retrieve IDs in batches to avoid memory exhaustion on large datasets.
	// Hard cap: 5000 per request; admin must re-run filters for larger sets.
	$filters['limit']  = 5000;
	$filters['offset'] = 0;
	$recipients        = certificate_generator_get_filtered_recipients( $filters );

	if ( empty( $recipients ) ) {
		wp_send_json_error( array( 'message' => 'No recipients match the selected filters' ) );
	}

	// Build email → best recipient-data map (last row per email wins; all have same email).
	global $wpdb;
	$cg_table = $wpdb->prefix . 'certificate_generator';
	$by_email = array();
	foreach ( $recipients as $r ) {
		$email = isset( $r['email'] ) ? sanitize_email( $r['email'] ) : '';
		if ( $email && is_email( $email ) ) {
			$by_email[ $email ] = $r;
		}
	}

	if ( empty( $by_email ) ) {
		wp_send_json_error( array( 'message' => 'No valid email addresses in filtered recipients' ) );
	}

	$all_emails   = array_keys( $by_email );
	$placeholders = implode( ',', array_fill( 0, count( $all_emails ), '%s' ) );

	// Find which emails already have a cg record.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$existing = $wpdb->get_col(
		$wpdb->prepare( "SELECT DISTINCT email FROM $cg_table WHERE email IN ($placeholders)", $all_emails )
	);
	$existing = array_flip( $existing );

	// Auto-insert a cg record for any recipient without one (certificate not yet generated).
	$now      = current_time( 'mysql' );
	$inserted = 0;
	foreach ( $by_email as $email => $r ) {
		if ( isset( $existing[ $email ] ) ) {
			continue;
		}

		$cert_data = array(
			'student_name'     => $r['name'] ?? '',
			'email'            => $email,
			'certificate_type' => $r['certificate_type'] ?? '',
			'post_type'        => $r['post_type'] ?? 'students',
			'issue_date'       => $r['issue_date'] ?? $now,
		);

		$wpdb->insert(
			$cg_table,
			array(
				'student_name'     => sanitize_text_field( $cert_data['student_name'] ),
				'email'            => $email,
				'certificate_type' => sanitize_text_field( $cert_data['certificate_type'] ),
				'certificate_data' => wp_json_encode( $cert_data ),
				'pdf_path'         => '',
				'generated_via'    => 'bulk_queue',
				'issued_at'        => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $wpdb->insert_id ) {
			++$inserted;
		}
	}

	// Fetch all cg_ids for these emails (includes newly inserted rows).
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$cg_ids = array_map(
		'intval',
		$wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM $cg_table WHERE email IN ($placeholders) ORDER BY id ASC", $all_emails )
		)
	);

	if ( empty( $cg_ids ) ) {
		wp_send_json_error( array( 'message' => 'Failed to create or find certificate records for filtered recipients' ) );
	}

	$result       = certificate_generator_bulk_queue_emails( '', $cg_ids, $filters['skip_already_sent'] );
	$total_queued = $result['queued'];

	$response = array(
		'queued'       => $total_queued,
		'auto_created' => $inserted,
		'details'      => $result,
		'message'      => sprintf(
			'Queued %d certificate(s) for sending%s',
			$total_queued,
			$inserted ? " ({$inserted} record(s) auto-created)" : ''
		),
	);

	if ( $total_queued === 0 ) {
		wp_send_json_error( $response );
	}

	wp_send_json_success( $response );
}
?>
