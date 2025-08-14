<?php
/**
 * WordPress Font Conversion Runner
 * Access via: http://test.local/wp-content/plugins/certificate-generator/run-conversion.php
 */

// Security check
if (!isset($_GET['run']) || $_GET['run'] !== 'fonts') {
    die('Access denied. Use: ?run=fonts&batch=20&start=0&category=ROCK');
}

// Get parameters
$batch_size = isset($_GET['batch']) ? (int)$_GET['batch'] : 20;
$start_from = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$category = isset($_GET['category']) ? $_GET['category'] : '';

echo "<h1>🎨 Font Conversion - Web Interface</h1>\n";
echo "<pre>\n";

// Include the bulk converter
ob_start();
include 'bulk-font-converter.php';
$output = ob_get_clean();

// Display output
echo htmlspecialchars($output);

echo "</pre>\n";

echo "<h2>🚀 Next Steps</h2>\n";
echo "<p>Conversion completed! <a href='?run=fonts&batch=$batch_size&start=" . ($start_from + $batch_size) . "&category=$category'>Continue Next Batch</a></p>\n";
?>