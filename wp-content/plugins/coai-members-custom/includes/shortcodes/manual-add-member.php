<?php
// /includes/shortcodes/manual-add-member.php
if (!defined('ABSPATH')) exit;

// Load the real implementation (single source of truth)
require_once plugin_dir_path(__FILE__) . '../dashboard/manual-add-member.php';

// Register shortcode that renders the dashboard UI on the front-end
add_shortcode('coai_manual_add_member', function () {
  if (!function_exists('coai_render_manual_add_member_page')) {
    return '<div style="color:#b91c1c;">Manual Add Member screen is unavailable.</div>';
  }

  ob_start();
  coai_render_manual_add_member_page();
  return ob_get_clean();
});
