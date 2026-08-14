<?php
if (!defined('ABSPATH')) exit;

/**
 * SOF Shared Component
 * COAI Region Selector
 */

function coai_get_available_coai_regions() {
    if (function_exists('coai_get_coai_regions')) {
        return coai_get_coai_regions();
    }

    return [];
}

/**
 * Determine if "All COAI Regions" was selected.
 *
 * @param array $regions
 * @return bool
 */
function coai_is_all_coai_regions_selected(array $regions) {

    $available = coai_get_available_coai_regions();

    sort($available);
    $selected = $regions;
    sort($selected);

    return $available === $selected;
}

/**
 * Normalize a COAI region selection into an array.
 *
 * @param mixed $selection
 * @return array
 */
function coai_normalize_coai_region_selection($selection) {

    if (empty($selection)) {
        return [];
    }

    if (is_string($selection)) {
        return [$selection];
    }

    if (is_array($selection)) {
        return array_values(array_unique($selection));
    }

    return [];
}

/**
 * Validate selected COAI Regions.
 *
 * Removes invalid region values and only returns regions
 * that exist in the central COAI Region configuration.
 *
 * @param array $regions
 * @return array
 */
function coai_validate_coai_regions(array $regions) {

    $available = coai_get_available_coai_regions();

    return array_values(
        array_intersect($regions, $available)
    );
}

