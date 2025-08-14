<?php
/**
 * Font Preview Script - Shows what fonts are available for conversion
 */

echo "🎨 Font Collection Preview\n";
echo "=========================\n\n";

$source_font_dir = __DIR__ . '/fonts/';
$fpdf_font_dir = __DIR__ . '/includes/fpdf/font/';

if (!is_dir($source_font_dir)) {
    die("❌ Font directory not found: $source_font_dir\n");
}

/**
 * Get existing FPDF fonts
 */
function getExistingFonts($fpdf_font_dir) {
    $existing = [];
    if (is_dir($fpdf_font_dir)) {
        $php_files = glob($fpdf_font_dir . '*.php');
        
        foreach ($php_files as $php_file) {
            $filename = basename($php_file, '.php');
            if (!in_array($filename, ['symbol', 'zapfdingbats'])) {
                $existing[] = strtolower($filename);
            }
        }
    }
    return $existing;
}

/**
 * Recursively find font files
 */
function findFontFiles($dir, $limit = 100) {
    $font_files = [];
    $count = 0;
    
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($count >= $limit) break;
        
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['ttf', 'otf'])) {
            $font_files[] = [
                'path' => $file->getPathname(),
                'name' => $file->getBasename(),
                'size' => $file->getSize(),
                'dir' => basename(dirname($file->getPathname()))
            ];
            $count++;
        }
    }
    
    return $font_files;
}

/**
 * Categorize fonts by directory
 */
function categorizeFonts($font_files) {
    $categories = [];
    
    foreach ($font_files as $font) {
        $category = $font['dir'];
        if (!isset($categories[$category])) {
            $categories[$category] = [];
        }
        $categories[$category][] = $font;
    }
    
    return $categories;
}

// Get existing fonts
$existing_fonts = getExistingFonts($fpdf_font_dir);
echo "🔍 Currently available FPDF fonts: " . count($existing_fonts) . "\n";

if (!empty($existing_fonts)) {
    echo "   Existing: " . implode(', ', array_slice($existing_fonts, 0, 10));
    if (count($existing_fonts) > 10) {
        echo " ... (+" . (count($existing_fonts) - 10) . " more)";
    }
    echo "\n";
}
echo "\n";

// Preview source fonts (limited to first 100 for performance)
echo "🔍 Scanning font collection (showing first 100 files)...\n";
$font_files = findFontFiles($source_font_dir, 100);

if (empty($font_files)) {
    echo "❌ No TTF/OTF files found in font directory\n";
    exit;
}

$total_fonts = count($font_files);
echo "📊 Found $total_fonts font files (showing sample)\n\n";

// Categorize fonts
$categories = categorizeFonts($font_files);

echo "📁 Font Categories Available:\n";
echo "============================\n";

foreach ($categories as $category => $fonts) {
    echo "📂 $category (" . count($fonts) . " fonts)\n";
    
    // Show first few fonts in each category
    $sample_fonts = array_slice($fonts, 0, 5);
    foreach ($sample_fonts as $font) {
        $size_kb = round($font['size'] / 1024, 1);
        echo "   • " . $font['name'] . " ({$size_kb}KB)\n";
    }
    
    if (count($fonts) > 5) {
        echo "   ... (+" . (count($fonts) - 5) . " more fonts)\n";
    }
    echo "\n";
}

// Check for potential issues
echo "⚠️  Conversion Notes:\n";
echo "====================\n";

$large_fonts = array_filter($font_files, function($font) {
    return $font['size'] > 1024 * 1024; // > 1MB
});

if (!empty($large_fonts)) {
    echo "📏 Large fonts (>1MB) found: " . count($large_fonts) . "\n";
    echo "   These may take longer to convert\n\n";
}

$special_chars = array_filter($font_files, function($font) {
    return preg_match('/[^a-zA-Z0-9._-]/', $font['name']);
});

if (!empty($special_chars)) {
    echo "🔤 Fonts with special characters: " . count($special_chars) . "\n";
    echo "   These will be renamed during conversion\n\n";
}

// Estimate conversion time and space
$total_size = array_sum(array_column($font_files, 'size'));
$size_mb = round($total_size / (1024 * 1024), 1);
$estimated_time = round(count($font_files) * 2 / 60, 1); // ~2 seconds per font

echo "📊 Conversion Estimates:\n";
echo "========================\n";
echo "📁 Total source size: {$size_mb}MB\n";
echo "⏱️  Estimated time: {$estimated_time} minutes\n";
echo "💾 Estimated output: " . round($size_mb * 0.3, 1) . "MB (PHP + compressed files)\n\n";

echo "🚀 Ready to convert fonts!\n";
echo "========================\n";
echo "To start conversion, run:\n";
echo "   php smart-font-converter.php\n";
echo "\nOr use PowerShell for batch processing:\n";
echo "   powershell -ExecutionPolicy Bypass -File batch-convert-fonts.ps1\n\n";

// Show command options
echo "💡 Conversion Options:\n";
echo "• Convert all fonts: Full conversion (may take time)\n";
echo "• Convert by category: Focus on specific font types\n";
echo "• Batch processing: Convert in smaller groups\n\n";

echo "⚡ The system will automatically skip fonts that already exist!\n";
?>