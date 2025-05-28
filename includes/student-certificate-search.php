<?php

// Include FPDF library (add this library in your plugin directory)
require_once __DIR__ . '/fpdf/fpdf.php';

// Debug logging function
function log_debug($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Certificate Generator Debug] ' . $message);
    }
}

// Function to convert hex color to RGB string
function hex2rgb_str($hex) {
    $hex = str_replace('#', '', $hex);
    
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    
    return "$r, $g, $b";
}

// Extend FPDF with Circle method for visual debugging
class FPDF_Debug extends FPDF {
    // Constructor to initialize properties
    function __construct($orientation='P', $unit='mm', $size='A4') {
        // Initialize extgstates as an array
        $this->extgstates = array();
        
        // Call parent constructor
        parent::__construct($orientation, $unit, $size);
    }
    // Method to draw a circle
    function Circle($x, $y, $r, $style='D') {
        $this->Ellipse($x, $y, $r, $r, $style);
    }
    
    // Method to draw an ellipse
    function Ellipse($x, $y, $rx, $ry, $style='D') {
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
            
        $lx=4/3*(M_SQRT2-1)*$rx;
        $ly=4/3*(M_SQRT2-1)*$ry;
        $k=$this->k;
        $h=$this->h;
        
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx)*$k, ($h-$y)*$k,
            ($x+$rx)*$k, ($h-($y-$ly))*$k,
            ($x+$lx)*$k, ($h-($y-$ry))*$k,
            $x*$k, ($h-($y-$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$lx)*$k, ($h-($y-$ry))*$k,
            ($x-$rx)*$k, ($h-($y-$ly))*$k,
            ($x-$rx)*$k, ($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$rx)*$k, ($h-($y+$ly))*$k,
            ($x-$lx)*$k, ($h-($y+$ry))*$k,
            $x*$k, ($h-($y+$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x+$lx)*$k, ($h-($y+$ry))*$k,
            ($x+$rx)*$k, ($h-($y+$ly))*$k,
            ($x+$rx)*$k, ($h-$y)*$k,
            $op));
    }
    
    // Method to set transparency/alpha
    function SetAlpha($alpha, $bm='Normal') {
        // Set alpha for stroking and non-stroking operations
        $gs = $this->AddExtGState(array('ca'=>$alpha, 'CA'=>$alpha, 'BM'=>'/'.$bm));
        $this->SetExtGState($gs);
    }
    
    // Add an ExtGState
    function AddExtGState($parms) {
        $n = count($this->extgstates) + 1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }
    
    // Set an ExtGState
    function SetExtGState($gs) {
        $this->_out(sprintf('/GS%d gs', $gs));
    }
    
    // Initialize extgstates array if needed
    function _enddoc() {
        if(!isset($this->extgstates) || count($this->extgstates)==0)
            $this->extgstates = array();
        parent::_enddoc();
    }
    
    // Add ExtGState resources to the PDF
    function _putextgstates() {
        for($i=1; $i<=count($this->extgstates); $i++) {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            $parms = $this->extgstates[$i]['parms'];
            $this->_put(sprintf('/ca %.3F', $parms['ca']));
            $this->_put(sprintf('/CA %.3F', $parms['CA']));
            $this->_put('/BM '.$parms['BM']);
            $this->_put('>>');
            $this->_put('endobj');
        }
    }
    
    // Override _putresourcedict to include ExtGState resources
    function _putresourcedict() {
        parent::_putresourcedict();
        $this->_put('/ExtGState <<');
        foreach($this->extgstates as $k=>$extgstate)
            $this->_put('/GS'.$k.' '.$extgstate['n'].' 0 R');
        $this->_put('>>');
    }
    
    // Override _putresources to include ExtGState resources
    function _putresources() {
        $this->_putextgstates();
        parent::_putresources();
    }
}

// Function to generate the certificate PDF with custom data
function validate_template_url($template_url) {
    // Step 1: Check if the URL is valid
    if (!$template_url || !filter_var($template_url, FILTER_VALIDATE_URL)) {
        error_log("Invalid or empty template URL: $template_url");
        return '<p style="color:red;">Template URL is invalid or missing. Please contact the administrator.</p>';
    }

    // Step 2: Check if the URL is accessible (follow redirects)
    $response = wp_remote_get($template_url, [
        'timeout' => 10,
        'redirection' => 5 // Follow up to 5 redirects
    ]);
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Error accessing template URL: $template_url - $error_message");
        return '<p style="color:red;">Template URL is inaccessible: ' . esc_html($error_message) . '. Please check the URL and try again.</p>';
    }

    // Step 3: Check the final HTTP status code after redirects
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log("Template URL returned status code $status_code: $template_url");
        
        // Provide more specific error messages for common status codes
        $status_messages = [
            301 => 'Template URL is being redirected',
            302 => 'Template URL is being redirected',
            403 => 'Access to the template was forbidden',
            404 => 'Template file was not found',
            500 => 'Server error occurred while accessing template'
        ];
        
        $message = isset($status_messages[$status_code]) 
            ? $status_messages[$status_code] 
            : "Template URL is inaccessible (HTTP $status_code)";
            
        return '<p style="color:red;">' . esc_html($message) . '. Please check the file and try again.</p>';
    }

    // Step 4: Validate the content type
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    if (strpos($content_type, 'image/') !== 0) {
        error_log("Invalid content type for template URL: $template_url - Content-Type: $content_type");
        return '<p style="color:red;">Template URL is not a valid image file. Please upload a valid image (PNG, JPG, etc.).</p>';
    }

    return true; // URL is valid and accessible
}

function generate_certificate_pdf_with_data($post_data) {
    $debug_mode = true; // Toggle debugging logs 
    $visual_debug = true; // Toggle visual debugging elements on the PDF
    
    // Fetch the certificate template based on certificate_type
    $certificate_query = new WP_Query([
        'post_type' => 'certificates',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'certificate_type',
                'value' => $post_data['certificate_type'],
                'compare' => '='
            ]
        ]
    ]);

    // Debugging: Log the query arguments
    error_log('Query Args: ' . print_r($certificate_query->query_vars, true));
    error_log('Post Data: ' . print_r($post_data, true));

    if (!$certificate_query->have_posts()) {
        error_log('No matching certificate template found.');
        echo '<div class="notice notice-error"><p>Unable to open the file. Please check the file and try again.</p></div>';
        return false; // No matching certificate template found
    }

    $certificate_template = $certificate_query->posts[0];
    $template_url = get_post_meta($certificate_template->ID, 'template_url', true);
    log_debug('Template URL: ' . $template_url);

    // Fetch dynamic fields
    $template_orientation = get_post_meta($certificate_template->ID, 'template_orientation', true) ?: 'portrait';
    $font_size = get_post_meta($certificate_template->ID, 'font_size', true) ?: 12;
    $font_color = get_post_meta($certificate_template->ID, 'font_color', true) ?: '#000000';
    $font_style = get_post_meta($certificate_template->ID, 'font_style', true) ?: 'Arial';
    
    // Create font mapping for FPDF compatibility
    $font_mapping = [
        'times new roman' => 'times',
        'times' => 'times',
        'arial' => 'helvetica',
        'helvetica' => 'helvetica',
        'courier new' => 'courier',
        'courier' => 'courier'
    ];
    
    // Normalize font name for FPDF compatibility
    $normalized_font = strtolower($font_style);
    $pdf_font = isset($font_mapping[$normalized_font]) ? $font_mapping[$normalized_font] : 'helvetica';
    log_debug("Font requested: {$font_style}, Normalized: {$normalized_font}, Using: {$pdf_font}");

    // Validate template
    $validation_result = validate_template_url($template_url);
    if ($validation_result !== true) {
        echo $validation_result;
        return;
    }
    
    // Dynamically determine fields from post_data (excluding certificate_type)
    $fields = array_keys($post_data);
    $fields = array_filter($fields, function($field) {
        return $field !== 'certificate_type';
    });
    
    error_log('Dynamic Fields: ' . print_r($fields, true));
    
    // Fetch field positions dynamically from the certificate template
    $field_positions = [];
    foreach ($fields as $index => $field) {
        $field_key = $index + 1; // Field keys are 1-indexed in the form
        $field_positions[$field] = [
            'x' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_x", true),
            'y' => get_post_meta($certificate_template->ID, "field_{$field_key}_position_y", true),
            'width' => get_post_meta($certificate_template->ID, "field_{$field_key}_width", true) ?: '100', // Default width
            'align' => trim(get_post_meta($certificate_template->ID, "field_{$field_key}_alignment", true)) ?: 'C', // Default center alignment, ensure trimmed value
            'visible' => get_post_meta($certificate_template->ID, "field_{$field_key}_visible", true) ?: '0' 
        ];
        
        // Ensure alignment value is valid and properly formatted
        $field_positions[$field]['align'] = strtoupper(trim($field_positions[$field]['align']));
        
        // Handle case where alignment might be stored with quotes
        if ($field_positions[$field]['align'] === "'R'" || $field_positions[$field]['align'] === '"R"') {
            $field_positions[$field]['align'] = 'R';
            error_log("Fixed quoted right alignment value for {$field}");
        } else if ($field_positions[$field]['align'] === "'L'" || $field_positions[$field]['align'] === '"L"') {
            $field_positions[$field]['align'] = 'L';
            error_log("Fixed quoted left alignment value for {$field}");
        } else if ($field_positions[$field]['align'] === "'C'" || $field_positions[$field]['align'] === '"C"') {
            $field_positions[$field]['align'] = 'C';
            error_log("Fixed quoted center alignment value for {$field}");
        }
        
        // Final validation
        if (!in_array($field_positions[$field]['align'], ['L', 'C', 'R'])) {
            $field_positions[$field]['align'] = 'C'; // Default to center if still invalid
            error_log("Invalid alignment value for {$field}, defaulting to Center");
        }
        
        // Debugging: Log field positions
        error_log("Field position for {$field}: " . print_r($field_positions[$field], true));
    }

    // Generate PDF
    $pdf = new FPDF_Debug($template_orientation, 'mm', 'A4');
    $pdf->AddPage();

    // Set text color
    $font_color_rgb = sscanf($font_color, "#%02x%02x%02x");
    $pdf->SetTextColor($font_color_rgb[0], $font_color_rgb[1], $font_color_rgb[2]);
    
    // Set default font using the normalized font name
    try {
        $pdf->SetFont($pdf_font, '', $font_size);
    } catch (Exception $e) {
        // If font still fails, use helvetica as ultimate fallback
        log_debug('Font error: ' . $e->getMessage() . '. Using fallback font.');
        $pdf->SetFont('helvetica', '', $font_size);
    }

    // Add template background
    $pdf->Image($template_url, 0, 0, $template_orientation === 'landscape' ? 297 : 210, $template_orientation === 'landscape' ? 210 : 297);
    
    // Add debug information legend if visual debugging is enabled
    if ($visual_debug) {
        // Store current color values before changing them
        $current_color_r = $font_color_rgb[0];
        $current_color_g = $font_color_rgb[1];
        $current_color_b = $font_color_rgb[2];
        
        // Set up debug info section
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetAlpha(0.8); // Make background slightly transparent
        
        // Draw debug info box
        $box_x = 5;
        $box_y = 5;
        $box_width = 80;
        $box_height = 40;
        $pdf->Rect($box_x, $box_y, $box_width, $box_height, 'F');
        
        // Add title
        $pdf->SetXY($box_x + 2, $box_y + 3);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($box_width - 4, 5, 'CERTIFICATE DEBUG MODE', 0, 1, 'L');
        
        // Add legend items
        $pdf->SetFont('helvetica', '', 6);
        
        // Red box - field boundaries
        $pdf->SetXY($box_x + 2, $box_y + 10);
        $pdf->SetDrawColor(255, 0, 0);
        $pdf->Rect($box_x + 2, $box_y + 10, 3, 3, 'D');
        $pdf->SetXY($box_x + 6, $box_y + 10);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell($box_width - 8, 3, 'Red Box: Field Boundaries', 0, 1, 'L');
        
        // Green dot - original position
        $pdf->SetXY($box_x + 2, $box_y + 15);
        $pdf->SetDrawColor(0, 255, 0);
        $pdf->SetFillColor(0, 255, 0);
        $pdf->Circle($box_x + 3.5, $box_y + 16.5, 0.5, 'F');
        $pdf->SetXY($box_x + 6, $box_y + 15);
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell($box_width - 8, 3, 'Green Dot: Original Position', 0, 1, 'L');
        
        // Blue dot - adjusted position
        $pdf->SetXY($box_x + 2, $box_y + 20);
        $pdf->SetDrawColor(0, 0, 255);
        $pdf->SetFillColor(0, 0, 255);
        $pdf->Circle($box_x + 3.5, $box_y + 21.5, 0.5, 'F');
        $pdf->SetXY($box_x + 6, $box_y + 20);
        $pdf->SetTextColor(0, 0, 255);
        $pdf->Cell($box_width - 8, 3, 'Blue Dot: Adjusted Position', 0, 1, 'L');
        
        // Version info
        $pdf->SetXY($box_x + 2, $box_y + 30);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($box_width - 4, 3, 'Certificate Generator Debug v1.0', 0, 1, 'L');
        
        // Restore original settings
        $pdf->SetAlpha(1.0);
        $pdf->SetFont($pdf_font, '', $font_size);
        $pdf->SetTextColor($current_color_r, $current_color_g, $current_color_b);
    }

    // **Word Wrap and Alignment Logic**
    foreach ($field_positions as $field => $position) {
        if ($position['visible'] == '0') {
                log_debug("Skipping hidden field: {$field}");
            continue; // Skip hidden fields
        }
        if (!empty($post_data[$field]) && is_numeric($position['x']) && is_numeric($position['y'])) {
                
                // **Set Font**
                $pdf->SetFont($font_style, 'B', $font_size);
                $text = mb_convert_encoding($post_data[$field], 'ISO-8859-1', 'UTF-8');

                // **Text Width Calculation**
                $char_width = $font_size * 0.2; // Approximate character width in mm
                $text_width = mb_strlen($text) * $char_width;
                $field_width = $position['width'];

                // **Word Wrap Logic**
                if ($text_width > $field_width) {
                    // Calculate approximate chars per line based on field width
                    $chars_per_line = floor($field_width / $char_width);
                    
                    // Split text into words
                    $words = explode(' ', $text);
                    $lines = [];
                    $current_line = '';
                    
                    // Build lines word by word
                    foreach ($words as $word) {
                        $test_line = $current_line . ($current_line ? ' ' : '') . $word;
                        if (mb_strlen($test_line) <= $chars_per_line) {
                            $current_line = $test_line;
                        } else {
                            if ($current_line) {
                                $lines[] = $current_line;
                            }
                            $current_line = $word;
                        }
                    }
                    
                    // Add the last line
                    if ($current_line) {
                        $lines[] = $current_line;
                    }
                    
                    // Calculate line height based on font size
                    $line_height = $font_size * 0.5; // Adjust as needed
                    
                    // Draw each line
                    foreach ($lines as $i => $line) {
                        $line_text = mb_convert_encoding($line, 'ISO-8859-1', 'UTF-8');
                        $line_width = mb_strlen($line_text) * $char_width;
                        
                        // **Determine Adjusted X Based on Alignment**
                        error_log("Alignment for {$field} (multiline): '{$position['align']}'");
                        
                        switch ($position['align']) {
                            case 'L': // Left Align
                                $adjusted_x = $position['x'] - $field_width / 2; // Start from left
                                error_log("Using LEFT alignment for {$field}");
                                break;
                            case 'R': // Right Align
                                $adjusted_x = $position['x'] + ($field_width/2) - $line_width; // Calculate from right edge by subtracting text width
                                error_log("Using RIGHT alignment for {$field} with adjusted_x: {$adjusted_x}");
                                break;
                            case 'C': // Center Align
                            default:
                                $adjusted_x = $position['x'] - $line_width / 2; // Center the text
                                error_log("Using CENTER alignment for {$field} (default or explicit)");
                                break;
                        }
                        
                        $adjusted_y = $position['y'] + ($i * $line_height);
                        $pdf->Text($adjusted_x, $adjusted_y, $line_text);
                        
                        // Draw dots for debugging (only for first and last line)
                        if ($i === 0 || $i === count($lines) - 1) {
                            $pdf->SetFillColor(0, 0, 255); // Blue for Adjusted Position
                            $pdf->Rect($adjusted_x, $adjusted_y, 1, 1, 'F');
                        }
                    }
                    
                    // Draw border around the entire text area
                    $total_height = count($lines) * $line_height;
                    $pdf->SetDrawColor(255, 0, 0); // Red for field border
                    $pdf->Rect($position['x'] - $field_width / 2, $position['y'] - $font_size, 
                              $field_width, $total_height + $font_size, 'D');
                    
                    // Draw original position dot
                    $pdf->SetFillColor(0, 255, 0); // Green for Original Position
                    $pdf->Rect($position['x'], $position['y'], 1, 1, 'F');
                    
                } else {
                    // **Single Line Text Logic**
                    // **Determine Adjusted X Based on Alignment**
                    // Log the alignment value for debugging
                    error_log("Alignment for {$field} (single line): '{$position['align']}'");
                    
                    switch ($position['align']) {
                        case 'L': // Left Align
                            $adjusted_x = $position['x'] - $field_width / 2; // Start from left
                            error_log("Using LEFT alignment for {$field} (single line)");
                            break;
                        case 'R': // Right Align
                            $adjusted_x = $position['x'] + $field_width / 2 - $text_width; // End at right
                            error_log("Using RIGHT alignment for {$field} (single line), adjusted_x: {$adjusted_x}");
                            break;
                        case 'C': // Center Align
                        default:
                            $adjusted_x = $position['x'] - $text_width / 2; // Center the text
                            error_log("Using CENTER alignment for {$field} (single line, default or explicit)");
                            break;
                    }

                    // **Set Position and Output Text**
                    // For right alignment, we need to make sure we're using the adjusted x position
                    $pdf->SetXY($adjusted_x, $position['y']);
                    $pdf->Text($adjusted_x, $position['y'], $text);

                    // **Debugging: Draw a Border around the Field**
                    $pdf->SetDrawColor(255, 0, 0); // Red for field border
                    $pdf->Rect($position['x'] - $field_width / 2, $position['y'] - $font_size, $field_width, $font_size + 2); // Draw border around the field

                    // **Debugging: Draw a Small Dot at Position**
                    $pdf->SetFillColor(0, 255, 0); // Green for Original Position
                    $pdf->Rect($position['x'], $position['y'], 1, 1, 'F'); // Original Position
                    $pdf->SetFillColor(0, 0, 255); // Blue for Adjusted Position
                    $pdf->Rect($adjusted_x, $position['y'], 1, 1, 'F'); // Adjusted Position
                }
                
            } else {
                error_log("Invalid position for $field: X={$position['x']}, Y={$position['y']}");
            }
        }
    

    // Output PDF
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['path'] . "/certificate_preview.pdf";
    $pdf->Output('F', $pdf_path);

    return $upload_dir['url'] . "/certificate_preview.pdf";
}

function generate_certificate_pdf($post_id, $fields) {
    // Get the certificate type from the current post
    $certificate_type = get_post_meta($post_id, 'certificate_type', true);
    
    // Start error logging
    error_log("Starting certificate generation for post ID: $post_id");
    error_log("Requested fields: " . print_r($fields, true));

    if (!$certificate_type) {
        $error_msg = 'Certificate type is missing for post ID: ' . $post_id;
        error_log($error_msg);
        echo '<div class="notice notice-error"><p>' . esc_html($error_msg) . '</p></div>';
        return false;
    }

    // Fetch certificate template details with improved matching
    // First try exact match, then try case-insensitive match
    $certificate_query = new WP_Query([
        'post_type' => 'certificates',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'certificate_type',
                'value' => $certificate_type,
                'compare' => '='
            ]
        ]
    ]);
    
    // If no exact match, try case-insensitive match
    if (!$certificate_query->have_posts()) {

        $certificate_query = new WP_Query([
            'post_type' => 'certificates',
            'posts_per_page' => -1,
        ]);
        
        // Manually check for case-insensitive match
        $matched_template = null;
        if ($certificate_query->have_posts()) {
            while ($certificate_query->have_posts()) {
                $certificate_query->the_post();
                $template_type = get_post_meta(get_the_ID(), 'certificate_type', true);
                
                if (strtolower(trim($template_type)) === strtolower(trim($certificate_type))) {
                    $matched_template = get_the_ID();

                    break;
                }
            }
            wp_reset_postdata();
            
            // If we found a match, create a new query with just that post
            if ($matched_template) {
                $certificate_query = new WP_Query([
                    'post_type' => 'certificates',
                    'p' => $matched_template,
                    'posts_per_page' => 1,
                ]);
            }
        }
    }



    if (!$certificate_query->have_posts()) {
        $error_msg = 'No certificate template found for type: ' . $certificate_type;
        error_log($error_msg);
        echo '<div class="certificate-request-form" style="margin: 20px auto; max-width: 600px; padding: 20px; background: #f8f9fa; border-radius: 5px;">';
        echo '<p style="margin-bottom: 15px;">' . esc_html($error_msg) . '</p>';
        echo '<p>Please kindly fill the form below if you don\'t find your certificate. You will receive a copy within 7 days.</p>';
        echo '</div>';
        return false; // No matching certificate template found
    }

    $certificate_template = $certificate_query->posts[0];
    $template_url = get_post_meta($certificate_template->ID, 'template_url', true);

    if (empty($template_url)) {
        $error_msg = 'Template URL is missing for certificate template ID: ' . $certificate_template->ID;
        error_log($error_msg);
        echo '<div class="notice notice-error"><p>' . esc_html($error_msg) . '</p></div>';
        return false;
    }

    // Fetch dynamic fields
    $template_orientation = get_post_meta($certificate_template->ID, 'template_orientation', true) ?: 'portrait';
    $font_size = get_post_meta($certificate_template->ID, 'font_size', true) ?: 12;
    $font_color = get_post_meta($certificate_template->ID, 'font_color', true) ?: '#000000';
    $font_style = get_post_meta($certificate_template->ID, 'font_style', true) ?: 'Arial';



    $validation_result = validate_template_url($template_url);
    if ($validation_result !== true) {

        echo $validation_result;
        return false;
    }

    // Fetch field positions dynamically from the certificate template
    $field_positions = [];
    $missing_positions = [];
    
    foreach ($fields as $index => $field) {
        $field_key = $index + 1; // Field keys are 1-indexed in the form
        
        $position_x = get_post_meta($certificate_template->ID, "field_{$field_key}_position_x", true);
        $position_y = get_post_meta($certificate_template->ID, "field_{$field_key}_position_y", true);
        
        // Check if positions are set
        if (empty($position_x) || empty($position_y)) {
            $missing_positions[] = $field;

            continue;
        }
        
        $field_positions[$field] = [
            'x' => $position_x,
            'y' => $position_y,
            'width' => get_post_meta($certificate_template->ID, "field_{$field_key}_width", true) ?: '100', // Default width
            'align' => get_post_meta($certificate_template->ID, "field_{$field_key}_alignment", true) ?: 'C', // Default center alignment
            'visible' => get_post_meta($certificate_template->ID, "field_{$field_key}_visible", true) ?: '0' // Default visible
        ];
        
        // Debugging: Log field positions
        error_log("Field position for {$field}: " . print_r($field_positions[$field], true));
    }
    
    // Check if we have any field positions
    if (empty($field_positions)) {
        $missing_fields = implode(', ', $missing_positions);
        error_log("Certificate generation failed: No valid field positions found. Missing positions for: " . $missing_fields);
        echo '<div class="notice notice-error"><p>Certificate template is missing field positions for: ' . esc_html($missing_fields) . '</p></div>';
        return false;
    }

    // Fetch post data dynamically
    $post_data = [];
    $missing_data = [];
    
    foreach ($fields as $field) {
        $value = get_post_meta($post_id, $field, true);
        if (empty($value)) {
            $missing_data[] = $field;
            error_log("Missing data for field {$field}");
            continue;
        }
        
        // Format issue_date to dd-mm-yyyy if it exists
        if ($field === 'issue_date') {
            // Try to parse the date
            $date_obj = DateTime::createFromFormat('Y-m-d', $value);
            if ($date_obj) {
                $value = $date_obj->format('d-m-Y');
            } else {
                // Try other common formats
                $date_obj = date_create($value);
                if ($date_obj) {
                    $value = date_format($date_obj, 'd-m-Y');
                }
                // If all parsing fails, keep the original value
            }
            error_log("Formatted issue_date: " . $value);
        }
        
        $post_data[$field] = $value;
    }
    
    // Check if we have any post data
    if (empty($post_data)) {
        $missing_fields = implode(', ', $missing_data);
        error_log("Certificate generation failed: No valid post data found. Missing data for: " . $missing_fields);
        echo '<div class="notice notice-error"><p>Certificate data is missing for: ' . esc_html($missing_fields) . '</p></div>';
        return false;
    }
    
    // Debug: Log post data
    error_log("Post data for PDF: " . print_r($post_data, true));

    try {
        // Generate PDF
        $pdf = new FPDF($template_orientation, 'mm', 'A4');
        $pdf->AddPage();

        // Add font support
        switch ($font_style) {
            case 'Times New Roman':
                $pdf->AddFont('Times New Roman', 'B', 'Times New Roman Bold.php');
                $pdf->AddFont('Times New Roman', '', 'Times New Roman.php');
                break;
            case 'Helvetica':
                $pdf->AddFont('Helvetica', 'B', 'helveticab.php');
                $pdf->AddFont('Helvetica', '', 'helvetica.php');
                break;
            case 'Courier New':
                $pdf->AddFont('Courier New', 'B', 'cour.php');
                $pdf->AddFont('Courier New', '', 'cour.php');
                break;
            case 'Verdana':
                $pdf->AddFont('Verdana', 'B', 'Verdanab.php');
                $pdf->AddFont('Verdana', '', 'Verdana.php');
                break;
            case 'Palatino':
                $pdf->AddFont('Palatino', 'B', 'Palatino Font.php');
                $pdf->AddFont('Palatino', '', 'Palatino Font.php');
                break;
            case 'Garamond':
                $pdf->AddFont('Garamond', 'B', 'garmond.php');
                $pdf->AddFont('Garamond', '', 'Garamond Regular.php');
                break;
            default:
                // Use default font
                break;
        }

        // Set font color
        $font_color_rgb = sscanf($font_color, "#%02x%02x%02x");
        $pdf->SetTextColor($font_color_rgb[0], $font_color_rgb[1], $font_color_rgb[2]);

        // Add template background
        try {
            $pdf->Image($template_url, 0, 0, $template_orientation === 'landscape' ? 297 : 210, $template_orientation === 'landscape' ? 210 : 297);
        } catch (Exception $e) {
            error_log("Error adding template image: " . $e->getMessage());
            echo '<div class="notice notice-error"><p>Error loading template image: ' . esc_html($e->getMessage()) . '</p></div>';
            return false;
        }

        // **Word Wrap and Alignment Logic**
        foreach ($field_positions as $field => $position) {
            if (!isset($post_data[$field])) {
                error_log("Field $field exists in positions but not in post data");
                continue;
            }
            
            // Skip if field is not visible
            if ($position['visible'] == '0') {
                log_debug("Skipping hidden field: {$field}");
                continue;
            }
            
            if (!empty($post_data[$field])) {
                if (is_numeric($position['x']) && is_numeric($position['y'])) {
                    
                    // **Set Font**
                    $pdf->SetFont($font_style, 'B', $font_size);
                    $text = mb_convert_encoding($post_data[$field], 'ISO-8859-1', 'UTF-8');

                    // **Text Width Calculation**
                    $char_width = $font_size * 0.2; // Approximate character width in mm
                    $text_width = mb_strlen($text) * $char_width;
                    $field_width = $position['width'];

                    // **Word Wrap Logic**
                    if ($text_width > $field_width) {
                        // Calculate approximate chars per line based on field width
                        $chars_per_line = floor($field_width / $char_width);
                        
                        // Split text into words
                        $words = explode(' ', $text);
                        $lines = [];
                        $current_line = '';
                        
                        // Build lines word by word
                        foreach ($words as $word) {
                            $test_line = $current_line . ($current_line ? ' ' : '') . $word;
                            if (mb_strlen($test_line) <= $chars_per_line) {
                                $current_line = $test_line;
                            } else {
                                if ($current_line) {
                                    $lines[] = $current_line;
                                }
                                $current_line = $word;
                            }
                        }
                        
                        // Add the last line
                        if ($current_line) {
                            $lines[] = $current_line;
                        }
                        
                        // Calculate line height based on font size
                        $line_height = $font_size * 0.5; // Adjust as needed
                        
                        // Draw each line
                        foreach ($lines as $i => $line) {
                            $line_text = mb_convert_encoding($line, 'ISO-8859-1', 'UTF-8');
                            $line_width = mb_strlen($line_text) * $char_width;
                            
                            // **Determine Adjusted X Based on Alignment**
                            switch ($position['align']) {
                                case 'L': // Left Align
                                    $adjusted_x = $position['x'] - $field_width / 2; // Start from left
                                    break;
                                case 'R': // Right Align
                                    $adjusted_x = $position['x'] + $field_width / 2 - $line_width; // End at right
                                    break;
                                case 'C': // Center Align
                                default:
                                    $adjusted_x = $position['x'] - $line_width / 2; // Center the text
                                    break;
                            }
                            
                            $adjusted_y = $position['y'] + ($i * $line_height);
                            $pdf->Text($adjusted_x, $adjusted_y, $line_text);
                            
                        }
                        
                        
                    } else {
                        // **Single Line Text Logic**
                        // **Determine Adjusted X Based on Alignment**
                        switch ($position['align']) {
                            case 'L': // Left Align
                                $adjusted_x = $position['x'] - $field_width / 2; // Start from left
                                break;
                            case 'R': // Right Align
                                $adjusted_x = $position['x'] + $field_width / 2 - $text_width; // End at right
                                break;
                            case 'C': // Center Align
                            default:
                                $adjusted_x = $position['x'] - $text_width / 2; // Center the text
                                break;
                        }

                        // **Set Position and Output Text**
                        $pdf->SetXY($adjusted_x, $position['y']);
                        $pdf->Text($adjusted_x, $position['y'], $text);

                    }
                    
                } else {
                    error_log("Invalid position for $field: X={$position['x']}, Y={$position['y']}");
                }
            }
        }

        // Output PDF
        $upload_dir = wp_upload_dir();
        // Ensure directory exists and is writable
        $upload_path = $upload_dir['path'];
        if (!file_exists($upload_path)) {
            wp_mkdir_p($upload_path);
            error_log("Created upload directory: $upload_path");
        }
        
        // Use consistent path format with correct directory separators
        $pdf_path = rtrim($upload_path, '/\\') . DIRECTORY_SEPARATOR . "certificate_$post_id.pdf";
        
        // Debug: Log upload directory and file path
        error_log("Upload directory: " . print_r($upload_dir, true));
        error_log("PDF file path: $pdf_path");
        
        // Check if directory is writable
        if (!is_writable($upload_path)) {
            error_log("Upload directory is not writable: " . $upload_path);
            echo '<div class="notice notice-error"><p>Upload directory is not writable. Please check permissions.</p></div>';
            return false;
        }
        
        // Output the PDF file
        try {
            $pdf->Output('F', $pdf_path);
        } catch (Exception $e) {
            $error_msg = "PDF generation failed for post ID $post_id: " . $e->getMessage();
            error_log($error_msg);
            error_log("Template URL: " . $template_url);
            error_log("PDF Path: " . $pdf_path);
            error_log("Field Positions: " . print_r($field_positions, true));
            error_log("Post Data: " . print_r($post_data, true));
            echo '<div class="notice notice-error"><p>' . esc_html($error_msg) . '</p></div>';
            return false;
        }
        
        // Check if file was created successfully
        if (!file_exists($pdf_path)) {
            $error_msg = "PDF file not created at: $pdf_path";
            error_log($error_msg);
            error_log("Upload directory permissions: " . (is_writable($upload_path) ? 'writable' : 'not writable'));
            error_log("Free disk space: " . disk_free_space($upload_path) . " bytes");
            echo '<div class="notice notice-error"><p>' . esc_html($error_msg) . '</p></div>';
            return false;
        }
        
        // Generate consistent file URL that matches the file path structure
        $file_url = $upload_dir['url'] . "/certificate_$post_id.pdf";
        error_log("PDF generated successfully. URL: $file_url");
        
        // Store the actual file path in a post meta for easier retrieval
        update_post_meta($post_id, 'certificate_file_path', $pdf_path);
        update_post_meta($post_id, 'certificate_file_url', $file_url);
        
        return $file_url;
    } catch (Exception $e) {
        error_log("Exception during PDF generation: " . $e->getMessage());
        echo '<div class="notice notice-error"><p>Error generating PDF: ' . esc_html($e->getMessage()) . '</p></div>';
        return false;
    }
}

// Shortcode to search for teacher certificates
add_shortcode('teacher_search', 'teacher_search_shortcode');
function teacher_search_shortcode(){
    if (isset($_GET['teacher_email'])) {
        $email = sanitize_email($_GET['teacher_email']);

        $args = [
            'post_type' => 'teachers',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => 'email',
                    'value' => $email,
                    'compare' => '=',
                ],
            ],
        ];
        

        $query = new WP_Query($args);
        $certificates_data = [];
        $error_count = 0;
        $certificates_to_generate_bg = []; // For background processing

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $teacher_name = get_post_meta($post_id, 'teacher_name', true) ?: get_the_title();
                $school_name = get_post_meta($post_id, 'school_name', true);
                $certificate_type = get_post_meta($post_id, 'certificate_type', true);
                $issue_date = get_post_meta($post_id, 'issue_date', true);
                $template_url = get_post_meta($post_id, 'template_url', true);

                $fields = ['teacher_name', 'school_name', 'issue_date'];
                $file_url = generate_certificate_pdf($post_id, $fields);

                if ($file_url) {
                    $certificates_data[] = [
                        'title' => get_the_title(),
                        'school' => $school_name,
                        'type' => $certificate_type,
                        'date' => $issue_date,
                        'url' => $file_url,
                        'post_id' => $post_id
                    ];
                } else {
                    $error_count++;
                    $certificates_to_generate_bg[] = [
                        'post_id' => $post_id,
                        'student_name' => $teacher_name, // Use teacher_name here
                        'school_name' => $school_name,
                        'certificate_type' => $certificate_type,
                        'issue_date' => $issue_date,
                        'template_url' => $template_url,
                        'post_type' => 'teachers'
                    ];
                }
            }
            wp_reset_postdata();
        }

        // Get styling options with defaults
        $options = get_option('certificate_generator_settings_email');
        $bg_color = $options['bg_color'] ?? '#f4f7f600';
        $card_bg = $options['card_bg'] ?? '#ffffff';
        $title_color = $options['title_color'] ?? '#2c3e50';
        $text_color = $options['text_color'] ?? '#7f8c8d';
        $btn_start = $options['btn_start'] ?? '#3498db';
        $btn_end = $options['btn_end'] ?? '#2980b9';
        $border_radius = $options['border_radius'] ?? '12';
        $font_family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

        if (!empty($certificates_data)) {
            $cert_count = count($certificates_data);
            $output = '<div class="certificates-container" style="background-color: ' . $bg_color . '; padding: ' . ($cert_count === 1 ? '50px 20px' : '30px 15px') . '; font-family: ' . $font_family . ';">';
            
            // Title
            $output .= '<h2 style="text-align: center; color: ' . $title_color . '; margin-bottom: ' . ($cert_count === 1 ? '40px' : '30px') . '; font-size: ' . ($cert_count === 1 ? '32px' : '28px') . '; font-weight: 700;">';
            $output .= sprintf(_n('%d Certificate Found', '%d Certificates Found', $cert_count, 'certificate-generator'), $cert_count);
            $output .= '</h2>';

            // Grid layout for certificates
            $grid_columns = $cert_count === 1 ? '1fr' : 'repeat(auto-fit, minmax(300px, 1fr))';
            $grid_gap = $cert_count === 1 ? '0' : '25px';
            $output .= '<div class="certificates-grid" style="display: grid; grid-template-columns: ' . $grid_columns . '; gap: ' . $grid_gap . '; max-width: ' . ($cert_count === 1 ? '650px' : '1200px') . '; margin: 0 auto;">';

            foreach ($certificates_data as $certificate) {
                $output .= '<div class="certificate-card" style="background: ' . $card_bg . '; border-radius: ' . $border_radius . 'px; padding: ' . ($cert_count === 1 ? '40px' : '30px') . '; box-shadow: 0 8px 25px rgba(0,0,0,0.07); transition: transform 0.3s ease, box-shadow 0.3s ease; display: flex; flex-direction: column; justify-content: space-between;" onmouseover="this.style.transform=\'translateY(-5px)\'; this.style.boxShadow=\'0 12px 30px rgba(0,0,0,0.1)\';" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 8px 25px rgba(0,0,0,0.07)\';">';
                
                // Certificate Title (Teacher Name)
                $output .= '<h3 style="color: ' . $title_color . '; font-size: ' . ($cert_count === 1 ? '26px' : '22px') . '; margin: 0 0 ' . ($cert_count === 1 ? '25px' : '20px') . '; font-weight: 600; line-height: 1.3;">' . esc_html($certificate['title']) . '</h3>';
                
                // Certificate Details
                $output .= '<div style="margin-bottom: ' . ($cert_count === 1 ? '30px' : '25px') . '; display: flex; flex-direction: column; gap: ' . ($cert_count === 1 ? '15px' : '12px') . ';">';
                
                // School Name
                $output .= '<div style="display: flex; align-items: center;">' . 
                          '<div style="min-width: 24px; color: ' . $btn_start . '; margin-right: ' . ($cert_count === 1 ? '15px' : '10px') . ';">' . 
                          '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' . 
                          'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . 
                          '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>' . 
                          '<polyline points="9 22 9 12 15 12 15 22"></polyline>' . 
                          '</svg></div>' . 
                          '<div><span style="font-weight: ' . ($cert_count === 1 ? '600' : '500') . '; color: ' . $title_color . ';">School:</span> ' . 
                          '<span style="color: ' . $text_color . '; font-size: ' . ($cert_count === 1 ? '15px' : '14px') . ';">' . esc_html($certificate['school']) . '</span></div>' . 
                          '</div>';
                
                // Certificate Type
                $output .= '<div style="display: flex; align-items: center;">' . 
                          '<div style="min-width: 24px; color: ' . $btn_start . '; margin-right: ' . ($cert_count === 1 ? '15px' : '10px') . ';">' . 
                          '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' . 
                          'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . 
                          '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>' . 
                          '<polyline points="13 2 13 9 20 9"></polyline>' . 
                          '</svg></div>' . 
                          '<div><span style="font-weight: ' . ($cert_count === 1 ? '600' : '500') . '; color: ' . $title_color . ';">Certificate:</span> ' . 
                          '<span style="color: ' . $text_color . '; font-size: ' . ($cert_count === 1 ? '15px' : '14px') . ';">' . esc_html($certificate['type']) . '</span></div>' . 
                          '</div>';
                
                // Issue Date
                $output .= '<div style="display: flex; align-items: center;">' . 
                          '<div style="min-width: 24px; color: ' . $btn_start . '; margin-right: ' . ($cert_count === 1 ? '15px' : '10px') . ';">' . 
                          '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' . 
                          'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . 
                          '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>' . 
                          '<line x1="16" y1="2" x2="16" y2="6"></line>' . 
                          '<line x1="8" y1="2" x2="8" y2="6"></line>' . 
                          '<line x1="3" y1="10" x2="21" y2="10"></line>' . 
                          '</svg></div>' . 
                          '<div><span style="font-weight: ' . ($cert_count === 1 ? '600' : '500') . '; color: ' . $title_color . ';">Issued On:</span> ' . 
                          '<span style="color: ' . $text_color . '; font-size: ' . ($cert_count === 1 ? '15px' : '14px') . ';">' . esc_html($certificate['date']) . '</span></div>' . 
                          '</div>';
                
                $output .= '</div>'; // End of certificate details
                
                // Download button
                $btn_width = $cert_count === 1 ? '60%' : '80%';
                $btn_padding = $cert_count === 1 ? '16px 24px' : '14px 20px';
                $btn_font_size = $cert_count === 1 ? '16px' : '14px';
                $btn_container_style = $cert_count === 1 ? 'text-align: center; margin-top: 30px;' : 'margin-top: auto;'; // Pushes button to bottom for multi-card
                
                $output .= '<div style="' . $btn_container_style . '">';
                $output .= '<a href="' . esc_url($certificate['url']) . '" target="_blank" ' .
                          'style="display: inline-block; width: ' . $btn_width . '; padding: ' . $btn_padding . '; ' . 
                          'background: linear-gradient(135deg, ' . $btn_start . ', ' . $btn_end . '); ' . 
                          'color: white; text-decoration: none; border-radius: ' . $border_radius . 'px; ' . 
                          'font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); ' . 
                          'text-align: center; text-transform: uppercase; letter-spacing: 0.5px; font-size: ' . $btn_font_size . ';" ' . 
                          'onmouseover="this.style.transform=\'translateY(-2px)\'; ' . 
                          'this.style.boxShadow=\'0 6px 15px rgba(0, 0, 0, 0.15)\';" ' . 
                          'onmouseout="this.style.transform=\'translateY(0)\'; ' . 
                          'this.style.boxShadow=\'0 4px 10px rgba(0, 0, 0, 0.1)\';">'
                          . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' . 
                          'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' . 
                          'stroke-linejoin="round" style="vertical-align: -3px; margin-right: 8px;">' . 
                          '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>' . 
                          '<polyline points="7 10 12 15 17 10"></polyline>' . 
                          '<line x1="12" y1="15" x2="12" y2="3"></line>' . 
                          '</svg>Download Certificate</a>';
                $output .= '</div>';
                
                $output .= '</div>'; // End of card
            }
            $output .= '</div>'; // End of grid
            $output .= '</div>'; // End of container

        } 
        else { // No certificates found or all had errors
            $output = '<div class="certificate-not-found" style="max-width: 600px; margin: 40px auto; font-family: ' . $font_family . ';">';
            $output .= '<div style="padding: 40px; background: ' . $card_bg . '; border-radius: ' . $border_radius . 'px; ' .
                      'box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); text-align: center;">';
            $output .= '<div style="margin-bottom: 25px; animation: pulse 2s infinite;">' .
                      '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" ' .
                      'stroke="' . $btn_start . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' .
                      '<circle cx="12" cy="12" r="10"></circle>' .
                      '<line x1="12" y1="8" x2="12" y2="12"></line>' .
                      '<line x1="12" y1="16" x2="12.01" y2="16"></line>' .
                      '</svg></div>';
            $output .= '<h2 style="color: ' . $title_color . '; font-size: 28px; margin: 0 0 15px; font-weight: 700;">' .
                      __('No Certificate Found', 'certificate-generator') . '</h2>';
            $output .= '<p style="color: ' . $text_color . '; margin-bottom: 30px; line-height: 1.6; font-size: 16px;">' .
                      __('We couldn\'t find any certificates matching the email address you provided for a teacher.', 'certificate-generator') . '</p>';
            // ... (rest of the no-found message, similar to student search but adapted for teachers)
            $output .= '<p style="color: ' . $text_color . '; margin-bottom: 20px; line-height: 1.6;">' .
                      __('If you are unable to find the certificate here, please drop a request email to us on:', 'certificate-generator') . ' ' .
                      certificate_generator_get_contact_email() . ' ' .
                      __('to resend it over email.', 'certificate-generator') . '</p>';
            $output .= '<div style="margin-bottom: 20px;">';
            $output .= '<p style="font-weight: bold; margin: 0 0 8px; color: ' . $title_color . '; font-size: 16px;">' .
                       __('Please include following details in your email:', 'certificate-generator') . '</p>';
            $output .= '<ul style="margin: 0; padding-left: 20px; list-style-type: disc; color: ' . $text_color . ';">';
            $output .= '<li style="margin-bottom: 8px;">' . __('Registered Email Id (Teacher)', 'certificate-generator') . '</li>';
            $output .= '<li style="margin-bottom: 8px;">' . __('Teacher Name', 'certificate-generator') . '</li>';
            $output .= '<li style="margin-bottom: 8px;">' . __('School Name', 'certificate-generator') . '</li>';
            $output .= '<li style="margin-bottom: 0;">' . __('Certificate Type', 'certificate-generator') . '</li>';
            $output .= '</ul></div>';

            $output .= '<div style="background: linear-gradient(to right, rgba(' . hex2rgb_str($btn_start) . ', 0.05), ' .
                      'rgba(' . hex2rgb_str($btn_end) . ', 0.05)); border-radius: ' . $border_radius . 'px; ' .
                      'padding: 25px; margin: 25px 0; border-left: 4px solid ' . $btn_start . ';">';
            $output .= '<div style="display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">' .
                      '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" ' .
                      'stroke="' . $btn_start . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' .
                      'style="margin-right: 10px;">' .
                      '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>' .
                      '</svg>' .
                      '<h3 style="color: ' . $title_color . '; margin: 0; font-size: 18px;">' .
                      __('Need Help Finding Your Certificate?', 'certificate-generator') . '</h3></div>';
            $output .= '<p style="color: ' . $text_color . '; margin-bottom: 20px; line-height: 1.6;">' .
                      __('If you believe this is an error or need assistance, please contact our support team.', 'certificate-generator') . '</p>';
            $output .= '<a href="mailto:' . certificate_generator_get_contact_email() . '" ' .
                      'style="display: inline-flex; align-items: center; padding: 12px 24px; ' .
                      'background: linear-gradient(135deg, ' . $btn_start . ', ' . $btn_end . '); ' .
                      'color: white; text-decoration: none; border-radius: ' . $border_radius . 'px; ' .
                      'font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);" ' .
                      'onmouseover="this.style.transform=\'translateY(-2px)\'; ' .
                      'this.style.boxShadow=\'0 8px 20px rgba(0, 0, 0, 0.15)\'" ' .
                      'onmouseout="this.style.transform=\'translateY(0)\'; ' .
                      'this.style.boxShadow=\'0 4px 15px rgba(0, 0, 0, 0.1)\'">' .
                      '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" ' .
                      'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' .
                      'stroke-linejoin="round" style="margin-right: 8px;">' .
                      '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>' .
                      '<polyline points="22,6 12,13 2,6"></polyline>' .
                      '</svg>' . __('Contact Support', 'certificate-generator') . '</a>';
            $output .= '</div>';
            $output .= '<a href="' . esc_url(remove_query_arg('teacher_email')) . '" ' .
                      'style="display: inline-flex; align-items: center; margin-top: 10px; ' .
                      'color: ' . $text_color . '; text-decoration: none; font-weight: 500; transition: color 0.3s ease;" ' .
                      'onmouseover="this.style.color=\'' . $btn_start . '\'" ' .
                      'onmouseout="this.style.color=\'' . $text_color . '\'">' .
                      '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' .
                      'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' .
                      'stroke-linejoin="round" style="margin-right: 6px;">' .
                      '<line x1="19" y1="12" x2="5" y2="12"></line>' .
                      '<polyline points="12 19 5 12 12 5"></polyline>' .
                      '</svg>' . __('Back to Search', 'certificate-generator') . '</a>';
            $output .= '</div>'; // End of error card
            $output .= '<style>
                @keyframes pulse {
                    0% { transform: scale(1); opacity: 1; }
                    50% { transform: scale(1.05); opacity: 0.8; }
                    100% { transform: scale(1); opacity: 1; }
                }
            </style>';
            $output .= '</div>'; // End container
        }

        // Trigger background processing for certificates that failed direct generation
        if (!empty($certificates_to_generate_bg)) {
            // Ensure the action name matches the one hooked in your plugin
            wp_schedule_single_event(time(), 'generate_certificates_background_teachers', [$certificates_to_generate_bg]);
        }
            
            // If any certificates had errors, show the error message
            if ($error_count > 0) {
                // Create a well-structured and user-friendly 'Certificate Generation Error' message
                $output = '<div style="max-width: 600px; margin: 30px auto; padding: 25px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); text-align: center;">';
                
                // Header section with icon and title
                $output .= '<div style="margin-bottom: 20px;">';
                $output .= '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d32f2f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
                $output .= '<h2 style="color: #d32f2f; font-size: 22px; margin: 15px 0 5px;">Certificate Generation Error</h2>';
                $output .= '<p style="color: #666; font-size: 16px; margin: 0 0 15px;">We found your record but couldn\'t generate your certificate due to a technical issue.</p>';
                $output .= '</div>';
                
                // Contact information section
                $output .= '<div style="margin-bottom: 25px; padding: 0 15px;">';
                $output .= '<p style="color: #555; font-size: 15px; line-height: 1.5; margin-bottom: 15px;">Please contact us at the email below and we\'ll resolve this issue for you:</p>';
                $output .= '<p style="color: #555; font-size: 15px; line-height: 1.5;">Email: <a href="mailto:support@example.com" style="color: #0073aa; font-weight: 500; text-decoration: none;">support@example.com</a></p>';
                $output .= '</div>';
                
                // Required information box
                $output .= '<div style="background: #f8f8f8; border-radius: 8px; padding: 20px; margin: 0 auto; text-align: left; border-left: 4px solid #0073aa;">';
                $output .= '<p style="font-weight: bold; margin: 0 0 12px; color: #333; font-size: 16px;">Please include these details in your email:</p>';
                $output .= '<ul style="margin: 0; padding-left: 20px; list-style-type: disc; color: #555;">';
                $output .= '<li style="margin-bottom: 8px;">Your Full Name</li>';
                $output .= '<li style="margin-bottom: 8px;">School Name</li>';
                $output .= '<li style="margin-bottom: 0;">Certificate Type</li>';
                $output .= '</ul>';
                $output .= '</div>';
                
                $output .= '</div>';
            }
    } else {
        // Get styling options with defaults
        $options = get_option('certificate_generator_settings_email');
        $title_color = $options['title_color'] ?? '#2c3e50';
        $text_color = $options['text_color'] ?? '#7f8c8d';
        $btn_start = $options['btn_start'] ?? '#3498db';
        $btn_end = $options['btn_end'] ?? '#2980b9';
        $border_radius = $options['border_radius'] ?? '12';
        $font_family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

        // Modern search form with consistent styling
        $output = '<div class="certificate-search-container" style="max-width: 600px; margin: 40px auto; font-family: ' . $font_family . ';">';
        
        // Form with enhanced styling
        $output .= '<form method="get" style="padding: 35px; background: #ffffff; border-radius: ' . $border_radius . 'px; ' .
                  'box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); transition: all 0.3s ease;">';
        
        // Heading with icon
        $output .= '<div style="text-align: center; margin-bottom: 30px;">' .
                  '<h2 style="color: ' . $title_color . '; margin: 0; font-size: 28px; font-weight: 700;">' .
                  __('Teacher Certificate Lookup', 'certificate-generator') . '</h2>' . // Changed title
                  '<p style="color: ' . $text_color . '; margin-top: 10px; font-size: 16px;">' .
                  __('Enter teacher email to find their certificates', 'certificate-generator') . '</p>' . // Changed placeholder text
                  '</div>';
        
        // Input field with floating label effect
        $output .= '<div style="position: relative; margin-bottom: 30px;">';
        $output .= '<label for="teacher_email" style="position: absolute; left: 16px; top: 18px; ' .
                  'color: ' . $text_color . '; font-size: 16px; transition: all 0.2s ease; pointer-events: none;">' .
                  __('Teacher Email Address', 'certificate-generator') . '</label>'; // Changed label
        $output .= '<input type="email" id="teacher_email" name="teacher_email" required ' . // Changed id and name
                  'placeholder="" style="width: 100%; padding: 18px 16px; padding-top: 26px; padding-bottom: 10px; ' .
                  'background: #f8f9fa; border: 2px solid #eaeaea; border-radius: ' . $border_radius . 'px; ' .
                  'font-size: 16px; transition: all 0.3s ease; outline: none; box-sizing: border-box;" ' .
                  'onfocus="this.style.borderColor=\'' . $btn_start . '\'; ' .
                  'this.previousElementSibling.style.top=\'8px\'; ' .
                  'this.previousElementSibling.style.fontSize=\'12px\'; ' .
                  'this.previousElementSibling.style.color=\'' . $btn_start . '\'" ' .
                  'onblur="if(this.value===\'\'){this.style.borderColor=\'#eaeaea\'; ' .
                  'this.previousElementSibling.style.top=\'18px\'; ' .
                  'this.previousElementSibling.style.fontSize=\'16px\'; ' .
                  'this.previousElementSibling.style.color=\'' . $text_color . '\'} ' .
                  'else{this.style.borderColor=\'#eaeaea\'; ' .
                  'this.previousElementSibling.style.color=\'' . $text_color . '\';}">';
        $output .= '</div>';
        
        // Submit button with icon and hover effect
        $output .= '<button type="submit" style="display: flex; align-items: center; justify-content: center; ' .
                  'width: 100%; padding: 16px; background: linear-gradient(135deg, ' . $btn_start . ', ' . $btn_end . '); ' .
                  'color: #fff; border: none; border-radius: ' . $border_radius . 'px; font-size: 16px; ' .
                  'font-weight: 600; cursor: pointer; text-align: center; transition: all 0.3s ease; ' .
                  'box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); transform: translateY(0);" ' .
                  'onmouseover="this.style.transform=\'translateY(-2px)\'; ' .
                  'this.style.boxShadow=\'0 8px 20px rgba(0, 0, 0, 0.15)\'" ' .
                  'onmouseout="this.style.transform=\'translateY(0)\'; ' .
                  'this.style.boxShadow=\'0 4px 15px rgba(0, 0, 0, 0.1)\'">' .
                  '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" ' .
                  'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' .
                  'stroke-linejoin="round" style="margin-right: 8px;">' .
                  '<circle cx="11" cy="11" r="8"></circle>' .
                  '<line x1="21" y1="21" x2="16.65" y2="16.65"></line>' .
                  '</svg>' . __('Search Certificates', 'certificate-generator') . '</button>';
        
        $output .= '</form>';
        
        // Add help text
        $output .= '<div style="text-align: center; margin-top: 20px; padding: 0 15px;">';
        $output .= '<p style="color: ' . $text_color . '; font-size: 14px;">' .
                  __('Enter the email address of the teacher to find their certificates.', 'certificate-generator') . // Changed help text
                  '</p>';
        $output .= '</div>';
        
        $output .= '</div>'; // End container
    }
    return $output;
}

// Shortcode to search for school certificates
add_shortcode('school_search', 'school_search_shortcode');

function school_search_shortcode() {
    // Check if input parameters are provided
    if (isset($_GET['school_name']) && isset($_GET['place'])) {
        $school_name_query = sanitize_text_field($_GET['school_name']);
        $place_query = sanitize_text_field($_GET['place']);

        $args = [
            'post_type' => 'schools',

            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'place',
                    'value' => $place_query,
                    'compare' => 'LIKE',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'school_name',
                        'value' => $school_name_query,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key' => 'school_abbreviation',
                        'value' => $school_name_query,
                        'compare' => 'LIKE',
                    ],
                ],
            ],
        ];

        $query = new WP_Query($args);
        $certificates_data = [];
        $certificates_to_generate_bg = [];
        $error_count = 0;
        $processed_certificates = []; // Track processed certificates to avoid duplicates

        if ($query->have_posts()) {
            $total_certificates_found = $query->post_count;

            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $current_school_name = get_post_meta($post_id, 'school_name', true);
                $current_place = get_post_meta($post_id, 'place', true);
                $current_issue_date = get_post_meta($post_id, 'issue_date', true);
                $current_cert_type = get_post_meta($post_id, 'certificate_type', true); // Assuming schools might have a cert type

                // Create fields to pass with actual values, not just field names
                $fields_to_pass = [
                    'school_name' => $current_school_name,
                    'place' => $current_place, 
                    'issue_date' => $current_issue_date,
                    'certificate_type' => $current_cert_type,
                ];
                $fields=['school_name','place','issue_date'];

                // Try to generate PDF directly - pass the actual values, not just field names
                $pdf_result = generate_certificate_pdf($post_id, $fields);

                if (is_wp_error($pdf_result)) {
                    $error_count++;
                    // Log error or add to a list for background processing
                    $certificates_to_generate_bg[] = ['post_id' => $post_id, 'fields' => $fields_to_pass];
                    // Log detailed error for direct generation failure
                    $log_file = WP_CONTENT_DIR . '/certificate_generator_school_direct_errors.log';
                    $timestamp = date('Y-m-d H:i:s');
                    $log_message = "[$timestamp] Direct generation error for school certificate (Post ID: $post_id): {$pdf_result->get_error_message()}\n";
                    error_log($log_message, 3, $log_file);
                } elseif ($pdf_result && !empty($pdf_result)) {
                    // Sanitize file names for ZIP with improved uniqueness
                    $s_name_sanitized = sanitize_file_name($current_school_name);
                    $place_sanitized = sanitize_file_name($current_place);
                    $cert_type_sanitized = !empty($current_cert_type) ? sanitize_file_name($current_cert_type) : 'standard';
                    $timestamp = current_time('timestamp');
                    
                    // Create a truly unique filename with more identifiers
                    $unique_id = $post_id . '-' . substr(md5($s_name_sanitized . $place_sanitized . $timestamp), 0, 10);
                    $unique_filename = $s_name_sanitized . '-' . $place_sanitized . '-' . $cert_type_sanitized . '-' . $unique_id . '.pdf';
                    
                    // Convert URL to file path
                    $upload_dir = wp_upload_dir();
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $pdf_result);
                    
                    // Only add if this certificate hasn't been processed yet
                    $certificate_key = md5($file_path);
                    if (!isset($processed_certificates[$certificate_key])) {
                        // Create certificate data entry with proper URL and path
                        $certificates_data[$post_id] = [
                            'url' => $pdf_result,
                            'path' => $file_path,
                            'filename' => $unique_filename,
                            'post_id' => $post_id,
                            'school_name' => $current_school_name,
                            'place' => $current_place,
                            'issue_date' => $current_issue_date,
                            'certificate_type' => $current_cert_type,
                        ];
                        
                        // Mark this certificate as processed
                        $processed_certificates[$certificate_key] = true;
                    }
                } else {
                    $error_count++;
                    $certificates_to_generate_bg[] = ['post_id' => $post_id, 'fields' => $fields_to_pass];
                }
            }
            wp_reset_postdata();

            // Prepare ZIP download if multiple certificates exist and were successfully generated
            $bulk_download_link = '';
            if (count($certificates_data) > 1 && class_exists('ZipArchive')) { // Changed from >3 to >1 for schools
                $upload_dir = wp_upload_dir();
                $timestamp = current_time('timestamp');
                $school_name_sanitized = sanitize_file_name($school_name_query);
                $place_sanitized = sanitize_file_name($place_query);
                $zip_filename = 'school_certificates_' . $school_name_sanitized . '_' . $place_sanitized . '_' . $timestamp . '.zip';
                $zip_path = $upload_dir['basedir'] . '/' . $zip_filename;
                $zip_url = $upload_dir['baseurl'] . '/' . $zip_filename;

                // Remove old ZIP file if it exists
                if (file_exists($zip_path)) {
                    @unlink($zip_path);
                }

                $zip = new ZipArchive();
                if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                    $zip_success = true;
                    foreach ($certificates_data as $certificate) {
                        if (file_exists($certificate['path'])) {
                            // Add file to ZIP with unique name to prevent overwriting
                            if (!$zip->addFile($certificate['path'], $certificate['filename'])) {
                                error_log('Failed to add school certificate to ZIP: ' . $certificate['path']);
                                $zip_success = false;
                            }
                        } else {
                            error_log('Certificate file does not exist: ' . $certificate['path']);
                            $zip_success = false;
                        }
                    }
                    $zip->close();
                    
                    if ($zip_success && file_exists($zip_path)) {
                        // Styling for this button will be handled by plugin options later
                        $bulk_download_link = $zip_url; // Store URL for now
                    } else {
                        error_log('Failed to create ZIP file or ZIP file does not exist: ' . $zip_path);
                    }
                } else {
                    error_log('Failed to open ZIP file for writing: ' . $zip_path);
                }
            }

            // Get styling options from plugin settings
            $options = get_option('certificate_generator_settings_email');
            $card_bg = $options['card_bg'] ?? '#f9f9f9';
            $title_color = $options['title_color'] ?? '#2c3e50';
            $text_color = $options['text_color'] ?? '#7f8c8d';
            $btn_start = $options['btn_start'] ?? '#3498db';
            $btn_end = $options['btn_end'] ?? '#2980b9';
            $hover_effect = $options['hover_effect'] ?? '5'; // Default hover effect in px
            $border_radius = $options['border_radius'] ?? '12';
            $font_family = $options['font_family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

            $output = '<div class="certificate-results-container" style="max-width: 1200px; margin: 20px auto; font-family: ' . esc_attr($font_family) . ';">';
            
            // Results header
            $output .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
            $actual_certs_generated = count($certificates_data);
            $output .= '<h2 style="font-size: 24px; color: ' . esc_attr($title_color) . '; margin: 0; font-weight: 600;">' .
                      sprintf(
                          _n('Found %s Certificate for %s', 'Found %s Certificates for %s', $actual_certs_generated, 'certificate-generator'),
                          '<span style="color: ' . esc_attr($btn_start) . ';">' . $actual_certs_generated . '</span>',
                          esc_html($school_name_query)
                      ) . '</h2>';
            
            if ($bulk_download_link) {
                $output .= '<div><a href="' . esc_url($bulk_download_link) . '" ' .
                          'class="bulk-download-button" style="display: inline-block; padding: 12px 24px; ' .
                          'background: linear-gradient(to right, ' . esc_attr($btn_start) . ', ' . esc_attr($btn_end) . '); ' .
                          'color: white; text-decoration: none; border-radius: ' . esc_attr($border_radius) . 'px; font-weight: 600; ' .
                          'transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); text-align: center;" ' .
                          'onmouseover="this.style.transform=\'translateY(-2px) scale(1.02)\';this.style.boxShadow=\'0 6px 20px rgba(0,0,0,0.2)\'" ' .
                          'onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 15px rgba(0,0,0,0.1)\'">' .
                          '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>' .
                          __('Download All Certificates', 'certificate-generator') . ' (' . $actual_certs_generated . ')</a></div>';
            }
            $output .= '</div>'; // End of header

            // Grid container
            $cert_count_display = count($certificates_data);
            $grid_columns = $cert_count_display === 1 ? 'minmax(300px, 600px)' : 'repeat(auto-fit, minmax(300px, 1fr))';
            $grid_justify = $cert_count_display === 1 ? 'center' : 'stretch';

            $output .= '<div class="certificate-grid" style="display: grid; grid-template-columns: ' . esc_attr($grid_columns) . '; ' .
                      'gap: 25px; padding: ' . ($cert_count_display > 0 ? '25px' : '0') . '; background: ' . ($cert_count_display > 0 ? esc_attr($card_bg) : 'transparent') . '; border-radius: ' . esc_attr($border_radius) . 'px; ' .
                      'box-shadow: ' . ($cert_count_display > 0 ? '0 10px 30px rgba(0, 0, 0, 0.05)' : 'none') . '; justify-content: ' . esc_attr($grid_justify) . ';">';

            if (!empty($certificates_data)) {
                foreach ($certificates_data as $cert_id => $data) {
                    $output .= '<div class="certificate-card" style="background: #ffffff; border-radius: ' . esc_attr($border_radius) . 'px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s ease;" ' .
                               'onmouseover="this.style.transform=\'translateY(-' . esc_attr($hover_effect) . 'px)\';this.style.boxShadow=\'0 ' . (5 + (int)$hover_effect) . 'px ' . (15 + (int)$hover_effect * 2) . 'px rgba(0,0,0,0.12)\'" ' .
                               'onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 5px 15px rgba(0,0,0,0.08)\'">';
                    $output .= '<div>'; // Content wrapper
                    $output .= '<h3 style="font-size: 18px; color: ' . esc_attr($title_color) . '; margin-top: 0; margin-bottom: 12px; font-weight: 600;">' . esc_html($data['school_name']) . '</h3>';
                    
                    // School Icon and Name (already in h3)
                    // Place
                    $output .= '<p style="font-size: 14px; color: ' . esc_attr($text_color) . '; margin-bottom: 8px; display: flex; align-items: center;">' .
                               '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: ' . esc_attr($btn_start) . ';"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>' .
                               esc_html($data['place']) . '</p>';
                    
                    // Issue Date
                    $output .= '<p style="font-size: 14px; color: ' . esc_attr($text_color) . '; margin-bottom: 8px; display: flex; align-items: center;">' .
                               '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: ' . esc_attr($btn_start) . ';"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>' .
                               esc_html($data['issue_date']) . '</p>';

                    // Certificate Type (if available for schools)
                    if (!empty($data['certificate_type'])) {
                        $output .= '<p style="font-size: 14px; color: ' . esc_attr($text_color) . '; margin-bottom: 15px; display: flex; align-items: center;">' .
                                   '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: ' . esc_attr($btn_start) . ';"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>' .
                                   esc_html($data['certificate_type']) . '</p>';
                    } else {
                         $output .= '<p style="font-size: 14px; color: ' . esc_attr($text_color) . '; margin-bottom: 15px; display: flex; align-items: center;">&nbsp;</p>'; // Placeholder for consistent height if no cert type
                    }
                    $output .= '</div>'; // End Content wrapper

                    // Download Button
                    $output .= '<div style="margin-top: auto;">'; // Button wrapper for bottom alignment
                    $output .= '<a href="' . esc_url($data['url']) . '" target="_blank" ' .
                               'style="display: block; padding: 10px 15px; background: linear-gradient(to right, ' . esc_attr($btn_start) . ', ' . esc_attr($btn_end) . '); ' .
                               'color: white; text-decoration: none; border-radius: ' . esc_attr($border_radius) . 'px; font-weight: 600; text-align: center; ' .
                               'transition: all 0.3s ease; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" ' .
                               'onmouseover="this.style.transform=\'translateY(-2px) scale(1.02)\';this.style.boxShadow=\'0 6px 12px rgba(0,0,0,0.15)\'" ' .
                               'onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 8px rgba(0,0,0,0.1)\'">' .
                               '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 8px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>' .
                               __('Download Certificate', 'certificate-generator') . '</a>';
                    $output .= '</div>'; // End Button wrapper
                    $output .= '</div>'; // End certificate-card
                }
            }
            $output .= '</div>'; // End certificate-grid
            $output .= '</div>'; // End certificate-results-container
        // After processing all posts from the query or if query had no posts
        if (empty($certificates_data) && isset($school_name_query)) { // Ensure search was actually performed
            // This block handles cases where:
            // 1. Initial WP_Query found no matching school posts.
            // 2. WP_Query found posts, but all PDF generations failed (direct and none queued for BG).
            $options = get_option('certificate_generator_settings_email');
            // $card_bg = $options['card_bg'] ?? '#f9f9f9'; // Not directly used for error message background
            $title_color = $options['title_color'] ?? '#2c3e50';
            $text_color = $options['text_color'] ?? '#7f8c8d';
            $btn_start = $options['btn_start'] ?? '#3498db';
            $btn_end = $options['btn_end'] ?? '#2980b9';
            $border_radius = $options['border_radius'] ?? '12';
            $font_family = $options['font_family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
            $support_email = get_option('certificate_generator_support_email', get_option('admin_email'));

            // Initialize $output if it hasn't been (e.g. if $query->have_posts() was false)
            if (!isset($output)) $output = '';

            // If there were errors during generation attempts, but ultimately no certs were successfully generated
            // $total_certificates_found is defined if $query->have_posts() was true.
            // We need to ensure $school_name_query and $place_query are set from the $_GET params.
            if ($error_count > 0 && isset($total_certificates_found) && $total_certificates_found > 0) { 
                $output = '<div class="certificate-error-container" style="max-width: 700px; margin: 40px auto; padding: 30px; background: #ffffff; border-radius: ' . esc_attr($border_radius) . 'px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; font-family: ' . esc_attr($font_family) . ';">';
                $output .= '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($btn_end) . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px; animation: pulseWarn 2s infinite;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
                $output .= '<style>@keyframes pulseWarn { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.05); } }</style>';
                $output .= '<h2 style="color: ' . esc_attr($title_color) . '; font-size: 24px; margin-top: 0; margin-bottom: 15px; font-weight: 600;">' . __('Certificate Generation Issue', 'certificate-generator') . '</h2>';
                $output .= '<p style="color: ' . esc_attr($text_color) . '; font-size: 16px; line-height: 1.6; margin-bottom: 20px;">' . sprintf(__('We found records for %s in %s, but encountered an issue while generating the certificate(s). Some certificates might be processed in the background. If you don\'t receive them shortly, please contact support.', 'certificate-generator'), '<strong>' . esc_html($school_name_query) . '</strong>', '<strong>' . esc_html($place_query) . '</strong>') . '</p>';
                $output .= '<div style="text-align: left; margin-top: 20px; margin-bottom: 25px; padding: 15px; background-color: #f8f9fa; border-radius: ' . esc_attr($border_radius) . 'px; border: 1px solid #e9ecef;">';
                $output .= '<h4 style="color: ' . esc_attr($title_color) . '; margin-top:0; margin-bottom:10px; font-size: 16px; font-weight: 600;">' . __('Please provide the following when contacting support:', 'certificate-generator') . '</h4>';
                $output .= '<ul style="list-style-type: disc; margin-left: 20px; padding-left: 0; color: ' . esc_attr($text_color) . '; font-size: 14px; line-height: 1.6;">';
                $output .= '<li>' . __('School Name Searched:', 'certificate-generator') . ' <strong>' . esc_html($school_name_query) . '</strong></li>';
                $output .= '<li>' . __('Place Searched:', 'certificate-generator') . ' <strong>' . esc_html($place_query) . '</strong></li>';
                $output .= '<li>' . __('Approximate Date/Time of Search:', 'certificate-generator') . ' ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), current_time('timestamp')) . '</li>';
                $output .= '<li>' . __('Any other relevant details or error messages you noticed.', 'certificate-generator') . '</li>';
                $output .= '</ul>';
                $output .= '</div>';
            } else { // No school posts found by the query at all, or $query had posts but all failed silently (less likely with current logic)
                $output = '<div class="certificate-error-container" style="max-width: 700px; margin: 40px auto; padding: 30px; background: #ffffff; border-radius: ' . esc_attr($border_radius) . 'px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; font-family: ' . esc_attr($font_family) . ';">';
                $output .= '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="' . esc_attr($btn_start) . '" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 20px; animation: pulseInfo 2s infinite;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
                $output .= '<style>@keyframes pulseInfo { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.05); } }</style>';
                $output .= '<h2 style="color: ' . esc_attr($title_color) . '; font-size: 24px; margin-top: 0; margin-bottom: 15px; font-weight: 600;">' . __('No Certificates Found', 'certificate-generator') . '</h2>';
                $output .= '<p style="color: ' . esc_attr($text_color) . '; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">' . sprintf(__('We couldn\'t find any certificates matching %s in %s. Please double-check the school name and place, or try a different search.', 'certificate-generator'), '<strong>' . esc_html($school_name_query) . '</strong>', '<strong>' . esc_html($place_query) . '</strong>') . '</p>';
            }
            // Common part for both error messages (contact support, back to search)
            $output .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">';
            $output .= '<p style="color: ' . esc_attr($text_color) . '; font-size: 14px; margin-bottom: 15px;">' . __('If you believe this is an error or need assistance, please contact our support team.', 'certificate-generator') . '</p>';
            $output .= '<a href="mailto:' . esc_attr($support_email) . '" style="display: inline-block; padding: 10px 20px; background: linear-gradient(to right, ' . esc_attr($btn_start) . ', ' . esc_attr($btn_end) . '); color: white; text-decoration: none; border-radius: ' . esc_attr($border_radius) . 'px; font-weight: 600; margin-right: 10px; transition: all 0.3s ease; box-shadow: 0 4px 8px rgba(0,0,0,0.1);" onmouseover="this.style.transform=\'translateY(-2px) scale(1.02)\';this.style.boxShadow=\'0 6px 12px rgba(0,0,0,0.15)\'" onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 4px 8px rgba(0,0,0,0.1)\'">' . 
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 8px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>' .
                __('Contact Support', 'certificate-generator') . '</a>';
            $output .= '<a href="' . esc_url(get_permalink()) . '" style="display: inline-block; padding: 10px 20px; background: #f0f0f0; color: ' . esc_attr($text_color) . '; text-decoration: none; border-radius: ' . esc_attr($border_radius) . 'px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" onmouseover="this.style.background=\'#e0e0e0\';this.style.transform=\'translateY(-2px)\';this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.1)\'" onmouseout="this.style.background=\'#f0f0f0\';this.style.transform=\'translateY(0)\';this.style.boxShadow=\'0 2px 8px rgba(0,0,0,0.05)\'">' . 
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -3px; margin-right: 8px;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>' .
                __('Back to Search', 'certificate-generator') . '</a>';
            $output .= '</div>'; // End contact/back-to-search section
            $output .= '</div>'; // End certificate-error-container
        }

        // Handle background processing for certificates that failed direct generation
        // Ensure $certificates_to_generate_bg is defined and not empty
        if (!empty($certificates_to_generate_bg)) {
            foreach ($certificates_to_generate_bg as $cert_job) {
                // Ensure post_id and fields are set before scheduling
                if (isset($cert_job['post_id'], $cert_job['fields'])) {
                    if (!wp_next_scheduled('process_single_certificate_hook_school', [$cert_job['post_id'], $cert_job['fields'], 'schools'])) {
                        wp_schedule_single_event(time() + 10, 'process_single_certificate_hook_school', [$cert_job['post_id'], $cert_job['fields'], 'schools']);
                    }
                }
            }
            // Optionally, add a notice that some certificates are being generated in the background if some were also generated successfully.
            // Ensure $certificates_data and $error_count are defined
            if (!empty($certificates_data) && isset($error_count) && $error_count > 0) {
                 $options = get_option('certificate_generator_settings_email'); // Re-fetch options for border-radius
                 $border_radius = $options['border_radius'] ?? '12';
                 // Ensure $output is initialized before appending
                 if (!isset($output)) $output = ''; 
                 $output .= '<div style="margin-top: 20px; padding: 15px; background-color: #e3f2fd; border: 1px solid #bbdefb; color: #1e88e5; border-radius: ' . esc_attr($border_radius) . 'px; text-align: center;">' . __('Some certificates are being generated in the background. They will appear once processed.', 'certificate-generator') . '</div>';
            }
        }
    } 
    // IMPORTANT: The search form HTML should be outside the if (isset($_GET['school_name'])) block
    // So it's always displayed. The following 'else' for the initial $_GET check is removed.
    // The search form generation starts here, regardless of whether a search was performed.
    // Syntax error fixed: removed extra brace and misplaced wp_reset_postdata().
    // The 'else' block below is for the initial display of the search form.
    } else {
        // Get styling options with defaults
        $options = get_option('certificate_generator_settings_email');
        $title_color = $options['title_color'] ?? '#2c3e50';
        $text_color = $options['text_color'] ?? '#7f8c8d';
        $btn_start = $options['btn_start'] ?? '#3498db';
        $btn_end = $options['btn_end'] ?? '#2980b9';
        $border_radius = $options['border_radius'] ?? '12';
        $font_family = $options['font_family'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

        // Modern search form with consistent styling
        $output = '<div class="certificate-search-container" style="max-width: 600px; margin: 40px auto; font-family: ' . esc_attr($font_family) . ';">';
        
        // Form with enhanced styling
        $output .= '<form method="get" style="padding: 35px; background: #ffffff; border-radius: ' . esc_attr($border_radius) . 'px; ' . 
                  'box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); transition: all 0.3s ease;">';
        
        // Heading with icon
        $output .= '<div style="text-align: center; margin-bottom: 30px;">' . 
                  '<h2 style="color: ' . esc_attr($title_color) . '; margin: 0; font-size: 28px; font-weight: 700;">' . 
                  __('School Certificate Lookup', 'certificate-generator') . '</h2>' . 
                  '<p style="color: ' . esc_attr($text_color) . '; margin-top: 10px; font-size: 16px;">' . 
                  __('Enter school name and place to find certificates', 'certificate-generator') . '</p>' . 
                  '</div>';
        
        // Input field for School Name
        $output .= '<div style="position: relative; margin-bottom: 20px;">';
        $output .= '<label for="school_name" style="position: absolute; left: 16px; top: 18px; ' . 
                  'color: ' . esc_attr($text_color) . '; font-size: 16px; transition: all 0.2s ease; pointer-events: none;">' . 
                  __('School Name', 'certificate-generator') . '</label>';
        $output .= '<input type="text" id="school_name" name="school_name" required ' . 
                  'placeholder="" style="width: 100%; padding: 18px 16px; padding-top: 26px; padding-bottom: 10px; ' . 
                  'background: #f8f9fa; border: 2px solid #eaeaea; border-radius: ' . esc_attr($border_radius) . 'px; ' . 
                  'font-size: 16px; transition: all 0.3s ease; outline: none; box-sizing: border-box;" ' . 
                  'onfocus="this.style.borderColor=\'' . esc_js($btn_start) . '\'; ' . 
                  'this.previousElementSibling.style.top=\'8px\'; ' . 
                  'this.previousElementSibling.style.fontSize=\'12px\'; ' . 
                  'this.previousElementSibling.style.color=\'' . esc_js($btn_start) . '\'" ' . 
                  'onblur="if(this.value===\'\'){this.style.borderColor=\'#eaeaea\'; ' . 
                  'this.previousElementSibling.style.top=\'18px\'; ' . 
                  'this.previousElementSibling.style.fontSize=\'16px\'; ' . 
                  'this.previousElementSibling.style.color=\'' . esc_js($text_color) . '\'} ' . 
                  'else{this.style.borderColor=\'#eaeaea\'; ' . 
                  'this.previousElementSibling.style.color=\'' . esc_js($text_color) . '\';}">';
        $output .= '</div>';

        // Input field for Place
        $output .= '<div style="position: relative; margin-bottom: 30px;">';
        $output .= '<label for="place" style="position: absolute; left: 16px; top: 18px; ' . 
                  'color: ' . esc_attr($text_color) . '; font-size: 16px; transition: all 0.2s ease; pointer-events: none;">' . 
                  __('Place', 'certificate-generator') . '</label>';
        $output .= '<input type="text" id="place" name="place" required ' . 
                  'placeholder="" style="width: 100%; padding: 18px 16px; padding-top: 26px; padding-bottom: 10px; ' . 
                  'background: #f8f9fa; border: 2px solid #eaeaea; border-radius: ' . esc_attr($border_radius) . 'px; ' . 
                  'font-size: 16px; transition: all 0.3s ease; outline: none; box-sizing: border-box;" ' . 
                  'onfocus="this.style.borderColor=\'' . esc_js($btn_start) . '\'; ' . 
                  'this.previousElementSibling.style.top=\'8px\'; ' . 
                  'this.previousElementSibling.style.fontSize=\'12px\'; ' . 
                  'this.previousElementSibling.style.color=\'' . esc_js($btn_start) . '\'" ' . 
                  'onblur="if(this.value===\'\'){this.style.borderColor=\'#eaeaea\'; ' . 
                  'this.previousElementSibling.style.top=\'18px\'; ' . 
                  'this.previousElementSibling.style.fontSize=\'16px\'; ' . 
                  'this.previousElementSibling.style.color=\'' . esc_js($text_color) . '\'} ' . 
                  'else{this.style.borderColor=\'#eaeaea\'; ' . 
                  'this.previousElementSibling.style.color=\'' . esc_js($text_color) . '\';}">';
        $output .= '</div>';
        
        // Submit button with icon and hover effect
        $output .= '<button type="submit" style="display: flex; align-items: center; justify-content: center; ' . 
                  'width: 100%; padding: 16px; background: linear-gradient(135deg, ' . esc_attr($btn_start) . ', ' . esc_attr($btn_end) . '); ' . 
                  'color: #fff; border: none; border-radius: ' . esc_attr($border_radius) . 'px; font-size: 16px; ' . 
                  'font-weight: 600; cursor: pointer; text-align: center; transition: all 0.3s ease; ' . 
                  'box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); transform: translateY(0);" ' . 
                  'onmouseover="this.style.transform=\'translateY(-2px)\'; ' . 
                  'this.style.boxShadow=\'0 8px 20px rgba(0, 0, 0, 0.15)\'" ' . 
                  'onmouseout="this.style.transform=\'translateY(0)\'; ' . 
                  'this.style.boxShadow=\'0 4px 15px rgba(0, 0, 0, 0.1)\'">' . 
                  '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" ' . 
                  'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' . 
                  'stroke-linejoin="round" style="margin-right: 8px;">' . 
                  '<circle cx="11" cy="11" r="8"></circle>' . 
                  '<line x1="21" y1="21" x2="16.65" y2="16.65"></line>' . 
                  '</svg>' . __('Search Certificates', 'certificate-generator') . '</button>';
        
        $output .= '</form>';
        
        // Add help text
        $output .= '<div style="text-align: center; margin-top: 20px; padding: 0 15px;">';
        $output .= '<p style="color: ' . esc_attr($text_color) . '; font-size: 14px;">' . 
                  __('Enter the school name and place to locate the certificates.', 'certificate-generator') . 
                  '</p>';
        $output .= '</div>';
        
        $output .= '</div>'; // End container
    }


    
    // Trigger background processing for certificates
    if (!empty($certificates_to_generate)) {
        wp_schedule_single_event(time(), 'generate_certificates_background', [$certificates_to_generate]);
    }
    
    return $output;
}

// Shortcode to search for student certificates
add_shortcode('student_search', 'scs_student_search_shortcode');
function scs_student_search_shortcode(){
    if (isset($_GET['student_email'])) {
        $email = sanitize_email($_GET['student_email']);



        $args = [
            'post_type' => 'students',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => 'email',
                    'value' => $email,
                    'compare' => '=',
                ],
            ],
        ];

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $total_certificates = $query->post_count;

            // Generate certificates with improved handling to prevent duplicates
            $certificates = [];
            $processed_certificates = []; // Track processed certificates to avoid duplicates
            
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $fields = ['student_name', 'school_name', 'issue_date'];
                $file_url = generate_certificate_pdf($post_id, $fields);

                if ($file_url) {
                    // Get student information for better file naming
                    $student_name = sanitize_file_name(get_post_meta($post_id, 'student_name', true));
                    $school_name = sanitize_file_name(get_post_meta($post_id, 'school_name', true));
                    
                    // Create a truly unique filename with more identifiers
                    $unique_id = $post_id . '-' . substr(md5($student_name . $school_name . time()), 0, 10);
                    $unique_filename = $student_name . '-' . $school_name . '-' . $unique_id . '.pdf';
                    
                    // Convert URL to file path
                    $upload_dir = wp_upload_dir();
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
                    
                    // Only add if this certificate hasn't been processed yet
                    $certificate_key = md5($file_path);
                    if (!isset($processed_certificates[$certificate_key])) {
                        $certificates[$post_id] = [
                            'url' => $file_url,
                            'path' => $file_path,
                            'filename' => $unique_filename,
                            'post_id' => $post_id,
                            'student_name' => $student_name,
                            'school_name' => $school_name
                        ];
                        
                        // Mark this certificate as processed
                        $processed_certificates[$certificate_key] = true;
                    }
                }
            }

            // Prepare ZIP download if certificates exist
            $bulk_download_link = '';
            if (!empty($certificates) && class_exists('ZipArchive') && count($certificates) > 3) {
               
                $upload_dir = wp_upload_dir();
                $timestamp = current_time('timestamp');
                $zip_filename = 'certificates_' . $timestamp . '.zip';
                $zip_path = $upload_dir['basedir'] . '/' . $zip_filename;
                $zip_url = $upload_dir['baseurl'] . '/' . $zip_filename;

                // Remove old ZIP file if it exists
                if (file_exists($zip_path)) {
                    @unlink($zip_path);
                }

                $zip = new ZipArchive();
                if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
                    $success = true;
                    foreach ($certificates as $certificate) {
                        if (file_exists($certificate['path'])) {
                            // Add file to ZIP with unique name to prevent overwriting
                            if (!$zip->addFile($certificate['path'], $certificate['filename'])) {
                                error_log('Failed to add file to ZIP: ' . $certificate['path']);
                                $success = false;
                            }
                        }
                    }
                    $zip->close();
                    
                    if ($success && file_exists($zip_path)) {
                        $bulk_download_link = '<a href="' . esc_url($zip_url) . '" class="bulk-download-button" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: linear-gradient(to right, #3498db, #2980b9); color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">Download All Certificates (ZIP)</a>';
                    }
                }
            }

            // Render certificates in a responsive grid layout with modern UI
            $options = get_option('certificate_generator_settings_email');
            
            // Get styling options with defaults
            $card_bg = $options['card_bg'] ?? '#f9f9f9';
            $title_color = $options['title_color'] ?? '#2c3e50';
            $text_color = $options['text_color'] ?? '#7f8c8d';
            $btn_start = $options['btn_start'] ?? '#3498db';
            $btn_end = $options['btn_end'] ?? '#2980b9';
            $hover_effect = $options['hover_effect'] ?? '5';
            $border_radius = $options['border_radius'] ?? '12';
            
            // Container styles
            $output = '<div class="certificate-results-container" style="max-width: 1200px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;">';
            
            // Results header with count
            $output .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">';
            $output .= '<h2 style="font-size: 24px; color: ' . $title_color . '; margin: 0; font-weight: 600;">' . 
                      sprintf(_n('Found %s Certificate', 'Found %s Certificates', $total_certificates, 'certificate-generator'), 
                      '<span style="color: ' . $btn_start . ';">' . $total_certificates . '</span>') . '</h2>';
            
            // Add download all button if available
            if (!empty($bulk_download_link)) {
                $bulk_download_link = str_replace('class="bulk-download-button" style="..."', 
                    'class="bulk-download-button" style="display: inline-block; padding: 12px 24px; background: linear-gradient(to right, ' . $btn_start . ', ' . $btn_end . '); ' . 
                    'color: white; text-decoration: none; border-radius: ' . $border_radius . 'px; font-weight: 500; ' . 
                    'transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); text-align: center;"', 
                    $bulk_download_link);
                $output .= '<div>' . $bulk_download_link . '</div>';
            }
            $output .= '</div>'; // End of header
            
            // Determine layout based on number of certificates
            $cert_count = count($certificates);
            $grid_columns = $cert_count === 1 ? 'minmax(300px, 600px)' : 'repeat(auto-fit, minmax(300px, 1fr))';
            $grid_justify = $cert_count === 1 ? 'center' : 'stretch';
            
            // Grid container with adaptive layout
            $output .= '<div class="certificate-grid" style="display: grid; grid-template-columns: ' . $grid_columns . '; ' . 
                      'gap: 25px; padding: 25px; background: ' . $card_bg . '; border-radius: ' . $border_radius . 'px; ' . 
                      'box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); justify-content: ' . $grid_justify . ';">';

            // Certificate cards
            foreach ($certificates as $certificate) {
                $post_id = $certificate['post_id'];
                $student_name = get_post_meta($post_id, 'student_name', true);
                $school_name = get_post_meta($post_id, 'school_name', true);
                $cert_type = get_post_meta($post_id, 'certificate_type', true);
                $issue_date = get_post_meta($post_id, 'issue_date', true);
                
                // Card with hover effect - enhanced for single/multiple certificate layouts
                $card_width = $cert_count === 1 ? '100%' : 'auto';
                $card_padding = $cert_count === 1 ? '40px' : '30px';
                $output .= '<div class="certificate-card" style="padding: ' . $card_padding . '; background: #ffffff; ' . 
                          'border-radius: ' . $border_radius . 'px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.06); ' . 
                          'transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1); transform: translateY(0); ' . 
                          'border-top: 4px solid ' . $btn_start . '; width: ' . $card_width . ';" ' . 
                          'onmouseover="this.style.transform=\'translateY(-' . $hover_effect . 'px)\'; ' . 
                          'this.style.boxShadow=\'0 15px 35px rgba(0, 0, 0, 0.1)\';" ' . 
                          'onmouseout="this.style.transform=\'translateY(0)\'; ' . 
                          'this.style.boxShadow=\'0 8px 25px rgba(0, 0, 0, 0.06)\';">'
                          
                // Certificate content - enhanced for single/multiple layouts
                . '<h3 style="font-size: ' . ($cert_count === 1 ? '26px' : '22px') . '; color: ' . $title_color . '; margin-top: 0; margin-bottom: 20px; ' . 
                'font-weight: 600; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; ' . ($cert_count === 1 ? 'text-align: center;' : '') . '">' . 
                esc_html($student_name) . '</h3>'
                
                // Certificate details with icons - enhanced for single/multiple layouts
                . '<div style="margin-bottom: ' . ($cert_count === 1 ? '35px' : '25px') . '; ' . ($cert_count === 1 ? 'max-width: 80%; margin-left: auto; margin-right: auto;' : '') . '">';
                
                // School name with icon - enhanced for single/multiple layouts
                $output .= '<div style="display: flex; align-items: flex-start; margin-bottom: ' . ($cert_count === 1 ? '16px' : '12px') . ';">' . 
                          '<div style="min-width: 24px; color: ' . $btn_start . '; margin-right: ' . ($cert_count === 1 ? '15px' : '10px') . ';">' . 
                          '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' . 
                          'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . 
                          '<path d="M2 22v-4h4"></path><path d="M3.5 17.5L7 14"></path>' . 
                          '<path d="M22 2v4h-4"></path><path d="M20.5 6.5L17 10"></path>' . 
                          '<path d="M22 22v-4h-4"></path><path d="M20.5 17.5L17 14"></path>' . 
                          '<path d="M2 2v4h4"></path><path d="M3.5 6.5L7 10"></path>' . 
                          '</svg></div>' . 
                          '<div><span style="font-weight: ' . ($cert_count === 1 ? '600' : '500') . '; color: ' . $title_color . ';">School:</span> ' . 
                          '<span style="color: ' . $text_color . '; font-size: ' . ($cert_count === 1 ? '15px' : '14px') . ';">' . esc_html($school_name) . '</span></div>' . 
                          '</div>';
                
                // Certificate type with icon - enhanced for single/multiple layouts
                $output .= '<div style="display: flex; align-items: flex-start; margin-bottom: ' . ($cert_count === 1 ? '16px' : '12px') . ';">' . 
                          '<div style="min-width: 24px; color: ' . $btn_start . '; margin-right: ' . ($cert_count === 1 ? '15px' : '10px') . ';">' . 
                          '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' . 
                          'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . 
                          '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>' . 
                          '</svg></div>' . 
                          '<div><span style="font-weight: ' . ($cert_count === 1 ? '600' : '500') . '; color: ' . $title_color . ';">Certificate Type:</span> ' . 
                          '<span style="color: ' . $text_color . '; font-size: ' . ($cert_count === 1 ? '15px' : '14px') . ';">' . esc_html($cert_type) . '</span></div>' . 
                          '</div>';
                
                // Issue date with icon - enhanced for single/multiple layouts
                $output .= '<div style="display: flex; align-items: flex-start;">' . 
                          '<div style="min-width: 24px; color: ' . $btn_start . '; margin-right: ' . ($cert_count === 1 ? '15px' : '10px') . ';">' . 
                          '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" ' . 
                          'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . 
                          '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>' . 
                          '<line x1="16" y1="2" x2="16" y2="6"></line>' . 
                          '<line x1="8" y1="2" x2="8" y2="6"></line>' . 
                          '<line x1="3" y1="10" x2="21" y2="10"></line>' . 
                          '</svg></div>' . 
                          '<div><span style="font-weight: ' . ($cert_count === 1 ? '600' : '500') . '; color: ' . $title_color . ';">Issued On:</span> ' . 
                          '<span style="color: ' . $text_color . '; font-size: ' . ($cert_count === 1 ? '15px' : '14px') . ';">' . esc_html($issue_date) . '</span></div>' . 
                          '</div>';
                
                $output .= '</div>'; // End of certificate details
                
                // Download button with hover effect - enhanced for single/multiple layouts
                $btn_width = $cert_count === 1 ? '60%' : '80%';
                $btn_padding = $cert_count === 1 ? '16px 24px' : '14px 20px';
                $btn_font_size = $cert_count === 1 ? '16px' : '14px';
                $btn_container = $cert_count === 1 ? 'text-align: center; margin-top: 30px;' : '';
                
                $output .= '<div style="' . $btn_container . '">';
                $output .= '<a href="' . esc_url($certificate['url']) . '" target="_blank" ' . 
                          'style="display: inline-block; width: ' . $btn_width . '; padding: ' . $btn_padding . '; ' . 
                          'background: linear-gradient(135deg, ' . $btn_start . ', ' . $btn_end . '); ' . 
                          'color: white; text-decoration: none; border-radius: ' . $border_radius . 'px; ' . 
                          'font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); ' . 
                          'text-align: center; text-transform: uppercase; letter-spacing: 0.5px; font-size: ' . $btn_font_size . ';" ' . 
                          'onmouseover="this.style.transform=\'translateY(-2px)\'; ' . 
                          'this.style.boxShadow=\'0 6px 15px rgba(0, 0, 0, 0.15)\';" ' . 
                          'onmouseout="this.style.transform=\'translateY(0)\'; ' . 
                          'this.style.boxShadow=\'0 4px 10px rgba(0, 0, 0, 0.1)\';">'
                          . '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' . 
                          'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' . 
                          'stroke-linejoin="round" style="vertical-align: -3px; margin-right: 8px;">' . 
                          '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>' . 
                          '<polyline points="7 10 12 15 17 10"></polyline>' . 
                          '<line x1="12" y1="15" x2="12" y2="3"></line>' . 
                          '</svg>Download Certificate</a>';
                $output .= '</div>';
                
                $output .= '</div>'; // End of card
            }

            $output .= '</div>'; // End of grid layout

            // Add download all button at the bottom if available
            if (!empty($bulk_download_link)) {
                $output .= '<div style="margin-top: 25px; text-align: center;">' . $bulk_download_link . '</div>';
            }

            $output .= '</div>'; // End of container
        } else {
            // Get styling options with defaults
            $options = get_option('certificate_generator_settings_email');
            $title_color = $options['title_color'] ?? '#2c3e50';
            $text_color = $options['text_color'] ?? '#7f8c8d';
            $btn_start = $options['btn_start'] ?? '#3498db';
            $btn_end = $options['btn_end'] ?? '#2980b9';
            $border_radius = $options['border_radius'] ?? '12';
            
            // Modern error message with consistent styling
            $output = '<div class="certificate-not-found" style="max-width: 600px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;">';
            
            // Error card with enhanced styling
            $output .= '<div style="padding: 40px; background: #ffffff; border-radius: ' . $border_radius . 'px; ' . 
                      'box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); text-align: center;">';
            
            // Error icon with animation
            $output .= '<div style="margin-bottom: 25px; animation: pulse 2s infinite;">' . 
                      '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" ' . 
                      'stroke="' . $btn_start . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . 
                      '<circle cx="12" cy="12" r="10"></circle>' . 
                      '<line x1="12" y1="8" x2="12" y2="12"></line>' . 
                      '<line x1="12" y1="16" x2="12.01" y2="16"></line>' . 
                      '</svg></div>';
            
            // Error message
            $output .= '<h2 style="color: ' . $title_color . '; font-size: 28px; margin: 0 0 15px; font-weight: 700;">' . 
                      __('No Certificate Found', 'certificate-generator') . '</h2>';
            $output .= '<p style="color: ' . $text_color . '; margin-bottom: 30px; line-height: 1.6; font-size: 16px;">' . 
                      __('We couldn\'t find any certificates matching the email address you provided.', 'certificate-generator') . '</p>
            <p style="color: ' . $text_color . '; margin-bottom: 20px; line-height: 1.6;">' . 
                      __('If you are unable to find your certificate here, please drop a request email to us on:', 'certificate-generator') . ' ' . 
                      certificate_generator_get_contact_email() . ' ' . 
                      __('to resend it over your email.', 'certificate-generator') . '</p>
            <div style="margin-bottom: 20px;">
              <p style="font-weight: bold; margin: 0 0 8px; color: ' . $title_color . '; font-size: 16px;">' . 
                __('Please include following details in your email:', 'certificate-generator') . '</p>
              <ul style="margin: 0; padding-left: 20px; list-style-type: disc; color: ' . $text_color . ';">
                <li style="margin-bottom: 8px;">' . __('Registered Email Id', 'certificate-generator') . '</li>
                <li style="margin-bottom: 8px;">' . __('Name', 'certificate-generator') . '</li>
                <li style="margin-bottom: 8px;">' . __('Parent Name', 'certificate-generator') . '</li>
                <li style="margin-bottom: 8px;">' . __('School', 'certificate-generator') . '</li>
                <li style="margin-bottom: 0;">' . __('Grade', 'certificate-generator') . '</li>
              </ul>
            </div>';
            
            // Support section
            $output .= '<div style="background: linear-gradient(to right, rgba(' . hex2rgb($btn_start) . ', 0.05), ' . 
                      'rgba(' . hex2rgb($btn_end) . ', 0.05)); border-radius: ' . $border_radius . 'px; ' . 
                      'padding: 25px; margin: 25px 0; border-left: 4px solid ' . $btn_start . ';">';
            $output .= '<div style="display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">' . 
                      '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" ' . 
                      'stroke="' . $btn_start . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" ' . 
                      'style="margin-right: 10px;">' . 
                      '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>' . 
                      '</svg>' . 
                      '<h3 style="color: ' . $title_color . '; margin: 0; font-size: 18px;">' . 
                      __('Need Help Finding Your Certificate?', 'certificate-generator') . '</h3></div>';
            $output .= '<p style="color: ' . $text_color . '; margin-bottom: 20px; line-height: 1.6;">' . 
                      __('If you believe this is an error or need assistance, please contact our support team.', 'certificate-generator') . '</p>';
            $output .= '<a href="mailto:' . certificate_generator_get_contact_email() . '" ' . 
                      'style="display: inline-flex; align-items: center; padding: 12px 24px; ' . 
                      'background: linear-gradient(135deg, ' . $btn_start . ', ' . $btn_end . '); ' . 
                      'color: white; text-decoration: none; border-radius: ' . $border_radius . 'px; ' . 
                      'font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);" ' . 
                      'onmouseover="this.style.transform=\'translateY(-2px)\'; ' . 
                      'this.style.boxShadow=\'0 8px 20px rgba(0, 0, 0, 0.15)\'" ' . 
                      'onmouseout="this.style.transform=\'translateY(0)\'; ' . 
                      'this.style.boxShadow=\'0 4px 15px rgba(0, 0, 0, 0.1)\'">' . 
                      '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" ' . 
                      'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' . 
                      'stroke-linejoin="round" style="margin-right: 8px;">' . 
                      '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>' . 
                      '<polyline points="22,6 12,13 2,6"></polyline>' . 
                      '</svg>' . __('Contact Support', 'certificate-generator') . '</a>';
            $output .= '</div>';
            
            // Add back button
            $output .= '<a href="' . esc_url(remove_query_arg('student_email')) . '" ' . 
                      'style="display: inline-flex; align-items: center; margin-top: 10px; ' . 
                      'color: ' . $text_color . '; text-decoration: none; font-weight: 500; transition: color 0.3s ease;" ' . 
                      'onmouseover="this.style.color=\'' . $btn_start . '\'" ' . 
                      'onmouseout="this.style.color=\'' . $text_color . '\'">' . 
                      '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" ' . 
                      'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' . 
                      'stroke-linejoin="round" style="margin-right: 6px;">' . 
                      '<line x1="19" y1="12" x2="5" y2="12"></line>' . 
                      '<polyline points="12 19 5 12 12 5"></polyline>' . 
                      '</svg>' . __('Back to Search', 'certificate-generator') . '</a>';
            
            $output .= '</div>'; // End of error card
            
            // Add CSS animation
            $output .= '<style>
                @keyframes pulse {
                    0% { transform: scale(1); opacity: 1; }
                    50% { transform: scale(1.05); opacity: 0.8; }
                    100% { transform: scale(1); opacity: 1; }
                }
            </style>';
            
            $output .= '</div>'; // End container
            
            // Use the existing hex2rgb function with default color if not set
            $hex_color = isset($hex_color) ? $hex_color : '#000000';
            $rgb = hex2rgb($hex_color);
        }

        wp_reset_postdata();



        return $output;
    } else {
        // Get styling options with defaults
        $options = get_option('certificate_generator_settings_email');
        $title_color = $options['title_color'] ?? '#2c3e50';
        $text_color = $options['text_color'] ?? '#7f8c8d';
        $btn_start = $options['btn_start'] ?? '#3498db';
        $btn_end = $options['btn_end'] ?? '#2980b9';
        $border_radius = $options['border_radius'] ?? '12';
        
        // Modern search form with consistent styling
        $output = '<div class="certificate-search-container" style="max-width: 600px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;">';
        
        // Form with enhanced styling
        $output .= '<form method="get" style="padding: 35px; background: #ffffff; border-radius: ' . $border_radius . 'px; ' . 
                  'box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08); transition: all 0.3s ease;">';
        
        // Heading with icon
        $output .= '<div style="text-align: center; margin-bottom: 30px;">' . 
                  '<h2 style="color: ' . $title_color . '; margin: 0; font-size: 28px; font-weight: 700;">' . 
                  __('Certificate Lookup', 'certificate-generator') . '</h2>' . 
                  '<p style="color: ' . $text_color . '; margin-top: 10px; font-size: 16px;">' . 
                  __('Enter your email to find your certificates', 'certificate-generator') . '</p>' . 
                  '</div>';
        
        // Input field with floating label effect
        $output .= '<div style="position: relative; margin-bottom: 30px;">';
        $output .= '<label for="student_email" style="position: absolute; left: 16px; top: 18px; ' . 
                  'color: ' . $text_color . '; font-size: 16px; transition: all 0.2s ease; pointer-events: none;">' . 
                  __('Your Email Address', 'certificate-generator') . '</label>';
        $output .= '<input type="email" id="student_email" name="student_email" required ' . 
                  'placeholder="" style="width: 100%; padding: 18px 16px; padding-top: 26px; padding-bottom: 10px; ' . 
                  'background: #f8f9fa; border: 2px solid #eaeaea; border-radius: ' . $border_radius . ' px; ' . 
                  'font-size: 16px; transition: all 0.3s ease; outline: none; box-sizing: border-box;" ' . 
                  'onfocus="this.style.borderColor=\'' . $btn_start . '\'; ' . 
                  'this.previousElementSibling.style.top=\'8px\'; ' . 
                  'this.previousElementSibling.style.fontSize=\'12px\'; ' . 
                  'this.previousElementSibling.style.color=\'' . $btn_start . '\'" ' . 
                  'onblur="if(this.value===\'\'){this.style.borderColor=\'#eaeaea\'; ' . 
                  'this.previousElementSibling.style.top=\'18px\'; ' . 
                  'this.previousElementSibling.style.fontSize=\'16px\'; ' . 
                  'this.previousElementSibling.style.color=\'' . $text_color . '\'} ' . 
                  'else{this.style.borderColor=\'#eaeaea\'; ' . 
                  'this.previousElementSibling.style.color=\'' . $text_color . '\';}">';
        $output .= '</div>';
        
        // Submit button with icon and hover effect
        $output .= '<button type="submit" style="display: flex; align-items: center; justify-content: center; ' . 
                  'width: 100%; padding: 16px; background: linear-gradient(135deg, ' . $btn_start . ', ' . $btn_end . '); ' . 
                  'color: #fff; border: none; border-radius: ' . $border_radius . 'px; font-size: 16px; ' . 
                  'font-weight: 600; cursor: pointer; text-align: center; transition: all 0.3s ease; ' . 
                  'box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); transform: translateY(0);" ' . 
                  'onmouseover="this.style.transform=\'translateY(-2px)\'; ' . 
                  'this.style.boxShadow=\'0 8px 20px rgba(0, 0, 0, 0.15)\'" ' . 
                  'onmouseout="this.style.transform=\'translateY(0)\'; ' . 
                  'this.style.boxShadow=\'0 4px 15px rgba(0, 0, 0, 0.1)\'">' . 
                  '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" ' . 
                  'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' . 
                  'stroke-linejoin="round" style="margin-right: 8px;">' . 
                  '<circle cx="11" cy="11" r="8"></circle>' . 
                  '<line x1="21" y1="21" x2="16.65" y2="16.65"></line>' . 
                  '</svg>' . __('Search Certificates', 'certificate-generator') . '</button>';
        
        $output .= '</form>';
        
        // Add help text
        $output .= '<div style="text-align: center; margin-top: 20px; padding: 0 15px;">';
        $output .= '<p style="color: ' . $text_color . '; font-size: 14px;">' . 
                  __('Enter the email address you used during registration to find your certificates.', 'certificate-generator') . 
                  '</p>';
        $output .= '</div>';
        
        $output .= '</div>'; // End container
    }

    return $output;
}

?>
