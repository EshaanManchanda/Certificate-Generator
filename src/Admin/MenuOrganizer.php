<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin;

/**
 * Centralized admin menu organizer.
 * Registers all submenu pages in a clean, logical order under cg-dashboard.
 */
class MenuOrganizer {

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
	}

	public function register_submenu(): void {
		// ── Entity Management ──
		// (StudentsPage, TeachersPage, SchoolsPage, TemplatesPage register themselves)

		// ── Bulk Operations ──
		add_submenu_page(
			'cg-dashboard',
			'Bulk Import',
			'Bulk Import',
			'manage_options',
			'cg-bulk-import',
			array( $this, 'render_bulk_import' )
		);

		add_submenu_page(
			'cg-dashboard',
			'Bulk Export',
			'Bulk Export',
			'manage_options',
			'cg-bulk-export',
			array( $this, 'render_bulk_export' )
		);

		add_submenu_page(
			'cg-dashboard',
			'Bulk Serial Numbers',
			'Bulk Serials',
			'manage_options',
			'cg-bulk-serials',
			array( $this, 'render_bulk_serials' )
		);

		// ── Email Operations ──
		add_submenu_page(
			'cg-dashboard',
			'Bulk Send Certificates',
			'Bulk Send',
			'manage_options',
			'certificate-bulk-send',
			array( $this, 'render_bulk_send' )
		);

		add_submenu_page(
			'cg-dashboard',
			'Email Logs',
			'Email Logs',
			'manage_options',
			'certificate-email-logs',
			array( $this, 'render_email_logs' )
		);

		// ── Analytics & Settings ──
		add_submenu_page(
			'cg-dashboard',
			'Certificate Analytics',
			'Analytics',
			'manage_options',
			'cg-analytics',
			array( $this, 'render_analytics' )
		);

		add_submenu_page(
			'cg-dashboard',
			'Serial Number Settings',
			'Serial Settings',
			'manage_options',
			'cg-serial-settings',
			array( $this, 'render_serial_settings' )
		);

		// ── Migration (last) ──
		// MigrationPage registers itself
	}

	// ── Renderers (proxy to existing functions) ──

	public function render_bulk_import(): void {
		if ( ! function_exists( 'bulk_import_students' ) ) {
			require_once CERTIFICATE_GENERATOR_PATH . 'includes/Services/bulk-import.php';
		}
		$tab = sanitize_key( $_GET['tab'] ?? 'students' );
		$this->render_tabs(
			'Bulk Import',
			array(
				'students'     => 'Students',
				'teachers'     => 'Teachers',
				'schools'      => 'Schools',
				'certificates' => 'Certificates',
			),
			$tab,
			function ( $t ) {
				switch ( $t ) {
					case 'students':
						bulk_import_students();
						break;
					case 'teachers':
						bulk_import_teachers();
						break;
					case 'schools':
						bulk_import_schools();
						break;
					case 'certificates':
						bulk_import_certificates();
						break;
				}
			}
		);
	}

	public function render_bulk_export(): void {
		if ( ! function_exists( 'render_bulk_export_students_page' ) ) {
			require_once CERTIFICATE_GENERATOR_PATH . 'includes/Services/bulk-export.php';
		}
		$tab = sanitize_key( $_GET['tab'] ?? 'students' );
		$this->render_tabs(
			'Bulk Export',
			array(
				'students'     => 'Students',
				'teachers'     => 'Teachers',
				'schools'      => 'Schools',
				'certificates' => 'Certificates',
			),
			$tab,
			function ( $t ) {
				switch ( $t ) {
					case 'students':
						render_bulk_export_students_page();
						break;
					case 'teachers':
						render_bulk_export_teachers_page();
						break;
					case 'schools':
						render_bulk_export_schools_page();
						break;
					case 'certificates':
						render_bulk_export_certificates_page();
						break;
				}
			}
		);
	}

	public function render_bulk_serials(): void {
		$instance = \CG_Bulk_Serial_Generator::get_instance();
		$instance->render_bulk_serial_page();
	}

	public function render_bulk_send(): void {
		if ( function_exists( 'render_bulk_send_page' ) ) {
			render_bulk_send_page();
		} else {
			echo '<div class="wrap"><h1>Bulk Send Certificates</h1><p>Bulk send functionality.</p></div>';
		}
	}

	public function render_email_logs(): void {
		if ( function_exists( 'render_email_logs_page' ) ) {
			render_email_logs_page();
		} else {
			echo '<div class="wrap"><h1>Email Logs</h1><p>Email logs functionality.</p></div>';
		}
	}

	public function render_analytics(): void {
		if ( class_exists( '\CG_Analytics' ) ) {
			$instance = \CG_Analytics::get_instance();
			$instance->render_analytics_page();
		} else {
			echo '<div class="wrap"><h1>Analytics</h1><p>Analytics functionality.</p></div>';
		}
	}

	public function render_serial_settings(): void {
		if ( class_exists( '\CG_Settings' ) ) {
			$instance = \CG_Settings::get_instance();
			$instance->render_serial_settings_page();
		} else {
			echo '<div class="wrap"><h1>Serial Settings</h1><p>Serial settings functionality.</p></div>';
		}
	}

	// ── Tab rendering helper ──

	private function render_tabs( string $title, array $tabs, string $active, callable $render ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}
		$base_url = admin_url( 'admin.php?page=' . ( $title === 'Bulk Import' ? 'cg-bulk-import' : 'cg-bulk-export' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
						class="nav-tab <?php echo $active === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<div style="margin-top: 20px;">
				<?php $render( $active ); ?>
			</div>
		</div>
		<?php
	}
}
