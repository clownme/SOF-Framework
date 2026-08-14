<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Confirm Communication Workspace Shortcode
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Presentation
 *
 * Shortcode:
 *     [sof_confirm_workspace]
 *
 * Purpose:
 *     Expose the Confirm Communication workspace through
 *     a frontend WordPress shortcode.
 *
 * Responsibilities:
 *     - Register the Confirm Communication shortcode
 *     - Delegate rendering to ConfirmWorkspace
 *
 * Does NOT:
 *     - Load Communications
 *     - Determine release results
 *     - Modify Communication lifecycle state
 *     - Perform delivery
 *
 * ============================================================
 */

class SOF_ConfirmWorkspaceShortcode
{
    /**
     * Register shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_confirm_workspace',
            [self::class, 'render']
        );
    }

    /**
     * Render shortcode.
     */
    public static function render(): string
    {
        return SOF_ConfirmWorkspace::render();
    }
}