<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/google-config.php';
require_once __DIR__ . '/google-logger.php';

function coai_google_drive_upload_csv($csv_content, $filename, $region, $member_count = 0) {
  $folder_id = coai_google_folder_id_for_region($region);

  if (!$folder_id) {
    return new WP_Error('missing_folder', 'No Google Drive folder is mapped for this COAI Region.');
  }

  if (!file_exists(COAI_GOOGLE_DRIVE_KEY_PATH)) {
    return new WP_Error('missing_key', 'Google service account JSON key file not found.');
  }

  if (!class_exists('Google_Client')) {
    return new WP_Error('missing_library', 'Google API PHP Client is not installed.');
  }

  try {
    $client = new Google_Client();
    $client->setAuthConfig(COAI_GOOGLE_DRIVE_KEY_PATH);
    $client->addScope(Google_Service_Drive::DRIVE_FILE);

    $service = new Google_Service_Drive($client);

    $file_meta = new Google_Service_Drive_DriveFile([
      'name'    => $filename,
      'parents' => [$folder_id],
    ]);

    $file = $service->files->create($file_meta, [
      'data'       => $csv_content,
      'mimeType'   => 'text/csv',
      'uploadType' => 'multipart',
      'fields'     => 'id, webViewLink',
    ]);

    $file_id = $file->id;
    $file_link = $file->webViewLink;

    coai_google_export_log([
      'export_type'      => 'google_drive',
      'region'           => $region,
      'member_count'     => $member_count,
      'filename'         => $filename,
      'google_file_id'   => $file_id,
      'google_folder_id' => $folder_id,
      'google_file_link' => $file_link,
      'status'           => 'success',
      'message'          => 'Uploaded successfully.',
    ]);

    return [
      'file_id'   => $file_id,
      'file_link' => $file_link,
      'folder_id' => $folder_id,
      'filename'  => $filename,
    ];

  } catch (Exception $e) {
    coai_google_export_log([
      'export_type'      => 'google_drive',
      'region'           => $region,
      'member_count'     => $member_count,
      'filename'         => $filename,
      'google_folder_id' => $folder_id,
      'status'           => 'error',
      'message'          => $e->getMessage(),
    ]);

    return new WP_Error('google_upload_failed', $e->getMessage());
  }
}