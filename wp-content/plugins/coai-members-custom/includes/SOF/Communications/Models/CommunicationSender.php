<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Sender
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Purpose:
 *     Represent the organizational identity of the person
 *     sending a Communication.
 *
 * Responsibilities:
 *     - Identify the sender
 *     - Describe the sender's organizational role
 *     - Describe the sender's organizational scope
 *     - Provide the sender's communication address
 *
 * Does NOT:
 *     - Authenticate users
 *     - Discover member records
 *     - Discover organizational assignments
 *     - Format communication content
 *     - Deliver communications
 *
 * ============================================================
 */

class SOF_CommunicationSender
{
    protected int $member_id;

    protected string $name;

    protected string $role;

    protected string $scope;

    protected string $email;

    public function __construct(
        int $member_id,
        string $name,
        string $role,
        string $scope,
        string $email
    ) {
        $this->member_id = $member_id;

        $this->name = trim($name);

        $this->role = trim($role);

        $this->scope = trim($scope);

        $this->email = trim($email);
    }

    public function get_member_id(): int
    {
        return $this->member_id;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_role(): string
    {
        return $this->role;
    }

    public function get_scope(): string
    {
        return $this->scope;
    }

    public function get_email(): string
    {
        return $this->email;
    }

    /**
     * Return the sender's organizational title for
     * presentation.
     */
    public function get_display_title(): string
    {
        $scope =
            $this->scope;

        if (
            $scope !== '' &&
            $this->role === 'Regional Vice President'
        ) {
            $scope =
                preg_replace(
                    '/\s+Region$/i',
                   '',
                    $scope
                );
        }

        if (
            $scope !== '' &&
            $this->role !== ''
        ) {
            return trim(
                $scope .
                ' ' .
                $this->role
            );
       }

        return $this->role;
    }
}