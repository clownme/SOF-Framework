<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communications Presentation Bootstrap
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Purpose:
 *     Load and register the Communications Presentation layer.
 *
 * Responsibilities:
 *     - Load communication cards
 *     - Load communication actions
 *     - Load communication workspaces
 *     - Load communication shortcodes
 *     - Register Presentation entry points
 *     - Load Presentation assets
 *
 * Does NOT:
 *     - Apply communication business rules
 *     - Build communication audiences
 *     - Deliver communications
 *     - Communicate directly with providers
 *
 * ============================================================
 */

// -------------------------------------------------
// Cards
// -------------------------------------------------

require_once __DIR__ .
    '/Cards/CommunicationWorkflowCard.php';

// -------------------------------------------------
// Actions
// -------------------------------------------------

require_once __DIR__ .
    '/Actions/CommunicationActions.php';

// -------------------------------------------------
// Workspaces
// -------------------------------------------------

require_once __DIR__ .
    '/Workspaces/CommunicationsWorkspace.php';

require_once __DIR__ .
    '/Workspaces/ComposeWorkspace.php';
    
require_once __DIR__ .
    '/Workspaces/VerifyWorkspace.php';
    
require_once __DIR__ .
    '/Workspaces/TestWorkspace.php';
    
require_once __DIR__ .
    '/Workspaces/ApproveWorkspace.php';

require_once __DIR__ .
    '/Workspaces/SendWorkspace.php';
    
require_once __DIR__ .
    '/Workspaces/ConfirmWorkspace.php';

// -------------------------------------------------
// Shortcodes
// -------------------------------------------------

require_once __DIR__ .
    '/Shortcodes/CommunicationsWorkspaceShortcode.php';

require_once __DIR__ .
    '/Shortcodes/ComposeWorkspaceShortcode.php';
    
require_once __DIR__ .
    '/Shortcodes/VerifyWorkspaceShortcode.php';
    
require_once __DIR__ .
    '/Shortcodes/TestWorkspaceShortcode.php';
    
require_once __DIR__ .
    '/Shortcodes/ApproveWorkspaceShortcode.php';
    
require_once __DIR__ .
    '/Shortcodes/SendWorkspaceShortcode.php';
    
require_once __DIR__ .
    '/Shortcodes/ConfirmWorkspaceShortcode.php';

// -------------------------------------------------
// Shortcode Registration
// -------------------------------------------------

add_action(
    'init',
    ['SOF_CommunicationsWorkspaceShortcode', 'register']
);

add_action(
    'init',
    ['SOF_ComposeWorkspaceShortcode', 'register']
);

add_action(
    'init',
    ['SOF_VerifyWorkspaceShortcode', 'register']
);

add_action(
    'init',
    ['SOF_TestWorkspaceShortcode', 'register']
);

add_action(
    'init',
    ['SOF_ApproveWorkspaceShortcode', 'register']
);

add_action(
    'init',
    ['SOF_SendWorkspaceShortcode', 'register']
);

add_action(
    'init',
    ['SOF_ConfirmWorkspaceShortcode', 'register']
);

// -------------------------------------------------
// Presentation Assets
// -------------------------------------------------

add_action(
    'wp_enqueue_scripts',
    function (): void {
        wp_enqueue_style(
            'sof-communications-workspace',
            plugins_url(
                'Assets/css/communications-workspace.css',
                __FILE__
            ),
            [],
            '1.0.2'
        );

        wp_enqueue_style(
            'sof-communication-workflow-card',
            plugins_url(
                'Assets/css/communication-workflow-card.css',
                __FILE__
            ),
            ['sof-communications-workspace'],
            '1.0.0'
        );
        
        wp_enqueue_style(
            'sof-compose-workspace',
            plugins_url(
                'Assets/css/compose-workspace.css',
                __FILE__
            ),
            ['sof-communications-workspace'],
            '1.0.1'
        );
    }
);