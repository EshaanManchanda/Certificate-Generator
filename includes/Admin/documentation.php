<?php
/**
 * Documentation & Getting Started Page
 *
 * @package Certificate Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── AJAX: dismiss welcome banner ─────────────────────────────────────────────
add_action(
	'wp_ajax_cg_dismiss_welcome',
	function () {
		check_ajax_referer( 'cg_dismiss_welcome', 'nonce' );
		update_option( 'cg_welcome_dismissed', 1 );
		wp_send_json_success();
	}
);

// ── Welcome banner (shows once after activation) ─────────────────────────────
add_action( 'admin_notices', 'cg_maybe_show_welcome_banner' );
function cg_maybe_show_welcome_banner() {
	if ( get_option( 'cg_welcome_dismissed' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$docs_url = admin_url( 'admin.php?page=cg-documentation' );
	?>
	<div class="notice notice-info cg-welcome-banner" style="display:flex;align-items:center;gap:16px;padding:14px 16px;">
		<span class="dashicons dashicons-award" style="font-size:32px;color:#2271b1;flex-shrink:0;"></span>
		<div style="flex:1;">
			<strong>Certificate Generator is active!</strong>
			New here? The <a href="<?php echo esc_url( $docs_url ); ?>">Getting Started guide</a>
			walks you through SMTP setup, templates, and your first bulk send in under 10 minutes.
		</div>
		<button type="button" class="cg-dismiss-welcome notice-dismiss" style="position:static;flex-shrink:0;">
			<span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'certificate-generator' ); ?></span>
		</button>
	</div>
	<script>
	(function(){
		document.querySelector('.cg-dismiss-welcome')?.addEventListener('click', function(){
			this.closest('.cg-welcome-banner').remove();
			fetch(ajaxurl, {
				method: 'POST',
				headers: {'Content-Type':'application/x-www-form-urlencoded'},
				body: 'action=cg_dismiss_welcome&nonce=<?php echo esc_js( wp_create_nonce( 'cg_dismiss_welcome' ) ); ?>'
			});
		});
	})();
	</script>
	<?php
}

// ── Page renderer ─────────────────────────────────────────────────────────────
function cg_render_documentation_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'certificate-generator' ) );
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'getting-started';
	$base_url   = admin_url( 'admin.php?page=cg-documentation' );

	// ── Live setup status ────────────────────────────────────────────────────
	$smtp_ok     = (bool) certificate_generator_is_wp_mail_smtp_active();
	$admin_email = get_option( 'admin_email', '' );

	global $wpdb;
	$cg_table        = $wpdb->prefix . 'certificate_generator';
	$has_cg_table    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cg_table ) ) === $cg_table;
	$cert_count      = $has_cg_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $cg_table" ) : 0;
	$tpl_table       = $wpdb->prefix . 'cg_certificate_templates';
	$templates_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $tpl_table WHERE status = 'published'" );
	$students_table  = $wpdb->prefix . 'cg_students';
	$students_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $students_table" );

	$steps = array(
		array(
			'done'      => $smtp_ok,
			'label'     => 'Email / SMTP configured',
			'fix'       => admin_url( 'admin.php?page=wps-mail-smtp' ),
			'fix_label' => 'Open WP Mail SMTP',
		),
		array(
			'done'      => $templates_count > 0,
			'label'     => 'At least one certificate template created',
			'fix'       => admin_url( 'admin.php?page=cg-templates' ),
			'fix_label' => 'Create template',
		),
		array(
			'done'      => $students_count > 0 || $cert_count > 0,
			'label'     => 'Student / teacher records added',
			'fix'       => admin_url( 'admin.php?page=cg-students' ),
			'fix_label' => 'Add students',
		),
		array(
			'done'      => $cert_count > 0,
			'label'     => 'Certificate records in custom table',
			'fix'       => admin_url( 'admin.php?page=cg-migration' ),
			'fix_label' => 'Run migration',
		),
	);

	$all_done = ! in_array( false, array_column( $steps, 'done' ), true );

	?>
	<div class="wrap cg-docs-wrap">
	<h1 style="display:flex;align-items:center;gap:10px;">
		<span class="dashicons dashicons-award" style="font-size:28px;color:#2271b1;"></span>
		Certificate Generator — Documentation
	</h1>

	<style>
	.cg-docs-wrap { max-width: 980px; }
	.cg-tab-content { background:#fff; border:1px solid #c3c4c7; border-top:none; padding:28px 32px; }
	.cg-step { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid #f0f0f1; }
	.cg-step:last-child { border-bottom:none; }
	.cg-badge { display:inline-block; padding:2px 10px; border-radius:12px; font-size:12px; font-weight:600; }
	.cg-badge-ok  { background:#d1fae5; color:#065f46; }
	.cg-badge-todo{ background:#fef3c7; color:#92400e; }
	.cg-section { margin-bottom:0; }
	.cg-section summary { font-size:15px; font-weight:600; cursor:pointer; padding:14px 0; list-style:none; display:flex; align-items:center; gap:8px; }
	.cg-section summary::-webkit-details-marker { display:none; }
	.cg-section summary::before { content:'▶'; font-size:11px; color:#2271b1; transition:transform .15s; }
	.cg-section[open] summary::before { transform:rotate(90deg); }
	.cg-section-body { padding:4px 0 20px 24px; color:#444; line-height:1.7; }
	.cg-section-body h4 { margin:16px 0 6px; color:#1d2327; }
	.cg-section-body ul,
	.cg-section-body ol { margin-left:20px; }
	.cg-section-body code { background:#f6f7f7; padding:2px 6px; border-radius:3px; font-size:13px; }
	.cg-section-body .cg-note { background:#f0f6fc; border-left:4px solid #2271b1; padding:10px 14px; border-radius:0 4px 4px 0; margin:12px 0; }
	.cg-section-body .cg-warn { background:#fff8e5; border-left:4px solid #f0b849; padding:10px 14px; border-radius:0 4px 4px 0; margin:12px 0; }
	.cg-divider { border:none; border-top:1px solid #f0f0f1; margin:4px 0; }
	.cg-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin:16px 0; }
	@media(max-width:680px){ .cg-grid{ grid-template-columns:1fr; } }
	.cg-card { border:1px solid #c3c4c7; border-radius:4px; padding:16px; }
	.cg-card h4 { margin:0 0 8px; }
	</style>

	<nav class="nav-tab-wrapper" style="margin-bottom:0;">
		<?php
		$tabs = array(
			'getting-started' => '🚀 Getting Started',
			'user-guide'      => '📖 User Guide',
			'email-guide'     => '✉️ Email & Bulk Send',
			'troubleshooting' => '🔧 Troubleshooting',
			'faq'             => '❓ FAQ',
		);
		foreach ( $tabs as $slug => $label ) {
			$class = $active_tab === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a href="' . esc_url( $base_url . '&tab=' . $slug ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		?>
	</nav>

	<div class="cg-tab-content">

	<?php /* ═══════════════════════  GETTING STARTED  ══════════════════════════ */ ?>
	<?php if ( $active_tab === 'getting-started' ) : ?>

		<?php if ( $all_done ) : ?>
		<div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:4px;padding:14px 18px;margin-bottom:20px;color:#065f46;font-weight:600;">
			✅ All setup steps complete — your plugin is ready for production!
		</div>
		<?php else : ?>
		<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:4px;padding:14px 18px;margin-bottom:20px;color:#92400e;">
			⚠️ Complete the steps below before sending live certificates.
		</div>
		<?php endif; ?>

		<h2 style="margin-top:0;">Setup Checklist</h2>
		<div style="margin-bottom:28px;">
		<?php foreach ( $steps as $step ) : ?>
			<div class="cg-step">
				<span class="cg-badge <?php echo $step['done'] ? 'cg-badge-ok' : 'cg-badge-todo'; ?>">
					<?php echo $step['done'] ? '✓ Done' : 'To do'; ?>
				</span>
				<div style="flex:1;">
					<strong><?php echo esc_html( $step['label'] ); ?></strong>
				</div>
				<?php if ( ! $step['done'] ) : ?>
				<a href="<?php echo esc_url( $step['fix'] ); ?>" class="button button-small">
					<?php echo esc_html( $step['fix_label'] ); ?> →
				</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		</div>

		<hr class="cg-divider">
		<h2>Quick Start (5 steps)</h2>
		<ol style="line-height:2;font-size:14px;">
			<li>
				<strong>Configure SMTP</strong> — install <em>WP Mail SMTP</em>, set mailer to your provider (Gmail/Hostinger/SendGrid), set <em>From Email</em> to match your SMTP account.
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wps-mail-smtp' ) ); ?>">Open SMTP settings →</a>
			</li>
			<li>
				<strong>Create a certificate template</strong> — go to
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cg-templates' ) ); ?>">Templates</a>,
				upload your background image, and position the text fields (name, certificate type, date).
			</li>
			<li>
				<strong>Add students</strong> — go to
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cg-students' ) ); ?>">Students</a>
				and add records manually, or use
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cg-bulk-import' ) ); ?>">Bulk Import</a>
				to upload a CSV.
			</li>
			<li>
				<strong>Send a test email</strong> — go to
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cg-email-settings' ) ); ?>">Email Settings</a>
				and use the <em>Send Test Email</em> button to verify delivery.
			</li>
			<li>
				<strong>Bulk send</strong> — go to
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=certificate-bulk-send' ) ); ?>">Bulk Send</a>,
				filter by school / certificate type, and click <em>Send Certificates</em>.
			</li>
		</ol>

		<hr class="cg-divider" style="margin-top:24px;">
		<h2>Quick Links</h2>
		<div class="cg-grid">
			<?php
			$links = array(
				array( 'Templates', admin_url( 'admin.php?page=cg-templates' ), 'dashicons-images-alt2' ),
				array( 'Students', admin_url( 'admin.php?page=cg-students' ), 'dashicons-groups' ),
				array( 'Bulk Send', admin_url( 'admin.php?page=certificate-bulk-send' ), 'dashicons-email-alt' ),
				array( 'Email Logs', admin_url( 'admin.php?page=certificate-email-logs' ), 'dashicons-list-view' ),
				array( 'Bulk Import', admin_url( 'admin.php?page=cg-bulk-import' ), 'dashicons-upload' ),
				array( 'Analytics', admin_url( 'admin.php?page=cg-analytics' ), 'dashicons-chart-bar' ),
			);
			foreach ( $links as [$label, $url, $icon] ) :
				?>
			<a href="<?php echo esc_url( $url ); ?>" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #c3c4c7;border-radius:4px;text-decoration:none;color:#1d2327;transition:border-color .15s;" onmouseover="this.style.borderColor='#2271b1'" onmouseout="this.style.borderColor='#c3c4c7'">
				<span class="dashicons <?php echo esc_attr( $icon ); ?>" style="color:#2271b1;flex-shrink:0;"></span>
				<strong><?php echo esc_html( $label ); ?></strong>
			</a>
			<?php endforeach; ?>
		</div>

		<?php /* ═══════════════════════  USER GUIDE  ══════════════════════════════ */ ?>
	<?php elseif ( $active_tab === 'user-guide' ) : ?>

		<h2 style="margin-top:0;">User Guide</h2>

		<details class="cg-section" open>
			<summary>Certificate Templates</summary>
			<div class="cg-section-body">
				<h4>Creating a template</h4>
				<ol>
					<li>Go to <strong>Certificate Generator → Templates → Add New</strong>.</li>
					<li>Upload your certificate background image (PNG or JPG, A4 landscape recommended).</li>
					<li>Use the field position editor to drag Name, Certificate Type, School, and Date fields onto the canvas.</li>
					<li>Set font, size, and colour for each field.</li>
					<li>Click <strong>Save Template</strong>.</li>
				</ol>
				<div class="cg-note">Each student's certificate type is matched against the template name. If no match is found, the default template is used.</div>
				<h4>Editing an existing template</h4>
				<p>Open the template post and adjust field positions. Changes apply to all future PDF generations — previously generated PDFs are not regenerated automatically.</p>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Managing Students, Teachers &amp; Schools</summary>
			<div class="cg-section-body">
				<h4>Adding records manually</h4>
				<p>Go to <strong>Students / Teachers / Schools → Add New</strong>. Fill in the required fields: Name, Email, School, Certificate Type, Issue Date.</p>
				<h4>Bulk import via CSV</h4>
				<ol>
					<li>Go to <strong>Bulk Import</strong>.</li>
					<li>Download the sample CSV to see required columns.</li>
					<li>Fill in your data and upload the file.</li>
					<li>Review the import preview and confirm.</li>
				</ol>
				<div class="cg-note">Required CSV columns: <code>student_name</code>, <code>email</code>, <code>school_name</code>, <code>certificate_type</code>, <code>issue_date</code>.</div>
				<h4>Bulk export</h4>
				<p>Go to <strong>Bulk Export</strong> to download all records as CSV. Use this to keep a backup or migrate data.</p>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Serial Numbers</summary>
			<div class="cg-section-body">
				<h4>Auto-generation</h4>
				<p>Serial numbers are generated automatically when a certificate is created. Format is configured in <strong>Serial Settings</strong>.</p>
				<h4>Format options</h4>
				<ul>
					<li><code>PREFIX-{YEAR}-{SEQ}</code> — e.g. <em>GEMA-2025-00142</em></li>
					<li>Prefix, year format, and sequence padding are all configurable.</li>
				</ul>
				<h4>Bulk serial assignment</h4>
				<p>Go to <strong>Bulk Serials</strong> to assign serials to existing records that don't have one yet.</p>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>QR Codes</summary>
			<div class="cg-section-body">
				<p>QR codes are embedded in certificates and link to the public verification page (<code>/verify-certificate/</code>).</p>
				<ul>
					<li>QR codes are generated automatically during PDF creation.</li>
					<li>Scanning the code takes the viewer to a page showing the certificate's authenticity status.</li>
					<li>No manual setup required — the library is bundled with the plugin.</li>
				</ul>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Analytics</summary>
			<div class="cg-section-body">
				<p>Go to <strong>Certificate Generator → Analytics</strong> to see:</p>
				<ul>
					<li>Total certificates issued</li>
					<li>Emails sent / failed / pending</li>
					<li>Send volume over time (chart)</li>
					<li>Breakdown by certificate type and school</li>
				</ul>
				<div class="cg-note">Analytics pull from the <code>wp_cert_email_logs</code> table. Data is only as complete as your email log history.</div>
			</div>
		</details>

		<?php /* ═══════════════════════  EMAIL & BULK SEND  ══════════════════════ */ ?>
	<?php elseif ( $active_tab === 'email-guide' ) : ?>

		<h2 style="margin-top:0;">Email &amp; Bulk Send Guide</h2>

		<details class="cg-section" open>
			<summary>Setting up SMTP (required for production)</summary>
			<div class="cg-section-body">
				<p>The plugin sends via WordPress's <code>wp_mail()</code>. For reliable delivery you <strong>must</strong> configure a real SMTP mailer.</p>
				<ol>
					<li>Install the free <strong>WP Mail SMTP</strong> plugin.</li>
					<li>Go to <strong>WP Mail SMTP → Settings</strong>.</li>
					<li>Set <em>From Email</em> to your authenticated sender address (must match your SMTP account login).</li>
					<li>Choose your mailer (Other SMTP / Gmail / SendGrid / Mailgun).</li>
					<li>Enter host, port, username, and password from your email provider.</li>
					<li>Click <strong>Send Test Email</strong> in WP Mail SMTP to verify.</li>
				</ol>
				<div class="cg-warn">⚠️ The From Email address <strong>must exactly match</strong> the account you authenticate with. Mismatch causes "Sender address rejected" bounces.</div>
				<h4>Hostinger / cPanel SMTP example</h4>
				<ul>
					<li>Host: <code>smtp.hostinger.com</code> (or your cPanel hostname)</li>
					<li>Port: <code>587</code> (TLS) or <code>465</code> (SSL)</li>
					<li>Username: full email address (e.g. <code>contact@yourdomain.com</code>)</li>
				</ul>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Email templates &amp; placeholders</summary>
			<div class="cg-section-body">
				<p>Configure per-entity email templates in <strong>Certificate Generator → Settings → Email</strong>.</p>
				<h4>Available placeholders</h4>
				<table style="border-collapse:collapse;width:100%;">
					<thead><tr style="background:#f6f7f7;"><th style="padding:8px 12px;text-align:left;border:1px solid #ddd;">Placeholder</th><th style="padding:8px 12px;text-align:left;border:1px solid #ddd;">Replaced with</th></tr></thead>
					<tbody>
						<?php
						$placeholders = array(
							'{name}'              => 'Recipient\'s full name',
							'{certificate_title}' => 'Certificate type / title',
							'{email}'             => 'Recipient\'s email address',
							'{serial_number}'     => 'Certificate serial number',
							'{expires_at}'        => 'Expiry date (or "Never")',
							'{certificate_count}' => 'Number of certificates in this send',
							'{result_link}'       => 'Link to online results page',
							'{verify_link}'       => 'Link to certificate verification page',
							'{zip_link}'          => 'Download link (when ZIP > 25 MB)',
						);
						foreach ( $placeholders as $ph => $desc ) :
							?>
						<tr><td style="padding:7px 12px;border:1px solid #ddd;"><code><?php echo esc_html( $ph ); ?></code></td><td style="padding:7px 12px;border:1px solid #ddd;"><?php echo esc_html( $desc ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="cg-note">WordPress shortcodes (e.g. <code>[site_name]</code>) are processed after placeholder substitution, so you can mix both.</div>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Sending bulk emails</summary>
			<div class="cg-section-body">
				<ol>
					<li>Go to <strong>Certificate Generator → Bulk Send</strong>.</li>
					<li>Use the filters to narrow by <em>School</em>, <em>Certificate Type</em>, or <em>Post Type</em>.</li>
					<li>Preview the recipient list.</li>
					<li>Choose whether to <em>skip already sent</em> (recommended — prevents duplicates).</li>
					<li>Click <strong>Send Certificates</strong>.</li>
				</ol>
				<div class="cg-note">Emails are processed via a background queue — the page doesn't need to stay open. Check <strong>Email Logs</strong> for delivery status.</div>
				<h4>How multiple certificates per recipient are handled</h4>
				<p>If a student has more than one certificate, all are bundled into a single ZIP file and attached to one email. This prevents inbox flooding.</p>
				<h4>Rate limits</h4>
				<p>Configurable in <strong>Settings → Rate Limits</strong>. Defaults: 60 emails/hour, 10 emails/minute. The queue processor respects these automatically.</p>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Email Logs</summary>
			<div class="cg-section-body">
				<p>Go to <strong>Certificate Generator → Email Logs</strong> to see every send attempt with:</p>
				<ul>
					<li>Recipient name &amp; email</li>
					<li>Certificate type</li>
					<li>Status: <code>sent</code> / <code>failed</code> / <code>pending</code></li>
					<li>Timestamp</li>
					<li>Error message (if failed)</li>
				</ul>
				<p>You can filter by status, date range, or search by email address. Logs are retained for 90 days then automatically cleaned up.</p>
			</div>
		</details>

		<?php /* ═══════════════════════  TROUBLESHOOTING  ══════════════════════════ */ ?>
	<?php elseif ( $active_tab === 'troubleshooting' ) : ?>

		<h2 style="margin-top:0;">Troubleshooting</h2>

		<details class="cg-section" open>
			<summary>Emails not being received</summary>
			<div class="cg-section-body">
				<ol>
					<li><strong>Check Email Logs</strong> — go to Email Logs and look for <code>failed</code> entries. The error message column shows the exact reason.</li>
					<li><strong>Verify SMTP config</strong> — open WP Mail SMTP → Tools → Email Test and send a test to your own address.</li>
					<li><strong>From Email mismatch</strong> — the most common cause. Your WP Mail SMTP <em>From Email</em> must exactly match the email address you authenticate with. Example: if your SMTP login is <code>contact@kidrove.com</code>, From Email must also be <code>contact@kidrove.com</code>.</li>
					<li><strong>Spam folder</strong> — check if emails are landing in spam. This usually means your domain lacks SPF/DKIM records.</li>
					<li><strong>Rate limit hit</strong> — if sending in bulk, the queue may be paused waiting for rate limits to reset. Check Email Logs for entries with error containing "rate limit".</li>
				</ol>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>"Successfully queued 0 certificates"</summary>
			<div class="cg-section-body">
				<p>This means the selected filter returned recipients, but no matching records were found in the certificate table.</p>
				<ol>
					<li>Go to <strong>Certificate Generator → Migration</strong> and run the data migration to populate the custom table from your CPT records.</li>
					<li>After migration, retry the bulk send.</li>
				</ol>
				<div class="cg-note">Run this SQL to check record count:<br><code>SELECT COUNT(*), SUM(email='') FROM wp_certificate_generator;</code><br>If <em>email</em> count is high, use the email backfill AJAX action.</div>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Certificate PDF not generating</summary>
			<div class="cg-section-body">
				<ol>
					<li>Make sure the certificate template has <strong>all field positions set</strong> (X and Y coordinates for each visible field). A missing position causes generation to abort.</li>
					<li>Check that the WordPress uploads directory is writable: <code><?php echo esc_html( wp_upload_dir()['basedir'] ); ?></code></li>
					<li>Ensure the FPDF library is present at <code>lib/fpdf/fpdf.php</code> — it should be bundled with the plugin.</li>
					<li>Enable <code>WP_DEBUG</code> and <code>WP_DEBUG_LOG</code> in <code>wp-config.php</code> and check <code>wp-content/debug.log</code> for FPDF errors.</li>
				</ol>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>ZIP file not attaching / download link missing</summary>
			<div class="cg-section-body">
				<p>ZIP files are created when a recipient has <strong>more than one certificate</strong>. Requirements:</p>
				<ul>
					<li>PHP's <code>ZipArchive</code> extension must be enabled — check with your host if unsure.</li>
					<li>The uploads directory must be writable.</li>
					<li>If the ZIP exceeds 25 MB, it is not attached but a download link is included in the email body instead.</li>
				</ul>
				<div class="cg-note">Run <code>php -m | grep zip</code> via SSH or ask your host to confirm <code>ZipArchive</code> is enabled.</div>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Bulk send shows "No certificate records found"</summary>
			<div class="cg-section-body">
				<p>The custom table (<code>wp_certificate_generator</code>) has no rows for the filtered recipients.</p>
				<ol>
					<li>Run the migration page to copy existing CPT data to the custom table.</li>
					<li>Or import students via CSV (Bulk Import), which populates the table directly.</li>
				</ol>
			</div>
		</details>
		<hr class="cg-divider">

		<details class="cg-section">
			<summary>Debug mode</summary>
			<div class="cg-section-body">
				<p>To get detailed logs, add this to <code>wp-config.php</code>:</p>
				<pre style="background:#f6f7f7;padding:12px;border-radius:4px;overflow-x:auto;">define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );</pre>
				<p>Then check <code>wp-content/debug.log</code> after triggering an email send. All <code>[CG Email]</code> prefixed lines are from this plugin.</p>
			</div>
		</details>

		<?php /* ═══════════════════════  FAQ  ══════════════════════════════════════ */ ?>
	<?php elseif ( $active_tab === 'faq' ) : ?>

		<h2 style="margin-top:0;">Frequently Asked Questions</h2>

		<?php
		$faqs = array(
			'Can I send certificates to teachers and schools too, not just students?' =>
				'Yes. The bulk send page lets you choose the entity type (students / teachers / schools) before filtering. Each entity type has its own email template configurable in Settings → Email.',

			'Will re-sending skip students who already received their certificate?' =>
				'Yes — the "Skip already sent" checkbox (checked by default) checks the email log and skips any recipient who has a <code>sent</code> entry for their certificate ID.',

			'Can I use a Gmail account to send emails?'   =>
				'Yes, but Gmail requires an App Password (not your regular login). In WP Mail SMTP: choose Gmail as mailer, follow the OAuth flow or use SMTP with host <code>smtp.gmail.com</code>, port 587, and your App Password.',

			'How do I change the certificate PDF design?' =>
				'Edit the template in Certificate Generator → Templates. Upload a new background image and reposition the text fields. Future PDFs will use the updated design; previously generated PDFs are not changed.',

			'What happens if an email fails to send?'     =>
				'The queue retries up to 3 times with exponential backoff. After 3 failures the item is marked <code>failed</code> in the queue and logged in Email Logs with the error message. You can reset failed items from the queue management section.',

			'How long are email logs kept?'               =>
				'Logs are retained for 90 days. A weekly cron job automatically deletes older entries. You can change this by editing the <code>certificate_generator_cleanup_email_logs()</code> call in <code>includes/Email/log.php</code>.',

			'Can students verify their certificate online?' =>
				'Yes. Each certificate has a QR code that links to <code>/verify-certificate/</code>. Visitors can also search by serial number or email on the results page (<code>/result/</code>).',

			'Is the plugin multisite compatible?'         =>
				'Partially. The plugin is designed for single-site use. Multisite installs may work but license checks and custom table creation have not been formally tested across network sites.',

			'How do I back up all certificate data?'      =>
				'Use Bulk Export (CSV) to back up all records. The custom tables (<code>wp_certificate_generator</code>, <code>wp_cert_email_logs</code>) are also included in any full database backup.',

			'How do I update the plugin without losing data?' =>
				'Plugin updates only replace PHP/JS/CSS files — they do not drop or truncate database tables. Your certificate records, email logs, and settings are preserved across updates. Always take a database backup before major updates.',
		);
		foreach ( $faqs as $q => $a ) :
			?>
		<details class="cg-section">
			<summary><?php echo esc_html( $q ); ?></summary>
			<div class="cg-section-body"><p><?php echo wp_kses_post( $a ); ?></p></div>
		</details>
		<hr class="cg-divider">
		<?php endforeach; ?>

	<?php endif; ?>

	</div><!-- .cg-tab-content -->
	</div><!-- .cg-docs-wrap -->
	<?php
}
