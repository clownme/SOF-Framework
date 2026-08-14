<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Library Shortcode
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Presentation
 *
 * Shortcode:
 *     Newsletter Library
 *
 * Purpose:
 *     Expose the Newsletter Library Workspace through a
 *     WordPress shortcode.
 *
 * Responsibilities:
 *     - Register the Newsletter Library shortcode
 *     - Delegate presentation to the Newsletter Library Workspace
 *
 * Does NOT:
 *     - Retrieve Newsletters
 *     - Apply Newsletter business rules
 *     - Persist Newsletter state
 *     - Render Newsletter delivery HTML
 *
 * ============================================================
 */

class SOF_NewsletterLibraryShortcode
{
    /**
     * Register shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_newsletter_library',
            [
                self::class,
                'render',
            ]
        );
    }

    /**
     * Render shortcode.
     */
    public static function render(): string
    {
        // -------------------------------------------------
        // Newsletter Presentation Styles
        // -------------------------------------------------

        $css_path =
            SOF_NEWSLETTERS_PATH .
            '/Presentation/Assets/newsletter-library-workspace.css';

        $css_url =
            plugins_url(
                'includes/SOF/Newsletters/Presentation/Assets/newsletter-library-workspace.css',
                COAI_PLUGIN_FILE
            );

        wp_enqueue_style(
            'sof-newsletter-library-workspace',
            $css_url,
            [],
            file_exists($css_path)
                ? filemtime($css_path)
                : '1.0.0'
        );

        return
            SOF_NewsletterLibraryWorkspace::render();
    }
}
SOF_NewsletterLibraryShortcode::register();
