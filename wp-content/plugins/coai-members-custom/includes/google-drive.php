<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/google-config.php';
require_once __DIR__ . '/google-logger.php';

/**
 * COAI Google Drive OAuth Export Service
 * SOF v4.3 OAuth implementation
 */

/* ------------------------------------------------------------
 * OAuth Config
 * ------------------------------------------------------------ */

function coai_google_oauth_client_config() {
    if (!defined('COAI_GOOGLE_OAUTH_CLIENT_PATH') || !file_exists(COAI_GOOGLE_OAUTH_CLIENT_PATH)) {
        return new WP_Error('missing_oauth_client', 'Google OAuth client JSON file not found.');
    }

    $json = json_decode(file_get_contents(COAI_GOOGLE_OAUTH_CLIENT_PATH), true);

    if (empty($json['web']['client_id']) || empty($json['web']['client_secret'])) {
        return new WP_Error('bad_oauth_client', 'Google OAuth client JSON is invalid.');
    }

    return $json['web'];
}

function coai_google_oauth_redirect_uri() {
    return home_url('/google-oauth-callback');
}

function coai_google_is_connected(): bool
{
    return !empty(get_option('coai_google_refresh_token'));
}

/* ------------------------------------------------------------
 * Google OAuth Reconnect
 * URL: /google-oauth-reconnect
 * ------------------------------------------------------------ */

add_action('init', function () {

    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    if ($path !== 'google-oauth-reconnect') {
        return;
    }

    delete_option('coai_google_refresh_token');
    delete_option('coai_google_connected_at');

    error_log('[COAI GOOGLE] OAuth reconnect requested via init.');

    wp_safe_redirect(coai_google_oauth_authorize_url());
    exit;
});

/* ------------------------------------------------------------
 * Region Filename / Folder Mapping
 * ------------------------------------------------------------ */

function coai_google_export_filename_for_region($region) {
    $region = trim((string) $region);

    $map = [
        'North East Region'     => 'Northeast.csv',
        'North Central Region'  => 'Northcentral.csv',
        'North West Region'     => 'Northwest.csv',
        'Mid East Region'       => 'Mideast.csv',
        'Mid West Region'       => 'Midwest.csv',
        'South East Region'     => 'Southeast.csv',
        'South West Region'     => 'Southwest.csv',
        'South Central Region'  => 'Southcentral.csv',
        'Canada Region'         => 'Canada.csv',
        'Latin Region'          => 'Latin.csv',
        'International Region'  => 'International.csv',
        'International Regions' => 'International.csv',
    ];

    return $map[$region] ?? sanitize_title($region) . '.csv';
}

/* ------------------------------------------------------------
 * OAuth Authorization URL
 * ------------------------------------------------------------ */

function coai_google_oauth_authorize_url() {
    $cfg = coai_google_oauth_client_config();
    if (is_wp_error($cfg)) return '#';

    return add_query_arg([
        'client_id'              => $cfg['client_id'],
        'redirect_uri'           => coai_google_oauth_redirect_uri(),
        'response_type'          => 'code',
        'scope'                  => 'https://www.googleapis.com/auth/drive',
        'access_type'            => 'offline',
        'prompt'                 => 'consent',
        'include_granted_scopes' => 'true',
    ], 'https://accounts.google.com/o/oauth2/v2/auth');
}

/* ------------------------------------------------------------
 * Connect Button Handler
 * ------------------------------------------------------------ */

add_action('template_redirect', function () {
    if (!is_page('member-directory')) return;
    if (empty($_GET['coai_google_auth'])) return;

    if (!is_user_logged_in() || !coai_staff_can('view')) {
        wp_die('Unauthorized', '', 403);
    }

    wp_redirect(coai_google_oauth_authorize_url());
    exit;
});

/* ------------------------------------------------------------
 * OAuth Callback Handler
 * ------------------------------------------------------------ */

add_action('template_redirect', function () {
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    if ($path !== 'google-oauth-callback') {
        return;
    }

    error_log('[COAI GOOGLE] OAuth callback hit: ' . ($_SERVER['REQUEST_URI'] ?? ''));

    if (empty($_GET['code'])) {
        delete_option('coai_google_refresh_token');
        wp_die('Google OAuth failed: missing authorization code.');
    }

    $cfg = coai_google_oauth_client_config();
    if (is_wp_error($cfg)) {
        wp_die('OAuth config error: ' . esc_html($cfg->get_error_message()));
    }

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'timeout' => 30,
        'body' => [
            'code'          => sanitize_text_field($_GET['code']),
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri'  => coai_google_oauth_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ],
    ]);

    if (is_wp_error($response)) {
        wp_die('OAuth request failed: ' . esc_html($response->get_error_message()));
    }

    $raw  = wp_remote_retrieve_body($response);
    $body = json_decode($raw, true);

    if (!empty($body['error'])) {
        delete_option('coai_google_refresh_token');
        wp_die('Google OAuth error: ' . esc_html($body['error_description'] ?? $body['error']));
    }

    if (empty($body['refresh_token'])) {
        delete_option('coai_google_refresh_token');
        wp_die('Google did not return a refresh token. Please revoke access and reconnect.');
    }

    update_option('coai_google_refresh_token', sanitize_text_field($body['refresh_token']));
    update_option('coai_google_connected_at', current_time('mysql'));

    wp_safe_redirect(home_url('/member-directory/?coai_google_connected=1'));
    exit;
});

/* ------------------------------------------------------------
 * Access Token
 * ------------------------------------------------------------ */

function coai_google_oauth_access_token() {
    $refresh_token = get_option('coai_google_refresh_token');

    if (!$refresh_token) {
        return new WP_Error('not_connected', 'Google Drive not connected.');
    }

    $cfg = coai_google_oauth_client_config();
    if (is_wp_error($cfg)) return $cfg;

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'timeout' => 30,
        'body' => [
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'refresh_token' => $refresh_token,
            'grant_type'    => 'refresh_token',
        ],
    ]);

    if (is_wp_error($response)) return $response;

    $raw  = wp_remote_retrieve_body($response);
    $body = json_decode($raw, true);

    if (!empty($body['error'])) {
        delete_option('coai_google_refresh_token');

        return new WP_Error(
            'token_failed',
            'Google token error: ' . ($body['error_description'] ?? $body['error'])
        );
    }

    if (empty($body['access_token'])) {
        return new WP_Error('token_failed', 'Google token refresh failed.');
    }

    return $body['access_token'];
}

/* ------------------------------------------------------------
 * Drive Helpers
 * ------------------------------------------------------------ */

function coai_google_drive_delete_existing_files($filename, $folder_id, $access_token) {
    $query = sprintf(
        "name='%s' and '%s' in parents and trashed=false",
        str_replace("'", "\\'", $filename),
        str_replace("'", "\\'", $folder_id)
    );

    $response = wp_remote_get(add_query_arg([
        'q'      => $query,
        'fields' => 'files(id,name)',
        'spaces' => 'drive',
    ], 'https://www.googleapis.com/drive/v3/files'), [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ],
    ]);

    if (is_wp_error($response)) return $response;

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code < 200 || $code >= 300) {
        return new WP_Error('drive_search_failed', 'Google Drive file search failed: ' . $raw);
    }

    foreach (($data['files'] ?? []) as $file) {
        if (empty($file['id'])) continue;

        $delete = wp_remote_request(
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode($file['id']),
            [
                'method'  => 'DELETE',
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
            ]
        );

        if (is_wp_error($delete)) return $delete;

        $delete_code = wp_remote_retrieve_response_code($delete);

        if ($delete_code < 200 || $delete_code >= 300) {
            return new WP_Error(
                'drive_delete_failed',
                'Google Drive delete failed: ' . wp_remote_retrieve_body($delete)
            );
        }
    }

    return true;
}

/* ------------------------------------------------------------
 * Upload CSV to Google Drive
 * ------------------------------------------------------------ */

function coai_google_drive_upload_csv($csv_content, $filename, $region, $member_count = 0) {
    $folder_id = coai_google_folder_id_for_region($region);

    if (!$folder_id) {
        return new WP_Error('missing_folder', 'No Google Drive folder is mapped for this COAI Region: ' . $region);
    }

    $access_token = coai_google_oauth_access_token();
    if (is_wp_error($access_token)) return $access_token;

    $delete_result = coai_google_drive_delete_existing_files($filename, $folder_id, $access_token);
    if (is_wp_error($delete_result)) return $delete_result;

    $boundary = 'coai_boundary_' . wp_generate_password(24, false, false);

    $metadata = [
        'name'     => $filename,
        'mimeType' => 'text/csv',
        'parents'  => [$folder_id],
    ];

    $body =
        "--{$boundary}\r\n" .
        "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
        wp_json_encode($metadata) . "\r\n" .
        "--{$boundary}\r\n" .
        "Content-Type: text/csv; charset=UTF-8\r\n\r\n" .
        $csv_content . "\r\n" .
        "--{$boundary}--\r\n";

    $response = wp_remote_post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'multipart/related; boundary=' . $boundary,
        ],
        'body' => $body,
    ]);

    if (is_wp_error($response)) return $response;

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code < 200 || $code >= 300 || empty($data['id'])) {
        return new WP_Error('drive_upload_failed', 'Google Drive upload failed: ' . $raw);
    }

    if (function_exists('coai_google_export_log')) {
        coai_google_export_log([
            'export_type'      => 'google_drive',
            'region'           => $region,
            'member_count'     => $member_count,
            'filename'         => $filename,
            'google_file_id'   => $data['id'],
            'google_folder_id' => $folder_id,
            'google_file_link' => $data['webViewLink'] ?? '',
            'status'           => 'success',
            'message'          => 'Uploaded successfully.',
        ]);
    }

    return [
        'file_id'   => $data['id'],
        'file_link' => $data['webViewLink'] ?? '',
        'folder_id' => $folder_id,
        'filename'  => $filename,
    ];
}

/* ------------------------------------------------------------
 * Legacy/SOF Wrapper Expected by Distribution Service
 * ------------------------------------------------------------ */

function coai_google_export_region(string $region): array {
    if (!function_exists('coai_members_export_csv_for_region')) {
        return [
            'success' => false,
            'region'  => $region,
            'message' => 'CSV export service is unavailable.',
            'errors'  => ['CSV export service is unavailable.'],
        ];
    }

    $csv_result = coai_members_export_csv_for_region($region);

    if (is_wp_error($csv_result)) {
        return [
            'success' => false,
            'region'  => $region,
            'message' => $csv_result->get_error_message(),
            'errors'  => [$csv_result->get_error_message()],
        ];
    }

    $csv_content  = $csv_result['csv'] ?? '';
    $member_count = (int) ($csv_result['count'] ?? 0);
    $filename     = $csv_result['filename'] ?? coai_google_export_filename_for_region($region);

    if ($csv_content === '') {
        return [
            'success' => false,
            'region'  => $region,
            'message' => 'CSV export returned no content.',
            'errors'  => ['CSV export returned no content.'],
        ];
    }

    $upload = coai_google_drive_upload_csv($csv_content, $filename, $region, $member_count);

    if (is_wp_error($upload)) {
        return [
            'success' => false,
            'region'  => $region,
            'count'   => $member_count,
            'filename'=> $filename,
            'message' => $upload->get_error_message(),
            'errors'  => [$upload->get_error_message()],
        ];
    }

    return [
        'success'          => true,
        'region'           => $region,
        'count'            => $member_count,
        'filename'         => $upload['filename'] ?? $filename,
        'upload'           => $upload,
        'google_file_id'   => $upload['file_id'] ?? '',
        'google_file_link' => $upload['file_link'] ?? '',
        'google_folder_id' => $upload['folder_id'] ?? '',
        'message'          => 'Uploaded successfully.',
        'errors'           => [],
    ];
}