<?php
if (!defined('ABSPATH')) exit;

if (!defined('COAI_MEMBERS_TABLE')) {
  define('COAI_MEMBERS_TABLE', 'wp_members');
}

/**
 * Return linked wp_members.member_id for current WP user via explicit usermeta only.
 * No email/username resolution here.
 */
if (!function_exists('coai_current_member_id_linked')) {
  function coai_current_member_id_linked(): int {
    if (!is_user_logged_in()) return 0;
    $u = wp_get_current_user();
    if (!$u || empty($u->ID)) return 0;
    return (int) get_user_meta((int)$u->ID, 'coai_member_id', true);
  }
}

/**
 * Optional: Read wp_members.usergroup for current WP user ONLY via explicit coai_member_id usermeta link.
 * Never by email/username.
 */
if (!function_exists('coai_current_usergroup_linked')) {
  function coai_current_usergroup_linked(): string {
    $mid = coai_current_member_id_linked();
    if ($mid <= 0) return '';

    global $wpdb;
    $table = COAI_MEMBERS_TABLE;

    $ug = $wpdb->get_var($wpdb->prepare("SELECT usergroup FROM `{$table}` WHERE member_id=%d LIMIT 1", $mid));
    return strtoupper(trim((string)$ug));
  }
}

if (!function_exists('coai_admin_portal_is_allowed')) {
  function coai_admin_portal_is_allowed(): bool {
    if (!is_user_logged_in()) return false;

    // WP administrators always allowed
    if (current_user_can('manage_options')) return true;

    $u = wp_get_current_user();
    $roles = array_map('strtolower', (array) ($u->roles ?? []));

    // Explicit allowlist
    $allow_roles = ['manager', 'finance'];

    // Explicit denylist (keeps member-test + newsletter manager out)
    $deny_roles  = ['member', 'newsletter-manager', 'newsletter_manager'];

    foreach ($deny_roles as $r) {
      if (in_array($r, $roles, true)) return false;
    }

    // then allow via canonical helper
    if (function_exists('coai_staff_can') && coai_staff_can('view')) return true;

    return false;
  }
}

add_shortcode('coai_members_admin_legacy', function () {

  if (!is_user_logged_in()) {
    wp_safe_redirect(home_url('/member-login/?login=required'));
    exit;
  }

  // 🔐 Permission check (WP role/cap based; no username/email mapping)
  if (!coai_admin_portal_is_allowed()) {
    return '<div style="max-width:980px;margin:24px auto;padding:12px;border:1px solid #fecaca;background:#fee2e2;border-radius:10px;">
      You do not have permission to access this area.
    </div>';
  }

  global $wpdb;
  $table = COAI_MEMBERS_TABLE;

  // Column detection
  $cols = array_map('strtolower', (array) $wpdb->get_col("DESC `{$table}`", 0));
  $has = function($col) use ($cols) {
    return in_array(strtolower($col), $cols, true);
  };

  // Handle schema typos / variants
  $first_col = 'first_name';
  $coai_col  = 'COAI_number';

  // Helpers
  $get = function($keys, $default = '') {
    foreach ((array)$keys as $k) {
      if (isset($_GET[$k]) && $_GET[$k] !== '') return (string) $_GET[$k];
    }
    return $default;
  };

  $clean_any = function($v) {
    $v = trim((string)$v);
    return ($v === '' || strtolower($v) === 'any') ? '' : $v;
  };

  $parse_date_to_mysql = function($s, $end_of_day = false) {
    $s = trim((string)$s);
    if ($s === '') return '';

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
      return $s . ($end_of_day ? ' 23:59:59' : ' 00:00:00');
    }
    $dt = DateTime::createFromFormat('m/d/Y', $s);
    if ($dt instanceof DateTime) {
      return $dt->format('Y-m-d') . ($end_of_day ? ' 23:59:59' : ' 00:00:00');
    }
    return '';
  };

  // Build WHERE + params once, reuse for list + export
  $build_filters = function() use ($wpdb, $table, $has, $clean_any, $parse_date_to_mysql, $get, $first_col, $coai_col) {
    $where  = [];
    $params = [];

    // --- Search (Name/username/member #/COAI #) ---
    // NOTE: Email search is OPTIONAL. Default OFF for Phase 2 discipline.
    $allow_email_search = defined('COAI_ADMINPORTAL_ALLOW_EMAIL_SEARCH') && COAI_ADMINPORTAL_ALLOW_EMAIL_SEARCH;

    $q = trim((string)$get(['q', 'search'], ''));
    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $parts = [];

      if ($has('last_name'))     { $parts[] = "last_name LIKE %s";     $params[] = $like; }
      if ($first_col !== '')     { $parts[] = "{$first_col} LIKE %s";  $params[] = $like; }
      if ($allow_email_search && $has('email')) { $parts[] = "email LIKE %s"; $params[] = $like; }
      if ($has('username'))      { $parts[] = "username LIKE %s";      $params[] = $like; }
      if ($has('member_number')) { $parts[] = "member_number LIKE %s"; $params[] = $like; }
      if ($coai_col !== '')      { $parts[] = "`{$coai_col}` LIKE %s"; $params[] = $like; }

      if (!empty($parts)) $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    // --- Level (ID) ---
    $level = $clean_any($get(['level', 'membership_level_id', 'level_id'], ''));
    if ($level !== '' && ctype_digit($level) && $has('membership_level_id')) {
      $where[]  = "membership_level_id = %d";
      $params[] = (int)$level;
    }
    
    // --- Status ---
    $status = strtoupper($clean_any($get(['status', 'member_status'], '')));
    if ($status !== '' && $has('status')) {
      $where[]  = "UPPER(TRIM(status)) = %s";
      $params[] = $status;
    }

    // --- Registered date range (Reg From/To) ---
    $reg_from_raw = $clean_any($get(['reg_from', 'registered_from', 'regFrom'], ''));
    $reg_to_raw   = $clean_any($get(['reg_to', 'registered_to', 'regTo'], ''));

    // --- Month/Year (Month From/To + Year) ---
    $month_from = $clean_any($get(['month_from', 'from_month', 'monthFrom'], ''));
    $month_to   = $clean_any($get(['month_to', 'to_month', 'monthTo'], ''));
    $year       = $clean_any($get(['year', 'reg_year'], ''));

    $mon_map = ['JAN'=>1,'FEB'=>2,'MAR'=>3,'APR'=>4,'MAY'=>5,'JUN'=>6,'JUL'=>7,'AUG'=>8,'SEP'=>9,'OCT'=>10,'NOV'=>11,'DEC'=>12];
    $month_from = strtoupper(trim((string)$month_from));
    $month_to   = strtoupper(trim((string)$month_to));

    if ($month_from !== '' && !ctype_digit($month_from)) {
      $month_from = (string)($mon_map[substr($month_from, 0, 3)] ?? '');
    }
    if ($month_to !== '' && !ctype_digit($month_to)) {
      $month_to = (string)($mon_map[substr($month_to, 0, 3)] ?? '');
    }

    // New Members toggle (flag-based)
    $new_only  = $clean_any($get(['new_only'], ''));

    // Effective registration date for filtering
    $date_expr = "COALESCE(NULLIF(registered_date,'0000-00-00 00:00:00'), created_at)";

    $reg_from = $parse_date_to_mysql($reg_from_raw, false);
    $reg_to   = $parse_date_to_mysql($reg_to_raw, true);

    if ($reg_from !== '' || $reg_to !== '') {
      if ($reg_from !== '') { $where[] = "$date_expr >= %s"; $params[] = $reg_from; }
      if ($reg_to !== '')   { $where[] = "$date_expr <= %s"; $params[] = $reg_to; }
    } else {
      $y  = ($year !== '' && ctype_digit($year)) ? (int)$year : 0;
      $mf = ($month_from !== '' && ctype_digit($month_from)) ? (int)$month_from : 0;
      $mt = ($month_to   !== '' && ctype_digit($month_to))   ? (int)$month_to   : 0;

      if ($y > 0) {
        if ($mf > 0 && $mt > 0) {
          $where[] = "YEAR($date_expr) = %d AND MONTH($date_expr) BETWEEN %d AND %d";
          $params[] = $y; $params[] = $mf; $params[] = $mt;
        } elseif ($mf > 0) {
          $where[] = "YEAR($date_expr) = %d AND MONTH($date_expr) >= %d";
          $params[] = $y; $params[] = $mf;
        } elseif ($mt > 0) {
          $where[] = "YEAR($date_expr) = %d AND MONTH($date_expr) <= %d";
          $params[] = $y; $params[] = $mt;
        } else {
          $where[] = "YEAR($date_expr) = %d";
          $params[] = $y;
        }
      }
    }

    // --- New Members only (based on is_new_member flag) ---
    if ($new_only === '1' && $has('is_new_member')) {
      $where[] = "is_new_member = 1";
    }

    // Optional: hide soft-deleted by default (deleted_at)
    if ($has('deleted_at') && (!isset($_GET['show_deleted']) || $_GET['show_deleted'] !== '1')) {
      $where[] = "(deleted_at IS NULL OR deleted_at = '' OR deleted_at = '0000-00-00 00:00:00')";
    }

    $where_sql = '';
    if (!empty($where)) $where_sql = ' WHERE ' . implode(' AND ', $where);

    return [
      'where_sql'   => $where_sql,
      'params'      => $params,
      'has_filters' => !empty($where),
    ];
  };

  // ----- Export (must happen before output) -----
  if (isset($_GET['coai_export'])) {
    if (!isset($_GET['_coai_nonce']) || !wp_verify_nonce($_GET['_coai_nonce'], 'coai_export')) {
      return '<p style="color:#b91c1c;">Invalid export nonce.</p>';
    }

    $filters = $build_filters();
    $order   = $has('last_name') ? "ORDER BY last_name ASC, " . ($first_col ?: 'full_name') . " ASC, member_id ASC" : "ORDER BY member_id ASC";

    $sql = "SELECT * FROM `{$table}`" . $filters['where_sql'] . " {$order}";
    $rows = $filters['has_filters']
      ? $wpdb->get_results($wpdb->prepare($sql, ...$filters['params']), ARRAY_A)
      : $wpdb->get_results($sql, ARRAY_A);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=members-' . date('Ymd-His') . '.csv');

    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
      fputcsv($out, array_keys($rows[0]));
      foreach ($rows as $r) fputcsv($out, $r);
    } else {
      fputcsv($out, ['No data']);
    }
    fclose($out);
    exit;
  }

  // ----- Detail view -----
  if (isset($_GET['member_id']) && (int)$_GET['member_id'] > 0) {
    $member_id = (int)$_GET['member_id'];

    // Block anything that can affect auth/permissions/linkage.
    $blocked = [
      'member_id',
      'password',
      'rest_token_hash',
      'reset_expires',
      'force_password_change',
      'created_at',
      'updated_at',
      'usergroup',        // staff/member authority in wp_members (do NOT edit here)
      'deleted_at',
      'deleted_by',
      'deleted_reason',
    ];

    $editable = array_values(array_diff($cols, array_map('strtolower', $blocked)));
    $msg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coai_save_member'])) {
      if (!isset($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_member_edit_'.$member_id)) {
        $msg = '<div style="margin-bottom:10px;padding:8px;border:1px solid #fecaca;background:#fee2e2;border-radius:8px;">Security check failed.</div>';
      } else {
        $data   = [];
        $format = [];

        foreach ($editable as $col) {
          if (!isset($_POST[$col])) continue;
          $val = is_array($_POST[$col]) ? '' : (string) wp_unslash($_POST[$col]);

          // Keep email sane if editable
          if (strtolower($col) === 'email') {
            $val = sanitize_email($val);
          } else {
            $val = sanitize_text_field($val);
          }

          $data[$col] = $val;
          $format[] = '%s';
        }

        if (!empty($data)) {
          if ($has('updated_at')) {
            $data['updated_at'] = current_time('mysql');
            $format[] = '%s';
          }

          $ok = $wpdb->update($table, $data, ['member_id' => $member_id], $format, ['%d']);
          $msg = ($ok !== false)
            ? '<div style="margin-bottom:10px;padding:8px;border:1px solid #16a34a;background:#dcfce7;border-radius:8px;">Saved.</div>'
            : '<div style="margin-bottom:10px;padding:8px;border:1px solid #f59e0b;background:#fef3c7;border-radius:8px;">No changes or update failed.</div>';
        }
      }
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE member_id=%d LIMIT 1", $member_id), ARRAY_A);
    if (!$row) {
      return '<div style="max-width:980px;margin:24px auto;padding:12px;border:1px solid #fecaca;background:#fee2e2;border-radius:10px;">Member not found.</div>';
    }

    $export_url = add_query_arg(
      array_merge($_GET, ['coai_export' => 1,'_coai_nonce' => wp_create_nonce('coai_export')]),
      home_url('/member-directory/')
    );

    ob_start(); ?>
    <div style="max-width:980px;margin:24px auto;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
      <h2 style="margin-top:0;">Edit Member</h2>
      <?php echo $msg; ?>
      <p>
        <a class="button" href="<?php echo esc_url(home_url('/member-directory/')); ?>">← Back to Members List</a>
        <a class="button" href="<?php echo esc_url($export_url); ?>" style="margin-left:.35rem;">Export CSV</a>
      </p>

      <form method="post" style="margin-top:10px;">
        <?php wp_nonce_field('coai_member_edit_'.$member_id, '_coai_nonce'); ?>
        <input type="hidden" name="member_id" value="<?php echo (int)$member_id; ?>">

        <table class="form-table" role="presentation">
          <tbody>
            <tr>
              <th scope="row">member_id</th>
              <td><code><?php echo (int)$row['member_id']; ?></code></td>
            </tr>

            <?php foreach ($cols as $col): ?>
              <?php
                if (in_array($col, ['member_id','password','rest_token_hash','reset_expires','force_password_change','created_at','updated_at'], true)) continue;
              ?>
              <tr>
                <th scope="row"><label for="f_<?php echo esc_attr($col); ?>"><?php echo esc_html($col); ?></label></th>
                <td>
                  <?php if ($col === 'usergroup' || $col === 'deleted_at' || $col === 'deleted_by' || $col === 'deleted_reason'): ?>
                    <input type="text" id="f_<?php echo esc_attr($col); ?>" value="<?php echo esc_attr((string)($row[$col] ?? '')); ?>"
                           style="width:360px;padding:.4rem;border:1px solid #d1d5db;border-radius:6px;background:#f3f4f6;" disabled>
                    <p class="description">This field is locked here.</p>
                  <?php else: ?>
                    <input type="text" name="<?php echo esc_attr($col); ?>" id="f_<?php echo esc_attr($col); ?>"
                           value="<?php echo esc_attr((string)($row[$col] ?? '')); ?>"
                           style="width:360px;padding:.4rem;border:1px solid #d1d5db;border-radius:6px;">
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <p>
          <button type="submit" name="coai_save_member" value="1" class="button button-primary">Save Changes</button>
          <a class="button" href="<?php echo esc_url(home_url('/member-directory/')); ?>" style="margin-left:.35rem;">Cancel</a>
        </p>
      </form>
    </div>
    <?php
    return ob_get_clean();
  }

  // ----- LIST VIEW -----

  // Build options for Level ID (only if column exists)
  $level_id_options = [];
  if ($has('membership_level_id')) {
    $level_id_options = (array) $wpdb->get_col("SELECT DISTINCT membership_level_id FROM `{$table}` WHERE membership_level_id IS NOT NULL AND membership_level_id<>'' ORDER BY membership_level_id+0 ASC");
  }

  // Current filter values (for form)
  $q          = trim((string)$get(['q','search'], ''));
  $level      = trim((string)$get(['level','membership_level_id','level_id'], ''));
  $status     = strtoupper(trim((string)$get(['status','member_status'], '')));
  $reg_from   = trim((string)$get(['reg_from','registered_from','regFrom'], ''));
  $reg_to     = trim((string)$get(['reg_to','registered_to','regTo'], ''));
  $month_from = trim((string)$get(['month_from','from_month','monthFrom'], ''));
  $month_to   = trim((string)$get(['month_to','to_month','monthTo'], ''));
  $year       = trim((string)$get(['year','reg_year'], ''));

  $filters = $build_filters();
  $order   = $has('last_name') ? "ORDER BY last_name ASC, " . ($first_col ?: 'full_name') . " ASC, member_id ASC" : "ORDER BY member_id ASC";
  $sql = "SELECT * FROM `{$table}`" . $filters['where_sql'] . " {$order} LIMIT 1000";
  $rows = $filters['has_filters']
    ? $wpdb->get_results($wpdb->prepare($sql, ...$filters['params']), ARRAY_A)
    : $wpdb->get_results($sql, ARRAY_A);

  $export_url = add_query_arg(
    array_merge($_GET, ['coai_export' => 1,'_coai_nonce' => wp_create_nonce('coai_export')]),
    home_url('/member-directory/')
  );

  $clear_url = home_url('/member-directory/');

  $months = [
    1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',
    7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'
  ];

  $allow_email_search = defined('COAI_ADMINPORTAL_ALLOW_EMAIL_SEARCH') && COAI_ADMINPORTAL_ALLOW_EMAIL_SEARCH;
  $search_placeholder = $allow_email_search
    ? 'Name, email, username, member #'
    : 'Name, username, member #';

  $status_options = [];
  if ($has('status')) {
    $status_options = (array) $wpdb->get_col("
      SELECT DISTINCT UPPER(TRIM(status))
      FROM `{$table}`
      WHERE status IS NOT NULL
        AND TRIM(status) <> ''
      ORDER BY UPPER(TRIM(status)) ASC
    ");
  }

  ob_start(); ?>
  
  <div style="max-width:1200px;margin:24px auto;padding:16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
      <h2 style="margin:0;">Members</h2>
      <a class="button" href="<?php echo esc_url(home_url('/member-portal/')); ?>">← Back to Member Portal</a>
    </div>

    <form method="get" style="margin:14px 0 12px;">
      <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:10px;align-items:end;">
        <div style="grid-column:span 12;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Search</label>
          <input type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr($search_placeholder); ?>"
                 style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:10px;">
        </div>

        <div style="grid-column:span 3;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Level</label>
          <select name="level" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
            <option value="">Any</option>
            <?php foreach ($level_id_options as $opt): ?>
              <?php $opt = (string)$opt; ?>
              <option value="<?php echo esc_attr($opt); ?>" <?php selected($level, $opt); ?>>
                <?php echo esc_html($opt); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div style="grid-column:span 3;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Status</label>
          <select name="status" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
            <option value="">Any</option>

            <?php foreach ($status_options as $opt): ?>
              <?php $opt = strtoupper(trim((string)$opt)); ?>

              <option value="<?php echo esc_attr($opt); ?>" <?php selected($status, $opt); ?>>
                <?php echo esc_html(ucwords(strtolower($opt))); ?>
              </option>

            <?php endforeach; ?>
          </select>
        </div>

        <div style="grid-column:span 3;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Reg From</label>
          <input type="text" name="reg_from" value="<?php echo esc_attr($reg_from); ?>" placeholder="mm/dd/yyyy or yyyy-mm-dd"
                 style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
        </div>

        <div style="grid-column:span 3;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Reg To</label>
          <input type="text" name="reg_to" value="<?php echo esc_attr($reg_to); ?>" placeholder="mm/dd/yyyy or yyyy-mm-dd"
                 style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
        </div>

        <div style="grid-column:span 3;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Month From</label>
          <select name="month_from" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
            <option value="">Any</option>
            <?php foreach ($months as $num=>$label): ?>
              <option value="<?php echo (int)$num; ?>" <?php selected((string)$month_from, (string)$num); ?>>
                <?php echo esc_html($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="grid-column:span 3;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Month To</label>
          <select name="month_to" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
            <option value="">Any</option>
            <?php foreach ($months as $num=>$label): ?>
              <option value="<?php echo (int)$num; ?>" <?php selected((string)$month_to, (string)$num); ?>>
                <?php echo esc_html($label); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="grid-column:span 2;">
          <label style="display:block;font-weight:600;margin-bottom:4px;">Year</label>
          <input type="text" name="year" value="<?php echo esc_attr($year); ?>" placeholder="2025"
                 style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:10px;">
        </div>

        <div style="grid-column:span 2;">
          <label style="display:flex;gap:8px;align-items:center;font-weight:600;margin-bottom:4px;">
            <input type="checkbox" name="new_only" value="1" <?php checked(!empty($_GET['new_only'])); ?>>
            New Members (first year)
          </label>
        </div>

        <div style="grid-column:span 4;display:flex;gap:10px;justify-content:flex-end;">
          <button type="submit" class="button button-primary">Apply</button>
          <a class="button" href="<?php echo esc_url($clear_url); ?>">Clear</a>
          <a class="button" href="<?php echo esc_url($export_url); ?>">Export CSV</a>
        </div>
      </div>
    </form>

    <p style="margin:8px 0 14px;color:#6b7280;">Tip: click the member name to open the edit screen.</p>

    <?php if (empty($rows)): ?>
      <p>No members found.</p>
    <?php else: ?>
      <div style="overflow:auto;">
        <table class="widefat striped" style="min-width:1100px;">
          <thead>
            <tr>
              <th>Member #</th>
              <th>Username</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Registered</th>
              <th>Expires</th>
              <th>Level</th>
              <th>COAI #</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $id  = (int)($r['member_id'] ?? 0);
              $mn  = (string)($r['member_number'] ?? '');
              $un  = (string)($r['username'] ?? '');
              $ln  = (string)($r['last_name'] ?? '');
              $fn  = $first_col !== '' ? (string)($r[$first_col] ?? '') : '';
              $em  = (string)($r['email'] ?? '');
              $ph  = (string)($r['phone'] ?? $r['mobile'] ?? '');
              $rd  = (string)($r['registered_date'] ?? '');
              $ex  = (string)($r['membership_expiration'] ?? '');
              $lid = (string)($r['membership_level_id'] ?? '');
              $co  = (string)($r['COAI_number'] ?? '');
              $st  = (string)($r['status'] ?? '');

              $name = trim($fn . ' ' . $ln);
              if ($name === '') $name = $ln !== '' ? $ln : ((string)($r['full_name'] ?? '(no name)'));

              $edit_url = add_query_arg(['member_id' => $id], home_url('/member-directory/'));
              ?>
              <tr>
                <td><?php echo esc_html($mn); ?></td>
                <td><?php echo esc_html($un); ?></td>
                <td>
                  <?php if ($id): ?>
                    <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($name); ?></a>
                  <?php else: ?>
                    <?php echo esc_html($name); ?>
                  <?php endif; ?>
                </td>
                <td><?php echo esc_html($em); ?></td>
                <td><?php echo esc_html($ph); ?></td>
                <td><?php echo esc_html($rd); ?></td>
                <td><?php echo esc_html($ex); ?></td>
                <td><?php echo esc_html($lid); ?></td>
                <td><?php echo esc_html($co); ?></td>
                <td><?php echo esc_html($st); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
});
