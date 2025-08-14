<?php
/**
 * Debug Font Converter - Web interface to run convert-fonts.php with debugging
 */

// Security check
if (!isset($_GET['run']) || $_GET['run'] !== 'debug') {
    die('Access denied. Use: ?run=debug&batch=5');
}

echo "<h1>🔍 Debug Font Converter</h1>\n";
echo "<pre>\n";

// Capture output from convert-fonts.php
ob_start();

// Set parameters for convert-fonts.php
$_SERVER['argv'] = ['convert-fonts.php'];
$_SERVER['argc'] = 1;

// Include the convert-fonts.php with debugging
include 'convert-fonts.php';

$output = ob_get_clean();

// Display the output with proper formatting
echo htmlspecialchars($output);

echo "</pre>\n";

echo "<h2>🔧 Analysis</h2>\n";
echo "<p>Check the DEBUG lines above to see:</p>\n";
echo "<ul>\n";
echo "<li>Are PHP files being created?</li>\n";
echo "<li>Are Z files being created?</li>\n";
echo "<li>What are the file sizes?</li>\n";
echo "<li>Where exactly is the conversion failing?</li>\n";
echo "</ul>\n";
?>