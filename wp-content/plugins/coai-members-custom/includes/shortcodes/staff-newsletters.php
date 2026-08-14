<?php
/**
 * Staff Newsletter Center (front-end)
 * Shortcode: [coai_staff_newsletters]
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('coai_staff_newsletters_shortcode')) {
  function coai_staff_newsletters_shortcode() {

    $news_url   = home_url('/staff-newsletters/');
    $portal_url = home_url('/member-portal/');
    $login_url  = home_url('/member-login/');

    // If not logged in, send them to your front-end login with return here.
    if (!is_user_logged_in()) {
      $login_with_return = add_query_arg('redirect_to', rawurlencode($news_url), $login_url);

      return '<div class="coai-card" style="max-width:900px;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
        <h3 style="margin:0 0 10px;">📰 Newsletter Center</h3>
        <p style="margin:0 0 14px;">Please log in to access staff newsletter tools.</p>
        <a class="button button-primary" href="' . esc_url($login_with_return) . '">Log in</a>
      </div>';
    }

    // FluentCRM active?
    if (!defined('FLUENTCRM')) {
      return '<div class="coai-card" style="max-width:900px;border:1px solid #fee2e2;border-radius:12px;padding:16px;">
        <h3 style="margin:0 0 10px;color:#991b1b;">FluentCRM not active</h3>
        <p style="margin:0;">FluentCRM is not active. Please contact an administrator.</p>
      </div>';
    }

    $u     = wp_get_current_user();
    $roles = array_map('strtolower', (array)($u->roles ?? []));
    
    // ----- Authoritative identity detection (for UI messaging) -----
    // NOTE: This is UI-only. Access rules still use $is_newsletter_user / $is_admin / $is_manager below.

    $is_admin = in_array('administrator', $roles, true);

    // Manager detection (role OR wp_members usergroup fallback)
    $is_manager = in_array('manager', $roles, true);
    if (!$is_manager && function_exists('coai_current_usergroup')) {
      $g = strtoupper((string) coai_current_usergroup());
      if ($g === 'MANAGER') $is_manager = true;
      if ($g === 'ADMIN')   $is_admin = true;
    }

    // Strict role checks (no “admin implies newsletter” here)
    $is_newsletter_role = in_array('newsletter_manager', $roles, true);

    // Newsletter access user (kiosk/2FA behavior expects this helper)
    if (function_exists('coai_is_newsletter_user')) {
      $is_newsletter_user = (bool) coai_is_newsletter_user(); // admins may be true here, by design
    } else {
      $is_newsletter_user = $is_newsletter_role;
    }

    // Primary identity for messaging (precedence)
    $primary_identity = 'staff';
    if ($is_admin) {
      $primary_identity = 'admin';
    } elseif ($is_manager) {
      $primary_identity = 'manager';
    } elseif ($is_newsletter_role) {
      $primary_identity = 'newsletter_manager';
    }
    
    if ($is_manager && $is_newsletter_role) {
      error_log('NEWSLETTER-CENTER WARNING: user has BOTH manager + newsletter_manager roles: ' . $u->user_login);
    }

    // Optional: log the computed identity (helps confirm “sometimes” cases)
    error_log('NEWSLETTER-CENTER IDENTITY: user=' . $u->user_login .
      ' roles=' . json_encode($roles) .
      ' identity=' . $primary_identity
    );

    // Hard deny if not admin, not manager, not newsletter user
    if (!$is_admin && !$is_manager && !$is_newsletter_user) {
      return '<div class="coai-card" style="max-width:900px;border:1px solid #fee2e2;border-radius:12px;padding:16px;">
        <h3 style="margin:0 0 10px;color:#991b1b;">Access denied</h3>
        <p style="margin:0;">You do not have permission to access the newsletter tools.</p>
      </div>';
    }

    // Managers: require re-auth (2FA) before accessing newsletters
    if ($primary_identity === 'manager') {

      $campaigns_url  = admin_url('admin.php?page=fluentcrm-admin&coai_route=campaigns&coai_intent=newsletters');
      $broadcasts_url = admin_url('admin.php?page=fluentcrm-admin&coai_route=broadcasts&coai_intent=newsletters');

      // Force WP re-auth (triggers 2FA if enabled)
      $campaigns_gateway  = $campaigns_url;
      $broadcasts_gateway = $broadcasts_url;

      return '<div class="coai-card" style="max-width:900px;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
        <h3 style="margin:0 0 10px;">📰 Newsletter Center</h3>

        <p style="margin:0 0 12px;color:#6b7280;">
          ✅ You’re signed in as <strong>Manager</strong>.
        </p>

        <p style="margin:0 0 14px;">
          To access newsletters and announcements, please verify your login (2FA required).
        </p>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <a class="button button-primary" href="' . esc_url($campaigns_gateway) . '">
            Open Campaigns
          </a>
          <a class="button" href="' . esc_url($broadcasts_gateway) . '">
            Open Broadcasts
          </a>
          <a class="button" href="' . esc_url($portal_url) . '">
            Back to Portal
          </a>
        </div>
      </div>';
    }

    // Newsletter user OR Admin: show tools UI + safe FluentCRM routes
    $campaigns_url  = admin_url('admin.php?page=fluentcrm-admin&coai_route=campaigns');
    $broadcasts_url = admin_url('admin.php?page=fluentcrm-admin&coai_route=broadcasts');

    // For Managers: force a fresh wp-login flow before entering FluentCRM
    $campaigns_gateway  = wp_login_url($campaigns_url) . '&reauth=1';
    $broadcasts_gateway = wp_login_url($broadcasts_url) . '&reauth=1';

    ob_start(); ?>

    <div class="coai-card" style="max-width:900px;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
      <h3 style="margin-top:0;">📰 Newsletter Center</h3>
      <p>Use this area to create newsletters and announcements.</p>

      <?php if ($primary_identity === 'newsletter_manager'): ?>
        <p style="margin:0 0 12px;color:#065f46;">
          ✅ You’re signed in as <strong>Newsletter Manager</strong>.
        </p>
      <?php elseif ($primary_identity === 'admin'): ?>
        <p style="margin:0 0 12px;color:#1d4ed8;">
          ✅ You’re signed in as <strong>Administrator</strong>.
        </p>
      <?php elseif ($primary_identity === 'manager'): ?>
        <p style="margin:0 0 12px;color:#6b7280;">
          ✅ You’re signed in as <strong>Manager</strong>.
        </p>
      <?php endif; ?>


      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:14px;">
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;">
          <h4 style="margin:0 0 8px;">Create Newsletter</h4>
          <p style="margin:0 0 12px;color:#374151;">Build, schedule, and send a campaign.</p>
          <a class="button button-primary" href="<?php echo esc_url($campaigns_url); ?>">Open Campaigns</a>
        </div>

        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;">
          <h4 style="margin:0 0 8px;">Quick Announcement</h4>
          <p style="margin:0 0 12px;color:#374151;">Send a one-time announcement/broadcast.</p>
          <a class="button" href="<?php echo esc_url($broadcasts_url); ?>">Open Broadcasts</a>
        </div>
      </div>

      <hr style="margin:18px 0;border:none;border-top:1px solid #e5e7eb;">

      <p style="color:#6b7280;margin:0;">
        Tip: If you only want a test email first, create the campaign/broadcast and send to a small test segment/tag before sending to everyone.
      </p>
    </div>

    <?php
    return ob_get_clean();
  }
}

// Register shortcode on init (consistent + safe)
add_action('init', function () {
  add_shortcode('coai_staff_newsletters', 'coai_staff_newsletters_shortcode');
}, 9);
