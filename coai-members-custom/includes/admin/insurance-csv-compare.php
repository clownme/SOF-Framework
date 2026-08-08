<?php
/**
 * File: insurance-csv-compare.php
 * Purpose: Upload insurance CSV -> preview diffs -> apply selected changes -> download unmatched rows
 *
 * Rules:
 * - Primary match: COAI_number (strict)
 * - Fallback match: email (case-insensitive) if COAI not found / blank
 * - Never overwrite an existing member COAI_number; only fill it if blank
 * - Append an audit note into internal_comments when changes are applied
 * - Allow download of unmatched rows as CSV (per-user, from last preview)
 */

if (!defined('ABSPATH')) exit;

error_log('[COAI] insurance-csv-compare.php LOADED v2026-05-13-ALLOW-LEGACY-COAI');

if (!function_exists('coai_get_members_table')) {
  function coai_get_members_table() {
    if (function_exists('coai_members_table_name')) return coai_members_table_name();
    if (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) return COAI_MEMBERS_TABLE;

    if (function_exists('coai_tables')) {
      $t = coai_tables();
      if (!empty($t['members'])) return $t['members'];
    }

    global $wpdb;
    return $wpdb->prefix . 'members';
  }
}

if (!function_exists('coai_get_coai_column_name')) {
  function coai_get_coai_column_name($table) {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];

    global $wpdb;

    $cols = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
    $cols = array_map('strval', (array)$cols);

    $hasUpper = in_array('COAI_number', $cols, true);
    $hasLower = in_array('coai_number', $cols, true);

    if ($hasUpper && !$hasLower) return $cache[$table] = 'COAI_number';
    if ($hasLower && !$hasUpper) return $cache[$table] = 'coai_number';

    if ($hasUpper && $hasLower) {
      $upperCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE `COAI_number` IS NOT NULL AND TRIM(`COAI_number`) <> ''");
      $lowerCount = (int)$wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE `coai_number` IS NOT NULL AND TRIM(`coai_number`) <> ''");
      $pick = ($lowerCount > $upperCount) ? 'coai_number' : 'COAI_number';
      error_log('[COAI] insurance-csv BOTH COAI cols exist; pick='.$pick.' upperCount='.$upperCount.' lowerCount='.$lowerCount);
      return $cache[$table] = $pick;
    }

    return $cache[$table] = 'COAI_number';
  }
}

if (!function_exists('coai_md_norm_date_ymd')) {
  function coai_md_norm_date_ymd($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    $ts = strtotime($raw);
    if (!$ts || $ts <= 0) return '';
    return date('Y-m-d', $ts);
  }
}

if (!function_exists('coai_md_add_1_year_ymd')) {
  function coai_md_add_1_year_ymd($ymd) {
    $ymd = trim((string)$ymd);
    if ($ymd === '') return '';

    $ts = strtotime($ymd);
    if (!$ts || $ts <= 0) return '';

    // Add exactly 1 year (handles leap years safely)
    return date('Y-m-d', strtotime('+1 year', $ts));
  }
}


if (!function_exists('coai_md_append_internal_comment')) {
  function coai_md_append_internal_comment($old_comments, $line) {
    $old_comments = (string)$old_comments;
    $line = trim((string)$line);
    if ($line === '') return $old_comments;

    $stamp = function_exists('current_time') ? current_time('Y-m-d H:i') : date('Y-m-d H:i');
    $entry = '[' . $stamp . '] ' . $line;

    if ($old_comments === '') return $entry;
    if (!preg_match("/\n$/", $old_comments)) $old_comments .= "\n";
    return $old_comments . $entry;
  }
}

/**
 * CSV column mapping (labels shown in preview)
 * NOTE: contact_name is NOT a db field; it's display-only.
 */
$INSURANCE_FIELDS = [
  'insurance_status'          => ['label' => 'Insurance Status'],
  'insurance_effective_date'  => ['label' => 'Policy Eff Date'],
  'insurance_expiration_date' => ['label' => 'Policy Exp Date'],
  // 'contact_name' is display-only (not applied)
];

/**
 * Unmatched transient key helper (per admin user).
 */
if (!function_exists('coai_ins_unmatched_token')) {
  function coai_ins_unmatched_token() {
    return 'coai_ins_unmatched_' . get_current_user_id();
  }
}

/**
 * Download handler for unmatched rows CSV (nonce protected).
 */
if (!function_exists('coai_insurance_csv_handle_download_unmatched')) {
  function coai_insurance_csv_handle_download_unmatched() {

    if (!(current_user_can('manage_options') || (function_exists('coai_staff_can') && coai_staff_can('manage')))) {
      wp_die('Unauthorized', '', 403);
    }

    $nonce = $_GET['_wpnonce'] ?? '';
    if (!$nonce || !wp_verify_nonce($nonce, 'coai_ins_csv_unmatched_download')) {
      wp_die('Bad nonce', '', 400);
    }

    $errors = get_transient(coai_ins_unmatched_token());
    if (!is_array($errors)) $errors = [];

    nocache_headers();
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=insurance-unmatched-compare-" . date('Ymd-His') . ".csv");

    $out = fopen('php://output', 'w');

    fputcsv($out, [
      'csv_email',
      'csv_contact_name',
      'csv_coai_number',
      'csv_insurance_status',
      'csv_policy_eff_date',
      'csv_policy_exp_date',

      'candidate_match_type',
      'db_member_id',
      'db_full_name',
      'db_email',
      'db_COAI_number',
      'db_insurance_status',
      'db_policy_eff_date',
      'db_policy_exp_date',

      'reason'
    ]);

    foreach ($errors as $e) {
      if (!is_array($e)) continue;

      fputcsv($out, [
        (string)($e['email'] ?? ''),
        (string)($e['contact_name'] ?? ''),
        (string)($e['coai'] ?? ''),

        (string)($e['csv_insurance_status'] ?? ''),
        (string)($e['csv_policy_eff_date'] ?? ''),
        (string)($e['csv_policy_exp_date'] ?? ''),

        (string)($e['candidate_match_type'] ?? ''),
        (string)($e['db_member_id'] ?? ''),
        (string)($e['db_full_name'] ?? ''),
        (string)($e['db_email'] ?? ''),
        (string)($e['db_coai_number'] ?? ''),
        (string)($e['db_insurance_status'] ?? ''),
        (string)($e['db_policy_eff_date'] ?? ''),
        (string)($e['db_policy_exp_date'] ?? ''),

        (string)($e['msg'] ?? ''),
      ]);
    }

    fclose($out);
    exit;
  }
}

add_action('admin_post_coai_ins_download_unmatched', 'coai_insurance_csv_handle_download_unmatched');

/**
 * Admin page renderer called from your admin_menu hook.
 */
if (!function_exists('coai_render_insurance_csv_compare_page')) {
  function coai_render_insurance_csv_compare_page() {
    if (!(current_user_can('manage_options') || (function_exists('coai_staff_can') && coai_staff_can('manage')))) {
      echo '<div class="wrap"><h1>Insurance CSV Compare</h1><div class="notice notice-error"><p>Access denied.</p></div></div>';
      return;
    }


    global $INSURANCE_FIELDS;

    $preview = null;

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if (empty($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'coai_ins_csv_compare')) {
          error_log('[COAI INS PAGE] POST received action=' . ($_POST['coai_ins_csv_action'] ?? ''));
          error_log('[COAI INS PAGE] dry_run_apply=' . (!empty($_POST['dry_run_apply']) ? '1' : '0') . ' apply_count=' . (is_array($_POST['apply'] ?? null) ? count($_POST['apply']) : 0));

        echo '<div class="wrap"><h1>Insurance CSV Compare</h1><div class="notice notice-error"><p>Bad nonce.</p></div></div>';
        return;
      }

      $action = sanitize_text_field($_POST['coai_ins_csv_action'] ?? '');

      if ($action === 'upload_parse') {
        $dry = !empty($_POST['dry_run']);
        $preview = coai_insurance_csv_build_preview($dry);
      }

      if ($action === 'apply_selected') {
        $dry = !empty($_POST['dry_run_apply']);
        $preview = coai_insurance_csv_apply_selected($dry);
      }
    }

    echo '<div class="wrap">';
    echo '<h1>Insurance CSV Compare</h1>';

    // Upload form
    echo '<h2>1) Upload & Preview</h2>';
    echo '<form method="post" enctype="multipart/form-data" style="background:#fff; padding:12px; border:1px solid #dcdcde; border-radius:8px;">';
    wp_nonce_field('coai_ins_csv_compare');
    echo '<input type="hidden" name="coai_ins_csv_action" value="upload_parse" />';

    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="insurance_csv">Insurance CSV</label></th>';
    echo '<td><input type="file" name="insurance_csv" id="insurance_csv" accept=".csv,text/csv" required /></td></tr>';

    echo '<tr><th scope="row">Dry-run</th><td>';
    echo '<label><input type="checkbox" name="dry_run" value="1" /> Parse only (no apply)</label>';
    echo '</td></tr>';

    echo '</table>';
    echo '<p><button class="button button-primary">Upload & Build Preview</button></p>';
    echo '</form>';

    // Preview output if available
    if ($preview) {

      $meta   = is_array($preview['meta'] ?? null) ? $preview['meta'] : [];
      $diffs  = is_array($preview['diffs'] ?? null) ? $preview['diffs'] : [];
      $errors = is_array($preview['errors'] ?? null) ? $preview['errors'] : [];

      // ---------- FALLBACK: load from transients if arrays came back empty ----------
      $uid = get_current_user_id();

      if (empty($diffs) && !empty($meta['diff_count'])) {
        $saved = get_transient('coai_ins_preview_' . $uid);
        if (is_array($saved) && !empty($saved['diffs']) && is_array($saved['diffs'])) {
          $diffs = $saved['diffs'];
        }
      }

      if (empty($errors) && !empty($meta['error_count'])) {
        $saved_errors = get_transient(coai_ins_unmatched_token());
        if (is_array($saved_errors)) {
          $errors = $saved_errors;
        }
      }

      // Optional: one-line debug (remove later)
      echo '<p style="color:#64748b;margin:6px 0 0;">';
      echo 'Debug: diffs_array=' . (is_array($diffs) ? 'yes' : 'no') . ' diffs=' . count($diffs);
      echo ' | errors_array=' . (is_array($errors) ? 'yes' : 'no') . ' errors=' . count($errors);
      echo '</p>';


      // Download unmatched button
      if (!empty($errors)) {
        $dl_url = add_query_arg([
          'action'   => 'coai_ins_download_unmatched',
          '_wpnonce' => wp_create_nonce('coai_ins_csv_unmatched_download'),
        ], admin_url('admin-post.php'));

        echo '<p style="margin:10px 0 14px;">';
        echo '<a class="button" href="' . esc_url($dl_url) . '">Download unmatched rows as CSV</a>';
        echo '</p>';
      }

      // Error report
      if (!empty($errors)) {
        echo '<details style="margin:12px 0;"><summary><strong>CSV Validation & Error Report</strong> (click to expand)</summary>';
        echo '<div style="margin-top:10px;">';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Row #</th><th>COAI #</th><th>Email</th><th>Contact Name</th><th>Error</th></tr></thead><tbody>';

        foreach ($errors as $e) {
          if (!is_array($e)) continue;
          echo '<tr>';
          echo '<td>' . esc_html((string)($e['row'] ?? '')) . '</td>';
          echo '<td><code>' . esc_html((string)($e['coai'] ?? '')) . '</code></td>';
          echo '<td>' . esc_html((string)($e['email'] ?? '')) . '</td>';
          echo '<td>' . esc_html((string)($e['contact_name'] ?? '')) . '</td>';
          echo '<td>' . esc_html((string)($e['msg'] ?? '')) . '</td>';
          echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div></details>';
      }

      // Diff table
      if (empty($diffs)) {
        echo '<p><em>No diffs found.</em></p>';
      } else {
        echo '<form method="post" style="margin-top:12px;">';
        wp_nonce_field('coai_ins_csv_compare');
        echo '<input type="hidden" name="coai_ins_csv_action" value="apply_selected" />';

        echo '<p style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">';
        echo '<button type="button" class="button" onclick="coaiInsSelectAll(true)">Select all</button>';
        echo '<button type="button" class="button" onclick="coaiInsSelectAll(false)">Select none</button>';
        echo '<label style="margin-left:auto;"><input type="checkbox" name="dry_run_apply" value="1" /> Dry-run apply (no DB writes)</label>';
        echo '</p>';

        echo '<table class="widefat striped" style="margin-top:8px;">';
        echo '<thead><tr>';
        echo '<th style="width:70px;">Apply</th>';
        echo '<th>Match</th>';
        echo '<th>COAI #</th>';
        echo '<th>Email</th>';
        echo '<th>Contact Name</th>';
        echo '<th>Member</th>';
        echo '<th>Field</th>';
        echo '<th>Old Value</th>';
        echo '<th>New Value</th>';
        echo '</tr></thead><tbody>';

        foreach ($diffs as $key => $d) {
          if (!is_array($d)) continue;

          $coai    = (string)($d['COAI_number'] ?? '');
          $email   = (string)($d['email'] ?? '');
          $contact = (string)($d['contact_name'] ?? '');
          $name    = (string)($d['member_name'] ?? '');
          $field   = (string)($d['field'] ?? '');
          $old     = (string)($d['old'] ?? '');
          $new     = (string)($d['new'] ?? '');
          $match   = (string)($d['match'] ?? '');

          $field_label = $INSURANCE_FIELDS[$field]['label'] ?? $field;

          echo '<tr>';
          echo '<td><input class="coai-ins-apply" type="checkbox" name="apply[]" value="' . esc_attr((string)$key) . '" checked /></td>';
          echo '<td>' . esc_html($match) . '</td>';
          echo '<td><code>' . esc_html($coai) . '</code></td>';
          echo '<td>' . esc_html($email) . '</td>';
          echo '<td>' . esc_html($contact) . '</td>';
          echo '<td>' . esc_html($name) . '</td>';
          echo '<td><strong>' . esc_html($field_label) . '</strong><br><code>' . esc_html($field) . '</code></td>';
          echo '<td>' . esc_html($old) . '</td>';
          echo '<td>' . esc_html($new) . '</td>';
          echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p style="margin-top:12px;">';
        echo '<button class="button button-primary">Apply Selected Changes</button>';
        echo '</p>';
        echo '</form>';

        echo '<script>
          function coaiInsSelectAll(on) {
            var boxes = document.querySelectorAll(".coai-ins-apply");
            boxes.forEach(function(b){ b.checked = !!on; });
          }
        </script>';
      }

      echo '</div>';
    }

    echo '</div>';
  }
}

/**
 * Parse uploaded CSV -> build diffs/errors
 */
if (!function_exists('coai_insurance_csv_build_preview')) {
  function coai_insurance_csv_build_preview($dry_run = true) {
    global $wpdb;

    $table    = coai_get_members_table();
    $coai_col = coai_get_coai_column_name($table);

    $meta = [
      'rows_total'   => 0,
      'rows_matched' => 0,
      'diff_count'   => 0,
      'error_count'  => 0,
      'dry_run'      => $dry_run ? 1 : 0,
    ];

    $diffs  = [];
    $errors = [];

    delete_transient(coai_ins_unmatched_token());

    if (empty($_FILES['insurance_csv']) || empty($_FILES['insurance_csv']['tmp_name'])) {
      $errors[] = ['row'=>'','coai'=>'','email'=>'','contact_name'=>'','msg'=>'No file uploaded.'];
      set_transient(coai_ins_unmatched_token(), $errors, 30 * MINUTE_IN_SECONDS);
      $meta['error_count'] = count($errors);
      return ['meta'=>$meta,'diffs'=>[],'errors'=>$errors];
    }

    $tmp = $_FILES['insurance_csv']['tmp_name'];

    $fh = fopen($tmp, 'r');
    if (!$fh) {
      $errors[] = ['row'=>'','coai'=>'','email'=>'','contact_name'=>'','msg'=>'Could not read uploaded file.'];
      set_transient(coai_ins_unmatched_token(), $errors, 30 * MINUTE_IN_SECONDS);
      $meta['error_count'] = count($errors);
      return ['meta'=>$meta,'diffs'=>[],'errors'=>$errors];
    }

    $header = fgetcsv($fh);
    if (!$header) {
      fclose($fh);
      $errors[] = ['row'=>'','coai'=>'','email'=>'','contact_name'=>'','msg'=>'CSV appears empty.'];
      set_transient(coai_ins_unmatched_token(), $errors, 30 * MINUTE_IN_SECONDS);
      $meta['error_count'] = count($errors);
      return ['meta'=>$meta,'diffs'=>[],'errors'=>$errors];
    }

    $hmap = [];
    foreach ($header as $i => $col) {
      $key = strtolower(trim((string)$col));
      $hmap[$key] = $i;
    }

    // Accept header aliases
    $idx_coai  =
      $hmap['membership code'] ??
      $hmap['coai_number'] ??
      $hmap['coai #'] ??
      $hmap['coai'] ??
      null;

    $idx_email   = $hmap['email'] ?? $hmap['e-mail'] ?? null;
    $idx_contact = $hmap['contact name'] ?? $hmap['contact_name'] ?? $hmap['name'] ?? $hmap['contact'] ?? null;

    $idx_ins_status = $hmap['status'] ?? $hmap['insurance_status'] ?? $hmap['insurance status'] ?? $hmap['insurance'] ?? null;
    $idx_eff        = $hmap['policy eff date'] ?? $hmap['policy effective date'] ?? $hmap['insurance_effective_date'] ?? null;
    $idx_exp        = $hmap['policy exp date'] ?? $hmap['policy expiration date'] ?? $hmap['insurance_expiration_date'] ?? null; // likely not present in this CSV

    if ($idx_coai === null && $idx_email === null) {
      fclose($fh);
      $errors[] = ['row'=>'','coai'=>'','email'=>'','contact_name'=>'','msg'=>'CSV missing COAI_number and Email columns (need at least one).'];
      set_transient(coai_ins_unmatched_token(), $errors, 30 * MINUTE_IN_SECONDS);
      $meta['error_count'] = count($errors);
      return ['meta'=>$meta,'diffs'=>[],'errors'=>$errors];
    }

    $rownum = 1;
    while (($r = fgetcsv($fh)) !== false) {
      $rownum++;
      $meta['rows_total']++;

      $csv_coai    = ($idx_coai    !== null) ? trim((string)($r[$idx_coai] ?? '')) : '';
      $csv_email   = ($idx_email   !== null) ? strtolower(trim((string)($r[$idx_email] ?? ''))) : '';
      $csv_contact = ($idx_contact !== null) ? trim((string)($r[$idx_contact] ?? '')) : '';
      
      // Normalize Membership Code (COAI_number) — protect against Excel damage
      $csv_coai = strtoupper(trim((string)$csv_coai));

      // Fix Excel-truncated suffix: 201703-6 → 201703-006
      if (preg_match('/^(\d{6})-(\d{1,2})$/', $csv_coai, $m)) {
        $csv_coai = $m[1] . '-' . str_pad($m[2], 3, '0', STR_PAD_LEFT);
      }

      // Validate Membership Code format.
      // Allow:
      // - New COAI format: YYYYMM-NNN
      // - Legacy COAI format: 4 or 5 digit member numbers
      if ($csv_coai !== '' && !preg_match('/^(\d{6}-\d{3}|\d{4,5})$/', $csv_coai)) {
        $errors[] = [
          'row' => $rownum,
          'coai' => $csv_coai,
          'email' => $csv_email,
          'contact_name' => $csv_contact,
          'msg' => 'Invalid Membership Code format. Expected YYYYMM-NNN or legacy 4/5 digit COAI number.',
        ];
        continue;
      }

      $csv_ins = ($idx_ins_status !== null) ? trim((string)($r[$idx_ins_status] ?? '')) : '';
      $csv_eff = ($idx_eff !== null) ? coai_md_norm_date_ymd((string)($r[$idx_eff] ?? '')) : '';
      $csv_exp = ($idx_exp !== null) ? coai_md_norm_date_ymd((string)($r[$idx_exp] ?? '')) : '';

      // ✅ If Policy Exp Date isn't present in CSV, derive it from Effective Date (+1 year)
      if ($csv_exp === '' && $csv_eff !== '') {
        $csv_exp = coai_md_add_1_year_ymd($csv_eff);
      }

      $member = null;
      $match_type = '';

      if ($csv_coai !== '') {
        $member = $wpdb->get_row($wpdb->prepare(
          "SELECT * FROM `$table` WHERE `$coai_col`=%s LIMIT 1",
          $csv_coai
        ), ARRAY_A);
        if ($member) $match_type = 'COAI';
      }

      if (!$member && $csv_email !== '') {
        $member = $wpdb->get_row($wpdb->prepare(
          "SELECT * FROM `$table` WHERE LOWER(email)=%s LIMIT 1",
          $csv_email
        ), ARRAY_A);
        if ($member) $match_type = 'Email';
      }

      if (!$member) {

        // Best-effort candidate by name (for staff review)
        $cand = null;
        $contact_norm = strtolower(trim(preg_replace('/\s+/', ' ', (string)$csv_contact)));

        if ($contact_norm !== '') {

          // Exact match on full_name
          $cand = $wpdb->get_row($wpdb->prepare(
            "SELECT member_id, full_name, email, `$coai_col` AS coai_db,
                    insurance_status, insurance_effective_date, insurance_expiration_date
             FROM `$table`
             WHERE LOWER(TRIM(full_name)) = %s
             LIMIT 1",
            $contact_norm
          ), ARRAY_A);

          // Fallback: first/last split
          if (!$cand) {
            $parts = preg_split('/\s+/', trim((string)$csv_contact));
            if (is_array($parts) && count($parts) >= 2) {
              $first = strtolower(array_shift($parts));
              $last  = strtolower(implode(' ', $parts));

              $cand = $wpdb->get_row($wpdb->prepare(
                "SELECT member_id, full_name, email, `$coai_col` AS coai_db,
                        insurance_status, insurance_effective_date, insurance_expiration_date
                 FROM `$table`
                 WHERE LOWER(TRIM(first_name)) = %s
                   AND LOWER(TRIM(last_name)) = %s
                 LIMIT 1",
                $first, $last
              ), ARRAY_A);
            }
          }
        }

        $errors[] = [
          'row' => $rownum,
          'coai' => $csv_coai,
          'email' => $csv_email,
          'contact_name' => $csv_contact,

          // CSV-side compare
          'csv_insurance_status' => $csv_ins,
          'csv_policy_eff_date'  => $csv_eff,
          'csv_policy_exp_date'  => $csv_exp,

          // Candidate DB-side compare
          'candidate_match_type' => $cand ? 'NAME' : '',
          'db_member_id'         => $cand ? (string)($cand['member_id'] ?? '') : '',
          'db_full_name'         => $cand ? (string)($cand['full_name'] ?? '') : '',
          'db_email'             => $cand ? (string)($cand['email'] ?? '') : '',
          'db_coai_number'       => $cand ? (string)($cand['coai_db'] ?? '') : '',
          'db_insurance_status'  => $cand ? (string)($cand['insurance_status'] ?? '') : '',
          'db_policy_eff_date'   => $cand ? (string)($cand['insurance_effective_date'] ?? '') : '',
          'db_policy_exp_date'   => $cand ? (string)($cand['insurance_expiration_date'] ?? '') : '',

          'msg' => $cand
            ? 'No match by COAI or Email. Possible member found by name (manual review).'
            : 'No match by COAI or Email.',
        ];

        continue;
      }

      $meta['rows_matched']++;

      $mid = (int)($member['member_id'] ?? 0);
      $member_name = trim((string)($member['full_name'] ?? ''));
      if ($member_name === '') $member_name = trim((string)($member['first_name'] ?? '') . ' ' . (string)($member['last_name'] ?? ''));
      if ($member_name === '') $member_name = 'Member #' . $mid;

      $member_coai = trim((string)($member[$coai_col] ?? ''));

      // COAI_number fill only if blank
      if ($member_coai === '' && $csv_coai !== '') {
        $diffs[] = [
          'member_id'     => $mid,
          'member_name'   => $member_name,
          'match'         => $match_type,
          'COAI_number'   => $csv_coai,
          'email'         => $csv_email ?: strtolower(trim((string)($member['email'] ?? ''))),
          'contact_name'  => $csv_contact,
          'field'         => $coai_col,
          'old'           => '',
          'new'           => $csv_coai,
          'kind'          => 'coai_fill',
        ];
      }

      // Insurance diffs (ONLY real db fields)
      $pairs = [
        'insurance_status'          => $csv_ins,
        'insurance_effective_date'  => $csv_eff,
        'insurance_expiration_date' => $csv_exp,
      ];

      foreach ($pairs as $field => $newv) {
        if ($newv === '') continue;
        $oldv = trim((string)($member[$field] ?? ''));
        if ($oldv !== $newv) {
          $diffs[] = [
            'member_id'     => $mid,
            'member_name'   => $member_name,
            'match'         => $match_type,
            'COAI_number'   => ($csv_coai !== '' ? $csv_coai : $member_coai),
            'email'         => ($csv_email !== '' ? $csv_email : strtolower(trim((string)($member['email'] ?? '')))),
            'contact_name'  => $csv_contact,
            'field'         => $field,
            'old'           => $oldv,
            'new'           => $newv,
            'kind'          => 'insurance',
          ];
        }
      }
    }

    fclose($fh);

    $meta['diff_count']  = count($diffs);
    $meta['error_count'] = count($errors);

    // Save preview diffs to transient so Apply can use it
    $token = 'coai_ins_preview_' . get_current_user_id();
    set_transient($token, ['diffs'=>$diffs, 'meta'=>$meta], 30 * MINUTE_IN_SECONDS);

    // Save unmatched rows for download
    set_transient(coai_ins_unmatched_token(), $errors, 30 * MINUTE_IN_SECONDS);

    return ['meta'=>$meta,'diffs'=>$diffs,'errors'=>$errors];
  }
}

/**
 * Apply selected diffs from last preview
 */
if (!function_exists('coai_insurance_csv_apply_selected')) {
  function coai_insurance_csv_apply_selected($dry_run = true) {

    error_log('[COAI INS APPLY] apply_selected START dry_run=' . ($dry_run ? '1' : '0'));
    
    global $wpdb;

    $token = 'coai_ins_preview_' . get_current_user_id();
    $saved = get_transient($token);

    $diffs = is_array($saved['diffs'] ?? null) ? $saved['diffs'] : [];

    error_log('[COAI INS APPLY] token=' . $token . ' saved_type=' . gettype($saved));
    error_log('[COAI INS APPLY] saved_diffs_count=' . (is_array($saved['diffs'] ?? null) ? count($saved['diffs']) : -1));
    error_log('[COAI INS APPLY] diffs_count=' . count($diffs));


    $meta = [
      'rows_total'     => (int)($saved['meta']['rows_total'] ?? 0),
      'rows_matched'   => (int)($saved['meta']['rows_matched'] ?? 0),
      'diff_count'     => 0,
      'error_count'    => 0,
      'dry_run_apply'  => $dry_run ? 1 : 0,
      'applied'        => 0,
      'skipped'        => 0,
    ];

    $errors = [];

    $apply_keys = $_POST['apply'] ?? [];
    error_log('[COAI INS APPLY] apply_count=' . (is_array($apply_keys) ? count($apply_keys) : -1));

    if (!is_array($apply_keys) || empty($apply_keys)) {
      $errors[] = ['row'=>'','coai'=>'','email'=>'','contact_name'=>'','msg'=>'No diffs selected to apply.'];
      $meta['error_count'] = count($errors);
      return ['meta'=>$meta,'diffs'=>[],'errors'=>$errors];
    }

    $table    = coai_get_members_table();
    $coai_col = coai_get_coai_column_name($table);

    $actor = wp_get_current_user();
    $actor_login = $actor ? (string)$actor->user_login : 'unknown';

    foreach ($apply_keys as $k) {
      $k = (int)$k;
      if (!isset($diffs[$k]) || !is_array($diffs[$k])) {
        $meta['skipped']++;
        continue;
      }

      $d = $diffs[$k];
      $mid   = (int)($d['member_id'] ?? 0);
      $field = (string)($d['field'] ?? '');
      $newv  = (string)($d['new'] ?? '');

      $coai   = (string)($d['COAI_number'] ?? '');
      $email  = (string)($d['email'] ?? '');
      $cname  = (string)($d['contact_name'] ?? '');

      if ($mid <= 0 || $field === '') {
        $errors[] = ['row'=>'','coai'=>$coai,'email'=>$email,'contact_name'=>$cname,'msg'=>'Bad diff record (missing member_id/field).'];
        continue;
      }

      $member = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", $mid),
        ARRAY_A
      );

      if (!$member) {
        $errors[] = [
          'row'          => '',
          'coai'         => $coai,
          'email'        => $email,
          'contact_name' => $cname,
          'msg'          => 'Member not found for member_id=' . $mid . ' during apply (was present during preview).',
        ];
        $meta['skipped']++;
        continue;
      }

      $updates = [];
      $notes   = [];

      // COAI fill only if blank right now
      if ($field === $coai_col) {
        $cur = trim((string)($member[$coai_col] ?? ''));
        if ($cur !== '') {
          $meta['skipped']++;
          continue;
        }
        $updates[$coai_col] = sanitize_text_field($newv);
        $notes[] = 'COAI # filled from "" to "' . $newv . '" by ' . $actor_login . ' (Insurance CSV Apply)';
      }

      // Insurance fields
      if (in_array($field, ['insurance_status','insurance_effective_date','insurance_expiration_date'], true)) {
        $cur = trim((string)($member[$field] ?? ''));
        $updates[$field] = ($field === 'insurance_effective_date' || $field === 'insurance_expiration_date')
          ? coai_md_norm_date_ymd($newv)
          : sanitize_text_field($newv);

        $notes[] = $field . ' changed from "' . $cur . '" to "' . (string)$updates[$field] . '" by ' . $actor_login . ' (Insurance CSV Apply)';
      }

      // 4) Auto-fill expiration = effective + 1 year (only if expiration is blank)
      if ($field === 'insurance_effective_date') {
        $curExp = trim((string)($member['insurance_expiration_date'] ?? ''));
        $newEff = (string)($updates['insurance_effective_date'] ?? '');
        $calcExp = ($newEff !== '') ? coai_md_add_1_year_ymd($newEff) : '';

        if ($curExp === '' && $calcExp !== '') {
          $updates['insurance_expiration_date'] = $calcExp;
          $notes[] =
            'insurance_expiration_date auto-filled from "" to "' .
            $calcExp .
            '" (1 year from effective date) by ' .
            $actor_login .
            ' (Insurance CSV Apply)';
        }
      }

      if (empty($updates)) {
        $meta['skipped']++;
        continue;
      }

      // Append audit note(s) to internal_comments
      $base_comments = (string)($member['internal_comments'] ?? '');
      foreach ($notes as $line) {
        $base_comments = coai_md_append_internal_comment($base_comments, $line);
      }
      $updates['internal_comments'] = $base_comments;

      if ($dry_run) {
        $meta['applied']++;
        continue;
      }

      error_log('[COAI INS APPLY] updating member_id=' . $mid . ' field=' . $field . ' updates_keys=' . implode(',', array_keys($updates)));

      $ok = $wpdb->update($table, $updates, ['member_id' => $mid]);

      error_log('[COAI INS APPLY] update_result member_id=' . $mid . ' ok=' . var_export($ok, true) . ' last_error=' . $wpdb->last_error);

      if ($ok === false) {
        $errors[] = ['row'=>'','coai'=>$coai,'email'=>$email,'contact_name'=>$cname,'msg'=>'DB update failed for member_id='.$mid.': '.$wpdb->last_error];
      } else {
        $meta['applied']++;
      }
    }

    $meta['diff_count']  = count($apply_keys);
    $meta['error_count'] = count($errors);

    // Show only selected diffs in apply result view
    $show_diffs = [];
    foreach ($apply_keys as $k) {
      $k = (int)$k;
      if (isset($diffs[$k])) $show_diffs[$k] = $diffs[$k];
    }

    return ['meta'=>$meta,'diffs'=>$show_diffs,'errors'=>$errors];
  }
}
