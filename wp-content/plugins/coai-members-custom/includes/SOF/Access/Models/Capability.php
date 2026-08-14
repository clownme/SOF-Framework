<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Capability
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Capability
 *
 * Purpose:
 *     Represent a business capability that may be granted
 *     to a person.
 *
 * Examples:
 *     - View Members
 *     - Edit Members
 *     - Export Members
 *     - Compose Communications
 *     - Release Communications
 *     - Manage Access
 *
 * Does NOT:
 *     - Determine who receives the capability
 *     - Define organizational roles
 *     - Determine organizational scope
 *     - Enforce presentation access
 *
 * ============================================================
 */

class SOF_Capability
{
    protected string $key;

    protected string $name;

    protected string $description;

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