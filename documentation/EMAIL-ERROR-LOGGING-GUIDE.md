# Email Error Logging Guide

## Overview
Enhanced error logging has been added to the Certificate Generator plugin to help diagnose email sending failures.

## What's Been Added

### 1. **Enhanced PHPMailer Error Capture**
- Captures detailed PHPMailer error messages
- Logs mailer type (mail, smtp, sendmail)
- Records SMTP host information if available
- Tracks PHP errors during email sending

### 2. **WordPress wp_mail_failed Hook**
- Automatically logs all wp_mail() failures
- Captures WP_Error objects with full details
- Logs error codes, messages, and additional data

### 3. **Retry Attempt Logging**
- Logs each of the 3 email attempts
- Shows exponential backoff timing (1s, 2s, 4s)
- Identifies permanent failures vs. temporary failures
- Tracks all errors from all attempts

### 4. **Email Configuration Logging**
- Logs recipient email
- Shows From email address being used
- Indicates if SMTP plugin is active
- Shows PHP mail() availability
- Lists attachment paths

### 5. **Admin Health Check Notice**
- Displays on certificate-related admin pages
- Shows current email configuration
- Lists detected issues
- Provides recommendations
- Links to install SMTP plugin

## Where to Find Error Logs

### Option 1: Server Error Logs (Primary)
All errors are logged using PHP's `error_log()` function. Location varies by host:

**Local by Flywheel:**
- `C:\Users\[username]\AppData\Local\Local\logs\php-errors.log`
- Or check Local by Flywheel's "Logs" tab in the site view

**Shared Hosting (Hostinger, etc.):**
- Usually in your hosting control panel under "Error Logs" or "Logs"
- Sometimes in `/home/username/error_log` or `/home/username/public_html/error_log`

**Other Environments:**
- Check `php.ini` for `error_log` directive
- May be in `/var/log/php-error.log` or `/var/log/apache2/error.log`

### Option 2: WordPress Debug Log
If you enable WordPress debugging:

**Enable Debug Logging:**
Add these lines to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Log Location:**
- `wp-content/debug.log`

## What Gets Logged

### On Each Email Attempt:
```
Certificate Generator: Email configuration: Array
(
    [to] => recipient@example.com
    [from] => noreply@test.local
    [smtp_active] =>
    [php_mail_available] => 1
    [attachment_count] => 1
    [attachment_paths] => Array
        (
            [0] => C:/Users/.../certificate_7.pdf
        )
    [server] => test.local
)

Certificate Generator: Email attempt 1/3 for recipient@example.com
Certificate Generator: Email attempt 1 failed: PHPMailer: Could not instantiate mail function
Certificate Generator: Error details: Array
(
    [phpmailer_error] => Could not instantiate mail function
    [mailer_type] => mail
)
```

### On wp_mail_failed Hook:
```
Certificate Generator: wp_mail_failed hook triggered
Certificate Generator: WP_Error details: Array
(
    [error_code] => wp_mail_failed
    [error_message] => Could not instantiate mail function
    [error_data] => Array
    [timestamp] => 2025-11-04 07:04:19
    [server] => test.local
)
```

### On Final Failure:
```
Certificate Generator: All email attempts failed. Errors: Attempt 1: PHPMailer: Could not instantiate mail function | Attempt 2: PHPMailer: Could not instantiate mail function | Attempt 3: PHPMailer: Could not instantiate mail function
```

## Common Error Messages & Solutions

### "Could not instantiate mail function"
**Cause:** PHP's `mail()` function is disabled or not working
**Solution:**
1. Install and configure WP Mail SMTP plugin
2. Configure with Gmail, SendGrid, or Mailgun SMTP

### "SMTP connect() failed"
**Cause:** Cannot connect to SMTP server
**Solution:**
1. Check SMTP credentials
2. Verify SMTP host and port
3. Ensure firewall isn't blocking port 25/465/587

### "Invalid address: noreply@test.local"
**Cause:** Using local development domain
**Solution:**
1. Install WP Mail SMTP to use proper email
2. Or set a valid admin email in WordPress Settings

### "Authentication failed"
**Cause:** Wrong SMTP username/password
**Solution:**
1. Verify SMTP credentials in WP Mail SMTP settings
2. Check if 2FA requires app-specific password (Gmail)

## Testing Email Configuration

### Check Admin Notice
1. Go to any Certificate Generator admin page
2. Look for yellow/red notice at the top showing email health status
3. Review issues and recommendations

### Check Database Email Logs
The plugin logs all email attempts to database:
```sql
SELECT * FROM wp_cert_email_logs
WHERE status = 'failed'
ORDER BY sent_at DESC
LIMIT 10;
```

Look at the `error_message` column for detailed failure reasons.

## Recommended Solution for Your Environment

Since you're on **shared hosting without SMTP configured**, the most likely cause is that PHP's `mail()` function is either:
1. Disabled by your host
2. Not properly configured
3. Being blocked by spam filters

### Recommended Fix:
**Install WP Mail SMTP Plugin**

1. Go to **Plugins → Add New**
2. Search for "WP Mail SMTP"
3. Install and activate
4. Configure with one of these options:

**Option A: Gmail (Free)**
- Mailer: Gmail
- From Email: your-gmail@gmail.com
- Create App Password in Google Account
- Use App Password in SMTP settings

**Option B: SendGrid (Free tier available)**
- Sign up at sendgrid.com (100 emails/day free)
- Get API key
- Use SendGrid in WP Mail SMTP

**Option C: Mailgun (Free tier available)**
- Sign up at mailgun.com (5,000 emails/month free)
- Get API credentials
- Use Mailgun in WP Mail SMTP

## Debug Mode Recommendations

For maximum visibility, enable these settings temporarily:

**In wp-config.php:**
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false); // Don't show errors to visitors
@ini_set('log_errors', 'On');
```

**After debugging, disable:**
```php
define('WP_DEBUG', false);
```

## Support

If you continue to have issues after:
1. Checking the error logs
2. Installing WP Mail SMTP
3. Verifying SMTP credentials

Then share the specific error messages from the logs for further diagnosis.
