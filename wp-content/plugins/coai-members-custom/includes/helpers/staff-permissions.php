<?php
if (!defined('ABSPATH')) exit;

/**
 * Staff permission helper (AUTHORITATIVE)
 *
 * Caps supported:
 * - manage     => Manager (WP) OR WP admin
 * - finance    => Finance (WP) OR WP admin
 * - newsletter => Newsletter-Manager (WP) OR WP admin
 * - view       => Any staff-ish role (Manager/Finance/Newsletter-Manager) OR WP admin
 *
 * Phase 2 lock:
 * - Permissions are derived ONLY from WordPress roles/capabilities.
 * - No wp_members lookups (email/username/usergroup never grant staff access).
 */
if (!function_exists('coai_staff_can')) {
  function coai_staff_can($cap) {

    if (!is_user_logged_in()) return false;

    // WP Admins always allowed (capability, not role string)
    if (function_exists('current_user_can') && current_user_can('manage_options')) return true;

    $u = wp_get_current_user();
    $roles = array_map('strtolower', (array) ($u->roles ?? []));

    $is_manager = in_array('manager', $roles, true);
    $is_finance = in_array('finance', $roles, true);

    // Some installs use newsletter_manager, others newsletter-manager
    $is_newsletter = in_array('newsletter_manager', $roles, true) || in_array('newsletter-manager', $roles, true);

    $cap = strtolower(trim((string)$cap));

    if ($cap === 'manage') {
      return $is_manager;
    }

    if ($cap === 'finance') {
      return $is_finance;
    }

    if ($cap === 'newsletter') {
      return $is_newsletter;
    }

    if ($cap === 'view') {
      return $is_manager || $is_finance || $is_newsletter;
    }

    return false;
  }
}

