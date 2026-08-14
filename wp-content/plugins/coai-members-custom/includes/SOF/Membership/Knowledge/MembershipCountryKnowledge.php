<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Country Knowledge
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Knowledge
 *
 * Knowledge:
 *     Organizational Country Knowledge
 *
 * Purpose:
 *     Define canonical country identities and organizational
 *     country groups used by Membership.
 *
 * Responsibilities:
 *     - Normalize known country values
 *     - Define canonical country identities
 *     - Define organizational country groups
 *     - Provide country membership for those groups
 *
 * Does NOT:
 *     - Query members
 *     - Determine communication authorization
 *     - Determine recipient availability
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_MembershipCountryKnowledge
{
    // -------------------------------------------------
    // Country Aliases
    // -------------------------------------------------

    /**
     * Return known country aliases mapped to canonical codes.
     *
     * @return array<string, string>
     */
    public function aliases(): array
    {
        return [
            'US' => 'US',
            'USA' => 'US',
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',

            'CA' => 'CA',
            'CAN' => 'CA',
            'CANADA' => 'CA',

            'MX' => 'MX',
            'MEXICO' => 'MX',

            'GB' => 'GB',
            'UK' => 'GB',
            'UNITED KINGDOM' => 'GB',
            'GREAT BRITAIN' => 'GB',

            'AU' => 'AU',
            'AUSTRALIA' => 'AU',

            'DE' => 'DE',
            'GE' => 'DE',
            'GERMANY' => 'DE',
        ];
    }

    // -------------------------------------------------
    // Country Normalization
    // -------------------------------------------------

    /**
     * Normalize a country value into its canonical identity.
     */
    public function normalize(
        string $country
    ): string {
        $country = strtoupper(
            trim($country)
        );

        if ($country === '') {
            return '';
        }

        $aliases = $this->aliases();

        return $aliases[$country] ?? $country;
    }

    // -------------------------------------------------
    // Country Groups
    // -------------------------------------------------

    /**
     * Return known organizational country groups.
     *
     * @return array<string, array<string, mixed>>
     */
    public function groups(): array
    {
        return [

            'LATIN' => [
                'name' => 'Latin Region',

                /*
                 * Mexico
                 * Central America
                 * South America
                 * Caribbean
                 */
                'countries' => [
                    'MX',

                    // Central America
                    'BZ',
                    'CR',
                    'SV',
                    'GT',
                    'HN',
                    'NI',
                    'PA',

                    // South America
                    'AR',
                    'BO',
                    'BR',
                    'CL',
                    'CO',
                    'EC',
                    'GY',
                    'PY',
                    'PE',
                    'SR',
                    'UY',
                    'VE',

                    // Caribbean
                    'AG',
                    'BS',
                    'BB',
                    'CU',
                    'DM',
                    'DO',
                    'GD',
                    'HT',
                    'JM',
                    'KN',
                    'LC',
                    'PR',
                    'VC',
                    'TT',
                ],
            ],

                /*
                 * Europe
                 * Australia
                 * Asia
                 *
                 * This list can grow as additional countries
                 * appear in Membership data.
                 */
                 
                 'EUROPE_ASIA_OCEANIA' => [
                     'name' => 'International Region',
                     
                    'countries' => [
                         // Europe
                        'AT',
                        'BE',
                        'BG',
                        'CH',
                        'CZ',
                        'DE',
                        'DK',
                        'ES',
                        'FI',
                        'FR',
                        'GB',
                        'GR',
                        'HU',
                        'IE',
                        'IS',
                        'IT',
                        'NL',
                        'NO',
                        'PL',
                        'PT',
                        'RO',
                        'SE',
                        'SK',
                        'UA',

                        // Asia
                        'BD',
                        'CN',
                        'HK',
                        'ID',
                        'IL',
                        'IN',
                        'JP',
                        'KR',
                        'LK',
                        'MY',
                        'NP',
                        'PH',
                        'PK',
                        'SG',
                        'TH',
                        'TW',
                        'VN',

                        // Oceania
                        'AU',
                        'NZ',
                        'PG',
                    ],
                ],
            ];
        }

    // -------------------------------------------------
    // Group Resolution
    // -------------------------------------------------

    /**
     * Resolve one country group.
     *
     * @return array<string, mixed>
     */
    public function group(
        string $group
    ): array {
        $group = strtoupper(
            trim($group)
        );

        if ($group === '') {
            return [];
        }

        $groups = $this->groups();

        return $groups[$group] ?? [];
    }

    /**
     * Return canonical countries belonging to one group.
     *
     * @return array<int, string>
     */
    public function countries(
        string $group
    ): array {
        $definition = $this->group($group);

        $countries =
            $definition['countries'] ?? [];

        return is_array($countries)
            ? $countries
            : [];
    }

    /**
     * Determine whether a country group exists.
     */
    public function has_group(
        string $group
    ): bool {
        return array_key_exists(
            strtoupper(trim($group)),
            $this->groups()
        );
    }
    
    /**
     * Return database values that may represent countries
     * belonging to one organizational country group.
     *
     * Includes canonical codes and known legacy aliases.
     *
     * @return array<int, string>
     */
    public function database_values_for_group(
        string $group
    ): array {
        $countries = $this->countries($group);

        if (!$countries) {
            return [];
        }

        $values = [];

        foreach ($countries as $country) {
            $values[] = $country;
        }

        foreach ($this->aliases() as $alias => $canonical) {
            if (in_array($canonical, $countries, true)) {
                $values[] = $alias;
            }
        }

        return array_values(
            array_unique($values)
        );
    }
}