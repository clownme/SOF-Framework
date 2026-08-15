<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF WordPress Member Resolver
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Integration
 *
 * Integration:
 *     WordPress Member Resolver
 *
 * Purpose:
 *     Resolve the COAI Membership record associated with
 *     the currently authenticated WordPress user.
 *
 * Responsibilities:
 *     - Read the current WordPress user
 *     - Resolve stored coai_member_id relationship
 *     - Retrieve the corresponding Membership record
 *     - Fall back to exact email or username matching
 *     - Repair the coai_member_id relationship when found
 *
 * Does NOT:
 *     - Determine renewal eligibility
 *     - Apply Membership business rules
 *     - Render presentation
 *     - Process payments
 *     - Modify Membership data
 *
 * ============================================================
 */

class SOF_WordPressMemberResolver
{
    /**
     * Resolve the Membership record for the current
     * authenticated WordPress user.
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

        if (
            !$user ||
            empty($user->ID)
        ) {
            return null;
        }

        return $this->resolve_user(
            $user
        );
    }


    /**
     * Resolve the Membership record associated with
     * a supplied WordPress user.
     *
     * @param WP_User $user
     *
     * @return array<string, mixed>|null
     */
    public function resolve_user(
        WP_User $user
    ): ?array {

        $wordpress_user_id =
            (int) $user->ID;

        if ($wordpress_user_id <= 0) {
            return null;
        }


        // -------------------------------------------------
        // Existing Member Relationship
        // -------------------------------------------------

        $member_id =
            (int) get_user_meta(
                $wordpress_user_id,
                'coai_member_id',
                true
            );

        if ($member_id > 0) {

            $member =
                $this->find_by_member_id(
                    $member_id
                );

            if ($member) {
                return $member;
            }
        }


        // -------------------------------------------------
        // Identity Fallback
        // -------------------------------------------------

        $member =
            $this->find_by_identity(
                (string) $user->user_email,
                (string) $user->user_login
            );

        if (!$member) {
            return null;
        }


        // -------------------------------------------------
        // Repair WordPress → Membership Relationship
        // -------------------------------------------------

        $resolved_member_id =
            (int) (
                $member['member_id']
                ?? 0
            );

        if ($resolved_member_id > 0) {

            update_user_meta(
                $wordpress_user_id,
                'coai_member_id',
                $resolved_member_id
            );
        }

        return $member;
    }


    /**
     * Resolve a Membership record by member ID.
     *
     * @return array<string, mixed>|null
     */
    protected function find_by_member_id(
        int $member_id
    ): ?array {

        if ($member_id <= 0) {
            return null;
        }

        /*
         * Use the established Member Repository whenever
         * available.
         */

        if (function_exists('coai_get_member_by_id')) {

            $member =
                coai_get_member_by_id(
                    $member_id
                );

            return is_array($member)
                ? $member
                : null;
        }

        return null;
    }


    /**
     * Resolve a Membership record using exact WordPress
     * email or username identity.
     *
     * @return array<string, mixed>|null
     */
    protected function find_by_identity(
        string $email,
        string $username
    ): ?array {
        global $wpdb;

        $email =
            trim($email);

        $username =
            trim($username);

        if (
            $email === '' &&
            $username === ''
        ) {
            return null;
        }


        // -------------------------------------------------
        // Members Table
        // -------------------------------------------------

        $table = '';

        if (function_exists('coai_members_table')) {

            $table =
                coai_members_table();

        } elseif (
            function_exists(
                'coai_get_members_table'
            )
        ) {

            $table =
                coai_get_members_table();
        }

        if ($table === '') {
            return null;
        }


        // -------------------------------------------------
        // Exact Identity Match
        // -------------------------------------------------

        $member =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT *
                     FROM {$table}
                     WHERE email = %s
                        OR username = %s
                     LIMIT 1",
                    $email,
                    $username
                ),
                ARRAY_A
            );

        return $member ?: null;
    }
}