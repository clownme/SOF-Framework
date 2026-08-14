<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Memory Framework
 * ============================================================
 *
 * Framework:
 *     Memory
 *
 * Purpose:
 *     Enable SOF to preserve meaningful organizational Events
 *     so that Timelines, Memory, and future organizational
 *     learning may be assembled from what actually occurred.
 *
 * Responsibilities:
 *     - Load the Event model
 *     - Load Event persistence
 *     - Load Event recording services
 *     - Provide the foundation for organizational Memory
 *
 * Does NOT:
 *     - Own business-domain behavior
 *     - Interpret Events
 *     - Assess Situations
 *     - Recommend business actions
 *     - Render presentation
 *
 * Principle:
 *     Situations are temporary.
 *     Events are permanent.
 *
 * ============================================================
 */

// -------------------------------------------------
// Framework Paths
// -------------------------------------------------

define(
    'SOF_MEMORY_PATH',
    __DIR__
);

// -------------------------------------------------
// Models
// -------------------------------------------------

require_once SOF_MEMORY_PATH .
    '/Models/Event.php';

// -------------------------------------------------
// Repositories
// -------------------------------------------------

require_once SOF_MEMORY_PATH .
    '/Repositories/EventRepository.php';
    
// -------------------------------------------------
// Services
// -------------------------------------------------

require_once SOF_MEMORY_PATH .
    '/Services/EventService.php';