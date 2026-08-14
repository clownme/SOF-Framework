<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Organizational Assignment
 * ============================================================
 *
 * Framework:
 *     Organization
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Assignment
 *
 * Purpose:
 *     Represent an organizational responsibility assigned
 *     to a person within an organizational scope.
 *
 * Examples:
 *     - Regional Vice President
 *       within South Central Region
 *
 *     - Membership Coordinator
 *       within Entire Organization
 *
 *     - Chapter President
 *       within Chapter 12
 *
 * Responsibilities:
 *     - Identify the assigned person
 *     - Identify the organizational responsibility
 *     - Identify the scope of that responsibility
 *
 * Does NOT:
 *     - Grant capabilities
 *     - Determine access
 *     - Authenticate users
 *     - Discover audience members
 *     - Define business experience behavior
 *
 * ============================================================
 */

class SOF_OrganizationalAssignment
{
    protected int $person_id;

    protected string $responsibility;

    protected SOF_OrganizationalScope $scope;

    public function __construct(
        int $person_id,
        string $responsibility,
        SOF_OrganizationalScope $scope
    ) {
        $this->person_id = $person_id;
        $this->responsibility = trim($responsibility);
        $this->scope = $scope;
    }

    public function get_person_id(): int
    {
        return $this->person_id;
    }

    public function get_responsibility(): string
    {
        return $this->responsibility;
    }

    public function get_scope(): SOF_OrganizationalScope
    {
        return $this->scope;
    }
}