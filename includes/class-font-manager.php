<?php

if (!defined('ABSPATH')) {
    exit;
}

class CertificateGenerator_FontManager
{

    private static $instance = null;
    private $font_path;
    private $available_fonts = array();
    private $essential_fonts_loaded = false;
    private $font_cache = array();
    private $memory_usage_threshold = 32; // MB

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->font_path = plugin_dir_path(__FILE__) . 'fpdf/font/';

        // Only load essential fonts during construction to avoid memory issues
        $this->load_essential_fonts();
    }

    /**
     * Load only essential fonts to reduce memory footprint during activation
     */
    private function load_essential_fonts()
    {
        // Define essential fonts that should always be available
        // Updated for optimized 200-font collection
        $essential_fonts = array(
            // User-requested fonts (top priority)
            'arial' => 'Arial',
            'times' => 'Times',
            'timesb' => 'Times Bold',
            'timesi' => 'Times Italic',
            'timesbi' => 'Times Bold Italic',
            'rumblebravescriptitalic' => 'Rumble Brave Script Italic',

            // Core system fonts
            'helvetica' => 'Helvetica',
            'helveticab' => 'Helvetica Bold',
            'helveticai' => 'Helvetica Italic',
            'helveticabi' => 'Helvetica Bold Italic',
            'courier' => 'Courier',
            'courierb' => 'Courier Bold',
            'courieri' => 'Courier Italic',
            'courierbi' => 'Courier Bold Italic',
            'georgia' => 'Georgia',
            'verdanab' => 'Verdana Bold',

            // Popular certificate fonts
            'opensans' => 'Open Sans',
            'lato' => 'Lato',
            'pacifico' => 'Pacifico',
            'lobster' => 'Lobster',
            'poppins' => 'Poppins',
            'comicspans' => 'Comic Sans'
        );

        foreach ($essential_fonts as $font_file => $display_name) {
            $font_path = $this->font_path . $font_file . '.php';
            $z_path = $this->font_path . $font_file . '.z';

            if (file_exists($font_path) && file_exists($z_path) && filesize($font_path) > 100) {
                $this->available_fonts[$font_file] = array(
                    'file' => $font_file,
                    'display_name' => $display_name,
                    'path' => $font_path,
                    'essential' => true
                );
            }
        }

        $this->essential_fonts_loaded = true;
    }

    /**
     * Lazy load all available fonts (only when needed)
     * Optimized for 200-font collection
     */
    private function discover_all_fonts()
    {
        // Skip if we only want essential fonts or already loaded
        if (!is_dir($this->font_path)) {
            return;
        }

        // Get font files - with optimized collection, we can load all safely
        $font_files = glob($this->font_path . '*.php');

        foreach ($font_files as $font_file) {
            $filename = basename($font_file, '.php');

            // Skip if already loaded or system files
            if (
                isset($this->available_fonts[$filename]) ||
                in_array($filename, array('symbol', 'zapfdingbats'))
            ) {
                continue;
            }

            // Validate font files
            $z_file = $this->font_path . $filename . '.z';
            $php_size = filesize($font_file);

            if (!file_exists($z_file) || $php_size < 100) {
                continue;
            }

            // Get human-readable font name
            $display_name = $this->get_font_display_name($filename);

            $this->available_fonts[$filename] = array(
                'file' => $filename,
                'display_name' => $display_name,
                'path' => $font_file,
                'essential' => false
            );
        }

        // Sort by display name
        uasort($this->available_fonts, function ($a, $b) {
            return strcmp($a['display_name'], $b['display_name']);
        });
    }

    /**
     * Convert font filename to human-readable display name
     */
    private function get_font_display_name($filename)
    {
        // Handle common font name patterns
        $name_mappings = array(
            'helvetica' => 'Helvetica',
            'helveticab' => 'Helvetica Bold',
            'helveticabi' => 'Helvetica Bold Italic',
            'helveticai' => 'Helvetica Italic',
            'times' => 'Times',
            'timesb' => 'Times Bold',
            'timesbi' => 'Times Bold Italic',
            'timesi' => 'Times Italic',
            'cour' => 'Courier',
            'courierb' => 'Courier Bold',
            'courierbi' => 'Courier Bold Italic',
            'courieri' => 'Courier Italic',
            'courier' => 'Courier',
            'garmond' => 'Garamond',
            'georgia' => 'Georgia'
        );

        $filename_lower = strtolower($filename);

        if (isset($name_mappings[$filename_lower])) {
            return $name_mappings[$filename_lower];
        }

        // Clean up filename for display
        $display_name = str_replace(array('-', '_'), ' ', $filename);
        $display_name = ucwords($display_name);

        return $display_name;
    }

    /**
     * Get all available fonts (with lazy loading option)
     * Optimized for 200-font collection
     */
    public function get_available_fonts($load_all = false)
    {
        if ($load_all && count($this->available_fonts) <= 25) {
            // Load all fonts since we now have only 200 optimized fonts
            $this->discover_all_fonts();
        }
        return $this->available_fonts;
    }

    /**
     * Get only essential fonts (fast, memory-efficient)
     */
    public function get_essential_fonts()
    {
        $essential = array();
        foreach ($this->available_fonts as $key => $font) {
            if (isset($font['essential']) && $font['essential']) {
                $essential[$key] = $font;
            }
        }
        return $essential;
    }

    /**
     * Get font names for dropdown/select options
     * Optimized for 200-font collection
     */
    public function get_font_options($include_all = false)
    {
        $fonts_to_use = $include_all ? $this->get_available_fonts(true) : $this->get_essential_fonts();

        $options = array();
        foreach ($fonts_to_use as $font_key => $font_data) {
            $options[$font_key] = $font_data['display_name'];
        }

        return $options;
    }

    /**
     * Add font to PDF instance
     */
    public function add_font_to_pdf($pdf, $font_name, $style = '', $size = 12)
    {
        if (!isset($this->available_fonts[$font_name])) {
            // Fallback to default font
            $font_name = 'helvetica';
            if (!isset($this->available_fonts[$font_name])) {
                // Ultimate fallback — Helvetica is always built into FPDF
                $pdf->SetFont('Helvetica', $style, $size);
                return 'Helvetica';
            }
        }

        $font_data = $this->available_fonts[$font_name];

        // Double-check font files exist before using
        $php_file = $this->font_path . $font_data['file'] . '.php';
        $z_file = $this->font_path . $font_data['file'] . '.z';

        if (!file_exists($php_file) || !file_exists($z_file)) {
            error_log("FontManager: Font files missing for $font_name, falling back to Helvetica");
            $pdf->SetFont('Helvetica', $style, $size);
            return 'Helvetica';
        }

        // Check for unsupported TrueTypeUnicode
        // This prevents "FPDF error: Unsupported font type: TrueTypeUnicode"
        $font_content = file_get_contents($php_file, false, null, 0, 100);
        if ($font_content !== false && strpos($font_content, "'TrueTypeUnicode'") !== false) {
            error_log("FontManager: Unsupported TrueTypeUnicode font detected ($font_name). Falling back to Helvetica to prevent crash.");
            $pdf->SetFont('Helvetica', $style, $size);
            return 'Helvetica';
        }

        try {
            // Add the font if not already added
            $pdf->AddFont($font_data['display_name'], $style, $font_data['file'] . '.php');
            $pdf->SetFont($font_data['display_name'], $style, $size);
            return $font_data['display_name'];
        } catch (\Throwable $e) {
            unset($this->available_fonts[$font_name]);  // don't retry this broken font
            error_log('Font loading error: ' . $e->getMessage());
            $pdf->SetFont('Helvetica', $style, $size);
            return 'Helvetica';
        }
    }

    /**
     * Get font file path for a given font name
     */
    public function get_font_file_path($font_name)
    {
        if (isset($this->available_fonts[$font_name])) {
            return $this->available_fonts[$font_name]['path'];
        }
        return null;
    }

    /**
     * Check if a font exists
     */
    public function font_exists($font_name)
    {
        return isset($this->available_fonts[$font_name]);
    }

    /**
     * Get display name for a font
     */
    public function get_font_display_name_by_key($font_name)
    {
        if (isset($this->available_fonts[$font_name])) {
            return $this->available_fonts[$font_name]['display_name'];
        }
        return $font_name;
    }

    /**
     * Refresh font list (useful after adding new fonts)
     */
    public function refresh_fonts($essential_only = true)
    {
        $this->available_fonts = array();
        if ($essential_only) {
            $this->load_essential_fonts();
        } else {
            $this->load_essential_fonts();
            $this->discover_all_fonts();
        }
    }

    /**
     * Check if we're in memory-safe mode
     */
    public function is_memory_safe_mode()
    {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = certificate_generator_convert_to_bytes($memory_limit);

        // If memory limit is less than 128MB, use memory-safe mode
        return $memory_limit_bytes < (128 * 1024 * 1024);
    }



    /**
     * Get default font
     */
    public function get_default_font()
    {
        if (!empty($this->available_fonts)) {
            $first_font = reset($this->available_fonts);
            return $first_font['file'];
        }
        return 'helvetica';
    }

    /**
     * Monitor memory usage and clear cache if needed
     */
    private function monitor_memory_usage()
    {
        if (!function_exists('memory_get_usage')) {
            return;
        }

        $current_memory = memory_get_usage(true) / 1024 / 1024; // Convert to MB

        if ($current_memory > $this->memory_usage_threshold) {
            $this->clear_font_cache();

            // Log memory optimization
            if (function_exists('error_log')) {
                error_log(sprintf(
                    'Certificate Generator: Memory optimization triggered at %.2f MB, cache cleared',
                    $current_memory
                ));
            }
        }
    }

    /**
     * Clear font cache to free memory
     */
    public function clear_font_cache()
    {
        $this->font_cache = array();

        // Keep only essential fonts in memory
        $essential_font_keys = array(
            'Arial',
            'helvetica',
            'times',
            'timesb',
            'courier',
            'opensans',
            'lato',
            'pacifico'
        );

        $filtered_fonts = array();
        foreach ($this->available_fonts as $key => $font) {
            if (in_array($key, $essential_font_keys)) {
                $filtered_fonts[$key] = $font;
            }
        }

        $this->available_fonts = $filtered_fonts;
    }

    /**
     * Get memory usage statistics
     */
    public function get_memory_stats()
    {
        $stats = array(
            'current_usage_mb' => 0,
            'cache_size' => count($this->font_cache),
            'fonts_loaded' => count($this->available_fonts),
            'memory_limit' => ini_get('memory_limit')
        );

        if (function_exists('memory_get_usage')) {
            $stats['current_usage_mb'] = round(memory_get_usage(true) / 1024 / 1024, 2);
        }

        return $stats;
    }

    /**
     * Optimize font loading for performance
     */
    public function optimize_for_performance()
    {
        // Clear any unnecessary font data
        $this->clear_font_cache();

        // Load only the most commonly used fonts
        $priority_fonts = array('Arial', 'helvetica', 'times', 'opensans');

        foreach ($priority_fonts as $font) {
            if (!isset($this->available_fonts[$font])) {
                $font_path = $this->font_path . $font . '.php';
                if (file_exists($font_path)) {
                    $this->available_fonts[$font] = array(
                        'file' => $font,
                        'name' => $this->generate_display_name($font),
                        'loaded_at' => time()
                    );
                }
            }
        }

        return true;
    }
}
?>