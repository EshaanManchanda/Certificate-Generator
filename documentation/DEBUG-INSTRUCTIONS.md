# Debug Instructions - "No Recipients Match" Issue

## Debug Logging Added

I've added comprehensive debug logging to help identify why the preview shows "No recipients match your filters".

## How to Test

### Step 1: Clear Browser Cache
**CRITICAL**: Clear your browser cache completely
- Chrome/Edge: Ctrl+Shift+Delete → Select "All time" → Clear cached images and files
- Or use Ctrl+Shift+R for hard refresh
- The version number has been updated to 1.0.3 to force reload

### Step 2: Enable WordPress Debug Log
Make sure WordPress debug logging is enabled. Check your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Debug log location: `wp-content/debug.log`

### Step 3: Load the Bulk Send Page
1. Open browser and press F12 to open Developer Tools
2. Go to the Console tab
3. Navigate to: `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`
4. Wait for page to fully load

### Step 4: Check Browser Console Output

You should see output like this:

```
=== CERTIFICATE FILTER DEBUG ===
Filters object: {
  "post_types": ["students", "teachers", "schools"],
  "schools": [],
  "certificate_types": [],
  "email_status": ["not_sent", "sent", "no_email"],
  "emails": [],
  "email_search": "",
  "skip_already_sent": false
}
Skip already sent checkbox: false
Email status checkboxes: ["not_sent", "sent", "no_email"]
Post types selected: ["students", "teachers", "schools"]
================================
```

**What to Look For**:
- ✅ `skip_already_sent` should be `false`
- ✅ `email_status` should be `["not_sent", "sent", "no_email"]`
- ✅ `post_types` should be `["students", "teachers", "schools"]`
- ❌ If any values are `null`, `undefined`, or different - THIS IS THE BUG

### Step 5: Check WordPress Debug Log

Open `wp-content/debug.log` and look for the most recent entries:

```
=== PREVIEW AJAX DEBUG ===
Raw POST filters: Array
(
    [post_types] => Array
        (
            [0] => students
            [1] => teachers
            [2] => schools
        )
    [schools] => Array
        (
        )
    [certificate_types] => Array
        (
        )
    [email_status] => Array
        (
            [0] => not_sent
            [1] => sent
            [2] => no_email
        )
    [emails] => Array
        (
        )
    [email_search] =>
    [skip_already_sent] =>
)
=========================

=== SQL QUERY DEBUG ===
Total certificates in DB: 2
Generated SQL: SELECT DISTINCT p.ID as post_id, p.post_type, ...
Post types filter: Array
(
    [0] => students
    [1] => teachers
    [2] => schools
)
Email status filter: Array
(
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Skip already sent: false
WHERE clauses count: 2
======================

Query returned 2 results
```

**What to Look For**:
- ✅ `Total certificates in DB: X` - Should be greater than 0
- ❌ If 0 - **No certificates exist in database**
- ✅ `Query returned X results` - Should match or be close to total
- ❌ If 0 - **Query logic is filtering out all results**

## Diagnosis Based on Output

### Scenario 1: Total certificates = 0
**Problem**: No certificates in database
**Solution**: Create test certificates (students, teachers, or schools)

### Scenario 2: Total certificates > 0, Query returns 0
**Problem**: SQL query is too restrictive
**Possible causes**:
- Email status filter logic is wrong
- WHERE clauses are conflicting
- JOIN conditions are excluding records

**Look at the SQL in the log** - it will show the exact query being run

### Scenario 3: JavaScript filters are wrong
**Problem**: `syncInitialValues()` not reading form correctly
**Check**:
- Are checkboxes actually checked in HTML?
- Is jQuery selector correct?
- Are values being captured properly?

### Scenario 4: AJAX not reaching server
**Problem**: Request failing before PHP
**Check**:
- Browser Network tab (F12 → Network)
- Look for `admin-ajax.php` request
- Check if it's returning 200 or error (403, 500)

## Common Issues and Solutions

### Issue: skip_already_sent is true when it should be false
**Cause**: JavaScript default doesn't match HTML
**Fix**: Already attempted - verify line 19 of admin-filters.js shows `false`

### Issue: email_status is empty array
**Cause**: Checkboxes not checked or selector wrong
**Fix**: Verify HTML checkboxes have `checked` attribute

### Issue: SQL has impossible condition
**Cause**: Conflicting WHERE clauses
**Example**: `el.status = 'sent' AND el.status != 'sent'`
**Fix**: Already attempted - verify admin-filters-api.php lines 228-230 have override logic

### Issue: No certificates in database
**Cause**: Database is empty
**Fix**: Create test certificates:
```php
// Run this in WordPress admin → Tools → Site Health → Info → Server
$post_id = wp_insert_post([
    'post_type' => 'students',
    'post_title' => 'Test Student',
    'post_status' => 'publish'
]);
update_post_meta($post_id, 'student_name', 'John Doe');
update_post_meta($post_id, 'email', 'test@example.com');
update_post_meta($post_id, 'school_name', 'Test School');
```

## Share Debug Output

After testing, please share:
1. **Browser console output** - Copy/paste the entire debug block
2. **WordPress debug log excerpt** - Copy the three debug blocks (AJAX, SQL)
3. **Screenshot** - If preview still shows "No recipients match your filters"

This will help me identify the exact issue and provide the correct fix.

## Next Steps After Diagnosis

Once we identify the issue from the logs:
1. I'll remove the debug code
2. Apply the permanent fix
3. Test to confirm it's working
4. Clean up and document the solution

## Debug Code Locations

If you want to review the debug code:
- **JavaScript**: `assets/js/admin-filters.js` lines 187-193
- **PHP AJAX**: `includes/admin-bulk-email.php` lines 463-466
- **SQL Query**: `includes/admin-filters-api.php` lines 268-286

## Temporary Debug Code

**IMPORTANT**: This debug code is temporary and will be removed once we identify the issue. It writes to logs on every page load, which could grow the log file large if left in production.
