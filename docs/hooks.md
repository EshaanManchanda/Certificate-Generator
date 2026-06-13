# Hooks Reference — Certificate-Generator-v7

> Generated: 2026-06-13 as part of v8 Phase -1 architecture inventory.

---

## Actions fired by this plugin

| Hook | Fired from | Args | Consumers |
|---|---|---|---|
| `cg_certificate_generated` | `certificate-search.php:2046` | `$post_id`, `$pdf_path` | `includes/Admin/usage-tracker.php:25` |
| `cg_email_sent` | *(planned — Phase 4)* | `EmailData $data` | `LogEmailListener`, `AnalyticsListener` |
| `cg_cleanup_qr_codes` | Cron | — | `includes/Cron/jobs.php:8` |
| `cg_check_expiring_certificates` | Cron | — | `includes/Cron/jobs.php:9` |
| `cg_cleanup_old_certificates` | Cron | — | `includes/Cron/jobs.php:10` |
| `cg_publish_scheduled_templates` | Cron | — | `includes/Cron/jobs.php:11` |

---

## Filters fired by this plugin

| Hook | Fired from | Return | Consumers |
|---|---|---|---|
| `cg_pre_generate_certificate` | `certificate-search.php:1565` | `bool` (false blocks PDF) | `includes/Admin/usage-tracker.php:74` (usage gate) |

> ⚠️ **Never remove this filter check** — it is the usage-limit and license gate.
> See project CLAUDE.md Don'ts.

---

## Hooks consumed from WordPress

| Hook | Where | Purpose |
|---|---|---|
| `plugins_loaded` | `certificate-generator.php:214` | Boot `Plugin` class |
| `plugins_loaded` | `certificate-generator.php:697` | `certificate_generator_update_check()` |
| `plugins_loaded` (priority 20) | `includes/Database/migration-queue-columns.php` | Add queue columns (Phase-1 migration) |
| `admin_menu` | `certificate-generator.php:264` | Register all admin pages |
| `admin_enqueue_scripts` | `certificate-generator.php:94` | `custom_admin_assets()` |
| `admin_init` | `certificate-generator.php:486` | Various admin handlers |
| `admin_init` | `certificate-generator.php:827` | Memory usage check |
| `admin_notices` | `certificate-generator.php:745` | `certificate_generator_admin_notices()` |
| `init` | `src/Core/Plugin.php:98` | `Plugin::init()` (textdomain) |
| `rest_api_init` | `src/Core/Plugin.php:99` | `Plugin::register_api_routes()` |
| `save_post_students` | `certificate-generator.php:322` | `cg_sync_to_dynamic_tags()` |
| `save_post_teachers` | `certificate-generator.php:329` | `cg_sync_to_dynamic_tags()` |
| `save_post_schools` | `certificate-generator.php:336` | `cg_sync_to_dynamic_tags()` |

---

## `cg_sync_to_dynamic_tags( $post_id )` — critical constraint

**Defined:** `includes/Core/post-types.php:25`

Called on CPT save hooks (for legacy WP post rows) AND directly from SQL admin page saves:
- `src/Admin/Pages/StudentsPage.php:499` (after SQL save, if `$wp_post_id > 0`)
- `src/Admin/Pages/TeachersPage.php:314` (same)
- `src/Admin/Pages/SchoolsPage.php:313` (same)

> ⚠️ **Do not change how `cg_sync_to_dynamic_tags()` hooks or is called** — other plugins
> (WP Dynamic Tags) depend on the timing. See project CLAUDE.md Don'ts.

---

## AJAX actions

| Action | Handler | Auth |
|---|---|---|
| `cg_student_send_email` | `StudentsPage` | nonce + manage_options |
| `certificate_generator_send_single_email_ajax` | `columns.php` (CPT-era, columns.php:369) | nonce + edit_posts |
| `cg_set_keep_data` | `certificate-generator.php:667` | nonce + manage_options |
| `cert_trigger_queue_processing` | *(removed Phase-1 — dead nonce path)* | — |

---

## v8 additions

Phase 4 adds:
```php
do_action( 'cg_email_sent', $email_data ); // fired by EmailService after every send
```

Registered listeners (side-effects only — no state mutation in listeners):
```php
add_action( 'cg_email_sent', [ LogEmailListener::class, 'handle' ] );
add_action( 'cg_email_sent', [ AnalyticsListener::class, 'handle' ] );
```
