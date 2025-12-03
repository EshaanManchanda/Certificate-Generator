# Debug: Preview Stuck on "Loading preview..."

## Date: November 8, 2025

## Issue
Preview stays stuck on "Loading preview..." even though:
- PHP backend is working (query returns 8 results)
- AJAX request reaches server successfully
- Filters are correct (skip_already_sent: false)

## Root Cause Hypothesis
The JavaScript success handler is failing silently when trying to render the preview, likely due to:
1. Missing or undefined data properties
2. JavaScript error in renderPreview() function
3. No error handling to catch and display the failure

## Solution Implemented

### Enhanced Error Handling and Logging

#### Fix 1: AJAX Success/Error Handler with Try-Catch
**File**: `assets/js/admin-filters.js`
**Lines**: 211-240

**Added**:
- Console logging of full AJAX response
- Try-catch around renderPreview() call
- Detailed error logging with stack trace
- Enhanced error messages in UI
- AJAX error handler with response text logging

**Benefits**:
- Catches any JavaScript errors during rendering
- Shows error message to user instead of silent failure
- Logs detailed information for debugging

#### Fix 2: Defensive Null Checks in renderPreview()
**File**: `assets/js/admin-filters.js`
**Lines**: 277-301

**Added**:
- Check if data exists before accessing properties
- Check if data.statistics exists (required)
- Check if data.recipients exists (optional, defaults to empty array)
- Early return with error message if data invalid
- Console logging of received data structure

**Benefits**:
- Prevents "Cannot read property 'X' of undefined" errors
- Gracefully handles missing data
- Shows helpful error messages to user
- Logs data structure for debugging

#### Fix 3: PHP Response Logging
**File**: `includes/admin-bulk-email.php`
**Lines**: 506-510

**Added**:
- Log count of recipients being sent
- Log statistics object structure
- Confirms PHP is sending correct data format

**Benefits**:
- Verifies PHP side is working correctly
- Shows what data structure is being sent to JavaScript
- Helps identify if issue is PHP or JavaScript

#### Fix 4: Cache Busting
**File**: `certificate-generator.php`
**Lines**: 28-29
- Version bumped to 1.0.5

## Testing Instructions

### Step 1: Clear Browser Cache
**CRITICAL**: Hard refresh
- Windows: Ctrl+Shift+R
- Mac: Cmd+Shift+R

### Step 2: Load Page with Console Open
1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Navigate to: `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`

### Step 3: Check Console Output

You should see detailed debug output:

**Filter Debug**:
```
=== CERTIFICATE FILTER DEBUG ===
Filters object: {...}
================================
```

**AJAX Success**:
```
=== AJAX SUCCESS ===
Response: {success: true, data: {...}}
Success: true
Data: {recipients: Array(8), statistics: {...}}
==================
```

**Render Preview**:
```
=== RENDER PREVIEW ===
Data received: {recipients: Array(8), statistics: {...}}
Has statistics: true
Has recipients: true
====================
```

### Step 4: Identify the Issue

#### Scenario A: Missing Statistics
**Console shows**:
```
Missing statistics in response
```
**Meaning**: PHP not sending statistics object
**Fix**: Check certificate_generator_get_filter_statistics() function

#### Scenario B: JavaScript Error
**Console shows**:
```
Error rendering preview: TypeError: Cannot read property 'total_certificates' of undefined
Stack trace: ...
```
**Meaning**: Statistics object exists but missing properties
**Fix**: Check statistics object structure

#### Scenario C: AJAX Error
**Console shows**:
```
=== AJAX ERROR ===
Status: error
Error: ...
Response: ...
==================
```
**Meaning**: AJAX request failed
**Fix**: Check PHP errors, nonce verification, permissions

#### Scenario D: Everything Works
**Console shows all debug blocks + preview renders**
**Meaning**: Issue was caching or timing
**Result**: Preview displays certificates

### Step 5: Check WordPress Debug Log

Open `wp-content/debug.log` and look for:

```
=== SENDING RESPONSE ===
Recipients count: 8
Statistics: Array (
    [total_certificates] => 4
    [unique_emails] => 4
    [will_send] => 8
    [will_skip] => 0
    [grouped_sends] => 0
)
=======================
```

This confirms PHP is sending correct data.

## Expected Results After Fix

### If Issue Was Missing Error Handling
- Preview will render correctly now
- No more silent failures
- User sees certificates

### If Issue Was Missing Data
- Clear error message appears: "Invalid response format: missing statistics"
- Console shows exactly what's missing
- Can fix the root cause

### If Issue Was JavaScript Error
- Error message appears with details
- Console shows stack trace
- Can identify and fix the failing code

## Diagnostic Console Patterns

### Pattern 1: Success Path
```
=== CERTIFICATE FILTER DEBUG === ✓
=== AJAX SUCCESS === ✓
=== RENDER PREVIEW === ✓
(Preview renders) ✓
```

### Pattern 2: Missing Statistics
```
=== CERTIFICATE FILTER DEBUG === ✓
=== AJAX SUCCESS === ✓
Missing statistics in response ✗
(Error message shown)
```

### Pattern 3: JavaScript Error
```
=== CERTIFICATE FILTER DEBUG === ✓
=== AJAX SUCCESS === ✓
=== RENDER PREVIEW === ✓
Error rendering preview: ... ✗
(Error message with details shown)
```

### Pattern 4: AJAX Failure
```
=== CERTIFICATE FILTER DEBUG === ✓
=== AJAX ERROR === ✗
(Error message shown)
```

## Files Modified

1. **assets/js/admin-filters.js**
   - Lines 211-240: Enhanced AJAX success/error handlers
   - Lines 277-301: Defensive null checks in renderPreview()

2. **includes/admin-bulk-email.php**
   - Lines 506-510: Response logging

3. **certificate-generator.php**
   - Lines 28-29: Cache busting (v1.0.5)

## Next Steps

Once you share the console output, I can:
1. Identify the exact failure point
2. Apply the specific fix needed
3. Remove debug logging once resolved

## Common Issues and Solutions

### Issue: "Missing statistics in response"
**Cause**: certificate_generator_get_filter_statistics() returning null or not being called
**Solution**: Check admin-filters-api.php for statistics function

### Issue: "Cannot read property 'total_certificates' of undefined"
**Cause**: Statistics object exists but empty
**Solution**: Ensure statistics function returns all required fields

### Issue: AJAX returns HTML instead of JSON
**Cause**: PHP error before wp_send_json_success()
**Solution**: Check PHP error log for fatal errors or warnings

### Issue: AJAX 403 Forbidden
**Cause**: Nonce verification failure
**Solution**: Check nonce is being sent and matches

## Cleanup Note

All debug logging (console.log and error_log) added in this fix should remain temporarily until the issue is resolved. They provide valuable diagnostic information. Once confirmed working, they can be removed in a cleanup commit.

## Success Criteria

- [x] Enhanced error handling prevents silent failures
- [x] Console shows detailed debug information
- [x] Clear error messages appear in UI when issues occur
- [x] Stack traces help identify exact failure points
- [ ] Preview renders successfully (pending verification)
- [ ] User sees certificates in table
- [ ] Statistics display correctly
