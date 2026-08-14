<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Assignment Repository
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Persistence
 *
 * Repository:
 *     Access Assignment
 *
 * Purpose:
 *     Persist and retrieve the SOF Access Profile assigned
 *     to a person.
 *
 * Responsibilities:
 *     - Find a person's SOF Access Assignment
 *     - Persist a person's SOF Access Assignment
 *
 * Does NOT:
 *     - Grant capabilities
 *     - Determine organizational scope
 *     - Modify legacy usergroups
 *     - Determine which profile should be assigned
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AccessAssignmentRepository
{
    protected string $table;

    public function __construct()
    {
        $this->table =
            function_exists('sof_table')
                ? sof_table('access_assignments')
                : '';
    }

    public function table_name(): string
    {
        return $this->table;
    }

    public function find_for_person(
        int $person_id
    ): ?SOF_AccessAssignment {
        global $wpdb;

        if (
            $person_id <= 0 ||
            $this->table === ''
        ) {
            return null;
        }

        $row =
            $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT
                        person_id,
                        profile_key
                     FROM {$this->table}
                     WHERE person_id = %d
                     LIMIT 1",
                    $person_id
                ),
                ARRAY_A
            );

        if (!$row) {
            return null;
        }

        return new SOF_AccessAssignment(
            (int) ($row['person_id'] ?? 0),
            (string) ($row['profile_key'] ?? '')
        );
    }

    public function save(
        SOF_AccessAssignment $assignment
    ): bool {
        global $wpdb;

        if (
            $assignment->get_person_id() <= 0 ||
            $assignment->get_profile_key() === '' ||
            $this->table === ''
        ) {
            return false;
        }

        $now =
            current_time('mysql');

        $result =
            $wpdb->replace(
                $this->table,
                [
                    'person_id' =>
                        $assignment->get_person_id(),

                    'profile_key' =>
                        $assignment->get_profile_key(),

                    'created_at' =>
                        $now,

                    'updated_at' =>
                        $now,
                ],
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

        return $result !== false;
    }
}