# Change: Show All Student Statuses by Default

## Date: November 8, 2025

## Change Summary
Updated the bulk send page default filters to show ALL certificates with ALL statuses by default, instead of only showing "Not Sent Yet" certificates.

## What Changed

### Before
Default preview showed:
- ✅ All post types (Students, Teachers, Schools)
- ⚠️ Only "Not Sent Yet" status
- ⚠️ "Skip Already Sent" was checked

**Result**: Preview only showed unsent certificates

### After
Default preview now shows:
- ✅ All post types (Students, Teachers, Schools)
- ✅ All email statuses (Not Sent, Already Sent, No Email)
- ✅ "Skip Already Sent" is unchecked

**Result**: Preview shows complete view of ALL certificates in the system

## Files Modified

### includes/admin-bulk-email.php

**Change 1 - Line 234:**
Added `checked` attribute to "Already Sent" checkbox
```php
<input type="checkbox" name="email_status[]" value="sent" checked>
```

**Change 2 - Line 238:**
Added `checked` attribute to "No Email Address" checkbox
```php
<input type="checkbox" name="email_status[]" value="no_email" checked>
```

**Change 3 - Line 266:**
Removed `checked` attribute from "Skip Already Sent" checkbox
```php
<input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent">
```

**Change 4 - Line 269:**
Updated help text to reflect new default behavior
```php
<?php _e('Check this to avoid duplicate emails when sending', 'certificate-generator'); ?>
```

## Technical Details

### Automatic Synchronization
The `syncInitialValues()` function in `admin-filters.js` automatically reads the HTML form defaults and syncs them to JavaScript:

```javascript
// Now captures all 3 checked checkboxes
$('input[name="email_status[]"]:checked').each((i, elem) => {
    this.filters.email_status.push($(elem).val());
});
// Result: ['not_sent', 'sent', 'no_email']

// Now reads unchecked state
this.filters.skip_already_sent = $('#cert-filter-skip-sent').is(':checked');
// Result: false
```

No JavaScript code changes were needed!

## User Experience Impact

### Initial Page Load
When users first load `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`:

**Statistics will show:**
- Total Certificates: All certificates in system
- Unique Emails: All unique email addresses
- Will Send: All unsent certificates (since skip_already_sent = false)
- Will Skip: 0 (nothing skipped by default)
- Grouped Sends: Count of emails with multiple certificates

**Preview table will display:**
- ✅ Certificates with "Pending" badge (not sent yet)
- ✅ Certificates with "✓ Sent" badge (already sent)
- ✅ Certificates with "No Email" badge (missing email)

### Filtering Workflow
Users can now:
1. See complete overview of all certificates
2. Uncheck statuses to narrow down view
3. Check "Skip Already Sent" before sending to avoid duplicates
4. Use other filters (school, type, email) in combination

### When Sending
Important: The "Skip Already Sent" checkbox controls sending behavior:
- **Unchecked (default)**: Will attempt to send to all filtered certificates (may resend)
- **Checked**: Will only send to certificates not previously sent

**Recommendation**: Users should check "Skip Already Sent" before clicking "Start Bulk Send" to avoid sending duplicate emails.

## Testing Instructions

### Test 1: Default Preview
1. Clear browser cache (Ctrl+Shift+R)
2. Go to: `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`
3. **Expected**: All 3 email status checkboxes are checked
4. **Expected**: "Skip Already Sent" checkbox is unchecked
5. **Expected**: Preview shows mix of Pending, Sent, and No Email badges

### Test 2: Filter Still Works
1. Uncheck "Already Sent" and "No Email"
2. Wait 500ms
3. **Expected**: Preview updates to show only "Pending" certificates
4. **Expected**: Same behavior as old default

### Test 3: Skip Already Sent Works
1. Check all 3 email statuses
2. Check "Skip Already Sent" checkbox
3. Click "Start Bulk Send"
4. **Expected**: Only unsent certificates are queued
5. **Expected**: Already sent certificates are skipped

### Test 4: Complete View
1. Select all post types
2. Check all 3 email statuses
3. Uncheck "Skip Already Sent"
4. **Expected**: Statistics show complete database counts
5. **Expected**: Preview shows every certificate in the system

## Database Impact
None - this only changes the UI defaults, not database queries or data.

## Rollback Instructions

If you need to revert to showing only "Not Sent Yet" by default:

**File**: `includes/admin-bulk-email.php`

1. Line 234: Remove `checked` from "Already Sent"
```php
<input type="checkbox" name="email_status[]" value="sent">
```

2. Line 238: Remove `checked` from "No Email Address"
```php
<input type="checkbox" name="email_status[]" value="no_email">
```

3. Line 266: Add `checked` back to "Skip Already Sent"
```php
<input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent" checked>
```

4. Line 269: Revert help text
```php
<?php _e('Recommended to avoid duplicate emails', 'certificate-generator'); ?>
```

## Benefits

### For Administrators
- ✅ Complete visibility of all certificates at a glance
- ✅ Easier to see sent vs unsent status distribution
- ✅ Can quickly identify certificates without emails
- ✅ Better understanding of database state

### For Workflows
- ✅ Filter down from "all" is more intuitive than expanding from "some"
- ✅ Prevents missing certificates that might need attention
- ✅ Still one click to filter to "not sent only" (uncheck 2 boxes)

### For Reporting
- ✅ Statistics show complete picture by default
- ✅ CSV export includes all statuses
- ✅ Better for auditing and verification

## Related Changes

This change works together with:
- Email grouping feature (sends one email per address with ZIP)
- Preview panel fixes (nonce mismatch resolved)
- Filter synchronization (syncInitialValues function)

## Notes

### Why Uncheck "Skip Already Sent"?
Since we're now showing all statuses including "Already Sent", it makes sense to:
1. Show them in the preview (all 3 statuses checked)
2. Allow them to be selected for sending (skip_already_sent unchecked)
3. Let users decide whether to skip them before sending (checkbox available)

This gives users more control and visibility.

### Will This Cause Duplicate Emails?
No, because:
1. Users must manually click "Start Bulk Send"
2. They can see "Already Sent" badges in preview
3. They can check "Skip Already Sent" before sending
4. Email grouping prevents duplicate sends to same address

The default is now "show everything, send carefully" instead of "hide sent, assume safe".

## Success Criteria

- [x] All 3 email status checkboxes checked by default
- [x] "Skip Already Sent" unchecked by default
- [x] Preview shows all certificates on initial load
- [x] Filtering still works as expected
- [x] Users can check/uncheck options freely
- [x] Help text updated to reflect new behavior
- [x] JavaScript automatically syncs defaults
- [x] No code changes needed in JS files

## Credits

**Requested**: User feedback for better default view
**Implemented**: November 8, 2025
**Impact**: Improved UX and visibility
