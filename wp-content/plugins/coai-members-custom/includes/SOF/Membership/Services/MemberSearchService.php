<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Member Search Service
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Member Search
 *
 * Purpose:
 *     Search Membership records for people matching
 *     a user-provided search term.
 *
 * Responsibilities:
 *     - Search by member number
 *     - Search by name
 *     - Search by username
 *     - Search by email
 *     - Return matching Membership records
 *
 * Does NOT:
 *     - Determine Access
 *     - Grant capabilities
 *     - Render presentation
 *     - Determine organizational responsibility
 *
 * ============================================================
 */

class SOF_MemberSearchService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(
        string $term,
        int $limit = 20
    ): array {
        global $wpdb;

        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $table = '';

        if (function_exists('coai_members_table')) {
            $table = coai_members_table();
        } elseif (function_exists('coai_get_members_table')) {
            $table = coai_get_members_table();
        }

        if ($table === '') {
            return [];
        }

        $limit = max(
            1,
            min(50, $limit)
        );

        $like =
            '%' . $wpdb->esc_like($term) . '%';

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                        member_id,
                        member_number,
                        first_name,
                        last_name,
                        username,
                        email,
                        usergroup
                     FROM {$table}
                     WHERE member_number LIKE %s
                        OR first_name LIKE %s
                        OR last_name LIKE %s
                        OR CONCAT(first_name, ' ', last_name) LIKE %s
                        OR username LIKE %s
                        OR email LIKE %s
                     ORDER BY last_name, first_name
                     LIMIT %d",
                    $like,
                    $like,
                    $like,
                    $like,
                    $like,
                    $like,
                    $limit
                ),
                ARRAY_A
            );

        return $rows ?: [];
    }
}