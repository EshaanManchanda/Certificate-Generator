# Certificate Generator v7 — Production Audit
**Date:** 2026-06-24  
**Version:** 7.0.0  
**Audited by:** Claude Sonnet 4.6 (automated + manual review)  
**Commit range:** `43108e7..HEAD` (8 commits, current HEAD `948a5c0` + cache-flush patch)

---

## Executive Summary

**Status: PRODUCTION READY** with known pre-existing phpcs debt (non-blocking).

All critical paths are secure, no PHP syntax errors, no fatal dependency gaps, no SQL injection vectors in new code. One bug found and fixed during audit (stale filter dropdown transients after custom-table writes).

---

## Audit Scope

| Area | Result |
|---|---|
| PHP syntax (all files) | PASS — zero errors |
| SQL injection | PASS — all dynamic values parameterised via `wpdb->prepare()` |
| CSRF / nonce | PASS — every AJAX handler and form submission verified |
| Capability checks | PASS — `manage_options` on all admin export/send handlers |
| Output escaping | PASS — all new HTML output uses `esc_html` / `esc_attr` / `esc_html_e` |
| Load order | PASS — `field-schema.php` loaded before all write paths |
| Transient invalidation | FIXED — stale-cache bug found and patched |
| Migration safety | PASS — idempotent, guards with `SHOW TABLES` + column-exists check |
| CSV round-trip | PASS — `year` stripped from field-slot registration on re-import |
| Pre-existing phpcs debt | INFO — 1679 violations exist across legacy files, not introduced by this work |

---

## Security Checklist

### SQL Injection
- `cg_build_recipient_filter_sql()` — all values via `%s` / `%d` placeholders; `$alias` never user-controlled.
- `cg_export_filter_where()` — POST arrays sanitised (`sanitize_text_field`, `intval`) before reaching the builder. Dates validated with `preg_match('/^\d{4}-\d{2}-\d{2}$/')`.
- `certificate_generator_get_unique_years()` — table names from `CustomTables::get_table()` (internal constant), not user input.
- `Migration003::up()` — table names from `$wpdb->prefix . 'cg_' . $hardcoded_string`; no user input in SQL.

### CSRF / Nonce
| Handler | Nonce action | Method |
|---|---|---|
| `certificate_generator_ajax_get_filter_options` | `cert_bulk_send` | `check_ajax_referer` |
| `certificate_generator_ajax_preview_recipients` | `cert_bulk_send` | `check_ajax_referer` |
| `certificate_generator_ajax_send_to_filtered` | `cert_bulk_send` | `check_ajax_referer` |
| `bulk_export_students` | `cg_export_students` | `check_admin_referer` |
| `bulk_export_schools` | `cg_export_schools` | `check_admin_referer` |
| `bulk_export_teachers` | `cg_export_teachers` | `check_admin_referer` |
| `bulk_export_certificates` | `cg_export_certificates` | `check_admin_referer` |

### Capability checks
All export handlers gate on `current_user_can('manage_options')` before writing CSV output.

---

## Bug Found and Fixed

### Stale filter dropdown transients after custom-table writes

**Severity:** Medium (UX — Year/School/CertType dropdowns could show stale options for up to 1 hour after import or admin edit)

**Root cause:** `delete_transient('cg_unique_years|schools|cert_types')` was only wired to `save_post` hook. Custom-table writes (StudentsPage, TeachersPage, SchoolsPage CRUD + all three bulk import functions) never fire `save_post`, so transients stayed stale until their 1-hour TTL expired.

**Fix:** Extracted a `cg_flush_filter_caches()` function in `filters-api.php`. Called from:
- `StudentsPage::render_edit()` — after `wpdb->insert/update`
- `TeachersPage::render_edit()` — after `wpdb->insert/update`
- `SchoolsPage::render_edit()` — after `wpdb->insert/update`
- `bulk_import_students()` — after successful import loop
- `bulk_import_teachers()` — after successful import loop
- `bulk_import_schools()` — after successful import loop

All calls are guarded with `function_exists('cg_flush_filter_caches')` for forward-safety.

---

## Feature Summary (commits since baseline)

### `feat(filters)` — Historical certificate filtering (`948a5c0`)
Enables Year-based filtering across all bulk admin flows so the same `certificate_type` across multiple years (2023 Olympiad Winner, 2024 Olympiad Winner…) can be targeted precisely.

**New components:**
- `Migration003_BackfillYear` — backfills `year = YEAR(issue_date)` for all existing rows; idempotent.
- `cg_year_from_issue_date(string)` — pure helper, always derives year from `issue_date`, never stored independently.
- `cg_build_recipient_filter_sql(array $filters, string $alias)` — single WHERE-builder used by both the UNION recipient engine and the single-table export queries. Prevents filter drift between flows.
- `certificate_generator_get_unique_years()` — UNION across 3 tables + CPT fallback; 1-hour transient cache.

**Flows updated:**
| Flow | What changed |
|---|---|
| Bulk Email | Year multi-select + Advanced date range added to filter bar; AJAX sanitise extended |
| Bulk Export (students/teachers/schools) | Filter form on each page; export SQL filtered; `year` column in CSV |
| Bulk Import | `year` auto-filled from `issue_date` on every insert; `year` in `known_optional` (not a PDF slot) |
| Admin CRUD (Students/Teachers/Schools) | `year` written on every create/update |

**Design invariant:** `year` is always derived from `issue_date`. It is never manually entered and is always recomputed on every write.

---

## Known Gaps (non-blocking)

### Test coverage
- **JS:** `CertFilterManager`, `bindEvents`, `loadFilterOptions`, `clearFilters` — no Jest/QUnit tests.
- **PHP:** `cg_build_recipient_filter_sql`, `certificate_generator_get_unique_years`, `cg_flush_filter_caches`, all 3 AJAX handlers — no PHPUnit tests.
- Graph risk score: **0.55 / 1.0** (moderate). Acceptable for internal admin tooling; add tests before exposing via REST API.

### phpcs debt
1,679 pre-existing violations across legacy files (`bulk-import.php`, admin pages, etc.) — primarily Yoda conditions, missing docblocks, `wp_unslash` before sanitize, short ternary bans. None are security issues. Scope: tracked separately; not introduced by this feature.

### bulk-download public shortcode
`includes/Services/bulk-download.php` uses WP_Query / CPT postmeta — architecturally incompatible with the SQL-table year filter. Year filtering for the public school certificate download portal is **out of scope** until that file is migrated to the SQL layer.

---

## Database State

### Tables
| Table | Year column | Indexed | Backfilled by |
|---|---|---|---|
| `wp_cg_students` | `year YEAR NULL` | `idx_year` | Migration003 |
| `wp_cg_teachers` | `year YEAR NULL` | `idx_year` | Migration003 |
| `wp_cg_schools` | `year YEAR NULL` | `idx_year` | Migration003 |

### Migration version
`cg_db_version` option must equal `'003'` post-activation.

### Activation checklist
1. Deactivate → Reactivate plugin (or visit Migration Page → Verify/Create Tables).
2. Confirm: `SELECT year, issue_date FROM wp_cg_students LIMIT 10` — `year` matches `YEAR(issue_date)`.
3. Import a CSV row with `issue_date = 2024-04-10` → row should have `year = 2024`.
4. Visit Bulk Email → Year dropdown should show all years present in data.
5. Export students with Year filter set → CSV contains only matching rows.

---

## File Change Manifest

| File | Type | Change |
|---|---|---|
| `src/Database/Migrations/Migration003_BackfillYear.php` | New | Backfill year column |
| `src/Database/Migrations/MigrationRunner.php` | Edit | Register 003, bump target |
| `includes/Core/field-schema.php` | Edit | `cg_year_from_issue_date()` helper |
| `includes/Admin/filters-api.php` | Edit | `get_unique_years`, `cg_build_recipient_filter_sql`, `cg_flush_filter_caches`, engine extended |
| `includes/Admin/bulk-email.php` | Edit | Year/date UI + AJAX sanitise |
| `includes/Services/bulk-export.php` | Edit | Filter form, filtered SQL, year CSV column |
| `includes/Services/bulk-import.php` | Edit | Auto-fill year, known_optional, cache flush |
| `src/Admin/Pages/StudentsPage.php` | Edit | Year on write, cache flush |
| `src/Admin/Pages/TeachersPage.php` | Edit | Year on write, cache flush |
| `src/Admin/Pages/SchoolsPage.php` | Edit | Year on write, cache flush |
| `assets/js/admin-filters.js` | Edit | Year AJAX load, event bindings, init/reset |
