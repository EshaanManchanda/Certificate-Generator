<?php
/**
 * Admin Page for Bulk Email Sending
 *
 * @package Certificate Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'certificate_generator_add_bulk_email_menu');
function certificate_generator_add_bulk_email_menu() {
    add_submenu_page(
        'options-general.php',
        __('Bulk Send Certificates', 'certificate-generator'),
        __('Bulk Send Certificates', 'certificate-generator'),
        'manage_options',
        'certificate-bulk-send',
        'certificate_generator_bulk_send_page'
    );
}

// Handle AJAX requests
add_action('wp_ajax_cert_start_bulk_send', 'certificate_generator_ajax_start_bulk_send');
add_action('wp_ajax_cert_get_queue_progress', 'certificate_generator_ajax_get_queue_progress');
add_action('wp_ajax_cert_pause_queue', 'certificate_generator_ajax_pause_queue');
add_action('wp_ajax_cert_resume_queue', 'certificate_generator_ajax_resume_queue');
add_action('wp_ajax_cert_clear_queue', 'certificate_generator_ajax_clear_queue');

// NEW: Filter-related AJAX handlers
add_action('wp_ajax_cert_get_filter_options', 'certificate_generator_ajax_get_filter_options');
add_action('wp_ajax_cert_preview_recipients', 'certificate_generator_ajax_preview_recipients');
add_action('wp_ajax_cert_send_to_filtered', 'certificate_generator_ajax_send_to_filtered');

function certificate_generator_ajax_start_bulk_send() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';

    if (!in_array($post_type, ['students', 'teachers', 'schools'])) {
        wp_send_json_error('Invalid post type');
    }

    $result = certificate_generator_start_bulk_send($post_type);

    wp_send_json_success($result);
}

function certificate_generator_ajax_get_queue_progress() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    $progress = certificate_generator_get_queue_progress();

    wp_send_json_success($progress);
}

function certificate_generator_ajax_pause_queue() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    certificate_generator_pause_queue();

    wp_send_json_success('Queue paused');
}

function certificate_generator_ajax_resume_queue() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    certificate_generator_resume_queue();

    wp_send_json_success('Queue resumed');
}

function certificate_generator_ajax_clear_queue() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $cleared = certificate_generator_clear_queue();

    wp_send_json_success("$cleared emails cleared from queue");
}

// Render bulk send page
function certificate_generator_bulk_send_page() {
    $progress = certificate_generator_get_queue_progress();
    $rate_status = certificate_generator_get_rate_limit_status();

    ?>
    <div class="wrap">
        <h1><?php _e('Bulk Send Certificates', 'certificate-generator'); ?></h1>

        <div class="card" style="max-width: 1000px;">
            <h2>📊 Current Queue Status</h2>

            <table class="widefat" style="margin-bottom: 20px;">
                <tr>
                    <th><?php _e('Total in Queue', 'certificate-generator'); ?></th>
                    <td><strong><?php echo number_format($progress['stats']['total']); ?></strong></td>
                </tr>
                <tr>
                    <th><?php _e('Pending', 'certificate-generator'); ?></th>
                    <td><span style="color: #d63638;"><?php echo number_format($progress['stats']['pending']); ?></span></td>
                </tr>
                <tr>
                    <th><?php _e('Sent', 'certificate-generator'); ?></th>
                    <td><span style="color: #00a32a;"><?php echo number_format($progress['stats']['sent']); ?></span></td>
                </tr>
                <tr>
                    <th><?php _e('Failed', 'certificate-generator'); ?></th>
                    <td><span style="color: #d63638;"><?php echo number_format($progress['stats']['failed']); ?></span></td>
                </tr>
                <tr>
                    <th><?php _e('Progress', 'certificate-generator'); ?></th>
                    <td>
                        <div style="background: #f0f0f1; border-radius: 3px; height: 24px; position: relative;">
                            <div style="background: #00a32a; height: 100%; width: <?php echo $progress['progress_percentage']; ?>%; border-radius: 3px;"></div>
                            <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold;">
                                <?php echo $progress['progress_percentage']; ?>%
                            </span>
                        </div>
                    </td>
                </tr>
                <?php if ($progress['eta']): ?>
                <tr>
                    <th><?php _e('Estimated Completion', 'certificate-generator'); ?></th>
                    <td>
                        <?php echo $progress['eta_human']; ?> remaining
                        <br><small><?php echo $progress['eta']; ?></small>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php _e('Queue Status', 'certificate-generator'); ?></th>
                    <td>
                        <?php if ($progress['is_paused']): ?>
                            <span style="color: #d63638; font-weight: bold;">⏸️ PAUSED</span>
                        <?php else: ?>
                            <span style="color: #00a32a; font-weight: bold;">▶️ RUNNING</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h2>⚡ Rate Limiting Status</h2>
            <table class="widefat" style="margin-bottom: 20px;">
                <tr>
                    <th><?php _e('Emails Sent (Last Hour)', 'certificate-generator'); ?></th>
                    <td>
                        <?php echo $rate_status['usage']['last_hour']; ?> / <?php echo $rate_status['limits']['emails_per_hour']; ?>
                        (<?php echo $rate_status['usage']['hourly_percentage']; ?>%)
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Remaining This Hour', 'certificate-generator'); ?></th>
                    <td><?php echo $rate_status['usage']['hourly_remaining']; ?></td>
                </tr>
                <tr>
                    <th><?php _e('Can Send Now', 'certificate-generator'); ?></th>
                    <td>
                        <?php if ($rate_status['can_send']['can_send']): ?>
                            <span style="color: #00a32a;">✅ Yes</span>
                        <?php else: ?>
                            <span style="color: #d63638;">❌ No - <?php echo $rate_status['can_send']['reason']; ?></span>
                            <br><small>Wait: <?php echo certificate_generator_format_wait_time($rate_status['can_send']['wait_seconds']); ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h2>🚀 Start Bulk Send with Advanced Filters</h2>
            <p>Use filters to precisely target which certificates to send. Preview recipients before sending.</p>

            <!-- Filter and Preview Container -->
            <div class="cert-filter-container">
                <!-- Left Panel: Filters -->
                <div class="cert-filter-panel">
                    <form id="bulk-send-form">
                        <?php wp_nonce_field('cert_bulk_send', 'cert_nonce'); ?>

                        <!-- Post Type Filter -->
                        <div class="cert-filter-section">
                            <h3><?php _e('Post Types', 'certificate-generator'); ?></h3>
                            <select name="post_types[]" id="cert-filter-post-types" class="cert-filter-select" multiple size="3">
                                <option value="students" selected><?php _e('Students', 'certificate-generator'); ?></option>
                                <option value="teachers" selected><?php _e('Teachers', 'certificate-generator'); ?></option>
                                <option value="schools" selected><?php _e('Schools', 'certificate-generator'); ?></option>
                            </select>
                            <p class="cert-help-text"><?php _e('Hold Ctrl/Cmd to select multiple', 'certificate-generator'); ?></p>
                        </div>

                        <!-- School Filter -->
                        <div class="cert-filter-section">
                            <h3><?php _e('Filter by School', 'certificate-generator'); ?></h3>
                            <select name="schools[]" id="cert-filter-schools" class="cert-filter-select" multiple size="5">
                                <!-- Populated via AJAX -->
                            </select>
                            <p class="cert-help-text"><?php _e('Leave empty to include all schools', 'certificate-generator'); ?></p>
                        </div>

                        <!-- Certificate Type Filter -->
                        <div class="cert-filter-section">
                            <h3><?php _e('Filter by Certificate Type', 'certificate-generator'); ?></h3>
                            <select name="certificate_types[]" id="cert-filter-certificate-types" class="cert-filter-select" multiple size="5">
                                <!-- Populated via AJAX -->
                            </select>
                            <p class="cert-help-text"><?php _e('Leave empty to include all types', 'certificate-generator'); ?></p>
                        </div>

                        <!-- Email Status Filter -->
                        <div class="cert-filter-section">
                            <h3><?php _e('Email Status', 'certificate-generator'); ?></h3>
                            <div class="cert-checkbox-group">
                                <label>
                                    <input type="checkbox" name="email_status[]" value="not_sent" checked>
                                    <?php _e('Not Sent Yet', 'certificate-generator'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="email_status[]" value="sent" checked>
                                    <?php _e('Already Sent', 'certificate-generator'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="email_status[]" value="no_email" checked>
                                    <?php _e('No Email Address', 'certificate-generator'); ?>
                                </label>
                            </div>
                        </div>

                        <!-- Email Search Filter -->
                        <div class="cert-filter-section">
                            <h3><?php _e('Filter by Email', 'certificate-generator'); ?></h3>
                            <label>
                                <?php _e('Search Email', 'certificate-generator'); ?>:
                                <input type="text" name="email_search" id="cert-filter-email-search" class="cert-filter-input" placeholder="<?php _e('e.g., @example.com', 'certificate-generator'); ?>">
                            </label>
                            <p class="cert-help-text"><?php _e('Search for specific email patterns', 'certificate-generator'); ?></p>
                        </div>

                        <!-- Email List Filter -->
                        <div class="cert-filter-section">
                            <label>
                                <?php _e('Or Paste Email List', 'certificate-generator'); ?>:
                                <textarea name="email_list" id="cert-filter-email-list" class="cert-filter-textarea" placeholder="<?php _e('Paste emails (comma or newline separated)', 'certificate-generator'); ?>"></textarea>
                            </label>
                            <p class="cert-help-text"><?php _e('One email per line or comma-separated', 'certificate-generator'); ?></p>
                        </div>

                        <!-- Skip Already Sent Option -->
                        <div class="cert-filter-section">
                            <label>
                                <input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent">
                                <strong><?php _e('Skip certificates already sent', 'certificate-generator'); ?></strong>
                            </label>
                            <p class="cert-help-text"><?php _e('Check this to avoid duplicate emails when sending', 'certificate-generator'); ?></p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="cert-filter-buttons">
                            <button type="button" class="cert-btn cert-btn-primary" id="cert-start-bulk-send" disabled>
                                🚀 <?php _e('Start Bulk Send', 'certificate-generator'); ?>
                            </button>
                            <button type="button" class="cert-btn cert-btn-secondary" id="cert-clear-filters">
                                🔄 <?php _e('Clear Filters', 'certificate-generator'); ?>
                            </button>
                        </div>

                        <div id="bulk-send-result" style="margin-top: 20px;"></div>
                    </form>
                </div>

                <!-- Right Panel: Preview -->
                <div class="cert-preview-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="margin: 0;"><?php _e('Recipients Preview', 'certificate-generator'); ?></h3>
                        <button type="button" class="cert-export-btn" id="cert-export-preview">
                            📥 <?php _e('Export CSV', 'certificate-generator'); ?>
                        </button>
                    </div>

                    <div class="cert-preview-content">
                        <div class="cert-loading"><?php _e('Loading preview...', 'certificate-generator'); ?></div>
                    </div>
                </div>
            </div>

            <h2>⚙️ Queue Controls</h2>
            <p>
                <button type="button" class="button" id="pause-queue" <?php echo $progress['is_paused'] ? 'disabled' : ''; ?>>
                    ⏸️ <?php _e('Pause Queue', 'certificate-generator'); ?>
                </button>
                <button type="button" class="button" id="resume-queue" <?php echo !$progress['is_paused'] ? 'disabled' : ''; ?>>
                    ▶️ <?php _e('Resume Queue', 'certificate-generator'); ?>
                </button>
                <button type="button" class="button button-secondary" id="refresh-status">
                    🔄 <?php _e('Refresh Status', 'certificate-generator'); ?>
                </button>
                <button type="button" class="button button-link-delete" id="clear-queue" onclick="return confirm('Are you sure? This will clear all pending emails from the queue.')">
                    🗑️ <?php _e('Clear Queue', 'certificate-generator'); ?>
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
        $('#bulk-send-form').on('submit', function(e) {
            e.preventDefault();

            var button = $('#start-bulk-send');
            var result = $('#bulk-send-result');
            var postType = $('#post-type-select').val();

            button.prop('disabled', true).text('Processing...');
            result.html('<div class="notice notice-info"><p>Starting bulk send...</p></div>');

            $.post(ajaxurl, {
                action: 'cert_start_bulk_send',
                nonce: '<?php echo wp_create_nonce('cert_bulk_send'); ?>',
                post_type: postType
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    result.html('<div class="notice notice-success"><p><strong>Success!</strong><br>' +
                        data.message + '<br>' +
                        'Estimated completion: ' + data.estimate.estimated_completion_human + '</p></div>');

                    // Refresh page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    result.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                    button.prop('disabled', false).text('📧 Start Bulk Send');
                }
            });
        });

        $('#pause-queue').on('click', function() {
            $.post(ajaxurl, {
                action: 'cert_pause_queue',
                nonce: '<?php echo wp_create_nonce('cert_bulk_send'); ?>'
            }, function(response) {
                location.reload();
            });
        });

        $('#resume-queue').on('click', function() {
            $.post(ajaxurl, {
                action: 'cert_resume_queue',
                nonce: '<?php echo wp_create_nonce('cert_bulk_send'); ?>'
            }, function(response) {
                location.reload();
            });
        });

        $('#clear-queue').on('click', function() {
            if (!confirm('Are you sure you want to clear the queue? This cannot be undone.')) {
                return;
            }

            $.post(ajaxurl, {
                action: 'cert_clear_queue',
                nonce: '<?php echo wp_create_nonce('cert_bulk_send'); ?>'
            }, function(response) {
                location.reload();
            });
        });

        $('#refresh-status').on('click', function() {
            location.reload();
        });

        // Auto-refresh every 30 seconds if queue is active
        <?php if ($progress['stats']['pending'] > 0 && !$progress['is_paused']): ?>
        setInterval(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
    });
    </script>
    <?php
}

/**
 * AJAX: Get filter options (schools, certificate types)
 */
function certificate_generator_ajax_get_filter_options() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $option_type = isset($_POST['option_type']) ? sanitize_text_field($_POST['option_type']) : '';

    $data = [];

    switch ($option_type) {
        case 'schools':
            $data = certificate_generator_get_unique_schools();
            break;
        case 'certificate_types':
            $data = certificate_generator_get_unique_certificate_types();
            break;
        case 'emails':
            $data = certificate_generator_get_unique_emails();
            break;
        default:
            wp_send_json_error(['message' => 'Invalid option type']);
    }

    wp_send_json_success($data);
}

/**
 * AJAX: Preview recipients based on filters
 */
function certificate_generator_ajax_preview_recipients() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    // Get filter parameters
    $filters = isset($_POST['filters']) ? $_POST['filters'] : [];

    // DEBUG: Log received filters
    error_log('=== PREVIEW AJAX DEBUG ===');
    error_log('Raw POST filters: ' . print_r($_POST['filters'], true));
    error_log('=========================');

    // Sanitize filters
    $filters['post_types'] = isset($filters['post_types']) && is_array($filters['post_types'])
        ? array_map('sanitize_text_field', $filters['post_types'])
        : ['students', 'teachers', 'schools'];

    $filters['schools'] = isset($filters['schools']) && is_array($filters['schools'])
        ? array_map('sanitize_text_field', $filters['schools'])
        : [];

    $filters['certificate_types'] = isset($filters['certificate_types']) && is_array($filters['certificate_types'])
        ? array_map('sanitize_text_field', $filters['certificate_types'])
        : [];

    $filters['email_status'] = isset($filters['email_status']) && is_array($filters['email_status'])
        ? array_map('sanitize_text_field', $filters['email_status'])
        : [];

    $filters['emails'] = isset($filters['emails']) && is_array($filters['emails'])
        ? array_map('sanitize_email', $filters['emails'])
        : [];

    $filters['email_search'] = isset($filters['email_search'])
        ? sanitize_text_field($filters['email_search'])
        : '';

    $filters['skip_already_sent'] = isset($filters['skip_already_sent']) && $filters['skip_already_sent'] !== false && $filters['skip_already_sent'] !== 'false' && $filters['skip_already_sent'] !== ''
        ? true
        : false; // Default to false (unchecked) to show all certificates

    $filters['limit'] = isset($_POST['limit']) ? intval($_POST['limit']) : 1000; // Increased from 100 to handle large batches
    $filters['offset'] = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    // Get recipients
    $recipients = certificate_generator_get_filtered_recipients($filters);

    // Get statistics
    $statistics = certificate_generator_get_filter_statistics($filters);

    // DEBUG: Log what we're sending back
    error_log('=== SENDING RESPONSE ===');
    error_log('Recipients count: ' . count($recipients));
    error_log('Statistics: ' . print_r($statistics, true));
    error_log('=======================');

    wp_send_json_success([
        'recipients' => $recipients,
        'statistics' => $statistics,
        'filters_applied' => $filters
    ]);
}

/**
 * AJAX: Start bulk send with filters
 */
function certificate_generator_ajax_send_to_filtered() {
    check_ajax_referer('cert_bulk_send', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    // Get filter parameters
    $filters = isset($_POST['filters']) ? $_POST['filters'] : [];

    // Sanitize filters (same as preview)
    $filters['post_types'] = isset($filters['post_types']) && is_array($filters['post_types'])
        ? array_map('sanitize_text_field', $filters['post_types'])
        : ['students', 'teachers', 'schools'];

    $filters['schools'] = isset($filters['schools']) && is_array($filters['schools'])
        ? array_map('sanitize_text_field', $filters['schools'])
        : [];

    $filters['certificate_types'] = isset($filters['certificate_types']) && is_array($filters['certificate_types'])
        ? array_map('sanitize_text_field', $filters['certificate_types'])
        : [];

    $filters['email_status'] = isset($filters['email_status']) && is_array($filters['email_status'])
        ? array_map('sanitize_text_field', $filters['email_status'])
        : [];

    $filters['emails'] = isset($filters['emails']) && is_array($filters['emails'])
        ? array_map('sanitize_email', $filters['emails'])
        : [];

    $filters['email_search'] = isset($filters['email_search'])
        ? sanitize_text_field($filters['email_search'])
        : '';

    $filters['skip_already_sent'] = isset($filters['skip_already_sent']) && $filters['skip_already_sent'] !== false && $filters['skip_already_sent'] !== 'false' && $filters['skip_already_sent'] !== ''
        ? true
        : false; // Default to false (unchecked) to show all certificates

    // Get all matching recipients (no limit)
    $filters['limit'] = 999999;
    $filters['offset'] = 0;
    $recipients = certificate_generator_get_filtered_recipients($filters);

    if (empty($recipients)) {
        wp_send_json_error(['message' => 'No recipients match the selected filters']);
    }

    // Extract post IDs
    $post_ids = array_column($recipients, 'post_id');

    // Group by post type and queue
    $results_by_type = [];
    $total_queued = 0;

    foreach (['students', 'teachers', 'schools'] as $post_type) {
        $type_post_ids = array_filter($post_ids, function($post_id) use ($post_type) {
            return get_post_type($post_id) === $post_type;
        });

        if (!empty($type_post_ids)) {
            $result = certificate_generator_bulk_queue_emails($post_type, array_values($type_post_ids), $filters['skip_already_sent']);
            $results_by_type[$post_type] = $result;
            $total_queued += $result['queued'];
        }
    }

    wp_send_json_success([
        'queued' => $total_queued,
        'details' => $results_by_type,
        'message' => sprintf('Successfully queued %d certificates for sending', $total_queued)
    ]);
}
?>
