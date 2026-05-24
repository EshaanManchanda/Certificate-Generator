<?php
/**
 * Certificate Generator — License Admin Tab
 *
 * Renders the "License" tab inside the plugin's Settings page.
 * Includes: plan badge, usage meter, license key input, activate/deactivate
 * buttons, plan comparison table, and upgrade CTAs.
 *
 * @package Certificate_Generator
 * @since   7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── AJAX: Activate license ────────────────────────────────────────────────

add_action( 'wp_ajax_cg_activate_license', 'cg_ajax_activate_license' );
add_action( 'wp_ajax_cg_deactivate_license', 'cg_ajax_deactivate_license' );

function cg_ajax_activate_license(): void {
	check_ajax_referer( 'cg_license_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'certificate-generator' ) ) );
	}

	$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
	$result = CG_License_Manager::activate_license( $key );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

function cg_ajax_deactivate_license(): void {
	check_ajax_referer( 'cg_license_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'certificate-generator' ) ) );
	}

	CG_License_Manager::deactivate_license();
	wp_send_json_success( array( 'message' => __( 'License deactivated. Plan reverted to Free.', 'certificate-generator' ) ) );
}

// ── Main render function ──────────────────────────────────────────────────

function cg_render_license_tab(): void {
	$plan        = CG_License_Manager::get_plan();
	$plan_label  = CG_License_Manager::get_plan_label();
	$usage       = CG_License_Manager::get_usage();
	$limit       = CG_License_Manager::get_limit();
	$expiry      = CG_License_Manager::get_expiry();
	$license_key = CG_License_Manager::get_license_key();
	$features    = CG_License_Manager::get_plan_features();
	$server_url  = CG_License_Manager::get_license_server();

	$last_heartbeat  = (string) get_option( 'cg_last_heartbeat', '' );
	$heartbeat_error = (string) get_transient( 'cg_heartbeat_error' );
	$is_expired      = $expiry && strtotime( $expiry ) < time();

	$plan_colors = array(
		'free'     => '#6b7280',
		'pro'      => '#3b82f6',
		'business' => '#7c3aed',
	);
	$plan_color  = $plan_colors[ $plan ] ?? '#6b7280';

	$usage_pct = $limit > 0 ? min( 100, round( ( $usage / $limit ) * 100 ) ) : 0;
	$bar_color = $usage_pct > 85 ? '#ef4444' : ( $usage_pct > 60 ? '#f59e0b' : '#22c55e' );

	$nonce       = wp_create_nonce( 'cg_license_nonce' );
	$upgrade_url = $server_url ? $server_url . '/pricing' : 'https://eshaanportfolio.vercel.app/';
	?>

	<style>
	.cg-license-wrap { max-width: 900px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

	/* ── Plan card ── */
	.cg-plan-card {
		display: flex; align-items: flex-start; gap: 24px;
		background: #fff; border: 1px solid #e5e7eb;
		border-radius: 12px; padding: 28px 32px; margin-bottom: 24px;
		box-shadow: 0 1px 4px rgba(0,0,0,.07);
	}
	.cg-plan-badge {
		display: inline-flex; align-items: center; justify-content: center;
		background: <?php echo esc_attr( $plan_color ); ?>;
		color: #fff; font-weight: 700; font-size: 13px;
		padding: 6px 16px; border-radius: 999px; white-space: nowrap;
		text-transform: uppercase; letter-spacing: .05em;
	}
	.cg-plan-info h2 { margin: 0 0 6px; font-size: 20px; }
	.cg-plan-meta { color: #6b7280; font-size: 13px; }

	/* ── Usage meter ── */
	.cg-usage-card {
		background: #fff; border: 1px solid #e5e7eb;
		border-radius: 12px; padding: 24px 32px; margin-bottom: 24px;
		box-shadow: 0 1px 4px rgba(0,0,0,.07);
	}
	.cg-usage-card h3 { margin: 0 0 14px; font-size: 15px; color: #374151; }
	.cg-meter-track {
		background: #f3f4f6; border-radius: 999px; height: 12px; position: relative; overflow: hidden;
	}
	.cg-meter-fill {
		background: <?php echo esc_attr( $bar_color ); ?>;
		height: 100%; border-radius: 999px;
		width: <?php echo esc_attr( $limit === 0 ? '100' : $usage_pct ); ?>%;
		transition: width .3s ease;
	}
	.cg-meter-label { margin-top: 8px; font-size: 13px; color: #6b7280; }

	/* ── License key box ── */
	.cg-license-box {
		background: #fff; border: 1px solid #e5e7eb;
		border-radius: 12px; padding: 24px 32px; margin-bottom: 24px;
		box-shadow: 0 1px 4px rgba(0,0,0,.07);
	}
	.cg-license-box h3 { margin: 0 0 14px; font-size: 15px; color: #374151; }
	.cg-license-input-row { display: flex; gap: 10px; align-items: center; }
	.cg-license-input-row input[type="text"] {
		flex: 1; padding: 9px 14px; border: 1px solid #d1d5db;
		border-radius: 8px; font-size: 14px;
	}
	.cg-btn {
		display: inline-flex; align-items: center; gap: 6px;
		padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600;
		cursor: pointer; border: none; text-decoration: none; transition: opacity .15s;
	}
	.cg-btn:hover { opacity: .88; }
	.cg-btn-primary  { background: #3b82f6; color: #fff; }
	.cg-btn-danger   { background: #ef4444; color: #fff; }
	.cg-btn-upgrade  { background: linear-gradient(135deg,#7c3aed,#3b82f6); color: #fff; font-size: 14px; padding: 11px 24px; }
	.cg-license-msg  { margin-top: 10px; font-size: 13px; }
	.cg-license-msg.success { color: #16a34a; }
	.cg-license-msg.error   { color: #dc2626; }

	/* ── Feature comparison table ── */
	.cg-compare-card {
		background: #fff; border: 1px solid #e5e7eb;
		border-radius: 12px; padding: 24px 32px; margin-bottom: 24px;
		box-shadow: 0 1px 4px rgba(0,0,0,.07);
	}
	.cg-compare-card h3 { margin: 0 0 18px; font-size: 15px; color: #374151; }
	.cg-compare-table { width: 100%; border-collapse: collapse; font-size: 14px; }
	.cg-compare-table th {
		text-align: center; padding: 10px 14px;
		background: #f9fafb; border-bottom: 2px solid #e5e7eb; font-size: 13px;
	}
	.cg-compare-table th:first-child { text-align: left; }
	.cg-compare-table td {
		padding: 10px 14px; border-bottom: 1px solid #f3f4f6; text-align: center;
	}
	.cg-compare-table td:first-child { text-align: left; color: #374151; }
	.cg-compare-table tr.cg-current td { background: #f0f9ff; font-weight: 600; }
	.cg-check { color: #22c55e; font-size: 18px; }
	.cg-cross { color: #d1d5db; font-size: 18px; }

	/* ── License server box ── */
	.cg-server-box {
		background: #fff; border: 1px solid #e5e7eb;
		border-radius: 12px; padding: 24px 32px; margin-bottom: 24px;
		box-shadow: 0 1px 4px rgba(0,0,0,.07);
	}
	.cg-server-box h3 { margin: 0 0 14px; font-size: 15px; color: #374151; }
	.cg-server-row { display: flex; gap: 8px; align-items: center; }
	.cg-server-row input[type="url"] {
		flex: 1; padding: 9px 14px; border: 1px solid #d1d5db;
		border-radius: 8px; font-size: 13px; font-family: monospace;
	}
	.cg-heartbeat-meta { margin-top: 10px; font-size: 12px; color: #9ca3af; }
	.cg-heartbeat-meta .error { color: #dc2626; }
	.cg-expired-badge {
		display: inline-block; background: #fef2f2; color: #dc2626;
		border: 1px solid #fca5a5; border-radius: 6px;
		font-size: 11px; font-weight: 700; padding: 2px 8px;
		margin-left: 8px; vertical-align: middle; text-transform: uppercase;
	}

	/* ── Upgrade banner ── */
	.cg-upgrade-banner {
		background: linear-gradient(135deg, #1e1b4b 0%, #3730a3 50%, #1d4ed8 100%);
		color: #fff; border-radius: 12px; padding: 28px 32px;
		display: flex; align-items: center; justify-content: space-between; gap: 24px;
	}
	.cg-upgrade-banner h3 { margin: 0 0 6px; font-size: 18px; }
	.cg-upgrade-banner p  { margin: 0; font-size: 13px; opacity: .85; }
	</style>

	<div class="cg-license-wrap">

		<!-- ── 1. PLAN CARD ── -->
		<div class="cg-plan-card">
			<div>
				<span class="cg-plan-badge"><?php echo esc_html( $plan_label ); ?></span>
			</div>
			<div class="cg-plan-info">
				<h2><?php printf( esc_html__( 'Current Plan: %s', 'certificate-generator' ), esc_html( $plan_label ) ); ?></h2>
				<div class="cg-plan-meta">
					<?php if ( $expiry ) : ?>
						<?php printf( esc_html__( 'License expires: %s', 'certificate-generator' ), esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expiry ) ) ) ); ?>
						<?php if ( $is_expired ) : ?>
							<span class="cg-expired-badge"><?php esc_html_e( 'Expired', 'certificate-generator' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<?php echo esc_html__( 'No expiry date set.', 'certificate-generator' ); ?>
					<?php endif; ?>
					&nbsp;&bull;&nbsp;
					<?php
					$site = home_url();
					echo esc_html( sprintf( __( 'Active on: %s', 'certificate-generator' ), $site ) );
					?>
				</div>
			</div>
		</div>

		<!-- ── 2. USAGE METER ── -->
		<div class="cg-usage-card">
			<h3>
				<?php esc_html_e( 'Monthly Certificate Usage', 'certificate-generator' ); ?>
				<span style="font-size:12px;font-weight:400;color:#9ca3af;margin-left:8px;">
					(<?php echo esc_html( $plan_label ); ?> plan —
					<?php
					echo $limit === 0
						? esc_html__( 'Unlimited', 'certificate-generator' )
						: sprintf( esc_html__( '%d/month', 'certificate-generator' ), $limit );
					?>
						)
				</span>
			</h3>
			<?php if ( $limit === 0 ) : ?>
				<div class="cg-meter-track"><div class="cg-meter-fill" style="background:#7c3aed;width:100%;"></div></div>
				<div class="cg-meter-label">
					<?php printf( esc_html__( '%d certificates generated this month — Unlimited', 'certificate-generator' ), $usage ); ?>
				</div>
			<?php else : ?>
				<div class="cg-meter-track"><div class="cg-meter-fill"></div></div>
				<div class="cg-meter-label">
					<?php printf( esc_html__( '%1$d / %2$d certificates generated this month', 'certificate-generator' ), $usage, $limit ); ?>
					<?php if ( $usage >= $limit ) : ?>
						&nbsp;<strong style="color:#ef4444;"><?php esc_html_e( '🚫 Limit reached! New certificates are blocked.', 'certificate-generator' ); ?></strong>
					<?php elseif ( $usage_pct >= 85 ) : ?>
						&nbsp;<strong style="color:#f59e0b;"><?php esc_html_e( '⚠ Approaching limit!', 'certificate-generator' ); ?></strong>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $limit > 0 ) : ?>
			<p style="margin:10px 0 0;font-size:12px;color:#9ca3af;">
				<?php esc_html_e( 'Resets automatically on the 1st of each month.', 'certificate-generator' ); ?>
				<?php if ( CG_License_Manager::is_free() ) : ?>
				&nbsp;<a href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade for higher limits →', 'certificate-generator' ); ?></a>
				<?php endif; ?>
			</p>
			<?php endif; ?>
		</div>

		<!-- ── 3. LICENSE KEY ACTIVATION ── -->
		<div class="cg-license-box">
			<h3><?php esc_html_e( 'License Key', 'certificate-generator' ); ?></h3>
			<?php if ( $license_key && ! CG_License_Manager::is_free() ) : ?>
				<p style="font-size:13px;color:#374151;">
					<?php printf( esc_html__( 'Active key: %s', 'certificate-generator' ), '<code>' . esc_html( $license_key ) . '</code>' ); ?>
				</p>
				<button class="cg-btn cg-btn-danger" id="cg-deactivate-btn"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Deactivate License', 'certificate-generator' ); ?>
				</button>
			<?php else : ?>
				<p style="font-size:13px;color:#6b7280;margin:0 0 12px;">
					<?php esc_html_e( 'Enter your Pro or Business license key to unlock premium features.', 'certificate-generator' ); ?>
				</p>
				<div class="cg-license-input-row">
					<input type="text" id="cg-license-key-input" placeholder="PRO-XXXX-XXXX-XXXX" value="" autocomplete="off" />
					<button class="cg-btn cg-btn-primary" id="cg-activate-btn"
						data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Activate', 'certificate-generator' ); ?>
					</button>
				</div>
				<p style="margin-top:10px;font-size:12px;color:#9ca3af;">
					<?php esc_html_e( 'Don\'t have a key yet?', 'certificate-generator' ); ?>
					<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank">
						<?php esc_html_e( 'Get a license →', 'certificate-generator' ); ?>
					</a>
				</p>
			<?php endif; ?>
			<div id="cg-license-msg" class="cg-license-msg" style="display:none;"></div>
		</div>

		<!-- ── 4. LICENSE SERVER ── -->
		<div class="cg-server-box">
			<h3><?php esc_html_e( 'License Server', 'certificate-generator' ); ?></h3>
			<div class="cg-server-row">
				<input type="url" id="cg-server-url-input"
					placeholder="https://your-portfolio.vercel.app"
					value="<?php echo esc_attr( $server_url ); ?>" />
				<button class="cg-btn cg-btn-primary" id="cg-save-server-btn"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Save', 'certificate-generator' ); ?>
				</button>
				<button class="cg-btn" id="cg-test-server-btn" style="background:#f3f4f6;color:#374151;"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Test Connection', 'certificate-generator' ); ?>
				</button>
				<?php if ( $license_key ) : ?>
				<button class="cg-btn" id="cg-verify-now-btn" style="background:#f3f4f6;color:#374151;"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Verify Now', 'certificate-generator' ); ?>
				</button>
				<?php endif; ?>
			</div>
			<div class="cg-heartbeat-meta">
				<?php if ( $last_heartbeat ) : ?>
					<?php
					printf(
						esc_html__( 'Last verified: %s', 'certificate-generator' ),
						esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $last_heartbeat ) ) )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Not yet verified against license server.', 'certificate-generator' ); ?>
				<?php endif; ?>
				<?php if ( $heartbeat_error ) : ?>
					&nbsp;<span class="error"><?php echo esc_html( $heartbeat_error ); ?></span>
				<?php endif; ?>
			</div>
			<div id="cg-server-msg" class="cg-license-msg" style="display:none;margin-top:8px;"></div>
		</div>

		<!-- ── 5. PLAN COMPARISON TABLE ── -->
		<div class="cg-compare-card">
			<h3><?php esc_html_e( 'Plan Comparison', 'certificate-generator' ); ?></h3>
			<table class="cg-compare-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Feature', 'certificate-generator' ); ?></th>
						<th style="color:<?php echo esc_attr( $plan_colors['free'] ); ?>;<?php echo $plan === 'free' ? 'background:#f0f9ff;border-top:3px solid ' . esc_attr( $plan_colors['free'] ) . ';' : ''; ?>">
							<?php esc_html_e( 'Free', 'certificate-generator' ); ?><br>
							<?php if ( $plan === 'free' ) : ?>
								<small style="font-weight:700;color:<?php echo esc_attr( $plan_colors['free'] ); ?>;">◀ Your Plan</small>
							<?php else : ?>
								<small style="font-weight:400;color:#9ca3af;"><?php esc_html_e( '100 certs/mo', 'certificate-generator' ); ?></small>
							<?php endif; ?>
						</th>
						<th style="color:<?php echo esc_attr( $plan_colors['pro'] ); ?>;<?php echo $plan === 'pro' ? 'background:#f0f9ff;border-top:3px solid ' . esc_attr( $plan_colors['pro'] ) . ';' : ''; ?>">
							<?php esc_html_e( 'Pro', 'certificate-generator' ); ?><br>
							<?php if ( $plan === 'pro' ) : ?>
								<small style="font-weight:700;color:<?php echo esc_attr( $plan_colors['pro'] ); ?>;">◀ Your Plan</small>
							<?php else : ?>
								<small style="font-weight:400;color:#9ca3af;">$9–15/mo</small>
							<?php endif; ?>
						</th>
						<th style="color:<?php echo esc_attr( $plan_colors['business'] ); ?>;<?php echo $plan === 'business' ? 'background:#f0f9ff;border-top:3px solid ' . esc_attr( $plan_colors['business'] ) . ';' : ''; ?>">
							<?php esc_html_e( 'Business', 'certificate-generator' ); ?><br>
							<?php if ( $plan === 'business' ) : ?>
								<small style="font-weight:700;color:<?php echo esc_attr( $plan_colors['business'] ); ?>;">◀ Your Plan</small>
							<?php else : ?>
								<small style="font-weight:400;color:#9ca3af;">$29–49/mo</small>
							<?php endif; ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $features as $key => $feature ) :
						// Highlight rows that are included in the current plan
						$row_class = ( ! empty( $feature[ $plan ] ) ) ? 'cg-current' : '';
						?>
						<tr class="<?php echo esc_attr( $row_class ); ?>">
							<td><?php echo esc_html( $feature['label'] ); ?></td>
							<td>
								<?php if ( $feature['free'] ) : ?>
									<span class="cg-check" title="<?php esc_attr_e( 'Included', 'certificate-generator' ); ?>">✔</span>
								<?php else : ?>
									<span class="cg-cross" title="<?php esc_attr_e( 'Not included', 'certificate-generator' ); ?>">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $feature['pro'] ) : ?>
									<span class="cg-check">✔</span>
								<?php else : ?>
									<span class="cg-cross">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $feature['business'] ) : ?>
									<span class="cg-check">✔</span>
								<?php else : ?>
									<span class="cg-cross">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- ── 6. RENEW BANNER (shown when license is expired) ── -->
		<?php if ( $is_expired ) : ?>
		<div class="cg-upgrade-banner" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);">
			<div>
				<h3><?php esc_html_e( '⚠ License Expired', 'certificate-generator' ); ?></h3>
				<p><?php esc_html_e( 'Your license has expired. Premium features are locked. Renew to restore access.', 'certificate-generator' ); ?></p>
			</div>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="cg-btn cg-btn-upgrade" style="background:#fff;color:#dc2626;">
				<?php esc_html_e( 'Renew License →', 'certificate-generator' ); ?>
			</a>
		</div>
		<?php endif; ?>

		<!-- ── 7. UPGRADE BANNER (shown only on Free / Pro, not expired) ── -->
		<?php if ( ! CG_License_Manager::is_business() && ! $is_expired ) : ?>
		<div class="cg-upgrade-banner">
			<div>
				<?php if ( CG_License_Manager::is_free() ) : ?>
					<h3><?php esc_html_e( '🚀 Unlock Pro Features', 'certificate-generator' ); ?></h3>
					<p><?php esc_html_e( 'Bulk CSV import, ZIP download, REST API, email templates and more.', 'certificate-generator' ); ?></p>
				<?php else : ?>
					<h3><?php esc_html_e( '⚡ Upgrade to Business', 'certificate-generator' ); ?></h3>
					<p><?php esc_html_e( '19,000+ fonts, unlimited API, multisite support, commercial license.', 'certificate-generator' ); ?></p>
				<?php endif; ?>
			</div>
			<a href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" class="cg-btn cg-btn-upgrade">
				<?php esc_html_e( 'Upgrade Now →', 'certificate-generator' ); ?>
			</a>
		</div>
		<?php endif; ?>

	</div><!-- .cg-license-wrap -->

	<script>
	(function($) {
		'use strict';

		var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

		// ── Activate
		$('#cg-activate-btn').on('click', function() {
			var $btn = $(this);
			var key  = $('#cg-license-key-input').val().trim();
			if (!key) { showMsg('error', '<?php echo esc_js( __( 'Please enter a license key.', 'certificate-generator' ) ); ?>'); return; }

			$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Activating…', 'certificate-generator' ) ); ?>');

			$.post(ajaxurl, {
				action: 'cg_activate_license',
				nonce:  $btn.data('nonce'),
				license_key: key
			})
			.done(function(res) {
				if (res.success) {
					showMsg('success', res.data.message);
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					showMsg('error', res.data.message || '<?php echo esc_js( __( 'Activation failed.', 'certificate-generator' ) ); ?>');
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Activate', 'certificate-generator' ) ); ?>');
				}
			})
			.fail(function() {
				showMsg('error', '<?php echo esc_js( __( 'Server error. Please try again.', 'certificate-generator' ) ); ?>');
				$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Activate', 'certificate-generator' ) ); ?>');
			});
		});

		// ── Deactivate
		$('#cg-deactivate-btn').on('click', function() {
			if (!confirm('<?php echo esc_js( __( 'Deactivate this license? The plan will revert to Free.', 'certificate-generator' ) ); ?>')) return;
			var $btn = $(this);
			$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Deactivating…', 'certificate-generator' ) ); ?>');

			$.post(ajaxurl, {
				action: 'cg_deactivate_license',
				nonce:  $btn.data('nonce'),
			})
			.done(function(res) {
				if (res.success) {
					showMsg('success', res.data.message);
					setTimeout(function() { location.reload(); }, 1200);
				} else {
					$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Deactivate License', 'certificate-generator' ) ); ?>');
				}
			});
		});

		function showMsg(type, msg) {
			$('#cg-license-msg').removeClass('success error').addClass(type).text(msg).show();
		}

		function showServerMsg(type, msg) {
			$('#cg-server-msg').removeClass('success error').addClass(type).text(msg).show();
		}

		// ── Save server URL
		$('#cg-save-server-btn').on('click', function() {
			var $btn = $(this);
			var url  = $('#cg-server-url-input').val().trim();
			$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving…', 'certificate-generator' ) ); ?>');
			$.post(ajaxurl, {
				action: 'cg_save_license_server',
				nonce: $btn.data('nonce'),
				server_url: url
			})
			.done(function(res) {
				if (res.success) {
					showServerMsg('success', res.data.message);
				} else {
					showServerMsg('error', res.data || '<?php echo esc_js( __( 'Save failed.', 'certificate-generator' ) ); ?>');
				}
			})
			.fail(function() {
				showServerMsg('error', '<?php echo esc_js( __( 'Server error.', 'certificate-generator' ) ); ?>');
			})
			.always(function() {
				$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save', 'certificate-generator' ) ); ?>');
			});
		});

		// ── Test connection
		$('#cg-test-server-btn').on('click', function() {
			var $btn = $(this);
			var url  = $('#cg-server-url-input').val().trim();
			$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing…', 'certificate-generator' ) ); ?>');
			$.post(ajaxurl, {
				action: 'cg_test_license_server',
				nonce: $btn.data('nonce'),
				server_url: url
			})
			.done(function(res) {
				if (res.success) {
					showServerMsg('success', res.data);
				} else {
					showServerMsg('error', res.data);
				}
			})
			.fail(function() {
				showServerMsg('error', '<?php echo esc_js( __( 'Request failed.', 'certificate-generator' ) ); ?>');
			})
			.always(function() {
				$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test Connection', 'certificate-generator' ) ); ?>');
			});
		});

		// ── Verify Now
		$('#cg-verify-now-btn').on('click', function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Verifying…', 'certificate-generator' ) ); ?>');
			$.post(ajaxurl, {
				action: 'cg_verify_now',
				nonce: $btn.data('nonce')
			})
			.done(function(res) {
				if (res.success) {
					showServerMsg('success', res.data.message);
					setTimeout(function() { location.reload(); }, 1500);
				} else {
					showServerMsg('error', res.data || '<?php echo esc_js( __( 'Verification failed.', 'certificate-generator' ) ); ?>');
				}
			})
			.fail(function() {
				showServerMsg('error', '<?php echo esc_js( __( 'Request failed.', 'certificate-generator' ) ); ?>');
			})
			.always(function() {
				$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Verify Now', 'certificate-generator' ) ); ?>');
			});
		});
	}(jQuery));
	</script>
	<?php
}
