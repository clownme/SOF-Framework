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
 *     - Resolve the current audience identity
 *     - Resolve the current user's assigned organizational scope
 *     - Confirm permission to communicate with assigned audience
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
     * Resolve the assigned Communication audience for the current user.
     *
     * @param array<int, string> $membership_statuses
     */
    public function resolve_current_audience(
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

        if ($member_id <= 0) {
            return null;
        }

        $usergroup =
            strtoupper(
                trim(
                    (string) (
                        $member['usergroup']
                        ?? ''
                    )
                )
            );

        // -------------------------------------------------
        // Persisted SOF Access
        // -------------------------------------------------

        if (class_exists('SOF_AccessGrantService')) {

            $access_grant_service =
                new SOF_AccessGrantService();

            if (
                $access_grant_service
                    ->person_has_capability(
                        $member_id,
                        'compose_communication'
                    )
            ) {
                $scope =
                    $access_grant_service
                        ->scope_for_capability(
                            $member_id,
                            'compose_communication'
                        );

                if ($scope) {

                    $scope_type =
                        trim(
                            (string)
                            $scope->get_type()
                        );

                    $scope_name =
                        trim(
                            (string)
                            $scope->get_name()
                        );

                    if (
                        $scope_type === 'organization' &&
                        $scope_name !== ''
                    ) {
                        return new SOF_CommunicationAudience(
                            'organization_members',
                            $scope_name,
                            $scope_name . ' members',
                            '',
                            $membership_statuses,
                            0,
                            false
                        );
                    }

                    if ($scope_name !== '') {
                        return new SOF_CommunicationAudience(
                            'assigned_audience',
                            $scope_name,
                            $scope_name . ' members',
                            $scope_name,
                            $membership_statuses,
                            0,
                            false
                        );
                    }
                }
            }
        }

    // -------------------------------------------------
    // Legacy Regional Access
    // -------------------------------------------------

    if (
        function_exists(
            'coai_get_active_rvp_region_for_member'
        )
    ) {
        $region =
            trim(
                (string)
                coai_get_active_rvp_region_for_member(
                    $member_id
                )
            );

        if (
            $region !== '' &&
            function_exists('coai_user_can') &&
            coai_user_can(
                'view_region_members',
                $usergroup
            )
        ) {
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
    }

    // -------------------------------------------------
    // Legacy Organizational Access
    // -------------------------------------------------

    if (
        in_array(
            $usergroup,
            [
                'ADMIN',
                'MANAGER',
            ],
            true
        )
    ) {
        return new SOF_CommunicationAudience(
            'organization_members',
            'Entire Organization',
            'Entire Organization members',
            '',
            $membership_statuses,
            0,
            false
        );
    }

    return null;

    }
    
    /**
     * Resolve the current member record.
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
     * Resolve the member record belonging to a specific
     * WordPress user.
     *
     * Background processes do not have an authenticated
     * browser session, so they must be able to resolve
     * organizational identity from a persisted user ID.
     *
     * @return array<string, mixed>|null
     */
    public function resolve_member_for_user(
        int $user_id
    ): ?array {
        if ($user_id <= 0) {
            return null;
        }

        $user =
            get_user_by(
                'id',
                $user_id
            );

        if (
            !$user ||
            !($user instanceof WP_User)
        ) {
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
     * Diagnose current audience resolution.
     *
     * Temporary development support only.
     *
     * @return array<string, mixed>
     */
    public function diagnose_current_audience(): array
    {
        $diagnostic = [
            'logged_in'                      => false,
            'wordpress_user_id'              => 0,
            'member_resolved'                => false,
            'member_id'                      => 0,
            'usergroup'                      => '',
            'member_lookup_available'        => false,
            'scope_resolver_available'       => false,
            'resolved_scope'                 => '',
            'permission_function_available'  => false,
            'can_access_audience'            => false,
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

        $diagnostic['scope_resolver_available'] =
            class_exists(
                'SOF_AccessGrantService'
            );

        $diagnostic['permission_function_available'] =
            $diagnostic['scope_resolver_available'];

        if (
            $diagnostic['member_id'] > 0 &&
            $diagnostic['scope_resolver_available']
        ) {
            $access_grant_service =
                new SOF_AccessGrantService();

            $diagnostic['can_access_audience'] =
                $access_grant_service
                    ->person_has_capability(
                        $diagnostic['member_id'],
                        'compose_communication'
                    );

            $scope =
                $access_grant_service
                    ->scope_for_capability(
                        $diagnostic['member_id'],
                        'compose_communication'
                    );

            if ($scope) {
                $diagnostic['resolved_scope'] =
                    $scope->get_name();
            }
        }

        return $diagnostic;
    }
}