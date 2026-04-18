jQuery(document).ready(function($) {
    $('#cg-bulk-generate-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Generating...');

        var progressDiv = $('#cg-bulk-progress');
        var resultsDiv = $('#cg-bulk-results');
        progressDiv.show();
        resultsDiv.hide();

        var data = {
            action: 'cg_bulk_generate_serials',
            nonce: cgBulkSerial.nonce,
            students: $('#cg-bulk-students').is(':checked') ? 1 : 0,
            teachers: $('#cg-bulk-teachers').is(':checked') ? 1 : 0,
            schools: $('#cg-bulk-schools').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: cgBulkSerial.ajaxurl,
            type: 'POST',
            data: data,
            timeout: 300000,
            success: function(response) {
                if (response.success) {
                    var r = response.data;
                    var total = r.success + r.skipped + r.errors;
                    
                    $('.cg-bulk-progress-fill').css('width', '100%');
                    $('.cg-bulk-progress-text').text('Completed! ' + total + ' posts processed.');
                    $('.cg-bulk-progress-detail').text(
                        'Generated: ' + r.success + ' | Skipped (already had serial): ' + r.skipped + ' | Errors: ' + r.errors
                    );

                    $('#cg-bulk-success-count').text(r.success);
                    resultsDiv.show();

                    if (r.details && r.details.length > 0) {
                        var tbody = $('#cg-bulk-recent-serials');
                        tbody.empty();
                        var maxShow = Math.min(r.details.length, 50);
                        for (var i = 0; i < maxShow; i++) {
                            var d = r.details[i];
                            tbody.append(
                                '<tr>' +
                                '<td>' + d.post_id + '</td>' +
                                '<td>' + d.post_type + '</td>' +
                                '<td>' + d.name + '</td>' +
                                '<td><code>' + d.serial + '</code></td>' +
                                '<td>' + d.issued_at + '</td>' +
                                '</tr>'
                            );
                        }
                        if (r.details.length > 50) {
                            tbody.append('<tr><td colspan="5" style="text-align:center;color:#646970;">Showing first 50 of ' + r.details.length + ' results</td></tr>');
                        }
                    }
                } else {
                    $('.cg-bulk-progress-text').text('Error: ' + (response.data || 'Unknown error'));
                    $('.cg-bulk-progress-fill').css('width', '0%').css('background', '#d63638');
                }
                btn.prop('disabled', false).text('Generate Serial Numbers');
            },
            error: function() {
                $('.cg-bulk-progress-text').text('Request failed. Please try again.');
                $('.cg-bulk-progress-fill').css('width', '0%').css('background', '#d63638');
                btn.prop('disabled', false).text('Generate Serial Numbers');
            }
        });
    });
});
