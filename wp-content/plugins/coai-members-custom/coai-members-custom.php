<?php
/**
 * Plugin Name: COAI Members Custom
 * Description: Member login + member portal shortcodes and routing.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {

    echo '<!-- COAI PLUGIN: ' . __FILE__ . ' -->';

}, 9999);

// Core helper dependencies (must load before shortcodes/hooks)
$staff_perm = plugin_dir_path(__FILE__) . 'includes/helpers/staff-permissions.php';
if (file_exists($staff_perm)) {
  require_once $staff_perm;
  error_log('[COAI] loaded helper: staff-permissions.php (coai_staff_can=' . (function_exists('coai_staff_can') ? 'YES' : 'NO') . ')');
} else {
  error_log('[COAI] MISSING helper: ' . $staff_perm);
}

add_action('wp', function () {
  if (!is_admin()) {
    error_log('[COAI] FRONT wp hook: uri=' . ($_SERVER['REQUEST_URI'] ?? '') .
      ' shortcode_exists(coai_staff_newsletters)=' . (shortcode_exists('coai_staff_newsletters') ? 'YES' : 'NO'));
  }
}, 20);


if (!defined('COAI_PLUGIN_FILE')) define('COAI_PLUGIN_FILE', __FILE__);
if (!defined('COAI_PLUGIN_DIR'))  define('COAI_PLUGIN_DIR',  plugin_dir_path(__FILE__));
if (!defined('COAI_PLUGIN_URL'))  define('COAI_PLUGIN_URL',  plugin_dir_url(__FILE__));
if (!defined('COAI_COMM_TEST_MODE')) {
    define('COAI_COMM_TEST_MODE', false);
}

if (!defined('COAI_COMM_TEST_EMAIL')) {
    define('COAI_COMM_TEST_EMAIL', 'santaduffyandmrsc@gmail.com');
}

if (!function_exists('coai_is_adminish_request')) {
  function coai_is_adminish_request(): bool {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return true;
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/wp-login.php') !== false) return true;
    if (strpos($uri, '/wp-admin') === 0) return true;
    if (function_exists('current_user_can') && current_user_can('manage_options')) return true;
    return false;
  }
}

// ---------- Safe includes ----------
if (!function_exists('coai_safe_require')) {
  function coai_safe_require(string $rel_path): void {
    $p = plugin_dir_path(__FILE__) . ltrim($rel_path, '/');
    if (file_exists($p)) {
      require_once $p;
    } else {
      error_log('[COAI] Missing include: ' . $p);
    }
  }
}

// COAI Core
coai_safe_require('includes/core/core-loader.php');

// COAI Admin
coai_safe_require('includes/admin/admin-loader.php');

// SOF Configuration
coai_safe_require('includes/config/coai-regions.php');

// SOF / COAI Helpers
coai_safe_require('includes/helpers/database-helper.php');

// SOF Authorization and organizational assignments
coai_safe_require('includes/repositories/region-officer-repository.php');
coai_safe_require('includes/roles.php');

// SOF Shared Components
coai_safe_require('includes/components/region-selector.php');
coai_safe_require('includes/google-drive.php');

// Membership Repository
coai_safe_require('includes/repositories/member-repository.php');

// SOF Magazines
coai_safe_require('includes/SOF/Magazines/magazines.php');

// SOF Membership
coai_safe_require('includes/SOF/Membership/membership.php');

// SOF Organization
coai_safe_require('includes/SOF/Organization/organization.php');

// SOF Access
coai_safe_require('includes/SOF/Access/access.php');

// SOF Audience
coai_safe_require('includes/SOF/Audience/audience.php');

// SOF Organizational Memory
coai_safe_require('includes/SOF/Memory/memory.php');

// SOF Shared Presentation
coai_safe_require('includes/SOF/Presentation/presentation.php');

// SOF Communications
coai_safe_require('includes/SOF/Communications/communications.php');

// SOF Newsletters
coai_safe_require('includes/SOF/Newsletters/newsletters.php');

// Core Communications
coai_safe_require('includes/services/communications-service.php');

// Membership Service
coai_safe_require('includes/services/membership-service.php');

// SOF Distribution
coai_safe_require('includes/distribution/distribution-service.php');

// SOF v4.3.0 Reporting Framework
coai_safe_require('includes/reporting/reporting-permissions.php');
coai_safe_require('includes/reporting/reporting-repository.php');
coai_safe_require('includes/reporting/reporting-service.php');

// COAI Shortcodes
coai_safe_require('includes/shortcodes/shortcode-loader.php');

// IMPORTANT: load newsletters immediately so the shortcode is always registered
// COAI Newsletters
coai_safe_require('includes/newsletters/newsletter-loader.php');

coai_safe_require('includes/audit-log.php');

add_action('init', function () {
    add_rewrite_rule(
        '^google-oauth-callback/?$',
        'index.php?google_oauth_callback=1',
        'top'
    );
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'google_oauth_callback';
    return $vars;
});

add_action('init', function () {
    add_rewrite_rule(
        '^google-oauth-callback/?$',
        'index.php?google_oauth_callback=1',
        'top'
    );
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'google_oauth_callback';
    return $vars;
});

/* =========================================================
 * Force Password Change Enforcement
 * - Redirect flagged users to /change-password/ after login
 * - Block access to other front-end pages until changed
 * =======================================================*/

add_filter('login_redirect', function ($redirect_to, $requested, $user) {
  if (is_wp_error($user) || empty($user) || empty($user->ID)) {
    return $redirect_to;
  }

  // Force password change always wins
  if (
    function_exists('coai_member_must_change_password') &&
    coai_member_must_change_password($user->ID)
  ) {
    return function_exists('coai_force_pw_url')
      ? coai_force_pw_url()
      : $redirect_to;
  }

  // Manager-only landing (Admins NOT affected)
  $u = get_userdata($user->ID);
  if ($u && !empty($u->roles)) {
    $roles = array_map('strtolower', (array) $u->roles);
    if (in_array('manager', $roles, true)) {
      return home_url('/member-portal/');
    }
  }

  return $redirect_to;
}, 20, 3);

add_action('template_redirect', function () {

  // Never interfere with admin/login/ajax/rest/cron
  if (function_exists('coai_is_adminish_request') && coai_is_adminish_request()) return;
  if (defined('REST_REQUEST') && REST_REQUEST) return;
  if (defined('DOING_AJAX') && DOING_AJAX) return;
  if (defined('DOING_CRON') && DOING_CRON) return;

  if (!is_user_logged_in()) return;

  $uid = get_current_user_id();
  if (!$uid) return;

  if (!coai_member_must_change_password($uid)) return;

  // Allow the Change Password page itself (slug must match your site)
  $force_url = coai_force_pw_url();
  if (!empty($force_url) && isset($_SERVER['REQUEST_URI'])) {
    $path = strtok($_SERVER['REQUEST_URI'], '?');

    // allow if we're already on the force password path
    if (rtrim($path, '/') === rtrim(parse_url($force_url, PHP_URL_PATH), '/')) return;
  }


  // Allow logout always
  if (isset($_GET['action']) && $_GET['action'] === 'logout') return;

  // Optional: let real WP admins bypass front-end lock (keeps you from getting trapped)
  if (function_exists('current_user_can') && current_user_can('manage_options')) return;

  wp_safe_redirect(coai_force_pw_url());
  exit;
}, 2); // run early (before your other template_redirect rules)


add_action('template_redirect', function () {
  if (!is_user_logged_in()) return;
  if (empty($_GET['coai_open'])) return;
  
  $what = sanitize_key((string) $_GET['coai_open']);

  // Staff only (Admin/Manager)
  $ok = (function_exists('coai_staff_can') && coai_staff_can('manage'));
  if (!$ok && function_exists('coai_current_usergroup')) {
    $g = strtoupper((string) coai_current_usergroup());
    $ok = in_array($g, ['ADMIN','MANAGER'], true);
  }

  if (!$ok) {
    wp_safe_redirect(home_url('/404/'));
    exit;
  }

  // FluentCRM destinations
  $base = admin_url('admin.php?page=fluentcrm-admin');

  if ($what === 'fluentcrm_campaigns') {
    wp_safe_redirect($base . '#/email/campaigns');
    exit;
  }

  if ($what === 'fluentcrm_broadcasts') {
    wp_safe_redirect($base . '#/email/broadcasts');
    exit;
  }
});


function coai_member_must_change_password($wp_user_id) {
  global $wpdb;

  $u = get_user_by('id', $wp_user_id);
  if (!$u || empty($u->user_email)) return false;

  $table = function_exists('coai_get_members_table') ? coai_get_members_table() : ($wpdb->prefix . 'members');
  $email = strtolower(trim($u->user_email));

  $flag = $wpdb->get_var($wpdb->prepare(
    "SELECT force_password_change FROM `$table` WHERE LOWER(TRIM(email)) = %s LIMIT 1",
    $email
  ));

  return ((int)$flag === 1);
}

function coai_force_pw_url() {
  $page = get_page_by_path('change-password'); // your slug
  if ($page) return get_permalink($page->ID);
  return home_url('/change-password/');
}


/** ---------------------------------------------------------
 * Assets: change-password.js (registered early, enqueued on use)
 * -------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;

    $handle = 'coai-change-password';
    $path   = COAI_PLUGIN_DIR . 'includes/js/change-password.js';
    $src    = COAI_PLUGIN_URL . 'includes/js/change-password.js';
    $ver    = file_exists($path) ? filemtime($path) : '1.0.0';

    // Register now (HEAD). We’ll enqueue when the form renders.
    wp_register_script($handle, $src, [], $ver, false);
}, 5);


/** Add attributes so optimizers don't move/aggregate/defer the script */
add_filter('script_loader_tag', function ($tag, $handle) {
    if ($handle === 'coai-change-password') {
        $tag = str_replace(
            ' src=',
            ' data-no-optimize="1" data-no-defer="1" data-cfasync="false" src=',
            $tag
        );
    }
    return $tag;
}, 10, 2);

// Scoped enqueue: only when the page contains the shortcode or is the change-password page
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;

    $should_enqueue = false;

    // enqueue on /change-password/ page (adjust if your page slug is different)
    if (function_exists('is_page') && is_page('change-password')) {
        $should_enqueue = true;
    }

    // also enqueue when the current post content literally has the shortcode
    if (!$should_enqueue && is_singular()) {
        $post = get_post();
        if ($post && has_shortcode($post->post_content, 'coai_member_change_password_form')) {
            $should_enqueue = true;
        }
    }

    // OPTIONAL: If your form is rendered via a template (not literal shortcode),
    // you can force load for that template/page too:
    // if (function_exists('is_page') && is_page('member-portal')) $should_enqueue = true;

    if ($should_enqueue) {
        $handle = 'coai-change-password';
        $path   = plugin_dir_path(__FILE__) . 'includes/js/change-password.js';
        $src    = plugins_url('includes/js/change-password.js', __FILE__);
        $ver    = file_exists($path) ? filemtime($path) : '1.0.0';

        if (!wp_script_is($handle, 'registered')) {
            wp_register_script($handle, $src, [], $ver, /* in_footer */ false);
        } else {
            // re-register in HEAD in case it was previously registered for footer
            wp_deregister_script($handle);
            wp_register_script($handle, $src, [], $ver, false);
        }

        wp_enqueue_script($handle);

        // tiny probe so we can verify execution in DevTools
        wp_add_inline_script($handle, 'window.__coaiPwInitFile = (window.__coaiPwInitFile||"probe");');
    }
}, 20);

// Safe wrapper so top-level calls never crash during early load
if (!function_exists('coai_safe_can')) {
    function coai_safe_can($cap) {
        // Only allow capability checks after pluggable is ready
        if (!function_exists('wp_get_current_user') || !function_exists('current_user_can')) return false;
        return current_user_can($cap);
    }
}

// Load shortcode files (after WP is ready)
add_action('init', function () {

  $files = [
    'login-form',
    'menu-login-panel',
    'member-portal',
    'profile-form',
    'reset-password',
    'admin-members',
    'change-password',
    'member-edit',
    'staff-newsletters',
    'home-login-help',
  ];

  foreach ($files as $f) {
    $p = plugin_dir_path(__FILE__) . "includes/shortcodes/{$f}.php";

    if (file_exists($p)) {
      require_once $p;
      error_log('[COAI] init loaded shortcode file: ' . $f . '.php');
    } else {
      error_log('[COAI] init missing shortcode file: ' . $p);
    }
  }

  $member_card = plugin_dir_path(__FILE__) . 'includes/shortcodes/member-card.php';
  if (file_exists($member_card)) {
    require_once $member_card;
    error_log('[COAI] loaded shortcode: member-card.php ([coai_member_card]=' . (shortcode_exists('coai_member_card') ? 'YES' : 'NO') . ', [coai_member_card_verify]=' . (shortcode_exists('coai_member_card_verify') ? 'YES' : 'NO') . ')');
  } else {
    error_log('[COAI] MISSING shortcode: ' . $member_card);
  }
  
  // After loading, confirm registration
  error_log('[COAI] shortcode_exists(coai_members_admin)=' . (shortcode_exists('coai_members_admin') ? 'YES' : 'NO'));

}, 20);

// --- Force-register the reset shortcodes (in case anything removes/overrides them)
//if (function_exists('coai_render_reset_password_v2')) {
//    remove_shortcode('coai_reset_password');
//    remove_shortcode('coai_member_reset_password_form');
//    
//    add_shortcode('coai_reset_password', 'coai_render_reset_password_v2');
//    add_shortcode('coai_member_reset_password_form', 'coai_render_reset_password_v2');
//    
//    error_log('[COAI] hard-registered reset shortcodes after include');
//} else {
//    error_log('[COAI] reset renderer missing right after include');
//}

// Redirect logged-in users away from /member-login,
// but never interfere with admin/preview/customizer/AJAX/REST or manual bypass.
add_action('template_redirect', function () {

    if (!function_exists('is_page')) return;

    // Never redirect in wp-admin or during AJAX/REST/cron
    if (is_admin()) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (defined('DOING_CRON') && DOING_CRON) return;
    
    // Never interfere with wp-login.php or any wp-admin routing
    $req = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($req, 'wp-login.php') !== false) return;
    if (strpos($req, '/wp-admin') !== false) return;

    // Manual bypass (for testing)
    if (isset($_GET['no_redirect'])) return;

    // --- Finance gating: keep Finance-only users off Admin/Manager dashboard pages ---
    if (is_page()) {
        global $post;

        // If this page is the Admin/Manager dashboard (shortcode-based)
        if ($post && function_exists('has_shortcode') && has_shortcode($post->post_content, 'coai_members_admin')) {

            // Finance-only: finance=yes, manage=no, and not WP-admin
            if (
                function_exists('coai_staff_can')
                && coai_staff_can('finance')
                && !coai_staff_can('manage')
                && !(function_exists('current_user_can') && current_user_can('manage_options'))
            ) {
                // Prevent redirect loops if this ever runs on the finance page
                if (!is_page('finance-portal')) {
                    wp_safe_redirect(home_url('/finance-portal/')); // change slug if needed
                    exit;
                }
                return;
            }
        }
    }

    // --- Keep logged-in users off login page ---
    if (!is_page('member-login')) return;

    // Allow preview/customizer (for testing/screenshots)
    if (is_preview()) return;
    if (function_exists('is_customize_preview') && is_customize_preview()) return;

    // Let admins/editors view the login page
    if (function_exists('current_user_can') && (current_user_can('edit_pages') || current_user_can('manage_options'))) {
        return;
    }

    // Logged-in users go to the portal
    if (function_exists('is_user_logged_in') && is_user_logged_in()) {
        wp_safe_redirect(home_url('/member-portal/'));
        exit;
    }

}, 9);

// Load admin-only screens only in wp-admin
add_action('admin_init', function () {
    if (!is_admin()) return;

    $files = [
        'includes/admin/import-members.php',
        'includes/admin/insurance-csv-compare.php',
    ];

    foreach ($files as $rel) {
        $p = plugin_dir_path(__FILE__) . $rel;
        if (file_exists($p)) {
            require_once $p;
            error_log('[COAI] admin_init included ' . $rel);
        } else {
            error_log('[COAI] admin_init missing ' . $rel . ' at ' . $p);
        }
    }
});


add_action('template_redirect', function () {
    if (coai_is_adminish_request()) return; // skip admin/login requests
    
    if (function_exists('is_page') && is_page('member-edit')) {
        if ( current_user_can('manage_options') || current_user_can('edit_users') ) {
            $mid = isset($_GET['mid']) ? (int) $_GET['mid'] : 0;
            wp_safe_redirect( add_query_arg(['mid'=>$mid, 'view'=>'edit'], home_url('/admin-members/')) );
            exit;
        }
    }
}, 1); // run very early

// Users → Import Members (Admins only)
add_action('admin_menu', function () {
    add_users_page(
        'Import Members',
        'Import Members',
        'manage_options',
        'coai-import-members',
        'coai_render_import_members_page' // always exists (fallback renderer below)
    );
});

// Users → Insurance CSV Compare
add_action('admin_menu', function () {
    add_users_page(
        'Insurance CSV Compare',          // Page title
        'Insurance CSV Compare',          // Menu label
        'manage_options',                 // Admins only (page enforces manager access separately)
        'coai-insurance-csv-compare',     // Menu slug
        'coai_render_insurance_csv_compare_page'
    );
});


add_action('init', function() {
    $path = __DIR__ . '/includes/shortcodes/reset-password.php';
    if (file_exists($path)) {
        require_once $path;
        error_log('[COAI] ✅ reset-password.php loaded successfully');
    } else {
        error_log('[COAI] ❌ reset-password.php missing at ' . $path);
    }
}, 12);

// Always-present page renderer with strong fallback
function coai_render_import_members_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Sorry, you are not allowed to import members.');
    }

    // If the external renderer was loaded, defer to it.
    if (function_exists('coai_render_import_members_admin_page')) {
        error_log('COAI Importer: calling external renderer');
        coai_render_import_members_admin_page();
        return;
    }

    // Fallback UI so the page never shows blank
    error_log('COAI Importer: using inline fallback UI');
    $levels_table  = function_exists('coai_get_levels_table') ? coai_get_levels_table() : 'wp_membership_levels';
    ?>
    <div class="wrap">
      <h1>Import Members</h1>
      <div class="notice notice-warning"><p>
        The external importer UI file wasn’t loaded. Using fallback form.
      </p></div>

      <p>This will validate <code>membership_level_id</code> against <code><?php echo esc_html($levels_table); ?></code>
         and insert/update rows in your members table.</p>

      <?php
      if (!empty($_GET['coai_import_msg'])) {
          echo '<div class="notice notice-info"><p>' . esc_html($_GET['coai_import_msg']) . '</p></div>';
      }
      if (!empty($_GET['coai_import_err'])) {
          echo '<div class="notice notice-error"><p>' . esc_html($_GET['coai_import_err']) . '</p></div>';
      }
      ?>

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
              <label><input type="checkbox" name="update_existing" value="1" checked> Update existing members</label><br>
              <label><input type="checkbox" name="skip_expired_empty" value="1"> Skip rows with <code>status=Expired</code> and no <code>membership_level_id</code></label>
            </td>
          </tr>
        </table>

        <p class="submit">
          <button type="submit" class="button button-primary">Run Import</button>
        </p>
      </form>
    </div>
    <?php
}

// -------------------------------------------------
// Election / Voting Module
// -------------------------------------------------
$coai_election_files = [
    __DIR__ . '/includes/helpers/election-db.php',
    __DIR__ . '/includes/helpers/election-permissions.php',
    __DIR__ . '/includes/shortcodes/member-voting.php',
    __DIR__ . '/includes/shortcodes/staff-election-admin.php',
    __DIR__ . '/includes/shortcodes/staff-election-results.php',
];

foreach ($coai_election_files as $file) {
    if (file_exists($file)) {
        require_once $file;
        error_log('[COAI] init loaded election file: ' . basename($file));
    } else {
        error_log('[COAI] Missing election file: ' . $file);
    }
}

$coai_more_shortcodes = [
    __DIR__ . '/includes/shortcodes/staff-region-backfill.php',
];
foreach ($coai_more_shortcodes as $file) {
    if (file_exists($file)) {
        require_once $file;
        error_log('[COAI] init loaded shortcode file: ' . basename($file));
    }
}

// --- Remove specific menu items from nav for logged-out visitors ---
add_action('init', function () {
    // Do nothing in the dashboard (prevents any chance of blocking wp-admin)
    if (is_admin()) {
        return;
    }

    add_filter('wp_nav_menu_objects', function ($items) {
        // Only hide items for logged-out visitors
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            return $items;
        }

        // Slugs to hide when logged out
        $protected_slugs = [
            'the-new-calliope',
            'coai-bylaws',
            'board-members',
            'judge-score-sheets',
            'coai-insurance',
        ];

        // Title fallbacks (in case URLs are odd)
        $title_contains = [
            'the new calliope',
            'bylaws',
            'board members',
            'judge score',
            'insurance',
        ];

        $filtered = [];
        foreach ($items as $item) {
            $path = parse_url($item->url, PHP_URL_PATH) ?? '';
            $path = trim($path, '/');
            $slug = $path ? basename($path) : '';
            $title_lc = strtolower($item->title);

            $match_by_slug  = $slug && in_array($slug, $protected_slugs, true);

            $match_by_title = false;
            foreach ($title_contains as $needle) {
                if (strpos($title_lc, $needle) !== false) {
                    $match_by_title = true;
                    break;
                }
            }

            if ($match_by_slug || $match_by_title) {
                // hide for logged-out users
                continue;
            }
            $filtered[] = $item;
        }

        return $filtered;
    }, 20);
});

// Alias: allow [coai_member_login] to behave like [coai_login_box]
add_shortcode('coai_member_login', function($atts = [], $content = ''){
    return do_shortcode('[coai_login_box]');
});

/* --- Finance view-only routing --- */

// Helper: true only if user is Finance (and NOT admin/manager)
if (!function_exists('coai_is_finance_only')) {
  function coai_is_finance_only(): bool {
    if (!is_user_logged_in()) return false;
    $u = wp_get_current_user();
    $roles = array_map('strtolower', (array)$u->roles);
    $is_admin   = in_array('administrator', $roles, true);
    $is_manager = in_array('manager', $roles, true);
    $is_finance = in_array('finance', $roles, true);

    if ($is_admin || $is_manager) return false;
    if ($is_finance) return true;

    // optional fallback via usergroup (wp_members)
    $mid = (int) get_user_meta($u->ID, 'coai_member_id', true);
    if ($mid) {
      global $wpdb; $t = defined('COAI_MEMBERS_TABLE') ? COAI_MEMBERS_TABLE : ($wpdb->prefix.'members');
      $g = strtoupper((string)$wpdb->get_var($wpdb->prepare("SELECT usergroup FROM `$t` WHERE member_id=%d", $mid)));
      if (in_array($g, ['ADMIN','MANAGER'], true)) return false;
      if ($g === 'FINANCE') return true;
    }
    return false;
  }
}

/**
 * Resolve the current person's first name.
 *
 * Resolution order:
 *     1. WordPress first_name user meta
 *     2. COAI member record using coai_member_id
 *     3. COAI member record using email or username
 *     4. WordPress display name
 */
if (!function_exists('coai_get_current_person_first_name')) {
    function coai_get_current_person_first_name(): string {

        if (!is_user_logged_in()) {
            return '';
        }

        global $wpdb;

        $current_user = wp_get_current_user();

        if (!$current_user || !$current_user->ID) {
            return '';
        }

        /*
         * First try standard WordPress user meta.
         */
        $first_name = trim(
            (string) get_user_meta(
                $current_user->ID,
                'first_name',
                true
            )
        );

        if ($first_name !== '') {
            return $first_name;
        }

        /*
         * Next, locate the corresponding COAI member.
         */
        $members_table = function_exists(
            'coai_get_members_table'
        )
            ? coai_get_members_table()
            : $wpdb->prefix . 'members';

        $member_id = (int) get_user_meta(
            $current_user->ID,
            'coai_member_id',
            true
        );

        if ($member_id > 0) {
            $first_name = trim(
                (string) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT first_name
                         FROM `$members_table`
                         WHERE member_id = %d
                         LIMIT 1",
                        $member_id
                    )
                )
            );
        }

        /*
         * Some older accounts do not yet have coai_member_id
         * stored in WordPress user meta. Resolve them using
         * email or username, just as the profile form does.
         */
        if ($first_name === '') {
            $member = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT member_id, first_name
                     FROM `$members_table`
                     WHERE email = %s
                        OR username = %s
                     LIMIT 1",
                    $current_user->user_email,
                    $current_user->user_login
                ),
                ARRAY_A
            );

            if ($member) {
                $first_name = trim(
                    (string) ($member['first_name'] ?? '')
                );

                if (!empty($member['member_id'])) {
                    update_user_meta(
                        $current_user->ID,
                        'coai_member_id',
                        (int) $member['member_id']
                    );
                }
            }
        }

        if ($first_name !== '') {
            return $first_name;
        }

        /*
         * Last-resort WordPress fallback.
         */
        $display_name = trim(
            (string) $current_user->display_name
        );

        if ($display_name !== '') {
            $name_parts = preg_split(
                '/\s+/',
                $display_name
            );

            return trim(
                (string) ($name_parts[0] ?? '')
            );
        }

        return 'My Account';
    }
}

// Toggle the Login menu item between:
// - A front-end login panel for logged-out visitors
// - Log Out for authenticated members
add_filter('wp_nav_menu_objects', function ($items, $args) {

    foreach ($items as $item) {

        $is_login_item =
            stripos($item->title, 'login') !== false ||
            preg_match(
                '#/member-login/?$#i',
                untrailingslashit($item->url)
            );

        if (!$is_login_item) {
            continue;
        }

        if (is_user_logged_in()) {

            $first_name = function_exists(
                'coai_get_current_person_first_name'
            )
                ? coai_get_current_person_first_name()
                : 'My Account';

            $item->title = $first_name;
            $item->url = '#coai-account-panel';

            $item->classes[] = 'coai-account-menu-trigger';

            continue;
        }

        $item->title = 'Log In';
        $item->url = '#coai-menu-login-panel';

        $item->classes[] = 'coai-menu-login-trigger';
    }

    return $items;

}, 9, 2);


/* =========================================================
 * Constants / Helpers
 * =======================================================*/

function coai_status_choices(): array {
    // canonical internal values (UPPERCASE) => labels
    return [
        'ACTIVE'  => 'Active',
        'EXPIRED' => 'Expired',
        'PENDING' => 'Pending',
    ];
}

function coai_normalize_status(?string $raw): string {
    $choices = coai_status_choices();
    $s = strtoupper(trim((string)$raw));
    return array_key_exists($s, $choices) ? $s : 'PENDING'; // or default you prefer
}

function coai_status_label(string $status): string {
    $choices = coai_status_choices();
    return $choices[$status] ?? 'Pending';
}


// Ensure region mapper is available plugin-wide (no output!)
// --- Region helper (robust country normalization) ---
if (!function_exists('coai_country')) {
    function coai_country(string $input, string $return = 'code'): string {
        // master labels (code => name)
        static $LABELS = [
            'US'=>'United States','CA'=>'Canada','GB'=>'United Kingdom','IE'=>'Ireland','FR'=>'France',
            'DE'=>'Germany','ES'=>'Spain','IT'=>'Italy','PT'=>'Portugal','NL'=>'Netherlands','BE'=>'Belgium',
            'CH'=>'Switzerland','AT'=>'Austria','SE'=>'Sweden','NO'=>'Norway','DK'=>'Denmark','FI'=>'Finland',
            'IS'=>'Iceland','PL'=>'Poland','CZ'=>'Czechia','SK'=>'Slovakia','HU'=>'Hungary','RO'=>'Romania',
            'BG'=>'Bulgaria','GR'=>'Greece','TR'=>'Türkiye','UA'=>'Ukraine','IL'=>'Israel','SA'=>'Saudi Arabia',
            'AE'=>'United Arab Emirates','QA'=>'Qatar','EG'=>'Egypt','ZA'=>'South Africa','NG'=>'Nigeria',
            'KE'=>'Kenya','IN'=>'India','PK'=>'Pakistan','BD'=>'Bangladesh','LK'=>'Sri Lanka','NP'=>'Nepal',
            'CN'=>'China','JP'=>'Japan','KR'=>'South Korea','TW'=>'Taiwan','HK'=>'Hong Kong','SG'=>'Singapore',
            'MY'=>'Malaysia','TH'=>'Thailand','PH'=>'Philippines','VN'=>'Vietnam','ID'=>'Indonesia',
            'AU'=>'Australia','NZ'=>'New Zealand','MX'=>'Mexico','BR'=>'Brazil','AR'=>'Argentina','CL'=>'Chile',
            'CO'=>'Colombia','PE'=>'Peru','TT'=>'Trinidad and Tobago','Barbados'=>'BB','Bosnia and Herzegovina'=>'BA',
			'Papua New Guinea'=>'PG','Honduras'=>'HN','Dominican Republic'=>'DO','Cuba'=>'CU',
			'Costa Rica'=>'CR','Ecuador'=>'EC','Venezuela'=>'VE','Nicaragua'=>'NI','Guatemala'=>'GT',
        ];

        // common aliases (name-ish => code)
        static $ALIASES = [
            'USA'=>'US','U S A'=>'US','UNITED STATES'=>'US','UNITED STATES OF AMERICA'=>'US','U.S.'=>'US','U.S.A.'=>'US',
            'UK'=>'GB','U K'=>'GB','U.K.'=>'GB','GREAT BRITAIN'=>'GB','ENGLAND'=>'GB','BRITAIN'=>'GB',
            'SOUTH KOREA'=>'KR','REPUBLIC OF KOREA'=>'KR',
            'UAE'=>'AE','UNITED ARAB EMIRATES'=>'AE',
        ];
        // sanitize input
        $s = strtoupper(trim($input));
        $s = preg_replace('~[.\'’`"]+~', '', $s);
        $s = preg_replace('~\s+~', ' ', $s);

        // 1) already a valid ISO-2?
        if (preg_match('~^[A-Z]{2}$~', $s) && isset($LABELS[$s])) {
            $code = $s;
        } else {
            // 2) exact name match
            $code = array_search(ucwords(strtolower($s)), $LABELS, true);
            if ($code === false) {
                // 3) alias match
                $code = $ALIASES[$s] ?? '';
            }
        }

        if (!$code) return ''; // unknown

        return ($return === 'label') ? ($LABELS[$code] ?? $code) : $code;
    }
}

// =========================================================
// Canonical table resolver (single source of truth)
// - Safe: defines only if missing (won't override MU-plugins)
// - No DB queries / no side effects
// =========================================================
if (!function_exists('coai_tables')) {
  function coai_tables(): array {
    global $wpdb;

    // Prefer explicit constants if set
    $members = (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) ? COAI_MEMBERS_TABLE : ($wpdb->prefix . 'members');
    $levels  = (defined('COAI_LEVELS_TABLE')  && COAI_LEVELS_TABLE)  ? COAI_LEVELS_TABLE  : ($wpdb->prefix . 'membership_levels');

    return [
      'members'            => $members,
      'membership_levels'  => $levels,
      // optional aliases:
      'levels'             => $levels,
    ];
  }
}

if (!function_exists('coai_table')) {
  function coai_table(string $key): string {
    $t = coai_tables();
    return $t[$key] ?? '';
  }
}

// Back-compat wrappers (optional, but helps standardize gradually)
if (!function_exists('coai_get_members_table')) {
  function coai_get_members_table(): string {
    $t = function_exists('coai_table') ? coai_table('members') : '';
    return $t ?: (defined('COAI_MEMBERS_TABLE') ? COAI_MEMBERS_TABLE : $GLOBALS['wpdb']->prefix . 'members');
  }
}

if (!function_exists('coai_members_table')) {
  function coai_members_table(): string {
    return coai_get_members_table();
  }
}

if (!function_exists('coai_get_levels_table')) {
  function coai_get_levels_table(): string {
    $t = function_exists('coai_table') ? coai_table('membership_levels') : '';
    return $t ?: (defined('COAI_LEVELS_TABLE') ? COAI_LEVELS_TABLE : $GLOBALS['wpdb']->prefix . 'membership_levels');
  }
}


// Your members table (adjust if different)
if (!defined('COAI_MEMBERS_TABLE')) {
    define('COAI_MEMBERS_TABLE', 'wp_members');
}

if ( ! defined('COAI_LEVELS_TABLE') ) {
    define('COAI_LEVELS_TABLE', 'wp_membership_levels'); // your levels table
}

/**
 * Build a site-relative URL correctly (handles subdirectory installs).
 * Usage: coai_url('member-login/') => https://site.com/subdir/member-login/
 */
function coai_url(string $path = ''): string {
    return home_url('/' . ltrim($path, '/'));
}

// --- Region helper (US, Canada, fallback International) ---
if (!function_exists('coai_region_from_location')) {
function coai_region_from_location($state, $country = 'US') {
    $state   = strtoupper(trim((string)$state));
    $country = coai_normalize_country($country ?: 'US');

    // Canada first
    if ($country === 'CA' || $country === 'CANADA') return 'Canada';

    // Non-US/Non-CA -> International
    if ($country && $country !== 'US') return 'International';

    // US regions
    $midwest   = ['IL','IN','IA','KS','MI','MN','MO','NE','ND','OH','SD','WI'];
    $south     = ['AL','AR','DE','DC','FL','GA','KY','LA','MD','MS','NC','OK','SC','TN','TX','VA','WV'];
    $northeast = ['CT','ME','MA','NH','NJ','NY','PA','RI','VT'];
    $west      = ['AK','AZ','CA','CO','HI','ID','MT','NV','NM','OR','UT','WA','WY'];

    if (in_array($state, $midwest, true))   return 'Midwest';
    if (in_array($state, $south, true))     return 'South';
    if (in_array($state, $northeast, true)) return 'Northeast';
    if (in_array($state, $west, true))      return 'West';

    return null; // unknown/blank
}}

// Convention attendee cross-reference admin tool
if (!function_exists('coai_cax_render_admin_page')) {
  @require_once plugin_dir_path(__FILE__) . 'includes/admin/convention-attendee-crossref.php';
}

// === Live Region lookup (AJAX) ===
add_action('wp_ajax_coai_region_lookup', function () {
    // nonce
    $nonce = $_POST['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'coai_region_ajax')) {
        wp_send_json_error(['msg' => 'bad nonce'], 400);
    }
    
        // allow any logged-in user (members & staff)
    if ( ! is_user_logged_in() ) {
        wp_send_json_error(['msg' => 'denied'], 403);
    }

    // ensure mapper is available
    if (!function_exists('coai_region_from_location')) {
        @require_once plugin_dir_path(__FILE__) . 'includes/region-map.php';
    }

    $state   = strtoupper(trim((string)($_POST['state'] ?? '')));
    $country = strtoupper(trim((string)($_POST['country'] ?? 'US')));
    if ($country === 'USA') $country = 'US';

    $region = function_exists('coai_region_from_location')
        ? coai_region_from_location($state, $country)
        : null;

    wp_send_json_success(['region' => ($region ?: '')]);
});

if (!function_exists('coai_normalize_state')) {
  function coai_normalize_state($s, $country = 'US') {
    $s = strtoupper(trim((string)$s));
    $country = coai_normalize_country($country);

    // already a 2-letter code
    if (preg_match('~^[A-Z]{2}$~', $s)) return $s;

    static $us = [
      'ALABAMA'=>'AL','ALASKA'=>'AK','ARIZONA'=>'AZ','ARKANSAS'=>'AR','CALIFORNIA'=>'CA','COLORADO'=>'CO',
      'CONNECTICUT'=>'CT','DELAWARE'=>'DE','DISTRICT OF COLUMBIA'=>'DC','WASHINGTON DC'=>'DC',
      'FLORIDA'=>'FL','GEORGIA'=>'GA','HAWAII'=>'HI','IDAHO'=>'ID','ILLINOIS'=>'IL','INDIANA'=>'IN',
      'IOWA'=>'IA','KANSAS'=>'KS','KENTUCKY'=>'KY','LOUISIANA'=>'LA','MAINE'=>'ME','MARYLAND'=>'MD',
      'MASSACHUSETTS'=>'MA','MASS'=>'MA','MICHIGAN'=>'MI','MINNESOTA'=>'MN','MISSISSIPPI'=>'MS',
      'MISSOURI'=>'MO','MONTANA'=>'MT','NEBRASKA'=>'NE','NEVADA'=>'NV','NEW HAMPSHIRE'=>'NH',
      'NEW JERSEY'=>'NJ','NEW MEXICO'=>'NM','NEW YORK'=>'NY','NORTH CAROLINA'=>'NC','NORTH DAKOTA'=>'ND',
      'OHIO'=>'OH','OKLAHOMA'=>'OK','OREGON'=>'OR','PENNSYLVANIA'=>'PA','RHODE ISLAND'=>'RI',
      'SOUTH CAROLINA'=>'SC','SOUTH DAKOTA'=>'SD','TENNESSEE'=>'TN','TEXAS'=>'TX','UTAH'=>'UT',
      'VERMONT'=>'VT','VIRGINIA'=>'VA','WASHINGTON'=>'WA','WEST VIRGINIA'=>'WV','WISCONSIN'=>'WI','WYOMING'=>'WY',
      'PUERTO RICO'=>'PR','GUAM'=>'GU','AMERICAN SAMOA'=>'AS','NORTHERN MARIANA ISLANDS'=>'MP','US VIRGIN ISLANDS'=>'VI'
    ];

    static $ca = [
      'ALBERTA'=>'AB','BRITISH COLUMBIA'=>'BC','MANITOBA'=>'MB','NEW BRUNSWICK'=>'NB','NEWFOUNDLAND AND LABRADOR'=>'NL',
      'NEWFOUNDLAND'=>'NL','NOVA SCOTIA'=>'NS','NORTHWEST TERRITORIES'=>'NT','NUNAVUT'=>'NU','ONTARIO'=>'ON',
      'PRINCE EDWARD ISLAND'=>'PE','QUEBEC'=>'QC','SASKATCHEWAN'=>'SK','YUKON'=>'YT'
    ];

    $clean = preg_replace('~[^A-Z ]~', '', $s);
    if ($country === 'US') return $us[$s] ?? $us[$clean] ?? $s;
    if ($country === 'CA') return $ca[$s] ?? $ca[$clean] ?? $s;
    return $s;
  }
}



// Change Password form for logged-in users
add_shortcode('coai_member_change_password_form', function () {
    if (!is_user_logged_in()) {
        return '<div style="color:#b91c1c;">You must be logged in.</div>';
    }

    global $wpdb;
    $table = function_exists('coai_get_members_table') ? coai_get_members_table() : ($wpdb->prefix . 'members');
    $msg = '';
    $ok  = false;

    // Load the corresponding wp_members row
    if (!function_exists('coai_get_current_member_row')) {
        return '<div style="color:#b91c1c;">Account lookup unavailable.</div>';
    }
    $row = coai_get_current_member_row();
    if (!$row) {
        return '<div style="color:#b91c1c;">Member record not found.</div>';
    }

    $force = isset($_GET['force']) && $_GET['force'] == '1';
    $requires_current = !$force; // if forced change, we won’t require current password

    // Handle submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coai_change_pw'])) {
        if (empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_change_pw')) {
            $msg = '<div style="color:#b91c1c;">Security check failed.</div>';
        } else {
            $new1 = (string)($_POST['new_password'] ?? '');
            $new2 = (string)($_POST['confirm_password'] ?? '');
            $cur  = (string)($_POST['current_password'] ?? '');

            // Basic checks
            if ($requires_current && $cur === '') {
                $msg = '<div style="color:#b91c1c;">Please enter your current password.</div>';
            } elseif ($new1 === '' || $new2 === '') {
                $msg = '<div style="color:#b91c1c;">Please enter and confirm a new password.</div>';
            } elseif ($new1 !== $new2) {
                $msg = '<div style="color:#b91c1c;">New passwords do not match.</div>';
            } elseif (strlen($new1) < 8) {
                $msg = '<div style="color:#b91c1c;">New password must be at least 8 characters.</div>';
            } else {
                // If we require the current password, verify it against wp_users OR wp_members
                $pass_ok = true;
                if ($requires_current) {
                    // Try WordPress auth
                    $user = wp_get_current_user();
                    $pass_ok = wp_check_password($cur, $user->user_pass, $user->ID);

                    // If WP check fails, try wp_members hash (in case of bridge)
                    if (!$pass_ok && !empty($row['password'])) {
                        $pass_ok = password_verify($cur, (string)$row['password']);
                    }
                }

                if (!$pass_ok) {
                    $msg = '<div style="color:#b91c1c;">Current password is incorrect.</div>';
                } else {
                    // Update both wp_members and wp_users to keep them in sync
                    $hashed = password_hash($new1, PASSWORD_DEFAULT);

                    // 1) wp_members
                    $data = ['password' => $hashed];
                    // Clear force flag if present
                    if (array_key_exists('force_password_change', $row)) {
                        $data['force_password_change'] = 0;
                    }
                    if ($wpdb->get_var("SHOW COLUMNS FROM `$table` LIKE 'updated_at'")) {
                        $data['updated_at'] = current_time('mysql');
                    }
                    $formats = ['%s'];
                    if (isset($data['force_password_change'])) $formats[] = '%d';
                    if (isset($data['updated_at']))            $formats[] = '%s';

                    $wpdb->update($table, $data, ['member_id' => (int)$row['member_id']], $formats, ['%d']);

                    // 2) WordPress user
                    $wp_user = wp_get_current_user();
                    if ($wp_user && $wp_user->ID) {
                        // This logs the user out of **all** sessions (standard WP behavior)
                        wp_set_password($new1, $wp_user->ID);
                        // Auto sign them back in for a smooth experience
                        wp_set_current_user($wp_user->ID);
                        wp_set_auth_cookie($wp_user->ID, true);
                    }

                    // Redirect to portal with notice
                    wp_safe_redirect( add_query_arg('pw_changed', 1, home_url('/member-portal/')) );
                    exit;
                }
            }
        }
    }

    // Form UI
    ob_start(); ?>
    <div style="max-width:520px;margin:1rem auto;padding:1rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
      <h2 style="margin:0 0 .75rem;">Change Password</h2>
      <?php if ($force): ?>
        <p style="margin:.25rem 0 1rem;color:#92400e;background:#fffbeb;border:1px solid #fde68a;padding:.5rem;border-radius:8px;">
          You’re using a temporary password. Please set a new password now.
        </p>
      <?php endif; ?>
      <?php echo $msg; ?>
      <form method="post">
        <?php wp_nonce_field('coai_change_pw', '_coai_nonce'); ?>

        <?php if ($requires_current): ?>
          <p>
            <label style="display:block;font-weight:600;">Current Password</label>
            <input type="password" name="current_password" required
                   style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
          </p>
        <?php endif; ?>

        <p>
          <label style="display:block;font-weight:600;">New Password</label>
          <input type="password" name="new_password" required
                 minlength="8"
                 style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </p>
        <p>
          <label style="display:block;font-weight:600;">Confirm New Password</label>
          <input type="password" name="confirm_password" required
                 minlength="8"
                 style="width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px;">
        </p>

        <p style="margin-top:.75rem;display:flex;gap:.5rem;">
          <button type="submit" name="coai_change_pw" class="button button-primary">Save New Password</button>
          <a class="button" href="<?php echo esc_url( home_url('/member-portal/') ); ?>">Cancel</a>
        </p>
      </form>
    </div>
    <?php
    return ob_get_clean();
});

// --- Normalize country to canonical codes ---
if (!function_exists('coai_normalize_country')) {
  function coai_normalize_country($c) {
    $c = strtoupper(trim((string)$c));
    $c = str_replace(['.',','], '', $c); // strip punctuation
    $us = ['US','USA','U S','UNITED STATES','UNITED STATES OF AMERICA','U S A'];
    $ca = ['CA','CAN','CANADA'];
    if ($c === '' || in_array($c, $us, true)) return 'US';
    if (in_array($c, $ca, true)) return 'CA';
    return $c;
  }
}

// --- Region lookup via AJAX (for live region preview) ---
if (!function_exists('coai_ajax_region_lookup')) {
  function coai_ajax_region_lookup() {
    // Nonce check (same name you’ll output in the form)
    check_ajax_referer('coai_region_ajax', 'nonce');

    $state   = isset($_POST['state'])   ? strtoupper(trim((string)$_POST['state']))   : '';
    $country = isset($_POST['country']) ? coai_normalize_country($_POST['country'])    : 'US';

    // Make sure the mapper exists
    if (!function_exists('coai_region_from_location')) {
      @require_once plugin_dir_path(__FILE__) . 'includes/region-map.php';
    }

    $region = function_exists('coai_region_from_location')
      ? coai_region_from_location($state, $country)
      : null;

    wp_send_json_success(['region' => $region]);
  }
}

if (!function_exists('coai_ajax_region_lookup_nopriv')) {
  function coai_ajax_region_lookup_nopriv() {
    wp_send_json_error(['message' => 'Not logged in'], 403);
  }
}

add_action('wp_ajax_coai_region_lookup', 'coai_ajax_region_lookup');
add_action('wp_ajax_nopriv_coai_region_lookup', 'coai_ajax_region_lookup_nopriv');



/* =========================================================
 * Shortcodes loader (front-end only)
 * =======================================================*/

/**
 * Member Edit page shortcode
 * Usage: put [coai_member_edit] on the /member-edit/ page.
 * Your directory links should point to: add_query_arg(['mid'=>(int)$id], home_url('/member-edit/'))
 */
add_shortcode('coai_member_edit', function () {
    if (!is_user_logged_in()) {
        return '<div style="color:#b91c1c;">Please log in.</div>';
    }
    // Only Admin/Manager can use the editor
    if (function_exists('coai_staff_can') && !coai_staff_can('manage')) {
        return '<div style="color:#b91c1c;">Access denied.</div>';
    }

    // Prevent caching on edit screen
    if (!headers_sent()) {
        nocache_headers();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    // Reuse the existing Admin/Manager editor (it switches to edit view when ?mid=### is present)
    if (shortcode_exists('coai_members_admin')) {
        return do_shortcode('[coai_members_admin]');
    }

    error_log('COAI: [coai_member_edit] — coai_members_admin shortcode not found');
    return '<div style="color:#b91c1c;">Editor not available.</div>';
});

// Ensure custom roles exist (no caps by default; adjust if needed)
add_action('init', function () {
  if (!get_role('manager')) add_role('manager', 'Manager');
  if (!get_role('finance')) add_role('finance', 'Finance');
  if (!get_role('member'))  add_role('member',  'Member');
}, 5);

/**
 * Map wp_members.usergroup -> WP role on every login (front-end or /wp-login.php)
 * ADMIN    -> administrator
 * MANAGER  -> manager
 * FINANCE  -> finance
 * MEMBER   -> member (or subscriber if you prefer)
 */
add_action('wp_login', function (string $user_login, WP_User $user) {
  // Safety: never auto-demote these protected logins
  $protected_admins = ['admin', 'siteowner']; // <-- put your real super-admin usernames here
  if (in_array(strtolower($user->user_login), array_map('strtolower', $protected_admins), true)) {
    return;
  }

  global $wpdb;
  // Find their member_id and usergroup
  $mid = (int) get_user_meta($user->ID, 'coai_member_id', true);
  if (!$mid) return;

  $table = function_exists('coai_get_members_table')
  ? coai_get_members_table()
  : (defined('COAI_MEMBERS_TABLE') ? COAI_MEMBERS_TABLE : ($wpdb->prefix . 'members'));
  $ug = strtoupper((string) $wpdb->get_var($wpdb->prepare("SELECT usergroup FROM `$table` WHERE member_id=%d", $mid)));

  // Decide WP role
  $map = [
    'ADMIN'   => 'administrator',
    'MANAGER' => 'manager',
    'FINANCE' => 'finance',
    'MEMBER'  => 'member',   // or 'subscriber'
  ];
  $target_role = $map[$ug] ?? null;
  if (!$target_role) return;

  // If already correct, do nothing
  if ($user->roles && in_array($target_role, $user->roles, true)) return;

  // Apply mapped role (this also clears other roles)
  $user->set_role($target_role);
  error_log(sprintf('COAI role-sync: %s set to WP role "%s" (usergroup=%s, mid=%d)', $user->user_login, $target_role, $ug, $mid));
}, 10, 2);

/**
 * Admin page to display the COAI Members Custom changelog.
 * Adds: Tools → COAI Changelog
 */
add_action('admin_menu', 'coai_members_register_changelog_page');

function coai_members_register_changelog_page() {
  add_management_page(
    'COAI Members Changelog',            // Page title
    'COAI Changelog',                    // Menu title
    'manage_options',                    // Capability (Admins only)
    'coai-members-changelog',            // Menu slug
    'coai_members_render_changelog_page' // Callback
  );
}

/**
 * Render the changelog page content.
 */
function coai_members_render_changelog_page() {
  if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to view this page.');
  }

  $changelog_file = plugin_dir_path(__FILE__) . 'docs/CHANGELOG.md';
  ?>
  <div class="wrap">
    <h1>COAI Members Custom — Changelog</h1>

    <?php if (is_readable($changelog_file)) : ?>
      <?php $contents = file_get_contents($changelog_file); ?>

      <p>
        This is the current <code>CHANGELOG.md</code> from the
        <code>coai-members-custom</code> plugin folder.
      </p>

      <div style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:900px;overflow:auto;">
        <pre style="white-space:pre-wrap;word-wrap:break-word;margin:0;"><?php echo esc_html($contents); ?></pre>
      </div>

    <?php else : ?>

      <p><strong>CHANGELOG.md was not found or is not readable.</strong></p>
      <p>
        Expected at:<br>
        <code><?php echo esc_html($changelog_file); ?></code>
      </p>

    <?php endif; ?>
  </div>
  <?php
}


add_action('wp_footer', function () {
    if (!is_page('change-password')) return;
    ?>
    <script>
        // (you can place the same IIFE from above here)
        console.log('coai footer script alive');
    </script>
    <?php
}, 100);
