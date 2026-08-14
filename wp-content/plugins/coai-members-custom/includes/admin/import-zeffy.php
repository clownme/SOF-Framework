<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin page: Import Members from Zeffy CSV
 *
 * - Upload a Zeffy CSV (export → save-as CSV)
 * - Auto-map common Zeffy headers -> wp_members columns
 * - Insert new or update existing (by email or member_number)
 * - Renewals: extend membership_expiration by +1 year
 *
 * Requires tables:
 *   wp_members (your main)
 *   wp_membership_levels (for mapping names -> membership_level_id), optional
 */

global $wpdb;

if (!defined('COAI_MEMBERS_TABLE')) {
  define('COAI_MEMBERS_TABLE', $wpdb->prefix . 'members');
}
if (!defined('COAI_LEVELS_TABLE'))  {
  define('COAI_LEVELS_TABLE',  $wpdb->prefix . 'membership_levels');
}


/** Add menu item under Users */
add_action('admin_menu', function () {
  add_users_page(
    'Import from Zeffy',
    'Import from Zeffy',
    'manage_options',
    'coai-import-zeffy',
    'coai_render_import_zeffy_page'
  );
});

/** Page UI */
function coai_render_import_zeffy_page() {
  if (!current_user_can('manage_options')) wp_die('Nope.');
  ?>
  <div class="wrap">
    <h1>Import from Zeffy</h1>
    <p>Upload the Zeffy export (CSV). The importer will map columns, normalize, and upsert into <code><?php echo esc_html(COAI_MEMBERS_TABLE); ?></code>.</p>

    <?php if (!empty($_GET['coai_msg'])): ?>
      <div class="notice notice-success"><p><?php echo esc_html($_GET['coai_msg']); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['coai_err'])): ?>
      <div class="notice notice-error"><p><?php echo esc_html($_GET['coai_err']); ?></p></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('coai_import_zeffy', '_coai_nonce'); ?>
      <input type="hidden" name="action" value="coai_import_zeffy_run">
      <table class="form-table" role="presentation">
        <tr>
          <th>CSV File</th>
          <td><input type="file" name="zeffy_csv" accept=".csv" required></td>
        </tr>
        <tr>
          <th>Default Membership Level</th>
          <td>
            <input type="text" name="default_level" placeholder="e.g. Individual" class="regular-text">
            <p class="description">Used if level can’t be mapped from the CSV.</p>
          </td>
        </tr>
        <tr>
          <th>Renewal Length</th>
          <td>
            <input type="number" name="renew_years" value="1" min="1" max="5" style="width:80px;"> year(s)
          </td>
        </tr>
        <tr>
          <th>Match Strategy</th>
          <td>
            <label><input type="checkbox" name="match_by_email" value="1" checked> Match by email</label><br>
            <label><input type="checkbox" name="match_by_member_number" value="1"> Match by member_number</label>
          </td>
        </tr>
        <tr>
          <th>Dry Run</th>
          <td><label><input type="checkbox" name="dry_run" value="1"> Parse and show a summary, but don’t write to DB</label></td>
        </tr>
      </table>
      <p><button type="submit" class="button button-primary">Run Import</button></p>
    </form>
  </div>
  <?php
}

/** Handle POST */
add_action('admin_post_coai_import_zeffy_run', function () {
  if (!current_user_can('manage_options')) wp_die('Nope.');
  if (empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_import_zeffy')) {
    wp_safe_redirect(add_query_arg('coai_err', 'Bad nonce', wp_get_referer())); exit;
  }
  if (empty($_FILES['zeffy_csv']['tmp_name']) || !is_uploaded_file($_FILES['zeffy_csv']['tmp_name'])) {
    wp_safe_redirect(add_query_arg('coai_err', 'Missing CSV', wp_get_referer())); exit;
  }

  $match_by_email          = !empty($_POST['match_by_email']);
  $match_by_member_number  = !empty($_POST['match_by_member_number']);
  $renew_years             = max(1, (int)($_POST['renew_years'] ?? 1));
  $default_level_label     = trim((string)($_POST['default_level'] ?? ''));
  $dry_run                 = !empty($_POST['dry_run']);

  global $wpdb;
  $members_table = COAI_MEMBERS_TABLE;
  $levels_table  = COAI_LEVELS_TABLE;

  // Map level label -> id
  $level_cache = [];
  if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $levels_table))) {
    $rows = $wpdb->get_results("SELECT membership_level_id, membership_level FROM `$levels_table`", ARRAY_A);
    foreach ($rows as $r) {
      $level_cache[ strtoupper(trim((string)$r['membership_level'])) ] = (int)$r['membership_level_id'];
    }
  }
  $resolve_level_id = function($label) use ($level_cache, $default_level_label) {
    $lab = strtoupper(trim((string)$label));
    if ($lab && isset($level_cache[$lab])) return $level_cache[$lab];
    if ($default_level_label) {
      $dl = strtoupper($default_level_label);
      return $level_cache[$dl] ?? null;
    }
    return null;
  };

  // Auto-map Zeffy headers to wp_members columns
  $map = [
    // zeffy header (case-insens, stripped) => wp_members column
    'member number'   => 'member_number',
    'membernumber'    => 'member_number',
    'coai number'     => 'COAI_number',
    'first name'      => 'first_name',
    'firstname'       => 'first_name',
    'last name'       => 'last_name',
    'lastname'        => 'last_name',
    'full name'       => 'full_name',
    'email'           => 'email',
    'phone'           => 'phone',
    'address'         => 'address',
    'city'            => 'city',
    'state'           => 'state',
    'province'        => 'state',
    'zip'             => 'zip',
    'postal code'     => 'zip',
    'country'         => 'country',
    'clown name'      => 'username', // or separate column if you have one
    'membership level'=> 'membership_level', // will map to id
    'status'          => 'status',
    'payment amount'  => 'payment_amount',
    'check number'    => 'check_number',
    'manual payment date' => 'manual_payment_date',
    
    // Zeffy Payments export (Renewals + New Members) ---
    'payment date (america/new_york)' => 'registered_date',
    'total amount'                    => 'payment_amount',
    'payment method'                  => 'payment_mode',
    'expiration date'                 => 'membership_expiration',
    
    // Common extra fields in New Members export
    'mobile'                          => 'phone',
    'address2'                        => 'address2',
    'billing address'                 => 'billing_address',
    
    // underscore style headers from Zeffy
    'coai_number'                     => 'COAI_number',
    'alley_membership'                => 'alley_membership',
    'clown name'                      => 'clown_name',
    
    'birthday'                        => 'birthday',
    'parent name'                     => 'parent_name',
    'e contact'                       => 'e_contact',
    
    // Shipping fields
    'shipping address'                => 'shipping_address',
    'shipping city'                   => 'shipping_city',
    'shipping state'                  => 'shipping_state',
    'shipping zip'                    => 'shipping_zip',
    'shipping country'                => 'shipping_country',
    
    // Notes/detail -> internal comments (safe catch-all)
    'detail'                          => 'internal_comments',
    'note'                            => 'internal_comments',

    'transaction date'=> 'registered_date', // fallback
    'registered date' => 'registered_date',
    'renewal'         => 'renew', // Y/N or 1/0; custom
    'expiration'      => 'membership_expiration',
  ];

  // Helpers
  $norm = function($s){
    $s = (string)$s;
    // Strip UTF-8 BOM if present
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
    $s = strtolower($s);

    // Convert underscores, hyphens, parentheses, slashes, etc. into spaces
    $s = preg_replace('~[^a-z0-9]+~', ' ', $s);

    // Collapse whitespace
    $s = trim(preg_replace('~\s+~', ' ', $s));
    return $s;
  };

  $parse_date = function($v){
    $v = trim((string)$v);
    if ($v === '') return null;
    // try common formats m/d/Y, d/m/Y, Y-m-d … fallback
    $ts = strtotime($v);
    if ($ts) return date('Y-m-d H:i:s', $ts);
    return null;
  };

  $new = 0; $updated = 0; $skipped = 0; $rows_seen = 0; $failed = 0;

  // Helper: determine "new member" from COAI_number baseline (202601-001+)
  $coai_is_new_from_number = function($coai_num): int {
    $coai_num = trim((string)$coai_num);
    if ($coai_num === '') return 0;
    $norm = (int) str_replace('-', '', $coai_num); // 202601-001 -> 202601001
    return ($norm >= 202601001) ? 1 : 0;
  };

  if (($fp = fopen($_FILES['zeffy_csv']['tmp_name'], 'r')) === false) {
    wp_safe_redirect(add_query_arg('coai_err', 'Unable to open CSV', wp_get_referer())); exit;
  }

  // Detect delimiter (comma vs semicolon) using first line
  $pos = ftell($fp);
  $firstLine = fgets($fp);
  fseek($fp, $pos);

  $delimiter = ','; //default
  if ($firstLine !== false) {
    $tab   = substr_count($firstLine, "\t");
    $comma = substr_count($firstLine, ',');
    $semi  = substr_count($firstLine, ';');
    if ($semi > $comma) $delimiter = ';';
    
    if ($tab >= $comma && $tab >= $semi && $tab > 0) $delimiter = "\t";
    else if ($semi > $comma) $delimiter = ";";
    else $delimiter = ",";
  }

  // header
  $header = fgetcsv($fp, 0, $delimiter);
  if (!$header) {
    fclose($fp);
    wp_safe_redirect(add_query_arg('coai_err', 'Empty CSV', wp_get_referer())); exit;
  }

  // build header index -> wp_members column name
  $hmap = [];
  foreach ($header as $i => $h) {
    $key = $norm($h);
    if (isset($map[$key])) $hmap[$i] = $map[$key];
  }

  error_log('[COAI ZEFFY] delimiter=' . ($delimiter === "\t" ? 'TAB' : $delimiter));
  error_log('[COAI ZEFFY] header=' . implode(' | ', $header));
  error_log('[COAI ZEFFY] mapped_cols=' . implode(',', array_unique(array_values($hmap))));

  // pre-fetch columns for safe writes
  $cols = array_map('strtolower', (array)$wpdb->get_col("DESC `$members_table`", 0));

  // read rows
  while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
    $rows_seen++;
    $rec = [];
    foreach ($hmap as $i => $col) {
      $rec[$col] = $row[$i] ?? '';
    }

    // Normalize
    $email = strtolower(trim((string)($rec['email'] ?? '')));
    $member_number = trim((string)($rec['member_number'] ?? ''));
    $coai_number = trim((string)($rec['coai_number'] ?? $rec['COAI_number'] ?? ''));
    if ($email === '' && $member_number === '' && $coai_number === '') { $skipped++; continue; }

    $first = trim((string)($rec['first_name'] ?? ''));
    $last  = trim((string)($rec['last_name'] ?? ''));
    $full  = trim((string)($rec['full_name'] ?? ''));
    if ($full === '' && ($first || $last)) $full = trim("$first $last");

    $status = strtoupper(trim((string)($rec['status'] ?? 'ACTIVE')));
    if (!in_array($status, ['ACTIVE','EXPIRED','PENDING'], true)) $status = 'ACTIVE';

    // map level → id
    $level_label = $rec['membership_level'] ?? '';
    $level_id = $resolve_level_id($level_label);

    // dates
    $registered_date = $parse_date($rec['registered_date'] ?? '') ?: current_time('mysql');
    $expiration_in  = $parse_date($rec['membership_expiration'] ?? '') ?: null;
    $manual_paid_at = $parse_date($rec['manual_payment_date'] ?? '') ?: null;

    // match existing member
    $where_sql = [];
    $where_vals = [];
    if ($match_by_email && $email) { $where_sql[] = 'LOWER(email)=LOWER(%s)'; $where_vals[] = $email; }
    if ($match_by_member_number && $member_number) { $where_sql[] = 'member_number=%s'; $where_vals[] = $member_number; }

    $existing = null;
    if ($where_sql) {
      $sql = "SELECT * FROM `$members_table` WHERE " . implode(' OR ', $where_sql) . " LIMIT 1";
      $existing = $wpdb->get_row($wpdb->prepare($sql, $where_vals), ARRAY_A);
    }

    // compute new expiration if renewing
    $is_renew = false;
    $renew_flag = strtoupper(trim((string)($rec['renew'] ?? '')));
    if (in_array($renew_flag, ['1','Y','YES','TRUE','RENEW'], true)) $is_renew = true;

    $new_exp = null;
    if ($is_renew) {
      $base = null;
      if ($existing && !empty($existing['membership_expiration'])) {
        $base = strtotime((string)$existing['membership_expiration']);
        if ($base && $base < time()) $base = time(); // expired → extend from now
      } else {
        $base = time();
      }
      $new_exp = date('Y-m-d H:i:s', strtotime("+{$renew_years} year", $base));
    } else if ($expiration_in) {
      $new_exp = $expiration_in;
    }

    // prepare data
    $data = [];
    $set  = function($col, $val) use (&$data, $cols) {
      if (in_array($col, $cols, true) && $val !== null) $data[$col] = $val;
    };

    $set('email', $email ?: null);
    $set('member_number', $member_number ?: null);
    // COAI_number: only set on insert, or when existing record has it blank
    $existing_coai = '';
    if ($existing) {
      $existing_coai = trim((string)($existing['COAI_number'] ?? $existing['coai_number'] ?? ''));
    }
    if (!$existing || $existing_coai === '') {
      $set('coai_number', $coai_number ?: null);
    }
    
    // is_new_member is driven ONLY by COAI_number baseline (no dates)
    $final_coai_for_flag = $existing ? ($existing_coai ?: $coai_number) : $coai_number;
    if ($final_coai_for_flag !== '') {
      $set('is_new_member', $coai_is_new_from_number($final_coai_for_flag));
    }

    $set('first_name', $first ?: null);
    $set('last_name',  $last ?: null);
    $set('full_name',  $full ?: null);
    $set('username',   ($rec['username'] ?? '') ?: null);
    $set('clown_name', ($rec['clown_name'] ?? '') ?: null);
    $set('phone',      ($rec['phone'] ?? '') ?: null);
    $set('address2', ($rec['address2'] ?? '') ?: null);
    $set('billing_address', ($rec['billing_address'] ?? '') ?: null);
    $set('status',     $status);
    if ($level_id) $set('membership_level_id', (int)$level_id);
    if ($registered_date) $set('registered_date', $registered_date);
    if ($manual_paid_at)  $set('manual_payment_date', $manual_paid_at);
    if ($new_exp)         $set('membership_expiration', $new_exp);

    // upsert
    if ($dry_run) { // just count
      if ($existing) $updated++; else $new++;
      continue;
    }

    if ($existing) {
      $r = $wpdb->update($members_table, $data, ['member_id' => (int)$existing['member_id']]);
      if ($r === false) {
        $failed++;
        error_log('[COAI ZEFFY] UPDATE FAIL: ' . $wpdb->last_error);
        error_log('[COAI ZEFFY] LAST QUERY: ' . $wpdb->last_query);
      } else {
        $updated++;
      }

    } else {
      // ensure created_at if column exists
      if (in_array('created_at', $cols, true) && empty($data['created_at'])) {
        $data['created_at'] = current_time('mysql');
      }
      $r = $wpdb->insert($members_table, $data);
     if ($r === false) {
        $failed++;
        error_log('[COAI ZEFFY] INSERT FAIL: ' . $wpdb->last_error);
        error_log('[COAI ZEFFY] LAST QUERY: ' . $wpdb->last_query);
      } else {
        $new++;
      }

      $new_member_id = (int) $wpdb->insert_id;

      // If COAI_number not provided in CSV, auto-assign it (new inserts only)
      if ($new_member_id > 0 && $coai_number === '' && function_exists('coai_assign_coai_number_if_missing')) {
        $assigned = (string) coai_assign_coai_number_if_missing($new_member_id);
        $assigned = trim($assigned);

        if ($assigned !== '') {
          // Set is_new_member from FINAL COAI_number
          $wpdb->update(
            $members_table,
            ['is_new_member' => (int) $coai_is_new_from_number($assigned)],
            ['member_id' => $new_member_id],
            ['%d'],
            ['%d']
          );
        }
      }

    }
  }
  fclose($fp);

$msg = sprintf(
  'Processed %d rows: %d new, %d updated, %d skipped, %d failed%s.',
  $rows_seen, $new, $updated, $skipped, $failed,
  $dry_run ? ' (dry run)' : ''
);

  wp_safe_redirect(add_query_arg('coai_msg', rawurlencode($msg), menu_page_url('coai-import-zeffy', false)));
  exit;
});
