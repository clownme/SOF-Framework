<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin-only docs viewer (read-only)
 * Appears in WP-Admin → Tools → MYCOAI Docs
 */

add_action('admin_menu', function () {
  if (!current_user_can('manage_options')) return;

  add_submenu_page(
    'tools.php',
    'MYCOAI Documentation',
    'MYCOAI Docs',
    'manage_options',
    'coai-admin-docs',
    'coai_render_admin_docs_page'
  );
});

/**
 * Serve board-safe PDF via admin-post (avoids direct /wp-content/ access blocks)
 */
add_action('admin_post_coai_docs_pdf', function () {

  if (!current_user_can('manage_options')) {
    wp_die('Access denied.', 403);
  }

  if (empty($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'coai_docs_pdf')) {
    wp_die('Invalid request.', 403);
  }

  if (!defined('COAI_PLUGIN_DIR')) {
    wp_die('Plugin base path not available.', 500);
  }

  // ✅ Correct base path (plugin root)
  $pdf_path = trailingslashit(COAI_PLUGIN_DIR) . 'docs/MYCOAI_FILE_MAPPING.pdf';

  if (!file_exists($pdf_path)) {
    error_log('[COAI DOCS] PDF not found at: ' . $pdf_path);
    wp_die('PDF not found.', 404);
  }

  while (ob_get_level()) { ob_end_clean(); }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="MYCOAI_FILE_MAPPING.pdf"');
  header('Content-Length: ' . filesize($pdf_path));
  header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');

  readfile($pdf_path);
  exit;
});

function coai_render_admin_docs_page() {

  if (!current_user_can('manage_options')) {
    echo '<div class="notice notice-error"><p>Access denied.</p></div>';
    return;
  }

  if (!defined('COAI_PLUGIN_DIR')) {
    echo '<div class="notice notice-error"><p>COAI_PLUGIN_DIR not defined.</p></div>';
    return;
  }

  // ✅ Correct docs folder (plugin root)
  $docs_dir = trailingslashit(COAI_PLUGIN_DIR) . 'docs/';

  $tech_path = $docs_dir . 'mycoai-file-mapping-tech.md';
  $chg_path  = $docs_dir . 'CHANGELOG.md';

  $pdf_url = wp_nonce_url(
    admin_url('admin-post.php?action=coai_docs_pdf'),
    'coai_docs_pdf'
  );

  echo '<div class="wrap">';
  echo '<h1>MYCOAI Internal Documentation</h1>';

  echo '<p><a class="button button-primary" href="' . esc_url($pdf_url) . '" target="_blank" rel="noopener">📄 View Board-Safe PDF</a></p>';

  echo '<hr>';

  echo '<h2>Technical File Mapping</h2>';
  echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-height:600px;overflow:auto;white-space:pre-wrap;">';
  echo esc_html(file_exists($tech_path) ? file_get_contents($tech_path) : 'File not found: ' . basename($tech_path));
  echo '</pre>';

  echo '<h2>Change Log</h2>';
  echo '<pre style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-height:400px;overflow:auto;white-space:pre-wrap;">';
  echo esc_html(file_exists($chg_path) ? file_get_contents($chg_path) : 'File not found: ' . basename($chg_path));
  echo '</pre>';

  echo '</div>';
}
