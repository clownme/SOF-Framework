<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [coai_staff_broadcasts]
 * Page: /staff-broadcasts/
 * Admin/Manager only.
 */
add_action('init', function () {
  add_shortcode('coai_staff_broadcasts', 'coai_render_staff_broadcasts');
});

function coai_render_staff_broadcasts($atts = []) {

  if (!is_user_logged_in()) {
    $login = home_url('/mike2025/');
    $return_to = home_url('/staff-broadcasts/');
    $login_url = add_query_arg('redirect_to', rawurlencode($return_to), $login);

    return '<div style="max-width:720px;padding:16px;border:1px solid #e5e7eb;border-radius:12px;">
      <h3 style="margin:0 0 10px;">Staff Broadcasts</h3>
      <p style="margin:0 0 14px;">Please log in to continue.</p>
      <a class="button button-primary" href="'.esc_url($login_url).'">Log in</a>
    </div>';
  }

  // Permission gate (prefer your helper)
  if (function_exists('coai_staff_can')) {
    if (!coai_staff_can('manage')) {
      return '<div style="color:#b91c1c;">Access denied.</div>';
    }
  } else {
    // Fallback: admin or manager role
    $u = wp_get_current_user();
    $roles = array_map('strtolower', (array) ($u->roles ?? []));
    $is_manager = in_array('manager', $roles, true);
    $is_admin = current_user_can('manage_options');
    if (!$is_admin && !$is_manager) {
      return '<div style="color:#b91c1c;">Access denied.</div>';
    }
  }

  if (!defined('FLUENTCRM')) {
    return '<div style="color:#b91c1c;">FluentCRM is not active.</div>';
  }

  $url = admin_url('admin.php?page=fluentcrm-admin#/email/broadcasts');

  return '<div class="coai-staff-broadcasts">
    <h3>Staff Broadcasts</h3>
    <p><a class="button button-primary" href="' . esc_url($url) . '">Open Broadcasts</a></p>
  </div>';
}
