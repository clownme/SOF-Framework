<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Framework
 * ============================================================
 *
 * Purpose:
 *     Provide the business foundation for determining what
 *     people are authorized to do.
 *
 * Principle:
 *     Access is expressed through business capabilities.
 *
 * Architecture:
 *
 *     Person
 *       ↓
 *     Access Profile / Capability Grants
 *       ↓
 *     Capability
 *       ↓
 *     Scope
 *       ↓
 *     Business Experience
 *
 * ============================================================
 */

define(
    'SOF_ACCESS_PATH',
    __DIR__
);

// -------------------------------------------------
// Models
// -------------------------------------------------

require_once SOF_ACCESS_PATH .
    '/Models/AccessProfile.php';

require_once SOF_ACCESS_PATH .
    '/Models/AccessGrant.php';
    
require_once SOF_ACCESS_PATH .
    '/Models/Capability.php';

require_once SOF_ACCESS_PATH .
    '/Models/AccessAssignment.php';

require_once SOF_ACCESS_PATH .
    '/Models/AccessScope.php';

// -------------------------------------------------
// Repositories
// -------------------------------------------------

require_once SOF_ACCESS_PATH .
    '/Services/AccessService.php';

require_once SOF_ACCESS_PATH .
    '/Repositories/AccessGrantRepository.php';

require_once SOF_ACCESS_PATH .
    '/Repositories/AccessAssignmentRepository.php';

// -------------------------------------------------
// Services
// -------------------------------------------------

require_once SOF_ACCESS_PATH .
    '/Services/CapabilityService.php';

require_once SOF_ACCESS_PATH .
    '/Services/AccessProfileService.php';

require_once SOF_ACCESS_PATH .
    '/Services/AccessScopeService.php';

require_once SOF_ACCESS_PATH .
    '/Services/AccessGrantService.php';

// -------------------------------------------------
// Presentation
// -------------------------------------------------

require_once SOF_ACCESS_PATH .
    '/Presentation/presentation.php';
    
    
    