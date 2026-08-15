<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Shared Presentation
 * ============================================================
 *
 * Framework:
 *     SOF
 *
 * Layer:
 *     Presentation
 *
 * Purpose:
 *     Load reusable presentation assets shared across
 *     multiple SOF business experiences.
 *
 * Responsibilities:
 *     - Register shared SOF presentation assets
 *     - Load reusable card presentation
 *     - Support consistent presentation across SOF experiences
 *
 * Does NOT:
 *     - Apply business rules
 *     - Resolve business situations
 *     - Perform application actions
 *     - Replace experience-specific presentation assets
 *
 * ============================================================
 */


/* ------------------------------------------------------------
 * Shared Presentation Assets
 * ------------------------------------------------------------ */

function sof_enqueue_shared_presentation_assets(): void
{
    wp_enqueue_style(
        'sof-cards',
        plugins_url(
            'Assets/css/sof-cards.css',
            __FILE__
        ),
        [],
        '1.0.2'
    );
}


/* ------------------------------------------------------------
 * WordPress Registration
 * ------------------------------------------------------------ */

add_action(
    'wp_enqueue_scripts',
    'sof_enqueue_shared_presentation_assets'
);