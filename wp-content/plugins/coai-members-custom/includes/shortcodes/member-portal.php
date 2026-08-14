<?php
if (!defined('ABSPATH')) exit;

add_shortcode('coai_member_portal', function () {

  if (!is_user_logged_in()) {
    wp_safe_redirect(add_query_arg('login','required', home_url('/member-login/')));
    exit;
  }

  $u  = wp_get_current_user();
  $wp = (int) $u->ID;

  global $wpdb;
  $table = function_exists('coai_get_members_table') ? coai_get_members_table() : ($wpdb->prefix . 'members');

  // Resolve member row
  $member_id = (int) get_user_meta($wp, 'coai_member_id', true);
  $row = null;

  if ($table) {
    if ($member_id > 0) {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", $member_id),
        ARRAY_A
      );
    }

    if (!$row) {
      $row = $wpdb->get_row(
        $wpdb->prepare(
          "SELECT * FROM `$table` WHERE email=%s OR username=%s LIMIT 1",
          (string)$u->user_email,
          (string)$u->user_login
        ),
        ARRAY_A
      );

      if ($row && !empty($row['member_id'])) {
        update_user_meta($wp, 'coai_member_id', (int)$row['member_id']);
      }
    }
  }

  // ---- Safe display values ----
  $username_disp = trim((string)($row['username'] ?? ''));
  if ($username_disp === '') $username_disp = (string)$u->user_login;
  if ($username_disp === '') $username_disp = '—';

  $email_disp = trim((string)($row['email'] ?? ''));
  if ($email_disp === '') $email_disp = (string)$u->user_email;
  if ($email_disp === '') $email_disp = '—';

  // Friendly group label
  $raw_group = strtoupper((string)($row['usergroup'] ?? ''));
  $map = ['ADMIN'=>'Administrator','MANAGER'=>'Manager','FINANCE'=>'Finance','MEMBER'=>'Member'];
  $group_label = $map[$raw_group] ?? ($raw_group !== '' ? $raw_group : 'Member');

  // ---- Renewal logic ----
  $coai_renew_url = 'https://www.zeffy.com/en-US/ticketing/clowns-of-america-international-incs-renewal-membership';

  $expires_at = null;
  if (!empty($row['membership_expiration'])) {
    $ts = strtotime((string)$row['membership_expiration']);
    if ($ts && $ts > 0) $expires_at = $ts;
  }

  $expired = false;
  $expiring_soon = false;
  if ($expires_at) {
    $today = strtotime('today');
    $expired = ($expires_at < $today);
    $expiring_soon = (!$expired && ($expires_at <= strtotime('+60 days', $today)));
  }

  $status_txt = strtoupper((string)($row['status'] ?? ''));
  if ($status_txt === 'EXPIRED' || $status_txt === 'DECEASED') {
    $expired = true;
    $expiring_soon = false;
  }

  $show_renew = ($expired || $expiring_soon);

  // ===== STRICT staff check for the Staff Tools card =====
  $roles = array_map('strtolower', (array) $u->roles);
  $is_newsletter_manager = in_array('newsletter_manager', $roles, true);

  error_log('PORTAL DEBUG: user=' . $u->user_login . ' roles=' . json_encode($roles));

  $mid = (int) get_user_meta($wp, 'coai_member_id', true);
  $usergroup = '';
  if ($mid) {
    $t = defined('COAI_MEMBERS_TABLE') ? COAI_MEMBERS_TABLE : ($wpdb->prefix . 'members');
    $usergroup = strtoupper((string) $wpdb->get_var($wpdb->prepare(
      "SELECT usergroup FROM `$t` WHERE member_id=%d",
      $mid
    )));
  }

  $is_admin_manager =
    in_array('administrator', $roles, true) ||
    in_array('manager', $roles, true) ||
    in_array($usergroup, ['ADMIN','MANAGER'], true);

  $is_finance =
    in_array('finance', $roles, true) ||
    ($usergroup === 'FINANCE');
    
  // -----------------------------
  // SOF Access capability
  // -----------------------------
  $can_manage_access =
      function_exists('sof_current_user_can') &&
      sof_current_user_can('manage_access');

  $is_staff =
      $is_admin_manager ||
      $is_finance ||
      $is_newsletter_manager ||
      $can_manage_access;

  // -----------------------------
  // Regional Vice President access
  // -----------------------------
  
  $rvp_region = ($member_id > 0 && function_exists('coai_get_active_rvp_region_for_member'))
      ? coai_get_active_rvp_region_for_member($member_id)
      : '';

  $is_rvp = ($rvp_region !== '')
      && function_exists('coai_user_can')
      && coai_user_can('view_region_members', $usergroup);

  $is_member_only = (
    !$is_admin_manager &&
    !$is_finance &&
    !$is_rvp
);

  // Optional debug
  error_log(sprintf(
    'PORTAL StaffTools: roles=%s usergroup=%s -> admin_mgr=%s finance=%s member_only=%s',
    json_encode($roles),
    $usergroup,
    $is_admin_manager ? 'yes' : 'no',
    $is_finance ? 'yes' : 'no',
    $is_member_only ? 'yes' : 'no'
  ));

  // -----------------------------
  // Insurance fields (display)
  // -----------------------------
  $ins_status = trim((string)($row['insurance_status'] ?? ''));
  $ins_eff    = trim((string)($row['insurance_effective_date'] ?? ''));
  $ins_exp    = trim((string)($row['insurance_expiration_date'] ?? ''));

  // Normalize/format dates for display
  $format_date = function ($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return '—';
    $ts = strtotime($raw);
    if (!$ts || $ts <= 0) return esc_html($raw); // show raw if it won't parse
    return date_i18n('M j, Y', $ts);
  };

  $ins_status_disp = ($ins_status !== '') ? $ins_status : '—';
  $ins_eff_disp    = $format_date($ins_eff);
  $ins_exp_disp    = $format_date($ins_exp);

  $has_any_ins = ($ins_status !== '' || $ins_eff !== '' || $ins_exp !== '');

  // -----------------------------
  // Recent Communication Delivery
  // -----------------------------

  $recent_communication = null;

  $communication_progress = [
      'total' => 0,
      'pending' => 0,
      'processing' => 0,
      'sent' => 0,
      'failed' => 0,
  ];

  $communication_remaining = 0;

  if (
      class_exists('SOF_CommunicationRepository') &&
      class_exists('SOF_CommunicationDeliveryQueueRepository') &&
      class_exists('SOF_CommunicationDeliveryQueueService')
  ) {

      $communication_repository =
          new SOF_CommunicationRepository();

      $recent_communication =
          $communication_repository
              ->find_latest_delivery_for_creator(
                  $wp
              );

      if ($recent_communication) {

          $queue_repository =
              new SOF_CommunicationDeliveryQueueRepository();

          $queue_service =
              new SOF_CommunicationDeliveryQueueService(
                  $queue_repository
              );

          $communication_progress =
              $queue_service->progress(
                  (int) $recent_communication->get_id()
              );

          $communication_remaining =
              (int) $communication_progress['pending'] +
              (int) $communication_progress['processing'];
      }
  }
  
  // -----------------------------
  // Renewal Management Review
  // -----------------------------

  $renewal_review = null;

  if (
      $is_admin_manager
      && class_exists('SOF_ZeffyRenewalReviewService')
  ) {
      $renewal_review_service =
          new SOF_ZeffyRenewalReviewService();

      $renewal_review =
          $renewal_review_service->review(500);
  }

  // ✅ Start buffering BEFORE any HTML output
  ob_start();
  ?>
  <div class="coai-portal-card" style="margin:0 0 18px;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;width:100%;max-width:none;">
    <h2 style="margin:0 0 .75rem;">Member Portal</h2>

    <?php if ($expired): ?>
      <div style="margin:0 0 1rem;padding:.75rem;border:1px solid #f59e0b;background:#fffbeb;border-radius:10px;">
        <strong>Your membership has expired.</strong>
        <a href="<?php echo esc_url($coai_renew_url); ?>" target="_blank" rel="noopener"
           style="margin-left:.5rem;display:inline-block;padding:.4rem .7rem;border:1px solid #d97706;border-radius:8px;background:#fbbf24;color:#111;text-decoration:none;">
          Renew membership
        </a>
      </div>
    <?php elseif ($expiring_soon): ?>
      <div style="margin:0 0 1rem;padding:.75rem;border:1px solid #93c5fd;background:#eff6ff;border-radius:10px;">
        <strong>Your membership expires soon.</strong>
        <?php if ($expires_at): ?>
          <span style="margin-left:.35rem;">Expires on <?php echo esc_html(date_i18n('M j, Y', $expires_at)); ?>.</span>
        <?php endif; ?>
        <a href="<?php echo esc_url($coai_renew_url); ?>" target="_blank" rel="noopener"
           style="margin-left:.5rem;display:inline-block;padding:.4rem .7rem;border:1px solid #2563eb;border-radius:8px;background:#dbeafe;color:#111;text-decoration:none;">
          Renew now
        </a>
      </div>
    <?php endif; ?>

    <p><strong>Username:</strong> <?php echo esc_html($username_disp); ?></p>
    <p><strong>Email:</strong> <?php echo esc_html($email_disp); ?></p>
    <p><strong>Group:</strong> <?php echo esc_html($group_label); ?></p>

    <div class="coai-portal-actions" style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:.5rem;">
      <a class="button" href="<?php echo esc_url(home_url('/profile/')); ?>" style="display:inline-block;padding:.5rem .75rem;border-radius:8px;border:1px solid #d1d5db;text-decoration:none;">View Profile</a>
      <a class="button" href="<?php echo esc_url(home_url('/member-reset-password-2/')); ?>" style="display:inline-block;padding:.5rem .75rem;border-radius:8px;border:1px solid #d1d5db;text-decoration:none;">Reset Password</a>
      <a class="button" href="<?php echo esc_url(home_url('/change-password/')); ?>" style="display:inline-block;padding:.5rem .75rem;border-radius:8px;border:1px solid #d1d5db;text-decoration:none;">Change Password</a>

      <a class="button" href="<?php echo esc_url($coai_renew_url); ?>" target="_blank" rel="noopener"
         style="display:inline-block;padding:.5rem .75rem;border-radius:8px;border:1px solid #d1d5db;text-decoration:none;background:#fbbf24;font-weight:600;">
        RENEW MEMBERSHIP
      </a>

      <a class="button" href="<?php echo esc_url(wp_logout_url(home_url('/member-login/'))); ?>"
         style="display:inline-block;padding:.5rem .75rem;border-radius:8px;border:1px solid #d1d5db;text-decoration:none;">
        Log Out
      </a>
    </div>
  </div>
  
    <?php if ($recent_communication): ?>

    <?php
      $communication_status =
          $recent_communication->get_status();

      $communication_id =
          (int) $recent_communication->get_id();

      $communication_subject =
          trim(
              (string) $recent_communication->get_subject()
          );

      $communication_complete =
          $communication_status === 'sent';

      $delivery_results_url =
          add_query_arg(
              'communication_id',
              $communication_id,
              home_url('/confirm-communication/')
          );
    ?>

    <div
      class="coai-portal-card coai-communication-status-card"
      style="margin:1rem 0;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;"
    >

      <h3 style="margin:0 0 .5rem;">
        <?php if ($communication_complete): ?>
          Communication Delivery Complete
        <?php else: ?>
          Communication Delivery in Progress
        <?php endif; ?>
      </h3>

      <?php if ($communication_subject !== ''): ?>

        <p style="margin:.25rem 0 .75rem;">
          <strong>Subject:</strong>
          <?php echo esc_html($communication_subject); ?>
        </p>

      <?php endif; ?>

      <?php if ($communication_complete): ?>

        <p style="margin:0 0 .75rem;color:#374151;">
          Your Communication has finished delivery.
        </p>

        <div style="display:grid;grid-template-columns:1fr;gap:.35rem;margin-bottom:1rem;">

          <div>
            <strong>Attempted:</strong>
            <?php
              echo esc_html(
                  number_format_i18n(
                      (int) $communication_progress['total']
                  )
              );
            ?>
          </div>

          <div>
            <strong>Delivered:</strong>
            <?php
              echo esc_html(
                  number_format_i18n(
                      (int) $communication_progress['sent']
                  )
              );
            ?>
          </div>

          <div>
            <strong>Failed:</strong>
            <?php
              echo esc_html(
                  number_format_i18n(
                      (int) $communication_progress['failed']
                  )
              );
            ?>
          </div>

        </div>

        <form
          method="get"
          action="<?php
            echo esc_url(
                home_url('/confirm-communication/')
            );
          ?>"
          style="display:inline;"
        >

          <input
            type="hidden"
            name="communication_id"
            value="<?php
              echo esc_attr(
                  (string) $communication_id
              );
            ?>"
          >

        <button
            type="submit"
            style="
                display:inline-block;
                padding:12px 20px;
                margin:8px 0 0;
                background:#1f365c;
                color:#ffffff;
                border:1px solid #1f365c;
                border-radius:8px;
                font-size:16px;
                font-weight:600;
                line-height:1.2;
                text-decoration:none;
                cursor:pointer;
                appearance:none;
                -webkit-appearance:none;
            "
        >
            View Delivery Results
        </button>

        </form>

      <?php else: ?>

        <p style="margin:0 0 .75rem;color:#374151;">
          SOF is continuing delivery in the background.
          You may continue using the Member Portal.
        </p>

        <div style="display:grid;grid-template-columns:1fr;gap:.35rem;">

          <div>
            <strong>Queued Recipients:</strong>
            <?php
              echo esc_html(
                  number_format_i18n(
                      (int) $communication_progress['total']
                  )
              );
            ?>
          </div>

          <div>
            <strong>Delivered:</strong>
            <?php
              echo esc_html(
                  number_format_i18n(
                      (int) $communication_progress['sent']
                  )
              );
            ?>
          </div>

          <div>
            <strong>Failed:</strong>
            <?php
              echo esc_html(
                  number_format_i18n(
                      (int) $communication_progress['failed']
                  )
              );
            ?>
          </div>

          <div>
            <strong>Remaining:</strong>
            <?php
              echo esc_html(
                  number_format_i18n(
                      $communication_remaining
                  )
              );
            ?>
          </div>

        </div>

        <form
          method="get"
          action="<?php
            echo esc_url(
                home_url('/member-portal/')
            );
          ?>"
          style="display:inline;"
        >

          <button
            type="submit"
            style="
                display:inline-block;
                padding:12px 20px;
                margin:12px 0 0;
                background:#1f365c;
                color:#ffffff;
                border:1px solid #1f365c;
                border-radius:8px;
                font-size:16px;
                font-weight:600;
                line-height:1.2;
                text-decoration:none;
                cursor:pointer;
                appearance:none;
                -webkit-appearance:none;
            "
          >
            Refresh Delivery Status
          </button>

        </form>

      <?php endif; ?>

    </div>

  <?php endif; ?>
  
  <?php
  if (
      $is_admin_manager
      && is_array($renewal_review)
      && (int)($renewal_review['attention_total'] ?? 0) > 0
  ) :
  ?>

    <?php
    $renewal_attention =
        (int)($renewal_review['attention_total'] ?? 0);

    $renewal_possible_applied =
        (int)(
            $renewal_review['possible_previously_applied']
            ?? 0
        );

    $renewal_management =
        (int)(
            $renewal_review['management_review']
            ?? 0
        );
    ?>

    <div
      class="coai-portal-card coai-renewal-review-card"
      style="
        margin:1rem 0;
        padding:1.25rem;
        border:1px solid #e5e7eb;
        border-radius:12px;
        background:#fff;
      "
    >

      <h3 style="margin:0 0 .5rem;">
        Renewal Management Review
      </h3>

      <p style="margin:0 0 .75rem;color:#374151;">
        <?php
        echo esc_html(
            number_format_i18n(
                $renewal_attention
            )
        );
        ?>
        Renewal transaction(s) require management attention
        before membership action should occur.
      </p>

      <div
        style="
          display:grid;
          grid-template-columns:1fr;
          gap:.35rem;
          margin-bottom:1rem;
        "
      >

        <div>
          <strong>Possible Previously Applied:</strong>
          <?php
          echo esc_html(
              number_format_i18n(
                  $renewal_possible_applied
              )
          );
          ?>
        </div>

        <div>
          <strong>Management Review:</strong>
          <?php
          echo esc_html(
              number_format_i18n(
                  $renewal_management
              )
          );
          ?>
        </div>

      </div>

      <a
        class="button"
        href="<?php
          echo esc_url(
              home_url('/renewal-management-review/')
          );
        ?>"
        style="
          display:inline-block;
          padding:.5rem .75rem;
          border:1px solid #d1d5db;
          border-radius:8px;
          text-decoration:none;
        "
      >
        Review Renewals
      </a>

    </div>

  <?php endif; ?>

  <!-- Insurance card (shown if any insurance data exists; flip to always-show if you prefer) -->
  <?php if ($has_any_ins): ?>
    <div class="coai-portal-card" style="max-width:720px;margin:1rem auto;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
      <h3 style="margin:0 0 .5rem;">Insurance</h3>

      <div style="display:grid;grid-template-columns:1fr;gap:.35rem;">
        <div><strong>Status:</strong> <?php echo esc_html($ins_status_disp); ?></div>
        <div><strong>Policy Effective Date:</strong> <?php echo esc_html($ins_eff_disp); ?></div>
        <div><strong>Policy Expiration Date:</strong> <?php echo esc_html($ins_exp_disp); ?></div>
      </div>

      <p style="margin:.75rem 0 0;color:#6b7280;font-size:.92em;">
        If anything looks wrong, contact the COAI office.
      </p>
    </div>
  <?php endif; ?>

  <?php if ($is_newsletter_manager): ?>
    <div class="coai-portal-card" style="max-width:720px;margin:1rem auto;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
      <h3 style="margin:0 0 .5rem;">Newsletter Tools</h3>
      <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a class="button" href="<?php echo esc_url(home_url('/staff-newsletters/')); ?>"
           style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
          Newsletter &amp; Announcements
        </a>
      </div>
    </div>
  <?php endif; ?>
  
<?php if ($is_rvp): ?>
    <div class="coai-portal-card coai-portal-region-tools"
         style="margin:1rem 0;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">

        <h3 style="margin:0 0 .5rem;">
            Regional Vice President Tools
        </h3>

        <p style="margin:0 0 .75rem;color:#6b7280;">
            Manage and communicate with members of the
            <strong><?php echo esc_html($rvp_region); ?></strong>.
        </p>

        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">

            <a class="button"
               href="<?php echo esc_url(home_url('/regional-member-directory/')); ?>"
               style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
                View Regional Members
            </a>

            <a class="button"
               href="<?php echo esc_url(home_url('/compose-communication/')); ?>"
               style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
                Compose Communication
            </a>

        </div>
    </div>
<?php endif; ?>

  <?php if ($is_staff): ?>
   <div class="coai-portal-card coai-portal-staff-tools" style="margin:1rem 0;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
      <h3 style="margin:0 0 .5rem;">Staff Tools</h3>
      <div style="display:flex;flex-wrap:wrap;gap:.5rem;">

        <?php if ($is_admin_manager): ?>
          <a class="button" href="<?php echo esc_url(home_url('/member-directory/')); ?>"
             style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
            View Members
          </a>
          
          <a class="button" href="<?php echo esc_url(home_url('/staff-archived-search/')); ?>"
              style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
              Archived Search
          </a>

          <a class="button" href="<?php echo esc_url(add_query_arg(
              array_filter([
                'coai_export' => 1,
                '_coai_nonce' => wp_create_nonce('coai_export'),
              ]),
              home_url('/member-directory/')
            )); ?>"
             style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
            Export CSV
          </a>

          <a class="button" href="<?php echo esc_url(home_url('/manual-add-member/')); ?>"
             style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
            Manual Add Member
          </a>

          <a class="button" href="<?php echo esc_url(home_url('/compose-communication/')); ?>"
             style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
            Compose Communications
          </a>

          <a class="button" href="<?php echo esc_url(home_url('/newsletters/')); ?>"
             style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
              Compose Newsletters
          </a>

          <a class="button" href="<?php echo esc_url(home_url('/access/')); ?>"
             style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
              Manage Access
          </a>

          <?php endif; ?>

        <?php if ($is_finance): ?>
          <a class="button" href="<?php echo esc_url(home_url('/finance-portal/')); ?>"
             style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">
            View Members (Finance)
          </a>
        <?php endif; ?>

      </div>
    </div>
  <?php endif; ?>

  <?php
  return ob_get_clean();
});
