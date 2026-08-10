<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Section
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Newsletter Section
 *
 * Purpose:
 *     Represent one structured content section within a
 *     Newsletter.
 *
 * Responsibilities:
 *     - Identify the type of Newsletter content
 *     - Represent section heading
 *     - Represent section content
 *     - Represent optional image
 *     - Represent optional action link
 *
 * Does NOT:
 *     - Render HTML
 *     - Upload images
 *     - Send Communications
 *     - Determine recipients
 *
 * ============================================================
 */

class SOF_NewsletterSection
{
    private string $type;

    private string $heading;

    private string $content;

    private ?int $image_attachment_id;

    private ?string $image_url;

    private string $image_alt;

    private string $image_layout;

    private ?string $link_url;

    private ?string $link_label;

    public function __construct(
        string $type,
        string $heading = '',
        string $content = '',
        ?int $image_attachment_id = null,
        ?string $image_url = null,
        string $image_alt = '',
        string $image_layout = 'above',
        ?string $link_url = null,
        ?string $link_label = null
    ) {
        $this->type = trim($type);
        $this->heading = trim($heading);
        $this->content = $content;
        $this->image_attachment_id = $image_attachment_id;
        $this->image_url = $image_url;
        $this->image_alt = trim($image_alt);
        $this->image_layout = trim($image_layout);
        $this->link_url = $link_url;
        $this->link_label = $link_label;
    }

    public function get_type(): string
    {
        return $this->type;
    }

    public function get_heading(): string
    {
        return $this->heading;
    }

    public function get_content(): string
    {
        return $this->content;
    }

    public function get_image_attachment_id(): ?int
    {
        return $this->image_attachment_id;
    }

    public function get_image_url(): ?string
    {
        return $this->image_url;
    }

    public function get_image_alt(): string
    {
        return $this->image_alt;
    }

    public function get_image_layout(): string
    {
        return $this->image_layout;
    }

    public function get_link_url(): ?string
    {
        return $this->link_url;
    }

    public function get_link_label(): ?string
    {
        return $this->link_label;
    }
}