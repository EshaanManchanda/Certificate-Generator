# Certificate Generator v7

## Purpose
WP plugin (v6.1.0) for managing, generating, and bulk-sending certificates for students and teachers. Supports QR codes, serial numbers, PDF generation, and email delivery.

## Key Files
- Entry: `certificate-generator.php`
- Admin CSS: `assets/css/admin-style.css`
- Admin JS: `assets/js/admin-script.js`
- Includes: `includes/` directory

## Integration Dependencies
- **Reads from:** gema MERN backend (student/cert data via REST API)
- **Called by:** Uncanny Automator triggers (course complete → generate cert)
- **DB:** Custom tables for students, teachers, schools, certificates
- **Custom Post Types:** students, teachers, schools (via Custom Post Type UI)

## WP Hooks
- Fires: `certificate_generated` (after PDF creation)
- Consumes: `learndash_course_completed` (via Uncanny Automator integration)
- Admin pages: `settings_page_certificate-bulk-send`, `settings_page_certificate-email-logs`, `edit-students`, `edit-teachers`, `edit-schools`

## Sub-Brain Tasks (use Ollama, not Claude tokens)
- Explain certificate generation logic → `qwen2.5-coder:7b`
- Write PHPDoc for admin functions → `qwen2.5-coder:7b`
- Generate boilerplate for new admin page → `qwen2.5-coder:7b`
- Summarize bulk-send flow → `llama3.1:8b`

## Testing
- Admin: WP admin → Certificate Generator menu
- Bulk send: use `settings_page_certificate-bulk-send` page
- Email logs: use `settings_page_certificate-email-logs` page
