<?php
if (!defined('ABSPATH')) exit;

/**
 * --------------------------------------------------------
 * COAI Distribution Service
 *
 * SOF v4.0
 * 
 * Responsibilities:
 *-  Retrieve members for a COAI region
 * - Build CSV export data
 * - Determine export filename
 * - Send CSV to Google Drive driver
 * - Return standardized export result
 * 
 * Current driver:
 * - Google Drive
 * --------------------------------------------------------
 */

function coai_google_rows_for_region(string $region): array
{
    global $wpdb;

    $table = coai_get_members_table();

    $filters = coai_md_build_filters($table, [
        'coai_region' => $region,
    ]);

    $sql = "SELECT `$table`.*
            FROM `$table`
            {$filters['join_sql']}
            {$filters['where']}
            ORDER BY last_name, first_name, username";

    return !empty($filters['args'])
        ? $wpdb->get_results($wpdb->prepare($sql, ...$filters['args']), ARRAY_A)
        : $wpdb->get_results($sql, ARRAY_A);
}

function coai_google_build_csv(array $rows): string
{
    $fp = fopen('php://temp', 'w+');

    if (!empty($rows)) {
        fputcsv($fp, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
    } else {
        fputcsv($fp, ['No rows']);
    }

    rewind($fp);

    $csv = stream_get_contents($fp);

    fclose($fp);

    return $csv ?: '';
}

function coai_google_export_region(string $region): array
{
    $region = trim($region);

    if ($region === '') {
        return [
            'success'  => false,
            'region'   => '',
            'count'    => 0,
            'filename' => '',
            'csv'      => '',
            'upload'   => [],
            'message'  => 'No COAI Region selected.',
            'errors'   => ['No COAI Region selected.'],
        ];
    }

    $rows = coai_google_rows_for_region($region);

    if (empty($rows)) {
        return [
            'success'  => false,
            'region'   => $region,
            'count'    => 0,
            'filename' => '',
            'csv'      => '',
            'upload'   => [],
            'message'  => 'No members found for this COAI Region.',
            'errors'   => ['No members found for this COAI Region.'],
        ];
    }

    $csv = coai_google_build_csv($rows);
    $filename = coai_google_export_filename_for_region($region);
    $member_count = count($rows);

    $upload = coai_google_drive_upload_csv(
        $csv,
        $filename,
        $region,
        $member_count
    );

    if (is_wp_error($upload)) {
        $error_message = $upload->get_error_message();

        return [
            'success'  => false,
            'region'   => $region,
            'count'    => $member_count,
            'filename' => $filename,
            'csv'      => $csv,
            'upload'   => [],
            'message'  => $error_message,
            'errors'   => [$error_message],
        ];
    }

    return [
        'success'  => true,
        'region'   => $region,
        'count'    => $member_count,
        'filename' => $filename,
        'csv'      => $csv,
        'upload'   => $upload,
        'message'  => 'Google Drive export completed successfully.',
        'errors'   => [],
    ];
}