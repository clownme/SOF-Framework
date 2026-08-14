<?php
/**
 * File: includes/audit-log.php
 * Purpose: Append-only audit log for wp_members changes (board-grade trail)
 */

if (!defined('ABSPATH')) exit;

/**
 * Return audit table name (respects wp_ prefix)
 */
if (!function_exists('coai_audit_table')) {
  function coai_audit_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'coai_member_audit';
  }
}

/**
 * Install/upgrade audit table
 */
if (!function_exists('coai_audit_install')) {
  function coai_audit_install(): void {
    global $wpdb;

    $table = coai_audit_table();
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      member_id BIGINT UNSIGNED NOT NULL,
      action VARCHAR(64) NOT NULL DEFAULT 'update',
      note TEXT NULL,

      actor_wp_user_id BIGINT UNSIGNED NULL,
      actor_login VARCHAR(60) NULL,
      actor_roles VARCHAR(255) NULL,

      actor_ip VARCHAR(64) NULL,
      actor_user_agent TEXT NULL,

      request_uri TEXT NULL,
      referrer TEXT NULL,

      changes LONGTEXT NULL,     /* JSON */
      snapshot LONGTEXT NULL,    /* JSON (optional) */

      created_at DATETIME NOT NULL,

      PRIMARY KEY (id),
      KEY member_id (member_id),
      KEY created_at (created_at),
      KEY action (action)
    ) {$charset};";

    dbDelta($sql);
  }
}

/**
 * Hook install to activation (safe even if called multiple times)
 * coai-members-custom.php defines COAI_PLUGIN_FILE early, but guard anyway.
 */
add_action('init', function () {
  if (defined('COAI_PLUGIN_FILE')) {
    // Register only once
    static $done = false;
    if ($done) return;
    $done = true;

    register_activation_hook(COAI_PLUGIN_FILE, 'coai_audit_install');
  }
}, 1);

/**
 * Safe JSON encode for DB storage
 */
if (!function_exists('coai_audit_json')) {
  function coai_audit_json($data): string {
    $json = wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!$json) $json = '{}';
    return $json;
  }
}

/**
 * Redact sensitive keys
 */
if (!function_exists('coai_audit_redact_key')) {
  function coai_audit_redact_key(string $k): bool {
    $k = strtolower($k);
    $deny = [
      'password', 'pass', 'user_pass', 'reset_token', 'token', 'nonce',
      'salt', 'hash', 'secret', 'api_key'
    ];
    return in_array($k, $deny, true);
  }
}

/**
 * Compute a compact diff between before/after associative arrays.
 * Returns: ['field' => ['from' => ..., 'to' => ...], ...]
 */
if (!function_exists('coai_audit_diff')) {
  function coai_audit_diff(array $before, array $after): array {
    $diff = [];

    $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
    foreach ($keys as $k) {
      if (coai_audit_redact_key($k)) continue;

      $b = array_key_exists($k, $before) ? (string)$before[$k] : '';
      $a = array_key_exists($k, $after)  ? (string)$after[$k]  : '';

      // normalize whitespace a bit
      $bN = trim($b);
      $aN = trim($a);

      if ($bN === $aN) continue;

      // Special handling: internal_comments can be huge — store “appended tail” if applicable
      if (strtolower($k) === 'internal_comments') {
        $tail = '';
        if ($bN !== '' && strpos($aN, $bN) === 0) {
          $tail = trim(substr($aN, strlen($bN)));
        } else {
          $tail = $aN;
        }
        // cap to keep audit rows reasonable
        if (strlen($tail) > 800) $tail = substr($tail, 0, 800) . '…(truncated)';
        $diff[$k] = [
          'from_len' => strlen($bN),
          'to_len'   => strlen($aN),
          'delta'    => $tail,
        ];
        continue;
      }

      // Default
      $diff[$k] = [
        'from' => (strlen($bN) > 300 ? substr($bN, 0, 300) . '…' : $bN),
        'to'   => (strlen($aN) > 300 ? substr($aN, 0, 300) . '…' : $aN),
      ];
    }

    return $diff;
  }
}

/**
 * Write an audit entry (append-only).
 * - changes: array diff or structured payload (will be JSON)
 * - snapshot: optional full row snapshot (JSON)
 */
if (!function_exists('coai_audit_log')) {
  function coai_audit_log(int $member_id, string $action, $changes = null, string $note = '', $snapshot = null): bool {
    global $wpdb;

    $table = coai_audit_table();

    $u = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
    $uid = ($u && !empty($u->ID)) ? (int)$u->ID : null;

    $login = ($u && !empty($u->user_login)) ? (string)$u->user_login : null;
    $roles = ($u && !empty($u->roles)) ? implode(',', array_map('strtolower', (array)$u->roles)) : null;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $uri = $_SERVER['REQUEST_URI'] ?? null;
    $ref = $_SERVER['HTTP_REFERER'] ?? null;

    $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');

    $row = [
      'member_id'        => (int)$member_id,
      'action'           => sanitize_key($action),
      'note'             => $note ? wp_strip_all_tags($note) : null,

      'actor_wp_user_id' => $uid,
      'actor_login'      => $login,
      'actor_roles'      => $roles,

      'actor_ip'         => $ip ? substr((string)$ip, 0, 64) : null,
      'actor_user_agent' => $ua ? (string)$ua : null,

      'request_uri'      => $uri ? (string)$uri : null,
      'referrer'         => $ref ? (string)$ref : null,

      'changes'          => is_null($changes) ? null : coai_audit_json($changes),
      'snapshot'         => is_null($snapshot) ? null : coai_audit_json($snapshot),

      'created_at'       => $now,
    ];

    $format = [
      '%d','%s','%s',
      '%d','%s','%s',
      '%s','%s',
      '%s','%s',
      '%s','%s',
      '%s'
    ];

    $ok = $wpdb->insert($table, $row, $format);

    if (!$ok) {
      error_log('[COAI][AUDIT] insert failed: ' . $wpdb->last_error);
      return false;
    }
    return true;
  }
}

/**
 * Helper: log update by diffing before/after.
 */
if (!function_exists('coai_audit_log_update')) {
  function coai_audit_log_update(int $member_id, array $before, array $after, string $note = ''): bool {
    $diff = coai_audit_diff($before, $after);
    if (empty($diff)) return true; // no changes to log
    return coai_audit_log($member_id, 'update', $diff, $note);
  }
}

/**
 * Ensure audit table exists (self-healing).
 * Runs once on normal page load if table was never created.
 */
add_action('init', function () {
  if (!function_exists('coai_audit_install')) return;

  $flag = 'coai_audit_table_installed_v1';
  if (get_option($flag)) return;

  coai_audit_install();
  update_option($flag, 1, false);

  error_log('[COAI][AUDIT] ensured audit table exists: ' . coai_audit_table());
}, 2);
