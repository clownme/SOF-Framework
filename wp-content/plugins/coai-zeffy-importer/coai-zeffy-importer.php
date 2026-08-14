<?php
/**
 * Plugin Name: COAI Zeffy Importer
 * Description: Admin tool to upload a Zeffy export (CSV or XLSX), normalize it, detect duplicates, and upsert into wp_members. Admin/Manager only.
 * Version: 0.2.0
 */

if (!defined('ABSPATH')) exit;
error_log('[COAI ZEFFY] file loaded');

/**
 * ------------------------------------------------------------
 * SOF Zeffy Framework
 * ------------------------------------------------------------
 */

$sof_zeffy_loader =
    WP_PLUGIN_DIR .
    '/coai-members-custom/includes/SOF/Zeffy/zeffy.php';

if (file_exists($sof_zeffy_loader)) {
    require_once $sof_zeffy_loader;
}

/**
 * ------------------------------------------------------------
 * Zeffy Campaign IDs
 * ------------------------------------------------------------
 */

define(
    'COAI_ZEFFY_CAMPAIGN_RENEWAL',
    '783e21fa-eaf2-41e6-a4c1-c4cf979cc976'
);

define(
    'COAI_ZEFFY_CAMPAIGN_NEW_MEMBERSHIP',
    'f3463c91-e07f-4300-b100-978febd8f3d2'
);

define(
    'COAI_ZEFFY_CAMPAIGN_2027_CONVENTION',
    '4ad60601-8fba-42f1-9f6d-5f6201439ae9'
);

define(
    'COAI_ZEFFY_CAMPAIGN_SHOP',
    'bb8c8e00-db06-45b3-97f2-0f9dbf5b3579'
);

define(
    'COAI_ZEFFY_CAMPAIGN_SILENT_AUCTION',
    'b985cb68-18d5-412b-9cc1-c26fad4498bb'
);

define(
    'COAI_ZEFFY_CAMPAIGN_2026_CONVENTION',
    '8b03a718-cf83-498f-aa4d-6c59a545a6b6'
);

define(
    'COAI_ZEFFY_CAMPAIGN_2026_BOARD_CONVENTION',
    'fb8231c2-7337-41de-bab4-e6c46616c6fd'
);

/**
 * ------------------------------------------------------------
 * Verified Zeffy Renewal Rate Map
 *
 * IMPORTANT:
 * Only add a rate here after its membership product has been
 * verified from Zeffy / existing COAI business data.
 * ------------------------------------------------------------
 */
function coaii_zeffy_renewal_rate_map(): array {
    return [
        '142e1b5e-b356-4652-bcf2-b016da0fa332' => [
            'name'   => 'Individual Member',
            'amount' => 5500,
        ],

        'e62115ec-5541-4b3d-be8b-4476a222fa99' => [
            'name'   => 'Senior Membership',
            'amount' => 4500,
        ],
            
        '72e08100-95e4-4d92-9cac-3c2fdfa06356' => [
            'name'   => 'Senior Membership',
            'amount' => 4500,
        ],

        '54b0df66-acb2-438b-bb10-cff9962e055c' => [
            'name'   => 'E-Membership - Individual + 1 Family Member',
            'amount' => 6500,
        ],

        '3453c973-ba07-4706-927e-ae8205f3794f' => [
            'name'   => 'Junior Joey Membership minimal',
            'amount' => 1000,
        ],

        '37851464-4674-4fd8-b9bb-3bf71c32c050' => [
            'name'   => 'E-Membership',
            'amount' => 4500,
        ],

        '0a9e899b-0ee7-4031-a3b7-d27affb496b3' => [
            'name'   => 'Member + 1 Family Member',
            'amount' => 8000,
        ],

        'c813273e-00c6-4b98-bb9d-ffab9546d710' => [
            'name'   => 'Senior Member + 1 Family Member',
            'amount' => 7000,
        ],

        'ad199c35-be95-48c8-871b-566133447575' => [
            'name'   => 'E-Membership International',
            'amount' => 3500,
        ],
    ];
}

/**
 * ------------------------------------------------------------
 * Zeffy API
 * ------------------------------------------------------------
 */

function coaii_zeffy_api_key(): string {
    if (!defined('COAI_ZEFFY_API_KEY')) {
        return '';
    }

    return trim((string) COAI_ZEFFY_API_KEY);
}

function coaii_zeffy_api_request(string $endpoint): array {
    $api_key = coaii_zeffy_api_key();

    if ($api_key === '') {
        return [
            'success' => false,
            'message' => 'COAI_ZEFFY_API_KEY is not configured.',
            'data'    => null,
        ];
    }

    $url = 'https://api.zeffy.com/api/v1/' . ltrim($endpoint, '/');

    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept'        => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => $response->get_error_message(),
            'data'    => null,
        ];
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body        = wp_remote_retrieve_body($response);
    $data        = json_decode($body, true);

    if ($status_code < 200 || $status_code >= 300) {
        return [
            'success' => false,
            'message' => 'Zeffy API returned HTTP ' . $status_code . '.',
            'data'    => $data,
        ];
    }

    return [
        'success' => true,
        'message' => 'Zeffy API connection successful.',
        'data'    => $data,
    ];
}

/**
 * ------------------------------------------------------------
 * Retrieve Zeffy campaigns
 * Diagnostic only — does NOT modify MyCOAI data.
 * ------------------------------------------------------------
 */
function coaii_zeffy_campaigns(): array {
    return coaii_zeffy_api_request('campaigns');
}

/**
 * ------------------------------------------------------------
 * Retrieve recent COAI Membership Renewal payments
 * Diagnostic only — does NOT modify MyCOAI data.
 * ------------------------------------------------------------
 */
function coaii_zeffy_renewal_payments(int $limit = 10): array {
    $limit = max(1, min(100, $limit));

    /*
     * Ask Zeffy for a larger diagnostic batch.
     *
     * MyCOAI does NOT trust the requested campaign filter by itself.
     * Every returned payment is independently checked below.
     */
    $query = http_build_query([
        'campaign_id' => COAI_ZEFFY_CAMPAIGN_RENEWAL,
        'limit'       => 100,
    ]);

    $result = coaii_zeffy_api_request('payments?' . $query);

    if (!$result['success']) {
        return $result;
    }

    $data = isset($result['data']) && is_array($result['data'])
        ? $result['data']
        : [];

    $payments = isset($data['data']) && is_array($data['data'])
        ? $data['data']
        : [];

    $renewal_payments = [];
    $excluded_count = 0;

    foreach ($payments as $payment) {
        if (!is_array($payment)) {
            continue;
        }

        $campaign_id = isset($payment['campaign_id'])
            ? trim((string) $payment['campaign_id'])
            : '';

        /*
         * HARD ROUTING RULE:
         *
         * A payment belongs to the Renewal process only when
         * its returned campaign_id exactly matches the known
         * COAI Renewal Membership campaign.
         */
        if ($campaign_id !== COAI_ZEFFY_CAMPAIGN_RENEWAL) {
            $excluded_count++;
            continue;
        }

        $renewal_payments[] = $payment;

        if (count($renewal_payments) >= $limit) {
            break;
        }
    }

    $data['data'] = $renewal_payments;

    /*
     * Diagnostic information only.
     */
    $data['coai_routing'] = [
        'renewal_campaign_id' => COAI_ZEFFY_CAMPAIGN_RENEWAL,
        'accepted'            => count($renewal_payments),
        'excluded'            => $excluded_count,
    ];

    $result['data'] = $data;

    return $result;
}

/**
 * ------------------------------------------------------------
 * Find an answer in a Zeffy question collection.
 * ------------------------------------------------------------
 */
function coaii_zeffy_question_answer(array $questions, string $question_name): string {
    foreach ($questions as $question) {
        if (!is_array($question)) {
            continue;
        }

        $name = isset($question['question'])
            ? trim((string) $question['question'])
            : '';

        if ($name === $question_name) {
            return isset($question['answer'])
                ? trim((string) $question['answer'])
                : '';
        }
    }

    return '';
}

/**
 * ------------------------------------------------------------
 * Resolve a verified Zeffy Renewal rate.
 * ------------------------------------------------------------
 */
function coaii_zeffy_renewal_rate(string $rate_id): ?array {
    $map = coaii_zeffy_renewal_rate_map();

    return isset($map[$rate_id])
        ? $map[$rate_id]
        : null;
}

/**
 * ------------------------------------------------------------
 * Classify a Zeffy payment by its exact campaign ID.
 * ------------------------------------------------------------
 */
function coaii_zeffy_business_process(string $campaign_id): string {
    $campaign_id = trim($campaign_id);

    switch ($campaign_id) {
        case COAI_ZEFFY_CAMPAIGN_RENEWAL:
            return 'renewal';

        case COAI_ZEFFY_CAMPAIGN_NEW_MEMBERSHIP:
            return 'new_membership';

        case COAI_ZEFFY_CAMPAIGN_2027_CONVENTION:
        case COAI_ZEFFY_CAMPAIGN_2026_CONVENTION:
        case COAI_ZEFFY_CAMPAIGN_2026_BOARD_CONVENTION:
            return 'convention';

        case COAI_ZEFFY_CAMPAIGN_SHOP:
            return 'shop';

        case COAI_ZEFFY_CAMPAIGN_SILENT_AUCTION:
            return 'auction';

        default:
            return 'unknown';
    }
}

/**
 * ------------------------------------------------------------
 * Convert one Zeffy Renewal payment into the same row shape
 * used by the existing CSV importer.
 *
 * Returns:
 *     array row when eligible
 *     null when payment must not enter Renewal processing
 * ------------------------------------------------------------
 */
function coaii_zeffy_renewal_payment_to_import_row(array $payment): ?array {

    $campaign_id = trim((string)($payment['campaign_id'] ?? ''));

    if ($campaign_id !== COAI_ZEFFY_CAMPAIGN_RENEWAL) {
        return null;
    }

    $status = strtolower(
        trim((string)($payment['status'] ?? ''))
    );

    /*
     * COAI Renewal rule:
     * Only a succeeded Zeffy payment is eligible.
     */
    if ($status !== 'succeeded') {
        return null;
    }

    $buyer = (
        isset($payment['buyer'])
        && is_array($payment['buyer'])
    )
        ? $payment['buyer']
        : [];

    $buyer_questions = (
        isset($payment['buyer_questions'])
        && is_array($payment['buyer_questions'])
    )
        ? $payment['buyer_questions']
        : [];

    $item = (
        isset($payment['items'][0])
        && is_array($payment['items'][0])
    )
        ? $payment['items'][0]
        : [];

    $item_questions = (
        isset($item['questions'])
        && is_array($item['questions'])
    )
        ? $item['questions']
        : [];

    $rate_id = trim(
        (string)($item['rate_id'] ?? '')
    );

    $rate = coaii_zeffy_renewal_rate($rate_id);

    /*
     * Unknown rates are never guessed.
     */
    if ($rate === null) {
        return null;
    }

    $amount_cents = isset($payment['amount'])
        ? (int) $payment['amount']
        : 0;

    /*
     * The rate identifies the membership product.
     * Amount independently validates the purchase.
     */
    if (
        isset($rate['amount'])
        && (int) $rate['amount'] !== $amount_cents
    ) {
        return null;
    }

    $created_timestamp = isset($payment['created'])
        ? (int) $payment['created']
        : 0;

    if ($created_timestamp <= 0) {
        return null;
    }

    $payment_date = wp_date(
        'm/d/Y, h:i A',
        $created_timestamp,
        new DateTimeZone('America/New_York')
    );

    $payment_method = '';

    if (
        isset($payment['payment_method'])
        && is_array($payment['payment_method'])
    ) {
        $payment_method = trim(
            (string)($payment['payment_method']['type'] ?? '')
        );
    }

    $coai_number = coaii_zeffy_question_answer(
        $buyer_questions,
        'COAI_Number'
    );

    $alley_membership = coaii_zeffy_question_answer(
        $buyer_questions,
        'Alley_Membership'
    );

    $family_email_1 = coaii_zeffy_question_answer(
        $item_questions,
        'Family member 1 Email'
    );

    $family_email_2 = coaii_zeffy_question_answer(
        $item_questions,
        'Family member 2 Email'
    );

    return [
        'Email'                           => trim((string)($buyer['email'] ?? '')),
        'First Name'                      => trim((string)($buyer['first_name'] ?? '')),
        'Last Name'                       => trim((string)($buyer['last_name'] ?? '')),
        'COAI_Number'                     => $coai_number,
        'Alley_Membership'                => $alley_membership,
        'Total Amount'                    => number_format($amount_cents / 100, 2, '.', ''),
        'Payment Method'                  => $payment_method,
        'Payment Status'                  => $status,
        'Payment Date (America/New_York)' => $payment_date,
        'Details'                         => (string)$rate['name'],
        'Campaign Title'                  => 'COAI Renewal Membership',
        'Family1_Email'                   => $family_email_1,
        'Family2_Email'                   => $family_email_2,
    ];
}

/**
 * ------------------------------------------------------------
 * Record one Zeffy payment in the SOF transaction ledger.
 *
 * Existing assessment / processing decisions are preserved.
 * Only current Zeffy facts are refreshed.
 * ------------------------------------------------------------
 */
function coaii_zeffy_record_transaction(array $payment): array {
    global $wpdb;

    $table = 'wp_sof_zeffy_transactions';

    $payment_id = trim((string)($payment['id'] ?? ''));

    if ($payment_id === '') {
        return [
            'success' => false,
            'inserted' => false,
            'message' => 'Payment has no Zeffy payment ID.',
        ];
    }

    $campaign_id = trim((string)($payment['campaign_id'] ?? ''));

    $business_process = coaii_zeffy_business_process(
        $campaign_id
    );

    $buyer = (
        isset($payment['buyer'])
        && is_array($payment['buyer'])
    )
        ? $payment['buyer']
        : [];

    $buyer_questions = (
        isset($payment['buyer_questions'])
        && is_array($payment['buyer_questions'])
    )
        ? $payment['buyer_questions']
        : [];

    $item = (
        isset($payment['items'][0])
        && is_array($payment['items'][0])
    )
        ? $payment['items'][0]
        : [];

    $rate_id = trim((string)($item['rate_id'] ?? ''));

    $membership_product = null;

    if ($business_process === 'renewal' && $rate_id !== '') {
        $rate = coaii_zeffy_renewal_rate($rate_id);

        if ($rate !== null) {
            $membership_product = (string)$rate['name'];
        }
    }

    $coai_number = coaii_zeffy_question_answer(
        $buyer_questions,
        'COAI_Number'
    );

    $created_timestamp = isset($payment['created'])
        ? (int)$payment['created']
        : 0;

    $payment_date = null;

    if ($created_timestamp > 0) {
        $date = new DateTimeImmutable(
            '@' . $created_timestamp
        );

        $date = $date->setTimezone(
            new DateTimeZone('America/New_York')
        );

        $payment_date = $date->format('Y-m-d H:i:s');
    }

    $payment_status = strtolower(
        trim((string)($payment['status'] ?? ''))
    );

    $payment_amount = isset($payment['amount'])
        ? ((int)$payment['amount'] / 100)
        : 0;

    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM {$table}
            WHERE zeffy_payment_id = %s
            LIMIT 1
            ",
            $payment_id
        )
    );

    if ($existing_id) {
        $updated = $wpdb->update(
            $table,
            [
                'zeffy_campaign_id' => $campaign_id,
                'business_process'  => $business_process,
                'payment_status'    => $payment_status,
                'payment_amount'    => $payment_amount,
                'payment_date'      => $payment_date,
                'buyer_first_name'  => trim((string)($buyer['first_name'] ?? '')),
                'buyer_last_name'   => trim((string)($buyer['last_name'] ?? '')),
                'buyer_email'       => trim((string)($buyer['email'] ?? '')),
                'coai_number'       => $coai_number !== '' ? $coai_number : null,
                'zeffy_rate_id'     => $rate_id !== '' ? $rate_id : null,
                'membership_product'=> $membership_product,
            ],
            [
                'id' => (int)$existing_id,
            ]
        );

        if ($updated === false) {
            return [
                'success' => false,
                'inserted' => false,
                'message' => $wpdb->last_error,
            ];
        }

        return [
            'success' => true,
            'inserted' => false,
            'message' => 'Transaction refreshed.',
        ];
    }

    $inserted = $wpdb->insert(
        $table,
        [
            'zeffy_payment_id'    => $payment_id,
            'zeffy_campaign_id'   => $campaign_id,
            'business_process'    => $business_process,
            'payment_status'      => $payment_status,
            'payment_amount'      => $payment_amount,
            'payment_date'        => $payment_date,
            'buyer_first_name'    => trim((string)($buyer['first_name'] ?? '')),
            'buyer_last_name'     => trim((string)($buyer['last_name'] ?? '')),
            'buyer_email'         => trim((string)($buyer['email'] ?? '')),
            'coai_number'         => $coai_number !== '' ? $coai_number : null,
            'zeffy_rate_id'       => $rate_id !== '' ? $rate_id : null,
            'membership_product'  => $membership_product,
            'identity_status'     => 'unassessed',
            'processing_status'   => 'discovered',
            'discovered_at'       => current_time('mysql'),
        ]
    );

    if ($inserted === false) {
        return [
            'success' => false,
            'inserted' => false,
            'message' => $wpdb->last_error,
        ];
    }

    return [
        'success' => true,
        'inserted' => true,
        'message' => 'Transaction discovered.',
    ];
}

/**
 * ------------------------------------------------------------
 * COAI Number auto-generation helpers (INSERT-ONLY)
 * ------------------------------------------------------------
 */
function coaii_coai_prefix(): string {
  return wp_date('Ym'); // WP timezone
}

if (!function_exists('coaii_get_members_table')) {
  function coaii_get_members_table() {
    if (function_exists('coai_members_table_name')) return coai_members_table_name();
    if (defined('COAI_MEMBERS_TABLE')) return COAI_MEMBERS_TABLE;
    return 'wp_members';
  }
}


function coaii_get_month_max_seq(string $ym): int {
  global $wpdb;
  $m = coaii_get_members_table();
  $like = $wpdb->esc_like($ym . '-') . '%';

  // Works with suffixes like 201805-038P or 202501-001dupa because CAST('038P' AS UNSIGNED)=38
  $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(COAI_number, '-', -1) AS UNSIGNED))
          FROM `{$m}`
          WHERE COAI_number LIKE %s";
  return (int) $wpdb->get_var($wpdb->prepare($sql, $like));
}

/**
 * Assign COAI numbers to members INSERTED in this batch who are missing COAI_number.
 * Targets rows via wp_members.updated_at = $batch_ts (INSERT sets updated_at = $batch_ts exactly).
 * INSERT-ONLY: we do NOT assign COAI numbers for UPDATE/renewals.
 */
function coaii_assign_coai_numbers_for_batch(string $batch_ts): array {
  global $wpdb;
  $m  = coaii_get_members_table();
  $ym = coaii_coai_prefix();

  // Grab inserted rows from THIS run that still have empty COAI_number
  $ids = $wpdb->get_col($wpdb->prepare("
    SELECT member_id
    FROM `{$m}`
    WHERE updated_at = %s
      AND (
          COAI_number IS NULL
          OR TRIM(COAI_number) = ''
          OR UPPER(TRIM(COAI_number)) IN ('N/A', 'NA')
      )
    ORDER BY member_id ASC
  ", $batch_ts));

  if (empty($ids)) return ['assigned' => 0, 'skipped' => 0];

  $seq = coaii_get_month_max_seq($ym);
  $assigned = 0;

  foreach ($ids as $member_id) {
    $member_id = (int)$member_id;

    for ($try = 0; $try < 50; $try++) {
      $seq++;

      // Use 3-digit padding (safer, aligns with historical patterns)
      $candidate = sprintf('%s-%03d', $ym, $seq);

      $ok = $wpdb->query($wpdb->prepare("
        UPDATE `{$m}`
        SET COAI_number = %s
        WHERE member_id = %d
          AND (
              COAI_number IS NULL
              OR TRIM(COAI_number) = ''
              OR UPPER(TRIM(COAI_number)) IN ('N/A', 'NA')
          )
      ", $candidate, $member_id));

      if ($ok === 1) { $assigned++; break; }

      // Handle duplicates by trying next number
      if (!empty($wpdb->last_error) && (strpos($wpdb->last_error, 'Duplicate entry') !== false || strpos($wpdb->last_error, '1062') !== false)) {
        continue;
      }

      // Any other error → stop trying for this member_id
      if (!empty($wpdb->last_error)) {
        error_log('[COAI] COAI_number assign failed: '.$wpdb->last_error.' (member_id='.$member_id.')');
      }
      break;
    }
  }

  return ['assigned' => $assigned, 'skipped' => count($ids) - $assigned];
}

function coaii_insert_family_members($batch_ts){
  global $wpdb;

  $members_table = coaii_get_members_table();
  $family_table  = 'wp_member_family_members';
  $rt = 'import_members_ready_zeffy';

  $rows = $wpdb->get_results("
    SELECT z.*, m.member_id
    FROM {$rt} z
    JOIN {$members_table} m
      ON m.updated_at = '{$batch_ts}'
  ", ARRAY_A);

  foreach ($rows as $r) {
    for ($i = 1; $i <= 3; $i++) {
      $first = trim((string)($r["family{$i}_first_name"] ?? ''));
      $last  = trim((string)($r["family{$i}_last_name"] ?? ''));

      if ($first === '' || $last === '') {
        continue;
      }

      $wpdb->insert($family_table, [
        'primary_member_id' => (int)$r['member_id'],
        'first_name'        => $first,
        'last_name'         => $last,
        'relationship'      => trim((string)($r["family{$i}_relationship"] ?? '')),
        'email'             => trim((string)($r["family{$i}_email"] ?? '')),
        'phone'             => trim((string)($r["family{$i}_phone"] ?? '')),
        'birthday'          => !empty($r["family{$i}_birthday"]) ? $r["family{$i}_birthday"] : null,
        'status'            => 'ACTIVE',
      ]);
    }
  }
}

/**
 * ------------------------------------------------------------
 * Capability gate
 * ------------------------------------------------------------
 */
function coaii_user_can_manage() {
  if (function_exists('coai_staff_can')) return coai_staff_can('manage');
  return current_user_can('manage_options');
}

/**
 * ------------------------------------------------------------
 * Admin menu
 * ------------------------------------------------------------
 */
add_action('admin_menu', function(){
  add_menu_page(
    'Zeffy Import',
    'Zeffy Import',
    'read',
    'coai-zeffy-import',
    'coaii_render_page',
    'dashicons-database-import',
    58
  );
});

/**
 * ------------------------------------------------------------
 * Core JOIN logic (single source of truth)
 * - Email match wins (matches BOTH m.email and m.username)
 * - COAI_number fallback only when Zeffy email is blank
 * - Archived / deleted members are ignored
 * ------------------------------------------------------------
 */
function coaii_join_sql(): string {

  // Normalize COAI_number (strip spaces + common hidden whitespace)
  $coai_norm_z = "
    TRIM(
      REPLACE(
        REPLACE(
          REPLACE(COALESCE(z.COAI_number,''), CHAR(160), ''),  -- NBSP
        CHAR(9), ''),                                         -- tabs
      CHAR(13), '')                                           -- CR
    )
  ";

  $coai_norm_m = "
    TRIM(
      REPLACE(
        REPLACE(
          REPLACE(COALESCE(m.COAI_number,''), CHAR(160), ''),  -- NBSP
        CHAR(9), ''),                                         -- tabs
      CHAR(13), '')                                           -- CR
    )
  ";

  return "
    (
      m.deleted_at IS NULL
      AND (m.status IS NULL OR UPPER(TRIM(m.status)) <> 'ARCHIVED')
      AND
      (
        -- If Zeffy row has COAI_number, match ONLY by COAI_number (exact, normalized)
        (
          z.COAI_number IS NOT NULL AND TRIM(z.COAI_number) <> ''
          AND {$coai_norm_z} = {$coai_norm_m}
        )

        OR

        -- Only when Zeffy COAI_number is blank, match by email/username
        (
          (z.COAI_number IS NULL OR TRIM(z.COAI_number) = '')
          AND z.email IS NOT NULL AND TRIM(z.email) <> ''
          AND (
            LOWER(TRIM(z.email)) = LOWER(TRIM(m.email))
            OR LOWER(TRIM(z.email)) = LOWER(TRIM(m.username))
          )
        )
      )
    )
  ";
}
/**
 * ------------------------------------------------------------
 * Email normalization helper (for detector / display)
 * ------------------------------------------------------------
 */
function coaii_norm_email($email): string {
  $email = strtolower(trim((string)$email));
  // Strip common hidden spaces
  $email = preg_replace('/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $email);
  return $email;
}

/**
 * ------------------------------------------------------------
 * Pre-import Duplicate Detector
 * Runs AFTER normalization (ready table exists), BEFORE plan_upsert / do_upsert.
 *
 * Hard stops:
 * - Duplicate email inside the import file
 * - Ambiguous match: one import row matches multiple existing members
 *
 * Warnings:
 * - Possible duplicates by name + city + state (non-blocking)
 * ------------------------------------------------------------
 */
function coaii_preimport_duplicate_detector(): array {

  global $wpdb;
  error_log('[COAI ZEFFY] dupe detector start');

  $m  = coaii_get_members_table();
  $st = 'import_members_staging_zeffy';
  $rt = 'import_members_ready_zeffy';

  $out = [
    'hard_errors' => [],
    'hard_rows'   => [],
    'warnings'    => [],
    'warn_rows'   => [],
    'match_rows'  => [],
  ];

  // HARD STOP #1: duplicate emails inside the SAME import file
  $dupEmails = $wpdb->get_results("
    SELECT
      LOWER(TRIM(`Email`)) AS email_norm,
      COUNT(*) AS cnt
    FROM `{$st}`
    WHERE `Email` IS NOT NULL AND TRIM(`Email`) <> ''
    GROUP BY LOWER(TRIM(`Email`))
    HAVING COUNT(*) > 1
    ORDER BY cnt DESC, email_norm ASC
    LIMIT 200
  ", ARRAY_A);

  if (!empty($dupEmails)) {
    foreach ($dupEmails as $d) {
      $out['hard_errors'][] = "Duplicate email detected in import file: {$d['email_norm']} (appears {$d['cnt']} times). Import halted.";
      $out['hard_rows'][] = [
        'type'  => 'FILE_DUP_EMAIL',
        'key'   => $d['email_norm'],
        'count' => (int)$d['cnt'],
      ];
    }
      error_log('[COAI ZEFFY] dupe detector complete hard_errors=' . (!empty($out['hard_errors']) ? 'YES' : 'NO') . ' warnings=' . (!empty($out['warnings']) ? 'YES' : 'NO'));
      return $out;
  }

   // Matches for each ready row
   $join = coaii_join_sql();

   $matchSql = "
     SELECT
       z.source_id,
       z.email AS z_email,
       z.full_name AS z_full_name,
       z.city AS z_city,
       z.state AS z_state,
       z.COAI_number AS z_coai,

       m.member_id,
       m.email AS m_email,
       m.username AS m_username,
       m.COAI_number AS m_coai
     FROM `{$rt}` z
     JOIN `{$m}` m
       ON {$join}
     ORDER BY z.email, z.COAI_number, m.member_id
   ";
   $matches = $wpdb->get_results($matchSql, ARRAY_A);
   $out['match_rows'] = $matches;

  // Bucket by source row
  $bucket = [];
  foreach ($matches as $r) {
    $key = !empty($r['source_id'])
      ? 'src:' . (int)$r['source_id']
      : 'k:' . coaii_norm_email($r['z_email'] ?? '') . '|' . (string)($r['z_coai'] ?? '');
    $bucket[$key][] = $r;
  }

  // HARD STOP #2: one import row matches multiple members
  foreach ($bucket as $k => $rows) {
    $memberIds = array_values(array_unique(array_map(fn($x)=> (int)$x['member_id'], $rows)));
    if (count($memberIds) > 1) {
      $zEmail = $rows[0]['z_email'] ?? '';
      $zCoai  = $rows[0]['z_coai'] ?? '';
      $out['hard_errors'][] =
        "Ambiguous match: one import row matches multiple existing members (member_id: " .
        implode(',', $memberIds) . "). Email={$zEmail} COAI={$zCoai}. Import halted.";

      $out['hard_rows'][] = [
        'type'       => 'AMBIGUOUS_MATCH',
        'source_id'  => $rows[0]['source_id'] ?? null,
        'z_email'    => $zEmail,
        'z_coai'     => $zCoai,
        'member_ids' => $memberIds,
      ];
    }
  }

  if (!empty($out['hard_errors'])) return $out;

  // WARNING: possible duplicates by name + city + state (non-blocking)
  $warnSql = "
    SELECT
      z.source_id,
      z.full_name AS z_full_name,
      z.email      AS z_email,
      z.city       AS z_city,
      z.state      AS z_state,
      z.COAI_number AS z_coai,

      m.member_id,
      m.full_name AS m_full_name,
      m.email     AS m_email,
      m.username  AS m_username,
      m.city      AS m_city,
      m.state     AS m_state,
      m.COAI_number AS m_coai
    FROM `{$rt}` z
    JOIN `{$m}` m
      ON (
        LOWER(TRIM(z.full_name)) = LOWER(TRIM(m.full_name))
        AND COALESCE(LOWER(TRIM(z.city)), '')  = COALESCE(LOWER(TRIM(m.city)), '')
        AND COALESCE(LOWER(TRIM(z.state)), '') = COALESCE(LOWER(TRIM(m.state)), '')
      )
    WHERE
      m.deleted_at IS NULL
      AND (m.status IS NULL OR UPPER(TRIM(m.status)) <> 'ARCHIVED')
      AND NOT (
        z.email IS NOT NULL AND z.email <> '' AND (
          LOWER(TRIM(z.email)) = LOWER(TRIM(m.email))
          OR LOWER(TRIM(z.email)) = LOWER(TRIM(m.username))
        )
      )
      AND NOT (
        z.COAI_number IS NOT NULL AND z.COAI_number <> ''
        AND z.COAI_number = m.COAI_number
      )
    ORDER BY z.full_name, z.city, z.state, m.member_id
    LIMIT 100
  ";
  $warnRows = $wpdb->get_results($warnSql, ARRAY_A);

  if (!empty($warnRows)) {
    $out['warnings'][] = "Potential duplicates detected (name/city/state). Review recommended before live import.";
    $out['warn_rows'] = $warnRows;
  }

  return $out;
}

function coaii_render_dupe_detector_results(array $det): void {
  if (empty($det)) return;

  if (!empty($det['hard_errors'])) {
    echo '<div class="notice notice-error"><p><strong>Import halted due to duplicates / ambiguity:</strong></p><ul>';
    foreach ($det['hard_errors'] as $e) echo '<li>' . esc_html($e) . '</li>';
    echo '</ul></div>';

    if (!empty($det['hard_rows'])) {
      echo '<table class="widefat fixed striped"><thead><tr>'
        . '<th>Type</th><th>Source ID</th><th>Email</th><th>COAI #</th><th>Member IDs</th><th>Count</th>'
        . '</tr></thead><tbody>';
      foreach ($det['hard_rows'] as $r) {
        echo '<tr>';
        echo '<td>' . esc_html($r['type'] ?? '') . '</td>';
        echo '<td>' . esc_html((string)($r['source_id'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['z_email'] ?? ($r['key'] ?? ''))) . '</td>';
        echo '<td>' . esc_html((string)($r['z_coai'] ?? '')) . '</td>';
        echo '<td>' . esc_html(isset($r['member_ids']) ? implode(',', (array)$r['member_ids']) : '') . '</td>';
        echo '<td>' . esc_html(isset($r['count']) ? (string)$r['count'] : '') . '</td>';
        echo '</tr>';
      }
      echo '</tbody></table>';
    }
  }

  if (!empty($det['warnings'])) {
    echo '<div class="notice notice-warning"><p><strong>Warnings:</strong></p><ul>';
    foreach ($det['warnings'] as $w) echo '<li>' . esc_html($w) . '</li>';
    echo '</ul></div>';

    if (!empty($det['warn_rows'])) {
      echo '<table class="widefat fixed striped"><thead><tr>'
        . '<th>Source ID</th><th>Import Name</th><th>Import Email</th><th>City</th><th>State</th>'
        . '<th>Possible Match member_id</th><th>Member Email</th><th>Member Username</th><th>Member COAI</th>'
        . '</tr></thead><tbody>';
      foreach ($det['warn_rows'] as $r) {
        echo '<tr>';
        echo '<td>' . esc_html((string)($r['source_id'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['z_full_name'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['z_email'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['z_city'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['z_state'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['member_id'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['m_email'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['m_username'] ?? '')) . '</td>';
        echo '<td>' . esc_html((string)($r['m_coai'] ?? '')) . '</td>';
        echo '</tr>';
      }
      echo '</tbody></table>';
    }
  }
}

/**
 * ------------------------------------------------------------
 * Render page + handle post
 * ------------------------------------------------------------
 */
function coaii_render_page(){
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

  error_log('[COAI ZEFFY] render_page start method=' . ($_SERVER['REQUEST_METHOD'] ?? ''));
  if (!coaii_user_can_manage()) {
    echo '<div class="notice notice-error"><p>Access denied.</p></div>';
    return;
  }

  global $wpdb;
  $uploads = wp_upload_dir();
  $dir = trailingslashit($uploads['basedir']).'coai-imports';
  if (!is_dir($dir)) wp_mkdir_p($dir);

  $ran = false;
  $msgs = [];
  $preview_rows = [];
  $dupe_det = null;
  $zeffy_campaigns = [];
  $zeffy_renewal_payments = [];
  $zeffy_api_dry_run = false;
  $zeffy_unknown_renewals = [];
  $zeffy_identity_results = [];
  $zeffy_business_results = [];

  $zeffy_member_search = '';
  $zeffy_member_search_transaction_id = 0;
  $zeffy_member_search_results = [];
  
  $zeffy_identity_confirmation_transaction = null;
  $zeffy_identity_confirmation_member = null;

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coaii_test_zeffy_api'])) {
    check_admin_referer('coaii_test_zeffy_api');

    $result = coaii_zeffy_api_request('payments');

    $ran = true;

    if ($result['success']) {
      $msgs[] = [
        'success',
        'Zeffy API connection successful. MyCOAI can retrieve Zeffy payments.'
      ];
    } else {
      $msgs[] = [
        'error',
        'Zeffy API connection failed: ' . $result['message']
      ];
    }
  }
  
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coaii_retrieve_zeffy_campaigns'])) {
    check_admin_referer('coaii_retrieve_zeffy_campaigns');

    $result = coaii_zeffy_campaigns();

    $ran = true;

    if ($result['success']) {
      $zeffy_campaigns = $result['data'];

      $msgs[] = [
        'success',
        'Zeffy campaign data retrieved successfully.'
      ];
    } else {
      $msgs[] = [
        'error',
        'Unable to retrieve Zeffy campaigns: ' . $result['message']
      ];
    }
  }
  
    if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_search_zeffy_member'])
  ) {

    $zeffy_member_search_transaction_id =
      isset($_POST['zeffy_transaction_id'])
        ? (int)$_POST['zeffy_transaction_id']
        : 0;

    $zeffy_member_search =
      isset($_POST['zeffy_member_search'])
        ? sanitize_text_field(
            wp_unslash($_POST['zeffy_member_search'])
        )
        : '';

    check_admin_referer(
      'coaii_search_zeffy_member_' .
      $zeffy_member_search_transaction_id
    );

    if ($zeffy_member_search === '') {

      $msgs[] = [
        'error',
        'Enter a name, COAI number, or email address to search MyCOAI.'
      ];

      $zeffy_member_search_results = [];

    } elseif (
      !function_exists(
        'coai_search_members_for_identity_review'
      )
    ) {

      $msgs[] = [
        'error',
        'SOF member identity search is not available.'
      ];

      $zeffy_member_search_results = [];

    } else {

      $zeffy_member_search_results =
        coai_search_members_for_identity_review(
          $zeffy_member_search
        );
    }

    /*
     * Rebuild the Renewal Identity Review so the transaction
     * being searched remains visible after the POST refresh.
     */
    if (
      class_exists('SOF_ZeffyTransactionRepository')
      && class_exists('SOF_ZeffyRenewalIdentityService')
    ) {

      $repository =
        new SOF_ZeffyTransactionRepository();

      $identity_service =
        new SOF_ZeffyRenewalIdentityService();

      $transactions =
        $repository->find_matched_renewals(500);

      foreach ($transactions as $transaction) {

        /*
         * ----------------------------------------------------
         * Human identity decisions are established business
         * knowledge.
         *
         * Automatic assessment must never reinterpret or
         * overwrite a transaction already resolved by a human.
         * ----------------------------------------------------
         */
        if (
          $transaction->identity_status === 'matched'
          && $transaction->match_method === 'human_review'
          && !empty($transaction->matched_member_id)
        ) {

          $counts['matched']++;

          continue;
        }

        $identity =
          $identity_service->assess($transaction);

        $status =
          (string)(
            $identity['identity_status']
            ?? 'unresolved'
          );

        $match_method =
          trim(
            (string)(
              $identity['match_method']
              ?? ''
            )
          );

        /*
         * Persist the identity assessment as established
         * SOF ledger knowledge.
         *
         * Confident automatic matches establish a member.
         * Review-required, ambiguous, and unresolved results
         * are also preserved so they remain visible for
         * human attention after this request ends.
         */
        if (
          $status === 'matched'
          && !empty($identity['matched_member_id'])
          && $match_method !== ''
        ) {

          $repository->record_automatic_identity_match(
            (int)$transaction->id,
            (int)$identity['matched_member_id'],
            $match_method
          );

        } elseif (
          in_array(
            $status,
            [
              'review_required',
              'ambiguous',
              'unresolved',
            ],
            true
          )
        ) {

          $repository->record_identity_assessment(
            (int)$transaction->id,
            $status,
            $match_method
          );
        }

        if (isset($counts[$status])) {
          $counts[$status]++;
        }

        $zeffy_identity_results[] = [
          'transaction' => $transaction,
          'identity'    => $identity,
        ];
      }
    }

    $ran = true;
  }
  
    if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_prepare_zeffy_identity_confirmation'])
  ) {

    $transaction_id = isset($_POST['zeffy_transaction_id'])
      ? (int)$_POST['zeffy_transaction_id']
      : 0;

    $member_id = isset($_POST['zeffy_member_id'])
      ? (int)$_POST['zeffy_member_id']
      : 0;

    check_admin_referer(
      'coaii_prepare_zeffy_identity_confirmation_' .
      $transaction_id
    );

    $ran = true;

    if (
      $transaction_id <= 0
      || $member_id <= 0
    ) {

      $msgs[] = [
        'error',
        'A valid Zeffy transaction and MyCOAI member are required.'
      ];

    } elseif (
      !class_exists('SOF_ZeffyTransactionRepository')
    ) {

      $msgs[] = [
        'error',
        'SOF Zeffy transaction repository is not available.'
      ];

    } else {

      $repository =
        new SOF_ZeffyTransactionRepository();

      $zeffy_identity_confirmation_transaction =
        $repository->find_by_id($transaction_id);

      $zeffy_identity_confirmation_member =
        coai_get_member_by_id($member_id);

      if (
        !$zeffy_identity_confirmation_transaction
        || !$zeffy_identity_confirmation_member
      ) {

        $zeffy_identity_confirmation_transaction = null;
        $zeffy_identity_confirmation_member = null;

        $msgs[] = [
          'error',
          'SOF could not prepare the requested identity confirmation.'
        ];
      }
    }

    /*
     * Keep the Renewal Identity Review visible after
     * the confirmation request refreshes the page.
     */
    if (
      class_exists('SOF_ZeffyTransactionRepository')
      && class_exists('SOF_ZeffyRenewalIdentityService')
    ) {

      $repository =
        new SOF_ZeffyTransactionRepository();

      $identity_service =
        new SOF_ZeffyRenewalIdentityService();

      $transactions =
        $repository->find_identity_ready_renewals(500);

      foreach ($transactions as $transaction) {

        if (
          $transaction->identity_status === 'matched'
          && $transaction->match_method === 'human_review'
          && !empty($transaction->matched_member_id)
        ) {
          continue;
        }

        $identity =
          $identity_service->assess($transaction);

        $zeffy_identity_results[] = [
          'transaction' => $transaction,
          'identity'    => $identity,
        ];
      }
    }
  }

  if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_resolve_zeffy_identity'])
  ) {

    $transaction_id = isset($_POST['zeffy_transaction_id'])
      ? (int)$_POST['zeffy_transaction_id']
      : 0;

    $member_id = isset($_POST['zeffy_member_id'])
      ? (int)$_POST['zeffy_member_id']
      : 0;
      
    check_admin_referer(
      'coaii_resolve_zeffy_identity_' . $transaction_id
    );

    $ran = true;

    if (
      !class_exists('SOF_ZeffyIdentityResolutionService')
    ) {

      $msgs[] = [
        'error',
        'SOF Zeffy identity resolution service is not available.'
      ];

    } else {

      $resolution_service =
        new SOF_ZeffyIdentityResolutionService();

      $resolution =
        $resolution_service->resolve(
          $transaction_id,
          $member_id
        );

      $msgs[] = [
        !empty($resolution['success'])
          ? 'success'
          : 'error',
        (string)($resolution['message'] ?? '')
      ];

      /*
       * Rebuild the Identity Review immediately so the
       * resolved transaction disappears from the review list.
       */
      if (
        !empty($resolution['success'])
        && class_exists('SOF_ZeffyTransactionRepository')
        && class_exists('SOF_ZeffyRenewalIdentityService')
      ) {

        $repository =
          new SOF_ZeffyTransactionRepository();

        $identity_service =
          new SOF_ZeffyRenewalIdentityService();

        $transactions =
          $repository->find_identity_ready_renewals(500);

        foreach ($transactions as $transaction) {

          /*
           * Do not reassess transactions already resolved
           * by a human.
           */
          if (
            $transaction->identity_status === 'matched'
            && $transaction->match_method === 'human_review'
          ) {
            continue;
          }

          $identity =
            $identity_service->assess($transaction);

          $zeffy_identity_results[] = [
            'transaction' => $transaction,
            'identity'    => $identity,
          ];
        }
      }
    }
  }
  
    /*
   * ==========================================================
   * SOF One-Click Renewal Assessment
   * ==========================================================
   *
   * Normal Admin workflow:
   *
   *   1. Assess Renewal transactions
   *   2. Assess member identity
   *   3. Assess membership business effect
   *
   * Individual assessment actions remain available under
   * Diagnostics & Maintenance.
   * ==========================================================
   */
  if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_assess_zeffy_all'])
  ) {
    check_admin_referer('coaii_assess_zeffy_all');

    $ran = true;

    if (
      !class_exists('SOF_ZeffyTransactionRepository')
      || !class_exists('SOF_ZeffyRenewalAssessmentService')
      || !class_exists('SOF_ZeffyRenewalIdentityService')
      || !class_exists(
        'SOF_ZeffyRenewalBusinessAssessmentService'
      )
    ) {

      $msgs[] = [
        'error',
        'SOF could not prepare the complete Renewal assessment.'
      ];

    } else {

      $repository =
        new SOF_ZeffyTransactionRepository();

      /*
       * ------------------------------------------------------
       * Stage 1 — Transaction Assessment
       * ------------------------------------------------------
       */
      $assessment_service =
        new SOF_ZeffyRenewalAssessmentService();

      $transactions =
        $repository->find_renewals(500);

      $transaction_counts = [
        'ready_for_identity_assessment' => 0,
        'payment_not_complete'          => 0,
        'membership_product_unknown'    => 0,
        'not_renewal'                   => 0,
      ];

      foreach ($transactions as $transaction) {

        $assessment =
          $assessment_service->assess(
            $transaction
          );

        $key =
          (string)(
            $assessment['assessment']
            ?? ''
          );

        if (
          isset(
            $transaction_counts[$key]
          )
        ) {
          $transaction_counts[$key]++;
        }
      }

      /*
       * ------------------------------------------------------
       * Stage 2 — Identity Assessment
       * ------------------------------------------------------
       */
      $identity_service =
        new SOF_ZeffyRenewalIdentityService();

      $identity_transactions =
        $repository
          ->find_identity_ready_renewals(500);

      $identity_counts = [
        'matched'         => 0,
        'ambiguous'       => 0,
        'review_required' => 0,
        'unresolved'      => 0,
      ];

      foreach (
        $identity_transactions
        as $transaction
      ) {

        /*
         * Preserve identity decisions already established
         * through human review.
         */
        if (
          $transaction->identity_status ===
            'matched'
          && $transaction->match_method ===
            'human_review'
          && !empty(
            $transaction->matched_member_id
          )
        ) {

          $identity_counts['matched']++;

          continue;
        }

        $identity =
          $identity_service->assess(
            $transaction
          );

        $status =
          (string)(
            $identity['identity_status']
            ?? 'unresolved'
          );

        $match_method =
          trim(
            (string)(
              $identity['match_method']
              ?? ''
            )
          );

        /*
         * Persist the identity assessment as established
         * SOF ledger knowledge.
         *
         * Confident automatic matches establish a member.
         * Review-required, ambiguous, and unresolved results
         * are preserved so they remain available for
         * human review after this request ends.
         */
        if (
          $status === 'matched'
          && !empty($identity['matched_member_id'])
          && $match_method !== ''
        ) {

          $repository->record_automatic_identity_match(
            (int)$transaction->id,
            (int)$identity['matched_member_id'],
            $match_method
          );

        } elseif (
          in_array(
            $status,
            [
              'review_required',
              'ambiguous',
              'unresolved',
            ],
            true
          )
        ) {

          $repository->record_identity_assessment(
            (int)$transaction->id,
            $status,
            $match_method
          );
        }

        if (
          isset(
            $identity_counts[$status]
          )
        ) {
          $identity_counts[$status]++;
        }
      }

      /*
       * ------------------------------------------------------
       * Stage 3 — Business Assessment
       *
       * Re-query after Identity Assessment so this stage uses
       * the newly established identity situation.
       * ------------------------------------------------------
       */
      $business_service =
        new SOF_ZeffyRenewalBusinessAssessmentService();

      $business_transactions =
        $repository
          ->find_identity_ready_renewals(500);

      $business_counts = [
        'ready_to_apply'              => 0,
        'possible_previously_applied' => 0,
        'management_review'           => 0,
        'cannot_assess'               => 0,
      ];

      foreach (
        $business_transactions
        as $transaction
      ) {

        $business_assessment =
          $business_service->assess(
            $transaction
          );

        $status =
          (string)(
            $business_assessment[
              'assessment_status'
            ]
            ?? 'cannot_assess'
          );

        if (
          isset(
            $business_counts[$status]
          )
        ) {
          $business_counts[$status]++;
        } else {
          $business_counts[
            'cannot_assess'
          ]++;
        }
      }

      /*
       * ------------------------------------------------------
       * Present one business result to the Admin.
       * ------------------------------------------------------
       */
      $msgs[] = [
        'success',
        sprintf(
          'SOF Renewal assessment complete. %d Renewal transaction(s) assessed; %d member identities matched; %d ready to apply; %d possibly previously applied; %d requiring management review; %d unable to assess.',
          count($transactions),
          $identity_counts['matched'],
          $business_counts[
            'ready_to_apply'
          ],
          $business_counts[
            'possible_previously_applied'
          ],
          $business_counts[
            'management_review'
          ],
          $business_counts[
            'cannot_assess'
          ]
        )
      ];
    }
  }

  
  if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_assess_zeffy_business'])
  ) {
    check_admin_referer('coaii_assess_zeffy_business');

    $ran = true;

    if (
      !class_exists('SOF_ZeffyTransactionRepository')
      || !class_exists('SOF_ZeffyRenewalBusinessAssessmentService')
    ) {

      $msgs[] = [
        'error',
        'SOF Zeffy Renewal Business Assessment services are not available.'
      ];

    } else {

      $repository =
        new SOF_ZeffyTransactionRepository();

      $business_service =
        new SOF_ZeffyRenewalBusinessAssessmentService();

      $transactions =
        $repository->find_matched_renewals(500);

      $counts = [
        'ready_to_apply'              => 0,
        'possible_previously_applied' => 0,
        'management_review'           => 0,
        'cannot_assess'               => 0,
      ];

      foreach ($transactions as $transaction) {

        $assessment =
          $business_service->assess($transaction);

        $status = (string)(
          $assessment['assessment_status']
          ?? 'cannot_assess'
        );

        if (isset($counts[$status])) {
          $counts[$status]++;
        } else {
          $counts['cannot_assess']++;
        }

        $zeffy_business_results[] = [
          'transaction' => $transaction,
          'assessment'  => $assessment,
        ];
      }

      $msgs[] = [
        'success',
        sprintf(
          'SOF assessed the business effect of %d Renewal transaction(s): %d ready to apply, %d possibly previously applied, %d requiring management review, %d unable to assess.',
          count($transactions),
          $counts['ready_to_apply'],
          $counts['possible_previously_applied'],
          $counts['management_review'],
          $counts['cannot_assess']
        )
      ];
    }
  }

    if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_assess_zeffy_identities'])
  ) {
    check_admin_referer('coaii_assess_zeffy_identities');

    $ran = true;

    if (
      !class_exists('SOF_ZeffyTransactionRepository')
      || !class_exists('SOF_ZeffyRenewalIdentityService')
    ) {

      $msgs[] = [
        'error',
        'SOF Zeffy identity services are not available.'
      ];

    } else {

      $repository =
        new SOF_ZeffyTransactionRepository();

      $identity_service =
        new SOF_ZeffyRenewalIdentityService();

      $transactions =
        $repository->find_identity_ready_renewals(500);

      $counts = [
        'matched'         => 0,
        'ambiguous'       => 0,
        'review_required' => 0,
        'unresolved'      => 0,
      ];

      foreach ($transactions as $transaction) {

        /*
         * ----------------------------------------------------
         * Human identity decisions are established business
         * knowledge.
         *
         * Automatic assessment must never reinterpret or
         * overwrite a transaction already resolved by a human.
         * ----------------------------------------------------
         */
        if (
          $transaction->identity_status === 'matched'
          && $transaction->match_method === 'human_review'
          && !empty($transaction->matched_member_id)
        ) {

          $counts['matched']++;

          continue;
        }

        $identity =
          $identity_service->assess($transaction);

        $status =
          (string)($identity['identity_status'] ?? 'unresolved');

        /*
         * Persist confident automatic identity matches as
         * established Zeffy ledger knowledge.
         *
         * Human-reviewed matches are handled separately by
         * SOF_ZeffyIdentityResolutionService.
         */
        if (
          $status === 'matched'
          && !empty($identity['matched_member_id'])
          && !empty($identity['match_method'])
        ) {
          $repository->record_automatic_identity_match(
              (int)$transaction->id,
              (int)$identity['matched_member_id'],
              (string)$identity['match_method']
          );
        }

        if (isset($counts[$status])) {
          $counts[$status]++;
        }

        $zeffy_identity_results[] = [
          'transaction' => $transaction,
          'identity'    => $identity,
        ];
      }

      $msgs[] = [
        'success',
        sprintf(
          'SOF assessed identity for %d Renewal transaction(s): %d matched, %d ambiguous, %d requiring review, %d unresolved.',
          count($transactions),
          $counts['matched'],
          $counts['ambiguous'],
          $counts['review_required'],
          $counts['unresolved']
        )
      ];
    }
  }

  if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_unknown_zeffy_renewals'])
  ) {
    check_admin_referer('coaii_unknown_zeffy_renewals');

    $ran = true;

    if (!class_exists('SOF_ZeffyTransactionRepository')) {

      $msgs[] = [
        'error',
        'SOF Zeffy transaction repository is not available.'
      ];

    } else {

      $repository =
        new SOF_ZeffyTransactionRepository();

      $zeffy_unknown_renewals =
        $repository->find_unknown_product_renewals(100);

      $msgs[] = [
        'info',
        sprintf(
          'SOF found %d succeeded Renewal transaction(s) with an unknown membership product.',
          count($zeffy_unknown_renewals)
        )
      ];
    }
  }

  if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_assess_zeffy_renewals'])
  ) {
    check_admin_referer('coaii_assess_zeffy_renewals');

    $ran = true;

    if (
      !class_exists('SOF_ZeffyTransactionRepository')
      || !class_exists('SOF_ZeffyRenewalAssessmentService')
    ) {

      $msgs[] = [
        'error',
        'SOF Zeffy assessment services are not available.'
      ];

    } else {

      $repository =
        new SOF_ZeffyTransactionRepository();

      $assessment_service =
        new SOF_ZeffyRenewalAssessmentService();

      $transactions =
        $repository->find_renewals(500);

      $counts = [
        'ready_for_identity_assessment' => 0,
        'payment_not_complete'          => 0,
        'membership_product_unknown'    => 0,
        'not_renewal'                   => 0,
      ];

      foreach ($transactions as $transaction) {

        $assessment =
          $assessment_service->assess($transaction);

        $key =
          (string)($assessment['assessment'] ?? '');

        if (isset($counts[$key])) {
          $counts[$key]++;
        }
      }

      $msgs[] = [
        'success',
        sprintf(
          'SOF assessed %d Renewal transaction(s): %d ready for identity assessment, %d waiting for completed payment, %d with an unknown membership product.',
          count($transactions),
          $counts['ready_for_identity_assessment'],
          $counts['payment_not_complete'],
          $counts['membership_product_unknown']
        )
      ];
    }
  }

  if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_sync_zeffy_ledger'])
  ) {
    check_admin_referer('coaii_sync_zeffy_ledger');

    $ran = true;

    /*
     * First ledger checkpoint:
     * retrieve the most recent 100 Zeffy payments.
     *
     * Pagination will be added after this behavior is validated.
     */
    $result = coaii_zeffy_api_request(
        'payments?limit=100'
    );

    if (!$result['success']) {

      $msgs[] = [
        'error',
        'Unable to retrieve Zeffy payments: '
          . $result['message']
      ];

    } else {

      $data = (
        isset($result['data'])
        && is_array($result['data'])
      )
        ? $result['data']
        : [];

      $payments = (
        isset($data['data'])
        && is_array($data['data'])
      )
        ? $data['data']
        : [];

      $inserted_count = 0;
      $refreshed_count = 0;
      $error_count = 0;

      foreach ($payments as $payment) {

        if (!is_array($payment)) {
          $error_count++;
          continue;
        }

        $ledger_result =
          coaii_zeffy_record_transaction($payment);

        if (!$ledger_result['success']) {
          $error_count++;
          continue;
        }

        if ($ledger_result['inserted']) {
          $inserted_count++;
        } else {
          $refreshed_count++;
        }
      }

      $msgs[] = [
        'success',
        sprintf(
          'Zeffy ledger synchronized: %d new transaction(s), %d existing transaction(s) refreshed, %d error(s).',
          $inserted_count,
          $refreshed_count,
          $error_count
        )
      ];
    }
  }

  if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['coaii_dry_run_zeffy_renewals'])
  ) {
    check_admin_referer('coaii_dry_run_zeffy_renewals');

    $ran = true;
    $zeffy_api_dry_run = true;

    /*
     * Retrieve a larger source batch.
     * coaii_zeffy_renewal_payments() independently enforces
     * the Renewal campaign ID.
     */
    $result = coaii_zeffy_renewal_payments(100);

    if (!$result['success']) {

      $msgs[] = [
        'error',
        'Unable to retrieve Zeffy Renewal payments: '
          . $result['message']
      ];

    } else {

      $data = (
        isset($result['data'])
        && is_array($result['data'])
      )
        ? $result['data']
        : [];

      $payments = (
        isset($data['data'])
        && is_array($data['data'])
      )
        ? $data['data']
        : [];

      $rows = [];

      $eligible_count = 0;
      $skipped_count = 0;

      foreach ($payments as $payment) {

        if (!is_array($payment)) {
          $skipped_count++;
          continue;
        }

        $row = coaii_zeffy_renewal_payment_to_import_row(
          $payment
        );

        if ($row === null) {
          $skipped_count++;
          continue;
        }

        $rows[] = $row;
        $eligible_count++;
      }

      if (empty($rows)) {

        $msgs[] = [
          'error',
          'No eligible succeeded Renewal payments with known membership rates were found.'
        ];

      } else {

        $batch_ts = current_time('mysql');

        list($ok, $msg) = coaii_load_staging($rows);

        $msgs[] = [
          $ok ? 'success' : 'error',
          $msg
        ];

        if ($ok) {

          list($ok2, $summary) =
            coaii_normalize_into_ready($batch_ts);

          $msgs[] = [
            $ok2 ? 'success' : 'error',
            $summary
          ];

          if ($ok2) {

            $dupe_det =
              coaii_preimport_duplicate_detector();

            if (!empty($dupe_det['hard_errors'])) {

              $msgs[] = [
                'error',
                'Duplicate detector halted the Renewal API dry-run. Review the details below.'
              ];

            } else {

              if (!empty($dupe_det['warnings'])) {
                $msgs[] = [
                  'info',
                  'Duplicate detector warnings are present. Review them below.'
                ];
              }

              $stats = coaii_plan_upsert();

              $msgs[] = [
                'info',
                sprintf(
                  'Renewal API dry-run: would UPDATE %d and INSERT %d member record(s).',
                  (int)($stats['updates'] ?? 0),
                  (int)($stats['inserts'] ?? 0)
                )
              ];

              $msgs[] = [
                'info',
                sprintf(
                  'API assessment: %d eligible Renewal payment(s); %d payment(s) skipped.',
                  $eligible_count,
                  $skipped_count
                )
              ];

              $preview_rows =
                coaii_get_preview_rows();
            }
          }
        }
      }
    }
  }

  if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['coaii_do_import'])){
    check_admin_referer('coaii_import');
    error_log('[COAI ZEFFY] nonce ok');


    $dry_run  = !empty($_POST['dry_run']);
    $batch_ts = current_time('mysql'); // batch marker for this run
    error_log('[COAI ZEFFY] batch_ts=' . $batch_ts);


    $set_new_on_insert   = !empty($_POST['set_new_on_insert']);
    $clear_new_on_update = !empty($_POST['clear_new_on_update']);

    // --- Accept file ---
    if (!empty($_FILES['zeffy_file']['name']) && is_uploaded_file($_FILES['zeffy_file']['tmp_name'])){
      $fn   = sanitize_file_name($_FILES['zeffy_file']['name']);
      $ext  = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
      $dest = $dir.'/'.time().'-'.$fn;

      if (!move_uploaded_file($_FILES['zeffy_file']['tmp_name'], $dest)){
        $msgs[] = ['error', 'Upload failed.'];
      } else {
        $rows = [];
        try{
          if ($ext==='csv'){
            $rows = coaii_parse_csv($dest);
          } elseif (in_array($ext, ['xlsx','xls'])){
              error_log('[COAI ZEFFY] parsed rows=' . (is_array($rows) ? count($rows) : 0));

            if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')){
              $rows = coaii_parse_xlsx($dest);
            } else {
              $msgs[] = ['error','XLSX support requires PhpSpreadsheet. Convert to CSV and try again.'];
            }
          } else {
            $msgs[] = ['error','Unsupported file type. Use CSV or XLSX.'];
          }
        } catch (Exception $e){
          $msgs[] = ['error','Parse error: '.$e->getMessage()];
        }

        if ($rows){
          list($ok, $msg) = coaii_load_staging($rows);
          $msgs[] = [$ok?'success':'error', $msg];

          if ($ok){
            list($ok2, $summary) = coaii_normalize_into_ready($batch_ts);
            $msgs[] = [$ok2?'success':'error', $summary];

            if ($ok2){
              // Duplicate detector BEFORE plan/upsert
              $dupe_det = coaii_preimport_duplicate_detector();
              if (!empty($dupe_det['hard_errors'])) {
                $msgs[] = ['error', 'Duplicate detector halted import. See details below.'];
                $ok2 = false;
              } else {
                if (!empty($dupe_det['warnings'])) {
                  $msgs[] = ['info', 'Duplicate detector warnings present. Review below before live import.'];
                }
              }
            }

                        if ($ok2){

              error_log('[COAI ZEFFY] before upsert branch dry_run=' . ($dry_run ? 'YES' : 'NO'));

              if ($dry_run){

                error_log('[COAI ZEFFY] about to plan_upsert');
                $stats = coaii_plan_upsert();
                error_log('[COAI ZEFFY] plan_upsert done updates='.(int)($stats['updates'] ?? 0).' inserts='.(int)($stats['inserts'] ?? 0));

                $msgs[] = ['info', sprintf(
                  'Dry-run: would UPDATE %d and INSERT %d rows.',
                  (int)($stats['updates'] ?? 0),
                  (int)($stats['inserts'] ?? 0)
                )];

                error_log('[COAI ZEFFY] about to get_preview_rows');
                $preview_rows = coaii_get_preview_rows();
                error_log('[COAI ZEFFY] get_preview_rows done rows='.(is_array($preview_rows) ? count($preview_rows) : 0));

              } else {

                error_log('[COAI ZEFFY] about to do_upsert');
                $stats = coaii_do_upsert($set_new_on_insert, $clear_new_on_update, $batch_ts);
                error_log('[COAI ZEFFY] do_upsert done updates='.(int)($stats['updates'] ?? 0).' inserts='.(int)($stats['inserts'] ?? 0).' err='.(string)($stats['error'] ?? ''));

                // If the upsert reported an error, stop downstream actions
                if (!empty($stats['error'])) {

                  $msgs[] = ['error', 'Import failed: ' . esc_html((string)$stats['error'])];

                } else {

                  // Assign COAI numbers ONLY to rows inserted in this run
                  error_log('[COAI ZEFFY] about to assign_coai_numbers');
                  $assign_stats = coaii_assign_coai_numbers_for_batch($batch_ts);
                  error_log('[COAI ZEFFY] assign_coai_numbers done assigned='.(int)($assign_stats['assigned'] ?? 0).' skipped='.(int)($assign_stats['skipped'] ?? 0));

                  $msgs[] = ['success', sprintf(
                    'COAI Numbers auto-assigned: %d (skipped: %d).',
                    (int)($assign_stats['assigned'] ?? 0),
                    (int)($assign_stats['skipped'] ?? 0)
                  )];

                  $msgs[] = ['success', sprintf(
                    'Imported: UPDATED %d, INSERTED %d.',
                    (int)($stats['updates'] ?? 0),
                    (int)($stats['inserts'] ?? 0)
                  )];

                  error_log('[COAI ZEFFY] about to log_import_run');
                  coaii_log_import_run($fn, $dry_run, $stats);
                  error_log('[COAI ZEFFY] log_import_run done');

                  error_log('[COAI ZEFFY] about to send_admin_summary');
                  coaii_send_admin_summary($fn, $stats);
                  error_log('[COAI ZEFFY] send_admin_summary done');

                  error_log('[COAI ZEFFY] about to send_member_notifications');
                  coaii_send_member_notifications($fn);
                  error_log('[COAI ZEFFY] send_member_notifications done');

                }
              }

            }

          }
        }
      }
    }

    $ran = true;
  }
  
/*
 * ============================================================
 * SOF Persistent Renewal Identity Review
 * ============================================================
 *
 * Identity assessment results are persisted in the Zeffy
 * transaction ledger. Rebuild the presentation from that
 * established knowledge whenever the Admin opens this page.
 * ============================================================
 */

$sof_identity_attention = [];

if (
    class_exists('SOF_ZeffyTransactionRepository')
    && class_exists('SOF_ZeffyRenewalIdentityService')
) {

    $sof_identity_repository =
        new SOF_ZeffyTransactionRepository();

    $sof_identity_service =
        new SOF_ZeffyRenewalIdentityService();

    $sof_identity_transactions =
        $sof_identity_repository
            ->find_identity_ready_renewals(500);

    foreach (
        $sof_identity_transactions
        as $sof_identity_transaction
    ) {

        if (
            !in_array(
                $sof_identity_transaction->identity_status,
                [
                    'review_required',
                    'ambiguous',
                    'unresolved',
                ],
                true
            )
        ) {
            continue;
        }

        /*
         * Re-assess only to rebuild current candidate evidence
         * and explanation for presentation.
         *
         * The persisted ledger status remains the established
         * SOF identity situation.
         */
        $sof_identity =
            $sof_identity_service->assess(
                $sof_identity_transaction
            );

        $sof_identity_attention[] = [
            'transaction' =>
                $sof_identity_transaction,

            'identity' =>
                $sof_identity,
        ];
    }
}

  echo '<div class="wrap"><h1>Zeffy Import</h1>';

  echo '<h2>Zeffy Connection</h2>';
  echo '<p>Connect MyCOAI directly to Zeffy before processing membership transactions.</p>';
  ?>
    <style>
        .sof-zeffy-workflow {
            max-width: 900px;
            margin-top: 20px;
        }

        .sof-zeffy-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 8px;
            padding: 22px;
            margin-bottom: 18px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }

        .sof-zeffy-card h2 {
            margin-top: 0;
        }

        .sof-zeffy-step {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .sof-zeffy-step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 50%;
            background: #2271b1;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
        }

        .sof-zeffy-step-content {
            flex: 1;
        }

        .sof-zeffy-step-content h2 {
            margin: 0 0 6px;
        }

        .sof-zeffy-step-content p {
            margin: 0 0 14px;
        }

        .sof-zeffy-primary-button {
            min-height: 38px !important;
            padding: 4px 18px !important;
            font-weight: 600;
        }

        .sof-zeffy-diagnostics summary {
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .sof-zeffy-diagnostic-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .sof-zeffy-diagnostic-actions form {
            margin: 0;
        }
    </style>

    <div class="sof-zeffy-workflow">

        <div class="sof-zeffy-card">

            <h2>Zeffy Renewal Processing</h2>

            <p>
                Retrieve new Zeffy transactions, allow SOF to assess
                the Renewals, and then complete business review in MyCOAI.
            </p>

        </div>

        <div class="sof-zeffy-card sof-zeffy-step">

            <div class="sof-zeffy-step-number">
                1
            </div>

            <div class="sof-zeffy-step-content">

                <h2>Retrieve Zeffy Transactions</h2>

                <p>
                    Synchronize the MyCOAI Zeffy transaction ledger
                    with the latest transactions available from Zeffy.
                </p>

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'coaii_sync_zeffy_ledger'
                    );
                    ?>

                    <button
                        type="submit"
                        class="button button-primary sof-zeffy-primary-button"
                        name="coaii_sync_zeffy_ledger"
                        value="1"
                    >
                        Sync Zeffy Transactions
                    </button>

                </form>

            </div>

        </div>

        <div class="sof-zeffy-card sof-zeffy-step">

            <div class="sof-zeffy-step-number">
                2
            </div>

            <div class="sof-zeffy-step-content">

                <h2>Assess Renewals</h2>

                <p>
                    Allow SOF to assess the Renewal transactions before
                    management review.
                </p>

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'coaii_assess_zeffy_all'
                    );
                    ?>

                    <button
                        type="submit"
                        class="button button-primary sof-zeffy-primary-button"
                        name="coaii_assess_zeffy_all"
                        value="1"
                    >
                        Assess Renewals
                    </button>

                </form>

            </div>

        </div>

        <?php if (!empty($sof_identity_attention)) : ?>

            <div class="sof-zeffy-card">

                <h2>
                    Renewal Identity Review
                    (<?php
                        echo esc_html(
                            (string)count(
                                $sof_identity_attention
                            )
                        );
                    ?>)
                </h2>

                <p>
                    SOF could not confidently identify one existing
                    MyCOAI member for the Renewal transactions below.
                    Review the evidence and confirm the correct member
                    before continuing to Renewal Management.
                </p>

                <?php
                foreach (
                    $sof_identity_attention
                    as $sof_identity_row
                ) :

                    $transaction =
                        $sof_identity_row['transaction'];

                    $identity =
                        $sof_identity_row['identity'];

                    $candidates =
                        (
                            isset($identity['candidates'])
                            && is_array(
                                $identity['candidates']
                            )
                        )
                            ? $identity['candidates']
                            : [];
                ?>

                    <div
                        style="
                            margin-top:16px;
                            padding:18px;
                            border:1px solid #dcdcde;
                            border-radius:8px;
                            background:#f9fafb;
                        "
                    >

                        <h3 style="margin-top:0;">
                            <?php
                            echo esc_html(
                                trim(
                                    $transaction->buyer_first_name
                                    . ' '
                                    . $transaction->buyer_last_name
                                )
                            );
                            ?>
                        </h3>

                        <p>
                            <strong>Zeffy Email:</strong>
                            <?php
                            echo esc_html(
                                $transaction->buyer_email !== ''
                                    ? $transaction->buyer_email
                                    : '—'
                            );
                            ?>
                            <br>

                            <strong>Membership Product:</strong>
                            <?php
                            echo esc_html(
                                $transaction->membership_product
                            );
                            ?>
                            <br>

                            <strong>Identity Result:</strong>
                            <?php
                            echo esc_html(
                                (string)(
                                    $identity['identity_status']
                                    ?? $transaction->identity_status
                                )
                            );
                            ?>
                            <br>

                            <strong>Match Method:</strong>
                            <?php
                            echo esc_html(
                                (string)(
                                    $identity['match_method']
                                    ?? $transaction->match_method
                                    ?? '—'
                                )
                            );
                            ?>
                        </p>

                        <?php
                        if (
                            !empty($identity['reason'])
                        ) :
                        ?>

                            <p>
                                <strong>Reason:</strong>
                                <?php
                                echo esc_html(
                                    (string)$identity['reason']
                                );
                                ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($candidates)) : ?>

                            <h4>Candidate Evidence</h4>

                            <table class="widefat striped">

                                <thead>
                                    <tr>
                                        <th>COAI Number</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Expiration</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php
                                    foreach (
                                        $candidates
                                        as $candidate
                                    ) :

                                        $candidate_name =
                                            trim(
                                                (string)(
                                                    $candidate[
                                                        'full_name'
                                                    ]
                                                    ?? ''
                                                )
                                            );

                                        if (
                                            $candidate_name === ''
                                        ) {
                                            $candidate_name =
                                                trim(
                                                    (string)(
                                                        $candidate[
                                                            'first_name'
                                                        ]
                                                        ?? ''
                                                    )
                                                    . ' '
                                                    . (string)(
                                                        $candidate[
                                                            'last_name'
                                                        ]
                                                        ?? ''
                                                    )
                                                );
                                        }

                                        $candidate_coai =
                                            trim(
                                                (string)(
                                                    $candidate[
                                                        'COAI_number'
                                                    ]
                                                    ?? $candidate[
                                                        'coai_number'
                                                    ]
                                                    ?? ''
                                                )
                                            );
                                    ?>

                                        <tr>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $candidate_coai !== ''
                                                        ? $candidate_coai
                                                        : '—'
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $candidate_name !== ''
                                                        ? $candidate_name
                                                        : '—'
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    (string)(
                                                        $candidate[
                                                            'email'
                                                        ]
                                                        ?? '—'
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    (string)(
                                                        $candidate[
                                                            'status'
                                                        ]
                                                        ?? '—'
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    (string)(
                                                        $candidate[
                                                            'membership_expiration'
                                                        ]
                                                        ?? '—'
                                                    )
                                                );
                                                ?>
                                            </td>

                                            <td>

                                                <form method="post">

                                                    <?php
                                                    wp_nonce_field(
                                                        'coaii_prepare_zeffy_identity_confirmation_'
                                                        . (int)$transaction->id
                                                    );
                                                    ?>

                                                    <input
                                                        type="hidden"
                                                        name="zeffy_transaction_id"
                                                        value="<?php
                                                            echo esc_attr(
                                                                (string)$transaction->id
                                                            );
                                                        ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="zeffy_member_id"
                                                        value="<?php
                                                            echo esc_attr(
                                                                (string)(
                                                                    $candidate[
                                                                        'member_id'
                                                                    ]
                                                                    ?? 0
                                                                )
                                                            );
                                                        ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="button button-primary"
                                                        name="coaii_prepare_zeffy_identity_confirmation"
                                                        value="1"
                                                    >
                                                        This Is the Member
                                                    </button>

                                                </form>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        <?php else : ?>

                            <p>
                                SOF did not identify a candidate
                                confidently enough to present here.
                                Use the Identity Review diagnostic
                                search to locate the correct MyCOAI
                                member.
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <div class="sof-zeffy-card sof-zeffy-step">

            <div class="sof-zeffy-step-number">
                3
            </div>

            <div class="sof-zeffy-step-content">

                <h2>Manage Renewals in MyCOAI</h2>

                <p>
                    Review SOF's findings, make management decisions,
                    and confirm membership changes before they are applied.
                </p>

                <a
                    class="button button-primary sof-zeffy-primary-button"
                    href="<?php
                        echo esc_url(
                            home_url(
                                '/renewal-management-review/'
                            )
                        );
                    ?>"
                >
                    Open Renewal Management
                </a>

            </div>

        </div>

        <div class="sof-zeffy-card sof-zeffy-diagnostics">

            <details>

                <summary>
                    Diagnostics &amp; Maintenance
                </summary>

                <p>
                    These tools are retained for troubleshooting and
                    technical review. They are not required during
                    normal Renewal processing.
                </p>

                <div class="sof-zeffy-diagnostic-actions">

                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'coaii_test_zeffy_api'
                        );
                        ?>

                        <button
                            type="submit"
                            class="button"
                            name="coaii_test_zeffy_api"
                            value="1"
                        >
                            Test Zeffy API Connection
                        </button>
                    </form>

                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'coaii_retrieve_zeffy_campaigns'
                        );
                        ?>

                        <button
                            type="submit"
                            class="button"
                            name="coaii_retrieve_zeffy_campaigns"
                            value="1"
                        >
                            Discover Zeffy Campaigns
                        </button>
                    </form>

                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'coaii_assess_zeffy_identities'
                        );
                        ?>

                        <button
                            type="submit"
                            class="button"
                            name="coaii_assess_zeffy_identities"
                            value="1"
                        >
                            Assess Renewal Identities
                        </button>
                    </form>

                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'coaii_assess_zeffy_business'
                        );
                        ?>

                        <button
                            type="submit"
                            class="button"
                            name="coaii_assess_zeffy_business"
                            value="1"
                        >
                            Assess Renewal Business
                        </button>
                    </form>

                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'coaii_unknown_zeffy_renewals'
                        );
                        ?>

                        <button
                            type="submit"
                            class="button"
                            name="coaii_unknown_zeffy_renewals"
                            value="1"
                        >
                            Show Unknown Renewal Products
                        </button>
                    </form>

                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'coaii_retrieve_zeffy_renewals'
                        );
                        ?>

                        <button
                            type="submit"
                            class="button"
                            name="coaii_retrieve_zeffy_renewals"
                            value="1"
                        >
                            Retrieve Recent Renewal Payments
                        </button>
                    </form>

                    <form method="post">
                        <?php
                        wp_nonce_field(
                            'coaii_dry_run_zeffy_renewals'
                        );
                        ?>

                        <button
                            type="submit"
                            class="button"
                            name="coaii_dry_run_zeffy_renewals"
                            value="1"
                        >
                            Dry-Run Renewal API Import
                        </button>
                    </form>

                </div>

            </details>

        </div>

    </div>

    <?php

echo '<hr>';

echo '<div class="sof-zeffy-card sof-zeffy-legacy">';

echo '<details>';

echo '<summary>
        Legacy File Import — Emergency Use Only
      </summary>';

echo '<p style="margin-top:14px;">
        Use this only if the Zeffy API is unavailable or a historical
        transaction must be processed manually.
      </p>';

echo '<h2>File Import</h2>';

echo '<p>
        Upload the Zeffy export (CSV preferred). Use Dry-run first.
      </p>';
  if ($ran){
    foreach ($msgs as $m){
      list($type,$text) = $m;
      $cls = ($type==='success'?'updated':($type==='error'?'error':'notice notice-info'));
      echo '<div class="notice '.$cls.'"><p>'.esc_html($text).'</p></div>';
    }

    // Render duplicate detector details (if any)
    if (!empty($dupe_det)) {
      coaii_render_dupe_detector_results($dupe_det);
    }

    if (!empty($preview_rows)) {
      echo '<h2>Dry-run Preview</h2>';
      echo '<p>This shows what exists now, what came from Zeffy, and what the record should contain after a real import.</p>';
      echo '<table class="widefat fixed striped">';
      echo '<thead><tr>'
        . '<th>Action</th>'
        . '<th>Match By</th>'
        . '<th>Full Name</th>'
        . '<th>Email</th>'
        . '<th>Member ID</th>'
        . '<th>Current COAI #</th>'
        . '<th>Incoming Zeffy COAI #</th>'
        . '<th>Result COAI #</th>'
        . '<th>Possible Match ID</th>'
        . '<th>Possible Match Email</th>'
        . '<th>Possible Match Username</th>'
        . '<th>Possible Match COAI #</th>'
        . '<th>Payment Amount</th>'
        . '<th>Payment Mode</th>'
        . '<th>Renewal Date</th>'
        . '<th>New Expiration</th>'
        . '<th>Old Status</th>'
        . '<th>New Status</th>'
        . '</tr></thead><tbody>';

      foreach ($preview_rows as $row) {
        $action = isset($row['action']) ? (string)$row['action'] : '';
        $result_coai = isset($row['result_coai_number']) ? (string)$row['result_coai_number'] : '';

        echo '<tr>';

        if ($action === 'REVIEW') {
          echo '<td><strong style="color:#b45309;">' . esc_html($action) . '</strong></td>';
        } elseif ($action === 'INSERT') {
          echo '<td><strong>' . esc_html($action) . '</strong></td>';
        } else {
          echo '<td><strong style="color:#166534;">' . esc_html($action) . '</strong></td>';
        }

        echo '<td>' . esc_html($row['match_method']) . '</td>';
        echo '<td>' . esc_html($row['full_name']) . '</td>';
        echo '<td>' . esc_html($row['email']) . '</td>';
        echo '<td>' . esc_html($row['existing_member_id']) . '</td>';
        echo '<td>' . esc_html($row['current_coai_number']) . '</td>';
        echo '<td>' . esc_html($row['incoming_coai_number']) . '</td>';

        if ($result_coai === 'AUTO-GENERATE' || $result_coai === 'REVIEW REQUIRED') {
          echo '<td><strong style="color:#b45309;">' . esc_html($result_coai) . '</strong></td>';
        } else {
          echo '<td>' . esc_html($result_coai) . '</td>';
        }

        echo '<td>' . esc_html($row['possible_member_id']) . '</td>';
        echo '<td>' . esc_html($row['possible_member_email']) . '</td>';
        echo '<td>' . esc_html($row['possible_member_username']) . '</td>';
        echo '<td>' . esc_html($row['possible_member_coai']) . '</td>';
        echo '<td>' . esc_html($row['payment_amount']) . '</td>';
        echo '<td>' . esc_html($row['payment_mode']) . '</td>';
        echo '<td>' . esc_html($row['membership_expiration']) . '</td>';
        echo '<td>' . esc_html($row['membership_expiration']) . '</td>';
        echo '<td>' . esc_html($row['old_status']) . '</td>';
        echo '<td>' . esc_html($row['new_status']) . '</td>';
        echo '</tr>';
      }

      echo '</tbody></table>';
    }
  }

  ?>
  
  <?php if (!empty($zeffy_campaigns)) : ?>

  <h2>Zeffy Campaigns — Diagnostic</h2>

  <p>
    Campaign information retrieved directly from Zeffy.
    No MyCOAI member records have been changed.
  </p>

  <pre style="
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    max-height: 600px;
    overflow: auto;
    white-space: pre-wrap;
  "><?php
    echo esc_html(
      wp_json_encode(
        $zeffy_campaigns,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
      )
    );
  ?></pre>

  <hr>

<?php endif; ?>

<?php
if (
    $zeffy_identity_confirmation_transaction
    && $zeffy_identity_confirmation_member
) :
?>

  <?php
  $confirm_transaction =
    $zeffy_identity_confirmation_transaction;

  $confirm_member =
    $zeffy_identity_confirmation_member;

  $confirm_member_name = trim(
      (string)($confirm_member['full_name'] ?? '')
  );

  if ($confirm_member_name === '') {
      $confirm_member_name = trim(
          (string)($confirm_member['first_name'] ?? '')
          . ' ' .
          (string)($confirm_member['last_name'] ?? '')
      );
  }

  $confirm_member_coai = trim(
      (string)(
          $confirm_member['COAI_number']
          ?? ''
      )
  );
  ?>

  <div
    class="notice notice-warning"
    style="
      padding: 18px;
      margin: 20px 0;
      border-left-width: 4px;
    "
  >

    <h2 style="margin-top: 0;">
      Confirm Identity Match
    </h2>

    <p>
      Review both identities before recording this decision.
      No membership dates or membership status will be changed.
    </p>

    <table
      class="widefat striped"
      style="margin-bottom: 16px;"
    >
      <thead>
        <tr>
          <th></th>
          <th>Name</th>
          <th>Email</th>
          <th>COAI Number</th>
          <th>Member ID</th>
        </tr>
      </thead>

      <tbody>

        <tr>
          <th>Zeffy Payment</th>

          <td>
            <?php
            echo esc_html(
                trim(
                    $confirm_transaction->buyer_first_name .
                    ' ' .
                    $confirm_transaction->buyer_last_name
                )
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
                $confirm_transaction->buyer_email !== ''
                    ? $confirm_transaction->buyer_email
                    : '—'
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
                $confirm_transaction->coai_number !== ''
                    ? $confirm_transaction->coai_number
                    : '—'
            );
            ?>
          </td>

          <td>—</td>

        </tr>

        <tr>
          <th>Selected MyCOAI Member</th>

          <td>
            <?php
            echo esc_html(
                $confirm_member_name !== ''
                    ? $confirm_member_name
                    : '—'
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
                !empty($confirm_member['email'])
                    ? (string)$confirm_member['email']
                    : '—'
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
                $confirm_member_coai !== ''
                    ? $confirm_member_coai
                    : '—'
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
                (string)$confirm_member['member_id']
            );
            ?>
          </td>

        </tr>

      </tbody>
    </table>

    <p>
      <strong>Membership Product:</strong>
      <?php
      echo esc_html(
          $confirm_transaction->membership_product
      );
      ?>
    </p>

    <form
      method="post"
      style="display: inline-block; margin-right: 8px;"
    >

      <?php
      wp_nonce_field(
          'coaii_resolve_zeffy_identity_' .
          (int)$confirm_transaction->id
      );
      ?>

      <input
        type="hidden"
        name="zeffy_transaction_id"
        value="<?php
          echo esc_attr(
              (string)$confirm_transaction->id
          );
        ?>"
      >

      <input
        type="hidden"
        name="zeffy_member_id"
        value="<?php
          echo esc_attr(
              (string)$confirm_member['member_id']
          );
        ?>"
      >

      <button
        type="submit"
        class="button button-primary"
        name="coaii_resolve_zeffy_identity"
        value="1"
      >
        Confirm Match
      </button>

    </form>

    <form
      method="post"
      style="display: inline-block;"
    >

      <?php
      wp_nonce_field(
          'coaii_assess_zeffy_identities'
      );
      ?>

      <button
        type="submit"
        class="button"
        name="coaii_assess_zeffy_identities"
        value="1"
      >
        Cancel
      </button>

    </form>

  </div>

<?php endif; ?>

<?php if (!empty($zeffy_business_results)) : ?>

  <?php
  
  $business_ready = array_filter(
    $zeffy_business_results,
    function ($row) {
        $status = (string)(
            $row['assessment']['assessment_status']
            ?? 'cannot_assess'
        );

        return $status === 'ready_to_apply';
      }
  );

  $business_attention = array_filter(
      $zeffy_business_results,
      function ($row) {
          $status = (string)(
              $row['assessment']['assessment_status']
              ?? 'cannot_assess'
          );

          return $status !== 'ready_to_apply';
      }
  );

  $business_attention = array_filter(
      $zeffy_business_results,
      function ($row) {
          $status = (string)(
              $row['assessment']['assessment_status']
              ?? 'cannot_assess'
          );

          return $status !== 'ready_to_apply';
      }
  );
  ?>

  <h2>Renewal Business Assessment</h2>

  <p>
    SOF compared each matched Renewal payment with the member's
    current MyCOAI expiration date. This assessment is read-only.
    No membership dates or statuses have been changed.
  </p>

  <?php if (empty($business_attention)) : ?>

    <div class="notice notice-success inline">
      <p>
        All assessed Renewal transactions are ready for the
        standard renewal path.
      </p>
    </div>

  <?php else : ?>

    <h3>Renewals Requiring Attention</h3>

    <p>
      These Renewal show SOF's current business assessment for
      each matched Renewal transaction. No membership action has
      been taken.
    </p>

    <table
      class="widefat striped"
      style="margin-bottom: 24px;"
    >
      <thead>
        <tr>
          <th>Member</th>
          <th>COAI Number</th>
          <th>Renewal Date</th>
          <th>Current Expiration</th>
          <th>Standard Expiration</th>
          <th>Membership Product</th>
          <th>Assessment</th>
          <th>Reason</th>
        </tr>
      </thead>

      <tbody>

        <?php foreach ($zeffy_business_results as $row) : ?>

          <?php
          $transaction = $row['transaction'];
          $assessment  = $row['assessment'];

          $member = (
              isset($assessment['member'])
              && is_array($assessment['member'])
          )
              ? $assessment['member']
              : [];

          $member_name = trim(
              (string)($member['full_name'] ?? '')
          );

          if ($member_name === '') {
              $member_name = trim(
                  (string)($member['first_name'] ?? '')
                  . ' ' .
                  (string)($member['last_name'] ?? '')
              );
          }

          $member_coai = trim(
              (string)(
                  $member['COAI_number']
                  ?? ''
              )
          );

          $renewal_date = trim(
              (string)(
                  $assessment['renewal_date']
                  ?? ''
              )
          );

          $current_expiration = trim(
              (string)(
                  $assessment['current_expiration']
                  ?? ''
              )
          );

          $standard_expiration = trim(
              (string)(
                  $assessment['standard_expiration']
                  ?? ''
              )
          );
          ?>

          <tr>

            <td>
              <?php
              echo esc_html(
                  $member_name !== ''
                      ? $member_name
                      : trim(
                          $transaction->buyer_first_name
                          . ' ' .
                          $transaction->buyer_last_name
                      )
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  $member_coai !== ''
                      ? $member_coai
                      : '—'
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  $renewal_date !== ''
                      ? wp_date(
                          'm/d/Y',
                          strtotime($renewal_date)
                      )
                      : '—'
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  $current_expiration !== ''
                      ? wp_date(
                          'm/d/Y',
                          strtotime($current_expiration)
                      )
                      : '—'
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  $standard_expiration !== ''
                      ? wp_date(
                          'm/d/Y',
                          strtotime($standard_expiration)
                      )
                      : '—'
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  $transaction->membership_product
              );
              ?>
            </td>

            <td>
              <strong>
                <?php
                echo esc_html(
                    (string)(
                        $assessment['assessment_title']
                        ?? ''
                    )
                );
                ?>
              </strong>

              <br>

              <?php
              echo esc_html(
                  (string)(
                      $assessment['recommended_path']
                      ?? ''
                  )
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  (string)(
                      $assessment['reason']
                      ?? ''
                  )
              );
              ?>
            </td>

          </tr>

        <?php endforeach; ?>

      </tbody>
    </table>

  <?php endif; ?>

<?php endif; ?>

<?php if (!empty($zeffy_identity_results)) : ?>

  <?php
  $identity_attention = array_filter(
      $zeffy_identity_results,
      function ($row) {
          $status =
              (string)($row['identity']['identity_status'] ?? '');

          return $status !== 'matched';
      }
  );
  ?>

  <?php if (!empty($identity_attention)) : ?>

    <h2>Renewal Identity Review</h2>

    <p>
      SOF could not confidently identify one existing member
      for these Renewal transactions. No membership action has
      been taken.
    </p>

    <table
      class="widefat striped"
      style="margin-bottom: 24px;"
    >
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>COAI Number</th>
          <th>Membership Product</th>
          <th>Identity Result</th>
          <th>Match Method</th>
          <th>Reason</th>
        </tr>
      </thead>

      <tbody>

        <?php foreach ($identity_attention as $row) : ?>

          <?php
          $transaction = $row['transaction'];
          $identity = $row['identity'];

          $candidates = (
              isset($identity['candidates'])
              && is_array($identity['candidates'])
          )
              ? $identity['candidates']
              : [];
          ?>

          <tr>

            <td>
              <?php
              echo esc_html(
                  trim(
                      $transaction->buyer_first_name . ' ' .
                      $transaction->buyer_last_name
                  )
              );
              ?>
            </td>

            <td>
              <?php echo esc_html($transaction->buyer_email); ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  $transaction->coai_number !== ''
                      ? $transaction->coai_number
                      : '—'
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  $transaction->membership_product
              );
              ?>
            </td>

            <td>
              <strong>
                <?php
                echo esc_html(
                    (string)($identity['identity_status'] ?? '')
                );
                ?>
              </strong>
            </td>

            <td>
              <?php
              echo esc_html(
                  (string)($identity['match_method'] ?? '—')
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                  (string)($identity['reason'] ?? '')
              );
              ?>
            </td>

          </tr>

          <tr>
            <td colspan="7">

              <div
                style="
                  margin: 8px 12px 16px;
                  padding: 14px;
                  background: #ffffff;
                  border: 1px solid #dcdcde;
                "
              >

                <strong>Candidate Evidence</strong>

                <?php if (!empty($candidates)) : ?>

                  <p style="margin: 6px 0 12px;">
                    These are the existing MyCOAI member records
                    SOF considered when assessing this transaction.
                  </p>

                  <table class="widefat striped">
                    <thead>
                      <tr>
                        <th>Member ID</th>
                        <th>COAI Number</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Expiration</th>
                        <th>Resolve</th>
                      </tr>
                    </thead>

                    <tbody>

                      <?php foreach ($candidates as $candidate) : ?>

                        <?php
                        $candidate_name = trim(
                            (string)($candidate['full_name'] ?? '')
                        );

                        if ($candidate_name === '') {
                            $candidate_name = trim(
                                (string)($candidate['first_name'] ?? '') .
                                ' ' .
                                (string)($candidate['last_name'] ?? '')
                            );
                        }

                        $candidate_coai = trim(
                            (string)(
                                $candidate['COAI_number']
                                ?? $candidate['coai_number']
                                ?? ''
                            )
                        );

                        $candidate_email = trim(
                            (string)($candidate['email'] ?? '')
                        );

                        $candidate_username = trim(
                            (string)($candidate['username'] ?? '')
                        );

                        $candidate_status = trim(
                            (string)($candidate['status'] ?? '')
                        );

                        $candidate_expiration = trim(
                            (string)(
                                $candidate['membership_expiration']
                                ?? ''
                            )
                        );
                        ?>

                        <tr>

                          <td>
                            <?php
                            echo esc_html(
                                (string)($candidate['member_id'] ?? '—')
                            );
                            ?>
                          </td>

                          <td>
                            <?php
                            echo esc_html(
                                $candidate_coai !== ''
                                    ? $candidate_coai
                                    : '—'
                            );
                            ?>
                          </td>

                          <td>
                            <?php
                            echo esc_html(
                                $candidate_name !== ''
                                    ? $candidate_name
                                    : '—'
                            );
                            ?>
                          </td>

                          <td>
                            <?php
                            echo esc_html(
                                $candidate_email !== ''
                                    ? $candidate_email
                                    : '—'
                            );
                            ?>
                          </td>

                          <td>
                            <?php
                            echo esc_html(
                                $candidate_username !== ''
                                    ? $candidate_username
                                    : '—'
                            );
                            ?>
                          </td>

                          <td>
                            <?php
                            echo esc_html(
                                $candidate_status !== ''
                                    ? $candidate_status
                                    : '—'
                            );
                            ?>
                          </td>

                          <td>
                            <?php
                            echo esc_html(
                                $candidate_expiration !== ''
                                    ? $candidate_expiration
                                    : '—'
                            );
                            ?>
                          </td>
                          
                          <td>

                              <form method="post">

                                  <?php
                                  wp_nonce_field(
                                      'coaii_prepare_zeffy_identity_confirmation_' .
                                      (int)$transaction->id
                                  );
                                  ?>

                                  <input
                                      type="hidden"
                                      name="zeffy_transaction_id"
                                      value="<?php
                                          echo esc_attr(
                                              (string)$transaction->id
                                          );
                                        ?>"
                                  >

                                  <input
                                      type="hidden"
                                      name="zeffy_member_id"
                                      value="<?php
                                          echo esc_attr(
                                              (string)($candidate['member_id'] ?? 0)
                                          );
                                        ?>"
                                  >

                                  <button
                                      type="submit"
                                      class="button button-primary"
                                      name="coaii_prepare_zeffy_identity_confirmation"
                                      value="1"
                                  >
                                      This Is the Member
                                  </button>

                              </form>

                          </td>

                        </tr>

                      <?php endforeach; ?>

                    </tbody>
                  </table>

                <?php else : ?>

                  <p style="margin: 6px 0 0;">
                    No existing MyCOAI member candidates were
                    identified from the current COAI number,
                    email, and name evidence.
                  </p>

                <?php endif; ?>

                <div style="margin-top: 16px;">

                  <strong>Can't find the correct member?</strong>

                  <p style="margin: 6px 0 10px;">
                    Search MyCOAI by name, COAI number,
                    email address, or username.
                  </p>

                  <form method="post">

                    <?php
                    wp_nonce_field(
                        'coaii_search_zeffy_member_' .
                        (int)$transaction->id
                    );
                    ?>

                    <input
                      type="hidden"
                      name="zeffy_transaction_id"
                      value="<?php
                        echo esc_attr(
                          (string)$transaction->id
                        );
                      ?>"
                    >

                    <input
                      type="text"
                      name="zeffy_member_search"
                      value="<?php
                        echo (
                          $zeffy_member_search_transaction_id ===
                          (int)$transaction->id
                        )
                          ? esc_attr($zeffy_member_search)
                          : '';
                      ?>"
                      placeholder="Name, COAI number, email, or username"
                      style="min-width: 300px;"
                    >

                    <button
                      type="submit"
                      class="button"
                      name="coaii_search_zeffy_member"
                      value="1"
                    >
                      Search MyCOAI
                    </button>

                  </form>

                </div>

                <?php
                if (
                    $zeffy_member_search_transaction_id ===
                        (int)$transaction->id
                    && $zeffy_member_search !== ''
                ) :
                ?>

                  <div style="margin-top: 16px;">

                    <strong>MyCOAI Search Results</strong>

                    <?php if (empty($zeffy_member_search_results)) : ?>

                      <p>
                        No active MyCOAI members matched this search.
                      </p>

                    <?php else : ?>

                      <table
                        class="widefat striped"
                        style="margin-top: 10px;"
                      >
                        <thead>
                          <tr>
                            <th>Member ID</th>
                            <th>COAI Number</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Expiration</th>
                            <th>Resolve</th>
                          </tr>
                        </thead>

                        <tbody>

                          <?php
                          foreach (
                              $zeffy_member_search_results
                              as $search_member
                          ) :
                          ?>

                            <?php
                            $search_name = trim(
                                (string)(
                                    $search_member['full_name']
                                    ?? ''
                                )
                            );

                            if ($search_name === '') {
                                $search_name = trim(
                                    (string)(
                                        $search_member['first_name']
                                        ?? ''
                                    )
                                    . ' ' .
                                    (string)(
                                        $search_member['last_name']
                                        ?? ''
                                    )
                                );
                            }

                            $search_coai = trim(
                                (string)(
                                    $search_member['COAI_number']
                                    ?? $search_member['coai_pick']
                                    ?? ''
                                )
                            );
                            ?>

                            <tr>

                              <td>
                                <?php
                                echo esc_html(
                                    (string)(
                                        $search_member['member_id']
                                        ?? ''
                                    )
                                );
                                ?>
                              </td>

                              <td>
                                <?php
                                echo esc_html(
                                    $search_coai !== ''
                                        ? $search_coai
                                        : '—'
                                );
                                ?>
                              </td>

                              <td>
                                <?php
                                echo esc_html(
                                    $search_name !== ''
                                        ? $search_name
                                        : '—'
                                );
                                ?>
                              </td>

                              <td>
                                <?php
                                echo esc_html(
                                    (string)(
                                        $search_member['email']
                                        ?? '—'
                                    )
                                );
                                ?>
                              </td>

                              <td>
                                <?php
                                echo esc_html(
                                    (string)(
                                        $search_member['username']
                                        ?? '—'
                                    )
                                );
                                ?>
                              </td>

                              <td>
                                <?php
                                echo esc_html(
                                    (string)(
                                        $search_member['status']
                                        ?? '—'
                                    )
                                );
                                ?>
                              </td>

                              <td>
                                <?php
                                echo esc_html(
                                    (string)(
                                        $search_member[
                                            'membership_expiration'
                                        ]
                                        ?? '—'
                                    )
                                );
                                ?>
                              </td>

                              <td>

                                <form method="post">

                                  <?php
                                  wp_nonce_field(
                                      'coaii_prepare_zeffy_identity_confirmation_' .
                                      (int)$transaction->id
                                  );
                                  ?>

                                  <input
                                    type="hidden"
                                    name="zeffy_transaction_id"
                                    value="<?php
                                      echo esc_attr(
                                        (string)$transaction->id
                                      );
                                    ?>"
                                  >

                                  <input
                                    type="hidden"
                                    name="zeffy_member_id"
                                    value="<?php
                                      echo esc_attr(
                                        (string)(
                                          $search_member['member_id']
                                          ?? 0
                                        )
                                      );
                                    ?>"
                                  >

                                  <button
                                    type="submit"
                                    class="button button-primary"
                                    name="coaii_prepare_zeffy_identity_confirmation"
                                    value="1"
                                  >
                                    This Is the Member
                                  </button>

                                </form>

                              </td>

                            </tr>

                          <?php endforeach; ?>

                        </tbody>
                      </table>

                    <?php endif; ?>

                  </div>

                <?php endif; ?>

              </div>

            </td>
          </tr>

        <?php endforeach; ?>

      </tbody>
    </table>

  <?php endif; ?>

<?php endif; ?>

<?php if (!empty($zeffy_unknown_renewals)) : ?>

  <h2>Unknown Renewal Products</h2>

  <p>
    These succeeded Renewal payments contain Zeffy rate IDs
    that SOF has not yet mapped to a COAI membership product.
    No membership action has been taken.
  </p>

  <table
    class="widefat striped"
    style="margin-bottom: 24px;"
  >
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>COAI Number</th>
        <th>Payment Date</th>
        <th>Amount</th>
        <th>Rate ID</th>
        <th>Status</th>
      </tr>
    </thead>

    <tbody>

      <?php foreach ($zeffy_unknown_renewals as $transaction) : ?>

        <tr>

          <td>
            <?php
            echo esc_html(
              trim(
                $transaction->buyer_first_name . ' ' .
                $transaction->buyer_last_name
              )
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
              $transaction->buyer_email !== ''
                ? $transaction->buyer_email
                : '—'
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
              $transaction->coai_number !== ''
                ? $transaction->coai_number
                : '—'
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
              $transaction->payment_date !== null
                ? $transaction->payment_date
                : '—'
            );
            ?>
          </td>

          <td>
            <?php
            echo esc_html(
              '$' .
              number_format(
                $transaction->payment_amount,
                2
              )
            );
            ?>
          </td>

          <td>
            <code>
              <?php
              echo esc_html(
                $transaction->zeffy_rate_id !== ''
                  ? $transaction->zeffy_rate_id
                  : '—'
              );
              ?>
            </code>
          </td>

          <td>
            <?php
            echo esc_html(
              $transaction->payment_status
            );
            ?>
          </td>

        </tr>

      <?php endforeach; ?>

    </tbody>
  </table>

<?php endif; ?> 

<?php if (!empty($zeffy_renewal_payments)) : ?>

  <h2>Recent COAI Renewal Payments — Diagnostic</h2>

  <p>
    These payments were retrieved directly from the
    COAI Renewal Membership campaign in Zeffy.
    No MyCOAI member records have been changed.
  </p>

  <h3>Renewal Payment Summary</h3>

  <div style="overflow-x: auto; margin-bottom: 24px;">
    <table class="widefat striped">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>COAI Number</th>
          <th>Status</th>
          <th>Payment Date</th>
          <th>Amount</th>
          <th>Membership Product</th>
          <th>Rate ID</th>
          <th>Family Email 1</th>
          <th>Family Email 2</th>
        </tr>
      </thead>

      <tbody>

        <?php foreach ($zeffy_renewal_payments as $payment) : ?>

          <?php
          $buyer = isset($payment['buyer']) && is_array($payment['buyer'])
            ? $payment['buyer']
            : [];

          $buyer_questions =
            isset($payment['buyer_questions'])
            && is_array($payment['buyer_questions'])
              ? $payment['buyer_questions']
              : [];

          $first_name = trim((string)($buyer['first_name'] ?? ''));
          $last_name  = trim((string)($buyer['last_name'] ?? ''));
          $email      = trim((string)($buyer['email'] ?? ''));

          $coai_number = coaii_zeffy_question_answer(
            $buyer_questions,
            'COAI_Number'
          );
          
          $payment_status = isset($payment['status'])
              ? trim((string) $payment['status'])
              : '';
              
          $created_timestamp = isset($payment['created'])
              ? (int) $payment['created']
              : 0;
              
          $payment_date = $created_timestamp > 0
              ? wp_date(
                  'm/d/Y',
                  $created_timestamp,
                  new DateTimeZone('America/New_York')
                )
                : '';

          $amount_cents = isset($payment['amount'])
            ? (int) $payment['amount']
            : 0;

          $amount = '$' . number_format($amount_cents / 100, 2);

          $rate_id = '';
          $membership_product = null;
          $family_email_1 = '';
          $family_email_2 = '';

          if (
            isset($payment['items'][0])
            && is_array($payment['items'][0])
          ) {
            $item = $payment['items'][0];

            $rate_id = trim((string)($item['rate_id'] ?? ''));
            
            $membership_product = coaii_zeffy_renewal_rate(
                $rate_id
            );

            $item_questions =
              isset($item['questions'])
              && is_array($item['questions'])
                ? $item['questions']
                : [];

            $family_email_1 = coaii_zeffy_question_answer(
              $item_questions,
              'Family member 1 Email'
            );

            $family_email_2 = coaii_zeffy_question_answer(
              $item_questions,
              'Family member 2 Email'
            );
          }
          ?>

          <tr>
            <td>
              <?php
              echo esc_html(
                trim($first_name . ' ' . $last_name)
              );
              ?>
            </td>

            <td>
              <?php echo esc_html($email); ?>
            </td>

            <td>
              <?php
              echo esc_html(
                $coai_number !== '' ? $coai_number : '—'
              );
              ?>
            </td>
            
            <td>
                <?php
                echo esc_html(
                    $payment_status !== '' ? $payment_status : '_'
                );
                ?>
            </td>
            
            <td>
                <?php
                echo esc_html(
                    $payment_date !== '' ? $payment_date : '—'
                );
                ?>
            </td>

            <td>
              <?php echo esc_html($amount); ?>
            </td>

            <td>
                <?php if ($membership_product !== null) : ?>
                
                    <strong>
                        <?php
                        echo esc_html(
                            $membership_product['name']
                        );
                        ?>
                    </strong>
                    
                <?php else : ?>
                
                    <span style="color:#b45309;">
                        Not yet identified
                    </span>
                    
                <?php endif; ?>
            </td>
            
            <td>
              <code><?php echo esc_html($rate_id); ?></code>
            </td>

            <td>
              <?php
              echo esc_html(
                $family_email_1 !== '' ? $family_email_1 : '—'
              );
              ?>
            </td>

            <td>
              <?php
              echo esc_html(
                $family_email_2 !== '' ? $family_email_2 : '—'
              );
              ?>
            </td>
          </tr>

        <?php endforeach; ?>

      </tbody>
    </table>
  </div>

  <h3>Raw Renewal Payment Data</h3>

  <?php foreach ($zeffy_renewal_payments as $index => $payment) : ?>

    <details style="margin-bottom: 12px;">
      <summary>
        <strong>
          Renewal Payment
          <?php echo esc_html((string)($index + 1)); ?>
        </strong>
      </summary>

      <pre style="
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 15px;
        margin-top: 8px;
        max-height: 600px;
        overflow: auto;
        white-space: pre-wrap;
      "><?php
        echo esc_html(
          wp_json_encode(
            $payment,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
          )
        );
      ?></pre>

    </details>

  <?php endforeach; ?>

  <hr>

<?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('coaii_import'); ?>
    <table class="form-table">
      <tr>
        <th scope="row">File</th>
        <td><input type="file" name="zeffy_file" accept=".csv,.xlsx,.xls" required></td>
      </tr>

      <tr>
        <th scope="row">Mode</th>
        <td>
          <label>
            <input type="checkbox" name="dry_run" value="1" checked>
            Dry-run (analyze only)
          </label>
        </td>
      </tr>

      <tr>
        <th scope="row">New Member Flag</th>
        <td>
          <label>
            <input type="checkbox" name="set_new_on_insert" value="1" checked>
            Mark INSERTED records as New Members (first year)
          </label>
        </td>
      </tr>

      <tr>
        <th scope="row">Renewal Behavior</th>
        <td>
          <label>
            <input type="checkbox" name="clear_new_on_update" value="1" checked>
            Clear New Member flag on UPDATED records (renewals)
          </label>
        </td>
      </tr>
    </table>

    <p><button class="button button-primary" name="coaii_do_import" value="1">Process File</button></p>
  </form>
  <?php
  echo '</div>';
}

/**
 * ------------------------------------------------------------
 * Header normalization
 * ------------------------------------------------------------
 */
function coaii_normalize_header($h){
  $s   = trim((string)$h);
  $low = strtolower($s);

  if (strpos($low,'email') !== false) return 'Email';

  if (strpos($low,'coai') !== false ||
      (strpos($low,'member') !== false && strpos($low,'number') !== false)) {
    return 'COAI_number';
  }

  if (strpos($low,'first name') !== false ||
      (strpos($low,'first') !== false && strpos($low,'name') !== false)) return 'First Name';

  if (strpos($low,'last name') !== false ||
      (strpos($low,'last') !== false && strpos($low,'name') !== false)) return 'Last Name';

  if (strpos($low,'phone') !== false && strpos($low,'number') === false) return 'Phone Number';
  if (strpos($low,'mobile') !== false || strpos($low,'cell') !== false) return 'Mobile';

  if (strpos($low,'address2') !== false || strpos($low,'address 2') !== false) return 'Address2';
  if (strpos($low,'address') !== false) return 'Address';

  if (strpos($low,'city') !== false) return 'City';
  if (strpos($low,'postal') !== false || strpos($low,'zip') !== false) return 'Postal Code';
  if (strpos($low,'state') !== false) return 'State';
  if (strpos($low,'country') !== false) return 'Country';

  if (strpos($low,'total amount') !== false || strpos($low,'amount') !== false) return 'Total Amount';
  if (strpos($low,'payment method') !== false) return 'Payment Method';
  if (strpos($low,'payment status') !== false) return 'Payment Status';
  if (strpos($low,'payout date') !== false) return 'Payout Date';
  if (strpos($low,'payment date') !== false) return 'Payment Date (America/New_York)';
  if (strpos($low,'expiration') !== false || strpos($low,'expiry') !== false) return 'Expiration Date';
  if (strpos($low,'details') !== false) return 'Details';
  if (strpos($low,'campaign') !== false || strpos($low,'product') !== false) return 'Campaign Title';

  if (strpos($low,'alley') !== false) return 'Alley_Membership';

  if (strpos($low,'shipping') !== false) {
    if (strpos($low,'address') !== false) return 'Shipping_Address';
    if (strpos($low,'city') !== false)    return 'Shipping_City';
    if (strpos($low,'state') !== false)   return 'Shipping_State';
    if (strpos($low,'zip') !== false || strpos($low,'postal') !== false) return 'Shipping_Zip';
    if (strpos($low,'country') !== false) return 'Shipping_Country';
  }

  if (strpos($low,'billing') !== false) return 'Billing_Address';
  if (strpos($low,'clown')   !== false) return 'Clown_Name';
  if (strpos($low,'parent')  !== false) return 'Parents Name';
  if (strpos($low,'e-contact') !== false || strpos($low,'e contact') !== false) return 'e-Contact';
  if (strpos($low,'birthday') !== false || strpos($low,'birth date') !== false) return 'Birthday';
  
  if (strpos($low,'family 1 first') !== false) return 'Family1_First_Name';
  if (strpos($low,'family 1 last') !== false) return 'Family1_Last_Name';
  if (strpos($low,'family 1 relationship') !== false) return 'Family1_Relationship';
  if (strpos($low,'family 1 email') !== false) return 'Family1_Email';
  if (strpos($low,'family 1 phone') !== false) return 'Family1_Phone';
  if (strpos($low,'family 1 birthday') !== false) return 'Family1_Birthday';
  
  if (strpos($low,'family 2 first') !== false) return 'Family2_First_Name';
  if (strpos($low,'family 2 last') !== false) return 'Family2_Last_Name';
  if (strpos($low,'family 2 relationship') !== false) return 'Family2_Relationship';
  if (strpos($low,'family 2 email') !== false) return 'Family2_Email';
  if (strpos($low,'family 2 phone') !== false) return 'Family2_Phone';
  if (strpos($low,'family 2 birthday') !== false) return 'Family2_Birthday';
  
  if (strpos($low,'family 3 first') !== false) return 'Family3_First_Name';
  if (strpos($low,'family 3 last') !== false) return 'Family3_Last_Name';
  if (strpos($low,'family 3 relationship') !== false) return 'Family3_Relationship';
  if (strpos($low,'family 3 email') !== false) return 'Family3_Email';
  if (strpos($low,'family 3 phone') !== false) return 'Family3_Phone';
  if (strpos($low,'family 3 birthday') !== false) return 'Family3_Birthday';
  
  

  $fallback = preg_replace('/[^\w\s\-\_\.]/u','', $s);
  $fallback = trim($fallback);
  if ($fallback === '') $fallback = 'col_'.substr(md5($s),0,6);
  return $fallback;
}

/**
 * ------------------------------------------------------------
 * CSV / XLSX parsers
 * ------------------------------------------------------------
 */
function coaii_parse_csv($path){
  $fh = fopen($path, 'r');
  if (!$fh) throw new Exception('Cannot open CSV');

  $firstLine = fgets($fh);
  if ($firstLine === false) {
    fclose($fh);
    throw new Exception('Empty CSV');
  }

  $firstTrim = trim($firstLine);

  // Handle Excel "sep=" hint like: sep=,  or sep=;
  $sepDelimiter = null;
  if (preg_match('/^sep=([^\r\n])$/i', $firstTrim, $m)) {
    $sepDelimiter = $m[1];
  }

  if ($sepDelimiter !== null) {
    $delimiter = $sepDelimiter;
  } else {
    if (strpos($firstLine, "\t") !== false) $delimiter = "\t";
    elseif (strpos($firstLine, ';') !== false) $delimiter = ';';
    else $delimiter = ',';
  }

  if ($sepDelimiter === null) rewind($fh);

  $rows = [];
  $header = null;

  while (($r = fgetcsv($fh, 0, $delimiter)) !== false) {
    $joined = trim(implode('', array_map('strval', $r)));
    if ($joined === '') continue;
    if (preg_match('/^sep=/i', $joined)) continue;

    if ($header === null) {
      $header = array_map(function($h){ return trim((string)$h); }, $r);
      continue;
    }

    if (coaii_row_is_empty($r)) continue;

    if (count($r) < count($header)) $r = array_pad($r, count($header), '');
    elseif (count($r) > count($header)) $r = array_slice($r, 0, count($header));

    $rows[] = array_combine($header, $r);
  }

  fclose($fh);
  return $rows;
}

function coaii_parse_xlsx($path){
  $io = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
  $sh = $io->getActiveSheet();
  $rows = $sh->toArray(null,true,true,true);
  $header = null; $out = [];

  foreach ($rows as $row){
    $vals = array_values($row);
    if ($header===null){ $header=$vals; continue; }
    if (coaii_row_is_empty($vals)) continue;
    $out[] = array_combine($header, $vals);
  }
  return $out;
}

function coaii_row_is_empty($arr){
  foreach($arr as $v){ if (trim((string)$v)!=='') return false; }
  return true;
}

/**
 * ------------------------------------------------------------
 * Stage table loader (drops/recreates; mirrors Zeffy headers + required columns)
 * ------------------------------------------------------------
 */
function coaii_load_staging($rows){
  global $wpdb;
  $staging = 'import_members_staging_zeffy';
  error_log('[COAI ZEFFY] staging loaded');


  if (empty($rows) || !is_array($rows) || !isset($rows[0])) {
    return [false, 'No rows to load.'];
  }

  $origHeaders = array_keys($rows[0]);
  $canonMap = [];
  $usedCanon = [];

  foreach ($origHeaders as $h){
    $canon = coaii_normalize_header($h);
    $base  = $canon;
    $i     = 1;

    while (in_array($canon, $usedCanon, true)) {
      $canon = $base . '_' . $i;
      $i++;
    }
    $canonMap[$h] = $canon;
    $usedCanon[]  = $canon;
  }

  $normRows = [];
  foreach ($rows as $r){
    $nr = [];
    foreach ($r as $origKey => $val){
      $k = isset($canonMap[$origKey]) ? $canonMap[$origKey] : coaii_normalize_header($origKey);
      $nr[$k] = is_null($val) ? null : (string)$val;
    }
    $normRows[] = $nr;
  }

  $cols = array_keys($normRows[0]);

  $requiredCols = [
    'Email','First Name','Last Name','Phone Number','Mobile',
    'Address','Address2','City','State','Postal Code','Country',
    'Shipping_Address','Shipping_City','Shipping_State','Shipping_Zip','Shipping_Country',
    'Billing_Address','Clown_Name','Parents Name','e-Contact','Alley_Membership',
    'COAI_number','Total Amount','Payment Method','Payment Status','Payout Date','Payment Date (America/New_York)',
    'Expiration Date','Details','Campaign Title','Birthday',
    'Family1_First_Name','Family1_Last_Name','Family1_Relationship',
    'Family1_Email','Family1_Phone','Family1_Birthday',
    'Family2_First_Name','Family2_Last_Name','Family2_Relationship',
    'Family2_Email','Family2_Phone','Family2_Birthday',
    'Family3_First_Name','Family3_Last_Name','Family3_Relationship',
    'Family3_Email','Family3_Phone','Family3_Birthday',
  ];

  foreach ($requiredCols as $rc) {
    if (!in_array($rc, $cols, true)) {
      $cols[] = $rc;
      foreach ($normRows as &$row) {
        if (!array_key_exists($rc, $row)) $row[$rc] = null;
      }
      unset($row);
    }
  }

  $sqlCols = [];
  foreach ($cols as $c){
    $sqlCols[] = '`'.esc_sql($c).'` TEXT NULL';
  }

  $wpdb->query('DROP TABLE IF EXISTS `'.$staging.'`');
  $wpdb->query('CREATE TABLE `'.$staging.'` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    '.implode(",\n    ", $sqlCols).',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

  $inserted = 0;

  foreach ($normRows as $r){
    $ordered = [];
    foreach ($cols as $c){
      $ordered[$c] = array_key_exists($c, $r) ? $r[$c] : null;
    }
    $result = $wpdb->insert($staging, $ordered, array_fill(0, count($ordered), '%s'));
    if ($result === false) {
      error_log('[COAI IMPORT] Staging insert failed: ' . $wpdb->last_error);
      continue;
    }
    if ($wpdb->rows_affected > 0) $inserted++;
  }

  return [true, "Loaded $inserted row(s) into staging."];
}

/**
 * ------------------------------------------------------------
 * Normalize staging -> ready table
 * IMPORTANT: Do NOT use $wpdb->prepare() on a query containing STR_TO_DATE('%m/%d/%Y...')
 * because % tokens are counted as placeholders.
 * ------------------------------------------------------------
 */
function coaii_normalize_into_ready($batch_ts){
    error_log('[COAI ZEFFY] normalized into ready');

  global $wpdb;
  $st = 'import_members_staging_zeffy';
  $rt = 'import_members_ready_zeffy';
  $wpdb->query('DROP TABLE IF EXISTS `'.$rt.'`');

  // Safe SQL-quoted batch_ts without preparing the whole SQL string
  $batch_ts_sql = $wpdb->prepare('%s', $batch_ts);

  $sql = "
    CREATE TABLE `{$rt}` AS
    SELECT
      z.`id`                                            AS source_id,
      z.`Email`                                         AS email,
      TRIM(z.`First Name`)                              AS first_name,
      TRIM(z.`Last Name`)                               AS last_name,
      CONCAT(TRIM(COALESCE(z.`First Name`,'')),' ',TRIM(COALESCE(z.`Last Name`,''))) AS full_name,
      NULLIF(TRIM(z.`Phone Number`),'')                 AS phone,
      NULLIF(TRIM(z.`Mobile`),'')                       AS mobile,
      NULLIF(TRIM(z.`Address`),'')                      AS address,
      NULLIF(TRIM(z.`Address2`),'')                     AS address2,
      NULLIF(TRIM(z.`City`),'')                         AS city,
      NULLIF(TRIM(z.`State`),'')                        AS state,
      NULLIF(TRIM(z.`Postal Code`),'')                  AS `zip`,
      CASE WHEN UPPER(TRIM(z.`Country`)) IN ('US','USA','UNITED STATES','UNITED STATES OF AMERICA') THEN 'US'
           ELSE NULLIF(TRIM(z.`Country`),'') END        AS country,
      NULLIF(TRIM(z.`Shipping_Address`),'')             AS shipping_address,
      NULLIF(TRIM(z.`Shipping_City`),'')                AS shipping_city,
      NULLIF(TRIM(z.`Shipping_State`),'')               AS shipping_state,
      NULLIF(TRIM(z.`Shipping_Zip`),'')                 AS shipping_zip,
      NULLIF(TRIM(z.`Shipping_Country`),'')             AS shipping_country,
      NULLIF(TRIM(z.`Clown_Name`),'')                   AS clown_name,
      NULLIF(TRIM(z.`Parents Name`),'')                 AS parent_name,
      NULLIF(TRIM(z.`e-Contact`),'')                    AS e_contact,
      NULLIF(TRIM(z.`Alley_Membership`),'')             AS alley_membership,
      NULLIF(TRIM(z.`Family1_First_Name`),'') AS family1_first_name,
      NULLIF(TRIM(z.`Family1_Last_Name`),'') AS family1_last_name,
      NULLIF(TRIM(z.`Family1_Relationship`),'') AS family1_relationship,
      NULLIF(TRIM(z.`Family1_Email`),'') AS family1_email,
      NULLIF(TRIM(z.`Family1_Phone`),'') AS family1_phone,
      STR_TO_DATE(z.`Family1_Birthday`, '%m/%d/%Y') AS family1_birthday,
      NULLIF(TRIM(z.`Family2_First_Name`),'') AS family2_first_name,
      NULLIF(TRIM(z.`Family2_Last_Name`),'') AS family2_last_name,
      NULLIF(TRIM(z.`Family2_Relationship`),'') AS family2_relationship,
      NULLIF(TRIM(z.`Family2_Email`),'') AS family2_email,
      NULLIF(TRIM(z.`Family2_Phone`),'') AS family2_phone,
      STR_TO_DATE(z.`Family2_Birthday`, '%m/%d/%Y') AS family2_birthday,
      NULLIF(TRIM(z.`Family3_First_Name`),'') AS family3_first_name,
      NULLIF(TRIM(z.`Family3_Last_Name`),'') AS family3_last_name,
      NULLIF(TRIM(z.`Family3_Relationship`),'') AS family3_relationship,
      NULLIF(TRIM(z.`Family3_Email`),'') AS family3_email,
      NULLIF(TRIM(z.`Family3_Phone`),'') AS family3_phone,
      STR_TO_DATE(z.`Family3_Birthday`, '%m/%d/%Y') AS family3_birthday,


      CASE
          WHEN UPPER(TRIM(COALESCE(z.`COAI_number`, z.`COAI_Number`))) IN ('N/A', 'NA', 'NONE', 'NULL')
            THEN NULL
        WHEN TRIM(COALESCE(z.`COAI_number`, z.`COAI_Number`)) = ''
            THEN NULL
        ELSE TRIM(COALESCE(z.`COAI_number`, z.`COAI_Number`))
      END AS COAI_number,

      CAST(
        NULLIF(
          REPLACE(REPLACE(TRIM(z.`Total Amount`), '$', ''), ',', ''),
        '')
      AS DECIMAL(10,2))                                  AS payment_amount,

      CASE
        WHEN z.`Payment Method` IS NULL OR TRIM(z.`Payment Method`) = '' THEN NULL
        WHEN LOWER(z.`Payment Method`) REGEXP 'card|credit|visa|master|mastercard|discover|amex|american express|apple|google|stripe'
          THEN 'CC'
        WHEN LOWER(z.`Payment Method`) REGEXP 'check|cheque'
          THEN 'Check'
        WHEN LOWER(z.`Payment Method`) REGEXP 'cash'
          THEN 'Cash'
        ELSE NULL
      END                                                AS payment_mode,

      COALESCE(
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y, %h:%i %p'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y %h:%i %p'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d %H:%i:%s'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d')
      )                                                  AS registered_date,

      COALESCE(
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y, %h:%i %p'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y %h:%i %p'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d %H:%i:%s'),
        STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d')
      )                                                  AS renewal_date,

      CASE
        WHEN COALESCE(
          STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y, %h:%i %p'),
          STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y %h:%i %p'),
          STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y'),
          STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d %H:%i:%s'),
          STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d')
        ) IS NOT NULL
        THEN DATE_SUB(
          DATE_ADD(
            DATE(COALESCE(
              STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y, %h:%i %p'),
              STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y %h:%i %p'),
              STR_TO_DATE(z.`Payment Date (America/New_York)`, '%m/%d/%Y'),
              STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d %H:%i:%s'),
              STR_TO_DATE(z.`Payment Date (America/New_York)`, '%Y-%m-%d')
            )),
            INTERVAL 1 YEAR
          ),
          INTERVAL 1 DAY
        )
        ELSE NULL
      END                                                AS membership_expiration,
      STR_TO_DATE(SUBSTRING_INDEX(z.`Birthday`, ' GMT', 1),'%a %b %e %Y %H:%i:%s') AS birthday,

      CASE
        WHEN LOWER(z.`Details`) LIKE '%lifetime%'                              THEN 1
        WHEN LOWER(z.`Details`) LIKE '%individual%'                            THEN 2
        WHEN LOWER(z.`Details`) LIKE '%senior member + 1 family%'              THEN 12
        WHEN LOWER(z.`Details`) LIKE '%senior%'                                THEN 3
        WHEN LOWER(z.`Details`) LIKE '%junior joey%'                           THEN 4
        WHEN LOWER(z.`Details`) LIKE '%e-membership - individual + 1 family%'  THEN 11
        WHEN LOWER(z.`Details`) LIKE '%e-membership international%'            THEN 7
        WHEN LOWER(z.`Details`) LIKE '%e-membership%'                          THEN 5
        WHEN LOWER(z.`Details`) LIKE '%international + 2 family%'              THEN 16
        WHEN LOWER(z.`Details`) LIKE '%international + 1 family%'              THEN 14
        WHEN LOWER(z.`Details`) LIKE '%senior international%'                  THEN 15
        WHEN LOWER(z.`Details`) LIKE '%international%'                         THEN 6
        WHEN LOWER(z.`Details`) LIKE '%member + 3 family%'                     THEN 10
        WHEN LOWER(z.`Details`) LIKE '%member + 2 family%'                     THEN 9
        WHEN LOWER(z.`Details`) LIKE '%member + 1 family%'                     THEN 8
        WHEN LOWER(z.`Details`) LIKE '%free membership%'                       THEN 13
        ELSE NULL
      END AS membership_level_id,

      'ACTIVE' AS status,
      {$batch_ts_sql} AS updated_at
    FROM `{$st}` z;
  ";

  $ok = $wpdb->query($sql) !== false;

  if ($ok) {
    $wpdb->query("ALTER TABLE `{$rt}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  }

  return [$ok, $ok ? 'Normalized rows into import_members_ready_zeffy.' : ('Normalize failed: '.$wpdb->last_error)];
}

/**
 * ------------------------------------------------------------
 * Dry-run planner
 * ------------------------------------------------------------
 */
function coaii_plan_upsert(){
  global $wpdb;
  $m  = coaii_get_members_table();
  $rt = 'import_members_ready_zeffy';
  $join = coaii_join_sql();

  $updates = (int)$wpdb->get_var("
    SELECT COUNT(*)
    FROM `{$m}` m
    JOIN `{$rt}` z ON {$join}
  ");

  $inserts = (int)$wpdb->get_var("
    SELECT COUNT(*)
    FROM `{$rt}` z
    LEFT JOIN `{$m}` m ON {$join}
    WHERE m.member_id IS NULL
  ");

  return ['updates'=>$updates,'inserts'=>$inserts];
}

/**
 * ------------------------------------------------------------
 * Upsert logic
 * Notes:
 * - Matching is done by join_sql (email matches m.email OR m.username; COAI fallback)
 * - COAI numbers are assigned INSERT-ONLY after insert
 * - Per your preference: we DO NOT overwrite an existing m.email with z.email.
 *   (We still keep username behavior as in your original code.)
 * ------------------------------------------------------------
 */
function coaii_do_upsert($set_new_on_insert = true, $clear_new_on_update = true, $batch_ts = null){
  global $wpdb;
  $m  = coaii_get_members_table();
  $rt = 'import_members_ready_zeffy';
  $join = coaii_join_sql();

  if ($batch_ts === null) $batch_ts = current_time('mysql');

  $wpdb->query('START TRANSACTION');

  // UPDATE existing
  $set_new_sql = $clear_new_on_update ? "m.is_new_member = 0,\n" : "";

  $ok_update = $wpdb->query("
    UPDATE `{$m}` m
    JOIN `{$rt}` z ON {$join}
    SET
      m.full_name             = COALESCE(z.full_name, m.full_name),

      -- Preserve existing email if present; only fill if blank
      m.email = CASE
        -- If matched by email, keep existing logic (don’t overwrite unless blank)
        WHEN z.email IS NOT NULL AND z.email <> '' AND (
          LOWER(TRIM(z.email)) = LOWER(TRIM(m.email))
          OR LOWER(TRIM(z.email)) = LOWER(TRIM(m.username))
        )
          THEN CASE
            WHEN (m.email IS NULL OR TRIM(m.email) = '') THEN z.email
            ELSE m.email
          END

        -- If matched by COAI_number (email differs), accept Zeffy email as new canonical email
        WHEN z.email IS NOT NULL AND z.email <> ''
          THEN z.email

        ELSE m.email
      END,

      m.first_name            = COALESCE(z.first_name, m.first_name),
      m.last_name             = COALESCE(z.last_name, m.last_name),
      m.phone                 = COALESCE(z.phone, m.phone),
      m.mobile                = COALESCE(z.mobile, m.mobile),
      m.address               = COALESCE(z.address, m.address),
      m.address2              = COALESCE(z.address2, m.address2),
      m.city                  = COALESCE(z.city, m.city),
      m.state                 = COALESCE(z.state, m.state),
      m.zip                   = COALESCE(z.zip, m.zip),
      m.country               = COALESCE(z.country, m.country),
      m.shipping_address      = COALESCE(z.shipping_address, m.shipping_address),
      m.shipping_city         = COALESCE(z.shipping_city, m.shipping_city),
      m.shipping_state        = COALESCE(z.shipping_state, m.shipping_state),
      m.shipping_zip          = COALESCE(z.shipping_zip, m.shipping_zip),
      m.shipping_country      = COALESCE(z.shipping_country, m.shipping_country),
      m.clown_name            = COALESCE(z.clown_name, m.clown_name),
      m.parent_name           = COALESCE(z.parent_name, m.parent_name),
      m.e_contact             = COALESCE(z.e_contact, m.e_contact),
      m.alley_membership      = COALESCE(z.alley_membership, m.alley_membership),

      -- Only fill COAI_number if blank (DO NOT overwrite existing)
      m.COAI_number           = CASE
                                  WHEN (m.COAI_number IS NULL OR TRIM(m.COAI_number) = '')
                                       AND z.COAI_number IS NOT NULL AND z.COAI_number <> ''
                                    THEN z.COAI_number
                                  ELSE m.COAI_number
                                END,

      -- Only set username if blank
      m.username              = CASE
                                  WHEN (m.username IS NULL OR m.username = '')
                                       AND z.email IS NOT NULL AND z.email <> ''
                                    THEN z.email
                                  ELSE m.username
                                END,

      m.payment_amount        = CASE
                                  WHEN z.payment_amount IS NOT NULL THEN z.payment_amount
                                  ELSE m.payment_amount
                                END,
      m.payment_mode          = CASE
                                  WHEN z.payment_mode IS NOT NULL THEN z.payment_mode
                                  ELSE m.payment_mode
                                END,
      m.registered_date       = COALESCE(z.registered_date, m.registered_date),
      m.renewal_date          = COALESCE(z.renewal_date, m.renewal_date),
      m.membership_expiration = CASE
                                  WHEN z.membership_expiration IS NOT NULL THEN z.membership_expiration
                                  ELSE m.membership_expiration
                                END,
      m.birthday              = COALESCE(z.birthday, m.birthday),
      m.membership_level_id   = COALESCE(z.membership_level_id, m.membership_level_id),

      m.status = CASE
        WHEN m.status = 'EXPIRED'
             AND z.membership_expiration IS NOT NULL
             AND z.membership_expiration >= CURDATE()
          THEN 'ACTIVE'
        ELSE m.status
      END,
      {$set_new_sql}
      m.updated_at = NOW()
  ");

  if ($ok_update === false) {
    error_log('[COAI IMPORT] UPDATE failed: ' . $wpdb->last_error);
    $wpdb->query('ROLLBACK');
    return ['updates' => 0, 'inserts' => 0, 'error' => 'update_failed'];
  }

  $updates = (int) $wpdb->rows_affected;

  // INSERT new
  $is_new_val = $set_new_on_insert ? 1 : 0;
  $batch_ts_sql = $wpdb->prepare('%s', $batch_ts);

  $ok_insert = $wpdb->query("
    INSERT INTO `{$m}` (
      full_name,username,email,first_name,last_name,
      phone,mobile,
      address,address2,city,state,zip,country,
      shipping_address,shipping_city,shipping_state,shipping_zip,shipping_country,
      clown_name,parent_name,e_contact,alley_membership,COAI_number,
      payment_amount,payment_mode,
      registered_date,renewal_date,membership_expiration,birthday,
      membership_level_id,usergroup,status,is_new_member,updated_at
    )
    SELECT
      z.full_name,
      COALESCE(NULLIF(z.email, ''), CONCAT('user_', UUID())),
      z.email,z.first_name,z.last_name,
      z.phone,z.mobile,
      z.address,z.address2,z.city,z.state,z.zip,z.country,
      z.shipping_address,z.shipping_city,z.shipping_state,z.shipping_zip,z.shipping_country,
      z.clown_name,z.parent_name,z.e_contact,z.alley_membership,z.COAI_number,
      z.payment_amount,z.payment_mode,
      z.registered_date,z.renewal_date,z.membership_expiration,z.birthday,
      z.membership_level_id,
      'Member',
      z.status,
      {$is_new_val},
      {$batch_ts_sql}
    FROM `{$rt}` z
    LEFT JOIN `{$m}` m ON {$join}
    WHERE m.member_id IS NULL
  ");

  if ($ok_insert === false) {
    error_log('[COAI IMPORT] INSERT failed: ' . $wpdb->last_error);
    $wpdb->query('ROLLBACK');
    return ['updates' => $updates, 'inserts' => 0, 'error' => 'insert_failed'];
  }

  $inserts = (int) $wpdb->rows_affected;

  $wpdb->query('COMMIT');
  
  coaii_insert_family_members($batch_ts);

  return ['updates' => $updates, 'inserts' => $inserts];
}

function coaii_get_preview_rows() {
  global $wpdb;
  $m  = coaii_get_members_table();
  $rt = 'import_members_ready_zeffy';
  $join = coaii_join_sql();

  $sql = "
    SELECT
      z.source_id,
      z.email,
      z.full_name,
      z.city,
      z.state,

      z.COAI_number AS incoming_coai_number,
      m.COAI_number AS current_coai_number,
      m.member_id   AS existing_member_id,

      z.payment_amount,
      z.payment_mode,
      z.membership_expiration,
      z.status AS new_status,
      m.status AS old_status,

      pm.member_id AS possible_member_id,
      pm.email     AS possible_member_email,
      pm.username  AS possible_member_username,
      pm.COAI_number AS possible_member_coai,
      pm.status    AS possible_member_status,

      CASE
        WHEN z.COAI_number IS NOT NULL AND TRIM(z.COAI_number) <> '' THEN 'COAI'
        WHEN z.email IS NOT NULL AND TRIM(z.email) <> '' AND LOWER(TRIM(z.email)) = LOWER(TRIM(m.email)) THEN 'EMAIL'
        WHEN z.email IS NOT NULL AND TRIM(z.email) <> '' AND LOWER(TRIM(z.email)) = LOWER(TRIM(m.username)) THEN 'USERNAME'
        WHEN m.member_id IS NULL AND pm.member_id IS NOT NULL THEN 'POSSIBLE_NAME'
        ELSE 'NONE'
      END AS match_method,

      CASE
        WHEN m.member_id IS NOT NULL THEN 'UPDATE'
        WHEN pm.member_id IS NOT NULL THEN 'REVIEW'
        ELSE 'INSERT'
      END AS action,

      CASE
        WHEN m.member_id IS NOT NULL
          AND m.COAI_number IS NOT NULL
          AND TRIM(m.COAI_number) <> ''
        THEN m.COAI_number

        WHEN m.member_id IS NOT NULL
          AND z.COAI_number IS NOT NULL
          AND TRIM(z.COAI_number) <> ''
        THEN z.COAI_number

        WHEN m.member_id IS NULL
          AND pm.member_id IS NOT NULL
          AND pm.COAI_number IS NOT NULL
          AND TRIM(pm.COAI_number) <> ''
        THEN pm.COAI_number

        WHEN m.member_id IS NULL
          AND z.COAI_number IS NOT NULL
          AND TRIM(z.COAI_number) <> ''
        THEN z.COAI_number

        WHEN m.member_id IS NULL
          AND pm.member_id IS NOT NULL
        THEN 'REVIEW REQUIRED'

        WHEN m.member_id IS NULL
        THEN 'AUTO-GENERATE'

        ELSE ''
      END AS result_coai_number

    FROM `{$rt}` z

    LEFT JOIN `{$m}` m
      ON {$join}

    LEFT JOIN `{$m}` pm
      ON (
        m.member_id IS NULL
        AND pm.deleted_at IS NULL
        AND (pm.status IS NULL OR UPPER(TRIM(pm.status)) <> 'ARCHIVED')
        AND LOWER(TRIM(z.full_name)) = LOWER(TRIM(pm.full_name))
        AND (
          (
            COALESCE(LOWER(TRIM(z.city)), '') <> ''
            AND COALESCE(LOWER(TRIM(z.state)), '') <> ''
            AND COALESCE(LOWER(TRIM(z.city)), '')  = COALESCE(LOWER(TRIM(pm.city)), '')
            AND COALESCE(LOWER(TRIM(z.state)), '') = COALESCE(LOWER(TRIM(pm.state)), '')
          )
          OR
          (
            COALESCE(LOWER(TRIM(z.city)), '') = ''
            AND COALESCE(LOWER(TRIM(z.state)), '') = ''
          )
        )
      )

    ORDER BY
      CASE
        WHEN m.member_id IS NOT NULL THEN 1
        WHEN pm.member_id IS NOT NULL THEN 2
        ELSE 3
      END,
      z.full_name,
      z.email
  ";

  return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * ------------------------------------------------------------
 * Audit + emails
 * ------------------------------------------------------------
 */
function coaii_log_import_run($file_name, $is_dry_run, $stats){
  global $wpdb;
  $rows_loaded = (int) $wpdb->get_var("SELECT COUNT(*) FROM import_members_staging_zeffy");
  $user_id = get_current_user_id();

  $wpdb->insert(
    'import_members_runs',
    [
      'run_at'       => current_time('mysql'),
      'run_by'       => $user_id ?: null,
      'file_name'    => $file_name,
      'is_dry_run'   => $is_dry_run ? 1 : 0,
      'rows_loaded'  => $rows_loaded,
      'rows_updated' => isset($stats['updates']) ? (int)$stats['updates'] : 0,
      'rows_inserted'=> isset($stats['inserts']) ? (int)$stats['inserts'] : 0,
    ],
    ['%s','%d','%s','%d','%d','%d','%d']
  );
}

function coaii_send_admin_summary($file_name, $stats){
  $to = [
    'coaioffice@mycoai.com',
    'srateach@gmail.com',
  ];

  $subject = '[COAI] Zeffy Import Completed';
  $body  = "A Zeffy import has just completed.\n\n";
  $body .= "File: " . $file_name . "\n";
  $body .= "Date: " . current_time('mysql') . "\n\n";
  $body .= "Updated rows: " . (int)$stats['updates'] . "\n";
  $body .= "Inserted rows: " . (int)$stats['inserts'] . "\n\n";
  $body .= "You can review details in wp-admin > Zeffy Import or via the import_members_runs table.\n";

  $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
  foreach ($to as $addr) {
    wp_mail($addr, $subject, $body, $headers);
  }
}

function coaii_send_member_notifications($file_name){
  global $wpdb;
  $m  = coaii_get_members_table();
  $rt = 'import_members_ready_zeffy';
  $join = coaii_join_sql();

  $sql = "
    SELECT
      z.email,
      z.full_name,
      z.COAI_number,
      z.payment_amount,
      z.membership_expiration,
      m.member_id,
      m.status AS old_status,
      CASE WHEN m.member_id IS NULL THEN 'INSERT' ELSE 'UPDATE' END AS action
    FROM `{$rt}` z
    LEFT JOIN `{$m}` m ON {$join}
  ";

  $rows = $wpdb->get_results($sql, ARRAY_A);
  if (empty($rows)) return;

  $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

  foreach ($rows as $row) {
    if (empty($row['email'])) continue;

    $email  = $row['email'];
    $name   = $row['full_name'];
    $coai   = $row['COAI_number'];
    $amount = $row['payment_amount'];
    $exp    = $row['membership_expiration'];
    $action = $row['action'];

    if ($action === 'INSERT') {
      $subject = 'Welcome to Clowns of America International';
      $body  = "Dear {$name},\n\n";
      $body .= "Thank you for becoming a member of Clowns of America International.\n\n";
      if (!empty($coai))   $body .= "Your COAI Number: {$coai}\n";
      if (!empty($amount)) $body .= "Payment Amount: {$amount}\n";
      if (!empty($exp))    $body .= "Membership Expiration: {$exp}\n";
      
      $body .= "\nIMPORTANT NOTICE:\n";
      $body .= "If you are purchasing insurance with your membership, please email the COAI Office as soon as possible so we can manually add your insurance coverage to your account.\n\n";
      $body .= "Please allow 24–48 hours for your membership and/or insurance information and any other changes to appear on the COAI membership website after your membership or insurance has been processed.\n";
      
      $body .= "\nWe’re glad to have you as part of the COAI family!\n\n— COAI Office\n";
    } else {
      $subject = 'Your COAI membership renewal has been processed';
      $body  = "Dear {$name},\n\n";
      $body .= "We have recorded your COAI membership renewal.\n\n";
      if (!empty($coai))   $body .= "COAI Number: {$coai}\n";
      if (!empty($amount)) $body .= "Payment Amount: {$amount}\n";
      if (!empty($exp))    $body .= "New Membership Expiration: {$exp}\n";
      
      $body .= "\nIMPORTANT NOTICE:\n";
      $body .= "If you are purchasing insurance with your membership, please email the COAI Office as soon as possible so we can manually add your insurance coverage to your account.\n\n";
      $body .= "Please allow 24–48 hours for your membership and/or insurance information and any other changes to appear on the COAI membership website after your membership renewal has been processed.\n";
      
      $body .= "\nThank you for continuing to be a member of COAI!\n\n— COAI Office\n";
    }

    wp_mail($email, $subject, $body, $headers);
  }
}

/**
 * ------------------------------------------------------------
 * Activation: table + index
 * ------------------------------------------------------------
 */
register_activation_hook(__FILE__, function(){
  global $wpdb;
  $m = coaii_get_members_table();

  // Add email index if it doesn't exist (portable across MySQL versions)
  $idx = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(1)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = %s
      AND INDEX_NAME = 'idx_email'
  ", $m));

  if ((int)$idx === 0) {
    $wpdb->query("ALTER TABLE `{$m}` ADD INDEX idx_email (email)");
  }

  $wpdb->query("
    CREATE TABLE IF NOT EXISTS import_members_runs (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      run_at DATETIME NOT NULL,
      run_by BIGINT UNSIGNED NULL,
      file_name VARCHAR(255) NULL,
      is_dry_run TINYINT(1) NOT NULL DEFAULT 0,
      rows_loaded INT UNSIGNED NOT NULL DEFAULT 0,
      rows_updated INT UNSIGNED NOT NULL DEFAULT 0,
      rows_inserted INT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
});