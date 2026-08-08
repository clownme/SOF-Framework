<?php

if (!defined('ABSPATH')) {
    exit;
}

interface SOF_MagazineViewerAdapterInterface
{
    public function name(): string;

    public function available(): bool;

    public function capabilities(): SOF_ViewerCapabilitySet;

    public function render(object $magazine): string;
}