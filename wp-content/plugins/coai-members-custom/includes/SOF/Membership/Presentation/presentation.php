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

function sof_membership_enqueue_presentation_assets(): void
{
    wp_enqueue_style(
        'sof-membership-renewal',
        plugins_url(
            'Assets/css/membership-renewal.css',
            __FILE__
        ),
        [],
        '1.0.0'
    );
}

add_action(
    'wp_enqueue_scripts',
    'sof_membership_enqueue_presentation_assets'
);

require_once __DIR__ .
    '/Shortcodes/MembershipRenewalShortcode.php';