<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Compose Newsletter Shortcode
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Presentation
 *
 * Shortcode:
 *     Compose Newsletter
 *
 * Purpose:
 *     Expose the Compose Newsletter Workspace through WordPress.
 *
 * Responsibilities:
 *     - Register the Compose Newsletter shortcode
 *     - Load Compose Newsletter presentation assets
 *     - Expose the Compose Newsletter Workspace
 *
 * Does NOT:
 *     - Compose Newsletter business information
 *     - Persist Newsletter information
 *     - Determine recipients
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_ComposeNewsletterShortcode
{
    /**
     * Register the shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_compose_newsletter',
            [self::class, 'render']
        );
    }

    /**
     * Render the Compose Newsletter Workspace.
     */
    public static function render(): string
    {
        // -------------------------------------------------
        // Workspace Styles
        // -------------------------------------------------

        $css_path =
            SOF_NEWSLETTERS_PATH .
            '/Presentation/Assets/newsletter-compose-workspace.css';

        $css_url =
            plugins_url(
                'includes/SOF/Newsletters/Presentation/Assets/newsletter-compose-workspace.css',
                COAI_PLUGIN_FILE
            );

        wp_enqueue_style(
            'sof-newsletter-compose-workspace',
            $css_url,
            [],
            file_exists($css_path)
                ? filemtime($css_path)
                : '1.0.0'
        );

        // -------------------------------------------------
        // WordPress Media Library
        // -------------------------------------------------

        wp_enqueue_media();

        // -------------------------------------------------
        // Workspace Scripts
        // -------------------------------------------------

        $js_path =
            SOF_NEWSLETTERS_PATH .
            '/Presentation/Assets/newsletter-compose-workspace.js';

        $js_url =
            plugins_url(
                'includes/SOF/Newsletters/Presentation/Assets/newsletter-compose-workspace.js',
                COAI_PLUGIN_FILE
            );

        wp_enqueue_script(
            'sof-newsletter-compose-workspace',
            $js_url,
            ['jquery'],
            file_exists($js_path)
                ? filemtime($js_path)
                : '1.0.0',
            true
        );

        return SOF_ComposeNewsletterWorkspace::render();
    }
}

SOF_ComposeNewsletterShortcode::register();