<?php
/**
 * SOF Framework
 *
 * Magazine Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOF_MagazineManagerPage
{
    public static function register(): void
    {
        add_management_page(
            'Magazine Manager',
            'Magazine Manager',
            'manage_options',
            'sof-magazine-manager',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        $scan_result = null;
        $save_result = null;
        $viewer_settings_saved = false;

        $flipbook_id = isset($_GET['sof_magazine_flipbook']) ? (int) $_GET['sof_magazine_flipbook'] : 0;
        $edit_id = isset($_GET['sof_magazine_edit']) ? (int) $_GET['sof_magazine_edit'] : 0;

        if ($flipbook_id > 0) {
            self::render_flipbook($flipbook_id);
            return;
        }

        if (
            isset($_POST['sof_magazine_scan'])
            && isset($_POST['_sof_magazine_nonce'])
            && wp_verify_nonce($_POST['_sof_magazine_nonce'], 'sof_magazine_scan')
        ) {
            $scan_result = SOF_MagazineScanner::scan();
        }

        if (
            isset($_POST['sof_magazine_save'])
            && isset($_POST['magazine_id'])
            && isset($_POST['_sof_magazine_edit_nonce'])
            && wp_verify_nonce($_POST['_sof_magazine_edit_nonce'], 'sof_magazine_edit_' . (int) $_POST['magazine_id'])
        ) {
            $save_result = self::save_magazine((int) $_POST['magazine_id']);
            $edit_id = (int) $_POST['magazine_id'];
        }
        
        if (
            isset($_POST['sof_magazine_viewer_settings_save'])
            && isset($_POST['_sof_magazine_viewer_settings_nonce'])
            && wp_verify_nonce($_POST['_sof_magazine_viewer_settings_nonce'], 'sof_magazine_viewer_settings')
        ) {
            $viewer_settings_saved = SOF_MagazineViewerSettings::setPreferred(
                isset($_POST['preferred_viewer']) ? (string) $_POST['preferred_viewer'] : 'auto'
            );
        }

        if ($edit_id > 0) {
            self::render_edit_form($edit_id, $save_result);
            return;
        }

        $stats = self::get_stats();

        echo '<div class="wrap">';
        echo '<h1>Magazine Manager</h1>';
        echo '<p>Manage the SOF Magazine Archive.</p>';
        
        if ($viewer_settings_saved) {
            echo '<div class="notice notice-success"><p>Presentation preferences saved.</p></div>';
        }
        
        self::render_viewer_info_panel();
        self::render_viewer_settings_panel();

        if ($scan_result) {
            echo '<div class="notice notice-success"><p>';
            echo 'Scan complete. ';
            echo 'Scanned: ' . (int) $scan_result['scanned'] . ' | ';
            echo 'Created: ' . (int) $scan_result['created'] . ' | ';
            echo 'Skipped: ' . (int) $scan_result['skipped'];

            if (!empty($scan_result['missing_folder'])) {
                echo ' | Missing folder: yes';
            }

            echo '</p></div>';
        }

        echo '<h2>Archive Status</h2>';
        echo '<table class="widefat striped" style="max-width:600px;">';
        echo '<tbody>';
        echo '<tr><th>Total Magazines</th><td>' . (int) $stats['total'] . '</td></tr>';
        echo '<tr><th>Needs Review</th><td>' . (int) $stats['needs_review'] . '</td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<form method="post" style="margin-top:20px;">';
        wp_nonce_field('sof_magazine_scan', '_sof_magazine_nonce');
        echo '<input type="hidden" name="sof_magazine_scan" value="1">';
        submit_button('Scan Library');
        echo '</form>';

        echo '<h2>Imported Magazines</h2>';

        $magazines = self::get_magazines();

        if (empty($magazines)) {
            echo '<p>No magazines imported yet.</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Year</th>';
            echo '<th>Cover</th>';
            echo '<th>Publication</th>';
            echo '<th>Issue Label</th>';
            echo '<th>Vol / No</th>';
            echo '<th>Description</th>';
            echo '<th>File</th>';
            echo '<th>Status</th>';
            echo '<th>Actions</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($magazines as $magazine) {
                $edit_url = add_query_arg(
                    ['sof_magazine_edit' => (int) $magazine->id],
                    admin_url('tools.php?page=sof-magazine-manager')
                );

                $flipbook_url = add_query_arg(
                    ['sof_magazine_flipbook' => (int) $magazine->id],
                    admin_url('tools.php?page=sof-magazine-manager')
                );

                echo '<tr>';
                echo '<td>' . esc_html($magazine->display_year ?: $magazine->year_folder) . '</td>';
                $cover_html = '';

                if (!empty($magazine->cover_attachment_id)) {
                    $cover_html = wp_get_attachment_image(
                        (int) $magazine->cover_attachment_id,
                        'thumbnail',
                        false,
                        [
                            'style' => 'width:60px;height:80px;object-fit:cover;border-radius:4px;box-shadow:0 2px 6px rgba(0,0,0,.2);'
                        ]
                    );
                }

                echo '<td>' . ($cover_html ?: '<span style="color:#777;">No cover</span>') . '</td>';
                echo '<td><strong>' . esc_html($magazine->publication_name ?: $magazine->title) . '</strong></td>';
                echo '<td>' . esc_html($magazine->issue_label ?: $magazine->title) . '</td>';
                echo '<td>' . esc_html(self::format_volume_issue($magazine)) . '</td>';
                echo '<td>' . esc_html(wp_trim_words((string) ($magazine->description ?? ''), 12)) . '</td>';
                echo '<td>' . esc_html($magazine->file_name) . '</td>';
                echo '<td>' . esc_html($magazine->status) . '</td>';
                echo '<td>';
                echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Edit</a> ';
                echo '<a class="button button-small" href="' . esc_url($magazine->file_url) . '" target="_blank" rel="noopener">View PDF</a> ';
                echo '<a class="button button-small button-primary" href="' . esc_url($flipbook_url) . '">View Flipbook</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }
        echo '</div>';
    }

    protected static function save_magazine(int $id): bool
    {
        global $wpdb;

        $table = sof_magazine_table_name();

        $updated = $wpdb->update(
            $table,
            [
                'publication_name' => sanitize_text_field($_POST['publication_name'] ?? ''),
                'issue_label'      => sanitize_text_field($_POST['issue_label'] ?? ''),
                'volume'           => sanitize_text_field($_POST['volume'] ?? ''),
                'issue_number'     => sanitize_text_field($_POST['issue_number'] ?? ''),
                'description'      => sanitize_textarea_field($_POST['description'] ?? ''),
                'cover_attachment_id' => isset($_POST['cover_attachment_id']) ? (int) $_POST['cover_attachment_id'] : 0,
                'status'           => sanitize_key($_POST['status'] ?? 'imported'),
                'notes'            => sanitize_textarea_field($_POST['notes'] ?? ''),
                'updated_at'       => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        return $updated !== false;
    }

    protected static function render_edit_form(int $id, ?bool $save_result = null): void
    {
        global $wpdb;

        $table = sof_magazine_table_name();
        $magazine = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id)
        );

        if (!$magazine) {
            echo '<div class="wrap"><h1>Magazine Not Found</h1></div>';
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>Edit Magazine Metadata</h1>';

        echo '<p><a class="button" href="' . esc_url(admin_url('tools.php?page=sof-magazine-manager')) . '">&larr; Back to Magazine Manager</a></p>';

        if ($save_result === true) {
            echo '<div class="notice notice-success"><p>Magazine metadata saved.</p></div>';
        } elseif ($save_result === false) {
            echo '<div class="notice notice-error"><p>Magazine metadata could not be saved.</p></div>';
        }

        echo '<form method="post">';
        wp_enqueue_media();
        wp_nonce_field('sof_magazine_edit_' . (int) $magazine->id, '_sof_magazine_edit_nonce');
        echo '<input type="hidden" name="sof_magazine_save" value="1">';
        echo '<input type="hidden" name="magazine_id" value="' . (int) $magazine->id . '">';

        echo '<table class="form-table" role="presentation"><tbody>';
        self::text_row('Publication Name', 'publication_name', $magazine->publication_name ?: 'The New Calliope');
        self::text_row('Issue Label', 'issue_label', $magazine->issue_label ?: $magazine->title);
        self::text_row('Volume', 'volume', $magazine->volume ?? '');
        self::text_row('Issue Number', 'issue_number', $magazine->issue_number ?? '');
        self::textarea_row('Description', 'description', $magazine->description ?: 'Official Publication of Clowns of America, International');
        self::cover_image_row((int) ($magazine->cover_attachment_id ?? 0));
        self::select_row('Status', 'status', $magazine->status ?: 'imported');
        self::textarea_row('Notes', 'notes', $magazine->notes ?? '');
        echo '<tr><th scope="row">File</th><td>' . esc_html($magazine->file_name) . '</td></tr>';
        echo '</tbody></table>';

        submit_button('Save Metadata');
        echo '</form>';
        echo '</div>';
    }

    protected static function text_row(string $label, string $name, string $value): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input class="regular-text" type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"></td>';
        echo '</tr>';
    }

    protected static function textarea_row(string $label, string $name, string $value): void
    {
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><textarea class="large-text" rows="4" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea></td>';
        echo '</tr>';
    }
    
    protected static function cover_image_row(int $attachment_id): void
    {
        $image_url = $attachment_id > 0
            ? wp_get_attachment_image_url($attachment_id, 'medium')
            : '';

        echo '<tr>';
        echo '<th scope="row"><label for="cover_attachment_id">Cover Image</label></th>';
        echo '<td>';

        echo '<input type="hidden" id="cover_attachment_id" name="cover_attachment_id" value="' . (int) $attachment_id . '">';

        echo '<div id="sof-cover-preview" style="margin-bottom:12px;">';

        if ($image_url) {
            echo '<img src="' . esc_url($image_url) . '" style="max-width:160px;height:auto;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.2);">';
        } else {
            echo '<span style="color:#777;">No cover selected.</span>';
        }

        echo '</div>';

        echo '<button type="button" class="button" id="sof-select-cover">Select Cover Image</button> ';
        echo '<button type="button" class="button" id="sof-remove-cover">Remove Cover</button>';

        echo '<script>
        jQuery(document).ready(function($){
            let frame;

            $("#sof-select-cover").on("click", function(e){
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: "Select Magazine Cover",
                    button: {
                        text: "Use this cover"
                    },
                    multiple: false
                });

                frame.on("select", function(){
                    const attachment = frame.state().get("selection").first().toJSON();

                    $("#cover_attachment_id").val(attachment.id);

                    const imageUrl = attachment.sizes && attachment.sizes.medium
                       ? attachment.sizes.medium.url
                        : attachment.url;

                    $("#sof-cover-preview").html(
                        "<img src=\"" + imageUrl + "\" style=\"max-width:160px;height:auto;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.2);\">"
                    );
                });

                frame.open();
            });

            $("#sof-remove-cover").on("click", function(e){
                e.preventDefault();
                $("#cover_attachment_id").val("0");
                $("#sof-cover-preview").html("<span style=\"color:#777;\">No cover selected.</span>");
            });
        });
        </script>';

        echo '</td>';
        echo '</tr>';
    }

    protected static function select_row(string $label, string $name, string $value): void
    {
        $statuses = [
            'imported' => 'Imported',
            'needs_review' => 'Needs Review',
            'published' => 'Published',
            'hidden' => 'Hidden',
        ];

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';

        foreach ($statuses as $key => $status_label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($status_label) . '</option>';
        }

        echo '</select></td>';
        echo '</tr>';
    }

    protected static function get_stats(): array
    {
        global $wpdb;

        $table = sof_magazine_table_name();

        return [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'needs_review' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'needs_review'"
            ),
        ];
    }

    protected static function get_magazines(): array
    {
        global $wpdb;

        $table = sof_magazine_table_name();

        return $wpdb->get_results(
            "SELECT *
             FROM {$table}
             ORDER BY display_year DESC, start_month ASC, title ASC
             LIMIT 100"
        );
    }

    protected static function render_flipbook(int $id): void
    {
        global $wpdb;

        $table = sof_magazine_table_name();

        $magazine = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $id
            )
        );

        if (!$magazine) {
            echo '<div class="wrap"><h1>Magazine Not Found</h1></div>';
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($magazine->publication_name ?: $magazine->title) . '</h1>';
        echo '<h2>' . esc_html($magazine->issue_label ?: $magazine->title) . '</h2>';

        if (self::format_volume_issue($magazine) !== '') {
            echo '<p><strong>' . esc_html(self::format_volume_issue($magazine)) . '</strong></p>';
        }

        if (!empty($magazine->description)) {
            echo '<p>' . esc_html($magazine->description) . '</p>';
        }

        echo '<p>';
        echo '<a class="button" href="' . esc_url(admin_url('tools.php?page=sof-magazine-manager')) . '">&larr; Back to Magazine Manager</a>';
        echo ' ';
        echo '<a class="button" href="' . esc_url($magazine->file_url) . '" target="_blank" rel="noopener">Open PDF</a>';
        echo '</p>';

        echo do_shortcode('[dflip source="' . esc_url($magazine->file_url) . '"][/dflip]');

        echo '</div>';
    }

    protected static function format_volume_issue($magazine): string
    {
        $parts = [];

        if (!empty($magazine->volume)) {
            $parts[] = 'Volume ' . $magazine->volume;
        }

        if (!empty($magazine->issue_number)) {
            $parts[] = 'Number ' . $magazine->issue_number;
        }

        return implode(' • ', $parts);
    }

    private static function render_viewer_info_panel(): void
    {
        $active_name = SOF_MagazineViewerManager::active_viewer_name();

        echo '<div class="postbox" style="margin-top:20px;">';
        echo '<div class="postbox-header"><h2>Presentation Engine</h2></div>';
        echo '<div class="inside">';

        echo '<p><strong>Active Viewer:</strong> ' . esc_html($active_name) . '</p>';

        foreach (SOF_MagazineViewerManager::ordered_adapters_for_display() as $adapter) {
            $available = $adapter->available();
            $capabilities = $adapter->capabilities();

            echo '<hr>';
            echo '<h3>' . esc_html($adapter->name()) . '</h3>';
            echo '<p><strong>Status:</strong> ' . ($available ? '🟢 Available' : '⚪ Not Available') . '</p>';

            echo '<p><strong>Capabilities:</strong></p>';
            echo '<ul style="margin-left:20px;">';

            self::render_capability_item('Responsive Layout', $capabilities->responsive);
            self::render_capability_item('Fullscreen', $capabilities->fullscreen);
            self::render_capability_item('Thumbnail Navigation', $capabilities->thumbnail_navigation);
            self::render_capability_item('Search', $capabilities->search);
           self::render_capability_item('Background Themes', $capabilities->background_themes);
            self::render_capability_item('Mobile Gestures', $capabilities->mobile_gestures);
            self::render_capability_item('Custom Toolbar', $capabilities->custom_toolbar);

            echo '</ul>';
        }

        echo '</div>';
        echo '</div>';
    }

    private static function render_capability_item(string $label, bool $supported): void
    {
        echo '<li>' . ($supported ? '✅ ' : '— ') . esc_html($label) . '</li>';
    }
    
    private static function render_viewer_settings_panel(): void
    {
        $preferred = SOF_MagazineViewerSettings::preferred();
        $options = SOF_MagazineViewerSettings::availableOptions();

        echo '<div class="postbox" style="margin-top:20px;max-width:900px;">';
        echo '<div class="postbox-header"><h2>Presentation Preferences</h2></div>';
        echo '<div class="inside">';

        echo '<form method="post">';
        wp_nonce_field('sof_magazine_viewer_settings', '_sof_magazine_viewer_settings_nonce');

        echo '<input type="hidden" name="sof_magazine_viewer_settings_save" value="1">';

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="preferred_viewer">Preferred Engine</label></th>';
        echo '<td>';
        echo '<select name="preferred_viewer" id="preferred_viewer">';

        foreach ($options as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($preferred, $value, false) . '>';
            echo esc_html($label);
            echo '</option>';
        }

        echo '</select>';
        echo '<p class="description">Choose how SOF should present magazines. Automatic will use the first available supported engine.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        submit_button('Save Presentation Preferences');

        echo '</form>';

        echo '</div>';
        echo '</div>';
    }
}