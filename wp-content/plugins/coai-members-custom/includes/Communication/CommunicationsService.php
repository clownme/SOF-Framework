<?php

declare(strict_types=1);

class CommunicationsService {

    public function buildEmailPayload(ExportResult $result): array {

        return [
            'subject' => 'COAI Regional Export Complete',

            'body' => [
                'message' => $result->message,
                'region'  => $result->region,
                'link'    => $result->fileUrl(),   // 🔥 SINGLE SOURCE OF TRUTH
                'count'   => $result->count,
            ],

            'recipients' => $result->notify['recipients'] ?? []
        ];
    }
}