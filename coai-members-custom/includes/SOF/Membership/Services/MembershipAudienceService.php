<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Audience Service
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Membership Audience Service
 *
 * Purpose:
 *     Resolve members who belong to a defined organizational
 *     membership audience.
 *
 * Responsibilities:
 *     - Resolve active members belonging to an organizational region
 *     - Consume Membership-owned region knowledge
 *     - Use the established membership repository
 *     - Return membership facts to other SOF domains
 *     - Preserve Membership ownership of member discovery
 *
 * Does NOT:
 *     - Determine communication authorization
 *     - Assess recipient availability
 *     - Determine communication readiness
 *     - Recommend communication actions
 *     - Render member information
 *     - Send communications
 *
 * Business Question:
 *     Which active members belong to this membership audience?
 *
 * ============================================================
 */

class SOF_MembershipAudienceService
{
    // -------------------------------------------------
    // Knowledge
    // -------------------------------------------------

    protected SOF_MembershipRegionKnowledge $region_knowledge;
    
    protected SOF_MembershipCountryKnowledge $country_knowledge;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    public function __construct(
        ?SOF_MembershipRegionKnowledge $region_knowledge = null,
        ?SOF_MembershipCountryKnowledge $country_knowledge = null
    ) {
        $this->region_knowledge =
            $region_knowledge ??
            new SOF_MembershipRegionKnowledge();

        $this->country_knowledge =
            $country_knowledge ??
            new SOF_MembershipCountryKnowledge();
    }

    // -------------------------------------------------
    // Regional Membership
    // -------------------------------------------------

    /**
     * Resolve active members belonging to an
     * organizational region.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolve_regional_members(
        string $region,
        ?array $statuses = ['Active']
    ): array {
        $region = trim($region);

        if ($region === '') {
            return [];
        }

        if (
            !function_exists('coai_members_table') ||
            !function_exists('coai_get_members_page')
        ) {
            return [];
        }

        // -------------------------------------------------
        // Resolve Organizational Knowledge
        // -------------------------------------------------

        $region_definition =
            $this->region_knowledge->region($region);

        if (!$region_definition) {
            return [];
        }

        $resolution_type = trim(
            (string) (
                $region_definition['type'] ?? ''
            )
        );

        // -------------------------------------------------
        // Resolve Audience
        // -------------------------------------------------

        switch ($resolution_type) {

            case 'location':

                return $this->resolve_location_members(
                    $region_definition,
                    $statuses
                );

            case 'country_group':

                return $this->resolve_country_group_members(
                    $region_definition,
                    $statuses
                );

            default:

                return [];
        }
    }
    
    /**
     * Resolve membership population counts by status
     * for an organizational region.
     *
     * Normal Communication audience statuses:
     *
     * Active
     * Expired
     * Archived
     *
     * Deceased is intentionally excluded from the
     * normal Communication audience population.
     *
     * @return array<string, int>
     */
    public function resolve_regional_status_counts(
        string $region
    ): array {
        $statuses = [
            'Active',
            'Expired',
            'Archived',
        ];

        $counts = [
            'Active'   => 0,
            'Expired'  => 0,
            'Archived' => 0,
        ];

        foreach ($statuses as $status) {

            $members =
                $this->resolve_regional_members(
                    $region,
                    [
                        $status,
                    ]
                );

            $counts[$status] =
                count($members);
        }

        return $counts;
    }

    // -------------------------------------------------
    // Location Resolution
    // -------------------------------------------------

    /**
     * Resolve members belonging to an organizational region
     * defined by states, provinces, or territories.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolve_location_members(
        array $definition,
        ?array $statuses = ['Active']
    ): array {
        $locations =
            $definition['locations'] ?? [];

        if (
            !is_array($locations) ||
            empty($locations)
        ) {
            return [];
        }

        $locations = array_values(
            array_filter(
                array_map(
                    static function ($location): string {
                        return strtoupper(
                            trim((string) $location)
                        );
                    },
                    $locations
                )
            )
        );

        if (!$locations) {
            return [];
        }

        $table = coai_members_table();

        $join_sql = '';

        $placeholders = implode(
            ', ',
            array_fill(
                0,
                count($locations),
                '%s'
            )
        );

        /*
         * Location codes are currently sufficient to identify
         * the organizational territory.
         *
         * We intentionally do not require country here because
         * legacy membership records may contain incomplete or
         * inconsistent country values.
         */
        $where = "
            WHERE UPPER(TRIM(`$table`.state))
                IN ($placeholders)
        ";

        $args = $locations;

        /*
         * -------------------------------------------------
         * Membership Status
         * -------------------------------------------------
         *
         * By default, audience discovery returns Active
         * members.
         *
         * A null status collection means return all members
         * belonging to the organizational audience.
         */

        if ($statuses !== null) {

            $statuses = array_values(
                array_filter(
                    array_map(
                        static function ($status): string {
                            return trim((string) $status);
                        },
                        $statuses
                    )
                )
            );

            if ($statuses) {

                $status_placeholders = implode(
                    ', ',
                    array_fill(
                        0,
                        count($statuses),
                        '%s'
                    )
                );

                $where .= "
                    AND `$table`.status
                        IN ($status_placeholders)
                ";

                $args = array_merge(
                    $args,
                    $statuses
                );
            }
        }

        return $this->discover_members(
            $table,
            $join_sql,
            $where,
            $args
        );
    }

    // -------------------------------------------------
    // Country Group Resolution
    // -------------------------------------------------

    /**
     * Resolve members belonging to an organizational
     * country group.
     *
     * @param array<string, mixed> $definition
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolve_country_group_members(
        array $definition,
        ?array $statuses = ['Active']
    ): array {
        $group = trim(
            (string) (
                $definition['group'] ?? ''
            )
        );

        if ($group === '') {
            return [];
        }

       $country_values =
            $this->country_knowledge
                ->database_values_for_group($group);

        if (!$country_values) {
            return [];
        }

        $table = coai_members_table();

        $join_sql = '';

        $placeholders = implode(
            ', ',
            array_fill(
                0,
                count($country_values),
                '%s'
            )
        );

        $where = "
            WHERE UPPER(TRIM(`$table`.country))
                IN ($placeholders)
        ";

        $args = $country_values;

        /*
         * -------------------------------------------------
         * Membership Status
         * -------------------------------------------------
         *
         * By default, audience discovery returns Active
         * members.
         *
         * A null status collection means return all members
         * belonging to the organizational audience.
         */

        if ($statuses !== null) {

            $statuses = array_values(
                array_filter(
                    array_map(
                        static function ($status): string {
                            return trim((string) $status);
                        },
                        $statuses
                    )
                )
            );

            if ($statuses) {

                $status_placeholders = implode(
                    ', ',
                    array_fill(
                        0,
                        count($statuses),
                        '%s'
                    )
                );

                $where .= "
                    AND `$table`.status
                        IN ($status_placeholders)
                ";

                $args = array_merge(
                    $args,
                    $statuses
                );
            }
        }       

        return $this->discover_members(
            $table,
            $join_sql,
            $where,
            $args
        );
    }


    // -------------------------------------------------
    // Repository Discovery
    // -------------------------------------------------

    /**
     * Execute member discovery through the established
     * Membership repository.
     *
     * @param array<int, mixed> $args
     *
     * @return array<int, array<string, mixed>>
     */
    protected function discover_members(
        string $table,
        string $join_sql,
        string $where,
        array $args
    ): array {
        $order_by = "
            ORDER BY
                `$table`.last_name ASC,
                `$table`.first_name ASC
        ";

        /*
         * Organizational audiences currently contain far fewer
         * members than this limit. The boundary prevents
         * accidental truncation without creating an unrestricted
         * query.
         */
        $limit = 5000;

        $offset = 0;

        return coai_get_members_page(
            $table,
            $join_sql,
            $where,
            $args,
            $order_by,
            $limit,
            $offset,
            false
        );
    }
}