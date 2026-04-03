<?php
/**
 * Integration Health Dashboard Widget
 *
 * Displays the status of all three plugins and their cross-plugin integration
 * links on the WordPress Dashboard home screen.
 *
 * @package Certificate Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the dashboard widget.
 */
add_action('wp_dashboard_setup', 'cg_register_integration_dashboard_widget');

function cg_register_integration_dashboard_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }

    wp_add_dashboard_widget(
        'cg_integration_health_widget',
        __('Plugin Integration Status', 'certificate-generator'),
        'cg_render_integration_dashboard_widget'
    );
}

/**
 * Render the dashboard widget content.
 */
function cg_render_integration_dashboard_widget() {
    // Detect active plugins
    $cg_active      = class_exists('CertificateGeneratorPlugin') || function_exists('certificate_generator_get_rate_limit_config');
    $chatbot_active = class_exists('AI_Chatbot_Widget') || class_exists('AI_Chatbot_API');
    $dt_active      = class_exists('WP_Dynamic_Tags_Plugin') || class_exists('WP_Dynamic_Tags_Table_Manager');

    $ok  = '<span style="color:#46b450;">&#10003;</span>';
    $warn = '<span style="color:#ffb900;">&#9888;</span>';
    $err  = '<span style="color:#dc3232;">&#10007;</span>';

    ?>
    <style>
        #cg_integration_health_widget .inside { padding: 0 12px 12px; }
        .cg-plugin-status-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .cg-plugin-status-table td { padding: 4px 6px; font-size: 13px; }
        .cg-plugin-status-table tr:nth-child(even) { background: #f9f9f9; }
        .cg-integration-links-table { width: 100%; border-collapse: collapse; }
        .cg-integration-links-table td { padding: 4px 6px; font-size: 12px; }
        .cg-integration-links-table tr:nth-child(even) { background: #f9f9f9; }
        .cg-widget-section-title { font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #555; margin: 8px 0 4px; }
    </style>

    <p class="cg-widget-section-title"><?php esc_html_e('Plugin Status', 'certificate-generator'); ?></p>
    <table class="cg-plugin-status-table">
        <tr>
            <td><?php echo $cg_active ? $ok : $err; ?></td>
            <td><?php esc_html_e('Certificate-Generator-v7', 'certificate-generator'); ?></td>
            <td style="color:<?php echo $cg_active ? '#46b450' : '#dc3232'; ?>;">
                <?php echo $cg_active ? esc_html__('Active', 'certificate-generator') : esc_html__('Inactive', 'certificate-generator'); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $chatbot_active ? $ok : $err; ?></td>
            <td><?php esc_html_e('chatbot-by-eshaan', 'certificate-generator'); ?></td>
            <td style="color:<?php echo $chatbot_active ? '#46b450' : '#dc3232'; ?>;">
                <?php echo $chatbot_active ? esc_html__('Active', 'certificate-generator') : esc_html__('Inactive', 'certificate-generator'); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $dt_active ? $ok : $err; ?></td>
            <td><?php esc_html_e('wp-dynamic-tags-v4', 'certificate-generator'); ?></td>
            <td style="color:<?php echo $dt_active ? '#46b450' : '#dc3232'; ?>;">
                <?php echo $dt_active ? esc_html__('Active', 'certificate-generator') : esc_html__('Inactive', 'certificate-generator'); ?>
            </td>
        </tr>
    </table>

    <p class="cg-widget-section-title"><?php esc_html_e('Integration Links', 'certificate-generator'); ?></p>
    <table class="cg-integration-links-table">
        <?php
        // CG → Dynamic Tags tag sync
        $cg_dt_sync = $cg_active && $dt_active;
        $cg_dt_last = get_option('cg_last_dt_sync_time', '');
        echo '<tr>';
        echo '<td>' . ($cg_dt_sync ? $ok : $warn) . '</td>';
        echo '<td>' . esc_html__('Cert → Dynamic Tags sync', 'certificate-generator') . '</td>';
        echo '<td style="color:#555;">';
        if ($cg_dt_sync) {
            echo $cg_dt_last ? esc_html__('Last: ', 'certificate-generator') . esc_html($cg_dt_last) : esc_html__('Ready (save a student to trigger)', 'certificate-generator');
        } else {
            echo esc_html__('Requires both plugins active', 'certificate-generator');
        }
        echo '</td></tr>';

        // Chatbot → Dynamic Tags sync
        $chatbot_dt_sync = $chatbot_active && $dt_active;
        $chatbot_dt_last = get_option('ai_chatbot_last_sync_timestamp', 0);
        echo '<tr>';
        echo '<td>' . ($chatbot_dt_sync ? $ok : $warn) . '</td>';
        echo '<td>' . esc_html__('Chatbot → Dynamic Tags', 'certificate-generator') . '</td>';
        echo '<td style="color:#555;">';
        if ($chatbot_dt_sync) {
            echo $chatbot_dt_last ? esc_html__('Last: ', 'certificate-generator') . esc_html(date('Y-m-d H:i', $chatbot_dt_last)) : esc_html__('Never synced', 'certificate-generator');
        } else {
            echo esc_html__('Requires both plugins active', 'certificate-generator');
        }
        echo '</td></tr>';

        // Chatbot → Certificate status lookup
        $cert_lookup = $chatbot_active && $cg_active;
        echo '<tr>';
        echo '<td>' . ($cert_lookup ? $ok : $warn) . '</td>';
        echo '<td>' . esc_html__('Chatbot → Cert status', 'certificate-generator') . '</td>';
        echo '<td style="color:#555;">' . esc_html__('Email session required', 'certificate-generator') . '</td>';
        echo '</tr>';
        ?>
    </table>

    <?php
    // Count tags in Dynamic Tags by category
    if ($dt_active && class_exists('WP_Dynamic_Tags_Table_Manager')) {
        $table_manager = WP_Dynamic_Tags_Table_Manager::get_instance();
        $cert_tags  = $table_manager->get_tags(array('search' => 'cg_', 'limit' => 0));
        $event_tags = $table_manager->get_tags(array('search' => 'event_', 'limit' => 0));
        echo '<p style="font-size:12px;color:#777;margin-top:8px;">';
        printf(
            esc_html__('Cert Tags in Dynamic Tags: %d &bull; Event Tags: %d', 'certificate-generator'),
            count($cert_tags),
            count($event_tags)
        );
        echo '</p>';
    }
    ?>
    <?php
}
