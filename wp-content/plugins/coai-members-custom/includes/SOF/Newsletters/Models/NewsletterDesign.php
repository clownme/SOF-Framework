<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Design
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Newsletter Design
 *
 * Purpose:
 *     Represent the visual design choices applied to a
 *     Newsletter.
 *
 * Responsibilities:
 *     - Represent Newsletter background color
 *     - Represent optional Newsletter background image
 *     - Represent content background color
 *     - Provide safe design defaults
 *
 * Does NOT:
 *     - Render Newsletter HTML
 *     - Upload or manage images
 *     - Store Newsletter content
 *     - Determine Newsletter audience
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_NewsletterDesign
{
    private string $background_color;

    private ?int $background_image_attachment_id;

    private ?string $background_image_url;

    private string $content_background_color;
    
    private ?int $header_logo_attachment_id;
    
    private ?string $header_logo_url;
    
    private string $header_logo_alt;

    public function __construct(
        string $background_color = '#f3f4f6',
        ?int $background_image_attachment_id = null,
        ?string $background_image_url = null,
        string $content_background_color = '#ffffff',
        ?int $header_logo_attachment_id = null,
        ?string $header_logo_url = null,
        string $header_logo_alt = ''
    ) {
        
        $this->background_color =
            $this->normalize_color(
                $background_color,
                '#f3f4f6'
            );

        $this->background_image_attachment_id =
            $background_image_attachment_id;

        $this->background_image_url =
            $background_image_url;

        $this->content_background_color =
            $this->normalize_color(
                $content_background_color,
                '#ffffff'
            );
            
        $this->header_logo_attachment_id =
            $header_logo_attachment_id;

        $this->header_logo_url =
            $header_logo_url;

        $this->header_logo_alt =
            trim($header_logo_alt);
    }

    public function get_background_color(): string
    {
        return $this->background_color;
    }
    
        public function get_header_logo_attachment_id(): ?int
    {
        return $this->header_logo_attachment_id;
    }

    public function get_header_logo_url(): ?string
    {
        return $this->header_logo_url;
    }

    public function get_header_logo_alt(): string
    {
        return $this->header_logo_alt;
    }

    public function get_background_image_attachment_id(): ?int
    {
        return $this->background_image_attachment_id;
    }

    public function get_background_image_url(): ?string
    {
        return $this->background_image_url;
    }

    public function get_content_background_color(): string
    {
        return $this->content_background_color;
    }

    /**
     * Normalize a CSS hex color and fall back safely.
     */
    private function normalize_color(
        string $color,
        string $fallback
    ): string {

        $color = trim($color);

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }

        return $fallback;
    }
}