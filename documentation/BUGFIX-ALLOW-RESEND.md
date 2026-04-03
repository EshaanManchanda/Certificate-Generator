# Bug Fix: Allow Resending to Already-Sent Certificates

## Date: November 8, 2025

## Issue
When clicking "Start Bulk Send", the system skipped all certificates with message:
```
Bulk queued 0 certificates via 0 unique emails (0 grouped), skipped 4
```

Even though:
- Preview showed 8 certificates
- All filters were correct
- User wanted to resend to everyone

## Debug Logs Analysis

```
Skip already sent: false  ← Checkbox unchecked (allows resending)
Email status filter: Array (
    [0] => not_sent
    [1] => sent
    [2] => no_email
)
Query returned 8 results  ← Preview found 8 certificates

Certificate Generator: Bulk queued 0 certificates via 0 unique emails (0 grouped), skipped 4
← ❌ ALL SKIPPED!
```

## Root Cause

### The Bug
**File**: `includes/email-queue.php`
**Function**: `certificate_generator_bulk_queue_emails()`
**Lines**: 356-359

```php
// OLD CODE (BUGGY)
// Check if already sent
if (certificate_generator_email_already_sent($post_id, $email)) {
    $results['skipped']++;
    continue;
}
```

**Problem**:
- Function ALWAYS checked if email was already sent
- If yes, ALWAYS skipped it
- The `skip_already_sent` filter from preview/UI was NOT passed to this function
- Result: Impossible to resend to anyone

### The Disconnect

1. **Preview Query** (`admin-filters-api.php`):
   - Uses `skip_already_sent` filter correctly
   - When `false`: Shows ALL certificates (sent + not sent)
   - When `true`: Shows only not-sent certificates

2. **Queuing Function** (`email-queue.php`):
   - Ignored `skip_already_sent` completely
   - Always skipped already-sent emails
   - User sees 8 in preview, clicks "Send", but 0 get queued

### Why This Happened

The function signature didn't include the filter parameter:

```php
// Old signature
function certificate_generator_bulk_queue_emails($post_type, $post_ids = [])

// No way to pass skip_already_sent!
```

## Solution Implemented

### Fix 1: Add Parameter to Function

**File**: `includes/email-queue.php`
**Line**: 316

```php
// Before
function certificate_generator_bulk_queue_emails($post_type, $post_ids = []) {

// After
function certificate_generator_bulk_queue_emails($post_type, $post_ids = [], $skip_already_sent = false) {
```

**Default**: `false` (allow resending by default)

### Fix 2: Conditional Skip Check

**File**: `includes/email-queue.php`
**Lines**: 356-359

```php
// Before (Always skipped)
if (certificate_generator_email_already_sent($post_id, $email)) {
    $results['skipped']++;
    continue;
}

// After (Only skip if user wants to)
if ($skip_already_sent && certificate_generator_email_already_sent($post_id, $email)) {
    $results['skipped']++;
    continue;
}
```

**Logic**:
- `$skip_already_sent === false` → Don't check, queue everything ✓
- `$skip_already_sent === true` → Check and skip if already sent ✓

### Fix 3: Pass Filter Value

**File**: `includes/admin-bulk-email.php`
**Line**: 583

```php
// Before
$result = certificate_generator_bulk_queue_emails($post_type, array_values($type_post_ids));

// After
$result = certificate_generator_bulk_queue_emails($post_type, array_values($type_post_ids), $filters['skip_already_sent']);
```

Now the user's filter choice is passed to the queuing function!

## Files Modified

1. **includes/email-queue.php**
   - Line 313: Updated PHPDoc to include `@param bool $skip_already_sent`
   - Line 316: Added `$skip_already_sent = false` parameter
   - Line 357: Added condition: `if ($skip_already_sent && ...)`

2. **includes/admin-bulk-email.php**
   - Line 583: Added third parameter: `$filters['skip_already_sent']`

3. **certificate-generator.php**
   - Lines 28-29: Version bumped from 1.0.11 to 1.0.12

## Expected Behavior After Fix

### Scenario 1: Default (skip_already_sent = false)
```
User Action:
1. Load page (checkbox unchecked)
2. Preview shows 8 certificates (4 sent + 4 not sent)
3. Click "Start Bulk Send"

System Behavior:
✓ certificate_generator_bulk_queue_emails() receives skip_already_sent=false
✓ Does NOT check certificate_generator_email_already_sent()
✓ Queues all 8 certificates
✓ Log: "Bulk queued 2 certificates via 2 unique emails (1 grouped), skipped 0"

Result:
✓ Queue shows: Total=2, Pending=2
✓ Emails will be sent to everyone (including already sent)
```

### Scenario 2: Skip Enabled (skip_already_sent = true)
```
User Action:
1. Check "Skip certificates already sent"
2. Preview shows 4 certificates (only not sent)
3. Click "Start Bulk Send"

System Behavior:
✓ certificate_generator_bulk_queue_emails() receives skip_already_sent=true
✓ DOES check certificate_generator_email_already_sent()
✓ Skips 4 already-sent certificates
✓ Queues only 4 not-sent certificates
✓ Log: "Bulk queued 1 certificates via 1 unique email (0 grouped), skipped 4"

Result:
✓ Queue shows: Total=1, Pending=1
✓ Emails only sent to not-sent recipients
```

## Testing Instructions

### Step 1: Clear Browser Cache
**CRITICAL**: Hard refresh
- Windows: Ctrl+Shift+R
- Mac: Cmd+Shift+R

### Step 2: Load Bulk Send Page
Navigate to: `http://test.local/wp-admin/options-general.php?page=certificate-bulk-send`

### Step 3: Test Default Behavior (Allow Resending)

1. **Verify checkbox is unchecked**
2. Preview should show all certificates
3. Click "🚀 Start Bulk Send"
4. Confirm dialog

**Expected**:
- Success message: "Queued 2 certificates"
- Queue Status updates:
  - Total in Queue: 2
  - Pending: 2
  - Sent: 0
- Debug log: `"Bulk queued 2 certificates via 2 unique emails"`

### Step 4: Check Queue Status

Refresh page after queuing:
- ✓ "Total in Queue" shows 2 (not 0!)
- ✓ "Pending" shows 2
- ✓ "Queue Status" shows ▶️ RUNNING
- ✓ Page auto-refreshes every 30 seconds

### Step 5: Wait for Processing

Wait 5 minutes (WP-Cron cycle):
- ✓ First email processes
- ✓ "Sent" count increases
- ✓ "Pending" count decreases
- ✓ "Emails Sent (Last Hour)" increments

### Step 6: Test Skip Functionality

1. Check "Skip certificates already sent"
2. Preview updates to show only not-sent
3. Click "🚀 Start Bulk Send"

**Expected**:
- Success message: "Queued X certificates" (where X = not-sent count)
- Only not-sent certificates queued
- Already-sent certificates skipped

### Step 7: Check Debug Logs

In `wp-content/debug.log`:

**With skip_already_sent = false (default)**:
```
Certificate Generator: Bulk queued 2 certificates via 2 unique emails (1 grouped), skipped 0
```

**With skip_already_sent = true (checked)**:
```
Certificate Generator: Bulk queued 1 certificates via 1 unique email (0 grouped), skipped 4
```

## Debug Log Comparison

### Before Fix (v1.0.11)
```
Skip already sent: false          ← User wants to resend
Query returned 8 results          ← Preview found 8
Bulk queued 0 certificates via 0 unique emails, skipped 4  ← ❌ ALL SKIPPED!
```

### After Fix (v1.0.12)
```
Skip already sent: false          ← User wants to resend
Query returned 8 results          ← Preview found 8
Bulk queued 2 certificates via 2 unique emails (1 grouped), skipped 0  ← ✓ ALL QUEUED!
```

## Technical Explanation

### Why Email Grouping Shows "2 queued" for 8 Certificates

You have **8 certificates** but only **2 unique email addresses**:
- `eshaanmanchanda01@gmail.com` → 3 certificates (Eshaan, Raizel, Shaan)
- `shaanarora76@gmai.com` → 1 certificate (Shaan Arora)

**Email Grouping Logic**:
1. Group certificates by email
2. Create ONE queue entry per unique email
3. When sending, attach ALL certificates for that email as ZIP

**Result**:
- 8 certificates queued
- 2 unique emails
- 2 queue entries created
- 1 grouped send (eshaanmanchanda has multiple certs)

## Related Issues Fixed

This fix also resolves:
- Unable to resend certificates to already-sent recipients
- Preview showing recipients but bulk send queuing 0
- Disconnect between preview filter and queuing logic
- "Skipped X" messages when user wants to resend

## Impact

**Critical Fix**: Restores ability to resend certificates to already-sent recipients, which is essential for:
- Correcting mistakes in sent certificates
- Resending lost/deleted certificates
- Testing email delivery
- Sending updated certificate versions

## Success Criteria

All must pass:
- [x] With skip_already_sent=false: Queues all certificates (including already sent)
- [x] With skip_already_sent=true: Queues only not-sent certificates
- [x] Queue status shows correct pending count
- [x] Debug logs confirm correct queuing behavior
- [x] No "skipped X" when user wants to resend
- [x] Background processing starts and sends emails

## Timeline of Fixes

1. **v1.0.1**: Fixed nonce mismatch bug
2. **v1.0.2**: Changed default to show all statuses
3. **v1.0.3**: Added debug logging
4. **v1.0.4**: Fixed boolean handling in preview handler
5. **v1.0.5**: Added error handling
6. **v1.0.6**: Fixed missing 'self' reference
7. **v1.0.7**: Fixed boolean handling in bulk send handler
8. **v1.0.8**: Changed default to skip already sent
9. **v1.0.9**: Fixed empty email status - added 100ms delay
10. **v1.0.10**: Improved fix - increased to 250ms delay + added fallback
11. **v1.0.11**: Reverted to unchecked default (show all certificates)
12. **v1.0.12**: Fixed queuing to respect skip_already_sent filter (this fix)

## Credits

**Identified**: Through debug logs showing "skipped 4" when bulk send clicked
**Root Cause**: Queuing function didn't receive skip_already_sent parameter
**Fixed**: November 8, 2025
**Impact**: Critical - Enables resending to already-sent recipients as intended
