<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Organizational Assignment Service
 * ============================================================
 *
 * Framework:
 *     Organization
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Organizational Assignment
 *
 * Purpose:
 *     Resolve organizational responsibilities and scopes
 *     assigned to a person.
 *
 * Responsibilities:
 *     - Resolve organizational assignment information
 *     - Translate organization-specific assignment data
 *       into SOF organizational models
 *
 * Does NOT:
 *     - Grant capabilities
 *     - Determine access
 *     - Discover Communication audiences
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_OrganizationalAssignmentService
{
    /**
     * Resolve the current COAI organizational assignment
     * for a member.
     */
    public function resolve_for_member(
        int $member_id
    ): ?SOF_OrganizationalAssignment
    {
        if ($member_id <= 0) {
            return null;
        }

        global $wpdb;

        $table = '';

        if (function_exists('coai_region_officers_table')) {
            $table = coai_region_officers_table();
        }

        if ($table === '') {
            return null;
        }

        $assignment =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT
                        coai_region,
                        contact_title
                     FROM {$table}
                     WHERE member_id = %d
                       AND is_active = 1
                     LIMIT 1",
                    $member_id
                ),
                ARRAY_A
            );

        if (!$assignment) {
            return null;
        }

        $scope_name =
            trim(
                (string) (
                    $assignment['coai_region'] ?? ''
                )
            );

        if ($scope_name === '') {
            return null;
        }

        $responsibility =
            trim(
                (string) (
                    $assignment['contact_title'] ?? ''
                )
            );

        if ($responsibility === '') {
            $responsibility =
                'Organizational Representative';
        }

        $scope_key =
            sanitize_title(
                $scope_name
            );

        $scope =
            new SOF_OrganizationalScope(
                $scope_key,
                'region',
                $scope_name
            );

        return new SOF_OrganizationalAssignment(
            $member_id,
            $responsibility,
            $scope
        );
    }
}