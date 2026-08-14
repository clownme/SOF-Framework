<?php

declare(strict_types=1);

class ExportNormalizer {

    public static function make(array $raw): ExportResult {

        $url =
            $raw['google_file_link']
            ?? $raw['upload']['file_link']
            ?? $raw['file_link']
            ?? '';

        return new ExportResult(
            status: $raw['success'] ? 'success' : 'error',
            message: $raw['message'] ?? '',
            region: $raw['region'] ?? null,
            count: (int)($raw['count'] ?? 0),

            file: [
                'url'  => $url,
                'name' => $raw['filename'] ?? '',
                'id'   => $raw['file_id'] ?? null,
            ],

            meta: [
                'type'       => $raw['type'] ?? 'region',
                'processed'  => $raw['processed'] ?? 0,
                'success'    => $raw['successful_regions'] ?? 0,
                'failed'     => $raw['failed_regions'] ?? 0,
            ],

            notify: $raw['notify'] ?? [
                'enabled' => false,
                'recipients' => [],
                'status' => 'disabled',
                'message' => '',
            ]
        );
    }
}