# Certificate Generator v7

> A production-ready WordPress plugin for managing, generating, and bulk-sending PDF certificates for students, teachers, and schools — with QR codes, serial numbers, expiration tracking, analytics, and custom SQL tables.

**Version:** 7.0.0 &nbsp;|&nbsp; **Author:** [Eshaan Manchanda](https://www.linkedin.com/in/eshaan-manchanda/) &nbsp;|&nbsp; **License:** GPL-2.0+ &nbsp;|&nbsp; **Requires:** WordPress 6.0+, PHP 7.4+

---

## Why Certificate Generator v7?

Most certificate plugins are simple — they stamp a name on a template and call it done. This plugin is built for organizations running large-scale certificate programs:

- **Thousands of recipients** handled via queue-based bulk email with rate limiting
- **QR codes** on every certificate link to a live verification page
- **Serial numbers** uniquely identify each certificate and are verifiable via REST API
- **Expiration tracking** so certificates can have real validity periods
- **SQL-first architecture** for performance at scale, with full CPT fallback
- **Analytics dashboard** with charts and CSV exports

---

## Feature Overview

| Feature | Description |
|---------|-------------|
| PDF Generation | FPDF-based PDF with customizable templates |
| QR Code | Embedded QR linking to live verification page |
| Serial Numbers | Sequential, prefixed, optionally date-stamped |
| REST Verification | `GET /wp-json/certificate-generator/v1/verify/{serial}` |
| Expiration | Per-template: days / months / years / never |
| Bulk Email | Queue-based with rate limiting, logging, retry |
| Bulk Import | CSV upload for students, teachers, schools |
| Bulk Export | CSV export with filters |
| Analytics | Charts + expiration/monthly CSV reports |
| SQL Tables | 11 custom tables, CPT mirror, live sync |
| CPT Migration | One-click + rollback support |
| Public Pages | Shortcode search forms for all entity types |

---

## Screenshots

### Admin Dashboard

The main WordPress admin view after plugin activation. All certificate management lives under the **Certificates** top-level menu.

![Admin View](_dev/screenshots/Admin%20View.png)

---

### Certificate Templates

The certificate admin list shows all created certificate types. Each template controls which fields appear, QR code position, serial number display, and expiration period.

![Certificate Admin](_dev/screenshots/certificate-admin.png)

> **Tip:** Click **Add New** to create a new certificate type. Each type gets its own PDF template, QR settings, and expiry rules.

---

### Sample Generated Certificate

A real PDF output from the plugin — background template with student name, certificate type, serial number, QR code, and issue date all rendered automatically.

![Sample Certificate](_dev/screenshots/sample-certificate.png)

---

### Template Design (Canva)

Design your certificate background in Canva (or any tool), export as PNG/JPG, and upload it as the template image. The plugin overlays text, QR, and serial on top.

![Canva Design](_dev/screenshots/canva.png)

---

### Preview Button

Before sending, use the **Preview** button on the template edit page to generate a sample PDF and verify positioning of all elements.

![Preview Button](_dev/screenshots/preview%20button.png)

---

### Visibility Settings

Control which fields appear on the public-facing certificate search result page. Toggle name, email, certificate type, issue date, expiry, and serial number visibility per template.

![Visibility Settings](_dev/screenshots/visibility.png)

---

## Student Management

### Students List Page

All student records in a sortable, filterable admin list. Columns show name, email, certificate type, issue date, and email delivery status at a glance.

> **Screenshot:** `screenshots/students-list.png` *(add your screenshot here)*

---

### Student Edit / Add New

The student edit screen captures: name, email, school, certificate type, issue date, and any extra custom fields defined for your certificate program.

> **Screenshot:** `screenshots/student-edit.png` *(add your screenshot here)*

---

### Student Public Search Shortcode

Add `[student_certificate_search]` to any page. Visitors search by name or email to find their certificate — no login required.

![Student Shortcode](_dev/screenshots/student%20shotcode.png)

---

### Student Search Form (Frontend)

The live public-facing form. On match, the certificate details are shown with a download button and QR-linked verification status.

![Student Search Form](_dev/screenshots/student%20search%20form.png)

---

## Teacher Management

### Teachers List Page

Same powerful admin list as students — sortable by name, email, certificate type, and email status. Supports bulk actions.

> **Screenshot:** `screenshots/teachers-list.png` *(add your screenshot here)*

---

### Teacher Public Search Shortcode

```
[teacher_certificate_search]
```

![Teacher Shortcode](_dev/screenshots/teacher%20shotcode.png)

---

### Teacher Search Form (Frontend)

![Teacher Search Form](_dev/screenshots/teacher%20search%20form.png)

---

## School Management

### Schools List Page

Manage school/institution records. Schools can be associated with students and teachers for filtered certificate generation.

> **Screenshot:** `screenshots/schools-list.png` *(add your screenshot here)*

---

### School Public Search Shortcode

```
[school_certificate_search]
```

![School Shortcode](_dev/screenshots/school%20shotcode.png)

---

### School Search Form (Frontend)

![School Search Form](_dev/screenshots/school%20search%20form.png)

---

## Certificate Verification

### Verification Shortcode

```
[cg_verify_certificate title="Verify Your Certificate" description="Enter your serial number below."]
```

Place this on any page. When a student scans the QR code on their PDF, they land directly on this page — the serial number is pre-filled and verified automatically.

> **Screenshot:** `screenshots/verification-page.png` *(add your screenshot here)*

---

### Verification Result

On a valid serial: student name, certificate type, issue date, expiry status, and a green "Certificate is Valid" badge are displayed. Expired certificates show a red status.

> **Screenshot:** `screenshots/verification-result.png` *(add your screenshot here)*

---

## Bulk Email

### Bulk Email Send Page

Filter recipients by certificate type, school, email status, or date range. Select all or individual rows, then send with one click. Emails are queued and processed in batches to respect server limits.

> **Screenshot:** `screenshots/bulk-email.png` *(add your screenshot here)*

---

### Email Logs

Every email sent is logged with recipient, timestamp, status (sent / failed / queued), and error message if applicable. Searchable and filterable.

> **Screenshot:** `screenshots/email-logs.png` *(add your screenshot here)*

---

### Email Template

Configure subject line, HTML body, and from-address in **Certificates → Settings → Email**. All placeholders are supported in subject and body.

**Available placeholders:**

| Placeholder | Value |
|-------------|-------|
| `{name}` | Recipient name |
| `{certificate_title}` | Certificate type |
| `{serial_number}` | Unique serial number |
| `{expires_at}` | Expiry date or "Never" |
| `{result_link}` | Link to result page |
| `{verify_link}` | Link to verification page |
| `{certificate_count}` | Number of certificates |
| `{email}` | Recipient email address |
| `{zip_link}` | ZIP download link |

---

## Bulk Import

### Import Page

Navigate to **Certificates → Bulk Import**. Upload a CSV file — the plugin validates columns, previews the data, and inserts records into both CPT and SQL tables simultaneously.

![Bulk Import](_dev/screenshots/bulk-import.png)

---

### CSV Format — Students

Required columns and example data for importing students in bulk.

![Excel Student Data](_dev/screenshots/excel-student-data.png)

---

### CSV Format — Teachers

![Excel Teacher Data](_dev/screenshots/excel-teacher-data.png)

---

### CSV Format — Schools

![Excel School Data](_dev/screenshots/excel-school-data.png)

---

### CSV Format — Certificates

![Excel Certificate Data](_dev/screenshots/excel-certificate-data.png)

> **Tip:** Download the sample CSV from the import page — it includes all required headers pre-filled.

---

## Bulk Export

### Export Page

Filter by entity type, certificate type, school, date range, or email status. Export the result as a CSV file with one click.

![Export](_dev/screenshots/export.png)

---

### Bulk Certificate Download

Select multiple records and download all their PDFs as a single ZIP file — useful for printing or offline distribution.

> **Screenshot:** `screenshots/bulk-download.png` *(add your screenshot here)*

---

## Analytics Dashboard

### Overview Stats

Six at-a-glance KPI cards: total certificates, issued this month, with serial number, expiring in 30 days, expired, and average per day.

> **Screenshot:** `screenshots/analytics-overview.png` *(add your screenshot here)*

---

### Issuance Over Time Chart

Line chart of certificates issued per day over the last 90 days. Identifies spikes around events or deadlines.

> **Screenshot:** `screenshots/analytics-chart.png` *(add your screenshot here)*

---

### Certificates by Type

Pie/bar chart breaking down how many certificates each type has — useful for understanding program distribution.

> **Screenshot:** `screenshots/analytics-by-type.png` *(add your screenshot here)*

---

### Generation Method Distribution

Shows how certificates were created — manual, bulk CSV, API, or automatic (webhook). Identifies which channels are most active.

> **Screenshot:** `screenshots/analytics-method.png` *(add your screenshot here)*

---

### Expiration Status Chart

Donut chart: Valid vs. Expiring Soon (30 days) vs. Expired. Instantly see how many certificates need attention.

> **Screenshot:** `screenshots/analytics-expiration.png` *(add your screenshot here)*

---

### CSV Reports

Two downloadable CSV reports direct from the analytics page:
- **Expiration Report** — all certificates with an expiry date, sorted soonest first
- **Monthly Report** — count of certificates issued per month in dd-mm-yyyy format

> **Screenshot:** `screenshots/analytics-reports.png` *(add your screenshot here)*

---

## Serial Numbers

### Serial Number Settings Page

Navigate to **Certificates → Serial Numbers** to configure:
- **Prefix** (e.g. `CERT`, `GEMA`, `OLYMPIAD`)
- **Number length** (zero-padded to N digits)
- **Suffix** (optional trailing label)
- **Include date** in serial (e.g. `CERT-20260406-00000001`)
- **Reset period** (none / daily / monthly / yearly)

> **Screenshot:** `screenshots/serial-settings.png` *(add your screenshot here)*

---

### Bulk Serial Generation Page

Navigate to **Certificates → Bulk Serials** to assign serial numbers to existing records that don't have one yet. Choose entity type, run generation, and see results in the table below.

> **Screenshot:** `screenshots/bulk-serials.png` *(add your screenshot here)*

---

## Plugin Settings

### General Settings

Control plugin-wide options: QR code default settings, PDF output mode, verification page URL, and template paths.

> **Screenshot:** `screenshots/settings-general.png` *(add your screenshot here)*

---

### Email / SMTP Settings

Configure the from-address, SMTP credentials, rate limits (per-email-per-day, global-per-hour), and email template defaults.

> **Screenshot:** `screenshots/settings-email.png` *(add your screenshot here)*

---

## SQL Migration

### Migration Page

Navigate to **Certificates → SQL Migration**. Shows record counts in CPT vs. SQL tables side by side. Run migration to copy all CPT data into SQL. After verifying counts match, optionally clean up CPT records.

> **Screenshot:** `screenshots/migration-page.png` *(add your screenshot here)*

---

### Rollback

Made a mistake? Click **↩ Rollback Migration** — all SQL tables are truncated and reset to zero. CPT data is untouched. Re-run migration at any time.

> **Screenshot:** `screenshots/migration-rollback.png` *(add your screenshot here)*

---

## REST API

### Verify Certificate

```
GET /wp-json/certificate-generator/v1/verify/{serial_number}
```

No authentication required — designed for public verification.

**Success response (200):**
```json
{
  "valid": true,
  "expired": false,
  "message": "Certificate is valid",
  "data": {
    "id": 123,
    "student_name": "Eshaan Manchanda",
    "certificate_type": "Participants",
    "issued_at": "05-04-2026",
    "expires_at": "05-04-2027",
    "serial_number": "CERT-00000001",
    "created_at": "05-04-2026"
  }
}
```

**Not found response (404):**
```json
{
  "valid": false,
  "message": "Certificate not found",
  "data": null
}
```

---

## Custom SQL Tables

| Table | Purpose |
|-------|---------|
| `wp_cg_students` | Student records with `extra_fields` JSON |
| `wp_cg_teachers` | Teacher records with `extra_fields` JSON |
| `wp_cg_schools` | School/institution records |
| `wp_cg_certificate_templates` | Template definitions (QR, serial, expiration) |
| `wp_cg_certificates` | Generated certificate records |
| `wp_cg_email_logs` | Email delivery tracking |
| `wp_cg_email_queue` | Bulk email queue |
| `wp_cg_student_certificates` | Student–certificate relationships |
| `wp_cg_teacher_certificates` | Teacher–certificate relationships |
| `wp_cg_settings` | Plugin settings |
| `wp_cg_migrations` | Migration version tracking |

---

## Cron Jobs

| Hook | Interval | Purpose |
|------|----------|---------|
| `cg_cleanup_qr_codes` | Daily | Delete QR images older than 7 days |
| `cg_check_expiring_certificates` | Daily | Email alerts for certificates expiring within 7 days |
| `cg_cleanup_old_certificates` | Weekly | Archive expired certificates older than 5 years |

---

## Directory Structure

```
Certificate-Generator-v7/
├── certificate-generator.php      # Plugin bootstrap, constants, includes, hooks
├── composer.json                  # PSR-4 autoloading + dev dependencies
│
├── includes/                      # Production code (domain-organized)
│   ├── Admin/                     # Admin pages (analytics, bulk-email, columns, settings…)
│   ├── API/                       # REST API endpoints
│   ├── Core/                      # CPT registration, security, font manager, license
│   ├── Cron/                      # Scheduled jobs
│   ├── Database/                  # DB migrator
│   ├── Email/                     # Email functions, log, queue, rate-limiter
│   ├── Public/                    # Public search + verification pages
│   └── Services/                  # PDF, QR, serial, bulk import/export, download
│
├── src/                           # PSR-4 OOP layer (loaded via Composer autoload)
│   ├── Admin/Pages/               # Admin page classes
│   ├── API/                       # REST controllers + rate-limit middleware
│   ├── Certificate/               # Expiration logic
│   ├── Core/                      # DI container, config, Plugin bootstrap
│   ├── Database/                  # Repository pattern, migrations, CPT→SQL
│   ├── Email/                     # Mailer wrapper
│   ├── Exception/                 # Custom exceptions
│   ├── Helpers/                   # Sanitizer, Formatter
│   ├── Integrations/              # GEMA MERN API client
│   ├── Models/                    # Certificate, EmailLog models
│   ├── Services/                  # Certificate, Email, QR, Serial, FieldManager
│   └── Traits/                    # Singleton
│
├── lib/
│   ├── fpdf/                      # FPDF PDF library
│   └── phpqrcode/                 # phpqrcode library
│
├── assets/
│   ├── css/                       # Admin + public stylesheets
│   ├── js/                        # Admin + public scripts
│   └── data/                      # Sample CSV templates for bulk import
│
└── templates/
    └── single-students.php        # Public student profile page template
```

---

## Changelog

### 7.0.0
- QR code generation with configurable size, position, error correction
- Unique serial number system — sequential, prefixed, date-stamped, resettable
- REST verification API (`/verify/{serial}`)
- Expiration management per template (days / months / years / never)
- Analytics dashboard: 6 KPI cards, 4 charts, 2 CSV exports
- Bulk serial assignment for existing records
- Public verification shortcode with QR auto-trigger (URL `?serial_number=`)
- CPT → SQL migration with one-click rollback
- SQL-first reads in admin list columns and email functions
- Duplicate serial prevention: same student + certificate type reuses existing serial
- Date display standardized to dd-mm-yyyy format everywhere
- phpqrcode PHP 8.x deprecation warnings suppressed
- Domain-organized `includes/` structure
- PSR-4 `src/` architecture with DI container, typed classes, custom exceptions

### 6.1.0
- Initial release: certificate generation, bulk email, custom post types

---

## License

GPL-2.0+ — [https://www.gnu.org/licenses/gpl-2.0.txt](https://www.gnu.org/licenses/gpl-2.0.txt)

---

## Contact & Support

Built and maintained by **Eshaan Manchanda**.

If you're interested in using this plugin, need customization, or want to discuss integration with your platform — reach out:

- **LinkedIn:** [linkedin.com/in/eshaan-manchanda](https://www.linkedin.com/in/eshaan-manchanda/)
- **GitHub Issues:** [github.com/eshaanmanchanda/certificate-generator/issues](https://github.com/eshaanmanchanda/certificate-generator/issues)
