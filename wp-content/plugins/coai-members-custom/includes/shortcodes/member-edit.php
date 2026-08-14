<?php
if (!defined('ABSPATH')) exit;

//  Never cache the member edit edit_page
if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
if (!defined('LSCACHE_NO_CACHE')) define('LSCACHE_NO_CACHE', true);
if (function_exists('do_action')) {
    // LiteSpeed hint
    do_action('litespeed_control_set_nocache');
}
if (!headers_sent()) {
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-LiteSpeed-Cache-Control: no-cache');
}

if (!function_exists('coai_member_edit_can_manage')) {
  function coai_member_edit_can_manage() {
    if (function_exists('coai_staff_can') && coai_staff_can('manage')) return true;
    return function_exists('current_user_can') && (current_user_can('manage_options') || current_user_can('edit_users'));
  }
}

if (!function_exists('coai_member_edit_audit')) {
  function coai_member_edit_audit($event, array $payload = []) {
    // Prefer your standardized audit helper if you have one
    if (function_exists('coai_audit_log')) {
      coai_audit_log($event, $payload);
      return;
    }
    // Fallback: error_log (still searchable + timestamped)
    error_log('[COAI][AUDIT] ' . $event . ' ' . wp_json_encode($payload));
  }
}

if (!function_exists('coai_family_members_table_name')) {
  function coai_family_members_table_name() {
    return 'wp_member_family_members';
  }
}

if (!function_exists('coai_get_family_members_for_member')) {
  function coai_get_family_members_for_member($member_id) {
    global $wpdb;

    $member_id = (int) $member_id;
    if ($member_id <= 0) return [];

    $table = coai_family_members_table_name();

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT *
         FROM `$table`
         WHERE primary_member_id = %d
         ORDER BY last_name ASC, first_name ASC, id ASC",
        $member_id
      ),
      ARRAY_A
    );
  }
}

// Ensure the GOLD directory/editor helpers are loaded for member-edit page
if (!function_exists('coai_md_render_edit_view')) {
  $maybe = plugin_dir_path(__FILE__) . 'admin-members.php'; // same folder: includes/shortcodes/
  if (file_exists($maybe)) {
    require_once $maybe;
    error_log('[COAI] member-edit.php required admin-members.php');
  } else {
    error_log('[COAI] member-edit.php could not find admin-members.php at ' . $maybe);
  }
}



// Detect which COAI column exists in wp_members (fallback only)
// NOTE: the "real" version should come from admin-members.php
if (!function_exists('coai_get_coai_column_name')) {
  function coai_get_coai_column_name($table) {
    global $wpdb;
    $cols = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
    $cols = array_map('strval', (array)$cols);

    if (in_array('COAI_number', $cols, true)) return 'COAI_number';
    if (in_array('coai_number', $cols, true)) return 'coai_number';
    return 'COAI_number';
  }
}

add_shortcode('coai_member_edit_form', 'coai_render_member_edit_form');

function coai_render_member_edit_form($atts = [], $content = null) {

    // Resolve member id from ?mid= or [coai_member_edit_form mid="..."]
    $mid = isset($_GET['mid']) ? (int) $_GET['mid'] : 0;
    if (!$mid && isset($_GET['member_id'])) {
        $mid = (int) $_GET['member_id'];
    }
    if (!$mid && is_array($atts) && !empty($atts['mid'])) {
        $mid = (int) $atts['mid'];
    }

    // --- Treat these WP roles as "staff" ---
    $user  = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
    $roles = $user ? (array) ($user->roles ?? []) : [];
    $roles_lc = array_map('strtolower', $roles);

    $is_admin_role   = in_array('administrator', $roles_lc, true);
    $is_manager_role = in_array('manager', $roles_lc, true);
    $is_finance_role = in_array('finance', $roles_lc, true);
    $current_user_member_id = $user ? (int) get_user_meta((int)$user->ID, 'coai_member_id', true) : 0;

    // Capability fallback (keeps admins/managers "staff" even if roles differ)
    $is_manage_caps = (function_exists('current_user_can') && (current_user_can('manage_options') || current_user_can('edit_users') || current_user_can('edit_pages')));

    // --- Admin/Manager: render FULL staff editor on member-edit page ---
    if ($is_admin_role || $is_manager_role || $is_manage_caps) {

        if ($mid <= 0) {
          return '<div class="notice notice-warning">Missing member id. Use /member-edit/?mid=####</div>';
        }

        // Prefer the GOLD editor if available
        if (function_exists('coai_md_render_edit_view') && function_exists('coai_get_members_table')) {
          $table = coai_get_members_table();
          return coai_md_render_edit_view($table, $mid, home_url('/member-portal/'));
        }

        // Fallback (should rarely happen)
        return '<div class="notice notice-error">Edit system not loaded (coai_md_render_edit_view missing).</div>';
    }

    // --- Finance: use member-edit page but limit fields later ---
    if ($is_finance_role) {
        if ($mid > 0 && empty($_GET['mid'])) {
            $_GET['mid'] = $mid;
        }
        $atts['mode'] = 'finance'; // tells the form renderer to show finance-only fields
        // DO NOT return here
    }

    $mode = '';
    if (!empty($atts['mode'])) {
        $mode = sanitize_key($atts['mode']);
    }
    $is_finance_mode = ($mode === 'finance');

    // --- MEMBERS + FINANCE lightweight view ---
    global $wpdb;
    $table = function_exists('coai_get_members_table') ? coai_get_members_table() : (defined('COAI_MEMBERS_TABLE') ? COAI_MEMBERS_TABLE : ($wpdb->prefix . 'members'));

    // Regular members should default to their own member record when no ?mid= is present.
    if ($mid <= 0 && $current_user_member_id > 0 && !$is_admin_role && !$is_manager_role && !$is_manage_caps) {
        $mid = $current_user_member_id;
    }

    // Prevent regular members from editing/viewing another member record.
    if (!$is_admin_role && !$is_manager_role && !$is_manage_caps && !$is_finance_role) {
        if ($current_user_member_id <= 0 || (int)$mid !== (int)$current_user_member_id) {
            return '<div style="color:#b91c1c;">Access denied.</div>';
        }
    }
    
    $row = null;
    if ($mid > 0) {
        $coai_col = coai_get_coai_column_name($table);

        $row = $wpdb->get_row(
            $wpdb->prepare(
              "SELECT *, `{$coai_col}` AS `COAI_number` FROM `{$table}` WHERE member_id=%d",
              (int)$mid
            ),
            ARRAY_A
        );
    }

    if ($mid > 0) {
      error_log('[COAI] member-edit mid=' . (int)$mid . ' coai_col=' . ($coai_col ?? 'NA') . ' COAI_number(alias)=' . ($row['COAI_number'] ?? 'NULL'));
    }

    // Decide privileges for save/render
    $is_manage_user  = ($is_admin_role || $is_manager_role || $is_manage_caps);
    $is_finance_user = (bool) $is_finance_role;

    // Finance edit requires a valid member record
    if ($is_finance_mode && (!$mid || empty($row))) {
        return '<div style="color:#b91c1c;">Member not found.</div>';
    }

    // Helper: normalize date for storing (YYYY-MM-DD) or empty string
    $normalize_date_for_db = function($raw) {
      $raw = trim((string)$raw);
      if ($raw === '') return '';
      $ts = strtotime($raw);
      if (!$ts || $ts <= 0) return $raw; // store raw if it doesn't parse (keeps data instead of wiping)
      return gmdate('Y-m-d', $ts);
    };

    // Handle POST save
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        error_log('[COAI] Member Edit SAVE fired. POST keys=' . implode(',', array_keys($_POST)));
        
                // Handle Family Member add/update/delete separately from normal member save.
        if (!empty($_POST['coai_family_action'])) {
          $can_manage_family_members =
          coai_member_edit_can_manage() ||
          $is_finance_user ||
          ((int)$current_user_member_id > 0 && (int)$mid === (int)$current_user_member_id);

        if (!$can_manage_family_members) {
          return '<div style="color:#b91c1c;">Access denied.</div>';
        }

          if (
            empty($_POST['_coai_family_nonce']) ||
            !wp_verify_nonce($_POST['_coai_family_nonce'], 'coai_family_members_' . (int)$mid)
          ) {
            return '<div style="color:#b91c1c;">Family member security check failed.</div>';
          }

          $family_table  = coai_family_members_table_name();
          $family_action = sanitize_key($_POST['coai_family_action']);

          if ($family_action === 'add_family_member' || $family_action === 'update_family_member') {
            $family_id = isset($_POST['family_id']) ? (int) $_POST['family_id'] : 0;

            $first_name   = sanitize_text_field(wp_unslash($_POST['family_first_name'] ?? ''));
            $last_name    = sanitize_text_field(wp_unslash($_POST['family_last_name'] ?? ''));
            $relationship = sanitize_text_field(wp_unslash($_POST['family_relationship'] ?? ''));
            $email        = sanitize_email(wp_unslash($_POST['family_email'] ?? ''));
            $phone        = sanitize_text_field(wp_unslash($_POST['family_phone'] ?? ''));
            $birthday_raw = sanitize_text_field(wp_unslash($_POST['family_birthday'] ?? ''));
            $status       = strtoupper(sanitize_text_field(wp_unslash($_POST['family_status'] ?? 'ACTIVE')));

            if (!in_array($status, ['ACTIVE', 'EXPIRED', 'ARCHIVED'], true)) {
              $status = 'ACTIVE';
            }

            $birthday = null;
            if ($birthday_raw !== '') {
              $ts = strtotime($birthday_raw);
              if ($ts) $birthday = date('Y-m-d', $ts);
            }

            if ($first_name === '' || $last_name === '') {
              echo '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;color:#991b1b;">Family member first and last name are required.</div>';
            } else {
              $data = [
                'primary_member_id' => (int)$mid,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'relationship'      => $relationship,
                'email'             => $email,
                'phone'             => $phone,
                'birthday'          => $birthday,
                'status'            => $status,
              ];

              $formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

              if ($family_action === 'update_family_member' && $family_id > 0) {
                $ok = $wpdb->update(
                  $family_table,
                  $data,
                  [
                    'id'                => $family_id,
                    'primary_member_id' => (int)$mid,
                  ],
                  $formats,
                  ['%d', '%d']
                );

                echo ($ok === false)
                  ? '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;color:#991b1b;">Family member update failed.</div>'
                  : '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #bbf7d0;background:#ecfdf5;border-radius:10px;color:#065f46;">Family member updated.</div>';
              } else {
                $ok = $wpdb->insert($family_table, $data, $formats);

                echo ($ok === false)
                  ? '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;color:#991b1b;">Family member add failed.</div>'
                  : '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #bbf7d0;background:#ecfdf5;border-radius:10px;color:#065f46;">Family member added.</div>';
              }
            }
          }

          if ($family_action === 'delete_family_member') {
            $family_id = isset($_POST['family_id']) ? (int) $_POST['family_id'] : 0;

            if ($family_id > 0) {
              $ok = $wpdb->delete(
                $family_table,
                [
                  'id'                => $family_id,
                  'primary_member_id' => (int)$mid,
                ],
                ['%d', '%d']
              );

              echo ($ok === false)
                ? '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;color:#991b1b;">Family member remove failed.</div>'
                : '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #bbf7d0;background:#ecfdf5;border-radius:10px;color:#065f46;">Family member removed.</div>';
            }
          }
        }
        
        if (!empty($_POST['coai_family_action'])) {
            return ob_get_clean();
        }

        if (!isset($_POST['coai_member_edit_nonce']) || !wp_verify_nonce($_POST['coai_member_edit_nonce'], 'coai_member_edit')) {
            // nonce missing/invalid — ignore save
        } else {

        $action = isset($_POST['action']) ? sanitize_key($_POST['action']) : 'save_member';

        // Never allow COAI_number to change on a normal save (or finance save)
        if ($action !== 'coai_fix_coai_number') {
          unset($_POST['COAI_number']);
        }

        /**
         * Explicit COAI_number correction (staff only)
         * This is NOT part of normal Save.
         */
        if ($action === 'coai_fix_coai_number') {

          if (!coai_member_edit_can_manage()) {
            return '<div style="color:#b91c1c;">Access denied.</div>';
          }

          // Reload current row fresh (don’t trust $row from earlier)
          $coai_col = coai_get_coai_column_name($table);

          $row_now = $wpdb->get_row(
            $wpdb->prepare(
              "SELECT member_id, `{$coai_col}` AS `COAI_number` FROM `{$table}` WHERE member_id=%d",
              (int)$mid
            ),
            ARRAY_A
          );

          if (!$row_now) {
            return '<div style="color:#b91c1c;">Member not found.</div>';
          }

          $old_posted = isset($_POST['coai_number_old']) ? trim((string) wp_unslash($_POST['coai_number_old'])) : '';
          $new_posted = isset($_POST['coai_number_new']) ? trim((string) wp_unslash($_POST['coai_number_new'])) : '';
          $confirm    = isset($_POST['coai_number_confirm']) ? strtoupper(trim((string) wp_unslash($_POST['coai_number_confirm']))) : '';
          $reason     = isset($_POST['coai_number_reason']) ? trim((string) wp_unslash($_POST['coai_number_reason'])) : '';

          $old_db = (string)($row_now['COAI_number'] ?? '');

          // Require “typed confirm”
          if ($confirm !== 'FIX') {
            return '<div style="color:#b91c1c;">Confirmation failed. Type FIX to proceed.</div>';
          }

          // Reason required for audit integrity
          if ($reason === '') {
            return '<div style="color:#b91c1c;">Reason is required.</div>';
          }

          // Must match current DB value to avoid accidental overwrites
          if ((string)$old_posted !== (string)$old_db) {
            return '<div style="color:#b91c1c;">COAI number changed since page load. Refresh and try again.</div>';
          }

          // Basic sanity checks (adjust to your real rules)
          if ($new_posted === '') {
            return '<div style="color:#b91c1c;">New COAI number cannot be blank.</div>';
          }
          if ($new_posted === $old_db) {
            return '<div style="color:#b91c1c;">New COAI number is the same as the current value.</div>';
          }

          // Example allows digits, dash, letters; prevents weird injections/whitespace
          if (!preg_match('/^[A-Za-z0-9\-]+$/', $new_posted)) {
            return '<div style="color:#b91c1c;">Invalid COAI number format.</div>';
          }

          // Update with WHERE member_id AND COAI_number=old (race-safe)
          $coai_col = coai_get_coai_column_name($table);

          $updated = $wpdb->update(
            $table,
            [$coai_col => $new_posted],
            ['member_id' => (int)$mid, $coai_col => $old_db],
            ['%s'],
            ['%d','%s']
          );

          if ($updated === false) {
            return '<div style="color:#b91c1c;">Database error updating COAI number.</div>';
          }
          if ($updated === 0) {
            return '<div style="color:#b91c1c;">No change saved (record may have changed). Refresh and try again.</div>';
          }

          $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
          coai_member_edit_audit('coai_number_corrected', [
            'member_id' => (int)$mid,
            'old'       => $old_db,
            'new'       => $new_posted,
            'reason'    => $reason,
            'by'        => $user ? $user->user_login : '',
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
          ]);

          // Refresh $row after update
          $coai_col = coai_get_coai_column_name($table);

          $row = $wpdb->get_row(
              $wpdb->prepare(
                "SELECT *, `{$coai_col}` AS `COAI_number` FROM `{$table}` WHERE member_id=%d",
                (int)$mid
              ),
              ARRAY_A
          );

          echo '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #bbf7d0;background:#ecfdf5;border-radius:10px;color:#065f46;">
                  COAI number updated.
                </div>';

        } elseif ($is_finance_user && !$is_manage_user && $action === 'save_member') {

          // Only allow finance fields
          $allowed = [
              'payment_amount',
              'payment_mode',
              'payment_date',
              'check_number',
              'manual_payment_date',
              'membership_expiration',
              'paid_manually', // 0/1
          ];

          $data = [];
          $formats = [];

          foreach ($allowed as $k) {

            // Checkbox: must always be handled (missing = 0)
            if ($k === 'paid_manually') {
              $data[$k] = !empty($_POST[$k]) ? 1 : 0;
              $formats[] = '%d';
              continue;
            }

            if (!array_key_exists($k, $_POST)) continue;

            $val = trim((string) $_POST[$k]);
            $data[$k] = ($val === '') ? null : $val;
            $formats[] = '%s';
          }

          if (!empty($data)) {
            $wpdb->update($table, $data, ['member_id' => (int)$mid], $formats, ['%d']);

            // Refresh row
            $coai_col = coai_get_coai_column_name($table);
            $row = $wpdb->get_row(
              $wpdb->prepare(
                "SELECT *, `{$coai_col}` AS `COAI_number` FROM `{$table}` WHERE member_id=%d",
                (int)$mid
              ),
              ARRAY_A
            );
          }

        } elseif ($action === 'save_member') {

          // MEMBER "Save" path (non-finance): currently read-only fields.
          // BUT: allow staff to update insurance fields here if this form is ever used by staff.
          // (Right now staff uses GOLD editor earlier; this block is mostly future-proof.)

          if (coai_member_edit_can_manage()) {
            $ins_allowed = [
              'insurance_status',
              'insurance_effective_date',
              'insurance_expiration_date',
              'birthday',
              'date_of_birth',
            ];

            $data = [];
            $formats = [];
            
            // Allow staff to update status here too (since the UI renders it)
            if (array_key_exists('status', $_POST)) {
              $new_status = sanitize_text_field(wp_unslash($_POST['status']));
              $allowed_statuses = ['Active','Deceased','Expired'];
 
              if (in_array($new_status, $allowed_statuses, true)) {
                $before_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM `$table` WHERE member_id=%d", (int)$mid));
                $wpdb->update(
                  $table,
                  ['status' => $new_status],
                  ['member_id' => (int)$mid],
                  ['%s'],
                  ['%d']
                );

                if ((string)$before_status !== (string)$new_status) {
                  coai_member_edit_audit('status_updated', [
                    'member_id' => (int)$mid,
                    'old'       => (string)$before_status,
                    'new'       => (string)$new_status,
                    'by'        => $user ? $user->user_login : '',
                    'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
                  ]);
                }
              }
            }

            foreach ($ins_allowed as $k) {
              if (!array_key_exists($k, $_POST)) continue;

              $val = trim((string) wp_unslash($_POST[$k]));

              if (in_array($k, ['insurance_effective_date', 'insurance_expiration_date', 'birthday', 'date_of_birth'], true)) {
                $val = $normalize_date_for_db($val); // returns Y-m-d
              }

              $data[$k] = ($val === '') ? null : $val;
              $formats[] = '%s';
            }

            // Keep both DOB columns synced
            if (array_key_exists('birthday', $data) && !array_key_exists('date_of_birth', $data)) {
              $data['date_of_birth'] = $data['birthday'];
              $formats[] = '%s';
            }
            if (array_key_exists('date_of_birth', $data) && !array_key_exists('birthday', $data)) {
              $data['birthday'] = $data['date_of_birth'];
              $formats[] = '%s';
            }

            if (!empty($data)) {

              // Capture before-values for audit
              $before = $wpdb->get_row(
                $wpdb->prepare("SELECT insurance_status, insurance_effective_date, insurance_expiration_date, birthday FROM `$table` WHERE member_id=%d", (int)$mid),
                ARRAY_A
              );

              $ok = $wpdb->update($table, $data, ['member_id' => (int)$mid], $formats, ['%d']);

              if ($ok !== false) {
                foreach ($data as $field => $newv) {
                  $oldv = isset($before[$field]) ? (string)$before[$field] : '';
                  $newv_s = ($newv === null) ? '' : (string)$newv;
                  if ($oldv !== $newv_s) {
                    coai_member_edit_audit('insurance_field_updated', [
                      'member_id' => (int)$mid,
                      'field'     => $field,
                      'old'       => $oldv,
                      'new'       => $newv_s,
                      'by'        => $user ? $user->user_login : '',
                      'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
                    ]);
                  }
                }
              }

              // Refresh row
              $coai_col = coai_get_coai_column_name($table);
              $row = $wpdb->get_row(
                $wpdb->prepare(
                  "SELECT *, `{$coai_col}` AS `COAI_number` FROM `{$table}` WHERE member_id=%d",
                  (int)$mid
                ),
                ARRAY_A
              );
            }
          }
        }

        } // nonce ok
    } // POST
    
    $family_members = ($mid > 0) ? coai_get_family_members_for_member($mid) : [];

    ob_start(); ?>

<style>
  .coai-member-edit input[readonly],
  .coai-member-edit textarea[readonly],
  .coai-member-edit select[readonly] {
    background-color: #f9fafb;
    color: #111827;
    opacity: 1;
    cursor: default;
  }

  .coai-member-edit input[readonly]:focus,
  .coai-member-edit textarea[readonly]:focus {
    outline: none;
    box-shadow: none;
  }
</style>

  <div class="coai-member-edit" style="max-width:760px;margin:1.25rem auto;padding:1rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">

  <div style="margin-bottom:.75rem;">
    <a href="<?php echo esc_url(home_url('/member-portal/')); ?>"
       style="text-decoration:none;color:#2563eb;font-size:.9rem;">
      &larr; Back to Member Portal
    </a>
  </div>

  <h2 style="margin:0 0 .75rem;">Edit Member</h2>
    <?php if (!$row): ?>
    <p style="color:#b91c1c;">Member not found.</p>
    <?php else: ?>

      <?php if ($is_finance_mode): ?>
        <p style="font-size:.9rem;color:#6b7280;margin-bottom:.75rem;">
          Some fields are read-only. Finance users may update payment-related information only.
        </p>
      <?php endif; ?>

<form method="post">
  <?php wp_nonce_field('coai_member_edit', 'coai_member_edit_nonce'); ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

    <label>First Name
      <input type="text" value="<?php echo esc_attr($row['first_name'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Last Name
      <input type="text" value="<?php echo esc_attr($row['last_name'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label style="grid-column:1 / span 2;">Email
      <input type="text" value="<?php echo esc_attr($row['email'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>
    
    <label style="grid-column:1 / span 2;">Clown Name
      <input type="text"
             value="<?php echo esc_attr($row['clown_name'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label style="grid-column:1 / span 2;">Address
      <input type="text"
             value="<?php echo esc_attr($row['address'] ?? $row['address1'] ?? $row['address_1'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label style="grid-column:1 / span 2;">Address 2
      <input type="text"
             value="<?php echo esc_attr($row['address2'] ?? $row['address_2'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>City
      <input type="text"
             value="<?php echo esc_attr($row['city'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>State
      <input type="text"
             value="<?php echo esc_attr($row['state'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Zip
      <input type="text"
             value="<?php echo esc_attr($row['zip'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Country
      <input type="text"
             value="<?php echo esc_attr($row['country'] ?? $row['country_code'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Region
      <input type="text"
             value="<?php echo esc_attr($row['region'] ?? ''); ?>"
             readonly
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Status
      <?php
      $can_edit_status  = (function_exists('coai_staff_can') && coai_staff_can('manage')); // Admin/Manager
      $allowed_statuses = ['Active', 'Deceased', 'Expired'];
      $current_status   = isset($row['status']) ? (string)$row['status'] : '';

      $current_status_norm = ucfirst(strtolower(trim($current_status)));
      if (!in_array($current_status_norm, $allowed_statuses, true)) {
        $current_status_norm = $current_status;
      }
      ?>

      <?php if ($can_edit_status): ?>
        <select name="status" id="status" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          <?php foreach ($allowed_statuses as $status_option): ?>
            <option value="<?php echo esc_attr($status_option); ?>" <?php selected($current_status_norm, $status_option); ?>>
              <?php echo esc_html($status_option); ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="text" value="<?php echo esc_attr($current_status_norm); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php endif; ?>
    </label>

    <label>Level ID
      <input type="text" value="<?php echo esc_attr((string)($row['membership_level_id'] ?? '')); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Expiration (Y-m-d)
      <input type="text" value="<?php echo esc_attr($row['membership_expiration'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>
    
    <?php $can_edit_dob = coai_member_edit_can_manage(); ?>

    <label>Birthday
      <?php
        $birthday_value = '';

        if (!empty($row['birthday'])) {
          $ts = strtotime((string)$row['birthday']);
          $birthday_value = $ts ? date('Y-m-d', $ts) : (string)$row['birthday'];
        } elseif (!empty($row['date_of_birth'])) {
          $ts = strtotime((string)$row['date_of_birth']);
          $birthday_value = $ts ? date('Y-m-d', $ts) : (string)$row['date_of_birth'];
        }
      ?>

      <?php if ($can_edit_dob): ?>
        <input name="birthday"
               type="date"
               value="<?php echo esc_attr($birthday_value); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php else: ?>
        <input type="text"
               value="<?php echo esc_attr($birthday_value); ?>"
               readonly
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php endif; ?>
    </label>

    </div>
    
    <!-- Family Members -->
  <hr style="margin:1rem 0;border:none;border-top:1px solid #e5e7eb;">
  <h3 style="margin:0 0 .5rem;">Family Members</h3>
  <p style="margin:0 0 .75rem;color:#6b7280;font-size:.9rem;">
    Linked family members for this primary member account.
  </p>

  <?php if (!empty($family_members)): ?>
    <?php foreach ($family_members as $family): ?>
      <form method="post" style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:12px;background:#f9fafb;">
        <?php wp_nonce_field('coai_family_members_' . (int)$mid, '_coai_family_nonce'); ?>
        <input type="hidden" name="coai_family_action" value="update_family_member">
        <input type="hidden" name="family_id" value="<?php echo esc_attr((int)$family['id']); ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <label>First Name
            <input name="family_first_name" type="text" required value="<?php echo esc_attr($family['first_name'] ?? ''); ?>" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </label>

          <label>Last Name
            <input name="family_last_name" type="text" required value="<?php echo esc_attr($family['last_name'] ?? ''); ?>" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </label>

          <label>Relationship
            <input name="family_relationship" type="text" value="<?php echo esc_attr($family['relationship'] ?? ''); ?>" placeholder="Spouse, Child, etc." style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </label>

          <label>Email
            <input name="family_email" type="email" value="<?php echo esc_attr($family['email'] ?? ''); ?>" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </label>

          <label>Phone
            <input name="family_phone" type="text" value="<?php echo esc_attr($family['phone'] ?? ''); ?>" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </label>

          <label>Birthday
            <input name="family_birthday" type="date" value="<?php echo esc_attr(!empty($family['birthday']) ? date('Y-m-d', strtotime((string)$family['birthday'])) : ''); ?>" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </label>

          <label>Status
            <?php $family_status = strtoupper($family['status'] ?? 'ACTIVE'); ?>
            <select name="family_status" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
              <option value="ACTIVE" <?php selected($family_status, 'ACTIVE'); ?>>ACTIVE</option>
              <option value="EXPIRED" <?php selected($family_status, 'EXPIRED'); ?>>EXPIRED</option>
              <option value="ARCHIVED" <?php selected($family_status, 'ARCHIVED'); ?>>ARCHIVED</option>
            </select>
          </label>
        </div>

        <div style="margin-top:10px;display:flex;gap:.5rem;justify-content:flex-end;">
          <button type="submit" class="button" style="padding:.45rem .7rem;border:1px solid #111;border-radius:8px;background:#111;color:#fff;">
            Update Family Member
          </button>
        </div>
      </form>

      <form method="post" onsubmit="return confirm('Remove this family member?');" style="margin:-6px 0 14px;display:flex;justify-content:flex-end;">
        <?php wp_nonce_field('coai_family_members_' . (int)$mid, '_coai_family_nonce'); ?>
        <input type="hidden" name="coai_family_action" value="delete_family_member">
        <input type="hidden" name="family_id" value="<?php echo esc_attr((int)$family['id']); ?>">
        <button type="submit" class="button" style="padding:.45rem .7rem;border:1px solid #b91c1c;border-radius:8px;background:#fff;color:#b91c1c;">
          Remove Family Member
        </button>
      </form>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:#6b7280;font-size:.9rem;">No family members listed.</p>
  <?php endif; ?>

  <div style="border:1px dashed #d1d5db;border-radius:10px;padding:12px;margin-top:12px;">
    <h4 style="margin:0 0 .75rem;">Add Family Member</h4>

    <form method="post">
      <?php wp_nonce_field('coai_family_members_' . (int)$mid, '_coai_family_nonce'); ?>
      <input type="hidden" name="coai_family_action" value="add_family_member">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label>First Name
          <input name="family_first_name" type="text" required style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </label>

        <label>Last Name
          <input name="family_last_name" type="text" required style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </label>

        <label>Relationship
          <input name="family_relationship" type="text" placeholder="Spouse, Child, etc." style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </label>

        <label>Email
          <input name="family_email" type="email" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </label>

        <label>Phone
          <input name="family_phone" type="text" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </label>

        <label>Birthday
          <input name="family_birthday" type="date" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </label>

        <label>Status
          <select name="family_status" style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
            <option value="ACTIVE">ACTIVE</option>
            <option value="EXPIRED">EXPIRED</option>
            <option value="ARCHIVED">ARCHIVED</option>
          </select>
        </label>
      </div>

      <div style="margin-top:10px;display:flex;justify-content:flex-end;">
        <button type="submit" name="coai_family_action" value="add_family_member" class="button" style="padding:.45rem .7rem;border:1px solid #111;border-radius:8px;background:#111;color:#fff;">
            Add Family Member
          </button>
      </div>
    </form>
  </div>

  <?php
  $coai_current = (string)($row['COAI_number'] ?? ($row['coai_number'] ?? ''));
  ?>

  <!-- Insurance Fields -->
  <hr style="margin:1rem 0;border:none;border-top:1px solid #e5e7eb;">
  <h3 style="margin:0 0 .5rem;">Insurance</h3>
  <p style="margin:0 0 .75rem;color:#6b7280;font-size:.9rem;">
    These fields are updated from the insurance CSV. Only Admin/Manager may edit.
  </p>

  <?php
    $can_edit_insurance = coai_member_edit_can_manage(); // staff only
    $ins_status = (string)($row['insurance_status'] ?? '');
    $ins_eff    = (string)($row['insurance_effective_date'] ?? '');
    $ins_exp    = (string)($row['insurance_expiration_date'] ?? '');
  ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
    <label>Insurance Status
      <?php if ($can_edit_insurance): ?>
        <input name="insurance_status" type="text"
               value="<?php echo esc_attr($ins_status); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php else: ?>
        <input type="text" readonly
               value="<?php echo esc_attr($ins_status); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php endif; ?>
    </label>

    <label>Policy Effective Date
      <?php if ($can_edit_insurance): ?>
        <input name="insurance_effective_date" type="text"
               value="<?php echo esc_attr($ins_eff); ?>"
               placeholder="YYYY-MM-DD"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php else: ?>
        <input type="text" readonly
               value="<?php echo esc_attr($ins_eff); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php endif; ?>
    </label>

    <label>Policy Expiration Date
      <?php if ($can_edit_insurance): ?>
        <input name="insurance_expiration_date" type="text"
               value="<?php echo esc_attr($ins_exp); ?>"
               placeholder="YYYY-MM-DD"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php else: ?>
        <input type="text" readonly
               value="<?php echo esc_attr($ins_exp); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php endif; ?>
    </label>
  </div>

  <?php if (coai_member_edit_can_manage()): ?>
    <hr style="margin:1rem 0;border:none;border-top:1px solid #e5e7eb;">
    <h3 style="margin:0 0 .5rem;">COAI Number Correction (Staff Only)</h3>
    <p style="margin:0 0 .75rem;color:#6b7280;font-size:.9rem;">
      This does not change on normal Save. Use only to correct a wrong COAI number.
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <label>Current COAI Number
        <input type="text" value="<?php echo esc_attr($coai_current); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>

      <label>New COAI Number
        <input name="coai_number_new" type="text" value=""
               autocomplete="off"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>

      <label style="grid-column:1 / span 2;">Reason (required)
        <input name="coai_number_reason" type="text" value=""
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>

      <label>Type FIX to confirm
        <input name="coai_number_confirm" type="text" value=""
               autocomplete="off"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>
    </div>

    <input type="hidden" name="coai_number_old" value="<?php echo esc_attr($coai_current); ?>">
  <?php endif; ?>


  <?php if (!empty($is_finance_mode) && $is_finance_mode): ?>
    <hr style="margin:1rem 0;border:none;border-top:1px solid #e5e7eb;">
    <h3 style="margin:0 0 .5rem;">Finance Fields</h3>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

      <label>Payment Amount
        <input name="payment_amount" type="text"
               value="<?php echo esc_attr($row['payment_amount'] ?? ''); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>

      <label>Payment Mode
        <input name="payment_mode" type="text"
               value="<?php echo esc_attr($row['payment_mode'] ?? ''); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>

      <label>Payment Date
        <input name="payment_date" type="text"
               value="<?php echo esc_attr($row['payment_date'] ?? ''); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>

      <label>Check Number
        <input name="check_number" type="text"
               value="<?php echo esc_attr($row['check_number'] ?? ''); ?>"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      </label>

      <label style="grid-column:1 / span 2; display:flex; align-items:center; gap:.5rem;">
        <input type="checkbox"
               name="paid_manually"
               value="1"
               <?php checked(!empty($row['paid_manually'])); ?>>
        Paid Manually
      </label>
    </div>
  <?php endif; ?>

  <div style="margin-top:.75rem;display:flex;gap:.5rem;justify-content:flex-end;">

    <!-- Normal Save -->
    <button type="submit"
            name="action"
            value="save_member"
            class="button"
            style="padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;background:#111;color:#fff;">
      Save
    </button>

    <!-- Intentional COAI correction -->
    <button type="submit"
            name="action"
            value="coai_fix_coai_number"
            class="button"
            style="padding:.5rem .75rem;border:1px solid #b91c1c;border-radius:8px;background:#fff;color:#b91c1c;">
      Correct COAI Number
    </button>

    <a class="button"
       href="<?php echo esc_url(home_url('/member-portal/')); ?>"
       style="text-decoration:none;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;background:#fff;">
      Cancel
    </a>
  </div>
</form>

      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
