=========================================================
SOF CHANGELOG
=============

Recovery Point:
RP47

Title:
Production Validation and Membership Renewal Protection

Date:
August 15, 2026

=========================================================
COMMUNICATIONS — PRODUCTION VALIDATION
======================================

VALIDATED:

RP44 Communications Queued Delivery Architecture in
PRODUCTION.

Production organizational Newsletter delivery:

Attempted:
1,050

Delivered:
1,050

Failed:
0

Confirmed durable queued delivery completes independently
of the initiating browser session.

Confirmed Production Member Portal reports persisted final
delivery results.

Confirmed server cron execution supports background delivery.

=========================================================
MEMBERSHIP — RENEWAL PROTECTION
===============================

ADDED:

includes/SOF/Membership/Services/
MembershipRenewalService.php

ADDED:

includes/SOF/Membership/Integration/
WordPressMemberResolver.php

ADDED:

includes/SOF/Membership/Presentation/
presentation.php

ADDED:

includes/SOF/Membership/Presentation/Shortcodes/
MembershipRenewalShortcode.php

ADDED:

includes/SOF/Membership/Presentation/Assets/css/
membership-renewal.css

UPDATED:

includes/SOF/Membership/membership.php

Added Membership Renewal Service loader.

Added WordPress Member Resolver loader.

Added Membership Presentation loader.

Preserved Production MemberLookupService dependency.

=========================================================
SHARED PRESENTATION
===================

ADDED:

includes/SOF/Presentation/
presentation.php

ADDED:

includes/SOF/Presentation/Assets/css/
sof-cards.css

UPDATED:

coai-members-custom.php

Added shared SOF Presentation loader.

Shared card presentation supports:

sof-card-row
sof-card
sof-card-action

Updated shared presentation asset version to:

1.0.2

This forced Production to retrieve the current SOF card
stylesheet rather than a cached version.

=========================================================
HOME EXPERIENCE
===============

UPDATED:

Production WordPress Home page content.

Created three member-focused cards:

Already a COAI Member?

Not a COAI Member Yet?

Need to Renew?

Actions:

LOG IN

JOIN COAI TODAY!

CHECK RENEWAL

CHECK RENEWAL now routes through the Membership Renewal
Protection experience instead of immediately sending a
member to the external renewal payment system.

Home page content is stored in WordPress and is therefore
not represented directly as a PHP file in this Git
recovery point.

Reusable card presentation is protected through
sof-cards.css.

=========================================================
RENEW MEMBERSHIP EXPERIENCE
===========================

UPDATED:

Production Renew Membership WordPress page.

Page now uses:

[sof_membership_renewal]

Logged-out members are instructed to log in before renewal.

Login preserves the Renew Membership page as the return
destination.

Logged-in members receive an assessment based on their
membership expiration situation.

Current members are informed that renewal is not yet needed.

Eligible or expired members may continue to the external
renewal process.

=========================================================
PRODUCTION DEPLOYMENT CORRECTIONS
=================================

CORRECTED:

Membership Presentation shared-function naming collision.

The Membership Presentation enqueue function now remains
separate from the shared SOF Presentation enqueue function.

CORRECTED:

Membership Presentation shortcode folder name to:

Shortcodes

This matches the Membership Presentation loader path.

CORRECTED:

Membership Renewal CSS deployment.

Production now uses the validated TEST
membership-renewal.css presentation.

CORRECTED:

Production Home card button alignment.

Shared SOF card CSS now correctly supports the Gutenberg
Columns → Column → Group → Buttons structure.

=========================================================
KNOWN FOLLOW-UP
===============

An additional inactive MembershipRenewalShortcode.php exists
under:

includes/SOF/Presentation/Shortcode/

The active Membership Renewal implementation is loaded from:

includes/SOF/Membership/Presentation/Shortcodes/

The inactive duplicate is retained in RP47 because this
recovery point captures the current validated Production
filesystem.

Future cleanup should occur separately after confirming no
dependency relies on the duplicate file.

=========================================================
FILES INCLUDED IN RP47
======================

MODIFIED:

wp-content/plugins/coai-members-custom/
coai-members-custom.php

wp-content/plugins/coai-members-custom/
includes/SOF/Membership/membership.php

NEW:

wp-content/plugins/coai-members-custom/
includes/SOF/Membership/Integration/
WordPressMemberResolver.php

wp-content/plugins/coai-members-custom/
includes/SOF/Membership/Presentation/
presentation.php

wp-content/plugins/coai-members-custom/
includes/SOF/Membership/Presentation/Shortcodes/
MembershipRenewalShortcode.php

wp-content/plugins/coai-members-custom/
includes/SOF/Membership/Presentation/Assets/css/
membership-renewal.css

wp-content/plugins/coai-members-custom/
includes/SOF/Membership/Services/
MembershipRenewalService.php

wp-content/plugins/coai-members-custom/
includes/SOF/Presentation/
presentation.php

wp-content/plugins/coai-members-custom/
includes/SOF/Presentation/Assets/css/
sof-cards.css

wp-content/plugins/coai-members-custom/
includes/SOF/Presentation/Shortcode/
MembershipRenewalShortcode.php

=========================================================
RECOVERY POINT
==============

Recovery Point:

RP47

Proposed Git Tag:

v4.3.0-RP47

Milestone:

Production Validation and Membership Renewal Protection

=========================================================
END CHANGELOG
=============
