<?php
// /wp-content/plugins/coai-members-custom/includes/shortcodes/login-form.php

if (!defined('ABSPATH')) exit;

add_shortcode('coai_login_box', function () {

    // Allow editors/builders to see the form without being redirected away
    $editing_context = is_admin()
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('DOING_AJAX') && DOING_AJAX)
        || is_customize_preview()
        || is_preview()
        || (function_exists('coai_safe_can') && coai_safe_can('edit_pages'));

    // If logged in (and not editing in builder), send to portal
    if (function_exists('is_user_logged_in') && is_user_logged_in() && !$editing_context) {
        wp_safe_redirect( home_url('/member-portal/') );
        exit;
    }

    $error = '';

    // Handle login submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
      if (empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_login')) {
        $error = 'Security check failed.';
      } else {
        $username = trim((string) wp_unslash($_POST['log'] ?? ''));
        $password = (string) wp_unslash($_POST['pwd'] ?? '');
        $remember = !empty($_POST['remember']);

        error_log('[COAI LOGIN FORM] submit detected');
        error_log('[COAI LOGIN FORM] username=' . $username . ' remember=' . ($remember ? 'YES' : 'NO'));

        $user = wp_signon([
          'user_login'    => $username,
          'user_password' => $password,
          'remember'      => $remember,
        ], is_ssl());

        if (is_wp_error($user)) {
          error_log('[COAI LOGIN FORM] wp_signon error code=' . $user->get_error_code());
          error_log('[COAI LOGIN FORM] wp_signon error message=' . $user->get_error_message());
        } else {
          error_log('[COAI LOGIN FORM] wp_signon success user_id=' . (int)$user->ID . ' login=' . $user->user_login);
        }

        if (!is_wp_error($user)) {
          // wp_signon() already sets the auth cookie.
          // But it's fine to ensure the current user is set for this request.
          wp_set_current_user($user->ID);

          // Optional: honor force_password_change flag (if present on members table)
          $needs_change = false;
          global $wpdb;

      // Use same members table resolver pattern as auth bridge
      if (function_exists('coai_members_table_name')) {
        $table = coai_members_table_name();
      } elseif (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) {
        $table = COAI_MEMBERS_TABLE;
      } else {
        $table = $wpdb->prefix . 'members';
      }

      $mid = (int) get_user_meta($user->ID, 'coai_member_id', true);

      if ($mid > 0) {
        // only query if the column exists (prevents SQL errors on older schemas)
        $cols = $wpdb->get_col("DESC `{$table}`", 0);
        $cols_lc = array_map('strtolower', (array)$cols);

        if (in_array('force_password_change', $cols_lc, true)) {
          $val = $wpdb->get_var($wpdb->prepare(
            "SELECT force_password_change FROM `{$table}` WHERE member_id=%d",
            $mid
          ));
          $needs_change = !empty($val);
        }
      }

      if ($needs_change) {
        wp_safe_redirect(home_url('/change-password/?force=1'));
        exit;
      }

      // Redirect: honor redirect_to ONLY for front-end pages; never send /member-login/ users to wp-admin
      $raw_redirect = isset($_REQUEST['redirect_to']) ? (string) $_REQUEST['redirect_to'] : '';
      $fallback = home_url('/member-portal/');

      $target = $fallback;

      if ($raw_redirect) {
        $candidate = wp_validate_redirect($raw_redirect, $fallback);

        // Block wp-admin redirects for this member portal flow
        if (strpos($candidate, admin_url()) !== 0) {
          $target = $candidate;
        }
      }

      wp_safe_redirect($target);
      exit;

    } else {
      $error = wp_strip_all_tags($user->get_error_message());
    }
  }
}

    // If they were bounced here from a protected page, show a friendly notice
    $pending_redirect = '';
    if (!empty($_GET['redirect_to'])) {
        $pending_redirect = esc_url_raw($_GET['redirect_to']);
        $redirect_note = '<div style="margin-bottom:.75rem;padding:.5rem;border:1px solid #bfdbfe;background:#dbeafe;color:#1e40af;border-radius:8px;">
            This page is for members only. Please log in — we’ll send you back when you’re in.
        </div>';
    } else {
        $redirect_note = '';
    }

    ob_start(); ?>
    
    <div class="coai-login">
      <h2 style="margin:0 0 .75rem;">Member Login</h2>
      
      <p class="coai-member-help" style="margin:0 0 2px;text-align:center;font-weight:700;color:#2563eb;">
        Already a member?
      </p>
      <p class="coai-login-hint" style="margin:0 0 10px;text-align:center;color:#6b7280;font-size:14px;">
        Log in below.
      </p>

      <?php echo $redirect_note; ?>

      <?php if ($error): ?>
        <div style="margin-bottom:.75rem;padding:.5rem;border:1px solid #b91c1c;background:#fee2e2;color:#991b1b;border-radius:8px;">
          <?php echo esc_html($error); ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?php echo esc_url(home_url('/member-login/')); ?>">
        <?php wp_nonce_field('coai_login', '_coai_nonce'); ?>

        <?php if (!empty($pending_redirect)): ?>
          <input type="hidden" name="redirect_to" value="<?php echo esc_attr($pending_redirect); ?>">
        <?php endif; ?>

        <div style="margin:6px 0 10px; padding:6px 10px;
                  border:1px solid #e5e7eb; border-radius:8px;
                  background:#f9fafb; color:#374151; font-size:13px;">
        <strong>Login Tip:</strong> Your username is usually your email address unless you changed it. If you forgot your password use the FORGOT YOUR PASSWORD? link below the LOGIN BUTTON
        </div>

        <div style="display:grid;gap:.4rem;">

          <label>Username or Email
            <input type="text" name="log" autocomplete="username" required
                   style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </label>

          <!-- Password field with show/hide toggle -->
          <div class="coai-field">
            <label for="coai-pass">Member Login Password</label>
            <div class="coai-passwrap">
              <input id="coai-pass"
                     type="password"
                     name="pwd"
                     autocomplete="current-password"
                     required
                     class="coai-input"
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
            <!-- 👇 ADD THIS LINE EXACTLY HERE -->
            <p style="margin:.25rem 0 0;color:#6b7280;font-size:13px;">
             This is your <strong>Member Portal</strong> password.
            </p>
          </div>
        </div>

          <div class="form-actions" style="display:flex;gap:.75rem;justify-content:flex-end;align-items:center;margin-top:.75rem;">
            <label style="display:flex;gap:.4rem;align-items:center;margin-right:auto;">
              <input type="checkbox" name="remember" value="1"> Remember me
            </label>

            <input type="submit" name="login_submit" value="Log In"
                   style="padding:.55rem 1rem;border:1px solid #d0d0d0;background:#f1f1f1;color:#333;border-radius:6px;cursor:pointer;">
          </div>

        <p class="coai-forgot" style="margin-top:.5rem;text-align:right;font-size:0.9rem;">
            <a href="<?php echo esc_url(
                function_exists('coai_page')
                    ? coai_page('member-reset-password-2')
                    : home_url('/member-reset-password-2/')
            ); ?>">
              FORGOT YOUR PASSWORD?
            </a>
          </p>

      </form>

      <style>
        .coai-passwrap{ position:relative; display:flex; align-items:center; }
        .coai-passwrap .coai-input{ width:100%; padding-right:2.25rem; }
        .coai-toggle{
          position:absolute; right:.5rem; top:50%; transform:translateY(-50%);
          border:0; background:transparent; padding:.25rem; cursor:pointer; line-height:0; color:#374151;
        }
        .coai-toggle:focus{ outline:2px solid #2563eb; border-radius:6px; }
      </style>

      <script>
      (function(){
        if (window.COAI_PW_TOGGLE_LOADED) return;
        window.COAI_PW_TOGGLE_LOADED = true;

        function toggle(btn){
          var wrap = btn.closest('.coai-passwrap');
          if(!wrap) return;
          var input = wrap.querySelector('input[type="password"], input[type="text"]');
          if(!input) return;
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          btn.setAttribute('aria-pressed', show ? 'true' : 'false');
          btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
          btn.title = show ? 'Hide password' : 'Show password';
          var eye = btn.querySelector('.coai-eye');
          var eyeOff = btn.querySelector('.coai-eye-off');
          if (eye && eyeOff){ eye.style.display = show ? 'none' : ''; eyeOff.style.display = show ? '' : 'none'; }
        }

        document.addEventListener('mousedown', function(e){
          if(e.target.closest && e.target.closest('.coai-toggle')) e.preventDefault();
        });
        document.addEventListener('click', function(e){
          var btn = e.target.closest && e.target.closest('.coai-toggle');
          if(btn) toggle(btn);
        });
      })();
      </script>

    </div>
    <?php
    return ob_get_clean();
});

// Legacy alias if any old shortcodes exist
add_shortcode('member_login_form', function () {
    return do_shortcode('[coai_login_box]');
});


