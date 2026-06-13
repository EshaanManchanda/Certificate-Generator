# Call Graph — Certificate-Generator-v7

> Generated: 2026-06-13 as part of v8 Phase -1 architecture inventory.
> Update this file when a caller is added, removed, or rerouted through a new service.

---

## `generate_certificate_pdf( $post_id, $fields, $student_data = null )`

**Defined:** `includes/Services/certificate-search.php:1514`

| Caller | Line | Context |
|--------|------|---------|
| `includes/API/endpoints.php` | 363 | REST POST /generate-certificate (by post_id) |
| `includes/API/endpoints.php` | 602 | REST POST /generate-certificate (bulk loop) |
| `includes/Email/functions.php` | 98 | `cg_send_certificate_email_by_id()` — email attach path |
| `includes/Services/background-processor.php` | 199 | Async bulk ZIP builder |
| `includes/Services/bulk-download.php` | 84 | Single-student school bulk download |
| `includes/Services/bulk-download.php` | 499 | School ZIP regeneration loop |
| `includes/Services/bulk-download.php` | 565 | Bulk re-generate + ZIP loop |
| `includes/Services/certificate-search.php` | 2088 | `cg_generate_certificate_path()` wrapper |
| `includes/Services/certificate-search.php` | 2195 | Teacher front-end search generate |
| `includes/Services/certificate-search.php` | 2636 | School front-end search generate |
| `includes/Services/certificate-search.php` | 3158 | Student front-end search generate |

**Total callers: 11**

---

## `generate_certificate_pdf_with_data( $post_data )`

**Defined:** `includes/Services/certificate-search.php:831`

| Caller | Line | Context |
|--------|------|---------|
| `includes/Core/post-types.php` | 2159 | CPT save hook (legacy path, CPT deregistered) |
| `includes/Public/student-template.php` | 85 | Front-end public template PDF link |
| `templates/single-students.php` | 23 | Single-student WP template |
| `includes/Email/functions.php` | 141 | `cg_generate_certificate_for_email_with_data()` |
| `includes/Admin/settings.php` | 2763 | Admin PDF preview (Settings page) |

**Total callers: 5**

---

## `generate_certificate_pdf_email( $post_id, $fields, $email_options = null )`

**Defined:** `includes/Services/certificate-search.php:2066`  
*Note: thin wrapper around `generate_certificate_pdf()` — collapses into PdfGenerator in Phase 1.*

| Caller | Line | Context |
|--------|------|---------|
| `includes/Admin/columns.php` | 511 | Admin columns single-send (dead CPT path) |

**Total callers: 1**

---

## `certificate_generator_send_email( $cg_id, $log_email = true )`

**Defined:** `includes/Email/functions.php:299`

| Caller | Line | Context |
|--------|------|---------|
| `src/Admin/Pages/StudentsPage.php` | 666 | SQL admin page single-send AJAX |
| `src/Services/EmailService.php` | 91 | Static facade shim (already wired) |
| `includes/Admin/columns.php` | 532 | Columns single-send (CPT-era, partially live) |
| `includes/Email/functions.php` | 925 | Single from batch trigger |
| `includes/Email/functions.php` | 991 | `cg_resend_certificate_email()` wrapper |
| `includes/Services/bulk-email-sender.php` | 114 | Batch email processor loop |

**Total callers: 6**

---

## `cg_build_certificate_zip()` — canonical ZIP

**Defined:** `includes/Email/functions.php:228`  
*SQL-row sourced, cg_id in filename, CREATE|OVERWRITE — the reference implementation.*

No external callers yet — all ZIP consumers build their own `ZipArchive` inline (see below).

---

## Inline `ZipArchive` instances (to be replaced by ZipService in Phase 2)

| File | Line | Context |
|------|------|---------|
| `includes/Email/functions.php` | 232 | `cg_build_certificate_zip()` — canonical |
| `includes/Services/background-processor.php` | 267 | Async bulk ZIP |
| `includes/Services/bulk-download.php` | 600 | School bulk download |
| `includes/API/endpoints.php` | 276 | REST ZIP-all endpoint |
| `includes/API/endpoints.php` | 396 | REST ZIP-for-email endpoint |
| `includes/Services/certificate-search.php` | 2713 | Front-end teacher ZIP |
| `includes/Services/certificate-search.php` | 3207 | Front-end student ZIP |

**Total inline ZIP builders: 7** (6 to migrate to `ZipService`, 1 becomes ZipService itself)

---

## v8 shim plan

After Phase 1–2 all callers stay put; the functions become one-liners:

```php
// includes/legacy-shims.php (frozen, @deprecated v8)
function generate_certificate_pdf( $id, $fields = [], $data = null ) {
    return \CertificateGenerator\Services\PdfGenerator::create(
        \CertificateGenerator\Services\PdfGenerator::resolve( $id, $fields, $data ) );
}

function generate_certificate_pdf_with_data( $post_data ) {
    return \CertificateGenerator\Services\PdfGenerator::createFromArray( $post_data );
}

function generate_certificate_pdf_email( $id, $fields, $opts = null ) {
    return generate_certificate_pdf( $id, $fields ); // now same path
}

function cg_build_certificate_zip( $cg_id ) {
    return \CertificateGenerator\Services\ZipService::forEmail( $cg_id );
}
```
