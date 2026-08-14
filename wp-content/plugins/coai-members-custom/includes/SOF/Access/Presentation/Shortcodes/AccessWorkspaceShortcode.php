<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Workspace Shortcode
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Presentation
 *
 * Purpose:
 *     Expose the Access Workspace through WordPress.
 *
 * ============================================================
 */

class SOF_AccessWorkspaceShortcode
{
    public static function register(): void
    {
        add_shortcode(
            'sof_access_workspace',
            [self::class, 'render']
        );
    }

    public static function render(): string
    {
        $workspace =
            new SOF_AccessWorkspace();

        return $workspace->render();
    }
}

add_action(
    'init',
    ['SOF_AccessWorkspaceShortcode', 'register']
);