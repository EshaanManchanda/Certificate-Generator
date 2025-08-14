<?php
/**
 * Debug utility for testing path normalization fixes
 * 
 * This file helps verify that the certificate file path issues are resolved
 * and provides debugging information for path handling.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug function to test path normalization
 */
function debug_certificate_paths($post_id) {
    echo "<h3>Certificate Path Debug for Post ID: {$post_id}</h3>";
    
    // Get upload directory info
    $upload_dir = wp_upload_dir();
    echo "<p><strong>Upload Directory Info:</strong></p>";
    echo "<pre>" . print_r($upload_dir, true) . "</pre>";
    
    // Get stored certificate path
    $stored_path = get_post_meta($post_id, 'certificate_file_path', true);
    echo "<p><strong>Stored Certificate Path:</strong> {$stored_path}</p>";
    
    // Check if file exists
    $file_exists = file_exists($stored_path);
    echo "<p><strong>File Exists:</strong> " . ($file_exists ? 'YES' : 'NO') . "</p>";
    
    // Show normalized path
    $normalized_path = wp_normalize_path($stored_path);
    echo "<p><strong>Normalized Path:</strong> {$normalized_path}</p>";
    
    // Check if normalized file exists
    $normalized_exists = file_exists($normalized_path);
    echo "<p><strong>Normalized File Exists:</strong> " . ($normalized_exists ? 'YES' : 'NO') . "</p>";
    
    // Show expected path
    $expected_path = wp_normalize_path($upload_dir['path'] . "/certificate_{$post_id}.pdf");
    echo "<p><strong>Expected Path:</strong> {$expected_path}</p>";
    
    // Check if expected file exists
    $expected_exists = file_exists($expected_path);
    echo "<p><strong>Expected File Exists:</strong> " . ($expected_exists ? 'YES' : 'NO') . "</p>";
    
    // Show path comparison
    echo "<p><strong>Paths Match:</strong> " . ($normalized_path === $expected_path ? 'YES' : 'NO') . "</p>";
    
    // Show directory permissions
    $upload_path = $upload_dir['path'];
    echo "<p><strong>Upload Directory Writable:</strong> " . (is_writable($upload_path) ? 'YES' : 'NO') . "</p>";
    
    // List files in upload directory
    echo "<p><strong>Files in Upload Directory:</strong></p>";
    if (is_dir($upload_path)) {
        $files = scandir($upload_path);
        echo "<ul>";
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $full_path = wp_normalize_path($upload_path . '/' . $file);
                echo "<li>{$file} - " . (is_file($full_path) ? 'FILE' : 'DIR') . "</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p>Upload directory does not exist or is not accessible.</p>";
    }
}

/**
 * Test certificate generation with path debugging
 */
function test_certificate_generation($post_id) {
    echo "<h3>Testing Certificate Generation for Post ID: {$post_id}</h3>";
    
    // Get post type and determine fields
    $post_type = get_post_type($post_id);
    $fields = [];
    
    switch ($post_type) {
        case 'students':
            $fields = ['student_name', 'school_name', 'issue_date'];
            break;
        case 'teachers':
            $fields = ['teacher_name', 'school_name', 'issue_date'];
            break;
        case 'schools':
            $fields = ['school_name', 'issue_date'];
            break;
        default:
            echo "<p style='color: red;'>Invalid post type: {$post_type}</p>";
            return false;
    }
    
    echo "<p><strong>Post Type:</strong> {$post_type}</p>";
    echo "<p><strong>Fields:</strong> " . implode(', ', $fields) . "</p>";
    
    // Test the email-compatible function
    echo "<p><strong>Testing generate_certificate_pdf_email function...</strong></p>";
    
    $result = generate_certificate_pdf_email($post_id, $fields);
    
    if ($result) {
        echo "<p style='color: green;'>Certificate generation successful!</p>";
        echo "<p><strong>Result:</strong></p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
        
        // Verify the file
        if (isset($result['path']) && file_exists($result['path'])) {
            echo "<p style='color: green;'>Certificate file verified at: {$result['path']}</p>";
            echo "<p><strong>File size:</strong> " . filesize($result['path']) . " bytes</p>";
        } else {
            echo "<p style='color: red;'>Certificate file not found at specified path!</p>";
        }
    } else {
        echo "<p style='color: red;'>Certificate generation failed!</p>";
    }
    
    return $result;
}

/**
 * Clean up old certificate files for testing
 */
function cleanup_certificate_files($post_id) {
    $upload_dir = wp_upload_dir();
    $certificate_path = wp_normalize_path($upload_dir['path'] . "/certificate_{$post_id}.pdf");
    
    if (file_exists($certificate_path)) {
        if (unlink($certificate_path)) {
            echo "<p style='color: green;'>Cleaned up certificate file: {$certificate_path}</p>";
            // Clear post meta
            delete_post_meta($post_id, 'certificate_file_path');
            delete_post_meta($post_id, 'certificate_file_url');
            echo "<p>Cleared certificate post meta.</p>";
        } else {
            echo "<p style='color: red;'>Failed to delete certificate file: {$certificate_path}</p>";
        }
    } else {
        echo "<p>No certificate file found to clean up.</p>";
    }
}

// Add admin page for debugging if in admin area
if (is_admin()) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'edit.php?post_type=students',
            'Debug Certificate Paths',
            'Debug Paths',
            'manage_options',
            'debug-certificate-paths',
            'render_debug_certificate_paths_page'
        );
    });
}

function render_debug_certificate_paths_page() {
    echo '<div class="wrap">';
    echo '<h1>Certificate Path Debug</h1>';
    
    if (isset($_POST['test_post_id'])) {
        $post_id = intval($_POST['test_post_id']);
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'debug':
                    debug_certificate_paths($post_id);
                    break;
                case 'test':
                    test_certificate_generation($post_id);
                    break;
                case 'cleanup':
                    cleanup_certificate_files($post_id);
                    break;
            }
        }
    }
    
    echo '<form method="post">';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">Post ID to Test</th>';
    echo '<td><input type="number" name="test_post_id" value="" placeholder="Enter post ID" required /></td>';
    echo '</tr>';
    echo '</table>';
    
    echo '<p class="submit">';
    echo '<input type="submit" name="action" value="debug" class="button-primary" /> ';
    echo '<input type="submit" name="action" value="test" class="button-secondary" /> ';
    echo '<input type="submit" name="action" value="cleanup" class="button-secondary" />';
    echo '</p>';
    echo '</form>';
    
    echo '<h3>Instructions</h3>';
    echo '<ul>';
    echo '<li><strong>Debug:</strong> Shows detailed path information for an existing certificate</li>';
    echo '<li><strong>Test:</strong> Generates a new certificate and tests the path handling</li>';
    echo '<li><strong>Cleanup:</strong> Removes certificate files and meta data for testing</li>';
    echo '</ul>';
    
    echo '</div>';
}
?>