# Service Map — Certificate-Generator-v7

> Generated: 2026-06-13 as part of v8 Phase -1 architecture inventory.
> Status: ✅ Live  🔶 Partially wired  ❌ Unwired scaffolding

---

## src/Core/

| Class | Status | Notes |
|---|---|---|
| `Plugin` | ✅ Live | Booted at `plugins_loaded`. Owns DI container, registers services. |
| `Container` | ✅ Live | DI container — singleton/bind/make. |
| `Config` | ❌ Unwired | Class exists with constants but `Config::` has **zero callers** in live code. Phase 0 extends it with queue constants + feature flags. |

## src/Services/

| Class | Status | Notes |
|---|---|---|
| `EmailService` | 🔶 Partial | Container-bound + static facade methods. Live callers: StudentsPage::cg_student_send_email. The OOP instance is NOT used for bulk/cron sends yet. |
| `EmailStatusService` | ✅ Live | Used by StudentsPage badge. Returns Sent/Failed/Queued etc. |
| `CertificateService` | 🔶 Partial | Container-bound (`Plugin.php:52`). `generate()/verify()` exist but no live callers confirmed. Phase 6 makes it the public API. |
| `CertificateGeneratorService` | ❌ Unwired | 4th PDF copy lives here. No live callers (obs 4610). Deprecated in Phase 10, deleted in v9. |
| `SerialNumberService` | ✅ Live | Container-bound; registers REST API routes. |
| `QRCodeService` | ✅ Live | Container-bound; registers template meta fields. |
| `SettingsService` | ✅ Live | Called from `Plugin::boot()` — migrate_legacy + seed_defaults. |
| `BulkIO` | ❌ Unwired | No callers found. |
| `FieldManager` | ❌ Unwired (check) | No callers found in grep. |

## src/Database/

| Class | Status | Notes |
|---|---|---|
| `Repository` | ❌ Unwired base | Abstract base — correct design, zero instances yet. Phase 3A builds repos on top. |
| `CustomTables` | ❌ Dead scaffold | Creates `wp_cg_*` tables. Live data never wrote here. Remove in Phase 3C. |
| `CptToSqlMigration` | ❌ Dead | Migration from CPTs to SQL — CPTs already deregistered. Remove in Phase 3C. |
| `DataMigration` | ❌ Dead | Same. Remove in Phase 3C. |
| `MigrationRunner` | ✅ Live | Called on `Plugin::activate()`. Runs Migration001/002. Phase 8 extends to Migration_8xx. |
| `Migration001` | ✅ Live | Adds time columns. |
| `Migration002` | ✅ Live | Adds `send_email` column. |

## src/Models/

| Class | Status | Notes |
|---|---|---|
| `Student` | ❌ Wrong table | Points at `wp_cg_students` (empty). Reconcile to `wp_certificate_generator` in Phase 3B. |
| `Certificate` | ❌ Wrong table | Points at `wp_cg_certificates` (empty). Reconcile to `wp_certificate_generator` in Phase 3B. |
| `EmailLog` | ❌ Wrong table | Points at `wp_cg_email_logs` (empty). Reconcile to `wp_cert_email_logs` in Phase 3B. |

## src/Email/

| Class | Status | Notes |
|---|---|---|
| `Mailer` | 🔶 Partial | Container-bound. `Mailer::make()` selects transport. Used for admin test-email only (obs 1213); bulk/cron still go through procedural `certificate_generator_send_email()`. Phase 4 routes bulk through it. |
| `WpMailTransport` | ✅ Live | Default transport. |
| `SmtpTransport` | ✅ Live | SMTP transport. |
| `TransportInterface` | ✅ Live | Strategy interface. |

## src/Admin/Pages/

| Class | Status | Notes |
|---|---|---|
| `StudentsPage` | ✅ Live | Registered in `admin_menu`. Uses EmailStatusService for badge. |
| `TeachersPage` | ✅ Live | Registered. |
| `SchoolsPage` | ✅ Live | Registered. |
| `TemplatesPage` | ✅ Live | Registered. |
| `MigrationPage` | ✅ Live | Registered. |
| `AnalyticsPage` | ❌ Not registered | Page exists but `->register()` never called. |
| `EmailSettingsPage` | ❌ Not registered | Same. |
| `SerialSettingsPage` | ❌ Not registered | Same. |
| `MenuOrganizer` | ❌ Dead | Duplicates all bulk menu slugs. Never instantiated. Remove in cleanup. |
| `Page` | ✅ Live | Base class for all pages. |

## src/API/

| Class | Status | Notes |
|---|---|---|
| `Controller` | 🔶 Check | May be used via REST. Confirm in Phase 6. |
| `Middleware/RateLimit` | 🔶 Check | Same. |

## src/Integrations/

| Class | Status | Notes |
|---|---|---|
| `GemaAPI` | 🔶 Partial | Container-bound in Plugin. No confirmed live callers in includes/. |

## src/Helpers/

| Class | Status | Notes |
|---|---|---|
| `DateHelper` | 🔶 Ad-hoc | Utility — may be called from various places. Not globally wired. |
| `Formatter` | 🔶 Ad-hoc | Same. |
| `Sanitizer` | 🔶 Ad-hoc | Same. |

## src/Exception/

| Classes | Status | Notes |
|---|---|---|
| `CertificateGenerationException`, `DatabaseException`, `EmailSendingException` | ✅ Used | Thrown from service layer. |

## src/Traits/

| Class | Status | Notes |
|---|---|---|
| `Singleton` | ✅ Used | Used by QR Code + legacy classes. |

---

## New classes planned in v8

| Class | Phase | Notes |
|---|---|---|
| `src/Services/PdfGenerator` | 1 | Canonical PDF engine (implements PdfGeneratorInterface) |
| `src/Services/ZipService` | 2 | Canonical ZIP builder (implements ZipServiceInterface) |
| `src/Services/TemplateResolver` | 7 | Resolves field layout/template by cert_type |
| `src/Services/EmailStatusService` | ✅ Done | Already exists |
| `src/Database/CertificateRepository` | 3A | Wraps `wp_certificate_generator` |
| `src/Database/EmailLogRepository` | 3A | Wraps `wp_cert_email_logs` |
| `src/Database/QueueRepository` | 3A | Wraps `wp_cert_email_queue` |
| `src/Database/UserRepository` | 3A | Students/teachers/schools (broad) |
| `src/DTO/CertificateData` | 5 | Typed value object |
| `src/DTO/EmailData` | 5 | Typed value object |
| `src/Listeners/LogEmailListener` | 4 | Side-effect: writes log row |
| `src/Listeners/AnalyticsListener` | 4 | Side-effect: counters |
| `src/Logging/Logger` | 0 | info/warning/error to uploads/logs |
| `src/Admin/Pages/SystemHealthPage` | 9 | Health dashboard |
| `src/Interfaces/PdfGeneratorInterface` | 1 | Contract for PDF generator |
| `src/Interfaces/ZipServiceInterface` | 2 | Contract for ZIP service |
| `src/Migrations/Migration_800` | 8 | Migration manager — first v8 migration |
