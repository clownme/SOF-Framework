<?php
/**
 * --------------------------------------------------------
 * SOF Distribution Service
 * --------------------------------------------------------
 *
 * Service:
 * Distribution
 *
 * Purpose:
 * Coordinates the business process for distributing
 * data to external destinations.
 *
 * Responsibilities:
 *  - Export Region
 *  - Master Export
 *  - Distribution Notifications
 *  - Distribution History
 *
 * This service DOES NOT:
 *  - Render HTML
 *  - Execute SQL directly
 *  - Communicate directly with Google APIs
 *
 * Those responsibilities belong to:
 *
 * Repositories
 * Drivers
 *
 * --------------------------------------------------------
 */

if (!defined('ABSPATH')) exit;

function coai_distribution_export_region(string $region): array
{
    return [
        'success' => false,
        'message' => 'Distribution Service not implemented yet.',
    ];
}

function coai_distribution_get_region_officer(string $region): ?array
{
    return coai_get_region_officer($region);
}

function coai_distribution_can_notify_region(string $region): bool
{
    $officer = coai_distribution_get_region_officer($region);

    if (!$officer) {
        return false;
    }

    return (
        (int)$officer['is_active'] === 1 &&
        (int)$officer['notify_email'] === 1 &&
        !empty($officer['email'])
    );
}
