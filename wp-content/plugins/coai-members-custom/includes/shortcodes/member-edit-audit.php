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
  /**
   * Board-grade audit wrapper
   * - Uses coai_audit_log() if available (preferred)
   * - Falls back to error_log otherwise
   */
  function coai_member_edit_audit(int $member_id, string $action, ?array $diff = null, string $note = '', ?array $snapshot = null): void {
    if (function_exists('coai_audit_log')) {
      coai_audit_log($member_id, $action, $diff, $note, $snapshot);
      return;
    }
    error_log('[COAI][AUDIT] member_id=' . $member_id . ' action=' . $action . ' note=' . $note . ' diff=' . wp_json_encode($diff) . ' snap=' . wp_json_encode($snapshot));
  }
}


// Detect which COAI column exists in wp_members (case variations happen in real life)
if (!function_exists('coai_get_coai_column_name')) {
  function coai_get_coai_column_name($table) {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    global $wpdb;
    $cols = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
    $cols = array_map('strval', (array)$cols);

    if (in_array('COAI_number', $cols, true)) {
      $cache[$table] = 'COAI_number';
      return $cache[$table];
    }
    if (in_array('coai_number', $cols, true)) {
      $cache[$table] = 'coai_number';
      return $cache[$table];
    }

    // Default to the name you *want*; prevents fatal errors elsewhere
    $cache[$table] = 'COAI_number';
    return $cache[$table];
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


// --- Finance: route to Finance editor/UI (NOT the admin editor) ---
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


    // Option 2: if you have a dedicated finance edit shortcode, use it instead:
    // return do_shortcode('[coai_members_finance_edit]');

    // --- MEMBERS: show lightweight read-only preview (same as before) ---
    global $wpdb;
    $table = defined('COAI_MEMBERS_TABLE') ? COAI_MEMBERS_TABLE : ($wpdb->prefix . 'members');

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

    // Handle POST save (Finance-only whitelist)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        error_log('[COAI] Member Edit SAVE fired. POST keys=' . implode(',', array_keys($_POST)));


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
  
  /* --- AUDIT: BEFORE snapshot (full row) --- */
  $before_full = [];
  if (function_exists('coai_audit_log_update')) {
    $before_full = (array) $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM `{$table}` WHERE member_id=%d LIMIT 1", (int)$mid),
      ARRAY_A
    );
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

  // Optional: format enforcement (tune this to your real format)
  // Example allows digits, dash, letters; prevents weird injections/whitespace
  if (!preg_match('/^[A-Za-z0-9\-]+$/', $new_posted)) {
    return '<div style="color:#b91c1c;">Invalid COAI number format.</div>';
  }

  // Update with WHERE member_id AND COAI_number=old (race-safe)
  $coai_col = coai_get_coai_column_name($table);

  // Update with WHERE member_id AND old value (race-safe)
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

  // --- AUDIT: AFTER snapshot + append-only diff log ---
  if (function_exists('coai_audit_log_update')) {

    $after_full = (array) $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM `{$table}` WHERE member_id=%d LIMIT 1", (int)$mid),
      ARRAY_A
    );

    // $before_full must have been captured earlier (right after $row_now exists)
    coai_audit_log_update(
      (int) $mid,
      (array) ($before_full ?? []),
      (array) $after_full,
      'COAI number correction: ' . $reason
    );
  }

  // Refresh $row after update
  $coai_col = coai_get_coai_column_name($table);

        $row = $wpdb->get_row(
            $wpdb->prepare(
              "SELECT *, `{$coai_col}` AS `COAI_number` FROM `{$table}` WHERE member_id=%d",
              (int)$mid
            ),
            ARRAY_A
        );

  // Optional: show a success banner (simple)
  echo '<div style="margin:0 auto 12px;max-width:760px;padding:10px;border:1px solid #bbf7d0;background:#ecfdf5;border-radius:10px;color:#065f46;">
          COAI number updated.
        </div>';

} elseif ($is_finance_user && !$is_manage_user && $action === 'save_member') {
    $before_fin = [];
    if (function_exists('coai_audit_log_update')) {
      $before_fin = (array) $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$table}` WHERE member_id=%d LIMIT 1", (int)$mid),
        ARRAY_A
      );
    }

  // Only allow finance fields (adjust names to your real wp_members columns)
  $allowed = [
      'payment_amount',
      'payment_mode',
      'payment_date',
      'check_number',
      'manual_payment_date',
      'membership_expiration',
      'paid_manually', // 0/1
      // 'status', // include ONLY if Finance is allowed to change status
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

    // Other fields: only update if present
    if (!array_key_exists($k, $_POST)) continue;

    $val = trim((string) $_POST[$k]);
    $data[$k] = ($val === '') ? null : $val;
    $formats[] = '%s';
}


if (!empty($data)) {

    // --- AUDIT: BEFORE snapshot ---
    $before_fin = [];
    if (function_exists('coai_audit_log_update')) {
        $before_fin = (array) $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE member_id=%d LIMIT 1",
                (int)$mid
            ),
            ARRAY_A
        );
    }

    // Perform update
    $ok = $wpdb->update(
        $table,
        $data,
        ['member_id' => (int)$mid],
        $formats,
        ['%d']
    );

    // --- AUDIT: AFTER snapshot + log ---
    if ($ok !== false && function_exists('coai_audit_log_update')) {
        $after_fin = (array) $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE member_id=%d LIMIT 1",
                (int)$mid
            ),
            ARRAY_A
        );

        coai_audit_log_update(
            (int)$mid,
            $before_fin,
            $after_fin,
            'Finance fields updated (member-edit)'
        );
    }

    // Refresh row for UI
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
    }

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

    <label>City
      <input type="text" value="<?php echo esc_attr($row['city'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>State
      <input type="text" value="<?php echo esc_attr($row['state'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Zip
      <input type="text" value="<?php echo esc_attr($row['zip'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Status
      <?php
      $can_edit_status  = (function_exists('coai_staff_can') && coai_staff_can('manage')); // Admin/Manager
      $allowed_statuses = ['Active', 'Deceased', 'Expired'];
      $current_status   = isset($row['status']) ? (string)$row['status'] : '';

      // Optional: normalize display in case DB has lowercase/extra spaces
      $current_status_norm = ucfirst(strtolower(trim($current_status)));
      if (!in_array($current_status_norm, $allowed_statuses, true)) {
        $current_status_norm = $current_status; // fall back to raw if unexpected
      }

      $user = wp_get_current_user();
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
        <!-- IMPORTANT: no name="status" here, so Finance cannot submit status changes -->
        <input type="text" value="<?php echo esc_attr($current_status_norm); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
      <?php endif; ?>
    </label>

    <label>Level ID
      <input type="text" value="<?php echo esc_attr((string)($row['membership_level_id'] ?? '')); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>

    <label>Expiration (Y-m-d)
      <input type="text" value="<?php echo esc_attr($row['membership_expiration'] ?? ''); ?>" readonly style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
    </label>
  </div>
  
  <?php
  $coai_current = (string)($row['COAI_number'] ?? ($row['coai_number'] ?? ''));
  ?>

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
