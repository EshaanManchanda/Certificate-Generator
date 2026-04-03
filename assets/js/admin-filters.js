/**
 * Admin Filters JavaScript
 * Certificate Generator Plugin
 */

(function($) {
    'use strict';

    // Filter Manager Class
    class CertFilterManager {
        constructor() {
            this.filters = {
                post_types: [],
                schools: [],
                certificate_types: [],
                email_status: [],
                emails: [],
                email_search: '',
                skip_already_sent: false
            };
            this.previewData = [];
            this.previewOffset = 0;
            this.previewLimit = 100;
            this.updateTimer = null;
            this.init();
        }

        init() {
            const self = this;
            this.bindEvents();
            this.loadFilterOptions();

            // Delay sync and preview to ensure DOM is fully ready
            setTimeout(() => {
                self.syncInitialValues();
                // Initial preview load
                if ($('.cert-preview-panel').length) {
                    self.updatePreview();
                }
            }, 250); // 250ms delay ensures DOM is ready, with fallback in syncInitialValues
        }

        syncInitialValues() {
            // Sync post types from form defaults
            this.filters.post_types = $('#cert-filter-post-types').val() || ['students', 'teachers', 'schools'];

            // Sync email status from checked checkboxes
            this.filters.email_status = [];
            $('input[name="email_status[]"]:checked').each((i, elem) => {
                this.filters.email_status.push($(elem).val());
            });

            // Fallback: If no checkboxes found, default to all statuses
            if (this.filters.email_status.length === 0) {
                console.warn('No email status checkboxes found, using defaults');
                this.filters.email_status = ['not_sent', 'sent', 'no_email'];
            }

            // Sync skip already sent checkbox
            this.filters.skip_already_sent = $('#cert-filter-skip-sent').is(':checked');
        }

        bindEvents() {
            const self = this;

            // Post type changes
            $('#cert-filter-post-types').on('change', function() {
                self.filters.post_types = $(this).val() || [];
                self.debouncedUpdate();
            });

            // School filter changes
            $('#cert-filter-schools').on('change', function() {
                self.filters.schools = $(this).val() || [];
                self.debouncedUpdate();
            });

            // Certificate type filter changes
            $('#cert-filter-certificate-types').on('change', function() {
                self.filters.certificate_types = $(this).val() || [];
                self.debouncedUpdate();
            });

            // Email status checkboxes
            $('input[name="email_status[]"]').on('change', function() {
                self.filters.email_status = [];
                $('input[name="email_status[]"]:checked').each(function() {
                    self.filters.email_status.push($(this).val());
                });
                self.debouncedUpdate();
            });

            // Email search input
            $('#cert-filter-email-search').on('input', function() {
                self.filters.email_search = $(this).val();
                self.debouncedUpdate();
            });

            // Email list textarea
            $('#cert-filter-email-list').on('input', function() {
                const text = $(this).val();
                self.filters.emails = self.parseEmailList(text);
                self.debouncedUpdate();
            });

            // Skip already sent checkbox
            $('#cert-filter-skip-sent').on('change', function() {
                self.filters.skip_already_sent = $(this).is(':checked');
                self.debouncedUpdate();
            });

            // Clear filters button
            $('#cert-clear-filters').on('click', function(e) {
                e.preventDefault();
                self.clearFilters();
            });

            // Load more button
            $(document).on('click', '.cert-load-more-btn', function(e) {
                e.preventDefault();
                self.loadMorePreview();
            });

            // Export preview button
            $('#cert-export-preview').on('click', function(e) {
                e.preventDefault();
                self.exportPreview();
            });

            // Start bulk send button
            $('#cert-start-bulk-send').on('click', function(e) {
                e.preventDefault();
                self.startBulkSend();
            });
        }

        loadFilterOptions() {
            const self = this;

            // Load unique schools
            $.ajax({
                url: certFilterAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cert_get_filter_options',
                    nonce: certFilterAjax.nonce,
                    option_type: 'schools'
                },
                success: function(response) {
                    if (response.success) {
                        self.populateSelect('#cert-filter-schools', response.data);
                    }
                }
            });

            // Load unique certificate types
            $.ajax({
                url: certFilterAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cert_get_filter_options',
                    nonce: certFilterAjax.nonce,
                    option_type: 'certificate_types'
                },
                success: function(response) {
                    if (response.success) {
                        self.populateSelect('#cert-filter-certificate-types', response.data);
                    }
                }
            });
        }

        populateSelect(selector, options) {
            const $select = $(selector);
            $select.empty();

            if (options && options.length > 0) {
                options.forEach(function(option) {
                    $select.append($('<option>', {
                        value: option,
                        text: option
                    }));
                });
            }
        }

        debouncedUpdate() {
            clearTimeout(this.updateTimer);
            this.updateTimer = setTimeout(() => {
                this.updatePreview();
            }, 500);
        }

        updatePreview() {
            const self = this;
            const $previewContainer = $('.cert-preview-content');

            // Show loading state
            $previewContainer.html('<div class="cert-loading">Loading preview...</div>');

            // Reset offset
            this.previewOffset = 0;

            $.ajax({
                url: certFilterAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cert_preview_recipients',
                    nonce: certFilterAjax.nonce,
                    filters: this.filters,
                    limit: this.previewLimit,
                    offset: this.previewOffset
                },
                success: function(response) {
                    if (response.success) {
                        try {
                            self.previewData = response.data.recipients;
                            self.renderPreview(response.data);
                        } catch (error) {
                            console.error('Error rendering preview:', error);
                            console.error('Stack trace:', error.stack);
                            $previewContainer.html('<div class="cert-error-message">Error rendering preview: ' + error.message + '<br>Check console for details.</div>');
                        }
                    } else {
                        console.error('Response failed:', response.data);
                        $previewContainer.html('<div class="cert-error-message">' + (response.data.message || 'Unknown error') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('=== AJAX ERROR ===');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response:', xhr.responseText);
                    console.error('==================');
                    $previewContainer.html('<div class="cert-error-message">Failed to load preview. Check console for details.</div>');
                }
            });
        }

        loadMorePreview() {
            const self = this;
            this.previewOffset += this.previewLimit;

            $('.cert-load-more').html('<div class="cert-loading">Loading more...</div>');

            $.ajax({
                url: certFilterAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cert_preview_recipients',
                    nonce: certFilterAjax.nonce,
                    filters: this.filters,
                    limit: this.previewLimit,
                    offset: this.previewOffset
                },
                success: function(response) {
                    if (response.success) {
                        const newRecipients = response.data.recipients;
                        self.previewData = self.previewData.concat(newRecipients);
                        self.appendPreviewRows(newRecipients);

                        // Update load more button
                        if (newRecipients.length < self.previewLimit) {
                            $('.cert-load-more').hide();
                        } else {
                            $('.cert-load-more').html('<button class="cert-load-more-btn">Load More</button>');
                        }
                    }
                }
            });
        }

        renderPreview(data) {
            const self = this;

            // Defensive null checks
            if (!data) {
                console.error('No data provided to renderPreview');
                $('.cert-preview-content').html('<div class="cert-error-message">No data received from server</div>');
                return;
            }

            if (!data.statistics) {
                console.error('Missing statistics in response');
                $('.cert-preview-content').html('<div class="cert-error-message">Invalid response format: missing statistics</div>');
                return;
            }

            if (!data.recipients) {
                console.warn('Missing recipients in response, using empty array');
                data.recipients = [];
            }

            const stats = data.statistics;
            const recipients = data.recipients;
            const hasMore = recipients.length >= this.previewLimit;

            let html = '';

            // Statistics
            html += '<div class="cert-preview-stats">';
            html += `<div class="cert-stat-box">
                        <div class="cert-stat-label">Total Certificates</div>
                        <div class="cert-stat-value primary">${stats.total_certificates || 0}</div>
                     </div>`;
            html += `<div class="cert-stat-box">
                        <div class="cert-stat-label">Unique Emails</div>
                        <div class="cert-stat-value">${stats.unique_emails}</div>
                     </div>`;
            html += `<div class="cert-stat-box">
                        <div class="cert-stat-label">Will Send</div>
                        <div class="cert-stat-value success">${stats.will_send}</div>
                     </div>`;
            html += `<div class="cert-stat-box">
                        <div class="cert-stat-label">Will Skip</div>
                        <div class="cert-stat-value warning">${stats.will_skip}</div>
                     </div>`;
            html += `<div class="cert-stat-box">
                        <div class="cert-stat-label">Grouped Sends</div>
                        <div class="cert-stat-value">${stats.grouped_sends}</div>
                     </div>`;
            html += '</div>';

            // Recipients table
            if (recipients.length > 0) {
                html += '<div class="cert-preview-table-wrapper">';
                html += '<table class="cert-preview-table">';
                html += `<thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>School</th>
                                <th>Certificate Type</th>
                                <th>Status</th>
                            </tr>
                         </thead>`;
                html += '<tbody id="cert-preview-tbody">';

                recipients.forEach(function(recipient) {
                    html += self.renderPreviewRow(recipient);
                });

                html += '</tbody>';
                html += '</table>';
                html += '</div>';

                // Load more button
                if (hasMore) {
                    html += '<div class="cert-load-more"><button class="cert-load-more-btn">Load More</button></div>';
                }
            } else {
                html += `<div class="cert-empty-state">
                            <div class="cert-empty-state-icon">📭</div>
                            <div class="cert-empty-state-text">No recipients match your filters</div>
                         </div>`;
            }

            $('.cert-preview-content').html(html);

            // Enable/disable start button
            $('#cert-start-bulk-send').prop('disabled', stats.will_send === 0);
        }

        renderPreviewRow(recipient) {
            const statusIcon = this.getStatusIcon(recipient);
            const statusBadge = this.getStatusBadge(recipient);

            return `<tr>
                        <td>${statusIcon}</td>
                        <td>${this.escapeHtml(recipient.name || '-')}</td>
                        <td>${this.escapeHtml(recipient.email || 'No Email')}</td>
                        <td>${this.escapeHtml(recipient.school_name || '-')}</td>
                        <td>${this.escapeHtml(recipient.certificate_type || '-')}</td>
                        <td>${statusBadge}</td>
                    </tr>`;
        }

        appendPreviewRows(recipients) {
            const self = this;
            let html = '';
            recipients.forEach(function(recipient) {
                html += self.renderPreviewRow(recipient);
            });
            $('#cert-preview-tbody').append(html);
        }

        getStatusIcon(recipient) {
            if (!recipient.email) {
                return '<span class="cert-status-icon no-email" title="No Email"></span>';
            } else if (recipient.email_status === 'sent') {
                return '<span class="cert-status-icon sent" title="Already Sent"></span>';
            } else {
                return '<span class="cert-status-icon pending" title="Pending"></span>';
            }
        }

        getStatusBadge(recipient) {
            if (!recipient.email) {
                return '<span class="cert-status-badge no-email">No Email</span>';
            } else if (recipient.email_status === 'sent') {
                return '<span class="cert-status-badge sent">✓ Sent</span>';
            } else {
                return '<span class="cert-status-badge pending">Pending</span>';
            }
        }

        parseEmailList(text) {
            if (!text) return [];

            // Split by common delimiters
            const emails = text.split(/[\s,;]+/).filter(Boolean);

            // Basic email validation
            const validEmails = emails.filter(function(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
            });

            return validEmails.map(e => e.trim());
        }

        clearFilters() {
            // Reset form fields
            $('#cert-filter-post-types').val(['students', 'teachers', 'schools']);
            $('#cert-filter-schools').val([]);
            $('#cert-filter-certificate-types').val([]);
            $('input[name="email_status[]"]').prop('checked', true); // Check all by default
            $('#cert-filter-email-search').val('');
            $('#cert-filter-email-list').val('');
            $('#cert-filter-skip-sent').prop('checked', false);

            // Reset filter object
            this.filters = {
                post_types: ['students', 'teachers', 'schools'],
                schools: [],
                certificate_types: [],
                email_status: ['not_sent', 'sent', 'no_email'], // All statuses by default
                emails: [],
                email_search: '',
                skip_already_sent: false
            };

            // Update preview
            this.updatePreview();
        }

        exportPreview() {
            // Create CSV content
            let csv = 'Name,Email,School,Certificate Type,Post Type,Status\n';

            this.previewData.forEach(function(recipient) {
                const row = [
                    recipient.name || '',
                    recipient.email || '',
                    recipient.school_name || '',
                    recipient.certificate_type || '',
                    recipient.post_type || '',
                    recipient.email_status || 'pending'
                ];
                csv += row.map(field => `"${field}"`).join(',') + '\n';
            });

            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'certificate-recipients-' + Date.now() + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        startBulkSend() {
            const self = this;

            if (!confirm('Are you sure you want to start sending emails to the filtered recipients?')) {
                return;
            }

            const $button = $('#cert-start-bulk-send');
            $button.prop('disabled', true).text('Starting...');

            $.ajax({
                url: certFilterAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cert_send_to_filtered',
                    nonce: certFilterAjax.nonce,
                    filters: this.filters
                },
                success: function(response) {
                    if (response.success) {
                        $('.cert-preview-content').prepend(
                            '<div class="cert-success-message">' +
                            'Bulk send started! Queued ' + response.data.queued + ' certificates. ' +
                            'Processing will continue in the background.' +
                            '</div>'
                        );
                        // Refresh preview
                        setTimeout(function() {
                            self.updatePreview();
                        }, 2000);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                    $button.prop('disabled', false).text('🚀 Start Bulk Send');
                },
                error: function() {
                    alert('Failed to start bulk send. Please try again.');
                    $button.prop('disabled', false).text('🚀 Start Bulk Send');
                }
            });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.cert-filter-panel').length) {
            window.certFilterManager = new CertFilterManager();
        }
    });

})(jQuery);
