<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Presentation
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Presentation
 *
 * Purpose:
 *     Load the presentation components required by the
 *     Access experience.
 *
 * ============================================================
 */

// -------------------------------------------------
// Workspaces
// -------------------------------------------------

require_once __DIR__ .
    '/Workspaces/AccessWorkspace.php';

// -------------------------------------------------
// Shortcodes
// -------------------------------------------------

require_once __DIR__ .
    '/Shortcodes/AccessWorkspaceShortcode.php';
    
// -------------------------------------------------
// CSS
// -------------------------------------------------

function sof_access_enqueue_assets(): void
{
    wp_enqueue_style(
        'sof-access-workspace',
        plugins_url(
            'Assets/access-workspace.css',
            __FILE__
        ),
        [],
        '1.0.0'
    );
}

add_action(
    'wp_enqueue_scripts',
    'sof_access_enqueue_assets'
);