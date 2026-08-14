<?php
/**
 * SOF Distribution Notification Service
 */

if (!defined('ABSPATH')) exit;

function coai_distribution_prepare_region_notification(string $region, array $exportResult = []): array
{
    $officer = coai_distribution_get_region_officer($region);

    if (!$officer) {
        return [
            'success' => false,
            'region'  => $region,
            'message' => 'No active Regional VP assigned.',
        ];
    }

    if (!coai_distribution_can_notify_region($region)) {
        return [
            'success' => false,
            'region'  => $region,
            'message' => 'Notification is disabled or no email is available.',
        ];
    }

    return [
        'success' => true,
        'region'  => $region,
        'to'      => $officer['email'],
        'name'    => $officer['full_name'],
        'subject' => 'COAI Regional Directory Export Updated',
        'message' => 'Notification prepared. Email not sent yet.',
        'export'  => $exportResult,
    ];
}

function coai_distribution_notification_regions_for_export(string $region): array
{
    $region = trim($region);

    if ($region === 'Canada Region') {
        return [
            'Western Canada Region',
            'Eastern Canada Region',
        ];
    }

    return [$region];
}

function coai_distribution_get_notification_contacts_for_export(string $region): array
{
    $notify_regions = coai_distribution_notification_regions_for_export($region);
    $contacts = [];

    foreach ($notify_regions as $notify_region) {
        if (function_exists('coai_comm_get_region_email_contacts')) {
            $rows = coai_comm_get_region_email_contacts($notify_region);

            foreach ($rows as $row) {
                $row['notification_region'] = $notify_region;
                $contacts[] = $row;
            }
        }
    }

    return $contacts;
}