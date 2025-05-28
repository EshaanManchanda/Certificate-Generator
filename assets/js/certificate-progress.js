/**
 * Certificate Generation Progress Bar
 * Handles real-time progress updates during bulk certificate generation
 */

jQuery(document).ready(function($) {
    // Get the progress container
    const progressContainer = $('#certificate-progress-container');
    
    // If progress container exists, start monitoring progress
    if (progressContainer.length) {
        const jobId = progressContainer.data('job-id');
        const progressBar = $('#certificate-progress-bar');
        const progressStatus = $('#certificate-progress-status');
        const progressText = $('#certificate-progress-text');
        
        // Start polling for progress updates
        const pollInterval = setInterval(function() {
            $.ajax({
                url: certificateProgress.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'check_certificate_progress',
                    nonce: certificateProgress.nonce,
                    job_id: jobId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const data = response.data;
                        
                        // Update progress bar
                        progressBar.css('width', data.progress + '%');
                        progressText.text(data.progress + '%');
                        
                        // Update status message
                        let statusMessage = 'Processing...';
                        if (data.status === 'pending') {
                            statusMessage = 'Waiting to start...';
                        } else if (data.status === 'processing') {
                            statusMessage = `Processing certificates (${data.processed} of ${data.total})`;
                        } else if (data.status === 'completed') {
                            statusMessage = 'Completed!';
                            
                            // Show download button if ZIP was created successfully
                            if (data.zip_url && data.zip_file_count > 0) {
                                const downloadHtml = `
                                    <div class="certificate-download-container">
                                        <p>All certificates have been processed successfully!</p>
                                        <a href="${data.zip_url}" class="certificate-download-button">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" style="vertical-align: middle; margin-right: 8px;" viewBox="0 0 16 16">
                                                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                            </svg>
                                            Download All Certificates (ZIP)
                                        </a>
                                        <p class="certificate-count">Contains ${data.zip_file_count} certificate${data.zip_file_count > 1 ? 's' : ''}</p>
                                    </div>
                                `;
                                
                                progressContainer.after(downloadHtml);
                            }
                            
                            // Stop polling when complete
                            clearInterval(pollInterval);
                        } else if (data.status === 'failed') {
                            statusMessage = 'Failed!';
                            
                            // Show error message
                            if (data.errors && data.errors.length) {
                                let errorHtml = '<div class="certificate-error-container"><p>Error processing certificates:</p><ul>';
                                
                                data.errors.forEach(function(error) {
                                    errorHtml += `<li>${error}</li>`;
                                });
                                
                                errorHtml += '</ul><p>Please try again or contact support.</p></div>';
                                
                                progressContainer.after(errorHtml);
                            }
                            
                            // Stop polling when failed
                            clearInterval(pollInterval);
                        }
                        
                        progressStatus.text(statusMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching certificate generation progress:', error);
                }
            });
        }, 3000); // Poll every 3 seconds to reduce server load
    }
});