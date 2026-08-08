<?php
/**
 * SOF Distribution Framework
 *
 * Distribution Execution Service
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Execute distribution for one or more COAI Regions.
 *
 * One process, multiple ways.
 *
 * @param array $regions
 * @param array $options
 * @return array
 */
function coai_distribution_execute_regions(array $regions, array $options = []): array
{
    $regions = array_values(array_unique(array_filter(array_map('trim', $regions))));

    $summary = [
        'success'            => false,
        'requested_regions'  => count($regions),
        'processed_regions'  => 0,
        'successful_regions' => 0,
        'failed_regions'     => 0,
        'results'            => [],
        'errors'             => [],
    ];

    if (empty($regions)) {
        $summary['errors'][] = 'No COAI Regions selected.';
        return $summary;
    }

    foreach ($regions as $region) {
        $result = coai_distribution_execute_region($region, $options);

        $summary['processed_regions']++;
        $summary['results'][$region] = $result;

        if (!empty($result['success'])) {
            $summary['successful_regions']++;
        } else {
            $summary['failed_regions']++;

            if (!empty($result['errors']) && is_array($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $summary['errors'][] = $region . ': ' . $error;
                }
            } elseif (!empty($result['message'])) {
                $summary['errors'][] = $region . ': ' . $result['message'];
            }
        }
    }

    $summary['success'] = ($summary['failed_regions'] === 0 && $summary['successful_regions'] > 0);

    return $summary;
}

/**
 * Execute distribution for a single COAI Region.
 *
 * Version 1 delegates to the existing Google export service.
 *
 * @param string $region
 * @param array $options
 * @return array
 */

function coai_distribution_execute_region(string $region, array $options = []): array
{
    if (!function_exists('coai_google_export_region')) {
        return [
            'success' => false,
            'region'  => $region,
            'message' => 'Google export service is unavailable.',
            'errors'  => ['Google export service is unavailable.'],
        ];
    }

    $result = coai_google_export_region($region);

    /**
     * 🔥 CRITICAL FIX: Flatten upload structure for RVP emails
     */
    $upload = $result['upload'] ?? [];
    
    $folder_id = '';

    if (function_exists('coai_google_export_folder_map')) {
        $folder_map = coai_google_export_folder_map();
        $folder_id = (string)($folder_map[$region] ?? '');
    }

    $result['folder_url'] = $folder_id !== ''
        ? 'https://drive.google.com/drive/folders/' . $folder_id
        : '';

        $result['google_file_id'] = $upload['file_id'] ?? ($result['google_file_id'] ?? '');
        $result['google_file_link'] = $upload['file_link'] ?? '';
 
   // Ensure consistency for downstream consumers
    $result['region'] = $region;

    /**
     * SOF Notification Hook
     *
     * Notifications happen after distribution completes.
     * The export result remains valid even if notification fails.
     */
    if (class_exists('SOF_NotificationService')) {
        $result['notification'] = SOF_NotificationService::notify($result, $options);
    } else {
        $result['notification'] = [
            'attempted' => false,
            'success'   => false,
            'message'   => 'Notification service is unavailable.',
        ];
    }

    return $result;
}

/**
 * Get all configured COAI Region labels for distribution.
 *
 * @return array
 */
 
function coai_distribution_get_all_region_labels(): array
{
    if (!function_exists('coai_get_coai_regions')) {
        return [];
    }

    $regions = coai_get_coai_regions();

    if (empty($regions) || !is_array($regions)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($region) {
        if (is_array($region) && !empty($region['label'])) {
            return trim((string) $region['label']);
        }

        if (is_string($region)) {
            return trim($region);
        }

        return '';
    }, $regions)));
}