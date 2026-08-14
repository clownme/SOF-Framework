/**
 * Shared COAI numbering (INSERT-ONLY)
 * Safe: only assigns if COAI_number is blank.
 */
function coai_coai_prefix(): string {
  return wp_date('Ym');
}

function coai_get_month_max_seq(string $ym): int {
  global $wpdb;
  $m = (function_exists('coai_get_members_table') ? coai_get_members_table() : $wpdb->prefix.'members');
  $like = $wpdb->esc_like($ym . '-') . '%';

  $sql = "SELECT MAX(CAST(SUBSTRING_INDEX(COAI_number, '-', -1) AS UNSIGNED))
          FROM `{$m}`
          WHERE COAI_number LIKE %s";
  return (int)$wpdb->get_var($wpdb->prepare($sql, $like));
}

function coai_assign_next_coai_number(int $member_id): array {
  global $wpdb;
  $m = (function_exists('coai_get_members_table') ? coai_get_members_table() : $wpdb->prefix.'members');

  // Only assign if still blank
  $current = $wpdb->get_var($wpdb->prepare("SELECT COAI_number FROM `{$m}` WHERE member_id=%d", $member_id));
  if (!empty($current) && trim((string)$current) !== '') {
    return ['ok'=>true, 'assigned'=>false, 'coai'=>$current];
  }

  $ym  = coai_coai_prefix();
  $seq = coai_get_month_max_seq($ym);

  for ($try = 0; $try < 50; $try++) {
    $seq++;
    $candidate = sprintf('%s-%03d', $ym, $seq);

    $ok = $wpdb->query($wpdb->prepare("
      UPDATE `{$m}`
      SET COAI_number = %s
      WHERE member_id = %d
        AND (COAI_number IS NULL OR TRIM(COAI_number) = '')
    ", $candidate, $member_id));

    if ($ok === 1) {
      return ['ok'=>true, 'assigned'=>true, 'coai'=>$candidate];
    }

    // If there's a UNIQUE index on COAI_number, collision will show here; try next
    if (!empty($wpdb->last_error) && (strpos($wpdb->last_error, 'Duplicate') !== false || strpos($wpdb->last_error, '1062') !== false)) {
      continue;
    }

    if (!empty($wpdb->last_error)) {
      error_log('[COAI] coai_assign_next_coai_number error: '.$wpdb->last_error.' (member_id='.$member_id.')');
      return ['ok'=>false, 'assigned'=>false, 'coai'=>null, 'error'=>$wpdb->last_error];
    }

    // If update didn’t affect a row, it likely got filled by someone else; re-check
    $current2 = $wpdb->get_var($wpdb->prepare("SELECT COAI_number FROM `{$m}` WHERE member_id=%d", $member_id));
    if (!empty($current2)) {
      return ['ok'=>true, 'assigned'=>false, 'coai'=>$current2];
    }
  }

  return ['ok'=>false, 'assigned'=>false, 'coai'=>null, 'error'=>'Could not assign COAI number after multiple attempts'];
}
