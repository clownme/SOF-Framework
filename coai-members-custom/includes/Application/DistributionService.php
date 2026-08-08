<?php

declare(strict_types=1);

class DistributionService {

    public function exportRegion(string $region): ExportResult {

        $result = coai_distribution_execute_region($region);

        return ExportNormalizer::make([
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? '',
            'region'  => $result['region'] ?? $region,
            'count'   => $result['count'] ?? 0,

            'google_file_link' => $result['google_file_link'] ?? null,
            'upload' => $result['upload'] ?? [],

            'filename' => $result['filename'] ?? '',
            'file_id'  => $result['file_id'] ?? null,

            'notify' => $this->notify($result),
        ]);
    }

    public function exportMaster(array $regions): ExportResult {

        $summary = coai_distribution_execute_regions($regions);

        return ExportNormalizer::make([
            'success' => true,
            'message' => 'Master export completed',

            'type' => 'master',

            'processed' => $summary['processed_regions'] ?? 0,
            'successful_regions' => $summary['successful_regions'] ?? 0,
            'failed_regions' => $summary['failed_regions'] ?? 0,

            'notify' => $this->notify($summary),
        ]);
    }

    private function notify($result): array {

        if (!function_exists('coai_comm_notify_region_export')) {
            return [
                'enabled' => false,
                'recipients' => [],
                'status' => 'disabled',
                'message' => 'Notification service missing'
            ];
        }

        return coai_comm_notify_region_export($result);
    }
}