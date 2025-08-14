<?php
/**
 * Certificate Background Processor
 *
 * Handles asynchronous processing of certificate generation and ZIP creation
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Certificate_Background_Processor class
 */
class Certificate_Background_Processor {
    /**
     * Action hook for background processing
     */
    const CRON_HOOK = 'certificate_background_processing';
    
    /**
     * Batch size for processing certificates
     */
    const BATCH_SIZE = 10;
    
    /**
     * Initialize the background processor
     */
    public function __construct() {
        // Register the cron hook
        add_action(self::CRON_HOOK, array($this, 'process_batch'), 10, 2);
        
        // Register AJAX handlers
        add_action('wp_ajax_check_certificate_progress', array($this, 'check_progress'));
        add_action('wp_ajax_nopriv_check_certificate_progress', array($this, 'check_progress'));
    }
    
    /**
     * Schedule a background job to process certificates
     *
     * @param array $post_ids Array of post IDs to process
     * @param string $email_hash Hash of the user's email for identification
     * @return string Job ID
     */
    public function schedule_certificate_processing($post_ids, $email_hash) {
        // Generate a unique job ID
        $job_id = 'cert_job_' . $email_hash . '_' . time();
        
        // Split post IDs into batches
        $batches = array_chunk($post_ids, self::BATCH_SIZE);
        $total_batches = count($batches);
        
        // Initialize job data
        $job_data = array(
            'job_id' => $job_id,
            'email_hash' => $email_hash,
            'total_posts' => count($post_ids),
            'processed_posts' => 0,
            'total_batches' => $total_batches,
            'processed_batches' => 0,
            'status' => 'pending',
            'start_time' => time(),
            'certificates' => array(),
            'errors' => array(),
            'zip_path' => '',
            'zip_url' => ''
        );
        
        // Store job data
        update_option('certificate_job_' . $job_id, $job_data);
        
        // Schedule the first batch immediately
        wp_schedule_single_event(time(), self::CRON_HOOK, array($job_id, $batches[0]));
        
        // Schedule remaining batches with a slight delay to prevent server overload
        for ($i = 1; $i < $total_batches; $i++) {
            wp_schedule_single_event(time() + (60 * $i), self::CRON_HOOK, array($job_id, $batches[$i]));
        }
        
        return $job_id;
    }
    
    /**
     * Process a batch of certificates
     *
     * @param string $job_id Job ID
     * @param array $batch Batch of post IDs to process
     */
    public function process_batch($job_id, $batch) {
        // Get job data
        $job_data = get_option('certificate_job_' . $job_id);
        
        if (!$job_data) {
            error_log('Certificate job not found: ' . $job_id);
            return;
        }
        
        // Update job status to processing if it's the first batch
        if ($job_data['status'] === 'pending') {
            $job_data['status'] = 'processing';
            update_option('certificate_job_' . $job_id, $job_data);
            
            // Invalidate HTML cache but keep certificate data cache
            $this->invalidate_html_cache($job_data['email_hash']);
        }
        
        // Process each post in the batch
        $fields = array('student_name', 'school_name', 'issue_date');
        $processed_in_batch = 0;
        
        foreach ($batch as $post_id) {
            // Check if certificate already exists
            $existing_file_path = get_post_meta($post_id, 'certificate_file_path', true);
            $existing_file_url = get_post_meta($post_id, 'certificate_file_url', true);
            
            if ($existing_file_path && file_exists($existing_file_path)) {
                // Certificate already exists, use it
                $job_data['certificates'][$post_id] = array(
                    'url' => $existing_file_url,
                    'path' => $existing_file_path,
                    'filename' => basename($existing_file_path)
                );
            } else {
                // Generate certificate
                $file_url = $this->generate_certificate($post_id, $fields);
                
                if ($file_url) {
                    // Get the actual file path from post meta or convert URL to path
                    $file_path = get_post_meta($post_id, 'certificate_file_path', true);
                    if (!$file_path) {
                        // If file path is not stored in meta, try to derive it from URL
                        $upload_dir = wp_upload_dir();
                        $file_path = str_replace($upload_dir['url'], $upload_dir['path'], $file_url);
                        
                        // Store the file path for future use
                        update_post_meta($post_id, 'certificate_file_path', $file_path);
                        update_post_meta($post_id, 'certificate_file_url', $file_url);
                    }
                    
                    // Only add to certificates array if file exists
                    if (file_exists($file_path)) {
                        $job_data['certificates'][$post_id] = array(
                            'url' => $file_url,
                            'path' => $file_path,
                            'filename' => basename($file_path)
                        );
                    } else {
                        $job_data['errors'][] = "Certificate file does not exist at path: $file_path";
                        error_log("Certificate file does not exist at path: $file_path");
                    }
                } else {
                    $job_data['errors'][] = "Failed to generate certificate for post ID: $post_id";
                    error_log("Failed to generate certificate for post ID: $post_id");
                }
            }
            
            $processed_in_batch++;
        }
        
        // Update job progress
        $job_data['processed_posts'] += $processed_in_batch;
        $job_data['processed_batches']++;
        
        // Check if all batches are processed
        if ($job_data['processed_batches'] >= $job_data['total_batches']) {
            // All batches processed, create ZIP file
            $this->create_zip_file($job_data);
            $job_data['status'] = 'completed';
        }
        
        // Update job data
        update_option('certificate_job_' . $job_id, $job_data);
    }
    
    /**
     * Generate a certificate PDF
     *
     * @param int $post_id Post ID
     * @param array $fields Fields to include in the certificate
     * @return string|bool URL of the generated certificate or false on failure
     */
    private function generate_certificate($post_id, $fields) {
        // This is a wrapper for the existing generate_certificate_pdf function
        if (function_exists('generate_certificate_pdf')) {
            return generate_certificate_pdf($post_id, $fields);
        }
        return false;
    }
    
    /**
     * Create a ZIP file containing all certificates
     *
     * @param array $job_data Job data
     * @return bool Success or failure
     */
    private function create_zip_file(&$job_data) {
        // Check if ZipArchive class exists
        if (!class_exists('ZipArchive')) {
            $job_data['errors'][] = 'ZipArchive extension is not installed on the server.';
            error_log('ZipArchive extension is not installed on the server.');
            return false;
        }
        
        // Create a new ZIP file
        $upload_dir = wp_upload_dir();
        $timestamp = time();
        $zip_filename = 'certificates_' . $job_data['email_hash'] . '_' . $timestamp . '.zip';
        $zip_path = $upload_dir['path'] . '/' . $zip_filename;
        $zip_url = $upload_dir['url'] . '/' . $zip_filename;
        
        // Clean up old ZIP files for this email
        $existing_zip_meta_key = 'certificates_zip_' . $job_data['email_hash'];
        $existing_zip_info = get_option($existing_zip_meta_key);
        
        if ($existing_zip_info && isset($existing_zip_info['path']) && file_exists($existing_zip_info['path'])) {
            @unlink($existing_zip_info['path']);
            error_log("Deleted old ZIP file: {$existing_zip_info['path']}");
        }
        
        // Create ZIP file
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            $added_files = 0;
            $post_data = array();
            
            // Get post data for all certificates
            $post_ids = array_keys($job_data['certificates']);
            foreach ($post_ids as $post_id) {
                $post_data[$post_id] = array(
                    'student_name' => get_post_meta($post_id, 'student_name', true),
                    'school_name' => get_post_meta($post_id, 'school_name', true),
                    'certificate_type' => get_post_meta($post_id, 'certificate_type', true),
                );
            }
            
            // Add files to ZIP in batches
            $certificate_chunks = array_chunk($job_data['certificates'], self::BATCH_SIZE, true);
            
            foreach ($certificate_chunks as $chunk) {
                foreach ($chunk as $cert_id => $certificate) {
                    if (file_exists($certificate['path'])) {
                        // Get metadata
                        $student_name = $post_data[$cert_id]['student_name'];
                        $school_name = $post_data[$cert_id]['school_name'];
                        $cert_type = $post_data[$cert_id]['certificate_type'];
                        
                        // Create a clean filename
                        $clean_name = sanitize_file_name($student_name . '_' . $school_name . '_' . $cert_type . '.pdf');
                        
                        // Add to ZIP
                        if ($zip->addFile($certificate['path'], $clean_name)) {
                            $added_files++;
                        } else {
                            $job_data['errors'][] = "Failed to add file to ZIP: {$certificate['path']}";
                            error_log("Failed to add file to ZIP: {$certificate['path']}");
                        }
                    }
                }
            }
            
            $zip->close();
            
            // Store ZIP info for future use if files were added
            if ($added_files > 0) {
                $job_data['zip_path'] = $zip_path;
                $job_data['zip_url'] = $zip_url;
                $job_data['zip_file_count'] = $added_files;
                
                update_option($existing_zip_meta_key, array(
                    'path' => $zip_path,
                    'url' => $zip_url,
                    'timestamp' => $timestamp,
                    'count' => $added_files
                ));
                
                // Invalidate HTML cache to ensure the download button is shown
                // but keep certificate data cache for future use
                $this->invalidate_html_cache($job_data['email_hash']);
                
                return true;
            } else {
                // No files were added, delete the empty ZIP
                @unlink($zip_path);
                $job_data['errors'][] = 'No valid certificates found to add to ZIP file.';
                return false;
            }
        } else {
            $job_data['errors'][] = "Failed to create ZIP file: $zip_path";
            error_log("Failed to create ZIP file: $zip_path");
            return false;
        }
    }
    
    /**
     * Check the progress of a certificate generation job
     */
    public function check_progress() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'certificate_generation_progress_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Get job ID
        if (!isset($_POST['job_id'])) {
            wp_send_json_error('Missing job ID');
        }
        
        $job_id = sanitize_text_field($_POST['job_id']);
        $job_data = get_option('certificate_job_' . $job_id);
        
        if (!$job_data) {
            wp_send_json_error('Job not found');
        }
        
        // Calculate progress percentage
        $progress = 0;
        if ($job_data['total_posts'] > 0) {
            $progress = round(($job_data['processed_posts'] / $job_data['total_posts']) * 100);
        }
        
        // Prepare response
        $response = array(
            'status' => $job_data['status'],
            'progress' => $progress,
            'processed' => $job_data['processed_posts'],
            'total' => $job_data['total_posts'],
            'zip_url' => isset($job_data['zip_url']) ? $job_data['zip_url'] : '',
            'zip_file_count' => isset($job_data['zip_file_count']) ? $job_data['zip_file_count'] : 0,
            'errors' => $job_data['errors']
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Get job data by email hash
     *
     * @param string $email_hash Email hash
     * @return array|bool Job data or false if not found
     */
    public function get_job_by_email_hash($email_hash) {
        global $wpdb;
        
        // Get all options that might be job data for this email hash
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 1",
                'certificate_job_cert_job_' . $email_hash . '_%'
            )
        );
        
        if (empty($option_names)) {
            return false;
        }
        
        // Get the most recent job (should be the first one due to ORDER BY)
        $job_data = get_option($option_names[0]);
        
        return $job_data;
    }
    
    /**
     * Invalidate HTML cache but keep certificate data cache
     *
     * @param string $email_hash Email hash
     */
    private function invalidate_html_cache($email_hash) {
        // Get all cache keys for this email
        $email_cache_keys = get_option('certificate_cache_keys_' . $email_hash, []);
        
        if (!empty($email_cache_keys)) {
            foreach ($email_cache_keys as $key => $timestamp) {
                // Only delete HTML output cache, keep certificate data cache
                if (strpos($key, 'certificate_data_') === false) {
                    delete_transient($key);
                    error_log("Invalidated cache key: {$key}");
                }
            }
        }
    }
    
    /**
     * Invalidate all caches for an email hash
     * 
     * @param string $email_hash Email hash
     */
    public function invalidate_all_caches($email_hash) {
        // Get all cache keys for this email
        $email_cache_keys = get_option('certificate_cache_keys_' . $email_hash, []);
        
        if (!empty($email_cache_keys)) {
            foreach ($email_cache_keys as $key => $timestamp) {
                delete_transient($key);
                error_log("Invalidated all cache for key: {$key}");
            }
            
            // Clear the cache keys list
            delete_option('certificate_cache_keys_' . $email_hash);
        }
    }
}

// Initialize the background processor
$certificate_background_processor = new Certificate_Background_Processor();