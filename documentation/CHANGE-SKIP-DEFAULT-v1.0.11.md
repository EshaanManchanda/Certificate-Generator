# Change: Revert "Skip Already Sent" to Unchecked by Default

## Date: November 8, 2025

## Change Summary
Reverted the "Skip certificates already sent" checkbox to be **unchecked by default** (v1.0.8 behavior reverted).

## Rationale
User requested to **show all certificates by default** regardless of sent status, allowing them to resend to everyone if needed. The checkbox should be an opt-in filter, not a default behavior.

### User's Use Case
- Often needs to resend emails to everyone (including already sent)
- Showing 0 results when all certificates are sent is confusing
- Wants to see full list by default and manually check the box to filter

## User Behavior Impact

### Before Change (v1.0.8-1.0.10)
- **Default state**: Checkbox checked ✓
- **Default behavior**: Shows only NOT SENT certificates
- **Problem**: If all certificates sent → shows 0 results → user confused
- **To resend**: User must uncheck box

### After Change (v1.0.11)
- **Default state**: Checkbox unchecked
- **Default behavior**: Shows ALL certificates (sent + not sent)
- **Result**: Always shows full list → user can see everything
- **To skip sent**: User checks the box (opt-in)

## How It Works Now

### Checkbox Unchecked (Default)
```
- Uses email status checkboxes for filtering
- All 3 status boxes checked by default
- SQL: Includes not_sent OR sent OR no_email
- Result: Shows all certificates in preview
```

### Checkbox Checked (User opts in)
```
- Overrides email status checkboxes
- SQL: WHERE (el.status IS NULL OR el.status != 'sent')
- Result: Filters out already-sent certificates
```

## Files Modified

### 1. includes/admin-bulk-email.php

**Line 266**: Removed `checked` attribute from checkbox
```html
<!-- Before (v1.0.8-1.0.10) -->
<input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent" checked>

<!-- After (v1.0.11) -->
<input type="checkbox" name="skip_already_sent" id="cert-filter-skip-sent">
```

**Lines 495 and 559**: Changed default from `true` to `false`
```php
// Before (v1.0.8-1.0.10)
$filters['skip_already_sent'] = isset($filters['skip_already_sent']) && ...
    ? true
    : true; // Default to true (checked) to avoid duplicate emails

// After (v1.0.11)
$filters['skip_already_sent'] = isset($filters['skip_already_sent']) && ...
    ? true
    : false; // Default to false (unchecked) to show all certificates
```

### 2. assets/js/admin-filters.js

**Line 19**: Changed JavaScript default from `true` to `false`
```javascript
// Before (v1.0.8-1.0.10)
skip_already_sent: true

// After (v1.0.11)
skip_already_sent: false
```

**Line 462**: Updated clearFilters() to match new default
```javascript
// Before (v1.0.8-1.0.10)
$('#cert-filter-skip-sent').prop('checked', true);
this.filters.skip_already_sent = true;

// After (v1.0.11)
$('#cert-filter-skip-sent').prop('checked', false);
this.filters.skip_already_sent = false;
```

### 3. certificate-generator.php

**Lines 28-29**: Version bumped from 1.0.10 to 1.0.11
```php
wp_enqueue_style('cert-filters-css', ..., '1.0.11');
wp_enqueue_script('cert-filters-js', ..., '1.0.11', true);
```

## User Scenarios

### Scenario 1: View All Certificates (Default)
```
1. User opens bulk send page
2. Checkbox is unchecked
3. Preview shows all 8 certificates (4 sent + 4 not sent)
4. Statistics: Total=8, Will Send=8
5. Table shows mix of "✓ Sent" and "Pending" badges
```

### Scenario 2: Skip Already Sent (User opts in)
```
1. User opens bulk send page
2. User checks "Skip certificates already sent"
3. Preview updates to show only not-sent (4 certificates)
4. Statistics: Total=4, Will Send=4
5. Table shows only "Pending" badges
```

### Scenario 3: Resend to Everyone
```
1. User opens bulk send page (default: unchecked)
2. Preview shows all 8 certificates
3. User clicks "Start Bulk Send"
4. System queues all 8 certificates (including already sent)
5. Resends emails to everyone
```

### Scenario 4: Send Only to Not-Sent
```
1. User opens bulk send page
2. User checks "Skip certificates already sent"
3. Preview shows only not-sent certificates
4. User clicks "Start Bulk Send"
5. System queues only not-sent certificates
6. Skips resending to already-sent certificates
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
- "Skip certificates already sent" checkbox is **UNCHECKED**
- Preview shows ALL certificates (8 total)
- Statistics show full count
- Table shows mix of "✓ Sent" and "Pending" statuses
- All 3 email status checkboxes are checked

### Step 4: Test Default Bulk Send

1. Click "🚀 Start Bulk Send" with default settings
2. **Expected**:
   - Success message: "Queued 8 certificates"
   - All certificates queued (including already sent)
   - Resends emails to everyone

### Step 5: Test Skip Functionality

1. Check "Skip certificates already sent"
2. Wait for preview to update
3. **Expected**:
   - Preview shows only not-sent certificates (4 out of 8)
   - Statistics show reduced count
   - Only "Pending" badges visible
4. Click "🚀 Start Bulk Send"
5. **Expected**:
   - Success message: "Queued 4 certificates"
   - Only not-sent certificates queued

### Step 6: Check Debug Logs

In `wp-content/debug.log`:

**Default state (unchecked)**:
```
Skip already sent: false
Email status filter: Array (
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Query returned 8 results  ← All certificates
```

**With checkbox checked**:
```
Skip already sent: true
Query returned 4 results  ← Only not-sent
```

## Expected Debug Output

### Page Load (Default)
```
=== CERTIFICATE FILTER DEBUG ===
Skip already sent checkbox: false        ← ✓ Unchecked
Email status checkboxes: ["not_sent", "sent", "no_email"]
================================

=== SQL QUERY DEBUG ===
Skip already sent: false                 ← ✓ False
Generated SQL: ... WHERE ... ((el.status IS NULL OR el.status != 'sent') OR el.status = 'sent' OR ...)
Query returned 8 results                 ← ✓ All certificates
======================
```

### With Skip Checked
```
=== CERTIFICATE FILTER DEBUG ===
Skip already sent checkbox: true         ← ✓ User checked it
================================

=== SQL QUERY DEBUG ===
Skip already sent: true                  ← ✓ True
Generated SQL: ... WHERE ... (el.status IS NULL OR el.status != 'sent')
Query returned 4 results                 ← ✓ Only not-sent
======================
```

## Comparison: v1.0.8 vs v1.0.11

| Aspect | v1.0.8-1.0.10 | v1.0.11 |
|--------|---------------|---------|
| **Checkbox default** | Checked ✓ | Unchecked |
| **Default preview** | Not-sent only | All certificates |
| **Default bulk send** | Skips sent | Includes sent |
| **Use case** | Prevent duplicates | Allow resends |
| **When all sent** | Shows 0 results | Shows all certificates |
| **User must** | Uncheck to resend | Check to skip |

## Related Documentation

- **CHANGE-SKIP-DEFAULT.md**: Original v1.0.8 change (now reverted)
- **BUGFIX-BULK-SEND-BOOLEAN.md**: Boolean handling fix
- **BUGFIX-BOOLEAN-HANDLING.md**: Preview boolean handling

## Success Criteria

All must pass:
- [x] Checkbox is unchecked by default in HTML
- [x] JavaScript initializes with `skip_already_sent: false`
- [x] Preview shows all certificates by default (8 total)
- [x] Bulk send queues all certificates by default
- [x] Checking box filters to only not-sent certificates
- [x] Debug logs confirm `skip_already_sent: false` by default
- [x] No "No recipients match your filters" error on page load

## Timeline of Changes

1. **v1.0.1**: Fixed nonce mismatch bug
2. **v1.0.2**: Changed default to show all statuses
3. **v1.0.3**: Added debug logging
4. **v1.0.4**: Fixed boolean handling in preview handler
5. **v1.0.5**: Added error handling
6. **v1.0.6**: Fixed missing 'self' reference
7. **v1.0.7**: Fixed boolean handling in bulk send handler
8. **v1.0.8**: Changed default to skip already sent (CHECKED)
9. **v1.0.9**: Fixed empty email status - added 100ms delay
10. **v1.0.10**: Improved fix - increased to 250ms delay + added fallback
11. **v1.0.11**: Reverted to unchecked default (UNCHECKED) - this change

## Credits

**Requested**: By user to show all certificates by default
**Implemented**: November 8, 2025
**Impact**: Improves UX by showing full list and allowing resends by default
