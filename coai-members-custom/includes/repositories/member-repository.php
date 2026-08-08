<?php
/**
 * --------------------------------------------------------
 * SOF Member Repository
 *
 * Repository:
 * Members
 *
 * Purpose:
 * Encapsulates all database access
 * for Member records.
 *
 * This repository owns:
 *
 * - Member lookup
 * - Member search
 * - Member retrieval
 * - Member updates
 *
 * It does NOT:
 *
 * - Render HTML
 * - Apply business rules
 * - Communicate externally
 * --------------------------------------------------------
 */

if (!defined('ABSPATH')) exit;

function coai_get_member_by_id(int $memberId): ?array
{
    global $wpdb;

    $table = coai_members_table();

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE member_id = %d",
            $memberId
        ),
        ARRAY_A
    );

    return $row ?: null;
}

function coai_get_members_page(
    string $table,
    string $join_sql,
    string $where,
    array $args,
    string $order_by,
    int $limit,
    int $offset,
    bool $show_created = false
): array
{
    global $wpdb;

    $renewal_expr = "COALESCE(`$table`.`renewal_date`, `$table`.`membership_expiration`)";

    $created_select = $show_created
        ? ", `$table`.created_at"
        : "";

    $coai_col = coai_get_coai_column_name($table);

    $coai_select = ", `$table`.`$coai_col` AS coai_pick";

    $sql = "
        SELECT
            `$table`.member_id,
            `$table`.member_number,
            `$table`.username,
            `$table`.full_name,
            `$table`.first_name,
            `$table`.last_name,
            `$table`.email,
            `$table`.clown_name,
            COALESCE(NULLIF(`$table`.mobile,''), `$table`.phone) AS phone,
            $renewal_expr AS renewal_date,
            `$table`.membership_expiration,
            `$table`.insurance_status,
            `$table`.insurance_effective_date,
            `$table`.insurance_expiration_date,
            `$table`.status,
            `$table`.city,
            `$table`.state,
            `$table`.region
            $coai_select
            $created_select
        FROM `$table`
        $join_sql
        $where
        $order_by
        LIMIT %d OFFSET %d
    ";

    return $wpdb->get_results(
        $wpdb->prepare(
            $sql,
            ...array_merge($args, [$limit, $offset])
        ),
        ARRAY_A
    );
}

function coai_get_member_by_number(string $memberNumber): ?array
{
    global $wpdb;

    if (!$memberNumber) {
        return null;
    }

    $table = coai_members_table();

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE member_number = %s
             LIMIT 1",
            $memberNumber
        ),
        ARRAY_A
    );

    return $row ?: null;
}

function coai_get_member_name($memberNumberOrId): string
{
    if (!$memberNumberOrId) {
        return '';
    }

    $member = coai_get_member_by_number((string)$memberNumberOrId);

    if (!$member) {
        $member = coai_get_member_by_id((int)$memberNumberOrId);
    }

    if (!$member) {
        return (string)$memberNumberOrId;
    }

    if (!empty($member['full_name'])) {
        return $member['full_name'];
    }

    $name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));

    return $name !== '' ? $name : (string)$memberNumberOrId;
}