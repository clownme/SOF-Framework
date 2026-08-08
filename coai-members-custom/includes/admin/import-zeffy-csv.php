<?php
if (!defined('ABSPATH')) exit;

if (!defined('COAI_MEMBERS_TABLE')) {
  global $wpdb;
  // Prefer shared helper if available
  if (function_exists('coai_get_members_table')) {
    define('COAI_MEMBERS_TABLE', coai_get_members_table());
  } else {
    define('COAI_MEMBERS_TABLE', $wpdb->prefix . 'members');
  }
}


add_action('admin_menu', function () {
  add_management_page(
    'COAI: Import Zeffy CSV',
    'COAI CSV Import',
    'manage_options',           // only admins; change to 'edit_others_posts' if managers need access
    'coai-import-zeffy-csv',
    'coai_render_import_zeffy_csv_page'
  );
});

function coai_render_import_zeffy_csv_page() {
  if (!current_user_can('manage_options')) { wp_die('Access denied'); }
  $notice = '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['coai_csv']['name'])) {
    check_admin_referer('coai_import_zeffy');

    $file = $_FILES['coai_csv'];
    if ($file['error'] === UPLOAD_ERR_OK) {
      $tmp = $file['tmp_name'];
      $res = coai_process_zeffy_csv($tmp);
      $notice = sprintf(
        'Imported: %d &nbsp; Updated: %d &nbsp; Skipped: %d',
        (int)$res['inserted'], (int)$res['updated'], (int)$res['skipped']
      );
    } else {
      $notice = 'Upload error: ' . (int)$file['error'];
    }
  }
  ?>
  <div class="wrap">
    <h1>COAI: Import Zeffy CSV</h1>
    <p>Export from Zeffy (Payments or Itemized Payments) with all the columns you need, then upload here.</p>
    <?php if ($notice): ?>
      <div class="notice notice-info"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('coai_import_zeffy'); ?>
      <input type="file" name="coai_csv" accept=".csv" required>
      <p><button class="button button-primary">Upload &amp; Import</button></p>
    </form>

    <p><strong>Matching &amp; Upsert logic:</strong> We match existing members by <code>member_number</code> if present, else by <code>email</code>. New members get a unique username and a random bcrypt password. We never overwrite existing passwords.</p>
  </div>
  <?php
}

function coai_process_zeffy_csv($filepath) {
  $h = fopen($filepath, 'r');
  if (!$h) return ['inserted'=>0,'updated'=>0,'skipped'=>0];

  // read header
  $header = fgetcsv($h);
  if (!$header) return ['inserted'=>0,'updated'=>0,'skipped'=>0];

  // normalize headers like "First Name" -> "first_name"
  $norm = function($s){
    $s = strtolower(trim((string)$s));
    $s = preg_replace('/[^a-z0-9]+/','_', $s);
    return trim($s, '_');
  };
  $cols = array_map($norm, $header);

  // synonyms -> DB columns
  $map = [
    'member_number' => ['member_number'], // keep separate
    'COAI_number'   => ['coai_number','coai_no','coai_id','coai'], // COAI field
    'username'              => ['username'],
    'email'                 => ['email','buyer_email','donor_email','payer_email'],
    'full_name'             => ['full_name','name'],
    'first_name'            => ['first_name','firstname','buyer_first_name','donor_first_name'],
    'last_name'             => ['last_name','lastname','buyer_last_name','donor_last_name'],
    'mobile'                => ['mobile','phone','phone_number','cell','telephone'],
    'address'               => ['address','billing_address'],
    'address2'              => ['address2','address_2','address_line_2'],
    'city'                  => ['city'],
    'state'                 => ['state','province','region_state'],
    'zip'                   => ['zip','postal_code','postcode'],
    'country'               => ['country'],
    'region'                => ['region'],
    'clown_name'            => ['clown_name'],
    'gender'                => ['gender','sex'],
    'birthday'              => ['birthday','birthdate','date_of_birth'],
    'alley_membership'      => ['alley_membership'],
    'payment_amount'        => ['payment_amount','amount','donation_amount','total_amount'],
    'payment_mode'          => ['payment_mode','payment_method','method'],
    'membership_level_id'   => ['membership_level_id','level_id'],
    'insurance_status'      => ['insurance_status','insurance'],
    'membership_expiration' => ['membership_expiration','expires','expiration','expiry_date','expires_on'],
    'status'                => ['status'],
    // optional shipping
    'shipping_address'      => ['shipping_address'],
    'shipping_address2'     => ['shipping_address2','shipping_address_2'],
    'shipping_city'         => ['shipping_city'],
    'shipping_state'        => ['shipping_state'],
    'shipping_zip'          => ['shipping_zip','shipping_postal_code'],
    'shipping_country'      => ['shipping_country'],
    // internal
    'internal_comments'     => ['internal_comments','notes'],
    'parent_name'           => ['parent_name'],
    'e_contact'             => ['e_contact','emergency_contact'],
    'renewal_date'          => ['renewal_date'],
    'registered_date'       => ['registered_date','created_at','order_date'],
    'manual_payment_date'   => ['manual_payment_date'],
    'check_number'          => ['check_number','cheque_number'],
  ];

  // find column positions for each db field
  $pos = [];
  foreach ($map as $dbcol => $syns) {
    foreach ($syns as $name) {
      $i = array_search($name, $cols, true);
      if ($i !== false) { $pos[$dbcol] = $i; break; }
    }
  }

  $inserted=0; $updated=0; $skipped=0;
  global $wpdb;
  $table = COAI_MEMBERS_TABLE;

  // helper: make username from email, unique in wp_members
  $make_username = function($email) use ($wpdb, $table) {
    $base = sanitize_user(current(explode('@', $email)) ?: 'member', true);
    if ($base === '') $base = 'member';
    $u = $base; $i=1;
    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table` WHERE username=%s", $u))) {
      $u = $base . $i++;
    }
    return $u;
  };
  
  // Helper: determine "new member" from COAI_number baseline (202601-001+)
  // NOTE: This is the ONLY rule for is_new_member (no date logic).
  $coai_is_new_from_number = function($coai_num): int {
    $coai_num = trim((string)$coai_num);
    if ($coai_num === '') return 0;

    // Normalize: 202601-001 -> 202601001
    $norm = (int) str_replace('-', '', $coai_num);
    return ($norm >= 202601001) ? 1 : 0;
  };

  while (($row = fgetcsv($h)) !== false) {
    // pull value by mapped position
    $get = function($dbcol) use ($pos, $row) {
      if (!isset($pos[$dbcol])) return null;
      return isset($row[$pos[$dbcol]]) ? trim((string)$row[$pos[$dbcol]]) : null;
    };

    $email = sanitize_email((string)$get('email'));
    $member_no = $get('member_number');
    $member_no = $member_no !== null ? sanitize_text_field($member_no) : null;
    $coai_no = $get('COAI_number');
    $coai_no = $coai_no !== null ? sanitize_text_field($coai_no) : null;
    if (!$email && !$member_no && !$coai_no) { $skipped++; continue; }

    // find existing
    $existing = null;
    if ($member_no) {
      $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE member_number=%s LIMIT 1", $member_no), ARRAY_A);
    }
    if (!$existing && $email) {
      $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE email=%s LIMIT 1", $email), ARRAY_A);
    }
    
    // Determine existing COAI_number (if any)
    $existing_coai = '';
    if ($existing) {
      $existing_coai = trim((string)($existing['COAI_number'] ?? ''));
    }

    // Decide which COAI_number should drive is_new_member (ONLY rule)
    $final_coai_for_flag = $existing ? ($existing_coai ?: (string)$coai_no) : (string)$coai_no;


    // build data set (only include non-empty values so we don't wipe data)
    $data = [];
    $set  = function($key, $val) use (&$data) {
      if ($val === null) return;
      $val = is_string($val) ? trim($val) : $val;
      if ($val === '') return; // skip empties on update
      $data[$key] = $val;
    };

    // basics
    $set('member_number', $member_no);
    // COAI_number: only set on insert, or when existing record has it blank
    if (!$existing || $existing_coai === '') {
      $set('COAI_number', $coai_no);
    }

    // is_new_member is driven ONLY by COAI_number baseline (no dates)
    if ($final_coai_for_flag !== '') {
      $data['is_new_member'] = $coai_is_new_from_number($final_coai_for_flag);
    }

    $set('email', $email);
    $set('full_name', $get('full_name'));
    $set('first_name', $get('first_name'));
    $set('last_name',  $get('last_name'));
    $set('mobile',     $get('mobile'));
    $set('address',    $get('address'));
    $set('address2',   $get('address2'));
    $set('city',       $get('city'));
    $set('state',      $get('state'));
    $set('zip',        $get('zip'));
    $set('country',    $get('country'));
    $set('region',     $get('region'));
    $set('clown_name', $get('clown_name'));
    $set('gender',     $get('gender'));
    $set('alley_membership', $get('alley_membership'));

    // dates
    $birthday = $get('birthday');
    if ($birthday) {
      $ts = strtotime(str_replace('/','-',$birthday));
      if ($ts) $set('birthday', date('Y-m-d', $ts));
    }
    $renewal = $get('renewal_date');
    if ($renewal) {
      $ts = strtotime(str_replace('/','-',$renewal));
      if ($ts) $set('renewal_date', date('Y-m-d', $ts));
    }
    $registered = $get('registered_date');
    if ($registered) {
      $ts = strtotime(str_replace('/','-',$registered));
      if ($ts) $set('registered_date', date('Y-m-d H:i:s', $ts));
    }
    $manual = $get('manual_payment_date');
    if ($manual) {
      $ts = strtotime(str_replace('/','-',$manual));
      if ($ts) $set('manual_payment_date', date('Y-m-d', $ts));
    }
    $expires = $get('membership_expiration');
    if ($expires) {
      $ts = strtotime(str_replace('/','-',$expires));
      if ($ts) $set('membership_expiration', date('Y-m-d H:i:s', $ts));
    }

    // payments/status
    $amt = $get('payment_amount');
    if ($amt !== null && $amt !== '') $set('payment_amount', number_format((float)$amt, 2, '.', ''));
    $set('payment_mode', $get('payment_mode'));
    $lvl = $get('membership_level_id');
    if ($lvl !== null && $lvl !== '') $set('membership_level_id', (int)$lvl);
    $set('insurance_status', $get('insurance_status'));
    $set('status', $get('status'));

    // shipping & misc
    $set('shipping_address',  $get('shipping_address'));
    $set('shipping_address2', $get('shipping_address2'));
    $set('shipping_city',     $get('shipping_city'));
    $set('shipping_state',    $get('shipping_state'));
    $set('shipping_zip',      $get('shipping_zip'));
    $set('shipping_country',  $get('shipping_country'));
    $set('internal_comments', $get('internal_comments'));
    $set('parent_name',       $get('parent_name'));
    $set('e_contact',         $get('e_contact'));
    $data['updated_at'] = current_time('mysql');

    if ($existing) {
      // UPDATE (never overwrite password here)
      $wpdb->update(COAI_MEMBERS_TABLE, $data, ['member_id' => (int)$existing['member_id']]);
      $updated++;
    } else {
      // INSERT
      // username
      $username = $get('username');
      if (!$username) $username = $email ? $make_username($email) : 'member' . time();
      $data['username'] = sanitize_user($username, true);

      // random bcrypt password (user can set their own later)
      $data['password'] = password_hash(wp_generate_password(20, true), PASSWORD_DEFAULT);
      $data['created_at'] = current_time('mysql');

      $wpdb->insert(COAI_MEMBERS_TABLE, $data);
      $inserted++;
      
      $new_member_id = (int) $wpdb->insert_id;

      // If COAI_number not provided in CSV, auto-assign it (new inserts only)
      if ($new_member_id > 0 && empty($coai_no) && function_exists('coai_assign_coai_number_if_missing')) {
        $assigned = trim((string) coai_assign_coai_number_if_missing($new_member_id));

        if ($assigned !== '') {
          // Set is_new_member from FINAL COAI_number baseline
          $wpdb->update(
            COAI_MEMBERS_TABLE,
            ['is_new_member' => (int) $coai_is_new_from_number($assigned)],
            ['member_id' => $new_member_id],
            ['%d'],
            ['%d']
    );
  }
}
    }
  }
  fclose($h);
  return ['inserted'=>$inserted,'updated'=>$updated,'skipped'=>$skipped];
}
