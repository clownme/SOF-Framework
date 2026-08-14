<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Preview Workspace
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Presentation
 *
 * Workspace:
 *     Newsletter Preview
 *
 * Purpose:
 *     Present a Newsletter as it will appear when rendered
 *     for email delivery.
 *
 * Responsibilities:
 *     - Assemble temporary Newsletter preview information
 *     - Demonstrate structured Newsletter sections
 *     - Demonstrate Newsletter image support
 *     - Render the Newsletter through the HTML renderer
 *
 * Does NOT:
 *     - Persist Newsletter information
 *     - Determine Newsletter audiences
 *     - Determine Access authorization
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_NewsletterPreviewWorkspace
{
    /**
     * Render the preview Workspace.
     */
    public static function render(): string
    {
        $design = new SOF_NewsletterDesign(
           '#e9edf2',
            null,
            null,
            '#ffffff'
        );
        
        $newsletter = new SOF_Newsletter(
            null,
            'South Central Region Newsletter',
            'News from the South Central Region',
            'regional'
        );

        // -------------------------------------------------
        // Regional Vice President Message
        // -------------------------------------------------

        $newsletter->add_section(
            new SOF_NewsletterSection(
                'regional_message',
                'A Message from Your Regional Vice President',
                '<p>Welcome to our regional newsletter.</p>

                <p>
                    This is where the Regional Vice President can
                    share news, updates, encouragement, and other
                    information with members throughout the region.
                </p>'
            )
        );

        // -------------------------------------------------
        // Feature Story
        // -------------------------------------------------

        $newsletter->add_section(
            new SOF_NewsletterSection(
                'story',
                'Around the Region',
                '<p>
                    COAI members continue to share laughter,
                    education, fellowship, and service throughout
                    the South Central Region.
                </p>

                <p>
                    Newsletter stories can include photographs,
                    announcements, member highlights, and links
                    to additional information.
                </p>'
            )
        );

        // -------------------------------------------------
        // Upcoming Event
        // -------------------------------------------------

        $newsletter->add_section(
            new SOF_NewsletterSection(
                'event',
                'Upcoming Events',
                '<p>
                    Regional events and other important dates can
                    be highlighted here so members know what is
                    coming next.
                </p>',
                null,
                null,
                '',
                'above',
                home_url('/'),
                'Learn More'
            )
        );

        // -------------------------------------------------
        // Render Newsletter
        // -------------------------------------------------

        $renderer = new SOF_NewsletterHtmlRenderer();

        $newsletter_html =
            $renderer->render($newsletter);

        ob_start();
        ?>

        <div
            class="sof-newsletter-preview-workspace"
            style="
                max-width:1100px;
                margin:0 auto;
                padding:24px 16px;
            "
        >

            <div style="margin-bottom:24px;">

                <h1 style="margin-bottom:8px;">
                    Newsletter Preview
                </h1>

                <p style="
                    margin:0;
                    color:#4b5563;
                    font-size:16px;
                ">
                    Preview how this Newsletter will appear
                    when delivered to members.
                </p>

            </div>

            <?php echo $newsletter_html; ?>

        </div>

        <?php

        return ob_get_clean();
    }
}