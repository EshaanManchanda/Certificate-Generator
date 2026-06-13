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

## `certificate_generator_create_zip_for_email( $certificates_data, $recipient_email )` — canonical ZIP

**Defined:** `includes/Email/functions.php` (formerly at :202, renamed to `_cg_create_zip_impl` in Phase 2a)  
*Accepts `[['path','filename']]` array + recipient string. CREATE|OVERWRITE. Returns `['zip_path','zip_url','certificate_count','failed_count','failed_files']`.*

**Phase 2b status:** All 6 inline builders now delegate here. ✅

| Caller | Line (approx) | Context |
|--------|--------------|---------|
| `includes/Services/background-processor.php` | ~267 | Async bulk ZIP (Phase 2b) |
| `includes/Services/bulk-download.php` | ~592 | School bulk download (Phase 2b) |
| `includes/API/endpoints.php` | ~265 | REST ZIP-all endpoint (Phase 2b) |
| `includes/API/endpoints.php` | ~385 | REST ZIP-for-email endpoint (Phase 2b) |
| `includes/Services/certificate-search.php` | ~2727 | Front-end school ZIP (Phase 2b) |
| `includes/Services/certificate-search.php` | ~3222 | Front-end student ZIP (Phase 2b) |
| `includes/Email/functions.php` | ~480 | `certificate_generator_send_email()` — original caller |

---

## Inline `ZipArchive` instances — Phase 2b status

All 6 inline builders have been replaced by calls to `certificate_generator_create_zip_for_email()`.
The only remaining `ZipArchive` instance is inside `_cg_create_zip_impl` (canonical impl) in `includes/Email/functions.php`.

---

## v8 shim plan

After Phase 1–2 all callers stay put; the functions become one-liners:

```php
// includes/legacy-shims.php (frozen, @deprecated v8)
function generate_certificate_pdf( $id, $fields = [], $data = null ) {
    return \CertificateGenerator\Services\PdfGenerator::make( $id, $fields, $data );
}

function generate_certificate_pdf_with_data( $post_data ) {
    return \CertificateGenerator\Services\PdfGenerator::makeFromArray( $post_data );
}

function generate_certificate_pdf_email( $id, $fields, $opts = null ) {
    return generate_certificate_pdf( $id, $fields ); // now same path
}

function certificate_generator_create_zip_for_email( $certs, $recipient = '' ) {
    return \CertificateGenerator\Services\ZipService::make( $certs, $recipient );
}
```
