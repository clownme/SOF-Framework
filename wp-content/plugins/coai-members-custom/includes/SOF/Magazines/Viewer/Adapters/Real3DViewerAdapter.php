<?php
if (!defined('ABSPATH')) exit;

class SOF_Real3DViewerAdapter implements SOF_MagazineViewerAdapterInterface
{
    public function name(): string
    {
        return 'Real3D FlipBook';
    }

    public function available(): bool
    {
        return shortcode_exists('real3dflipbook');
    }

    public function render(object $magazine): string
    {
        return do_shortcode(
            '[real3dflipbook pdf="' . esc_url($magazine->file_url) . '"]'
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