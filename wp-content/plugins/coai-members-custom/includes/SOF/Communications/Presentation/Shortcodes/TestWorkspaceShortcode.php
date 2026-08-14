<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Test Workspace Shortcode
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Shortcode:
 *     sof_test_workspace
 *
 * Purpose:
 *     Expose the Test Communication Workspace through
 *     WordPress Presentation.
 *
 * ============================================================
 */

class SOF_TestWorkspaceShortcode
{
    /**
     * Register the shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_test_workspace',
            [self::class, 'render']
        );
    }

    /**
     * Render the Test Communication Workspace.
     */
    public static function render(): string
    {
        return SOF_TestWorkspace::render();
    }
}