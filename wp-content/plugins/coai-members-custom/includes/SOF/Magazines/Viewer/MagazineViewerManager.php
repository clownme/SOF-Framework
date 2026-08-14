<?php
if (!defined('ABSPATH')) exit;

class SOF_MagazineViewerManager
{
    public static function render(object $magazine): string
    {
        foreach (self::ordered_adapters() as $adapter) {
            if ($adapter->available()) {
                return $adapter->render($magazine);
            }
        }

        return '';
    }

    public static function active_viewer_name(): string
    {
        foreach (self::ordered_adapters() as $adapter) {
            if ($adapter->available()) {
                return $adapter->name();
            }
        }

        return 'None';
    }

    private static function ordered_adapters(): array
    {
        $preferred = SOF_MagazineViewerSettings::preferred();

        $adapters = [
            'real3d'   => new SOF_Real3DViewerAdapter(),
            'dearflip' => new SOF_DearFlipViewerAdapter(),
            'pdf'      => new SOF_PdfFallbackViewerAdapter(),
        ];

        if ($preferred !== 'auto' && isset($adapters[$preferred])) {
            return array_merge(
                [$adapters[$preferred]],
                array_diff_key($adapters, [$preferred => true])
            );
        }

        return [
            $adapters['real3d'],
            $adapters['dearflip'],
            $adapters['pdf'],
        ];
    }
    
    public static function ordered_adapters_for_display(): array
    {
        return self::ordered_adapters();
    }
}