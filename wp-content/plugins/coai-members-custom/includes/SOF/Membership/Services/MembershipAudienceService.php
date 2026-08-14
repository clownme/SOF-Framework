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
 *     Membership Audience
 *
 * Purpose:
 *     Resolve members belonging to an organizational
 *     Membership audience.
 *
 * Responsibilities:
 *     - Resolve members for an organizational region
 *     - Apply requested Membership statuses
 *     - Use Membership-owned region knowledge
 *     - Use Membership-owned country knowledge
 *     - Return member information for consuming frameworks
 *
 * Does NOT:
 *     - Determine Communication authorization
 *     - Determine recipient availability
 *     - Determine Communication lifecycle state
 *     - Render presentation
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_MembershipAudienceService
{
    protected SOF_MembershipRegionKnowledge $region_knowledge;

    protected SOF_MembershipCountryKnowledge $country_knowledge;

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

    /**
     * Resolve members belonging to an organizational region.
     *
     * @param array<int, string> $membership_statuses
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolve_regional_members(
        string $region,
        array $membership_statuses = ['Active']
    ): array {
        global $wpdb;

        $region =
            trim($region);

        if ($region === '') {
            return [];
        }

        // -------------------------------------------------
        // Resolve Member Table
        // -------------------------------------------------

        $table = '';

        if (function_exists('coai_members_table')) {
            $table =
                coai_members_table();

        } elseif (
            function_exists('coai_get_members_table')
        ) {
            $table =
                coai_get_members_table();
        }

        if ($table === '') {
            return [];
        }

        // -------------------------------------------------
        // Resolve Region Definition
        // -------------------------------------------------

        $definition =
            $this->region_knowledge
                ->region($region);

        if (!$definition) {
            return [];
        }

        // -------------------------------------------------
        // Normalize Membership Statuses
        // -------------------------------------------------

        $membership_statuses =
            $this->normalize_membership_statuses(
                $membership_statuses
            );

        if (!$membership_statuses) {
            return [];
        }

        // -------------------------------------------------
        // Build Membership Status Filter
        // -------------------------------------------------

        $status_placeholders =
            implode(
                ', ',
                array_fill(
                    0,
                    count($membership_statuses),
                    '%s'
                )
            );

        $where = [
            "
            UPPER(TRIM(status))
            IN ({$status_placeholders})
            ",
        ];

        $args =
            array_map(
                static function (
                    string $status
                ): string {
                    return strtoupper($status);
                },
                $membership_statuses
            );

        // -------------------------------------------------
        // Location-Based Region
        // -------------------------------------------------

        if (
            ($definition['type'] ?? '') ===
                'location'
        ) {
            $locations =
                $definition['locations'] ?? [];

            if (
                !is_array($locations) ||
                !$locations
            ) {
                return [];
            }

            $location_placeholders =
                implode(
                    ', ',
                    array_fill(
                        0,
                        count($locations),
                        '%s'
                    )
                );

            $where[] =
                "
                UPPER(TRIM(state))
                IN ({$location_placeholders})
                ";

            foreach ($locations as $location) {
                $args[] =
                    strtoupper(
                        trim(
                            (string) $location
                        )
                    );
            }

            $country =
                trim(
                    (string) (
                        $definition['country']
                        ?? ''
                    )
                );

            if ($country !== '') {

                $country_values =
                    $this->database_values_for_country(
                        $country
                    );

                if ($country_values) {

                    $country_placeholders =
                        implode(
                            ', ',
                            array_fill(
                                0,
                                count($country_values),
                                '%s'
                            )
                        );

                    $where[] =
                        "
                        UPPER(TRIM(country))
                        IN ({$country_placeholders})
                        ";

                    foreach (
                        $country_values
                        as $country_value
                    ) {
                        $args[] =
                            strtoupper(
                                trim(
                                    $country_value
                                )
                            );
                    }
                }
            }
        }

        // -------------------------------------------------
        // Country-Group Region
        // -------------------------------------------------

        elseif (
            ($definition['type'] ?? '') ===
                'country_group'
        ) {
            $group =
                trim(
                    (string) (
                        $definition['group']
                        ?? ''
                    )
                );

            if ($group === '') {
                return [];
            }

            $country_values =
                $this->country_knowledge
                    ->database_values_for_group(
                        $group
                    );

            if (!$country_values) {
                return [];
            }

            $country_placeholders =
                implode(
                    ', ',
                    array_fill(
                        0,
                        count($country_values),
                        '%s'
                    )
                );

            $where[] =
                "
                UPPER(TRIM(country))
                IN ({$country_placeholders})
                ";

            foreach (
                $country_values
                as $country_value
            ) {
                $args[] =
                    strtoupper(
                        trim(
                            $country_value
                        )
                    );
            }
        }

        else {
            return [];
        }

        // -------------------------------------------------
        // Query Membership
        // -------------------------------------------------

        $where_sql =
            implode(
                ' AND ',
                $where
            );

        $sql =
            "
            SELECT *
            FROM {$table}
            WHERE {$where_sql}
            ORDER BY last_name, first_name, member_id
            ";

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    $sql,
                    ...$args
                ),
                ARRAY_A
            );

        return $rows ?: [];
    }
    
    /**
     * Resolve members belonging to the entire organization.
     *
     * @param array<int, string> $membership_statuses
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolve_organizational_members(
        array $membership_statuses = ['Active']
    ): array {
        global $wpdb;

        // -------------------------------------------------
        // Resolve Member Table
        // -------------------------------------------------

        $table = '';

        if (function_exists('coai_members_table')) {
            $table =
                coai_members_table();

        } elseif (
            function_exists('coai_get_members_table')
        ) {
            $table =
                coai_get_members_table();
        }

        if ($table === '') {
            return [];
        }

        // -------------------------------------------------
        // Normalize Membership Statuses
        // -------------------------------------------------

        $membership_statuses =
            $this->normalize_membership_statuses(
                $membership_statuses
            );

        if (!$membership_statuses) {
            return [];
        }

        // -------------------------------------------------
        // Build Membership Status Filter
        // -------------------------------------------------

        $status_placeholders =
            implode(
                ', ',
                array_fill(
                    0,
                    count($membership_statuses),
                    '%s'
                )
            );

        $args =
            array_map(
                static function (
                    string $status
                ): string {
                    return strtoupper($status);
                },
                $membership_statuses
            );

        // -------------------------------------------------
        // Query Membership
        // -------------------------------------------------

        $sql =
            "
            SELECT *
            FROM {$table}
            WHERE UPPER(TRIM(status))
                IN ({$status_placeholders})
            ORDER BY last_name, first_name, member_id
            ";

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    $sql,
                    ...$args
                ),
                ARRAY_A
            );

        return $rows ?: [];
    }

    /**
     * Resolve the current Membership population counts
     * for the entire organization.
     *
     * @return array<string, int>
     */
    public function resolve_organizational_status_counts():
        array {

        $status_counts = [
            'Active'   => 0,
            'Expired'  => 0,
            'Archived' => 0,
        ];

        foreach (
            array_keys($status_counts)
            as $membership_status
        ) {
            $members =
                $this->resolve_organizational_members(
                    [
                        $membership_status,
                    ]
                );

            $status_counts[$membership_status] =
                count(
                    $members
                );
        }

        return $status_counts;
    }
    
    /**
     * Resolve the current Membership population counts
     * for an organizational region.
     *
     * @return array<string, int>
     */
    public function resolve_regional_status_counts(
        string $region
    ): array {
        $region =
            trim(
                $region
            );

        $status_counts = [
            'Active' => 0,
            'Expired' => 0,
            'Archived' => 0,
        ];

        if ($region === '') {
            return $status_counts;
        }

        foreach (
            array_keys($status_counts)
            as $membership_status
        ) {
            $members =
                $this->resolve_regional_members(
                    $region,
                    [
                        $membership_status,
                    ]
                );

            $status_counts[$membership_status] =
                count(
                    $members
                );
        }

        return $status_counts;
    }

    /**
     * Normalize supported Membership statuses.
     *
     * @param array<int, string> $statuses
     *
     * @return array<int, string>
     */
    private function normalize_membership_statuses(
        array $statuses
    ): array {

        $supported = [
            'Active',
            'Expired',
            'Archived',
        ];

        $normalized = [];

        foreach ($statuses as $status) {

            $status =
                trim(
                    (string) $status
                );

            foreach ($supported as $known_status) {

                if (
                    strcasecmp(
                        $status,
                        $known_status
                    ) === 0
                ) {
                    $normalized[] =
                        $known_status;

                    break;
                }
            }
        }

        return array_values(
            array_unique(
                $normalized
            )
        );
    }

    /**
     * Return database values that may represent one country.
     *
     * @return array<int, string>
     */
    private function database_values_for_country(
        string $country
    ): array {

        $canonical =
            $this->country_knowledge
                ->normalize($country);

        if ($canonical === '') {
            return [];
        }

        $values = [
            $canonical,
        ];

        foreach (
            $this->country_knowledge->aliases()
            as $alias => $resolved
        ) {
            if ($resolved === $canonical) {
                $values[] =
                    $alias;
            }
        }

        return array_values(
            array_unique(
                $values
            )
        );
    }
}