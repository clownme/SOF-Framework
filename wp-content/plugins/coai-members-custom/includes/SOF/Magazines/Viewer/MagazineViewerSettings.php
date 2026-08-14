<?php

if (!defined('ABSPATH')) {
    exit;
}

class SOF_MagazineViewerSettings
{
    public const OPTION_NAME = 'sof_magazine_viewer_preferred';

    public static function preferred(): string
    {
        $preferred = get_option(self::OPTION_NAME, 'auto');

        return array_key_exists($preferred, self::availableOptions())
            ? $preferred
            : 'auto';
    }

    public static function setPreferred(string $viewer): bool
    {
        $viewer = sanitize_key($viewer);

        if (!array_key_exists($viewer, self::availableOptions())) {
            $viewer = 'auto';
        }

        return update_option(self::OPTION_NAME, $viewer);
    }

    public static function availableOptions(): array
    {
        return [
            'auto'     => 'Automatic',
            'real3d'   => 'Real3D FlipBook',
            'dearflip' => 'DearFlip',
            'pdf'      => 'Browser PDF Viewer',
        ];
    }

    public static function isPreferred(string $viewer): bool
    {
        return self::preferred() === $viewer;
    }
}