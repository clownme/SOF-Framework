<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Organizational Scope
 * ============================================================
 *
 * Framework:
 *     Organization
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Scope
 *
 * Purpose:
 *     Represent the organizational boundary within which
 *     a responsibility or business capability applies.
 *
 * Examples:
 *     - Entire Organization
 *     - South Central Region
 *     - Membership Department
 *     - Chapter 12
 *     - Eastern Territory
 *
 * Responsibilities:
 *     - Identify a scope
 *     - Describe the type of scope
 *     - Provide a human-readable scope name
 *
 * Does NOT:
 *     - Grant access
 *     - Define capabilities
 *     - Determine organizational responsibilities
 *     - Discover people within the scope
 *     - Enforce authorization
 *
 * ============================================================
 */

class SOF_OrganizationalScope
{
    protected string $key;

    protected string $type;

    protected string $name;

    public function __construct(
        string $key,
        string $type,
        string $name
    ) {
        $this->key = trim($key);
        $this->type = trim($type);
        $this->name = trim($name);
    }

    public function get_key(): string
    {
        return $this->key;
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