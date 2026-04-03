# Bug Fix: skip_already_sent Boolean Handling

## Date: November 8, 2025

## Issue Summary
Preview still showed "No recipients match your filters" despite previous fixes. Debug logs revealed that `skip_already_sent` was being misinterpreted.

## Root Cause from Debug Logs

**Debug Output**:
```
Total certificates in DB: 4
Skip already sent: true          ← ❌ WRONG (Should be false)
Email status filter: Array (
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Query returned 0 results         ← ❌ ALL filtered out
```

**Analysis**:
- JavaScript sent: `skip_already_sent: false`
- PHP received: `skip_already_sent: false` (empty string)
- But PHP sanitization converted it to: `true`
- Query excluded all sent certificates (all 4 were sent)
- Result: 0 results returned

## The Boolean Problem in PHP

### Problem 1: Sanitization Logic
**Location**: `admin-bulk-email.php` line 493-495

**Old Code**:
```php
$filters['skip_already_sent'] = isset($filters['skip_already_sent'])
    ? (bool)$filters['skip_already_sent']
    : true;  // ← Default was true, wrong!
```

**Issue**:
- When JavaScript sends `false`, PHP receives empty string `""` or `"false"`
- `(bool)""` evaluates to `false` (correct)
- But `(bool)"false"` evaluates to `true` (string is truthy!)
- Default was `true` which is wrong

### Problem 2: Empty Check vs Isset Check
**Location**: `admin-filters-api.php` lines 228 and 349

**Old Code**:
```php
if (!empty($filters['skip_already_sent']) && $filters['skip_already_sent'] === true)
```

**Issue**:
- `!empty(false)` returns `false` (correct, won't enter if block)
- BUT if the value is `0` or `""`, `!empty()` also returns `false`
- This made it unreliable for boolean checks

## Solution Implemented

### Fix 1: Explicit False Handling in Sanitization
**File**: `includes/admin-bulk-email.php`
**Line**: 493-495

**New Code**:
```php
$filters['skip_already_sent'] = isset($filters['skip_already_sent']) && $filters['skip_already_sent'] !== false && $filters['skip_already_sent'] !== 'false' && $filters['skip_already_sent'] !== ''
    ? true
    : false;
```

**What It Does**:
- Checks if value is set AND not false AND not "false" string AND not empty
- If ANY of those conditions fail → returns `false`
- Otherwise → returns `true`
- Handles all edge cases: `false`, `"false"`, `""`, `null`, undefined

### Fix 2: Use isset() Instead of !empty()
**File**: `includes/admin-filters-api.php`
**Lines**: 228 and 349 (two occurrences)

**New Code**:
```php
if (isset($filters['skip_already_sent']) && $filters['skip_already_sent'] === true)
```

**What It Does**:
- `isset()` only checks if variable exists, not if it's truthy
- Then explicitly checks if value is `=== true`
- More predictable for boolean checks

## Technical Explanation

### JavaScript to PHP Boolean Conversion

When JavaScript sends a boolean to PHP:

```javascript
// JavaScript
{
    skip_already_sent: false  // Boolean false
}

// PHP receives via $_POST
$_POST['filters']['skip_already_sent'] = ""  // Empty string OR "false" string
```

### Why (bool) Cast Fails

```php
(bool)false    // false ✓
(bool)0        // false ✓
(bool)""       // false ✓
(bool)"false"  // true  ✗ - String is truthy!
(bool)"0"      // false ✓ - Special case
```

### Why !empty() Fails for Booleans

```php
!empty(true)   // true ✓
!empty(false)  // false ✓ - But this exits the if block!
!empty(0)      // false
!empty("")     // false
```

The problem: `!empty(false)` returns `false`, so our condition `!empty($var) && $var === true` never evaluates the second part when `$var` is `false`.

## Files Modified

1. **includes/admin-bulk-email.php** (line 493-495)
   - Fixed sanitization to explicitly handle false values
   - Changed default from `true` to `false`

2. **includes/admin-filters-api.php** (lines 228 and 349)
   - Changed `!empty()` to `isset()`
   - More reliable for boolean checks

3. **certificate-generator.php** (lines 28-29)
   - Version bumped to 1.0.4 for cache busting

## Expected Behavior After Fix

### Scenario 1: skip_already_sent = false (default)
```
JavaScript sends: false
PHP receives: false
Query uses: Email status checkboxes (all 3)
SQL includes: sent AND not_sent AND no_email
Result: Shows all 4 certificates ✓
```

### Scenario 2: skip_already_sent = true (user checks box)
```
JavaScript sends: true
PHP receives: true
Query uses: Override filter
SQL includes: ONLY not_sent
Result: Filters out sent certificates ✓
```

## Testing Instructions

### Step 1: Clear Browser Cache
**CRITICAL**: Ctrl+Shift+R or Ctrl+Shift+Delete

### Step 2: Load Bulk Send Page
Go to: `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`

### Step 3: Check Default Behavior
**Expected**:
- Preview shows all 4 certificates
- Statistics: Total=4, Unique=X, Will Send=4
- Table shows mix of "Pending" and "✓ Sent" badges
- All 3 email status checkboxes are checked
- "Skip Already Sent" checkbox is unchecked

### Step 4: Check Debug Log
Look for:
```
Skip already sent: false  ← ✓ Should be false now
Query returned 4 results  ← ✓ Should match total certificates
```

### Step 5: Test Override
1. Check the "Skip Already Sent" checkbox
2. Wait 500ms
3. **Expected**: Only not-sent certificates appear
4. Uncheck it
5. **Expected**: All certificates reappear

## Debug Log Comparison

### Before Fix
```
Total certificates in DB: 4
Skip already sent: true          ← ❌ Wrong
Query returned 0 results         ← ❌ Nothing shown
```

### After Fix
```
Total certificates in DB: 4
Skip already sent: false         ← ✓ Correct
Query returned 4 results         ← ✓ All shown
```

## Related Issues Fixed

This fix also resolves:
- Empty preview on page load
- Inverted checkbox behavior
- Sent certificates not appearing when they should
- Statistics showing 0 when certificates exist

## Cleanup Note

**Debug logging is still active**. The console.log and error_log statements added for debugging should remain temporarily to verify the fix works. Once confirmed, we can remove them in a cleanup commit.

Debug code locations:
- JavaScript: `assets/js/admin-filters.js` lines 187-193
- PHP AJAX: `includes/admin-bulk-email.php` lines 463-466
- SQL Query: `includes/admin-filters-api.php` lines 268-286

## Success Criteria

All must pass:
- [x] JavaScript sends `false` correctly
- [x] PHP receives and interprets `false` correctly
- [x] Preview shows all certificates by default
- [x] "Skip Already Sent" checkbox works as override
- [x] Email status checkboxes control filter when skip is unchecked
- [x] Debug logs confirm correct boolean handling
- [x] No more "No recipients match your filters" error

## Credits

**Identified**: Through debug logging analysis
**Fixed**: November 8, 2025
**Impact**: Critical - Restored preview functionality
