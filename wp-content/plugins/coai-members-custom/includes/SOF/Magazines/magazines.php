<?php
/**
 * SOF Framework
 *
 * Magazine Framework
 *
 * Handles COAI magazine archive discovery,
 * metadata storage, and future presentation/search.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Models/Magazine.php';

require_once __DIR__ . '/Services/MagazineCoverGenerator.php';
require_once __DIR__ . '/Services/MagazineScanner.php';

require_once __DIR__ . '/Viewer/Models/ViewerCapabilitySet.php';
require_once __DIR__ . '/Viewer/Contracts/MagazineViewerAdapterInterface.php';
require_once __DIR__ . '/Viewer/Adapters/Real3DViewerAdapter.php';
require_once __DIR__ . '/Viewer/Adapters/DearFlipViewerAdapter.php';
require_once __DIR__ . '/Viewer/Adapters/PdfFallbackViewerAdapter.php';
require_once __DIR__ . '/Viewer/MagazineViewerSettings.php';
require_once __DIR__ . '/Viewer/MagazineViewerManager.php';

require_once __DIR__ . '/Presentation/MagazineViewer.php';
require_once __DIR__ . '/Presentation/MagazineArchiveShortcode.php';

require_once __DIR__ . '/Admin/MagazineManagerPage.php';
add_action('init', ['SOF_MagazineArchiveShortcode', 'register']);

add_action('admin_menu', ['SOF_MagazineManagerPage', 'register']);

function sof_magazine_table_name(): string
{
    global $wpdb;
    return $wpdb->prefix . 'sof_magazines';
}

function sof_magazine_install_table(): void
{
    global $wpdb;

    $table = sof_magazine_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        publication_name VARCHAR(255) NULL,
        issue_label VARCHAR(255) NULL,
        volume VARCHAR(50) NULL,
        issue_number VARCHAR(50) NULL,
        description TEXT NULL,
        cover_attachment_id BIGINT UNSIGNED NULL,
        year_folder VARCHAR(20) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path TEXT NOT NULL,
        file_url TEXT NOT NULL,
        display_year INT NULL,
        start_month INT NULL,
        end_month INT NULL,
        issue_type VARCHAR(50) NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'imported',
        notes TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY file_path_unique (file_path(191)),
        KEY display_year (display_year),
        KEY issue_type (issue_type),
        KEY status (status)
    ) {$charset_collate};";

    dbDelta($sql);
    sof_magazine_backfill_metadata();
}

function sof_magazine_backfill_metadata(): void
{
    global $wpdb;

    $table = sof_magazine_table_name();

    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

    if ($exists !== $table) {
        return;
    }

    $wpdb->query(
        "UPDATE {$table}
         SET publication_name = 'The New Calliope'
         WHERE publication_name IS NULL OR publication_name = ''"
    );

    $wpdb->query(
        "UPDATE {$table}
         SET issue_label = title
         WHERE issue_label IS NULL OR issue_label = ''"
    );

    $wpdb->query(
        "UPDATE {$table}
         SET description = 'Official Publication of Clowns of America, International'
         WHERE description IS NULL OR description = ''"
    );
}

add_action('init', 'sof_magazine_install_table');

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (empty($_GET['sof_scan_magazines'])) {
        return;
    }

    $result = SOF_MagazineScanner::scan();

    wp_die(
        '<pre>' . esc_html(print_r($result, true)) . '</pre>',
        'SOF Magazine Scan'
    );
});

add_action('wp_ajax_sof_load_magazine_viewer', 'sof_load_magazine_viewer');
add_action('wp_ajax_nopriv_sof_load_magazine_viewer', 'sof_load_magazine_viewer');

function sof_load_magazine_viewer(): void
{
    global $wpdb;

    $magazine_id = isset($_POST['magazine_id']) ? (int) $_POST['magazine_id'] : 0;

    if ($magazine_id <= 0) {
        wp_send_json_error(['message' => 'Invalid magazine.']);
    }

    $table = sof_magazine_table_name();

    $magazine = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            $magazine_id
        )
    );

    if (!$magazine) {
        wp_send_json_error(['message' => 'Magazine not found.']);
    }

    $html = SOF_MagazineViewer::render($magazine);

    if (empty($html)) {
        wp_send_json_error(['message' => 'Viewer rendered empty HTML.']);
    }

    wp_send_json_success([
        'html' => $html,
    ]);
}
