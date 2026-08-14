# SOF Engineering Journal

---

## Project

Current Implementation:
COAI Members Custom Plugin

Framework:
SOF (Simplified Organizational Framework)

Purpose:
To document engineering decisions, architectural milestones, recovery points, implementation notes, and future development of the SOF Framework.

---

# SOF Mission

SOF exists to help organizations succeed by providing software that is:

* Simple to understand
* Well designed
* Easy to maintain
* Easy to extend
* Reliable
* Customer focused

Technology should reduce complexity, not create it.

Every design decision should improve the experience of both the user and the developer.

---

# Engineering Workflow

Every engineering task follows this process:

1. Define the Mission
2. Create a Recovery Point
3. Make One Refactor
4. Test
5. Document
6. Update CHANGELOG
7. Commit to Git (future)
8. Begin Next Mission

---

# SOF Architecture

```
Presentation
      │
      ▼
Services
      │
      ▼
Repositories
      │
      ▼
Libraries
      │
      ▼
Database / Drivers
```

Responsibilities:

Libraries

* Shared infrastructure
* Configuration
* Table names
* Regions
* Dates
* Validation
* Strings

Repositories

* Database access only

Services

* Business logic only

Drivers

* External systems only

Presentation

* WordPress pages
* Shortcodes
* Admin screens
* User interaction

---

# SOF Engineering Principles

1. Simplicity over cleverness.
2. Every refactor leaves the system better than before.
3. One recovery point per milestone.
4. One source of truth.
5. Design interfaces before implementations.
6. Services own business rules.
7. Repositories own data access.
8. Libraries own shared infrastructure.
9. Drivers own external systems.
10. Presentation communicates only with Services.
11. Centralize infrastructure knowledge.
12. A layer should never know more than it needs to know.

---

# Current Milestones

## RP01 – Foundation

Completed

* Recovery Point process established
* Documentation process established
* Engineering workflow established

---

## RP02 – Repository Layer

Completed

* Region Officer Repository created
* Production Region Officer table implemented
* Repository successfully tested

---

## RP03 – Service Layer

Completed

* Distribution Service created
* Distribution Notification Service created
* Repository and Service integration verified

---

# Future Roadmap

Phase 1
Foundation

Completed

Phase 2
Distribution Framework

In Progress

Phase 3
Membership Framework

In Progress

Phase 4
Election Framework

Planned

Phase 5
Finance Framework

Planned

Phase 6
Reporting Framework

Planned

Phase 7
Communications Framework

Planned

---

## RP04 – Infrastructure Centralization

Completed

* Member table resolution centralized
* Repository now uses coai_members_table()
* Single source of truth established for member table access
* Infrastructure layer strengthened

---

## RP05 – First Business Logic Extraction

Completed

* First production business logic moved from Presentation layer
* MemberRepository now owns member retrieval
* admin-members.php now consumes repository methods
* Regression testing completed successfully
* Production deployment verified
* 
## RP06 - Member Repository Expansion

Completed

* Repository methods added (get_member_by_id, get_members_page, member lookup consolidation)
* 
## RP07 - Membership Service refactor

Completed

* Business logic centralized into membership-service.php

## RP08 - Google Driver Isolation

Completed

* google-drive.php removed from global loading and converted to lazy loading

## RP09 - OAuth Callback Ingegration

Completed

* OAuth callback route working, productions successfully connected to Google

## RP10 - First Production Google Drive upload

Completed

Recovery Point:
2026-06-23-RP10-first-production-upload

Completed:

• Production OAuth authentication verified
• Refresh token successfully stored
• Google Drive upload completed successfully
• Canada Region exported successfully
• 95 member records uploaded
• Driver architecture verified
• Lazy loading architecture verified
• No production regressions detected

Status:

Production Google Drive integration operational.

## RP11 - Production Distribution Framework

Completed

✅ OAuth authentication
✅ Production folder mapping
✅ Single-region exports
✅ Correct Google folder destinations
✅ Correct regional filenames (Canada.csv, Northeast.csv, etc.)
✅ Existing files replaced rather than duplicated
✅ Driver owns filename logic
✅ No regressions

==================================================
Milestone 1 – Google OAuth Foundation
Milestone 2 – Regional Distribution Framework (Demo)
Milestone 3 – SOF v4.1 Production Migration ✅
Milestone 4 – Communications Framework (planned)
Milestone 5 – Reporting Framework (planned)

Milestone 3
SOF v4.1 Production Migration

Completed:
2026-06-23

Recovery Point:
2026-06-23-RP12-sof-distribution-service-production-working

Objective:
Deploy the SOF Distribution Framework from the demo environment to the
production COAI website and restore Google Drive regional exports using
the new service-oriented architecture.

Completed:
• Migrated admin-members.php from demo to production.
• Deployed Google Distribution Service (google-service.php).
• Enabled Google Drive driver loading in coai-members-custom.php.
• Preserved existing production OAuth credentials and regional folder IDs.
• Verified Google OAuth authentication remained operational.
• Verified Distribution Service integration with Google Drive driver.
• Verified Google export logging remained operational.
• Successfully exported Canada Region to Google Drive.
• Uploaded Canada.csv containing 95 member records.
• Production Google Drive exports restored.

Architecture:

Presentation Layer
(admin-members.php)
        ↓
Distribution Service
(google-service.php)
        ↓
Google Drive Driver
(google-drive.php)
        ↓
Export Logger
(google-logger.php)

Validation:
✓ Member Directory operational
✓ COAI Region filtering operational
✓ Upload to Google Drive operational
✓ Google Drive upload successful
✓ Export logging operational

Status:
SOF v4.1 fully deployed to Production.

Next Milestone:
SOF v4.2
Communications Framework
• Regional VP email notifications
• Master Export orchestration
• Automated export completion messaging

==================================================

## RP12 - SOF Distirubtion Service 

Completed

✅ Production deployment completed.
✅ admin-members.php synchronized.
✅ Distribution Service migrated.
✅ Google Drive driver operational.
✅ OAuth operational.
✅ Production folder IDs preserved.
✅ Logger operational.
✅ Upload works without Apply.
✅ Canada.csv generated correctly.
✅ 95 records uploaded.
✅ Recovery point created.

Milestone 4
SOF v4.2
Communications Framework – Phase 1

Document:

• Architecture
• Recovery Point
• Validation
• Next milestone

==================================================

Milestone 4

SOF v4.2
Communications Framework – Phase 1

Completed

2026-06-24

Recovery Points

2026-06-24-v4.2-communications-framework-phase1

2026-06-24-loader-refactor-core-admin-shortcodes-newsletters-services

Objective

Introduce the Communications Framework into the Service-Oriented Framework
(SOF) architecture and refactor the plugin bootstrap into modular loader
components.

Completed

• Created Communications Service.
• Added Region Officer repository lookup.
• Added Member email resolution.
• Added export notification pipeline.
• Added Communication Test Mode.
• Integrated Communications Framework with Distribution Framework.
• Successfully validated end-to-end notification workflow.

Bootstrap Architecture

• Core Loader
• Admin Loader
• Shortcode Loader
• Newsletter Loader
• Service Loader

Architecture

Presentation Layer
        ↓
Service Loader
        ↓
Distribution Service
        ↓
Google Driver
        ↓
Communications Service
        ↓
WordPress Email Driver

Validation

✓ Distribution Framework operational.
✓ Communications Framework operational.
✓ Email notifications operational.
✓ Modular bootstrap architecture operational.

Next Milestone

SOF v4.2 Phase 2

• Driver Loader
• Repository Loader
• Email Driver abstraction
• Notification templates

==================================================
Milestone 4
Regional Distribution Workflow
==================================================

Completed

2026-06-24

Recovery Point

2026-06-24-v4.2-regional-distribution-summary-complete

Architecture

Presentation
        │
        ▼
Distribution Summary Component
        │
        ▼
Communications Service
        │
        ▼
Distribution Notification Service
        │
        ▼
Distribution Service
        │
        ▼
Google Driver

Completed

✓ Google Drive export
✓ Regional notification routing
✓ Canada dual-region support
✓ Notification summary cards
✓ Export summary component
✓ Google Drive integration
---------------------------------------------------------------

### SOF v4.2.2 – Configuration and Region Selection Foundation

#### Objective

Complete the Distribution Framework by adding support for multiple COAI Region selection while preserving the existing single-region and all-region export/notification behavior.

#### Architectural Direction

A reusable SOF Configuration Layer and Region Selector Component were introduced before modifying Distribution logic.

#### New Layers

```text
Configuration
    ↓
Components
    ↓
Distribution Framework
    ↓
Communications Framework
    ↓
Reporting Framework
```

#### Files Introduced

```text
includes/config/coai-regions.php
includes/components/region-selector.php
```

#### Naming Standard

COAI Region component functions must use COAI-specific names:

```php
coai_get_available_coai_regions()
coai_normalize_coai_region_selection()
coai_validate_coai_regions()
coai_is_all_coai_regions_selected()
```

#### Important Finding

A naming conflict occurred with:

```php
coai_get_available_regions()
```

This affected Member Directory filtering without causing a fatal PHP error. The function was renamed using COAI-specific naming, and Member Directory behavior was restored.

#### Next Step

Add richer COAI Region metadata through:

```php
coai_get_coai_region_config()
```

without modifying the existing backward-compatible:

```php
coai_get_coai_regions()
```
---------------------------------------------------------
Milestone A-001
Distribution Framework Architecture

Discovery

Finding D-001
distribution-service.php currently contains no implementation.

Finding D-002
admin-members.php directly invokes:
coai_google_export_region($region)

Conclusion

Current distribution execution resides in the Presentation Layer.
A new Distribution Service will be introduced to centralize execution.

Discovery
✅ D-001 — distribution-service.php is currently blank.
✅ D-002 — admin-members.php directly calls:
$result = coai_google_export_region($region);

## D-003 – Google Export Function Location

File:
includes/google-service.php

Line:
66

Current Code:
function coai_google_export_region(string $region): array

Purpose:
Executes the current Google Drive export/upload process for a single COAI Region.

Current Architecture:
admin-members.php calls into google-service.php directly for export execution.

Architecture Impact:
The current export execution logic is external to the blank Distribution Service.

Future State:
The Distribution Service should call or wrap this function during the first consolidation phase, instead of moving all Google logic immediately.

## D-004 – Current Google Export Function Responsibilities

File:
includes/google-service.php

Function:
coai_google_export_region(string $region): array

Current Responsibilities:
1. Validates selected region.
2. Retrieves member rows for the region.
3. Builds CSV content.
4. Creates export filename.
5. Uploads CSV to Google Drive.
6. Returns success/failure result array.

Helper Functions Used:
- coai_google_rows_for_region($region)
- coai_google_build_csv($rows)
- coai_google_export_filename_for_region($region)
- coai_google_drive_upload_csv($csv, $filename, $region, $member_count)

Architecture Impact:
This function is already a clean single-region execution unit.

Future State:
The new Distribution Service should not duplicate this logic initially.
It should call this function once per resolved region key/label and aggregate the results.

## D-005 – Master Export UI Trigger

File:
includes/shortcodes/admin-members.php

Lines:
1019–1026

Current Code:
Master Export All COAI Regions button using query arg:
coai_master_export=1

Current Purpose:
Provides the admin/manager UI action for exporting all configured COAI Regions.

Current Architecture:
The Presentation Layer creates a direct master export request using URL query parameters and nonce validation.

Architecture Impact:
This is only the trigger. The actual execution logic still needs to be located.

Next Discovery Step:
Search admin-members.php for:
coai_master_export

## D-006 – Master Export Handler Missing

File:
includes/shortcodes/admin-members.php

Finding:
The Master Export button exists and generates:
coai_master_export=1

However, no request handler was found for:
coai_master_export

Conclusion:
The “Export ALL COAI Regions” button currently appears to be UI-only and does not have an active execution path.

Architecture Impact:
There is not currently a working All Regions execution engine to refactor.
The new Distribution Service should provide the missing All Regions execution path.

I-001 – Distribution Service Foundation

Completed:
2026-06-25

Summary:
Implemented the initial Distribution Service.

The service provides a unified execution engine for one or more COAI Regions.

Version 1 delegates execution to the existing Google Service while aggregating results for future expansion.

Validation:
Member Directory loaded successfully with no regressions.

I-002 – Presentation Layer Integration

Completed:
2026-06-25

Summary:
The Member Directory now delegates export execution to the Distribution Service.

The Distribution Service orchestrates execution while the existing Google Service continues to perform the actual export.

Validation:
Single Region export completed successfully with no regression.

I-003 - Master Export handler

Completed:
2026-06-25

Summary:
The Export ALL COAI Regions button works with new processes in place.

V-003 - Master Export

Completed:
2026-06-25

Test	Result
Master Export button recognized	✅
All configured regions collected	✅
Distribution Service executed all regions	✅
11 of 11 regions exported	✅
Summary notice displayed	✅

I-004 deferred:
Master Export notification cards require an active Communications Service function.
Current code references coai_comm_notify_region_export(), but no function definition was found.

V-001 – Distribution Service Foundation: PASS
V-002 – Single Region Export through Distribution Service: PASS
V-003 – Master Export through Distribution Service: PASS
V-004 – Master Export Dashboard: PASS

I-004 – RVP notification cards deferred.

Reason:
Current code references coai_comm_notify_region_export(), but no function definition was found. Communications Service discovery is required before wiring Master Export email counts/cards.

