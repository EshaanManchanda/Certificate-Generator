# Certificate Generator

**Contributors:** EshaanManchanda
**Tags:** certificates, generator, students, school, teacher, admin tools, bulk import, bulk export, pdf, shortcode, email
**Requires at least:** 5.0
**Tested up to:** 6.4
**Requires PHP:** 7.4
**Stable tag:** 3.3.1
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A powerful WordPress plugin for creating, managing, and distributing customizable certificates for students, schools, and teachers, with robust admin tools, frontend search capabilities, and automated email delivery.

## Description

The Certificate Generator plugin offers a comprehensive solution for educational institutions and organizations to manage, issue, and automatically email certificates efficiently. It features custom post types for students, schools, and teachers, bulk data management tools, dynamic PDF generation, highly customizable frontend search interfaces, and reliable email delivery system.

### Key Features

*   **Custom Post Types:** Dedicated management areas for Students, Schools, and Teachers.
*   **Bulk Data Import:** Easily upload Student, School, and Teacher data via CSV files.
*   **Bulk Data Export:** Export Student, School, and Teacher data to CSV.
*   **Dynamic PDF Generation:** Automatically generate PDF certificates with customizable templates and fields.
*   **Automated Email Delivery:** Send certificates directly to recipients with configurable email templates and reliable delivery tracking.
*   **Advanced Field Positioning:** Precise control over text and image placement on certificates with X/Y coordinates, alignment (left, right, center), and width adjustments.
*   **Debug Preview Mode:** Visual tool for accurate field placement on certificate templates, showing field boundaries and alignment guides.
*   **Frontend Search Shortcodes:**
    *   `[student_search]`: Allows students to search for their certificates by email.
    *   `[school_search]`: Allows searching for all certificates issued to a specific school and place.
    *   `[teacher_search]`: Allows teachers to search for their certificates by email.
*   **Bulk Certificate Download:**
    *   For Schools: Download all certificates for a specific school as a ZIP archive.
    *   For Students: Download all their certificates as a ZIP archive if multiple are found.
*   **Admin Settings Panel:** Customize plugin behavior including:
    *   Email templates and settings
    *   Contact email configuration
    *   SMTP integration with WP Mail SMTP
    *   Certificate card styling options
*   **Email Delivery System:**
    *   Integration with WP Mail SMTP for reliable delivery
    *   Configurable email templates
    *   Email delivery tracking and logs
    *   Debug tools for email troubleshooting
*   **REST API Integration:**
    *   Secure API endpoints for external integrations
    *   AI agent compatibility for automated certificate generation
    *   API key authentication with comprehensive security
    *   Health monitoring and validation endpoints
*   **Improved UI/UX:** Modern, responsive design with interactive elements.
*   **Robust Error Handling:** Clear error messages and diagnostic tools.
*   **Duplicate Prevention:** Ensures unique certificate generation.
*   **Secure Filename Generation:** Enhanced security for generated files.

## Installation

1.  Download the plugin ZIP file.
2.  In your WordPress admin panel, go to **Plugins > Add New**.
3.  Click **Upload Plugin** and choose the downloaded ZIP file.
4.  Activate the plugin through the 'Plugins' menu in WordPress.
5.  Navigate to **Settings > Certificate Generator** to configure plugin options.
6.  Configure email settings:
    *   Install and activate WP Mail SMTP plugin
    *   Configure SMTP settings for reliable email delivery
    *   Test email configuration using the provided diagnostic tool
7.  Manage Students, Schools, and Teachers under their respective menus.
8.  Upload certificate templates (PNG format recommended).

## Email Configuration

### Setting Up Email Delivery

1. **Install WP Mail SMTP:**
   * Install and activate the WP Mail SMTP plugin
   * Configure your SMTP provider settings

2. **Configure Email Templates:**
   * Set up email subject lines and content
   * Customize the sender name and email
   * Configure CC and BCC options if needed

3. **Test Email Configuration:**
   * Use the built-in email testing tool
   * Verify delivery to test email addresses
   * Check email logs for delivery status

### Troubleshooting Email Issues

If you're experiencing email delivery problems:

1. **Check SMTP Configuration:**
   - Verify your SMTP settings in WP Mail SMTP plugin
   - Test with different SMTP providers (Gmail, SendGrid, etc.)
   - Ensure correct port and security settings

2. **Use the Diagnostic Tool:**
   - Upload the `email-test.php` script to your WordPress root directory
   - Run it via browser to get detailed email diagnostics
   - Check for "silent failures" where emails appear sent but aren't delivered
   - **Important:** Delete the script after use for security

3. **Common Issues:**
   - Plugin configured to use PHP's `mail()` instead of SMTP
   - Incorrect authentication credentials
   - Server firewall blocking SMTP ports
   - Missing SPF/DKIM records for your domain

4. **Enable Debug Logging:**
   - Turn on WordPress debug mode
   - Check `/wp-content/debug.log` for detailed error messages
   - Monitor SMTP debug output in the diagnostic tool

## API Integration

The Certificate Generator plugin provides a secure REST API for external integrations, including AI agents and automated systems.

### API Setup

1. **Enable API Access:**
   - Go to Settings > Certificate Generator
   - Navigate to the "API Configuration" section
   - Check "Enable API Access"
   - Click "Generate New API Key" to create a secure key
   - Copy and securely store the API key

2. **Security Configuration:**
   - API keys are stored securely in your WordPress database
   - Each request must include the API key in the Authorization header
   - API access can be disabled at any time from the admin panel

### Available Endpoints

#### Issue Certificate
- **URL:** `/wp-json/certificate-generator/v1/issue-certificate`
- **Method:** POST
- **Authentication:** Bearer token (API key)
- **Payload:**
  ```json
  {
    "student_email": "student@example.com",
    "certificate_type": "Course Completion",
    "student_name": "John Doe" (optional),
    "school_name": "Example School" (optional),
    "teacher_email": "teacher@example.com" (optional),
    "issue_date": "2024-01-15" (optional)
  }
  ```
- **Response:** Returns download URL for generated certificate

#### Health Check
- **URL:** `/wp-json/certificate-generator/v1/health`
- **Method:** GET
- **Authentication:** Bearer token (API key)
- **Response:** API status and configuration information

#### Validate API Key
- **URL:** `/wp-json/certificate-generator/v1/validate-key`
- **Method:** GET
- **Authentication:** Bearer token (API key)
- **Response:** API key validation status

### Usage Examples

For detailed integration examples and AI agent workflows, see the `API-INTEGRATION-GUIDE.md` file included with the plugin.

### Security Best Practices

- Store API keys securely and never expose them in client-side code
- Use HTTPS for all API communications
- Regularly rotate API keys
- Monitor API usage through WordPress logs
- Disable API access when not needed

## Troubleshooting Guide

### Quick Diagnosis

If you encounter issues during plugin activation or operation, follow these steps:

1. **Check Environment:**
   - Local Development: Ensure MySQL service is running
   - Live Server: Verify WordPress file permissions
   - Check PHP version compatibility (7.4+)

2. **Database Issues:**
   ```sql
   -- Verify tables exist
   SHOW TABLES LIKE 'wp_certificate%';
   
   -- Manual table creation if needed
   CREATE TABLE IF NOT EXISTS wp_certificate_generator (
       id mediumint(9) NOT NULL AUTO_INCREMENT,
       student_name varchar(255) NOT NULL,
       certificate_data text NOT NULL,
       created_at datetime DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   CREATE TABLE IF NOT EXISTS wp_cert_email_logs (
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

3. **Path Issues:**
   - Verify WordPress admin includes directory accessibility
   - Check for proper file permissions (755 for directories, 644 for files)
   - Use the built-in path verification tool

### Debug Tools

1. **API Debug Dashboard:**
   - Access via Settings > API Debug Dashboard
   - View API access status
   - Test API endpoints
   - Monitor API key management

2. **Email Diagnostic Tools:**
   - Built-in email testing tool
   - SMTP configuration verification
   - Email delivery logging
   - Debug mode for detailed error tracking

3. **Database Diagnostic:**
   - Table structure verification
   - Data integrity checks
   - Auto-repair functionality

### Common Issues and Solutions

1. **Activation Errors:**
   - Clear plugin cache
   - Verify database permissions
   - Check WordPress version compatibility
   - Review error logs

2. **Email Delivery Issues:**
   - Verify SMTP configuration
   - Check email template settings
   - Monitor delivery logs
   - Test with different email providers

3. **Database Connection:**
   - Verify MySQL service status
   - Check database credentials
   - Test connection manually
   - Review server error logs

4. **File Permission Issues:**
   ```bash
   # Set correct permissions
   chmod 755 wp-content/plugins/certificate-generator/
   chmod 644 wp-content/plugins/certificate-generator/*.php
   ```

### Environment-Specific Solutions

#### Local Development

1. **Database Connection:**
   ```php
   // Add to wp-config.php for testing
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Server Configuration:**
   - Start local MySQL service
   - Verify port configurations
   - Check localhost accessibility

#### Production Server

1. **Memory Limits:**
   ```php
   // Add to wp-config.php
   ini_set('memory_limit', '256M');
   ini_set('max_execution_time', 300);
   ```

2. **Security Settings:**
   - Verify file permissions
   - Check server firewall rules
   - Monitor error logs

## Changelog

### Version 1.3.0
* **NEW:** REST API integration for external systems and AI agents
* **NEW:** Secure API key authentication system
* **NEW:** Three API endpoints: issue-certificate, health check, and validate-key
* **NEW:** Comprehensive API documentation and integration guide
* **NEW:** Admin interface for API configuration and key management
* **ENHANCED:** Security measures for server-to-server communication
* **ENHANCED:** Logging system for API requests and responses

### Version 1.2.0
* Enhanced email delivery system with WP Mail SMTP integration
* Added configurable email templates for certificate delivery
* Implemented comprehensive email debugging and diagnostic tools
* Added email delivery tracking and logging capabilities
* Improved error handling for email-related issues
* Added admin interface for SMTP configuration management

### 3.3.1 (Planned)
*   **Major UI Overhaul:** Implemented modern, responsive UI for frontend search forms, certificate cards, and buttons with enhanced hover effects and SVG icons.
*   **Admin Settings Enhancements:** Added comprehensive styling options in the admin panel for frontend components (colors, gradients, hover effects, border-radius).
*   **Bulk Certificate Download for Schools:** Implemented robust ZIP file generation for all certificates associated with a school, accessible via school search results and a dedicated shortcode `[school_bulk_certificate_download]` with progress tracking.
*   **Improved Student Bulk Download:** ZIP download for students with multiple certificates from the `[student_search]` shortcode.
*   **Enhanced Error Handling:** More detailed and user-friendly error messages on the frontend, including a support information section.
*   **Duplicate Prevention Logic:** Strengthened duplicate certificate prevention in search functionalities.
*   **Secure Filename Generation:** Enhanced security for generated files.
*   **Code Refinements:** General code cleanup, performance improvements, and alignment of features between student and school search functionalities.

## Support

If you continue to experience issues:

1. Run the built-in diagnostics tool
2. Check WordPress debug logs
3. Review server error logs
4. Contact support with diagnostic output
