<?php
declare(strict_types=1);

namespace CertificateGenerator\Admin\Pages;

/**
 * Serial Number Settings admin page.
 */
class SerialSettingsPage extends Page {

    public function __construct() {
        parent::__construct('cg-serial-settings', 'Serial Numbers');
    }

    public function render(): void {
        if (!$this->check_permission()) {
            wp_die(__('Insufficient permissions', 'certificate-generator'));
        }

        $prefix = get_option('cg_serial_prefix', 'CERT');
        $length = (int) get_option('cg_serial_length', 8);
        $suffix = get_option('cg_serial_suffix', '');
        $reset_period = get_option('cg_serial_reset_period', 'none');
        $include_date = (bool) get_option('cg_serial_include_date', false);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->title); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields('cg_serial_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="cg_serial_prefix">Prefix</label></th>
                        <td>
                            <input type="text" id="cg_serial_prefix" name="cg_serial_prefix"
                                   value="<?php echo esc_attr($prefix); ?>" class="regular-text">
                            <p class="description">Prefix added before the serial number</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cg_serial_length">Number Length</label></th>
                        <td>
                            <input type="number" id="cg_serial_length" name="cg_serial_length"
                                   value="<?php echo esc_attr($length); ?>" min="4" max="12" class="small-text">
                            <p class="description">Number of digits in the sequential part (4-12)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cg_serial_suffix">Suffix</label></th>
                        <td>
                            <input type="text" id="cg_serial_suffix" name="cg_serial_suffix"
                                   value="<?php echo esc_attr($suffix); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cg_serial_reset_period">Reset Period</label></th>
                        <td>
                            <select id="cg_serial_reset_period" name="cg_serial_reset_period">
                                <option value="none" <?php selected($reset_period, 'none'); ?>>Never Reset</option>
                                <option value="daily" <?php selected($reset_period, 'daily'); ?>>Daily</option>
                                <option value="monthly" <?php selected($reset_period, 'monthly'); ?>>Monthly</option>
                                <option value="yearly" <?php selected($reset_period, 'yearly'); ?>>Yearly</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cg_serial_include_date">Include Date in Serial</label></th>
                        <td>
                            <input type="checkbox" id="cg_serial_include_date" name="cg_serial_include_date"
                                   value="1" <?php checked($include_date, true); ?>>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}
