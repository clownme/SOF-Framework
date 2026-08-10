<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Preview Shortcode
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Presentation
 *
 * Shortcode:
 *     Newsletter Preview
 *
 * Purpose:
 *     Expose the Newsletter Preview Workspace through WordPress.
 *
 * ============================================================
 */

class SOF_NewsletterPreviewShortcode
{
    /**
     * Register shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_newsletter_preview',
            [self::class, 'render']
        );
    }

    /**
     * Render shortcode.
     */
    public static function render(): string
    {
        return SOF_NewsletterPreviewWorkspace::render();
    }
}

SOF_NewsletterPreviewShortcode::register();