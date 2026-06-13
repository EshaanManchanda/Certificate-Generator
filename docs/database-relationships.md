# Certificate Generator - Database Relationship & Improvement Guide

## Overview

This document details the custom database tables in the Certificate Generator WordPress plugin (v7) and explains how entities are linked together.

---

## Database Tables Overview

### Table Prefix
All custom tables use the prefix: `wp_cg_`

| Table Name | Purpose | Key Fields |
|------------|---------|------------|
| `wp_cg_students` | Student records | id, wp_post_id, student_name, email, serial_number, certificate_type |
| `wp_cg_teachers` | Teacher records | id, wp_post_id, teacher_name, email, serial_number, certificate_type |
| `wp_cg_schools` | School records | id, wp_post_id, school_name, serial_number, certificate_type |
| `wp_cg_certificate_templates` | Certificate template definitions | id, certificate_type, template_url, field_config, status |
| `wp_cg_certificates` | Generated certificates | id, serial_number, student_id, teacher_id, template_id, certificate_type |
| `wp_cg_email_logs` | Email delivery logs | id, certificate_id, recipient_email, status |
| `wp_cg_email_queue` | Pending email deliveries | id, certificate_id, status, scheduled_at |
| `wp_cg_student_certificates` | Junction: students ↔ certificates | student_id, certificate_id |
| `wp_cg_teacher_certificates` | Junction: teachers ↔ certificates | teacher_id, certificate_id |
| `wp_cg_settings` | Plugin settings | setting_key, setting_value |
| `wp_cg_migrations` | Migration tracking | migration_name, batch, executed_at |

---

## Entity Relationships

### 1. Students, Teachers, Schools (Entity Tables)

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress CPT Layer                      │
│  (students, teachers, schools - Custom Post Types)          │
└─────────────────────────────────────────────────────────────┘
                            │
                    wp_post_id (FK)
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   SQL Table Layer                          │
│  wp_cg_students / wp_cg_teachers / wp_cg_schools            │
└─────────────────────────────────────────────────────────────┘
```

**Linkage:**
- Each record in SQL table has optional `wp_post_id` linking to WordPress CPT
- After certificate generation, `serial_number` and `certificate_type` are populated
- `school_id` in students links to `schools.id`

### 2. Template Selection Flow

```
Student/Teacher/School
        │
        ▼
certificate_type + issue_date
        │
        ▼
┌─────────────────────────────┐
│ wp_cg_certificate_templates│
│                             │
│ Query:                     │
│ SELECT * WHERE            │
│   certificate_type = ?     │
│   AND status = 'published'│
│   AND event_date = ?       │
└─────────────────────────────┘
        │
        ▼
   template_id
        │
        ▼
┌─────────────────────┐
│ Field Config        │
│ (extra_fields JSON) │
│                     │
│ 1_position_x: 107   │
│ 1_position_y: 153   │
│ 1_visible: 1       │
│ 2_position_x: 109   │
│ template_field_count│
└─────────────────────┘
```

### 3. Certificate Generation Flow

```
┌──────────────┐     ┌─────────────────────┐     ┌──────────────────┐
│   Student   │────▶│  Serial Generator    │────▶│ Certificate PDF  │
│   Data      │     │  (serial-generator)  │     │  Generation      │
└──────────────┘     └─────────────────────┘     └──────────────────┘
       │                     │                           │
       │                     ▼                           │
       │              ┌──────────────┐                   │
       │              │ wp_cg_students│                  │
       │              │ (UPDATE)     │                  │
       │              │ serial_number│                  │
       │              │ certificate_type                  │
       │              └──────────────┘                   │
       │                                                ▼
       │                                    ┌──────────────────────┐
       │                                    │  wp_cg_certificates  │
       └───────────────────────────────────▶│  (INSERT)            │
                                             │  - template_id       │
                                             │  - student_id        │
                                             │  - serial_number     │
                                             │  - certificate_type │
                                             └──────────────────────┘
```

### 4. Email Flow

```
┌─────────────────────┐
│ wp_cg_certificates │
│   (generated)      │
└─────────┬──────────┘
          │
          ▼
┌─────────────────────┐     ┌──────────────────┐
│  wp_cg_email_queue  │────▶│  Email Sender    │
│  (pending)          │     │  (bulk-email)    │
└─────────────────────┘     └────────┬─────────┘
                                      │
                                      ▼
                               ┌────────────────┐
                               │ wp_cg_email_logs│
                               │ status: sent   │
                               │ sent_at: ...   │
                               └────────────────┘
```

---

## Relationship Diagram (ERD)

```
┌──────────────────┐       ┌───────────────────────┐
│  students       │       │  certificate_templates │
└────────┬────────┘       └───────────┬───────────┘
         │                             │
         │ school_id                   │ template_id
         │                             │
         ▼                             ▼
┌──────────────────┐       ┌──────────────────────┐
│    schools       │       │     certificates     │
│                  │       │                      │
└──────────────────┘       └──────────┬───────────┘
                                       │
                    ┌──────────────────┼──────────────────┐
                    │                  │                  │
                    ▼                  ▼                  ▼
          ┌─────────────────┐ ┌─────────────┐ ┌──────────────────┐
          │ student_cert    │ │ teacher_    │ │ teacher_cert     │
          │ _certificates   │ │ certificates│ │                  │
          └─────────────────┘ └─────────────┘ └──────────────────┘
                    │                  │                  │
                    ▼                  ▼                  ▼
          ┌─────────────────┐ ┌─────────────┐ ┌──────────────────┐
          │ students.id    │ │ teachers.id │ │ teachers.id      │
          └─────────────────┘ └─────────────┘ └──────────────────┘
```

---

## Key Field Mappings

| Field | Table | Description |
|-------|-------|-------------|
| `certificate_type` | All entity tables, templates, certificates | Links entities to templates |
| `serial_number` | students, teachers, schools, certificates | Generated unique identifier |
| `wp_post_id` | All entity tables | Links SQL record to WordPress CPT |
| `template_id` | certificates | Links to certificate_templates |
| `student_id` | certificates, student_certificates | Links to students |
| `teacher_id` | certificates, teacher_certificates | Links to teachers |
| `school_id` | students, certificates | Links to schools |
| `event_date` | certificate_templates | For date-based template matching |

---

## Serial Number Flow

```
1. User generates certificate via shortcode/admin
        │
        ▼
2. generate_certificate_pdf() called
        │
        ▼
3. CG_Serial_Number_Generator::generate(certificate_type, student_data)
        │
        ├── Generates serial: CERT-2026-000001
        │
        └── Updates student table:
            UPDATE wp_cg_students 
            SET serial_number = 'CERT-2026-000001',
                certificate_type = 'The Attenborough Award',
                updated_at = NOW()
            WHERE email = 'student@example.com'
        │
        ▼
4. Certificate record inserted:
            INSERT INTO wp_cg_certificates 
            (serial_number, student_id, template_id, certificate_type, ...)
```

---

## Improvement Guide

### 1. Missing Relationships

**Issue:** Currently no direct `school_id` linking in teachers table schema

**Recommendation:** Add `school_id` to teachers table for school-based queries

### 2. Index Optimization

**Current indexes are adequate, but consider adding:**
- Composite index on `(certificate_type, serial_number)` for lookup
- Index on `email` in certificates table for email verification

### 3. Data Consistency

**Action Items:**
- Implement triggers to auto-update entity tables when certificates are generated
- Add foreign key constraints between related tables
- Create sync mechanism between WP CPT and SQL tables

### 4. Future Enhancements

- Add `school_id` to certificate_templates for organization-specific templates
- Implement soft delete (add `deleted_at` column)
- Add `certificate_id` to email_queue with foreign key constraint
- Create views for common queries (student certificates, school certificates)

---

## Common Queries

### Find student's certificate by email
```sql
SELECT c.*, ct.template_name, ct.template_url
FROM wp_cg_certificates c
JOIN wp_cg_certificate_templates ct ON c.template_id = ct.id
JOIN wp_cg_students s ON c.student_id = s.id
WHERE s.email = 'student@example.com';
```

### Get all certificates for a school
```sql
SELECT c.*, s.school_name
FROM wp_cg_certificates c
JOIN wp_cg_students s ON c.student_id = s.id
WHERE s.school_id = 11;
```

### Find certificate by serial number
```sql
SELECT c.*, s.student_name, ct.certificate_type
FROM wp_cg_certificates c
LEFT JOIN wp_cg_students s ON c.student_id = s.id
LEFT JOIN wp_cg_teachers t ON c.teacher_id = t.id
LEFT JOIN wp_cg_certificate_templates ct ON c.template_id = ct.id
WHERE c.serial_number = 'CERT-2026-000001';
```

---

## File Locations

| File | Purpose |
|------|---------|
| `src/Database/CustomTables.php` | Table definitions and creation |
| `includes/Services/serial-generator.php` | Serial number generation |
| `includes/Services/certificate-search.php` | Certificate generation, template selection |
| `includes/Services/certificate-search.php` | Student search shortcode |
| `includes/Services/qr-generator.php` | QR code generation |
| `src/Admin/Pages/StudentsPage.php` | Student admin management |

---

## Version History

- v1.0.0 (2026-04) - Initial schema with 11 tables
- Hybrid approach: SQL tables alongside WordPress CPTs