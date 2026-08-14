<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Organization Framework
 * ============================================================
 *
 * Purpose:
 *     Represent organizational knowledge required by
 *     SOF business experiences.
 *
 * Initial Responsibility:
 *     - Represent organizational scope
 *
 * Architectural Direction:
 *
 *     Person
 *       ↓
 *     Organizational Responsibility
 *       ↓
 *     Scope
 *       ↓
 *     Capability
 *       ↓
 *     Business Experience
 *
 * Organization describes the business structure.
 *
 * Access determines what a person may do.
 *
 * Business domains determine what actions mean.
 *
 * ============================================================
 */

define(
    'SOF_ORGANIZATION_PATH',
    __DIR__
);

// -------------------------------------------------
// Models
// -------------------------------------------------

require_once SOF_ORGANIZATION_PATH .
    '/Models/Scope.php';
    
require_once SOF_ORGANIZATION_PATH .
    '/Models/Relationship.php';

require_once SOF_ORGANIZATION_PATH .
    '/Models/Assignment.php';

// -------------------------------------------------
// Services
// -------------------------------------------------

require_once SOF_ORGANIZATION_PATH .
    '/Services/AssignmentService.php';
    
require_once SOF_ORGANIZATION_PATH .
    '/Services/ScopeService.php';