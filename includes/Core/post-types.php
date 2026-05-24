<?php
/*
Plugin Name: Custom Post Types with ACF and Meta Boxes
Description: A plugin to create custom post types, advanced custom fields, and custom meta boxes for Students, Teachers, Schools, and Certificates.
Version: 1.3
Author: Your Name
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/../Services/certificate-search.php';
require_once __DIR__ . '/../Admin/columns.php';
// Function to Register Custom Post Types
function register_custom_post_type( $type, $singular, $plural, $supports = array( 'title', 'custom-fields' ) ) {
	register_post_type(
		$type,
		array(
			'labels'       => array(
				'name'               => __( $plural ),
				'singular_name'      => __( $singular ),
				'add_new'            => __( 'Add New ' . $singular ),
				'add_new_item'       => __( 'Add New ' . $singular ),
				'edit_item'          => __( 'Edit ' . $singular ),
				'new_item'           => __( 'New ' . $singular ),
				'view_item'          => __( 'View ' . $singular ),
				'search_items'       => __( 'Search ' . $plural ),
				'not_found'          => __( 'No ' . strtolower( $plural ) . ' found' ),
				'not_found_in_trash' => __( 'No ' . strtolower( $plural ) . ' found in Trash' ),
				'all_items'          => __( 'All ' . $plural ),
				'menu_name'          => __( $plural ),
			),
			'public'       => true,
			'has_archive'  => true,
			'supports'     => &$supports,
			'show_ui'      => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
		)
	);
}

// Register All Custom Post Types
// NOTE: students/teachers/schools removed — plugin now uses wp_cg_* SQL tables exclusively.
// NOTE: certificates CPT hidden from admin — templates managed via SQL-backed Templates page.
function register_custom_post_types() {
	register_custom_post_type( 'certificates', 'Certificate', 'Certificates' );
	// Hide certificates CPT from admin menu (SQL-backed Templates page is primary)
	add_action(
		'admin_menu',
		function () {
			remove_menu_page( 'edit.php?post_type=certificates' );
		},
		999
	);
}

/**
 * Sync Certificate-Generator post meta to WP Dynamic Tags on save.
 * Creates/updates tags like [cg_student_name_123], [cg_last_student_name], etc.
 * Conditional on WP_Dynamic_Tags_Table_Manager being available.
 *
 * @param int $post_id The saved post ID.
 */
function cg_sync_to_dynamic_tags( $post_id ) {
	if ( ! class_exists( 'WP_Dynamic_Tags_Table_Manager' ) ) {
		return;
	}

	// Avoid infinite loops from wp_update_post inside save_post
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	if ( ! in_array( $post_type, array( 'students', 'teachers', 'schools' ), true ) ) {
		return;
	}

	// Do not create dynamic tags for student records (user requirement).
	if ( $post_type === 'students' ) {
		return;
	}

	$manager = WP_Dynamic_Tags_Table_Manager::get_instance();

	// Determine name meta key by post type
	$name_key = ( $post_type === 'students' ) ? 'student_name' : ( ( $post_type === 'teachers' ) ? 'teacher_name' : 'school_name' );

	$fields = array(
		'cg_' . $post_type . '_name_' . $post_id  => get_post_meta( $post_id, $name_key, true ),
		'cg_' . $post_type . '_email_' . $post_id => get_post_meta( $post_id, 'email', true ),
		'cg_school_name_' . $post_id              => get_post_meta( $post_id, 'school_name', true ),
		'cg_cert_type_' . $post_id                => get_post_meta( $post_id, 'certificate_type', true ),
		'cg_issue_date_' . $post_id               => get_post_meta( $post_id, 'issue_date', true ),
	);

	// Summary / last-operation tags
	if ( $post_type === 'schools' ) {
		$fields['cg_last_school_name'] = get_post_meta( $post_id, 'school_name', true );
	}

	// Sync extra fields registered for this post's certificate type
	if ( class_exists( 'CG_Field_Schema' ) ) {
		$cert_type    = get_post_meta( $post_id, 'certificate_type', true );
		$extra_fields = CG_Field_Schema::get_extra_fields( $cert_type );
		foreach ( $extra_fields as $slug ) {
			$value = get_post_meta( $post_id, $slug, true );
			if ( $value !== '' && $value !== null ) {
				$fields[ 'cg_' . $slug . '_' . $post_id ] = $value;
			}
		}
	}

	// Resolve the "Certificates" tag group term ID so tags appear under the right group
	$cert_term        = get_term_by( 'slug', 'certificates', 'tag_groups' );
	$cert_category_id = ( $cert_term && ! is_wp_error( $cert_term ) ) ? (int) $cert_term->term_id : 0;

	foreach ( $fields as $shortcode => $value ) {
		if ( $value === '' || $value === null ) {
			continue;
		}

		$tag_name = ucwords( str_replace( array( 'cg_', '_' ), array( '', ' ' ), $shortcode ) );

		$existing = $manager->get_tag_by_shortcode( $shortcode );
		if ( $existing && ! empty( $existing->id ) ) {
			$manager->update_tag( $existing->id, array( 'content' => $value ) );
		} else {
			$manager->create_tag(
				array(
					'tag_name'    => $tag_name,
					'shortcode'   => $shortcode,
					'content'     => $value,
					'category_id' => $cert_category_id,
				)
			);
		}
	}
}

// CPT save hooks removed — cg_sync_to_dynamic_tags is now triggered from SQL admin page saves.

/**
 * Populate extra_fields JSON column in the SQL table whenever a student/teacher/school is saved.
 * Core columns (name, email, phone, school_name, certificate_type, issue_date, etc.) have their
 * own SQL columns; everything else (registered via CG_Field_Schema) goes to extra_fields JSON.
 */
function cg_sync_extra_fields_to_sql( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		return;
	}
	if ( ! class_exists( 'CG_Field_Schema' ) ) {
		return;
	}

	$post_type = get_post_type( $post_id );
	if ( ! in_array( $post_type, array( 'students', 'teachers', 'schools' ), true ) ) {
		return;
	}

	global $wpdb;
	$tables = \CertificateGenerator\Database\CustomTables::instance();
	$table  = $tables->get_table( $post_type );
	if ( ! $table || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return;
	}

	// Core columns that already have dedicated SQL columns — skip from JSON
	$core_keys = array(
		'student_name',
		'teacher_name',
		'school_name',
		'email',
		'phone',
		'certificate_type',
		'issue_date',
		'school_abbreviation',
		'place',
		'email_status',
		'send_email',
		'wp_post_id',
		'status',
		'created_at',
		'updated_at',
		'id',
	);

	$cert_type   = get_post_meta( $post_id, 'certificate_type', true );
	$extra_slugs = CG_Field_Schema::get_extra_fields( $cert_type );

	$extra = array();
	foreach ( $extra_slugs as $slug ) {
		if ( in_array( $slug, $core_keys, true ) ) {
			continue;
		}
		$value = get_post_meta( $post_id, $slug, true );
		if ( $value !== '' && $value !== null ) {
			// Strip field_ prefix for cleaner JSON keys
			$key           = preg_replace( '/^field_/', '', $slug );
			$extra[ $key ] = $value;
		}
	}

	if ( empty( $extra ) ) {
		return;
	}

	$wpdb->update(
		$table,
		array( 'extra_fields' => wp_json_encode( $extra ) ),
		array( 'wp_post_id' => $post_id ),
		array( '%s' ),
		array( '%d' )
	);
}
// CPT extra-fields sync hooks removed — SQL admin pages write extra_fields directly on save.

// Add Email Logs Meta Box for Certificates
function add_certificate_email_logs_meta_box() {
	add_meta_box(
		'certificate_email_logs_meta_box',
		'Email Logs',
		'render_certificate_email_logs',
		'certificates',
		'normal',
		'low'
	);
}
add_action( 'add_meta_boxes', 'add_certificate_email_logs_meta_box' );

// Add Email Status Meta Box for Students, Teachers, Schools
function add_email_status_meta_box() {
	$post_types = array( 'students', 'teachers', 'schools' );
	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'email_status_meta_box',
			'Certificate Email Status',
			'render_email_status_meta_box',
			$post_type,
			'side',
			'high'
		);
	}
}
add_action( 'add_meta_boxes', 'add_email_status_meta_box' );

// Render Email Status Meta Box
function render_email_status_meta_box( $post ) {
	$email            = get_post_meta( $post->ID, 'email', true );
	$certificate_type = get_post_meta( $post->ID, 'certificate_type', true );

	echo '<div class="email-status-container">';

	if ( empty( $email ) ) {
		echo '<div class="email-status-item no-email">';
		echo '<span class="dashicons dashicons-warning"></span>';
		echo '<span class="status-text">' . __( 'No email address configured', 'certificate-generator' ) . '</span>';
		echo '</div>';
		echo '<p class="description">' . __( 'Add an email address to enable certificate email functionality.', 'certificate-generator' ) . '</p>';
	} else {
		// Check if email was already sent
		$email_sent = certificate_generator_email_already_sent( $post->ID, $email );

		echo '<div class="email-status-item">';
		echo '<label><strong>' . __( 'Email Address:', 'certificate-generator' ) . '</strong></label>';
		echo '<span class="status-value">' . esc_html( $email ) . '</span>';
		echo '</div>';

		echo '<div class="email-status-item">';
		echo '<label><strong>' . __( 'Email Status:', 'certificate-generator' ) . '</strong></label>';
		if ( $email_sent ) {
			echo '<span class="status-value sent">';
			echo '<span class="dashicons dashicons-yes-alt"></span>';
			echo __( 'Email sent successfully', 'certificate-generator' );
			echo '</span>';
		} else {
			echo '<span class="status-value pending">';
			echo '<span class="dashicons dashicons-email-alt"></span>';
			echo __( 'Email not sent yet', 'certificate-generator' );
			echo '</span>';
		}
		echo '</div>';

		// Check certificate template
		if ( ! empty( $certificate_type ) ) {
			// Check if template exists
			$template_query = new WP_Query(
				array(
					'post_type'      => 'certificates',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'     => 'certificate_type',
							'value'   => $certificate_type,
							'compare' => '=',
						),
					),
				)
			);

			echo '<div class="email-status-item">';
			echo '<label><strong>' . __( 'Certificate Template:', 'certificate-generator' ) . '</strong></label>';
			if ( $template_query->have_posts() ) {
				echo '<span class="status-value template-found">';
				echo '<span class="dashicons dashicons-yes-alt"></span>';
				printf( __( 'Template found for "%s"', 'certificate-generator' ), esc_html( $certificate_type ) );
				echo '</span>';
			} else {
				echo '<span class="status-value template-missing">';
				echo '<span class="dashicons dashicons-warning"></span>';
				printf( __( 'No template found for "%s"', 'certificate-generator' ), esc_html( $certificate_type ) );
				echo '</span>';
			}
			echo '</div>';
		}

		// Add send email button
		if ( ! $email_sent ) {
			echo '<div class="email-actions">';
			echo '<button type="button" class="button button-primary send-individual-email" data-post-id="' . esc_attr( $post->ID ) . '">';
			echo __( 'Send Certificate Email', 'certificate-generator' );
			echo '</button>';
			echo '<span class="individual-email-status"></span>';
			echo '</div>';
		}
	}

	echo '</div>';

	// Add inline styles
	echo '<style>
        .email-status-container {
            font-size: 13px;
        }

        .email-status-item {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .email-status-item.no-email {
            color: #d63638;
            align-items: center;
        }

        .email-status-item label {
            min-width: 80px;
            font-weight: 600;
            margin: 0;
        }

        .status-value {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .status-value.sent {
            color: #46b450;
        }

        .status-value.pending {
            color: #ffba00;
        }

        .status-value.template-found {
            color: #46b450;
        }

        .status-value.template-missing {
            color: #d63638;
        }

        .email-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .individual-email-status {
            display: block;
            margin-top: 8px;
            font-size: 12px;
        }

        .dashicons {
            width: 16px;
            height: 16px;
            font-size: 16px;
        }
    </style>';

	// Add JavaScript for individual email sending
	echo '<script>
        jQuery(document).ready(function($) {
            $(".send-individual-email").on("click", function() {
                var button = $(this);
                var postId = button.data("post-id");
                var statusSpan = $(".individual-email-status");

                button.prop("disabled", true);
                statusSpan.html("<span class=\"spinner is-active\" style=\"float: none; margin: 0;\"></span> Sending...");

                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: "certificate_generator_send_single_email",
                        post_id: postId,
                        nonce: "' . wp_create_nonce( 'certificate_generator_send_email' ) . '"
                    },
                    success: function(response) {
                        button.prop("disabled", false);
                        if (response.success) {
                            statusSpan.html("<div style=\"color: green;\">" + response.data.message + "</div>");
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            statusSpan.html("<div style=\"color: red;\">" + response.data.message + "</div>");
                        }
                    },
                    error: function() {
                        button.prop("disabled", false);
                        statusSpan.html("<div style=\"color: red;\">An error occurred. Please try again.</div>");
                    }
                });
            });
        });
    </script>';
}

// Render Email Logs Meta Box
function render_certificate_email_logs( $post ) {
	// Get logs using the core function
	$logs_data = certificate_generator_get_email_logs(
		array(
			'per_page' => 10,
			'cert_id'  => $post->ID,
		)
	);

	$logs = $logs_data['logs'];

	// Add inline styles
	echo '<style>
        #certificate_email_logs_meta_box .inside {
            padding: 0;
            margin: 0;
        }
        #certificate_email_logs_meta_box table {
            border: none;
            margin: 0;
        }
        #certificate_email_logs_meta_box th {
            background: #f8f9fa;
            padding: 8px;
        }
        #certificate_email_logs_meta_box td {
            padding: 12px 8px;
        }
        #certificate_email_logs_meta_box .error {
            color: #dc3545;
        }
        #certificate_email_logs_meta_box .button {
            margin: 10px;
        }
    </style>';

	if ( empty( $logs ) ) {
		echo '<p>' . esc_html__( 'No email logs found for this certificate.', 'certificate-generator' ) . '</p>';
		return;
	}

	echo '<table class="widefat fixed striped">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Date', 'certificate-generator' ) . '</th>';
	echo '<th>' . esc_html__( 'Recipient', 'certificate-generator' ) . '</th>';
	echo '<th>' . esc_html__( 'Status', 'certificate-generator' ) . '</th>';
	echo '<th>' . esc_html__( 'Details', 'certificate-generator' ) . '</th>';
	echo '</tr></thead>';

	foreach ( $logs as $log ) {
		echo '<tr>';
		echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->sent_at ) ) ) . '</td>';
		echo '<td>' . esc_html( $log->recipient_email ) . '<br>' . esc_html( $log->recipient_name ) . '</td>';
		echo '<td>' . esc_html( ucfirst( $log->status ) ) . '</td>';
		echo '<td>';
		if ( $log->status === 'failed' && ! empty( $log->error_message ) ) {
			echo '<span class="error">' . esc_html( $log->error_message ) . '</span>';
		} else {
			echo esc_html( $log->email_subject );
		}
		echo '</td>';
		echo '</tr>';
	}

	echo '</table>';
	echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=certificate-email-logs' ) ) . '" class="button">' . esc_html__( 'View All Logs', 'certificate-generator' ) . '</a></p>';
}
add_action( 'init', 'register_custom_post_types' );


// Define Fields for Students
function render_students_form( $post ) {
	$student_name     = get_post_meta( $post->ID, 'student_name', true );
	$email            = get_post_meta( $post->ID, 'email', true );
	$school_name      = get_post_meta( $post->ID, 'school_name', true );
	$issue_date       = get_post_meta( $post->ID, 'issue_date', true );
	$certificate_type = get_post_meta( $post->ID, 'certificate_type', true );
	?>
	<div class="custom-form-wrap">
		<?php wp_nonce_field( 'cg_save_student', 'cg_student_nonce' ); ?>

		<!-- Card 1: Student Info -->
		<div class="cg-student-card">
			<div class="cg-student-card-header">
				<span class="dashicons dashicons-admin-users"></span> Student Information
			</div>
			<div class="custom-form-group">
				<label for="student_name">Student Name <span class="cg-required">*</span></label>
				<input type="text" id="student_name" name="student_name"
					value="<?php echo esc_attr( $student_name ); ?>"
					class="custom-form-input" data-required="true" autocomplete="name">
				<span class="cg-field-error">Student Name is required.</span>
			</div>
			<div class="custom-form-group">
				<label for="email">Email <span class="cg-required">*</span></label>
				<input type="email" id="email" name="email"
					value="<?php echo esc_attr( $email ); ?>"
					class="custom-form-input" data-required="true" autocomplete="email">
				<span class="cg-field-error">A valid email address is required.</span>
			</div>
		</div>

		<!-- Card 2: Certificate Info -->
		<div class="cg-student-card">
			<div class="cg-student-card-header">
				<span class="dashicons dashicons-awards"></span> Certificate Details
			</div>
			<div class="custom-form-group">
				<label for="school_name">School Name</label>
				<input type="text" id="school_name" name="school_name"
					value="<?php echo esc_attr( $school_name ); ?>"
					class="custom-form-input cg-school-autocomplete" autocomplete="off">
			</div>
			<div class="custom-form-group">
				<label for="issue_date">Issue Date</label>
				<input type="date" id="issue_date" name="issue_date"
					value="<?php echo esc_attr( class_exists( '\CertificateGenerator\Helpers\DateHelper' ) ? \CertificateGenerator\Helpers\DateHelper::to_html5( $issue_date ) : $issue_date ); ?>"
					class="custom-form-input">
			</div>
			<div class="custom-form-group">
				<label for="certificate_type">Certificate Type <span class="cg-required">*</span></label>
				<input type="text" id="certificate_type" name="certificate_type"
					value="<?php echo esc_attr( $certificate_type ); ?>"
					class="custom-form-input" data-required="true">
				<span class="cg-field-error">Certificate Type is required.</span>
			</div>
		</div>
	</div>
	<?php
}

// Extra Fields Meta Box for Students
function add_students_extra_fields_meta_box() {
	add_meta_box(
		'students_extra_fields_meta_box',
		'Extra Fields',
		'render_students_extra_fields_form',
		'students',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'add_students_extra_fields_meta_box' );

function render_students_extra_fields_form( $post ) {
	$certificate_type = get_post_meta( $post->ID, 'certificate_type', true );
	if ( ! class_exists( 'CG_Field_Schema' ) ) {
		return;
	}
	$extra_fields = CG_Field_Schema::get_extra_fields( $certificate_type );
	?>
	<div class="cg-student-card" id="cg-extra-fields-card">
		<?php if ( empty( $extra_fields ) ) : ?>
			<p class="description" id="cg-no-extra-msg">
				<?php
				echo $certificate_type
					? esc_html( "No extra fields registered for \"{$certificate_type}\" yet." )
					: 'Set a Certificate Type in the Student Details box above, then add fields here.';
				?>
			</p>
		<?php else : ?>
			<p class="description">
				Extra fields for certificate type: <strong><?php echo esc_html( $certificate_type ); ?></strong>
			</p>
		<?php endif; ?>

		<div id="cg-extra-fields-list">
			<?php
			foreach ( $extra_fields as $slug ) :
				$value = get_post_meta( $post->ID, $slug, true );
				$label = CG_Field_Schema::get_display_label( $slug );
				?>
				<div class="custom-form-group">
					<label for="cg_extra_<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></label>
					<input type="text"
						id="cg_extra_<?php echo esc_attr( $slug ); ?>"
						name="cg_extra_field[<?php echo esc_attr( $slug ); ?>]"
						value="<?php echo esc_attr( $value ); ?>"
						class="custom-form-input">
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Inline Add-Field creator -->
		<div class="cg-add-field-section"
			data-post-id="<?php echo esc_attr( $post->ID ); ?>"
			data-cert-type="<?php echo esc_attr( $certificate_type ); ?>"
			data-nonce="<?php echo wp_create_nonce( 'cg_add_extra_field' ); ?>">
			<strong>Add New Field</strong>
			<div class="cg-add-field-row" style="margin-top:8px;">
				<input type="text" id="cg_new_field_slug" placeholder="e.g. team_name"
					class="custom-form-input" style="max-width:200px;">
				<button type="button" id="cg_add_field_btn" class="button button-secondary">
					+ Add Field
				</button>
			</div>
			<p class="description" style="margin-top:4px;">
				Lowercase letters and underscores only. The new field will be saved to the schema for "<strong><?php echo esc_html( $certificate_type ?: 'this certificate type' ); ?></strong>".
			</p>
			<div class="cg-add-field-status"></div>
		</div>
	</div>
	<?php
}

// Add Meta Box for Students Post Type
function add_students_meta_box() {
	add_meta_box(
		'students_meta_box',
		'Student Details',
		'render_students_form',
		'students',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'add_students_meta_box' );

// Save Students Data and Prevent Infinite Loop
function save_students_data( $post_id ) {
	// Prevent recursion
	static $updating = false;
	if ( $updating ) {
		return;
	}

	// Check if this is an autosave or if it's not a 'students' post type
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['cg_student_nonce'] ) || ! wp_verify_nonce( $_POST['cg_student_nonce'], 'cg_save_student' ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'students' ) {
		return;
	}

	// Update meta fields
	if ( isset( $_POST['student_name'] ) ) {
		update_post_meta( $post_id, 'student_name', sanitize_text_field( $_POST['student_name'] ) );
	}

	if ( isset( $_POST['email'] ) ) {
		update_post_meta( $post_id, 'email', sanitize_email( $_POST['email'] ) );
	}

	if ( isset( $_POST['school_name'] ) ) {
		update_post_meta( $post_id, 'school_name', sanitize_text_field( $_POST['school_name'] ) );
	}

	if ( isset( $_POST['issue_date'] ) ) {
		$_raw_issue    = sanitize_text_field( $_POST['issue_date'] );
		$_stored_issue = class_exists( '\CertificateGenerator\Helpers\DateHelper' )
			? \CertificateGenerator\Helpers\DateHelper::to_storage( $_raw_issue )
			: ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_raw_issue ) ? $_raw_issue : null );
		if ( $_stored_issue !== null ) {
			update_post_meta( $post_id, 'issue_date', $_stored_issue );
		}
	}

	if ( isset( $_POST['certificate_type'] ) ) {
		update_post_meta( $post_id, 'certificate_type', sanitize_text_field( $_POST['certificate_type'] ) );
	}

	// Save extra fields for this student's certificate type
	if ( class_exists( 'CG_Field_Schema' ) && isset( $_POST['cg_extra_field'] ) && is_array( $_POST['cg_extra_field'] ) ) {
		$cert_type    = sanitize_text_field( $_POST['certificate_type'] ?? '' );
		$extra_fields = CG_Field_Schema::get_extra_fields( $cert_type );
		foreach ( $extra_fields as $slug ) {
			if ( array_key_exists( $slug, $_POST['cg_extra_field'] ) ) {
				update_post_meta( $post_id, $slug, sanitize_text_field( $_POST['cg_extra_field'][ $slug ] ) );
			}
		}
	}

	// Auto-generate the title
	$student_name = get_post_meta( $post_id, 'student_name', true );
	$school_name  = get_post_meta( $post_id, 'school_name', true );

	if ( $student_name || $school_name ) {
		$updating  = true; // Prevent recursive calls
		$new_title = ( $student_name ?: 'Student' ) . ' - ' . ( $school_name ?: 'Unknown School' );
		$new_slug  = sanitize_title( $new_title );

		// Update the post title and slug
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $new_title,
				'post_name'  => $new_slug,
			)
		);
		$updating = false;
	}
}
add_action( 'save_post', 'save_students_data' );

// AJAX: Register a new extra field slug into the schema
add_action( 'wp_ajax_cg_add_extra_field', 'cg_ajax_add_extra_field' );
function cg_ajax_add_extra_field() {
	check_ajax_referer( 'cg_add_extra_field', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$cert_type = sanitize_text_field( $_POST['cert_type'] ?? '' );
	$slug      = sanitize_key( $_POST['slug'] ?? '' );

	if ( empty( $cert_type ) || empty( $slug ) ) {
		wp_send_json_error( array( 'message' => 'Certificate type and field name are required.' ) );
	}

	if ( ! class_exists( 'CG_Field_Schema' ) ) {
		wp_send_json_error( array( 'message' => 'Field schema not available.' ) );
	}

	$registered = CG_Field_Schema::register_field( $cert_type, $slug );
	if ( ! $registered ) {
		$existing = CG_Field_Schema::get_extra_fields( $cert_type );
		if ( in_array( $slug, $existing, true ) ) {
			wp_send_json_error( array( 'message' => "Field \"{$slug}\" is already registered." ) );
		}
		wp_send_json_error( array( 'message' => "Could not register \"{$slug}\". It may be a reserved name or the slot limit (15) has been reached." ) );
	}

	$label = CG_Field_Schema::get_display_label( $slug );
	$html  = '<div class="custom-form-group" id="cg-extra-row-' . esc_attr( $slug ) . '">'
			. '<label for="cg_extra_' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</label>'
			. '<input type="text" id="cg_extra_' . esc_attr( $slug ) . '" '
			. 'name="cg_extra_field[' . esc_attr( $slug ) . ']" '
			. 'value="" class="custom-form-input">'
			. '</div>';

	wp_send_json_success(
		array(
			'html'  => $html,
			'slug'  => $slug,
			'label' => $label,
		)
	);
}

// AJAX: Return school names matching a search term
add_action( 'wp_ajax_cg_school_autocomplete', 'cg_ajax_school_autocomplete' );
function cg_ajax_school_autocomplete() {
	check_ajax_referer( 'cg_school_autocomplete', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json( array() );
	}

	$term = sanitize_text_field( $_GET['term'] ?? '' );
	if ( strlen( $term ) < 2 ) {
		wp_send_json( array() );
	}

	global $wpdb;
	$like    = '%' . $wpdb->esc_like( $term ) . '%';
	$results = array();

	// SQL-first: query wp_cg_schools
	if ( class_exists( '\CertificateGenerator\Database\CustomTables' ) ) {
		$tables = \CertificateGenerator\Database\CustomTables::instance();
		$tbl    = $tables->get_table( 'schools' );
		if ( $tbl && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) {
			$results = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT school_name FROM $tbl WHERE school_name LIKE %s ORDER BY school_name ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$like
				)
			);
		}
	}

	wp_send_json( array_values( array_filter( $results ) ) );
}


// Define Fields for Teachers
function render_teachers_form( $post ) {
	$teacher_name        = get_post_meta( $post->ID, 'teacher_name', true );
	$email               = get_post_meta( $post->ID, 'email', true );
	$school_name         = get_post_meta( $post->ID, 'school_name', true );
	$school_abbreviation = get_post_meta( $post->ID, 'school_abbreviation', true );
	$issue_date          = get_post_meta( $post->ID, 'issue_date', true );
	$certificate_type    = get_post_meta( $post->ID, 'certificate_type', true );
	?>
	<div class="custom-form-wrap">
		<?php wp_nonce_field( 'cg_save_teacher', 'cg_teacher_nonce' ); ?>
		<div class="custom-form-group">
			<label for="teacher_name">Teacher Name:</label>
			<input type="text" id="teacher_name" name="teacher_name" value="<?php echo esc_attr( $teacher_name ); ?>"
				class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="email">Email:</label>
			<input type="email" id="email" name="email" value="<?php echo esc_attr( $email ); ?>" class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="school_name">School Name:</label>
			<input type="text" id="school_name" name="school_name" value="<?php echo esc_attr( $school_name ); ?>"
				class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="school_abbreviation">School Abbreviation:</label>
			<input type="text" id="school_abbreviation" name="school_abbreviation"
				value="<?php echo esc_attr( $school_abbreviation ); ?>" class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="issue_date">Issue Date:</label>
			<input type="date" id="issue_date" name="issue_date" value="<?php echo esc_attr( class_exists( '\CertificateGenerator\Helpers\DateHelper' ) ? \CertificateGenerator\Helpers\DateHelper::to_html5( $issue_date ) : $issue_date ); ?>"
				class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="certificate_type">Certificate Type:</label>
			<input type="text" id="certificate_type" name="certificate_type"
				value="<?php echo esc_attr( $certificate_type ); ?>" class="custom-form-input">
		</div>
	</div>
	<?php
}

// Add Meta Box for Teachers Post Type
function add_teachers_meta_box() {
	add_meta_box(
		'teachers_meta_box',
		'Teacher Details',
		'render_teachers_form',
		'teachers',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'add_teachers_meta_box' );

// Save Teachers Data
function save_teachers_data( $post_id ) {
	// Check if data is being saved correctly
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['cg_teacher_nonce'] ) || ! wp_verify_nonce( $_POST['cg_teacher_nonce'], 'cg_save_teacher' ) ) {
		return;
	}
	// Ensure this is for the 'teachers' post type
	if ( get_post_type( $post_id ) !== 'teachers' ) {
		return;
	}

	// Update meta fields if set
	if ( isset( $_POST['teacher_name'] ) ) {
		update_post_meta( $post_id, 'teacher_name', sanitize_text_field( $_POST['teacher_name'] ) );
	}

	if ( isset( $_POST['school_name'] ) ) {
		$school_name = sanitize_text_field( $_POST['school_name'] );
		update_post_meta( $post_id, 'school_name', $school_name );

		// Generate school abbreviation
		$words        = explode( ' ', $school_name );
		$abbreviation = '';
		foreach ( $words as $word ) {
			$abbreviation .= strtoupper( $word[0] ); // Get the first letter of each word
		}
		update_post_meta( $post_id, 'school_abbreviation', $abbreviation ); // Save abbreviation
	}

	if ( isset( $_POST['email'] ) ) {
		update_post_meta( $post_id, 'email', sanitize_text_field( $_POST['email'] ) );
	}
	if ( isset( $_POST['issue_date'] ) ) {
		$_raw_issue    = sanitize_text_field( $_POST['issue_date'] );
		$_stored_issue = class_exists( '\CertificateGenerator\Helpers\DateHelper' )
			? \CertificateGenerator\Helpers\DateHelper::to_storage( $_raw_issue )
			: ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_raw_issue ) ? $_raw_issue : null );
		if ( $_stored_issue !== null ) {
			update_post_meta( $post_id, 'issue_date', $_stored_issue );
		}
	}

	if ( isset( $_POST['certificate_type'] ) ) {
		update_post_meta( $post_id, 'certificate_type', sanitize_text_field( $_POST['certificate_type'] ) );
	}

	// Auto-generate the title for the teacher
	$teacher_name = get_post_meta( $post_id, 'teacher_name', true );
	$school_name  = get_post_meta( $post_id, 'school_name', true );

	if ( ! empty( $teacher_name ) || ! empty( $school_name ) ) {
		$new_title = ( $teacher_name ?: 'Teacher' ) . ' - ' . ( $school_name ?: 'Unknown School' );
		$new_slug  = sanitize_title( $new_title );

		// Prevent infinite loop by removing and re-adding the save action
		remove_action( 'save_post', 'save_teachers_data' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $new_title,
				'post_name'  => $new_slug,
			)
		);
		add_action( 'save_post', 'save_teachers_data' );
	}
}
add_action( 'save_post', 'save_teachers_data', 10, 3 );


// Define Fields for Schools
function render_schools_form( $post ) {
	$school_name         = get_post_meta( $post->ID, 'school_name', true );
	$school_abbreviation = get_post_meta( $post->ID, 'school_abbreviation', true );
	$place               = get_post_meta( $post->ID, 'place', true );
	$issue_date          = get_post_meta( $post->ID, 'issue_date', true );
	$certificate_type    = get_post_meta( $post->ID, 'certificate_type', true );
	?>
	<div class="custom-form-wrap">
		<?php wp_nonce_field( 'cg_save_school', 'cg_school_nonce' ); ?>
		<div class="custom-form-group">
			<label for="school_name">School Name:</label>
			<input type="text" id="school_name" name="school_name" value="<?php echo esc_attr( $school_name ); ?>"
				class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="school_abbreviation">School Abbreviation:</label>
			<input type="text" id="school_abbreviation" name="school_abbreviation"
				value="<?php echo esc_attr( $school_abbreviation ); ?>" class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="place">Place:</label>
			<input type="text" id="place" name="place" value="<?php echo esc_attr( $place ); ?>" class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="issue_date">Issue Date:</label>
			<input type="date" id="issue_date" name="issue_date" value="<?php echo esc_attr( class_exists( '\CertificateGenerator\Helpers\DateHelper' ) ? \CertificateGenerator\Helpers\DateHelper::to_html5( $issue_date ) : $issue_date ); ?>"
				class="custom-form-input">
		</div>
		<div class="custom-form-group">
			<label for="certificate_type">Certificate Type:</label>
			<input type="text" id="certificate_type" name="certificate_type"
				value="<?php echo esc_attr( $certificate_type ); ?>" class="custom-form-input">
		</div>
	</div>
	<?php
}

// Add Meta Box for Schools Post Type
function add_schools_meta_box() {
	add_meta_box(
		'schools_meta_box',
		'School Details',
		'render_schools_form',
		'schools',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'add_schools_meta_box' );

// Save Schools Data
function save_schools_data( $post_id ) {
	// Verify it's not an autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['cg_school_nonce'] ) || ! wp_verify_nonce( $_POST['cg_school_nonce'], 'cg_save_school' ) ) {
		return;
	}
	// Ensure this logic applies only to the 'schools' post type
	if ( get_post_type( $post_id ) !== 'schools' ) {
		return;
	}

	// Update meta fields if set
	if ( isset( $_POST['school_name'] ) ) {
		$school_name = sanitize_text_field( $_POST['school_name'] );
		update_post_meta( $post_id, 'school_name', $school_name );

		// Generate school abbreviation
		$words        = explode( ' ', $school_name );
		$abbreviation = '';
		foreach ( $words as $word ) {
			$abbreviation .= strtoupper( $word[0] ); // Get the first letter of each word
		}
		update_post_meta( $post_id, 'school_abbreviation', $abbreviation ); // Save abbreviation
	}
	if ( isset( $_POST['place'] ) ) {
		update_post_meta( $post_id, 'place', sanitize_text_field( $_POST['place'] ) );
	}
	if ( isset( $_POST['issue_date'] ) ) {
		$_raw_issue    = sanitize_text_field( $_POST['issue_date'] );
		$_stored_issue = class_exists( '\CertificateGenerator\Helpers\DateHelper' )
			? \CertificateGenerator\Helpers\DateHelper::to_storage( $_raw_issue )
			: ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_raw_issue ) ? $_raw_issue : null );
		if ( $_stored_issue !== null ) {
			update_post_meta( $post_id, 'issue_date', $_stored_issue );
		}
	}
	if ( isset( $_POST['certificate_type'] ) ) {
		update_post_meta( $post_id, 'certificate_type', sanitize_text_field( $_POST['certificate_type'] ) );
	}

	// Auto-generate the title based on school-specific fields
	$school_name = get_post_meta( $post_id, 'school_name', true );
	$place       = get_post_meta( $post_id, 'place', true );

	if ( ! empty( $school_name ) || ! empty( $place ) ) {
		$new_title = ( $school_name ?: 'School' ) . ' - ' . ( $place ?: 'Unknown Place' );
		$new_slug  = sanitize_title( $new_title );

		// Prevent infinite loop
		remove_action( 'save_post', 'save_schools_data' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $new_title,
				'post_name'  => $new_slug,
			)
		);
		add_action( 'save_post', 'save_schools_data' );
	}
}
add_action( 'save_post', 'save_schools_data', 10, 3 );

// Include FontManager class
require_once plugin_dir_path( __FILE__ ) . 'font-manager.php';

// Define Fields for Certificates
function render_certificates_form( $post ) {
	$certificate_type     = get_post_meta( $post->ID, 'certificate_type', true );
	$template_url         = get_post_meta( $post->ID, 'template_url', true );
	$template_orientation = get_post_meta( $post->ID, 'template_orientation', true );
	$font_size            = get_post_meta( $post->ID, 'font_size', true ) ?: '12'; // Default font size
	$font_color           = get_post_meta( $post->ID, 'font_color', true ) ?: '#000000'; // Default black color

	// Get FontManager instance — Business plan gets the full 200-font library
	$font_manager   = CertificateGenerator_FontManager::getInstance();
	$cg_is_business = class_exists( 'CG_License_Manager' ) && CG_License_Manager::is_business();
	$font_options   = $font_manager->get_font_options( $cg_is_business );
	$font_style     = get_post_meta( $post->ID, 'font_style', true ) ?: $font_manager->get_default_font();
	// Set paper sizes based on orientation
	$paper_size = ( $template_orientation == 'landscape' ) ? 'Landscape (297mm x 210mm)' : 'Portrait (210mm x 297mm)';
	$width      = get_post_meta( $post->ID, 'width', true ) ?: '210'; // Default width
	?>
	<div class="custom-form-wrap">
		<?php wp_nonce_field( 'cg_save_certificate', 'cg_certificate_nonce' ); ?>
		<h2>Certificate Configuration</h2>
		<p><strong>Paper Size:</strong> <?php echo esc_html( $paper_size ); ?></p>

		<div class="custom-form-group">
			<label for="certificate_type"><strong>Certificate Type:</strong></label>
			<input type="text" id="certificate_type" name="certificate_type"
				value="<?php echo esc_attr( $certificate_type ); ?>" class="custom-form-input">
		</div>

		<div class="custom-form-group">
			<label for="event_date"><strong>Event Date:</strong></label>
			<?php
			$raw_date   = get_post_meta( $post->ID, 'event_date', true );
			$date_value = '';
			if ( ! empty( $raw_date ) ) {
				$date_value = \CertificateGenerator\Helpers\DateHelper::to_display( $raw_date );
			}
			?>
			<input type="text" id="event_date" name="event_date"
				value="<?php echo esc_attr( $date_value ); ?>" class="custom-form-input"
				placeholder="DD-MM-YYYY (e.g., 23-03-2026)">
			<p class="description">Set this when multiple templates share the same certificate type (e.g., Dec 2025 vs Mar
				2026). Leave blank if this is the only template for this type. Format: <strong>d-m-Y</strong></p>
		</div>

		<div class="custom-form-group">
			<label for="template_url"><strong>Template Image:</strong></label>
			<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
				<input type="text" id="template_url" name="template_url" value="<?php echo esc_attr( $template_url ); ?>"
					class="custom-form-input" style="flex:1;min-width:200px;"
					placeholder="Paste URL or choose from library →">
				<button type="button" id="cg_upload_template_btn" class="button button-secondary">
					📁 Choose from Media Library
				</button>
			</div>
			<div id="cg_template_preview" style="margin-top:10px;<?php echo $template_url ? '' : 'display:none;'; ?>">
				<?php if ( $template_url ) : ?>
					<img src="<?php echo esc_url( $template_url ); ?>"
						style="max-width:300px;max-height:180px;border:1px solid #ddd;border-radius:4px;display:block;">
				<?php endif; ?>
			</div>
		</div>

		<div class="custom-form-group">
			<label for="template_orientation"><strong>Template Orientation:</strong></label>
			<select id="template_orientation" name="template_orientation" class="custom-form-input">
				<option value="landscape" <?php selected( $template_orientation, 'landscape' ); ?>>Landscape (297x210 mm)
				</option>
				<option value="portrait" <?php selected( $template_orientation, 'portrait' ); ?>>Portrait (210x297 mm)
				</option>
			</select>
		</div>

		<h3>Font Settings</h3>
		<div class="custom-form-group">
			<label for="font_size"><strong>Font Size:</strong></label>
			<input type="number" id="font_size" name="font_size" value="<?php echo esc_attr( $font_size ); ?>"
				class="custom-form-input" min="6" max="48">
		</div>

		<div class="custom-form-group">
			<label for="font_color"><strong>Font Color:</strong></label>
			<input type="color" id="font_color" name="font_color" value="<?php echo esc_attr( $font_color ); ?>"
				class="custom-form-input">
		</div>

		<div class="custom-form-group">
			<label for="font_style"><strong>Font Style:</strong></label>
			<div class="font-search-wrapper">
				<input type="text" id="font_search" placeholder="Search fonts..."
					class="custom-form-input font-search-input" style="margin-bottom: 8px; width: 100%;">
				<select id="font_style" name="font_style" class="custom-form-input font-select">
					<?php foreach ( $font_options as $font_key => $font_name ) : ?>
						<option value="<?php echo esc_attr( $font_key ); ?>" <?php selected( $font_style, $font_key ); ?>>
							<?php echo esc_html( $font_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<div class="font-preview"
					style="margin-top: 8px; padding: 8px; background: #f9f9f9; border: 1px solid #ddd; font-size: 16px; min-height: 30px;">
					Preview text will appear here
				</div>
				<?php
				if ( ! $cg_is_business ) :
					$upgrade_url = admin_url( 'options-general.php?page=certificate_generator_settings&tab=license' );
					?>
					<p
						style="margin:8px 0 0;padding:8px 10px;background:#f0f6fc;border-left:3px solid #2271b1;border-radius:0 3px 3px 0;font-size:12px;color:#1d2327;">
						Showing <strong><?php echo count( $font_options ); ?> essential fonts</strong>.
						<a href="<?php echo esc_url( $upgrade_url ); ?>">Upgrade to Business</a> for the full 200+ font library.
					</p>
				<?php endif; ?>
			</div>
		</div>

		<style>
			.font-search-wrapper {
				position: relative;
			}

			.font-search-input {
				border: 1px solid #ddd;
				border-radius: 4px;
				padding: 8px;
			}

			.font-select {
				max-height: 200px;
				overflow-y: auto;
			}

			.font-preview {
				border-radius: 4px;
				font-weight: normal;
				color: #333;
				transition: all 0.3s ease;
			}

			.font-search-no-results {
				padding: 8px;
				color: #666;
				font-style: italic;
				text-align: center;
			}

			.font-category-group {
				background: #f0f0f0;
				font-weight: bold;
				padding: 4px 8px;
				color: #666;
				font-size: 11px;
				text-transform: uppercase;
			}
		</style>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const searchInput = document.getElementById('font_search');
				const fontSelect = document.getElementById('font_style');
				const fontPreview = document.querySelector('.font-preview');

				if (!searchInput || !fontSelect || !fontPreview) return;

				// Store original options
				const originalOptions = Array.from(fontSelect.options).map(option => ({
					value: option.value,
					text: option.textContent,
					selected: option.selected
				}));

				// Font categories for better organization
				const fontCategories = {
					'sans': ['opensans', 'poppins', 'lato', 'helvetica', 'arial', 'roboto', 'montserrat'],
					'serif': ['times', 'garamond', 'georgia', 'palatino', 'merriweather'],
					'script': ['greatvibes', 'pacifico', 'lobster', 'dancingscript', 'satisfy'],
					'display': ['bebasneue', 'oswald', 'anton', 'impact']
				};

				function getCategoryForFont(fontKey) {
					for (const [category, fonts] of Object.entries(fontCategories)) {
						if (fonts.some(font => fontKey.toLowerCase().includes(font))) {
							return category;
						}
					}
					return 'other';
				}

				function updateFontPreview() {
					const selectedFont = fontSelect.value;
					const selectedText = fontSelect.options[fontSelect.selectedIndex]?.text || 'Font Preview';

					// Update preview text
					fontPreview.innerHTML = `
					<div style="font-size: 16px; margin-bottom: 4px;">
						<strong>${selectedText}</strong>
					</div>
					<div style="font-size: 14px; color: #666;">
						The quick brown fox jumps over the lazy dog
					</div>
				`;

					// Add category badge
					const category = getCategoryForFont(selectedFont);
					const categoryColors = {
						'sans': '#2196F3',
						'serif': '#8B4513',
						'script': '#E91E63',
						'display': '#FF9800',
						'other': '#9E9E9E'
					};

					const categoryBadge = `
					<span style="display: inline-block; background: ${categoryColors[category]};
								color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px;
								text-transform: uppercase; margin-left: 8px;">
						${category}
					</span>
				`;

					fontPreview.querySelector('div').innerHTML += categoryBadge;
				}

				function filterFonts() {
					const searchTerm = searchInput.value.toLowerCase();

					// Clear current options
					fontSelect.innerHTML = '';

					if (searchTerm === '') {
						// Show all fonts organized by category
						const categorizedFonts = {};

						originalOptions.forEach(option => {
							const category = getCategoryForFont(option.value);
							if (!categorizedFonts[category]) {
								categorizedFonts[category] = [];
							}
							categorizedFonts[category].push(option);
						});

						// Add fonts by category
						const categoryOrder = ['sans', 'serif', 'script', 'display', 'other'];
						const categoryNames = {
							'sans': 'Sans-Serif Fonts',
							'serif': 'Serif Fonts',
							'script': 'Script Fonts',
							'display': 'Display Fonts',
							'other': 'Other Fonts'
						};

						categoryOrder.forEach(category => {
							if (categorizedFonts[category] && categorizedFonts[category].length > 0) {
								// Add category header
								const categoryGroup = document.createElement('optgroup');
								categoryGroup.label = categoryNames[category] || category;
								fontSelect.appendChild(categoryGroup);

								categorizedFonts[category].forEach(option => {
									const newOption = document.createElement('option');
									newOption.value = option.value;
									newOption.textContent = option.text;
									newOption.selected = option.selected;
									categoryGroup.appendChild(newOption);
								});
							}
						});
					} else {
						// Filter fonts based on search
						const filteredOptions = originalOptions.filter(option =>
							option.text.toLowerCase().includes(searchTerm) ||
							option.value.toLowerCase().includes(searchTerm)
						);

						if (filteredOptions.length === 0) {
							const noResults = document.createElement('option');
							noResults.textContent = 'No fonts found';
							noResults.disabled = true;
							fontSelect.appendChild(noResults);
						} else {
							filteredOptions.forEach(option => {
								const newOption = document.createElement('option');
								newOption.value = option.value;
								newOption.textContent = option.text;
								newOption.selected = option.selected;
								fontSelect.appendChild(newOption);
							});
						}
					}

					updateFontPreview();
				}

				// Event listeners
				searchInput.addEventListener('input', filterFonts);
				fontSelect.addEventListener('change', updateFontPreview);

				// Initialize
				filterFonts();
				updateFontPreview();

				// Add keyboard navigation
				searchInput.addEventListener('keydown', function (e) {
					if (e.key === 'ArrowDown') {
						e.preventDefault();
						fontSelect.focus();
						if (fontSelect.options.length > 0) {
							fontSelect.selectedIndex = 0;
							updateFontPreview();
						}
					} else if (e.key === 'Enter') {
						e.preventDefault();
						if (fontSelect.options.length > 0) {
							fontSelect.selectedIndex = 0;
							updateFontPreview();
						}
					}
				});
			});
		</script>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var uploadBtn = document.getElementById('cg_upload_template_btn');
				if (!uploadBtn) return;
				uploadBtn.addEventListener('click', function (e) {
					e.preventDefault();
					var frame = wp.media({
						title: 'Select Certificate Template Image',
						button: { text: 'Use this image' },
						multiple: false,
						library: { type: 'image' }
					});
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						var urlInput = document.getElementById('template_url');
						var preview = document.getElementById('cg_template_preview');
						urlInput.value = attachment.url;
						// Build preview using safe DOM methods (no innerHTML)
						while (preview.firstChild) { preview.removeChild(preview.firstChild); }
						var img = document.createElement('img');
						img.src = attachment.url;
						img.style.cssText = 'max-width:300px;max-height:180px;border:1px solid #ddd;border-radius:4px;display:block;';
						preview.appendChild(img);
						preview.style.display = 'block';
						// Notify the visual canvas that the template URL changed
						urlInput.dispatchEvent(new Event('change'));
					});
					frame.open();
				});
			});
		</script>

		<h3>Field Positioning (Based on Selected Orientation)</h3>
		<p><strong>Note:</strong> Adjust X & Y positions based on the paper size above.</p>

		<?php
		// Determine field count for this template
		$field_count = (int) get_post_meta( $post->ID, 'template_field_count', true ) ?: 3;
		$field_count = max( 2, min( CG_Field_Schema::MAX_FIELDS, $field_count ) );

		// Get all renderable fields (standard + extra) for this certificate type
		$all_renderable = CG_Field_Schema::get_all_renderable_fields( $certificate_type );

		// Build per-slot label array for the visual canvas JS
		$cg_canvas_labels = array();
		for ( $j = 1; $j <= CG_Field_Schema::MAX_FIELDS; $j++ ) {
			$cg_canvas_labels[] = isset( $all_renderable[ $j - 1 ] )
				? CG_Field_Schema::get_display_label( $all_renderable[ $j - 1 ] )
				: "Field {$j}";
		}
		?>

		<!-- ── Visual drag-and-drop canvas ──────────────────────────────── -->
		<style>
		#cg-canvas-outer-scroll {
			overflow: auto;
			background: #f0f0f1;
			border: 2px solid #0073aa;
			border-radius: 4px;
			padding: 10px;
			display: block;
			max-width: 100%;
			box-sizing: border-box;
			max-height: 620px;
		}
		#cg-canvas-container {
			position: relative;
			display: inline-block;
			box-shadow: 0 4px 16px rgba(0,0,0,.2);
			cursor: crosshair;
			flex-shrink: 0;
		}
		#cg-canvas-img {
			display: block;
			/* width & height forced by JS to match A4 at chosen scale */
		}
		.cg-field-handle {
			position: absolute;
			box-sizing: border-box;
			border-radius: 3px;
			color: #fff;
			padding: 3px 8px;
			font-size: 11px;
			font-weight: 700;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			line-height: 1.4;
			white-space: nowrap;
			box-shadow: 0 2px 5px rgba(0,0,0,.4);
			z-index: 10;
			cursor: grab;
			user-select: none;
		}
		.cg-field-handle:active { cursor: grabbing; }
		.cg-field-handle .cg-width-bar {
			display: block;
			height: 2px;
			background: rgba(255,255,255,.55);
			border-radius: 1px;
			margin-top: 2px;
			min-width: 4px;
		}
		</style>

		<div id="cg-visual-editor-wrap" style="margin-bottom:20px;<?php echo $template_url ? '' : 'display:none;'; ?>">
			<h4 style="margin:0 0 4px 0;">&#127919; Visual Field Placement</h4>
			<p class="description" style="margin:0 0 10px 0;">
				Drag the coloured labels directly on the template to position each field.
				X &amp; Y inputs below update in real time.
			</p>
			<div id="cg-canvas-outer-scroll">
				<div id="cg-canvas-container">
					<img id="cg-canvas-img"
						src="<?php echo esc_url( $template_url ); ?>"
						alt="Certificate template preview">
				</div>
			</div>
			<p class="description" style="margin-top:6px;font-style:italic;">
				Canvas is scaled to fit — coordinates are always stored as real mm values.
				Faded handles = hidden field.
			</p>
		</div>
		<!-- ── /canvas ──────────────────────────────────────────────────── -->
		<div class="custom-form-group">
			<label><strong>Number of Fields on Certificate:</strong></label>
			<div style="display:flex;align-items:center;gap:10px;">
				<button type="button" id="cg_field_count_dec" class="button" style="font-size:16px;padding:0 10px;line-height:28px;">−</button>
				<input type="number" id="template_field_count" name="template_field_count"
					value="<?php echo esc_attr( $field_count ); ?>"
					min="2" max="<?php echo esc_attr( CG_Field_Schema::MAX_FIELDS ); ?>"
					class="custom-form-input" style="width:60px;text-align:center;">
				<button type="button" id="cg_field_count_inc" class="button" style="font-size:16px;padding:0 10px;line-height:28px;">+</button>
				<span class="description">Min 2 · Max <?php echo esc_html( CG_Field_Schema::MAX_FIELDS ); ?></span>
			</div>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			var countInput = document.getElementById('template_field_count');
			var decBtn     = document.getElementById('cg_field_count_dec');
			var incBtn     = document.getElementById('cg_field_count_inc');
			var max        = parseInt(countInput.max, 10);
			var min        = parseInt(countInput.min, 10);
			function updateRows() {
				var count = parseInt(countInput.value, 10);
				document.querySelectorAll('.cg-field-row').forEach(function (row) {
					var idx = parseInt(row.dataset.fieldIndex, 10);
					row.style.display = (idx <= count) ? '' : 'none';
				});
			}
			decBtn.addEventListener('click', function () {
				var v = Math.max(min, parseInt(countInput.value, 10) - 1);
				countInput.value = v;
				updateRows();
			});
			incBtn.addEventListener('click', function () {
				var v = Math.min(max, parseInt(countInput.value, 10) + 1);
				countInput.value = v;
				updateRows();
			});
			countInput.addEventListener('change', updateRows);
			updateRows();
		});
		</script>

		<?php
		for ( $i = 1; $i <= CG_Field_Schema::MAX_FIELDS; $i++ ) {
			$position_x  = get_post_meta( $post->ID, "field_{$i}_position_x", true );
			$position_y  = get_post_meta( $post->ID, "field_{$i}_position_y", true );
			$visibility  = get_post_meta( $post->ID, "field_{$i}_visible", true ) ?: '0';
			$field_width = get_post_meta( $post->ID, "field_{$i}_width", true ) ?: '100'; // Default width
			$alignment   = get_post_meta( $post->ID, "field_{$i}_alignment", true ) ?: 'C'; // Default center alignment

			// Determine field label: use schema name if available, else generic
			if ( isset( $all_renderable[ $i - 1 ] ) ) {
				$field_label = CG_Field_Schema::get_display_label( $all_renderable[ $i - 1 ] );
			} else {
				$field_label = "Field {$i}";
			}
			$row_display = ( $i <= $field_count ) ? '' : 'display:none;';
			?>
			<div class="custom-form-wrap cg-field-row" data-field-index="<?php echo $i; ?>" style="<?php echo esc_attr( $row_display ); ?>">
				<h4><?php echo esc_html( $field_label ); ?> <span style="color:#888;font-weight:normal;font-size:12px;">(Slot <?php echo $i; ?>)</span></h4>
				<div class="custom-form-group">
					<label for="field_<?php echo $i; ?>_position_x"><strong>Position X:</strong> (0 to
						<?php echo ( $template_orientation == 'landscape' ) ? '297' : '210'; ?> mm)</label>
					<input type="number" id="field_<?php echo $i; ?>_position_x" name="field_<?php echo $i; ?>_position_x"
						value="<?php echo esc_attr( $position_x ); ?>" class="custom-form-input" min="0"
						max="<?php echo ( $template_orientation == 'landscape' ) ? '297' : '210'; ?>">
				</div>
				<div class="custom-form-group">
					<label for="field_<?php echo $i; ?>_position_y"><strong>Position Y:</strong> (0 to
						<?php echo ( $template_orientation == 'landscape' ) ? '210' : '297'; ?> mm)</label>
					<input type="number" id="field_<?php echo $i; ?>_position_y" name="field_<?php echo $i; ?>_position_y"
						value="<?php echo esc_attr( $position_y ); ?>" class="custom-form-input" min="0"
						max="<?php echo ( $template_orientation == 'landscape' ) ? '210' : '297'; ?>">
				</div>
				<div class="custom-form-group">
					<label for="field_<?php echo $i; ?>_visible"><strong>Visible:</strong></label>
					<input type="checkbox" id="field_<?php echo $i; ?>_visible" name="field_<?php echo $i; ?>_visible" value="1"
						<?php checked( $visibility, '1' ); ?>> Show this field
				</div>
				<div class="custom-form-group">
					<label for="field_<?php echo $i; ?>_width"><strong>Width:</strong></label>
					<input type="number" id="field_<?php echo $i; ?>_width" name="field_<?php echo $i; ?>_width"
						value="<?php echo esc_attr( $field_width ); ?>" class="custom-form-input" min="0" max="297">
				</div>
				<div class="custom-form-group">
					<label for="field_<?php echo $i; ?>_alignment"><strong>Alignment:</strong></label>
					<select id="field_<?php echo $i; ?>_alignment" name="field_<?php echo $i; ?>_alignment"
						class="custom-form-input">
						<option value="L" <?php selected( $alignment, 'L' ); ?>>Left</option>
						<option value="C" <?php selected( $alignment, 'C' ); ?>>Center</option>
						<option value="R" <?php selected( $alignment, 'R' ); ?>>Right</option>
					</select>
				</div>
			</div>
			<?php
		}
		?>

		<!-- ── Visual canvas JavaScript ─────────────────────────────────── -->
		<script>
		(function ($) {
			var MAX_FIELDS   = <?php echo (int) CG_Field_Schema::MAX_FIELDS; ?>;
			var FIELD_LABELS = <?php echo wp_json_encode( array_values( $cg_canvas_labels ) ); ?>;
			var COLORS = [
				'#c0392b','#2980b9','#27ae60','#8e44ad','#f39c12',
				'#16a085','#e74c3c','#1abc9c','#d35400','#7f8c8d',
				'#2c3e50','#e67e22','#3498db','#9b59b6','#0097a7'
			];

			// Canvas renders at a fixed height; width computed from orientation.
			var CANVAS_H = 520; // px

			var $canvas = $('#cg-canvas-container');
			var $img    = $('#cg-canvas-img');
			var $wrap   = $('#cg-visual-editor-wrap');

			if (!$canvas.length) return;

			// ── Helpers ───────────────────────────────────────────────────

			function orientation() {
				return $('#template_orientation').val() || 'portrait';
			}

			function pageDims() {
				return orientation() === 'landscape'
					? { w: 297, h: 210 }
					: { w: 210, h: 297 };
			}

			function canvasDims() {
				var d = pageDims();
				return { w: Math.round((d.w / d.h) * CANVAS_H), h: CANVAS_H };
			}

			function mmToPx(val, axis) {
				var d = pageDims(), cd = canvasDims();
				return axis === 'x' ? (val / d.w) * cd.w : (val / d.h) * cd.h;
			}

			function pxToMm(val, axis) {
				var d = pageDims(), cd = canvasDims();
				return axis === 'x'
					? Math.round((val / cd.w) * d.w)
					: Math.round((val / cd.h) * d.h);
			}

			function updateCanvasSize() {
				var cd = canvasDims();
				$canvas.css({ width: cd.w + 'px', height: cd.h + 'px' });
				$img.css({ width: cd.w + 'px', height: cd.h + 'px' });
			}

			// ── Handle positioning ────────────────────────────────────────

			function positionHandle(n) {
				var $h = $('#cg-handle-' + n);
				if (!$h.length) return;

				var x_mm = parseFloat($('#field_' + n + '_position_x').val()) || 0;
				var y_mm = parseFloat($('#field_' + n + '_position_y').val()) || 0;
				var w_mm = parseFloat($('#field_' + n + '_width').val())      || 100;

				var cx = mmToPx(x_mm, 'x');
				var cy = mmToPx(y_mm, 'y');
				var wPx = Math.max(4, mmToPx(w_mm, 'x'));

				// Center handle on (cx, cy) — measure after it's in the DOM
				var hw = $h.outerWidth()  / 2 || 0;
				var hh = $h.outerHeight() / 2 || 0;
				$h.css({ left: (cx - hw) + 'px', top: (cy - hh) + 'px' });
				$h.find('.cg-width-bar').css('width', wPx + 'px');

				var visible = $('#field_' + n + '_visible').prop('checked');
				$h.css({ opacity: visible ? '1' : '0.3',
						'pointer-events': visible ? '' : 'none' });
			}

			function repositionAll() {
				$canvas.find('.cg-field-handle').each(function () {
					positionHandle(parseInt($(this).data('field'), 10));
				});
				positionQRHandle();
				positionSerialHandle();
			}

			// ── QR Code handle ────────────────────────────────────────────

			function positionQRHandle() {
				var $h = $('#cg-handle-qr');
				if (!$h.length) return;

				var enabled = $('#qr_enabled').is(':checked');
				if (!enabled) { $h.hide(); return; }
				$h.show();

				var x_mm = parseFloat($('#qr_position_x').val()) || 250;
				var y_mm = parseFloat($('#qr_position_y').val()) || 180;
				var size_mm = parseFloat($('#qr_size').val()) || 15;

				var cx = mmToPx(x_mm, 'x');
				var cy = mmToPx(y_mm, 'y');
				var sPx = mmToPx(size_mm, 'x');

				var hw = $h.outerWidth() / 2 || 0;
				var hh = $h.outerHeight() / 2 || 0;
				$h.css({ left: (cx - hw) + 'px', top: (cy - hh) + 'px', width: Math.max(sPx, 20) + 'px', height: Math.max(sPx, 20) + 'px' });
			}

			// ── Serial Number handle ──────────────────────────────────────

			function positionSerialHandle() {
				var $h = $('#cg-handle-serial');
				if (!$h.length) return;

				var enabled = $('#serial_number_display').is(':checked');
				if (!enabled) { $h.hide(); return; }
				$h.show();

				var x_mm = parseFloat($('#serial_number_position_x').val()) || 105;
				var y_mm = parseFloat($('#serial_number_position_y').val()) || 200;

				var cx = mmToPx(x_mm, 'x');
				var cy = mmToPx(y_mm, 'y');

				var hw = $h.outerWidth() / 2 || 0;
				var hh = $h.outerHeight() / 2 || 0;
				$h.css({ left: (cx - hw) + 'px', top: (cy - hh) + 'px' });
			}

			// ── Build all draggable handles ───────────────────────────────

			function buildHandles() {
				$canvas.find('.cg-field-handle').remove();

				var count = Math.min(
					parseInt($('#template_field_count').val(), 10) || 3,
					MAX_FIELDS
				);

				for (var n = 1; n <= count; n++) {
					var label = FIELD_LABELS[n - 1] || ('Field ' + n);
					var color = COLORS[(n - 1) % COLORS.length];

					var $h = $('<div>')
						.attr({ id: 'cg-handle-' + n, 'data-field': n })
						.addClass('cg-field-handle')
						.css('background', color)
						.append($('<span>').addClass('cg-handle-label').text(label))
						.append($('<span>').addClass('cg-width-bar'));

					$canvas.append($h);
					positionHandle(n); // sets left/top after element is in DOM

					// Attach draggable
					(function (fieldN, $handle) {
						$handle.draggable({
							containment: '#cg-canvas-container',
							cursor: 'grabbing',
							drag: function (e, ui) {
								var hw    = $handle.outerWidth()  / 2;
								var hh    = $handle.outerHeight() / 2;
								var cd    = canvasDims();
								var d     = pageDims();
								// Compute handle centre, clamped to canvas bounds
								var cx_px = Math.max(0, Math.min(cd.w, ui.position.left + hw));
								var cy_px = Math.max(0, Math.min(cd.h, ui.position.top  + hh));
								// Clamp mm values to valid page range
								var x_mm = Math.max(0, Math.min(d.w, pxToMm(cx_px, 'x')));
								var y_mm = Math.max(0, Math.min(d.h, pxToMm(cy_px, 'y')));
								$('#field_' + fieldN + '_position_x').val(x_mm);
								$('#field_' + fieldN + '_position_y').val(y_mm);
							}
						});
					})(n, $h);
				}

				// ── QR Code handle ────────────────────────────────────
				$canvas.find('#cg-handle-qr').remove();

				var qrEnabled = $('#qr_enabled').is(':checked');
				if (qrEnabled) {
					var qrX = parseFloat($('#qr_position_x').val()) || 250;
					var qrY = parseFloat($('#qr_position_y').val()) || 180;
					var qrSize = parseFloat($('#qr_size').val()) || 15;

					var $qr = $('<div>')
						.attr('id', 'cg-handle-qr')
						.addClass('cg-field-handle')
						.css({ background: '#8e44ad', width: Math.max(mmToPx(qrSize, 'x'), 20) + 'px', height: Math.max(mmToPx(qrSize, 'x'), 20) + 'px' })
						.append($('<span>').addClass('cg-handle-label').text('QR Code'));

					$canvas.append($qr);
					positionQRHandle();

					$qr.draggable({
						containment: '#cg-canvas-container',
						cursor: 'grabbing',
						drag: function (e, ui) {
							var hw = $qr.outerWidth() / 2;
							var hh = $qr.outerHeight() / 2;
							var cd = canvasDims();
							var d = pageDims();
							var cx_px = Math.max(0, Math.min(cd.w, ui.position.left + hw));
							var cy_px = Math.max(0, Math.min(cd.h, ui.position.top + hh));
							var x_mm = Math.max(0, Math.min(d.w, pxToMm(cx_px, 'x')));
							var y_mm = Math.max(0, Math.min(d.h, pxToMm(cy_px, 'y')));
							$('#qr_position_x').val(x_mm);
							$('#qr_position_y').val(y_mm);
						}
					});
				}

				// ── Serial Number handle ──────────────────────────────
				$canvas.find('#cg-handle-serial').remove();

				var serialEnabled = $('#serial_number_display').is(':checked');
				if (serialEnabled) {
					var snX = parseFloat($('#serial_number_position_x').val()) || 105;
					var snY = parseFloat($('#serial_number_position_y').val()) || 200;

					var $sn = $('<div>')
						.attr('id', 'cg-handle-serial')
						.addClass('cg-field-handle')
						.css({ background: '#16a085' })
						.append($('<span>').addClass('cg-handle-label').text('Serial: CERT-00000001'));

					$canvas.append($sn);
					positionSerialHandle();

					$sn.draggable({
						containment: '#cg-canvas-container',
						cursor: 'grabbing',
						drag: function (e, ui) {
							var hw = $sn.outerWidth() / 2;
							var hh = $sn.outerHeight() / 2;
							var cd = canvasDims();
							var d = pageDims();
							var cx_px = Math.max(0, Math.min(cd.w, ui.position.left + hw));
							var cy_px = Math.max(0, Math.min(cd.h, ui.position.top + hh));
							var x_mm = Math.max(0, Math.min(d.w, pxToMm(cx_px, 'x')));
							var y_mm = Math.max(0, Math.min(d.h, pxToMm(cy_px, 'y')));
							$('#serial_number_position_x').val(x_mm);
							$('#serial_number_position_y').val(y_mm);
						}
					});
				}
			}

			// ── Canvas init ───────────────────────────────────────────────

			function initCanvas() {
				var url = $('#template_url').val().trim();
				if (!url) { $wrap.hide(); return; }

				$wrap.show();
				updateCanvasSize();

				// Always remove stale handler first to avoid double-fires
				$img.off('load.cgcanvas');

				if ($img.attr('src') === url) {
					// Same image already loaded — just rebuild handles
					buildHandles();
					return;
				}

				// Set the new src; check complete AFTER to catch cached images
				// (some browsers fire 'load' synchronously on src assignment)
				$img.attr('src', url);

				if ($img[0].complete && $img[0].naturalWidth) {
					// Served from cache — already fully loaded
					buildHandles();
				} else {
					// Wait for the network load to finish
					$img.one('load.cgcanvas', function () { buildHandles(); });
				}
			}

			// ── Event bindings ────────────────────────────────────────────

			$('#template_url').on('change', initCanvas);

			$('#template_orientation').on('change', function () {
				updateCanvasSize();
				repositionAll();
			});

			$('#template_field_count').on('change', function () {
				setTimeout(buildHandles, 20);
			});
			$('#cg_field_count_dec, #cg_field_count_inc').on('click', function () {
				setTimeout(buildHandles, 60);
			});

			for (var n = 1; n <= MAX_FIELDS; n++) {
				(function (fieldN) {
					$('#field_' + fieldN + '_position_x,' +
						'#field_' + fieldN + '_position_y,' +
						'#field_' + fieldN + '_width')
						.on('input change', function () { positionHandle(fieldN); });
					$('#field_' + fieldN + '_visible')
						.on('change', function () { positionHandle(fieldN); });
				})(n);
			}

			// QR Code settings → update handle
			$('#qr_enabled, #qr_position_x, #qr_position_y, #qr_size').on('change input', function () {
				positionQRHandle();
				if ($('#qr_enabled').is(':checked')) {
					if (!$('#cg-handle-qr').length) buildHandles();
				}
			});

			// Serial Number settings → update handle
			$('#serial_number_display, #serial_number_position_x, #serial_number_position_y').on('change input', function () {
				positionSerialHandle();
				if ($('#serial_number_display').is(':checked')) {
					if (!$('#cg-handle-serial').length) buildHandles();
				}
			});

			// Initialize on page load
			$(function () { initCanvas(); });

		}(jQuery));
		</script>
		<!-- ── /canvas JS ────────────────────────────────────────────────── -->
	</div>
	<?php
}

// Add Meta Box for Certificates Post Type
function add_certificates_meta_box() {
	add_meta_box(
		'certificates_meta_box',
		'Certificate Details',
		'render_certificates_form',
		'certificates',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'add_certificates_meta_box' );

// Save Certificates Data
function save_certificates_data( $post_id ) {
	// Check if data is being saved correctly
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['cg_certificate_nonce'] ) || ! wp_verify_nonce( $_POST['cg_certificate_nonce'], 'cg_save_certificate' ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'certificates' ) {
		return;
	}

	if ( isset( $_POST['certificate_type'] ) ) {
		update_post_meta( $post_id, 'certificate_type', sanitize_text_field( $_POST['certificate_type'] ) );
	}

	if ( isset( $_POST['event_date'] ) ) {
		$raw = sanitize_text_field( $_POST['event_date'] );
		if ( $raw === '' ) {
			update_post_meta( $post_id, 'event_date', '' );
		} else {
			// Use DateHelper to parse and normalize any input format to Y-m-d
			$stored = class_exists( '\CertificateGenerator\Helpers\DateHelper' )
				? \CertificateGenerator\Helpers\DateHelper::to_storage( $raw )
				: null;
			if ( $stored !== null ) {
				update_post_meta( $post_id, 'event_date', $stored );
			} else {
				set_transient( 'cg_event_date_invalid_' . $post_id, 1, 30 );
			}
		}
	}
	// Invalidate duplicate-template warning cache when any certificate template is saved
	delete_transient( 'cg_duplicate_template_warning' );

	if ( isset( $_POST['template_url'] ) ) {
		update_post_meta( $post_id, 'template_url', esc_url_raw( $_POST['template_url'] ) );
	}
	if ( isset( $_POST['template_orientation'] ) ) {
		update_post_meta( $post_id, 'template_orientation', sanitize_text_field( $_POST['template_orientation'] ) );
	}

	if ( isset( $_POST['font_size'] ) ) {
		update_post_meta( $post_id, 'font_size', intval( $_POST['font_size'] ) );
	}

	if ( isset( $_POST['font_color'] ) ) {
		update_post_meta( $post_id, 'font_color', sanitize_hex_color( $_POST['font_color'] ) );
	}

	if ( isset( $_POST['font_style'] ) ) {
		update_post_meta( $post_id, 'font_style', sanitize_text_field( $_POST['font_style'] ) );
	}

	// Save number-of-fields stepper
	if ( isset( $_POST['template_field_count'] ) ) {
		$field_count = max( 2, min( CG_Field_Schema::MAX_FIELDS, intval( $_POST['template_field_count'] ) ) );
		update_post_meta( $post_id, 'template_field_count', $field_count );
	}

	for ( $i = 1; $i <= CG_Field_Schema::MAX_FIELDS; $i++ ) {
		$x_input = $_POST[ "field_{$i}_position_x" ] ?? '';
		$y_input = $_POST[ "field_{$i}_position_y" ] ?? '';
		$x_val   = $x_input !== '' ? sanitize_text_field( $x_input ) : strval( 105 );
		$y_val   = $y_input !== '' ? sanitize_text_field( $y_input ) : strval( 60 + ( $i * 25 ) );
		update_post_meta( $post_id, "field_{$i}_position_x", $x_val );
		update_post_meta( $post_id, "field_{$i}_position_y", $y_val );

		$visibility = isset( $_POST[ "field_{$i}_visible" ] ) ? '1' : '0';
		update_post_meta( $post_id, "field_{$i}_visible", $visibility );

		if ( isset( $_POST[ "field_{$i}_width" ] ) ) {
			update_post_meta( $post_id, "field_{$i}_width", intval( $_POST[ "field_{$i}_width" ] ) );
		}

		if ( isset( $_POST[ "field_{$i}_alignment" ] ) ) {
			update_post_meta( $post_id, "field_{$i}_alignment", sanitize_text_field( $_POST[ "field_{$i}_alignment" ] ) );
		}
	}

	// Auto-generate the title for the certificate
	$certificate_type = get_post_meta( $post_id, 'certificate_type', true );
	$template_url     = get_post_meta( $post_id, 'template_url', true );
	$width            = get_post_meta( $post_id, 'width', true );

	if ( $certificate_type || $template_url ) {
		$event_date  = get_post_meta( $post_id, 'event_date', true );
		$date_suffix = $event_date ? ' (' . $event_date . ')' : '';
		$host        = $template_url ? parse_url( $template_url, PHP_URL_HOST ) : 'No Template URL';
		$new_title   = ( $certificate_type ?: 'Certificate' ) . $date_suffix . ' - ' . ( $host ?: 'No Template URL' );
		$new_slug    = sanitize_title( $new_title );

		// Prevent infinite loop by removing and re-adding the save action
		remove_action( 'save_post', 'save_certificates_data' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $new_title,
				'post_name'  => $new_slug,
			)
		);
		add_action( 'save_post', 'save_certificates_data' );
	}
}
add_action( 'save_post', 'save_certificates_data' );

/**
 * Admin notice: invalid event_date format.
 */
function cg_certificates_event_date_invalid_notice() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'certificates' || $screen->base !== 'post' ) {
		return;
	}
	global $post;
	if ( ! $post ) {
		return;
	}
	if ( get_transient( 'cg_event_date_invalid_' . $post->ID ) ) {
		delete_transient( 'cg_event_date_invalid_' . $post->ID );
		echo '<div class="notice notice-error is-dismissible"><p><strong>Certificate Generator:</strong> Invalid Event Date format. Please use the date picker or enter a date in YYYY-MM-DD format.</p></div>';
	}
}
add_action( 'admin_notices', 'cg_certificates_event_date_invalid_notice' );

/**
 * Admin notice: multiple templates share the same certificate_type but none have event_date.
 * Only shown on the certificates list screen. Cached for 5 minutes.
 */
function cg_certificates_duplicate_type_warning() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->id !== 'edit-certificates' ) {
		return;
	}

	$cached = get_transient( 'cg_duplicate_template_warning' );
	if ( $cached === false ) {
		// Build map: certificate_type => [has_date, count]
		$all      = get_posts(
			array(
				'post_type'      => 'certificates',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$type_map = array();
		foreach ( $all as $id ) {
			$type = get_post_meta( $id, 'certificate_type', true );
			$date = get_post_meta( $id, 'event_date', true );
			if ( ! isset( $type_map[ $type ] ) ) {
				$type_map[ $type ] = array(
					'count'    => 0,
					'has_date' => false,
				);
			}
			++$type_map[ $type ]['count'];
			if ( $date ) {
				$type_map[ $type ]['has_date'] = true;
			}
		}
		$bad_types = array();
		foreach ( $type_map as $type => $info ) {
			if ( $info['count'] > 1 && ! $info['has_date'] ) {
				$bad_types[] = esc_html( $type );
			}
		}
		$cached = $bad_types;
		set_transient( 'cg_duplicate_template_warning', $cached, 5 * MINUTE_IN_SECONDS );
	}

	if ( ! empty( $cached ) ) {
		$list = implode( ', ', array_map( fn( $t ) => '<strong>' . $t . '</strong>', $cached ) );
		echo '<div class="notice notice-warning is-dismissible"><p><strong>Certificate Generator:</strong> The following certificate types have multiple templates but no <em>Event Date</em> set — the system cannot reliably pick the correct template. Please add an Event Date to each template: ' . $list . '.</p></div>';
	}
}
add_action( 'admin_notices', 'cg_certificates_duplicate_type_warning' );

// Add Preview Certificate Button to Certificates Admin Page
add_action( 'add_meta_boxes', 'add_preview_certificate_meta_box' );
function add_preview_certificate_meta_box() {
	add_meta_box(
		'preview_certificate_meta_box',
		'Preview Certificate',
		'render_preview_certificate_button',
		'certificates',
		'side',
		'high'
	);
}

function render_preview_certificate_button( $post ) {
	$preview_url = add_query_arg(
		array(
			'preview_certificate' => '1',
			'post_id'             => $post->ID,
		),
		site_url()
	);

	echo '<a href="' . esc_url( $preview_url ) . '" target="_blank" class="button">Preview Certificate</a>';
}

add_action( 'init', 'handle_certificate_preview' );
function handle_certificate_preview() {
	if ( isset( $_GET['preview_certificate'] ) && $_GET['preview_certificate'] === '1' && isset( $_GET['post_id'] ) ) {
		$post_id = intval( $_GET['post_id'] );

		// Read the actual post meta so the preview reflects real data
		$certificate_type = get_post_meta( $post_id, 'certificate_type', true );
		if ( empty( $certificate_type ) ) {
			wp_die( 'No certificate type is assigned to this post. Please set one and try again.' );
		}

		$post_type = get_post_type( $post_id );

		// If previewing a "certificates" template post directly:
		if ( $post_type === 'certificates' ) {
			$certificate_type = get_post_meta( $post_id, 'certificate_type', true ) ?: 'Preview Type';
			$event_date       = get_post_meta( $post_id, 'event_date', true ) ?: date( 'Y-m-d' );

			$post_data = array(
				'student_name'     => 'Jonathan Sample Student',
				'school_name'      => 'Example International School',
				'issue_date'       => $event_date,
				'certificate_type' => $certificate_type,
				'_preview_post_id' => $post_id,
			);

			// Add sample placeholder values for any extra fields registered for this
			// certificate type so that slots beyond the standard 3 render in the preview.
			if ( class_exists( 'CG_Field_Schema' ) ) {
				foreach ( CG_Field_Schema::get_extra_fields( $certificate_type ) as $slug ) {
					$post_data[ $slug ] = CG_Field_Schema::get_display_label( $slug );
				}
			}
		} else {
			// Previewing a student/teacher/school post:
			$certificate_type = get_post_meta( $post_id, 'certificate_type', true );
			if ( empty( $certificate_type ) ) {
				wp_die( 'No certificate type is assigned to this post. Please set one and try again.' );
			}

			$name_field  = ( $post_type === 'teachers' ) ? 'teacher_name'
						: ( ( $post_type === 'schools' ) ? 'school_name' : 'student_name' );
			$person_name = get_post_meta( $post_id, $name_field, true )
						?: get_the_title( $post_id );

			$post_data = array(
				'student_name'     => $person_name,
				'school_name'      => get_post_meta( $post_id, 'school_name', true ),
				'issue_date'       => get_post_meta( $post_id, 'issue_date', true ),
				'certificate_type' => $certificate_type,
				'_preview_post_id' => $post_id,
			);

			// Merge actual extra-field values so slots beyond the standard 3 render.
			// Mirrors the same logic in cg_handle_cert_download (single-student-template.php).
			if ( class_exists( 'CG_Field_Schema' ) ) {
				foreach ( CG_Field_Schema::get_extra_fields( $certificate_type ) as $slug ) {
					$value = get_post_meta( $post_id, $slug, true );
					if ( $value !== '' && $value !== false ) {
						$post_data[ $slug ] = $value;
					}
				}
			}
		}

		// Generate the PDF for preview
		$certificate_url = generate_certificate_pdf_with_data( $post_data );
		if ( $certificate_url ) {
			nocache_headers();
			wp_redirect( add_query_arg( 'v', time(), $certificate_url ) );
			exit;
		} else {
			wp_die( 'Failed to generate certificate preview. Check that a template with a matching event_date exists for type "' . esc_html( $certificate_type ) . '".' );
		}
	}
}


// Fields Definitions
$student_fields = array(
	array(
		'key'   => 'field_student_name',
		'label' => 'Student Name',
		'name'  => 'student_name',
		'type'  => 'text',
	),
	array(
		'key'   => 'field_email',
		'label' => 'Email',
		'name'  => 'email',
		'type'  => 'email',
	),
	array(
		'key'   => 'field_school_name',
		'label' => 'School Name',
		'name'  => 'school_name',
		'type'  => 'text',
	),
	array(
		'key'            => 'field_issue_date',
		'label'          => 'Issue Date',
		'name'           => 'issue_date',
		'type'           => 'date_picker',
		'display_format' => 'd-m-Y',
		'return_format'  => 'd-m-Y',
		'default_value'  => date( 'd-m-Y' ),
	),
	array(
		'key'   => 'field_certificate_type',
		'label' => 'Certificate Type',
		'name'  => 'certificate_type',
		'type'  => 'text',
	),
);

$teacher_fields = array(
	array(
		'key'   => 'field_teacher_name',
		'label' => 'Teacher Name',
		'name'  => 'teacher_name',
		'type'  => 'text',
	),
	array(
		'key'   => 'field_email_teacher',
		'label' => 'Email',
		'name'  => 'email',
		'type'  => 'email',
	),
	array(
		'key'   => 'field_school_name_teacher',
		'label' => 'School Name',
		'name'  => 'school_name',
		'type'  => 'text',
	),
	array(
		'key'   => 'field_school_abbreviation_teacher',
		'label' => 'School Abbreviation',
		'name'  => 'school_abbreviation',
		'type'  => 'text',
	),
	array(
		'key'            => 'field_issue_date_teacher',
		'label'          => 'Issue Date',
		'name'           => 'issue_date',
		'type'           => 'date_picker',
		'display_format' => 'd-m-Y',
		'return_format'  => 'd-m-Y',
		'default_value'  => date( 'd-m-Y' ),
	),
	array(
		'key'   => 'field_certificate_type',
		'label' => 'Certificate Type',
		'name'  => 'certificate_type',
		'type'  => 'text',
	),
);

$school_fields = array(
	array(
		'key'   => 'field_school_name_schools',
		'label' => 'School Name',
		'name'  => 'school_name',
		'type'  => 'text',
	),
	array(
		'key'   => 'field_school_abbreviation_schools',
		'label' => 'School Abbreviation',
		'name'  => 'school_abbreviation',
		'type'  => 'text',
	),
	array(
		'key'   => 'field_place',
		'label' => 'Place',
		'name'  => 'place',
		'type'  => 'text',
	),
	array(
		'key'            => 'field_issue_date_schools',
		'label'          => 'Issue Date',
		'name'           => 'issue_date',
		'type'           => 'date_picker',
		'display_format' => 'd-m-Y',
		'return_format'  => 'd-m-Y',
		'default_value'  => date( 'd-m-Y' ),
	),
	array(
		'key'   => 'field_certificate_type',
		'label' => 'Certificate Type',
		'name'  => 'certificate_type',
		'type'  => 'text',
	),
);

// Define Fields for Certificates
$certificate_fields = array(
	array(
		'key'   => 'field_certificate_type',
		'label' => 'Certificate Type',
		'name'  => 'certificate_type',
		'type'  => 'text',
	),
	array(
		'key'   => 'field_template_url',
		'label' => 'Template URL',
		'name'  => 'template_url',
		'type'  => 'url',
	),
	array(
		'key'     => 'field_template_orientation',
		'label'   => 'Template Orientation',
		'name'    => 'template_orientation',
		'type'    => 'select',
		'choices' => array(
			'landscape' => 'Landscape',
			'portrait'  => 'Portrait',
		),
	),
	array(
		'key'   => 'field_font_size',
		'label' => 'Font Size',
		'name'  => 'font_size',
		'type'  => 'number',
	),
	array(
		'key'   => 'field_font_color',
		'label' => 'Font Color',
		'name'  => 'font_color',
		'type'  => 'color_picker',
	),
	array(
		'key'     => 'field_font_style',
		'label'   => 'Font Style',
		'name'    => 'font_style',
		'type'    => 'select',
		'choices' => array(
			'Arial'           => 'Arial',
			'Helvetica'       => 'Helvetica',
			'Times New Roman' => 'Times New Roman',
			'Courier New'     => 'Courier New',
			'Verdana'         => 'Verdana',
			'Palatino'        => 'Palatino',
			'Garamond'        => 'Garamond',
		),
	),
	array(
		'key'   => 'field_field_1_position_x',
		'label' => 'Field 1 Position X',
		'name'  => 'field_1_position_x',
		'type'  => 'number',
	),
	array(
		'key'   => 'field_field_1_position_y',
		'label' => 'Field 1 Position Y',
		'name'  => 'field_1_position_y',
		'type'  => 'number',
	),
	array(
		'key'           => 'field_field_1_visible',
		'label'         => 'Field 1 Visible',
		'name'          => 'field_1_visible',
		'type'          => 'true_false',
		'default_value' => 1,
	),
	array(
		'key'   => 'field_field_2_position_x',
		'label' => 'Field 2 Position X',
		'name'  => 'field_2_position_x',
		'type'  => 'number',
	),
	array(
		'key'   => 'field_field_2_position_y',
		'label' => 'Field 2 Position Y',
		'name'  => 'field_2_position_y',
		'type'  => 'number',
	),
	array(
		'key'           => 'field_field_2_visible',
		'label'         => 'Field 2 Visible',
		'name'          => 'field_2_visible',
		'type'          => 'true_false',
		'default_value' => 1,
	),
	array(
		'key'   => 'field_field_3_position_x',
		'label' => 'Field 3 Position X',
		'name'  => 'field_3_position_x',
		'type'  => 'number',
	),
	array(
		'key'   => 'field_field_3_position_y',
		'label' => 'Field 3 Position Y',
		'name'  => 'field_3_position_y',
		'type'  => 'number',
	),
	array(
		'key'           => 'field_field_3_visible',
		'label'         => 'Field 3 Visible',
		'name'          => 'field_3_visible',
		'type'          => 'true_false',
		'default_value' => 1,
	),
);

// Add Advanced Custom Fields
function add_acf_field_group( $group_key, $title, $fields, $post_type ) {
	if ( function_exists( 'acf_add_local_field_group' ) ) {
		acf_add_local_field_group(
			array(
				'key'      => $group_key,
				'title'    => $title,
				'fields'   => $fields,
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => $post_type,
						),
					),
				),
			)
		);
	}
}

function add_custom_fields() {
	global $student_fields, $teacher_fields, $school_fields, $certificate_fields;
	add_acf_field_group( 'group_students', 'Student Fields', $student_fields, 'students' );
	add_acf_field_group( 'group_teachers', 'Teacher Fields', $teacher_fields, 'teachers' );
	add_acf_field_group( 'group_schools', 'School Fields', $school_fields, 'schools' );
	add_acf_field_group( 'group_certificates', 'Certificate Settings', $certificate_fields, 'certificates' );
}
add_action( 'acf/init', 'add_custom_fields' );

// Add Admin Page for Data Management
// function custom_post_admin_menu() {
// add_menu_page(
// 'Custom Post Management',
// 'Post Management',
// 'manage_options',
// 'custom-post-management',
// 'render_custom_post_admin_page',
// 'dashicons-admin-generic',
// 20
// );
// }
// add_action('admin_menu', 'custom_post_admin_menu');

// Enqueue WordPress media uploader on CPT edit screens
add_action( 'admin_enqueue_scripts', 'cg_enqueue_cpt_admin_scripts' );
function cg_enqueue_cpt_admin_scripts( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	global $post_type;
	if ( in_array( $post_type, array( 'students', 'teachers', 'schools', 'certificates' ), true ) ) {
		wp_enqueue_media();
	}
	if ( $post_type === 'certificates' ) {
		wp_enqueue_script( 'jquery-ui-draggable' );
	}
}

// Hide auto-generated title box on CPT edit screens
add_action( 'admin_head', 'cg_cpt_admin_head_styles' );
function cg_cpt_admin_head_styles() {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'students', 'teachers', 'schools', 'certificates' ), true ) ) {
		return;
	}
	echo '<style>#titlediv{display:none!important}.cg-custom-form-wrap{padding-top:4px}</style>';
}

// Render the Admin Page
function render_custom_post_admin_page() {
	echo '<div class="wrap">';
	echo '<h1>Manage Custom Post Data</h1>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'custom_post_options_group' );
	do_settings_sections( 'custom-post-management' );
	submit_button();
	echo '</form>';
	echo '</div>';
}
?>