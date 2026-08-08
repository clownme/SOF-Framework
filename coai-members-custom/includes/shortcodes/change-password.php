<?php
// /includes/shortcodes/change-password.php
if (!defined('ABSPATH')) exit;

/**
 * Optional debug toggle for this file.
 * To enable temporarily, add in wp-config.php:
 *   define('COAI_CHANGE_PW_DEBUG', true);
 */
if (!function_exists('coai_change_pw_log')) {
  function coai_change_pw_log(string $msg): void {
    if (defined('COAI_CHANGE_PW_DEBUG') && COAI_CHANGE_PW_DEBUG) {
      error_log('[CHANGE-PW] ' . $msg);
    }
  }
}

// Debug (gated)
coai_change_pw_log('file loaded');

/**
 * Helper: fetch current member row from wp_members for the logged-in user
 */
if (!function_exists('coai_get_current_member_row')) {
  function coai_get_current_member_row() {
    if (!is_user_logged_in()) return null;
    $u = wp_get_current_user();
    if (!$u || !$u->ID) return null;

    global $wpdb;

    // Resolve members table consistently (same style as auth bridge/login form)
    if (function_exists('coai_members_table_name')) {
      $t = coai_members_table_name();
    } elseif (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) {
      $t = COAI_MEMBERS_TABLE;
    } else {
      $t = $wpdb->prefix . 'members';
    }

    // 1) Explicit link: coai_member_id usermeta
    $mid = (int) get_user_meta($u->ID, 'coai_member_id', true);
    if ($mid > 0) {
      $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE member_id=%d LIMIT 1", $mid), ARRAY_A);
      return $row ?: null;
    }

    // 2) Optional explicit reverse link: wp_members.wp_user_id (if column exists)
    $cols = $wpdb->get_col("DESC `{$t}`", 0);
    $cols_lc = array_map('strtolower', (array)$cols);

    if (in_array('wp_user_id', $cols_lc, true)) {
      $row = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$t}` WHERE wp_user_id=%d LIMIT 1", (int)$u->ID),
        ARRAY_A
      );
      if ($row && !empty($row['member_id'])) {
        // store explicit link going forward (safe)
        update_user_meta($u->ID, 'coai_member_id', (int)$row['member_id']);
        return $row;
      }
    }

    // No explicit link → do NOT “helpfully” match by email
    return null;
  }
}

/**
 * Password verifier:
 * - Prefer WP's verifier (supports $wp$2y$, $P$, $H$, etc.)
 * - Fallback to common formats if WP verifier isn't available
 */
if (!function_exists('coai_check_legacy_hash')) {
  function coai_check_legacy_hash(string $plain, $hash): bool {
    $hash = trim((string)$hash);
    if ($hash === '') return false;

    // Preferred: WP verifier (handles $wp$ wrapper, phpass, bcrypt, etc.)
    if (function_exists('wp_check_password')) {
      return (bool) wp_check_password($plain, $hash);
    }

    // WordPress-wrapped bcrypt ($wp$2y$...) fallback
    if (strpos($hash, '$wp$') === 0 && function_exists('password_verify')) {
      return password_verify($plain, substr($hash, 4));
    }

    // bcrypt
    if (strpos($hash, '$2') === 0 && function_exists('password_verify')) {
      return password_verify($plain, $hash);
    }

    // WordPress phpass ($P$ / $H$)
    if (strpos($hash, '$P$') === 0 || strpos($hash, '$H$') === 0) {
      require_once ABSPATH . WPINC . '/class-phpass.php';
      $h = new PasswordHash(8, true);
      return $h->CheckPassword($plain, $hash);
    }

    // raw MD5
    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
      return (md5($plain) === strtolower($hash));
    }

    // last resort: plain compare
    return hash_equals($hash, $plain);
  }
}

// Register our shortcode last so it wins over any previously registered one
add_action('init', function () {

  if (shortcode_exists('coai_member_change_password_form')) {
    remove_shortcode('coai_member_change_password_form');
  }

  add_shortcode('coai_member_change_password_form', function () {
    if (!is_user_logged_in()) {
      return '<div class="coai2-alert" style="color:#b91c1c;">Please log in first.</div>';
    }

    global $wpdb;

    // Use same members table resolver pattern as auth bridge/login form
    if (function_exists('coai_members_table_name')) {
      $table = coai_members_table_name();
    } elseif (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) {
      $table = COAI_MEMBERS_TABLE;
    } else {
      $table = $wpdb->prefix . 'members';
    }

    $row = coai_get_current_member_row();
    if (!$row) {
      return '<div class="coai2-alert" style="color:#b91c1c;">Member record not found.</div>';
    }

    // Column-safe force mode (avoids notices if column isn't present)
    $force_col = array_key_exists('force_password_change', $row) ? (int)$row['force_password_change'] : 0;
    $force = (isset($_GET['force']) && $_GET['force'] == '1') || ($force_col === 1);

    $msg = '';

    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coai_change_pw'])) {

      if (empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_change_pw')) {
        $msg = '<p class="coai2-msg coai2-error">Security check failed. Please try again.</p>';

      } else {

        $current_pw = (string) wp_unslash($_POST['current_password'] ?? '');
        $new_pw     = (string) wp_unslash($_POST['new_password'] ?? '');
        $confirm_pw = (string) wp_unslash($_POST['confirm_password'] ?? '');

        // Basic checks
        if ($new_pw === '' || strlen($new_pw) < 8 || !preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $new_pw)) {
          $msg = '<p class="coai2-msg coai2-error">New password must be at least 8 characters and include a letter and a number.</p>';

        } elseif ($new_pw !== $confirm_pw) {
          $msg = '<p class="coai2-msg coai2-error">New password and confirmation do not match.</p>';

        } else {

          $ok_current = true;

          // Verify current unless in forced-change mode
          if (!$force) {
            $stored_hash = (string)($row['password'] ?? '');
            $ok_current  = coai_check_legacy_hash($current_pw, $stored_hash);

            if (!$ok_current) {
              $msg = '<p class="coai2-msg coai2-error">Current password is incorrect.</p>';
            }
          }

          if ($ok_current) {

            // Use WP hash format when available (matches auth bridge + avoids format drift)
            $new_hash = function_exists('wp_hash_password')
              ? wp_hash_password($new_pw)
              : password_hash($new_pw, PASSWORD_DEFAULT);

            // Build update data + formats in lockstep
            $cols = array_map('strtolower', (array)$wpdb->get_col("DESC `{$table}`", 0));

            $data    = ['password' => $new_hash];
            $formats = ['%s'];

            if (in_array('force_password_change', $cols, true)) {
              $data['force_password_change'] = 0;
              $formats[] = '%d';
            }

            if (in_array('updated_at', $cols, true)) {
              $data['updated_at'] = current_time('mysql');
              $formats[] = '%s';
            }

            $updated = $wpdb->update(
              $table,
              $data,
              ['member_id' => (int)$row['member_id']],
              $formats,
              ['%d']
            );

            if ($updated === false) {
              $msg = '<p class="coai2-msg coai2-error">Database update failed: ' . esc_html($wpdb->last_error) . '</p>';
            } else {

              // Redirect with success flag
              wp_safe_redirect(add_query_arg('pw', 'changed', home_url('/member-portal/')));
              exit;

            } // end updated else
          } // end ok_current
        } // end basic checks ok
      } // end nonce ok
    } // end POST

    // Render form
    ob_start(); ?>
    <div class="coai2-wrap" style="max-width:640px;margin:1rem auto;padding:1rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
      <h2 style="margin:0 0 .75rem;"><?php echo $force ? 'Set Your New Password' : 'Change Password'; ?></h2>

      <p style="margin:0 0 .75rem;
            padding:.5rem .75rem;
            border:1px solid #e5e7eb;
            background:#f9fafb;
            border-radius:8px;
            font-size:.9rem;
            color:#374151;">
        This changes your <strong>Member Login Password</strong> for the Member Portal
        <span style="color:#6b7280;">(not your WordPress admin password)</span>.
      </p>

      <?php if ($force): ?>
        <div class="coai2-note" style="margin:.5rem 0 1rem;padding:.5rem;border:1px solid #fbbf24;background:#fffbeb;border-radius:8px;">
          You’re using a temporary password. Please set a new password now.
        </div>
      <?php endif; ?>

      <?php echo $msg; ?>

      <form method="post" class="coai2-form" novalidate>
        <?php wp_nonce_field('coai_change_pw', '_coai_nonce'); ?>

        <?php if (!$force): ?>
          <div class="coai2-field">
            <label for="coai2_current" class="coai2-label">Current Password</label>
            <div class="coai2-passwrap">
              <input id="coai2_current" type="password" name="current_password" autocomplete="current-password" required class="coai2-input">
              <button type="button" class="coai2-toggle" aria-label="Show password" aria-pressed="false" title="Show password">
                <!-- eye -->
                <svg class="coai2-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                <!-- eye-off -->
                <svg class="coai2-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none">
                  <path d="M3 3l18 18M9.9 9.9A3.25 3.25 0 0012 15.25c4.75 0 8-3.25 10-6.25-.63-.98-1.41-1.96-2.36-2.85M7 7C4.93 8.38 3.5 10.12 2 12c1.52 2.09 3.58 4.09 6.53 5.35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <div class="coai2-field">
          <label for="coai2_new" class="coai2-label">New Password</label>
          <div class="coai2-passwrap">
            <input id="coai2_new" type="password" name="new_password" autocomplete="new-password" minlength="8" required class="coai2-input" aria-describedby="coai2_rules">
            <button type="button" class="coai2-toggle" aria-label="Show password" aria-pressed="false" title="Show password">
              <svg class="coai2-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.5"/></svg>
              <svg class="coai2-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none"><path d="M3 3l18 18M9.9 9.9A3.25 3.25 0 0012 15.25c4.75 0 8-3.25 10-6.25-.63-.98-1.41-1.96-2.36-2.85M7 7C4.93 8.38 3.5 10.12 2 12c1.52 2.09 3.58 4.09 6.53 5.35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
          <small id="coai2_rules" class="coai2-help">Use at least 8 characters with a letter and a number.</small>
        </div>

        <div class="coai2-field">
          <label for="coai2_confirm" class="coai2-label">Confirm New Password</label>
          <div class="coai2-passwrap">
            <input id="coai2_confirm" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required class="coai2-input" aria-describedby="coai2_match">
            <button type="button" class="coai2-toggle" aria-label="Show password" aria-pressed="false" title="Show password">
              <svg class="coai2-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.5"/></svg>
              <svg class="coai2-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none"><path d="M3 3l18 18M9.9 9.9A3.25 3.25 0 0012 15.25c4.75 0 8-3.25 10-6.25-.63-.98-1.41-1.96-2.36-2.85M7 7C4.93 8.38 3.5 10.12 2 12c1.52 2.09 3.58 4.09 6.53 5.35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>

          <!-- live match indicator -->
          <div id="coai2_match" class="coai2-match" aria-live="polite" style="display:flex;align-items:center;gap:.35rem;margin:.35rem 0 0 .1rem;">
            <svg class="coai2-match-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none">
              <!-- check -->
              <path class="coai2-check" d="M20 6L9 17l-5-5" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              <!-- x -->
              <path class="coai2-x1" d="M18 6L6 18" stroke="#dc2626" stroke-width="2" stroke-linecap="round" style="display:none"/>
              <path class="coai2-x2" d="M6 6l12 12" stroke="#dc2626" stroke-width="2" stroke-linecap="round" style="display:none"/>
            </svg>
            <span class="coai2-match-text" style="font-size:.9rem;color:#6b7280;">Start typing to confirm your new password.</span>
          </div>
        </div>

        <p class="coai2-actions" style="margin-top:.9rem;display:flex;gap:.5rem;align-items:center;">
          <button type="submit" name="coai_change_pw" class="button button-primary">Save New Password</button>
          <a class="button" href="<?php echo esc_url( home_url('/member-portal/') ); ?>">Cancel</a>
        </p>
      </form>
    </div>

    <style>
      /* Layout & field spacing */
      .coai2-field{ margin:.8rem 0; }
      .coai2-label{ display:block; font-weight:600; margin-bottom:.35rem; }

      /* Input box that resists theme resets */
      .coai2-input{
        width:100% !important;
        min-height:44px !important;
        padding:.55rem .75rem !important;
        padding-right:2.6rem !important; /* space for eye */
        border:1px solid #d1d5db !important;
        border-radius:8px !important;
        background:#fff !important;
        color:#111 !important;
        box-sizing:border-box !important;
        appearance:none !important; -webkit-appearance:none !important;
      }

      /* Password wrapper (relative so we can float the eye) */
      .coai2-passwrap{ position:relative; }

      /* Eye button pinned inside the input on the right */
      .coai2-toggle{
        position:absolute; right:.65rem; top:50%; transform:translateY(-50%);
        padding:.25rem; border:0; background:transparent; line-height:0;
        cursor:pointer; color:#374151;
      }
      .coai2-toggle:focus{ outline:2px solid #2563eb; border-radius:6px; }

      .coai2-help{ display:block; color:#6b7280; margin-top:.35rem; }

      /* If old classes sneak in from cache, neutralize them safely */
      .coai-pwbtn { display:none !important; }
    </style>

    <script>
      (function(){
        if (window.COAI2_CHANGE_PW_LOADED) return;
        window.COAI2_CHANGE_PW_LOADED = true;

        // Eye toggle (event delegation)
        function toggleEye(btn){
          var wrap = btn.closest('.coai2-passwrap'); if(!wrap) return;
          var input = wrap.querySelector('input'); if(!input) return;
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.setAttribute('aria-pressed', show ? 'true' : 'false');
          btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
          btn.title = show ? 'Hide password' : 'Show password';
          var eye = btn.querySelector('.coai2-eye'), off = btn.querySelector('.coai2-eye-off');
          if (eye && off){ eye.style.display = show ? 'none' : ''; off.style.display = show ? '' : 'none'; }
        }
        document.addEventListener('mousedown', function(e){
          if(e.target.closest && e.target.closest('.coai2-toggle')) e.preventDefault();
        });
        document.addEventListener('click', function(e){
          var btn = e.target.closest && e.target.closest('.coai2-toggle'); if(btn) toggleEye(btn);
        });

        // Live match indicator (✓ / ✗) with copy/paste allowed
        var newPw = document.getElementById('coai2_new');
        var conf  = document.getElementById('coai2_confirm');
        var box   = document.getElementById('coai2_match');
        if (newPw && conf && box){
          var icon  = box.querySelector('.coai2-match-icon');
          var check = box.querySelector('.coai2-check');
          var x1    = box.querySelector('.coai2-x1');
          var x2    = box.querySelector('.coai2-x2');
          var text  = box.querySelector('.coai2-match-text');

          function update(){
            var a = newPw.value, b = conf.value;
            if (!a && !b){
              icon.style.display = 'none';
              if (text){ text.style.color = '#6b7280'; text.textContent = 'Start typing to confirm your new password.'; }
              return;
            }
            icon.style.display = '';
            var ok = (a === b && a.length >= 8);
            check.style.display = ok ? '' : 'none';
            x1.style.display    = ok ? 'none' : '';
            x2.style.display    = ok ? 'none' : '';
            if (text){
              text.style.color = ok ? '#16a34a' : '#dc2626';
              text.textContent = ok ? 'Passwords match.' : 'Passwords do not match yet.';
            }
          }
          ['input','change','paste'].forEach(function(evt){
            newPw.addEventListener(evt, update);
            conf .addEventListener(evt, update);
          });
          update();
        }
      })();
    </script>
    <?php
    return ob_get_clean();
  });

}, 9999);
