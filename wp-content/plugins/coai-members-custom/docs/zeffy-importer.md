# COAI Zeffy Importer – Operational Guide

## Overview
The **COAI Zeffy Importer** is an admin-only tool used to import Zeffy donation/membership exports
(CSV or XLSX) into the `wp_members` table.

It is designed to be **safe, auditable, and non-destructive**, with a required dry-run phase
before any live import.

Primary goals:
- Prevent duplicate members
- Preserve existing COAI numbers
- Cleanly update renewals
- Safely insert new members with auto-assigned COAI numbers
- Provide clear admin visibility before committing changes

---

## Access & Permissions
- Location: **WP Admin → Zeffy Import**
- Required capability:
  - `coai_staff_can('manage')` OR
  - WordPress `manage_options`

Unauthorized users will see **Access Denied**.

---

## Import Workflow (Required Order)

### 1. Upload File
- Accepted formats:
  - CSV (preferred)
  - XLSX / XLS
- File is stored temporarily under:
  - `wp-content/uploads/coai-imports/`

---

### 2. Dry-Run (MANDATORY)
Dry-run performs **no database writes** to `wp_members`.

Dry-run executes:
1. **Load Staging**
   - Data loaded into `import_members_staging_zeffy`
2. **Normalize**
   - Transforms data into `import_members_ready_zeffy`
3. **Duplicate Detection**
   - Hard stops:
     - Duplicate email inside the import file
     - One import row matching multiple existing members
   - Warnings:
     - Possible duplicates based on name + city + state
4. **Plan Upsert**
   - Calculates:
     - Number of UPDATEs
     - Number of INSERTs
5. **Preview Table**
   - Shows row-by-row outcome (UPDATE vs INSERT)
   - Displays expiration changes and status transitions

⚠️ **Dry-run must be clean (no warnings) before live import.**

---

## Duplicate Detection Logic

### Strong Matches (Auto-UPDATE)
- Import email matches:
  - `wp_members.email` OR
  - `wp_members.username`
- OR matching COAI_number (when import email is blank)

### Hard Stop Conditions
- Same email appears multiple times in import file
- One import row matches multiple existing members

### Warning Conditions
- Name + City + State match
- Email and COAI do not match exactly

Warnings require **manual resolution** before live import.

---

## Live Import Behavior

When Dry-run is disabled:

### Updates
- Existing members are updated with:
  - New expiration dates
  - Status transitions (EXPIRED → ACTIVE)
- Existing COAI numbers are **never overwritten**
- Email overwrite rules:
  - Email is preserved unless explicitly allowed by logic

### Inserts
- Only unmatched rows are inserted
- New members receive:
  - Auto-generated COAI number (insert-only)
  - `is_new_member = 1` (configurable)

### Post-Import Actions
- Import run logged to `import_members_runs`
- Admin summary email sent
- Member notification emails sent:
  - Welcome (INSERT)
  - Renewal confirmation (UPDATE)

---

## COAI Number Assignment Rules
- Assigned **only** to newly inserted members
- Format: `YYYYMM-###`
- Sequence is month-safe and collision-resistant
- Existing COAI numbers are never modified

---

## Tables Used

| Table | Purpose |
|-----|-------|
| `import_members_staging_zeffy` | Raw imported data |
| `import_members_ready_zeffy` | Normalized, validated rows |
| `wp_members` | Primary member table |
| `import_members_runs` | Import audit log |

---

## Best Practices
- Always resolve warnings before live import
- Prefer fixing emails in `wp_members` over editing CSV when possible
- Keep Dry-run enabled until preview looks correct
- Perform imports during low-traffic admin windows

---

## Troubleshooting
- If importer crashes:
  - Disable plugin by renaming file
  - Check `error_log` for parse errors
- If duplicates appear:
  - Verify email canonicalization
  - Re-run Dry-run after fixes

---

## Versioning
- Plugin: `coai-zeffy-importer.php`
- See `changelog.md` for detailed change history
