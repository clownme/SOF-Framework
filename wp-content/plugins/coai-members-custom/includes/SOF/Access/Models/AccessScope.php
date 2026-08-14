<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Scope
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Access Scope
 *
 * Purpose:
 *     Represent where granted access applies.
 *
 * Scope Types:
 *     - Entire Organization
 *     - Specific Scope
 *
 * Does NOT:
 *     - Grant capabilities
 *     - Discover organizational assignments
 *     - Define organization-specific areas
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AccessScope
{
    public const TYPE_ORGANIZATION = 'organization';

    public const TYPE_SPECIFIC_SCOPE = 'specific_scope';

    protected string $type;

    protected string $name;

    public function __construct(
        string $type,
        string $name
    ) {
        $this->type = trim($type);
        $this->name = trim($name);
    }

    public function get_type(): string
    {
        return $this->type;
    }

    public function get_name(): string
    {
        return $this->name;
    }
}