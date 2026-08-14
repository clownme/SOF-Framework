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

/**
 * Find one active/non-archived member by COAI number.
 */
function coai_get_member_by_coai_number(string $coaiNumber): ?array
{
    global $wpdb;

    $coaiNumber = trim($coaiNumber);

    if ($coaiNumber === '') {
        return null;
    }

    $table = coai_members_table();

    $coaiColumn = function_exists('coai_get_coai_column_name')
        ? coai_get_coai_column_name($table)
        : 'COAI_number';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT *
            FROM `{$table}`
            WHERE TRIM(`{$coaiColumn}`) = %s
              AND deleted_at IS NULL
              AND (
                    status IS NULL
                    OR UPPER(TRIM(status)) <> 'ARCHIVED'
              )
            LIMIT 1
            ",
            $coaiNumber
        ),
        ARRAY_A
    );

    return $row ?: null;
}

/**
 * Find active/non-archived members sharing an email/username.
 *
 * Multiple rows are deliberately allowed because COAI
 * households may legitimately share an email address.
 */
function coai_find_members_by_email(string $email): array
{
    global $wpdb;

    $email = strtolower(trim($email));

    if ($email === '') {
        return [];
    }

    $table = coai_members_table();

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM `{$table}`
            WHERE deleted_at IS NULL
              AND (
                    status IS NULL
                    OR UPPER(TRIM(status)) <> 'ARCHIVED'
              )
              AND (
                    LOWER(TRIM(email)) = %s
                    OR LOWER(TRIM(username)) = %s
              )
            ORDER BY member_id ASC
            ",
            $email,
            $email
        ),
        ARRAY_A
    );
}

/**
 * Find active/non-archived members by exact first and last name.
 *
 * Multiple rows are deliberately allowed because names are
 * evidence, not unique identity.
 */
function coai_find_members_by_exact_name(
    string $firstName,
    string $lastName
): array {
    global $wpdb;

    $firstName = strtolower(trim($firstName));
    $lastName  = strtolower(trim($lastName));

    if ($firstName === '' || $lastName === '') {
        return [];
    }

    $table = coai_members_table();

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM `{$table}`
            WHERE deleted_at IS NULL
              AND (
                    status IS NULL
                    OR UPPER(TRIM(status)) <> 'ARCHIVED'
              )
              AND LOWER(TRIM(first_name)) = %s
              AND LOWER(TRIM(last_name)) = %s
            ORDER BY member_id ASC
            ",
            $firstName,
            $lastName
        ),
        ARRAY_A
    );
}

/**
 * Append an internal comment to an existing member.
 *
 * Existing comments are preserved.
 */
function coai_append_member_internal_comment(
    int $memberId,
    string $comment
): bool {
    global $wpdb;

    if ($memberId <= 0) {
        return false;
    }

    $comment = trim($comment);

    if ($comment === '') {
        return false;
    }

    $table = coai_members_table();

    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT internal_comments
            FROM `{$table}`
            WHERE member_id = %d
            LIMIT 1
            ",
            $memberId
        )
    );

    $existing = trim((string)$existing);

    $updated_comments = $existing !== ''
        ? $existing . "\n\n" . $comment
        : $comment;

    $result = $wpdb->update(
        $table,
        [
            'internal_comments' => $updated_comments,
        ],
        [
            'member_id' => $memberId,
        ]
    );

    return $result !== false;
}

/**
 * --------------------------------------------------------
 * Update renewal-controlled membership fields.
 *
 * Purpose:
 *     Apply an approved Renewal Date and Membership
 *     Expiration Date to one existing member.
 *
 * Responsibilities:
 *     - Update renewal_date
 *     - Update membership_expiration
 *     - Reactivate an EXPIRED member when the approved
 *       expiration is current or future
 *     - Update updated_at
 *
 * Does NOT:
 *     - Determine whether a Renewal should be applied
 *     - Resolve Zeffy identity
 *     - Make management decisions
 * --------------------------------------------------------
 */
function coai_update_member_renewal_fields(
    int $memberId,
    string $renewalDate,
    string $expirationDate
): bool {
    global $wpdb;

    if ($memberId <= 0) {
        return false;
    }

    $renewalDate = trim($renewalDate);
    $expirationDate = trim($expirationDate);

    if (
        $renewalDate === ''
        || $expirationDate === ''
    ) {
        return false;
    }

    $table = coai_members_table();

    $member = coai_get_member_by_id(
        $memberId
    );

    if (!$member) {
        return false;
    }

    $data = [
        'renewal_date'          => $renewalDate,
        'membership_expiration' => $expirationDate,
        'updated_at'            => current_time('mysql'),
    ];

    $current_status = strtoupper(
        trim(
            (string)(
                $member['status']
                ?? ''
            )
        )
    );

    $expiration_timestamp =
        strtotime($expirationDate);

    $today_timestamp =
        strtotime(
            current_time('Y-m-d')
        );

    if (
        $current_status === 'EXPIRED'
        && $expiration_timestamp
        && $expiration_timestamp >= $today_timestamp
    ) {
        $data['status'] = 'ACTIVE';
    }

    $result = $wpdb->update(
        $table,
        $data,
        [
            'member_id' => $memberId,
        ]
    );

    return $result !== false;
}

/**
 * Search active/non-archived members for human identity review.
 *
 * Search evidence:
 * - COAI number
 * - Email
 * - Username
 * - Full name
 * - First name
 * - Last name
 *
 * Search results are evidence only.
 * This function does NOT resolve identity.
 */
function coai_search_members_for_identity_review(
    string $search,
    int $limit = 25
): array {
    global $wpdb;

    $search = trim($search);

    if ($search === '') {
        return [];
    }

    $limit = max(1, min($limit, 50));

    $table = coai_members_table();

    $coaiColumn = function_exists('coai_get_coai_column_name')
        ? coai_get_coai_column_name($table)
        : 'COAI_number';

    $like = '%' . $wpdb->esc_like($search) . '%';

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM `{$table}`
            WHERE deleted_at IS NULL
              AND (
                    status IS NULL
                    OR UPPER(TRIM(status)) <> 'ARCHIVED'
              )
              AND (
                    TRIM(`{$coaiColumn}`) = %s
                    OR LOWER(TRIM(email)) = LOWER(%s)
                    OR LOWER(TRIM(username)) = LOWER(%s)
                    OR full_name LIKE %s
                    OR first_name LIKE %s
                    OR last_name LIKE %s
              )
            ORDER BY
                CASE
                    WHEN TRIM(`{$coaiColumn}`) = %s THEN 1
                    WHEN LOWER(TRIM(email)) = LOWER(%s) THEN 2
                    WHEN LOWER(TRIM(username)) = LOWER(%s) THEN 3
                    WHEN LOWER(TRIM(full_name)) = LOWER(%s) THEN 4
                    ELSE 5
                END,
                last_name ASC,
                first_name ASC,
                member_id ASC
            LIMIT %d
            ",
            $search,
            $search,
            $search,
            $like,
            $like,
            $like,
            $search,
            $search,
            $search,
            $search,
            $limit
        ),
        ARRAY_A
    );
}