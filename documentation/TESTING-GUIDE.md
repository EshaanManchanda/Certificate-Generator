# Testing Guide - Certificate Generator Advanced Features

## Pre-Testing Checklist

### Environment Verification
- [ ] WordPress site accessible at: `http://test.local`
- [ ] Plugin activated and visible in admin
- [ ] Test certificates exist (students, teachers, schools)
- [ ] Email configuration working (check via WP Mail SMTP or similar)
- [ ] Browser developer tools accessible (F12)

### Required Test Data

Create the following test data if not already present:

1. **Students** (at least 10)
   - 3 students from "Lincoln High School" with email: student@test.com
   - 2 students from "Lincoln High School" with email: student2@test.com
   - 2 students from "Washington Middle School" with email: student3@test.com
   - 3 students with different emails from various schools

2. **Teachers** (at least 5)
   - 2 teachers with email: teacher@test.com
   - 3 teachers with different emails

3. **Schools** (at least 3)
   - 2 schools with email: school@test.com
   - 1 school with different email

### Database Backup
**IMPORTANT**: Before testing, backup your database:
```bash
wp db export test-backup.sql
```

## Test Suite 1: Email Grouping & ZIP Attachments

### Test 1.1: Single Certificate Send
**Objective**: Verify single PDF attachment when only one certificate exists

**Steps**:
1. Go to Students list: `wp-admin/edit.php?post_type=students`
2. Find a student with unique email address (no other certificates with same email)
3. Hover over student name, click "Send Email"
4. Wait for success message

**Expected Results**:
- ✓ Success message appears
- ✓ Email sent with single PDF attachment (NOT ZIP)
- ✓ Email log shows status 'sent'
- ✓ No errors in browser console

**Verification**:
- Check email inbox for PDF attachment
- Go to Email Logs page, verify entry exists
- Check file size is reasonable (not empty)

---

### Test 1.2: Multiple Certificates - Same Email
**Objective**: Verify ZIP creation when multiple certificates share same email

**Steps**:
1. Create 3 students with identical email: `test-group@example.com`
2. Go to first student's edit page
3. Click "Send Email" button
4. Wait for success message

**Expected Results**:
- ✓ Success message appears
- ✓ Email sent with ZIP file attachment (NOT individual PDFs)
- ✓ ZIP contains 3 PDF files
- ✓ All 3 students marked as sent in email logs
- ✓ Only ONE email sent (not 3)

**Verification**:
```sql
SELECT * FROM wp_cert_email_logs
WHERE email = 'test-group@example.com'
ORDER BY sent_date DESC
LIMIT 10;
```
Should show 3 log entries with same sent_date

**Check ZIP Contents**:
- Download ZIP from email
- Verify it contains 3 PDFs
- Verify each PDF opens correctly
- Verify filenames are descriptive

---

### Test 1.3: Skip Already Sent
**Objective**: Verify already-sent certificates are excluded from grouping

**Steps**:
1. Use the same email from Test 1.2: `test-group@example.com`
2. Create 2 MORE students with this email (total now 5 students)
3. Click "Send Email" on one of the new students
4. Wait for success message

**Expected Results**:
- ✓ Email contains ZIP with only 2 PDFs (the new ones)
- ✓ Previous 3 certificates NOT included
- ✓ Email log shows 2 new entries
- ✓ Total of 5 log entries for this email

**Verification**:
```sql
SELECT COUNT(*) as total_sent
FROM wp_cert_email_logs
WHERE email = 'test-group@example.com'
AND status = 'sent';
```
Should return 5

---

### Test 1.4: Mixed Post Types
**Objective**: Verify grouping works across students, teachers, schools

**Steps**:
1. Create 1 student with email: `mixed@example.com`
2. Create 1 teacher with email: `mixed@example.com`
3. Create 1 school with email: `mixed@example.com`
4. Click "Send Email" on student
5. Wait for success message

**Expected Results**:
- ✓ Email sent with ZIP containing 3 PDFs
- ✓ ZIP has 1 student cert, 1 teacher cert, 1 school cert
- ✓ All 3 post types marked as sent

**Verification**:
- Check email logs table for all 3 certificate_ids
- Verify different post types in database

---

### Test 1.5: Bulk Send Grouping
**Objective**: Verify bulk send groups certificates by email

**Steps**:
1. Go to Bulk Send page: `wp-admin/options-general.php?page=certificate-bulk-send`
2. Select "Students" post type
3. Select "All Students" option
4. Ensure "Skip already sent" is checked
5. Click "Start Sending"
6. Wait for completion message

**Expected Results**:
- ✓ Success message shows: "X emails sent, Y grouped sends"
- ✓ Grouped sends count > 0 (if test data has duplicates)
- ✓ Emails with multiple certs received ZIP files
- ✓ Emails with single cert received PDF

**Verification**:
- Count unique emails in students table
- Compare with "emails sent" count
- Should match (not exceed)

---

## Test Suite 2: Advanced Filtering System

### Test 2.1: Post Type Filter
**Objective**: Verify filtering by post types

**Steps**:
1. Go to Bulk Send page: `wp-admin/options-general.php?page=certificate-bulk-send`
2. In Post Types filter, select only "Students"
3. Wait for preview to update (500ms debounce)
4. Observe statistics and preview table

**Expected Results**:
- ✓ Preview shows only students
- ✓ Total Certificates count matches student count
- ✓ Table displays only student records
- ✓ Statistics update within 1 second

**Verification**:
- Check browser Network tab for AJAX request to `cert_preview_recipients`
- Verify response contains only students
- Check "Total Certificates" stat

---

### Test 2.2: School Filter
**Objective**: Verify filtering by specific school

**Steps**:
1. On Bulk Send page, select all post types
2. In Schools filter, select "Lincoln High School"
3. Wait for preview update
4. Review results

**Expected Results**:
- ✓ Only certificates from Lincoln High School appear
- ✓ Statistics reflect filtered count
- ✓ Table shows only matching school names

**Verification**:
- Scroll through preview table
- Verify every row has "Lincoln High School" in School column

---

### Test 2.3: Email Status Filter
**Objective**: Verify filtering by sent/not sent status

**Steps**:
1. Check only "Not Sent" checkbox
2. Uncheck "Sent" and "No Email"
3. Wait for preview update
4. Observe results

**Expected Results**:
- ✓ Only certificates that haven't been sent appear
- ✓ Status badges show "Pending" only
- ✓ "Will Send" stat > 0
- ✓ "Will Skip" stat = 0

**Send some, then retest**:
5. Click "Start Bulk Send" for a small batch
6. Wait for completion
7. Reload page
8. Check "Not Sent" filter again

**Expected Results**:
- ✓ Just-sent certificates removed from preview
- ✓ Count decreased appropriately

---

### Test 2.4: Email Search
**Objective**: Verify searching by specific email

**Steps**:
1. Type "student@test.com" in Email Search box
2. Wait for preview update
3. Observe results

**Expected Results**:
- ✓ Only certificates with matching email appear
- ✓ Partial matches work (e.g., "student" finds all student emails)
- ✓ Search is case-insensitive
- ✓ Statistics update correctly

---

### Test 2.5: Email List Paste
**Objective**: Verify pasting list of emails

**Steps**:
1. Clear all other filters
2. Paste into Email List textarea:
```
student@test.com
teacher@test.com, school@test.com
```
3. Wait for preview update

**Expected Results**:
- ✓ Only certificates matching the 3 emails appear
- ✓ System parses comma and newline delimiters
- ✓ Invalid emails ignored
- ✓ "Unique Emails" stat shows 3

**Test invalid emails**:
4. Add invalid email: "notanemail"
5. Wait for update

**Expected Results**:
- ✓ Invalid email silently ignored
- ✓ No error message
- ✓ Preview unchanged

---

### Test 2.6: Multiple Filters Combined
**Objective**: Verify all filters work together

**Steps**:
1. Select Post Types: "Students", "Teachers"
2. Select Schools: "Lincoln High School"
3. Select Certificate Types: "Honor Roll"
4. Check Email Status: "Not Sent"
5. Type in Email Search: "@test.com"
6. Wait for preview update

**Expected Results**:
- ✓ Preview shows only records matching ALL criteria
- ✓ Statistics reflect combined filters
- ✓ Empty state appears if no matches

**Verification**:
- Manually verify a few rows match all filters
- Check that excluded data doesn't appear

---

### Test 2.7: Statistics Accuracy
**Objective**: Verify statistics calculations

**Steps**:
1. Apply filters that return 10 certificates
2. Ensure 2 of them share same email
3. Observe statistics panel

**Expected Results**:
- ✓ Total Certificates: 10
- ✓ Unique Emails: 9 (10 certs, minus 1 duplicate)
- ✓ Will Send: 10 (or less if some already sent)
- ✓ Grouped Sends: 1 (the email with 2 certs)

**Formula Verification**:
```
Unique Emails = Total Certificates - Duplicate Emails
Grouped Sends = Count of emails with > 1 certificate
```

---

### Test 2.8: Preview Table Pagination
**Objective**: Verify "Load More" button

**Prerequisites**: Have more than 100 certificates in database

**Steps**:
1. Clear all filters to show maximum results
2. Observe preview table
3. Scroll to bottom
4. Click "Load More" button
5. Wait for additional results

**Expected Results**:
- ✓ First 100 results load immediately
- ✓ "Load More" button appears at bottom
- ✓ Clicking loads next 100 results
- ✓ New rows append to existing table (not replace)
- ✓ Button disappears when all results loaded

---

### Test 2.9: CSV Export
**Objective**: Verify export functionality

**Steps**:
1. Apply some filters
2. Wait for preview to load
3. Click "Export CSV" button
4. File downloads automatically

**Expected Results**:
- ✓ CSV file downloads with timestamp in filename
- ✓ File contains columns: Name, Email, School, Certificate Type, Post Type, Status
- ✓ Data matches preview table
- ✓ All rows included (not just visible 100)

**Verification**:
- Open CSV in Excel/Numbers
- Verify column headers
- Verify data formatting
- Check for proper escaping of commas in data

---

### Test 2.10: Start Bulk Send from Filters
**Objective**: Verify sending from filtered preview

**Steps**:
1. Apply filters to narrow down to 5-10 certificates
2. Verify preview shows correct results
3. Click "Start Bulk Send" button
4. Confirm dialog appears
5. Click "OK" to confirm
6. Wait for completion

**Expected Results**:
- ✓ Confirmation dialog appears
- ✓ Button shows "Starting..." during process
- ✓ Success message appears with queue count
- ✓ Preview refreshes after 2 seconds
- ✓ Sent certificates now show "Sent" badge
- ✓ Statistics update to reflect sent status

**Verification**:
```sql
SELECT * FROM wp_cert_email_queue
ORDER BY created_at DESC
LIMIT 20;
```
Should show newly queued entries

---

### Test 2.11: Clear Filters Button
**Objective**: Verify filter reset functionality

**Steps**:
1. Apply multiple filters (schools, types, email search)
2. Click "Clear Filters" button
3. Observe form and preview

**Expected Results**:
- ✓ All dropdowns reset to default
- ✓ Email search cleared
- ✓ Email list textarea cleared
- ✓ Checkboxes reset to defaults
- ✓ Preview updates to show all results
- ✓ Statistics recalculate

---

### Test 2.12: Real-time Updates
**Objective**: Verify debouncing and real-time preview

**Steps**:
1. Type slowly in Email Search: "s-t-u-d-e-n-t"
2. Observe preview panel
3. Type quickly in Email Search (rapid keystrokes)

**Expected Results**:
- ✓ Preview updates 500ms after last keystroke
- ✓ Loading indicator appears during update
- ✓ Multiple rapid changes don't cause multiple AJAX calls
- ✓ Final result reflects latest input

**Verification**:
- Open browser Network tab
- Watch AJAX requests
- Should see debounced calls (not one per keystroke)

---

## Test Suite 3: List Page Filters

### Test 3.1: Dropdown Filters on List Page
**Objective**: Verify filter dropdowns appear and work

**Steps**:
1. Go to Students list: `wp-admin/edit.php?post_type=students`
2. Observe area above table (between bulk actions and table)
3. Locate filter dropdowns

**Expected Results**:
- ✓ 3 dropdowns visible: School, Certificate Type, Email Status
- ✓ Dropdowns populated with unique values
- ✓ "All Schools", "All Types", "All Statuses" as default options

---

### Test 3.2: Filter by School on List Page
**Objective**: Verify school filtering

**Steps**:
1. On Students list page
2. Select "Lincoln High School" from School dropdown
3. Click "Filter" button (WordPress native button)
4. Wait for page reload

**Expected Results**:
- ✓ Page reloads with filtered results
- ✓ Only students from Lincoln High School appear
- ✓ Dropdown remains selected (preserves choice)
- ✓ URL contains: `?filter_school=Lincoln+High+School`

---

### Test 3.3: Filter by Email Status on List Page
**Objective**: Verify email status filtering

**Steps**:
1. Select "Not Sent" from Email Status dropdown
2. Click "Filter"
3. Wait for page reload

**Expected Results**:
- ✓ Only not-sent certificates appear
- ✓ No "Sent" badges visible in table
- ✓ Count in heading reflects filtered count

**Additional Test**:
4. Select "Sent" instead
5. Click "Filter"

**Expected Results**:
- ✓ Only sent certificates appear
- ✓ All rows have "Sent" status

---

### Test 3.4: Multiple Filters Combined on List Page
**Objective**: Verify combined filtering

**Steps**:
1. Select School: "Lincoln High School"
2. Select Certificate Type: "Honor Roll"
3. Select Email Status: "Not Sent"
4. Click "Filter"
5. Wait for page reload

**Expected Results**:
- ✓ Only records matching all 3 criteria appear
- ✓ All filters preserved in URL
- ✓ All dropdowns show selected values

---

### Test 3.5: Send to Filtered List Button
**Objective**: Verify bulk send from filtered list

**Steps**:
1. Apply filters to narrow down to 5 certificates
2. Observe "Send to Filtered List (5)" button above table
3. Click button
4. Confirm dialog
5. Wait for AJAX response

**Expected Results**:
- ✓ Button shows correct count in parentheses
- ✓ Confirmation dialog appears
- ✓ Success message appears: "Queued 5 certificates"
- ✓ Button disabled during processing
- ✓ Email queue populated

**Verification**:
```sql
SELECT COUNT(*) FROM wp_cert_email_queue
WHERE status = 'pending';
```
Should show 5 new entries

---

### Test 3.6: Filtered List Count Accuracy
**Objective**: Verify button count matches filtered results

**Steps**:
1. Apply any combination of filters
2. Count rows in table manually
3. Compare with button count

**Expected Results**:
- ✓ Count in button matches visible rows
- ✓ Count updates when filters change
- ✓ Count is 0 when no results: button disabled

---

### Test 3.7: All Post Types
**Objective**: Verify filters work on all post types

**Repeat Tests 3.1-3.6 for**:
- Teachers: `wp-admin/edit.php?post_type=teachers`
- Schools: `wp-admin/edit.php?post_type=schools`

**Expected Results**:
- ✓ All filters work identically
- ✓ Button appears on all post types
- ✓ Filtering logic consistent

---

## Test Suite 4: Integration & Edge Cases

### Test 4.1: No Email Address
**Objective**: Handle certificates without email

**Steps**:
1. Create student with no email (leave blank)
2. Try to send email to this student
3. Observe result

**Expected Results**:
- ✓ Error message: "No email address found"
- ✓ No email sent
- ✓ No log entry created

**In Filters**:
4. Go to Bulk Send page
5. Check "No Email" status
6. Observe preview

**Expected Results**:
- ✓ No-email certificates appear
- ✓ Badge shows "No Email"
- ✓ Red status icon
- ✓ "Will Skip" count includes these

---

### Test 4.2: Invalid Email Format
**Objective**: Handle invalid email addresses

**Steps**:
1. Create student with invalid email: "notanemail"
2. Try to send email
3. Observe result

**Expected Results**:
- ✓ Email validation catches error
- ✓ Warning message appears
- ✓ No email sent

---

### Test 4.3: Large Dataset Performance
**Objective**: Test with 500+ certificates

**Prerequisites**: Generate test data
```php
// Add to functions.php temporarily
for ($i = 0; $i < 500; $i++) {
    $post_id = wp_insert_post([
        'post_type' => 'students',
        'post_title' => 'Test Student ' . $i,
        'post_status' => 'publish'
    ]);
    update_post_meta($post_id, 'email', 'student' . ($i % 50) . '@test.com');
    update_post_meta($post_id, 'school_name', 'School ' . ($i % 10));
}
```

**Steps**:
1. Go to Bulk Send page
2. Select all post types
3. Wait for preview to load
4. Scroll through results
5. Click "Load More" several times

**Expected Results**:
- ✓ Initial preview loads in < 2 seconds
- ✓ Pagination works smoothly
- ✓ Statistics calculate correctly
- ✓ No browser lag or freezing
- ✓ Memory usage reasonable

---

### Test 4.4: Concurrent Sends
**Objective**: Handle multiple users sending simultaneously

**Steps**:
1. Open two browser windows (different sessions)
2. In both, navigate to same student
3. Click "Send Email" in both windows simultaneously
4. Observe results

**Expected Results**:
- ✓ Both requests process
- ✓ Email sent only once (duplicate detection)
- ✓ No database locking errors
- ✓ Both users see success message

**Verification**:
```sql
SELECT COUNT(*) FROM wp_cert_email_logs
WHERE certificate_id = [student_id];
```
Should be 1 or 2 depending on grouping logic

---

### Test 4.5: Email Queue Processing
**Objective**: Verify queue processes automatically

**Steps**:
1. Queue 20 certificates via bulk send
2. Manually trigger WP-Cron:
```bash
wp cron event run --due-now
```
3. Wait 1 minute
4. Check queue status

**Expected Results**:
- ✓ First 10 emails processed
- ✓ 10 remain in queue
- ✓ Processed emails marked as sent
- ✓ Run cron again, remaining 10 processed

**Verification**:
```sql
SELECT status, COUNT(*)
FROM wp_cert_email_queue
GROUP BY status;
```

---

### Test 4.6: Rate Limiting
**Objective**: Verify rate limits are respected

**Steps**:
1. Queue 100 certificates
2. Monitor queue processing
3. Check send rate

**Expected Results**:
- ✓ No more than 10 emails sent per minute
- ✓ No more than 80 emails sent per hour
- ✓ Queue pauses when limit reached
- ✓ Resumes after cooldown period

---

### Test 4.7: Failed Email Handling
**Objective**: Test failure retry logic

**Steps**:
1. Temporarily break email configuration
2. Queue certificate for sending
3. Trigger queue processing
4. Observe result

**Expected Results**:
- ✓ Email fails to send
- ✓ Status set to 'failed'
- ✓ Error logged
- ✓ Retry attempted (up to 3 times)
- ✓ After 3 failures, removed from queue

**Verification**:
```sql
SELECT * FROM wp_cert_email_queue
WHERE attempts >= 3;
```

---

### Test 4.8: ZIP File Cleanup
**Objective**: Verify temporary ZIPs are cleaned up

**Steps**:
1. Send grouped email (creates ZIP)
2. Check uploads directory:
```
wp-content/uploads/certificates/zips/
```
3. Wait 24 hours (or manually run cleanup)
4. Check directory again

**Expected Results**:
- ✓ ZIP created during send
- ✓ ZIP accessible for download
- ✓ Old ZIPs deleted after 24h
- ✓ No disk space issues from accumulated ZIPs

---

### Test 4.9: Browser Console Errors
**Objective**: Ensure no JavaScript errors

**Steps**:
1. Open browser console (F12)
2. Navigate through all pages
3. Interact with all filters
4. Trigger all AJAX calls
5. Observe console

**Expected Results**:
- ✓ No red error messages
- ✓ No 404 requests for assets
- ✓ No undefined variable warnings
- ✓ AJAX responses return 200 OK

---

### Test 4.10: Mobile Responsiveness
**Objective**: Verify UI works on mobile

**Steps**:
1. Open Chrome DevTools
2. Toggle device toolbar (mobile view)
3. Set viewport to iPhone SE (375px width)
4. Navigate to Bulk Send page
5. Test filters and preview

**Expected Results**:
- ✓ Filters panel stacks vertically
- ✓ Preview panel moves below filters
- ✓ Table scrolls horizontally
- ✓ Buttons remain accessible
- ✓ Touch interactions work
- ✓ No horizontal scrolling on page

---

## Test Suite 5: Security & Permissions

### Test 5.1: Nonce Verification
**Objective**: Verify AJAX endpoints require valid nonce

**Steps**:
1. Open browser console
2. Execute AJAX request without nonce:
```javascript
jQuery.post(ajaxurl, {
    action: 'cert_preview_recipients',
    filters: {}
}, console.log);
```
3. Observe response

**Expected Results**:
- ✓ Request fails
- ✓ Response: "Invalid nonce"
- ✓ No data returned

---

### Test 5.2: Capability Checks
**Objective**: Verify non-admin users can't access

**Steps**:
1. Create user with Subscriber role
2. Log in as subscriber
3. Try to access:
   - Bulk Send page
   - Email Logs page
   - Send Email button
4. Observe results

**Expected Results**:
- ✓ Access denied to all admin pages
- ✓ Send Email button not visible
- ✓ AJAX requests fail with permission error

---

### Test 5.3: SQL Injection Prevention
**Objective**: Verify inputs are sanitized

**Steps**:
1. In Email Search, enter:
```
' OR '1'='1
```
2. Wait for preview update
3. Observe results

**Expected Results**:
- ✓ No SQL error
- ✓ Input treated as literal string
- ✓ No unauthorized data returned
- ✓ Query sanitized properly

---

### Test 5.4: XSS Prevention
**Objective**: Verify output is escaped

**Steps**:
1. Create student with name: `<script>alert('XSS')</script>`
2. View student in preview table
3. Observe rendering

**Expected Results**:
- ✓ Script tag displayed as text (not executed)
- ✓ HTML escaped: `&lt;script&gt;...`
- ✓ No alert popup
- ✓ No console errors

---

## Test Suite 6: Email Content & Templates

### Test 6.1: Email Template Validation
**Objective**: Verify email appears correctly

**Steps**:
1. Send test email with single certificate
2. Check inbox
3. Review email content

**Expected Results**:
- ✓ Subject line present and descriptive
- ✓ Recipient name in greeting
- ✓ Certificate type mentioned
- ✓ PDF attached correctly
- ✓ File size reasonable (not corrupted)
- ✓ Professional formatting

---

### Test 6.2: ZIP Email Template
**Objective**: Verify grouped email template

**Steps**:
1. Send email to address with 3 certificates
2. Check inbox
3. Review email content

**Expected Results**:
- ✓ Subject mentions "certificates" (plural)
- ✓ Body lists all certificate types
- ✓ ZIP file attached
- ✓ ZIP filename descriptive
- ✓ Email explains ZIP contains all certificates

---

### Test 6.3: Email Headers
**Objective**: Verify proper email headers

**Steps**:
1. Send test email
2. View email source/headers
3. Review headers

**Expected Results**:
- ✓ From address set correctly
- ✓ Reply-To address present
- ✓ Content-Type: multipart/mixed
- ✓ Attachment headers correct
- ✓ No spam flags

---

## Post-Testing Checklist

After completing all tests:

### Cleanup
- [ ] Delete test certificates created during testing
- [ ] Clear email queue: `DELETE FROM wp_cert_email_queue WHERE status = 'pending'`
- [ ] Clear test email logs (optional)
- [ ] Remove any test user accounts
- [ ] Clear browser cache and cookies

### Documentation
- [ ] Document any bugs found
- [ ] Note performance metrics
- [ ] Record any edge cases discovered
- [ ] Update user documentation if needed

### Production Readiness
- [ ] All critical tests passed
- [ ] No security vulnerabilities found
- [ ] Performance acceptable with large datasets
- [ ] Email delivery working reliably
- [ ] Error handling robust
- [ ] User experience smooth

## Bug Report Template

If you find issues during testing, document them using this format:

```markdown
### Bug: [Short Description]

**Severity**: Critical / High / Medium / Low

**Test Case**: [Which test revealed this]

**Steps to Reproduce**:
1. Step 1
2. Step 2
3. Step 3

**Expected Result**:
What should happen

**Actual Result**:
What actually happened

**Browser/Environment**:
- Browser: Chrome 120
- WordPress: 6.4
- PHP: 8.1

**Screenshots/Logs**:
[Attach any relevant screenshots or error logs]

**Suggested Fix** (optional):
[If you have ideas on how to fix]
```

## Performance Benchmarks

Record these metrics during testing:

| Metric | Target | Actual | Pass/Fail |
|--------|--------|--------|-----------|
| Initial preview load (< 100 certs) | < 1s | | |
| Initial preview load (500+ certs) | < 2s | | |
| Filter update response time | < 500ms | | |
| CSV export (1000 rows) | < 3s | | |
| ZIP creation (5 PDFs) | < 2s | | |
| Email send (single) | < 3s | | |
| Email send (ZIP) | < 5s | | |
| Queue processing (10 emails) | < 30s | | |

## Support Information

If you need help during testing:

- **Plugin Documentation**: FEATURE-SUMMARY.md
- **WordPress Debug Log**: `wp-content/debug.log`
- **Email Queue Status**: Check `wp_cert_email_queue` table
- **Email Logs**: Check `wp_cert_email_logs` table
- **Browser Console**: F12 → Console tab

## Testing Sign-Off

After completing all tests:

**Tester Name**: ___________________
**Date**: ___________________
**Overall Result**: Pass / Fail / Conditional Pass
**Notes**:
___________________________________________
___________________________________________
___________________________________________

**Ready for Production**: Yes / No / With Reservations
