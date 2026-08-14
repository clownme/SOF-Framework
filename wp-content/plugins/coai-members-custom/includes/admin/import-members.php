<?php
if (!defined('ABSPATH')) exit;

/**
 * Renders the Users → Import Members admin page
 * (Menu wired from coai-members-custom.php)
 */
function coai_render_import_members_admin_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Sorry, you are not allowed to import members.');
    }

    // Show result notices (after POST redirect)
    if (!empty($_GET['coai_import_msg'])) {
        echo '<div class="notice notice-info"><p>' . esc_html($_GET['coai_import_msg']) . '</p></div>';
    }
    if (!empty($_GET['coai_import_err'])) {
        echo '<div class="notice notice-error"><p>' . esc_html($_GET['coai_import_err']) . '</p></div>';
    }

    // Find a member row by member_id OR email OR username
function coai_find_member_row($row) {
    global $wpdb;
    $members = coai_get_members_table();

    // prefer member_id if present
    if (!empty($row['member_id'])) {
        $mid = (int)$row['member_id'];
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$members` WHERE member_id=%d", $mid), ARRAY_A);
        if ($m) return $m;
    }
    // fallback by email
    if (!empty($row['email'])) {
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$members` WHERE email=%s", trim($row['email'])), ARRAY_A);
        if ($m) return $m;
    }
    // fallback by username
    if (!empty($row['username'])) {
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$members` WHERE username=%s", trim($row['username'])), ARRAY_A);
        if ($m) return $m;
    }
    return null;
}

// Map a level name → id, with cache
function coai_level_id_from_csv($level_id, $level_name) {
    static $cache = [];
    if ($level_id) return (int)$level_id;

    $name = trim((string)$level_name);
    if ($name === '') return 0;

    if (isset($cache[strtolower($name)])) return $cache[strtolower($name)];

    global $wpdb;
    $levels = coai_get_levels_table();
    $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `$levels` WHERE LOWER(name)=LOWER(%s) LIMIT 1", $name));
    $cache[strtolower($name)] = $id;
    return $id;
}

// Parse a human date → 'Y-m-d H:i:s' or '' if unusable
function coai_parse_any_datetime($s) {
    $s = trim((string)$s);
    if ($s === '') return '';
    $ts = strtotime($s);
    return $ts ? date('Y-m-d H:i:s', $ts) : '';
}

// Compute new expiration (priority: explicit expiration > payment_date+term_years > now+term_years)
function coai_compute_expiration($row) {
    $explicit = coai_parse_any_datetime($row['membership_expiration'] ?? '');
    if ($explicit) return $explicit;

    $years = (int)($row['term_years'] ?? 1);
    if ($years < 1) $years = 1;

    $base = coai_parse_any_datetime($row['payment_date'] ?? '');
    if (!$base) $base = current_time('mysql');
    return date('Y-m-d H:i:s', strtotime("+$years year", strtotime($base)));
}

    ?>
    <div class="wrap">
      <h1>Import Members</h1>
      <p>Upload a CSV with your members. The importer will:</p>
      <ul style="list-style:disc;padding-left:1.25rem;">
        <li>Validate the <code>membership_level_id</code> exists in <code><?php echo esc_html( coai_get_levels_table() ); ?></code>.</li>
        <li>Insert new members or update existing ones matched by <strong>email</strong> (fallback: <strong>username</strong>).</li>
      </ul>

      <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" style="margin-top:1rem;">
        <?php wp_nonce_field('coai_members_import_run', '_coai_nonce'); ?>
        <input type="hidden" name="action" value="coai_members_import_run">

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="coai_csv">CSV file</label></th>
            <td><input type="file" id="coai_csv" name="coai_csv" accept=".csv" required></td>
          </tr>
          <tr>
            <th scope="row">Behavior</th>
            <td>
              <label><input type="checkbox" name="update_existing" value="1" checked> Update existing members (match by email, fallback username)</label><br>
              <label><input type="checkbox" name="skip_expired_empty" value="1"> Skip rows where <code>status=Expired</code> and both <code>membership_level</code> and <code>membership_level_id</code> are empty</label>
            </td>
          </tr>
        </table>
		
		<!-- inside the <form> where you upload CSV -->
        <p>
            <label>
                <input type="checkbox" name="renewal_mode" value="1">
                Treat this file as <strong>Renewals</strong> (update existing members, set ACTIVE, update level & expiration)
            </label>
        </p>

        <p>
          <label>
            <input type="checkbox" name="dry_run" value="1" checked>
            Dry run (analyze only; don’t modify the database)
          </label>
        </p>

        <p class="submit">
          <button type="submit" class="button button-primary">Run Import</button>
        </p>
      </form>
    </div>
    <?php
}

/**
 * Handle POST (admin-post.php?action=coai_members_import_run)
 */
// Handle POST from Users ▸ Import Members
add_action('admin_post_coai_members_import_run', function () {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Not allowed.');
    }
    if ( empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_members_import_run') ) {
        wp_die('Nonce failed.');
    }

    $redirect = admin_url('users.php?page=coai-import-members');

    // ---- validate upload ----
    if (empty($_FILES['coai_csv']['tmp_name']) || !is_uploaded_file($_FILES['coai_csv']['tmp_name'])) {
        wp_safe_redirect( add_query_arg('coai_import_err', rawurlencode('No file uploaded.'), $redirect) );
        exit;
    }

    $fh = fopen($_FILES['coai_csv']['tmp_name'], 'r');
    if (!$fh) {
        wp_safe_redirect( add_query_arg('coai_import_err', rawurlencode('Could not open uploaded file.'), $redirect) );
        exit;
    }
    
    // Detect delimiter (Excel exports may be TAB, semicolon, or comma)
    $pos = ftell($fh);
    $firstLine = fgets($fh);
    fseek($fh, $pos);

    $delimiter = ',';
    if ($firstLine !== false) {
        $tab   = substr_count($firstLine, "\t");
        $semi  = substr_count($firstLine, ";");
        $comma = substr_count($firstLine, ",");

        if ($tab > 0 && $tab >= $semi && $tab >= $comma) $delimiter = "\t";
        else if ($semi > $comma) $delimiter = ";";
        else $delimiter = ",";
    }

    global $wpdb;

    // Tables
    $members_table = coai_get_members_table();
    $levels_table  = coai_get_levels_table();

    // Valid level IDs set
    $valid_level_ids = array_map('intval', (array) $wpdb->get_col("SELECT id FROM `{$levels_table}`"));

    // UI flags
    $update_existing    = !empty($_POST['update_existing']);
    $skip_expired_empty = !empty($_POST['skip_expired_empty']);
    $renewal_mode       = !empty($_POST['renewal_mode']); // NEW
    $dry_run            = !empty($_POST['dry_run']);       // optional

    // Header
    $header = fgetcsv($fh, 0, $delimiter);
    if (!$header) {
        fclose($fh);
        wp_safe_redirect( add_query_arg('coai_import_err', rawurlencode('Empty or invalid CSV header.'), $redirect) );
        exit;
    }
    $norm = function($s){
        $s = (string)$s;
        // Strip UTF-8 BOM (Excel sometimes adds it)
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
        $s = strtolower(trim($s));
        // Convert spaces, underscores, hyphens, parentheses etc to underscores
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim($s, '_');
    };

    $header_norm = array_map($norm, $header);

    // Helper for row values by normalized header name + aliases
     $get = function(array $row, string $col, array $aliases = []) use ($header_norm, $norm) {
        $keys = array_merge([$col], $aliases);
        foreach ($keys as $k) {
            $needle = $norm($k);
            $idx = array_search($needle, $header_norm, true);
            if ($idx !== false && isset($row[$idx])) return trim((string)$row[$idx]);
        }
        return '';
    };

    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;
    $errors   = 0;

    // ---- process rows ----
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {

        // =========================
        // RENEWAL MODE (update only)
        // =========================
        if ($renewal_mode) {
            $member_id_csv   = $get($row, 'member_id');
            $coai_csv        = $get($row, 'coai_number', ['COAI_number', 'coai number']);
            $email_csv       = $get($row, 'email');
            $username_csv    = $get($row, 'username');
            $level_id_csv    = $get($row, 'membership_level_id');
            $level_name_csv  = $get($row, 'membership_level');
            $payment_amount  = $get($row, 'payment_amount');
            $payment_mode    = $get($row, 'payment_mode');
            $payment_date    = $get($row, 'payment_date');           // optional
            $term_years      = $get($row, 'term_years');             // optional
            $explicit_exp    = $get($row, 'membership_expiration');  // optional
            $country_csv     = $get($row, 'country');                // optional
            $state_csv       = $get($row, 'state');                  // optional

            // locate existing member
            $existing = null;
            if ($member_id_csv !== '') {
                $existing = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM `{$members_table}` WHERE member_id=%d", (int)$member_id_csv),
                    ARRAY_A
                );
            }
            if (!$existing && $email_csv !== '') {
                $existing = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM `{$members_table}` WHERE email=%s", $email_csv),
                    ARRAY_A
                );
            }
            if (!$existing && $username_csv !== '') {
                $existing = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM `{$members_table}` WHERE username=%s", $username_csv),
                    ARRAY_A
                );
            }

            // ✅ match by COAI_number BEFORE skipping
            if (!$existing && $coai_csv !== '') {
                $existing = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM `{$members_table}` WHERE COAI_number=%s", $coai_csv),
                    ARRAY_A
                );
            }

            if (!$existing) { $skipped++; continue; }

            // resolve level id
            $level_id = null;
            if ($level_id_csv !== '' && is_numeric($level_id_csv)) {
                $level_id = (int)$level_id_csv;
            } elseif ($level_name_csv !== '') {
                $level_id = (int) $wpdb->get_var(
                    $wpdb->prepare("SELECT id FROM `{$levels_table}` WHERE LOWER(name)=LOWER(%s) LIMIT 1", $level_name_csv)
                );
            }
            if (!$level_id || !in_array($level_id, $valid_level_ids, true)) { $skipped++; continue; }

            // expiration
            $term   = (int)$term_years ?: 1;
            $newexp = '';
            if ($explicit_exp !== '') {
                $ts = strtotime($explicit_exp);
                $newexp = $ts ? date('Y-m-d H:i:s', $ts) : '';
            }
            if ($newexp === '') {
                $anchor = $payment_date ? strtotime($payment_date) : time();
                $newexp = date('Y-m-d H:i:s', strtotime("+{$term} year", $anchor));
            }

            // build update
            $data = [
                'status'                => 'ACTIVE',
                'membership_level_id'   => $level_id,
                'membership_expiration' => $newexp,
                'renewal_date'          => current_time('mysql'),
                'updated_at'            => current_time('mysql'),
                'billing_address' => $get($row, 'billing_address', ['billing address']),

            ];
            if ($payment_amount !== '') $data['payment_amount'] = (float)$payment_amount;
            if ($payment_mode   !== '') $data['payment_mode']   = sanitize_text_field($payment_mode);

            // optional region recalc
            if ($country_csv !== '') $data['country'] = coai_normalize_country($country_csv);
            if ($state_csv   !== '') $data['state']   = strtoupper($state_csv);
            if (!empty($data['country']) || !empty($data['state'])) {
                if (!function_exists('coai_region_from_location')) {
                    @require_once plugin_dir_path(__FILE__) . '/../region-map.php';
                }
                $st = $data['state']   ?? ($existing['state']   ?? '');
                $ct = $data['country'] ?? ($existing['country'] ?? 'US');
                $data['region'] = function_exists('coai_region_from_location')
                    ? coai_region_from_location($st, $ct)
                    : ($existing['region'] ?? '');
            }

            if ($dry_run) { $updated++; continue; }

            $ok = $wpdb->update($members_table, $data, ['member_id' => (int)$existing['member_id']]);
            if ($ok === false) { $errors++; } else { $updated++; }
            continue; // next CSV row
        }

        // =========================
        // NORMAL IMPORT (create/update)
        // =========================
        $username   = $get($row, 'username');
        $email      = $get($row, 'email');
        $status_txt = strtolower($get($row, 'status')); // e.g., "active"/"expired"
        $level_id_s = $get($row, 'membership_level_id');
        $level_id   = is_numeric($level_id_s) ? (int)$level_id_s : null;

        // optional skip
        if ($skip_expired_empty && $status_txt === 'expired' && ($level_id_s === '' || strtolower($level_id_s) === 'nan')) {
            $skipped++;
            continue;
        }

        // validate level id if provided
        if ($level_id !== null && $level_id !== 0 && !in_array($level_id, $valid_level_ids, true)) {
            $errors++;
            continue;
        }

        // collect columns you want to import (add/remove as needed)
        $data = [
            'username'   => $username,
            'email'      => sanitize_email($email),
            'full_name'  => $get($row, 'full_name'),
            'first_name' => $get($row, 'first_name'),
            'last_name'  => $get($row, 'last_name'),
            'phone'      => $get($row, 'phone', ['mobile']),
            'address'    => $get($row, 'address'),
            'address2'   => $get($row, 'address2'),
            'city'       => $get($row, 'city'),
            'state'      => strtoupper($get($row, 'state')),
            'zip'        => $get($row, 'zip'),
            'country'    => coai_normalize_country($get($row, 'country') ?: 'US'),
            'clown_name' => $get($row, 'clown_name'),
            'alley_membership' => $get($row, 'alley_membership'),
            'status'     => strtoupper($status_txt ?: 'ACTIVE'),
        ];
        if ($level_id) $data['membership_level_id'] = $level_id;

        // region auto
        if (!function_exists('coai_region_from_location')) {
            @require_once plugin_dir_path(__FILE__) . '/../region-map.php';
        }
        if (function_exists('coai_region_from_location')) {
            $data['region'] = coai_region_from_location($data['state'] ?? '', $data['country'] ?? 'US') ?: ($data['region'] ?? '');
        }

        // upsert rule: update by email/username if exists, else insert
        $existing = null;
        if (!empty($data['email'])) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$members_table}` WHERE email=%s", $data['email']), ARRAY_A);
        }
        
        if (!$existing && !empty($data['username'])) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$members_table}` WHERE username=%s", $data['username']), ARRAY_A);
        }

        // ALSO match by COAI_number to prevent uq_coai_number duplicates
        if (!$existing && !empty($data['COAI_number'])) {
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$members_table}` WHERE COAI_number=%s",
                    $data['COAI_number']
                ),
                ARRAY_A
            );
        }

        if ($existing) {
            if ($update_existing) {
                $data['updated_at'] = current_time('mysql');
                if ($dry_run) { $updated++; continue; }
                $ok = $wpdb->update($members_table, $data, ['member_id' => (int)$existing['member_id']]);
                if ($ok === false) { $errors++; } else { $updated++; }
            } else {
                $skipped++;
            }
        } else {
            $data['registered_date'] = current_time('mysql');
            $data['updated_at']      = current_time('mysql');
            if ($dry_run) { $inserted++; continue; }
            $ok = $wpdb->insert($members_table, $data);
            if ($ok === false) { $errors++; } else { $inserted++; }
        }
    } // while rows

    fclose($fh);

    // summary message
    $msg = http_build_query([
        'coai_import_ok' => 1,
        'ins' => $inserted,
        'upd' => $updated,
        'skp' => $skipped,
        'err' => $errors,
        'dry' => $dry_run ? 1 : 0,
        'ren' => $renewal_mode ? 1 : 0,
    ]);
    wp_safe_redirect( $redirect . '&' . $msg );
    exit;
});
