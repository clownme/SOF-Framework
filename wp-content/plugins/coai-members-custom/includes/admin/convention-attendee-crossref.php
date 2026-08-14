<?php
if (!defined('ABSPATH')) exit;

/**
 * COAI Convention Attendee Cross Reference
 *
 * Admin tool to:
 * - Upload Zeffy convention attendee CSV
 * - Stage rows into a custom table
 * - Cross reference against wp_members
 * - Mark rows as NEW_MEMBER if not found in wp_members
 * - Export only NEW_MEMBER rows as CSV
 *
 * Matching logic:
 *  1) Email exact match (normalized)
 *  2) First Name + Last Name exact match (normalized)
 *  3) If neither match -> NEW_MEMBER
 */

/* ---------------------------------------------------------
 * Helpers
 * --------------------------------------------------------- */

if (!function_exists('coai_cax_members_table_name')) {
    function coai_cax_members_table_name() {
        global $wpdb;
        if (function_exists('coai_members_table_name')) {
            return coai_members_table_name();
        }
        if (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) {
            return COAI_MEMBERS_TABLE;
        }
        return $wpdb->prefix . 'members';
    }
}

if (!function_exists('coai_cax_stage_table_name')) {
    function coai_cax_stage_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'coai_convention_attendee_xref';
    }
}

if (!function_exists('coai_cax_can_manage')) {
    function coai_cax_can_manage() {
        if (function_exists('coai_staff_can')) {
            return coai_staff_can('manage');
        }
        return current_user_can('manage_options');
    }
}

if (!function_exists('coai_cax_norm')) {
    function coai_cax_norm($value) {
        $value = is_string($value) ? trim($value) : '';
        $value = wp_strip_all_tags($value);
        return $value;
    }
}

if (!function_exists('coai_cax_norm_name')) {
    function coai_cax_norm_name($value) {
        $value = is_string($value) ? $value : '';
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F\xA0]+/u', ' ', $value);
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return strtolower($value);
    }
}

if (!function_exists('coai_cax_norm_email')) {
    function coai_cax_norm_email($value) {
        $value = is_string($value) ? $value : '';
        $value = wp_strip_all_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F\xA0]+/u', '', $value);
        $value = trim($value);
        return strtolower($value);
    }
}

if (!function_exists('coai_cax_header_key')) {
    function coai_cax_header_key($value) {
        $value = strtolower(trim((string)$value));
        $value = str_replace([':', '#', '(', ')', '/', '\\'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}

if (!function_exists('coai_cax_find_col')) {
    function coai_cax_find_col(array $header_map, array $possible_names) {
        foreach ($possible_names as $name) {
            $key = coai_cax_header_key($name);
            if (array_key_exists($key, $header_map)) {
                return (int)$header_map[$key];
            }
        }
        return null;
    }
}

/* ---------------------------------------------------------
 * Install / upgrade staging table
 * --------------------------------------------------------- */

if (!function_exists('coai_cax_maybe_create_table')) {
    function coai_cax_maybe_create_table() {
        global $wpdb;

        $table = coai_cax_stage_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            batch_key VARCHAR(64) NOT NULL,
            payment_date VARCHAR(100) DEFAULT NULL,
            total_amount DECIMAL(10,2) DEFAULT NULL,
            payment_method VARCHAR(100) DEFAULT NULL,
            payment_status VARCHAR(100) DEFAULT NULL,
            payout_date VARCHAR(100) DEFAULT NULL,
            extra_donation DECIMAL(10,2) DEFAULT NULL,
            refund_amount DECIMAL(10,2) DEFAULT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            company_name VARCHAR(150) DEFAULT NULL,
            tax_receipt_no VARCHAR(100) DEFAULT NULL,
            tax_receipt_url TEXT DEFAULT NULL,
            eligible_amount DECIMAL(10,2) DEFAULT NULL,
            details LONGTEXT DEFAULT NULL,
            note LONGTEXT DEFAULT NULL,
            phone_number VARCHAR(50) DEFAULT NULL,
            clown_name VARCHAR(100) DEFAULT NULL,
            fund VARCHAR(150) DEFAULT NULL,

            matched_member_id BIGINT DEFAULT NULL,
            matched_coai_number VARCHAR(50) DEFAULT NULL,
            matched_email VARCHAR(150) DEFAULT NULL,
            matched_first_name VARCHAR(100) DEFAULT NULL,
            matched_last_name VARCHAR(100) DEFAULT NULL,
            matched_by VARCHAR(50) DEFAULT NULL,
            match_status VARCHAR(50) DEFAULT NULL,

            imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),
            KEY idx_batch_key (batch_key),
            KEY idx_email (email),
            KEY idx_match_status (match_status),
            KEY idx_matched_member_id (matched_member_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}

/* ---------------------------------------------------------
 * Admin menu
 * --------------------------------------------------------- */

add_action('admin_menu', 'coai_cax_register_admin_page');

function coai_cax_register_admin_page() {
    add_management_page(
        'Convention Attendee Cross Reference',
        'Convention Attendee Cross Ref',
        'read',
        'coai-convention-attendee-crossref',
        'coai_cax_render_admin_page'
    );
}

/* ---------------------------------------------------------
 * CSV import
 * --------------------------------------------------------- */

if (!function_exists('coai_cax_parse_money')) {
    function coai_cax_parse_money($value) {
        $value = trim((string)$value);
        if ($value === '') return null;
        $value = str_replace([',', '$'], '', $value);
        return is_numeric($value) ? (float)$value : null;
    }
}

if (!function_exists('coai_cax_import_csv')) {
    function coai_cax_import_csv($file_path, &$result = []) {
        global $wpdb;

        $result = [
            'batch_key' => '',
            'inserted'  => 0,
            'errors'    => [],
        ];

        $table = coai_cax_stage_table_name();
        $batch_key = 'batch_' . gmdate('Ymd_His') . '_' . wp_generate_password(6, false, false);
        $result['batch_key'] = $batch_key;

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $result['errors'][] = 'Unable to open uploaded CSV file.';
            return false;
        }

        $header = fgetcsv($handle);
        if (!$header || !is_array($header)) {
            fclose($handle);
            $result['errors'][] = 'CSV header row not found.';
            return false;
        }

        $header_map = [];
        foreach ($header as $i => $col) {
            $header_map[coai_cax_header_key($col)] = $i;
        }

        $col_payment_date   = coai_cax_find_col($header_map, ['Payment Date (America/New_York)', 'Payment Date']);
        $col_total_amount   = coai_cax_find_col($header_map, ['Total Amount']);
        $col_payment_method = coai_cax_find_col($header_map, ['Payment Method']);
        $col_payment_status = coai_cax_find_col($header_map, ['Payment Status']);
        $col_payout_date    = coai_cax_find_col($header_map, ['Payout Date']);
        $col_extra_donation = coai_cax_find_col($header_map, ['Extra Donation']);
        $col_refund_amount  = coai_cax_find_col($header_map, ['Refund Amount']);
        $col_first_name     = coai_cax_find_col($header_map, ['First Name']);
        $col_last_name      = coai_cax_find_col($header_map, ['Last Name']);
        $col_email          = coai_cax_find_col($header_map, ['Email']);
        $col_company_name   = coai_cax_find_col($header_map, ['Company Name']);
        $col_tax_receipt_no = coai_cax_find_col($header_map, ['Tax Receipt #', 'Tax Receipt']);
        $col_tax_receipt_url= coai_cax_find_col($header_map, ['Tax Receipt URL']);
        $col_eligible_amt   = coai_cax_find_col($header_map, ['Eligible Amount']);
        $col_details        = coai_cax_find_col($header_map, ['Details']);
        $col_note           = coai_cax_find_col($header_map, ['Note']);
        $col_phone          = coai_cax_find_col($header_map, ['Phone Number', 'Phone']);
        $col_clown_name     = coai_cax_find_col($header_map, ['Clown Name:', 'Clown Name']);
        $col_fund           = coai_cax_find_col($header_map, ['Fund']);

        $required = [
            'First Name' => $col_first_name,
            'Last Name'  => $col_last_name,
            'Email'      => $col_email,
        ];

        foreach ($required as $label => $idx) {
            if ($idx === null) {
                fclose($handle);
                $result['errors'][] = 'Missing required CSV header: ' . $label;
                return false;
            }
        }

        while (($row = fgetcsv($handle)) !== false) {
            $first_name = $col_first_name !== null ? coai_cax_norm($row[$col_first_name] ?? '') : '';
            $last_name  = $col_last_name  !== null ? coai_cax_norm($row[$col_last_name] ?? '') : '';
            $email      = $col_email      !== null ? coai_cax_norm_email($row[$col_email] ?? '') : '';

            if ($first_name === '' && $last_name === '' && $email === '') {
                continue;
            }

            $insert = [
                'batch_key'         => $batch_key,
                'payment_date'      => $col_payment_date   !== null ? coai_cax_norm($row[$col_payment_date] ?? '') : null,
                'total_amount'      => $col_total_amount   !== null ? coai_cax_parse_money($row[$col_total_amount] ?? '') : null,
                'payment_method'    => $col_payment_method !== null ? coai_cax_norm($row[$col_payment_method] ?? '') : null,
                'payment_status'    => $col_payment_status !== null ? coai_cax_norm($row[$col_payment_status] ?? '') : null,
                'payout_date'       => $col_payout_date    !== null ? coai_cax_norm($row[$col_payout_date] ?? '') : null,
                'extra_donation'    => $col_extra_donation !== null ? coai_cax_parse_money($row[$col_extra_donation] ?? '') : null,
                'refund_amount'     => $col_refund_amount  !== null ? coai_cax_parse_money($row[$col_refund_amount] ?? '') : null,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'email'             => $email,
                'company_name'      => $col_company_name   !== null ? coai_cax_norm($row[$col_company_name] ?? '') : null,
                'tax_receipt_no'    => $col_tax_receipt_no !== null ? coai_cax_norm($row[$col_tax_receipt_no] ?? '') : null,
                'tax_receipt_url'   => $col_tax_receipt_url!== null ? coai_cax_norm($row[$col_tax_receipt_url] ?? '') : null,
                'eligible_amount'   => $col_eligible_amt   !== null ? coai_cax_parse_money($row[$col_eligible_amt] ?? '') : null,
                'details'           => $col_details        !== null ? coai_cax_norm($row[$col_details] ?? '') : null,
                'note'              => $col_note           !== null ? coai_cax_norm($row[$col_note] ?? '') : null,
                'phone_number'      => $col_phone          !== null ? coai_cax_norm($row[$col_phone] ?? '') : null,
                'clown_name'        => $col_clown_name     !== null ? coai_cax_norm($row[$col_clown_name] ?? '') : null,
                'fund'              => $col_fund           !== null ? coai_cax_norm($row[$col_fund] ?? '') : null,
                'matched_member_id' => null,
                'matched_coai_number'=> null,
                'matched_email'     => null,
                'matched_first_name'=> null,
                'matched_last_name' => null,
                'matched_by'        => null,
                'match_status'      => null,
            ];

            $wpdb->insert($table, $insert);
            $result['inserted']++;
        }

        fclose($handle);

        return true;
    }
}

/* ---------------------------------------------------------
 * Matching logic
 * --------------------------------------------------------- */

if (!function_exists('coai_cax_run_match')) {
    function coai_cax_run_match($batch_key) {
        global $wpdb;

        $stage_table   = coai_cax_stage_table_name();
        $members_table = coai_cax_members_table_name();

        // Reset all match fields for this batch
        $wpdb->query($wpdb->prepare("
            UPDATE {$stage_table}
            SET
                matched_member_id = NULL,
                matched_coai_number = NULL,
                matched_email = NULL,
                matched_first_name = NULL,
                matched_last_name = NULL,
                matched_by = NULL,
                match_status = NULL
            WHERE batch_key = %s
        ", $batch_key));

        // Load members
        $members = $wpdb->get_results("
            SELECT
                member_id,
                COAI_number,
                first_name,
                last_name,
                email,
                clown_name,
                deleted_at
            FROM {$members_table}
            WHERE deleted_at IS NULL
        ");

        $by_email = [];
        $by_name  = [];

        foreach ($members as $m) {
            $email = coai_cax_norm_email($m->email ?? '');
            $fn    = coai_cax_norm_name($m->first_name ?? '');
            $ln    = coai_cax_norm_name($m->last_name ?? '');
            $name_key = $fn . '|' . $ln;

            $data = [
                'member_id'   => (int)($m->member_id ?? 0),
                'COAI_number' => (string)($m->COAI_number ?? ''),
                'email'       => (string)($m->email ?? ''),
                'first_name'  => (string)($m->first_name ?? ''),
                'last_name'   => (string)($m->last_name ?? ''),
            ];

            if ($email !== '' && !isset($by_email[$email])) {
                $by_email[$email] = $data;
            }

            if ($fn !== '' && $ln !== '' && !isset($by_name[$name_key])) {
                $by_name[$name_key] = $data;
            }
        }

        // Load stage rows for this batch
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT id, first_name, last_name, email
            FROM {$stage_table}
            WHERE batch_key = %s
            ORDER BY id ASC
        ", $batch_key));

        foreach ($rows as $r) {
            $stage_id = (int)$r->id;
            $email    = coai_cax_norm_email($r->email ?? '');
            $fn       = coai_cax_norm_name($r->first_name ?? '');
            $ln       = coai_cax_norm_name($r->last_name ?? '');
            $name_key = $fn . '|' . $ln;

            $matched = null;
            $matched_by = 'NONE';
            $match_status = 'NEW_MEMBER';

            if ($email !== '' && isset($by_email[$email])) {
                $matched = $by_email[$email];
                $matched_by = 'EMAIL';
                $match_status = 'EXISTING_MEMBER';
            } elseif ($fn !== '' && $ln !== '' && isset($by_name[$name_key])) {
                $matched = $by_name[$name_key];
                $matched_by = 'NAME';
                $match_status = 'EXISTING_MEMBER';
            }

            if ($matched) {
                $wpdb->update(
                    $stage_table,
                    [
                        'matched_member_id'   => (int)$matched['member_id'],
                        'matched_coai_number' => (string)$matched['COAI_number'],
                        'matched_email'       => (string)$matched['email'],
                        'matched_first_name'  => (string)$matched['first_name'],
                        'matched_last_name'   => (string)$matched['last_name'],
                        'matched_by'          => $matched_by,
                        'match_status'        => $match_status,
                    ],
                    ['id' => $stage_id],
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                $wpdb->update(
                    $stage_table,
                    [
                        'matched_by'   => 'NONE',
                        'match_status' => 'NEW_MEMBER',
                    ],
                    ['id' => $stage_id],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }
    }
}

/* ---------------------------------------------------------
 * Batch stats / reads
 * --------------------------------------------------------- */

if (!function_exists('coai_cax_get_latest_batch_key')) {
    function coai_cax_get_latest_batch_key() {
        global $wpdb;
        $table = coai_cax_stage_table_name();
        return $wpdb->get_var("SELECT batch_key FROM {$table} ORDER BY id DESC LIMIT 1");
    }
}

if (!function_exists('coai_cax_get_batch_stats')) {
    function coai_cax_get_batch_stats($batch_key) {
        global $wpdb;
        $table = coai_cax_stage_table_name();

        $stats = [
            'total' => 0,
            'new_member' => 0,
            'existing_member' => 0,
        ];

        if (!$batch_key) return $stats;

        $stats['total'] = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table} WHERE batch_key = %s
        ", $batch_key));

        $stats['new_member'] = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table} WHERE batch_key = %s AND match_status = 'NEW_MEMBER'
        ", $batch_key));

        $stats['existing_member'] = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table} WHERE batch_key = %s AND match_status = 'EXISTING_MEMBER'
        ", $batch_key));

        return $stats;
    }
}

if (!function_exists('coai_cax_get_batch_rows')) {
    function coai_cax_get_batch_rows($batch_key, $limit = 500) {
        global $wpdb;
        $table = coai_cax_stage_table_name();

        if (!$batch_key) return [];

        $limit = max(1, (int)$limit);

        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table}
            WHERE batch_key = %s
            ORDER BY last_name ASC, first_name ASC, id ASC
            LIMIT %d
        ", $batch_key, $limit));
    }
}

/* ---------------------------------------------------------
 * CSV export of NEW_MEMBER rows
 * --------------------------------------------------------- */

add_action('admin_init', 'coai_cax_handle_export');

function coai_cax_handle_export() {
    if (
        !is_admin() ||
        !isset($_GET['page'], $_GET['coai_cax_action']) ||
        $_GET['page'] !== 'coai-convention-attendee-crossref' ||
        $_GET['coai_cax_action'] !== 'export_new'
    ) {
        return;
    }

    if (!coai_cax_can_manage()) {
        wp_die('Access denied.');
    }

    check_admin_referer('coai_cax_export_new');

    $batch_key = isset($_GET['batch_key']) ? sanitize_text_field(wp_unslash($_GET['batch_key'])) : '';
    if ($batch_key === '') {
        wp_die('Missing batch key.');
    }

    global $wpdb;
    $table = coai_cax_stage_table_name();

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT
            first_name,
            last_name,
            email,
            phone_number,
            clown_name,
            payment_date,
            total_amount,
            payment_status,
            fund
        FROM {$table}
        WHERE batch_key = %s
          AND match_status = 'NEW_MEMBER'
        ORDER BY last_name ASC, first_name ASC
    ", $batch_key), ARRAY_A);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=coai-new-members-' . $batch_key . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'First Name',
        'Last Name',
        'Email',
        'Phone Number',
        'Clown Name',
        'Payment Date',
        'Total Amount',
        'Payment Status',
        'Fund',
    ]);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

/* ---------------------------------------------------------
 * Page POST handler
 * --------------------------------------------------------- */

if (!function_exists('coai_cax_handle_upload')) {
    function coai_cax_handle_upload() {
        $notice = [
            'type' => '',
            'message' => '',
            'batch_key' => '',
        ];

        if (
            empty($_POST['coai_cax_upload_csv']) ||
            !isset($_POST['_coai_cax_nonce'])
        ) {
            return $notice;
        }

        if (!wp_verify_nonce($_POST['_coai_cax_nonce'], 'coai_cax_upload_csv')) {
            $notice['type'] = 'error';
            $notice['message'] = 'Security check failed.';
            return $notice;
        }

        if (!coai_cax_can_manage()) {
            $notice['type'] = 'error';
            $notice['message'] = 'Access denied.';
            return $notice;
        }

        if (empty($_FILES['coai_cax_csv']['tmp_name'])) {
            $notice['type'] = 'error';
            $notice['message'] = 'Please choose a CSV file to upload.';
            return $notice;
        }

        $file = $_FILES['coai_cax_csv'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $notice['type'] = 'error';
            $notice['message'] = 'Only CSV files are supported.';
            return $notice;
        }

        $result = [];
        $ok = coai_cax_import_csv($file['tmp_name'], $result);

        if (!$ok) {
            $notice['type'] = 'error';
            $notice['message'] = !empty($result['errors'])
                ? implode(' ', $result['errors'])
                : 'CSV import failed.';
            return $notice;
        }

        coai_cax_run_match($result['batch_key']);

        $notice['type'] = 'success';
        $notice['message'] = 'CSV uploaded and cross-referenced successfully.';
        $notice['batch_key'] = $result['batch_key'];

        return $notice;
    }
}

/* ---------------------------------------------------------
 * Render page
 * --------------------------------------------------------- */

function coai_cax_render_admin_page() {
    if (!coai_cax_can_manage()) {
        wp_die('Access denied.');
    }

    coai_cax_maybe_create_table();

    $notice = coai_cax_handle_upload();

    $batch_key = '';
    if (!empty($notice['batch_key'])) {
        $batch_key = $notice['batch_key'];
    } elseif (!empty($_GET['batch_key'])) {
        $batch_key = sanitize_text_field(wp_unslash($_GET['batch_key']));
    } else {
        $batch_key = coai_cax_get_latest_batch_key();
    }

    $stats = coai_cax_get_batch_stats($batch_key);
    $rows  = coai_cax_get_batch_rows($batch_key, 500);
    ?>
    <div class="wrap">
        <h1>Convention Attendee Cross Reference</h1>

        <p>
            Upload a Zeffy Convention attendee CSV and compare it against <code><?php echo esc_html(coai_cax_members_table_name()); ?></code>.
            A row is marked <strong>NEW_MEMBER</strong> only when no match is found in <code>wp_members</code>.
        </p>

        <?php if (!empty($notice['message'])) : ?>
            <div class="notice notice-<?php echo $notice['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin:16px 0;">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('coai_cax_upload_csv', '_coai_cax_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="coai_cax_csv">Zeffy CSV</label></th>
                        <td>
                            <input type="file" name="coai_cax_csv" id="coai_cax_csv" accept=".csv" required>
                            <p class="description">
                                Expected headers include: First Name, Last Name, Email, Phone Number, Clown Name:, Fund, Payment Date, Total Amount, Payment Status.
                            </p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="coai_cax_upload_csv" value="1" class="button button-primary">
                        Upload and Cross Reference
                    </button>
                </p>
            </form>
        </div>

        <?php if ($batch_key) : ?>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:180px;">
                    <div style="font-size:12px;color:#666;">Batch</div>
                    <div style="font-size:16px;font-weight:700;"><?php echo esc_html($batch_key); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:140px;">
                    <div style="font-size:12px;color:#666;">Total Rows</div>
                    <div style="font-size:24px;font-weight:700;"><?php echo (int)$stats['total']; ?></div>
                </div>
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:140px;">
                    <div style="font-size:12px;color:#666;">New Members</div>
                    <div style="font-size:24px;font-weight:700;color:#b91c1c;"><?php echo (int)$stats['new_member']; ?></div>
                </div>
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px;min-width:140px;">
                    <div style="font-size:12px;color:#666;">Existing Members</div>
                    <div style="font-size:24px;font-weight:700;color:#166534;"><?php echo (int)$stats['existing_member']; ?></div>
                </div>
            </div>

            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(
                    admin_url('tools.php?page=coai-convention-attendee-crossref&coai_cax_action=export_new&batch_key=' . rawurlencode($batch_key)),
                    'coai_cax_export_new'
                )); ?>">
                    Download NEW_MEMBER CSV
                </a>
            </p>
        <?php endif; ?>

        <?php if (!empty($rows)) : ?>
            <h2>Latest Batch Results</h2>
            <div style="overflow:auto;background:#fff;border:1px solid #dcdcde;border-radius:8px;">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>First</th>
                            <th>Last</th>
                            <th>Email</th>
                            <th>Clown Name</th>
                            <th>Phone</th>
                            <th>Payment Status</th>
                            <th>Total</th>
                            <th>Fund</th>
                            <th>Match Status</th>
                            <th>Matched By</th>
                            <th>Member ID</th>
                            <th>COAI #</th>
                            <th>Matched Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r) : ?>
                            <tr>
                                <td><?php echo (int)$r->id; ?></td>
                                <td><?php echo esc_html($r->first_name); ?></td>
                                <td><?php echo esc_html($r->last_name); ?></td>
                                <td><?php echo esc_html($r->email); ?></td>
                                <td><?php echo esc_html($r->clown_name); ?></td>
                                <td><?php echo esc_html($r->phone_number); ?></td>
                                <td><?php echo esc_html($r->payment_status); ?></td>
                                <td><?php echo esc_html($r->total_amount); ?></td>
                                <td><?php echo esc_html($r->fund); ?></td>
                                <td>
                                    <?php if ($r->match_status === 'NEW_MEMBER') : ?>
                                        <span style="font-weight:700;color:#b91c1c;">NEW_MEMBER</span>
                                    <?php else : ?>
                                        <span style="font-weight:700;color:#166534;"><?php echo esc_html($r->match_status); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($r->matched_by); ?></td>
                                <td><?php echo $r->matched_member_id ? (int)$r->matched_member_id : ''; ?></td>
                                <td><?php echo esc_html($r->matched_coai_number); ?></td>
                                <td><?php echo esc_html($r->matched_email); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="description" style="margin-top:8px;">
                Showing up to 500 rows from the selected batch.
            </p>
        <?php elseif ($batch_key) : ?>
            <p>No rows found for this batch.</p>
        <?php endif; ?>
    </div>
    <?php
}