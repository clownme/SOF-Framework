<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Approve Workspace Shortcode
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Shortcode:
 *     sof_approve_workspace
 *
 * Purpose:
 *     Expose the Approve Communication Workspace through
 *     WordPress Presentation.
 *
 * ============================================================
 */

class SOF_ApproveWorkspaceShortcode
{
    public static function register(): void
    {
        add_shortcode(
            'sof_approve_workspace',
            [self::class, 'render']
        );
    }

    public static function render(): string
    {
        return SOF_ApproveWorkspace::render();
    }
}