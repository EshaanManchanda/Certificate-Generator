# Table Map — Certificate-Generator-v7

> Generated: 2026-06-13 as part of v8 Phase -1 architecture inventory.

---

## Live tables (hold actual data)

### `wp_certificate_generator`
- **PK:** `cg_id` (not `id`)
- **Role:** Anchor table. Every certificate row. PDF path/URL written to postmeta via `update_post_meta`.
- **Key cols:** `cg_id`, `email`, `student_name`, `certificate_type`, `school_name`, `serial_number`, `file_url`, `send_email`
- **Who reads:** `includes/Email/functions.php` (cg_get_certs_by_email), `includes/Services/*`, `includes/Admin/*`, `src/Admin/Pages/*`
- **FK'd by:** `wp_cert_email_logs.cg_id`, `wp_cert_email_queue.certificate_id`

### `wp_cert_email_logs`
- **PK:** `id`
- **FK:** `cg_id` → `wp_certificate_generator.cg_id`
- **Role:** Append-only log of every send attempt.
- **Key cols:** `id`, `cg_id`, `email`, `status` (sent/failed), `created_at`, `error_message`
- **Who reads:** `includes/Admin/email-logs.php`, `src/Services/EmailStatusService.php`, badge in StudentsPage

### `wp_cert_email_queue`
- **PK:** `id`
- **FK:** `certificate_id` → `wp_certificate_generator.cg_id`
- **Role:** Queue for async/batch sends. Status machine: `pending → sending → sent | failed`.
- **Key cols:** `id`, `certificate_id`, `email`, `status`, `attempts`, `last_attempt_at`, `updated_at`
- **Who reads:** `includes/Email/queue.php`, `includes/Services/bulk-email-sender.php`, `src/Services/EmailStatusService.php`

---

## Dead scaffold tables (exist in DB, hold no live data)

> Created by `src/Database/CustomTables.php`. Live admin UI writes to `wp_certificate_generator`
> and `wp_cert_email_*`, not these. They will be confirmed empty and removed in Phase 3C.

| Scaffold table | Model class | PK | Status |
|---|---|---|---|
| `wp_cg_students` | `src/Models/Student.php` | `id` | Dead — retarget or remove in Phase 3 |
| `wp_cg_teachers` | *(none)* | `id` | Dead |
| `wp_cg_schools` | *(none)* | `id` | Dead |
| `wp_cg_certificates` | `src/Models/Certificate.php` | `id` | Dead |
| `wp_cg_email_logs` | `src/Models/EmailLog.php` | `id` | Dead — live logs are in `wp_cert_email_logs` |
| `wp_cg_email_queue` | *(none)* | `id` | Dead — live queue is in `wp_cert_email_queue` |
| `wp_cg_certificate_templates` | *(none)* | `id` | Dead |
| `wp_cg_student_certificates` | *(none)* | `id` | Dead |
| `wp_cg_teacher_certificates` | *(none)* | `id` | Dead |
| `wp_cg_settings` | *(none)* | `id` | Dead |
| `wp_cg_migrations` | *(none)* | `id` | Dead |

---

## Phase 3 reconciliation plan

| Model | Current `$table` | Target `$table` | Note |
|---|---|---|---|
| `EmailLog` | `cg_email_logs` | `cert_email_logs` | Direct swap |
| `Certificate` | `cg_certificates` | `certificate_generator` | PK override `cg_id` |
| `Student` | `cg_students` | `certificate_generator` | Students ARE cert rows in live schema |

> **Verify before switching:** `SELECT COUNT(*) FROM wp_cg_students` vs
> `SELECT COUNT(*) FROM wp_certificate_generator` to confirm scaffold is empty.

---

## Migrations (live)

| File | Adds | Status |
|---|---|---|
| `src/Database/Migrations/Migration001_AddTimeColumns.php` | time columns | live |
| `src/Database/Migrations/Migration002_AddSendEmailColumn.php` | `send_email` to cert table | live |
| `includes/Database/migration-queue-columns.php` | `last_attempt_at`, indexes on queue | live (Phase-1) |
