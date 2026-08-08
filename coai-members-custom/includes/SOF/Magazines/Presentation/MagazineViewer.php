<?php
/**
 * SOF Magazine Viewer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOF_MagazineViewer
{
    public static function render(object $magazine): string
    {
        ob_start();

        $publication_name = !empty($magazine->publication_name)
            ? $magazine->publication_name
            : 'The New Calliope';

        $issue_label = !empty($magazine->issue_label)
            ? $magazine->issue_label
            : $magazine->title;

        echo '<div class="coai-magazine-reader-block">';

        echo '<h1 class="coai-magazine-title">' . esc_html($publication_name) . '</h1>';
        echo '<div class="coai-magazine-subtitle">' . esc_html($issue_label) . '</div>';

        $meta_parts = [];

        if (!empty($magazine->volume)) {
            $meta_parts[] = 'Volume ' . $magazine->volume;
        }

        if (!empty($magazine->issue_number)) {
            $meta_parts[] = 'Number ' . $magazine->issue_number;
        }

        $meta = implode(' • ', $meta_parts);

        if ($meta !== '') {
            echo '<div class="coai-magazine-meta">' . esc_html($meta) . '</div>';
        }

        if (!empty($magazine->description)) {
            echo '<div class="coai-magazine-description">' . esc_html($magazine->description) . '</div>';
        }

        echo '<div class="coai-magazine-viewer">';
        echo SOF_MagazineViewerManager::render($magazine);
        echo '</div>';

        echo '</div>';

        return ob_get_clean();
    }
}