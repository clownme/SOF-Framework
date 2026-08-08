# Zeffy Import Rollback Checklist

This checklist is used if a live Zeffy import produces unexpected results.

---

## BEFORE EVERY LIVE IMPORT (Preventive)
- [ ] Dry-run completed with **no warnings**
- [ ] Preview table reviewed (UPDATE vs INSERT correct)
- [ ] CSV file archived (original, unchanged)
- [ ] Import timestamp noted
- [ ] Admin performing import recorded

---

## IF AN ISSUE IS DETECTED AFTER LIVE IMPORT

### Step 1: STOP
- [ ] Do NOT run another import
- [ ] Do NOT manually edit affected records yet

---

### Step 2: Identify the Import Run
- [ ] Check table: `import_members_runs`
- [ ] Record:
  - `run_at`
  - `run_by`
  - `file_name`
  - `rows_updated`
  - `rows_inserted`

---

### Step 3: Identify Affected Members
- [ ] Query members by `updated_at >= run_at`
- [ ] Separate:
  - Updated existing members
  - Newly inserted members

---

### Step 4: Rollback Strategy

#### A) Undo INSERTS (New Members)
- [ ] Identify inserted members by:
  - `updated_at = batch timestamp`
  - Missing prior history
- [ ] Soft-delete or set status to `DELETED` / `INACTIVE`
- [ ] Do NOT hard-delete unless approved

#### B) Undo UPDATES (Existing Members)
- [ ] Compare against:
  - Pre-import backups
  - Previous expiration dates
  - Previous status
- [ ] Restore fields manually or via SQL
- [ ] Do NOT change COAI numbers

---

### Step 5: Notifications
- [ ] Determine if member emails were sent
- [ ] If needed:
  - Send correction email
  - Or suppress follow-up messaging

---

### Step 6: Documentation
- [ ] Record issue in `changelog.md`
- [ ] Note:
  - Cause
  - Resolution
  - Preventive change (if any)

---

## EMERGENCY ACTIONS
- Disable importer plugin if behavior is unclear
- Contact COAI technical admin before further changes

---

## Rollback Rule
> **Never attempt to “fix forward” with another import until the prior run is fully understood.**
