<?php
/**
 * --------------------------------------------------------
 * SOF Region Officer Repository
 *
 * Repository:
 * Region Officers
 *
 * Purpose:
 * Encapsulates all database access for
 * wp_coai_region_officers.
 *
 * SOF Layer:
 * Repository
 *
 * Created:
 * SOF v4.0
 * --------------------------------------------------------
 */

if (!defined('ABSPATH')) exit;

function coai_get_region_officer(string $region): ?array
{
    global $wpdb;

    $table = coai_region_officers_table();
    $members = coai_get_members_table();

    $sql = "
        SELECT
            ro.*,
            m.full_name,
            m.email,
            m.phone,
            m.mobile
        FROM {$table} ro
        INNER JOIN {$members} m
            ON m.member_id = ro.member_id
        WHERE
            ro.coai_region = %s
            AND ro.is_active = 1
        LIMIT 1
    ";

    $row = $wpdb->get_row(
        $wpdb->prepare($sql, $region),
        ARRAY_A
    );

    return $row ?: null;
}

function coai_region_has_officer(string $region): bool
{
    return coai_get_region_officer($region) !== null;
}

function coai_get_all_region_officers(): array
{
    global $wpdb;

    $table = coai_region_officers_table();
    $members = coai_get_members_table();

    return $wpdb->get_results("
        SELECT
            ro.*,
            m.full_name,
            m.email
        FROM {$table} ro
        INNER JOIN {$members} m
            ON m.member_id = ro.member_id
        WHERE ro.is_active = 1
        ORDER BY ro.coai_region
    ", ARRAY_A);
}

/**
 * Return the active Regional Vice President assignment for a member.
 */
function coai_get_active_rvp_assignment_for_member(int $member_id): ?array
{
    global $wpdb;

    if ($member_id <= 0) {
        return null;
    }

    $table = coai_region_officers_table();

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE member_id = %d
               AND is_active = 1
               AND LOWER(TRIM(contact_title)) = %s
             ORDER BY id DESC
             LIMIT 1",
            $member_id,
            'regional vice president'
        ),
        ARRAY_A
    );

    return $row ?: null;
}

/**
 * Return the assigned COAI region for an active Regional Vice President.
 */
function coai_get_active_rvp_region_for_member(int $member_id): string
{
    $assignment = coai_get_active_rvp_assignment_for_member($member_id);

    return $assignment
        ? trim((string) ($assignment['coai_region'] ?? ''))
        : '';
}

