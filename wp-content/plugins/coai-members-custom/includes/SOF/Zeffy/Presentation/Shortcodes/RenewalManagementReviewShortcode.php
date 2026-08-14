<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Management Review Shortcode
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Layer:
 *     Presentation
 *
 * Purpose:
 *     Expose the Renewal Management Review workspace through
 *     a frontend shortcode.
 *
 * ============================================================
 */

class SOF_ZeffyRenewalManagementReviewShortcode
{
    public static function register(): void
    {
        add_shortcode(
            'sof_zeffy_renewal_management_review',
            [self::class, 'render']
        );
    }

    public static function render(): string
    {
        if (
            !class_exists(
                'SOF_ZeffyRenewalManagementReviewWorkspace'
            )
        ) {
            return '';
        }

        return
            SOF_ZeffyRenewalManagementReviewWorkspace::render();
    }
}

SOF_ZeffyRenewalManagementReviewShortcode::register();