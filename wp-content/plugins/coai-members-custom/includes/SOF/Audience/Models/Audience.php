<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Audience
 * ============================================================
 *
 * Framework:
 *     Audience
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Audience
 *
 * Purpose:
 *     Represent an organizational population defined by one
 *     or more organizational relationships.
 *
 * Business Question:
 *     Who are we engaging?
 *
 * Examples:
 *     - Entire Organization
 *     - South Central Region Members
 *     - Regional Vice Presidents
 *     - Finance Committee
 *     - Convention Attendees
 *     - Magazine Subscribers
 *
 * Responsibilities:
 *     - Identify the Audience
 *     - Describe the Audience
 *     - Identify the relationships defining the Audience
 *     - Identify the organizational scope, when applicable
 *
 * Does NOT:
 *     - Discover people
 *     - Resolve the current population
 *     - Determine authorization
 *     - Apply membership eligibility
 *     - Select Communication recipients
 *     - Deliver Communications
 *     - Render Presentation
 *
 * ============================================================
 */

class SOF_Audience
{
    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

    protected string $key;

    protected string $name;

    protected string $description;

    // -------------------------------------------------
    // Organizational Definition
    // -------------------------------------------------

    /**
     * Organizational relationship keys that define
     * this Audience.
     *
     * Examples:
     *
     * member
     * regional_vice_president
     * finance_committee_member
     * convention_attendee
     *
     * @var array<int, string>
     */
    protected array $relationship_keys;

    /**
     * Organizational boundary associated with
     * this Audience, when applicable.
     */
    protected ?SOF_OrganizationalScope $scope;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    public function __construct(
        string $key,
        string $name,
        string $description = '',
        array $relationship_keys = [],
        ?SOF_OrganizationalScope $scope = null
    ) {
        $this->key =
            trim(
                $key
            );

        $this->name =
            trim(
                $name
            );

        $this->description =
            trim(
                $description
            );

        $this->relationship_keys =
            $this->normalize_relationship_keys(
                $relationship_keys
            );

        $this->scope =
            $scope;
    }

    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Organizational Definition
    // -------------------------------------------------

    /**
     * Return the organizational relationship keys
     * defining this Audience.
     *
     * @return array<int, string>
     */
    public function get_relationship_keys(): array
    {
        return $this->relationship_keys;
    }

    public function has_relationships(): bool
    {
        return !empty(
            $this->relationship_keys
        );
    }

    public function includes_relationship(
        string $relationship_key
    ): bool {
        $relationship_key =
            strtolower(
                trim(
                    $relationship_key
                )
            );

        return in_array(
            $relationship_key,
            $this->relationship_keys,
            true
        );
    }

    // -------------------------------------------------
    // Organizational Scope
    // -------------------------------------------------

    public function get_scope(): ?SOF_OrganizationalScope
    {
        return $this->scope;
    }

    public function has_scope(): bool
    {
        return $this->scope !== null;
    }

    // -------------------------------------------------
    // Normalization
    // -------------------------------------------------

    /**
     * Normalize relationship keys into a unique,
     * lower-case collection.
     *
     * @param array<int, mixed> $relationship_keys
     *
     * @return array<int, string>
     */
    protected function normalize_relationship_keys(
        array $relationship_keys
    ): array {
        $normalized = [];

        foreach ($relationship_keys as $relationship_key) {

            if (!is_scalar($relationship_key)) {
                continue;
            }

            $relationship_key =
                strtolower(
                    trim(
                        (string) $relationship_key
                    )
                );

            if ($relationship_key === '') {
                continue;
            }

            $normalized[] =
                $relationship_key;
        }

        return array_values(
            array_unique(
                $normalized
            )
        );
    }
}