<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [coai_staff_archived_search]
 * Staff tool to search archived members in wp_members.
 */

add_shortcode('coai_staff_archived_search', function () {

  if (!is_user_logged_in()) {
    wp_safe_redirect(home_url('/member-login/?login=required'));
    exit;
  }

  if (!function_exists('coai_staff_can') || !coai_staff_can('manage')) {
    return '<div style="max-width:900px;margin:20px auto;padding:12px;border:1px solid #fecaca;background:#fee2e2;border-radius:10px;">
      Access denied.
    </div>';
  }

  global $wpdb;

  // Members table
  $table = defined('COAI_MEMBERS_TABLE')
    ? COAI_MEMBERS_TABLE
    : $wpdb->prefix . 'members';

  $q = isset($_GET['q']) ? trim((string) wp_unslash($_GET['q'])) : '';

  $rows = [];

  if ($q !== '') {

    $like = '%' . $wpdb->esc_like($q) . '%';

    $sql = "
      SELECT
        member_id,
        COAI_number,
        first_name,
        last_name,
        full_name,
        clown_name,
        username,
        email,
        status
      FROM {$table}
      WHERE UPPER(TRIM(status)) = 'ARCHIVED'
        AND (
          COAI_number LIKE %s
          OR first_name LIKE %s
          OR last_name LIKE %s
          OR full_name LIKE %s
          OR clown_name LIKE %s
          OR username LIKE %s
          OR email LIKE %s
        )
      ORDER BY last_name ASC, first_name ASC
      LIMIT 200
    ";

    $rows = $wpdb->get_results(
      $wpdb->prepare($sql,
        $like, $like, $like, $like, $like, $like, $like
      ),
      ARRAY_A
    );
  }

  ob_start();
?>

<div class="coai-wrap" style="max-width:1100px;margin:20px auto;">

  <div class="coai-toolbar" style="margin-bottom:10px;">
    <a class="coai-btn" href="<?php echo esc_url(home_url('/member-portal/')); ?>">
      ← Member Portal
    </a>
  </div>

  <h2 style="margin:0 0 10px;">Archived Member Search</h2>

  <form method="get" style="display:flex;gap:10px;margin-bottom:15px;">
    <input
      type="text"
      name="q"
      value="<?php echo esc_attr($q); ?>"
      placeholder="Search archived by name, COAI #, username, or email"
      style="flex:1;padding:.55rem .65rem;border:1px solid #d1d5db;border-radius:8px;"
    />

    <button class="button button-primary" type="submit">
      Search
    </button>

    <?php if ($q !== '') : ?>
      <a class="button" href="<?php echo esc_url(remove_query_arg('q')); ?>">
        Clear
      </a>
    <?php endif; ?>
  </form>


<?php if ($q === '') : ?>

  <div style="padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
    Enter a search term to find archived members.
  </div>

<?php else : ?>

  <div style="margin-bottom:10px;">
    Results: <strong><?php echo count($rows); ?></strong>
  </div>

  <div style="overflow:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">

  <table class="widefat striped" style="width:100%;min-width:900px;">
    <thead>
      <tr>
        <th>ID</th>
        <th>COAI #</th>
        <th>Name</th>
        <th>Clown Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>

    <tbody>

<?php if (empty($rows)) : ?>

  <tr>
    <td colspan="8" style="padding:10px;">
      No archived members found for "<?php echo esc_html($q); ?>"
    </td>
  </tr>

<?php else : ?>

<?php foreach ($rows as $r) :

  $mid   = (int) ($r['member_id'] ?? 0);
  $coai  = (string) ($r['COAI_number'] ?? '');
  $email = (string) ($r['email'] ?? '');
  $user  = (string) ($r['username'] ?? '');
  $clown = (string) ($r['clown_name'] ?? '');
  $st    = (string) ($r['status'] ?? '');

  $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
  if ($name === '') {
    $name = $r['full_name'] ?? '(no name)';
  }

  $edit_url = home_url('/member-edit/?mid=' . $mid);

?>

<tr>

  <td><?php echo $mid; ?></td>

  <td><?php echo esc_html($coai); ?></td>

  <td><?php echo esc_html($name); ?></td>

  <td><?php echo esc_html($clown); ?></td>

  <td><?php echo esc_html($user); ?></td>

  <td><?php echo esc_html($email); ?></td>

  <td><?php echo esc_html($st); ?></td>

  <td>
    <a class="button button-small"
       href="<?php echo esc_url($edit_url); ?>">
       Open
    </a>
  </td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

    </tbody>
  </table>

  </div>

<?php endif; ?>

</div>

<?php

  return ob_get_clean();

});