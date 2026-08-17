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

  // -----------------------------
  // Membership Renewal Situation
  // -----------------------------
$coai_renew_url =
    home_url(
        '/renew-membership/'
    );

  $renewal_situation = [
      'situation' => 'unavailable',
      'expiration_date' => '',
      'expiration_timestamp' => null,
      'days_until_expiration' => null,
      'may_renew' => false,
      'message' => '',
  ];

  if (
      $row &&
      function_exists('coai_member_service')
  ) {
      $renewal_situation =
          coai_member_service()
              ->get_renewal_situation(
                  $row,
                  60
              );
  }

  $renewal_state =
      (string)($renewal_situation['situation'] ?? 'unavailable');

  $membership_expiration_display =
      (string)($renewal_situation['expiration_date'] ?? '');

  $show_renew =
      !empty($renewal_situation['may_renew']);

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

  // ✅ Start buffering BEFORE any HTML output
  ob_start();
  ?>

<style>

    /* =========================================================
       Member Portal Page Width
       PRODUCTION page ID: 3248
       ========================================================= */

    body.page-id-3248 #cwp-main,
    body.page-id-3248 #cwp-main-wrap,
    body.page-id-3248 .cwp-menu-wrapper.cwp-right-push {
        width: 100% !important;
        max-width: none !important;
    }

    body.page-id-3248 #cwp-main {
        box-sizing: border-box;
    }

    body.page-id-3248 #cwp-main > .wp-block-group {
        width: 100% !important;
        max-width: none !important;
        box-sizing: border-box;
    }


    /* =========================================================
       Member Portal Workspace
       ========================================================= */

    .coai-member-portal-workspace {
        width: 100% !important;
        max-width: 1200px !important;
        margin: 0 auto !important;
        box-sizing: border-box;
    }

    .coai-member-portal-workspace > .coai-portal-card {
        display: block;
        width: 100% !important;
        max-width: none !important;
        margin: 0 0 18px !important;
        padding: 1.25rem !important;
        box-sizing: border-box;
        border: 1px solid #e5e7eb !important;
        border-radius: 12px !important;
        background: #ffffff !important;
    }

    .coai-member-portal-workspace > .coai-portal-card:last-child {
        margin-bottom: 0 !important;
    }


    /* =========================================================
       Personal Member Portal Cards
       ========================================================= */

    .coai-portal-membership {
        margin-bottom: 20px !important;
        border-left-width: 4px !important;
        border-left-style: solid !important;
    }

    .coai-portal-membership.coai-membership-state-current {
        border-left-color: #22c55e !important;
    }

    .coai-portal-membership.coai-membership-state-renewal_window {
        border-left-color: #3b82f6 !important;
    }

    .coai-portal-membership.coai-membership-state-expired {
        border-left-color: #f59e0b !important;
    }

    .coai-portal-membership.coai-membership-state-unavailable {
        border-left-color: #94a3b8 !important;
    }

    .coai-portal-account,
    .coai-portal-insurance {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 1.5rem !important;
        box-sizing: border-box !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 14px !important;
        background: #ffffff !important;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .08) !important;
    }

    .coai-portal-personal-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        column-gap: 20px !important;
        row-gap: 20px !important;
        align-items: stretch !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 0 20px !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }

    .coai-portal-personal-grid > .coai-portal-card {
        display: block !important;
        grid-column: auto !important;
        grid-row: auto !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        height: 100% !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }

    /* If Insurance is not present, My Account uses the full row. */
    .coai-portal-personal-grid > .coai-portal-card:only-child {
        grid-column: 1 / -1 !important;
    }


    /* =========================================================
       Organization Capabilities
       ========================================================= */

    .coai-portal-capability-section {
        margin-top: 18px;
    }

    .coai-portal-capability-header {
        width: 100% !important;
        margin: 0 0 20px;
        padding: 1rem 1.5rem;
        box-sizing: border-box;
        border: 1px solid #cbd5e1 !important;
        border-left: 4px solid #ff4f9a !important;
        border-radius: 12px !important;
        background: #f8fafc !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .05);
    }

    .coai-portal-capability-heading {
        margin: 0 0 .5rem !important;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
    }

    .coai-portal-capability-intro {
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
        color: #6b7280;
    }

    .coai-portal-capability-grid {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 20px;
        align-items: stretch;
        width: 100%;
        margin-top: 0;
    }

    .coai-portal-capability-grid > .coai-portal-card {
        grid-column: auto !important;
        grid-row: auto !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 1.5rem !important;
        box-sizing: border-box;
        border: 1px solid #cbd5e1 !important;
        border-radius: 14px !important;
        background: #ffffff !important;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .08) !important;
    }

    .coai-portal-capability-card {
        display: block !important;
        min-height: 0 !important;
    }

    .coai-portal-capability-card h3 {
        margin: 0 0 .65rem;
        line-height: 1.2;
    }

    .coai-portal-capability-card p {
        margin: 0 0 1.25rem;
        color: #6b7280;
        line-height: 1.55;
    }

    .coai-portal-capability-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: .5rem;
        margin-top: 1rem;
    }

    .coai-portal-capability-actions .button {
        display: inline-block !important;
        width: auto !important;
        padding: .5rem .75rem !important;
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        box-shadow: none !important;
        color: #ff4f9a !important;
        text-decoration: none !important;
        font-weight: 400 !important;
        line-height: 1.3;
    }

    .coai-portal-capability-actions .button:hover,
    .coai-portal-capability-actions .button:focus {
        background: #f9fafb !important;
        border-color: #9ca3af !important;
        color: #d9367f !important;
        text-decoration: none !important;
    }

    .coai-portal-capability-actions .button:focus {
        outline: 2px solid #d9367f;
        outline-offset: 2px;
    }


    /* =========================================================
       Responsive Presentation
       ========================================================= */

    @media (max-width: 760px) {
        .coai-portal-personal-grid {
            grid-template-columns: 1fr !important;
        }
    }

    @media (max-width: 640px) {
        .coai-portal-capability-grid {
            grid-template-columns: 1fr !important;
        }
    }

</style>

<div class="coai-member-portal-workspace">
    <!-- =====================================================
         Membership
         ===================================================== -->
    <div
        class="
            coai-portal-card
            coai-portal-membership
            coai-membership-state-<?php
                echo esc_attr(
                    sanitize_html_class(
                        $renewal_state
                    )
                );
            ?>
        "
        style="
            margin:0 0 18px;
            padding:1.25rem;
            border:1px solid #e5e7eb;
            border-radius:12px;
            background:#fff;
            width:100%;
            max-width:none;
        "
    >

        <h2 style="margin:0 0 .5rem;">
            Member Portal
        </h2>

        <p style="margin:0 0 1rem;color:#6b7280;">
            View your membership information and available services.
        </p>

        <?php if ($renewal_state === 'current'): ?>

            <div
                style="
                    padding:1rem;
                    border:0;
                    background:#f0fdf4;
                    border-radius:10px;
                "
            >

                <strong>
                    Your membership is current.
                </strong>

                <?php if ($membership_expiration_display !== ''): ?>

                    <div style="margin-top:.35rem;">
                        Membership Expiration Date:
                        <strong>
                            <?php
                                echo esc_html(
                                    $membership_expiration_display
                                );
                            ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <div style="margin-top:.35rem;color:#374151;">
                    There is no need to renew at this time.
                </div>

            </div>

        <?php elseif ($renewal_state === 'renewal_window'): ?>

            <div
                style="
                    padding:1rem;
                    border:0;
                    background:#eff6ff;
                    border-radius:10px;
                "
            >

                <strong>
                    Your membership is approaching expiration.
                </strong>

                <?php if ($membership_expiration_display !== ''): ?>

                    <div style="margin-top:.35rem;">
                        Membership Expiration Date:
                        <strong>
                            <?php
                                echo esc_html(
                                    $membership_expiration_display
                                );
                            ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <p style="margin:.5rem 0 0;color:#374151;">
                    You may renew your membership now.
                </p>

                <div style="margin-top:.75rem;">

                    <a
                        href="<?php echo esc_url($coai_renew_url); ?>"
                        target="_blank"
                        rel="noopener"
                        style="
                            display:inline-block;
                            padding:.5rem .8rem;
                            border:1px solid #2563eb;
                            border-radius:8px;
                            background:#dbeafe;
                            color:#111;
                            text-decoration:none;
                            font-weight:600;
                        "
                    >
                        Renew Membership
                    </a>

                </div>

            </div>

        <?php elseif ($renewal_state === 'expired'): ?>

            <div
                style="
                    padding:1rem;
                    border:0;
                    background:#fffbeb;
                    border-radius:10px;
                "
            >

                <strong>
                    Your membership has expired.
                </strong>

                <?php if ($membership_expiration_display !== ''): ?>

                    <div style="margin-top:.35rem;">
                        Membership Expiration Date:
                        <strong>
                            <?php
                                echo esc_html(
                                    $membership_expiration_display
                                );
                            ?>
                        </strong>
                    </div>

                <?php endif; ?>

                <p style="margin:.5rem 0 0;color:#374151;">
                    Renew your membership to restore your current membership status.
                </p>

                <div style="margin-top:.75rem;">

                    <a
                        href="<?php echo esc_url($coai_renew_url); ?>"
                        target="_blank"
                        rel="noopener"
                        style="
                            display:inline-block;
                            padding:.5rem .8rem;
                            border:1px solid #d97706;
                            border-radius:8px;
                            background:#fbbf24;
                            color:#111;
                            text-decoration:none;
                            font-weight:600;
                        "
                    >
                        Renew Membership
                    </a>

                </div>

            </div>

        <?php elseif (
            $renewal_state === 'unavailable' &&
            !empty($renewal_situation['message'])
        ): ?>

            <div
                style="
                    padding:1rem;
                    border:0;
                    background:#f9fafb;
                    border-radius:10px;
                "
            >
                <?php
                    echo esc_html(
                        $renewal_situation['message']
                    );
                ?>
            </div>

        <?php endif; ?>

    </div>


    <div class="coai-portal-personal-grid">

        <!-- =====================================================
             My Account
             ===================================================== -->
        <div
            class="coai-portal-card coai-portal-account"
            style="
                margin:0 0 18px;
                padding:1.25rem;
                border:1px solid #e5e7eb;
                border-radius:12px;
                background:#fff;
                width:100%;
                max-width:none;
            "
        >

            <h3 style="margin:0 0 .5rem;">
                My Account
            </h3>

            <p style="margin:0 0 .75rem;color:#6b7280;">
                View or update your personal information and manage your account.
            </p>

            <p style="margin:0 0 1rem;">
                <strong>Email:</strong>
                <?php echo esc_html($email_disp); ?>
            </p>

            <div
                class="coai-portal-actions"
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:.5rem;
                "
            >

                <a
                    class="button"
                    href="<?php
                        echo esc_url(
                            home_url('/profile/')
                        );
                    ?>"
                    style="
                        display:inline-block;
                        padding:.5rem .75rem;
                        border-radius:8px;
                        border:1px solid #d1d5db;
                        text-decoration:none;
                    "
                >
                    View My Profile
                </a>

                <a
                    class="button"
                    href="<?php
                        echo esc_url(
                            home_url('/change-password/')
                        );
                    ?>"
                    style="
                        display:inline-block;
                        padding:.5rem .75rem;
                        border-radius:8px;
                        border:1px solid #d1d5db;
                        text-decoration:none;
                    "
                >
                    Change Password
                </a>

                <a
                    class="button"
                    href="<?php
                        echo esc_url(
                            wp_logout_url(
                                home_url('/member-login/')
                            )
                        );
                    ?>"
                    style="
                        display:inline-block;
                        padding:.5rem .75rem;
                        border-radius:8px;
                        border:1px solid #d1d5db;
                        text-decoration:none;
                    "
                >
                    Log Out
                </a>

            </div>

        </div>

        <!-- =====================================================
             Insurance
             ===================================================== -->
        <?php if ($has_any_ins): ?>

            <div
                class="coai-portal-card coai-portal-insurance"
                style="
                    max-width:720px;
                    margin:1rem auto;
                    padding:1.25rem;
                    border:1px solid #e5e7eb;
                    border-radius:12px;
                    background:#fff;
                "
            >

                <h3 style="margin:0 0 .5rem;">
                    Insurance
                </h3>

                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr;
                        gap:.35rem;
                    "
                >

                    <div>
                        <strong>Status:</strong>
                        <?php echo esc_html($ins_status_disp); ?>
                    </div>

                    <div>
                        <strong>Policy Effective Date:</strong>
                        <?php echo esc_html($ins_eff_disp); ?>
                    </div>

                    <div>
                        <strong>Policy Expiration Date:</strong>
                        <?php echo esc_html($ins_exp_disp); ?>
                    </div>

                </div>

                <p
                    style="
                        margin:.75rem 0 0;
                        color:#6b7280;
                        font-size:.92em;
                    "
                >
                    If anything looks wrong, contact the COAI office.
                </p>

            </div>

        <?php endif; ?>

    </div>
    <!-- /.coai-portal-personal-grid -->
  
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
    $show_organization_capabilities =
        $is_admin_manager ||
        $is_finance ||
        $is_rvp ||
        $is_newsletter_manager ||
        $can_manage_access;
    ?>

    <?php if ($show_organization_capabilities): ?>

        <section class="coai-portal-capability-section">

            <div class="coai-portal-capability-header">

                <h2 class="coai-portal-capability-heading">
                    Organization Capabilities
                </h2>

                <p class="coai-portal-capability-intro">
                    Tools available based on your responsibilities
                    within the organization.
                </p>

            </div>

            <div class="coai-portal-capability-grid">


                <?php if ($is_admin_manager): ?>

                    <!-- =========================================
                         Membership Management
                         ========================================= -->
                    <div
                        class="
                            coai-portal-card
                            coai-portal-capability-card
                        "
                    >

                        <h3>
                            Membership Management
                        </h3>

                        <p>
                            View and manage organization membership
                            information.
                        </p>

                        <div class="coai-portal-capability-actions">

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        home_url(
                                            '/member-directory/'
                                        )
                                    );
                                ?>"
                            >
                                View Members
                            </a>

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        home_url(
                                            '/staff-archived-search/'
                                        )
                                    );
                                ?>"
                            >
                                Archived Members
                            </a>

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        home_url(
                                            '/manual-add-member/'
                                        )
                                    );
                                ?>"
                            >
                                Add Member
                            </a>

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        add_query_arg(
                                            [
                                                'coai_export' => 1,
                                                '_coai_nonce' =>
                                                    wp_create_nonce(
                                                        'coai_export'
                                                    ),
                                            ],
                                            home_url(
                                                '/member-directory/'
                                            )
                                        )
                                    );
                                ?>"
                            >
                                Export Member List
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if ($is_admin_manager || $is_rvp): ?>

                    <!-- =========================================
                         Communications
                         ========================================= -->
                    <div
                        class="
                            coai-portal-card
                            coai-portal-capability-card
                        "
                    >

                        <h3>
                            Communications
                        </h3>

                        <p>
                            Prepare and send communications to the
                            members you are authorized to reach.
                        </p>

                        <div class="coai-portal-capability-actions">

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        home_url(
                                            '/compose-communication/'
                                        )
                                    );
                                ?>"
                            >
                                Compose Communication
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if (
                    $is_admin_manager ||
                    $is_newsletter_manager
                ): ?>

                    <!-- =========================================
                         Newsletters
                         ========================================= -->
                    <div
                        class="
                            coai-portal-card
                            coai-portal-capability-card
                        "
                    >

                        <h3>
                            Newsletters
                        </h3>

                        <p>
                            Create newsletters and organization
                            announcements.
                        </p>

                        <div class="coai-portal-capability-actions">

                            <?php if ($is_admin_manager): ?>

                                <a
                                    class="button"
                                    href="<?php
                                        echo esc_url(
                                            home_url(
                                                '/newsletters/'
                                            )
                                        );
                                    ?>"
                                >
                                    Compose Newsletter
                                </a>

                            <?php else: ?>

                                <a
                                    class="button"
                                    href="<?php
                                        echo esc_url(
                                            home_url(
                                                '/staff-newsletters/'
                                            )
                                        );
                                    ?>"
                                >
                                    Newsletter &amp; Announcements
                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if ($can_manage_access || $is_admin_manager): ?>

                    <!-- =========================================
                         Access Management
                         ========================================= -->
                    <div
                        class="
                            coai-portal-card
                            coai-portal-capability-card
                        "
                    >

                        <h3>
                            Access Management
                        </h3>

                        <p>
                            Control who may use organizational
                            capabilities and responsibilities.
                        </p>

                        <div class="coai-portal-capability-actions">

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        home_url(
                                            '/access/'
                                        )
                                    );
                                ?>"
                            >
                                Manage Access
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if ($is_rvp): ?>

                    <!-- =========================================
                         Regional Leadership
                         ========================================= -->
                    <div
                        class="
                            coai-portal-card
                            coai-portal-capability-card
                        "
                    >

                        <h3>
                            Regional Leadership
                        </h3>

                        <p>
                            View and work with members of the
                            <strong>
                                <?php echo esc_html($rvp_region); ?>
                            </strong>.
                        </p>

                        <div class="coai-portal-capability-actions">

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        home_url(
                                            '/regional-member-directory/'
                                        )
                                    );
                                ?>"
                            >
                                View Regional Members
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <?php if ($is_finance): ?>

                    <!-- =========================================
                         Finance
                         ========================================= -->
                    <div
                        class="
                            coai-portal-card
                            coai-portal-capability-card
                        "
                    >

                        <h3>
                            Finance
                        </h3>

                        <p>
                            View membership information available
                            for financial responsibilities.
                        </p>

                        <div class="coai-portal-capability-actions">

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        home_url(
                                            '/finance-portal/'
                                        )
                                    );
                                ?>"
                            >
                                Open Finance Portal
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


            </div>

        </section>

    <?php endif; ?>

</div>
<!-- /.coai-member-portal-workspace -->

  <?php
  return ob_get_clean();
});
