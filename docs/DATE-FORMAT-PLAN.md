# Date Format Standardization Plan

**Version:** 1.0.0  
**Date:** 2026-04-07  
**Status:** Implementation Plan

---

## 1. The Problem

WordPress and the browser have conflicting date format requirements:
- **HTML5 `<input type="date">`** requires `YYYY-MM-DD` format for its value attribute
- **User preference** is `d-m-Y` (e.g., `23-03-2026`)
- **Database sorting** works best with `Y-m-d` (ISO 8601)
- **CSV imports** may contain `d-m-Y`, `m/d/Y`, or `Y-m-d`

Currently, dates are stored inconsistently, causing template matching failures and confusing admin display.

---

## 2. The Solution: Store Y-m-d, Display d-m-Y

### Core Principle
- **Storage:** Always `Y-m-d` (ISO 8601) — enables proper sorting, indexing, and comparison
- **Display:** Always `d-m-Y` — user-facing format in admin forms, emails, certificates
- **Input:** Accept any common format, normalize to `Y-m-d` before storage

### Why Not Store d-m-Y?
- `ORDER BY` on `d-m-Y` strings sorts alphabetically: `01-02-2026` < `02-01-2026` (wrong!)
- Date comparisons fail: `23-03-2026` > `01-12-2025` is false as string comparison
- MySQL `DATE` columns require `Y-m-d` format
- Template matching (`event_date = issue_date`) breaks with mixed formats

---

## 3. Implementation Plan

### Phase 1: Create Date Helper Class

Create `src/Helpers/DateHelper.php`:

```php
class DateHelper {
    const DISPLAY_FORMAT = 'd-m-Y';
    const STORAGE_FORMAT = 'Y-m-d';
    const INPUT_FORMATS = ['Y-m-d', 'd-m-Y', 'm/d/Y', 'Y/m/d', 'd/m/Y'];

    /**
     * Parse any date string to Y-m-d for storage.
     * Returns null if unparseable.
     */
    public static function to_storage(?string $date): ?string {
        if (empty($date)) return null;
        foreach (self::INPUT_FORMATS as $format) {
            $dt = DateTime::createFromFormat($format, $date);
            if ($dt && $dt->format($format) === $date) {
                return $dt->format(self::STORAGE_FORMAT);
            }
        }
        // Fallback: try strtotime
        $ts = strtotime($date);
        return $ts ? date(self::STORAGE_FORMAT, $ts) : null;
    }

    /**
     * Convert stored Y-m-d to display format d-m-Y.
     */
    public static function to_display(?string $date): string {
        if (empty($date)) return '';
        $dt = DateTime::createFromFormat(self::STORAGE_FORMAT, $date);
        return $dt ? $dt->format(self::DISPLAY_FORMAT) : $date;
    }

    /**
     * Convert stored Y-m-d to HTML5 date input value (Y-m-d).
     * HTML5 date inputs require this exact format.
     */
    public static function to_html5(?string $date): string {
        if (empty($date)) return '';
        $dt = DateTime::createFromFormat(self::STORAGE_FORMAT, $date);
        return $dt ? $dt->format(self::STORAGE_FORMAT) : '';
    }
}
```

### Phase 2: Update Admin Forms

#### Event Date Field (certificate template edit)
**File:** `includes/Core/post-types.php`

**Display (line ~991):**
```php
// Before:
value="<?php echo esc_attr(get_post_meta($post->ID, 'event_date', true)); ?>"

// After:
value="<?php echo esc_attr(DateHelper::to_display(get_post_meta($post->ID, 'event_date', true))); ?>"
```

**Save (line ~1857):**
```php
// Before:
update_post_meta($post_id, 'event_date', $raw);

// After:
$stored = DateHelper::to_storage($raw);
if ($stored !== null) {
    update_post_meta($post_id, 'event_date', $stored);
}
```

#### Issue Date Field (student/teacher/school edit)
Same pattern for all date fields across the plugin.

### Phase 3: Update Certificate Generation

#### Template Matching
**File:** `includes/Services/certificate-search.php`

The `cg_select_certificate_template()` function compares `issue_date` with `event_date`. Both must be in `Y-m-d` format for comparison:

```php
// Normalize issue_date from CSV (d-m-Y) to storage format (Y-m-d)
$issue_date_iso = DateHelper::to_storage($issue_date);

// Template event_date is already stored as Y-m-d
$template_date = $template->event_date; // Y-m-d

// Comparison works correctly
if ($issue_date_iso === $template_date) { ... }
```

#### PDF Display
When rendering dates on certificates, convert back to display format:

```php
// In generate_certificate_pdf_with_data():
$display_date = DateHelper::to_display($post_data['issue_date']);
$pdf->Cell(0, 10, $display_date); // Shows "23-03-2026"
```

### Phase 4: Update Bulk Import/Export

#### Import (CSV → Database)
**File:** `includes/Services/bulk-import.php`

```php
// CSV has: issue_date = "23-03-2026"
$issue_date = $student_data['issue_date']; // "23-03-2026"

// Convert to storage format before saving
$stored_date = DateHelper::to_storage($issue_date); // "2026-03-23"

// Save to SQL table
$insert_data['issue_date'] = $stored_date;

// Save to CPT meta
update_post_meta($post_id, 'issue_date', $stored_date);
```

#### Export (Database → CSV)
**File:** `includes/Services/bulk-export.php`

```php
// Database has: issue_date = "2026-03-23"
$stored_date = $row['issue_date']; // "2026-03-23"

// Convert to display format for CSV
$csv_date = DateHelper::to_display($stored_date); // "23-03-2026"

// Write to CSV
fputcsv($output, [..., $csv_date, ...]);
```

### Phase 5: Update Admin List Columns

**File:** `includes/Admin/columns.php`

```php
// Display issue_date column
$date = get_post_meta($post_id, 'issue_date', true);
echo esc_html(DateHelper::to_display($date)); // Shows "23-03-2026"
```

### Phase 6: Update Email Templates

**File:** `includes/Email/functions.php`

```php
// Replace {issue_date} placeholder
$placeholders['{issue_date}'] = DateHelper::to_display($issue_date);
```

### Phase 7: Data Migration (Existing Data)

Existing data may have mixed formats. Create a one-time migration:

```php
function cg_migrate_date_formats(): void {
    global $wpdb;

    // Fix event_date in postmeta
    $dates = $wpdb->get_results(
        "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = 'event_date' AND meta_value != ''"
    );
    foreach ($dates as $row) {
        $stored = DateHelper::to_storage($row->meta_value);
        if ($stored && $stored !== $row->meta_value) {
            update_post_meta($row->post_id, 'event_date', $stored);
        }
    }

    // Fix issue_date in postmeta
    $dates = $wpdb->get_results(
        "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = 'issue_date' AND meta_value != ''"
    );
    foreach ($dates as $row) {
        $stored = DateHelper::to_storage($row->meta_value);
        if ($stored && $stored !== $row->meta_value) {
            update_post_meta($row->post_id, 'issue_date', $stored);
        }
    }

    // Fix dates in SQL tables
    $tables = ['students', 'teachers', 'schools'];
    foreach ($tables as $table) {
        $full_table = $wpdb->prefix . 'cg_' . $table;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table)) === $full_table) {
            // Fix enrollment_date, graduation_date, hire_date, etc.
            // Similar pattern: read, normalize, update
        }
    }

    update_option('cg_date_migration_completed', current_time('mysql'));
}
```

---

## 4. File Changes Summary

| File | Changes |
|------|---------|
| `src/Helpers/DateHelper.php` | **NEW** — Central date conversion utility |
| `includes/Core/post-types.php` | Event date display/save normalization |
| `includes/Services/certificate-search.php` | Template matching date normalization |
| `includes/Services/bulk-import.php` | Import date conversion (CSV → Y-m-d) |
| `includes/Services/bulk-export.php` | Export date conversion (Y-m-d → d-m-Y) |
| `includes/Admin/columns.php` | List column date display |
| `includes/Email/functions.php` | Email placeholder date formatting |
| `includes/Admin/settings.php` | Settings date fields |

---

## 5. Testing Checklist

- [ ] Event date displays as `d-m-Y` in template edit form
- [ ] Event date saves correctly when entered as `d-m-Y`
- [ ] HTML5 date picker works (value is `Y-m-d` internally)
- [ ] CSV import with `d-m-Y` dates stores as `Y-m-d`
- [ ] CSV export shows `d-m-Y` dates
- [ ] Template matching works with imported dates
- [ ] Certificate PDF shows `d-m-Y` dates
- [ ] Email templates show `d-m-Y` dates
- [ ] Admin list columns show `d-m-Y` dates
- [ ] Existing data migrated correctly

---

## 6. Edge Cases

### HTML5 Date Picker Limitation
The browser's `<input type="date">` **only accepts `YYYY-MM-DD`** in its value attribute. We cannot change this. The solution:
- Store: `Y-m-d` (always)
- Display in text fields: `d-m-Y`
- Display in date pickers: `Y-m-d` (browser requirement)
- On save: Convert any format to `Y-m-d`

### Mixed Format CSVs
If a CSV has mixed date formats (`23-03-2026`, `03/23/2026`, `2026-03-23`), the `DateHelper::to_storage()` method tries each format in order until one matches.

### Invalid Dates
If a date cannot be parsed (e.g., `32-13-2026`), it's stored as-is and flagged for manual review.

---

## 7. Implementation Order

1. Create `DateHelper.php` class
2. Update admin form display (event_date, issue_date)
3. Update admin form save handlers
4. Update bulk import date conversion
5. Update bulk export date conversion
6. Update certificate generation date display
7. Update email template date placeholders
8. Run data migration for existing records
9. Test all date-related functionality
