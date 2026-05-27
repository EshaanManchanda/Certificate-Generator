<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

use CertificateGenerator\Email\Mailer;
use CertificateGenerator\Services\SettingsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailSettingsPage extends Page {

	private string $partials_dir;

	public function __construct() {
		parent::__construct( 'cg-email-settings', 'Email Settings' );
		$this->partials_dir = dirname( __DIR__ ) . '/Partials/';
	}

	public function register(): void {
		parent::register();

		add_action(
			'admin_enqueue_scripts',
			function ( string $hook ): void {
				if ( strpos( $hook, $this->slug ) === false ) {
					return;
				}
				wp_enqueue_media();
				wp_enqueue_script(
					'cg-media-uploader',
					CERTIFICATE_GENERATOR_URL . 'assets/js/cg-media-uploader.js',
					array( 'jquery' ),
					'1.0.0',
					true
				);
			}
		);
	}

	public function render(): void {
		if ( ! $this->check_permission() ) {
			wp_die( 'Insufficient permissions' );
		}

		// Run once — no-op if canonical keys already set.
		SettingsService::migrate_legacy();

		$message = '';
		$errors  = array();

		// ── Save settings ──────────────────────────────────────────────────────
		if (
			$_SERVER['REQUEST_METHOD'] === 'POST'
			&& ! empty( $_POST['cg_email_settings_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cg_email_settings_nonce'] ) ), 'cg_email_settings_save' )
			&& empty( $_POST['cg_email_test_send'] )
		) {
			$result = SettingsService::save_email_settings( wp_unslash( $_POST ) );

			if ( empty( $result['errors'] ) ) {
				$message = 'Settings saved.';
			} else {
				$errors = $result['errors'];
			}
		}

		// ── Test email ─────────────────────────────────────────────────────────
		if (
			$_SERVER['REQUEST_METHOD'] === 'POST'
			&& ! empty( $_POST['cg_email_test_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cg_email_test_nonce'] ) ), 'cg_email_test_send' )
		) {
			$test_to = sanitize_email( $_POST['cg_test_email_to'] ?? get_bloginfo( 'admin_email' ) );
			if ( ! is_email( $test_to ) ) {
				$errors[] = 'Invalid test recipient email address.';
			} else {
				$mailer = Mailer::make();
				$ok     = $mailer->send_html(
					$test_to,
					'Certificate Generator — Test Email',
					'<p>This is a test email from <strong>Certificate Generator</strong>. Your mail transport is working correctly.</p>'
				);
				if ( $ok ) {
					$message = 'Test email sent to <strong>' . esc_html( $test_to ) . '</strong>.';
				} else {
					$errors[] = 'Test email failed. Check transport settings and server mail logs.';
				}
			}
		}

		// ── Current values ─────────────────────────────────────────────────────
		$transport  = SettingsService::get( 'cg_email_transport' );
		$from_name  = SettingsService::get( 'cg_email_from_name' );
		$from_email = SettingsService::get( 'cg_email_from_email' );
		$subject    = SettingsService::get( 'cg_email_subject' );
		$body       = SettingsService::get( 'cg_email_body' );

		$smtp      = SettingsService::get_smtp_config();
		$smtp_host = $smtp['host'];
		$smtp_port = $smtp['port'];
		$smtp_user = $smtp['username'];
		$smtp_enc  = $smtp['encryption'];
		?>
		<div class="wrap">
			<h1>Email Settings</h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $message ); ?></p></div>
			<?php endif; ?>
			<?php foreach ( $errors as $err ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div>
			<?php endforeach; ?>

			<form method="post">
				<?php wp_nonce_field( 'cg_email_settings_save', 'cg_email_settings_nonce' ); ?>

				<h2>Transport</h2>
				<table class="form-table">
					<tr>
						<th><label for="cg_email_transport">Mail Transport</label></th>
						<td>
							<select id="cg_email_transport" name="cg_email_transport"
									onchange="document.getElementById('cg-smtp-settings').style.display=this.value==='smtp'?'':'none'">
								<option value="wp_mail" <?php selected( $transport, 'wp_mail' ); ?>>wp_mail (server default)</option>
								<option value="smtp"    <?php selected( $transport, 'smtp' ); ?>>SMTP (custom)</option>
							</select>
							<p class="description">Use <em>wp_mail</em> if your host handles outbound mail. Use <em>SMTP</em> to configure Gmail, SendGrid, Mailgun, etc.</p>
						</td>
					</tr>
				</table>

				<h2>Sender</h2>
				<table class="form-table">
					<?php
					include $this->partials_dir . 'email-from-fields.php';
					?>
				</table>

				<h2>Certificate Email Template</h2>
				<p class="description" style="margin-bottom:12px">
					Placeholders: <code>{name}</code> <code>{certificate_title}</code> <code>{serial_number}</code> <code>{expires_at}</code> <code>{issue_date}</code>. WordPress shortcodes supported.
				</p>
				<table class="form-table">
					<tr>
						<th><label for="cg_email_subject">Subject</label></th>
						<td><input type="text" id="cg_email_subject" name="cg_email_subject"
									value="<?php echo esc_attr( $subject ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th><label for="cg_email_body">Body (HTML)</label></th>
						<td><textarea id="cg_email_body" name="cg_email_body" rows="8" class="large-text"><?php echo esc_textarea( $body ); ?></textarea></td>
					</tr>
				</table>

				<div id="cg-smtp-settings" style="<?php echo $transport === 'smtp' ? '' : 'display:none'; ?>">
					<h2>SMTP Configuration</h2>
					<table class="form-table">
						<tr>
							<th><label for="cg_smtp_host">SMTP Host</label></th>
							<td><input type="text" id="cg_smtp_host" name="cg_smtp_host"
										value="<?php echo esc_attr( $smtp_host ); ?>" class="regular-text" placeholder="smtp.gmail.com"></td>
						</tr>
						<tr>
							<th><label for="cg_smtp_port">Port</label></th>
							<td><input type="number" id="cg_smtp_port" name="cg_smtp_port"
										value="<?php echo esc_attr( $smtp_port ); ?>" class="small-text" min="1" max="65535"></td>
						</tr>
						<tr>
							<th><label for="cg_smtp_encryption">Encryption</label></th>
							<td>
								<select id="cg_smtp_encryption" name="cg_smtp_encryption">
									<option value="tls"  <?php selected( $smtp_enc, 'tls' ); ?>>TLS (STARTTLS) — port 587</option>
									<option value="ssl"  <?php selected( $smtp_enc, 'ssl' ); ?>>SSL — port 465</option>
									<option value="none" <?php selected( $smtp_enc, 'none' ); ?>>None — port 25</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="cg_smtp_username">Username</label></th>
							<td><input type="text" id="cg_smtp_username" name="cg_smtp_username"
										value="<?php echo esc_attr( $smtp_user ); ?>" class="regular-text" autocomplete="off"></td>
						</tr>
						<tr>
							<th><label for="cg_smtp_password">Password</label></th>
							<td>
								<input type="password" id="cg_smtp_password" name="cg_smtp_password"
										value="" class="regular-text" autocomplete="new-password"
										placeholder="Leave blank to keep current password">
								<?php if ( SettingsService::get( 'cg_smtp_password' ) ) : ?>
									<p class="description">Password saved. Enter a new one to replace it.</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( 'Save Settings' ); ?>
			</form>

			<hr>
			<h2>Send Test Email</h2>
			<form method="post" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
				<?php wp_nonce_field( 'cg_email_test_send', 'cg_email_test_nonce' ); ?>
				<div>
					<label for="cg_test_email_to" style="display:block;margin-bottom:4px;font-weight:600">Recipient</label>
					<input type="email" id="cg_test_email_to" name="cg_test_email_to"
							value="<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>"
							class="regular-text" required>
				</div>
				<button type="submit" class="button button-secondary">Send Test</button>
			</form>
		</div>
		<?php
	}
}
