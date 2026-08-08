<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Audience Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Audience
 *
 * Purpose:
 *     Resolve the communication audience available to the
 *     current authorized user.
 *
 * Responsibilities:
 *     - Resolve the current COAI member identity
 *     - Resolve the current RVP assignment
 *     - Confirm permission to communicate with regional members
 *     - Build a Communication Audience model
 *
 * Does NOT:
 *     - Render workspace content
 *     - Process form submissions
 *     - Send communications
 *     - Communicate directly with delivery providers
 *
 * ============================================================
 */

class SOF_CommunicationAudienceService
{
    /**
     * Resolve the regional audience for the current user.
     *
     * @param array<int, string> $membership_statuses
     */
    public function resolve_current_regional_audience(
        array $membership_statuses = ['Active']
    ): ?SOF_CommunicationAudience
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user = wp_get_current_user();

        if (!$user || $user->ID <= 0) {
            return null;
        }

        $member = $this->resolve_member($user);

        if (!$member) {
            return null;
        }

        $member_id = (int) ($member['member_id'] ?? 0);

        $usergroup = strtoupper(
            trim((string) ($member['usergroup'] ?? ''))
        );

        if ($member_id <= 0) {
            return null;
        }

        if (
            !function_exists(
                'coai_get_active_rvp_region_for_member'
            )
        ) {
            return null;
        }

        $region = trim(
            (string) coai_get_active_rvp_region_for_member(
                $member_id
            )
        );

        if ($region === '') {
            return null;
        }

        if (
            !function_exists('coai_user_can') ||
            !coai_user_can(
                'view_region_members',
                $usergroup
            )
        ) {
            return null;
        }

        return new SOF_CommunicationAudience(
            'regional_members',
            $region,
            $region . ' members',
            $region,
            $membership_statuses,
            0,
            false
        );
    }
    
    /**
     * Resolve the current authenticated user's COAI member record.
     *
     * @return array<string, mixed>|null
     */
    public function resolve_current_member(): ?array
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user =
            wp_get_current_user();

        if (!$user || $user->ID <= 0) {
            return null;
        }
 
        return $this->resolve_member(
            $user
        );
    }

    /**
     * Resolve the COAI member record belonging to a
     * WordPress user.
     *
     * @return array<string, mixed>|null
     */
    protected function resolve_member(
        WP_User $user
    ): ?array {
        $member_id = (int) get_user_meta(
            $user->ID,
            'coai_member_id',
            true
        );

        if (
            $member_id > 0 &&
            function_exists('coai_get_member_by_id')
        ) {
            $member = coai_get_member_by_id($member_id);

            if ($member) {
                return $member;
            }
        }

        $member = $this->find_member_by_identity($user);

        if (
            $member &&
            !empty($member['member_id'])
        ) {
            update_user_meta(
                $user->ID,
                'coai_member_id',
                (int) $member['member_id']
            );
        }

        return $member;
    }

    /**
     * Locate a member by WordPress email or username.
     *
     * @return array<string, mixed>|null
     */
    protected function find_member_by_identity(
        WP_User $user
    ): ?array {
        global $wpdb;

        $table = '';

        if (function_exists('coai_members_table')) {
            $table = coai_members_table();
        } elseif (function_exists('coai_get_members_table')) {
            $table = coai_get_members_table();
        }

        if ($table === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE email = %s
                   OR username = %s
                 LIMIT 1",
                (string) $user->user_email,
                (string) $user->user_login
            ),
            ARRAY_A
        );

        return $row ?: null;
    }
    
    // -------------------------------------------------
    // Development Diagnostics
    // -------------------------------------------------

    /**
     * Diagnose current regional audience resolution.
     *
     * Temporary development support only.
     *
     * @return array<string, mixed>
     */
    public function diagnose_current_regional_audience(): array
    {
        $diagnostic = [
            'logged_in'                     => false,
            'wordpress_user_id'             => 0,
            'member_resolved'               => false,
            'member_id'                     => 0,
            'usergroup'                     => '',
            'member_lookup_available'        => false,
            'region_function_available'      => false,
            'resolved_region'               => '',
            'permission_function_available'  => false,
            'can_view_region_members'        => false,
        ];

        $diagnostic['logged_in'] =
            is_user_logged_in();

        if (!$diagnostic['logged_in']) {
            return $diagnostic;
        }

        $user = wp_get_current_user();

        if (!$user || $user->ID <= 0) {
            return $diagnostic;
        }

        $diagnostic['wordpress_user_id'] =
            (int) $user->ID;

        $diagnostic['member_lookup_available'] =
            function_exists('coai_get_member_by_id') ||
            function_exists('coai_members_table') ||
            function_exists('coai_get_members_table');

        $member = $this->resolve_member($user);

        if (!$member) {
            return $diagnostic;
        }

        $diagnostic['member_resolved'] = true;

        $diagnostic['member_id'] =
            (int) ($member['member_id'] ?? 0);

        $diagnostic['usergroup'] =
            strtoupper(
                trim(
                    (string) ($member['usergroup'] ?? '')
                )
            );

        $diagnostic['region_function_available'] =
            function_exists(
                'coai_get_active_rvp_region_for_member'
            );

        if (
            $diagnostic['member_id'] > 0 &&
            $diagnostic['region_function_available']
        ) {
            $diagnostic['resolved_region'] =
                trim(
                    (string)
                    coai_get_active_rvp_region_for_member(
                        $diagnostic['member_id']
                    )
                );
        }

        $diagnostic['permission_function_available'] =
            function_exists('coai_user_can');

        if ($diagnostic['permission_function_available']) {
            $diagnostic['can_view_region_members'] =
                coai_user_can(
                    'view_region_members',
                    $diagnostic['usergroup']
                );
        }

        return $diagnostic;
    }
}