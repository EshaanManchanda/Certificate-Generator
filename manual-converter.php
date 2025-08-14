<?php
/**
 * Manual Font Converter - Process one directory at a time
 */

if (!isset($_GET['run'])) {
    die('Access denied. Use: ?run=fonts&dir=acdc&batch=5');
}

echo "<h1>🎨 Manual Font Converter</h1>\n";
echo "<pre>\n";

require_once __DIR__ . '/includes/fpdf/fpdf.php';
require_once __DIR__ . '/includes/fpdf/makefont/makefont.php';
require_once __DIR__ . '/includes/fpdf/makefont/ttfparser.php';

$target_dir = isset($_GET['dir']) ? $_GET['dir'] : 'acdc';
$batch_size = isset($_GET['batch']) ? (int)$_GET['batch'] : 5;

echo "🎯 Manual Font Converter\n";
echo "========================\n";
echo "Directory: $target_dir | Batch: $batch_size\n\n";

// Specific directories to choose from
$available_dirs = [
    'acdc' => 'fonts/FUENTESDEROCK/Brinde - Fontes de Rock/acdc/',
    'metallica' => 'fonts/FUENTESDEROCK/Brinde - Fontes de Rock/metallica/',
    'beatles' => 'fonts/FUENTESDEROCK/Brinde - Fontes de Rock/beatles/',
    'aerosmith' => 'fonts/FUENTESDEROCK/Brinde - Fontes de Rock/aerosmith/',
    'tipografias-a' => 'fonts/TIPOGRAFIAS/Fontes PACK/125 Mil Fontes A - Z/Fontes - A/',
    'tipografias-b' => 'fonts/TIPOGRAFIAS/Fontes PACK/125 Mil Fontes A - Z/Fontes - B/'
];

if (!isset($available_dirs[$target_dir])) {
    echo "❌ Invalid directory. Available options:\n";
    foreach ($available_dirs as $key => $path) {
        echo "• <a href='?run=fonts&dir=$key&batch=5'>$key</a> ($path)\n";
    }
    echo "</pre>";
    exit;
}

$source_path = __DIR__ . '/' . $available_dirs[$target_dir];
$fpdf_font_dir = __DIR__ . '/includes/fpdf/font/';

echo "📁 Source: $source_path\n";
echo "📁 Output: $fpdf_font_dir\n\n";

if (!is_dir($source_path)) {
    echo "❌ Directory not found: $source_path\n";
    echo "</pre>";
    exit;
}

// Find fonts in specific directory
$fonts = [];
$files = glob($source_path . '*.{ttf,otf,TTF,OTF}', GLOB_BRACE);
foreach ($files as $file) {
    if (filesize($file) > 1000) {
        $fonts[] = [
            'path' => $file,
            'name' => basename($file)
        ];
    }
}

echo "📊 Fonts found: " . count($fonts) . "\n\n";

if (empty($fonts)) {
    echo "❌ No fonts found in directory\n";
    echo "</pre>";
    exit;
}

// Get existing
$existing = [];
$php_files = glob($fpdf_font_dir . '*.php');
foreach ($php_files as $file) {
    $existing[] = strtolower(basename($file, '.php'));
}

// Process fonts
$processed = array_slice($fonts, 0, $batch_size);
$converted = 0;

echo "🔄 Converting $batch_size fonts from $target_dir...\n";
echo "================================================\n";

foreach ($processed as $i => $font) {
    $base_name = pathinfo($font['name'], PATHINFO_FILENAME);
    $clean_name = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($base_name));
    $clean_name = str_replace(['regular', 'normal'], '', $clean_name);
    $target_name = $target_dir . substr($clean_name, 0, 10);
    
    echo "[" . ($i + 1) . "] " . $font['name'] . " → $target_name ... ";
    
    if (in_array(strtolower($target_name), $existing)) {
        echo "EXISTS\n";
        continue;
    }
    
    try {
        $old_dir = getcwd();
        chdir($fpdf_font_dir);
        
        ob_start();
        error_reporting(0);
        MakeFont($font['path'], 'cp1252', false, $target_name);
        error_reporting(E_ALL);
        ob_end_clean();
        
        chdir($old_dir);
        
        if (file_exists($fpdf_font_dir . $target_name . '.php')) {
            echo "✅\n";
            $converted++;
            $existing[] = strtolower($target_name);
        } else {
            echo "❌\n";
        }
        
    } catch (Exception $e) {
        echo "❌\n";
        if (isset($old_dir)) chdir($old_dir);
    }
}

echo "\n📊 Converted: $converted fonts\n\n";

echo "🎯 Convert more directories:\n";
foreach ($available_dirs as $key => $path) {
    if ($key !== $target_dir) {
        echo "• <a href='?run=fonts&dir=$key&batch=10'>$key</a>\n";
    }
}

echo "\n🔄 <a href='/wp-admin/edit.php?post_type=certificate'>Refresh WordPress Admin</a> to see new fonts\n";

echo "</pre>\n";
?>