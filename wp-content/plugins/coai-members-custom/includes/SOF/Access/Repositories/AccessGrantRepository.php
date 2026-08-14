<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Grant Repository
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Persistence
 *
 * Repository:
 *     Access Grant
 *
 * Purpose:
 *     Persist and retrieve capability grants assigned
 *     to people.
 *
 * Does NOT:
 *     - Decide whether access should be granted
 *     - Define capability meaning
 *     - Determine organizational responsibility
 *     - Render access management
 *
 * ============================================================
 */

class SOF_AccessGrantRepository
{
    protected string $table;

    public function __construct()
    {
        $this->table =
            function_exists('sof_table')
                ? sof_table('access_grants')
                : '';
    }


    public function table_name(): string
    {
        return $this->table;
    }

    /**
     * Retrieve all access grants assigned to a person.
     *
     * @return array<int, SOF_AccessGrant>
     */
    public function find_for_person(
        int $person_id
    ): array {
        global $wpdb;

        if (
            $person_id <= 0 ||
            $this->table === ''
        ) {
            return [];
        }

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                        capability,
                        category,
                        scope_key,
                        scope_type,
                        scope_name
                     FROM {$this->table}
                     WHERE person_id = %d
                     ORDER BY category, capability",
                    $person_id
                ),
                ARRAY_A
            );

        if (!$rows) {
            return [];
        }

        $grants = [];

        foreach ($rows as $row) {

            $scope =
                new SOF_OrganizationalScope(
                    (string) ($row['scope_key'] ?? ''),
                    (string) ($row['scope_type'] ?? ''),
                    (string) ($row['scope_name'] ?? '')
                );

            $grants[] =
                new SOF_AccessGrant(
                    $person_id,
                    (string) ($row['capability'] ?? ''),
                    (string) ($row['category'] ?? ''),
                    $scope
                );
        }

        return $grants;
    }
    
    /**
     * Delete all access grants assigned to a person.
     */
    public function delete_for_person(
        int $person_id
    ): bool {
        global $wpdb;

        if (
            $person_id <= 0 ||
            $this->table === ''
        ) {
            return false;
        }

        $result =
            $wpdb->delete(
                $this->table,
                [
                    'person_id' => $person_id,
                ],
                [
                    '%d',
                ]
            );

        return $result !== false;
    }

    /**
     * Persist one access grant.
     */
    public function insert(
        SOF_AccessGrant $grant
    ): bool {
        global $wpdb;

        if ($this->table === '') {
            return false;
        }

        $scope =
            $grant->get_scope();
            
        $now =
            current_time('mysql');

        $result =
            $wpdb->insert(
                $this->table,
                [
                    'person_id' =>
                        $grant->get_person_id(),

                    'capability' =>
                        $grant->get_capability(),

                    'category' =>
                        $grant->get_category(),

                    'scope_key' =>
                        $scope->get_key(),

                    'scope_type' =>
                        $scope->get_type(),

                    'scope_name' =>
                        $scope->get_name(),

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
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

        return $result !== false;
    }    
}