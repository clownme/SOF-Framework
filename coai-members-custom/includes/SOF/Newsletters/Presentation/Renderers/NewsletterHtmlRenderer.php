<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter HTML Renderer
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Presentation
 *
 * Renderer:
 *     Newsletter HTML
 *
 * Purpose:
 *     Transform structured Newsletter information into
 *     email-compatible HTML.
 *
 * Responsibilities:
 *     - Render Newsletter structure
 *     - Render Newsletter sections
 *     - Render images safely
 *     - Render optional actions
 *     - Produce responsive email-compatible HTML
 *
 * Does NOT:
 *     - Determine Newsletter audience
 *     - Determine Access
 *     - Persist Newsletter information
 *     - Deliver email
 *
 * ============================================================
 */

class SOF_NewsletterHtmlRenderer
{
    /**
     * Render a Newsletter.
     */
    public function render(
        SOF_Newsletter $newsletter
    ): string {

        $sections_html = '';

        foreach ($newsletter->get_sections() as $section) {
            $sections_html .=
                $this->render_section($section);
        }

        $signature_html =
            $this->render_signature(
                $newsletter->get_signature()
            );

        $design = $newsletter->get_design();
        $background_color =
            $design->get_background_color();

        $content_background_color =
            $design->get_content_background_color();

        $background_image_url =
            $this->resolve_background_image($design);
            
        $header_logo_html =
            $this->render_header_logo($design);

        $title = esc_html(
            $newsletter->get_title()
        );
        
        $background_style =
            'background-color:' .
            esc_attr($background_color) .
            ';';

        if ($background_image_url) {
            $background_style .=
                'background-image:url(' .
                esc_url($background_image_url) .
                ');' .
                'background-position:center top;' .
                'background-repeat:repeat;';
        }

        return '
        <div style="
            margin:0;
            padding:24px 12px;
            ' . $background_style . '
            font-family:Arial,Helvetica,sans-serif;
            color:#1f2937;
        ">

            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                border="0"
            >
                <tr>
                    <td align="center">

                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                            style="
                                max-width:680px;
                                background:' .
                                    esc_attr($content_background_color) .
                                    ';
                                border-collapse:collapse;
                            "
                        >

                            <tr>
                                <td style="
                                    padding:32px 28px;
                                    text-align:center;
                                    background:#ffffff;
                                    border-bottom:4px solid #374151;
                                ">

                                    ' . $header_logo_html . '

                                    <div style="
                                        font-size:30px;
                                        line-height:1.2;
                                        font-weight:bold;
                                        color:#111827;
                                    ">
                                        ' . $title . '
                                    </div>

                                </td>
                            </tr>

                            ' . $sections_html . '
                            
                            ' . $signature_html . '

                            <tr>
                                <td style="
                                    padding:24px 28px;
                                    text-align:center;
                                    font-size:12px;
                                    line-height:1.6;
                                    color:#6b7280;
                                    border-top:1px solid #e5e7eb;
                                ">

                                    Clowns of America International

                                </td>
                            </tr>

                        </table>

                    </td>
                </tr>
            </table>

        </div>';
    }
    
    /**
     * Render the optional Newsletter header logo.
     */
    private function render_header_logo(
        SOF_NewsletterDesign $design
    ): string {

        $logo_url =
            $design->get_header_logo_url();

        if (
            !$logo_url &&
            $design->get_header_logo_attachment_id()
        ) {
            $resolved =
                wp_get_attachment_image_url(
                    $design->get_header_logo_attachment_id(),
                    'medium'
                );

            if ($resolved) {
                $logo_url = $resolved;
            }
        }

        if (!$logo_url) {

            return '
                <div style="
                    font-size:14px;
                    font-weight:bold;
                    letter-spacing:2px;
                    text-transform:uppercase;
                    color:#6b7280;
                    margin-bottom:10px;
                ">
                    COAI
                </div>
            ';
        }

        return '
            <div style="
                margin:0 0 16px;
                text-align:center;
            ">

                <img
                    src="' .
                        esc_url($logo_url) .
                    '"
                    alt="' .
                        esc_attr(
                            $design->get_header_logo_alt()
                        ) .
                    '"
                    style="
                        display:block;
                        width:auto;
                        max-width:220px;
                        max-height:110px;
                        height:auto;
                        margin:0 auto;
                        border:0;
                    "
                >

            </div>
        ';
    }

    /**
     * Resolve the optional Newsletter background image.
     */
    private function resolve_background_image(
        SOF_NewsletterDesign $design
    ): ?string {

        $image_url =
            $design->get_background_image_url();

        if (
            !$image_url &&
            $design->get_background_image_attachment_id()
        ) {
            $resolved = wp_get_attachment_image_url(
                $design->get_background_image_attachment_id(),
                'full'
            );

            if ($resolved) {
                $image_url = $resolved;
            }
        }

        return $image_url ?: null;
    }
    
        /**
     * Render the optional Newsletter signature.
     */
    private function render_signature(
        SOF_NewsletterSignature $signature
    ): string {

        if (!$signature->has_content()) {
            return '';
        }

        $image_html = '';

        $image_url =
            $signature->get_image_url();

        if (
            !$image_url &&
            $signature->get_image_attachment_id()
        ) {
            $resolved =
                wp_get_attachment_image_url(
                    $signature->get_image_attachment_id(),
                    'medium'
                );

            if ($resolved) {
                $image_url = $resolved;
            }
        }

        if ($image_url) {
            $image_html =
                '<div style="
                    margin:0 0 14px;
                ">
                    <img
                        src="' .
                            esc_url($image_url) .
                        '"
                        alt=""
                        style="
                            display:block;
                            max-width:180px;
                            height:auto;
                            border:0;
                        "
                    >
                </div>';
        }

        $name =
            trim($signature->get_name());

        $title =
            trim($signature->get_title());

        $name_html =
            $name !== ''
                ? '<div style="
                    margin:0 0 4px;
                    font-size:18px;
                    line-height:1.4;
                    font-weight:700;
                    color:#111827;
                ">' .
                    esc_html($name) .
                '</div>'
                : '';

        $title_html =
            $title !== ''
                ? '<div style="
                    margin:0;
                    font-size:14px;
                    line-height:1.5;
                    color:#6b7280;
                ">' .
                    esc_html($title) .
                '</div>'
                : '';

        return '
            <tr>
                <td style="
                    padding:28px;
                    border-top:1px solid #e5e7eb;
                ">

                    <div style="
                        margin:0 0 14px;
                        font-size:15px;
                        line-height:1.6;
                        color:#374151;
                    ">
                        Sincerely,
                    </div>

                    ' . $image_html . '

                    ' . $name_html . '

                    ' . $title_html . '

                </td>
            </tr>
        ';
    }

    /**
     * Render one Newsletter section.
     */
    private function render_section(
        SOF_NewsletterSection $section
    ): string {

        $heading = '';

        if ($section->get_heading() !== '') {
            $heading = '
                <div style="
                    font-size:22px;
                    line-height:1.3;
                    font-weight:bold;
                    color:#111827;
                    margin-bottom:14px;
                ">
                    ' .
                    esc_html($section->get_heading()) .
                    '
                </div>';
        }

        $image = $this->render_image($section);

        $content = '';

        if ($section->get_content() !== '') {
            $content = '
                <div style="
                    font-size:16px;
                    line-height:1.7;
                    color:#374151;
                ">
                    ' .
                    wpautop(
                        wp_kses_post(
                            $section->get_content()
                        )
                    ) .
                    '
                </div>';
        }

        $action = $this->render_action($section);

        return '
            <tr>
                <td style="
                    padding:28px;
                    border-bottom:1px solid #e5e7eb;
                ">

                    ' . $heading . '

                    ' . $image . '

                    ' . $content . '

                    ' . $action . '

                </td>
            </tr>';
    }

    /**
     * Render an optional section image.
     */
    private function render_image(
        SOF_NewsletterSection $section
    ): string {

        $image_url = $section->get_image_url();

        if (
            !$image_url &&
            $section->get_image_attachment_id()
        ) {
            $resolved = wp_get_attachment_image_url(
                $section->get_image_attachment_id(),
                'large'
            );

            if ($resolved) {
                $image_url = $resolved;
            }
        }

        if (!$image_url) {
            return '';
        }

        return '
            <div style="
                margin:0 0 20px;
                text-align:center;
            ">

                <img
                    src="' . esc_url($image_url) . '"
                    alt="' .
                    esc_attr($section->get_image_alt()) .
                    '"
                    width="624"
                    style="
                        display:block;
                        width:100%;
                        max-width:624px;
                        height:auto;
                        margin:0 auto;
                        border:0;
                    "
                >

            </div>';
    }

    /**
     * Render an optional action button.
     */
    private function render_action(
        SOF_NewsletterSection $section
    ): string {

        if (
            !$section->get_link_url() ||
            !$section->get_link_label()
        ) {
            return '';
        }

        return '
            <table
                role="presentation"
                cellspacing="0"
                cellpadding="0"
                border="0"
                width="auto"
                style="
                    width:auto !important;
                    max-width:none !important;
                    margin-top:22px;
                    border-collapse:collapse;
                "
            >
                <tr>
                    <td
                        bgcolor="#374151"
                        style="
                            background:#374151;
                            border-radius:4px;
                            text-align:center;
                        "
                    >
                        <a
                            class="sof-newsletter-action-button"
                            href="' .
                            esc_url($section->get_link_url()) .
                            '"
                            style="
                                display:block;
                                padding:12px 20px;
                                color:#ffffff;
                                text-decoration:none;
                                font-size:15px;
                                font-weight:bold;
                                line-height:1.2;
                                border:0;
                            "
                        >
                            ' .
                            esc_html($section->get_link_label()) .
                            '
                        </a>
                    </td>
                </tr>
            </table>';
    }
}