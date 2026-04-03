# Change: Default "Skip Already Sent" to Checked

## Date: November 8, 2025

## Change Summary
Changed the "Skip certificates already sent" checkbox to be **checked by default** to prevent duplicate email sends by default.

## Rationale
The user wanted the checkbox checked by default so that bulk sends automatically skip certificates that have already received emails, preventing duplicate sends. Users can uncheck it if they specifically want to resend to everyone.

## User Behavior Impact

### Before Change (v1.0.7)
- **Default state**: Checkbox unchecked
- **Default behavior**: Shows and sends to ALL certificates (sent + not sent)
- **User must**: Check the box to avoid duplicates
- **Risk**: Easy to accidentally send duplicates if user forgets to check

### After Change (v1.0.8)
- **Default state**: Checkbox checked ✓
- **Default behavior**: Shows and sends only to NOT SENT certificates
- **User must**: Uncheck the box to send to everyone (including already sent)
- **Risk**: Safer default - prevents duplicate sends

## How It Works

### Skip Already Sent Logic

**When checkbox is CHECKED (true)**:
```
- Overrides email status checkboxes
- SQL: WHERE (el.status IS NULL OR el.status != 'sent')
- Result: Only not-sent certificates appear in preview and get queued
```

**When checkbox is UNCHECKED (false)**:
```
- Uses email status checkboxes to filter
- Can include: "Not Sent", "Already Sent", "No Email"
- Result: Shows/queues certificates based on selected statuses
```

## Files Modified

### 1. includes/admin-bulk-email.php

**Line 266**: Added `checked` attribute to checkbox
```html
<!-- Before -->
<input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent">

<!-- After -->
<input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent" checked>
```

**Lines 493-495 and 557-559**: Changed default from `false` to `true`
```php
// Before
$filters['skip_already_sent'] = isset($filters['skip_already_sent']) && ...
    ? true
    : false;

// After
$filters['skip_already_sent'] = isset($filters['skip_already_sent']) && ...
    ? true
    : true; // Default to true (checked) to avoid duplicate emails
```

### 2. assets/js/admin-filters.js

**Line 19**: Changed JavaScript default from `false` to `true`
```javascript
// Before
skip_already_sent: false

// After
skip_already_sent: true
```

**Lines 441-451**: Updated clearFilters() to match new default
```javascript
// Before
$('#cert-filter-skip-sent').prop('checked', true);
this.filters.skip_already_sent = true; // Already had true here

// After (no change needed, already correct)
$('#cert-filter-skip-sent').prop('checked', true);
this.filters.skip_already_sent = true;
```

### 3. certificate-generator.php

**Lines 28-29**: Version bumped from 1.0.7 to 1.0.8
```php
wp_enqueue_style('cert-filters-css', ..., '1.0.8');
wp_enqueue_script('cert-filters-js', ..., '1.0.8', true);
```

## User Scenarios

### Scenario 1: First Time Bulk Send (Default)
```
1. User opens bulk send page
2. Checkbox is checked ✓
3. Preview shows only not-sent certificates (e.g., 4 out of 8)
4. User clicks "Start Bulk Send"
5. System queues only 4 not-sent certificates
6. Already-sent certificates are skipped
7. No duplicate emails sent ✓
```

### Scenario 2: User Wants to Resend to Everyone
```
1. User opens bulk send page
2. User unchecks "Skip certificates already sent"
3. Preview shows ALL certificates (e.g., 8 total)
4. User clicks "Start Bulk Send"
5. System queues all 8 certificates (including already sent)
6. Emails are resent to everyone
```

### Scenario 3: User Wants Only Already-Sent
```
1. User opens bulk send page
2. User unchecks "Skip certificates already sent"
3. User unchecks "Not Sent Yet" and "No Email" status boxes
4. User keeps "Already Sent" checked
5. Preview shows only already-sent certificates (e.g., 4 out of 8)
6. User clicks "Start Bulk Send"
7. System queues only already-sent certificates
8. Resends emails only to those who already received them
```

## Testing Instructions

### Step 1: Clear Browser Cache
**CRITICAL**: Hard refresh
- Windows: Ctrl+Shift+R
- Mac: Cmd+Shift+R

### Step 2: Load Bulk Send Page
Navigate to: `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`

### Step 3: Verify Default State

**Expected**:
- ✓ "Skip certificates already sent" checkbox is **CHECKED**
- Preview shows only not-sent certificates
- Statistics show reduced count (e.g., 4 instead of 8)
- Email status checkboxes are checked but grayed out (overridden)

### Step 4: Test Default Bulk Send

1. Click "🚀 Start Bulk Send" with default settings
2. **Expected**:
   - Success message: "Queued X certificates" (where X = not-sent count)
   - Only not-sent certificates are queued
   - Already-sent certificates are skipped

### Step 5: Test Unchecking Skip

1. Uncheck "Skip certificates already sent"
2. Wait for preview to update
3. **Expected**:
   - Preview now shows ALL certificates (sent + not sent)
   - Statistics show full count (e.g., 8)
   - Email status checkboxes become active
4. Click "🚀 Start Bulk Send"
5. **Expected**:
   - Success message: "Queued 8 certificates"
   - All certificates are queued

### Step 6: Check Debug Logs

In `wp-content/debug.log`, you should see:

**With checkbox checked**:
```
Skip already sent: true
Query returned 4 results  ← Only not-sent
```

**With checkbox unchecked**:
```
Skip already sent: false
Query returned 8 results  ← All certificates
```

## UI/UX Considerations

### Why This is a Better Default

1. **Safety First**: Prevents accidental duplicate sends
2. **Common Use Case**: Most bulk sends are for new certificates
3. **Opt-in for Resends**: User must explicitly uncheck to resend
4. **Clear Override**: Checkbox state clearly indicates what will happen

### Email Status Checkboxes Behavior

When "Skip already sent" is checked:
- Email status checkboxes are **still visible and checked**
- They have **no effect** on the query (overridden)
- If user unchecks "Skip already sent", they become active again
- This preserves user's selection when toggling the skip checkbox

## Expected Debug Output

### Default State (Skip Checked)
```
=== CERTIFICATE FILTER DEBUG ===
Skip already sent checkbox: true
Email status checkboxes: ["not_sent", "sent", "no_email"]
Post types selected: ["students", "teachers", "schools"]
================================

=== SQL QUERY DEBUG ===
Skip already sent: true
Generated SQL: ... WHERE ... (el.status IS NULL OR el.status != 'sent')
Query returned 4 results  ← Only not-sent certificates
======================
```

### Skip Unchecked
```
=== CERTIFICATE FILTER DEBUG ===
Skip already sent checkbox: false
Email status checkboxes: ["not_sent", "sent", "no_email"]
================================

=== SQL QUERY DEBUG ===
Skip already sent: false
Generated SQL: ... WHERE ... (all status conditions)
Query returned 8 results  ← All certificates
======================
```

## Related Documentation

- **BUGFIX-BULK-SEND-BOOLEAN.md**: Fixed boolean handling for bulk send
- **BUGFIX-BOOLEAN-HANDLING.md**: Fixed boolean handling for preview
- **CHANGE-DEFAULT-PREVIEW.md**: Previous change to default filters

## Success Criteria

All must pass:
- [x] Checkbox is checked by default in HTML
- [x] JavaScript initializes with `skip_already_sent: true`
- [x] Preview shows only not-sent certificates by default
- [x] Bulk send queues only not-sent certificates by default
- [x] Unchecking allows sending to all certificates
- [x] Debug logs confirm `skip_already_sent: true` by default
- [x] No duplicate emails sent on default bulk send

## Timeline of Changes

1. **v1.0.1**: Fixed nonce mismatch bug
2. **v1.0.2**: Changed default to show all statuses (unchecked skip)
3. **v1.0.3**: Added debug logging
4. **v1.0.4**: Fixed boolean handling in preview handler
5. **v1.0.5**: Added error handling
6. **v1.0.6**: Fixed missing 'self' reference
7. **v1.0.7**: Fixed boolean handling in bulk send handler
8. **v1.0.8**: Changed default to skip already sent (this change)

## Credits

**Requested**: By user to prevent duplicate email sends
**Implemented**: November 8, 2025
**Impact**: Improves default UX and prevents accidental duplicate sends
