<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Assignment
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Access Assignment
 *
 * Purpose:
 *     Represent the SOF Access Profile assigned to a person.
 *
 * Responsibilities:
 *     - Identify the person receiving SOF access
 *     - Identify the assigned Access Profile
 *
 * Does NOT:
 *     - Define capabilities
 *     - Grant capabilities
 *     - Determine organizational scope
 *     - Modify legacy usergroups
 *     - Persist itself
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AccessAssignment
{
    protected int $person_id;

    protected string $profile_key;

    public function __construct(
        int $person_id,
        string $profile_key
    ) {
        $this->person_id =
            $person_id;

        $this->profile_key =
            sanitize_key(
                $profile_key
            );
    }

    public function get_person_id(): int
    {
        return $this->person_id;
    }

    public function get_profile_key(): string
    {
        return $this->profile_key;
    }
}