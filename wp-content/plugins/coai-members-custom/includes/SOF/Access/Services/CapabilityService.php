<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Capability Service
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Capability
 *
 * Purpose:
 *     Provide the capabilities that may be granted through
 *     the SOF Access framework.
 *
 * Responsibilities:
 *     - Provide available business capabilities
 *     - Provide available platform capabilities
 *     - Provide capability labels for presentation
 *
 * Does NOT:
 *     - Grant capabilities
 *     - Persist access
 *     - Determine organizational scope
 *     - Authenticate users
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AccessCapabilityService
{
    /**
     * Return available business capabilities.
     *
     * @return array<string, string>
     */
    public function business_capabilities(): array
    {
        return [
            'view_members' =>
                'View Members',

            'add_members' =>
                'Add Members',

            'edit_members' =>
                'Edit Members',

            'archive_members' =>
                'Archive Members',

            'remove_members' =>
                'Remove Members',

            'export_members' =>
                'Export Members',

            'view_reports' =>
                'View Reports',

            'export_reports' =>
                'Export Reports',

            'compose_communication' =>
                'Compose Communication',

            'test_communication' =>
                'Test Communication',

            'approve_communication' =>
                'Approve Communication',

            'release_communication' =>
                'Send Communication',

            'manage_access' =>
                'Manage Access',

            'manage_newsletters' =>
                'Manage Newsletters',
        ];
    }

    /**
     * Return available platform capabilities.
     *
     * @return array<string, string>
     */
    public function platform_capabilities(): array
    {
        return [
            'access_wordpress_administration' =>
                'Access WordPress Administration',

            'manage_plugins' =>
                'Manage Plugins',

            'manage_themes' =>
                'Manage Themes',

            'manage_wordpress_users' =>
                'Manage WordPress Users',
        ];
    }
}