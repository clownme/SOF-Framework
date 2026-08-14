<?php
/**
 * SOF Magazine Archive
 *
 * Automatic Magazine Cover Generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOF_MagazineCoverGenerator
{
    public static function generate_from_pdf(string $pdf_path, string $title = ''): int
    {
        if (!file_exists($pdf_path)) {
            return 0;
        }

        if (!class_exists('Imagick')) {
            return 0;
        }

        try {
            $upload = wp_upload_dir();

            if (empty($upload['basedir'])) {
                return 0;
            }

            $cover_dir = trailingslashit($upload['basedir']) . 'sof-magazine-covers/';

            if (!file_exists($cover_dir)) {
                wp_mkdir_p($cover_dir);
            }

            $base_name = sanitize_title(pathinfo($pdf_path, PATHINFO_FILENAME));
            $jpg_path  = $cover_dir . $base_name . '-cover.jpg';

            if (file_exists($jpg_path)) {
                $existing_id = self::find_attachment_by_path($jpg_path);
                if ($existing_id > 0) {
                    return $existing_id;
                }
            }

            $imagick = new Imagick();
            $imagick->setResolution(160, 160);
            $imagick->readImage($pdf_path . '[0]');
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(88);
            $imagick->setImageBackgroundColor('white');

            if ($imagick->getImageAlphaChannel()) {
                $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            }

            $imagick->thumbnailImage(900, 1200, true);
            $imagick->writeImage($jpg_path);
            $imagick->clear();
            $imagick->destroy();

            if (!file_exists($jpg_path)) {
                return 0;
            }

            return self::create_attachment($jpg_path, $title ?: $base_name);

        } catch (Throwable $e) {
            error_log('[SOF Magazine Cover Generator] ' . $e->getMessage());
            return 0;
        }
    }

    protected static function create_attachment(string $file_path, string $title): int
    {
        $upload = wp_upload_dir();

        $filetype = wp_check_filetype(basename($file_path), null);

        $attachment = [
            'guid'           => trailingslashit($upload['baseurl']) . 'sof-magazine-covers/' . basename($file_path),
            'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
            'post_title'     => sanitize_text_field($title),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        return (int) $attachment_id;
    }

    protected static function find_attachment_by_path(string $file_path): int
    {
        global $wpdb;

        $upload = wp_upload_dir();
        $relative = str_replace(trailingslashit($upload['basedir']), '', $file_path);

        $attachment_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = '_wp_attached_file'
                 AND meta_value = %s
                 LIMIT 1",
                $relative
            )
        );

        return $attachment_id;
    }
}