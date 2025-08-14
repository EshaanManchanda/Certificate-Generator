# Certificate Generator - Activation Troubleshooting Guide

## Issue Analysis

Based on your debug logs, the plugin is **successfully activating on your live Hostinger server** but experiencing database connection issues in your **local development environment**.

### What the Logs Show:

✅ **Live Server (Hostinger) - SUCCESS:**
- "Certificate Generator activated successfully on Hostinger"
- All activation steps completed successfully
- Database tables created
- Custom post types registered
- Rewrite rules flushed

❌ **Local Environment - DATABASE CONNECTION ISSUE:**
- `mysqli_real_connect(): (HY000/2002): No connection could be made because the target machine actively refused it`
- This indicates MySQL service is not running or not accessible on localhost

## Solutions

### 1. Fix Local Development Environment

#### For Local by Flywheel Users:
```bash
# Start the Local site
1. Open Local by Flywheel
2. Click on your site
3. Click "Start Site" if it's stopped
4. Wait for all services (Nginx, PHP, MySQL) to start
```

#### For XAMPP Users:
```bash
# Start MySQL service
1. Open XAMPP Control Panel
2. Click "Start" next to MySQL
3. Ensure Apache is also running
```

#### For WAMP Users:
```bash
# Start all services
1. Click WAMP icon in system tray
2. Ensure all services are green
3. Restart if any are orange/red
```

### 2. Verify Database Connection

Add this test to your `wp-config.php` temporarily:

```php
// Test database connection
$test_connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($test_connection->connect_error) {
    die('Database connection failed: ' . $test_connection->connect_error);
} else {
    echo 'Database connection successful!';
}
$test_connection->close();
```

### 3. Enhanced Activation Function

I'll create an improved activation function that handles both environments better:

```php
// Add to certificate-generator.php
function certificate_generator_smart_activate() {
    // Detect environment
    $is_local = (
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
        strpos($_SERVER['HTTP_HOST'], '.test') !== false ||
        $_SERVER['REMOTE_ADDR'] === '127.0.0.1'
    );
    
    if ($is_local) {
        // Use local-safe activation
        return certificate_generator_local_safe_activate();
    } else {
        // Use Hostinger-safe activation
        return certificate_generator_hostinger_safe_activate();
    }
}
```

### 4. Check WordPress Database Settings

Verify your `wp-config.php` has correct database settings:

```php
// Local environment typically uses:
define('DB_NAME', 'local');
define('DB_USER', 'root');
define('DB_PASSWORD', 'root'); // or empty string
define('DB_HOST', 'localhost');
```

### 5. Force Plugin Reactivation

If the plugin appears "activated" but isn't working:

1. **Via WordPress Admin:**
   - Go to Plugins page
   - Deactivate Certificate Generator
   - Reactivate it

2. **Via Database:**
   ```sql
   DELETE FROM wp_options WHERE option_name = 'active_plugins';
   -- Then reactivate through admin
   ```

3. **Via Code:**
   ```php
   // Add to functions.php temporarily
   add_action('init', function() {
       if (current_user_can('activate_plugins')) {
           deactivate_plugins('certificate-generator/certificate-generator.php');
           activate_plugin('certificate-generator/certificate-generator.php');
       }
   });
   ```

## Environment-Specific Debugging

### Local Environment Debug:
```php
// Add to wp-config.php for local debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);
```

### Live Server Debug:
```php
// Add to wp-config.php for production debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false); // Don't show errors to visitors
```

## Quick Fixes

### 1. Reset Plugin Data
```sql
-- Remove plugin options
DELETE FROM wp_options WHERE option_name LIKE 'certificate_generator_%';

-- Drop plugin tables
DROP TABLE IF EXISTS wp_certificate_generator;
DROP TABLE IF EXISTS wp_cert_email_logs;
```

### 2. Manual Table Creation
If activation fails, create tables manually:

```sql
CREATE TABLE wp_certificate_generator (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    student_name varchar(255) NOT NULL,
    certificate_data text NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE wp_cert_email_logs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    recipient_email varchar(255) NOT NULL,
    student_name varchar(255) NOT NULL,
    certificate_id mediumint(9) NOT NULL,
    email_subject varchar(500) NOT NULL,
    email_body text NOT NULL,
    sent_at datetime DEFAULT CURRENT_TIMESTAMP,
    status varchar(20) DEFAULT 'pending',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Verification Steps

1. **Check Plugin Status:**
   - Go to WordPress Admin → Plugins
   - Verify "Certificate Generator" shows as active

2. **Check Database Tables:**
   ```sql
   SHOW TABLES LIKE '%certificate%';
   ```

3. **Check Plugin Menu:**
   - Look for "Certificate Generator" in admin menu
   - Try accessing plugin settings

4. **Check Error Logs:**
   - Local: Check `wp-content/debug.log`
   - Hostinger: Check cPanel error logs

## Summary

**Your live server is working fine!** The activation logs show complete success on Hostinger. The issue is only in your local development environment where MySQL service isn't running properly.

**Next Steps:**
1. Fix your local MySQL service
2. Test plugin functionality on live server
3. Use the enhanced activation function for better error handling

The plugin should be fully functional on your live Hostinger server based on the successful activation logs.