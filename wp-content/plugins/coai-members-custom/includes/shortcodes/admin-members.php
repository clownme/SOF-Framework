<?php
/**
 * File: admin-members.php
 * Purpose: Member Directory + Admin/Manager/Finance lists + CSV export + Staff Edit view
 *
 * Key rule (Dec 2025):
 * - "New Members only" is driven ONLY by wp_members.is_new_member = 1
 * - It is NOT tied to created_at / registered_date / 90-day logic anymore
 *
 * Password rule (Feb 2026):
 * - Member Login password is stored ONLY in wp_members.password
 * - Admin/Manager can reset Member Login password here
 * - WordPress admin passwords are managed in WP only (NOT here)
 */

if (!defined('ABSPATH')) exit;

error_log('[COAI] admin-members.php LOADED v2026-02-02-SINGLE-PASSWORD-SOURCE');

// Google Drive modules are loaded by coai-members-custom.php

// ------------------------------------------------------------
// Fallback staff permissions (only if helper not loaded)
// ------------------------------------------------------------
if (!function_exists('coai_staff_can')) {
  function coai_staff_can($cap) {
    if (current_user_can('administrator')) return true;

    $u = wp_get_current_user();
    $roles = (array)($u->roles ?? []);

    if ($cap === 'manage') {
      return in_array('manager', $roles, true) || in_array('administrator', $roles, true);
    }
    if ($cap === 'view') {
      return in_array('manager', $roles, true)
        || in_array('finance', $roles, true)
        || in_array('administrator', $roles, true);
    }
    return false;
  }
  error_log('[COAI] WARNING: using fallback coai_staff_can() in admin-members.php');
}

// ------------------------------------------------------------
// Helpers: date normalize + internal comment append
// ------------------------------------------------------------
if (!function_exists('coai_md_norm_date_ymd')) {
  function coai_md_norm_date_ymd($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    $ts = strtotime($raw);
    if (!$ts || $ts <= 0) return '';
    return date('Y-m-d', $ts);
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

function coai_md_resolve_member_name($member_number_or_id) {
  global $wpdb;

  if (!$member_number_or_id) return '';

  $table = COAI_MEMBERS_TABLE;

  // Try member_number first, then member_id fallback
  $row = $wpdb->get_row($wpdb->prepare("
    SELECT full_name, first_name, last_name, member_number
    FROM {$table}
    WHERE member_number = %s OR member_id = %d
    LIMIT 1
  ", (string)$member_number_or_id, (int)$member_number_or_id), ARRAY_A);

  if (!$row) return (string)$member_number_or_id;

  if (!empty($row['full_name'])) return $row['full_name'];

  $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
  return $name !== '' ? $name : (string)$member_number_or_id;
}


// ------------------------------------------------------------
// Table resolvers
// ------------------------------------------------------------
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

if (!function_exists('coai_get_levels_table')) {
  function coai_get_levels_table() {
    if (function_exists('coai_levels_table_name')) return coai_levels_table_name();
    if (defined('COAI_LEVELS_TABLE') && COAI_LEVELS_TABLE) return COAI_LEVELS_TABLE;

    global $wpdb;
    return $wpdb->prefix . 'membership_levels';
  }
}

if (!function_exists('coai_get_levels_pk')) {
  function coai_get_levels_pk() {
    if (defined('COAI_LEVELS_PK') && COAI_LEVELS_PK) return COAI_LEVELS_PK;
    return 'id';
  }
}

// ------------------------------------------------------------
// Detect COAI column name (COAI_number vs coai_number)
// - If both exist, choose the one with more real values.
// ------------------------------------------------------------
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
      error_log('[COAI] coai_get_coai_column_name BOTH exist: upperCount='.$upperCount.' lowerCount='.$lowerCount.' pick='.$pick);

      return $cache[$table] = $pick;
    }

    return $cache[$table] = 'COAI_number';
  }
}

// ------------------------------------------------------------
// Filters + sorting helpers
// ------------------------------------------------------------
if (!function_exists('coai_month_to_int')) {
  function coai_month_to_int($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || strtolower($raw) === 'any') return 0;

    if (preg_match('/^\d{1,2}$/', $raw)) {
      $m = (int)$raw;
      return ($m >= 1 && $m <= 12) ? $m : 0;
    }
    $map = [
      'jan'=>1,'january'=>1,'feb'=>2,'february'=>2,'mar'=>3,'march'=>3,'apr'=>4,'april'=>4,'may'=>5,
      'jun'=>6,'june'=>6,'jul'=>7,'july'=>7,'aug'=>8,'august'=>8,'sep'=>9,'sept'=>9,'september'=>9,
      'oct'=>10,'october'=>10,'nov'=>11,'november'=>11,'dec'=>12,'december'=>12,
    ];
    $k = strtolower($raw);
    return $map[$k] ?? 0;
  }
}

if (!function_exists('coai_md_address_region_states')) {
  function coai_md_address_region_states() {
    return [
      'North East Region'    => ['CT','MA','ME','NH','NY','RI'],
      'North Central Region' => ['ND','SD','NE','KS','OK','AR','MO'],
      'North West Region'   => ['ID','MT','ND','NE','OR','SD','WA','WY'],
      
      'Mid East Region'     => ['DC','DE','MD','NJ','PA','VA','WV'],
      'Mid West Region'     => ['IA','IL','IN','KY','MI','MN','MO','OH','WI'],
      'South East Region'   => ['AL','AR','FL','GA','LA','MS','NC','SC','TN'],
      
      'South Central Region'=> ['CO','KS','NM','OK','TX'],
      'South West Region'   => ['AZ','CA','HI','NV','UT'],
      
      'Canada Region'       => ['__CANADA__'],
      'Latin Region'        => ['__LATIN__'],
      'International Region'=> ['__INTERNATIONAL__'],
    ];
  }
}

/**
 * Build WHERE / args / optional JOIN
 * IMPORTANT: new_only uses ONLY `$table`.is_new_member = 1
 *
 * @return array{where:string,args:array,join_sql:string}
 */
if (!function_exists('coai_md_build_filters')) {
  function coai_md_build_filters($table, $params) {
    global $wpdb;

    $where    = 'WHERE 1=1';
    $args     = [];
    $join_sql = '';
    
    $include_archived = !empty($params['include_archived']);


    if (!empty($params['new_only'])) {
      $where .= " AND `$table`.is_new_member = 1";
    }

    $q          = isset($params['q']) ? sanitize_text_field($params['q']) : '';
    $status     = isset($params['status']) ? strtoupper(sanitize_text_field($params['status'])) : '';
    $state_f    = isset($params['state']) ? strtoupper(sanitize_text_field($params['state'])) : '';
    $country_f  = isset($params['country']) ? strtoupper(sanitize_text_field($params['country'])) : '';
    $region_f   = isset($params['region']) ? sanitize_text_field($params['region']) : '';
    $coai_region_f = isset($params['coai_region']) ? sanitize_text_field($params['coai_region']) : '';
    $ins_f      = isset($params['insurance_status']) ? sanitize_text_field($params['insurance_status']) : '';
    $level_id   = isset($params['level_id']) ? (int)$params['level_id'] : 0;
    $level_name = isset($params['level_name']) ? trim(sanitize_text_field($params['level_name'])) : '';

    $reg_from_raw = isset($params['reg_from']) ? sanitize_text_field($params['reg_from']) : '';
    $reg_to_raw   = isset($params['reg_to'])   ? sanitize_text_field($params['reg_to'])   : '';
    $reg_from     = $reg_from_raw ? date('Y-m-d 00:00:00', strtotime($reg_from_raw)) : '';
    $reg_to       = $reg_to_raw   ? date('Y-m-d 23:59:59', strtotime($reg_to_raw))   : '';

    $date_expr = "COALESCE(NULLIF(`$table`.registered_date,'0000-00-00 00:00:00'),`$table`.created_at)";

    $year_f         = (isset($params['year']) && $params['year'] !== '') ? (int)$params['year'] : null;
    $month_from_raw = isset($params['month_from']) ? sanitize_text_field($params['month_from']) : '';
    $month_to_raw   = isset($params['month_to'])   ? sanitize_text_field($params['month_to'])   : '';
    $m1             = coai_month_to_int($month_from_raw);
    $m2             = coai_month_to_int($month_to_raw);

    if ($q !== '') {
      $cols = [
        'member_id','member_number','username','full_name','first_name','last_name','email','mobile',
        'address','address2','city','state','zip','country','region','clown_name','alley_membership',
        'insurance_status','status','COAI_number','coai_number',
        'shipping_address','shipping_address2','shipping_city','shipping_state','shipping_zip','shipping_country'
      ];
      $like = '%' . $wpdb->esc_like($q) . '%';

      $real_cols = $wpdb->get_col("DESC `$table`", 0);
      $real_cols = array_map('strval', (array)$real_cols);

      $use = [];
      foreach ($cols as $c) {
        if (in_array($c, $real_cols, true)) $use[] = "`$table`.`$c`";
      }

      if ($use) {
        $concat = "CONCAT_WS(' ', " . implode(',', $use) . ")";
        $where .= " AND ($concat LIKE %s)";
        $args[] = $like;
      }
    }

    if ($status !== '') {
      $where .= " AND UPPER(`$table`.status) = %s";
      $args[] = $status;
    } else {
      // Default behavior: hide Archived unless explicitly included
      if (!$include_archived) {
        $where .= " AND (UPPER(`$table`.status) <> 'ARCHIVED' OR `$table`.status IS NULL OR `$table`.status = '')";
      }
    }

    if ($state_f !== '')   { $where .= " AND UPPER(`$table`.state) = %s";  $args[] = $state_f; }
    if ($country_f !== '') { $where .= " AND UPPER(`$table`.country) = %s";$args[] = $country_f; }
    if ($region_f !== '')  { $where .= " AND `$table`.region = %s";        $args[] = $region_f; }
    if ($ins_f !== '')     { $where .= " AND `$table`.insurance_status = %s"; $args[] = $ins_f; }
    
    if ($coai_region_f !== '') {
      $address_regions = coai_md_address_region_states();

      if (isset($address_regions[$coai_region_f])) {
        $states = $address_regions[$coai_region_f];

        if (in_array('__CANADA__', $states, true)) {
          $where .= " AND (
            UPPER(TRIM(`$table`.country)) IN ('CA', 'CAN', 'CANADA')
            OR UPPER(TRIM(`$table`.region)) = 'CANADA'
          )";
        } elseif (in_array('__LATIN__', $states, true)) {
          $where .= " AND UPPER(TRIM(`$table`.country)) NOT IN ('', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA', 'CA', 'CAN', 'CANADA')";
        } elseif (in_array('__INTERNATIONAL__', $states, true)) {
          $where .= " AND UPPER(TRIM(`$table`.country)) NOT IN ('', 'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA', 'CA', 'CAN', 'CANADA')";
        } else {
          $placeholders = implode(',', array_fill(0, count($states), '%s'));
          $where .= " AND UPPER(TRIM(`$table`.state)) IN ($placeholders)";
          foreach ($states as $abbr) {
            $args[] = $abbr;
          }
        }
      }
    }

    if ($level_id > 0) { $where .= " AND `$table`.membership_level_id = %d"; $args[] = $level_id; }

    // keep as-is to preserve your existing level_name behavior
    if ($level_name !== '') {
      $levels_table = coai_get_levels_table();
      $pk = coai_get_levels_pk();
      $join_sql .= " LEFT JOIN `$levels_table` lvl ON lvl.`$pk` = `$table`.membership_level_id";
      $where .= " AND lvl.name = %s";
      $args[] = $level_name;
    }

    if ($reg_from) { $where .= " AND $date_expr >= %s"; $args[] = $reg_from; }
    if ($reg_to)   { $where .= " AND $date_expr <= %s"; $args[] = $reg_to; }

    if ($year_f !== null) { $where .= " AND YEAR($date_expr) = %d"; $args[] = $year_f; }

    if ($m1 && $m2) {
      if ($m1 <= $m2) {
        $where .= " AND MONTH($date_expr) BETWEEN %d AND %d";
        array_push($args, $m1, $m2);
      } else {
        $where .= " AND (MONTH($date_expr) >= %d OR MONTH($date_expr) <= %d)";
        array_push($args, $m1, $m2);
      }
    } elseif ($m1 && !$m2) {
      $where .= " AND MONTH($date_expr) >= %d"; $args[] = $m1;
    } elseif (!$m1 && $m2) {
      $where .= " AND MONTH($date_expr) <= %d"; $args[] = $m2;
    }

    return ['where'=>$where, 'args'=>$args, 'join_sql'=>$join_sql];
  }
}



if (!function_exists('coai_md_get_levels')) {
  function coai_md_get_levels() {
    global $wpdb;
    $t  = coai_get_levels_table();
    $pk = coai_get_levels_pk();
    $rows = $wpdb->get_results("SELECT `$pk` AS ID, name FROM `$t` ORDER BY name ASC", ARRAY_A);
    return is_array($rows) ? $rows : [];
  }
}

if (!function_exists('coai_md_sort_sql')) {
  function coai_md_sort_sql($table) {
    $sort = sanitize_text_field($_GET['sort'] ?? '');
    $dir  = strtolower(sanitize_text_field($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

    $renewal_expr = "COALESCE(`$table`.`renewal_date`, `$table`.`membership_expiration`)";
    $coai_col = coai_get_coai_column_name($table);

    $map = [
      'member_number' => "`$table`.member_number",
      'coai_number'   => "`$table`.`$coai_col`",
      'username'      => "`$table`.username",
      'name'          => "`$table`.last_name",
      'email'         => "`$table`.email",
      'phone'         => "`$table`.mobile",
      'clown_name'    => "`$table`.clown_name",
      'city'          => "`$table`.city",
      'state'         => "`$table`.state",
      'region'        => "`$table`.region",
      'renewal'       => $renewal_expr,
      'expires'       => "`$table`.membership_expiration",
      'insurance'     => "`$table`.insurance_status",
      'ins_eff'       => "`$table`.insurance_effective_date",
      'ins_exp'       => "`$table`.insurance_expiration_date",
      'status'        => "`$table`.status",
      'updated'       => "`$table`.updated_at",
      'created'       => "`$table`.created_at",
    ];

    $expr = $map[$sort] ?? "`$table`.updated_at";
    return " ORDER BY $expr $dir, `$table`.member_id DESC ";
  }
}

if (!function_exists('coai_md_sort_link')) {
  function coai_md_sort_link($key) {
    $cur_sort = sanitize_text_field($_GET['sort'] ?? '');
    $cur_dir  = strtolower(sanitize_text_field($_GET['dir'] ?? 'desc'));
    $next_dir = ($cur_sort === $key && $cur_dir === 'asc') ? 'desc' : 'asc';

    $params = $_GET;
    $params['sort'] = $key;
    $params['dir']  = $next_dir;
    unset($params['pg']);
    return esc_url(add_query_arg($params));
  }
}

if (!function_exists('coai_md_sort_header')) {
  function coai_md_sort_header(string $key, string $label): string {
    $cur_sort = sanitize_text_field($_GET['sort'] ?? '');
    $cur_dir  = strtolower(sanitize_text_field($_GET['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $active   = ($cur_sort === $key);
    $arrow    = $active ? ($cur_dir === 'asc' ? '▲' : '▼') : '↕';
    $title    = $active
      ? 'Currently sorted ' . ($cur_dir === 'asc' ? 'ascending' : 'descending') . '. Select to reverse.'
      : 'Sort by ' . $label;

    return '<a class="coai-sort-link' . ($active ? ' is-active' : '') . '" href="' .
      coai_md_sort_link($key) . '" title="' . esc_attr($title) . '">' .
      esc_html($label) . ' <span class="coai-sort-arrow" aria-hidden="true">' . esc_html($arrow) . '</span></a>';
  }
}

if (!function_exists('coai_md_pager')) {
  function coai_md_pager($total, $pp) {
    $pg = max(1, (int)($_GET['pg'] ?? 1));
    $pages = max(1, (int)ceil($total / $pp));
    if ($pages <= 1) return '';

    $params = $_GET;
    $html = '<div class="coai-pager">';

    $prev = max(1, $pg - 1);
    $next = min($pages, $pg + 1);

    $params['pg'] = $prev;
    $html .= '<a class="coai-pagebtn" href="'.esc_url(add_query_arg($params)).'">‹ Prev</a>';

    $start = max(1, $pg - 2);
    $end   = min($pages, $pg + 2);

    if ($start > 1) {
      $params['pg']=1;
      $html .= '<a class="coai-pagebtn" href="'.esc_url(add_query_arg($params)).'">1</a>';
      if ($start > 2) $html .= '<span class="coai-ellipsis">…</span>';
    }

    for ($i=$start; $i<=$end; $i++) {
      $params['pg']=$i;
      $cls = $i === $pg ? ' coai-pagebtn is-active' : ' coai-pagebtn';
      $html .= '<a class="'.$cls.'" href="'.esc_url(add_query_arg($params)).'">'.(int)$i.'</a>';
    }

    if ($end < $pages) {
      if ($end < $pages-1) $html .= '<span class="coai-ellipsis">…</span>';
      $params['pg']=$pages;
      $html .= '<a class="coai-pagebtn" href="'.esc_url(add_query_arg($params)).'">'.(int)$pages.'</a>';
    }

    $params['pg'] = $next;
    $html .= '<a class="coai-pagebtn" href="'.esc_url(add_query_arg($params)).'">Next ›</a>';

    $html .= '</div>';
    return $html;
  }
}

if (!function_exists('coai_md_styles')) {
  function coai_md_styles() {
    static $done = false;
    if ($done) return '';
    $done = true;
    ob_start(); ?>
    <style>
      .coai-wrap{max-width:none}
      .coai-table-wrap{max-width:1200px;margin:0 auto;padding:0 12px;overflow-x:auto;border:1px solid #eef2f7;border-radius:14px;background:#fff;}
      .coai-table{width:100%;border-collapse:collapse}
      .coai-table th,.coai-table td{padding:8px 10px;white-space:nowrap}
      .coai-table th a{color:inherit;text-decoration:none}
      .coai-table th a:hover{text-decoration:underline}
      .coai-sort-link{display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
      .coai-sort-link.is-active{font-weight:800;text-decoration:underline}
      .coai-sort-arrow{font-size:11px;line-height:1;opacity:.65}
      .coai-sort-link.is-active .coai-sort-arrow{opacity:1}
      .coai-toolbar{max-width:1200px;margin:0 auto 10px;padding:0 12px;display:flex;justify-content:space-between;gap:12px;align-items:center}
      .coai-pill{display:inline-block;padding:6px 10px;border:1px solid #e5e7eb;border-radius:999px}
      .status-badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #e5e7eb}
      .coai-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;

        min-width:130px;
        height:46px;
        padding:0 22px;

        border-radius:10px;
        border:1px solid #cbd5e1;

        background:#ffffff;
        color:#e91e63;

        text-decoration:none;
        cursor:pointer;

        font-size:15px;
        font-weight:700;
        line-height:1;

        box-shadow:
            0 1px 2px rgba(0,0,0,.08),
            0 3px 8px rgba(0,0,0,.06);

        transition:
            transform .18s ease,
            box-shadow .18s ease,
            background-color .18s ease,
            color .18s ease;
    }
      .coai-pager{max-width:1200px;margin:12px auto 0;padding:0 12px;display:flex;gap:6px;align-items:center;flex-wrap:wrap}
      .coai-pagebtn{padding:6px 10px;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none}
      .coai-pagebtn.is-active{font-weight:700}
      .coai-ellipsis{padding:0 6px}
      .coai-filters{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;align-items:end;margin:10px auto 14px;max-width:1200px;padding:0 12px;}
      .coai-field label{display:block;font-weight:600;margin:0 0 6px}
      .coai-input,.coai-select{width:100%;max-width:100%;box-sizing:border-box;background:#fff;color:#111;border:1px solid #d1d5db;border-radius:12px;padding:10px 12px;}
      .coai-actions{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:10px;margin-bottom:18px}
      @media (max-width: 980px){ .coai-filters{grid-template-columns:repeat(2,minmax(0,1fr));} }
      @media (max-width: 600px){ .coai-filters{grid-template-columns:1fr;} }
      .notice{margin:12px auto;max-width:1200px;padding:10px 12px;border-radius:12px;border:1px solid #e5e7eb;background:#fff;}
      .notice.notice-error{border-color:#fecaca;background:#fff1f2;color:#991b1b;}
      .notice.notice-success{border-color:#bbf7d0;background:#f0fdf4;color:#065f46;}
      .notice.notice-warning{border-color:#fde68a;background:#fffbeb;color:#92400e;}
      .coai-form-wrap{max-width:1100px;margin:12px auto;padding:12px 14px;border:1px solid #e5e7eb;border-radius:16px;background:#fff;}
      .coai-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}
      @media (max-width: 900px){ .coai-grid{grid-template-columns:1fr;} }
      .coai-subtle{color:#6b7280;font-size:13px;line-height:1.35}
      
      .coai-btn:hover{
          transform:translateY(-1px);
          box-shadow:
              0 4px 10px rgba(0,0,0,.12),
              0 2px 4px rgba(0,0,0,.08);
      }

      .coai-btn:active{
          transform:translateY(1px);
          box-shadow:
              inset 0 2px 5px rgba(0,0,0,.10);
      }
      
      .coai-btn-apply{
          background:#b91c1c !important;
          color:#ffffff;
          border-color:#991b1b !important;
      }
      
      .coai-btn-apply:hover{
          background:#991b1b; !important;
          color:#ffffff; !important;
      }
      
      .coai-btn-reset{
          background:#ffffff !important;
          color:#e91e63 !important;
          border-color:#cbd5e1 !importanti;
      }
          
      .coai-btn-reset:hover{
          background:#f8fafc !important;
          color:#e91e63 !important;
      }
      
      .coai-filter-actions{
        display:flex;
        gap:10px;
        align-items:center;
        margin-top:10px;
        margin-bottom:22px;
      }

      .coai-filter-actions .coai-btn{
        flex:0 0 auto;
        min-width:125px;
        max-width:125px;
      }
      .coai-actions .coai-btn,
      .coai-actions button.coai-btn{
          min-width:110px;
          height:44px;
          display:inline-flex;
          align-items:center;
          justify-content:center;
          font-weight:600;
      }
      
      /* -------------------------------------------------------
         COAI Distribution Center
      ------------------------------------------------------- */

      .coai-distribution-center{
          grid-column:1/-1;
          width:100%;
          box-sizing:border-box;
          
          margin:20px 0 24px;
          padding:20px 24px  18px;
          
          background:#f8fafc;
          
          border:1px solid #CBD5E1;
          border-radius:14px;
          
          box-shadow:
              0 2px 6px rgba(15,23,42,.05),
              0 8px 20px rgba(15,23,42,.08);

          transition:
              box-shadow .2s ease,
              transform .2s ease,
      }

      .coai-distribution-header{
          margin-bottom:18px;
      }

      .coai-distribution-header h3{
          margin:0 9 10px;
          font-size:36px;
          font-weight:700;
          line-height:1.2;
      }
      
      .coai-distribution-header h4{
          margin:0 0 10px;
          font-size:20px;
          font-weight:600;
          color:#475569;
      }

      .coai-distribution-header p{
          margin:0;
          color:#64748b;
          font-size:15px;
      }

      .coai-distribution-center .coai-actions{
          display:flex;
          flex-wrap:wrap;
          gap:12px;
          align-items:center;
          margin-top:0;
      }

      .coai-distribution-center .coai-btn{
          flex:1;
          min-width:220px;
          max-width:260px;
          text-align:center;
      }
      
      .coai-divider{
        border:0;
        border-top:1px solid #dbe4ee;
        margin:16px 0 18px;
    }
    
    .coai-actions:first-of-type{
       margin-bottom:24px;
    }
    
    .coai-btn-master{
      background:#c89b3c !important;
      color:#fff !important;
      border-color:#a6791f !important;
    }

    .coai-btn-master:hover{
      background:#a6791f !important;
      color:#fff !important;
    }
    
    </style>
    <?php return ob_get_clean();
  }
}

if (!function_exists('coai_md_filters_form')) {
  function coai_md_filters_form($levels) {
    $q            = sanitize_text_field($_GET['q'] ?? '');
    $level_id_f   = (int)($_GET['level_id'] ?? 0);
    $level_name_f = sanitize_text_field($_GET['level_name'] ?? '');
    $reg_from_raw = sanitize_text_field($_GET['reg_from'] ?? '');
    $reg_to_raw   = sanitize_text_field($_GET['reg_to'] ?? '');
    $mon_a_raw    = sanitize_text_field($_GET['month_from'] ?? '');
    $mon_b_raw    = sanitize_text_field($_GET['month_to'] ?? '');
    $year_f       = sanitize_text_field($_GET['year'] ?? '');
    $region_f      = sanitize_text_field($_GET['region'] ?? '');
    $coai_region_f = sanitize_text_field($_GET['coai_region'] ?? '');
    $coai_regions  = coai_md_address_region_states();
    $new_only      = !empty($_GET['new_only']);
    $include_archived = !empty($_GET['include_archived']);

    $months = [
      ''=>'Any','1'=>'Jan','2'=>'Feb','3'=>'Mar','4'=>'Apr','5'=>'May','6'=>'Jun',
      '7'=>'Jul','8'=>'Aug','9'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'
    ];

    ob_start(); ?>
    
    <?php if (!empty($_GET['coai_master_status'])): ?>

    <?php
    $master_status    = sanitize_text_field($_GET['coai_master_status'] ?? '');
    $master_processed = (int) ($_GET['coai_master_processed'] ?? 0);
    $master_success   = (int) ($_GET['coai_master_success'] ?? 0);
    $master_failed    = (int) ($_GET['coai_master_failed'] ?? 0);
    $master_emailed   = (int) ($_GET['coai_master_emailed'] ?? 0);
    ?>

    <div class="coai-export-notice <?php echo esc_attr($master_status); ?>" style="
        max-width:980px;
        margin:24px 0;
        padding:24px;
        background:#f8fafc;
        border:1px solid #dbe4ef;
        border-left:6px solid #16a34a;
        border-radius:12px;
        box-shadow:0 4px 12px rgba(15,23,42,.08);
    ">

        <h3 style="margin:0 0 6px;color:#14532d;font-size:22px;">
            🌎 Master Export Complete
        </h3>

        <p style="margin:0 0 20px;color:#334155;">
            All regional export files were uploaded successfully to Google Drive.
        </p>

       <div style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:14px;
            margin-top:18px;
        ">

            <div style="background:#ffffff;border:1px solid #dbe4ef;border-radius:10px;padding:16px;">
                <div style="font-size:13px;color:#64748b;font-weight:700;">COAI Regions Processed</div>
                <div style="font-size:30px;font-weight:800;color:#0f172a;margin-top:6px;">
                    <?php echo (int) $master_processed; ?>
                </div>
            </div>
  
            <div style="background:#ffffff;border:1px solid #dbe4ef;border-radius:10px;padding:16px;">
                <div style="font-size:13px;color:#64748b;font-weight:700;">Successful Exports</div>
                <div style="font-size:30px;font-weight:800;color:#166534;margin-top:6px;">
                    <?php echo (int) $master_success; ?>
                </div>
            </div>

            <div style="background:#ffffff;border:1px solid #dbe4ef;border-radius:10px;padding:16px;">
                <div style="font-size:13px;color:#64748b;font-weight:700;">Failed Exports</div>
                <div style="font-size:30px;font-weight:800;color:#991b1b;margin-top:6px;">
                    <?php echo (int) $master_failed; ?>
                </div>
            </div>

            <div style="background:#ffffff;border:1px solid #dbe4ef;border-radius:10px;padding:16px;">
                <div style="font-size:13px;color:#64748b;font-weight:700;">RVPs Emailed</div>
                <div style="font-size:30px;font-weight:800;color:#1e3a8a;margin-top:6px;">
                    <?php echo (int) $master_emailed; ?>
                </div>
            </div>

        </div>

    </div>

    <?php endif; ?>

    <?php if (!empty($_GET['coai_export_status'])): ?>

    <?php
    $status   = sanitize_text_field($_GET['coai_export_status']);
    $msg      = rawurldecode($_GET['coai_export_msg'] ?? '');
    $link     = rawurldecode($_GET['coai_file_link'] ?? '');
    $filename = rawurldecode($_GET['coai_filename'] ?? '');
    $region   = sanitize_text_field($_GET['coai_region'] ?? '');
    $updated_at = rawurldecode($_GET['coai_updated_at'] ?? '');
    ?>

    <div class="coai-export-notice <?php echo esc_attr($status); ?>">

    <?php if ($status === 'success'): ?>

    <h3 style="margin:0 0 12px;">✅ Google Drive Upload Successful  </h3>
    
    <p style="margin:0 0 10px;">
    <?php echo esc_html($msg); ?>
    </p>

    <p style="margin:0;">
    <strong>COAI Region:</strong> <?php echo esc_html($region); ?>
    </p>

    <?php if (!empty($filename)): ?>
    <p style="margin:6px 0 0;">
    <strong>Filename:</strong> <?php echo esc_html($filename); ?>
    </p>
    <?php endif; ?>
    
    <?php if (!empty($updated_at)): ?>
    <p style="margin:6px 0 0;">
    <strong>Last Updated:</strong>
    <?php echo esc_html(
        date_i18n(
            get_option('date_format') . ' ' . get_option('time_format'),
            strtotime($updated_at)
        )
    ); ?>
    </p>
<?php endif; ?>

<hr style="margin:18px 0;border:none;border-top:1px solid #d1d5db;">
<?php
$notify_list = [];

if (!empty($_GET['coai_notify_list'])) {

    $decoded = json_decode(
        wp_unslash(rawurldecode((string)$_GET['coai_notify_list'])),
        true
    );

    if (is_array($decoded)) {
        $notify_list = $decoded;
    }
}
?>

<?php if (!empty($notify_list)) : ?>

    <?php
    $notify_count = count($notify_list);
    ?>
    
    <div style="
        max-width:980px;
        margin:24px 0 18px;
        padding:22px 24px;
        background:#f8fafc;
        border:5px solid #bfdbfe;
        border-left:5px solid #256eeb;
        border-radius:10px;
        box-shadow:0 4px 12px rgba(15,23,42,.08);
    ">

    <div style="
    text-align:center;
        font-size:17px;
        font-weight:700;
        color:#1e3a8a;
        margin-bottom:18px;
    ">
        
    Regional Distribution Summary
    </div>

    <p style="margin:0 0 16px;
              color:#166534;
              font-weight:600;
              font-size:14px;
        ">
        ✅  <?php echo (int)$notify_count; ?>
    of
    <?php echo (int)$notify_count; ?>
    recipient(s) notified successfully.
</p>

    <hr style="
        margin:12px 0 16px;
        border:0;
        border-top:1px solid #dbe4ef;
    ">

    <div style="
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
        gap:14px;
        margin:0 auto 14px;
        max-width:900px;
    ">

    <?php foreach ($notify_list as $recipient) :

        $parts = array_map('trim', explode(' - ', $recipient, 3));

    ?>

        <div style="
            background:#ffffff;
            border:1px solid #dbe4ef;
            border-radius:10px;
            padding:14px 16px;
            box-shadow:0 2px 6px rgba(15,23,42,.05);
        ">

            <div style="color:#1e3a8a;font-weight:700;margin-bottom:8px;">
                <?php echo esc_html($parts[0] ?? ''); ?>
            </div>

            <div style="color:#1f2937;font-weight:600;margin-bottom:6px;">
                👤 <?php echo esc_html($parts[1] ?? ''); ?>
            </div>

            <div style="color:#334155;">
                ✉️ <?php echo esc_html($parts[2] ?? ''); ?>
            </div>

        </div>

    <?php endforeach; ?>

    </div>
    
    <hr style="
        margin:18px 0 14px;
        border:0;
        border-top:1px solid #dbe4ef;
    ">

    <div style="text-align:center;">

        <a class="coai-btn"
           href="<?php echo esc_url($link); ?>"
           target="_blank"
           rel="noopener">

            📁 Open File in Google Drive

        </a>
  
    </div>
    </div>

<?php elseif (!empty($_GET['coai_notify_msg'])) : ?>

    <p>
        <strong>Email Notification:</strong>
        <?php echo esc_html(rawurldecode((string)$_GET['coai_notify_msg'])); ?>
    </p>

<?php endif; ?>

    <?php else: ?>

    <h3 style="margin:0 0 12px;">❌ Google Drive Export Failed</h3>

    <p style="margin:0;">
    <?php echo esc_html($msg); ?>
    </p>

    <?php endif; ?>

    </div>

    <?php endif; ?>

    <form method="get" class="coai-filters">
        <input type="hidden" name="_coai_export_nonce" value="<?php echo esc_attr(wp_create_nonce('coai_export')); ?>">
        <input type="hidden" name="_coai_export_google_nonce" value="<?php echo esc_attr(wp_create_nonce('coai_export_google')); ?>">
        <input type="hidden" name="_coai_export_both_nonce" value="<?php echo esc_attr(wp_create_nonce('coai_export_both')); ?>">
      <div class="coai-field">
        <label>Search</label>
        <input class="coai-input" type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Name, email, username, member #">
      </div>

      <div class="coai-field">
        <label>Level</label>
        <select name="level_id" class="coai-select">
          <option value="">Any</option>
          <?php foreach (($levels ?? []) as $lvl): ?>
            <option value="<?php echo (int)$lvl['ID']; ?>" <?php selected($level_id_f, (int)$lvl['ID']); ?>>
              <?php echo esc_html($lvl['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="coai-field">
        <label>Level Name</label>
        <select name="level_name" class="coai-select">
          <option value="">Any</option>
          <?php foreach (($levels ?? []) as $lvl): $nm = $lvl['name']; ?>
            <option value="<?php echo esc_attr($nm); ?>" <?php selected($level_name_f, $nm); ?>>
              <?php echo esc_html($nm); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="coai-field">
        <label>Normal Region</label>
        <select name="region" class="coai-select">
          <option value="">Any</option>
          <?php foreach (['Northeast','Midwest','South','West','Canada','International'] as $region_name): ?>
            <option value="<?php echo esc_attr($region_name); ?>" <?php selected($region_f, $region_name); ?>>
              <?php echo esc_html($region_name); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="coai-field">
       <label>COAI Region</label>
        <select name="coai_region" class="coai-select">
          <option value="">Any</option>
          <?php foreach ($coai_regions as $region_name => $states): ?>
           <option value="<?php echo esc_attr($region_name); ?>" <?php selected($coai_region_f, $region_name); ?>>
              <?php echo esc_html($region_name); ?>
            </option>
         <?php endforeach; ?>
        </select>
      </div>

      <div class="coai-field"><label>Reg From</label><input class="coai-input" type="date" name="reg_from" value="<?php echo esc_attr($reg_from_raw); ?>"></div>
      <div class="coai-field"><label>Reg To</label><input class="coai-input" type="date" name="reg_to" value="<?php echo esc_attr($reg_to_raw); ?>"></div>

      <div class="coai-field">
        <label>Month From</label>
        <select class="coai-select" name="month_from">
          <?php foreach ($months as $val => $label): ?>
            <option value="<?php echo esc_attr($val); ?>" <?php selected((string)$mon_a_raw, (string)$val); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="coai-field">
        <label>Month To</label>
        <select class="coai-select" name="month_to">
          <?php foreach ($months as $val => $label): ?>
            <option value="<?php echo esc_attr($val); ?>" <?php selected((string)$mon_b_raw, (string)$val); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="coai-field"><label>Year</label><input class="coai-input" type="number" name="year" min="1900" max="2100" value="<?php echo esc_attr($year_f); ?>"></div>

      <div class="coai-field" style="grid-column:1/-1;">
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="new_only" value="1" <?php checked($new_only); ?>>
          New Members only (flag)
        </label>
      </div>
      
      <div class="coai-field" style="grid-column:1/-1;">
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="include_archived" value="1" <?php checked($include_archived); ?>>
          Include Archived
        </label>
      </div>

      <div class="coai-actions coai-filter-actions">
        <button class="coai-btn coai-btn-apply" type="submit">
          🔍 Apply
        </button>

        <a class="coai-btn coai-btn-reset" href="<?php echo esc_url(home_url('/member-directory/')); ?>">
          ↺ Reset
        </a>
      </div>
      
            <div class="coai-distribution-center">
                
                <div class="coai-distribution-header">

                    <h3>📁 COAI Distribution Center</h3>
                    <h4>Google Drive Regional Directory Management</h4>
                    <p>Upload member directories to Google Drive for COAI Regional Vice Presidents.</p>
      </div>
      
      <hr class="coai-divider">
        
      <div class="coai-actions">

          <!-- Download CSV -->
          <button class="coai-btn"
                  type="submit"
                  name="coai_export"
                  value="1">
              Download CSV
          </button>

          <!-- Upload Selected Region Files -->
          <button class="coai-btn"
                  type="submit"
                  name="coai_export_google"
                  value="1"
                  style="background:#4285F4;color:#fff;border-color:#4285F4;">
              Upload Selected Region Files
          </button>
          
          <!-- Master Export All COAI Regions -->
          <a class="coai-btn coai-btn-master" style="background:#c89b3c;color:#fff;border-color:#a6791f;"
             href="<?php echo esc_url(add_query_arg([
                'coai_master_export' => 1,
                '_coai_nonce'       => wp_create_nonce('coai_master_export'),
             ])); ?>">
              🌎 Export ALL COAI Regions
          </a>

         <!-- Upload + Download CSV -->
         <button class="coai-btn"
                 type="submit"
                 name="coai_export_both"
                 value="1"
                 style="background:#16a34a;color:#fff;border-color:#16a34a;">
             Upload + Download CSV
         </button>
         <?php
         $google_reconnect_url = home_url('/google-oauth-reconnect');
         ?>
      </div>
    </div>
    </form>

    <?php
    $history_rows = function_exists('coai_google_export_history_rows')
        ? coai_google_export_history_rows(10)
        : [];
    ?>

    <?php if (!empty($history_rows)): ?>
    <details class="coai-export-history" style="margin-top:24px;">
        <summary style="cursor:pointer;font-weight:700;font-size:18px;margin-bottom:12px;">
           Export History
        </summary>

      <h3>Recent Google Export History</h3>

      <table class="coai-table">
        <thead>
          <tr>
            <th>Date/Time</th>
            <th>User</th>
            <th>Type</th>
            <th>Region</th>
            <th>Records</th>
            <th>Status</th>
            <th>File</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history_rows as $row): ?>
            <tr>
              <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['export_date']))); ?></td>
              <td><?php echo esc_html($row['exported_by_login'] ?: 'Unknown'); ?></td>
              <td><?php echo esc_html($row['export_type']); ?></td>
              <td><?php echo esc_html($row['region']); ?></td>
              <td><?php echo esc_html(number_format_i18n((int)$row['member_count'])); ?></td>
              <td><?php echo esc_html($row['status']); ?></td>
              <td>
                <?php if (!empty($row['google_file_link'])): ?>
                  <a href="<?php echo esc_url($row['google_file_link']); ?>" target="_blank" rel="noopener">Open</a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
         <?php endforeach; ?>
       </tbody>
      </table>
    </details>

    <?php endif; ?>
    <?php return ob_get_clean();
  }
}

function coai_members_export_csv_for_region(string $region): array
{
    global $wpdb;

    $table = coai_get_members_table();

    $request = [
        'coai_region' => $region,
    ];

    $f = coai_md_build_filters($table, $request);

    $where    = $f['where'];
    $args     = $f['args'];
    $join_sql = $f['join_sql'];

    $sql = "SELECT `$table`.* FROM `$table` $join_sql $where ORDER BY last_name, first_name, username";

    $rows = !empty($args)
        ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A)
        : $wpdb->get_results($sql, ARRAY_A);

    $filename = function_exists('coai_google_export_filename_for_region')
        ? coai_google_export_filename_for_region($region)
        : sanitize_title($region) . '.csv';

    $out = fopen('php://temp', 'r+');

    if ($rows) {
        fputcsv($out, array_keys($rows[0]));

        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
    } else {
        fputcsv($out, ['No rows']);
    }

    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    return [
        'success'  => true,
        'region'   => $region,
        'count'    => count($rows),
        'filename' => $filename,
        'csv'      => $csv,
    ];
}

// ------------------------------------------------------------
// CSV export (member-directory only)
// NOTE: exports `$table`.* so new insurance columns are included automatically.
// ------------------------------------------------------------
add_action('template_redirect', function () {
  if (!is_page('member-directory')) return;
  if (empty($_GET['coai_export'])) return;

  if (!coai_staff_can('view')) wp_die('Unauthorized', '', 403);
  if (
      empty($_GET['_coai_export_nonce']) ||
      !wp_verify_nonce($_GET['_coai_export_nonce'], 'coai_export')
  ) {
      wp_die('Bad nonce', '', 400);
  }

  global $wpdb;
  $table = coai_get_members_table();

  $f = coai_md_build_filters($table, $_GET);
  $where    = $f['where'];
  $args     = $f['args'];
  $join_sql = $f['join_sql'];

  $sql = "SELECT `$table`.* FROM `$table` $join_sql $where ORDER BY last_name, first_name, username";

  $rows = !empty($args)
    ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A)
    : $wpdb->get_results($sql, ARRAY_A);

  nocache_headers();
  header("Content-Type: text/csv; charset=utf-8");
  $export_label = 'All-Members';

  if (!empty($_GET['coai_region'])) {
    $export_label = sanitize_text_field($_GET['coai_region']);
  } elseif (!empty($_GET['region'])) {
    $export_label = sanitize_text_field($_GET['region']) . '-Region';
  }

  $export_filename = sanitize_title($export_label) . '-' . date('Ymd-His') . '.csv';

  header("Content-Disposition: attachment; filename=" . $export_filename);

    $out = fopen('php://output', 'w');
    if ($rows) {
      fputcsv($out, array_keys($rows[0]));
      foreach ($rows as $r) fputcsv($out, $r);
    } else {
      fputcsv($out, ['No rows']);
    }
    fclose($out);
    exit;
  });
  
// ------------------------------------------------------------
// Google OAuth Start
// ------------------------------------------------------------
add_action('template_redirect', function () {

    if (empty($_GET['coai_google_auth'])) {
        return;
    }

    if (!coai_staff_can('view')) {
        wp_die('Unauthorized', '', 403);
    }
    
    if (!function_exists('coai_google_oauth_authorize_url')) {
    $google_drive_file = COAI_PLUGIN_DIR . 'includes/google-drive.php';

    if (file_exists($google_drive_file)) {
        require_once $google_drive_file;
    }
}

    if (!function_exists('coai_google_oauth_authorize_url')) {
        wp_die('Google OAuth service unavailable.', '', 500);
    }

    wp_redirect(coai_google_oauth_authorize_url());
    exit;
}); 

// ------------------------------------------------------------
// Google Drive Export (v3.0)
// ------------------------------------------------------------
add_action('template_redirect', function () {

    if (!is_page('member-directory')) {
        return;
    }

    $google_export = !empty($_GET['coai_export_google']);
    $both_export   = !empty($_GET['coai_export_both']);
    $master_export = !empty($_GET['coai_master_export']);

    if (!$google_export && !$both_export && !$master_export) {
        return;
    }

    if (!coai_staff_can('view')) {
        wp_die('Unauthorized', '', 403);
    }

    if ($master_export) {
        $nonce_action = 'coai_master_export';
        $nonce_value  = $_GET['_coai_nonce'] ?? '';
    } else {
        $nonce_action = $both_export ? 'coai_export_both' : 'coai_export_google';

        $nonce_value = $both_export
            ? ($_GET['_coai_export_both_nonce'] ?? '')
            : ($_GET['_coai_export_google_nonce'] ?? '');
    }

    if (
        empty($nonce_value) ||
        !wp_verify_nonce($nonce_value, $nonce_action)
    ) {
        wp_die('Bad nonce', '', 400);
    }

    $region = sanitize_text_field($_GET['coai_region'] ?? '');

    if (!$master_export && $region === '') {
        wp_safe_redirect(add_query_arg([
            'coai_export_status' => 'error',
            'coai_export_msg'    => rawurlencode('Please select a COAI Region before exporting to Google Drive.')
        ], remove_query_arg([
            'coai_export_google',
            'coai_export_both',
            'coai_notify_status',
            'coai_notify_msg',
            'coai_notify_list',
            '_coai_nonce'
        ])));

        exit;
    }
    
    if (!coai_google_is_connected()) {
    wp_safe_redirect(add_query_arg([
        'coai_google_auth' => 1,
    ], remove_query_arg([
        'coai_export_google',
        'coai_export_both',
        'coai_master_export',
        '_coai_nonce',
        '_coai_export_google_nonce',
        '_coai_export_both_nonce',
        'coai_export_status',
        'coai_export_msg',
        'coai_file_link',
    ])));

    exit;
}

    // -----------------------------------------
    // Upload to Google Drive
    // -----------------------------------------

    if ($master_export) {
        $regions = coai_distribution_get_all_region_labels();

        $distribution_summary = coai_distribution_execute_regions($regions);

        $msg = sprintf(
            'Master Export completed. %d of %d region(s) exported successfully.',
            (int) ($distribution_summary['successful_regions'] ?? 0),
            (int) ($distribution_summary['requested_regions'] ?? 0)
        );

         wp_safe_redirect(add_query_arg([
            'coai_master_status'    => $distribution_summary['success'] ? 'success' : 'error',
            'coai_master_msg'       => rawurlencode($msg),
            'coai_master_processed' => (int) ($distribution_summary['processed_regions'] ?? 0),
            'coai_master_success'   => (int) ($distribution_summary['successful_regions'] ?? 0),
            'coai_master_failed'    => (int) ($distribution_summary['failed_regions'] ?? 0),
            'coai_master_emailed'   => (int) ($distribution_summary['notifications_sent'] ?? 0),
        ], remove_query_arg([
            'coai_master_export',
            'coai_export_google',
            'coai_export_both',
            '_coai_nonce',
            '_coai_export_google_nonce',
            '_coai_export_both_nonce',
        ])));

        exit;
    }

$distribution_summary = coai_distribution_execute_regions([$region]);

    $result = $distribution_summary['results'][$region] ?? [
        'success'  => false,
        'region'   => $region,
        'count'    => 0,
        'filename' => '',
        'csv'      => '',
        'upload'   => [],
        'message'  => 'Distribution service did not return a result for this region.',
        'errors'   => ['Distribution service did not return a result for this region.'],
    ];
    
    if (!$result['success']) {

        wp_safe_redirect(add_query_arg([
            'coai_export_status' => 'error',
            'coai_export_msg'    => rawurlencode(
                'Google export failed: ' .
                $result['message']
            )
        ], remove_query_arg([
            'coai_export_google',
            'coai_export_both',
            '_coai_nonce'
        ])));

        exit;
    }
    
    /* SOF v4.2 - Notify Regional VP after successful Google export */
    $notify_result = [
        'success' => false,
        'sent'    => 0,
        'message' => 'Communications Service unavailable.',
    ];

    if (function_exists('coai_comm_notify_region_export')) {
        $notify_result = coai_comm_notify_region_export($result);
    }

    // -----------------------------------------
    // Export Both
    // -----------------------------------------

    if ($both_export) {

    nocache_headers();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($result['filename'] ?? 'coai-export.csv') . '"');

    echo $result['csv'] ?? '';

    exit;
}

    // -----------------------------------------
    // Success
    // -----------------------------------------

    wp_safe_redirect(add_query_arg([
        'coai_export_status' => 'success',
        'coai_export_msg' => rawurlencode(
            sprintf(
                '%d member records uploaded successfully.',
                (int)$result['count']
            )
        ),
        'coai_region'     => rawurlencode($result['region']),
        'coai_file_link'  => rawurlencode($result['upload']['file_link'] ?? ''),
        'coai_filename'   => rawurlencode($result['filename']),
        'coai_updated_at'    => rawurlencode(current_time('mysql')),
        'coai_notify_status' => !empty($notify_result['success']) ? 'success' : 'warning',
        'coai_notify_msg'    => rawurlencode((string)($notify_result['message'] ?? '')),
        'coai_notify_list' => rawurlencode(json_encode($notify_result['recipients'] ?? [])),
    ], remove_query_arg([
        'coai_export_google',
        'coai_export_both',
        '_coai_nonce',
        'coai_export_status',
        'coai_export_msg',
        'coai_file_link',
        'coai_notify_status',
        'coai_notify_msg',
        'coai_notify_list',
    ])));

    exit;

});

// ------------------------------------------------------------
// Password helper: update member login password (wp_members.password)
// - Use wp_hash_password so wp_check_password works in auth-bridge
// ------------------------------------------------------------
if (!function_exists('coai_admin_update_member_login_password')) {
  function coai_admin_update_member_login_password(string $table, int $member_id, string $plain): bool {
    global $wpdb;
    $plain = trim($plain);
    if ($member_id <= 0 || $plain === '') return false;

    $hash = function_exists('wp_hash_password')
      ? wp_hash_password($plain)
      : password_hash($plain, PASSWORD_DEFAULT);

    $updated = $wpdb->update(
      $table,
      ['password' => $hash],
      ['member_id' => $member_id],
      ['%s'],
      ['%d']
    );

    return ($updated !== false);
  }
}

// ------------------------------------------------------------
// Dynamic field rendering/saving (auto-show any DB columns not in core lists)
// ------------------------------------------------------------
if (!function_exists('coai_md_describe_table')) {
  function coai_md_describe_table(string $table): array {
    global $wpdb;
    $rows = $wpdb->get_results("DESCRIBE `$table`", ARRAY_A);
    return is_array($rows) ? $rows : [];
  }
}

if (!function_exists('coai_md_field_input_type')) {
  function coai_md_field_input_type(string $mysqlType): string {
    $t = strtolower($mysqlType);

    if (strpos($t, 'tinyint(1)') !== false) return 'checkbox';
    if (strpos($t, 'date') !== false && strpos($t, 'datetime') === false) return 'date';
    if (strpos($t, 'datetime') !== false || strpos($t, 'timestamp') !== false) return 'datetime-local';
    if (preg_match('/int|decimal|float|double/', $t)) return 'number';
    if (preg_match('/text|longtext|mediumtext/', $t)) return 'textarea';

    return 'text';
  }
}

if (!function_exists('coai_md_pretty_label')) {
  function coai_md_pretty_label(string $col): string {
    $col = str_replace('_', ' ', $col);
    return ucwords($col);
  }
}

// ------------------------------------------------------------
// Family Members helpers
// ------------------------------------------------------------
if (!function_exists('coai_family_members_table_name')) {
  function coai_family_members_table_name() {
    return 'wp_member_family_members';
  }
}

if (!function_exists('coai_get_family_members_for_member')) {
  function coai_get_family_members_for_member($member_id) {
    global $wpdb;

    $member_id = (int)$member_id;
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


// ------------------------------------------------------------
// Edit view (staff)
// ------------------------------------------------------------
if (!function_exists('coai_md_render_edit_view')) {
  function coai_md_render_edit_view($table, $mid, $portal_url) {
    if (!coai_staff_can('manage')) {
      return '<div class="notice notice-error">Access denied.</div>';
    }

    global $wpdb;
    $mid = (int)$mid;
    if ($mid <= 0) return '<div class="notice notice-error">Missing member id.</div>';

    $levels_table = coai_get_levels_table();
    $levels_pk    = coai_get_levels_pk();

    $lvl_id_col = $levels_pk;
    $lvl_cols = $wpdb->get_col("DESC `$levels_table`", 0);
    if (is_array($lvl_cols) && in_array('id', $lvl_cols, true)) $lvl_id_col = 'id';

    $coai_col = coai_get_coai_column_name($table);

    $row = $wpdb->get_row(
      $wpdb->prepare("
        SELECT m.*,
               m.`$coai_col` AS coai_pick,
               lvl.name AS membership_level_name
        FROM `$table` m
        LEFT JOIN `$levels_table` lvl ON lvl.`$lvl_id_col` = m.membership_level_id
        WHERE m.member_id=%d
        LIMIT 1
      ", $mid),
      ARRAY_A
    );

    if (!$row) return '<div class="notice notice-error">Member not found.</div>';

    $levels = $wpdb->get_results("SELECT `$lvl_id_col` AS id, name FROM `$levels_table` ORDER BY name", ARRAY_A);
    if (!is_array($levels)) $levels = [];

    // Column existence map (for safe optional updates)
    $cols = $wpdb->get_col("DESC `$table`", 0);
    $cols_lc = array_map('strtolower', (array)$cols);
    $has_force_pw = in_array('force_password_change', $cols_lc, true);

    $msg = '';
    $reason = '';

    // -------------------------------------------------
    // Handle POSTs
    // -------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
      // Family Members add/update/delete
      if (!empty($_POST['coai_family_action'])) {
        if (empty($_POST['_coai_family_nonce']) || !wp_verify_nonce($_POST['_coai_family_nonce'], 'coai_family_members_'.$mid)) {
          $msg .= '<div class="notice notice-error">Bad family member nonce.</div>';
        } elseif (!coai_staff_can('manage')) {
          $msg .= '<div class="notice notice-error">Access denied.</div>';
        } else {
          $family_table  = coai_family_members_table_name();
          $family_action = sanitize_key($_POST['coai_family_action']);
          $family_id     = isset($_POST['family_id']) ? (int)$_POST['family_id'] : 0;

          if ($family_action === 'add_family_member' || $family_action === 'update_family_member') {
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
              $msg .= '<div class="notice notice-error">Family member first and last name are required.</div>';
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

              $formats = ['%d','%s','%s','%s','%s','%s','%s','%s'];

              if ($family_action === 'update_family_member' && $family_id > 0) {
                $ok = $wpdb->update(
                  $family_table,
                  $data,
                  [
                    'id'                => $family_id,
                    'primary_member_id' => (int)$mid,
                  ],
                  $formats,
                  ['%d','%d']
                );

                $msg .= ($ok === false)
                  ? '<div class="notice notice-error">Family member update failed: '.esc_html($wpdb->last_error).'</div>'
                  : '<div class="notice notice-success">Family member updated.</div>';
              } else {
                // Prevent duplicate family members for the same primary member.
                $existing_family_id = $wpdb->get_var(
                  $wpdb->prepare(
                    "SELECT id
                     FROM `$family_table`
                     WHERE primary_member_id = %d
                       AND first_name = %s
                       AND last_name = %s
                       AND relationship = %s
                     LIMIT 1",
                    (int)$mid,
                    $first_name,
                    $last_name,
                    $relationship
                  )
                );

                if ($existing_family_id) {
                  $msg .= '<div class="notice notice-warning">Family member already exists.</div>';
                } else {
                  $ok = $wpdb->insert($family_table, $data, $formats);

                  $msg .= ($ok === false)
                    ? '<div class="notice notice-error">Family member add failed: '.esc_html($wpdb->last_error).'</div>'
                    : '<div class="notice notice-success">Family member added.</div>';
                }
              }
            }
          }

          if ($family_action === 'delete_family_member') {
            if ($family_id <= 0) {
              $msg .= '<div class="notice notice-error">Invalid family member selected.</div>';
            } else {
              $ok = $wpdb->delete(
                $family_table,
                [
                  'id'                => $family_id,
                  'primary_member_id' => (int)$mid,
                ],
                ['%d','%d']
              );

              $msg .= ($ok === false)
                ? '<div class="notice notice-error">Family member remove failed: '.esc_html($wpdb->last_error).'</div>'
                : '<div class="notice notice-success">Family member removed.</div>';
            }
          }
        }
      }

      // A) Member Login password reset (wp_members.password)
      if (!empty($_POST['coai_admin_set_member_password'])) {
        if (empty($_POST['_coai_member_pw_nonce']) || !wp_verify_nonce($_POST['_coai_member_pw_nonce'], 'coai_admin_member_pw_'.$mid)) {
          $msg .= '<div class="notice notice-error">Bad password nonce.</div>';
        } elseif (!coai_staff_can('manage')) {
          $msg .= '<div class="notice notice-error">Access denied.</div>';
        } else {
          $p1 = trim((string) wp_unslash($_POST['new_member_password'] ?? ''));
          $p2 = trim((string) wp_unslash($_POST['new_member_password_confirm'] ?? ''));

          if ($p1 === '' || $p2 === '') {
            $msg .= '<div class="notice notice-error">Password not set: both fields are required.</div>';
          } elseif ($p1 !== $p2) {
            $msg .= '<div class="notice notice-error">Password not set: passwords do not match.</div>';
          } elseif (strlen($p1) < 8) {
            $msg .= '<div class="notice notice-error">Password not set: must be at least 8 characters.</div>';
          } else {
            $ok = coai_admin_update_member_login_password($table, (int)$mid, $p1);
            $actor = wp_get_current_user();
            $actor_label = $actor ? ($actor->user_login . ' (#' . (int)$actor->ID . ')') : 'unknown';

            if ($ok) {
              if ($has_force_pw) {
                $wpdb->update($table, ['force_password_change'=>0], ['member_id'=>(int)$mid], ['%d'], ['%d']);
              }
              error_log(sprintf('[COAI] MEMBER PASSWORD RESET mid=%d by=%s', (int)$mid, $actor_label));
              $msg .= '<div class="notice notice-success">✅ <strong>Member Login Password</strong> updated.</div>';

              if (function_exists('coai_audit_log')) {
                coai_audit_log((int)$mid, 'password_changed', [
                  'password' => ['from' => '(hidden)', 'to' => '(hidden)'],
                ], 'Admin/Manager reset member login password');
              }
            } else {
              $msg .= '<div class="notice notice-error">Failed updating Member Login Password.</div>';
            }
          }
        }
      }

      // B) Main member update
      if (!empty($_POST['coai_admin_update_member'])) {
        if (empty($_POST['_coai_edit_nonce']) || !wp_verify_nonce($_POST['_coai_edit_nonce'], 'coai_admin_edit_'.$mid)) {
          $msg .= '<div class="notice notice-error">Bad nonce.</div>';
        } else {

          $fields = [];
          $coai_block_save = false;

          $post_text  = function($k) { return isset($_POST[$k]) ? sanitize_text_field(wp_unslash($_POST[$k])) : null; };
          $post_email = function($k) { return isset($_POST[$k]) ? sanitize_email(wp_unslash($_POST[$k])) : null; };

          foreach ([
            'username','full_name','email','usergroup','phone','mobile',
            'first_name','last_name',
            'address','address2','city','state','zip','country','region',
            'shipping_address','shipping_address2','shipping_city','shipping_state','shipping_zip','shipping_country',
            'billing_address2',
            'clown_name','parent_name','e_contact','alley_membership',
            'internal_comments'
          ] as $k) {

            if ($k === 'email') {
              $v = $post_email($k);
            } elseif ($k === 'internal_comments') {
              $v = isset($_POST[$k]) ? wp_kses_post(wp_unslash($_POST[$k])) : null;
            } else {
              $v = $post_text($k);
            }

            if ($v !== null) $fields[$k] = $v;
          }

          foreach ([
            'member_number','insurance_status','status',
            'payment_amount','check_number'
          ] as $k) {
            $v = $post_text($k);
            if ($v !== null) $fields[$k] = ($k === 'status') ? strtoupper($v) : $v;
          }

          // Payment Mode: dropdown + optional "Other"
          $pm_sel   = isset($_POST['payment_mode_select']) ? sanitize_text_field(wp_unslash($_POST['payment_mode_select'])) : '';
          $pm_other = isset($_POST['payment_mode_other']) ? sanitize_text_field(wp_unslash($_POST['payment_mode_other'])) : '';

          if ($pm_sel === 'Other') {
            $fields['payment_mode'] = $pm_other;
          } else {
            $fields['payment_mode'] = $pm_sel; // blank or standard option
          }

          // Insurance effective/expiration (store as Y-m-d)
          $ins_eff_in = isset($_POST['insurance_effective_date']) ? coai_md_norm_date_ymd(wp_unslash($_POST['insurance_effective_date'])) : '';
          $ins_exp_in = isset($_POST['insurance_expiration_date']) ? coai_md_norm_date_ymd(wp_unslash($_POST['insurance_expiration_date'])) : '';
          if ($ins_eff_in !== '') $fields['insurance_effective_date'] = $ins_eff_in;
          if ($ins_exp_in !== '') $fields['insurance_expiration_date'] = $ins_exp_in;

          if (isset($_POST['membership_level_id'])) {
            $fields['membership_level_id'] = (int) $_POST['membership_level_id'];
          }

          $fields['is_new_member']  = !empty($_POST['is_new_member']) ? 1 : 0;
          $fields['paid_manually']  = !empty($_POST['paid_manually']) ? 1 : 0;

          foreach ([
            'registered_date','membership_expiration','renewal_date',
            'birthday','manual_payment_date'
          ] as $df) {
            if (!array_key_exists($df, $_POST)) continue;

            $raw = trim((string) wp_unslash($_POST[$df] ?? ''));
            if ($raw === '') continue;

            $ts = strtotime($raw);
            if (!$ts) continue;

            if ($df === 'birthday') {
              // birthday is a DATE column
              $fields['birthday'] = date('Y-m-d', $ts);

              // keep date_of_birth synced too if that column exists
              if (in_array('date_of_birth', $cols, true)) {
                $fields['date_of_birth'] = date('Y-m-d', $ts);
              }
            } else {
              // existing behavior for datetime-style fields
              $fields[$df] = date('Y-m-d H:i:s', $ts);
            }
          }

          // -------------------------------------------------
          // AUTO-SYNC status from membership_expiration
          // Rule:
          // - Never override DECEASED or ARCHIVED
          // - If expiration is before today => EXPIRED
          // - Otherwise => ACTIVE
          // -------------------------------------------------
          $current_status = strtoupper(trim((string)($fields['status'] ?? ($row['status'] ?? ''))));

          if (!in_array($current_status, ['DECEASED', 'ARCHIVED'], true)) {
            $exp_raw = (string)($fields['membership_expiration'] ?? ($row['membership_expiration'] ?? ''));

            if ($exp_raw !== '' && $exp_raw !== '0000-00-00 00:00:00') {
              $exp_ts   = strtotime($exp_raw);
              $today_ts = strtotime(current_time('Y-m-d 00:00:00'));

              if ($exp_ts && $exp_ts < $today_ts) {
                $fields['status'] = 'EXPIRED';
              } else {
                $fields['status'] = 'ACTIVE';
              }
            }
          }

          // -------------------------------------------------
          // AUTO-SAVE: any other DB columns posted that are not in the curated core lists
          // -------------------------------------------------
          $core_keys = [
            // core text/email already handled
            'username','full_name','email','usergroup','phone','mobile',
            'first_name','last_name',
            'address','address2','city','state','zip','country','region',
           'shipping_address','shipping_address2','shipping_city','shipping_state','shipping_zip','shipping_country',
            'billing_address2',
            'clown_name','parent_name','e_contact','alley_membership',
            'internal_comments',

            // core meta/finance handled
            'member_number','insurance_status','status',
            'payment_amount','payment_mode','check_number',
            'insurance_effective_date','insurance_expiration_date',
            'membership_level_id',
            'registered_date','membership_expiration','renewal_date',
            'birthday','manual_payment_date',
            'is_new_member','paid_manually',

            // special handled fields (do NOT treat as generic)
            'coai_number','COAI_number','coai_number_reason',
            'password', 'force_password_change',

            // protected / read-only
            'member_id','created_at','updated_at',
          ];

          $core_lc = array_fill_keys(array_map('strtolower', $core_keys), true);

          // We will allow generic save for everything else that exists in the table.
          $describe = coai_md_describe_table($table);
          foreach ($describe as $colinfo) {
            $col = (string)($colinfo['Field'] ?? '');
            if ($col === '') continue;

            $lc = strtolower($col);
            if (isset($core_lc[$lc])) continue;              // already handled
            if (!array_key_exists($col, $_POST)) continue;   // not posted

            // sanitize based on type
            $type = (string)($colinfo['Type'] ?? 'text');
            $inputType = coai_md_field_input_type($type);

            if ($inputType === 'checkbox') {
              $fields[$col] = !empty($_POST[$col]) ? 1 : 0;
            } elseif ($inputType === 'textarea') {
              $fields[$col] = sanitize_textarea_field(wp_unslash($_POST[$col]));
            } elseif ($inputType === 'number') {
              $raw = trim((string)wp_unslash($_POST[$col]));
              $fields[$col] = ($raw === '') ? '' : $raw; // let DB type coerce; keep blank if blank
            } elseif ($inputType === 'date') {
              $raw = trim((string)wp_unslash($_POST[$col]));
              $fields[$col] = $raw ? coai_md_norm_date_ymd($raw) : '';
            } elseif ($inputType === 'datetime-local') {
              $raw = trim((string)wp_unslash($_POST[$col]));
              if ($raw !== '') {
                // HTML datetime-local = 2026-02-03T14:30
                $raw = str_replace('T', ' ', $raw) . ':00';
                $ts = strtotime($raw);
                if ($ts) $fields[$col] = date('Y-m-d H:i:s', $ts);
              }
            } else {
              $fields[$col] = sanitize_text_field(wp_unslash($_POST[$col]));
            }
          }

          // never let staff overwrite created_at
          unset($fields['created_at']);

          // Clean to real columns only (case safe)
          $real_cols = $wpdb->get_col("DESC `$table`", 0);
          if (is_array($real_cols) && $real_cols) {
            $col_map = [];
            foreach ($real_cols as $c) $col_map[strtolower($c)] = $c;

            $clean = [];
            foreach ($fields as $k => $v) {
              $lk = strtolower($k);
              if (isset($col_map[$lk])) $clean[$col_map[$lk]] = $v;
            }
            $fields = $clean;
          }

          // COAI number handling: write ONLY into detected coai column
          $coai_col = coai_get_coai_column_name($table);

          unset($fields['COAI_number']);
          unset($fields['coai_number']);

          $old = trim((string)($row[$coai_col] ?? ''));
          $new = isset($_POST['COAI_number']) ? trim((string) wp_unslash($_POST['COAI_number'])) : '';
          $reason = trim((string) wp_unslash($_POST['coai_number_reason'] ?? ''));

          $final_status = strtoupper(trim((string)($fields['status'] ?? ($row['status'] ?? ''))));

          $coai_changed = false;
          $coai_old_val = $old;
          $coai_new_val = $new;

          if ($final_status === 'ACTIVE' && $new === '') {
            $msg .= '<div class="notice notice-error">Active members must have a COAI #.</div>';
            $coai_block_save = true;
          }

          if (!$coai_block_save && $new !== '' && $new !== $old) {
            if (!preg_match('/^[A-Za-z0-9\-]+$/', $new)) {
              $msg .= '<div class="notice notice-error">COAI # not saved: invalid format. (letters/numbers/dashes only)</div>';
              $coai_block_save = true;
            } else {
              $dupe_mid = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT member_id FROM `$table` WHERE `$coai_col`=%s AND member_id<>%d LIMIT 1",
                $new, $mid
              ));
              if ($dupe_mid > 0) {
                $msg .= '<div class="notice notice-error">COAI # already belongs to Member ID '.(int)$dupe_mid.'. Use suffix (a/b) if needed.</div>';
                $coai_block_save = true;
              } else {
                $fields[$coai_col] = sanitize_text_field($new);
                $coai_changed = true;

                $actor = wp_get_current_user();
                $actor_label = $actor ? ($actor->user_login . ' (#' . (int)$actor->ID . ')') : 'unknown';

                error_log(sprintf(
                  '[COAI] COAI_number CHANGE mid=%d col=%s old="%s" new="%s" by=%s reason="%s"',
                  (int)$mid, $coai_col, $old, $new, $actor_label, $reason
                ));
              }
            }
          }

          if ($coai_block_save) {
            // do not save anything if COAI guard failed
          } else {

            // Append internal_comments note if insurance fields changed
            $actor_user  = wp_get_current_user();
            $actor_login = $actor_user ? (string)$actor_user->user_login : 'unknown';

            $old_ins_status = trim((string)($row['insurance_status'] ?? ''));
            $old_ins_eff    = trim((string)($row['insurance_effective_date'] ?? ''));
            $old_ins_exp    = trim((string)($row['insurance_expiration_date'] ?? ''));

            $new_ins_status = array_key_exists('insurance_status', $fields)
              ? trim((string)$fields['insurance_status'])
              : $old_ins_status;

            $new_ins_eff = array_key_exists('insurance_effective_date', $fields)
              ? trim((string)$fields['insurance_effective_date'])
              : $old_ins_eff;

            $new_ins_exp = array_key_exists('insurance_expiration_date', $fields)
              ? trim((string)$fields['insurance_expiration_date'])
              : $old_ins_exp;

            $ins_changes = [];
            if ($new_ins_status !== '' && $new_ins_status !== $old_ins_status) {
              $ins_changes[] = 'insurance_status changed from "' . $old_ins_status . '" to "' . $new_ins_status . '" by ' . $actor_login;
            }
            if ($new_ins_eff !== '' && $new_ins_eff !== $old_ins_eff) {
              $ins_changes[] = 'insurance_effective_date changed from "' . $old_ins_eff . '" to "' . $new_ins_eff . '" by ' . $actor_login;
            }
            if ($new_ins_exp !== '' && $new_ins_exp !== $old_ins_exp) {
              $ins_changes[] = 'insurance_expiration_date changed from "' . $old_ins_exp . '" to "' . $new_ins_exp . '" by ' . $actor_login;
            }

            if (!empty($ins_changes)) {
              $base_comments = array_key_exists('internal_comments', $fields)
                ? (string)$fields['internal_comments']
                : (string)($row['internal_comments'] ?? '');

              foreach ($ins_changes as $line) {
                $base_comments = coai_md_append_internal_comment($base_comments, 'Insurance update: ' . $line);
              }
              $fields['internal_comments'] = $base_comments;
            }
            
            // -------------------------------------------------
            // Append internal_comments note if deletion fields changed
            // -------------------------------------------------
            $old_deleted_at     = trim((string)($row['deleted_at'] ?? ''));
            $old_deleted_reason = trim((string)($row['deleted_reason'] ?? ''));
            $old_deleted_by_name = trim((string)($row['deleted_by_name'] ?? ''));

            // If your table doesn't have deleted_by_name yet, this will be blank until you add the column.
            // (After you add the column, it will work.)

            $new_deleted_at = array_key_exists('deleted_at', $fields)
              ? trim((string)$fields['deleted_at'])
              : $old_deleted_at;

            $new_deleted_reason = array_key_exists('deleted_reason', $fields)
              ? trim((string)$fields['deleted_reason'])
              : $old_deleted_reason;

            $new_deleted_by_name = array_key_exists('deleted_by_name', $fields)
              ? trim((string)$fields['deleted_by_name'])
              : $old_deleted_by_name;

            $del_summary_parts = [];

            if ($new_deleted_at !== '' && $new_deleted_at !== $old_deleted_at) {
              $del_summary_parts[] = 'date "' . $old_deleted_at . '" → "' . $new_deleted_at . '"';
            }

            if ($new_deleted_reason !== '' && $new_deleted_reason !== $old_deleted_reason) {
              $del_summary_parts[] = 'reason "' . $old_deleted_reason . '" → "' . $new_deleted_reason . '"';
            }

            if ($new_deleted_by_name !== '' && $new_deleted_by_name !== $old_deleted_by_name) {
              $del_summary_parts[] = 'by "' . $old_deleted_by_name . '" → "' . $new_deleted_by_name . '"';
            }

            if (!empty($del_summary_parts)) {
              $base_comments = array_key_exists('internal_comments', $fields)
                ? (string)$fields['internal_comments']
                : (string)($row['internal_comments'] ?? '');

              $base_comments = coai_md_append_internal_comment(
                $base_comments,
                'Deletion update: ' . implode('; ', $del_summary_parts) . ' (staff=' . $actor_login . ')'
              );

              $fields['internal_comments'] = $base_comments;
            }

            error_log('[COAI] EDIT save_member mid=' . $mid . ' fields=' . implode(',', array_keys($fields)));

            // AUDIT snapshots (if available)
            $before_full = [];
            if (function_exists('coai_audit_log_update')) {
              $before_full = (array) $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", (int)$mid),
                ARRAY_A
              );
            }

            $updated = $wpdb->update($table, $fields, ['member_id' => $mid]);

            if ($updated > 0 && function_exists('coai_audit_log_update')) {
              $after_full = (array) $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", (int)$mid),
                ARRAY_A
              );

              $note = $coai_changed
                ? ('Admin edit saved (COAI changed: ' . $coai_old_val . ' → ' . $coai_new_val . '). ' . ($reason ? 'Reason: ' . $reason : ''))
                : 'Admin edit saved.';

              if (!empty($ins_changes)) $note .= ' Insurance fields updated.';

              coai_audit_log_update((int)$mid, $before_full, $after_full, $note);
            }

            if ($updated === false) {
              $msg .= '<div class="notice notice-error">Update failed: '.esc_html($wpdb->last_error).'</div>';
            } else {
              $msg .= '<div class="notice notice-success">'.($updated === 0 ? 'No changes to save.' : 'Member updated.').'</div>';

              if (!empty($coai_changed)) {
                $msg .= '<div class="notice notice-warning">⚠️ COAI # changed from <strong>'.esc_html($coai_old_val).'</strong> to <strong>'.esc_html($coai_new_val).'</strong>.</div>';
              }
              if (!empty($ins_changes)) {
                $msg .= '<div class="notice notice-warning">📝 Insurance change noted in Internal Comments.</div>';
              }
            }

            // Reload row
            $coai_col = coai_get_coai_column_name($table);
            $row = $wpdb->get_row(
              $wpdb->prepare("
                SELECT m.*,
                       m.`$coai_col` AS coai_pick,
                       lvl.name AS membership_level_name
                FROM `$table` m
                LEFT JOIN `$levels_table` lvl ON lvl.`$lvl_id_col` = m.membership_level_id
                WHERE m.member_id=%d
                LIMIT 1
              ", $mid),
              ARRAY_A
            );
            if (!$row) return '<div class="notice notice-error">Member not found after update.</div>';
          }
        }
      }
    }

    $date_fmt = function($v){
      if (!$v) return '';
      $ts = strtotime($v);
      return ($ts && $ts > 0) ? date('Y-m-d', $ts) : '';
    };

    $coai_val = (string)($row['coai_pick'] ?? '');
    $back_url = wp_get_referer() ?: home_url('/member-directory/');
    $back_url = remove_query_arg(['mid','member_id'], $back_url);

    $ins_eff_val = !empty($row['insurance_effective_date']) ? $date_fmt($row['insurance_effective_date']) : '';
    $ins_exp_val = !empty($row['insurance_expiration_date']) ? $date_fmt($row['insurance_expiration_date']) : '';
    
    $family_members = coai_get_family_members_for_member((int)$mid);

    ob_start();
    echo coai_md_styles();
    ?>
    <div class="coai-wrap">
      <div class="coai-toolbar">
        <div class="coai-left">
          <a class="coai-btn" href="<?php echo esc_url($portal_url); ?>">← Member Portal</a>
          <a class="coai-btn" href="<?php echo esc_url($back_url); ?>">← Back to Results</a>
        </div>
        <div class="coai-right">
          <span class="coai-pill">Editing Member ID <?php echo (int)$mid; ?></span>
        </div>
      </div>

      <?php echo $msg; ?>

      <form method="post" class="coai-form-wrap">
        <?php wp_nonce_field('coai_admin_edit_'.$mid, '_coai_edit_nonce'); ?>
        <input type="hidden" name="coai_admin_update_member" value="1" />

        <!-- REPLACEMENT START: Sectioned Member Edit Layout -->

<style>
  .coai-section{margin:14px 0 10px;}
  .coai-section h3{margin:0 0 10px;}
  .coai-section .coai-grid{margin-top:6px;}
  .coai-grid--1{grid-template-columns:1fr !important;}
  .coai-grid--2{grid-template-columns:repeat(2,minmax(0,1fr)) !important;}
  .coai-grid--3{grid-template-columns:repeat(3,minmax(0,1fr)) !important;}
  @media (max-width: 820px){
    .coai-grid--2,.coai-grid--3{grid-template-columns:1fr !important;}
  }
  .coai-field--full{grid-column:1 / -1;}
  .coai-readonly{background:#f8fafc;}
  .coai-subhead{margin:0 0 6px;font-size:12px;color:#64748b;}
</style>

<?php
  $can_manage = function_exists('coai_staff_can') ? coai_staff_can('manage') : current_user_can('administrator');
  $can_staff  = function_exists('coai_staff_can') ? (coai_staff_can('manage') || coai_staff_can('view') || coai_staff_can('finance') || coai_staff_can('newsletter')) : current_user_can('administrator');

  $status_val = strtoupper(trim((string)($row['status'] ?? '')));

  // Payment mode handling (preserve your existing behavior)
  $payment_amount_val = (string)($row['payment_amount'] ?? '');
  $check_number_val   = (string)($row['check_number'] ?? '');

  $payment_mode_val = trim((string)($row['payment_mode'] ?? ''));
  $payment_mode_opts = ['CC','Check','PayPal','Venmo','CashApp'];
  $is_other_mode = ($payment_mode_val !== '' && !in_array($payment_mode_val, $payment_mode_opts, true));
  $payment_mode_selected = $is_other_mode ? 'Other' : ($payment_mode_val ?: '');
?>

<style>
  .coai-form-wrap .coai-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px;
    flex-wrap:wrap;
  }
</style>


<div class="coai-section">
  <h3>Member</h3>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field">
      <label>Created At (read-only)</label>
      <input class="coai-input coai-readonly" readonly value="<?php echo esc_attr($row['created_at'] ?? ''); ?>">
    </div>

    <div class="coai-field coai-field--full" style="padding-top:2px;">
      <label style="display:flex;gap:10px;align-items:center;">
        <input type="checkbox" name="is_new_member" value="1" <?php checked(!empty($row['is_new_member'])); ?>>
        New Member (first year)
      </label>
    </div>
  </div>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field"><label>Username</label><input class="coai-input" name="username" value="<?php echo esc_attr($row['username'] ?? ''); ?>"></div>

    <div class="coai-field">
      <label>Full Name</label>
      <input class="coai-input coai-readonly" readonly value="<?php echo esc_attr($row['full_name'] ?? ''); ?>">
    </div>

    <div class="coai-field"><label>Email</label><input class="coai-input" name="email" value="<?php echo esc_attr($row['email'] ?? ''); ?>"></div>
  </div>

  <div class="coai-grid coai-grid--3">
  <div class="coai-field">
    <label>First Name</label>
    <input class="coai-input" name="first_name" value="<?php echo esc_attr($row['first_name'] ?? ''); ?>">
  </div>

  <div class="coai-field">
    <label>Last Name</label>
    <input class="coai-input" name="last_name" value="<?php echo esc_attr($row['last_name'] ?? ''); ?>">
  </div>

  <div class="coai-field">
    <label>Clown Name</label>
    <input class="coai-input" name="clown_name" value="<?php echo esc_attr($row['clown_name'] ?? ''); ?>" placeholder="e.g., Sparkles">
    <div class="coai-subtle" style="margin-top:6px;">Editable “stage/clown” name stored in <code>clown_name</code>.</div>
  </div>
</div>

<div class="coai-grid coai-grid--3">
  <div class="coai-field">
    <label>Phone</label>
    <input class="coai-input" name="phone" value="<?php echo esc_attr($row['phone'] ?? ''); ?>">
  </div>

  <div class="coai-field">
    <label>Mobile</label>
    <input class="coai-input" name="mobile" value="<?php echo esc_attr($row['mobile'] ?? ''); ?>">
  </div>

  <div class="coai-field">
    <label>Birthday</label>
    <input class="coai-input" type="date" name="birthday"
           value="<?php echo esc_attr(!empty($row['birthday']) ? date('Y-m-d', strtotime((string)$row['birthday'])) : (!empty($row['date_of_birth']) ? date('Y-m-d', strtotime((string)$row['date_of_birth'])) : '')); ?>">
  </div>
  
    <div class="coai-field"></div>
  </div>
  
  <div class="coai-section">
  <h3>Address</h3>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field coai-field--full">
      <label>Address</label>
      <input class="coai-input" name="address" value="<?php echo esc_attr($row['address'] ?? ''); ?>">
    </div>

    <div class="coai-field coai-field--full">
      <label>Address 2</label>
      <input class="coai-input" name="address2" value="<?php echo esc_attr($row['address2'] ?? ''); ?>">
    </div>
  </div>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field">
      <label>City</label>
      <input class="coai-input" name="city" value="<?php echo esc_attr($row['city'] ?? ''); ?>">
    </div>

    <div class="coai-field">
      <label>State</label>
      <input class="coai-input" name="state" value="<?php echo esc_attr($row['state'] ?? ''); ?>" maxlength="2" placeholder="VA">
      <div class="coai-subtle" style="margin-top:6px;">2-letter code for US states (e.g., VA).</div>
    </div>

    <div class="coai-field">
      <label>Zip</label>
      <input class="coai-input" name="zip" value="<?php echo esc_attr($row['zip'] ?? ''); ?>">
    </div>
  </div>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field">
      <label>Country</label>
      <input class="coai-input" name="country" value="<?php echo esc_attr($row['country'] ?? 'US'); ?>" placeholder="US">
      <div class="coai-subtle" style="margin-top:6px;">Use US / CA, or other country code/name.</div>
    </div>

    <div class="coai-field">
      <label>Region (auto)</label>
      <input class="coai-input" name="region" value="<?php echo esc_attr($row['region'] ?? ''); ?>" readonly>
      <div class="coai-subtle" style="margin-top:6px;">Auto-set from State/Country.</div>
    </div>
  </div>
</div>


  <div class="coai-grid coai-grid--3">
    <div class="coai-field">
      <label>COAI #</label>
      <input class="coai-input" name="COAI_number" value="<?php echo esc_attr($coai_val); ?>">
    </div>

    <div class="coai-field">
      <label>COAI Change Reason</label>
      <input class="coai-input" name="coai_number_reason" value="<?php echo esc_attr($reason ?? ''); ?>">
    </div>

    <div class="coai-field">
      <label>Usergroup</label>
      <input class="coai-input" name="usergroup" value="<?php echo esc_attr($row['usergroup'] ?? ''); ?>">
    </div>
  </div>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field"><label>Registered Date</label><input class="coai-input" type="date" name="registered_date" value="<?php echo esc_attr($date_fmt($row['registered_date'] ?? '')); ?>"></div>
    <div class="coai-field"><label>Renewal Date</label><input class="coai-input" type="date" name="renewal_date" value="<?php echo esc_attr($date_fmt($row['renewal_date'] ?? '')); ?>"></div>
    <div class="coai-field"><label>Membership Expiration Date</label><input class="coai-input" type="date" name="membership_expiration" value="<?php echo esc_attr($date_fmt($row['membership_expiration'] ?? '')); ?>"></div>
  </div>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field">
      <label>Membership Level</label>
      <select class="coai-select" name="membership_level_id">
        <option value="">—</option>
        <?php foreach ($levels as $lvl): ?>
          <option value="<?php echo (int)$lvl['id']; ?>" <?php selected((int)($row['membership_level_id'] ?? 0), (int)$lvl['id']); ?>>
            <?php echo esc_html($lvl['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="coai-field">
      <label>Alley Membership</label>
      <select class="coai-select" name="alley_membership">
        <?php
          $alley_val = trim((string)($row['alley_membership'] ?? ''));
          $alley_opts = ['' => '—','No'=>'No','Yes'=>'Yes'];
          foreach ($alley_opts as $k=>$lab):
        ?>
          <option value="<?php echo esc_attr($k); ?>" <?php selected($alley_val, (string)$k); ?>><?php echo esc_html($lab); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="coai-field">
      <label>Status</label>
      <select class="coai-select" name="status">
        <?php
          $opts = ['ACTIVE','EXPIRED','DECEASED','ARCHIVED'];
          foreach ($opts as $opt):
        ?>
          <option value="<?php echo esc_attr($opt); ?>" <?php selected($status_val, $opt); ?>>
            <?php echo esc_html(ucfirst(strtolower($opt))); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<div class="coai-section">
  <h3>Insurance</h3>
  <div class="coai-grid coai-grid--3">
    <div class="coai-field"><label>Insurance Status</label><input class="coai-input" name="insurance_status" value="<?php echo esc_attr($row['insurance_status'] ?? ''); ?>"></div>
    <div class="coai-field"><label>Policy Effective Date</label><input class="coai-input" type="date" name="insurance_effective_date" value="<?php echo esc_attr($ins_eff_val); ?>"></div>
    <div class="coai-field"><label>Policy Expiration Date</label><input class="coai-input" type="date" name="insurance_expiration_date" value="<?php echo esc_attr($ins_exp_val); ?>"></div>
  </div>
</div>

<div class="coai-section">
  <h3>Financial</h3>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field">
      <label>Payment Mode</label>
      <select class="coai-select" name="payment_mode_select" id="payment_mode_select">
        <option value="">—</option>
        <?php foreach ($payment_mode_opts as $opt): ?>
          <option value="<?php echo esc_attr($opt); ?>" <?php selected($payment_mode_selected, $opt); ?>>
            <?php echo esc_html($opt); ?>
          </option>
        <?php endforeach; ?>
        <option value="Other" <?php selected($payment_mode_selected, 'Other'); ?>>Other</option>
      </select>
    </div>

    <div class="coai-field">
      <label>Payment Amount</label>
      <input class="coai-input" name="payment_amount" value="<?php echo esc_attr($payment_amount_val); ?>" placeholder="0.00">
    </div>

    <div class="coai-field" style="padding-top:28px;">
      <label style="display:flex;gap:10px;align-items:center;">
        <input type="checkbox" name="paid_manually" value="1" <?php checked(!empty($row['paid_manually'])); ?>>
        Paid Manually (Yes/No)
      </label>
    </div>
  </div>

  <div class="coai-grid coai-grid--3">
    <div class="coai-field" id="payment_mode_other_wrap" style="<?php echo $payment_mode_selected === 'Other' ? '' : 'display:none;'; ?>">
      <label>Payment Mode (Other)</label>
      <input class="coai-input" type="text" name="payment_mode_other" id="payment_mode_other"
             value="<?php echo esc_attr($is_other_mode ? $payment_mode_val : ''); ?>"
             placeholder="Type custom mode">
    </div>

    <div class="coai-field" id="check_number_wrap">
      <label>Check Number</label>
      <input class="coai-input" name="check_number" value="<?php echo esc_attr($check_number_val); ?>">
    </div>

    <div class="coai-field">
      <label>Manual Payment Date</label>
      <input class="coai-input" type="date" name="manual_payment_date" value="<?php echo esc_attr($date_fmt($row['manual_payment_date'] ?? '')); ?>">
    </div>
  </div>

  <script>
  (function(){
    const sel = document.getElementById('payment_mode_select');
    const otherWrap = document.getElementById('payment_mode_other_wrap');
    const other = document.getElementById('payment_mode_other');
    const checkWrap = document.getElementById('check_number_wrap');

    function sync(){
      if(!sel) return;

      const isOther = sel.value === 'Other';
      if (otherWrap) otherWrap.style.display = isOther ? '' : 'none';
      if (!isOther && other) other.value = '';

      const isCheck = sel.value === 'Check';
      if (checkWrap) checkWrap.style.display = isCheck ? '' : 'none';
    }

    if (sel) sel.addEventListener('change', sync);
    sync();
  })();
  </script>
</div>

<div class="coai-section">
  <h3>Internal (Staff Only)</h3>

  <?php if ($can_staff): ?>

    <?php if ($can_manage): ?>
      <div class="coai-grid coai-grid--1">
        <div class="coai-field">
          <label>Internal Comments (Admin/Manager only)</label>
          <textarea class="coai-input" name="internal_comments" rows="6" style="width:100%;resize:vertical;"><?php echo esc_textarea((string)($row['internal_comments'] ?? '')); ?></textarea>
        </div>
      </div>
    <?php else: ?>
      <div class="notice"><div class="coai-subtle">Internal Comments hidden (Admin/Manager only).</div></div>
    <?php endif; 

    ?>

    <div class="coai-grid coai-grid--3">
      <div class="coai-field">
        <label>Deleted Date</label>
        <input class="coai-input" type="date" name="deleted_at"
          value="<?php echo esc_attr(!empty($row['deleted_at']) ? date('Y-m-d', strtotime((string)$row['deleted_at'])) : ''); ?>">
      </div>

      <div class="coai-field">
        <label>Deleted By (Admin/Manager Name)</label>
        <?php
          $deleted_by_name_input = (string)($row['deleted_by_name'] ?? '');

          if ($deleted_by_name_input === '') {
            $raw = (string)($row['deleted_by'] ?? '');
            if ($raw !== '' && ctype_digit($raw) && function_exists('coai_md_resolve_member_name')) {
              $resolved = coai_md_resolve_member_name((int)$raw);
              if (!empty($resolved)) $deleted_by_name_input = (string)$resolved;
            } elseif ($raw !== '' && !ctype_digit($raw)) {
              // if deleted_by already contains a name/email, use it
              $deleted_by_name_input = $raw;
            }
          }
        ?>
        <input class="coai-input" name="deleted_by_name"
               value="<?php echo esc_attr($deleted_by_name_input); ?>"
               placeholder="Admin/Manager name">
        <div class="coai-subtle" style="margin-top:6px;">This should be a name (not an ID).</div>
      </div>

      <div class="coai-field">
        <label>Deleted Reason</label>
        <input class="coai-input" name="deleted_reason"
               value="<?php echo esc_attr((string)($row['deleted_reason'] ?? '')); ?>">
      </div>
      
      <?php
        $del_date_disp   = $date_fmt($row['deleted_at'] ?? '');
        $del_reason_disp = trim((string)($row['deleted_reason'] ?? ''));

        if ($del_date_disp || $del_reason_disp):
      ?>
        <div class="coai-field coai-field--full">
          <div class="coai-subtle" style="margin-top:6px;">
            <strong>Deleted:</strong>
            <?php echo esc_html($del_date_disp ?: '—'); ?>
            <?php if ($del_reason_disp): ?>
              — <?php echo esc_html($del_reason_disp); ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

    </div>

  <?php else: ?>

    <div class="notice"><div class="coai-subtle">Internal section is staff-only.</div></div>

  <?php endif; ?>
</div>

<div class="coai-section">
  <h3>Security / Password</h3>

  <div class="notice">
    <div class="coai-subtle">
      This resets the <strong>Member Login</strong> password stored in <code>wp_members.password</code>.
      WordPress admin passwords are managed in WP only.
    </div>
  </div>

  <style>
  .coai-passwrap{
    position:relative;
    display:flex;
    align-items:center;
    width:100%;
  }
  .coai-passwrap > input.coai-input{
    width:100%;
    padding-right:44px; /* room for the eye button */
    box-sizing:border-box;
  }
  .coai-passwrap > button.coai-toggle{
    position:absolute;
    right:10px;
    top:50%;
    transform:translateY(-50%);
    border:0;
    background:transparent;
    padding:6px;
    cursor:pointer;
    line-height:1;
    z-index:5;
  }
  
  .coai-btn-danger {
    border-color: #b91c1c !important;
    color: #b91c1c !important;
    background: #fff !important;
  }

  .coai-btn-danger:hover {
    background: #b91c1c !important;
    color: #fff !important;
  }
  
  /* Base buttons already exist: .coai-btn */

  /* Primary (Add) */
  .coai-btn-primary {
    background: #111;
    color: #fff;
    border-color: #111;
  }

  .coai-btn-primary:hover {
    background: #333;
    color: #fff;
  }

  /* Danger (Delete) */
  .coai-btn-danger {
    background: #fff;
    color: #b91c1c;
    border: 1px solid #b91c1c;
  }

  .coai-btn-danger:hover {
    background: #b91c1c;
    color: #fff;
  }
  
  .coai-family-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    margin-top: 10px;
  }

  .coai-family-actions .coai-btn {
    width: 178px;
    min-height: 40px;
    padding: 8px 12px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    line-height: 1.2 !important;
    text-align: center !important;
    box-sizing: border-box;
  }
  </style>

  <div class="coai-grid coai-grid--3">
  <div class="coai-field">
    <label>Changed Member Login Password</label>

    <div class="coai-passwrap">
      <input class="coai-input"
             type="password"
             name="new_member_password"
             value=""
             autocomplete="new-password"
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">

      <button type="button"
              class="coai-toggle"
              aria-label="Show password"
              aria-pressed="false"
              title="Show password">
        <!-- eye (on) -->
        <svg class="coai-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <!-- eye (off) -->
        <svg class="coai-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none">
          <path d="M3 3l18 18M9.9 9.9A3.25 3.25 0 0012 15.25c4.75 0 8-3.25 10-6.25-.63-.98-1.41-1.96-2.36-2.85M7 7C4.93 8.38 3.5 10.12 2 12c1.52 2.09 3.58 4.09 6.53 5.35"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>

  <div class="coai-field">
    <label>Confirm Changed Password</label>

    <div class="coai-passwrap">
      <input class="coai-input"
             type="password"
             name="new_member_password_confirm"
             value=""
             autocomplete="new-password"
             style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">

      <button type="button"
              class="coai-toggle"
              aria-label="Show password"
              aria-pressed="false"
              title="Show password">
        <!-- eye (on) -->
        <svg class="coai-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        <!-- eye (off) -->
        <svg class="coai-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none">
          <path d="M3 3l18 18M9.9 9.9A3.25 3.25 0 0012 15.25c4.75 0 8-3.25 10-6.25-.63-.98-1.41-1.96-2.36-2.85M7 7C4.93 8.38 3.5 10.12 2 12c1.52 2.09 3.58 4.09 6.53 5.35"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>
  </div>
  
  <div class="coai-field" style="padding-top:28px;">
    <?php wp_nonce_field('coai_admin_member_pw_'.$mid, '_coai_member_pw_nonce'); ?>
    <button class="coai-btn" type="submit" name="coai_admin_set_member_password" value="1" style="font-weight:600;">
      Set Member Password
    </button>
  </div>
</div>

<!-- REPLACEMENT END -->

<!-- Keep the JS inside the form (fine), but keep buttons inside the LAST section box -->
<div class="coai-section">
  <div class="coai-actions" style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
    <button class="coai-btn" type="submit" style="font-weight:600;">Save Changes</button>
    <a class="coai-btn" href="<?php echo esc_url($back_url); ?>">Cancel</a>
  </div>
</div>

<script>
document.addEventListener('click', function (e) {
  const btn = e.target.closest('.coai-toggle');
  if (!btn) return;

  const wrap = btn.closest('.coai-passwrap');
  if (!wrap) return;

  const input = wrap.querySelector('input');
  if (!input) return;

  const eye = btn.querySelector('.coai-eye');
  const eyeOff = btn.querySelector('.coai-eye-off');

  const show = (input.type === 'password');
  input.type = show ? 'text' : 'password';

  btn.setAttribute('aria-pressed', show ? 'true' : 'false');
  btn.title = show ? 'Hide password' : 'Show password';

  if (eye && eyeOff) {
    eye.style.display = show ? 'none' : 'inline';
    eyeOff.style.display = show ? 'inline' : 'none';
  }
});
</script>

<script>
(function(){
  function regionFrom(state, country){
    state = (state || '').toUpperCase().trim();
    country = (country || '').toUpperCase().trim();
    if (country === 'USA') country = 'US';

    if (country === 'CA') return 'Canada';
    if (country && country !== 'US') return 'International';

    const northeast = new Set(['CT','ME','MA','NH','RI','VT','NJ','NY','PA']);
    const midwest   = new Set(['IL','IN','MI','OH','WI','IA','KS','MN','MO','NE','ND','SD']);
    const south     = new Set(['DE','DC','FL','GA','MD','NC','SC','VA','WV','AL','KY','MS','TN','AR','LA','OK','TX']);
    const west      = new Set(['AZ','CO','ID','MT','NV','NM','UT','WY','AK','CA','HI','OR','WA']);
    const terr      = new Set(['PR','GU','MP','VI','AS']);

    if (northeast.has(state)) return 'Northeast';
    if (midwest.has(state)) return 'Midwest';
    if (south.has(state)) return 'South';
    if (west.has(state)) return 'West';
    if (terr.has(state)) return 'International';
    return '';
  }

  function bind(){
    const st = document.querySelector('[name="state"]');
    const ct = document.querySelector('[name="country"]');
    const rg = document.querySelector('[name="region"]');
    if (!st || !rg) return;

    function update(){
      const val = regionFrom(st.value, ct ? ct.value : 'US');
      if (val) rg.value = val;
    }
    st.addEventListener('change', update);
    st.addEventListener('input', update);
    if (ct) ct.addEventListener('change', update);
    if (!rg.value) update();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
</script>

</form>

<div class="coai-form-wrap">
  <div class="coai-section">
    <h3>Family Members</h3>
    <p class="coai-subtle" style="margin:0 0 12px;">
      Linked family members connected to this primary member account.
    </p>

    <?php if (!empty($family_members)): ?>
      <?php foreach ($family_members as $family): ?>
        <form method="post" style="border:1px solid #e5e7eb;border-radius:14px;padding:12px;margin:0 0 12px;background:#f9fafb;">
          <?php wp_nonce_field('coai_family_members_' . (int)$mid, '_coai_family_nonce'); ?>
          <input type="hidden" name="coai_family_action" value="update_family_member">
          <input type="hidden" name="family_id" value="<?php echo esc_attr((int)$family['id']); ?>">

          <div class="coai-grid coai-grid--3">
            <div class="coai-field">
              <label>First Name</label>
              <input class="coai-input" name="family_first_name" required value="<?php echo esc_attr($family['first_name'] ?? ''); ?>">
            </div>

            <div class="coai-field">
              <label>Last Name</label>
              <input class="coai-input" name="family_last_name" required value="<?php echo esc_attr($family['last_name'] ?? ''); ?>">
            </div>

            <div class="coai-field">
              <label>Relationship</label>
              <input class="coai-input" name="family_relationship" value="<?php echo esc_attr($family['relationship'] ?? ''); ?>" placeholder="Spouse, Child, etc.">
            </div>

            <div class="coai-field">
              <label>Email</label>
              <input class="coai-input" type="email" name="family_email" value="<?php echo esc_attr($family['email'] ?? ''); ?>">
            </div>

            <div class="coai-field">
              <label>Phone</label>
              <input class="coai-input" name="family_phone" value="<?php echo esc_attr($family['phone'] ?? ''); ?>">
            </div>

            <div class="coai-field">
              <label>Birthday</label>
              <input class="coai-input" type="date" name="family_birthday"
                     value="<?php echo esc_attr(!empty($family['birthday']) ? date('Y-m-d', strtotime((string)$family['birthday'])) : ''); ?>">
            </div>

            <div class="coai-field">
              <label>Status</label>
              <?php $family_status = strtoupper($family['status'] ?? 'ACTIVE'); ?>
              <select class="coai-select" name="family_status">
                <option value="ACTIVE" <?php selected($family_status, 'ACTIVE'); ?>>ACTIVE</option>
                <option value="EXPIRED" <?php selected($family_status, 'EXPIRED'); ?>>EXPIRED</option>
                <option value="ARCHIVED" <?php selected($family_status, 'ARCHIVED'); ?>>ARCHIVED</option>
              </select>
            </div>
          </div>

          <div class="coai-actions" style="margin-top:10px;justify-content:flex-end;">
            <button class="coai-btn"
                    type="submit"
                    name="coai_family_action"
                    value="update_family_member"
                    style="background:#111;color:#fff;">
              Update Family Member
            </button>
          </div>
        </form>

        <form method="post" onsubmit="return confirm('Remove this family member?');" style="display:flex;justify-content:flex-end;margin:-6px 0 14px;">
          <?php wp_nonce_field('coai_family_members_' . (int)$mid, '_coai_family_nonce'); ?>
          <input type="hidden" name="coai_family_action" value="delete_family_member">
          <input type="hidden" name="family_id" value="<?php echo esc_attr((int)$family['id']); ?>">
          
          <button class="coai-btn" type="submit" style="border-color:#b91c1c;color:#b91c1c;">
            Remove Family Member
          </button>
        </form>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="coai-subtle">No family members listed.</p>
    <?php endif; ?>

    <hr style="margin:18px 0;border:none;border-top:1px solid #e5e7eb;">

    <h4 style="margin:0 0 10px;">Add Family Member</h4>

    <form method="post">
      <?php wp_nonce_field('coai_family_members_' . (int)$mid, '_coai_family_nonce'); ?>
      <input type="hidden" name="coai_family_action" value="add_family_member">

      <div class="coai-grid coai-grid--3">
        <div class="coai-field">
          <label>First Name</label>
          <input class="coai-input" name="family_first_name" required>
        </div>

        <div class="coai-field">
          <label>Last Name</label>
          <input class="coai-input" name="family_last_name" required>
        </div>

        <div class="coai-field">
          <label>Relationship</label>
          <input class="coai-input" name="family_relationship" placeholder="Spouse, Child, etc.">
        </div>

        <div class="coai-field">
          <label>Email</label>
          <input class="coai-input" type="email" name="family_email">
        </div>

        <div class="coai-field">
          <label>Phone</label>
          <input class="coai-input" name="family_phone">
        </div>

        <div class="coai-field">
          <label>Birthday</label>
          <input class="coai-input" type="date" name="family_birthday">
        </div>

        <div class="coai-field">
          <label>Status</label>
          <select class="coai-select" name="family_status">
            <option value="ACTIVE">ACTIVE</option>
            <option value="EXPIRED">EXPIRED</option>
            <option value="ARCHIVED">ARCHIVED</option>
          </select>
        </div>
      </div>

      <div class="coai-actions" style="margin-top:10px;justify-content:flex-end;align-items:center;">

        <button class="coai-btn coai-btn-primary"
                type="submit"
                name="coai_family_action"
                value="add_family_member">
          Add Family Member
        </button>

      </div>
    </form>
</div>

</div> <!-- ✅ CLOSE .coai-wrap (critical so footer is NOT inside) -->

    <?php
    return ob_get_clean();
  }
}
// ------------------------------------------------------------
// Regional CSV export
// - Region is always resolved from the logged-in RVP assignment.
// - URL-supplied region values are ignored.
// ------------------------------------------------------------
add_action('template_redirect', function () {
  if (!is_page('regional-member-directory')) return;
  if (empty($_GET['coai_regional_export'])) return;

  if (!is_user_logged_in() || !function_exists('coai_user_can') || !coai_user_can('view_region_members')) {
    wp_die('Unauthorized', '', 403);
  }

  if (
    empty($_GET['_coai_regional_export_nonce']) ||
    !wp_verify_nonce($_GET['_coai_regional_export_nonce'], 'coai_regional_export')
  ) {
    wp_die('Bad nonce', '', 400);
  }

  $member_id = (int) get_user_meta(get_current_user_id(), 'coai_member_id', true);
  $assigned_region = ($member_id > 0 && function_exists('coai_get_active_rvp_region_for_member'))
    ? coai_get_active_rvp_region_for_member($member_id)
    : '';

  if ($assigned_region === '') {
    wp_die('No active Regional Vice President assignment was found.', '', 403);
  }

  global $wpdb;
  $table = coai_get_members_table();

  $filter_params = $_GET;
  $filter_params['coai_region'] = $assigned_region;
  $filter_params['include_archived'] = !empty($_GET['include_archived']) ? 1 : 0;
  unset(
    $filter_params['region'],
    $filter_params['level_id'],
    $filter_params['level_name'],
    $filter_params['coai_regional_export'],
    $filter_params['_coai_regional_export_nonce']
  );

  $f = coai_md_build_filters($table, $filter_params);
  $where = $f['where'];
  $args = $f['args'];
  $join_sql = $f['join_sql'];

  $coai_col = coai_get_coai_column_name($table);
  $sql = "
    SELECT
      `$table`.member_number AS `Member Number`,
      `$table`.`$coai_col` AS `COAI Number`,
      `$table`.username AS `Username`,
      `$table`.full_name AS `Full Name`,
      `$table`.first_name AS `First Name`,
      `$table`.last_name AS `Last Name`,
      `$table`.email AS `Email`,
      COALESCE(NULLIF(`$table`.mobile,''), `$table`.phone) AS `Phone`,
      `$table`.clown_name AS `Clown Name`,
      `$table`.address AS `Address`,
      `$table`.address2 AS `Address 2`,
      `$table`.city AS `City`,
      `$table`.state AS `State`,
      `$table`.zip AS `ZIP`,
      `$table`.country AS `Country`,
      `$table`.region AS `Region`,
      `$table`.status AS `Status`,
      `$table`.renewal_date AS `Renewal Date`,
      `$table`.membership_expiration AS `Membership Expiration`,
      `$table`.insurance_status AS `Insurance Status`,
      `$table`.insurance_effective_date AS `Insurance Effective Date`,
      `$table`.insurance_expiration_date AS `Insurance Expiration Date`
    FROM `$table`
    $join_sql
    $where
    ORDER BY `$table`.last_name, `$table`.first_name, `$table`.username
  ";

  $rows = !empty($args)
    ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A)
    : $wpdb->get_results($sql, ARRAY_A);

  $filename = sanitize_title($assigned_region) . '-members-' . current_time('Ymd-His') . '.csv';

  nocache_headers();
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');

  $out = fopen('php://output', 'w');
  if (!empty($rows)) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
      fputcsv($out, $row);
    }
  } else {
    fputcsv($out, ['No members found']);
  }
  fclose($out);
  exit;
});

// ------------------------------------------------------------
// Regional directory filters
// - Deliberately excludes exports, region selectors, edit controls,
//   archived access, and Distribution Center actions.
// ------------------------------------------------------------
if (!function_exists('coai_md_regional_filters_form')) {
  function coai_md_regional_filters_form(string $assigned_region): string {
    $q = sanitize_text_field($_GET['q'] ?? '');
    $status = strtoupper(sanitize_text_field($_GET['status'] ?? ''));
    $include_archived = !empty($_GET['include_archived']);

    ob_start(); ?>
    <div class="coai-form-wrap">
      <h2 style="margin:0 0 6px;">Regional Member Directory</h2>
      <p style="margin:0 0 16px;color:#64748b;">
        Showing members assigned to <strong><?php echo esc_html($assigned_region); ?></strong>.
      </p>

      <form method="get" class="coai-filters" style="padding:0;margin:0;">
        <div class="coai-field">
          <label for="coai-regional-search">Search</label>
          <input id="coai-regional-search" class="coai-input" type="text" name="q"
                 value="<?php echo esc_attr($q); ?>"
                 placeholder="Name, email, member number, city">
        </div>

        <div class="coai-field">
          <label for="coai-regional-status">Membership Status</label>
          <select id="coai-regional-status" name="status" class="coai-select">
            <option value="">Active and Expired</option>
            <option value="ACTIVE" <?php selected($status, 'ACTIVE'); ?>>Active</option>
            <option value="EXPIRED" <?php selected($status, 'EXPIRED'); ?>>Expired</option>
          </select>
        </div>

        <div class="coai-field" style="grid-column:1/-1;">
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="include_archived" value="1" <?php checked($include_archived); ?>>
            Include Archived Members
          </label>
        </div>

        <div class="coai-actions coai-filter-actions">
          <button class="coai-btn coai-btn-apply" type="submit">🔍 Apply</button>
          <button class="coai-btn" type="submit" name="coai_regional_export" value="1">
            Download Regional CSV
          </button>
          <a class="coai-btn coai-btn-reset"
             href="<?php echo esc_url(home_url('/regional-member-directory/')); ?>">↺ Reset</a>
        </div>
        <?php wp_nonce_field('coai_regional_export', '_coai_regional_export_nonce'); ?>
      </form>
    </div>
    <?php
    return ob_get_clean();
  }
}

// ------------------------------------------------------------
// Directory render (admin vs finance view)
// - Admin uses inline edit mode on /member-directory/?mid=###
// ------------------------------------------------------------
if (!function_exists('coai_md_render_directory')) {
  function coai_md_render_directory($mode = 'admin') {
    $is_regional = ($mode === 'regional');
    $assigned_region = '';

    if ($mode === 'admin') {
      if (!coai_staff_can('manage')) return '<div class="notice notice-error">Access denied.</div>';
    } elseif ($mode === 'finance') {
      if (!coai_staff_can('view')) return '<div class="notice notice-error">Access denied.</div>';
    } elseif ($is_regional) {
      if (!is_user_logged_in() || !function_exists('coai_user_can') || !coai_user_can('view_region_members')) {
        return '<div class="notice notice-error">Access denied.</div>';
      }

      $wp_user_id = get_current_user_id();
      $member_id = (int) get_user_meta($wp_user_id, 'coai_member_id', true);
      $assigned_region = ($member_id > 0 && function_exists('coai_get_active_rvp_region_for_member'))
        ? coai_get_active_rvp_region_for_member($member_id)
        : '';

      if ($assigned_region === '') {
        return '<div class="notice notice-error">No active Regional Vice President assignment was found for your member account.</div>';
      }
    } else {
      return '<div class="notice notice-error">Invalid directory mode.</div>';
    }

    global $wpdb;
    $table = coai_get_members_table();
    error_log('[COAI] MEMBERS TABLE IN USE: '.$table);

    $editing_id = (int)($_GET['mid'] ?? 0);
    if (!$editing_id) $editing_id = (int)($_GET['member_id'] ?? 0);

    if ($mode === 'admin' && $editing_id > 0) {
      return coai_md_render_edit_view($table, $editing_id, home_url('/member-portal/'));
    }

    $levels = coai_md_get_levels();

    $pg  = max(1, (int)($_GET['pg'] ?? 1));
    $pp  = 25;
    $off = ($pg - 1) * $pp;

    $filter_params = $_GET;

    if ($is_regional) {
      // Security boundary: always overwrite any URL-supplied region.
      // Archived visibility is allowed, but remains locked to the assigned region.
      $filter_params['coai_region'] = $assigned_region;
      $filter_params['include_archived'] = !empty($_GET['include_archived']) ? 1 : 0;
      unset($filter_params['region'], $filter_params['level_id'], $filter_params['level_name']);
    }

    $f = coai_md_build_filters($table, $filter_params);
    $where    = $f['where'];
    $args     = $f['args'];
    $join_sql = $f['join_sql'];

    $sql_count = "SELECT COUNT(*) FROM `$table` $join_sql $where";
    $total = !empty($args) ? (int)$wpdb->get_var($wpdb->prepare($sql_count, ...$args))
                           : (int)$wpdb->get_var($sql_count);

    // Filtered counts (respect current search/filter)
    $active_total = !empty($args)
      ? (int)$wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM `$table` $join_sql $where AND UPPER(status) = 'ACTIVE'",
          ...$args
        ))
      : (int)$wpdb->get_var(
          "SELECT COUNT(*) FROM `$table` $join_sql $where AND UPPER(status) = 'ACTIVE'"
        );

    $expired_total = !empty($args)
      ? (int)$wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM `$table` $join_sql $where AND UPPER(status) = 'EXPIRED'",
          ...$args
        ))
      : (int)$wpdb->get_var(
          "SELECT COUNT(*) FROM `$table` $join_sql $where AND UPPER(status) = 'EXPIRED'"
        );

    // Archived totals respect the same regional/search boundary as the directory.
    if ($is_regional) {
      $archived_total = !empty($args)
        ? (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `$table` $join_sql $where AND UPPER(status) = 'ARCHIVED'",
            ...$args
          ))
        : (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM `$table` $join_sql $where AND UPPER(status) = 'ARCHIVED'"
          );
    } else {
      $archived_total = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM `$table`
        WHERE UPPER(status) = 'ARCHIVED'
      ");
    }

    $renewal_expr = "COALESCE(`$table`.`renewal_date`, `$table`.`membership_expiration`)";
    $show_created = !empty($_GET['new_only']);
    $created_select = $show_created ? ", `$table`.created_at" : "";

    $coai_col = coai_get_coai_column_name($table);
    $coai_select = ", `$table`.`$coai_col` AS coai_pick";

    $order_by = coai_md_sort_sql($table);

    $sql_rows = "
      SELECT
        `$table`.member_id,
        `$table`.member_number,
        `$table`.username,
        `$table`.full_name,
        `$table`.first_name,
        `$table`.last_name,
        `$table`.email,
        `$table`.clown_name,
        COALESCE(NULLIF(`$table`.mobile,''), `$table`.phone) AS phone,
        $renewal_expr AS renewal_date,
        `$table`.membership_expiration,
        `$table`.insurance_status,
        `$table`.insurance_effective_date,
        `$table`.insurance_expiration_date,
        `$table`.status,
        `$table`.city,
        `$table`.state,
        `$table`.region
        $coai_select
        $created_select
      FROM `$table`
      $join_sql
      $where
      $order_by
      LIMIT %d OFFSET %d
    ";

    $rows = $wpdb->get_results(
      $wpdb->prepare($sql_rows, ...array_merge($args, [$pp, $off])),
      ARRAY_A
    );

    $edit_base  = home_url('/member-edit/');
    $portal_url = home_url('/member-portal/');

    $date_fmt = get_option('date_format') ?: 'Y-m-d';
    $fmt = function($v) use ($date_fmt){
      if(!$v) return '—';
      $ts = strtotime($v);
      return ($ts && $ts > 0) ? date_i18n($date_fmt, $ts) : '—';
    };

    ob_start();
    echo coai_md_styles();
    echo $is_regional
      ? coai_md_regional_filters_form($assigned_region)
      : coai_md_filters_form($levels);
    ?>
    <div class="coai-wrap">
      <div class="coai-toolbar">
        <div class="coai-left">
          <div class="coai-pill-group">

              <span class="coai-pill coai-pill-active">
                Active: <?php echo number_format_i18n($active_total); ?>
              </span>

              <span class="coai-pill coai-pill-expired">
                Expired: <?php echo number_format_i18n($expired_total); ?>
              </span>

              <?php if (!$is_regional || !empty($_GET['include_archived'])): ?>
                <span class="coai-pill coai-pill-archived">
                  Archived: <?php echo number_format_i18n($archived_total); ?>
                </span>
              <?php endif; ?>
              
            </div>
        </div>
        <div class="coai-right">
          <a class="coai-btn" href="<?php echo esc_url($portal_url); ?>">← Member Portal</a>
        </div>
      </div>

      <div class="coai-table-wrap">
        <table class="coai-table">
          <thead><tr>
            <th><?php echo coai_md_sort_header('member_number', 'Member #'); ?></th>
            <th><?php echo coai_md_sort_header('coai_number', 'COAI #'); ?></th>
            <th><?php echo coai_md_sort_header('username', 'Username'); ?></th>
            <th><?php echo coai_md_sort_header('name', 'Name'); ?></th>
            <th class="hide-md"><?php echo coai_md_sort_header('email', 'Email'); ?></th>
            <th class="hide-md"><?php echo coai_md_sort_header('phone', 'Phone'); ?></th>
            <th class="hide-md"><?php echo coai_md_sort_header('clown_name', 'Clown Name'); ?></th>

            <th class="hide-md"><?php echo coai_md_sort_header('city', 'City'); ?></th>
            <th class="hide-md"><?php echo coai_md_sort_header('state', 'State'); ?></th>
            <th class="hide-md"><?php echo coai_md_sort_header('region', 'Region'); ?></th>

            <th class="col--date"><?php echo coai_md_sort_header('renewal', 'Renewal'); ?></th>
            <th class="col--date"><?php echo coai_md_sort_header('expires', 'Expires'); ?></th>

            <th class="hide-md"><?php echo coai_md_sort_header('insurance', 'Ins Status'); ?></th>
            <th class="hide-md"><?php echo coai_md_sort_header('ins_eff', 'Ins Eff'); ?></th>
            <th class="hide-md"><?php echo coai_md_sort_header('ins_exp', 'Ins Exp'); ?></th>

            <th><?php echo coai_md_sort_header('status', 'Status'); ?></th>
            <?php if ($show_created): ?><th class="col--date"><?php echo coai_md_sort_header('created', 'Created'); ?></th><?php endif; ?>
          </tr></thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="<?php echo $show_created ? 17 : 16; ?>">No members found.</td></tr>
          <?php else:
            foreach ($rows as $r):
              $mid = (int)$r['member_id'];
              $edit_url = add_query_arg(['mid' => $mid], $edit_base);
              $uname = $r['username'] ?: '—';
              $name  = $r['full_name'] ?: trim(($r['first_name']??'').' '.($r['last_name']??''));
              $name  = $name ?: '—';
              $coai  = (string)($r['coai_pick'] ?? '');
          ?>
            <tr>
              <td><?php echo esc_html($r['member_number'] ?: '—'); ?></td>
              <td><?php echo esc_html($coai ?: '—'); ?></td>
              <td>
                <?php if ($is_regional): ?>
                  <?php echo esc_html($uname); ?>
                <?php else: ?>
                  <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($uname); ?></a>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($is_regional): ?>
                  <?php echo esc_html($name); ?>
                <?php else: ?>
                  <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($name); ?></a>
                <?php endif; ?>
              </td>
              <td class="hide-md"><?php echo esc_html($r['email'] ?: '—'); ?></td>
              <td class="hide-md"><?php echo esc_html($r['phone'] ?: '—'); ?></td>
              <td class="hide-md"><?php echo esc_html($r['clown_name'] ?: '_'); ?></td>

              <td class="hide-md"><?php echo esc_html($r['city'] ?: '—'); ?></td>
              <td class="hide-md"><?php echo esc_html($r['state'] ?: '—'); ?></td>
              <td class="hide-md"><?php echo esc_html($r['region'] ?: '—'); ?></td>

              <td class="col--date"><?php echo esc_html($fmt($r['renewal_date'])); ?></td>
              <td class="col--date"><?php echo esc_html($fmt($r['membership_expiration'])); ?></td>

              <td class="hide-md"><?php echo esc_html($r['insurance_status'] ?: '—'); ?></td>
              <td class="hide-md"><?php echo esc_html($fmt($r['insurance_effective_date'] ?? '')); ?></td>
              <td class="hide-md"><?php echo esc_html($fmt($r['insurance_expiration_date'] ?? '')); ?></td>

              <td><span class="status-badge"><?php echo esc_html($r['status'] ?: '—'); ?></span></td>
              <?php if ($show_created): ?><td class="col--date"><?php echo esc_html($fmt($r['created_at'] ?? '')); ?></td><?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php echo coai_md_pager($total, $pp); ?>
    </div>
    <?php
    return ob_get_clean();
  }
}

// ------------------------------------------------------------
// Shortcodes
// ------------------------------------------------------------
if (!function_exists('coai_members_shortcode_finance')) {
  function coai_members_shortcode_finance() {
    return coai_md_render_directory('finance');
  }
}

if (!function_exists('coai_members_shortcode_regional')) {
  function coai_members_shortcode_regional() {
    return coai_md_render_directory('regional');
  }
}

if (!function_exists('coai_members_shortcode_admin')) {
  function coai_members_shortcode_admin() {
    if (function_exists('coai_is_finance_only') && coai_is_finance_only()) {
      return coai_md_render_directory('finance');
    }
    return coai_md_render_directory('admin');
  }
}

/**
 * Register shortcodes safely (idempotent).
 */
if (!shortcode_exists('coai_members_finance')) {
  add_shortcode('coai_members_finance', 'coai_members_shortcode_finance');
}
if (!shortcode_exists('coai_members_admin')) {
  add_shortcode('coai_members_admin', 'coai_members_shortcode_admin');
}
if (!shortcode_exists('coai_members_regional')) {
  add_shortcode('coai_members_regional', 'coai_members_shortcode_regional');
}

add_action('init', function () {
  if (!shortcode_exists('coai_members_admin')) {
    add_shortcode('coai_members_admin', 'coai_members_shortcode_admin');
    error_log('[COAI] FORCE-REGISTER coai_members_admin on init');
  }

  if (!shortcode_exists('coai_members_finance')) {
    add_shortcode('coai_members_finance', 'coai_members_shortcode_finance');
    error_log('[COAI] FORCE-REGISTER coai_members_finance on init');
  }

  if (!shortcode_exists('coai_members_regional')) {
    add_shortcode('coai_members_regional', 'coai_members_shortcode_regional');
    error_log('[COAI] FORCE-REGISTER coai_members_regional on init');
  }

  error_log(
    '[COAI] shortcode_exists admin=' . (shortcode_exists('coai_members_admin') ? 'YES' : 'NO') .
    ' finance=' . (shortcode_exists('coai_members_finance') ? 'YES' : 'NO') .
    ' regional=' . (shortcode_exists('coai_members_regional') ? 'YES' : 'NO')
  );
}, 99);

error_log('[COAI] REGISTERED shortcodes: coai_members_admin, coai_members_finance, coai_members_regional');
