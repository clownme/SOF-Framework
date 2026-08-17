============================================================
SOF RECOVERY POINT
============================================================

Recovery Point:
    RP46

Name:
    Membership Transaction Management &
    Controlled Renewal Application

Date:
    August 17, 2026

Status:
    PRODUCTION STABLE

============================================================
RECOVERY PURPOSE
============================================================

RP46 establishes the first controlled SOF business process that
may safely modify authoritative Membership information after:

    evidence
    assessment
    human approval
    preparation
    re-assessment
    execution
    verification

This Recovery Point should be preserved before further
Membership transaction-processing development.

============================================================
BUSINESS WORKFLOW
============================================================

Normal payment/provider evidence remains immutable.

An exceptional New Membership payment involving an existing
member may progress through:

    Detect
        ↓
    Assess
        ↓
    Recommend
        ↓
    Human Decision
        ↓
    Re-Assess
        ↓
    Approve
        ↓
    Prepare
        ↓
    Verify Preconditions
        ↓
    Execute
        ↓
    Verify Result
        ↓
    Conclude

============================================================
RECOVERY FILE SET
============================================================

includes/SOF/Membership/membership.php

includes/SOF/Membership/Models/
    MembershipRenewalCandidate.php
    MembershipRenewalApplication.php

includes/SOF/Membership/Repositories/
    MembershipRenewalApplicationRepository.php

includes/SOF/Membership/Services/
    MembershipRenewalAssessmentService.php
    MembershipRenewalManagementReviewService.php
    MembershipRenewalApplicationService.php
    MembershipRenewalApplicationExecutionService.php

includes/SOF/Zeffy/zeffy.php

includes/SOF/Zeffy/Services/
    ZeffyNewMembershipBusinessAssessmentService.php
    ZeffyNewMembershipReviewService.php

includes/SOF/Zeffy/Presentation/Workspaces/
    NewMembershipManagementReviewWorkspace.php

includes/SOF/Zeffy/Presentation/Shortcodes/
    NewMembershipManagementReviewShortcode.php

coai-zeffy-importer/
    coai-zeffy-importer.php

============================================================
DATABASE RECOVERY REQUIREMENTS
============================================================

Required SOF tables:

    wp_sof_zeffy_transactions

    wp_sof_membership_management_decisions

    wp_sof_membership_renewal_applications

The Application ledger must retain a unique relationship between
source provider + source transaction so the same payment cannot
produce duplicate Renewal Applications.

============================================================
DO NOT RESTORE TEMPORARY TEST IMPLEMENTATION
============================================================

Do NOT add coai_update_member_renewal_fields() to:

    wp-content/mu-plugins/01-coai-core.php

PRODUCTION already owns this capability in:

    includes/repositories/member-repository.php

The MU plugin should remain responsible only for established
Membership table resolution.

============================================================
VERIFIED CONTROLLED TEST
============================================================

TEST member_id:
    126

Application:
    id 1

Result:
    Membership Renewal applied and verified.

Final Membership:

    renewal_date:
        2026-08-17

    membership_expiration:
        2027-08-16

Final Application:

    application_status:
        applied

============================================================
RECOVERY PRINCIPLE
============================================================

Historical evidence must survive conclusion.

Active workspaces must display only work that still requires a
person.

Membership writes must occur only through a controlled,
verifiable business execution boundary.

============================================================
END RECOVERY POINT
============================================================