<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/google-config.php';
require_once __DIR__ . '/google-logger.php';

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

function coai_google_export_filename_for_region($region) {
  $region = trim((string)$region);

  $map = [
    'North East Region'    => 'Northeast.csv',
    'North Central Region' => 'Northcentral.csv',
    'North West Region'    => 'Northwest.csv',
    'Mid East Region'      => 'Mideast.csv',
    'Mid West Region'      => 'Midwest.csv',
    'South East Region'    => 'Southeast.csv',
    'South West Region'    => 'Southwest.csv',
    'South Central Region' => 'Southcentral.csv',
    'Canada Region'        => 'Canada.csv',
    'Latin Region'         => 'Latin.csv',
    'International Region' => 'International.csv',
  ];

  if (isset($map[$region])) {
    return $map[$region];
  }

  return sanitize_title($region) . '.csv';
}

function coai_google_oauth_authorize_url() {
  $cfg = coai_google_oauth_client_config();
  if (is_wp_error($cfg)) return '#';

  return add_query_arg([
    'client_id'     => $cfg['client_id'],
    'redirect_uri'  => coai_google_oauth_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'https://www.googleapis.com/auth/drive.file',
    'access_type'   => 'offline',
    'prompt'        => 'consent',
  ], 'https://accounts.google.com/o/oauth2/v2/auth');
}

add_action('template_redirect', function () {
  if (!is_page('member-directory')) return;
  if (empty($_GET['coai_google_auth'])) return;

  if (!is_user_logged_in() || !coai_staff_can('manage')) {
    wp_die('Unauthorized', '', 403);
  }

  wp_redirect(coai_google_oauth_authorize_url());
  exit;
});

add_action('template_redirect', function () {
  $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
  if ($path !== trim(parse_url(coai_google_oauth_redirect_uri(), PHP_URL_PATH), '/')) return;

  if (!is_user_logged_in() || !coai_staff_can('manage')) {
    wp_die('Unauthorized', '', 403);
  }

  if (!empty($_GET['error'])) {
    wp_die('Google authorization failed: ' . esc_html(sanitize_text_field($_GET['error'])));
  }

  if (empty($_GET['code'])) {
    wp_safe_redirect(home_url('/member-directory/?coai_google_missing_code=1'));
    exit;
  }

  $cfg = coai_google_oauth_client_config();
  if (is_wp_error($cfg)) wp_die($cfg->get_error_message());

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

  if (is_wp_error($response)) wp_die($response->get_error_message());

  $body = json_decode(wp_remote_retrieve_body($response), true);

  if (empty($body['refresh_token'])) {
    wp_die('Google did not return a refresh token. Reconnect using prompt=consent.');
  }

  update_option('coai_google_refresh_token', sanitize_text_field($body['refresh_token']));
  update_option('coai_google_connected_at', current_time('mysql'));

  wp_safe_redirect(home_url('/member-directory/?coai_google_connected=1'));
  exit;
});

function coai_google_oauth_access_token() {
  $refresh_token = get_option('coai_google_refresh_token');
  if (!$refresh_token) {
    return new WP_Error('not_connected', 'Google Drive is not connected yet.');
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

  $body = json_decode(wp_remote_retrieve_body($response), true);

  if (empty($body['access_token'])) {
    return new WP_Error('token_failed', 'Google token refresh failed: ' . wp_remote_retrieve_body($response));
  }

  return $body['access_token'];
}

function coai_google_drive_find_file_in_folder($filename, $folder_id, $access_token) {
  $query = sprintf(
    "name='%s' and '%s' in parents and trashed=false",
    str_replace("'", "\\'", $filename),
    str_replace("'", "\\'", $folder_id)
  );

  $response = wp_remote_get(add_query_arg([
    'q'      => $query,
    'fields' => 'files(id,name,webViewLink)',
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

  return $data['files'][0] ?? null;
}

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

function coai_google_drive_upload_csv($csv_content, $filename, $region, $member_count = 0) {
  $folder_id = coai_google_folder_id_for_region($region);
  if (!$folder_id) {
    return new WP_Error('missing_folder', 'No Google Drive folder is mapped for this COAI Region: ' . $region);
  }

  $token = coai_google_oauth_access_token();
  if (is_wp_error($token)) return $token;

  // Delete existing files with this same filename in the target folder.
  $delete_result = coai_google_drive_delete_existing_files($filename, $folder_id, $token);
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

  $response = wp_remote_post(
    'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink',
    [
      'timeout' => 60,
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'multipart/related; boundary=' . $boundary,
      ],
      'body' => $body,
    ]
  );

  if (is_wp_error($response)) return $response;

  $code = wp_remote_retrieve_response_code($response);
  $raw  = wp_remote_retrieve_body($response);
  $data = json_decode($raw, true);

  if ($code < 200 || $code >= 300 || empty($data['id'])) {
    coai_google_export_log([
      'export_type'      => 'google_drive',
      'region'           => $region,
      'member_count'     => $member_count,
      'filename'         => $filename,
      'google_folder_id' => $folder_id,
      'status'           => 'error',
      'message'          => $raw,
    ]);

    return new WP_Error('upload_failed', 'Google Drive upload failed: ' . $raw);
  }

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

  return [
    'file_id'   => $data['id'],
    'file_link' => $data['webViewLink'] ?? '',
    'folder_id' => $folder_id,
    'filename'  => $filename,
  ];
}