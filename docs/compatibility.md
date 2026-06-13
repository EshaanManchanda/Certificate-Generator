# Compatibility & Legacy Shims — Certificate-Generator-v7

> Generated: 2026-06-13 as part of v8 Phase -1 architecture inventory.
>
> **Rule:** Old functions are never hard-cut in v8. They live in `includes/legacy-shims.php`,
> marked `@deprecated v8`. All call sites remain untouched. Deletion in v9.

---

## Function → Service mapping

| Legacy function | Defined in | Routes to (v8) | Phase |
|---|---|---|---|
| `generate_certificate_pdf($id, $fields, $data)` | `certificate-search.php:1514` | `PdfGenerator::create()` | 1 |
| `generate_certificate_pdf_with_data($post_data)` | `certificate-search.php:831` | `PdfGenerator::createFromArray()` | 1 |
| `generate_certificate_pdf_email($id, $fields, $opts)` | `certificate-search.php:2066` | `PdfGenerator::create()` (same path) | 1 |
| `cg_build_certificate_zip($cg_id)` | `Email/functions.php:228` | `ZipService::forEmail()` | 2 |
| `certificate_generator_send_email($cg_id, $log)` | `Email/functions.php:299` | `EmailService::sendById()` | 4 |
| `certificate_generator_queue_email($cg_id, $opts)` | `Email/queue.php` | `EmailService::queueById()` | 4 |

---

## Class → Class mapping

| Legacy class | Routes to (v8) | Phase | Deletion |
|---|---|---|---|
| `CertificateGeneratorService` | `CertificateService` (delegates) | 10 (deprecate) | v9 |
| `CG_Certificate_Search` (procedural) | `PdfGenerator` | 1 | v9 |

---

## Shim file

After Phase 1, all shim functions will be consolidated into:

```
includes/legacy-shims.php
```

Format of each shim:

```php
/**
 * @deprecated v8 Use \CertificateGenerator\Services\PdfGenerator::create() instead.
 *             File is frozen — no new features. Will be deleted in v9.
 */
function generate_certificate_pdf( $post_id, $fields = array(), $student_data = null ) {
    return \CertificateGenerator\Services\PdfGenerator::create(
        \CertificateGenerator\Services\PdfGenerator::resolve( $post_id, $fields, $student_data )
    );
}
```

---

## Feature flags (all default OFF until phase completes golden-master test)

| Flag constant | Default | Controls | Flipped in |
|---|---|---|---|
| `CG_USE_NEW_PDF` | `false` | `generate_certificate_pdf*` shims | Phase 1 |
| `CG_USE_NEW_ZIP` | `false` | ZIP builder paths | Phase 2 |
| `CG_USE_REPOSITORIES` | `false` | DB read/write paths in Pages + EmailStatusService | Phase 3B |
| `CG_USE_DTO` | `false` | CertificateData/EmailData objects | Phase 5 |
| `CG_USE_EVENTS` | `false` | `do_action('cg_email_sent')` + listeners | Phase 4 |

Rollback = `define('CG_USE_NEW_PDF', false)` — no commit revert needed.
Flags live through **all of v8**, removed in v9.

---

## Callers that DO NOT change (17 confirmed)

`generate_certificate_pdf`: endpoints.php:363/602, Email/functions.php:98,
background-processor.php:199, bulk-download.php:84/499/565,
certificate-search.php:2088/2195/2636/3158 (11)

`generate_certificate_pdf_with_data`: post-types.php:2159, student-template.php:85,
single-students.php:23, Email/functions.php:141, settings.php:2763 (5)

`generate_certificate_pdf_email`: columns.php:511 (1)

All stay as-is. The shim absorbs the change.
