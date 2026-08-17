# Changelog
All notable changes to the **COAI Members Custom** plugin will be documented in this file.

FORMAT FOR ALL CHANGELOG ENTIRES CODE BLOCK FORMAT

# ============================================================
#
# Date:
#     August 17, 2026
#
# Recovery Point:
#     RP46
# ============================================================
# Area:
#     Member Portal Presentation Refinement
#
# Environment:
#     TEST → PRODUCTION
#
# ============================================================

## Summary

Completed the SOF Member Portal presentation refinement and
successfully migrated the validated experience from TEST to
PRODUCTION.

The Member Portal now clearly separates a member's personal
relationship with the organization from the organizational
capabilities available through assigned responsibilities.

## Presentation Refinements

- Established a consistent SOF card presentation throughout
  the Member Portal.

- Refined the Membership card to visually communicate the
  member's current renewal situation.

- Membership state presentation now supports:

  - Current
  - Renewal Window
  - Expired
  - Unavailable

- Added state-specific left-border indicators for membership
  situations.

- Organized My Account and Insurance into a responsive
  personal-information grid.

- My Account automatically expands when Insurance information
  is not available.

- Added the Organization Capabilities presentation section.

- Organization Capabilities now uses the SOF responsibility
  model to present only tools available to the logged-in
  person.

- Capability cards use a consistent two-column presentation
  on larger displays and collapse to one column on smaller
  displays.

## Organization Capability Presentation

Validated capability presentation for multiple organizational
responsibilities.

Administrator / Manager:

- Membership Management
- Communications
- Newsletters
- Access Management

Regional Vice President:

- Communications
- Regional Leadership

Baseline Member:

- No Organization Capabilities section is displayed when the
  member has no organizational responsibilities.

## Membership Renewal Protection

Confirmed continued integration of the Membership Renewal
Protection experience within the Member Portal.

The portal displays:

- Membership Expiration Date
- Current membership status
- Renewal availability when within the renewal window
- Renew Membership action when renewal is appropriate

This preserves the RP45 protection against unnecessary or
early membership renewals.

## Production Validation

PRODUCTION validation completed successfully.

Validated:

- Baseline Member
- Regional Vice President
- Administrator / Manager
- Desktop presentation
- Mobile presentation

Each tested account displayed the appropriate Member Portal
experience and organizational capabilities.

Responsive presentation was confirmed operational on mobile.

## Architecture

The Member Portal now reinforces the SOF distinction between:

    My relationship with the organization

and:

    What I am authorized to do for the organization

Organizational capabilities are presented according to
business responsibility rather than exposing administrative
technology or WordPress implementation details.

## Files Changed

PRODUCTION:

    includes/shortcodes/member-portal.php

## Result

Member Portal Presentation Refinement:

    COMPLETE

TEST Validation:

    PASSED

PRODUCTION Migration:

    COMPLETE

PRODUCTION Role Validation:

    PASSED

Mobile Validation:

    PASSED

Production Status:

    STABLE

============================================================
Recovery Point:
    RP46 — Membership Transaction Management &
    Controlled Renewal Application
============================================================
Date:
    August 17, 2026

============================================================
MEMBERSHIP TRANSACTION MANAGEMENT
============================================================

Added controlled handling for situations where an existing COAI
member uses the New Membership registration/payment process.

SOF can now distinguish:

    - Payment may already be reflected
    - Possible Membership Renewal
    - Membership review required
    - Renewal approved for application
    - Application prepared
    - Application pending execution
    - Application requires re-review
    - Application requires verification
    - Application applied

============================================================
MEMBERSHIP RENEWAL CANDIDATE
============================================================

Added provider-independent Membership Renewal Candidate model.

The candidate preserves:

    - Source provider
    - Source transaction identifier
    - Original source business process
    - Member identity
    - Payment evidence
    - Membership intent
    - Intent source

Provider evidence remains unchanged.

============================================================
MANAGEMENT DECISIONS
============================================================

Expanded Membership Management decisions to support a
second-stage Renewal review.

New Membership determination:

    process_as_renewal

remains preserved independently from the later Renewal decision.

Renewal decisions support:

    approve_renewal

    already_reflected

    further_review

============================================================
MEMBERSHIP RENEWAL APPLICATION LEDGER
============================================================

Added persistent Membership Renewal Application ledger:

    wp_sof_membership_renewal_applications

The ledger records both the Membership values before application
and the approved values intended to be applied.

The ledger prevents duplicate Application creation for the same
source payment.

Application states now support controlled progression including:

    pending
    applied
    requires_review
    verification_required
    failed

============================================================
CONTROLLED APPLICATION PREPARATION
============================================================

Added Prepare Application business step.

Preparation:

    - Requires Management approval
    - Reconstructs authoritative source evidence
    - Re-assesses current Membership information
    - Records the pending Application ledger
    - Does NOT modify Membership
    - Does NOT modify Zeffy payment evidence

============================================================
CONTROLLED RENEWAL EXECUTION
============================================================

Added Membership Renewal Application Execution Service.

Execution now:

    - Requires pending Application status
    - Refuses already-applied Applications
    - Requires matching Management approval
    - Performs fresh Membership assessment
    - Verifies calculated values still match prepared values
    - Verifies current Membership values still match the
      Application's recorded BEFORE values
    - Prevents duplicate application
    - Calls Membership-owned write capability
    - Re-reads the Membership record
    - Verifies stored values
    - Marks Application Applied only after successful verification

============================================================
MEMBERSHIP PERSISTENCE
============================================================

Confirmed PRODUCTION already provides:

    coai_update_member_renewal_fields()

through:

    includes/repositories/member-repository.php

PRODUCTION implementation:

    - Updates renewal_date
    - Updates membership_expiration
    - Updates updated_at
    - Reactivates an EXPIRED member when the resulting
      Membership expiration is current/future
    - Uses the established Membership table resolution

A temporary duplicate implementation added to the PRODUCTION
MU plugin was removed after PHP correctly reported a duplicate
function declaration.

PRODUCTION returned to stable operation immediately afterward.

============================================================
ACTIVE WORKSPACE CLEANUP
============================================================

Completed Application records with:

    application_status = applied

are excluded from:

    - Approved Renewals — Ready for Application
    - Active New Membership exception counts

Historical source transactions and Application records remain
preserved.

Principle established:

    Completion does not erase history.
    Completion removes work from the person's desk.

============================================================
PRESENTATION
============================================================

Updated Current New Membership Situation wording to describe
the responsibility of the workspace rather than claiming that
SOF never changes Membership records.

Updated Membership Renewal Management Review wording so an
empty review queue accurately states that no Renewals currently
require Management review.

============================================================
CONTROLLED TEST
============================================================

Completed first end-to-end controlled Membership Renewal in TEST.

Member:

    member_id 126

Before:

    renewal_date = 2025-08-21
    expiration   = 2026-08-20

Applied:

    renewal_date = 2026-08-17
    expiration   = 2027-08-16

Application ledger completed:

    application_status = applied
    applied_by         = 128
    applied_at         = 2026-08-17 14:33:57

Post-write Membership verification succeeded.

============================================================
PRODUCTION
============================================================

RP46 workflow promoted to PRODUCTION.

Temporary New Membership Review page loads successfully.

Current active PRODUCTION Renewal situation:

    Requires Management Attention: 0
    Possible Membership Renewal: 0
    Membership Review Required: 0
    Renewals Ready for Application: 0

No live Membership Renewal execution was performed during
PRODUCTION deployment validation.

============================================================
END CHANGELOG
============================================================

==================================================
## RP45 — Membership Renewal Protection Experience
==================================================

**Date:** August 14, 2026
**Recovery Point:** RP45
**Version:** v4.3.0-RP45
**Status:** Production Validated

---

## 1. Purpose

RP45 introduces the **SOF Membership Renewal Protection Experience**.

The purpose of this recovery point is to prevent COAI members from renewing their memberships unnecessarily before their current membership approaches expiration.

Previously, members could access a public **RENEW MEMBERSHIP** action and proceed directly to Zeffy without MyCOAI first determining whether renewal was necessary.

RP45 changes the experience so that MyCOAI first discovers and assesses the member's current membership situation before presenting a renewal action.

---

## 2. Business Problem

Members could previously renew through the public website without first seeing their current membership expiration date.

This created several risks:

* Members renewing significantly earlier than necessary.
* Members renewing again shortly after a previous renewal.
* Members responding to general renewal communications without realizing their membership was already current.
* Renewal actions being presented without considering the member's actual membership situation.
* MyCOAI acting primarily as a path to payment rather than helping the member make the correct decision.

The desired business outcome is:

**A member should understand their current membership situation before being asked to renew.**

---

## 3. SOF Business Process

RP45 implements the renewal experience using the SOF process:

**Discover Facts**

→ Identify the member.
→ Retrieve the current membership status.
→ Retrieve the Membership Expiration Date.

**Assess**

→ Determine the member's current renewal situation.

**Recommend**

→ Determine whether renewal is appropriate.

**Present**

→ Show the expiration date.
→ Explain the membership situation.
→ Present only the appropriate action.

**Human Response**

→ Member renews when appropriate.
→ Member takes no action when membership is already current.

---

## 4. Renewal Situations

The Membership Service now recognizes the following business situations.

### Current

The membership expiration date is more than 60 days in the future.

MyCOAI presents:

**Your membership is current.**

The Membership Expiration Date is displayed.

The member is informed:

**There is no need to renew at this time.**

No Renew Membership action is presented.

---

### Renewal Window

The membership expiration date is within 60 days.

MyCOAI presents:

**Your membership is approaching expiration.**

The Membership Expiration Date is displayed.

The member is presented with:

**Renew Membership**

---

### Expired

The membership has expired based upon the membership status or expiration date.

MyCOAI presents the expired membership situation and provides the:

**Renew Membership**

action.

---

### Unavailable

If MyCOAI cannot safely determine the member's expiration situation, SOF does not guess.

The member is directed toward assistance rather than being presented with an unsupported renewal recommendation.

---

### Deceased

A deceased membership must never produce a renewal action.

---

## 5. Membership Service Architecture

Renewal eligibility is no longer determined independently inside the Member Portal.

The business decision is now owned by:

`includes/services/membership-service.php`

The Membership Service now provides:

`COAI_Member_Service::get_renewal_situation()`

The service returns the member's renewal situation and supporting business facts, including:

* Situation
* Membership Expiration Date
* Expiration timestamp
* Days until expiration
* Whether renewal may be offered
* Member-facing situation message

This establishes the architectural responsibility:

**Membership Service decides.**

**Member Portal presents.**

**Zeffy processes the renewal when appropriate.**

---

## 6. Member Portal Changes

The Member Portal now consumes the shared Membership Renewal Situation.

The previous duplicated renewal-date calculations were removed from the presentation layer.

The Member Portal now prominently presents the member's Membership Expiration Date and current renewal situation.

The previous unconditional yellow:

**RENEW MEMBERSHIP**

button was removed.

Renew Membership is now presented only when the Membership Service determines that renewal is appropriate.

---

## 7. Public Renewal Protection

The public Home-page **RENEW MEMBERSHIP** button previously allowed members to proceed directly toward Zeffy.

That path has been removed.

The new renewal path is:

**Home**

→ **RENEW MEMBERSHIP**

→ **Renew Membership Gateway**

→ **MyCOAI Login when required**

→ **Member Portal**

→ **Membership Situation Assessment**

→ **Zeffy only when renewal is appropriate**

A new WordPress page was created:

`/renew-membership/`

This page acts as the public gateway into the protected MyCOAI renewal experience.

---

## 8. Home Page Member Experience

The Home-page membership guidance was redesigned around the visitor's business intention.

### Already a COAI Member?

Existing members are instructed to use:

**LOG IN**

to access the Member Portal.

Members are informed that when already authenticated, their **name replaces LOG IN** in the menu bar and may be clicked to access the Member Portal.

---

### Password or First Access Assistance

Existing members who have never logged into MyCOAI or who do not know or have forgotten their password are directed to:

**FORGOT YOUR PASSWORD**

A temporary password is sent to the member's current membership email address.

Members are informed that their username is normally their email address unless they previously changed it.

Members who still require assistance are directed to the COAI Office.

---

### Not a COAI Member Yet?

Non-members are directed to:

**JOIN COAI TODAY!**

This separates new membership from existing-member renewal.

---

### Need to Renew Your Membership?

Existing members are directed to:

**RENEW MEMBERSHIP**

MyCOAI then displays the member's current Membership Expiration Date and determines whether renewal is appropriate.

---

## 9. Changed Plugin Files

The following PRODUCTION plugin files were changed during RP45:

`coai-members-custom.php`

`includes/services/membership-service.php`

`includes/shortcodes/member-portal.php`

---

## 10. WordPress Content Changes

The following WordPress-managed content was changed:

**Home Page**

* Revised membership access guidance.
* Separated Login, Join, and Renewal intentions.
* Explained authenticated member-name navigation.
* Changed the public Renew Membership destination.

**Renew Membership Page**

* Created `/renew-membership/`.
* Established MyCOAI as the gateway to membership renewal.

These WordPress content changes are not contained solely within the plugin Git repository and must therefore be protected through the appropriate Production/site backup process.

---

## 11. Validation

RP45 was first implemented and validated in TEST.

Validation included:

* Member within the 60-day renewal window.
* Member outside the 60-day renewal window.
* Correct Membership Expiration Date presentation.
* Correct Renew Membership action presentation.
* Correct suppression of renewal when renewal is unnecessary.
* Removal of the duplicate Member Portal renewal button.
* Public Renew Membership gateway.
* Home-page member guidance.

Following successful TEST validation, RP45 was deployed to PRODUCTION.

The complete PRODUCTION renewal experience was successfully tested.

**Production Status: VALIDATED**

---

## 12. Technical Finding — Service Loader

During TEST, the main plugin loader was temporarily changed to load:

`includes/services/service-loader.php`

This exposed an existing duplicate function declaration:

`coai_google_export_region()`

The function was already declared through:

`includes/google-drive.php`

and was declared again through:

`includes/services/google-service.php`

This produced a WordPress fatal error.

RP45 intentionally did not expand scope to repair the unrelated service-loader architecture.

Instead, the Membership Service is loaded directly:

`includes/services/membership-service.php`

The service-loader duplication remains a future technical cleanup item.

---

## 13. Architectural Result

RP45 changes renewal from a payment-first experience into a situation-first experience.

Before RP45:

**Member clicks Renew**

→ **Payment path**

After RP45:

**Member requests renewal**

→ **MyCOAI identifies member**

→ **SOF discovers membership facts**

→ **SOF assesses expiration**

→ **SOF recommends the appropriate action**

→ **Member renews only when appropriate**

This establishes an important SOF principle:

> **Discover the facts. Assess the situation. Recommend the appropriate action. Present only what the person needs.**

---

## 14. Recovery Point

**Recovery Point:** RP45
**Git Tag:** `v4.3.0-RP45`

Recommended commit message:

`RP45: Add Membership Renewal Protection Experience`

RP45 represents the Production-validated baseline for the MyCOAI Membership Renewal Protection Experience.


=============================================================
# SOF CHANGELOG — RP45 Zeffy Renewal Integration & Management
=============================================================

**Date:** August 13, 2026  
**Environment:** Production MyCOAI

## Recovery Point Summary

RP45 replaces the normal manual Zeffy Renewal file-import workflow with a direct API-driven SOF process and adds a guarded management workflow for identity, business assessment, application, verification, and audit.

## Added / Changed

### Zeffy API Intake

- Added/validated direct Zeffy API connection.
- Added Renewal campaign routing.
- Added verified Zeffy Renewal rate/product mappings.
- Added Zeffy transaction-ledger synchronization.
- Preserved established SOF assessment/processing knowledge when refreshing known transactions.

### WP-Admin Zeffy Experience

- Reworked Zeffy Import into a three-step Admin workflow:
  1. Retrieve Zeffy Transactions
  2. Assess Renewals
  3. Manage Renewals in MyCOAI
- Added one-click Renewal Assessment.
- Moved engineering controls under Diagnostics & Maintenance.
- Moved old file workflow under Legacy File Import — Emergency Use Only.
- Added persistent Renewal Identity Review to the main workflow.
- Added candidate evidence and human identity confirmation.

### Identity Framework

- Added/preserved automatic matching by established identity evidence.
- Added durable persistence for:
  - review_required
  - ambiguous
  - unresolved
- Prevented automatic assessment from overwriting human-reviewed identity decisions.
- Validated side-by-side Confirm Identity Match experience.

### Renewal Business Assessment

- Standard Renewal expiration calculated as:
  - payment date + 1 year - 1 day
- Business outcomes validated:
  - ready_to_apply
  - possible_previously_applied
  - management_review
  - cannot_assess
- Added/validated Current Expiration vs Standard Expiration review logic.

### Renewal Management Decisions

- Added management decision model/repository/service.
- Decision states:
  - already_applied
  - needs_processing
  - further_review
  - applied
- Decisions preserve transaction, decision maker, timestamp, and notes.
- Active Renewal Management queues now exclude completed/decided historical work appropriately.

### Renewal Application

- Added guarded application service.
- Application re-assesses immediately before update.
- Ready-to-Apply assessment can authorize standard Renewal execution.
- `needs_processing` management decision can authorize managed execution.
- Member is read back after update.
- Date/datetime verification normalized to `Y-m-d`.
- Successful verified applications record decision `applied`.

### Member Portal

- Added Renewal Management Review awareness for Admin/Manager users when current Renewal situations require attention.

## Production Defects Found and Resolved

### Non-Matched Identity Results Were Not Persistent

Symptom:
A new Renewal could be counted as unable to assess but remain `identity_status = unassessed` after the request.

Resolution:
Added `record_identity_assessment()` to persist review_required, ambiguous, and unresolved states.

### One-Click Assessment Did Not Persist Stage 2 Identity Results

Symptom:
The new combined Assess Renewals handler counted identity outcomes but did not write them back to the SOF ledger.

Resolution:
Wired the one-click Stage 2 assessment to persist matched and non-matched identity outcomes.

### Identity Review Was Buried in Diagnostic Output

Symptom:
Admin clicked identity assessment but useful review evidence appeared far below the normal workflow.

Resolution:
Added persistent Renewal Identity Review directly between workflow Steps 2 and 3.

### Application Verification Failed on Equivalent Date/Datetime Values

Symptom:
Member was correctly updated but SOF reported that verification values did not match.

Cause:
MyCOAI `membership_expiration` may be stored as DATETIME (`YYYY-MM-DD 00:00:00`) while SOF proposed a date (`YYYY-MM-DD`).

Resolution:
Normalize stored and proposed Renewal/Expiration values to calendar dates before verification.

### Completed Application Audit Rows Missing During Early Validation

Validated affected records were repaired only after member values and transaction identity were proven.

The corrected Application Service now records the `applied` decision after successful read-back verification.

## Production Validation

Validated:
- successful API intake
- identity auto-match
- identity exception and human review
- previously-applied detection
- management decision
- Ready-to-Apply application
- read-back verification
- audit decision
- zero remaining active Renewal queues

Final Current Renewal Situation:
- Requires Management Attention: 0
- Possible Previously Applied: 0
- Management Review: 0
- Needs Processing: 0
- Further Review: 0
- Ready to Apply: 0

## Changed-File Inventory for RP45

### Confirmed/Strongly Indicated Modified August 13

- `coai-zeffy-importer.php`
- `member-portal.php`
- `repositories/member-repository.php`
- `includes/SOF/Zeffy/Models/ZeffyRenewalManagementDecision.php`
- `includes/SOF/Zeffy/Repositories/ZeffyRenewalManagementDecisionRepository.php`
- `includes/SOF/Zeffy/Repositories/ZeffyTransactionRepository.php`
- `includes/SOF/Zeffy/zeffy.php`
- `includes/SOF/Zeffy/Presentation/Shortcodes/RenewalManagementReviewShortcode.php`
- `includes/SOF/Zeffy/Presentation/Workspaces/RenewalManagementReviewWorkspace.php`
- `includes/SOF/Zeffy/Services/ZeffyRenewalApplicationService.php`
- `includes/SOF/Zeffy/Services/ZeffyRenewalReviewService.php`
- `includes/SOF/Zeffy/Services/ZeffyRenewalBusinessAssessmentService.php`
- `includes/SOF/Zeffy/Services/ZeffyRenewalManagementDecisionService.php`

### Included in Zeffy Folder but File Timestamp Indicates August 12 Foundation

These are dependencies of RP45 and should be included in the repository baseline if not already present, but their supplied timestamps do not prove an August 13 modification:

- `includes/SOF/Zeffy/Models/ZeffyTransaction.php`
- `includes/SOF/Zeffy/Services/ZeffyRenewalIdentityService.php`
- `includes/SOF/Zeffy/Services/ZeffyIdentityResolutionService.php`
- `includes/SOF/Zeffy/Services/ZeffyRenewalAssessmentService.php`

### Repository ZIP Items Not Indicated as August 13 RP45 Changes

The supplied repository ZIP also contained:
- `region-officer-repository.php`
- `region-officer-repository.phpbeforeupdate`

Their supplied timestamps are from June/July 2026. Do not include them in the RP45 change set solely because they were in the ZIP.

## Git Recovery Point Recommendation

Recovery point:

**RP45 — Zeffy Renewal Integration & Management**

Suggested tag:

`v4.3.0-RP45`

Suggested commit message:

`RP45: Complete Zeffy Renewal API integration and management workflow`


# ============================================================
# SOF CHANGELOG
# ============================================================
#
# Date:
#     August 11, 2026
#
# Area:
#     Communications / Newsletters
#
# Recovery Point:
#     RP44.x — Newsletter Production Integration
#
# ============================================================


# ============================================================
# NEWSLETTER COMPOSITION
# ============================================================

ADDED:

    Rich-text editing for Newsletter content sections.

ADDED:

    Dynamic rich-text editor initialization for newly created
    Newsletter sections.

ADDED:

    Configurable section image sizes:

        Small
        Medium
        Large
        Full Width

ADDED:

    Medium as the default image size for newly created
    Newsletter sections.

UPDATED:

    Newsletter section processing to preserve image-size
    selections through save and reload.

VALIDATED:

    Newsletter drafts retain rich content, images, image-size
    selections, and other composition information after being
    reopened.


# ============================================================
# NEWSLETTER RENDERING
# ============================================================

UPDATED:

    Newsletter title presentation to prevent long titles from
    being visually cut off.

UPDATED:

    Newsletter section content presentation to provide natural
    text wrapping for longer content.

UPDATED:

    Section image rendering to honor the selected image size
    while remaining responsive.

UPDATED:

    Newsletter action buttons to use email-safe table-based
    markup.

FIXED:

    Mobile email clients could display an additional unwanted
    block around or near Newsletter action buttons.

VALIDATED:

    Final action button renders correctly and remains clickable
    in an actual received mobile email.


# ============================================================
# COMMUNICATION WORKFLOW
# ============================================================

FIXED:

    SendWorkspace.php was applying wpautop() to already-rendered
    Newsletter HTML.

    This could alter Newsletter markup between approval and the
    final Send Communication presentation.

CHANGED:

    Send Workspace now presents the stored Communication body
    without applying wpautop() again.

ESTABLISHED:

    Rendered Communication HTML should remain stable throughout
    downstream lifecycle presentation.


# ============================================================
# PRODUCTION VALIDATION
# ============================================================

DEPLOYED:

    Final Newsletter editor and rendering changes to Production.

VALIDATED:

    Production Newsletter composition and Communication
    integration.

VALIDATED:

    Production queued Newsletter delivery.

RESULT:

    Attempted: 4
    Delivered: 4
    Failed: 0
    Completion: 100%

VALIDATED:

    Newsletter received by actual email recipient.

VALIDATED:

    Newsletter rendering on mobile email client.

VALIDATED:

    Newsletter action button operates correctly from the
    received email.


# ============================================================
# FILES CHANGED
# ============================================================

MODIFIED:

    includes/SOF/Communications/Presentation/Workspaces/
    SendWorkspace.php

MODIFIED:

    includes/SOF/Newsletters/Presentation/Assets/
    newsletter-compose-workspace.js

MODIFIED:

    includes/SOF/Newsletters/Presentation/Renderers/
    NewsletterHtmlRenderer.php

MODIFIED:

    includes/SOF/Newsletters/Presentation/Shortcodes/
    ComposeNewsletterShortcode.php

MODIFIED:

    includes/SOF/Newsletters/Presentation/Workspaces/
    ComposeNewsletterWorkspace.php


# ============================================================
# RESULT
# ============================================================

Newsletter composition and delivery are now integrated with
the SOF Communications architecture.

The completed implementation has been validated through:

    Composition
    Persistence
    Presentation
    Verification
    Test Delivery
    Approval
    Queued Delivery
    Delivery Status
    Actual Recipient Email
    Mobile Email Rendering

Newsletter Production Integration:

    VALIDATED

=================================================================
# RP44 — Newsletter Production Deployment & SOF Access Validation
=================================================================

**Date:** August 10, 2026  
**Recovery Point:** RP44  
**Status:** Production Deployed and Validated

---

## Summary

RP44 completed the Production deployment and operational validation of the SOF Newsletter framework.

The Newsletter authoring experience is now integrated with the SOF Communications lifecycle and supported by SOF Access, Audience, Organization, and Membership capabilities.

Production validation confirmed that an authorized Administrator can create a Newsletter, define its audience, select individual recipients, preview the completed Newsletter, perform a test delivery, review the communication, approve it, and safely move backward through the lifecycle for additional testing or revision.

This recovery point also established the SOF Access persistence infrastructure required for capability-based Newsletter authorization.

---

## Newsletter Production Deployment

Deployed the SOF Newsletter framework to Production.

Production now includes:

- Newsletter Models
- Newsletter Services
- Newsletter Repository
- Newsletter HTML Renderer
- Newsletter Composition Workspace
- Newsletter Library Workspace
- Newsletter Preview Workspace
- Newsletter Shortcodes
- Newsletter presentation assets
- Newsletter communication integration

Created the Production Newsletter persistence table required by the framework.

Created the required WordPress pages for:

- Newsletters
- Compose Newsletter
- Newsletter Preview

Validated that the Newsletter workspaces load correctly from the Production Member Portal.

---

## SOF Supporting Framework Deployment

Production was brought into alignment with the SOF architecture required by Newsletters and Communications.

The following SOF frameworks were deployed or activated:

- Access
- Audience
- Organization
- Newsletters

The primary plugin loader was updated so these frameworks are loaded in the required architectural sequence.

---

## SOF Access Persistence

Production was missing the persistence layer required by the SOF Access framework.

Created:

- `wp_sof_access_assignments`
- `wp_sof_access_grants`

The Production schemas were matched to the validated Test environment, including required indexes and uniqueness constraints.

Access was then assigned through the SOF Manage Access experience rather than by manually inserting authorization records.

The Production Super Admin profile successfully generated its associated business and platform capability grants.

---

## Newsletter Authorization

Validated the SOF authorization path:

Person  
→ Access Profile  
→ Capability Grant  
→ Scope  
→ Newsletter Audience

The Production Administrator was assigned:

- Access Profile: Super Admin
- Scope: Entire Organization

The resulting capability grants included:

- `manage_newsletters`

with organization-wide scope.

Newsletter audience resolution subsequently identified the organization-wide membership population correctly.

---

## Newsletter Audience and Recipient Selection

Before SOF Access persistence was established, Newsletter composition reported:

- Eligible Members: 0
- Specific Member Selection: unavailable

After Access configuration, Production correctly resolved:

- Membership Status: Active
- Eligible Members: 1,052

Specific Member Selection successfully loaded the eligible member population.

Production testing used a single selected member to prevent accidental organizational delivery during validation.

---

## Newsletter Media

Validated WordPress Media Library integration in Production.

Newsletter authors can select Production media for:

- Header logos
- Newsletter section images
- Signature images

Confirmed that media selection resolves against the Production WordPress uploads environment.

---

## Newsletter Action Buttons

Validated Newsletter section action buttons.

A rendering artifact was discovered around Newsletter action buttons during lifecycle review and delivered email testing.

The Newsletter action button renderer was revised to use email-compatible presentation-table markup.

The button was further constrained to retain its natural content width rather than expanding across the Newsletter.

Final validation confirmed:

- Compact action button presentation
- Correct button label
- Correct link behavior
- No unwanted rendering artifact
- Correct presentation in delivered test email

---

## Structured Newsletter HTML

A presentation issue was identified when Newsletter HTML entered the Communications lifecycle.

Approve Communication was applying `wpautop()` to the already structured Newsletter HTML.

Because Newsletter bodies contain complete HTML structures including presentation tables, images, sections, and action buttons, applying `wpautop()` could introduce additional paragraph or break markup.

Approve Communication was updated to distinguish structured HTML from ordinary Communication content.

Structured Newsletter HTML is now rendered without additional automatic paragraph processing.

Normal text Communications continue to receive standard paragraph formatting.

This preserves both:

- traditional Communication rendering
- structured Newsletter rendering

---

## Communications Lifecycle Integration

Production successfully validated the Newsletter-to-Communications workflow:

Newsletter Composition  
→ Save Draft  
→ Preview  
→ Verify  
→ Test Communication Delivery  
→ Approve Communication

Validation confirmed preservation of:

- Newsletter subject
- Newsletter HTML
- Header logo
- Section content
- Section images
- Action buttons
- Signature
- Audience
- Membership status
- Eligible recipient population
- Selected recipient population

---

## Lifecycle Navigation

A defect was identified in:

Approve Communication  
→ Return to Test

The Test URL contained the Communication identity, but the GET form did not explicitly submit the `communication_id`.

The navigation was updated to submit the Communication identity as a hidden form value.

Production validation confirmed:

Approve Communication  
→ Return to Test  
→ Correct Communication Restored

The communication retained:

- Entire Organization audience
- Active membership status
- 1,052 eligible members
- 1 selected member
- Newsletter content
- Test delivery results

This confirms that the Communications lifecycle can move backward for additional review without losing its business context.

---

## Production Safety Validation

Production testing deliberately used a single selected member.

The workflow was exercised through the final approval/review stages without performing an unintended organization-wide delivery.

The Revise Communication and Return to Test paths were also exercised to confirm safe backward lifecycle navigation.

---

## Architectural Validation

RP44 demonstrates the operational SOF relationship:

Organization  
→ Person  
→ Access  
→ Capability  
→ Scope  
→ Audience  
→ Communication  
→ Human Experience

Authorization is no longer determined merely by a WordPress role or legacy member `usergroup`.

SOF Access provides explicit business capabilities and organizational scope that downstream frameworks can consume.

Newsletter audience resolution successfully demonstrated this architecture in Production.

---

## Production Status

At completion of RP44:

- SOF Newsletters operational
- SOF Access operational
- SOF Audience operational
- SOF Organization operational
- Newsletter persistence operational
- Access persistence operational
- Administrator authorization operational
- Organization-wide audience resolution operational
- Membership filtering operational
- Specific recipient selection operational
- WordPress Media Library integration operational
- Newsletter preview operational
- Test email delivery operational
- Structured HTML rendering operational
- Newsletter action buttons operational
- Communications lifecycle integration operational
- Return to Test navigation operational
- Revision workflow operational

**RP44 Production deployment and operational validation are complete.**

---

## Next — RP45 Newsletter Authoring Experience

The next Newsletter evolution will focus on improving the author's content-writing experience.

Initial capabilities identified for RP45 include:

- Bold
- Italic
- Links
- Bulleted lists
- Numbered lists
- Undo
- Redo

The author will control the meaning and emphasis of the content while SOF continues to control the overall Newsletter presentation and design.

### Design Principle

> **Authors control the message. SOF protects the experience.**

# ============================================================
# RP43 — Communications Production Deployment
# ============================================================

Date:
    August 9, 2026

Status:
    Production Validated

------------------------------------------------------------
Summary
------------------------------------------------------------

RP43 Communications Workspace Experience was successfully
deployed from the Test environment into Production.

The complete Communications lifecycle is now operational
in Production:

    Compose Communication
        ↓
    Verify Communication
        ↓
    Test Communication
        ↓
    Approve Communication
        ↓
    Send Communication
        ↓
    Confirm Communication

A controlled Production communication was successfully
completed through the entire lifecycle.

------------------------------------------------------------
Production Deployment
------------------------------------------------------------

The current SOF Communications, Membership, Audience,
Presentation, and supporting framework files were deployed
into Production.

Production WordPress pages were created for the
Communications Workspace lifecycle.

During Production validation, an older version of:

    Communications/Services/CommunicationAudienceService.php

was discovered in Production.

The Production file was replaced with the current RP43
Test version supporting:

    resolve_current_audience()

This restored the expected RP43 audience resolution used
by Compose Communication.

------------------------------------------------------------
Production Database Migration
------------------------------------------------------------

The Production table:

    wp_sof_communications

was updated to support the current RP43 Communication model.

Columns added:

    source_type
    source_id
    recipient_selection_mode
    selected_member_ids

This aligned the Production database schema with the
Communication persistence model.

A separate migration record was created:

    RP43-PRODUCTION-DATABASE-CHANGES.txt

------------------------------------------------------------
Production Operational Validation
------------------------------------------------------------

The complete Communications lifecycle was validated in
Production.

Compose Communication
    PASS

Verify Communication
    PASS

Test Communication
    PASS

Amazon SES Test Delivery
    PASS

Test Email Received
    PASS

Approve Communication
    PASS

Send Communication
    PASS

Confirm Communication
    PASS

------------------------------------------------------------
Controlled Production Delivery
------------------------------------------------------------

The Production validation used recipient selection to
ensure that organizational delivery remained controlled.

Eligible Members:
    152

Selected Members:
    1

Approved Recipients:
    1

Currently Available:
    1

Attempted:
    1

Delivered:
    1

Failed:
    0

The selected-recipient intent successfully survived the
entire lifecycle from Compose through actual delivery.

------------------------------------------------------------
Production Result
------------------------------------------------------------

RP43 Communications Workspace Experience is operational
and validated in Production.

The Production deployment demonstrated successful:

    Audience resolution
    Recipient selection
    Communication persistence
    Lifecycle transitions
    Amazon SES test delivery
    Organizational delivery
    Delivery confirmation

RP43 is considered stable and Production ready.

------------------------------------------------------------
Architecture Observations
------------------------------------------------------------

The Production deployment reinforced an important SOF
deployment requirement:

    Application Files
        +
    WordPress Presentation Pages
        +
    Database Schema
        =
    Complete Deployment

WordPress Workspace pages are database-managed resources
and therefore must be included in future deployment
planning even though they are not represented by the
plugin Git repository.

Database migrations must likewise be explicitly documented
and applied when framework models evolve.

------------------------------------------------------------
Next Development Direction
------------------------------------------------------------

The next development direction will focus on SOF Access
and organizational capabilities.

The Member Portal will remain the common home for all
members.

Personal account functions remain available through the
member account experience.

Organizational capabilities will be presented through:

    Staff Tools

Staff Tools will expose capabilities according to the
person's authorization and organizational scope.

Planned capabilities include:

    Compose Communication
    Compose Newsletter

Administrators and Managers will be able to control which
people are authorized to use these capabilities.

The existing SOF Newsletter composition framework will
also be reviewed for reintegration with the completed
RP43 Communications lifecycle.

====================================================================
# RP38 - Communications Workflow and Release
====================================================================

## Added

- SOF Communication Verify workspace.
- SOF Communication Test workspace.
- SOF Communication Approve workspace.
- SOF Communication Send workspace.
- SOF Communication Confirm workspace.
- Communication Situation architecture.
- Communication Facts architecture.
- Communication audience population discovery.
- Membership-status audience selection.
- Communication sender resolution.
- Communication delivery provider boundary.
- WordPress Mail Delivery Provider.
- Communication Delivery Service.
- Communication Release Service.
- Communication revision lifecycle.
- Atomic Communication release claim.
- Persisted Communication release results.
- Release result confirmation presentation.

## Audience

- Added eligible Communication population support for Active members.
- Added eligible Communication population support for Expired members.
- Added eligible Communication population support for Archived members.
- Intentionally excluded Deceased members from normal Communication audiences.
- Added persisted membership-status audience selection.
- Added All Eligible Members audience selection.
- Added approved audience count presentation.
- Added current delivery audience count presentation.

## Situation

- Expanded Communication Situation to include Audience.
- Expanded Communication Situation to include Population.
- Expanded Communication Situation to include Recipients.
- Added Communication Facts.
- Separated discovered facts from Assessment.
- Separated Assessment from Recommendation.
- Added Available Actions to Communication Situation.

## Workflow

- Established operational Communication workflow:

  Compose
  → Verify
  → Test
  → Approve
  → Send
  → Confirm

- Added persisted verification evidence.
- Added persisted test evidence.
- Added persisted approval evidence.
- Added persisted release evidence.
- Added final release result presentation.

## Revision

- Added Revise Communication action for approved Communications.
- Added approved as a valid source state for Communication revision.
- Revision returns the Communication to composed.
- Revision clears previous verification evidence.
- Revision clears previous test evidence.
- Revision clears previous approval evidence.
- Revised Communications must pass Verify, Test, and Approve again before release.

## Delivery

- Added CommunicationDeliveryProvider boundary.
- Added SOF_WordPressMailDeliveryProvider.
- Added CommunicationDeliveryService provider coordination.
- Integrated SOF delivery with wp_mail().
- Verified FluentSMTP transport.
- Verified Amazon SES physical email transport.
- Preserved organizational From identity.
- Added human Communication sender as Reply-To.

## Release

- Added CommunicationReleaseService.
- Release coordinates approved Communication delivery.
- Added sending lifecycle persistence before recipient delivery.
- Added successful delivery submission counting.
- Added failed delivery submission counting.
- Added final sent lifecycle persistence.
- Added delivery_failed lifecycle persistence.
- Added release failure result handling.

## Release Safety

- Added WordPress nonce protection to Communication release.
- Added atomic approved-to-sending persistence transition.
- Prevented concurrent release requests from independently claiming the same approved Communication.
- Added authoritative reload of the persisted sending Communication before delivery.
- Added protection against normal repeated release after delivery begins.
- Added persistence uncertainty handling.
- Prevented Send presentation from treating uncertain release results as safely releasable.

## Recovery

- Identified stranded sending lifecycle state during release testing.
- Reconciled persisted Communication state against FluentSMTP delivery history before recovery.
- Confirmed no audience delivery occurred before restoring the test Communication to approved.
- Identified future Communication Release Recovery and Reconciliation capability.

## Confirm

- Added Confirm Communication workspace.
- Added persisted release result presentation.
- Added attempted recipient count.
- Added delivered submission count.
- Added failed submission count.
- Added sent timestamp presentation.
- Added Communication audience, subject, message, and delivery information.

## Production Verification

- Successfully verified test delivery through FluentSMTP and Amazon SES.
- Successfully verified organizational From identity.
- Successfully verified human Reply-To identity.
- Successfully exercised approved Communication revision.
- Successfully re-verified, re-tested, and re-approved revised Communication content.
- Successfully completed the first controlled live SOF Communication audience release.

## First Live Release

Communication 11:

- Audience: South Central Region
- Included Members: Active
- Delivery: Email
- Attempted: 156
- Successful Submissions: 156
- Failed: 0
- Final Status: sent
- Date: July 28, 2026

This represents the first verified production Communication completed through the full SOF Communications workflow.

## Engineering

- Maintained Membership ownership of member discovery and eligibility knowledge.
- Maintained Communications ownership of Communication intent and lifecycle.
- Maintained Release ownership of approved action coordination.
- Maintained Delivery ownership of delivery capability selection.
- Maintained Provider ownership of transport execution.
- Kept SOF independent of Amazon SES implementation details.
- Established atomic persistence as the release concurrency boundary.
- Established persisted lifecycle state as authoritative for release results.

## Future

- Intentional partial audience selection.
- Communication scheduling workflow.
- Interrupted release recovery and reconciliation.
- Provider event feedback.
- Bounce handling.
- Complaint handling.
- Final mailbox delivery confirmation.
- Failed-recipient review and follow-up.

=====================================================================
## Communications - Verify, Test, Approval, and Release Foundation
=====================================================================

### Added

- Verify Communication lifecycle experience.
- Test Communication lifecycle experience.
- Approve Communication lifecycle experience.
- Release Communication foundation.
- Send Communication Workspace.
- Send Communication shortcode.
- Send Communication WordPress presentation page.
- Communication Sender model.
- Communication Sender Service.
- Organizational sender resolution.
- Test recipient override capability.
- Final approval confirmation.
- Release confirmation presentation.
- Send Now release path.
- Schedule Delivery release path foundation.

## Communication Verification

### Added

- Persisted Communication loading by identity.
- Verification presentation for:
  - Audience
  - Delivery channel
  - Recipient count
  - Subject
  - Message
- Verified lifecycle transition.
- Continue to Test lifecycle action.

## Communication Testing

### Added

- Test Communication Workspace.
- Test recipient email input.
- Test delivery execution.
- Test delivery result handling.
- Tested lifecycle transition.
- Test result persistence.
- Revision path from Test.
- Continue to Approve lifecycle action.

### Changed

- Test recipient identity now resolves from Membership data rather than relying on the WordPress user profile email.
- Test email may be changed for an individual test without modifying the member record.
- Test Communication presentation now identifies the organizational sender.
- Successful Test presentation now provides lifecycle actions rather than permitting accidental duplicate test submission.

## Communication Sender

### Added

- Communication Sender business object.
- Communication Sender Service.
- Sender member identity.
- Sender organizational role.
- Sender organizational scope.
- Sender communication address.
- Organizational display title generation.

### Changed

- Regional Vice President presentation now removes the redundant Region suffix from organizational scope.

Example:

South Central Region
+
Regional Vice President

is presented as:

South Central Regional Vice President

- Test email closing now includes organizational sender identity.

Example:

Thank you,

Sender Name
Organizational Title
Sender Email

## Communication Approval

### Added

- Approve Communication Workspace.
- Approve Communication shortcode.
- Tested lifecycle guard.
- Final approval confirmation.
- Approved lifecycle transition.
- Approval persistence.
- Send Now release action.
- Schedule Delivery release action.

### Changed

- Approval is now treated as authorization for Release rather than delivery itself.
- Approval presentation focuses on business evidence rather than duplicate technical lifecycle state.
- Redundant Status / Tested presentation was removed.
- Successful test evidence is presented as the relevant approval evidence.

## Communication Release

### Added

- Release as a distinct Communication business concept.
- Send Now as an immediate Release strategy.
- Schedule Delivery as a future Release strategy.
- Send Communication Workspace.
- Final Release confirmation.
- Approved lifecycle guard for Send Communication.

### Architecture

The Communication lifecycle now follows:

Composed
→ Verified
→ Tested
→ Approved
→ Released
→ Delivered

Approval authorizes Release.

Release causes delivery.

## Audience Resolution

### Direction

Release will re-resolve the current eligible audience before actual delivery.

The persisted recipient count represents the audience approved earlier in the lifecycle.

The current delivery audience will be determined from current Membership knowledge before Release.

This allows delivery to respect:

- Unsubscribes
- Archived members
- Missing email addresses
- Invalid email addresses
- Other eligibility changes

## Delivery Provider Architecture

### Direction

Large email Communications will use the organization's existing Amazon SES capability.

The Send Workspace will not communicate directly with Amazon SES.

The intended architecture is:

Send Workspace
→ Communication Release Service
→ Communication Delivery Service
→ Delivery Provider
→ Amazon SES

Provider-specific delivery remains outside the Communication Experience layer.

Future delivery channels may introduce additional providers without changing the Communication lifecycle.

## Test Delivery

### Direction

Test delivery should use the same delivery provider capability used for actual Release.

This allows Test to validate the actual delivery path before a Communication is approved and released.

## Engineering

- Continued separation between Business and Experience responsibilities.
- Communication lifecycle state remains persisted with the same Communication identity.
- Implemented Post → Redirect → Get behavior for lifecycle actions.
- Maintained lifecycle guards between Verify, Test, Approve, and Release.
- Separated human approval from delivery execution.
- Prevented Send Communication from performing bulk delivery until provider architecture is established.
- Continued frontend Workspace architecture without introducing wp-admin workflow dependencies.

## Next

- Establish Communication Release Service.
- Establish Communication Delivery Service.
- Establish Delivery Provider abstraction.
- Integrate Amazon SES as the email delivery provider.
- Re-resolve current Membership recipients at Release.
- Present current eligible recipient information before Send.
- Activate Send Communication only after Release safety boundaries are verified.
- Record delivery results.
- Build delivery Confirmation experience.
- Implement Schedule Delivery.

=====================================================================
# RP38 - Knowledge Domain Collaboration and Communication Composition
=====================================================================

## Added

- SOF Membership Knowledge Domain foundation.
- Membership framework loader.
- Membership Audience Service.
- Membership Region Knowledge.
- Membership Country Knowledge.
- Communication Composition Service.
- Country-group audience resolution.
- Canonical country identity support.
- Legacy country alias support.
- Communication composition handling in Compose Workspace.

## Membership

- Established Membership ownership of member audience discovery.
- Moved regional membership knowledge behind the Membership domain boundary.
- Established organizational region definitions independent of the legacy member region field.
- Added geographic resolution for organizational regional audiences.
- Added country-group resolution for international organizational audiences.
- Established canonical country identities for Membership Knowledge.
- Added support for existing production country representations.
- Added legacy country aliases including:
   - UK → GB
   - GE → DE
- Defined Latin Region as:
   - Mexico
   - Central America
   - South America
   - Caribbean
- Defined International Region as:
   - Europe
   - Asia
   - Oceania
- Defined Western Canada as:
   - British Columbia
   - Alberta
   - Saskatchewan
   - Manitoba
- Defined Eastern Canada as:
   - Newfoundland and Labrador
   - Prince Edward Island
   - Nova Scotia
   - New Brunswick
   - Quebec
   - Ontario
- Preserved Canadian territories within the broader Canada Region.

## Communications

- Integrated Membership Audience capability with Communications.
- Updated Communication Recipients discovery to consume Membership-owned audience discovery.
- Preserved Communications ownership of recipient availability and communication readiness.
- Continued Communication Situation integration within Compose Workspace.
- Added Communication Composition capability.
- Connected Compose Workspace subject and message input to Communication Composition.
- Created composed `SOF_Communication` business objects from user input.
- Recorded communication creator.
- Recorded organizational region.
- Recorded resolved recipient count.
- Established `composed` as the successful result of the Compose experience.
- Enabled the Continue to Verify action after successful audience resolution.
- Added nonce validation to communication composition.
- Preserved subject and message content following composition processing.

## Communication Situation

- Continued use of Communication Situation as the aggregate understanding presented to Compose Workspace.
- Communication Situation currently provides:
   - Audience
   - Recipients
   - Assessment
   - Recommendation
   - Available Actions
- Refined recommendation language to separate assessment from recommended action.
- Preserved business reasoning outside Presentation.

## Knowledge

- Established Membership Region Knowledge as the organizational authority for regional membership definitions.
- Established Membership Country Knowledge as the organizational authority for country identities and country groups.
- Separated canonical country identity from legacy data representation.
- Established the pattern:

  Human Identity

  ↓

  Knowledge Normalization

  ↓

  Canonical Identity

  ↓

  Business Capability

- Allowed Membership capabilities to understand legacy production data without requiring member records to be rewritten.

## Architecture

- Demonstrated the first operational Knowledge Domain collaboration in SOF.
- Established Membership as the owner of membership knowledge.
- Established Membership Audience Service as a Business Capability consumed by Communications.
- Removed regional member discovery responsibility from Communications.
- Continued separation of Presentation from business reasoning.
- Established the operational flow:

  Membership Knowledge

  ↓

  Membership Capability

  ↓

  Communications

  ↓

  Communication Situation

  ↓

  Compose Experience

  ↓

  Communication Composition Capability

  ↓

  SOF Communication

- Demonstrated that Business Experiences can consume Knowledge Domain capabilities without owning their implementation.

## Production Validation

- Restored Compose Communication after Membership dependency and loader integration.
- Corrected Membership repository loading dependencies.
- Corrected organizational region resolution where `wp_members.region` represents broad geographic groups rather than RVP organizational regions.
- Validated production country data against Membership Country Knowledge.
- Confirmed South Central Region audience discovery throughout the architectural refactor.
- Validated South Central Regional Vice President audience at:
   - 160 total recipients
   - 160 available recipients
   - 0 unavailable recipients
- Confirmed Compose Workspace remained operational after Region Knowledge integration.
- Confirmed Compose Workspace remained operational after Country Knowledge integration.
- Confirmed successful creation of a composed Communication from Regional Vice President subject and message input.

## Experience

- Advanced the Regional Vice President Communication Experience from audience discovery into operational composition.
- Regional Vice Presidents can now:
   - Access their authorized regional communication audience.
   - See resolved recipient availability.
   - Understand the current Communication Situation.
   - Enter a communication subject.
   - Enter a communication message.
   - Prepare the Communication for verification.
- Removed the need for the user to manually manage recipient lists during communication composition.

## Current Limitation

- Composed Communications currently exist only during the Compose request.
- Communications are not yet persisted.
- Verify Workspace has not yet been implemented.
- Compose does not yet transition a persisted Communication into Verify.
- Additional audience validation remains for:
   - Western Canada Region
   - Eastern Canada Region
   - Canada Region
   - Latin Region
   - International Region

## Next

- Create Communication Repository.
- Create Communication Persistence Service.
- Persist composed Communications.
- Assign persistent Communication identity.
- Create Verify Communication Workspace.
- Transition Compose → Verify using persisted Communication identity.
- Continue the Communication Experience:
   - Verify
   - Test
   - Approve
   - Schedule
   - Send
   - Confirm
- Complete the first persisted Business Experience lifecycle transition.

=============================================
# RP38 - Communication Situation Integration
=============================================

## Added

- SOF Membership Audience Service.
- Membership-owned regional member discovery.
- Communication Situation Service.
- Communication Available Actions object.
- Communication Available Actions Service.
- Communication Situation integration into Compose Workspace.
- Audience resolution diagnostics for development.

## Architecture

- Established Communication Situation as the aggregate business object for the Communications framework.
- Established Available Actions as a distinct business concept separate from authorization.
- Established Membership ownership of member discovery.
- Communications no longer owns membership repository knowledge.
- Business reasoning pipeline now consists of:
  - Audience
  - Recipients
  - Assessment
  - Recommendation
  - Available Actions
  - Situation

## Engineering

- Added temporary audience diagnostics to validate business experience resolution.
- Confirmed audience resolution for Regional Vice Presidents.
- Confirmed communication assessment pipeline executes through Situation resolution.
- Identified Membership as the proper owner of member discovery responsibilities.
- Deferred Communications-to-Membership integration until Membership services are fully established.

=======================================
# RP35 - Regional Workspace Foundation
=======================================

## Added

- Regional Workspace foundation.
- Regional Member Directory.
- Regional Member Directory shortcode.
- Regional Member Portal integration.
- Regional Vice President capability authorization.
- Active Regional Vice President discovery.
- Region-locked member access.
- Regional archived member filtering.
- Regional member CSV export.
- Regional member directory sorting.

## Changed

- Member Portal now displays Regional Vice President tools for active Regional Vice Presidents.
- Removed Staff Tools section from Regional Vice President Member Portal.
- Regional directory now supports archived member visibility.
- Regional directory now supports sortable column headings.
- Regional CSV export restricted to assigned COAI region.
- Regional member directory preserves search, filtering, paging, and sorting during navigation.

## Engineering

- Established Regional Workspace foundation architecture.
- Extended existing Member Directory without duplicating business logic.
- Reused existing filtering, sorting, paging, and export framework.
- Implemented server-side regional authorization enforcement.
- Centralized Regional Vice President identity discovery.
- Separated Regional Vice President responsibilities from Staff responsibilities.
- Reinforced capability-based authorization over role-based access.
- Protected regional exports from URL parameter manipulation.

=======================================
# RP33.2 - Magazine Archive Experience
=======================================

## Added

- Archive landing page hero section.
- Member Portal navigation button.
- Magazine detail page header.
- Volume and Number presentation.
- Publication year display.
- Official publication subtitle.
- Improved archive card titles using Volume and Number.
- Enhanced magazine hover animation.

## Improved

- Magazine Archive visual design.
- Archive background with warm gradient styling.
- Archive page depth using elevated shadow effects.
- Year section headings for improved readability.
- Magazine card spacing and layout.
- Archive introduction messaging.
- Magazine detail page presentation.
- Member navigation experience.

## Engineering

- Refactored archive layout into a single presentation container.
- Corrected archive wrapper structure.
- Simplified magazine detail presentation logic.
- Standardized navigation using `home_url()`.
- Improved responsive archive grid behavior.
- Added mobile-safe grid rendering.
- Reduced dependence on filename-based presentation.
- Continued separation of presentation from magazine metadata.

## User Experience

- Archive now presents as a curated digital magazine library.
- Navigation is more intuitive with direct return to the Member Portal.
- Magazine information emphasizes publication details instead of filenames.
- Archive browsing is cleaner and easier to scan.
- Hover interactions provide a more tactile magazine selection experience.
- Overall presentation reinforces the premium feel of the COAI member experience.
==================================================
## RP27.x
==================================================

• Fixed Family Members Add form submitting incorrect actions.
• Corrected Add/Update/Delete form separation.
• Prevented duplicate Family Member records from being created.
• Improved Family Member form stability and validation.

===================================================
### SOF v4.2.2 – Distribution Framework Completion
===================================================

#### Added

* Introduced SOF Configuration Layer for centralized framework configuration.
* Added centralized COAI Region configuration file.
* Added reusable SOF Region Selector Component.
* Added COAI-specific region selection helper functions to prevent naming conflicts with legacy address-region logic.
* Established standard implementation format requiring file scope, expected result, test procedure, and architectural impact before code changes.

#### Changed

* SOF development process now requires identifying modified PHP files before implementation.
* Region-related helper functions now use COAI-specific naming to avoid conflicts.

#### Notes

* Reporting Framework v4.3.0 foundation remains in place but active development is temporarily paused while Distribution Framework v4.2.2 is completed.


==================================================
SOF v4.2.1
Date: 2026-06-25
Type: Stabilization / Repository Baseline
==================================================

Fixed

• Corrected production PHP parse error in admin-members.php caused by stray character after Regional VP notification code.
• Restored mycoai.com website to operational status.
• Rebuilt local Git repository after object corruption was detected.
• Replaced invalid .gitignore and LICENSE directories with proper files.
• Excluded preserved corrupt Git backup folder from tracking.

Changed

• Established v4.2.1 as the clean SOF repository baseline.
• Confirmed documentation now lives under docs/.
• Confirmed Git repository health using git fsck --full.

Status

• Production stable.
• Git repository healthy.
• Ready to begin SOF v4.3.0 Reporting Framework.

==================================================
Version 4.2
Completed: 2026-06-24
==================================================

Regional Distribution Summary

Added:
• Regional Distribution Summary card displayed after successful Google Drive export.
• Export summary now includes:
    - Member record count
    - COAI Region
    - Export filename
    - Last updated timestamp
• Regional Notification Summary component added.
• Notification cards display:
    - COAI Region
    - Recipient name
    - Email address
• Google Drive action button moved into the summary card.
• Canada Region notification routing expanded to support:
    - Western Canada Region
    - Eastern Canada Region
• Communications Service now returns recipient details for UI display.
• Export success page redesigned into reusable Distribution Summary component.

Architecture

• Continued migration toward SOF View Components.
• Distribution workflow now fully separated into:
    Distribution Service
    Communications Service
    Notification Service
    Presentation Layer

## [2026-06-24] - SOF v4.2 Communications Framework Phase 1

### Added
- Added Communications Framework (`communications-service.php`).
- Added Regional VP contact lookup using `wp_coai_region_officers`.
- Added automatic member lookup through `wp_members`.
- Added `coai_comm_get_region_email_contacts()`.
- Added `coai_comm_notify_region_export()`.
- Added communication test mode constants:
  - `COAI_COMM_TEST_MODE`
  - `COAI_COMM_TEST_EMAIL`
- Added automatic notification trigger following successful Google Drive exports.

### Changed
- Refactored plugin bootstrap using loader architecture.
- Added `service-loader.php`.
- Added `core-loader.php`.
- Added `admin-loader.php`.
- Added `shortcode-loader.php`.
- Added `newsletter-loader.php`.
- Simplified `coai-members-custom.php` by replacing multiple `coai_safe_require()` calls with loader modules.

### Fixed
- Corrected Region Officer lookup to use `wp_coai_region_officers`.
- Corrected communication query to use integer flags (`1/0`) for `notify_email` and `is_active`.
- Verified successful Google Drive export followed by Communications Framework notification.

### Tested
- Google Drive export
- Regional VP lookup
- Communications Framework
- Email delivery (Test Mode)
- Bootstrap loader architecture

## SOF v4.0 - 2026-06-24

Completed

- Added Communications Framework.
- Added communications-service.php.
- Added Regional VP lookup.
- Added test mode constants.
- Added export notification integration.
- Added coai_comm_notify_region_export().
- Added coai_comm_get_region_email_contacts().
- Successfully tested email notifications.

# COAI Mobile Submenu Icon Fix - Changelog

## [1.0] - 2026-06-18

### Added
- Added mobile submenu fix for CosmosWP navigation.
- Added JavaScript click handler for mobile/tablet dropdown arrows.
- Added support for common submenu toggle selectors used by WordPress/CosmosWP.
- Added mobile-only behavior for screens 1024px and below.
- Added larger 44px mobile tap target for submenu arrows.

### Fixed
- Fixed issue where tapping dropdown arrows on phones opened the parent page instead of expanding submenu items.
- Fixed issue where submenu arrows responded only when tapped in a very small area.
- Fixed submenu display by forcing visibility, opacity, height, max-height, overflow, and pointer-events when opened.
- Fixed mobile menu usability after WordPress/CosmosWP update changed menu behavior.

### Notes
- This plugin avoids editing the CosmosWP theme directly.
- If a future WordPress or CosmosWP update changes menu markup again, this plugin should be reviewed first.


## [2026-06-16] - Member Directory COAI Region Filtering & CSV Export

### Added

* Added a new **COAI Region** filter to the Member Directory.
* COAI Region filtering is calculated dynamically from each member's **State/Country** rather than the stored `region` database field.
* Added support for the following official COAI Regions:

  * North East Region
  * North Central Region
  * North West Region
  * Mid East Region
  * Mid West Region
  * South East Region
  * South Central Region
  * South West Region
  * Canada Region
  * Latin Region
  * International Region

### Changed

* Renamed the new filter from **Address Region** to **COAI Region** to better reflect its purpose.
* Restored the existing **Normal Region** filter so administrators can continue filtering using the database `region` field.
* Updated the Member Directory filter form to include both:

  * **Normal Region** (stored database value)
  * **COAI Region** (calculated from address)
* Reordered COAI Region dropdown values to match the official COAI website ordering.

### Filtering Logic

* Normal Region continues to filter against the existing `wp_members.region` field.
* COAI Region filters members by evaluating the member's address information:

  * US members by State
  * Canadian members by Country/Province
  * Latin Region by Country
  * International Region for all remaining countries

### CSV Export

* Updated CSV export to preserve the selected COAI Region filter.
* Updated CSV filenames to use meaningful names instead of generic exports.

Examples:

* `north-east-region-YYYYMMDD-HHMMSS.csv`
* `mid-west-region-YYYYMMDD-HHMMSS.csv`
* `canada-region-YYYYMMDD-HHMMSS.csv`
* `all-members-YYYYMMDD-HHMMSS.csv`

### Internal

* Added helper function:

```
coai_md_address_region_states()
```

to centrally manage COAI Region-to-State/Country mappings.

* Updated `coai_md_build_filters()` to support:

  * `coai_region`
  * Existing `region`
  * Simultaneous filtering using both values.

* Updated `coai_md_filters_form()` to:

  * Read the new `coai_region` parameter.
  * Display the COAI Region dropdown.
  * Preserve selections during filtering and CSV export.

### Notes

This implementation intentionally does **not** modify the existing `region` field stored within `wp_members`. The COAI Region filter exists solely as a dynamic search and export tool, allowing administrators and managers to generate Regional Vice President mailing lists and reports without affecting the member record.


## [2026-05-27] — Manual Add Member: Membership Level Support

### Added

* Added Membership Level dropdown to Manual Add Member form
* Dropdown now loads values from `wp_membership_levels`
* Dropdown displays:

  * `wp_membership_levels.id`
  * `wp_membership_levels.name`

### Database Changes

* Added saving of:

  * `membership_level_id`
* Value now writes into:

  * `wp_members.membership_level_id`

### Validation

* Added required validation for Membership Level selection
* Prevents save if no Membership Level is selected

### Form Persistence

* Added `membership_level_id` to form re-display array so selected value persists after validation errors or lookup actions

### Compatibility Fix

* Replaced dynamic:

  * `$wpdb->prefix . 'membership_levels'`
* With hardcoded:

  * `wp_membership_levels`

### Reason

* Site database prefix resolves to `zweam_`
* Actual membership levels table uses:

  * `wp_membership_levels`
* Prevented SQL table-not-found errors on Manual Add Member page

### Files Modified

* `/includes/dashboard/manual-add-member.php`


## [2026-05-18] - Member Directory Status Filter Added

### Added
- Added new `Status` dropdown filter to `/member-directory/`
- Added support for filtering members by:
  - ACTIVE
  - EXPIRED
  - ARCHIVED
  - DECEASED
- Added automatic dynamic loading of distinct statuses from `wp_members.status`

### Changed
- Updated reusable `$build_filters()` query builder in `admin-portal.php`
- Added normalized status matching using:
  ```sql
  UPPER(TRIM(status))

## [2026-05-05] - Member Profile Family Members Integration

### Added

* Added family member helper functions to `profile-form.php`

  * `coai_pf_family_members_table_name()`
  * `coai_pf_get_family_members_for_member()`

* Added Family Members section to `/profile/`

  * Displays existing linked family members
  * Allows members to:

    * Edit family member information
    * Add new family members
    * Remove family members
  * Added fields:

    * First Name
    * Last Name
    * Relationship
    * Email
    * Phone
    * Birthday

### Changed

* Extended profile save handler in `profile-form.php`

  * Existing family members now update during normal profile save
  * New family members insert during normal profile save
  * Family member delete handled via checkbox during save

* Added automatic retrieval of linked family members using:

  * `primary_member_id`

### Security

* Family member updates restricted to the logged-in member’s own profile
* Family member operations protected with WordPress nonce validation
* Sanitization added for:

  * text fields
  * email fields
  * birthday/date fields

### UI

* Added dedicated “Family Members” section to member profile page
* Added styled Family Member cards matching existing MYCOAI profile layout
* Added “Add Family Member” bordered section for cleaner UX

### Notes

* `/profile/` uses `profile-form.php`
* Family member data stored in:

  * `wp_member_family_members`
* Existing `member-edit.php` Family Member tools remain intact for staff/admin workflows


## 2026-05-05 - Zeffy Family Member Import Integration

### Added
- Added Family Member import support to `coai-zeffy-importer.php`.
- Added support for:
  - Family Member 1
  - Family Member 2
  - Family Member 3
- Added normalization mappings for:
  - First Name
  - Last Name
  - Relationship
  - Email
  - Phone
  - Birthday

### Added - Staging / Ready Table
- Added Family Member fields to:
  - `import_members_staging_zeffy`
  - `import_members_ready_zeffy`
- Added normalized ready-table aliases:
  - `family1_*`
  - `family2_*`
  - `family3_*`

### Added - Import Processing
- Added new helper:
  - `coaii_insert_family_members($batch_ts)`
- Added automatic insertion into:
  - `wp_member_family_members`
- Linked imported family members using:
  - `primary_member_id`

### Updated
- Updated importer loop to process Family Member slots dynamically using:
  ```php
  for ($i = 1; $i <= 3; $i++)

## 2026-05-05 - Family Members Feature + UI Fixes

### Added
- Implemented Family Members system linked to primary member records.
- Created new table: `wp_member_family_members`.
- Added Family Members section to Admin Member Edit page.
- Added functionality to:
  - Add Family Members
  - Update Family Members
  - Delete Family Members

### Updated
- Forced Family Members table reference to `wp_member_family_members` to match existing `wp_members` structure.
- Integrated Family Members into `admin-members.php` using existing COAI form patterns.
- Standardized button styling using:
  - `.coai-btn-primary` (Add)
  - `.coai-btn-danger` (Delete)

### UI Improvements
- Fixed inconsistent button fonts and styling.
- Aligned Add and Delete buttons to match COAI design system.
- Created `.coai-family-actions` wrapper to:
  - Ensure consistent button width and height
  - Align buttons vertically with proper spacing
- Normalized button padding, font size, and alignment for consistent UX.

### Fixed
- Resolved issue where Add button text was not visible due to CSS conflicts.
- Removed duplicate `.coai-btn-danger` CSS definitions.
- Corrected layout issue where buttons appeared misaligned and different sizes.

### Notes
- Family Members are stored separately and linked via `primary_member_id`.
- No impact to existing `wp_members` structure.
- Future enhancement potential:
  - Convert Family Member → Full Member
  - Include Family Members in membership counts/renewals
  - Display on Member Portal or Membership Card

## [1.0.0] - 2026-04-19

### Added
- New MU-plugin: `/wp-content/mu-plugins/coai-menu-visibility.php`
  - Hides "Member Voting" menu item for all logged-out visitors
  - Ensures front-end navigation only shows voting to authenticated users

### Updated
- `includes/shortcodes/member-voting.php`
  - Added login requirement check at top of shortcode
  - Prevents direct URL access to `/member-voting/` for non-logged-in users

### Security
- Closed access gap where non-members could view voting page via direct URL
- Ensured voting visibility is restricted to authenticated users only

### Notes
- Menu visibility handled at MU-plugin level for global consistency
- Page-level protection enforced inside shortcode for security redundancy
- No database changes
- No impact to election tables or vote logic

SQL to find ALL members by membership_level_id

SELECT member_id, full_name, membership_level_id, membership_expiration
FROM wp_members
WHERE membership_level_id = 1
ORDER BY full_name ASC;

SQL to change all Active members in a membership_level to a new expiration_date

UPDATE wp_members
SET membership_expiration = '2050-10-26 23:59:59'
WHERE membership_level_id = 1;

### YYYY -MM -DD - CHANGE TITLE
 - Short, clear description of the primary change.
 - Additional bullet describing what was fixed, added, or changed.
 - Another concise bullet if needed.
 - Security, permission, or validation changes (if applicable).
 - UX or UI improvements (if applicable).

 - Pages / Files Updated:
   - path/to/file-one.php
   - path/to/file-two.php
   - path/to/file-three.php
   - Changed format from listing new at bottom to listing new at top

## [2026-04-16] - Fixed Results Export by Position

### Fixed
- Fixed `Export Results by Position` CSV generating incorrect output.

### Cause
- `$vote_items_table` was not defined inside the results export block.
- This caused the SQL join alias `vi` to resolve against a non-existent table.

### Updated
- Added:
  - `$vote_items_table = coai_election_table('vote_items');`
  inside the results export block.

### Result
- Results export now correctly outputs:
  - Group
  - Position
  - Candidate
  - Votes

### Files Updated
- `includes/shortcodes/staff-election-admin.php`

## [Member Status Auto-Sync] - 2026-04-16

### Added
- New MU-plugin: `coai-member-status-sync.php`
  - Automatically syncs `wp_members.status` with `membership_expiration` daily.
  - Ensures members transition from ACTIVE → EXPIRED when expiration date passes.
  - Ensures members transition from EXPIRED → ACTIVE if expiration date is updated to a future date.
  - Excludes DECEASED and ARCHIVED from automatic updates.
  - Includes optional manual trigger via:
    /wp-admin/?coai_run_member_status_sync=1

### Updated
- `admin-members.php`
  - Added automatic status synchronization during member save.
  - When a staff member edits and saves a record:
    - If `membership_expiration` < today → status is set to EXPIRED
    - If `membership_expiration` >= today → status is set to ACTIVE
    - DECEASED and ARCHIVED statuses are preserved and never overridden

### Behavior Changes
- Member status is now **fully date-driven** based on `membership_expiration`.
- Eliminates cases where expired members remain marked as ACTIVE.
- Ensures consistency across:
  - Member Directory counts
  - Member Portal behavior
  - Login handling via `coai-auth-bridge.php`

### Notes
- No grace period is applied:
  - Members are ACTIVE through their expiration date
  - Members become EXPIRED the day after expiration
- Existing login flow remains unchanged:
  - EXPIRED and ARCHIVED members can authenticate but are redirected to renewal
- Improves data integrity without modifying authentication logic

### Testing
- Verified:
  - Expired member updates to EXPIRED on save
  - Future-dated member updates to ACTIVE on save
  - Manual sync correctly updates all records via MU-plugin trigger

### [Portal] Always Show Renew Membership Button
- Removed conditional display logic for Renew Membership button in member-portal.php
- Renew button now appears for ALL logged-in members regardless of expiration status
- Improved visibility with highlighted button styling
- Retained expiration and expiring-soon alert banners for context

SQL QUERY FOR ELECTION
  RESULTS by Position
SELECT
  p.id AS position_id,
  p.group_name,
  p.position_name,
  c.id AS candidate_id,
  c.candidate_name,
  COUNT(*) AS total_votes
FROM wp_coai_election_vote_items vi
INNER JOIN wp_coai_election_votes v
  ON v.id = vi.vote_id
INNER JOIN wp_coai_election_positions p
  ON p.id = vi.position_id
INNER JOIN wp_coai_election_candidates c
  ON c.id = vi.candidate_id
WHERE v.election_id = 1
GROUP BY
  p.id,
  p.group_name,
  p.position_name,
  c.id,
  c.candidate_name
ORDER BY
  p.sort_order ASC,
  p.group_name ASC,
  p.position_name ASC,
  total_votes DESC,
  c.sort_order ASC,
  c.candidate_name ASC;

SQL for all members who have Voted
SELECT 
    m.member_id,
    m.first_name,
    m.last_name,
    m.COAI_number,
    m.email,
    v.election_id,
    v.submitted_at,
    v.submission_method
FROM wp_coai_election_votes v
JOIN wp_members m 
    ON m.member_id = v.member_id
WHERE v.election_id = 1
ORDER BY v.submitted_at DESC;

SQL for how many have Voted
SELECT 
    election_id,
    COUNT(*) AS total_voted
FROM wp_coai_election_votes
WHERE election_id = 1
GROUP BY election_id;

SQL for who has NOT Voted
SELECT 
    m.member_id,
    m.first_name,
    m.last_name,
    m.COAI_number,
    m.status,
    m.email
FROM wp_members m
LEFT JOIN wp_coai_election_votes v 
    ON v.member_id = m.member_id
    AND v.election_id = 1
WHERE v.member_id IS NULL AND status = 'Active'
ORDER BY m.last_name, m.first_name;

SQL for Full Status (Voted/Not Voted)
SELECT 
    m.member_id,
    m.first_name,
    m.last_name,
    m.COAI_number,
    m.email,
    CASE 
        WHEN v.member_id IS NOT NULL THEN 'Voted'
        ELSE 'Not Voted'
    END AS voting_status,
    v.submitted_at
FROM wp_members m
LEFT JOIN wp_coai_election_votes v 
    ON v.member_id = m.member_id
    AND v.election_id = 1
ORDER BY voting_status DESC, m.last_name;

SQL for results by candidtate
SELECT 
    p.position_name,
    c.candidate_name,
    COUNT(vi.id) AS total_votes
FROM wp_coai_election_vote_items vi
JOIN wp_coai_election_candidates c 
    ON c.id = vi.candidate_id
JOIN wp_coai_election_positions p 
    ON p.id = vi.position_id
WHERE p.election_id = 1
GROUP BY p.id, c.id
ORDER BY p.sort_order ASC, total_votes DESC;

## 2026-03-21 – Zeffy Import Archived Ignore + Possible Match Review Layer

### Fixed
- Zeffy importer no longer matches against archived member records.
- Archived members are excluded from core join logic:
  - `deleted_at IS NOT NULL` ignored
  - `status = 'ARCHIVED'` ignored
- Duplicate warning query updated to ignore archived records.

### Added
- New Dry Run review layer for likely existing members that do not match strict import rules.
- New `REVIEW` action added to preview for rows that:
  - do not match by COAI / email / username
  - but do match an active member by exact full name
  - and city/state when available
- New preview fields added:
  - Possible Match ID
  - Possible Match Email
  - Possible Match Username
  - Possible Match COAI #
- New match method:
  - `POSSIBLE_NAME`
- New result state:
  - `REVIEW REQUIRED`

### Behavior Changes
- Importer remains strict for live updates:
  - only COAI / email / username trigger automatic UPDATE
- Name-based matches are now shown for manual review instead of being silently treated as plain INSERT rows.
- Archived records remain excluded from both update matching and warning review output.

### Files Updated
- `/wp-content/plugins/coai-members-custom/includes/coai-zeffy-importer.php`
  - `coaii_join_sql()`
  - duplicate warning query
  - `coaii_get_preview_rows()`
  - Dry Run preview table rendering

## [2026-04-10] Birthday Field + Layout Update

### Summary
Added and standardized Birthday field across member edit interfaces. Updated layout for improved usability and corrected data handling for DATE field storage.

---

### Database
- Confirmed `birthday` column is stored as `DATE`
- Ensured compatibility with existing `date_of_birth` column (kept in sync for legacy support)

---

### Admin Members (admin-members.php)
- Updated save logic:
  - `birthday` now saved as `Y-m-d` (correct DATE format)
  - Removed incorrect datetime (`Y-m-d H:i:s`) handling for birthday
  - Added automatic sync:
    - `birthday` → `date_of_birth` (if column exists)

- Added Birthday field to Member Edit (GOLD editor):
  - Uses `<input type="date">`
  - Displays existing value from:
    - `birthday` (primary)
    - fallback to `date_of_birth` (legacy data)

---

### Member Edit (member-edit.php)
- Updated Birthday field:
  - Changed field name from `date_of_birth` → `birthday`
  - Displays correct formatted date (`Y-m-d`)
  - Supports fallback from `date_of_birth`

- Updated save handling:
  - Accepts both `birthday` and `date_of_birth`
  - Normalizes to `Y-m-d`
  - Keeps both fields synchronized

---

### UI / Layout Improvements
- Moved Birthday field to align with:
  - Phone
  - Mobile
- Removed empty grid spacer that caused layout break
- Fixed grid structure so all three fields display on same row

---

### Result
- Consistent Birthday handling across system
- Correct DATE storage format
- Improved admin usability and layout clarity
- Backward compatibility maintained with legacy `date_of_birth` field

---

### Files Updated
- `/includes/shortcodes/admin-members.php`
- `/includes/shortcodes/member-edit.php`

## 2026-04-06 — Phase 2 Complete: Admin Reporting, Manual Ballot Entry, Removal, Print Ballots

---

### 🗳️ Admin Voting Progress Report (NEW)
- Added full **Voting Progress Report** section to `staff-election-admin.php`
- Displays:
  - Eligible voters
  - Ballots received
  - Not yet voted
  - Percent complete
  - Last ballot timestamp
- Member list includes:
  - Name
  - COAI #
  - Email
  - Status (Voted / Not Voted)
  - Method (online / mail / email / admin-entered)
  - Voted At timestamp
- Report section collapses by default for cleaner UI
- Member list hidden by default with **Show Members / Hide Members toggle**

---

### 📤 CSV Export (NEW)
- Added export buttons:
  - Export All Eligible
  - Export Voted
  - Export Not Voted
- CSV includes:
  - Name
  - COAI #
  - Email
  - Status
  - Method
  - Voted At
- Export respects current election + filters

---

### 🧾 Submission Method Tracking (NEW)
- Added fields to `wp_coai_election_votes`:
  - `submission_method`
  - `entered_by_user_id`
  - `admin_note`
- Online votes now explicitly saved as:
  - `submission_method = 'online'`
- Manual entries support:
  - `mail`
  - `email`
  - `admin-entered`

---

### 📝 Manual Ballot Entry (NEW)
- Added full **Manual Ballot Entry form** in admin
- Allows staff to:
  - Select election
  - Enter Member ID
  - Choose submission method
  - Select candidates per position
- Saves:
  - vote header (`wp_coai_election_votes`)
  - vote selections (`vote_items`)
- Uses DB transaction for integrity (START TRANSACTION / COMMIT / ROLLBACK)
- Validates:
  - member eligibility
  - valid candidate selections
  - duplicate voting prevention

---

### ❌ Remove Ballot (NEW)
- Added **Remove Ballot correction tool**
- Deletes:
  - vote_items rows
  - vote header row
- Returns member to **Not Voted**
- Includes confirmation prompt
- Prevents orphaned or partial vote data

---

### 🖨️ Print Blank Ballot (NEW)
- Added **Print Blank Ballot feature**
- Opens print-friendly page in new tab
- Includes:
  - Election title
  - Optional voter info (Name, COAI #, Member ID)
  - All voteable positions and candidates
- Clean print layout with:
  - selection circles
  - grouped positions
  - return instructions
- Supports:
  - generic blank ballot
  - member-specific ballot

---

### 🧹 Removed Legacy Manual Ballot Received (CLEANUP)
- Removed:
  - Manual Ballot Received form
  - `coai_mark_ballot_received` handler
- Reason:
  - Prevent partial vote records (header without selections)
  - Enforce single correct workflow via Manual Ballot Entry

---

### 🛠️ Data Integrity Improvements
- Unified vote system:
  - All ballots (online + manual) stored consistently
- Eliminated risk of:
  - incomplete ballots
  - duplicate vote headers
- Ensured:
  - report accuracy
  - export consistency
  - proper audit capability

---

### 🔐 Privacy Protection Maintained
- Admin report shows:
  - participation only (who voted)
  - NOT individual vote selections
- Candidate selections remain isolated in `vote_items`
- Supports election integrity requirements

---

### 📁 Files Updated
- `/wp-content/plugins/coai-members-custom/includes/shortcodes/staff-election-admin.php`
- `/wp-content/plugins/coai-members-custom/includes/shortcodes/member-voting.php`

---

### 🗄️ Database Updates
- Table: `wp_coai_election_votes`
  - Added:
    - `submission_method`
    - `entered_by_user_id`
    - `admin_note`

---

### 🚀 System Status After This Release
✔ Online voting  
✔ Manual ballot entry (mail/email/admin)  
✔ Participation tracking  
✔ Submission method tracking  
✔ Admin reporting dashboard  
✔ CSV export  
✔ Ballot removal / correction  
✔ Printable ballots  
✔ Data integrity safeguards  

---

### 🔜 Recommended Next Phase
- Admin Audit Trail:
  - who entered manual ballots
  - who removed ballots
  - timestamps and change history

- Optional:
  - Print Saved Ballot (internal audit copy)
  - Member lookup dropdown instead of manual ID entry

---

## v2026-04-01 – ELECTION ADMIN: Candidate Removal Controls Added

### Summary
Added admin capability to remove or delete candidates directly from the Election Admin interface without requiring database access. This improves usability for non-developer Admins and preserves election integrity.

---

### New Features

#### Candidate Remove / Delete Button
- Added **"Remove Candidate"** button to each candidate in:
  - Staff Election Admin → Edit Existing Candidates
- Button includes confirmation prompt before execution

---

### Backend Logic (Safe Removal Handling)

#### Vote-Aware Removal Logic
- When **Remove Candidate** is clicked:
  - If candidate **HAS votes**:
    - Candidate is **soft-removed** (`is_active = 0`)
    - Vote history is preserved
  - If candidate **HAS NO votes**:
    - Candidate is **permanently deleted** from database

---

### Security

- Added nonce verification:
  - `coai_remove_candidate`
- Capability check enforced via existing:
  - `coai_user_can_manage_elections()`

---

### Database Behavior

- Uses existing `is_active` column:
  - `1 = active (visible on ballot)`
  - `0 = removed (hidden from ballot)`
- No schema changes required

---

### UI Improvements

- Admins no longer need database access to:
  - Remove duplicate candidates
  - Remove withdrawn candidates
- Clear separation of actions:
  - **Update Candidate**
  - **Remove Candidate**

---

### Files Updated

- `/wp-content/plugins/coai-members-custom/includes/shortcodes/staff-election-admin.php`

---

### Notes

- Existing functionality retained:
  - Admins can still manually toggle **Active checkbox** for quick removal
- Front-end ballot must filter:
  - `WHERE is_active = 1`
- Recommended (already implemented):
  - Unique vote constraint on `(election_id, member_id)` to prevent duplicate voting

---

### Result

- Non-technical Admins can fully manage candidate lists
- Election integrity preserved (no accidental vote loss)
- Eliminates need for direct database edits for candidate removal

## [2026-03-27] — Zeffy Importer Renewal Date + Expiration Logic Fix

### Summary
Fixed Zeffy importer so that `renewal_date` is properly stored and `membership_expiration` is calculated correctly based on a 1-year membership period. Removed reliance on Zeffy’s provided expiration date.

---

### Problem
- `renewal_date` was NOT being populated at all
- `membership_expiration` was using Zeffy’s `Expiration Date` field (inconsistent / incorrect)
- System requirement: expiration must always be **1 year minus 1 day from renewal payment date**

---

### Solution
- Introduced `renewal_date` derived from Zeffy **Payment Date**
- Replaced Zeffy expiration usage with calculated expiration logic:
  - `expiration = renewal_date + 1 year - 1 day`
- Updated importer to consistently use this rule for both INSERT and UPDATE operations

---

### Changes — zeffy-importer.php

#### 1) Normalize Step (coaii_normalize_into_ready)
- Added:
  - `renewal_date` from Zeffy Payment Date
- Replaced:
  - `membership_expiration` now calculated using SQL:
    - `DATE_ADD(..., INTERVAL 1 YEAR)`
    - `DATE_SUB(..., INTERVAL 1 DAY)`
- Removed dependency on Zeffy `Expiration Date`

---

#### 2) Update Existing Members (coaii_do_upsert)
- Replaced:
  - `registered_date` → `renewal_date`
- Now updates:
  - `m.renewal_date = COALESCE(z.renewal_date, m.renewal_date)`
- Ensures expiration updates only when calculated value exists

---

#### 3) Insert New Members (coaii_do_upsert)
- Updated column list:
  - Added `renewal_date`
  - Removed use of `registered_date`
- Updated SELECT mapping:
  - `z.renewal_date` now inserted into `wp_members`

---

#### 4) (Optional UX Enhancement) Dry Run Preview
- Added `renewal_date` column to preview table
- Allows verification before import execution

---

### Result
- Renewal payment date from Zeffy is now:
  - Stored in `renewal_date`
- Membership expiration is now:
  - Always exactly **1 year minus 1 day from renewal**
- Eliminates inconsistent or missing expiration values
- Aligns system with COAI membership rules

---

### Data Integrity Impact
- Prevents incorrect expirations caused by:
  - Missing Zeffy expiration values
  - User-entered incorrect dates
- Standardizes all renewals to a consistent lifecycle

---

### Files Updated
- `/wp-content/plugins/coai-zeffy-importer/zeffy-importer.php`

---

### Notes
- Requires `renewal_date` column to exist in `wp_members`
- No changes required to member-edit page (data layer fix only)
- Safe for existing members — only updates when new import runs

---

### Version Tag
v2026-03-27-ZEFFY-RENEWAL-DATE-FIX

## [2026-03-23] Phase 1.8 — Membership Card Email: Front + Back + Full Card Image

### Summary
Expanded the COAI Membership Card email feature to include:
- Full membership card (live view)
- Front print card
- Back print card

Members now receive all formats needed for:
- Mobile storage (full card)
- Printing (front/back)

---

### What Changed

#### JavaScript (member-card.php)
- Updated email button workflow to capture THREE card versions:
  - Visible full card (`.coai-member-card`)
  - Hidden print front (`#coai-email-card-front`)
  - Hidden print back (`#coai-email-card-back`)
- Added image preload handling to ensure logos and QR codes render before capture
- Ensured html2canvas captures off-screen elements using:
  - `opacity: 0` (instead of `visibility: hidden`)
- Expanded FormData payload:
  - `full_image`
  - `front_image`
  - `back_image`
  - `image_data` (backward compatibility)

---

#### PHP AJAX Handler (member-card.php)
- Extended `coai_email_card_image_ajax()` to support:
  - Full card image
  - Front card image
  - Back card image
- Added validation and decoding for all three image inputs
- Generates three attachment files:
  - `coai-membership-card-full-XXXXX.png`
  - `coai-membership-card-front-XXXXX.png`
  - `coai-membership-card-back-XXXXX.png`
- Improved cleanup:
  - Deletes all temp files after email send
- Updated email body to clearly explain included attachments
- Preserved backward compatibility with existing `image_data`

---

#### Shortcode Output (member-card.php)
- Added hidden email capture container:
  - Uses print card layout for consistency
  - Positioned off-screen (`left:-99999px`)
  - Uses `opacity:0` for html2canvas compatibility
- Added required print styles:
  - `echo coai_member_card_print_styles();`
- Ensures accurate rendering of front/back email images

---

### Files Updated
- `/wp-content/plugins/coai-members-custom/includes/shortcodes/member-card.php`

---

### Result
Email now includes:
- ✅ Full membership card image (for phone/tablet use)
- ✅ Front card image (print)
- ✅ Back card image (print)

---

### Notes
- Chose PNG attachment approach over PDF for:
  - Lower production risk
  - No additional libraries
  - Faster performance
- Reused existing print card layout for consistency across:
  - Print page
  - Email attachments
- Maintained full backward compatibility with Phase 1.7

---

### Next Phase Ideas (Optional)
- Phase 1.9:
  - PDF generation (single combined card)
  - ZIP download (front/back bundle)
  - Apple Wallet / Google Wallet pass

## [v2026-03-23 – MEMBERSHIP CARD EMAIL (Phase 1.7)]

### Summary
Added ability for logged-in members to email their Membership Card directly from the Member Card page. The card is captured as an image and sent as an email attachment for easy saving to mobile devices.

---

### New Features

- ✉️ **Email My Membership Card Button**
  - Added button to Member Card page action bar
  - Allows members to email their card to themselves
  - Positioned alongside Print and Download buttons

- 🖼️ **Card Image Capture (html2canvas)**
  - Captures visible Membership Card as PNG image
  - Uses high-resolution rendering (scale: 2)
  - Supports logo + QR code rendering

- 📧 **Email with Attachment**
  - Sends Membership Card as PNG attachment
  - Automatically uses:
    - `wp_members.email` (primary)
    - fallback to WordPress user email
  - Includes simple message with instructions for saving

- 🔐 **Secure AJAX Processing**
  - Uses WordPress AJAX (`admin-ajax.php`)
  - Nonce validation for security
  - Logged-in user enforcement

---

### UX Improvements

- Button shows **"Sending..."** while processing
- Prevents duplicate clicks during send
- Displays success or error alert message
- Clean integration with existing card UI

---

### Files Updated

**/wp-content/plugins/coai-members-custom/includes/shortcodes/member-card.php**

- Added:
  - `coai_email_card_image_ajax()` (AJAX handler)
  - AJAX action hooks:
    - `wp_ajax_coai_email_card_image`
    - `wp_ajax_nopriv_coai_email_card_image`

- Updated:
  - `.coai-card-actions` UI block to include email button
  - JavaScript block:
    - Added html2canvas integration
    - Added email capture + AJAX send logic

---

### Technical Notes

- Card image generated client-side (browser)
- Image temporarily written to `/uploads/`
- File deleted after email is sent
- Uses `wp_mail()` for delivery
- No external API required

---

### Known Limitations

- Sends **front card only** (current Phase 1.7)
- Not integrated with Apple Wallet / Google Wallet
- Email includes PNG (not PDF)

---

### Next Phase Options (Future)

- 📄 Email as PDF (front/back layout)
- 🪪 Dual attachment (front + back images)
- 📱 Apple Wallet / Google Wallet integration
- 📲 SMS/Text delivery (via Twilio or similar)

---

### Status

✅ Fully functional  
✅ Tested from Member Card page  
✅ Email delivery confirmed  

## [2026-03-22] - MEMBER CARD FEATURE (PHASE 1 FINAL DESIGN POLISH)

### 🎨 Final Membership Card Design Polish

Completed visual refinement of the Phase 1 COAI Membership Card for mobile display, print presentation, and branded member use.

---

### ✅ Design Updates Completed

- Updated card header from plain red to themed circus-style image background
- Improved header text readability:
  - Increased size and weight of "Clowns of America International"
  - Restyled "Official Membership Card" for visibility over themed image
  - Removed dark boxed background styling from header text
  - Replaced boxed text treatment with text-shadow styling for cleaner presentation

- Added COAI logo to right-side verification column above QR code
- Repositioned COAI logo out of header area to avoid layout crowding
- Enlarged and refined QR / logo presentation for better balance

- Added member title line under Full Name:
  - "Ambassador of Joy"

- Added faint COAI logo watermark behind card body
  - Increased watermark size
  - Adjusted watermark vertical position lower in the card for improved composition

- Added soft gold border/glow effect around card
  - Tuned color from overly warm/red tone to more neutral gold
  - Finalized premium credential-style border appearance

---

### 🛠️ CSS / Layout Fixes

- Corrected header text styling issues caused by invalid CSS property typos
- Moved `.coai-member-tagline` screen styling outside print-only CSS block
- Fixed invalid COAI number text color declaration
- Corrected QR area flex styling so container, not image, controls layout
- Refined watermark layering using `::before` with z-index-safe content stacking
- Adjusted watermark background size and position for better visibility without interfering with field readability

---

### 📱 Final Card Design Characteristics

The final Phase 1 Membership Card now includes:

- Circus-themed branded header image
- COAI gold-trimmed card border
- Large organization title and subtitle in readable overlay styling
- COAI #, Full Name, Clown Name, Expiration Date, and Status
- "Ambassador of Joy" title under member name
- COAI logo + QR verification area
- Faint centered COAI watermark in card body
- Print-friendly and mobile-friendly layout

---

### 🛠️ Files Updated

- `/wp-content/plugins/coai-members-custom/includes/shortcodes/member-card.php`

---

### 📌 Notes

- Final design approved based on live visual review
- Expiration Date field still displays "Not Available" where source data is not yet mapped or populated
- Print testing recommended after final CSS freeze

---

## [2026-03-22] - MEMBER CARD FEATURE (PHASE 1)

### 🎟️ New Feature: COAI Membership Card (Phase 1)

Introduced a new Membership Card system for COAI members, accessible via the Member Portal. This allows members to view, save, and print their official membership card and enables QR-based verification.

---

### ✅ Features Added

- New Member Card page:
  - `/member-card/`
  - Shortcode: `[coai_member_card]`

- New Verification page:
  - `/member-card-verify/`
  - Shortcode: `[coai_member_card_verify]`

- Member Card displays:
  - COAI #
  - Full Name
  - Clown Name
  - Expiration Date
  - Membership Status (Active / Expired)
  - QR Code (links to verification page)

- QR Code functionality:
  - Points to `/member-card-verify/?coai=XXXX`
  - Public-facing verification page
  - Displays limited member information for validation

- Member Portal integration:
  - Added "🎟️ My Membership Card" button linking to `/member-card/`

---

### 🎨 UI / UX Enhancements

- Redesigned card layout:
  - Left column: member details
  - Right column: COAI logo + QR code
  - Balanced layout for mobile and desktop

- Header styling:
  - Updated to red gradient for COAI branding

- Logo placement:
  - Moved COAI logo from header to right-side QR section
  - Allows larger display without breaking layout

- Print optimization:
  - Card formats to wallet size (3.5in x 2in)
  - Hides buttons and extra UI when printing

---

### 🔧 Technical Fixes

- Corrected database table reference:
  - Changed from `$wpdb->prefix . 'members'` to `wp_members`

- Corrected primary key usage:
  - Replaced `id` with `member_id` in all queries

- Fixed COAI number field mapping:
  - Supports multiple field variations:
    - `COAI_number`
    - `coai_number`

- Improved member lookup logic:
  - Uses `coai_member_id` from `wp_usermeta`
  - Added debug logging for tracing lookup flow

---

### 🛠️ Files Added

- `/wp-content/plugins/coai-members-custom/includes/shortcodes/member-card.php`

---

### 🛠️ Files Updated

- `/wp-content/plugins/coai-members-custom/coai-members-custom.php`
  - Added conditional loader for `member-card.php`

- `/wp-content/plugins/coai-members-custom/includes/shortcodes/member-portal.php`
  - Added "My Membership Card" button

---

### ⚠️ Notes

- QR code currently uses external service:
  - `api.qrserver.com`
  - Future enhancement: self-hosted QR generation

- Verification page is public but not linked in navigation

---

### 🚀 Future Enhancements (Planned)

- PDF download version of membership card
- Self-hosted QR code generation
- Optional member photo on card
- Apple Wallet / Google Wallet integration (Phase 3)
- Staff-only enhanced verification view

---

## 2026-03-21 – Zeffy Import COAI # Handling + Dry Run UX Upgrade

### Fixed
- Resolved issue where new members imported from Zeffy were assigned `COAI_number = 'NA'` instead of a valid generated number.
- Root cause:
  - Zeffy-ready table passed through `N/A` values as non-empty strings.
  - Import insert logic wrote `N/A` directly into `wp_members.COAI_number`.
  - Batch assigner skipped those rows because it only targeted NULL/blank values.
- Updated COAI assignment logic to treat `'N/A'` and `'NA'` as empty values:
  - Applies to both SELECT (candidate rows) and UPDATE (assignment guard).
- Verified that COAI numbers are now correctly generated for new members after import.

---

### Updated
- Improved normalization handling for Zeffy `COAI_number` field:
  - `'N/A'`, `'NA'`, `'NONE'`, and `'NULL'` are now treated as empty values (NULL equivalent).
- Dry Run preview logic enhanced to reflect real system behavior:
  - Added distinction between:
    - Current COAI # (from `wp_members`)
    - Incoming COAI # (from Zeffy/ready table)
    - Result COAI # (post-import expected value)
- Matching visibility improved with `Match By` column:
  - Values include: `COAI`, `EMAIL`, `USERNAME`, `NONE`
- Action clarity improved:
  - `UPDATE` rows show existing member COAI #
  - `INSERT` rows show `AUTO-GENERATE` when COAI # will be assigned after import

---

### Added
- New Dry Run preview columns:
  - Current COAI #
  - Incoming Zeffy COAI #
  - Result COAI #
  - Match Method
  - Member ID
- Result COAI # logic:
  - UPDATE → retain existing COAI #
  - INSERT + no Zeffy COAI → `AUTO-GENERATE`
  - INSERT + Zeffy COAI present → use Zeffy value
- Visual emphasis for `AUTO-GENERATE` rows in preview

---

### Behavior Changes
- Zeffy COAI # field is now treated as optional and informational:
  - No longer required for matching or assignment
  - System-generated COAI numbers are the primary source of truth
- Dry Run preview no longer misleading:
  - Previously displayed only Zeffy COAI #
  - Now reflects actual post-import state

---

### Notes
- COAI number generation still occurs only after live insert via:
  - `coaii_assign_coai_numbers_for_batch()`
- Dry Run does not compute exact future sequence numbers:
  - Displays `AUTO-GENERATE` instead
- Matching logic remains strict:
  - COAI # match (if present)
  - else email / username
  - no name-based matching in importer (intentional for data integrity)

---

### Files Updated
- `/wp-content/plugins/coai-members-custom/includes/coai-zeffy-importer.php`
  - `coaii_assign_coai_numbers_for_batch()` updated to include `N/A` handling
  - `coaii_normalize_into_ready()` updated to normalize invalid COAI values
  - `coaii_get_preview_rows()` enhanced for multi-column COAI preview
  - Dry Run preview table rendering updated for clarity

---

### Future Enhancements (Planned)
- Optional calculation of next COAI number in Dry Run preview
- Add "Possible Match" detection layer (name / clown name fallback)
- Add manual match override UI for import review
- Add audit logging for COAI assignment events

## [2026-03-20] Election Admin Photo Picker + Upload Fix + UX Improvements

### Summary
Resolved media picker issues on the Election Admin page, including incorrect target assignment, failed uploads from front-end login, and added UX improvements for clearer workflow and navigation.

---

### Fixes

#### 1. Media Picker Targeting Issue
- Fixed issue where selecting a photo for an existing candidate populated the Add Candidate section instead.
- Root cause: duplicate DOM IDs across candidate rows.
- Solution:
  - Added unique IDs per candidate row using candidate ID suffixes.
  - Updated `data-target` and `data-preview` attributes accordingly.

---

#### 2. JavaScript Media Frame Behavior
- Replaced shared `frame` variable with per-click media frame instance.
- Ensures correct binding of target input and preview container.

```js
const frame = wp.media({...});

## [2026-03-19] - Candidate Photo Picker Restricted to Media Library Selection

### Updated
- Adjusted Election Admin candidate photo workflow to use existing WordPress Media Library items.

### Reason
- Front-end uploads from Election Admin redirect to `wp-login.php` via `/wp-admin/async-upload.php`, indicating upload endpoint auth mismatch under the custom front-end login flow.

### Behavior
- Candidate photos should be uploaded in WordPress Media first.
- Election Admin then selects the image from Media Library.

### Files Updated
- `includes/shortcodes/staff-election-admin.php`

## [2026-03-16] - Election Admin Collapsible Layout Upgrade

### Updated
- Updated `includes/shortcodes/staff-election-admin.php` to use collapsible `<details>` sections for cleaner admin layout.

### Added
- Added collapsible sections for:
  - Create Election
  - Add Position
  - Add Candidate
  - Existing Elections
  - Edit Existing Positions
  - Edit Existing Candidates

### Improved
- Reduced visual clutter on Election Admin page.
- Edit sections remain collapsed until needed.
- Add sections stay open by default for quicker workflow.
- Retained media picker support for candidate photos.
- Retained edit support for existing positions and candidates.

### Files Updated
- `includes/shortcodes/staff-election-admin.php`

## [2026-03-16] - Election Admin Upgraded with Edit Support and Photo Picker

### Updated
- Upgraded `includes/shortcodes/staff-election-admin.php` with edit support for existing positions and candidates.

### Added
- Added position update form for:
  - group name
  - position name
  - max selections
  - sort order
- Added candidate update form for:
  - position assignment
  - candidate name
  - candidate member ID
  - photo
  - bio
  - sort order
  - active/inactive toggle
- Added WordPress Media Library image picker for candidate photos.

### Improved
- Candidate photo field now supports media picker instead of requiring manual URL entry.
- Existing positions and candidates can now be updated from Election Admin without phpMyAdmin edits.

### Files Updated
- `includes/shortcodes/staff-election-admin.php`

## [2026-03-19] Member Directory – Status Pills Enhancement

### Summary
Replaced single member count pill with multi-status pill system showing Active, Expired, and Archived totals.

### Changes
- Removed single "X members" pill from Member Directory toolbar
- Added three status pills:
  - Active (filtered count)
  - Expired (filtered count)
  - Archived (global count)

### Logic Updates
- Active and Expired counts now respect current filters/search
- Archived count uses global query to avoid exclusion from default WHERE clause
- Archived status determined via UPPER(status) = 'ARCHIVED'

### Files Updated
- /wp-content/plugins/coai-members-custom/includes/shortcodes/admin-members.php

### Code Additions
- Added SQL count queries for Active, Expired, and Archived
- Replaced single pill with .coai-pill-group structure
- Added new CSS classes:
  - .coai-pill-group
  - .coai-pill-active
  - .coai-pill-expired
  - .coai-pill-archived

### UI Improvements
- Improved visibility of membership distribution
- Better alignment with existing CosmosWP UI
- Mobile-friendly pill layout

### Notes
- Archived count intentionally global to avoid conflict with default directory filters
- Future enhancement: clickable pills for quick filtering

## [2026-03-16] - Group Headings Styled as Uppercase

### Updated
- Updated ballot group headings to display in uppercase.

### Methods
- Applied `text-transform: uppercase` styling to group titles.

### Result
- Improved visual consistency and readability of ballot sections.

### Files Updated
- `includes/shortcodes/member-voting.php`
- `includes/shortcodes/staff-election-results.php`

## [2026-03-16] - Ballot Grouping Added to Member Voting Page

### Updated
- Updated `includes/shortcodes/member-voting.php` to render ballot positions by `group_name`.

### Added
- Added grouped ballot rendering for:
  - Executive Committee
  - Directors
  - Regional Vice Presidents
  - Appointees
- Added group-level note support for:
  - Regional Vice Presidents
  - Appointees

### Kept
- Kept per-position note for Regional Vice President offices:
  - `Please vote only for the Regional Vice President in your own region.`

### Behavior
- Positions are grouped by `group_name` from `wp_coai_election_positions`.
- Positions with no `group_name` fall into `General`.

### Files Updated
- `includes/shortcodes/member-voting.php`

# CHANGELOG.md

## [2026-03-17] Password Reset + Auth Bridge Stabilization + Duplicate Link Cleanup

### Summary

Resolved critical issues preventing members from logging in after password reset. Root cause identified as duplicate `coai_member_id` linkages in WordPress usermeta combined with incomplete reset password handling. Implemented fixes to reset flow, improved authentication handling, and added hardening to prevent future login failures.

---

## 🔧 Password Reset Fixes

### Fixed Critical Error on Reset Email

* Resolved fatal error in `reset-password.php` (line 216)
* Corrected variable usage in token generation (`$plain` → `$token_plain`)

### Standardized Password Hashing

* Ensured reset-password flow uses `wp_hash_password()` (same as auth bridge)
* Guarantees compatibility with `wp_check_password()` in login process

### Updated Functions

#### `coai_member_set_password()`

* Now:

  * Uses WordPress hashing
  * Clears reset token after use
  * Resets `force_password_change`
  * Updates `updated_at` timestamp
  * Adds debug logging

#### `coai_member_set_reset_token()`

* Fixed incorrect variable usage
* Uses `password_hash()` for token storage

#### `coai_member_verify_reset_token()`

* Ensures proper UTC expiration handling
* Uses `password_verify()` consistently

---

## 🔐 Authentication Fixes

### Root Cause Identified

* Multiple WP users linked to same `coai_member_id`
* Auth bridge intentionally blocked login for safety
* WordPress surfaced error incorrectly as "wrong password"

### Resolution

* Cleaned duplicate entries in `zweam_usermeta`
* Ensured only ONE WP user is linked per `coai_member_id`

---

## 🛡️ Auth Bridge Hardening

### Updated Duplicate Handling Logic

#### Improvements:

* Logs ALL conflicting WP user IDs
* Attempts safe auto-resolution before failing:

  1. Prefer deterministic login: `coai_m_{member_id}`
  2. Fallback to exact WP username match
  3. Fail only if still ambiguous

### Updated Code Block

* Replaced strict failure logic with intelligent resolution
* Increased query limit from 2 → 10 for better detection
* Added detailed debug logging

---

## 🧹 Data Integrity Cleanup

### Identified Issues

* Admin/test accounts incorrectly linked to members via `coai_member_id`
* Caused login blocking for valid members

### Actions Taken

* Removed invalid `coai_member_id` links from:

  * Admin accounts
  * Test accounts
* Preserved correct member-linked shadow accounts

### Rule Established

* ✅ Member login account → keep `coai_member_id`
* ❌ Admin/test account → remove `coai_member_id`

---

## 📊 New Admin SQL Tools

### Find Duplicate Member Links

```sql
SELECT 
  meta_value AS coai_member_id,
  COUNT(*) AS total_links
FROM zweam_usermeta
WHERE meta_key = 'coai_member_id'
GROUP BY meta_value
HAVING COUNT(*) > 1;
```

### Inspect Duplicate Users

```sql
SELECT 
  u.ID,
  u.user_login,
  u.user_email,
  um.umeta_id,
  um.meta_value
FROM zweam_users u
JOIN zweam_usermeta um ON um.user_id = u.ID
WHERE um.meta_key = 'coai_member_id'
  AND um.meta_value = 'X';
```

### Remove Bad Link (Safe)

```sql
DELETE FROM zweam_usermeta
WHERE umeta_id = X;
```

---

## 🧪 Debugging Enhancements

### Added Logging

* Reset password success logs:

  ```
  [COAI RESET] set_password mid=XXX ok=YES
  ```

* Login form logs:

  ```
  [COAI LOGIN FORM] submit detected
  ```

* Auth bridge duplicate detection:

  ```
  AUTH-BRIDGE: multiple WP users have coai_member_id=XXX ids=...
  ```

---

## ✅ Final Result

* Password reset flow fully operational
* Members can successfully log in after reset
* Duplicate linkage no longer blocks login
* System now resilient to future duplicate WP user scenarios

---

## 📁 Files Updated

* `/includes/shortcodes/reset-password.php`
* `/includes/shortcodes/login-form.php`
* `/includes/shortcodes/change-password.php`
* `/mu-plugins/coai-auth-bridge.php`

---

## 🚀 Recommended Next Steps

* Run duplicate scan periodically
* Consider admin UI tool for detecting/fixing duplicate links
* Optionally enforce single-link constraint at DB or application level

---

## [1.0.1] - 2025-11-20
### Fixed
- Resolved an issue where the password fields on the **Change Password** page did not appear because of an overly-aggressive CSS rule:
  - Removed `.coai-pwwrap` from a `display: none !important` selector so the password input wrappers are no longer hidden.

### Added
- Implemented a structured **Change Password** form renderer (`coai_render_change_password_form()`), including:
  - `Current Password` (`coai_current_password`)
  - `New Password` (`coai_new_password`)
  - `Confirm Password` (`coai_confirm_password`)
  - Helper text indicating the required password length (8–12 characters).
  - WordPress nonce field for security: `wp_nonce_field( 'coai_change_password', 'coai_change_password_nonce' )`.

- Added UI enhancements:
  - **Show / Hide password** toggle buttons (`.coai-pwbtn`) for each password field using a `data-target` attribute to link buttons to their respective inputs.
  - A **password match indicator**:
    - Green `✓` and “Passwords match.” when new and confirm passwords match.
    - Red `✕` and “Passwords do not match.” when they differ.

### Changed
- Updated inline CSS for the Change Password form:
  - Styled `.coai-pwwrap` as a flex row for cleaner alignment of label, input, and button.
  - Standardized input appearance (borders, padding, width).
  - Styled `.coai-pwmatch-icon` for visual feedback (green/red states).
  - Styled the primary submit button as a blue action button (`.coai-btn`).

### Notes
- Copy/paste in password fields is **explicitly allowed**; no JS is used to block clipboard actions.
- Shortcode `[coai_member_change_password_form]` is wired to use the new renderer function.

## [2025-12-01] – Zeffy Import Enhancements & Renewal Fixes

### Added
- Support for automatic CSV/Excel header normalization and mapping.
- Auto-detection of CSV delimiter (comma vs tab).
- Support for Zeffy renewal files with varying column order and header names.
- Automatic renewal handling: set member status to `ACTIVE` when imported expiration date is in the future.
- Forced update logic for important fields during renewals.

### Updated
- Upsert matching now prioritizes `email`, falling back to `COAI_number` only when available.
- Improved payment mode translation: now converts `Card` → `CC`, `Check` → `Check`, others as `Cash`.
- Improved mapping of:
  - `Total Amount` → `payment_amount`
  - `Expiration Date` → `membership_expiration`
- Improved IMPORT Dry-Run and actual commit reporting.

### Fixed
- Issue where renewal imports showed `UPDATE 0` even when matching member existed.
- Duplicate column conflict (`COAI_number` appearing twice) causing SQL failure.
- Collation mismatch errors on JOIN during upsert.
- Critical fatal error caused by unmatched `}` and malformed function block boundaries.
- Wrong or missing mapping causing `payment_amount`, `payment_mode`, and `membership_expiration` not to update.

### Notes
- File renaming is no longer required — plugin automatically sanitizes filenames on upload.
- Staging table rebuilds are now clean and conflict-free.
- 
### 2025-12-02 — Zeffy Import Enhancements & Fixes
- Improved CSV/XLSX header normalization and automatic column handling.
- Added support for both `COAI_number` and `COAI_Number` column variants.
- Added auto-generation of missing usernames: default to email address, fallback to random UUID if email missing.
- Added auto-assignment of `usergroup = 'Member'` for all newly inserted member records.
- Updated upsert join logic to match primarily on email; use COAI number only when email is empty.
- Updated import to properly override payment fields (`payment_amount`, `payment_mode`) and expiration fields (`membership_expiration`) when new values exist.
- Added EXPIRED → ACTIVE status flip when renewal expiration is in the future.
- Implemented Dry-run Preview UI showing planned INSERT/UPDATE actions.
- Added email notifications: Admin summary and automatic member welcome/renewal emails.
- Added audit log tracking via `import_members_runs` table.
- Improved delimiter handling for CSV files including `sep=` Excel header line.
- Enabled support for importing files with unusual characters by auto-sanitizing headers.
- Fixed staging table recreation preventing leftover rows from previous imports.

### 2025-12-15 — Member Directory Filtering & CSV Export Updates

Updated CSV Export to respect all active Member Directory filter criteria (search, level, level name, registration date range, month range, and year).
(Files: admin-portal.php, admin-members.php)

Standardized export behavior so that:

When no filters are selected, all member records are exported.

When one or more filters are selected, only the filtered results are exported.
(Files: admin-portal.php, admin-members.php)

Aligned list view and CSV export queries to prevent mismatches between on-screen results and exported data.
(Files: admin-portal.php, admin-members.php)

Updated CSV export permission checks to allow Finance (view-only) users to export reports while keeping edit access restricted to Admin/Manager roles.
(File: admin-members.php)

Added Export CSV button to the Finance member directory view.
(File: admin-portal.php)

Ensured all Export CSV links preserve current filter selections via merged query parameters.
(File: admin-portal.php)

Improved Member Directory filter input readability by forcing entered text to display in dark/black text instead of light gray.
(Files: CSS via theme / coai-members-custom plugin)

Cleaned up and consolidated export handling logic to improve long-term maintainability.
(Files: admin-portal.php, admin-members.php)

### 2025-12-15 — Member Directory Filtering & Export Fixes

- Added **“New Members only”** filter to the Member Directory.
  - Identifies members with **no renewal / expiration date**.
  - Uses `registered_date` for date/month/year filtering.
- Updated Member Directory grid layout to include a dedicated **New Members only** filter column.
- Ensured **Export CSV** now respects **all active filters**, including:
  - Registration date range
  - Month From / Month To / Year
  - Level / Level Name
  - Status, Region, Insurance, etc.
  - New Members only
- Fixed export logic to match on-screen results exactly (no more full-table exports).
- Improved filter consistency between:
  - On-screen list
  - Pagination counts
  - CSV export
- Minor UI polish to filter alignment and layout for better readability.

## 2025-12-16 — Member Directory Filter Alignment & New Members Enhancements

### Fixed
- Resolved mismatch between Member Directory list results and CSV export counts by consolidating filter logic.
- Fixed Month From / Month To behavior so:
  - Month From only = from that month through December
  - Month To only = January through that month
  - Month ranges (including wrap-around) behave consistently.
- Eliminated legacy parameter usage (`mon_a`, `mon_b`) in favor of unified `month_from` / `month_to` inputs.
- Corrected SQL prepare syntax issue in list query that could cause critical errors.
- Ensured export links always preserve the exact active filter set.

### Changed
- Centralized all Member Directory filtering logic into a single shared builder function:
  - `coai_md_build_filters()`
  - Used by both list view and CSV export to prevent future drift.
- “New Members only” filter now consistently switches date filtering to `created_at`.
- Default 90-day window for “New Members only” is applied only when no explicit date filters are set.
- Updated export handler to rely exclusively on the shared filter builder (single source of truth).

### Added
- Conditional **Created Date** column in the Member Directory list:
  - Displays only when “New Members only” is checked.
  - Helps administrators visually confirm which records are being selected.
- Support for filtering new members by **Created Date** using:
  - Month From / Month To
  - Year
- Improved clarity and predictability of New Members filtering behavior for staff users.

### Notes
- “New Members only” now means: *filter by `created_at`*, not renewal or expiration dates.
- For historical records without `created_at`, results will only include rows where this field is populated.

## 2025-12-17 — Admin Members Edit Page Stabilization & UI Polish

### Fixed
- Resolved PHP parse error caused by mismatched closing braces in `admin-members.php`.
- Corrected function structure so `admin-members.php` safely loads via `add_action('init', require_once ...)` with:
  - No executable HTML at top level
  - No nonce calls at top level
- Restored full functionality of the **Member Edit page** (admin/manager view).
- Fixed nonce handling for member updates to prevent false “Bad nonce” errors.
- Ensured Save action reliably updates `wp_members` without redirect or reload issues.
- Prevented accidental overwrites of date fields when inputs are left blank.
- Filtered update payload to valid database columns only (prevents SQL errors).

### Improved
- Implemented **server-side Region auto-calculation** based on State/Country to mirror client-side behavior.
- Added client-side Region auto-update when State or Country changes.
- Improved resilience of membership level joins by dynamically resolving primary key (`ID` vs `id`).

### UI / UX
- Softened edit form layout with:
  - Rounded inputs
  - Subtle borders
  - Consistent spacing
- Replaced harsh black input styling with clean, modern card-style inputs.
- Added smooth focus states for all inputs and selects.
- Styled section headers (`Member`, `Address`, `Shipping`, etc.) with:
  - Softer blue color
  - Reduced visual weight
  - Subtle divider for section separation
- Updated field labels to:
  - Normal font weight (non-bold)
  - Clean black color for better readability
- Improved overall visual hierarchy to feel more like a modern CRM/admin panel.

### Verified
- Member Edit page loads without errors.
- Save action persists changes correctly.
- Region auto-updates both client-side and server-side.
- No PHP parse errors, fatal errors, or nonce warnings observed.

## 2025-12-18 — Finance Member Edit Restoration & Permissions Hardening

### Fixed
- Restored Finance user ability to open `/member-edit/?mid=####` and load the correct member record.
- Fixed Finance users being incorrectly routed into the Admin editor UI (`[coai_members_admin]`), which prevented proper member loading.
- Resolved multiple PHP syntax issues caused by mismatched braces during Finance save logic refactor.
- Corrected checkbox handling for `paid_manually` so unchecked values reliably save as `0` (previously could not be cleared).
- Fixed layout issues caused by nested grid/button markup in the Member Edit form.
- Prevented unintended overwrites of non-finance fields by enforcing a strict Finance-only save whitelist.

### Added
- Finance-only edit mode in `member-edit.php` using `mode=finance`.
- Finance-only editable fields:
  - `payment_amount`
  - `payment_mode`
  - `payment_date`
  - `check_number`
  - `manual_payment_date`
  - `membership_expiration`
  - `paid_manually` (0/1 checkbox)
- Server-side nonce protection for all Member Edit form submissions.
- Finance user notice indicating which fields are editable vs read-only.
- Readability styling for read-only fields to avoid dark/disabled appearance while preserving non-editable state.

### Changed
- Refactored `coai_render_member_edit_form()` routing logic:
  - Admin/Manager → Admin editor UI
  - Finance → Member Edit page (finance-limited)
  - Members → Self-only preview
- Updated Finance field rendering to use explicit named inputs matching `wp_members` schema.
- Improved UX consistency by visually distinguishing editable vs read-only fields without using `disabled` inputs.

### Security
- Enforced server-side field whitelisting for Finance users to prevent tampering with non-finance data.
- Ensured Finance users cannot modify personal/member profile data outside permitted finance fields.
- Maintained Admin/Manager unrestricted edit capability.

### Notes
- Legacy rewrite-based edit routes remain deprecated and unused.
- Finance edit functionality now fully independent of Admin dashboard code paths.
- Added “Back to Member Portal” navigation link on the Finance Edit Member screen for easier return to Finance tools.

### 2025 -12 -21 - Status Permission Fix
 - Restored Status dropdown visibility for Admin and Manager users.
 - Standardized Status edit permissions to coai_staff_can('manage').
 - Prevented Finance users from submitting or modifying Status values.
 - Hardened server-side validation to block unauthorized Status changes.
 - Updated Status handling to ensure consistency across edit views.
 - Pages / Files Updated:
   - includes/shortcodes/member-edit.php
   - includes/shortcodes/admin-members.php
   - includes/helpers.php

### 2025 -12 -21 - Manual Add Member (Check) Workflow
 - Added Manual Add Member workflow for Admin and Manager users to record members who pay by check.
 - Added Manual Add Member page rendered via shortcode.
 - Added Lookup Member by Email to prefill form from existing member records.
 - Required confirmation before updating an existing member to prevent accidental overwrites.
 - Added Internal Comments field for manual payment notes.
 - Automatically appended audit notes for manual check payments.
 - Added optional welcome email for newly added manual members.
 - Added Region field with automatic derivation from State.
 - Added real-time Region updates as State is entered.
 - Added Return to Member Portal navigation button.
 - Enforced business rules on save:
   - Status set to Active
   - Usergroup set to Member
   - Payment Mode set to Check
   - Membership Expiration set to one year from Registered Date
 - Restricted Manual Add Member access to Admin and Manager roles only.
 - Protected all Manual Add Member actions with nonce validation.
 - Prevented tampering with derived and enforced fields.
 - Pages / Files Updated:
   - includes/dashboard/manual-add-member.php
   - includes/shortcodes/manual-add-member.php
   - includes/dashboard/admin-tools.php
   - includes/shortcodes/member-portal.php

### 2025-12-15 — Member Directory Filtering & CSV Export Updates
- Updated CSV Export to respect all active Member Directory filter criteria (search, level, level name, registration date range, month range, and year).  
  *(Files: admin-portal.php, admin-members.php)*
- Standardized export behavior so that:
  - When no filters are selected, all member records are exported.
  - When one or more filters are selected, only the filtered results are exported.  
  *(Files: admin-portal.php, admin-members.php)*
- Aligned list view and CSV export queries to prevent mismatches between on-screen results and exported data.  
  *(Files: admin-portal.php, admin-members.php)*
- Updated CSV export permission checks to allow Finance (view-only) users to export reports while keeping edit access restricted to Admin/Manager roles.  
  *(File: admin-members.php)*
- Added Export CSV button to the Finance member directory view.  
  *(File: admin-portal.php)*
- Ensured all Export CSV links preserve current filter selections via merged query parameters.  
  *(File: admin-portal.php)*
- Improved Member Directory filter input readability by forcing entered text to display in dark/black text instead of light gray.  
  *(Files: admin-portal.php, plugin/theme CSS)*
- Cleaned up and consolidated export handling logic to improve long-term maintainability.  
  *(Files: admin-portal.php, admin-members.php)*

### 2025-12-22 — Force Password Change on First Login
- .../wp-content/plugins/coai-members-custom/includes/shortcodes/manual-add-member.php
 - Generated a temporary password for new members and stored it hashed in `wp_members.password`.
 - Set `force_password_change = 1` for newly inserted members to require a password update on first login.

- .../wp-content/plugins/coai-members-custom/coai-members-custom.php
 - Enforced `force_password_change` by redirecting logged-in users to `/change-password/` until the flag is cleared.

- .../wp-content/plugins/coai-members-custom/includes/shortcodes/change-password.php
 - Cleared `force_password_change` after a successful member password update to restore normal portal access.

### 2025-12-22 - Forced Password Change Enforcement

- Enforced mandatory password change on first login for members flagged with `force_password_change = 1`.
- Added post-login redirect to the Change Password page when a forced change is required.
- Blocked access to all front-end pages until password is successfully updated.
- Safely bypassed enforcement for admin, REST, AJAX, cron, and logout requests.
- Cleared `force_password_change` flag automatically after successful password update.
- Hardened password update logic to ensure database and WordPress user passwords stay in sync.
- Fixed update format handling to prevent failed flag resets and redirect loops.

Files updated:
- `/wp-content/plugins/coai-members-custom/coai-members-custom.php`
- `/wp-content/plugins/coai-members-custom/includes/shortcodes/change-password.php`

### 2025-12-31 - Staff Newsletter System Stabilization & Admin Redirect Debugging

- Fixed multiple site-wide 404 / lockout issues caused by PHP fatals during plugin load.
- Identified and corrected unsafe `require_once` usage that executed WP user functions at file load time.
- Introduced `coai_safe_require()` helper to prevent missing/typo’d includes from breaking frontend or wp-admin.
- Refactored staff shortcode files to ensure:
  - No WP user/session logic runs at file load time.
  - All logic executes inside shortcode callbacks only.
- Stabilized shortcode registration by ensuring files are loaded before shortcode registration hooks fire.
- Fixed shortcode rendering issues where pages displayed literal shortcode text due to late or missing registration.
- Restored proper rendering for:
  - Staff Newsletter Center (`[coai_staff_newsletters]`)
  - Staff Campaigns (`[coai_staff_campaigns]`)
  - Staff Broadcasts (`[coai_staff_broadcasts]`)
- Standardized staff-facing UX so staff pages render portal cards with buttons instead of performing redirects inside shortcodes.
- Corrected invalid admin capability checks (`current_user_can('administrator')`) to use `manage_options`.
- Verified FluentCRM Manager permissions were correctly configured and not the root cause of access issues.
- Instrumented detailed redirect and lifecycle logging to trace wp-admin redirects with full backtraces.
- Discovered wp-admin access was being forcibly redirected to `/access-denied/` during `wp_loaded`.
- Confirmed FluentCRM wp-admin access was being blocked by custom admin-guard logic rather than FluentCRM itself.
- Isolated the remaining issue to hidden wp-admin redirect logic requiring FluentCRM allowlisting for Manager users.
- Prepared plan to map all plugin, MU-plugin, and theme files to locate and consolidate admin access control.

Files touched / reviewed:
- coai-members-custom.php
- includes/helpers.php
- includes/shortcodes/staff-newsletters.php
- includes/shortcodes/staff-campaigns.php
- includes/shortcodes/staff-broadcasts.php
- MU plugins (trace / nocache instrumentation)

### 2025-12-31 - FluentCRM Redirect Root Cause + WPS Hide Login Removal

 - Identified and confirmed FluentCRM admin redirects were caused by WPS Hide Login forcing wp-admin requests to `/access-denied/`.
   - File: /wp-content/plugins/wps-hide-login.off/classes/plugin.php (wp_loaded → wp_safe_redirect)
   - File: /wp-content/mu-plugins/zz-redirect-trace.php (redirect backtrace logging)
   - File: /wp-content/mu-plugins/trace.php (request lifecycle tracing)

 - Disabled WPS Hide Login to restore authenticated Manager access to FluentCRM admin routes.
   - Plugin: wps-hide-login.off

 - Logged and verified redirect path: `/wp-admin/admin.php?page=fluentcrm-admin` → `/access-denied/` (302) and removed the blocker.
   - File: /wp-content/mu-plugins/zz-redirect-trace.php

### 2025-12-31 - FluentCRM Access Restoration & Staff Broadcasts Shortcode Fix

 - Identified and resolved FluentCRM access failures for Manager users caused by WPS Hide Login blocking authenticated `/wp-admin` requests.
   - Root cause confirmed via redirect tracing: WPS Hide Login forcing `/wp-admin/admin.php?page=fluentcrm-admin` to `/access-denied/`.
   - Files involved:
     - /wp-content/plugins/wps-hide-login.off/classes/plugin.php
     - /wp-content/mu-plugins/zz-redirect-trace.php
     - /wp-content/mu-plugins/trace.php

 - Disabled WPS Hide Login to restore authenticated Admin/Manager access to FluentCRM admin routes.
   - Plugin: wps-hide-login.off

 - Added a floating “Back to Portal” kiosk navigation button for Manager users inside wp-admin (FluentCRM) to return cleanly to the front-end Member Portal.
   - File: /wp-content/mu-plugins/coai-manager-fluentcrm-kiosk.php

 - Fixed Staff Broadcasts page rendering issue where `[coai_staff_broadcasts]` shortcode was displayed as raw text.
   - Root cause: staff-broadcasts shortcode file was conditionally loaded on `init`, causing the shortcode to be unregistered at render time.
   - Resolution: moved staff-broadcasts.php to immediate load with other staff shortcodes.
   - File: /wp-content/plugins/coai-members-custom/coai-members-custom.php
   - File: /wp-content/plugins/coai-members-custom/includes/shortcodes/staff-broadcasts.php

 - Removed deferred `init`-based include for staff-broadcasts.php to prevent duplicate or late shortcode registration.
   - File: /wp-content/plugins/coai-members-custom/coai-members-custom.php

### 2025-12-31 - Staff Newsletter System Stabilization & Admin Redirect Debugging

- Fixed multiple site-wide 404 / lockout issues caused by PHP fatals during plugin load.
- Identified and corrected unsafe `require_once` usage that executed WP user functions at file load time.
- Introduced `coai_safe_require()` helper to prevent missing/typo’d includes from breaking frontend or wp-admin.
- Refactored staff shortcode files to ensure:
  - No WP user/session logic runs at file load time.
  - All logic executes inside shortcode callbacks only.
- Stabilized shortcode registration by ensuring files are loaded before shortcode registration hooks fire.
- Fixed shortcode rendering issues where pages displayed literal shortcode text due to late or missing registration.
- Restored proper rendering for:
  - Staff Newsletter Center (`[coai_staff_newsletters]`)
  - Staff Campaigns (`[coai_staff_campaigns]`)
  - Staff Broadcasts (`[coai_staff_broadcasts]`)
- Standardized staff-facing UX so staff pages render portal cards with buttons instead of performing redirects inside shortcodes.
- Corrected invalid admin capability checks (`current_user_can('administrator')`) to use `manage_options`.
- Verified FluentCRM Manager permissions were correctly configured and not the root cause of access issues.
- Instrumented detailed redirect and lifecycle logging to trace wp-admin redirects with full backtraces.
- Discovered wp-admin access was being forcibly redirected to `/access-denied/` during `wp_loaded`.
- Confirmed FluentCRM wp-admin access was being blocked by custom admin-guard logic rather than FluentCRM itself.
- Isolated the remaining issue to hidden wp-admin redirect logic requiring FluentCRM allowlisting for Manager users.
- Prepared plan to map all plugin, MU-plugin, and theme files to locate and consolidate admin access control.

Files touched / reviewed:
- coai-members-custom.php
- includes/helpers.php
- includes/shortcodes/staff-newsletters.php
- includes/shortcodes/staff-campaigns.php
- includes/shortcodes/staff-broadcasts.php
- MU plugins (trace / nocache instrumentation)

### 2025-12-31 - Manager 2FA Enforcement, FluentCRM Access Control & Newsletter UX Refinement

 - Enforced Two-Factor Authentication (2FA) for Manager access to FluentCRM (Newsletters & Broadcasts).
   - Managers are blocked from accessing FluentCRM in wp-admin until 2FA setup is completed.
   - Member Portal remains fully accessible without forcing immediate 2FA enrollment.
   - File: /wp-content/mu-plugins/coai-manager-fluentcrm-kiosk.php

 - Hardened FluentCRM access boundary for Managers.
   - Prevents bypass via direct wp-admin URLs or repeated authentication attempts.
   - Enforcement occurs specifically when entering FluentCRM, not during normal site usage.
   - File: /wp-content/mu-plugins/coai-manager-fluentcrm-kiosk.php

 - Simplified Newsletter & Broadcast workflow to one-click access from the front end.
   - Eliminated multi-step navigation through staff-campaigns and staff-broadcasts pages.
   - Managers now launch Campaigns or Broadcasts directly into FluentCRM.
   - File: /wp-content/plugins/coai-members-custom/includes/shortcodes/staff-newsletters.php

 - Added FluentCRM route handling to support direct Campaign and Broadcast landing.
   - Front-end route parameters are translated into FluentCRM internal navigation.
   - File: /wp-content/mu-plugins/coai-manager-fluentcrm-kiosk.php

 - Updated wp-admin FluentCRM navigation for both Managers and Administrators.
   - Floating navigation buttons now appear for Admins and Managers within FluentCRM.
   - Provides quick return paths to Member Portal and Newsletter Center.
   - Improves usability without restricting full wp-admin access for Administrators.
   - File: /wp-content/mu-plugins/coai-manager-fluentcrm-kiosk.php

 - Deprecated staff-campaigns and staff-broadcasts pages as primary workflow entry points.
   - Pages may be safely repurposed to display the Newsletter Center shortcode.
   - Removed unsafe redirect logic that could interrupt WordPress bootstrap.

### 2026-01-02 - Manager FluentCRM 2FA Enforcement & Kiosk Security

 - Enforced mandatory WP-2FA setup for Manager users when accessing FluentCRM (Campaigns & Broadcasts).
 - Implemented automatic redirect to WP-2FA setup wizard (`profile.php?page=wp-2fa-setup`) when 2FA is not configured.
 - Eliminated intermediate “2FA Required” interstitial page in favor of direct setup flow.
 - Updated front-end Member Portal login to establish a full WordPress authentication session.
 - Removed redundant wp-admin login prompts prior to FluentCRM access.
 - Locked Manager users into a kiosk-style wp-admin experience, allowing only FluentCRM and Profile access.
 - Preserved full wp-admin access for Administrators.
 - Hardened login redirect logic to prevent unintended admin or portal redirect loops.

Files:
 - mu-plugins/coai-manager-fluentcrm-kiosk.php
 - mu-plugins/coai-login-redirect-guard.php
 - plugins/coai-members-custom/includes/shortcodes/login-form.php
 - plugins/coai-members-custom/includes/shortcodes/staff-newsletters.php

### 2026-01-03 — Manager 2FA + FluentCRM Access Stabilization

- Removed forced `reauth=1` login behavior from Newsletter Manager / Manager access flow to eliminate unpredictable re-login and 2FA loops.
- Stabilized Manager login flow so normal login + 2FA always lands on the Member Portal / Staff Tools instead of wp-admin or FluentCRM.
- Corrected FluentCRM access so Managers only enter FluentCRM when explicitly clicking “Open Campaigns” or “Open Broadcasts”.
- Prevented Managers from being redirected into FluentCRM immediately after login due to kiosk enforcement.
- Ensured WP 2FA challenges occur naturally (session-based) without forced re-authentication redirects.
- Verified clean behavior in both normal browser sessions and Incognito (fresh session) logins.

**Files updated:**
- `includes/shortcodes/staff-newsletters.php`
- `mu-plugins/coai-manager-fluentcrm-kiosk.php`


### 2026-01-02 — FluentCRM Kiosk & WP 2FA Enforcement Refinement

- Audited and refined FluentCRM “kiosk mode” enforcement to restrict Manager access to FluentCRM without exposing full wp-admin.
- Adjusted kiosk redirect logic to route Managers to the Member Portal instead of FluentCRM after generic admin landings.
- Confirmed WP 2FA setup enforcement only triggers when attempting to access FluentCRM routes.
- Eliminated redirect loops between wp-login, wp-admin, and WP 2FA setup screens.
- Validated correct role-based behavior for Manager vs Newsletter Manager accounts during authentication.

### 2026-01-11 — Zeffy Importer Normalization, Renewal Matching & COAI Assignment Fixes

- Resolved Zeffy importer “Normalize failed” error caused by improper use of `wpdb::prepare()` with `STR_TO_DATE()` format strings containing `%` tokens.
- Corrected normalization flow to reliably populate `import_members_ready_zeffy` from staging data.
- Refined renewal matching logic so Zeffy imports correctly UPDATE existing members when the incoming email matches either `wp_members.email` or `wp_members.username`, preventing duplicate member creation  when member emails change.
- Preserved insert-only COAI number assignment behavior, ensuring COAI numbers are generated for new members only and never overwritten during renewals.
- Fixed edge case where newly inserted members could miss COAI number assignment due to batch timestamp drift.
- Validated end-to-end Zeffy import workflow for both new memberships and renewals using dry-run and live execution.

### 2026-01-11 — Zeffy Importer Pre-Import Duplicate Detection

- Implemented pre-import duplicate detection to identify existing members before normalization and upsert processing.
- Added authoritative matching against `wp_members.email` and `wp_members.username` to correctly route renewals to UPDATE logic even when member email addresses change.
- Integrated COAI number matching as a secondary safeguard when Zeffy email data is missing or incomplete.
- Introduced non-blocking warnings for potential duplicates based on name and location similarity, allowing administrative review without forced merges.
- Added hard-stop validation to prevent duplicate email entries within the same Zeffy import file.
- Strengthened data integrity controls to eliminate duplicate member creation while preserving correct renewal behavior.

### 2026-01-11 — Manual Add Member COAI Auto-Numbering

- Updated Manual Add Member logic to use `wp_members.member_id` as the primary key for lookup, update, and internal comments retrieval.
- Ensured new manual member inserts automatically receive the next COAI Number in sequence by calling `coai_assign_coai_number_if_missing()` after successful insert.
- Kept COAI number assignment insert-only to prevent COAI numbers from being created or overwritten during renewals/updates.
- Added optional inclusion of the assigned COAI Number in the welcome email sent to newly created members.

### 2026-01-11 — COAI Number Auto-Assignment Fix & Validation

- Implemented reliable COAI number auto-assignment for **new member inserts only**.
- Added monthly COAI numbering format `YYYYMM-##` with collision-safe retry logic.
- Fixed MU-plugin table resolution to correctly target `wp_members` (instead of `$wpdb->prefix.'members'`).
- Confirmed `COAI_number` column usage and UNIQUE constraint compatibility.
- Integrated COAI assignment call into `manual-add-member.php` immediately after successful INSERT.
- Added and validated diagnostic logging to trace insert → assignment flow (removed after confirmation).
- Verified COAI numbers are now assigned correctly on manual member creation.
- Identified and isolated legacy members without COAI numbers for future backfill.
- Added SQL audits to detect duplicate, missing, and malformed COAI numbers.
- Cleaned up test/dummy member records created during validation.

### 2026-01-11 — COAI Number Persistence + Edit Save Reliability
- Confirmed COAI_number auto-generation is enforced across:
  - Zeffy importer (new members / renewals where COAI_number is blank)
  - CSV import pipelines (COAI_number assigned when missing)
  - Manual Add Member (Admin/Manager flow assigns next COAI_number)
- Hardened Admin/Manager “Member Edit” save flow to prevent silent drops and “saved but not reflected” behavior:
  - Added case-insensitive DB column filtering to only send real wp_members columns to wpdb->update().
  - Ensured COAI_number uses the real DB column casing (COAI_number) and is not lost during sanitization/filtering.
  - Added server-side guardrails for COAI_number integrity:
    - Blocks duplicate COAI_number values (unique index enforcement support).
    - Blocks bogus “xx” values unless prefixed with TEST-.
    - Skips COAI_number update entirely when blank or unchanged (prevents unnecessary writes).
  - Added pre/post update logging to validate actual payload + DB persistence (FINAL UPDATE PAYLOAD, EDIT UPDATED, POST-UPDATE SELECT).
- Resolved critical error caused by malformed block insertion (PHP parse error / unclosed brace) and restored site stability.
- File(s) updated:
  - wp-content/plugins/coai-members-custom/includes/shortcodes/admin-members.php

### 2026-01-10 — Automatic COAI Number Assignment (All Intake Paths)
- Implemented centralized COAI_number assignment logic to ensure every member record receives a valid COAI #.
- Enforced automatic COAI_number generation when missing during:
  - Zeffy membership imports (new members and renewals).
  - CSV-based member imports.
  - Manual Add Member workflow (Admin/Manager only).
- Standardized COAI_number sequencing to prevent gaps, collisions, or duplicate assignments.
- Ensured manual check payments and offline entries receive a COAI_number at creation time.
- Prevented overwriting of existing COAI_number values during renewals and updates.
- File(s) updated:
  - wp-content/plugins/coai-members-custom/includes/importers/
  - wp-content/plugins/coai-members-custom/includes/dashboard/manual-add-member.php

### 2026-01-13 – COAI Number Edit Hardening & Simplification

**Files Updated**
- `includes/shortcodes/admin-members.php`

**Changes**
- Removed the separate **“Correct COAI Number”** action/button and FIX confirmation workflow.
- Standardized COAI # edits to occur through the normal **Save Changes** flow.
- Reintroduced server-side **COAI_number guard** during save:
  - Validates allowed format.
  - Prevents duplicate COAI numbers across members.
  - Skips update when unchanged or blank.
- Added **audit logging** for COAI number changes (old → new, actor).
- Added **warning banner** shown after save when a COAI number is changed.
- Preserved Admin/Manager ability to freely edit COAI # (no field hiding or disabling).
- Cleaned up legacy COAI fix branch logic and resolved mismatched braces.
- Ensured update logic executes once with consistent messaging and reload behavior.

**Behavior Notes**
- COAI # changes now occur only when the value actually differs.
- Warning banner appears only on successful save with a COAI change.
- No additional confirmation or reason fields required; internal comments remain optional.

## [2026-01-13] — Admin / Member Edit Gold Stabilization

### Fixed
- Restored correct **Member Directory → Member Edit** navigation:
  - Member listing remains at `/member-directory/`
  - Editing a member now correctly routes to `/member-edit/?mid=####`
  - Page title and context correctly display as **Member Edit**
- Resolved issue where `[coai_member_edit_form]` did not render on `/member-edit/`
  due to load-order and routing conflicts.
- Fixed shortcode registration edge cases that caused pages to display
  literal shortcode text instead of rendering content.

### Added
- Restored **Admin/Manager “Change Password”** capability on the Member Edit screen:
  - Allows staff to set/reset a member’s WordPress login password
  - Automatically invalidates existing sessions for the member
  - Secure nonce protection and permission checks
  - Logged via server audit (`[COAI] PASSWORD RESET ...`)
- Added clear **COAI # change warning banner** when a COAI number is modified.
- Added audit logging for COAI number changes including:
  - Old value
  - New value
  - Acting staff user
  - Optional reason field
- Implemented reliable WordPress user lookup for password resets:
  - Uses `wp_user_id` / `user_id` if present
  - Falls back to username, then email

### Improved
- Clean separation of concerns:
  - **Member Directory** = browse, filter, export
  - **Member Edit** = edit individual records
- Hardened shortcode registration:
  - Immediate registration
  - Late `init` fallback registration to prevent load-order issues
- Simplified finance-only routing:
  - Finance-only users now render finance view directly (no nested shortcodes)
- Improved resilience against caching on edit pages:
  - Explicit no-cache headers enforced on Member Edit

### Technical Notes
- `admin-members.php` is now the single source of truth for:
  - Staff edit UI
  - COAI number validation & audit
  - Password reset logic
- `member-edit.php` now delegates Admin/Manager editing
  to the GOLD editor renderer instead of attempting inline directory edits.
- Inline editing on `/member-directory/` is deprecated in favor of
  the dedicated `/member-edit/` page.

### Status
- ✅ Production-stable
- ✅ Backward compatible
- ✅ Fully audited
- ✅ Ready for documentation & backup

## [Unreleased]
- Added Insurance CSV Compare admin page with preview/apply workflow and rollback by Batch ID (zweam_coai_member_audit).
- Added CSV validation + expanded error report columns (Membership Code, Name, Email).
- Mapped CSV fields:
  - Membership Code → wp_members.COAI_number (strict match key)
  - Policy Eff Date → insurance_effective_date (normalized to YYYY-MM-DD)
  - Insurance Status → insurance_status
  - (Optional) Expiration date mapping supported if present (normalized to YYYY-MM-DD).
- Added optional “COAI # Fill” mode:
  - If strict match fails, match by Email and create a fill diff only when COAI_number is blank.
  - Never overwrites existing COAI_number; overwrite attempts are blocked.
- Hardened date comparison to prevent false diffs (CSV + DB normalized).
- 
## [2026-02-02] Zeffy Importer Stabilization & Duplicate Safety Enhancements

### Fixed
- Resolved fatal PHP error in `coai-zeffy-importer.php` caused by an extra closing brace in `coaii_render_page()`.
- Corrected undefined variable reference (`$dupe_det`) in `coaii_preimport_duplicate_detector()` that could trigger notices or strict-mode failures.
- Removed unused preview HTML accumulator logic to reduce complexity and risk in deep conditional branches.

### Improved
- Hardened dry-run workflow to clearly separate:
  - staging load
  - normalization
  - duplicate detection
  - plan (UPDATE vs INSERT)
- Ensured downstream actions (COAI assignment, logging, emails) execute **only** after successful live upsert.
- Normalized logging for dry-run and live import phases for clearer audit/debug trails.

### Added
- Pre-import duplicate detector with:
  - **Hard stops** for ambiguous matches (one import row → multiple members)
  - **Warnings** for potential duplicates based on name + city + state
- Admin-facing warning table displaying:
  - Import row details
  - Possible matching member records
  - COAI numbers and emails for review
- Safe workflow requiring warnings to be resolved before live import.

### Verified
- Dry-run preview accurately reflects planned UPDATE vs INSERT actions.
- COAI numbers are auto-assigned **insert-only** and never overwritten.
- Existing member records are updated without duplication once warnings are resolved.
- Live import completes with clean audit logging and notifications.

### Files
- `wp-content/plugins/coai-zeffy-importer/coai-zeffy-importer.php`

### Notes
- Importer now follows a board-safe workflow: Dry-run → Review → Fix → Clean dry-run → Live.
- Duplicate detector is intentionally conservative to prevent silent data corruption.

## [2026-02-03] Admin Member Edit – Sectioned Layout + Deletion Audit

### Added
- Fully sectioned **Member Edit** layout:
  - Member
  - Insurance
  - Financial
  - Internal (Staff Only)
  - Security / Password
- Editable **Deleted Date**, **Deleted Reason**, and **Deleted By (name)** fields for Admin/Manager users.
- Automatic **Internal Comments** audit entries when deletion-related fields change.
- Deletion audit summary includes:
  - Deleted date
  - Deleted reason
  - Deleted by (staff name)
  - Staff user who made the change

### Changed
- Replaced legacy flat edit form with structured grid-based sections.
- `Deleted By` now stores and displays a **human-readable Admin/Manager name** (not member_id).
- Internal deletion changes are appended as timestamped audit notes instead of overwriting comments.
- Archived members remain hidden from standard views unless explicitly included.

### Fixed
- Resolved PHP parse errors caused by duplicate Internal sections.
- Corrected loss of deletion metadata on save.
- Prevented silent overwrite of deletion audit fields.
- Ensured archived/renewed dual-member records behave correctly in edit views.

### Notes
- Archived records remain queryable by direct `member_id` but are excluded from active results.
- Supports historical member retention without hard deletes.
- Compatible with existing audit logging and COAI number guards.

Files touched:
- `/includes/dashboard/admin-members.php`

## [2026-02-03] Authentication & Membership Controls Hardening

### Added
- Enforced **ARCHIVED / EXPIRED membership gating** at authentication layer:
  - Members with `status = ARCHIVED` or `status = EXPIRED` are no longer allowed into the member portal
  - Successful credential validation now **redirects to Zeffy renewal page**
- Centralized renewal destination via constant:
  - `COAI_RENEWAL_URL` defined in `coai-auth-bridge.php`
  - Single authoritative location for 3rd-party renewal flow
- Optional renewal context support via URL parameters:
  - `?reason=archived` or `?reason=expired` appended on redirect

### Changed
- Authentication logic now distinguishes between:
  - **Credential validity** (password correct)
  - **Membership eligibility** (status check happens after auth)
- Member login flow explicitly separates:
  - `wp_members` authentication (member-login only)
  - WordPress core authentication (wp-admin only)

### Security
- Prevented ARCHIVED members from gaining portal access even with valid credentials
- Deleted members (`deleted_at` set) remain fully blocked from login
- No changes made to WordPress admin or system user authentication paths

### Technical Notes
- Renewal flow is intentionally handled at the **auth-bridge layer**
- Renewal URL is not user-configurable and not stored in the database
- Design assumes renewals are processed externally (Zeffy as system of record)

### Files Modified
- `/mu-plugins/coai-auth-bridge.php`

## 2026-02-04 — Insurance CSV Apply Fixes (COAI Membership Code + Audit Stability)

### Fixed
- Resolved issue where **“Apply Selected Changes”** did not update records after preview.
- Fixed apply handler execution order and variable scope (`$dry_run`, `$diffs`) so apply logic executes reliably.
- Corrected audit logging to prevent DB failures when appending to `internal_comments`.

### Improved
- Normalized **Membership Code / COAI_number** from CSV:
  - Uppercases value
  - Repairs Excel-truncated suffixes (e.g. `201703-6` → `201703-006`)
  - Validates strict format `YYYYMM-NNN`
- Ensured COAI_number is **never overwritten** — only filled when blank.
- Auto-calculates insurance expiration date (+1 year) when missing and effective date is present.
- Hardened apply flow with detailed debug logging for traceability.

### Schema
- Updated `wp_members.internal_comments` to `LONGTEXT` to safely support append-only audit history.

### Files
- `includes/admin/insurance-csv-compare.php`
- `includes/audit-log.php` (comment append helper)
- Database: `wp_members.internal_comments`

### Notes
- Preview → Apply now uses a persistent transient (`coai_ins_preview_{user_id}`).
- Unmatched CSV rows remain downloadable for manual review.
- Debug logging may be reduced once stable in PROD.

## [2026-02-04] COAI Voice FAQ – Home & Portal Enhancements

### Added
- Added Voice-Activated FAQ widget for Home and Member Portal pages.
- Implemented mode-aware FAQ routing:
  - **Home**: registration, login help, renewal only.
  - **Member Portal**: insurance, Calliope magazines, navigation help.
- Added persistent COAI contact information (email + phone) visible in all FAQ responses.
- Added friendly female voice preference for Microsoft Edge using explicit voice selection.
- Added automatic input focus after voice input, responses, and actions for improved UX.

### Changed
- Split registration FAQ into discrete questions:
  - How to register
  - Why register
  - Registration benefits
  - Registration cost
- Updated voice input handling to process **only the first question** when multiple questions are spoken.
- Reduced Home page Voice FAQ layout width to align visually with login card.
- Tuned speech synthesis rate and pitch for a warmer, more approachable tone.
- Improved JavaScript handling for browser voice availability and fallback selection.

### Fixed
- Fixed issue where voice responses read multiple FAQ answers in a single interaction.
- Fixed Edge browser defaulting to male voice by explicitly preferring Microsoft Zira.
- Fixed intermittent “network error” caused by cached JavaScript during development.
- Fixed AJAX handling to correctly respect Home vs Portal FAQ mode.

### Notes
- Voice selection depends on OS/browser-available speech voices; Edge now consistently selects a female voice when available.
- JavaScript and CSS updates may require cache clearing or version bump to load immediately.

## [2026-02-05]

### Fixed
- Restored missing address-related fields in **Admin Member Edit**:
  - Address
  - Address 2
  - City
  - State
  - Zip
  - Country
  - Region
- Re-enabled **automatic Region calculation** based on State (with Country override support).
- Corrected layout issue where **Save Changes / Cancel** buttons rendered outside the intended form area.
- Fixed container nesting so the **site footer no longer renders inside the member edit form**.
- Corrected markup boundaries around the **Security / Password** section to prevent layout bleed.

### Improved
- Password reset UI now fully self-contained within the **Security / Password** section.
- Password visibility toggle (eye icon) positioned correctly inside input fields.
- Member Edit form structure now consistently respects `.coai-section` and `.coai-wrap` boundaries.

### Notes
- Member Login passwords remain stored **only** in `wp_members.password`.
- WordPress admin passwords are unchanged and managed exclusively by WordPress core.

## [2026-02-05] Home Page Layout Cleanup (In Progress)

### Changed
- Reduced duplicate CTAs on Home page:
  - Kept a single primary **Join COAI Today** CTA.
  - Removed inline **Register** / **Renew** buttons from the Member Login shortcode.
- Moved **Renew Membership** to a secondary button under the primary Join CTA for clarity.
- Clarified member flow with:
  - “Already a member?” + “Log in below” text above the login card.

### Updated
- Refactored `coai_login_box` usage on Home page to focus on login only.
- Adjusted login messaging to reduce confusion between:
  - Member Portal password
  - WordPress admin password.
- Footer updated to include Mission statement styled consistently with Copyright.

### Investigated
- Extensive testing of Gutentor, Columns, and Container/Cover blocks with CosmosWP theme.
- Confirmed CosmosWP re-wraps block output on front-end, preventing reliable side-by-side layout using block-based columns alone.
- Identified that theme output structure (not editor settings) causes Login + Voice FAQ stacking.

### Planned
- Introduce a new wrapper shortcode:
  - `[coai_home_login_help]`
  - Purpose: render **Member Login** and **Voice FAQ** in a controlled grid layout.
- This will replace dual shortcodes on Home page to ensure:
  - Stable side-by-side layout on desktop
  - Clean stacked layout on mobile
  - No dependency on theme/block layout quirks.

### Notes
- No production-breaking changes deployed.
- Wrapper shortcode approach selected as the most stable, theme-agnostic solution.

## 2026-02-06 — Zeffy Importer: Fix ambiguous matches when COAI # is present

### Fixed
- Prevented “Ambiguous match” hard-stops where a Zeffy row matched one member by **COAI_number** and a different member by **email/username** (common when an Admin/staff record shares an email/username with a Member record).
- Updated `coaii_join_sql()` to be **mutually exclusive**:
  - If `z.COAI_number` is present → match **ONLY** on normalized `COAI_number` (exact).
  - If `z.COAI_number` is blank → match by `email` / `username` fallback.

### Technical
- Replaced OR-based join logic (`email OR COAI`) with conditional join logic (`COAI-only when present; else email/username`).
- Preserved existing COAI normalization behavior (trimming NBSP/tabs/CR) for consistent matching.

### Files
- `coai-zeffy-importer.php`

## [Unreleased] – 2026-02-06

### Fixed
- Resolved broken page layout caused by unclosed HTML in the `coai_voice_faq` shortcode.
  - Added missing closing `</div>` tags for `.coai-vfaq-answer-wrap`, `.coai-vfaq-inner`, and `.coai-vfaq`.
  - Prevented footer and downstream content from being trapped inside the Home login/FAQ wrapper.
- Restored proper rendering of `[coai_home_login_help]` shortcode after DOM corruption was fixed.

### Improved
- Balanced Home page layout between **Member Login** and **Voice FAQ**:
  - Reduced visual dominance of the login form.
  - Adjusted grid column ratios to give the FAQ equal or greater visual weight.
  - Tightened spacing and padding for better vertical alignment.
- Improved overall Home page visual rhythm and section separation above the footer.

### UI / Content
- Clarified Gutentor Button configuration for external links:
  - Corrected misuse of `Link Rel` vs `URL` fields.
  - Ensured “Renew Membership” button properly links to Zeffy checkout.
- Removed accidental rendering of raw URLs inside button text.

### Notes
- All shortcodes involved now use `ob_start()` / `return ob_get_clean()` patterns.
- HTML structure has been validated to prevent future DOM bleed issues.

## [2026-02-06]

### Home Page – Login & Help Section
- Rebuilt **Home Login + Voice FAQ** layout using a stable CSS Grid wrapper (`.coai-home-login-help`).
- Balanced column widths so **Login is compact** and **FAQ is visually equal-weight**.
- Removed all negative-margin / pull-up hacks that caused skew and overlap.
- Fixed vertical alignment drift between Login and FAQ cards.
- Ensured both cards share consistent border radius, padding, and visual weight.
- Eliminated the thin gray divider line beneath the Login/FAQ section.
- Added proper bottom spacing so footer/legal content does not collide with home content.

### Mobile Layout Fixes
- Forced Home Login + FAQ to **stack cleanly on mobile** (single-column layout).
- Prevented **Renew Membership button** from overlapping the “Member Login” heading.
- Neutralized any theme transforms or positioning that caused mobile text collision.
- Ensured full-width cards with readable spacing on phones.

### Renew Membership Button
- Corrected Renew Membership button behavior so it properly links to the Zeffy renewal page.
- Resolved Gutenberg/Gutentor link-field conflict that caused the button to reload the current page.

### Member Login Page
- Restored correct login card width and centering on `/member-login/`.
- Prevented Home-specific `.coai-login` styles from leaking into the standalone login page.
- Verified logout → login flow renders cleanly without layout distortion.

### Content Updates
- Added **Clowns of America International Mission** statement.
- Added **COAI Core Values**:
  - Integrity
  - Respect
  - Service
  - Excellence
- Improved visual hierarchy for Mission & Values content on the Purpose of COAI page.

### Technical Notes
- CSS cleanup consolidated overlapping rules to avoid future layout regressions.
- Mobile overrides now explicitly undo any desktop-only positioning.
- Home-specific styling fully isolated from Member Login page styles.

## [Unreleased] – 2026-02-06

### Fixed
- **Admin Member Edit – Missing Clown Name Field**
  - Added editable **Clown Name** field to the admin member-edit form.
  - Field is bound directly to the existing `clown_name` column in `wp_members`.
  - Ensured the field posts and saves correctly using existing update logic (no schema changes required).

- **Member Directory – Grey Search Input Text**
  - Fixed CSS so text typed into Member Directory search fields displays in black instead of grey.
  - Preserved muted styling for placeholder text only.

### Notes
- No database migrations required.
- No changes to save/update logic were necessary beyond rendering the missing field.
- Changes are UI-only and safe for production deployment.

## [2026-02-15] – Member Portal Layout & Voice FAQ UI Refinement

### ✨ Improvements
- Implemented responsive layout behavior for Member Portal page:
  - **Large desktop (≥1200px):** 3-column layout  
    `Voice FAQ | Member Portal | Staff Tools`
  - **Laptop / tablet (821–1199px):** 2-column layout  
    `Voice FAQ | Member Portal + Staff Tools (stacked)`
  - **Mobile (≤820px):** Fully stacked layout

- Corrected visual spacing and width inconsistencies between:
  - Voice FAQ widget
  - Member Portal card
  - Staff Tools card

---

### 🧩 CSS Changes
**Updated responsive grid system**

Added:

```css
.coai-portal-card{
  width: 100% !important;
  max-width: none !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
}

@media (min-width: 1200px){
  .coai-portal-grid{
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
  }

  .coai-portal-grid .coai-vfaq{ grid-column: 1; }
  .coai-portal-grid .coai-portal-card:not(.coai-portal-staff-tools){ grid-column: 2; }
  .coai-portal-grid .coai-portal-staff-tools{ grid-column: 3; }
}

@media (min-width: 821px) and (max-width: 1199px){
  .coai-portal-grid{
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
  }

  .coai-portal-grid .coai-vfaq{ grid-column: 1; }
  .coai-portal-grid .coai-portal-card{ grid-column: 2; }
}

@media (max-width: 820px){
  .coai-portal-grid{
    display: block !important;
  }

  .coai-portal-card{
    margin-bottom: 18px;
  }
}

Member Portal card width normalization

Updated inline style:

Before

<div class="coai-portal-card" style="max-width:720px;margin:2rem auto; ...">


After

<div class="coai-portal-card" style="margin:0 0 18px; padding:1.25rem; border:1px solid #e5e7eb; border-radius:12px; background:#fff; width:100%; max-width:none;">


Reason

Removed forced centering (margin:auto)

Removed width constraint (max-width)

Allows grid layout to control sizing properly

## 2026-02-15 — Fix member login from homepage login box (COAI auth bridge routing)

### Summary
- Fixed Member Login failures when logging in from the homepage/login box.
- Root cause: login form posted to `/` (homepage), so `coai-auth-bridge.php` treated it as NOT `/member-login/` and fell back to WordPress core auth (which fails for members who exist only in `wp_members`).

### What Changed
#### 1) Force login form to submit to `/member-login/`
- Updated the login form action so requests hit the `/member-login/` route, ensuring wp_members authentication is used.

### Files Updated
1) `/wp-content/plugins/coai-members-custom/includes/shortcodes/login-form.php`
- Before:
  - `<form method="post" action="">`
- After:
  - `<form method="post" action="<?php echo esc_url(home_url('/member-login/')); ?>">`

### Validation
- Reset member password via Admin/Manager Member Edit.
- Logged in successfully as a `wp_members`-only member (e.g., `Dulcimerguy`) using the test password from the homepage login box.

### Notes
- This preserves the intended security model:
  - `/member-login/` authenticates against `wp_members` only.
  - `/wp-login.php` authenticates WordPress users only.

## 2026-02-15 — coai-auth-bridge hardening + cleanup (no behavior change intended)

### Updated
- Added `coai_auth_log()` helper gated by `COAI_AUTH_DEBUG` to prevent persistent debug spam in production.
- Normalized member identifier input using `trim()` + `wp_unslash()` for more reliable lookups.
- Fixed password hash upgrade detection to recognize WordPress-wrapped bcrypt hashes (`$wp$2y$...`) as bcrypt (prevents unnecessary “plainish” upgrade logic).

### Files Updated
- public_html/wp-content/mu-plugins/coai-auth-bridge.php

## 2026-02-15 — Member Login redirect hardening (prevent wp-admin redirects)

### Summary
Adjusted Member Login behavior to ensure users logging in through the `/member-login/` flow are never redirected into `wp-admin` via the `redirect_to` parameter.

### Details
- Updated login redirect logic in the Member Login form.
- `redirect_to` is now honored **only for front-end URLs**.
- Any `redirect_to` pointing to `wp-admin` is ignored.
- Fallback redirect remains `/member-portal/`.

### Reason
All roles (Member, Admin, Manager, Finance) authenticate through the same Member Login UI.  
Staff tools are presented inside the **Member Portal**, not wp-admin.  
Preventing wp-admin redirects avoids:

- Confusing post-login behavior
- Accidental admin landing pages
- Potential redirect abuse scenarios

### Files Updated
- public_html/wp-content/plugins/coai-members-custom/includes/shortcodes/login-form.php

### Behavior After Change
- Valid front-end `redirect_to` → redirect honored
- `redirect_to` → wp-admin → redirected to `/member-portal/`
- No `redirect_to` → redirected to `/member-portal/`

## 2026-02-15 — Change Password: fix WP hash verification + standardize hashing

### Summary
Fixed Change Password current-password verification for WordPress-wrapped bcrypt hashes and standardized new password hashing to use WordPress hashing when available.

### Reason
- Many member passwords are stored using WordPress hashing format (e.g., `$wp$2y$...`). The previous legacy verifier did not recognize this format, causing false “Current password is incorrect.”
- New passwords should be stored using `wp_hash_password()` for consistency with the auth bridge and existing password formats.

### Code Change
**File:** public_html/wp-content/plugins/coai-members-custom/includes/shortcodes/change-password.php

**Before**
```php
// Legacy hash checker (bcrypt/phpass/md5/plain)
if (!function_exists('coai_check_legacy_hash')) {
  function coai_check_legacy_hash($plain, $hash) {
    $hash = (string)$hash;
    if ($hash === '') return false;

    // bcrypt
    if (strpos($hash, '$2') === 0 && function_exists('password_verify')) {
      return password_verify($plain, $hash);
    }

    // WordPress phpass ($P$ / $H$)
    if (strpos($hash, '$P$') === 0 || strpos($hash, '$H$') === 0) {
      require_once ABSPATH . WPINC . '/class-phpass.php';
      $h = new PasswordHash(8, true);
      return $h->CheckPassword($plain, $hash);
    }

    // raw MD5
    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
      return (md5($plain) === strtolower($hash));
    }

    // last resort: plain compare
    return hash_equals($hash, $plain);
  }
}
...
$new_hash = password_hash($new_pw, PASSWORD_DEFAULT);

After:
// Password verifier:
// - Prefer WP's verifier (supports $wp$2y$, $P$, $H$, etc.)
// - Fallback to common formats if WP verifier isn't available
if (!function_exists('coai_check_legacy_hash')) {
  function coai_check_legacy_hash(string $plain, $hash): bool {
    $hash = trim((string)$hash);
    if ($hash === '') return false;

    if (function_exists('wp_check_password')) {
      return (bool) wp_check_password($plain, $hash);
    }

    if (strpos($hash, '$wp$') === 0 && function_exists('password_verify')) {
      return password_verify($plain, substr($hash, 4));
    }

    if (strpos($hash, '$2') === 0 && function_exists('password_verify')) {
      return password_verify($plain, $hash);
    }

    if (strpos($hash, '$P$') === 0 || strpos($hash, '$H$') === 0) {
      require_once ABSPATH . WPINC . '/class-phpass.php';
      $h = new PasswordHash(8, true);
      return $h->CheckPassword($plain, $hash);
    }

    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
      return (md5($plain) === strtolower($hash));
    }

    return hash_equals($hash, $plain);
  }
}
...
$new_hash = function_exists('wp_hash_password')
  ? wp_hash_password($new_pw)
  : password_hash($new_pw, PASSWORD_DEFAULT);

## 2026-02-15 — Reset Password: schema-safe lookup + token URL fix + WP hashing

### Summary
Hardened the member self-service password reset flow to work reliably across schema variations and match the site-wide WP hashing standard.

### Reason
- Reset lookup was hardcoded to `username`/`email` column names, which can fail on schema variants.
- Reset URL was double-encoding tokens (`rawurlencode` inside `add_query_arg`), causing valid links to fail verification.
- New member passwords should be stored using `wp_hash_password()` when available (consistent with auth bridge/change-password).

### Code Change
**File:** public_html/wp-content/plugins/coai-members-custom/includes/shortcodes/reset-password.php

**Before**
```php
$row = $wpdb->get_row(
  $wpdb->prepare("SELECT * FROM `{$t}` WHERE TRIM(LOWER(username)) = TRIM(LOWER(%s)) LIMIT 1", $ident),
  ARRAY_A
);
...
$count = (int)$wpdb->get_var(
  $wpdb->prepare("SELECT COUNT(*) FROM `{$t}` WHERE TRIM(LOWER(email)) = TRIM(LOWER(%s))", $ident)
);
...
$url = add_query_arg(
  ['mid' => $member_id, 'token' => rawurlencode($token_plain)],
  home_url('/member-reset-password-2/')
);
...
$hash = password_hash($plain, PASSWORD_DEFAULT);
...
$data['reset_token_hash'] = null;
$data['reset_expires'] = null;

After:
// Detect username/email column names (schema-safe)
$username_col = coai_members_pick_col($cols_lc, ['username','user_name','user','login','user_login','member_username']);
$email_col    = coai_members_pick_col($cols_lc, ['email','e_mail','email_address','member_email']);

// Build reset URL without double-encoding
$url = add_query_arg(
  ['mid' => $member_id, 'token' => $token_plain],
  home_url('/member-reset-password-2/')
);

// Store new member password using WP hash when available
$hash = function_exists('wp_hash_password')
  ? wp_hash_password($plain)
  : password_hash($plain, PASSWORD_DEFAULT);

// Clear token fields reliably
$data['reset_token_hash'] = '';
$data['reset_expires'] = '';

## 2026-02-15 — Auth Bridge: recognize COAI login POSTs even when URI isn’t /member-login/

### Summary
Hardened member-login detection so wp_members authentication is used when a login POST originates from the COAI login form, even if the request URI is "/" (e.g., homepage login box).

### Reason
Homepage login attempts can post to "/" depending on form action/theme behavior. In that case, the auth bridge treated the request as a normal WordPress login (wp_users), producing: “The username X is not registered on this site.”

### Code Change
**File:** public_html/wp-content/mu-plugins/coai-auth-bridge.php

**Added (immediately after $is_member_login regex detection)**
```php
// ALSO treat as member-login when the POST came from our COAI login form (home page box may post to "/")
if (!$is_member_login && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

  $nonce_ok = false;
  if (!empty($_POST['_coai_nonce']) && function_exists('wp_verify_nonce')) {
    $nonce_ok = wp_verify_nonce(wp_unslash($_POST['_coai_nonce']), 'coai_login');
  }

  // Our COAI login form posts these fields (login-form.php)
  $looks_like_coai_form =
    !empty($_POST['login_submit']) &&
    (isset($_POST['log']) || isset($_POST['username'])) &&
    (isset($_POST['pwd']) || isset($_POST['password']));

  if ($nonce_ok && $looks_like_coai_form) {
    $is_member_login = true;
  }
}

## 2026-02-15 — Restore wp-admin Change Log viewer

### Summary
- Re-enabled the wp-admin Tools menu item that displays the plugin `docs/CHANGELOG.md` file.
- This restores **Tools → COAI Changelog** for administrators.

### Files Updated
- `/wp-content/plugins/coai-members-custom/coai-members-custom.php`

### Code Changes
Re-enabled the changelog admin page registration and renderer:

```php
add_action('admin_menu', 'coai_members_register_changelog_page');

function coai_members_register_changelog_page() {
  add_management_page(
    'COAI Members Changelog',
    'COAI Changelog',
    'manage_options',
    'coai-members-changelog',
    'coai_members_render_changelog_page'
  );
}

function coai_members_render_changelog_page() {
  if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to view this page.');
  }

  $changelog_file = plugin_dir_path(__FILE__) . 'docs/CHANGELOG.md';
  // ...renders file contents safely...
}

## [2026-03-16] - Election / Member Voting Module v1

### Added
- Added new election voting module to support secure officer elections for members.
- Added dedicated election database tables:
  - `wp_coai_elections`
  - `wp_coai_election_positions`
  - `wp_coai_election_candidates`
  - `wp_coai_election_votes`
  - `wp_coai_election_vote_items`
- Added schema installer helper in `includes/helpers/election-db.php`.
- Added election permission / eligibility helper in `includes/helpers/election-permissions.php`.
- Added member voting shortcode in `includes/shortcodes/member-voting.php`.
- Added staff election admin shortcode in `includes/shortcodes/staff-election-admin.php`.
- Added staff election results shortcode in `includes/shortcodes/staff-election-results.php`.

### Voting Logic
- Restricted voting page to logged-in users only.
- Added lookup for current logged-in member record.
- Added voting eligibility check based on active member status.
- Blocked archived/deleted members from voting.
- Limited members to one vote per election using DB unique key on `(election_id, member_id)`.
- Added nonce validation and server-side candidate validation on ballot submit.
- Added vote header + vote item storage with transaction handling.
- Logged vote submission success/failure to debug log.

### Admin / Staff Tools
- Added election creation form with:
  - title
  - description
  - status
  - opens_at
  - closes_at
  - show_results
- Added position creation form.
- Added candidate creation form.
- Added basic staff-only results screen by election.

### Files Updated
- `coai-members-custom.php`
- `includes/helpers/election-db.php`
- `includes/helpers/election-permissions.php`
- `includes/shortcodes/member-voting.php`
- `includes/shortcodes/staff-election-admin.php`
- `includes/shortcodes/staff-election-results.php`

### Notes
- v1 supports one ballot submission per member per election.
- v1 allows offices to be skipped during submission.
- v1 does not yet include edit/delete admin actions, CSV export, or public results display.
- Logged-in member ID mapping may need to be adjusted to match existing COAI auth bridge helper/meta structure.

## [2026-03-16] — Election & Voting System Implemented

### Added

* New **Election / Voting module** integrated into the COAI Members Custom plugin.
* Created database schema for election system including:

  * `wp_coai_elections`
  * `wp_coai_election_positions`
  * `wp_coai_election_candidates`
  * `wp_coai_election_votes`
  * `wp_coai_election_vote_items`
* Added new helper and shortcode files:

  * `includes/helpers/election-db.php`
  * `includes/helpers/election-permissions.php`
  * `includes/shortcodes/member-voting.php`
  * `includes/shortcodes/staff-election-admin.php`
  * `includes/shortcodes/staff-election-results.php`
* Added plugin loader block in `coai-members-custom.php` to initialize election system.

### Features

* **Member Voting Page**

  * Displays active election when `status='open'`
  * Respects election open/close dates
  * Prevents duplicate voting
  * Records vote metadata (member_id, timestamp, IP, user agent)
  * Supports multiple ballot positions and candidates

* **Election Admin Page**

  * Create elections
  * Set election open/close dates
  * Add ballot positions
  * Add candidates to positions
  * View existing elections, positions, and candidates

* **Results Page**

  * Staff view of vote totals by candidate and position
  * Uses aggregated vote_items table

### Database

* Election tables were initially generated with site prefix `zweam_`.
* Final implementation standardized election tables to use **explicit `wp_` table names** to match manually created tables.

Correct table mapping in `election-db.php`:

```
wp_coai_elections
wp_coai_election_positions
wp_coai_election_candidates
wp_coai_election_votes
wp_coai_election_vote_items
```

### Fixes

* Resolved issue where plugin queried `zweam_` prefixed tables instead of `wp_` tables.
* Corrected `coai_election_table()` mapping to remove `$wpdb->prefix` and use explicit table names.
* Restored visibility of elections, positions, and candidates in admin and voting pages.

### Notes

* Voting page only displays elections meeting all conditions:

  * `status = 'open'`
  * `opens_at <= current_time`
  * `closes_at >= current_time`
* Future elections will not appear until the open date is reached.

### Pages Using Shortcodes

* **Member Voting Page**

  * `[coai_member_voting]`

* **Election Admin Page**

  * `[coai_staff_election_admin]`

* **Election Results Page**

  * `[coai_staff_election_results]`

### Files Updated

```
coai-members-custom.php
includes/helpers/election-db.php
includes/helpers/election-permissions.php
includes/shortcodes/member-voting.php
includes/shortcodes/staff-election-admin.php
includes/shortcodes/staff-election-results.php
```

## [Planned – Phase 2] Election System Enhancements

### Planned Improvements

The following upgrades are planned to expand the COAI election and voting system after the initial production rollout.

### Regional Voting Controls

* Add **region-based ballot filtering** so members only see the Regional Vice President race for their own region.
* Region will be determined from the member record in `wp_members`.
* Positions containing **"Regional Vice President"** will automatically match against member region.

Example behavior:

* Member region = `South East`
* Ballot shows only **South East Regional Vice President**
* All other regional races are hidden.

### Candidate Enhancements

* Support **candidate photos** displayed on the ballot.
* Improve candidate **bio formatting** on the voting page.
* Optional **candidate links** (campaign page or website).

### Ballot UX Improvements

* Improve ballot layout for clarity and accessibility.
* Add clearer voting instructions above each office.
* Add confirmation screen before vote submission.

### Election Administration Improvements

Enhancements planned for the admin interface:

* Edit existing elections
* Edit positions and candidates
* Reorder ballot positions
* Activate / deactivate candidates
* Duplicate an election for the next year

### Election Results Improvements

* Live vote totals visible to staff
* Optional results visibility for members
* Percentage and vote count display
* Ability to export results to CSV

### Data Integrity & Auditing

* Enhanced logging for vote submissions
* Optional audit log for election administration changes
* Additional validation to prevent malformed ballots

### Security Enhancements

* Rate limiting for vote submissions
* Additional nonce validation
* Optional vote confirmation token

### Future Expansion Possibilities

* Ranked-choice voting support
* Multi-seat elections (vote for N candidates)
* Election participation reporting
* Historical election archive page

## [2026-03-16] - Election Ballot Regional VP Instruction Note

### Updated
- Updated `includes/shortcodes/member-voting.php` to display a ballot instruction note for Regional Vice President offices.

### Added
- Added helper function `coai_get_position_note()` to detect ballot positions containing `Regional Vice President`.
- Added visual instruction note box above Regional Vice President candidate selections.

### Ballot Behavior
- Any position title containing `Regional Vice President` now displays:
  - `Please vote only for the Regional Vice President in your own region.`

### Files Updated
- `includes/shortcodes/member-voting.php`

### Notes
- This version adds an instructional note only.
- It does not yet automatically restrict members to seeing only their own region’s Regional Vice President race.
- Region-based filtering can be added in a later phase.

## [2026-03-16] - Regional VP Instruction + Write-In Support Preparation

### Updated
- Updated `member-voting.php` to display an instructional note for Regional Vice President positions:
  - "Please vote only for the Regional Vice President in your own region."

### Added
- Added support structure for optional write-in candidates.
- Added `allow_write_in` column to `wp_coai_election_positions`.
- Added `write_in_name` column to `wp_coai_election_vote_items`.

### Behavior
- Positions can optionally allow write-in candidates.
- Write-ins are stored alongside standard candidate selections for reporting.

### Files Updated
- `includes/shortcodes/member-voting.php`
- `includes/helpers/election-db.php`

### Notes
- Write-in UI and result reporting enhancements planned for next release phase.

## [2026-03-16] - Regional VP Filtering Tightened

### Fixed
- Fixed Regional Vice President ballot filtering logic so broad member region values do not match multiple Regional VP offices.

### Updated
- Updated `coai_position_matches_member_region()` in `includes/shortcodes/member-voting.php`.
- Removed broad substring matching for Regional Vice President races.
- Changed logic to use exact region mapping only.

### Behavior
- Members now only see a Regional VP office when their stored member region matches a defined COAI regional mapping.
- Broad values such as `South` no longer incorrectly match both:
  - `South East Regional Vice President`
  - `Southwest & Texas Regional Vice President`

### Notes
- If a member record contains a broad or non-standard region value, no Regional VP office will be shown until the region value is made more specific.

## [2026-03-16] - Reverted Regional VP Auto-Filtering; Kept Ballot Instruction Note

### Updated
- Reverted automatic Regional Vice President ballot filtering in `includes/shortcodes/member-voting.php`.
- Restored display of all ballot positions for eligible voters.

### Kept
- Kept ballot instruction note for positions containing `Regional Vice President`:
  - `Please vote only for the Regional Vice President in your own region.`

### Reason
- Existing `region-map.php` returns broad region buckets:
  - `Northeast`
  - `Midwest`
  - `South`
  - `West`
  - `Canada`
  - `International`
- Current Regional Vice President ballot offices are more granular than those stored region values.
- Automatic filtering could incorrectly hide or show the wrong Regional VP race.

### Files Updated
- `includes/shortcodes/member-voting.php`

### Notes
- Region-based Regional VP filtering is deferred until election-region mapping is defined more precisely.
- Current production behavior shows all Regional VP races with a clear instruction note to vote only in the member’s own region.

## [2026-03-16] - Region Backfill Final Cleanup

### Fixed
- Cleaned up remaining unmapped region records after one-time region backfill.

### Updated
- Assigned `International` region to remaining Puerto Rico (`PR`) records as needed.
- Assigned `International` region to remaining Dominican Republic records.

### Improved
- Recommended mapper fallback update to better handle:
  - `PR`
  - `Dominican Republic`
  when country data is blank or inconsistent.

### Result
- Region backfill now resolves nearly all remaining location-based region gaps in member records.

## 2026-03-17 – Convention Attendee Cross Reference Tool (Zeffy Integration)

### Added
- New admin tool page under:
  - Tools → Convention Attendee Cross Ref
- Upload support for Zeffy Convention attendee CSV exports.
- Automatic staging table creation:
  - `{prefix}_coai_convention_attendee_xref`
- Batch-based processing with unique `batch_key` per upload.
- Cross-reference engine comparing Zeffy attendees against `wp_members`.
- Matching logic implemented:
  - Primary: Email (normalized)
  - Secondary: First Name + Last Name (normalized)
- Classification rules:
  - `EXISTING_MEMBER` → match found in `wp_members`
  - `NEW_MEMBER` → no match found in `wp_members`
- Admin UI features:
  - Batch summary cards (Total / New / Existing)
  - Results table (up to 500 rows)
  - Highlighting of NEW_MEMBER rows
- CSV export feature:
  - Download only `NEW_MEMBER` records
- Safe handling:
  - Excludes archived members (`deleted_at IS NULL`)
  - No writes to `wp_members` (read-only comparison tool)

---

### Updated
- Matching engine refactored from SQL JOIN-based logic to PHP-based lookup system:
  - Improves reliability with inconsistent data (spacing, casing, encoding)
  - Prevents missed matches like:
    - trailing spaces
    - mixed casing
    - hidden characters
- Normalization functions improved:
  - `coai_cax_norm_name()`
    - trims whitespace
    - removes control characters
    - collapses multiple spaces
    - converts to lowercase
  - `coai_cax_norm_email()`
    - trims + lowercases
    - removes hidden characters

---

### Fixed
- Issue where valid existing members were incorrectly flagged as `NEW_MEMBER`
  - Root cause: strict SQL matching failing on minor data inconsistencies
  - Resolution: replaced with normalized PHP matching logic
- Verified correction using real dataset:
  - Example: "Michael Cox" now correctly resolves as `EXISTING_MEMBER`

---

### Notes
- Tool uses its own staging table:
  - `{prefix}_coai_convention_attendee_xref`
  - (Example: `zweam_coai_convention_attendee_xref`)
- Existing legacy staging table `import_convention_zeffy` is not used by this tool.
- Name-only matching remains exact (post-normalization):
  - Variants like "D Becvar" vs "Becvar" will be treated as `NEW_MEMBER`
  - This is intentional to avoid false positives.

---

### Future Enhancements (Planned / Suggested)
- Add `POSSIBLE_MATCH` classification for fuzzy matches:
  - partial last name match
  - clown name match
  - phone match
- Add admin review panel for manual linking
- Add “Convert NEW_MEMBER → wp_members” workflow with:
  - COAI number auto-generation
  - `is_new_member` flag support
- Add re-run matching button for existing batches
- Optional fuzzy matching enhancements (controlled, not automatic)

---

### Files Added
- `/wp-content/plugins/coai-members-custom/includes/admin/convention-attendee-crossref.php`

---

### Files Updated
- `/wp-content/plugins/coai-members-custom/coai-members-custom.php`
  - Added conditional include:
    ```php
    if (!function_exists('coai_cax_render_admin_page')) {
      @require_once plugin_dir_path(__FILE__) . 'includes/admin/convention-attendee-crossref.php';
    }
    ```

