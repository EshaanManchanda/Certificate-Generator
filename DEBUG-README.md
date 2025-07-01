# Certificate Generator Plugin - Debug Tools

## Overview

This document provides information about the debugging tools included in the Certificate Generator plugin. These tools are designed to help troubleshoot issues with the API key generation and management functionality.

**IMPORTANT: These debug tools should be removed in production environments.**

## Available Debug Tools

### 1. API Debug Dashboard

**Access:** Settings > API Debug Dashboard

A central dashboard that provides an overview of all API-related settings and quick access to all debugging tools. It displays:

- API access status
- API key status
- REST API availability
- Database status
- Links to all debugging tools

### 2. API Key Generator

**Access:** Settings > API Key Generator

A standalone tool for generating and managing API keys. Features include:

- Generate new API keys
- Toggle API access on/off
- View current API key settings
- View raw database values

### 3. API Key Testing

**Access:** Settings > API Key Testing

A tool for testing the API endpoints with your current API key. Features include:

- Test the health check endpoint
- View response status codes and bodies
- View available API endpoints

### 4. API Key Check

**Access:** Via the API Debug Dashboard

A simple tool that displays the raw database values for the API key settings.

## JavaScript Debugging Scripts

### 1. api-key-fix.js

A script that fixes issues with the API key generation and display functionality. It:

- Ensures the API key generation buttons exist
- Handles API key generation and display
- Provides fallbacks for missing elements

### 2. api-debug.js

A script that adds debugging functionality to the API settings page. It:

- Logs the active tab
- Forces redirection to the API tab for debugging
- Adds debug buttons to force API key generation
- Shows saved options

### 3. tab-debug.js

A script that helps diagnose issues with tab navigation on the settings page. It:

- Logs the active tab
- Checks tab navigation and section visibility
- Adds a debug section with tab switching links
- Includes a fix for the API tab display

## Common Issues and Solutions

### API Key Not Generating

**Symptoms:**
- Clicking the "Generate API Key" button does nothing
- No API key appears after clicking the button

**Solutions:**
- Check for JavaScript errors in the browser console
- Use the API Key Generator tool to generate a key directly
- Verify that the `api-key-fix.js` script is loaded

### API Key Not Saving

**Symptoms:**
- API key appears to generate but disappears after page reload
- Settings page shows no API key even though one was generated

**Solutions:**
- Check the database values using the API Key Check tool
- Verify that the form is submitting correctly
- Use the API Key Generator tool to generate and save a key directly

### API Access Not Working

**Symptoms:**
- API endpoints return 401 Unauthorized errors
- Cannot access API endpoints even with a valid key

**Solutions:**
- Ensure API access is enabled in the settings
- Verify that the API key is correctly generated and saved
- Use the API Key Testing tool to test the endpoints

## Removing Debug Tools

Before deploying to production, remove the following files:

1. `api-key-debug.php`
2. `api-key-generate.php`
3. `api-key-test.php`
4. `api-debug-dashboard.php`
5. `api-key-check.php`
6. `assets/js/api-debug.js`
7. `assets/js/tab-debug.js`
8. `assets/js/api-key-fix.js`

Also, remove or comment out the following line in `certificate-generator.php`:

```php
'debug-tools.php'  // Temporary debugging tools (remove in production)
```

And remove the debug script enqueues:

```php
// Add debug scripts for troubleshooting
wp_enqueue_script('api-debug-js', plugin_dir_url(__FILE__) . 'assets/js/api-debug.js', ['jquery'], time(), true);
wp_enqueue_script('tab-debug-js', plugin_dir_url(__FILE__) . 'assets/js/tab-debug.js', ['jquery'], time(), true);
wp_enqueue_script('api-key-fix-js', plugin_dir_url(__FILE__) . 'assets/js/api-key-fix.js', ['jquery'], time(), true);
```

## Support

If you continue to experience issues after using these debugging tools, please contact the plugin developer for further assistance.