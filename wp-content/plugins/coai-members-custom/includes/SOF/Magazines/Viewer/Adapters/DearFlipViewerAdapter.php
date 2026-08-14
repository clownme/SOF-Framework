<?php
if (!defined('ABSPATH')) exit;

class SOF_DearFlipViewerAdapter implements SOF_MagazineViewerAdapterInterface
{
    public function name(): string
    {
        return 'DearFlip';
    }

    public function available(): bool
    {
        return shortcode_exists('dflip');
    }

    public function render(object $magazine): string
    {
        return do_shortcode(
            '[dflip source="' . esc_url($magazine->file_url) . '"][/dflip]'
        );
    }
    
    public function capabilities(): SOF_ViewerCapabilitySet
    {
        $set = new SOF_ViewerCapabilitySet();

        $set->responsive = true;
        $set->fullscreen = true;
        $set->thumbnail_navigation = true;
        $set->background_themes = true;
        $set->mobile_gestures = true;

        return $set;
    }
}