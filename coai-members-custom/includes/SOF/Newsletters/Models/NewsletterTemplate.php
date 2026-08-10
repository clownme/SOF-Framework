<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Template
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Newsletter Template
 *
 * Purpose:
 *     Describe an available Newsletter design.
 *
 * Responsibilities:
 *     - Identify a Newsletter template
 *     - Provide its human-readable name
 *     - Describe its intended use
 *
 * Does NOT:
 *     - Render Newsletter HTML
 *     - Store Newsletter content
 *     - Determine recipients
 *     - Send Communications
 *
 * ============================================================
 */

class SOF_NewsletterTemplate
{
    private string $key;

    private string $name;

    private string $description;

    public function __construct(
        string $key,
        string $name,
        string $description = ''
    ) {
        $this->key = trim($key);
        $this->name = trim($name);
        $this->description = trim($description);
    }

    public function get_key(): string
    {
        return $this->key;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_description(): string
    {
        return $this->description;
    }
}