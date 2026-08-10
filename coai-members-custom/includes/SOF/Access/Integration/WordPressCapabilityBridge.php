<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF WordPress Capability Bridge
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Integration
 *
 * Purpose:
 *     Translate SOF business authorization into narrowly
 *     required WordPress platform capabilities.
 *
 * Responsibilities:
 *     - Resolve the current WordPress user to a SOF person
 *     - Evaluate persisted SOF capabilities
 *     - Expose required WordPress capabilities when authorized
 *
 * Does NOT:
 *     - Change WordPress roles
 *     - Persist WordPress capabilities
 *     - Change SOF Access assignments
 *     - Grant general WordPress administration
 *
 * ============================================================
 */

/**
 * Grant narrowly required WordPress capabilities from SOF Access.
 *
 * Current mapping:
 *
 * manage_newsletters
 *     ↓
 * upload_files
 *
 * upload_files is required by the WordPress Media Library
 * for querying and uploading Newsletter images.
 */
add_filter(
    'user_has_cap',
    static function (
        array $allcaps,
        array $caps,
        array $args,
        WP_User $user
    ): array {

        // -------------------------------------------------
        // Only evaluate the capability we may provide
        // -------------------------------------------------

        if (!in_array('upload_files', $caps, true)) {
            return $allcaps;
        }

        // -------------------------------------------------
        // SOF Access must be available
        // -------------------------------------------------

        if (!class_exists('SOF_AccessGrantService')) {
            return $allcaps;
        }

        // -------------------------------------------------
        // Resolve WordPress User → SOF Person
        // -------------------------------------------------

        $person_id = (int) get_user_meta(
            $user->ID,
            'coai_member_id',
            true
        );

        if ($person_id <= 0) {
            return $allcaps;
        }

        // -------------------------------------------------
        // Evaluate Newsletter authorization
        // -------------------------------------------------

        $grant_service =
            new SOF_AccessGrantService();

        $can_manage_newsletters =
            $grant_service->person_has_capability(
                $person_id,
                'manage_newsletters'
            );

        if (!$can_manage_newsletters) {
            return $allcaps;
        }

        // -------------------------------------------------
        // Provide required WordPress platform capability
        // -------------------------------------------------

        $allcaps['upload_files'] = true;

        return $allcaps;
    },
    20,
    4
);