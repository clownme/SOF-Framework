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

    /**
     * Assess the member's current renewal situation.
     *
     * Situations:
     *
     * current
     *     Membership is current and outside the renewal window.
     *
     * renewal_window
     *     Membership is current and within the renewal window.
     *
     * expired
     *     Membership has expired.
     *
     * unavailable
     *     SOF cannot safely determine the renewal situation.
     */
    public function get_renewal_situation(
        $member,
        $renewal_window_days = 60
    ) {
        $result = [
            'situation' => 'unavailable',
            'expiration_date' => '',
            'expiration_timestamp' => null,
            'days_until_expiration' => null,
            'may_renew' => false,
            'message' =>
                'We could not determine your current membership ' .
                'expiration date. Please contact the COAI office.',
        ];

        if (!$member || !is_array($member)) {
            return $result;
        }

        $status = strtoupper(
            trim(
                (string)($member['status'] ?? '')
            )
        );

        /*
         * Deceased membership must never offer a renewal path.
         */
        if ($status === 'DECEASED') {
            $result['message'] = '';

            return $result;
        }

        $raw_expiration = trim(
            (string)($member['membership_expiration'] ?? '')
        );

        if ($raw_expiration === '') {
            return $result;
        }

        $expiration_timestamp = strtotime(
            $raw_expiration
        );

        if (
            !$expiration_timestamp ||
            $expiration_timestamp <= 0
        ) {
            return $result;
        }

        /*
         * Compare calendar dates so the stored time portion
         * cannot affect the business decision.
         */
        $today_string = wp_date('Y-m-d');

        $expiration_string = wp_date(
            'Y-m-d',
            $expiration_timestamp
        );

        $today = strtotime(
            $today_string . ' 00:00:00'
        );

        $expiration_day = strtotime(
            $expiration_string . ' 00:00:00'
        );

        if (
            !$today ||
            !$expiration_day
        ) {
            return $result;
        }

        $days_until_expiration = (int) floor(
            (
                $expiration_day -
                $today
            ) /
            DAY_IN_SECONDS
        );

        $result['expiration_date'] = wp_date(
            'F j, Y',
            $expiration_day
        );

        $result['expiration_timestamp'] =
            $expiration_day;

        $result['days_until_expiration'] =
            $days_until_expiration;

        /*
         * Either the stored membership status or the actual
         * expiration date may establish an expired situation.
         */
        if (
            $status === 'EXPIRED' ||
            $expiration_day < $today
        ) {
            $result['situation'] = 'expired';
            $result['may_renew'] = true;
            $result['message'] =
                'Your membership has expired.';

            return $result;
        }

        $renewal_window_days = max(
            0,
            (int)$renewal_window_days
        );

        if (
            $days_until_expiration <=
            $renewal_window_days
        ) {
            $result['situation'] =
                'renewal_window';

            $result['may_renew'] = true;

            $result['message'] =
                'Your membership is approaching its expiration date.';

            return $result;
        }

        $result['situation'] = 'current';
        $result['may_renew'] = false;

        $result['message'] =
            'Your membership is current. ' .
            'There is no need to renew at this time.';

        return $result;
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