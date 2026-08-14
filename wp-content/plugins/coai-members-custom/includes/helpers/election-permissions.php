<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('coai_get_logged_in_member_id')) {
    function coai_get_logged_in_member_id() {
        if (!is_user_logged_in()) return 0;

        $user_id = get_current_user_id();

        // Preferred: use same linkage as coai-auth-bridge.php
        $member_id = (int) get_user_meta($user_id, 'coai_member_id', true);
        if ($member_id > 0) return $member_id;

        // Backward-compat fallback if older linkage exists
        $member_id = (int) get_user_meta($user_id, 'member_id', true);
        if ($member_id > 0) return $member_id;

        return 0;
    }
}

if (!function_exists('coai_get_members_table_name_safe')) {
    function coai_get_members_table_name_safe() {
        global $wpdb;

        if (function_exists('coai_members_table_name')) {
            return coai_members_table_name();
        }

        if (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) {
            return COAI_MEMBERS_TABLE;
        }

        return $wpdb->prefix . 'members';
    }
}

if (!function_exists('coai_get_member_row_for_voting')) {
    function coai_get_member_row_for_voting($member_id) {
        global $wpdb;

        $table = coai_get_members_table_name_safe();
        $cols  = (array) $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
        $cols_lc = array_map('strtolower', array_map('strval', $cols));

        // Your auth bridge uses member_id as the source-of-truth key.
        if (in_array('member_id', $cols_lc, true)) {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE member_id = %d LIMIT 1",
                    (int) $member_id
                ),
                ARRAY_A
            );
        }

        // Fallback only if table happens to use id instead
        if (in_array('id', $cols_lc, true)) {
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1",
                    (int) $member_id
                ),
                ARRAY_A
            );
        }

        error_log('[COAI ELECTION] Could not find member key column in ' . $table);
        return null;
    }
}

if (!function_exists('coai_member_is_voting_eligible')) {
    function coai_member_is_voting_eligible($member_id) {
        $row = coai_get_member_row_for_voting($member_id);

        if (!$row) {
            return [false, 'Member record not found.'];
        }

        $status = strtoupper(trim((string)($row['status'] ?? $row['member_status'] ?? '')));
        $deleted_at = trim((string)($row['deleted_at'] ?? ''));

        if ($deleted_at !== '' && $deleted_at !== '0000-00-00 00:00:00') {
            return [false, 'This account is archived or deactivated and cannot vote.'];
        }

        if ($status !== 'ACTIVE') {
            return [false, 'Only active members may vote.'];
        }

        return [true, 'Eligible'];
    }
}

if (!function_exists('coai_user_can_manage_elections')) {
    function coai_user_can_manage_elections() {
        if (function_exists('coai_staff_can') && coai_staff_can('manage')) {
            return true;
        }

        return current_user_can('manage_options');
    }
}