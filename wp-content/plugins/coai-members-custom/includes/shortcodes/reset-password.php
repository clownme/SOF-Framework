<?php
// /wp-content/plugins/coai-members-custom/includes/shortcodes/reset-password.php
if (!defined('ABSPATH')) exit;

/**
 * Self-service reset for MEMBER LOGIN PASSWORD only (wp_members.password).
 * - Never updates wp_users passwords
 * - Allows lookup by username OR email, but email match must be unique
 * - Uses wp_members.reset_token_hash + wp_members.reset_expires
 */

if (!function_exists('coai_members_table_resolve')) {
  function coai_members_table_resolve(): string {
    global $wpdb;
    if (function_exists('coai_members_table_name')) return coai_members_table_name();
    if (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) return COAI_MEMBERS_TABLE;
    return $wpdb->prefix . 'members';
  }
}

if (!function_exists('coai_members_pick_col')) {
  function coai_members_pick_col(array $cols_lc, array $candidates): string {
    foreach ($candidates as $c) {
      if (in_array(strtolower($c), $cols_lc, true)) return $c;
    }
    return '';
  }
}

if (!function_exists('coai_member_lookup_for_reset')) {
  function coai_member_lookup_for_reset(string $ident): array {
    global $wpdb;
    $t = coai_members_table_resolve();
    $ident = trim((string)$ident);
    if ($ident === '') return [null, 'missing'];

    // Detect real column names safely
    $cols = (array) $wpdb->get_col("SHOW COLUMNS FROM `{$t}`", 0);
    $cols_lc = array_map('strtolower', array_map('strval', $cols));

    $username_col = coai_members_pick_col($cols_lc, [
      'username','user_name','user','login','user_login','member_username'
    ]);

    $email_col = coai_members_pick_col($cols_lc, [
      'email','e_mail','email_address','member_email'
    ]);

    if ($username_col === '') {
      // Fail closed: we can’t safely resolve an account without a username column
      return [null, 'schema_missing_username'];
    }

    // Prefer username match first (case-insensitive)
    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM `{$t}` WHERE TRIM(LOWER(`{$username_col}`)) = TRIM(LOWER(%s)) LIMIT 1",
        $ident
      ),
      ARRAY_A
    );
    if ($row) return [$row, 'username'];

    // Then email, but only if unique AND only if ident looks like email
    if ($email_col !== '' && strpos($ident, '@') !== false) {

      $count = (int)$wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(*) FROM `{$t}` WHERE TRIM(LOWER(`{$email_col}`)) = TRIM(LOWER(%s))",
          $ident
        )
      );

      if ($count === 1) {
        $row = $wpdb->get_row(
          $wpdb->prepare(
            "SELECT * FROM `{$t}` WHERE TRIM(LOWER(`{$email_col}`)) = TRIM(LOWER(%s)) LIMIT 1",
            $ident
          ),
          ARRAY_A
        );
        return [$row, 'email'];
      }

      if ($count > 1) return [null, 'ambiguous_email'];
    }

    return [null, 'not_found'];
  }
}

if (!function_exists('coai_member_set_reset_token')) {
  function coai_member_set_reset_token(int $member_id, string $token_plain, int $minutes = 30): bool {
    global $wpdb;
    $t = coai_members_table_resolve();

    $cols = array_map('strtolower', (array)$wpdb->get_col("DESC `{$t}`", 0));
    if (!in_array('reset_token_hash', $cols, true) || !in_array('reset_expires', $cols, true)) {
      return false;
    }

    $exp  = gmdate('Y-m-d H:i:s', time() + ($minutes * 60));
    $hash = password_hash($token_plain, PASSWORD_DEFAULT);

    $ok = $wpdb->update(
      $t,
      [
        'reset_token_hash' => $hash,
        'reset_expires'    => $exp,
      ],
      ['member_id' => $member_id],
      ['%s','%s'],
      ['%d']
    );

    return ($ok !== false);
  }
}

if (!function_exists('coai_member_verify_reset_token')) {
  function coai_member_verify_reset_token(array $row, string $token_plain): bool {
    $hash = (string)($row['reset_token_hash'] ?? '');
    if ($hash === '' || $token_plain === '') return false;

    $exp = (string)($row['reset_expires'] ?? '');
    if ($exp === '') return false;

    // Exp is stored in UTC (gmdate); compare using unix timestamps (UTC)
    $exp_ts = strtotime($exp . ' UTC');
    if (!$exp_ts || $exp_ts < time()) return false;

    return password_verify($token_plain, $hash);
  }
}

if (!function_exists('coai_member_set_password')) {
  function coai_member_set_password(int $member_id, string $plain): bool {
    global $wpdb;
    $t = coai_members_table_resolve();

    $plain = trim($plain);
    if ($member_id <= 0 || $plain === '') return false;

    $cols = array_map('strtolower', (array)$wpdb->get_col("DESC `{$t}`", 0));
    if (!in_array('password', $cols, true)) return false;

    // Must match coai-auth-bridge.php verification logic
    $hash = function_exists('wp_hash_password')
      ? wp_hash_password($plain)
      : password_hash($plain, PASSWORD_DEFAULT);

    $data = [
      'password' => $hash,
    ];
    $formats = ['%s'];

    if (in_array('reset_token_hash', $cols, true)) {
      $data['reset_token_hash'] = '';
      $formats[] = '%s';
    }
    if (in_array('reset_expires', $cols, true)) {
      $data['reset_expires'] = '';
      $formats[] = '%s';
    }
    if (in_array('force_password_change', $cols, true)) {
      $data['force_password_change'] = 0;
      $formats[] = '%d';
    }
    if (in_array('updated_at', $cols, true)) {
      $data['updated_at'] = current_time('mysql');
      $formats[] = '%s';
    }

    $ok = $wpdb->update(
      $t,
      $data,
      ['member_id' => $member_id],
      $formats,
      ['%d']
    );

    error_log('[COAI RESET] set_password mid=' . (int)$member_id . ' ok=' . (($ok !== false) ? 'YES' : 'NO') . ' hash_prefix=' . substr($hash, 0, 12));

    return ($ok !== false);
  }
}

add_shortcode('coai_member_reset_password', function () {

  $msg = '';
  $step = 'request';

  $token = isset($_GET['token']) ? trim((string) wp_unslash($_GET['token'])) : '';
  $mid   = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;

  if ($token !== '' && $mid > 0) $step = 'set';

  // Request reset email
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_reset_request'])) {
    if (empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_member_reset_request')) {
      $msg = '<div class="notice notice-error">Security check failed.</div>';
    } else {
      $ident = sanitize_text_field((string)($_POST['ident'] ?? ''));
      [$row, $why] = coai_member_lookup_for_reset($ident);

      // Generic response to prevent account enumeration
      $msg = '<div class="notice notice-success">If an account was found, a reset email has been sent.</div>';

      if ($why === 'ambiguous_email') {
        $msg = '<div class="notice notice-warning">If your email is linked to multiple accounts, please use your COAI username, or contact support.</div>';
      }

      if ($row && !empty($row['email'])) {
        $email = sanitize_email((string)$row['email']);
        if ($email && is_email($email)) {
          $member_id = (int)$row['member_id'];
          $token_plain = wp_generate_password(64, false, false);

          if (coai_member_set_reset_token($member_id, $token_plain, 30)) {

            // IMPORTANT: do NOT rawurlencode here; add_query_arg handles encoding
            $url = add_query_arg(
              ['mid' => $member_id, 'token' => $token_plain],
              home_url('/member-reset-password-2/')
            );

            $subject = 'COAI Member Portal Password Reset';
            $body = "Hello,\n\nWe received a request to reset your Member Portal password.\n\n"
                  . "Use this link to set a new Member Login Password (valid for 30 minutes):\n"
                  . $url . "\n\n"
                  . "If you did not request this, you can ignore this email.\n";

            wp_mail($email, $subject, $body);
          }
        }
      }
    }
  }

  // Set new password
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_reset_set'])) {
    if (empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_member_reset_set')) {
      $msg = '<div class="notice notice-error">Security check failed.</div>';
      $step = 'set';
    } else {
      $mid   = (int)($_POST['mid'] ?? 0);
      $token = isset($_POST['token']) ? trim((string) wp_unslash($_POST['token'])) : '';

      global $wpdb;
      $t = coai_members_table_resolve();
      $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE member_id=%d LIMIT 1", $mid), ARRAY_A);

      if (!$row || !coai_member_verify_reset_token($row, $token)) {
        $msg = '<div class="notice notice-error">Reset link is invalid or expired. Please request a new one.</div>';
        $step = 'request';
      } else {
        $p1 = (string)($_POST['p1'] ?? '');
        $p2 = (string)($_POST['p2'] ?? '');

        if (trim($p1) === '' || trim($p2) === '') {
          $msg = '<div class="notice notice-error">Both password fields are required.</div>';
          $step = 'set';
        } elseif ($p1 !== $p2) {
          $msg = '<div class="notice notice-error">Passwords do not match.</div>';
          $step = 'set';
        } elseif (strlen($p1) < 8) {
          $msg = '<div class="notice notice-error">Password must be at least 8 characters.</div>';
          $step = 'set';
        } else {
          if (coai_member_set_password($mid, $p1)) {
            $msg = '<div class="notice notice-success">✅ Member Login Password updated. You can now log in.</div>';
            $step = 'done';
          } else {
            $msg = '<div class="notice notice-error">Update failed. Please try again.</div>';
            $step = 'set';
          }
        }
      }
    }
  }

  ob_start(); ?>
  <div style="max-width:520px;margin:1.25rem auto;padding:1rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
    <h2 style="margin:0 0 .75rem;">Reset Member Login Password</h2>

    <div style="margin:6px 0 14px; padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb; color:#374151; font-size:14px;">
      <strong>Note:</strong> This resets your <strong>Member Login Password</strong> for the Member Portal.
      It does <strong>not</strong> reset any WordPress admin password.
    </div>

    <?php if ($msg) echo $msg; ?>

    <?php if ($step === 'request'): ?>
      <form method="post">
        <?php wp_nonce_field('coai_member_reset_request', '_coai_nonce'); ?>
        <label style="display:block;font-weight:600;margin:.75rem 0 .25rem;">COAI Username or Email</label>
        <input type="text" name="ident" required
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">

        <p style="margin:.5rem 0 0;color:#6b7280;font-size:13px;">
          If your email is linked to multiple accounts, you must use your COAI username.
        </p>

        <p style="margin-top:1rem;display:flex;justify-content:flex-end;">
          <button class="button button-primary" type="submit" name="coai_reset_request" value="1">Send Reset Email</button>
        </p>
      </form>
    <?php elseif ($step === 'set'): ?>
      <form method="post">
        <?php wp_nonce_field('coai_member_reset_set', '_coai_nonce'); ?>
        <input type="hidden" name="mid" value="<?php echo (int)$mid; ?>">
        <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

        <label style="display:block;font-weight:600;margin:.75rem 0 .25rem;">New Member Login Password</label>
        <input type="password" name="p1" required autocomplete="new-password"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">

        <label style="display:block;font-weight:600;margin:.75rem 0 .25rem;">Confirm Password</label>
        <input type="password" name="p2" required autocomplete="new-password"
               style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">

        <p style="margin-top:1rem;display:flex;justify-content:flex-end;">
          <button class="button button-primary" type="submit" name="coai_reset_set" value="1">Set New Password</button>
        </p>
      </form>
    <?php else: ?>
      <p><a class="button button-primary" href="<?php echo esc_url(home_url('/member-login/')); ?>">Go to Member Login</a></p>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
});
