<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Framework
 * ============================================================
 *
 * Purpose:
 *     Provide Membership business knowledge and capabilities
 *     to SOF experiences.
 *
 * Responsibilities:
 *     - Load Membership business services
 *     - Provide Membership-owned audience discovery
 *     - Provide Membership-owned member search
 *
 * Does NOT:
 *     - Determine Communication behavior
 *     - Determine Access authorization
 *     - Render presentation
 *     - Manage organizational responsibilities
 *
 * Architectural Principle:
 *
 *     Membership owns knowledge about members.
 *
 *     Other SOF frameworks may consume Membership
 *     capabilities without owning Membership data rules.
 *
 * ============================================================
 */

define(
    'SOF_MEMBERSHIP_PATH',
    __DIR__
);

// -------------------------------------------------
// Knowledge
// -------------------------------------------------

require_once SOF_MEMBERSHIP_PATH .
    '/Knowledge/MembershipRegionKnowledge.php';

require_once SOF_MEMBERSHIP_PATH .
    '/Knowledge/MembershipCountryKnowledge.php';

// -------------------------------------------------
// Services
// -------------------------------------------------

require_once SOF_MEMBERSHIP_PATH .
    '/Services/MemberSearchService.php';
    
require_once SOF_MEMBERSHIP_PATH .
    '/Services/MemberLookupService.php';

require_once SOF_MEMBERSHIP_PATH .
    '/Services/MembershipAudienceService.php';
    
require_once SOF_MEMBERSHIP_PATH .
    '/Services/MembershipRenewalService.php';

// -------------------------------------------------
// Integration
// -------------------------------------------------

require_once SOF_MEMBERSHIP_PATH .
    '/Integration/WordPressMemberResolver.php';

// -------------------------------------------------
// Presentation
// -------------------------------------------------

require_once SOF_MEMBERSHIP_PATH .
    '/Presentation/presentation.php';    
