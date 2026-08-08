<?php
/**
 * SOF Membership Service
 *
 * Business logic layer for member-related operations.
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . '../repositories/member-repository.php';

class COAI_Member_Service {

    private $repository;

    public function __construct() {
        $this->repository = null;
    }

    /**
     * Get full member row by member ID.
     */
    public function get_member_by_id($member_id) {
    if (!$member_id) {
        return null;
    }

    if (function_exists('coai_get_member_by_id')) {
        return coai_get_member_by_id((int)$member_id);
    }

    return null;
}

public function get_member_by_number($member_number) {
    global $wpdb;

    if (!$member_number) {
        return null;
    }

    $table = coai_members_table();

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE member_number = %s
             LIMIT 1",
            (string)$member_number
        ),
        ARRAY_A
    );

    return $row ?: null;
}

public function get_member_display_name($member_number_or_id) {
    if (!$member_number_or_id) {
        return '';
    }

    if (function_exists('coai_get_member_name')) {
        return coai_get_member_name($member_number_or_id);
    }

    return (string)$member_number_or_id;
}

    /**
     * Determine whether a member row is active.
     */
    public function is_active_member($member) {
        if (!$member || !is_array($member)) {
            return false;
        }

        $status = strtolower(trim($member['status'] ?? ''));

        return $status === 'active';
    }

    /**
     * Get normalized membership status.
     */
    public function get_membership_status($member) {
        if (!$member || !is_array($member)) {
            return '';
        }

        return trim((string)($member['status'] ?? ''));
    }
}

/**
 * Helper accessor for Membership Service.
 */
function coai_member_service() {
    static $service = null;

    if ($service === null) {
        $service = new COAI_Member_Service();
    }

    return $service;
}