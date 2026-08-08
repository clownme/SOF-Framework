<?php
if (!defined('ABSPATH')) exit;

function coai_google_export_log($data) {
  global $wpdb;

  $table = $wpdb->prefix . 'coai_export_history';

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $charset = $wpdb->get_charset_collate();

  dbDelta("CREATE TABLE {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exported_by BIGINT UNSIGNED NULL,
    exported_by_login VARCHAR(191) NULL,
    export_date DATETIME NOT NULL,
    export_type VARCHAR(50) NOT NULL,
    region VARCHAR(191) NULL,
    member_count INT DEFAULT 0,
    filename VARCHAR(255) NULL,
    google_file_id VARCHAR(255) NULL,
    google_folder_id VARCHAR(255) NULL,
    google_file_link TEXT NULL,
    status VARCHAR(50) NOT NULL,
    message TEXT NULL,
    PRIMARY KEY (id)
  ) {$charset};");

  $user = wp_get_current_user();

  $wpdb->insert($table, [
    'exported_by'       => $user ? (int)$user->ID : null,
    'exported_by_login' => $user ? $user->user_login : '',
    'export_date'       => current_time('mysql'),
    'export_type'       => sanitize_text_field($data['export_type'] ?? 'google_drive'),
    'region'            => sanitize_text_field($data['region'] ?? ''),
    'member_count'      => (int)($data['member_count'] ?? 0),
    'filename'          => sanitize_text_field($data['filename'] ?? ''),
    'google_file_id'    => sanitize_text_field($data['google_file_id'] ?? ''),
    'google_folder_id'  => sanitize_text_field($data['google_folder_id'] ?? ''),
    'google_file_link'  => esc_url_raw($data['google_file_link'] ?? ''),
    'status'            => sanitize_text_field($data['status'] ?? 'unknown'),
    'message'           => sanitize_textarea_field($data['message'] ?? ''),
  ]);
}

function coai_google_export_history_rows($limit = 10) {
  global $wpdb;

  $table = $wpdb->prefix . 'coai_export_history';
  $limit = max(1, min(50, (int)$limit));

  return $wpdb->get_results(
    $wpdb->prepare(
      "SELECT *
       FROM {$table}
       ORDER BY export_date DESC, id DESC
       LIMIT %d",
      $limit
    ),
    ARRAY_A
  );
}