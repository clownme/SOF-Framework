<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Framework
 * ============================================================
 *
 * Purpose:
 *     Allow SOF to understand Zeffy payment transactions and
 *     route them into the appropriate business processes.
 *
 * Responsibilities:
 *     - Represent Zeffy transactions
 *     - Access the Zeffy transaction ledger
 *     - Assess Zeffy business events
 *     - Support Renewal and New Membership workflows
 *
 * Does NOT:
 *     - Own Zeffy API credentials
 *     - Render WP-admin importer screens
 *     - Directly update member records
 *
 * Architectural Principle:
 *
 *     Zeffy provides payment facts.
 *     SOF determines business meaning.
 *
 * ============================================================
 */

define(
    'SOF_ZEFFY_PATH',
    __DIR__
);

// -------------------------------------------------
// Models
// -------------------------------------------------

require_once SOF_ZEFFY_PATH .
    '/Models/ZeffyTransaction.php';
    
require_once SOF_ZEFFY_PATH .
    '/Models/ZeffyRenewalManagementDecision.php';

// -------------------------------------------------
// Repositories
// -------------------------------------------------

require_once SOF_ZEFFY_PATH .
    '/Repositories/ZeffyTransactionRepository.php';
    
require_once SOF_ZEFFY_PATH .
    '/Repositories/ZeffyRenewalManagementDecisionRepository.php';

// -------------------------------------------------
// Services
// -------------------------------------------------

require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyRenewalAssessmentService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyRenewalBusinessAssessmentService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyNewMembershipReviewService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyMembershipRenewalCandidateAdapter.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyNewMembershipBusinessAssessmentService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyRenewalManagementDecisionService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyRenewalApplicationService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyRenewalReviewService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyRenewalIdentityService.php';
    
require_once SOF_ZEFFY_PATH .
    '/Services/ZeffyIdentityResolutionService.php';
    
// -------------------------------------------------
// Workspaces
// -------------------------------------------------

require_once SOF_ZEFFY_PATH .
    '/Presentation/Workspaces/RenewalManagementReviewWorkspace.php';
    
require_once SOF_ZEFFY_PATH .
    '/Presentation/Workspaces/NewMembershipManagementReviewWorkspace.php';
    
// -------------------------------------------------
// Shortcodes
// -------------------------------------------------

require_once SOF_ZEFFY_PATH .
    '/Presentation/Shortcodes/RenewalManagementReviewShortcode.php';
    
require_once SOF_ZEFFY_PATH .
    '/Presentation/Shortcodes/NewMembershipManagementReviewShortcode.php';