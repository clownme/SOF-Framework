<?php

declare(strict_types=1);

class ExportResult {

    public function __construct(
        public string $status,
        public string $message,
        public ?string $region,
        public int $count,
        public array $file,
        public array $meta,
        public array $notify
    ) {}

    public function fileUrl(): string {
        return $this->file['url'] ?? '';
    }

    public function isSuccess(): bool {
        return $this->status === 'success';
    }
}