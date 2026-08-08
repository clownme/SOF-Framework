<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Framework
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Purpose:
 *     Provide organizational membership knowledge and
 *     capabilities to SOF Business Experiences and other
 *     Knowledge Domains.
 *
 * Responsibilities:
 *     - Own membership knowledge
 *     - Expose membership capabilities
 *     - Resolve membership audiences
 *     - Provide membership facts to authorized consumers
 *
 * Does NOT:
 *     - Determine communication behavior
 *     - Render communication experiences
 *     - Send communications
 *
 * ============================================================
 */

// -------------------------------------------------
// Framework Paths
// -------------------------------------------------

define('SOF_MEMBERSHIP_PATH', __DIR__);

// -------------------------------------------------
// Knowledge
// -------------------------------------------------

require_once SOF_MEMBERSHIP_PATH .
    '/Knowledge/MembershipRegionKnowledge.php';
    
require_once SOF_MEMBERSHIP_PATH .
    '/Knowledge/MembershipCountryKnowledge.php';


// -------------------------------------------------
// Business Capabilities
// -------------------------------------------------

require_once SOF_MEMBERSHIP_PATH .
    '/Services/MembershipAudienceService.php';