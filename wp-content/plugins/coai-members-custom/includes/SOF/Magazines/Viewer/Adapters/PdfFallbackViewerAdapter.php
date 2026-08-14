<?php
if (!defined('ABSPATH')) exit;

class SOF_PdfFallbackViewerAdapter implements SOF_MagazineViewerAdapterInterface
{
    public function name(): string
    {
        return 'Browser PDF Viewer';
    }

    public function available(): bool
    {
        return true;
    }

    public function render(object $magazine): string
    {
        return '<iframe src="' . esc_url($magazine->file_url) . '" style="width:100%;height:850px;border:0;"></iframe>';
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