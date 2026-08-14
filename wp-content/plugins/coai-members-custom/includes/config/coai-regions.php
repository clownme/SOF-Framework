<?php
if (!defined('ABSPATH')) exit;

/**
 * SOF Configuration
 * COAI Regions
 *
 * Single source of truth for COAI Region names.
 */

function coai_get_coai_regions() {
    return [
        'North East Region',
        'North Central Region',
        'North West Region',
        'Mid East Region',
        'Mid West Region',
        'South East Region',
        'South Central Region',
        'South West Region',
        'Canada Region',
        'Latin Region',
        'International Region',
    ];
}

/**
 * Get COAI Region configuration metadata.
 *
 * Stable keys are internal identifiers.
 * Display names are user-facing labels.
 *
 * @return array
 */
function coai_get_coai_region_config() {

    return [

        'north-east' => [
            'name'       => 'North East Region',
            'code'       => 'NER',
            'sort_order' => 10,
        ],

        'north-central' => [
            'name'       => 'North Central Region',
            'code'       => 'NCR',
            'sort_order' => 20,
        ],

        'north-west' => [
            'name'       => 'North West Region',
            'code'       => 'NWR',
            'sort_order' => 30,
        ],

        'mid-east' => [
            'name'       => 'Mid East Region',
            'code'       => 'MER',
            'sort_order' => 40,
        ],

        'mid-west' => [
            'name'       => 'Mid West Region',
            'code'       => 'MWR',
            'sort_order' => 50,
        ],

        'south-east' => [
            'name'       => 'South East Region',
            'code'       => 'SER',
            'sort_order' => 60,
        ],

        'south-central' => [
            'name'       => 'South Central Region',
            'code'       => 'SCR',
            'sort_order' => 70,
        ],

        'south-west' => [
            'name'       => 'South West Region',
            'code'       => 'SWR',
            'sort_order' => 80,
        ],

        'canada' => [
            'name'       => 'Canada Region',
            'code'       => 'CAN',
            'sort_order' => 90,
        ],

        'latin' => [
            'name'       => 'Latin Region',
            'code'       => 'LAT',
            'sort_order' => 100,
        ],

        'international' => [
            'name'       => 'International Region',
            'code'       => 'INT',
            'sort_order' => 110,
        ],

    ];
}

/**
 * Get a COAI Region by its internal key.
 *
 * @param string $key
 * @return array|null
 */
function coai_get_coai_region($key) {

    $config = coai_get_coai_region_config();

    return $config[$key] ?? null;
}

/**
 * Get a COAI Region display name.
 *
 * @param string $key
 * @return string
 */
function coai_get_coai_region_name($key) {

    $region = coai_get_coai_region($key);

    return $region['name'] ?? '';
}

/**
 * Get a COAI Region code.
 *
 * @param string $key
 * @return string
 */
function coai_get_coai_region_code($key) {

    $region = coai_get_coai_region($key);

    return $region['code'] ?? '';
}
 
