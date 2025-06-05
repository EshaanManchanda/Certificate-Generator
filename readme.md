# Certificate Generator

**Contributors:** EshaanManchanda
**Tags:** certificates, generator, students, school, teacher, admin tools, bulk import, bulk export, pdf, shortcode
**Requires at least:** 5.0
**Tested up to:** 6.4
**Requires PHP:** 7.4
**Stable tag:** 3.3.1
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A powerful WordPress plugin for creating, managing, and distributing customizable certificates for students, schools, and teachers, with robust admin tools and frontend search capabilities.

## Description

The Certificate Generator plugin offers a comprehensive solution for educational institutions and organizations to manage and issue certificates efficiently. It features custom post types for students, schools, and teachers, bulk data management tools, dynamic PDF generation, and highly customizable frontend search interfaces.

### Key Features

*   **Custom Post Types:** Dedicated management areas for Students, Schools, and Teachers with enhanced table UI:
    * Sortable columns for Email, School Name, Certificate Type, and Issue Date
    * Quick search functionality across all fields including email addresses
    * Modern table layout with improved readability
    * Visual indicators for empty fields
*   **Advanced Search Capabilities:**
    * Global search across multiple fields (email, name, school, certificate type)
    * Smart search that works with partial matches
    * Improved search response time with optimized queries
*   **Bulk Data Import:** Easily upload Student, School, and Teacher data via CSV files. (See <mcfolder name="includes" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes"></mcfolder> - <mcfile name="bulk-import.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\bulk-import.php"></mcfile>)
*   **Bulk Data Export:** Export Student, School, and Teacher data to CSV. (See <mcfolder name="includes" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes"></mcfolder> - <mcfile name="bulk-export.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\bulk-export.php"></mcfile>)
*   **Dynamic PDF Generation:** Automatically generate PDF certificates with customizable templates and fields. (See <mcfile name="certificate-generator.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\certificate-generator.php"></mcfile> and <mcfile name="student-certificate-search.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\student-certificate-search.php"></mcfile>)
*   **Advanced Field Positioning:** Precise control over text and image placement on certificates with X/Y coordinates, alignment (left, right, center), and width adjustments.
*   **Debug Preview Mode:** Visual tool for accurate field placement on certificate templates, showing field boundaries and alignment guides.
*   **Frontend Search Shortcodes:**
    *   `[student_search]`: Allows students to search for their certificates by email.
    *   `[school_search]`: Allows searching for all certificates issued to a specific school and place.
    *   `[teacher_search]`: Allows teachers to search for their certificates by email.
    (Implemented in <mcfile name="student-certificate-search.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\student-certificate-search.php"></mcfile>)
*   **Bulk Certificate Download:**
    *   For Schools: Download all certificates for a specific school as a ZIP archive via the `[school_search]` shortcode results or the `[school_bulk_certificate_download]` shortcode.
    *   For Students: Download all their certificates as a ZIP archive if multiple are found via the `[student_search]` shortcode.
    (See <mcfile name="student-certificate-search.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\student-certificate-search.php"></mcfile> and <mcfile name="bulk-certificate-download.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\bulk-certificate-download.php"></mcfile>)
*   **Admin Settings Panel:** Customize plugin behavior and appearance, including contact email and styling options for certificate cards and buttons (background colors, text colors, button gradients, hover effects, border-radius). (See <mcfile name="admin-settings.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\admin-settings.php"></mcfile>)
*   **Improved UI/UX:** Modern, responsive design for search forms, certificate cards, and download buttons with interactive hover effects and SVG icons.
*   **Robust Error Handling:** Clear and user-friendly error messages and support information for frontend searches.
*   **Duplicate Prevention:** Ensures unique certificate generation, preventing duplicates for the same student/school record.
*   **Secure Filename Generation:** Enhanced unique filenames for generated certificates and ZIP archives.

## Installation

1.  Download the plugin ZIP file.
2.  In your WordPress admin panel, go to **Plugins > Add New**.
3.  Click **Upload Plugin** and choose the downloaded ZIP file.
4.  Activate the plugin through the 'Plugins' menu in WordPress.
5.  Navigate to **Settings > Certificate Generator** to configure plugin options.
6.  Manage Students, Schools, and Teachers under their respective custom post type menus.
7.  Upload your certificate template (PNG format recommended) when creating a new certificate design. You can find templates on [Canva Portrait certificates](https://www.canva.com/templates/?category=tAFBBL5OE1A&doctype=TAEdwwJWdWc) or [Canva Landscape certificates](https://www.canva.com/templates/?category=tAFBBL5OE1A&doctype=TACTmE1fsnQ).
    ![Canva Page for Certificate Templates](/assets/screenshots/canva.png)

## Functionality Details

### 1. Enhanced Admin Interface

The admin interface has been significantly improved with a modern, user-friendly table layout:

* **Sortable Columns:**
  * Email - Sort entries alphabetically by email address
  * School Name - Organize entries by school
  * Certificate Type - Group similar certificates together
  * Issue Date - Sort by newest or oldest first

* **Advanced Search Features:**
  * Universal Search Box - Searches across all relevant fields
  * Smart Matching - Finds partial matches in email addresses and names
  * Real-time Results - Updates as you type
  * Clear Visual Feedback - Shows when no results are found

* **Table Improvements:**
  * Cleaner Layout - Better spacing and alignment
  * Visual Indicators - Clear display of empty fields with "—"
  * Responsive Design - Adapts to different screen sizes
  * Optimized Performance - Faster loading and sorting

### 2. Admin Settings

Access the settings via **Settings > Certificate Generator**. Here you can configure:

*   **Contact Email:** The email address used for support links in error messages.
*   **Card Styling:** Customize the appearance of certificate cards and buttons on the frontend search results. Options include:
    *   Card Background Color
    *   Title Color
    *   Text Color
    *   Button Gradient Start & End Colors
    *   Hover Effect Intensity (card lift)
    *   Border Radius (for cards and buttons)

![Admin Settings Panel](/assets/screenshots/setting%20page.png) *(New screenshot needed for the actual admin settings panel)*

### 2. Bulk Import

The plugin supports bulk importing data for Students, Schools, and Teachers using CSV files. Navigate to the respective custom post type menu (e.g., **Students > Bulk Import**) to access the import interface.

*   **Required CSV Headers for Students:** `student_name`, `email`, `school_name`, `issue_date`, `certificate_type`
*   **Required CSV Headers for Schools:** `school_name`, `school_abbreviation`, `place`, `issue_date`, `certificate_type`
*   **Required CSV Headers for Teachers:** `teacher_name`, `email`, `school_name`, `issue_date`, `certificate_type`

![Bulk Import Interface](/assets/screenshots/bulk-import.png)

### 3. Bulk Export

Export data for Students, Schools, and Teachers to a CSV file. Navigate to the respective custom post type menu (e.g., **Students > Export Students**) and click the "Export to CSV" button.

![Bulk Export Interface](/assets/screenshots/export.png)

### 4. Frontend Certificate Search

Use the following shortcodes on any page or post to display search forms:

*   **Student Search (`[student_search]`):**
    *   Allows students to search for their certificates using their email address.
    *   Displays individual certificate cards with download buttons.
    *   If multiple certificates are found, a "Download All Certificates" button (ZIP) is provided.
    *   Features improved UI with responsive design, hover effects, and clear metadata display.

    ![Student Search Form](/assets/screenshots/Student%20Form.png) *(New screenshot needed for the improved student search form)*
    ![Student Search Results](/assets/screenshots/result.png) *(New screenshot needed for the improved student search results/cards)*
    ![Student bulk certificate Download](/assets/screenshots/bulk%20certificate.png)
*   **School Search (`[school_search]`):**
    *   Allows users to search for all certificates associated with a specific school name and place.
    *   Displays certificate cards for each matching record.
    *   Provides a "Download All Certificates" button to download a ZIP archive of all found certificates.
    *   Features enhanced UI, robust error handling with support information, and improved button styling.

    ![School Search Form](/assets/screenshots/school%20form.png) *(New screenshot needed for the improved school search form)*
    ![School Search Results](/assets/screenshots/school-search-results-ui.png) *(New screenshot needed for the improved school search results/cards)*

*   **Teacher Search (`[teacher_search]`):**
    *   Allows teachers to search for their certificates using their email address.
    *   Similar UI and functionality to the student search.

    ![Teacher Search Form](/assets/screenshots/Teacher%20Form.png) *(New screenshot needed for the improved teacher search form)*

### 5. Bulk Certificate Download

*   **From Search Results:**
    *   The `[student_search]` and `[school_search]` shortcodes provide a "Download All Certificates" button if multiple certificates are found. This button generates a ZIP file containing all relevant PDF certificates.
*   **Dedicated School Bulk Download Shortcode (`[school_bulk_certificate_download]`):**
    *   Provides a form to enter a school name.
    *   Upon submission, it finds all associated students/certificates and generates a ZIP file for download.
    

### 6. Certificate Creation and Design

When creating or editing a certificate template (typically under a 'Certificates' custom post type or similar admin section):

*   **Upload Template:** Upload a base image for your certificate (PNG recommended).
*   **Field Positioning:** Add fields (e.g., Student Name, School Name, Issue Date) and position them precisely using X and Y coordinates.
*   **Alignment & Width:** Control text alignment (left, center, right) and field width to ensure proper layout.
*   **Debug Preview:** Use the "Preview" button to see a visual representation of your field placements on the template, with guides for boundaries and alignment points.

![Certificate Design Interface](/assets/screenshots/certificate-admin.png)
![Certificate Design Preview](/assets/screenshots/preview%20button.png)

## Improved UI and UX

The plugin has undergone significant UI enhancements:

*   **Modern Card Design:** Certificate search results are displayed in stylish, responsive cards with configurable background, text colors, and border-radius via admin settings.
*   **Interactive Buttons:** Download and action buttons feature gradient backgrounds, SVG icons, and smooth hover animations (scaling, shadow changes).
*   **Floating Labels:** Input fields in search forms use modern floating label effects for a cleaner look.
*   **Consistent Styling:** Plugin settings allow for global styling, ensuring a consistent look and feel across different frontend components.
*   **Enhanced Error Messages:** Error displays are more user-friendly, with animated icons, clear instructions, and a dedicated section for support information when certificate generation fails.

## Sample Data

Sample CSV files are provided to help you get started with bulk imports.

*   [All sample data files](/assets/data/)
*   [Student Data CSV](/assets/data/students.csv) - ![Student Data Preview](/assets/screenshots/excel-student-data.png)
*   [School Data CSV](/assets/data/school.csv) - ![School Data Preview](/assets/screenshots/excel-school-data.png)
*   [Teacher Data CSV](/assets/data/teacher.csv) - ![Teacher Data Preview](/assets/screenshots/excel-teacher-data.png)
*   [Certificate Data CSV](/assets/data/certificates.csv) - ![Certificate Data Preview](/assets/screenshots/excel-certificate-data.png)

## Frequently Asked Questions

**Q: How do I use the shortcodes?**
A: Simply add the desired shortcode (e.g., `[student_search]`) to any WordPress page or post using the text editor or a shortcode block in the block editor.

**Q: Can I customize the certificate PDF design beyond the admin settings?**
A: Yes, the PDF generation logic is primarily within <mcfile name="student-certificate-search.php" path="c:\Users\eshaa\Local Sites\test\app\public\wp-content\plugins\certificate-generator\includes\student-certificate-search.php"></mcfile> (specifically functions like `generate_certificate_pdf`) and uses the FPDF library. Developers can modify this code or use hooks (if available/added) to further customize the PDF output.

**Q: Where are generated certificates stored?**
A: Generated PDF certificates are typically stored in the WordPress uploads directory, within a subdirectory like `certificates`.

**Q: What happens if a student or school has multiple certificates?**
A: The search results will display all associated certificates. For bulk downloads, a single ZIP file containing all these individual PDF certificates will be created.

## Screenshots

*(This section should be updated with new screenshots reflecting the improved UI and features described above. Existing screenshots are kept for reference but should be replaced.)*

1.  **Admin Dashboard Interface:** (Shows main CPTs: Students, Schools, Teachers, Certificates)
    ![Admin Dashboard Interface](/assets/screenshots/Admin%20View.png)
2.  **Admin Settings Panel:** (New screenshot needed)
    *Placeholder for new Admin Settings screenshot*
3.  **Bulk Import (Students):**
    ![Bulk Import Feature](/assets/screenshots/bulk-import.png)
4.  **Bulk Export (Students):**
    ![Bulk Export Feature](/assets/screenshots/export.png)
5.  **Certificate Design & Preview:**
    ![Certificate Design Admin](/assets/screenshots/certificate-admin.png)
    ![Certificate Preview Mode](/assets/screenshots/preview%20button.png)
6.  **Frontend Student Search Form & Results:** (New screenshots needed for improved UI)
    ![Student Search Form (Old)](/assets/screenshots/student%20search%20form.png)
    *Placeholder for new Student Search Form screenshot*
    *Placeholder for new Student Search Results screenshot*
7.  **Frontend School Search Form & Results:** (New screenshots needed for improved UI)
    ![School Search Form (Old)](/assets/screenshots/school%20search%20form.png)
    *Placeholder for new School Search Form screenshot*
    *Placeholder for new School Search Results screenshot*
8.  **Bulk Download ZIP (Example from School Search):** (New screenshot needed)
    *Placeholder for Bulk Download ZIP in action screenshot*
9.  **Error Handling with Support Info:** (New screenshot needed)
    *Placeholder for new Error Message UI screenshot*

## While importing Certificate.csv

1.  You have to enable visibility every time for the certificate design.
    ![Visibility On for Certificate Design](/assets/screenshots/visibility.png)

## Changelog

### 3.3.1 (Planned)
*   **Admin Table UI Enhancement:** 
    * Added sortable columns for Email, School Name, Certificate Type, and Issue Date
    * Implemented improved search functionality across all fields
    * Enhanced table layout with better visual indicators and responsiveness
*   **Major UI Overhaul:** Implemented modern, responsive UI for frontend search forms, certificate cards, and buttons with enhanced hover effects and SVG icons.
*   **Admin Settings Enhancements:** Added comprehensive styling options in the admin panel for frontend components (colors, gradients, hover effects, border-radius).
*   **Bulk Certificate Download for Schools:** Implemented robust ZIP file generation for all certificates associated with a school, accessible via school search results and a dedicated shortcode `[school_bulk_certificate_download]` with progress tracking.
*   **Improved Student Bulk Download:** ZIP download for students with multiple certificates from the `[student_search]` shortcode.
*   **Enhanced Error Handling:** More detailed and user-friendly error messages on the frontend, including a support information section.
*   **Duplicate Prevention Logic:** Strengthened duplicate certificate prevention in search functionalities.
*   **Secure Filename Generation:** Improved uniqueness and security for generated PDF and ZIP filenames.
*   **Code Refinements:** General code cleanup, performance improvements, and alignment of features between student and school search functionalities.
*   Updated `readme.md` with detailed feature explanations, new screenshots (placeholders added), and references to key code files.

### 3.3.0
*   Added activation, deactivation, and uninstall hooks.
*   Introduced database table creation for certificate data.
*   Added support for plugin versioning and update checks.
*   Enhanced file structure for better maintainability.
*   Added new field alignment options (left, right, center).
*   Implemented debug preview mode for precise field positioning.

## Upgrade Notice

### 3.3.1
This version introduces significant UI and functionality enhancements. Review the new admin settings and test frontend shortcodes after upgrading. Clear any caching plugins if frontend changes are not immediately visible.

### 3.3.0
This version includes significant database and codebase updates. Ensure you back up your site before upgrading.

## Links

*   [Plugin Homepage & Source Code](https://github.com/EshaanManchanda/Certificate-Generator)
*   [Report an Issue](https://github.com/EshaanManchanda/Certificate-Generator/issues)
