<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Audience Framework
 * ============================================================
 *
 * Framework:
 *     Audience
 *
 * Purpose:
 *     Represent and understand organizational populations
 *     defined by organizational relationships.
 *
 * Business Question:
 *     Who are we engaging?
 *
 * Initial Responsibility:
 *     - Represent an Audience
 *
 * Architectural Direction:
 *
 *     Person
 *       ↓
 *     Organizational Relationship
 *       ↓
 *     Audience
 *       ↓
 *     Audience Situation
 *       ↓
 *     Audience Assessment
 *       ↓
 *     Audience Recommendation
 *       ↓
 *     Available Actions
 *
 * Organization defines relationships.
 *
 * Access determines which Audiences a person may use.
 *
 * Audience understands the organizational population.
 *
 * Communications consumes an Audience.
 *
 * Does NOT:
 *     - Grant access
 *     - Compose Communications
 *     - Deliver Communications
 *     - Render Presentation
 *
 * ============================================================
 */

define(
    'SOF_AUDIENCE_PATH',
    __DIR__
);

// -------------------------------------------------
// Models
// -------------------------------------------------

require_once SOF_AUDIENCE_PATH .
    '/Models/Audience.php';