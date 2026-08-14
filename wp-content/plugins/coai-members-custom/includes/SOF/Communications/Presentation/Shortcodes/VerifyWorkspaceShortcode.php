<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Verify Workspace Shortcode
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Shortcode:
 *     sof_verify_workspace
 *
 * Purpose:
 *     Expose the Verify Communication Workspace through
 *     WordPress presentation.
 *
 * ============================================================
 */

class SOF_VerifyWorkspaceShortcode
{
    /**
     * Register the shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_verify_workspace',
            [self::class, 'render']
        );
    }

    /**
     * Render the Verify Communication Workspace.
     */
    public static function render(): string
    {
        return SOF_VerifyWorkspace::render();
    }
}