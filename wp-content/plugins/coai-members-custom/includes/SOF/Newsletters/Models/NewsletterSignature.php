<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Signature
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Newsletter Signature
 *
 * Purpose:
 *     Represent the optional signature presented at the end
 *     of a Newsletter.
 *
 * Responsibilities:
 *     - Preserve signature name
 *     - Preserve signature title
 *     - Preserve optional signature image
 *
 * Does NOT:
 *     - Determine who should sign a Newsletter
 *     - Resolve organizational responsibility
 *     - Render Newsletter HTML
 *     - Persist Newsletter information
 *
 * ============================================================
 */

class SOF_NewsletterSignature
{
    private string $name;

    private string $title;

    private ?int $image_attachment_id;

    private ?string $image_url;

    public function __construct(
        string $name = '',
        string $title = '',
        ?int $image_attachment_id = null,
        ?string $image_url = null
    ) {
        $this->name = $name;
        $this->title = $title;

        $this->image_attachment_id =
            $image_attachment_id;

        $this->image_url =
            $image_url;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_title(): string
    {
        return $this->title;
    }

    public function get_image_attachment_id(): ?int
    {
        return $this->image_attachment_id;
    }

    public function get_image_url(): ?string
    {
        return $this->image_url;
    }

    public function has_content(): bool
    {
        return
            trim($this->name) !== '' ||
            trim($this->title) !== '' ||
            $this->image_attachment_id !== null ||
            (
                $this->image_url !== null &&
                trim($this->image_url) !== ''
            );
    }
}