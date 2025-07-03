# Certificate Generator Plugin - Troubleshooting Guide

## Quick Diagnosis

Based on your debug logs, the plugin **IS activating successfully on the live Hostinger server**, but you're experiencing **database connection issues in your local development environment**.

### Your Log Analysis:
- ✅ **Live Server (Hostinger)**: Plugin activated successfully
- ❌ **Local Environment**: MySQL connection refused (Error 2002)

## Immediate Solutions

### For Local Development Issues

#### 1. Start Your Local MySQL Service

**Local by Flywheel:**
```bash
# Open Local by Flywheel application
# Click on your site
# Click "Start Site" button
# Wait for all services to turn green
```

**XAMPP:**
```bash
# Open XAMPP Control Panel
# Click "Start" next to MySQL
# Ensure Apache is also running
```

**WAMP:**
```bash
# Open WAMP Server
# Click on WAMP icon in system tray
# Start All Services
# Ensure MySQL is running (green)
```

#### 2. Quick Status Check

Run this file directly in your browser:
```
http://localhost/your-site/wp-content/plugins/certificate-generator/check-activation.php
```

Or access the diagnostic tool in WordPress admin:
```
WordPress Admin → Tools → Cert Gen Diagnostics
```

### For Live Server Issues

If you're still experiencing issues on the live server despite successful activation logs:

#### 1. Clear Plugin Cache
```php
// Add this to your wp-config.php temporarily
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Then deactivate and reactivate the plugin
```

#### 2. Force Plugin Reactivation
```sql
-- Run in your database (phpMyAdmin)
DELETE FROM wp_options WHERE option_name LIKE 'certificate_generator%';
```

#### 3. Manual Table Creation
```sql
-- Main certificate table
CREATE TABLE IF NOT EXISTS `wp_certificate_generator` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `student_name` varchar(255) NOT NULL,
  `certificate_data` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email log table
CREATE TABLE IF NOT EXISTS `wp_cert_email_logs` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `certificate_id` mediumint(9) NOT NULL,
  `email_subject` varchar(500) NOT NULL,
  `email_body` text NOT NULL,
  `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) DEFAULT 'pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Environment-Specific Solutions

### Local Development Environment

#### Common Issues:
1. **MySQL Service Not Running**
   - **Solution**: Start your local server stack (XAMPP/WAMP/Local)
   - **Check**: Look for green indicators in your server control panel

2. **Port Conflicts**
   - **Solution**: Change MySQL port in your local server settings
   - **Default ports**: 3306 (MySQL), 80 (Apache)

3. **Database Credentials**
   - **Check**: `wp-config.php` database settings
   - **Local defaults**: Usually `root` with empty password

#### Local Environment Verification:
```bash
# Test MySQL connection
mysql -u root -p -h localhost

# Check if WordPress can connect
wp db check

# Test plugin activation
wp plugin activate certificate-generator
```

### Hostinger Live Server

#### Common Issues:
1. **File Permissions**
   ```bash
   # Set correct permissions
   chmod 755 wp-content/plugins/certificate-generator/
   chmod 644 wp-content/plugins/certificate-generator/*.php
   ```

2. **Memory Limits**
   ```php
   // Add to wp-config.php
   ini_set('memory_limit', '256M');
   ini_set('max_execution_time', 300);
   ```

3. **Database Prefix Issues**
   - **Check**: Ensure your `wp-config.php` has correct `$table_prefix`
   - **Hostinger default**: Usually `wp_` but can vary

## Diagnostic Tools

### 1. Built-in Diagnostics
- **Location**: WordPress Admin → Tools → Cert Gen Diagnostics
- **Features**: Environment detection, database checks, auto-fix

### 2. Quick Status Checker
- **File**: `check-activation.php`
- **Usage**: Run directly in browser or CLI
- **Output**: Comprehensive status report

### 3. Debug Logging
```php
// Enable in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Check logs at:
// wp-content/debug.log
```

## Step-by-Step Troubleshooting

### Step 1: Identify Your Environment
```
Local Development:
- localhost, 127.0.0.1, .local domains
- XAMPP, WAMP, Local by Flywheel

Live Server:
- Your actual domain
- Hostinger, cPanel, etc.
```

### Step 2: Check Database Connection
```php
// Test in WordPress
global $wpdb;
$result = $wpdb->get_var("SELECT 1");
echo $result === '1' ? 'Connected' : 'Failed';
```

### Step 3: Verify Plugin Files
```
Required files:
✓ certificate-generator.php
✓ enhanced-activation.php
✓ hostinger-safe-activation.php
✓ activation-diagnostic.php
✓ includes/certificate-post-type.php
```

### Step 4: Check Plugin Status
```php
// Check activation status
$activated = get_option('certificate_generator_activated');
echo $activated ? 'Activated' : 'Not activated';

// Check tables
global $wpdb;
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}certificate%'");
var_dump($tables);
```

### Step 5: Force Reactivation
```
1. Deactivate plugin in WordPress admin
2. Delete plugin options (optional):
   DELETE FROM wp_options WHERE option_name LIKE 'certificate_generator%';
3. Reactivate plugin
4. Check diagnostic page
```

## Common Error Messages & Solutions

### "No connection could be made because the target machine actively refused it"
- **Cause**: MySQL service not running
- **Solution**: Start your local MySQL service
- **Environment**: Local development only

### "Access denied for user"
- **Cause**: Wrong database credentials
- **Solution**: Check wp-config.php database settings
- **Environment**: Both local and live

### "Table doesn't exist"
- **Cause**: Plugin activation incomplete
- **Solution**: Run auto-fix or create tables manually
- **Environment**: Both local and live

### "Plugin activated but not working"
- **Cause**: File permissions or missing files
- **Solution**: Check file permissions and upload all files
- **Environment**: Live server mainly

## Prevention Tips

1. **Always test locally first** before deploying to live server
2. **Keep backups** of working configurations
3. **Use staging environments** for testing
4. **Monitor error logs** regularly
5. **Keep WordPress and plugins updated**

## Getting Help

If issues persist:

1. **Run diagnostics**: Use the built-in diagnostic tool
2. **Check error logs**: Look at WordPress debug.log
3. **Test environment**: Verify your server environment
4. **Contact support**: Provide diagnostic output and error logs

## Quick Reference Commands

```bash
# WordPress CLI commands
wp plugin list
wp plugin activate certificate-generator
wp db check
wp option get certificate_generator_activated

# Database commands
mysql -u root -p
SHOW DATABASES;
USE your_database_name;
SHOW TABLES LIKE 'wp_certificate%';

# File permissions
chmod 755 wp-content/plugins/certificate-generator/
chmod 644 wp-content/plugins/certificate-generator/*.php
```

Remember: Your logs show the plugin IS working on the live server. The MySQL errors are from your local development environment where the MySQL service isn't running.