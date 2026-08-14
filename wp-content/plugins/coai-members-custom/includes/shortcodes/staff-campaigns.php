<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [coai_staff_campaigns]
 * Page: /staff-campaigns/
 * Admin/Manager only.
 */
add_action('init', function () {
  add_shortcode('coai_staff_campaigns', 'coai_render_staff_campaigns');
});

function coai_render_staff_campaigns($atts = []) {

  if (!is_user_logged_in()) {
    return '<div class="coai-error">Please log in.</div>';
  }

  // Prefer your permission system if available
  if (function_exists('coai_staff_can')) {
    if (!coai_staff_can('manage')) {
      return '<div class="coai-error">Access denied.</div>';
    }
  } else {
    // Fallback role check if helper not loaded for some reason
    $u = wp_get_current_user();
    $roles = array_map('strtolower', (array) ($u->roles ?? []));
    if (!in_array('manager', $roles, true) && !current_user_can('manage_options')) {
      return '<div class="coai-error">Access denied.</div>';
    }
  }

  // If you’re embedding/linking FluentCRM campaigns:
  // Use a link/button and let FluentCRM manage authorization.
  $url = admin_url('admin.php?page=fluentcrm-admin#/email/campaigns');

  return '<div class="coai-staff-campaigns">
    <h3>Newsletter Campaigns</h3>
    <p><a class="button button-primary" href="' . esc_url($url) . '">Open Campaigns</a></p>
  </div>';
}
