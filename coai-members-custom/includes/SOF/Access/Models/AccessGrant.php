<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Grant
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Access Grant
 *
 * Purpose:
 *     Represent a business or platform capability granted
 *     to a person within an organizational scope.
 *
 * Responsibilities:
 *     - Identify the person receiving access
 *     - Identify the granted capability
 *     - Identify the scope in which the capability applies
 *     - Identify the capability category
 *
 * Does NOT:
 *     - Authenticate users
 *     - Define organizational responsibilities
 *     - Discover people
 *     - Determine business behavior
 *     - Render access controls
 *
 * ============================================================
 */

class SOF_AccessGrant
{
    public const CATEGORY_BUSINESS = 'business';

    public const CATEGORY_PLATFORM = 'platform';

    protected int $person_id;

    protected string $capability;

    protected string $category;

    protected SOF_OrganizationalScope $scope;

    public function __construct(
        int $person_id,
        string $capability,
        string $category,
        SOF_OrganizationalScope $scope
    ) {
        $this->person_id = $person_id;
        $this->capability = trim($capability);
        $this->category = trim($category);
        $this->scope = $scope;
    }

    public function get_person_id(): int
    {
        return $this->person_id;
    }

    public function get_capability(): string
    {
        return $this->capability;
    }

    public function get_category(): string
    {
        return $this->category;
    }

    public function get_scope(): SOF_OrganizationalScope
    {
        return $this->scope;
    }

    public function is_business_capability(): bool
    {
        return $this->category === self::CATEGORY_BUSINESS;
    }

    public function is_platform_capability(): bool
    {
        return $this->category === self::CATEGORY_PLATFORM;
    }
}