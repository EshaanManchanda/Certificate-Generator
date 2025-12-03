# Certificate Generator - Advanced Features Summary

## Overview

This document summarizes the advanced email grouping and filtering features implemented in the Certificate Generator plugin.

## Feature 1: Email Grouping with ZIP Attachments

### What It Does
- Groups multiple certificates by email address
- Sends only ONE email per unique email address (instead of one per certificate)
- Automatically creates ZIP file when multiple certificates exist for same email
- Attaches single PDF when only one certificate exists
- Skips already-sent certificates when grouping

### How It Works

#### Individual Send Email Button
When you click "Send Email" on any certificate:
1. System searches for ALL certificates (students, teachers, schools) with the same email address
2. Filters out certificates that have already been sent
3. Generates PDFs for all unsent certificates
4. If multiple certificates: Creates ZIP file and attaches it
5. If single certificate: Attaches PDF directly
6. Sends ONE email with all certificates
7. Logs all certificate IDs as sent together

#### Bulk Send
When using bulk send operations:
1. System groups all selected certificates by email address
2. Processes one email per unique address
3. Same ZIP/PDF logic applies
4. Returns statistics: total emails sent, grouped sends count

### Files Modified
- `includes/email-functions.php` - Main email sending logic
- `includes/email-queue.php` - Queue grouping logic

### Key Functions
```php
certificate_generator_create_zip_for_email($certificates_data, $recipient_email)
certificate_generator_send_email($post_id, $log_email = true)
certificate_generator_send_bulk_emails($post_type, $skip_already_sent, $specific_post_ids)
```

## Feature 2: Advanced Filtering System

### What It Does
- Provides comprehensive filtering on bulk send page
- Shows live preview of recipients
- Displays statistics: total certificates, unique emails, will send, will skip, grouped sends
- Allows filtering by: post types, schools, certificate types, email status, specific emails
- Export preview to CSV
- Start bulk send directly from filtered results

### Bulk Send Page Filters

Located at: `wp-admin/options-general.php?page=certificate-bulk-send`

#### Available Filters

1. **Post Types** (Multi-select)
   - Students
   - Teachers
   - Schools

2. **Schools** (Multi-select)
   - Dynamically populated with unique school names
   - Select one or multiple schools

3. **Certificate Types** (Multi-select)
   - Dynamically populated with unique certificate types
   - Select one or multiple types

4. **Email Status** (Checkboxes)
   - ✓ Not Sent (default checked)
   - Sent
   - No Email

5. **Email Search** (Text input)
   - Search for specific email addresses
   - Real-time filtering

6. **Email List** (Textarea)
   - Paste list of emails (comma, newline, semicolon, or space separated)
   - System validates each email
   - Filters to only show certificates for these emails

7. **Skip Already Sent** (Checkbox)
   - When checked: excludes certificates already sent
   - Default: checked

#### Preview Panel

Shows real-time preview with:
- **Statistics Cards**:
  - Total Certificates: All certificates matching filters
  - Unique Emails: Number of unique recipients
  - Will Send: Certificates that will be sent
  - Will Skip: Certificates that will be skipped
  - Grouped Sends: Number of emails with multiple certificates

- **Recipients Table**:
  - Status Icon (colored dot)
  - Name
  - Email
  - School
  - Certificate Type
  - Status Badge (Sent/Pending/No Email)

- **Actions**:
  - Load More: Load next 100 recipients
  - Export CSV: Download all filtered results
  - Start Bulk Send: Queue filtered certificates for sending

### Files Created
- `includes/admin-filters-api.php` - Filter backend logic (370 lines)
- `assets/css/admin-filters.css` - Professional styling (484 lines)
- `assets/js/admin-filters.js` - JavaScript functionality (474 lines)

### Files Modified
- `certificate-generator.php` - Asset enqueuing and API include
- `includes/admin-bulk-email.php` - UI and AJAX handlers

### Key Functions
```php
certificate_generator_get_filtered_recipients($filters)
certificate_generator_get_filter_statistics($filters)
certificate_generator_parse_email_list($email_list_text)
```

### AJAX Endpoints
```javascript
// Get dropdown options
action: 'cert_get_filter_options'

// Preview filtered recipients
action: 'cert_preview_recipients'

// Send to filtered list
action: 'cert_send_to_filtered'
```

## Feature 3: List Page Filters

### What It Does
- Adds dropdown filters above student/teacher/school list tables
- Filter by: School, Certificate Type, Email Status
- "Send to Filtered List" button appears above table
- Shows count of filtered results
- One-click send to all filtered certificates

### List Page Locations
- `wp-admin/edit.php?post_type=students`
- `wp-admin/edit.php?post_type=teachers`
- `wp-admin/edit.php?post_type=schools`

#### Available Filters

1. **Filter by School**
   - Dropdown with all unique schools
   - Shows "All Schools" by default

2. **Filter by Certificate Type**
   - Dropdown with all unique types
   - Shows "All Types" by default

3. **Filter by Email Status**
   - Has Email
   - No Email
   - Sent
   - Not Sent
   - Shows "All Statuses" by default

#### Send to Filtered List Button
- Appears above the list table
- Shows count: "📧 Send to Filtered List (X)"
- Disabled when no results
- Click to queue all filtered certificates
- Uses email grouping (same as bulk send)

### Files Modified
- `includes/admin-columns.php` - Added filter functions (lines 945-1267)

### Key Functions
```php
certificate_generator_add_list_filters($post_type)
certificate_generator_apply_list_filters($query)
certificate_generator_add_send_filtered_button($which)
certificate_generator_filter_posts_join($join, $query)
certificate_generator_filter_posts_where($where, $query)
```

## Technical Details

### Email Grouping Algorithm
```
1. Collect all certificates with filters applied
2. Group certificates by email address
3. For each unique email:
   a. Filter out already-sent certificates
   b. Generate PDFs for all unsent certificates
   c. If count > 1: Create ZIP file
   d. If count = 1: Use PDF directly
   e. Send email with attachment
   f. Log all certificate IDs as sent together
```

### Filter Query Logic
```sql
SELECT DISTINCT p.*
FROM wp_posts p
LEFT JOIN wp_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'email'
LEFT JOIN wp_postmeta pm_school ON p.ID = pm_school.post_id AND pm_school.meta_key = 'school_name'
LEFT JOIN wp_postmeta pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'certificate_type'
LEFT JOIN wp_cert_email_logs log ON p.ID = log.certificate_id AND log.status = 'sent'
WHERE p.post_type IN ('students', 'teachers', 'schools')
  AND p.post_status = 'publish'
  [+ filter conditions]
GROUP BY p.ID
```

### Performance Optimizations
- Debounced filter updates (500ms delay)
- Paginated preview (100 results per page)
- Indexed database queries
- Efficient grouping algorithm

### Security
- Nonce verification on all AJAX requests
- Sanitization of all filter inputs
- Email validation
- Capability checks (manage_options)

## Database Schema

### Email Logs Table
```sql
CREATE TABLE wp_cert_email_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  certificate_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  status VARCHAR(50) NOT NULL,
  sent_date DATETIME NOT NULL,
  INDEX idx_cert_id (certificate_id),
  INDEX idx_email (email),
  INDEX idx_status (status)
)
```

### Email Queue Table
```sql
CREATE TABLE wp_cert_email_queue (
  id INT PRIMARY KEY AUTO_INCREMENT,
  certificate_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  status VARCHAR(50) DEFAULT 'pending',
  attempts INT DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_status (status)
)
```

## Browser Compatibility

### Supported Browsers
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### JavaScript Dependencies
- jQuery (included with WordPress)
- ES6 Classes
- Fetch API
- Blob API

### CSS Features
- CSS Grid
- Flexbox
- CSS Variables
- Media Queries

## Rate Limiting

### Hostinger Limits
- 80 emails per hour
- 10 emails per minute

### Queue Processing
- WP-Cron every 5 minutes
- Batch size: 10 emails per run
- Automatic retry on failure (max 3 attempts)

## Error Handling

### Email Sending Errors
- Logged to email logs table
- Status set to 'failed'
- Error message stored
- User notified via admin notice

### Filter Errors
- Empty results: Shows "No recipients match your filters"
- Invalid email format: Silently ignored
- AJAX failures: User-friendly error message

### ZIP Creation Errors
- Falls back to individual PDFs
- Error logged to WordPress debug log
- User notified if attachment fails

## Backward Compatibility

### Safe for Existing Data
- All existing certificates work unchanged
- Email logs preserved
- Queue entries backward compatible
- No database migration required

### Plugin Deactivation
- Email logs remain in database
- Queue entries remain
- No data loss on deactivation

## File Structure

```
certificate-generator/
├── includes/
│   ├── email-functions.php (modified - email grouping)
│   ├── email-queue.php (modified - queue grouping)
│   ├── admin-filters-api.php (new - filter backend)
│   ├── admin-bulk-email.php (modified - filter UI)
│   └── admin-columns.php (modified - list filters)
├── assets/
│   ├── css/
│   │   └── admin-filters.css (new - filter styles)
│   └── js/
│       └── admin-filters.js (new - filter logic)
└── certificate-generator.php (modified - integration)
```

## Statistics & Metrics

### What Gets Tracked
- Total certificates queued
- Unique emails
- Grouped sends (emails with multiple certificates)
- Already sent count
- Skipped count
- Success/failure rates

### Where to View
- Bulk send page: Real-time statistics panel
- Email logs page: `wp-admin/options-general.php?page=certificate-email-logs`
- List pages: Count in "Send to Filtered List" button

## User Workflow Examples

### Example 1: Send to Specific School
1. Go to Bulk Send page
2. Select "Schools" in Post Types filter
3. Select specific school from Schools dropdown
4. Check "Not Sent" in Email Status
5. Review preview panel
6. Click "Start Bulk Send"
7. System queues emails, groups by email address
8. One email sent per unique address with ZIP if multiple certificates

### Example 2: Send to Email List
1. Go to Bulk Send page
2. Paste list of emails in "Email List" textarea
3. Select all post types
4. Check "Skip Already Sent"
5. Preview shows only matching certificates
6. Click "Start Bulk Send"
7. Only specified emails receive certificates

### Example 3: Filter and Send from List Page
1. Go to Students list page
2. Select school from "Filter by School" dropdown
3. Select certificate type from dropdown
4. Select "Not Sent" from email status
5. Click "Filter"
6. Review filtered count in "Send to Filtered List" button
7. Click button to queue emails
8. Success message appears

## Troubleshooting

### Preview Not Loading
- Check browser console for JavaScript errors
- Verify AJAX nonce is valid
- Check WordPress debug log
- Ensure admin-filters.js is enqueued

### Emails Not Grouping
- Verify email addresses are identical (case-sensitive)
- Check email logs for already-sent status
- Review WordPress debug log for errors
- Test with small batch first

### Filters Not Working
- Clear browser cache
- Verify post meta keys exist (email, school_name, certificate_type)
- Check database for orphaned posts
- Ensure filters are saved correctly

### ZIP Files Not Created
- Check PHP ZipArchive extension installed
- Verify upload directory permissions (wp-content/uploads/certificates/zips/)
- Check available disk space
- Review error logs

## Development Notes

### Adding New Filters
1. Add filter HTML in `admin-bulk-email.php` or `admin-columns.php`
2. Update JavaScript to capture filter value in `admin-filters.js`
3. Add filter logic to `certificate_generator_get_filtered_recipients()` in `admin-filters-api.php`
4. Update SQL query if needed

### Adding New Email Status
1. Define new status constant
2. Update email log function to support status
3. Add checkbox/option in filter UI
4. Update SQL WHERE clause

### Extending Statistics
1. Add calculation in `certificate_generator_get_filter_statistics()`
2. Add stat box HTML in `admin-filters.js` renderPreview()
3. Add CSS styling in `admin-filters.css`

## Version History

### Version 2.0 (Current)
- Added email grouping with ZIP attachments
- Added advanced filtering system
- Added list page filters
- Added real-time preview panel
- Added CSV export
- Added statistics dashboard
- Enhanced AJAX integration

### Version 1.0 (Previous)
- Basic email sending
- Simple bulk send
- Email logging
- Queue system

## Credits

Developed for WordPress Certificate Generator Plugin
