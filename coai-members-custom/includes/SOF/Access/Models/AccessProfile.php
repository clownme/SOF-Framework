<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Profile
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Access Profile
 *
 * Purpose:
 *     Represent a reusable collection of business capabilities
 *     that may be assigned to a person.
 *
 * Examples:
 *     - Administrator
 *     - Manager
 *     - Membership Coordinator
 *     - Regional Representative
 *
 * Important:
 *     The profile name is organizational terminology.
 *     Access decisions are based on capabilities.
 *
 * Does NOT:
 *     - Represent membership status
 *     - Determine organizational scope
 *     - Authenticate users
 *     - Render access controls
 *
 * ============================================================
 */

class SOF_AccessProfile
{
    protected string $key;

    protected string $name;

    protected string $description;

    /**
     * @var array<int, string>
     */
    protected array $capabilities;

    public function __construct(
        string $key,
        string $name,
        string $description = '',
        array $capabilities = []
    ) {
        $this->key = trim($key);
        $this->name = trim($name);
        $this->description = trim($description);

        $this->capabilities =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'trim',
                            $capabilities
                        )
                    )
                )
            );
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

    /**
     * @return array<int, string>
     */
    public function get_capabilities(): array
    {
        return $this->capabilities;
    }

    public function has_capability(
        string $capability
    ): bool {
        return in_array(
            $capability,
            $this->capabilities,
            true
        );
    }
}