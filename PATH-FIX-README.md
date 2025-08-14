# WordPress Path Fix for Certificate Generator Plugin

## Issue Description

The plugin was encountering a critical error during activation due to the WordPress `upgrade.php` file not being found at the expected path. This file is required for database table creation using the `dbDelta()` function.

## Changes Made

1. **Added Path Verification in `certificate-generator.php`**
   - Added a check to verify if the WordPress admin includes directory is accessible
   - Implemented a fallback mechanism to use direct SQL queries when `upgrade.php` is not available
   - Removed redundant call to `certificate_generator_create_email_log_table()`

2. **Updated `email-log.php`**
   - Added similar path verification and fallback mechanism
   - Improved error logging for better diagnostics

3. **Added Debugging Tools**
   - Created `wp-path-debug.php` to help diagnose WordPress path issues
   - This file can be accessed directly to check WordPress paths and includes

## How to Test

1. Deactivate the plugin
2. Activate the plugin again
3. The plugin should now activate without errors

## Troubleshooting

If you continue to experience issues:

1. Access the `wp-path-debug.php` file directly in your browser to check WordPress paths
2. Check your WordPress installation structure
3. Verify that your WordPress installation has the standard directory structure
4. Check PHP error logs for any additional error messages

## Technical Details

The issue was related to the WordPress path structure in your Local by Flywheel installation. The plugin was expecting the WordPress admin includes directory to be at a standard location, but it wasn't found there.

The fix implements a more robust approach by:

1. Checking if the required file exists before attempting to include it
2. Providing a fallback mechanism using direct SQL queries
3. Adding better error logging for diagnostics

This approach ensures the plugin will work even in non-standard WordPress installations or custom development environments.