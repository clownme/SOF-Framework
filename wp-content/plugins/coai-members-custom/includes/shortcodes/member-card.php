<?php
if (!defined('ABSPATH')) exit;

/**
 * COAI Member Card + QR Verify
 *
 * Shortcodes:
 *   [coai_member_card]
 *   [coai_member_card_verify]
 *
 * Phase 1:
 * - Logged-in member can view a printable/mobile-friendly card
 * - QR code points to public verify page using COAI number
 * - Verify page shows limited public-safe membership info
 *
 * Assumptions / fallbacks:
 * - wp_members table exists
 * - primary fields are:
 *     coai_number
 *     first_name
 *     last_name
 *     clown_name
 *     expiration_date
 *     status
 *     email
 * - If your site uses a custom bridge/meta field, this file tries a few fallbacks
 */

add_shortcode('coai_member_card', 'coai_member_card_shortcode');
add_shortcode('coai_member_card_verify', 'coai_member_card_verify_shortcode');
add_shortcode('coai_member_card_print', 'coai_member_card_print_shortcode');

/* === COAI EMAIL CARD AJAX === */
add_action('wp_ajax_coai_email_card_image', 'coai_email_card_image_ajax');
add_action('wp_ajax_nopriv_coai_email_card_image', 'coai_email_card_image_ajax');

/**
 * Basic styles shared by card + verify page.
 */
function coai_member_card_styles() {
    ob_start(); ?>
    <style>
      .coai-card-wrap{
        max-width:900px;
        margin:1.25rem auto;
        padding:0 1rem;
      }

      .coai-card-actions{
        display:flex;
        gap:.75rem;
        flex-wrap:wrap;
        margin:0 0 1rem 0;
      }

      .coai-card-btn{
        display:inline-block;
        text-decoration:none;
        border:1px solid #d1d5db;
        background:#fff;
        color:#111827;
        padding:.7rem 1rem;
        border-radius:10px;
        font-weight:600;
        line-height:1.2;
      }
      .coai-card-btn:hover{
        background:#f9fafb;
      }

      .coai-member-card-shell{
        display:flex;
        justify-content:center;
      }

      .coai-member-card{
        position:relative;
        width:100%;
        max-width:560px;
        background:linear-gradient(135deg,#ffffff 0%,#f8fbff 100%);
        border:2px solid #c9a961;
        border-radius:18px;
        box-shadow:0 10px 30px rgba(0,0,0,.08),
        0 0 0 2px rgba(212,175,55,0.35),    /* gold outline */
        0 0 16px rgba(212,175,55,0.18);     /* soft glow */
        overflow:hidden;
      }
      
      /* Watermark */
      .coai-member-card::before{
        content:"";
        position:absolute;
        inset:0;

        background-image:url('https://mycoai.com/wp-content/uploads/2026/03/COAILogo-transparent-scaled.png');
        background-repeat:no-repeat;
        background-position:center 68%;
        background-size:520px;

        opacity:0.04; /* start subtle */
        pointer-events:none;
        z-index:0;
      }

      /* Keep content above watermark */
      .coai-member-card > *{
        position:relative;
        z-index:1;
      }

      .coai-member-card-header{
        position:relative;
        padding:1.6rem 1.25rem 1.4rem;
        color:#fff;
        overflow:hidden;

        background-image:url('https://mycoai.com/wp-content/uploads/2026/03/CircusTopImage.avif');
        background-size:cover;
        background-position:center;
      }
      
      .coai-member-card-header::after{
         content:"";
        position:absolute;
        left:0;
        right:0;
        bottom:0;
        height:35px;

        background:linear-gradient(
          to bottom,
          rgba(255,255,255,0),
          rgba(255,255,255,0.95)
        );

        z-index:1;
      }

      /* RED OVERLAY (for readability) */
      .coai-member-card-header::before{
        content:"";
        position:absolute;
        inset:0;
        background:rgba(255,255,255,0.15); /* very light, almost invisible */
      }

      /* Keep text above overlay */
      .coai-member-card-header *{
        position:relative;
        z-index:2;
      }

      /* Safety: no accidental images in header */
      .coai-member-card-header img{
        display:none;
      }
      
      .coai-card-logo-large{
        display:block;
        max-width:120px;
        height:auto;
        margin:0 auto .4rem;
      }
      
      .coai-qr-img{
          display:block;
          width:100%;
          max-width:160px;
          height:auto;
          margin:0 auto .4rem;
          
      }
      
      .coai-member-card-org{
          margin:0;
          font-size:1.35rem;
          font-weight:900;
          line-height:1.2;
          letter-spacing:.5px;
          color:#ffffff;

          text-shadow:
            0 2px 4px rgba(0,0,0,0.7),
            0 0 2px rgba(0,0,0,0.9);

          display:block;
        }

      .coai-member-card-sub{
          margin:.35rem 0 0 0;
          font-size:1rem;
          font-weight:700;
          color:#ffffff;
          
          display:block;
        }
        
      .coai-member-card-body{
        padding:1rem 1.15rem 1.15rem;
      }

      .coai-member-grid{
        display:grid;
        grid-template-columns:1.4fr .9fr;
        gap:1rem;
        align-items:start;
      }

      .coai-member-fields{
        display:grid;
        gap:.7rem;
      }

      .coai-field{
        border:1px solid #e5e7eb;
        border-radius:12px;
        padding:.7rem .8rem;
        background:#fff;
      }

      .coai-field-label{
        display:block;
        margin:0 0 .2rem;
        font-size:.78rem;
        font-weight:700;
        text-transform:uppercase;
        letter-spacing:.04em;
        color:#6b7280;
      }

      .coai-field-value{
        display:block;
        margin:0;
        font-size:1rem;
        font-weight:700;
        color:#111827;
        word-break:break-word;
      }

      .coai-field-value.coai-number{
        font-size:1.05rem;
        color:#b91c1c;
      }
      
      .coai-member-tagline{
          display:block;
          margin-top:.2rem;
          font-size:.78rem;
          font-weight:800 !important;
          text-transform:uppercase;
          color:#6b7280;
          letter-spacing:.08em;
          line-height:1.2;
        }

      .coai-qr-box{
        border:1px solid #e5e7eb;
        border-radius:12px;
        background:#fff;
        padding:.8rem;
        text-align:center;
      }

      .coai-qr-box{
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
      }

      .coai-qr-note{
        margin:0;
        font-size:.78rem;
        color:#6b7280;
        line-height:1.35;
      }

      .coai-card-foot{
        margin-top:.9rem;
        padding-top:.85rem;
        border-top:1px dashed #d1d5db;
        font-size:.86rem;
        color:#4b5563;
      }

      .coai-verify-wrap{
        max-width:760px;
        margin:1.25rem auto;
        padding:0 1rem;
      }

      .coai-verify-card{
        border:1px solid #e5e7eb;
        border-radius:16px;
        background:#fff;
        box-shadow:0 8px 24px rgba(0,0,0,.05);
        overflow:hidden;
      }

      .coai-verify-head{
        background:linear-gradient(135deg,#0f4c81 0%,#1b75bb 100%);
        color:#fff;
        padding:1rem 1.15rem;
      }

      .coai-verify-head h2{
        margin:0;
        font-size:1.2rem;
      }

      .coai-verify-body{
        padding:1rem 1.15rem 1.15rem;
      }

      .coai-verify-status{
        display:inline-block;
        padding:.4rem .7rem;
        border-radius:999px;
        font-size:.85rem;
        font-weight:800;
        margin:0 0 .9rem;
      }

      .coai-status-active{
        background:#dcfce7;
        color:#166534;
      }

      .coai-status-expired{
        background:#fee2e2;
        color:#991b1b;
      }

      .coai-status-unknown{
        background:#e5e7eb;
        color:#374151;
      }

      .coai-verify-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:.8rem;
      }

      .coai-note-muted{
        color:#6b7280;
        font-size:.92rem;
      }

      .coai-notice{
        max-width:760px;
        margin:1rem auto;
        padding:.9rem 1rem;
        border-radius:12px;
        border:1px solid #e5e7eb;
        background:#fff;
      }

      .coai-notice-error{
        border-color:#fecaca;
        background:#fef2f2;
        color:#991b1b;
      }

      .coai-notice-ok{
        border-color:#bfdbfe;
        background:#eff6ff;
        color:#1e40af;
      }

      @media (max-width: 640px){
        .coai-member-grid,
        .coai-verify-grid{
          grid-template-columns:1fr;
        }

        .coai-member-card{
          max-width:100%;
        }
      }

      @media print {
        body * {
          visibility:hidden !important;
        }

        .coai-member-card-print,
        .coai-member-card-print * {
          visibility:visible !important;
        }

        .coai-member-card-print {
          position:absolute;
          left:0;
          top:0;
          width:3.5in;
          height:2in;
          margin:0;
          padding:0;
        }

        .coai-member-card{
          width:3.5in !important;
          height:2in !important;
          max-width:none !important;
          border-radius:.12in !important;
          box-shadow:none !important;
          overflow:hidden !important;
        }

        .coai-member-card-header{
          padding:.18in .2in .11in !important;
        }

        .coai-member-card-org{
          font-size:12pt !important;
          line-height:1.05 !important;
        }

        .coai-member-card-sub{
          font-size:7.5pt !important;
          margin:.03in 0 0 0 !important;
        }

        .coai-member-card-body{
          padding:.12in .2in .16in !important;
        }

        .coai-member-grid{
          grid-template-columns:1.55fr .8fr !important;
          gap:.1in !important;
        }

        .coai-member-fields{
          gap:.06in !important;
        }

        .coai-field{
          padding:.07in .08in !important;
          border-radius:.08in !important;
        }

        .coai-field-label{
          font-size:6.4pt !important;
          margin:0 0 .02in 0 !important;
        }

        .coai-field-value{
          font-size:8.6pt !important;
          line-height:1.05 !important;
        }
        
        .coai-field-value.coai-number{
          font-size:9pt !important;
        }
        
        .coai-qr-box{
          padding:.07in !important;
          border-radius:.08in !important;
        }

        .coai-qr-box img{
          max-width:.95in !important;
          margin:0 auto .03in !important;
        }

        .coai-qr-note,
        .coai-card-actions,
        .coai-card-foot{
          display:none !important;
        }
      }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Get wp_members table name.
 */
function coai_member_card_table_name() {
    return 'wp_members';
}

/**
 * Safe field fetch if column exists in row.
 */
function coai_member_card_val($row, $key, $default = '') {
    return isset($row[$key]) ? $row[$key] : $default;
}

/**
 * Try to parse expiration date.
 */
function coai_member_card_parse_date($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return false;

    $ts = strtotime($raw);
    if ($ts !== false) {
        return $ts;
    }

    $formats = array('Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y');
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->getTimestamp();
        }
    }

    return false;
}

/**
 * Format expiration date for display.
 */
function coai_member_card_format_expiration($raw) {
    $ts = coai_member_card_parse_date($raw);
    if (!$ts) return 'Not Available';
    return date_i18n('m/d/Y', $ts);
}

/**
 * Decide active/expired based on status + expiration.
 */
function coai_member_card_membership_state($row) {
    $status = strtoupper(trim((string)coai_member_card_val($row, 'status', '')));
    $exp_raw = coai_member_card_val($row, 'expiration_date', '');
    $exp_ts  = coai_member_card_parse_date($exp_raw);

    $today = current_time('timestamp');
    $today_midnight = strtotime(date('Y-m-d 00:00:00', $today));

    if ($status === 'ARCHIVED') {
        return array(
            'label' => 'Archived',
            'class' => 'coai-status-expired',
        );
    }

    if ($status === 'EXPIRED') {
        return array(
            'label' => 'Expired',
            'class' => 'coai-status-expired',
        );
    }

    if ($exp_ts && $exp_ts < $today_midnight) {
        return array(
            'label' => 'Expired',
            'class' => 'coai-status-expired',
        );
    }

    if ($status === 'ACTIVE' || $status === '') {
        return array(
            'label' => 'Active',
            'class' => 'coai-status-active',
        );
    }

    return array(
        'label' => $status !== '' ? ucwords(strtolower($status)) : 'Unknown',
        'class' => 'coai-status-unknown',
    );
}

/**
 * Try to locate current member row from logged-in WP user.
 * This is intentionally defensive because your auth bridge setup is custom.
 */
function coai_member_card_get_current_member_row() {
    global $wpdb;

    if (!is_user_logged_in()) {
        error_log('[COAI CARD] no logged-in WP user');
        return null;
    }

    $user  = wp_get_current_user();
    $table = coai_member_card_table_name();

    if (!$user || empty($user->ID)) {
        error_log('[COAI CARD] wp_get_current_user returned empty');
        return null;
    }

    error_log('[COAI CARD] lookup start wp_user_id=' . (int)$user->ID . ' login=' . $user->user_login . ' email=' . $user->user_email);

    // 1) Best path: COAI bridge helper returns member ID
    if (function_exists('coai_current_member_id')) {
        $mid = (int) coai_current_member_id();
        error_log('[COAI CARD] coai_current_member_id()=' . $mid);

        if ($mid > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `$table` WHERE member_id = %d LIMIT 1", $mid),
                ARRAY_A
            );

            if (!empty($row)) {
                error_log('[COAI CARD] matched by coai_current_member_id mid=' . $mid . ' coai_number=' . ($row['coai_number'] ?? ''));
                return $row;
            }

            error_log('[COAI CARD] no wp_members row found for mid=' . $mid);
        }
    } else {
        error_log('[COAI CARD] function coai_current_member_id() does not exist');
    }

    // 2) Known usermeta mappings
    $meta_keys = array(
        'coai_member_id',
        'member_id',
        'wp_member_id',
    );

    foreach ($meta_keys as $meta_key) {
        $mid = (int) get_user_meta($user->ID, $meta_key, true);
        error_log('[COAI CARD] usermeta ' . $meta_key . '=' . $mid);

        if ($mid > 0) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `$table` WHERE member_id = %d LIMIT 1", $mid),
                ARRAY_A
            );

            if (!empty($row)) {
                error_log('[COAI CARD] matched by usermeta ' . $meta_key . ' mid=' . $mid . ' coai_number=' . ($row['coai_number'] ?? ''));
                return $row;
            }
        }
    }

    // 3) Fallback by exact email
    $email = trim((string)$user->user_email);
    if ($email !== '') {
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `$table` WHERE email = %s LIMIT 1", $email),
            ARRAY_A
        );

        if (!empty($row)) {
            error_log('[COAI CARD] matched by email=' . $email . ' member_id=' . ($row['id'] ?? 0));
            return $row;
        }

        error_log('[COAI CARD] no email match for ' . $email);
    }

    error_log('[COAI CARD] no member record match found for wp_user_id=' . (int)$user->ID);
    return null;
}

/**
 * Find row by COAI number for public verification.
 */
function coai_member_card_get_row_by_coai($coai_number) {
    global $wpdb;

    $coai_number = trim((string)$coai_number);
    if ($coai_number === '') return null;

    $table = coai_member_card_table_name();
    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `$table` WHERE COAI_number = %s LIMIT 1", $coai_number),
        ARRAY_A
    );

    return !empty($row) ? $row : null;
}

/**
 * Build verify URL used inside the QR.
 */
function coai_member_card_verify_url($coai_number) {
    $base = home_url('/member-card-verify/');
    return add_query_arg(array(
        'coai' => rawurlencode((string)$coai_number),
    ), $base);
}

/**
 * Shared front-side fields markup.
 */
function coai_member_card_front_fields_html($full_name, $clown_name, $exp_display, $coai_number) {
    ob_start(); ?>
    <div class="coai-print-front-fields">
      <div class="coai-print-row">
        <span class="coai-print-label">COAI #</span>
        <span class="coai-print-value coai-print-value-coai"><?php echo esc_html($coai_number !== '' ? $coai_number : 'Not Assigned'); ?></span>
      </div>

      <div class="coai-print-row coai-print-row-name">
        <span class="coai-print-label">Full Name</span>
        <span class="coai-print-value"><?php echo esc_html($full_name !== '' ? $full_name : 'Not Available'); ?></span>
      </div>

      <div class="coai-print-row coai-print-row-clown">
        <span class="coai-print-label">Clown Name</span>
        <span class="coai-print-value"><?php echo esc_html($clown_name !== '' ? $clown_name : '—'); ?></span>
      </div>

      <div class="coai-print-row">
        <span class="coai-print-label">Expiration</span>
        <span class="coai-print-value"><?php echo esc_html($exp_display); ?></span>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shared print/download styles.
 */
function coai_member_card_print_styles() {
    ob_start(); ?>
    <style>
      .coai-print-wrap{
        max-width:900px;
        margin:1.25rem auto;
        padding:0 1rem;
      }

      .coai-print-actions{
        display:flex;
        gap:.75rem;
        flex-wrap:wrap;
        margin:0 0 1rem 0;
      }

      .coai-print-btn{
        display:inline-block;
        text-decoration:none;
        border:1px solid #d1d5db;
        background:#fff;
        color:#111827;
        padding:.7rem 1rem;
        border-radius:10px;
        font-weight:600;
        line-height:1.2;
      }
      .coai-print-btn:hover{ background:#f9fafb; }

      .coai-print-stack{
        display:grid;
        gap:1.25rem;
        justify-content:center;
      }

      .coai-print-card{
        position:relative;
        width:3.5in;
        height:2in;
        background:linear-gradient(135deg,#ffffff 0%,#f8fbff 100%);
        border:2px solid #c9a961;
        border-radius:.18in;
        box-shadow:
          0 10px 24px rgba(0,0,0,.08),
          0 0 0 2px rgba(201,169,97,0.22),
          0 0 14px rgba(201,169,97,0.14);
        overflow:hidden;
      }

      .coai-print-card::before{
        content:"";
        position:absolute;
        inset:0;
        background-image:url('https://mycoai.com/wp-content/uploads/2026/03/coai-logo.png');
        background-repeat:no-repeat;
        background-position:center 68%;
        background-size:440px;
        opacity:.04;
        pointer-events:none;
        z-index:0;
      }

      .coai-print-card > *{
        position:relative;
        z-index:1;
      }

      .coai-print-front-header{
        position:relative;
        padding:.03in .07in .025in;
        color:#fff;
        overflow:hidden;

        background-image:url('https://mycoai.com/wp-content/uploads/2026/03/CircusTopImagesmaller.jpg');
        background-size:cover;
        background-position:center;
      }

      .coai-print-front-header::before{
        content:"";
        position:absolute;
        inset:0;
        background:rgba(0,0,0,0.18);
        z-index:1;
      }

      .coai-print-front-header *{
        position:relative;
        z-index:2;
      }

      .coai-print-org{
        margin:0;
        font-size:7pt;
        font-weight:900;
        line-height:1.0;
        color:#fff;
        text-shadow:0 1px 2px rgba(0,0,0,0.35);
      }

      .coai-print-sub{
        display:none;
      }

      .coai-print-front-body{
        padding:.02in .07in .025in;
      }

      .coai-print-front-fields{
        display:grid;
        gap:.01in;
      }

      .coai-print-row{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:.03in;
        border-bottom:1px solid rgba(96,112,137,.10);
        padding:.006in 0;
      }

      .coai-print-row:last-child{
        border-bottom:none;
      }

      .coai-print-label{
        font-size:5.2pt;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.06em;
        color:#374151;
        flex:0 0 .74in;
        
        text-shadow:0 0 .2px rgba(0,0,0,0.4);
      }

      .coai-print-value{
        font-size:7.4pt;
        font-weight:800;
        line-height:1.0;
        color:#111827;
        text-align:right;
        flex:1 1 auto;
        white-space:nowrap;
      }

      .coai-print-value-coai{
        color:#c81e1e;
      }

      .coai-print-row.coai-print-row-name .coai-print-value,
        white-space:normal;
        line-height:1.0;
      .coai-print-row.coai-print-row-clown .coai-print-value{
        font-size:6.8pt;
      }

      .coai-print-back{
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:flex-start;
        text-align:center;
        padding:.07in .08in .06in;
      }

      .coai-print-back-logo{
        width:.58in;
        height:auto;
        margin:0 0 .02in 0;
      }

      .coai-print-back-qr{
        width:1.18in;
        height:auto;
        margin:0 0 .02in 0;
      }

      .coai-print-back-note{
        font-size:5.8pt;
        color:#51627c;
        line-height:1.05;
      }

      .coai-download-note{
        margin:.5rem 0 0 0;
        color:#6b7280;
        font-size:.92rem;
      }
      
      @media screen {
        .coai-print-card{
          transform:scale(1.0);
          transform-origin:top center;
          margin-bottom:.22in;
        }

      .coai-print-stack{
          padding-top:.08in;
      }
    }

      @media print {
        @page {
          size: letter portrait;
          margin: .4in;
        }

        body * {
          visibility:hidden !important;
        }

        .coai-print-only,
        .coai-print-only * {
          visibility:visible !important;
        }

        .coai-print-only{
          position:absolute;
          left:0;
          top:0;
          width:100%;
          margin:0;
          padding:0;
        }

        .coai-print-actions{
          display:none !important;
        }

        .coai-print-stack{
          gap:.2in !important;
        }

        .coai-print-card{
          box-shadow:none !important;
          break-inside:avoid;
          page-break-inside:avoid;
        }
      }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * QR image URL.
 *
 * Phase 1 uses api.qrserver.com for simplicity.
 * Later you can swap this to a local QR library if you want fully self-hosted.
 */
function coai_member_card_qr_image_url($verify_url) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=8&data=' . rawurlencode($verify_url);
}

/**
 * AJAX: receive card image PNG(s) and email them to the logged-in member.
 */
function coai_email_card_image_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in.'));
    }

    check_ajax_referer('coai_email_card_image_nonce', 'nonce');

    $row = coai_member_card_get_current_member_row();
    if (empty($row) || !is_array($row)) {
        wp_send_json_error(array('message' => 'Could not find your member record.'));
    }

    $user = wp_get_current_user();

    $to_email = trim((string)($row['email'] ?? ''));
    if ($to_email === '') {
        $to_email = trim((string)($user->user_email ?? ''));
    }

    if ($to_email === '' || !is_email($to_email)) {
        wp_send_json_error(array('message' => 'No valid email address found.'));
    }

    $full_image  = isset($_POST['full_image'])  ? (string) wp_unslash($_POST['full_image'])  : '';
    $front_image = isset($_POST['front_image']) ? (string) wp_unslash($_POST['front_image']) : '';
    $back_image  = isset($_POST['back_image'])  ? (string) wp_unslash($_POST['back_image'])  : '';
    $image_data  = isset($_POST['image_data'])  ? (string) wp_unslash($_POST['image_data'])  : '';

    if ($full_image === '' && $image_data !== '') {
        $full_image = $image_data;
    }

    if ($full_image === '' || strpos($full_image, 'data:image/png;base64,') !== 0) {
        wp_send_json_error(array('message' => 'Full card image data was missing or invalid.'));
    }

    if ($front_image !== '' && strpos($front_image, 'data:image/png;base64,') !== 0) {
        wp_send_json_error(array('message' => 'Front card image data was invalid.'));
    }

    if ($back_image !== '' && strpos($back_image, 'data:image/png;base64,') !== 0) {
        wp_send_json_error(array('message' => 'Back card image data was invalid.'));
    }

    $full_binary = base64_decode(substr($full_image, strlen('data:image/png;base64,')));
    if ($full_binary === false) {
        wp_send_json_error(array('message' => 'Could not decode full card image.'));
    }

    $front_binary = null;
    if ($front_image !== '') {
        $front_binary = base64_decode(substr($front_image, strlen('data:image/png;base64,')));
        if ($front_binary === false) {
            wp_send_json_error(array('message' => 'Could not decode front card image.'));
        }
    }

    $back_binary = null;
    if ($back_image !== '') {
        $back_binary = base64_decode(substr($back_image, strlen('data:image/png;base64,')));
        if ($back_binary === false) {
            wp_send_json_error(array('message' => 'Could not decode back card image.'));
        }
    }

    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        wp_send_json_error(array('message' => 'Upload folder is not available.'));
    }

    $full_name = trim(
        (string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')
    );

    $coai_number = trim((string)(
        $row['COAI_number']
        ?? $row['coai_number']
        ?? $row['coai']
        ?? ''
    ));

    $safe_name = sanitize_file_name($coai_number !== '' ? $coai_number : ('member-' . get_current_user_id()));

    $attachments = array();

    $full_filepath = trailingslashit($upload_dir['path']) . 'coai-membership-card-full-' . $safe_name . '.png';
    if (file_put_contents($full_filepath, $full_binary) === false) {
        wp_send_json_error(array('message' => 'Could not create the full card image file.'));
    }
    $attachments[] = $full_filepath;

    $front_filepath = '';
    if ($front_binary !== null) {
        $front_filepath = trailingslashit($upload_dir['path']) . 'coai-membership-card-front-' . $safe_name . '.png';
        if (file_put_contents($front_filepath, $front_binary) === false) {
            if (file_exists($full_filepath)) {
                @unlink($full_filepath);
            }
            wp_send_json_error(array('message' => 'Could not create the front card image file.'));
        }
        $attachments[] = $front_filepath;
    }

    $back_filepath = '';
    if ($back_binary !== null) {
        $back_filepath = trailingslashit($upload_dir['path']) . 'coai-membership-card-back-' . $safe_name . '.png';
        if (file_put_contents($back_filepath, $back_binary) === false) {
            if (file_exists($full_filepath)) {
                @unlink($full_filepath);
            }
            if ($front_filepath !== '' && file_exists($front_filepath)) {
                @unlink($front_filepath);
            }
            wp_send_json_error(array('message' => 'Could not create the back card image file.'));
        }
        $attachments[] = $back_filepath;
    }

    $subject = 'Your COAI Membership Card';
    $greeting_name = $full_name !== '' ? $full_name : ($user->display_name ?: 'COAI Member');

    $body  = "Hello {$greeting_name},\n\n";
    $body .= "Attached is your full COAI Membership Card image for saving to your phone or tablet.\n\n";

    if ($front_binary !== null && $back_binary !== null) {
        $body .= "Also attached are the front and back print card images.\n\n";
    }

    $body .= "You can save these images to your device or print them for reference.\n\n";
    $body .= "Print-friendly card page:\n" . home_url('/member-card-print/') . "\n\n";
    $body .= "Thank you,\n";
    $body .= "Clowns of America International\n";

    $headers = array('Content-Type: text/plain; charset=UTF-8');

    $sent = wp_mail($to_email, $subject, $body, $headers, $attachments);

    foreach ($attachments as $file) {
        if ($file && file_exists($file)) {
            @unlink($file);
        }
    }

    if (!$sent) {
        error_log('[COAI CARD] email attachment FAILED to ' . $to_email);
        wp_send_json_error(array('message' => 'We could not email your card right now.'));
    }

    error_log('[COAI CARD] email attachment sent to ' . $to_email . ' (attachments=' . count($attachments) . ')');

    wp_send_json_success(array(
        'message' => 'Your full membership card plus front and back images were emailed to ' . $to_email . '.'
    ));
}

/**
 * Member Card shortcode.
 */
function coai_member_card_shortcode($atts = array(), $content = '') {
    $row = coai_member_card_get_current_member_row();

    if (!is_user_logged_in()) {
        return coai_member_card_styles()
            . '<div class="coai-notice coai-notice-error">You must be logged in to view your membership card.</div>';
    }

    if (empty($row)) {
        return coai_member_card_styles()
            . '<div class="coai-notice coai-notice-error">We could not find a matching member record for your login.</div>';
    }

    $coai_number = trim((string)(
    $row['COAI_number']
    ?? $row['coai_number']
    ?? $row['coai']
    ?? ''
));
    $full_name   = trim(coai_member_card_val($row, 'first_name', '') . ' ' . coai_member_card_val($row, 'last_name', ''));
    $clown_name  = trim((string)coai_member_card_val($row, 'clown_name', ''));
    $exp_raw     = coai_member_card_val($row, 'expiration_date', '');
    $exp_display = coai_member_card_format_expiration($exp_raw);

    $verify_url  = $coai_number !== '' ? coai_member_card_verify_url($coai_number) : '';
    $qr_img      = $verify_url !== '' ? coai_member_card_qr_image_url($verify_url) : '';
    $state       = coai_member_card_membership_state($row);
    $logo_url    = 'https://mycoai.com/wp-content/uploads/2026/03/COAILogo-transparent-scaled.png';

    ob_start();
    echo coai_member_card_styles();
    echo coai_member_card_print_styles();
    ?>
    <div class="coai-card-wrap">
      <?php if (!empty($email_notice)) echo $email_notice; ?>
      
      <div class="coai-card-actions">
        <a class="coai-card-btn" href="<?php echo esc_url(home_url('/member-portal/')); ?>">← Return to Member Portal</a>
        <a class="coai-card-btn" href="<?php echo esc_url(home_url('/member-card-print/')); ?>" target="_blank" rel="noopener">🖨️ Print Front / Back Card</a>
        <a class="coai-card-btn" href="#" id="coai-download-card-btn">📥 Download Card Image</a>
        <button type="button" class="coai-card-btn" id="coai-email-card-btn" style="cursor:pointer;">✉️ Email My Membership Card</button>
      </div>

      <div class="coai-member-card-shell coai-member-card-print">
        <div class="coai-member-card">
          <div class="coai-member-card-header">

            <div class="coai-card-header-inner">
    
              <?php if (!empty($logo_url)): ?>
                <img src="<?php echo esc_url($logo_url); ?>" class="coai-card-logo" alt="COAI Logo">
              <?php endif; ?>

              <div class="coai-card-header-text">
                <h2 class="coai-member-card-org">Clowns of America International</h2>
                <p class="coai-member-card-sub">Official Membership Card</p>
              </div>
          </div>
        </div>

          <div class="coai-member-card-body">
            <div class="coai-member-grid">
              <div class="coai-member-fields">
                <div class="coai-field">
                  <span class="coai-field-label">COAI #</span>
                  <span class="coai-field-value coai-number"><?php echo esc_html($coai_number !== '' ? $coai_number : 'Not Assigned'); ?></span>
                </div>

                <div class="coai-field">
                  <span class="coai-field-label">Full Name</span>
                  <span class="coai-field-value"><?php echo esc_html($full_name !== '' ? $full_name : 'Not Available'); ?></span>
                  
                  <span class="coai-member-tagline">Ambassador of Joy</span>
                </div>

                <div class="coai-field">
                  <span class="coai-field-label">Clown Name</span>
                  <span class="coai-field-value"><?php echo esc_html($clown_name !== '' ? $clown_name : '—'); ?></span>
                </div>

                <div class="coai-field">
                  <span class="coai-field-label">Expiration Date</span>
                  <span class="coai-field-value"><?php echo esc_html($exp_display); ?></span>
                </div>
              </div>

              <div>
               <div class="coai-qr-box">

                <?php if (!empty($logo_url)): ?>
                  <img src="<?php echo esc_url($logo_url); ?>" class="coai-card-logo-large" alt="COAI Logo">
                <?php endif; ?>

                <?php if ($qr_img !== ''): ?>
                  <img src="<?php echo esc_url($qr_img); ?>" class="coai-qr-img" alt="Membership verification QR code">
                <?php else: ?>
                  <div style="padding:1rem .5rem;color:#6b7280;font-size:.9rem;">
                    QR unavailable until COAI # is assigned.
                  </div>
                <?php endif; ?>

                <p class="coai-qr-note">
                  Scan to verify membership
                </p>

              </div>
            </div>
        </div>

            <div class="coai-card-foot">
              <strong>Status:</strong>
              <span class="<?php echo esc_attr($state['class']); ?>" style="margin-left:.4rem;"><?php echo esc_html($state['label']); ?></span>
            </div>
          </div>
        </div>
      </div>
      
          <<div style="position:absolute;left:-99999px;top:0;width:900px;opacity:0;pointer-events:none;overflow:hidden;">
        <div class="coai-print-stack">

          <!-- EMAIL FRONT -->
          <div class="coai-print-card" id="coai-email-card-front">
            <div class="coai-print-front-header">
              <h2 class="coai-print-org">Clowns of America International</h2>
              <p class="coai-print-sub">Official Membership Card</p>
            </div>

            <div class="coai-print-front-body">
              <?php echo coai_member_card_front_fields_html($full_name, $clown_name, $exp_display, $coai_number); ?>
            </div>
          </div>

          <!-- EMAIL BACK -->
          <div class="coai-print-card" id="coai-email-card-back">
            <div class="coai-print-back">
              <?php if (!empty($logo_url)): ?>
                <img src="<?php echo esc_url($logo_url); ?>" class="coai-print-back-logo" alt="COAI Logo">
              <?php endif; ?>

              <?php if ($qr_img !== ''): ?>
                <img src="<?php echo esc_url($qr_img); ?>" class="coai-print-back-qr" alt="Membership verification QR code">
              <?php endif; ?>

              <div class="coai-print-back-note">
                Scan to verify membership
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
    <?php
    
?>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var downloadBtn = document.getElementById('coai-download-card-btn');
  var emailBtn = document.getElementById('coai-email-card-btn');

  var visibleFrontTarget = document.querySelector('.coai-member-card');
  var emailFrontTarget   = document.getElementById('coai-email-card-front');
  var emailBackTarget    = document.getElementById('coai-email-card-back');

  if (typeof html2canvas === 'undefined') return;

  function waitForImages(container) {
    if (!container) return Promise.resolve();

    var images = Array.prototype.slice.call(container.querySelectorAll('img'));
    if (!images.length) return Promise.resolve();

    return Promise.all(images.map(function(img) {
      return new Promise(function(resolve) {
        if (img.complete && img.naturalWidth > 0) {
          resolve();
          return;
        }

        var done = function() { resolve(); };
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });

        setTimeout(resolve, 3000);
      });
    }));
  }

  function captureNode(node) {
    if (!node) {
      return Promise.reject(new Error('Capture target not found.'));
    }

    return waitForImages(node).then(function() {
      return html2canvas(node, {
        backgroundColor: '#ffffff',
        scale: 2,
        useCORS: true
      });
    });
  }

  if (downloadBtn && visibleFrontTarget) {
    downloadBtn.addEventListener('click', function (e) {
      e.preventDefault();

      captureNode(visibleFrontTarget).then(function(canvas) {
        var link = document.createElement('a');
        link.download = 'coai-membership-card.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      }).catch(function(err) {
        console.error('COAI card download failed', err);
        alert('Unable to download the card image right now.');
      });
    });
  }

  if (emailBtn) {
    emailBtn.addEventListener('click', function (e) {
      e.preventDefault();

      var originalText = emailBtn.textContent;
      emailBtn.disabled = true;
      emailBtn.textContent = 'Sending...';

      Promise.all([
        captureNode(visibleFrontTarget),
        captureNode(emailFrontTarget),
        captureNode(emailBackTarget)
      ]).then(function(results) {
        var fullCanvas  = results[0];
        var frontCanvas = results[1];
        var backCanvas  = results[2];

        var fullImage  = fullCanvas.toDataURL('image/png');
        var frontImage = frontCanvas.toDataURL('image/png');
        var backImage  = backCanvas.toDataURL('image/png');

        var formData = new FormData();
        formData.append('action', 'coai_email_card_image');
        formData.append('nonce', '<?php echo esc_js(wp_create_nonce('coai_email_card_image_nonce')); ?>');
        formData.append('full_image', fullImage);
        formData.append('front_image', frontImage);
        formData.append('back_image', backImage);
        formData.append('image_data', fullImage); // backward-compatible fallback

        return fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
          method: 'POST',
          credentials: 'same-origin',
          body: formData
        });
      }).then(function(response) {
        return response.json();
      }).then(function(data) {
        if (data && data.success) {
          alert(data.data.message || 'Your membership card was emailed successfully.');
        } else {
          alert((data && data.data && data.data.message) ? data.data.message : 'We could not email your card right now.');
        }
      }).catch(function(err) {
        console.error('COAI email card failed', err);
        alert('Unable to email the card images right now.');
      }).finally(function() {
        emailBtn.disabled = false;
        emailBtn.textContent = originalText;
      });
    });
  }
});
</script>
<?php
    
    return ob_get_clean();
}

/**
 * Verify shortcode.
 *
 * Use on a public page:
 *   /member-card-verify/
 * with shortcode [coai_member_card_verify]
 */
function coai_member_card_verify_shortcode($atts = array(), $content = '') {
    $coai_number = isset($_GET['coai']) ? sanitize_text_field(wp_unslash($_GET['coai'])) : '';

    if ($coai_number === '') {
        return coai_member_card_styles()
            . '<div class="coai-notice coai-notice-error">Verification code missing.</div>';
    }

    $row = coai_member_card_get_row_by_coai($coai_number);

    if (empty($row)) {
        return coai_member_card_styles()
            . '<div class="coai-notice coai-notice-error">No membership record found for that COAI number.</div>';
    }

    $full_name   = trim(coai_member_card_val($row, 'first_name', '') . ' ' . coai_member_card_val($row, 'last_name', ''));
    $clown_name  = trim((string)coai_member_card_val($row, 'clown_name', ''));
    $exp_display = coai_member_card_format_expiration(coai_member_card_val($row, 'expiration_date', ''));
    $state       = coai_member_card_membership_state($row);

    ob_start();
    echo coai_member_card_styles();
    echo coai_member_card_print_styles();
    
    ?>
    <div class="coai-verify-wrap">
      <div class="coai-verify-card">
        <div class="coai-verify-head">
          <h2>Clowns of America International</h2>
          <div>Membership Verification</div>
        </div>

        <div class="coai-verify-body">
          <div class="coai-verify-status <?php echo esc_attr($state['class']); ?>">
            <?php echo esc_html($state['label']); ?>
          </div>

          <div class="coai-verify-grid">
            <div class="coai-field">
              <span class="coai-field-label">COAI #</span>
              <span class="coai-field-value"><?php echo esc_html($coai_number); ?></span>
            </div>

            <div class="coai-field">
              <span class="coai-field-label">Expiration Date</span>
              <span class="coai-field-value"><?php echo esc_html($exp_display); ?></span>
            </div>

            <div class="coai-field">
              <span class="coai-field-label">Full Name</span>
              <span class="coai-field-value"><?php echo esc_html($full_name !== '' ? $full_name : 'Not Available'); ?></span>
            </div>

            <div class="coai-field">
              <span class="coai-field-label">Clown Name</span>
              <span class="coai-field-value"><?php echo esc_html($clown_name !== '' ? $clown_name : '—'); ?></span>
            </div>
          </div>

          <p class="coai-note-muted" style="margin-top:1rem;">
            This page confirms the current membership record associated with the scanned COAI number.
          </p>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Front / Back print view shortcode.
 */
function coai_member_card_print_shortcode($atts = array(), $content = '') {
    $row = coai_member_card_get_current_member_row();

    if (!is_user_logged_in()) {
        return coai_member_card_print_styles()
            . '<div class="coai-notice coai-notice-error">You must be logged in to view your membership card print layout.</div>';
    }

    if (empty($row)) {
        return coai_member_card_print_styles()
            . '<div class="coai-notice coai-notice-error">We could not find a matching member record for your login.</div>';
    }

    $coai_number = trim((string)(
        $row['COAI_number']
        ?? $row['coai_number']
        ?? $row['coai']
        ?? ''
    ));

    $full_name   = trim(coai_member_card_val($row, 'first_name', '') . ' ' . coai_member_card_val($row, 'last_name', ''));
    $clown_name  = trim((string)coai_member_card_val($row, 'clown_name', ''));
    $exp_display = coai_member_card_format_expiration(
        $row['membership_expiration']
        ?? $row['expiration_date']
        ?? ''
    );

    $logo_url = 'https://mycoai.com/wp-content/uploads/2026/03/COAILogo-transparent-1-scaled.png';
    $verify_url = $coai_number !== '' ? coai_member_card_verify_url($coai_number) : '';
    $qr_img = $verify_url !== '' ? coai_member_card_qr_image_url($verify_url) : '';

    ob_start();
    echo coai_member_card_print_styles();
    ?>
    <div class="coai-print-wrap">
      <div class="coai-print-actions">
        <a class="coai-print-btn" href="<?php echo esc_url(home_url('/member-card/')); ?>">← Return to Member Card</a>
        <a class="coai-print-btn" href="#" onclick="window.print(); return false;">🖨️ Print Now</a>
      </div>

      <div class="coai-print-stack coai-print-only">

        <!-- FRONT -->
        <div class="coai-print-card">
          <div class="coai-print-front-header">
            <h2 class="coai-print-org">Clowns of America International</h2>
            <p class="coai-print-sub">Official Membership Card</p>
          </div>

          <div class="coai-print-front-body">
            <?php echo coai_member_card_front_fields_html($full_name, $clown_name, $exp_display, $coai_number); ?>
          </div>
        </div>

        <!-- BACK -->
        <div class="coai-print-card">
          <div class="coai-print-back">
            <?php if (!empty($logo_url)): ?>
              <img src="<?php echo esc_url($logo_url); ?>" class="coai-print-back-logo" alt="COAI Logo">
            <?php endif; ?>

            <?php if ($qr_img !== ''): ?>
              <img src="<?php echo esc_url($qr_img); ?>" class="coai-print-back-qr" alt="Membership verification QR code">
            <?php endif; ?>

            <div class="coai-print-back-note">
              Scan to verify membership
            </div>
          </div>
        </div>

      </div>

      <p class="coai-download-note">
        Print view uses a standard front/back membership card layout sized to 3.5in × 2in.
      </p>
    </div>
    
    <?php
    return ob_get_clean();
}