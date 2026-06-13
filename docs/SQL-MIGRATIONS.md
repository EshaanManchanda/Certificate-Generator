# SQL Migrations Plan

## Current State
- Plugin supports both CPT (posts) and SQL tables
- Existing migration infrastructure: `CptToSqlMigration`, `MigrationRunner`
- Data flows: reads from SQL first, falls back to CPT

## Migration Phases

### Phase 1: v7.0.0 - Initial SQL Tables
`CREATE` certificate_templates, students, teachers, schools, certificates tables.

### Phase 2: v7.1.0 - Extra Fields Schema
`ALTER` certificate_templates ADD extra_fields JSON, template_field_count INT.

```sql
-- Run via MigrationRunner
ALTER TABLE {$wpdb->prefix}cg_certificate_templates 
ADD COLUMN extra_fields JSON NULL AFTER template_orientation,
ADD COLUMN template_field_count INT DEFAULT 3 AFTER extra_fields;
```

### Phase 3: v7.2.0 - Field Position Normalization
`ALTER` Add numeric position columns for field slots.

```sql
-- field_1_position_x, field_1_position_y, etc.
-- This avoids extra_fields JSON parsing on every generation
ALTER TABLE {$wpdb->prefix}cg_certificate_templates 
ADD COLUMN position_data JSON NULL AFTER template_field_count;
```

### Phase 4: v7.3.0 - Index Optimizations
```sql
CREATE INDEX idx_cert_type ON {$wpdb->prefix}cg_certificates(certificate_type);
CREATE INDEX idx_serial ON {$wpdb->prefix}cg_certificates(serial_number);
CREATE INDEX idx_student_email ON {$wpdb->prefix}cg_students(email);
```

### Phase 5: v7.4.0 - Soft Deletes / Archive
```sql
ALTER TABLE {$wpdb->prefix}cg_certificates 
ADD COLUMN status VARCHAR(20) DEFAULT 'active',
ADD COLUMN archived_at DATETIME NULL;
```

## Migration Runner Pattern

```php
// src/Database/Migrations/MigrationRunner.php
private array $migrations = [
    '7.1.0' => ExtraFieldsMigration::class,
    '7.2.0' => FieldPositionMigration::class,
    '7.3.0' => IndexOptimizations::class,
];
```

Each migration class implements:
- `up()` - apply changes
- `down()` - rollback changes  
- `version()` - return version string

## Running Migrations

```bash
# WP-CLI
wp certificate-generator migrate
wp certificate-generator migrate --dry-run
wp certificate-generator migrate --rollback
```

## Rollback Strategy
- Store pre-migration snapshots in option/table
- Soft deletes for data (archive table)
- CPT data remains as fallback even after SQL migration