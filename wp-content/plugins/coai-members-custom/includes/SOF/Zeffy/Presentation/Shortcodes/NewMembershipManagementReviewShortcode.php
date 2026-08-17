<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy New Membership Management Review Shortcode
 * ============================================================
 *
 * Purpose:
 *     Provide the WordPress shortcode entry point for the
 *     New Membership Management Review workspace.
 *
 * Does NOT:
 *     - Assess business rules
 *     - Resolve member identity
 *     - Update member records
 *     - Process transactions
 *
 * ============================================================
 */

class SOF_ZeffyNewMembershipManagementReviewShortcode
{
    /**
     * Register shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_new_membership_management_review',
            [
                self::class,
                'render',
            ]
        );
    }

    /**
     * Render workspace.
     */
    public static function render(
        array $atts = []
    ): string {

        if (
            !class_exists(
                'SOF_ZeffyNewMembershipManagementReviewWorkspace'
            )
        ) {
            return '<p>New Membership Management Review is not available.</p>';
        }

        return
            SOF_ZeffyNewMembershipManagementReviewWorkspace::render();
    }
}

SOF_ZeffyNewMembershipManagementReviewShortcode::register();