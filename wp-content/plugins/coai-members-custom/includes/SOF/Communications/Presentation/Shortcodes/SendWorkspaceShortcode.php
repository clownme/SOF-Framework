<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Send Workspace Shortcode
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Shortcode:
 *     sof_send_workspace
 *
 * Purpose:
 *     Expose the Send Communication Workspace through
 *     WordPress Presentation.
 *
 * ============================================================
 */

class SOF_SendWorkspaceShortcode
{
    public static function register(): void
    {
        add_shortcode(
            'sof_send_workspace',
            [self::class, 'render']
        );
    }

    public static function render(): string
    {
        return SOF_SendWorkspace::render();
    }
}