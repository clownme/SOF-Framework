<?php
// /includes/dashboard/manual-add-member.php
if (!defined('ABSPATH')) exit;

function coai_render_manual_add_member_page() {
  // Admin/Manager only
  if (!function_exists('coai_staff_can') || !coai_staff_can('manage')) {
    echo '<div style="color:#b91c1c;">Access denied.</div>';
    return;
  }

  if (!function_exists('coai_calc_region_from_state')) {
    function coai_calc_region_from_state($st) {
      $st = strtoupper(trim((string)$st));

      $WEST = ['AK','AZ','CA','CO','HI','ID','MT','NM','NV','OR','UT','WA','WY'];
      $MIDWEST = ['IA','IL','IN','KS','MI','MN','MO','ND','NE','OH','SD','WI'];
      $SOUTH = ['AL','AR','DC','DE','FL','GA','KY','LA','MD','MS','NC','OK','SC','TN','TX','VA','WV'];
      $NORTHEAST = ['CT','MA','ME','NH','NJ','NY','PA','RI','VT'];

      if (in_array($st, $WEST, true)) return 'West';
      if (in_array($st, $MIDWEST, true)) return 'Midwest';
      if (in_array($st, $SOUTH, true)) return 'South';
      if (in_array($st, $NORTHEAST, true)) return 'Northeast';
      return '';
    }
  }

  global $wpdb;
  $table = coai_get_members_table();

  $msg = '';
  $err = '';

  // Defaults for form re-display
  $v = [
    'full_name' => '',
    'first_name' => '',
    'last_name' => '',
    'clown_name' => '',
    'email' => '',
    'address' => '',
    'address2' => '',
    'city' => '',
    'state' => '',
    'zip' => '',
    'country' => '',
    'region' => '',
    'mobile' => '',
    'registered_date' => '',
    'membership_level_id' => '',
    'paid_manually' => '1',
    'manual_payment_date' => '',
    'check_number' => '',
    'payment_amount' => '',
    'internal_comments' => '',
    'send_welcome_email' => '1', // default ON for new inserts
    'confirm_update' => '0',
  ];

  $is_lookup = !empty($_POST['coai_manual_member_lookup']);
  $is_submit = !empty($_POST['coai_manual_member_submit']);

  // ----- LOOKUP (fills form, no save) -----
  if ($is_lookup) {
    check_admin_referer('coai_manual_member_add', 'coai_nonce');

    $lookup_email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    if (empty($lookup_email) || !is_email($lookup_email)) {
      $err = 'Enter a valid Email, then click Lookup Member by Email.';
    } else {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `$table` WHERE LOWER(TRIM(email)) = LOWER(TRIM(%s)) LIMIT 1", $lookup_email),
        ARRAY_A
      );


      if (!$row) {
        $msg = 'No existing member found for that email. You can add a new record.';
        $v['email'] = $lookup_email;
        $v['confirm_update'] = '0';
        $v['send_welcome_email'] = '1';
      } else {
        // Populate known fields only
        foreach ($v as $k => $ignore) {
          if (array_key_exists($k, $row)) {
            $v[$k] = (string)$row[$k];
          }
        }

        $v['email'] = $lookup_email;
        $v['paid_manually'] = !empty($row['paid_manually']) ? '1' : '0';

        // If existing member, default welcome email OFF and require explicit confirm_update to change anything
        $v['send_welcome_email'] = '0';
        $v['confirm_update'] = '0';

        // If region exists in DB, keep it; otherwise derive from state for display
        if (empty($v['region'])) {
          $v['region'] = coai_calc_region_from_state($v['state']);
        }

        $msg = 'Existing member found — form populated. Review, check "Yes, update existing member" if you intend to update, then click Save.';
      }
    }
  }

  // ----- SAVE (insert/update) -----
  if ($is_submit) {
    check_admin_referer('coai_manual_member_add', 'coai_nonce');

    // Pull + sanitize
    $v['full_name'] = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
    $v['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
    $v['last_name']  = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
    $v['clown_name'] = sanitize_text_field(wp_unslash($_POST['clown_name'] ?? ''));
    $v['email']      = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $v['address']    = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));
    $v['address2']   = sanitize_text_field(wp_unslash($_POST['address2'] ?? ''));
    $v['city']       = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
    $v['state']      = sanitize_text_field(wp_unslash($_POST['state'] ?? ''));
    // Always compute region from state (authoritative)
    $v['region'] = coai_calc_region_from_state($v['state']);
    $v['zip']        = sanitize_text_field(wp_unslash($_POST['zip'] ?? ''));
    $v['country']    = sanitize_text_field(wp_unslash($_POST['country'] ?? ''));
    $v['mobile']     = sanitize_text_field(wp_unslash($_POST['mobile'] ?? ''));
    $v['registered_date']      = sanitize_text_field(wp_unslash($_POST['registered_date'] ?? ''));
    $v['membership_level_id']  = absint($_POST['membership_level_id'] ?? 0);
    $v['manual_payment_date']  = sanitize_text_field(wp_unslash($_POST['manual_payment_date'] ?? ''));
    $v['check_number']         = sanitize_text_field(wp_unslash($_POST['check_number'] ?? ''));
    $v['payment_amount']       = sanitize_text_field(wp_unslash($_POST['payment_amount'] ?? ''));
    $v['internal_comments']    = sanitize_textarea_field(wp_unslash($_POST['internal_comments'] ?? ''));
    $v['paid_manually']        = !empty($_POST['paid_manually']) ? '1' : '0';
    $v['send_welcome_email']   = !empty($_POST['send_welcome_email']) ? '1' : '0';
    $v['confirm_update']       = !empty($_POST['confirm_update']) ? '1' : '0';

    // Hard-set (per your requirements)
    $status = 'Active';
    $usergroup = 'Member';
    $payment_mode = 'Check';

    // Validate required fields
    if (empty($v['email']) || !is_email($v['email'])) {
      $err = 'A valid Email is required.';
    } elseif (empty($v['first_name']) || empty($v['last_name'])) {
      $err = 'First Name and Last Name are required.';
    } elseif (empty($v['registered_date'])) {
      $err = 'Registered Date is required.';
    } elseif (empty($v['membership_level_id'])) {
      $err = 'Membership Level is required.';
    }

    // Build full_name if missing
    if (empty($err) && empty($v['full_name'])) {
      $v['full_name'] = trim($v['first_name'] . ' ' . $v['last_name']);
    }

    // Parse registered_date
    $registered_mysql = null;
    $expiration_mysql = null;
    if (empty($err)) {
      try {
        $rd = new DateTime($v['registered_date']); // supports YYYY-MM-DD or datetime
      } catch (Exception $e) {
        $rd = null;
      }
      if (!$rd) {
        $err = 'Registered Date must be a valid date (recommended: YYYY-MM-DD).';
      } else {
        $registered_mysql = $rd->format('Y-m-d H:i:s');
        $exp = clone $rd;
        $exp->modify('+1 year');
        $expiration_mysql = $exp->format('Y-m-d H:i:s');
      }
    }

    // Parse manual_payment_date (optional)
    $manual_payment_mysql = null;
    if (empty($err) && !empty($v['manual_payment_date'])) {
      try {
        $mp = new DateTime($v['manual_payment_date']);
        $manual_payment_mysql = $mp->format('Y-m-d H:i:s');
      } catch (Exception $e) {
        $err = 'Manual Payment Date must be a valid date (recommended: YYYY-MM-DD).';
      }
    }

    // Normalize payment_amount
    $payment_amount = null;
    if (empty($err)) {
      $pa = trim((string)$v['payment_amount']);
      if ($pa !== '') {
        $pa = preg_replace('/[^0-9.]/', '', $pa);
        $payment_amount = ($pa === '' ? null : $pa);
      }
    }

    // Find existing member by email (member_id)
    $existing_id = null;
    if (empty($err)) {
      $existing_id = $wpdb->get_var(
        $wpdb->prepare("SELECT member_id FROM `$table` WHERE LOWER(TRIM(email)) = LOWER(TRIM(%s)) LIMIT 1", $v['email'])
      );

      // Require explicit confirmation to update an existing record
      if ($existing_id && $v['confirm_update'] !== '1') {
        $err = 'A member with this email already exists. Check "Yes, update existing member" and click Save again.';
      }
    }

    // Only write to DB if NO errors
    if (empty($err)) {
      // Build internal comments (append notes + audit line)
      $existing_comments = '';
      if ($existing_id) {
        $existing_comments = (string)$wpdb->get_var(
          $wpdb->prepare("SELECT internal_comments FROM `$table` WHERE member_id = %d LIMIT 1", (int)$existing_id)
        );
      }
      $existing_comments = trim((string)$existing_comments);

      $entered_by = wp_get_current_user();
      $note_auto = sprintf(
        "Manual check recorded by %s (user ID %d) on %s | Check#: %s | Amount: %s | Paid Manually: %s | Manual Payment Date: %s",
        ($entered_by->user_login ?? 'unknown'),
        (int)get_current_user_id(),
        current_time('Y-m-d H:i:s'),
        $v['check_number'],
        ($payment_amount ?? ''),
        ($v['paid_manually'] === '1' ? 'Yes' : 'No'),
        $v['manual_payment_date']
      );

      $manual_note = trim((string)$v['internal_comments']);
      $append_parts = [];
      if ($manual_note !== '') $append_parts[] = $manual_note;
      $append_parts[] = $note_auto;

      $append_block = trim(implode("\n", $append_parts));

      $final_comments = $existing_comments;
      if ($append_block !== '') {
        $final_comments = ($final_comments ? $final_comments . "\n\n" : '') . $append_block;
      }

      // Column detection
      $cols = array_map('strtolower', (array) $wpdb->get_col("DESC `$table`", 0));
      $has_password = in_array('password', $cols, true);
      $has_force_pw = in_array('force_password_change', $cols, true);

      // Enforce username = email
      $login_username = $v['email'];

      // Data to write
      $data = [
        'full_name' => $v['full_name'],
        'first_name' => $v['first_name'],
        'last_name' => $v['last_name'],
        'clown_name' => $v['clown_name'],
        'email' => $v['email'],
        'username' => $login_username, // email
        'address' => $v['address'],
        'address2' => $v['address2'],
        'city' => $v['city'],
        'state' => $v['state'],
        'region' => $v['region'],
        'zip' => $v['zip'],
        'country' => $v['country'],
        'mobile' => $v['mobile'],

        'registered_date' => $registered_mysql,
        'membership_expiration' => $expiration_mysql,
        'membership_level_id' => (int)$v['membership_level_id'],

        'status' => $status,
        'usergroup' => $usergroup,

        'paid_manually' => (int)$v['paid_manually'],
        'manual_payment_date' => $manual_payment_mysql, // may be null
        'check_number' => $v['check_number'],
        'payment_amount' => $payment_amount,            // may be null
        'payment_mode' => $payment_mode,

        'internal_comments' => $final_comments,
      ];

      // NOTE: keep formats aligned with $data order
      $format = [
        '%s', // full_name
        '%s', // first_name
        '%s', // last_name
        '%s', // clown_name
        '%s', // email
        '%s', // username
        '%s', // address
        '%s', // address2
        '%s', // city
        '%s', // state
        '%s', // region
        '%s', // zip
        '%s', // country
        '%s', // mobile
        '%s', // registered_date
        '%s', // membership_expiration
        '%d', // membership_level_id
        '%s', // status
        '%s', // usergroup
        '%d', // paid_manually
        '%s', // manual_payment_date
        '%s', // check_number
        '%s', // payment_amount
        '%s', // payment_mode
        '%s', // internal_comments
      ];

if ($existing_id) {

  // Never update COAI_number from this screen
  unset($data['COAI_number']);

  // --- AUDIT: capture before ---
  $before = [];
  if (function_exists('coai_audit_log_update')) {
    $before = (array) $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", (int) $existing_id),
      ARRAY_A
    );
  }

  $ok = $wpdb->update(
    $table,
    $data,
    ['member_id' => (int) $existing_id],
    $format,
    ['%d']
  );

  // --- AUDIT: capture after + log diff (ONLY if update succeeded) ---
  if ($ok !== false && function_exists('coai_audit_log_update')) {
    $after = (array) $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", (int) $existing_id),
      ARRAY_A
    );

    coai_audit_log_update(
      (int) $existing_id,
      $before,
      $after,
      'Manual Add Member (check) update by email match'
    );
  }

  if ($ok === false) {
    $err = 'Database update failed: ' . esc_html($wpdb->last_error);
  } else {
    $msg = 'Member updated successfully (matched by email).';
  }

} else {

  // ===== temp password for NEW members (BEFORE INSERT) =====
  $temp_password = wp_generate_password(12, true);

  if ($has_password) {
    $data['password'] = password_hash($temp_password, PASSWORD_DEFAULT);
    $format[] = '%s';
  }

  if ($has_force_pw) {
    $data['force_password_change'] = 1;
    $format[] = '%d';
  }
  
  // Helper: determine "new member" from COAI_number baseline (202601-001+)
  $coai_is_new_from_number = function($coai_num): int {
    $coai_num = trim((string)$coai_num);
    if ($coai_num === '') return 0;
    $norm = (int) str_replace('-', '', $coai_num); // 202601-001 -> 202601001
    return ($norm >= 202601001) ? 1 : 0;
  };
  
  // Default: until COAI_number is final, new-member flag should be 0
  if (!isset($data['is_new_member'])) {
    $data['is_new_member'] = 0;
    $format[] = '%d';
  }

  // INSERT new member
  $ok = $wpdb->insert($table, $data, $format);

  if (!$ok) {
    $err = 'Database insert failed: ' . esc_html($wpdb->last_error);
  } else {
    $new_member_id = (int) $wpdb->insert_id;

    // --- AUDIT: Log creation snapshot ---
    if ($new_member_id > 0 && function_exists('coai_audit_log')) {
      $snap = (array) $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", (int) $new_member_id),
        ARRAY_A
      );
      coai_audit_log((int) $new_member_id, 'create', null, 'Manual Add Member (check) created', $snap);
    }

    // ===== COAI NUMBER AUTO-ASSIGN (NEW INSERTS ONLY) =====
    $assigned_coai = '';

    if ($new_member_id > 0 && function_exists('coai_assign_coai_number_if_missing')) {
      $assigned_coai = (string) coai_assign_coai_number_if_missing($new_member_id);
      // If the helper returns false/null, normalize to empty string
      $assigned_coai = trim($assigned_coai);
    }

    // ===== FINALIZE is_new_member from FINAL COAI_number (NEW INSERTS ONLY) =====
    if ($new_member_id > 0 && $assigned_coai !== '') {

      $is_new = $coai_is_new_from_number($assigned_coai);

      $wpdb->update(
        $table,
        ['is_new_member' => (int) $is_new],
        ['member_id' => (int) $new_member_id],
        ['%d'],
        ['%d']
      );

      // Audit: COAI number assignment (if assigned)
      if (function_exists('coai_audit_log')) {
        coai_audit_log((int)$new_member_id, 'coai_number_assigned', [
          'COAI_number' => ['from' => '', 'to' => $assigned_coai]
        ], 'Auto-assigned COAI number on new insert');

        coai_audit_log((int)$new_member_id, 'is_new_member_set', [
          'is_new_member' => ['from' => '', 'to' => (string)(int)$is_new],
          'rule' => ['from' => '', 'to' => 'COAI_number >= 202601-001']
        ], 'Auto-set is_new_member from COAI_number baseline on new insert');
      }
    }

    $msg = $assigned_coai
      ? 'Member added successfully. COAI Number assigned: ' . esc_html($assigned_coai)
      : 'Member added successfully.';

    // Welcome email (new inserts only)
    if ($v['send_welcome_email'] === '1') {
      $portal_url = function_exists('coai_page') ? coai_page('member-portal') : home_url('/member-portal/');
      $reset_url  = function_exists('coai_page') ? coai_page('reset-password') : home_url('/reset-password/');

      $to = $v['email'];
      $subject = 'COAI Membership Created — Your Login Information Enclosed';

      $body =
        "Hi {$v['first_name']},\n\n" .
        "Welcome to COAI! Your account has been created.\n\n" .
        "Login URL:\n{$portal_url}\n\n" .
        "Username:\n{$login_username}\n\n" .
        "Temporary Password:\n{$temp_password}\n\n" .
        "Set/Reset Password:\n{$reset_url}\n\n";

      if (!empty($assigned_coai)) {
        $body .= "Your COAI Number:\n{$assigned_coai}\n\n";
      }

      $body .=
        "For security, please log in and change your password.\n\n" .
        "— COAI";

      $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Cc: coaioffice@mycoai.com',
        'Cc: srateach@gmail.com',
      ];

      wp_mail($to, $subject, $body, $headers);
    }
  }
}
    } // end if (empty($err))
} // end if ($is_submit)

  // ----- UI -----
  $portal_url = function_exists('coai_page') ? coai_page('member-portal') : home_url('/member-portal/');

  echo '<div style="max-width:980px;margin:0 auto;border:1px solid #e5e7eb;border-radius:14px;padding:18px;background:#fff;">';
  echo '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">';
  echo '<h2 style="margin:0;">Manual Add Member (Check)</h2>';
  echo '<a class="button" href="' . esc_url($portal_url) . '" style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;">← Return to Member Portal</a>';
  echo '</div>';

  echo '<p style="margin:10px 0 0;color:#6b7280;">Status is set to <b>Active</b>, Usergroup to <b>Member</b>, Payment Mode to <b>Check</b>. Membership Expiration is set to 1 year after Registered Date.</p>';

  if ($err) echo '<div style="margin-top:12px;padding:10px 12px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;color:#991b1b;">' . esc_html($err) . '</div>';
  if ($msg) echo '<div style="margin-top:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:10px;color:#166534;">' . wp_kses_post($msg) . '</div>';

  echo '<style>
    .coai-grid{margin-top:16px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    @media (max-width:820px){.coai-grid{grid-template-columns:1fr}}
    .coai-field label{display:block;font-weight:600;margin:0 0 6px;color:#374151}
    .coai-input{width:100%;padding:.55rem .65rem;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    .coai-input[readonly]{background:#f9fafb}
    .coai-help{font-size:12px;color:#6b7280;margin-top:6px}
    .coai-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
  </style>';

  echo '<script>
  (function(){
    function calcRegion(st){
      st = (st || "").toUpperCase().trim();
      const WEST = new Set(["AK","AZ","CA","CO","HI","ID","MT","NM","NV","OR","UT","WA","WY"]);
      const MIDWEST = new Set(["IA","IL","IN","KS","MI","MN","MO","ND","NE","OH","SD","WI"]);
      const SOUTH = new Set(["AL","AR","DC","DE","FL","GA","KY","LA","MD","MS","NC","OK","SC","TN","TX","VA","WV"]);
      const NORTHEAST = new Set(["CT","MA","ME","NH","NJ","NY","PA","RI","VT"]);

      if (WEST.has(st)) return "West";
      if (MIDWEST.has(st)) return "Midwest";
      if (SOUTH.has(st)) return "South";
      if (NORTHEAST.has(st)) return "Northeast";
      return "";
    }

    function bind(){
      const st = document.getElementById("state");
      const rg = document.getElementById("region");
      if (!st || !rg) return;

      const update = () => { rg.value = calcRegion(st.value); };
      st.addEventListener("input", update);
      st.addEventListener("change", update);
      update();
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", bind);
    } else {
      bind();
    }
  })();
  </script>';

  ?>
  <form method="post" style="margin-top:16px;" novalidate>
    <?php wp_nonce_field('coai_manual_member_add', 'coai_nonce'); ?>

    <div class="coai-grid">
      <div class="coai-field">
        <label for="first_name">First Name *</label>
        <input class="coai-input" name="first_name" id="first_name" value="<?php echo esc_attr($v['first_name']); ?>" required>
      </div>

      <div class="coai-field">
        <label for="last_name">Last Name *</label>
        <input class="coai-input" name="last_name" id="last_name" value="<?php echo esc_attr($v['last_name']); ?>" required>
      </div>

      <div class="coai-field">
        <label for="full_name">Full Name</label>
        <input class="coai-input" name="full_name" id="full_name" value="<?php echo esc_attr($v['full_name']); ?>">
        <div class="coai-help">If left blank, it will be auto-generated from First + Last.</div>
      </div>

      <div class="coai-field">
        <label for="clown_name">Clown Name</label>
        <input class="coai-input" name="clown_name" id="clown_name" value="<?php echo esc_attr($v['clown_name']); ?>">
      </div>

      <div class="coai-field">
        <label for="email">Email *</label>
        <input class="coai-input" name="email" id="email" value="<?php echo esc_attr($v['email']); ?>" required>
        <div class="coai-help">Tip: enter email then click “Lookup Member by Email” to prefill.</div>
      </div>

      <div class="coai-field" style="display:flex;align-items:flex-end;">
        <label style="display:flex;align-items:center;gap:10px;margin:0;">
          <input type="checkbox" name="confirm_update" value="1" <?php checked($v['confirm_update'], '1'); ?>>
          Yes, update existing member if email already exists
        </label>
      </div>

      <div class="coai-field">
        <label for="mobile">Phone (Mobile)</label>
        <input class="coai-input" name="mobile" id="mobile" value="<?php echo esc_attr($v['mobile']); ?>">
      </div>

      <div class="coai-field">
        <label for="address">Address</label>
        <input class="coai-input" name="address" id="address" value="<?php echo esc_attr($v['address']); ?>">
      </div>

      <div class="coai-field">
        <label for="address2">Address2</label>
        <input class="coai-input" name="address2" id="address2" value="<?php echo esc_attr($v['address2']); ?>">
      </div>

      <div class="coai-field">
        <label for="city">City</label>
        <input class="coai-input" name="city" id="city" value="<?php echo esc_attr($v['city']); ?>">
      </div>

      <div class="coai-field">
        <label for="state">State</label>
        <input class="coai-input" name="state" id="state" value="<?php echo esc_attr($v['state']); ?>" maxlength="2">
        <div class="coai-help">2-letter code recommended (e.g., VA).</div>
      </div>

      <div class="coai-field">
        <label for="region">Region</label>
        <input class="coai-input" name="region" id="region" value="<?php echo esc_attr($v['region']); ?>" readonly>
        <div class="coai-help">Auto-set from State.</div>
      </div>

      <div class="coai-field">
        <label for="zip">Zip</label>
        <input class="coai-input" name="zip" id="zip" value="<?php echo esc_attr($v['zip']); ?>">
      </div>

      <div class="coai-field">
        <label for="country">Country</label>
        <input class="coai-input" name="country" id="country" value="<?php echo esc_attr($v['country']); ?>">
      </div>

      <div class="coai-field">
        <label for="registered_date">Registered Date *</label>
        <input class="coai-input" type="date" name="registered_date" id="registered_date" value="<?php echo esc_attr($v['registered_date']); ?>" required>
        <div class="coai-help">Expiration is set automatically to 1 year after this date.</div>
      </div>
      
      <?php
      $membership_levels = $wpdb->get_results(
        "SELECT id, name FROM `wp_membership_levels` ORDER BY name ASC",
         ARRAY_A
      );
      ?>

      <div class="coai-field">
        <label for="membership_level_id">Membership Level *</label>
        <select class="coai-input" name="membership_level_id" id="membership_level_id" required>
          <option value="">Select Membership Level</option>
          <?php foreach ($membership_levels as $level): ?>
            <option value="<?php echo esc_attr($level['id']); ?>" <?php selected((int)$v['membership_level_id'], (int)$level['id']); ?>>
              <?php echo esc_html($level['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="coai-help">Choose the level the member paid for.</div>
      </div>

      <div class="coai-field" style="display:flex;align-items:flex-end;">
        <label style="display:flex;align-items:center;gap:10px;margin:0;">
          <input type="checkbox" name="paid_manually" value="1" <?php checked($v['paid_manually'], '1'); ?>>
          Paid by check/manual
        </label>
      </div>

      <div class="coai-field">
        <label for="manual_payment_date">Manual Payment Date</label>
        <input class="coai-input" type="date" name="manual_payment_date" id="manual_payment_date" value="<?php echo esc_attr($v['manual_payment_date']); ?>">
      </div>

      <div class="coai-field">
        <label for="check_number">Check #</label>
        <input class="coai-input" name="check_number" id="check_number" value="<?php echo esc_attr($v['check_number']); ?>">
      </div>

      <div class="coai-field">
        <label for="payment_amount">Payment Amount</label>
        <input class="coai-input" name="payment_amount" id="payment_amount" value="<?php echo esc_attr($v['payment_amount']); ?>" placeholder="e.g., 25.00">
      </div>

      <div class="coai-field">
        <label>Payment Mode</label>
        <input class="coai-input" value="Check" readonly>
      </div>

      <div class="coai-field" style="display:flex;align-items:flex-end;">
        <label style="display:flex;align-items:center;gap:10px;margin:0;">
          <input type="checkbox" name="send_welcome_email" value="1" <?php checked($v['send_welcome_email'], '1'); ?>>
          Send welcome email (new members only)
        </label>
      </div>

      <div class="coai-field" style="grid-column:1 / -1;">
        <label for="internal_comments">Internal Comments (Manual Payment Notes)</label>
        <textarea class="coai-input" name="internal_comments" id="internal_comments" rows="4"><?php echo esc_textarea($v['internal_comments']); ?></textarea>
        <div class="coai-help">Internal only. On Save we also auto-append a standardized “manual check recorded…” line.</div>
      </div>

      <div class="coai-field">
        <label>Status</label>
        <input class="coai-input" value="Active" readonly>
      </div>

      <div class="coai-field">
        <label>Usergroup</label>
        <input class="coai-input" value="Member" readonly>
      </div>
    </div>

    <div class="coai-actions">
      <button type="submit" class="button" name="coai_manual_member_lookup" value="1" formnovalidate>Lookup Member by Email</button>
      <button type="submit" class="button button-primary" name="coai_manual_member_submit" value="1">Save Member</button>
      <a class="button" href="<?php echo esc_url($portal_url); ?>">Return to Member Portal</a>
    </div>
  </form>
  <?php

  echo '</div>';
}
