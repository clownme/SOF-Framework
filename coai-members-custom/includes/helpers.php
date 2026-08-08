<?php
if (!defined('ABSPATH')) exit;

/** Canonical table registry (single source of truth) */
if (!function_exists('coai_tables')) {
    function coai_tables(): array {
        global $wpdb;

        $members = (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE)
            ? COAI_MEMBERS_TABLE
            : ($wpdb->prefix . 'members');

        $levels = (defined('COAI_LEVELS_TABLE') && COAI_LEVELS_TABLE)
            ? COAI_LEVELS_TABLE
            : ($wpdb->prefix . 'membership_levels');

        return [
            'members'           => $members,
            'membership_levels' => $levels,
            'levels'            => $levels, // alias
        ];
    }
}

if (!function_exists('coai_table')) {
    function coai_table(string $key): string {
        $t = coai_tables();
        return $t[$key] ?? '';
    }
}


/** Tables (single source of truth) */
if (!function_exists('coai_get_members_table')) {
    function coai_get_members_table(): string {
        $t = function_exists('coai_table') ? coai_table('members') : '';
        return $t ?: ($GLOBALS['wpdb']->prefix . 'members');
    }
}

if (!function_exists('coai_get_levels_table')) {
    function coai_get_levels_table(): string {
        $t = function_exists('coai_table') ? coai_table('membership_levels') : '';
        return $t ?: ($GLOBALS['wpdb']->prefix . 'membership_levels');
    }
}


/** Detect levels PK (ID vs id) once, then cache */
if (!function_exists('coai_get_levels_pk')) {
    function coai_get_levels_pk(): string {
        static $pk = null;
        if ($pk !== null) return $pk;
        global $wpdb;
        $levels = coai_get_levels_table();
        // Prefer 'ID' if it exists, else 'id', else fallback 'ID'
        $candidate = $wpdb->get_var($wpdb->prepare(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s
               AND COLUMN_NAME IN ('ID','id')
             ORDER BY FIELD(COLUMN_NAME,'ID','id') LIMIT 1",
            $levels
        ));
        $pk = $candidate ?: 'ID';
        return $pk;
    }
}

/** Get a level name by ID (fast: static + transient cache) */
if (!function_exists('coai_get_level_name')) {
    function coai_get_level_name(int $level_id): string {
        if ($level_id <= 0) return '';
        static $memo = [];
        if (isset($memo[$level_id])) return $memo[$level_id];

        $cache_key = 'coai_level_name_' . $level_id;
        $name = get_transient($cache_key);
        if ($name !== false && $name !== null) { $memo[$level_id] = (string)$name; return (string)$name; }

        global $wpdb;
        $levels = coai_get_levels_table();
        $pk     = coai_get_levels_pk();
        $name   = (string)$wpdb->get_var($wpdb->prepare(
            "SELECT name FROM `$levels` WHERE `$pk` = %d LIMIT 1",
            $level_id
        ));

        // cache for 10 minutes
        set_transient($cache_key, $name, 10 * MINUTE_IN_SECONDS);
        return $memo[$level_id] = $name;
    }
}

/** Get a full map of levels (id => name) for dropdowns */
if (!function_exists('coai_get_level_map')) {
    function coai_get_level_map(): array {
        $cache_key = 'coai_level_map_v1';
        $map = get_transient($cache_key);
        if (is_array($map)) return $map;

        global $wpdb;
        $levels = coai_get_levels_table();
        $pk     = coai_get_levels_pk();
        // Order safely even if sort_order doesn't exist
        $has_sort = $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'sort_order'",
          $levels
        ));

        $order = ((int)$has_sort > 0) ? "ORDER BY sort_order, name" : "ORDER BY name";
        $rows = $wpdb->get_results("SELECT `$pk` AS id, name FROM `$levels` $order", ARRAY_A) ?: [];

        $map = [];
        foreach ($rows as $r) $map[(int)$r['id']] = (string)$r['name'];

        set_transient($cache_key, $map, 10 * MINUTE_IN_SECONDS);
        return $map;
    }
}

/** Convenience: get level name from a member row */
if (!function_exists('coai_level_name_for_row')) {
    function coai_level_name_for_row(array $row): string {
        $id = isset($row['membership_level_id']) ? (int)$row['membership_level_id'] : 0;
        return $id > 0 ? coai_get_level_name($id) : '';
    }
}

/** Optional: fetch member with joined level name */
if (!function_exists('coai_member_with_level')) {
    function coai_member_with_level(int $member_id): ?array {
        global $wpdb;
        $m  = coai_get_members_table();
        $l  = coai_get_levels_table();
        $pk = coai_get_levels_pk();
        $sql = $wpdb->prepare(
            "SELECT m.*, l.name AS membership_name
             FROM `$m` m
             LEFT JOIN `$l` l ON l.`$pk` = m.membership_level_id
             WHERE m.member_id = %d LIMIT 1",
            $member_id
        );
        $row = $wpdb->get_row($sql, ARRAY_A);
        return $row ?: null;
    }
}
