<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Profile Service
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Access Profile
 *
 * Purpose:
 *     Provide the organization's standard access profiles
 *     and their default capability sets.
 *
 * Responsibilities:
 *     - Define standard access profiles
 *     - Provide default capability sets
 *     - Translate legacy organizational access labels
 *       into SOF Access Profiles
 *
 * Does NOT:
 *     - Persist access grants
 *     - Authenticate users
 *     - Determine organizational scope
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AccessProfileService
{
    /**
     * @return array<string, SOF_AccessProfile>
     */
    public function profiles(): array
    {
        return [

            'super_admin' =>
                new SOF_AccessProfile(
                    'super_admin',
                    'Super Admin',
                    'Complete organizational and platform administration.',
                    [
                        'view_members',
                        'add_members',
                        'edit_members',
                        'archive_members',
                        'remove_members',
                        'export_members',

                        'view_reports',
                        'export_reports',

                        'compose_communication',
                        'test_communication',
                        'approve_communication',
                        'release_communication',

                        'manage_access',

                        'access_wordpress_administration',
                        'manage_plugins',
                        'manage_themes',
                        'manage_wordpress_users',
                        'manage_newsletters',
                    ]
                ),

            'admin' =>
                new SOF_AccessProfile(
                    'admin',
                    'Admin',
                    'Complete organizational functions without general platform administration.',
                    [
                        'view_members',
                        'add_members',
                        'edit_members',
                        'archive_members',
                        'remove_members',
                        'export_members',

                        'view_reports',
                        'export_reports',

                        'compose_communication',
                        'test_communication',
                        'approve_communication',
                        'release_communication',

                        'manage_access',

                        'manage_newsletters',
                    ]
                ),

            'manager' =>
                new SOF_AccessProfile(
                    'manager',
                    'Manager',
                    'Operational management without restricted administrative functions.',
                    [
                        'view_members',
                        'add_members',
                        'edit_members',
                        'archive_members',
                        'export_members',

                        'view_reports',
                        'export_reports',

                        'compose_communication',
                        'test_communication',
                        'approve_communication',
                        'release_communication',

                        'manage_newsletters',
                    ]
                ),

            'member' =>
                new SOF_AccessProfile(
                    'member',
                    'Member',
                    'Standard member access.',
                    []
                ),

            'custom' =>
                new SOF_AccessProfile(
                    'custom',
                    'Custom',
                    'Access individually configured for this person.',
                    []
                ),
        ];
    }

    public function find(
        string $key
    ): ?SOF_AccessProfile {
        $profiles =
            $this->profiles();

        return $profiles[$key] ?? null;
    }

    public function resolve_legacy_profile(
        string $usergroup
    ): SOF_AccessProfile {
        $legacy =
            strtoupper(
                trim($usergroup)
            );

        $key =
            match ($legacy) {

                'ADMIN' =>
                    'admin',

                'MANAGER' =>
                    'manager',

                default =>
                    'member',
            };

        return $this->find($key)
            ?? $this->find('member');
    }
}