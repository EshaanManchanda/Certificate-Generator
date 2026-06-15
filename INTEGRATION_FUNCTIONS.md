# Certificate Generator v7 — Integration Functions

> Public functions available for use by other WordPress plugins. Always wrap calls with `function_exists()` checks.

---

## Table of Contents

1. [Certificate Lookup](#1-certificate-lookup)
2. [Certificate Generation](#2-certificate-generation)
3. [Email Sending](#3-email-sending)
4. [Email Queue & Bulk Operations](#4-email-queue--bulk-operations)
5. [Email Logs & Stats](#5-email-logs--stats)
6. [Rate Limiting](#6-rate-limiting)
7. [Filters & Recipients](#7-filters--recipients)
8. [Settings & Config](#8-settings--config)
9. [REST API Endpoints](#9-rest-api-endpoints)
10. [WordPress Hooks](#10-wordpress-hooks)
11. [Quick Start Examples](#11-quick-start-examples)

---

## 1. Certificate Lookup

### `cg_get_certs_by_email(string $email): array`
**File:** `includes/Email/functions.php:38`

Returns all certificate rows from `wp_certificate_generator` table matching the given email. Built-in static caching.

```php
if (function_exists('cg_get_certs_by_email')) {
    $certs = cg_get_certs_by_email('student@example.com');
    // Each row: id, student_name, email, certificate_data (JSON), certificate_type, pdf_path, serial_number, issued_at, generated_via
}
```

### `certificate_generator_find_certificates_by_email_table(string $email): array`
**File:** `includes/API/endpoints.php:817`

Returns certificates from `wp_cg_certificates` table by recipient email.

```php
if (function_exists('certificate_generator_find_certificates_by_email_table')) {
    $certs = certificate_generator_find_certificates_by_email_table('student@example.com');
}
```

### `certificate_generator_find_students_by_email_table(string $email): array`
**File:** `includes/API/endpoints.php:799`

Returns student records from `wp_cg_students` table by email.

```php
if (function_exists('certificate_generator_find_students_by_email_table')) {
    $students = certificate_generator_find_students_by_email_table('student@example.com');
}
```

### `certificate_generator_find_certificates_by_student_table(int $studentId): array`
**File:** `includes/API/endpoints.php:808`

Returns all certificates for a specific student ID from `wp_cg_certificates` table.

```php
if (function_exists('certificate_generator_find_certificates_by_student_table')) {
    $certs = certificate_generator_find_certificates_by_student_table(42);
}
```

### `cg_email_get_sql_row(int $post_id, string $post_type): ?array`
**File:** `includes/Email/functions.php:99`

Fetches a flattened entity row from SQL tables by WP post ID. Returns null if CustomTables unavailable.

```php
if (function_exists('cg_email_get_sql_row')) {
    $row = cg_email_get_sql_row(123, 'students');
    // Returns merged row + extra_fields
}
```

---

## 2. Certificate Generation

### `generate_certificate_pdf_with_data(array $post_data): string|false`
**File:** `includes/Services/certificate-search.php:667`

Generates a PDF certificate from raw data array. Returns file path on success, false on failure.

```php
if (function_exists('generate_certificate_pdf_with_data')) {
    $result = generate_certificate_pdf_with_data([
        'student_name'     => 'John Doe',
        'email'            => 'john@example.com',
        'certificate_type' => 'completion',
        'issue_date'       => '2025-01-15',
        'school_name'      => 'ABC Academy',
        // ... any template field values
    ]);
    // $result = '/path/to/certificate.pdf' or false
}
```

### `generate_certificate_pdf($post_id, $fields, $student_data = null): string|false`
**File:** `includes/Services/certificate-search.php:1319`

Generates a PDF for an existing WP post (students/teachers/schools CPT).

```php
if (function_exists('generate_certificate_pdf')) {
    $pdf_url = generate_certificate_pdf($post_id, ['student_name', 'school_name', 'issue_date']);
}
```

### `cg_generate_pdf_from_row(array $row): ?string`
**File:** `includes/Email/functions.php:55`

Generates or retrieves an existing PDF for a `wp_certificate_generator` row. Returns filesystem path.

```php
if (function_exists('cg_generate_pdf_from_row')) {
    $pdf_path = cg_generate_pdf_from_row($cert_row);
}
```

### `cg_insert_certificate_record(array $post_data, string $serial_number, string $generated_via = 'manual'): void`
**File:** `includes/Services/certificate-search.php:1093`

Inserts a certificate record into `wp_certificate_generator` table and optionally into `wp_cg_certificates` table.

```php
if (function_exists('cg_insert_certificate_record')) {
    cg_insert_certificate_record([
        'student_name'     => 'Jane Smith',
        'email'            => 'jane@example.com',
        'certificate_type' => 'achievement',
        'issue_date'       => '2025-03-20',
    ], 'CERT-2025-00123', 'api');
}
```

### `certificate_generator_create_zip_for_email(array $certificates_data, string $recipient_email): array|false`
**File:** `includes/Email/functions.php:120`

Creates a ZIP file containing multiple certificate PDFs.

```php
if (function_exists('certificate_generator_create_zip_for_email')) {
    $result = certificate_generator_create_zip_for_email([
        ['path' => '/path/to/cert1.pdf', 'filename' => 'cert1.pdf', 'post_id' => 1],
        ['path' => '/path/to/cert2.pdf', 'filename' => 'cert2.pdf', 'post_id' => 2],
    ], 'student@example.com');
    // Returns: ['zip_path' => '...', 'zip_url' => '...', 'certificate_count' => 2, 'failed_count' => 0, 'failed_files' => []]
}
```

---

## 3. Email Sending

### `certificate_generator_send_email(int $cg_id, bool $log_email = true): bool`
**File:** `includes/Email/functions.php:218`

Sends a certificate email for a `wp_certificate_generator` row ID. Handles single PDF or grouped ZIP automatically.

```php
if (function_exists('certificate_generator_send_email')) {
    $sent = certificate_generator_send_email(42); // cg_id from wp_certificate_generator
}
```

### `certificate_generator_send_custom_email(string $to, string $subject, string $message, string $name = '', string $certificate_title = '', string $pdf_path = '', array $options = []): bool`
**File:** `includes/Email/functions.php:838`

Sends a custom HTML email with optional PDF attachment. Supports `{name}`, `{certificate_title}` placeholders.

```php
if (function_exists('certificate_generator_send_custom_email')) {
    $sent = certificate_generator_send_custom_email(
        'student@example.com',
        'Your Certificate is Ready',
        'Dear {name}, your {certificate_title} certificate is attached.',
        'John Doe',
        'Course Completion',
        '/path/to/certificate.pdf',
        ['reply_to' => 'admin@site.com', 'cc' => ['copy@site.com']]
    );
}
```

### `certificate_generator_send_bulk_emails(string $post_type, bool $skip_already_sent = true, array $specific_post_ids = []): array`
**File:** `includes/Email/functions.php:705`

Sends certificate emails in bulk. Groups multiple certs per email into ZIP files.

```php
if (function_exists('certificate_generator_send_bulk_emails')) {
    $results = certificate_generator_send_bulk_emails('students', true);
    // Returns: ['success' => 50, 'failure' => 2, 'skipped' => 10, 'errors' => [], 'total' => 62, 'emails_sent' => 45, 'grouped_sends' => 8]
}
```

### `certificate_generator_auto_send_email(int $post_id, bool $force_send = false): bool`
**File:** `includes/Email/functions.php:805`

Auto-sends certificate email when a certificate is generated. Respects the `certificate_generator_auto_send_enabled` option.

```php
if (function_exists('certificate_generator_auto_send_email')) {
    certificate_generator_auto_send_email($post_id, true); // force_send bypasses setting
}
```

### `certificate_generator_send_fallback_email(string $to, string $subject, string $message, string $attachment = '', string $reply_to = '', string $cc = '', string $bcc = ''): bool`
**File:** `includes/Email/functions.php:964`

Simple email sender with fallback system (SMTP or PHP mail).

```php
if (function_exists('certificate_generator_send_fallback_email')) {
    certificate_generator_send_fallback_email(
        'user@example.com', 'Subject', 'Message body', '/path/to/file.pdf'
    );
}
```

---

## 4. Email Queue & Bulk Operations

### `certificate_generator_queue_email(int $cg_id, string $recipient_email, array $options = []): int|false`
**File:** `includes/Email/queue.php:61`

Adds a certificate email to the queue table. Options: `scheduled_time`, `priority`.

```php
if (function_exists('certificate_generator_queue_email')) {
    $queue_id = certificate_generator_queue_email(42, 'student@example.com', [
        'scheduled_time' => '2025-01-20 10:00:00',
        'priority' => 1, // 1 = highest, 10 = lowest
    ]);
}
```

### `certificate_generator_bulk_queue_emails(string $post_type = '', array $cg_ids = [], bool $skip_already_sent = false): array`
**File:** `includes/Email/queue.php:303`

Queues bulk emails grouped by unique recipient. Returns counts.

```php
if (function_exists('certificate_generator_bulk_queue_emails')) {
    $result = certificate_generator_bulk_queue_emails('students', [], true);
    // Returns: ['queued' => 50, 'skipped' => 10, 'errors' => [], 'unique_emails' => 40, 'grouped_emails' => 8]
}
```

### `certificate_generator_get_next_batch(int $limit = 10): array`
**File:** `includes/Email/queue.php:119`

Gets next batch of pending queue items ready to send.

```php
if (function_exists('certificate_generator_get_next_batch')) {
    $batch = certificate_generator_get_next_batch(20);
}
```

### `certificate_generator_update_queue_status(int $queue_id, string $status, string $error_message = null): bool`
**File:** `includes/Email/queue.php:147`

Updates queue item status: `pending`, `sending`, `sent`, `failed`.

```php
if (function_exists('certificate_generator_update_queue_status')) {
    certificate_generator_update_queue_status($queue_id, 'sent');
}
```

### `certificate_generator_get_queue_stats(): array`
**File:** `includes/Email/queue.php:189`

Returns queue statistics.

```php
if (function_exists('certificate_generator_get_queue_stats')) {
    $stats = certificate_generator_get_queue_stats();
    // ['total' => 100, 'pending' => 30, 'sending' => 5, 'sent' => 60, 'failed' => 5, ...]
}
```

### `certificate_generator_retry_failed_emails(int $max_attempts = 3): int`
**File:** `includes/Email/queue.php:265`

Resets failed queue items back to pending for retry.

```php
if (function_exists('certificate_generator_retry_failed_emails')) {
    $reset_count = certificate_generator_retry_failed_emails(3);
}
```

### `certificate_generator_start_bulk_send(string $post_type, array $post_ids = []): array`
**File:** `includes/Services/bulk-email-sender.php:171`

Starts a bulk send operation via the queue processor.

```php
if (function_exists('certificate_generator_start_bulk_send')) {
    $result = certificate_generator_start_bulk_send('students', [1, 2, 3]);
}
```

### `certificate_generator_process_queue_now(int $num_batches = 1): array`
**File:** `includes/Services/bulk-email-sender.php:128`

Processes queue items immediately (bypasses cron).

```php
if (function_exists('certificate_generator_process_queue_now')) {
    $results = certificate_generator_process_queue_now(5);
}
```

### `certificate_generator_get_queue_progress(): array`
**File:** `includes/Services/bulk-email-sender.php:237`

Returns current bulk send progress.

```php
if (function_exists('certificate_generator_get_queue_progress')) {
    $progress = certificate_generator_get_queue_progress();
}
```

### `certificate_generator_pause_queue(): void`
**File:** `includes/Services/bulk-email-sender.php:208`

Pauses the queue processor.

### `certificate_generator_resume_queue(): void`
**File:** `includes/Services/bulk-email-sender.php:217`

Resumes the queue processor.

### `certificate_generator_is_queue_paused(): bool`
**File:** `includes/Services/bulk-email-sender.php:228`

Checks if queue processing is paused.

### `certificate_generator_clear_queue(): void`
**File:** `includes/Services/bulk-email-sender.php:273`

Clears all queue items.

---

## 5. Email Logs & Stats

### `certificate_generator_log_email(int $certificate_id, string $recipient_email, string $recipient_name = '', string $certificate_type = '', string $email_subject = '', bool $success = true, string $error_message = ''): int|false`
**File:** `includes/Email/log.php:146`

Logs an email send attempt to `wp_cert_email_logs` table.

```php
if (function_exists('certificate_generator_log_email')) {
    $log_id = certificate_generator_log_email(42, 'student@example.com', 'John Doe', 'completion', 'Your Certificate', true);
}
```

### `certificate_generator_get_email_logs(array $args = []): array`
**File:** `includes/Email/log.php:182`

Retrieves email logs with filtering and pagination.

```php
if (function_exists('certificate_generator_get_email_logs')) {
    $logs = certificate_generator_get_email_logs([
        'per_page' => 20,
        'page' => 1,
        'status' => 'sent', // or 'failed'
        'search' => 'example.com',
        'date_from' => '2025-01-01',
        'date_to' => '2025-12-31',
        'certificate_id' => 42,
    ]);
    // Returns: ['logs' => [...], 'total' => 150, 'pages' => 8]
}
```

### `certificate_generator_get_email_stats(): array`
**File:** `includes/Email/log.php:272`

Returns email statistics.

```php
if (function_exists('certificate_generator_get_email_stats')) {
    $stats = certificate_generator_get_email_stats();
    // ['total_sent' => 500, 'total_failed' => 12, 'today' => 25, 'this_week' => 150, 'this_month' => 400, 'success_rate' => 97.6]
}
```

### `certificate_generator_cleanup_email_logs(int $days_to_keep = 90): int`
**File:** `includes/Email/log.php:319`

Deletes old email log entries.

```php
if (function_exists('certificate_generator_cleanup_email_logs')) {
    $deleted = certificate_generator_cleanup_email_logs(30);
}
```

### `certificate_generator_email_already_sent(int $cg_id, string $email): bool`
**File:** `includes/Email/functions.php:788`

Checks if an email was already sent for a certificate.

```php
if (function_exists('certificate_generator_email_already_sent')) {
    if (!certificate_generator_email_already_sent(42, 'student@example.com')) {
        certificate_generator_send_email(42);
    }
}
```

---

## 6. Rate Limiting

### `certificate_generator_can_send_email(): array`
**File:** `includes/Email/rate-limiter.php:38`

Checks if email can be sent now based on rate limits.

```php
if (function_exists('certificate_generator_can_send_email')) {
    $check = certificate_generator_can_send_email();
    // ['can_send' => true, 'reason' => 'Within rate limits', 'wait_seconds' => 0]
    // ['can_send' => false, 'reason' => 'Hourly limit reached (80/80)', 'wait_seconds' => 1200]
}
```

### `certificate_generator_get_sent_count(int $seconds = 3600): int`
**File:** `includes/Email/rate-limiter.php:81`

Returns count of unique emails sent in the last N seconds.

```php
if (function_exists('certificate_generator_get_sent_count')) {
    $sent_last_hour = certificate_generator_get_sent_count(3600);
    $sent_last_min  = certificate_generator_get_sent_count(60);
}
```

### `certificate_generator_estimate_send_time(int $num_emails): array`
**File:** `includes/Email/rate-limiter.php:193`

Estimates how long it will take to send N emails.

```php
if (function_exists('certificate_generator_estimate_send_time')) {
    $est = certificate_generator_estimate_send_time(500);
    // ['num_emails' => 500, 'emails_per_hour' => 80, 'hours_needed' => 7, 'estimated_completion' => '...', 'estimated_completion_human' => '7 hours']
}
```

### `certificate_generator_get_rate_limit_status(): array`
**File:** `includes/Email/rate-limiter.php:155`

Returns full rate limit status and usage.

### `certificate_generator_update_rate_limit_config(array $config): bool`
**File:** `includes/Email/rate-limiter.php:233`

Updates rate limit settings programmatically.

```php
if (function_exists('certificate_generator_update_rate_limit_config')) {
    certificate_generator_update_rate_limit_config([
        'emails_per_hour' => 100,
        'emails_per_minute' => 15,
        'batch_size' => 20,
        'batch_delay' => 300,
        'enabled' => true,
    ]);
}
```

### `certificate_generator_reset_rate_limits(): bool`
**File:** `includes/Email/rate-limiter.php:217`

Resets rate limit counters (for testing).

### `certificate_generator_get_wait_time_for_hourly_reset(): int`
**File:** `includes/Email/rate-limiter.php:110`

Returns seconds to wait until hourly limit resets.

### `certificate_generator_format_wait_time(int $seconds): string`
**File:** `includes/Email/rate-limiter.php:258`

Formats wait time into human-readable string.

---

## 7. Filters & Recipients

### `certificate_generator_get_filtered_recipients(array $filters = []): array`
**File:** `includes/Admin/filters-api.php:204`

Gets recipients matching filter criteria. Supports school, certificate type, email status, email search.

```php
if (function_exists('certificate_generator_get_filtered_recipients')) {
    $recipients = certificate_generator_get_filtered_recipients([
        'post_types'       => ['students', 'teachers'],
        'schools'          => ['ABC Academy'],
        'certificate_types'=> ['completion'],
        'email_status'     => ['not_sent'],
        'email_search'     => '@gmail.com',
        'skip_already_sent'=> true,
        'limit'            => 100,
        'offset'           => 0,
    ]);
}
```

### `certificate_generator_count_filtered_recipients(array $filters = []): int`
**File:** `includes/Admin/filters-api.php:359`

Counts recipients matching filter criteria (same args as `get_filtered_recipients`).

### `certificate_generator_get_filter_statistics(array $filters = []): array`
**File:** `includes/Admin/filters-api.php:545`

Returns statistics for filtered recipients including email grouping info.

```php
if (function_exists('certificate_generator_get_filter_statistics')) {
    $stats = certificate_generator_get_filter_statistics($filters);
    // ['total_certificates' => 100, 'unique_emails' => 85, 'will_send' => 90, 'will_skip' => 10, 'no_email' => 5, 'grouped_sends' => 12, 'email_groups' => [...]]
}
```

### `certificate_generator_get_unique_schools(string|array $post_types = ['students', 'teachers', 'schools']): array`
**File:** `includes/Admin/filters-api.php:20`

Returns unique school names across entities. Cached.

### `certificate_generator_get_unique_certificate_types(string|array $post_types = ['students', 'teachers', 'schools']): array`
**File:** `includes/Admin/filters-api.php:73`

Returns unique certificate types. Cached.

### `certificate_generator_get_unique_emails(string|array $post_types = ['students', 'teachers', 'schools']): array`
**File:** `includes/Admin/filters-api.php:133`

Returns unique email addresses from post meta.

### `certificate_generator_get_email_status_for_posts(array $post_ids): array`
**File:** `includes/Admin/filters-api.php:165`

Returns email send status map for multiple post IDs.

```php
if (function_exists('certificate_generator_get_email_status_for_posts')) {
    $statuses = certificate_generator_get_email_status_for_posts([1, 2, 3, 4, 5]);
    // [1 => ['sent' => true, 'last_sent' => '2025-01-15 10:30:00'], 2 => ['sent' => false, 'last_sent' => null], ...]
}
```

### `certificate_generator_parse_email_list(string $email_list_text): array`
**File:** `includes/Admin/filters-api.php:518`

Parses comma/newline/space-separated email list into validated array.

```php
if (function_exists('certificate_generator_parse_email_list')) {
    $emails = certificate_generator_parse_email_list("a@b.com, c@d.com; e@f.com\ng@h.com");
    // ['a@b.com', 'c@d.com', 'e@f.com', 'g@h.com']
}
```

---

## 8. Settings & Config

### `certificate_generator_get_contact_email(): string`
**File:** `includes/Admin/settings.php:652`

Returns the configured contact email, falling back to WP admin email.

### `certificate_generator_get_from_email(): string`
**File:** `includes/Email/functions.php:1027`

Returns the appropriate "from" email based on SMTP configuration.

### `certificate_generator_get_email_status(): array`
**File:** `includes/Email/functions.php:1044`

Returns email delivery method status.

```php
if (function_exists('certificate_generator_get_email_status')) {
    $status = certificate_generator_get_email_status();
    // ['method' => 'WP Mail SMTP', 'from_email' => 'admin@site.com', 'smtp_configured' => true, ...]
}
```

### `certificate_generator_email_health_check(): array`
**File:** `includes/Email/functions.php:1061`

Comprehensive email system health check.

```php
if (function_exists('certificate_generator_email_health_check')) {
    $health = certificate_generator_email_health_check();
    // ['overall_status' => 'healthy', 'issues' => [], 'recommendations' => [], 'details' => [...]]
}
```

### `certificate_generator_is_wp_mail_smtp_active(): bool`
**File:** `includes/Email/functions.php` (line ~1370+)

Checks if WP Mail SMTP plugin is active and configured.

### `certificate_generator_test_email_send(string $test_email, array $options = []): array`
**File:** `includes/Email/functions.php:1295`

Sends a test email and returns results.

```php
if (function_exists('certificate_generator_test_email_send')) {
    $result = certificate_generator_test_email_send('test@example.com');
}
```

---

## 9. REST API Endpoints

All endpoints require API key authentication via `Authorization: Bearer <key>` header (except `/health`).

| Endpoint | Method | Description |
|---|---|---|
| `/certificate-generator/v1/issue-certificate` | POST | Issue a certificate to a student email |
| `/certificate-generator/v1/certificates-by-email` | GET | Get all certificates for an email (read-only) |
| `/certificate-generator/v1/health` | GET | Plugin health check (no auth required) |
| `/certificate-generator/v1/validate-key` | POST | Validate API key |

### Example: Get certificates by email via REST API

```bash
curl -X GET \
  "https://yoursite.com/wp-json/certificate-generator/v1/certificates-by-email?email=student@example.com" \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### Example: Issue certificate via REST API

```bash
curl -X POST \
  "https://yoursite.com/wp-json/certificate-generator/v1/issue-certificate" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"student_email": "student@example.com"}'
```

### Example: Health check (no auth)

```bash
curl "https://yoursite.com/wp-json/certificate-generator/v1/health"
```

---

## 10. WordPress Hooks

### Actions (fired by this plugin)

| Hook | Parameters | Description |
|---|---|---|
| `certificate_generated` | `$post_id`, `$pdf_path`, `$student_data` | Fired after a PDF certificate is created |
| `certificate_generator_email_sent` | `$cg_id`, `$recipient_email`, `$success` | Fired after an email send attempt |

### Filters (available for modification)

| Filter | Parameters | Description |
|---|---|---|
| `certificate_generator_email_subject` | `$subject`, `$cg_id` | Modify email subject before sending |
| `certificate_generator_email_message` | `$message`, `$cg_id` | Modify email body before sending |
| `certificate_generator_email_headers` | `$headers`, `$cg_id` | Modify email headers |
| `certificate_generator_email_attachments` | `$attachments`, `$cg_id` | Modify email attachments |
| `certificate_generator_from_email` | `$from_email` | Override the "from" email address |
| `certificate_generator_from_name` | `$from_name` | Override the "from" name |

### Usage Example

```php
// Modify email subject
add_filter('certificate_generator_email_subject', function($subject, $cg_id) {
    return '[GEMA] ' . $subject;
}, 10, 2);

// Listen for certificate generation
add_action('certificate_generated', function($post_id, $pdf_path, $student_data) {
    error_log('New certificate generated: ' . $post_id . ' at ' . $pdf_path);
}, 10, 3);
```

---

## 11. Quick Start Examples

### Example 1: Find all certificates for an email and send them

```php
if (function_exists('cg_get_certs_by_email') && function_exists('certificate_generator_send_email')) {
    $certs = cg_get_certs_by_email('student@example.com');
    
    if (!empty($certs)) {
        // Send email (will auto-group into ZIP if multiple certs)
        $first_cg_id = $certs[0]['id'];
        $sent = certificate_generator_send_email($first_cg_id);
        
        if ($sent) {
            echo 'Certificates sent successfully!';
        }
    }
}
```

### Example 2: Generate and email a certificate from external data

```php
if (function_exists('generate_certificate_pdf_with_data') && function_exists('cg_insert_certificate_record')) {
    // Generate PDF
    $pdf_path = generate_certificate_pdf_with_data([
        'student_name'     => 'John Doe',
        'email'            => 'john@example.com',
        'certificate_type' => 'completion',
        'issue_date'       => '2025-01-15',
        'school_name'      => 'ABC Academy',
        'course_name'      => 'Web Development 101',
    ]);
    
    if ($pdf_path) {
        // Insert record
        cg_insert_certificate_record([
            'student_name'     => 'John Doe',
            'email'            => 'john@example.com',
            'certificate_type' => 'completion',
            'issue_date'       => '2025-01-15',
            'school_name'      => 'ABC Academy',
            'course_name'      => 'Web Development 101',
            'pdf_path'         => $pdf_path,
        ], 'CERT-2025-00123', 'external_plugin');
        
        // Send email
        certificate_generator_send_custom_email(
            'john@example.com',
            'Your Certificate is Ready',
            'Dear {name}, your {certificate_title} certificate is attached.',
            'John Doe',
            'completion',
            $pdf_path
        );
    }
}
```

### Example 3: Bulk send with rate limiting

```php
if (function_exists('certificate_generator_can_send_email') && function_exists('certificate_generator_send_bulk_emails')) {
    // Check rate limits first
    $rate_check = certificate_generator_can_send_email();
    
    if ($rate_check['can_send']) {
        $results = certificate_generator_send_bulk_emails('students', true);
        echo "Sent: {$results['success']}, Failed: {$results['failure']}, Skipped: {$results['skipped']}";
    } else {
        echo "Rate limited. Wait {$rate_check['wait_seconds']} seconds. Reason: {$rate_check['reason']}";
    }
}
```

### Example 4: Queue emails for scheduled sending

```php
if (function_exists('cg_get_certs_by_email') && function_exists('certificate_generator_queue_email')) {
    $certs = cg_get_certs_by_email('student@example.com');
    
    foreach ($certs as $cert) {
        certificate_generator_queue_email($cert['id'], $cert['email'], [
            'scheduled_time' => '2025-01-20 09:00:00',
            'priority' => 5,
        ]);
    }
}
```

---

## Notes

- **Always check `function_exists()`** before calling any function — the plugin may be deactivated.
- **Database tables:** Custom tables (`wp_certificate_generator`, `wp_cg_students`, `wp_cg_certificates`, `wp_cert_email_logs`, `wp_cert_email_queue`) are created on plugin activation.
- **API Key:** REST API endpoints require the API to be enabled in plugin settings (Pro/Business plan required).
- **Email grouping:** When multiple certificates exist for the same email, `certificate_generator_send_email()` automatically creates a ZIP file.
- **Caching:** Several functions use WordPress transients or static caching for performance.
