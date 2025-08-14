<?php

if (!defined('ABSPATH')) {
    exit;
}

class CertificateGenerator_FontManager {
    
    private static $instance = null;
    private $font_path;
    private $available_fonts = array();
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->font_path = plugin_dir_path(__FILE__) . 'fpdf/font/';
        $this->discover_fonts();
    }
    
    /**
     * Automatically discover all available fonts in the font directory
     */
    private function discover_fonts() {
        $this->available_fonts = array();
        
        if (!is_dir($this->font_path)) {
            return;
        }
        
        $font_files = glob($this->font_path . '*.php');
        
        foreach ($font_files as $font_file) {
            $filename = basename($font_file, '.php');
            
            // Skip system files
            if (in_array($filename, array('symbol', 'zapfdingbats'))) {
                continue;
            }
            
            // Validate font files - check if both .php and .z files exist
            $z_file = $this->font_path . $filename . '.z';
            $php_size = filesize($font_file);
            
            if (!file_exists($z_file) || $php_size < 100) {
                error_log("FontManager: Skipping broken font: $filename (missing .z file or invalid .php)");
                continue;
            }
            
            // Get human-readable font name
            $display_name = $this->get_font_display_name($filename);
            
            $this->available_fonts[$filename] = array(
                'file' => $filename,
                'display_name' => $display_name,
                'path' => $font_file
            );
        }
        
        // Sort by display name
        uasort($this->available_fonts, function($a, $b) {
            return strcmp($a['display_name'], $b['display_name']);
        });
    }
    
    /**
     * Convert font filename to human-readable display name
     */
    private function get_font_display_name($filename) {
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
     * Get all available fonts
     */
    public function get_available_fonts() {
        return $this->available_fonts;
    }
    
    /**
     * Get font names for dropdown/select options
     */
    public function get_font_options() {
        $options = array();
        foreach ($this->available_fonts as $font_key => $font_data) {
            $options[$font_key] = $font_data['display_name'];
        }
        return $options;
    }
    
    /**
     * Add font to PDF instance
     */
    public function add_font_to_pdf($pdf, $font_name, $style = '', $size = 12) {
        if (!isset($this->available_fonts[$font_name])) {
            // Fallback to default font
            $font_name = 'helvetica';
            if (!isset($this->available_fonts[$font_name])) {
                // Ultimate fallback
                $pdf->SetFont('Arial', $style, $size);
                return 'Arial';
            }
        }
        
        $font_data = $this->available_fonts[$font_name];
        
        // Double-check font files exist before using
        $php_file = $this->font_path . $font_data['file'] . '.php';
        $z_file = $this->font_path . $font_data['file'] . '.z';
        
        if (!file_exists($php_file) || !file_exists($z_file)) {
            error_log("FontManager: Font files missing for $font_name, falling back to Arial");
            $pdf->SetFont('Arial', $style, $size);
            return 'Arial';
        }
        
        try {
            // Add the font if not already added
            $pdf->AddFont($font_data['display_name'], $style, $font_data['file'] . '.php');
            $pdf->SetFont($font_data['display_name'], $style, $size);
            return $font_data['display_name'];
        } catch (Exception $e) {
            // Log error and fallback
            error_log('Font loading error: ' . $e->getMessage());
            $pdf->SetFont('Arial', $style, $size);
            return 'Arial';
        }
    }
    
    /**
     * Get font file path for a given font name
     */
    public function get_font_file_path($font_name) {
        if (isset($this->available_fonts[$font_name])) {
            return $this->available_fonts[$font_name]['path'];
        }
        return null;
    }
    
    /**
     * Check if a font exists
     */
    public function font_exists($font_name) {
        return isset($this->available_fonts[$font_name]);
    }
    
    /**
     * Get display name for a font
     */
    public function get_font_display_name_by_key($font_name) {
        if (isset($this->available_fonts[$font_name])) {
            return $this->available_fonts[$font_name]['display_name'];
        }
        return $font_name;
    }
    
    /**
     * Refresh font list (useful after adding new fonts)
     */
    public function refresh_fonts() {
        $this->discover_fonts();
    }
    
    /**
     * Get default font
     */
    public function get_default_font() {
        if (!empty($this->available_fonts)) {
            $first_font = reset($this->available_fonts);
            return $first_font['file'];
        }
        return 'helvetica';
    }
}