<?php
if (!defined('ABSPATH')) exit;

/**
 * NOTE:
 * Election tables are intentionally hardcoded to wp_ names.
 * Do not prepend $wpdb->prefix here.
 */
 
/**
 * Election table names
 */
if (!function_exists('coai_election_table')) {
    function coai_election_table($name = '') {
        global $wpdb;
        $map = [
            'elections'   => 'wp_coai_elections',
            'positions'   => 'wp_coai_election_positions',
            'candidates'  => 'wp_coai_election_candidates',
            'votes'       => 'wp_coai_election_votes',
            'vote_items'  => 'wp_coai_election_vote_items',
        ];
        return $map[$name] ?? '';
    }
}

/**
 * Install / update election schema
 */
if (!function_exists('coai_install_election_schema')) {
    function coai_install_election_schema() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $elections  = coai_election_table('elections');
        $positions  = coai_election_table('positions');
        $candidates = coai_election_table('candidates');
        $votes      = coai_election_table('votes');
        $vote_items = coai_election_table('vote_items');

        $sql = [];

        $sql[] = "CREATE TABLE {$elections} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(191) NOT NULL,
            description LONGTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            opens_at DATETIME NULL,
            closes_at DATETIME NULL,
            show_results TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_opens_at (opens_at),
            KEY idx_closes_at (closes_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$positions} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            election_id BIGINT UNSIGNED NOT NULL,
            position_name VARCHAR(191) NOT NULL,
            max_selections INT UNSIGNED NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_election_id (election_id),
            KEY idx_sort_order (sort_order)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$candidates} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            position_id BIGINT UNSIGNED NOT NULL,
            candidate_name VARCHAR(191) NOT NULL,
            candidate_member_id BIGINT UNSIGNED NULL,
            bio LONGTEXT NULL,
            photo_url VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_position_id (position_id),
            KEY idx_member_id (candidate_member_id),
            KEY idx_is_active (is_active)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$votes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            election_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NOT NULL,
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(64) NULL,
            user_agent TEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_election_member (election_id, member_id),
            KEY idx_member_id (member_id),
            KEY idx_submitted_at (submitted_at)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$vote_items} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vote_id BIGINT UNSIGNED NOT NULL,
            position_id BIGINT UNSIGNED NOT NULL,
            candidate_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_vote_id (vote_id),
            KEY idx_position_id (position_id),
            KEY idx_candidate_id (candidate_id)
        ) {$charset_collate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('coai_election_schema_version', '1.0.0');
    }
}

/**
 * Call installer on init if needed
 */
if (!function_exists('coai_maybe_install_election_schema')) {
    function coai_maybe_install_election_schema() {
        $installed = get_option('coai_election_schema_version', '');
        if ($installed !== '1.0.0') {
            coai_install_election_schema();
            error_log('[COAI ELECTION] schema installed/updated');
        }
    }
    add_action('init', 'coai_maybe_install_election_schema', 5);
}

if (!function_exists('coai_get_open_election')) {
    function coai_get_open_election() {
        global $wpdb;
        
        $table = coai_election_table('elections');
        $now   = current_time('mysql');

        // Auto-close expired elections still marked open
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status = 'closed'
                 WHERE status = 'open'
                   AND closes_at IS NOT NULL
                   AND closes_at <> '0000-00-00 00:00:00'
                   AND closes_at <%s",
                 $now
            )
        );
        
        return $wpdb->get_row(
            $wpdb->prepare(
              "SELECT *
               FROM {$table}
               WHERE LOWER(TRIM(status)) = 'open'
                 AND (
                      opens_at IS NULL
                      OR opens_at = '0000-00-00 00:00:00'
                      OR opens_at <= %s
                 )
                 AND (
                      closes_at IS NULL
                      OR closes_at = '0000-00-00 00:00:00'
                      OR closes_at >= %s
                 )
               ORDER BY id DESC
               LIMIT 1",
              $now,
              $now
          ), 
          ARRAY_A
        );
      }
  }

if (!function_exists('coai_get_election')) {
    function coai_get_election($election_id) {
        global $wpdb;
        $table = coai_election_table('elections');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
            (int)$election_id
        ), ARRAY_A);
    }
}

if (!function_exists('coai_get_election_positions')) {
    function coai_get_election_positions($election_id) {
        global $wpdb;
        $table = coai_election_table('positions');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE election_id = %d
             ORDER BY sort_order ASC, id ASC",
            (int)$election_id
        ), ARRAY_A);
    }
}

if (!function_exists('coai_get_position_candidates')) {
    function coai_get_position_candidates($position_id) {
        global $wpdb;
        $table = coai_election_table('candidates');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE position_id = %d
               AND is_active = 1
             ORDER BY sort_order ASC, id ASC",
            (int)$position_id
        ), ARRAY_A);
    }
}

if (!function_exists('coai_member_has_voted')) {
    function coai_member_has_voted($election_id, $member_id) {
        global $wpdb;
        $table = coai_election_table('votes');
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT id
             FROM {$table}
             WHERE election_id = %d
               AND member_id = %d
             LIMIT 1",
            (int)$election_id,
            (int)$member_id
        ));
        return !empty($found);
    }
}