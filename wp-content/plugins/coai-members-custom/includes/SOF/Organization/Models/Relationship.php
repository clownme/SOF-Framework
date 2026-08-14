<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Organizational Relationship
 * ============================================================
 *
 * Framework:
 *     Organization
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Relationship
 *
 * Purpose:
 *     Represent how a person participates within an
 *     organization.
 *
 * Business Question:
 *     What is this person to the organization?
 *
 * Examples:
 *     - Member
 *     - Volunteer
 *     - Employee
 *     - Regional Vice President
 *     - Board Member
 *     - Committee Member
 *     - Convention Attendee
 *     - Magazine Subscriber
 *
 * Responsibilities:
 *     - Identify the person
 *     - Identify the organizational relationship
 *     - Describe the relationship
 *     - Identify the organizational scope, when applicable
 *
 * Does NOT:
 *     - Grant access
 *     - Define permissions
 *     - Determine capabilities
 *     - Discover audience populations
 *     - Compose communications
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_OrganizationalRelationship
{
    protected int $person_id;

    protected string $key;

    protected string $name;

    protected string $description;

    protected ?SOF_OrganizationalScope $scope;

    public function __construct(
        int $person_id,
        string $key,
        string $name,
        string $description = '',
        ?SOF_OrganizationalScope $scope = null
    ) {
        $this->person_id =
            max(
                0,
                $person_id
            );

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

        $this->scope =
            $scope;
    }

    public function get_person_id(): int
    {
        return $this->person_id;
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

    public function get_scope(): ?SOF_OrganizationalScope
    {
        return $this->scope;
    }

    public function has_scope(): bool
    {
        return $this->scope !== null;
    }
}