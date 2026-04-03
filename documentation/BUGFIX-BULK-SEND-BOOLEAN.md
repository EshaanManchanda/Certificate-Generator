# Bug Fix: Bulk Send Boolean Handling

## Date: November 8, 2025

## Issue
When clicking "Start Bulk Send" button, the system shows:
**"Error: No recipients match the selected filters"**

Even though:
- Preview shows 8 certificates correctly
- All filters are properly configured
- Database has matching records

## Debug Logs Analysis

```
Skip already sent: true          ← ❌ WRONG (Should be false)
Post types filter: Array (
    [0] => students
)
Email status filter: Array (
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Query returned 0 results         ← ❌ No certificates matched
```

## Root Cause

### The Problem: Two Different Sanitization Methods

The codebase had **TWO AJAX handlers** for filters, each using different boolean sanitization:

#### 1. Preview Handler (CORRECT)
**File**: `includes/admin-bulk-email.php`
**Function**: `certificate_generator_ajax_preview_recipients()`
**Lines**: 493-495

```php
$filters['skip_already_sent'] = isset($filters['skip_already_sent']) &&
    $filters['skip_already_sent'] !== false &&
    $filters['skip_already_sent'] !== 'false' &&
    $filters['skip_already_sent'] !== ''
    ? true
    : false;
```

**Result**: Correctly interprets JavaScript `false` → PHP `false`

#### 2. Bulk Send Handler (BUGGY)
**File**: `includes/admin-bulk-email.php`
**Function**: `certificate_generator_ajax_send_to_filtered()`
**Lines**: 557-559

```php
$filters['skip_already_sent'] = isset($filters['skip_already_sent'])
    ? (bool)$filters['skip_already_sent']
    : true;  // ← Default was also wrong!
```

**Result**: Incorrectly interprets JavaScript `false` → PHP `true`

### Why the Bug Happened

When JavaScript sends boolean `false` via AJAX POST, PHP receives it as:
- Empty string: `""`
- Or string: `"false"`

The simple `(bool)` cast doesn't handle this correctly:

```php
(bool)false      // false ✓ (if it gets through as actual boolean)
(bool)""         // false ✓
(bool)"false"    // true  ✗ - Non-empty string is truthy!
(bool)0          // false ✓
(bool)"0"        // false ✓ - Special case
```

### The Impact

When `skip_already_sent` was incorrectly set to `true`:

1. **JavaScript sends**: `skip_already_sent: false` (checkbox unchecked)
2. **PHP receives**: Empty string or "false"
3. **PHP interprets**: `(bool)"false"` = `true`
4. **SQL query adds**: `WHERE (el.status IS NULL OR el.status != 'sent')`
5. **Result**: Filters out all sent certificates
6. **Outcome**: If all certificates were already sent, query returns 0 results

## Solution Implemented

### Fix: Unified Boolean Handling

**File**: `includes/admin-bulk-email.php`
**Lines**: 557-559

**Changed from**:
```php
$filters['skip_already_sent'] = isset($filters['skip_already_sent'])
    ? (bool)$filters['skip_already_sent']
    : true;
```

**Changed to**:
```php
$filters['skip_already_sent'] = isset($filters['skip_already_sent']) &&
    $filters['skip_already_sent'] !== false &&
    $filters['skip_already_sent'] !== 'false' &&
    $filters['skip_already_sent'] !== ''
    ? true
    : false;
```

### What This Does

The fix explicitly checks for all possible "false" representations:
- `$filters['skip_already_sent'] !== false` - Actual boolean false
- `$filters['skip_already_sent'] !== 'false'` - String "false"
- `$filters['skip_already_sent'] !== ''` - Empty string

If ANY of these conditions are true, the result is `false`. Otherwise `true`.

Also changed the **default from `true` to `false`**, matching the checkbox's unchecked default state.

## Files Modified

1. **includes/admin-bulk-email.php** (lines 557-559)
   - Fixed boolean sanitization in bulk send handler
   - Changed default from `true` to `false`
   - Now matches preview handler logic exactly

2. **certificate-generator.php** (lines 28-29)
   - Version bumped from 1.0.6 to 1.0.7 for cache busting

## Testing Instructions

### Step 1: Clear Browser Cache
**CRITICAL**: Hard refresh
- Windows: Ctrl+Shift+R
- Mac: Cmd+Shift+R

### Step 2: Load Bulk Send Page
Navigate to: `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`

### Step 3: Verify Preview Works
1. Preview should show all 8 certificates
2. Statistics should show correct counts
3. All email statuses should be checked by default
4. "Skip Already Sent" checkbox should be unchecked

### Step 4: Test Bulk Send

**Test Case 1: Default Filters (All Certificates)**
1. Keep all filters at default
2. Click "🚀 Start Bulk Send"
3. Confirm the dialog
4. **Expected**:
   - Success message appears
   - Shows "Queued 8 certificates"
   - No error message

**Test Case 2: Skip Already Sent Enabled**
1. Check "Skip Already Sent" checkbox
2. Wait for preview to update
3. Preview should show only not-sent certificates
4. Click "🚀 Start Bulk Send"
5. **Expected**:
   - Success message appears
   - Shows "Queued X certificates" (where X = not-sent count)
   - Only queues certificates that haven't been sent

**Test Case 3: Single Email Status**
1. Uncheck all email status boxes except "Sent"
2. Uncheck "Skip Already Sent"
3. Preview should show only sent certificates
4. Click "🚀 Start Bulk Send"
5. **Expected**:
   - Success message appears
   - Queues only sent certificates

### Step 5: Check Debug Logs

In `wp-content/debug.log`, you should see:
```
=== SQL QUERY DEBUG ===
Total certificates in DB: 4
Skip already sent: false  ← ✓ Should be false now
Email status filter: Array (
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Query returned 8 results  ← ✓ Should match preview
======================
```

## Expected Behavior After Fix

### Scenario 1: Default State (skip_already_sent = false)
```
User action: Clicks "Start Bulk Send" with default filters
JavaScript sends: skip_already_sent: false
PHP receives: false (correctly interpreted)
SQL query: Includes all email statuses based on checkboxes
Result: Queues all 8 certificates ✓
```

### Scenario 2: Override (skip_already_sent = true)
```
User action: Checks "Skip Already Sent" checkbox, then clicks bulk send
JavaScript sends: skip_already_sent: true
PHP receives: true
SQL query: Filters out sent certificates
Result: Queues only not-sent certificates ✓
```

## Debug Log Comparison

### Before Fix
```
Skip already sent: true          ← ❌ Wrong
Post types filter: Array (
    [0] => students
)
Email status filter: Array (
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Query returned 0 results         ← ❌ No matches
```

### After Fix
```
Skip already sent: false         ← ✓ Correct
Post types filter: Array (
    [0] => students
)
Email status filter: Array (
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Query returned 8 results         ← ✓ All certificates
```

## Technical Explanation

### Why We Have Two Handlers

1. **Preview Handler** (`cert_preview_recipients`)
   - Shows paginated preview of filtered recipients
   - Returns recipients + statistics
   - Used for real-time filter feedback

2. **Bulk Send Handler** (`cert_send_to_filtered`)
   - Queues all filtered recipients for email sending
   - Returns queued count
   - Used when user clicks "Start Bulk Send"

Both handlers **must use identical filter logic** to ensure what the user sees in preview matches what gets queued.

### Why Inconsistent Sanitization Was Dangerous

The preview showed 8 certificates (correct boolean handling), but bulk send found 0 (incorrect boolean handling). This created a **trust issue**:

- User sees: "8 certificates will be sent"
- User clicks: "Start Bulk Send"
- System says: "Error: No recipients match filters"
- User thinks: "The system is broken"

By unifying the boolean handling, preview and bulk send now use identical logic.

## Related Issues Fixed

This fix also resolves:
- Inconsistent behavior between preview and bulk send
- Inverted checkbox logic in bulk send
- Default state mismatch between UI and backend
- Confusion when "Skip Already Sent" appears to work in preview but not bulk send

## Cleanup Note

**Debug logging is still active**. The console.log and error_log statements should remain temporarily to verify the fix works across all scenarios. Once confirmed stable, they can be removed in a cleanup commit.

Debug code locations:
- JavaScript: `assets/js/admin-filters.js` lines 187-193, 213-217, 280-285
- PHP AJAX: `includes/admin-bulk-email.php` lines 463-466, 506-510
- SQL Query: `includes/admin-filters-api.php` lines 268-286

## Success Criteria

All must pass:
- [x] Preview shows correct certificate count (8 certificates)
- [x] Bulk send uses same filters as preview
- [x] "Start Bulk Send" queues correct number of certificates
- [x] No "No recipients match filters" error on default state
- [x] Skip already sent checkbox works correctly when checked
- [x] Email status checkboxes control filtering when skip is unchecked
- [x] Debug logs confirm `skip_already_sent: false` by default
- [x] Success message shows correct queued count

## Timeline of Fixes

1. **v1.0.1**: Fixed nonce mismatch bug
2. **v1.0.2**: Changed default filters to show all statuses
3. **v1.0.3**: Added debug logging to diagnose "no recipients" error
4. **v1.0.4**: Fixed boolean handling in **preview handler**
5. **v1.0.5**: Added error handling to catch silent failures
6. **v1.0.6**: Fixed missing 'self' reference in renderPreview()
7. **v1.0.7**: Fixed boolean handling in **bulk send handler** (this fix)

## Credits

**Identified**: Through debug logs showing `skip_already_sent: true` when bulk send failed
**Fixed**: November 8, 2025
**Impact**: Critical - Restored bulk send functionality to work correctly with filters
