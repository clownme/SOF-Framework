<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Region Knowledge
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Knowledge
 *
 * Knowledge:
 *     Organizational Membership Regions
 *
 * Purpose:
 *     Define the organizational geographic boundaries used
 *     to determine membership responsibility.
 *
 * Responsibilities:
 *     - Define organizational membership regions
 *     - Define how each region is resolved
 *     - Define US state boundaries
 *     - Define Canadian province and territory boundaries
 *     - Identify country-group based regions
 *
 * Does NOT:
 *     - Query members
 *     - Determine communication authorization
 *     - Determine recipient availability
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_MembershipRegionKnowledge
{
    // -------------------------------------------------
    // Organizational Regions
    // -------------------------------------------------

    /**
     * Return known organizational region definitions.
     *
     * Resolution Types:
     *
     * location
     *     Resolve members by state, province, or territory.
     *
     * country_group
     *     Resolve members by an organizational group of
     *     countries.
     *
     * @return array<string, array<string, mixed>>
     */
    public function regions(): array
    {
        return [

            // -------------------------------------------------
            // United States
            // -------------------------------------------------

            'North East Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'CT',
                    'MA',
                    'ME',
                    'NH',
                    'NY',
                    'RI',
                ],
            ],

            'North Central Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'ND',
                    'SD',
                    'NE',
                    'KS',
                    'OK',
                    'AR',
                    'MO',
                ],
            ],

            'North West Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'ID',
                    'MT',
                    'ND',
                    'NE',
                    'OR',
                    'SD',
                    'WA',
                    'WY',
                ],
            ],

            'Mid East Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'DC',
                    'DE',
                    'MD',
                    'NJ',
                    'PA',
                    'VA',
                    'WV',
                ],
            ],

            'Mid West Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'IA',
                    'IL',
                    'IN',
                    'KY',
                    'MI',
                    'MN',
                    'MO',
                    'OH',
                    'WI',
                ],
            ],

            'South East Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'AL',
                    'AR',
                    'FL',
                    'GA',
                    'LA',
                    'MS',
                    'NC',
                    'SC',
                    'TN',
                ],
            ],

            'South Central Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'CO',
                    'NM',
                    'OK',
                    'TX',
                ],
            ],

            'South West Region' => [
                'type' => 'location',
                'country' => 'US',
                'locations' => [
                    'AZ',
                    'CA',
                    'HI',
                    'NV',
                    'UT',
                ],
            ],

            // -------------------------------------------------
            // Canada
            // -------------------------------------------------

            'Western Canada Region' => [
                'type' => 'location',
                'country' => 'CA',
                'locations' => [
                    'BC',
                    'AB',
                    'SK',
                    'MB',
                ],
            ],

            'Eastern Canada Region' => [
                'type' => 'location',
                'country' => 'CA',
                'locations' => [
                    'NL',
                    'PE',
                    'NS',
                    'NB',
                    'QC',
                    'ON',
                ],
            ],

            /*
             * Canadian territories remain under the
             * Canada Region rather than East or West.
             */
            'Canada Region' => [
                'type' => 'location',
                'country' => 'CA',
                'locations' => [
                    'YT',
                    'NT',
                    'NU',
                ],
            ],

            // -------------------------------------------------
            // Country Groups
            // -------------------------------------------------

            /*
             * Latin Region currently represents all
             * Latin countries.
             *
             * The authoritative country membership for this
             * group will be defined separately.
             */
            'Latin Region' => [
                'type' => 'country_group',
                'group' => 'LATIN',
            ],

            /*
             * International Region currently represents:
             *
             * Europe
             * Australia
             * Asia
             *
             * The authoritative country membership for this
             * group will be defined separately.
             */
            'International Region' => [
                'type' => 'country_group',
                'group' => 'EUROPE_ASIA_OCEANIA',
            ],
        ];
    }

    // -------------------------------------------------
    // Region Resolution
    // -------------------------------------------------

    /**
     * Resolve the definition for one organizational region.
     *
     * @return array<string, mixed>
     */
    public function region(
        string $region
    ): array {
        $region = trim($region);

        if ($region === '') {
            return [];
        }

        $regions = $this->regions();

        return $regions[$region] ?? [];
    }

    /**
     * Determine whether the organizational region exists.
     */
    public function has_region(
        string $region
    ): bool {
        return array_key_exists(
            trim($region),
            $this->regions()
        );
    }
}