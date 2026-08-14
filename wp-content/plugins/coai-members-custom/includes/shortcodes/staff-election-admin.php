<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('coai_render_staff_election_admin_shortcode')) {
    function coai_render_staff_election_admin_shortcode() {
        if (!is_user_logged_in() || !coai_user_can_manage_elections()) {
            return '<div class="notice notice-error"><p>Access denied.</p></div>';
        }
        
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        wp_enqueue_media();

        global $wpdb;

        $elections_table  = coai_election_table('elections');
        $positions_table  = coai_election_table('positions');
        $candidates_table = coai_election_table('candidates');
        $votes_table      = coai_election_table('votes');
        $members_table    = 'wp_members';

        $msg = '';

        // -------------------------------------------------
        // Create election
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_create_election'])) {
            if (
                empty($_POST['_coai_create_election_nonce']) ||
                !wp_verify_nonce($_POST['_coai_create_election_nonce'], 'coai_create_election')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad election nonce.</p></div>';
            } else {
                $title        = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
                $description  = wp_kses_post(wp_unslash($_POST['description'] ?? ''));
                $status       = sanitize_text_field(wp_unslash($_POST['status'] ?? 'draft'));
                $opens_at     = sanitize_text_field(wp_unslash($_POST['opens_at'] ?? ''));
                $closes_at    = sanitize_text_field(wp_unslash($_POST['closes_at'] ?? ''));
                $show_results = !empty($_POST['show_results']) ? 1 : 0;

                if ($title === '') {
                    $msg .= '<div class="notice notice-error"><p>Election title is required.</p></div>';
                } else {
                    $ok = $wpdb->insert(
                        $elections_table,
                        [
                            'title'        => $title,
                            'description'  => $description,
                            'status'       => in_array($status, ['draft', 'open', 'closed', 'archived'], true) ? $status : 'draft',
                            'opens_at'     => ($opens_at !== '' ? $opens_at : null),
                            'closes_at'    => ($closes_at !== '' ? $closes_at : null),
                            'show_results' => $show_results,
                            'created_by'   => get_current_user_id(),
                            'created_at'   => current_time('mysql'),
                        ],
                        ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
                    );

                    $msg .= $ok
                        ? '<div class="notice notice-success"><p>Election created.</p></div>'
                        : '<div class="notice notice-error"><p>Could not create election.</p></div>';
                }
            }
        }

        // -------------------------------------------------
        // Add position
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_add_position'])) {
            if (
                empty($_POST['_coai_add_position_nonce']) ||
                !wp_verify_nonce($_POST['_coai_add_position_nonce'], 'coai_add_position')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad position nonce.</p></div>';
            } else {
                $election_id    = (int)($_POST['election_id'] ?? 0);
                $group_name     = sanitize_text_field(wp_unslash($_POST['group_name'] ?? ''));
                $position_name  = sanitize_text_field(wp_unslash($_POST['position_name'] ?? ''));
                $max_selections = max(1, (int)($_POST['max_selections'] ?? 1));
                $sort_order     = (int)($_POST['sort_order'] ?? 0);

                if ($election_id <= 0 || $position_name === '') {
                    $msg .= '<div class="notice notice-error"><p>Election and position name are required.</p></div>';
                } else {
                    $ok = $wpdb->insert(
                        $positions_table,
                        [
                            'election_id'    => $election_id,
                            'group_name'     => $group_name,
                            'position_name'  => $position_name,
                            'max_selections' => $max_selections,
                            'sort_order'     => $sort_order,
                        ],
                        ['%d', '%s', '%s', '%d', '%d']
                    );

                    $msg .= $ok
                        ? '<div class="notice notice-success"><p>Position added.</p></div>'
                        : '<div class="notice notice-error"><p>Could not add position.</p></div>';
                }
            }
        }

        // -------------------------------------------------
        // Update position
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_update_position'])) {
            if (
                empty($_POST['_coai_update_position_nonce']) ||
                !wp_verify_nonce($_POST['_coai_update_position_nonce'], 'coai_update_position')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad position update nonce.</p></div>';
            } else {
                $position_id    = (int)($_POST['position_id'] ?? 0);
                $group_name     = sanitize_text_field(wp_unslash($_POST['group_name'] ?? ''));
                $position_name  = sanitize_text_field(wp_unslash($_POST['position_name'] ?? ''));
                $max_selections = max(1, (int)($_POST['max_selections'] ?? 1));
                $sort_order     = (int)($_POST['sort_order'] ?? 0);

                if ($position_id <= 0 || $position_name === '') {
                    $msg .= '<div class="notice notice-error"><p>Position ID and position name are required.</p></div>';
                } else {
                    $ok = $wpdb->update(
                        $positions_table,
                        [
                            'group_name'     => $group_name,
                            'position_name'  => $position_name,
                            'max_selections' => $max_selections,
                            'sort_order'     => $sort_order,
                        ],
                        ['id' => $position_id],
                        ['%s', '%s', '%d', '%d'],
                        ['%d']
                    );

                    $msg .= ($ok !== false)
                        ? '<div class="notice notice-success"><p>Position updated.</p></div>'
                        : '<div class="notice notice-error"><p>Could not update position.</p></div>';
                }
            }
        }

        // -------------------------------------------------
        // Add candidate
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_add_candidate'])) {
            if (
                empty($_POST['_coai_add_candidate_nonce']) ||
                !wp_verify_nonce($_POST['_coai_add_candidate_nonce'], 'coai_add_candidate')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad candidate nonce.</p></div>';
            } else {
                $position_id    = (int)($_POST['position_id'] ?? 0);
                $candidate_name = sanitize_text_field(wp_unslash($_POST['candidate_name'] ?? ''));
                $member_id      = (int)($_POST['candidate_member_id'] ?? 0);
                $bio            = wp_kses_post(wp_unslash($_POST['bio'] ?? ''));
                $photo_url      = esc_url_raw(wp_unslash($_POST['photo_url'] ?? ''));
                $sort_order     = (int)($_POST['sort_order'] ?? 0);

                if ($position_id <= 0 || $candidate_name === '') {
                    $msg .= '<div class="notice notice-error"><p>Position and candidate name are required.</p></div>';
                } else {
                    $ok = $wpdb->insert(
                        $candidates_table,
                        [
                            'position_id'         => $position_id,
                            'candidate_name'      => $candidate_name,
                            'candidate_member_id' => ($member_id > 0 ? $member_id : null),
                            'bio'                 => $bio,
                            'photo_url'           => $photo_url,
                            'sort_order'          => $sort_order,
                            'is_active'           => 1,
                        ],
                        ['%d', '%s', '%d', '%s', '%s', '%d', '%d']
                    );

                    $msg .= $ok
                        ? '<div class="notice notice-success"><p>Candidate added.</p></div>'
                        : '<div class="notice notice-error"><p>Could not add candidate.</p></div>';
                }
            }
        }

        // -------------------------------------------------
        // Update candidate
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_update_candidate'])) {
            if (
                empty($_POST['_coai_update_candidate_nonce']) ||
                !wp_verify_nonce($_POST['_coai_update_candidate_nonce'], 'coai_update_candidate')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad candidate update nonce.</p></div>';
            } else {
                $candidate_id   = (int)($_POST['candidate_id'] ?? 0);
                $position_id    = (int)($_POST['position_id'] ?? 0);
                $candidate_name = sanitize_text_field(wp_unslash($_POST['candidate_name'] ?? ''));
                $member_id      = (int)($_POST['candidate_member_id'] ?? 0);
                $bio            = wp_kses_post(wp_unslash($_POST['bio'] ?? ''));
                $photo_url      = esc_url_raw(wp_unslash($_POST['photo_url'] ?? ''));
                $sort_order     = (int)($_POST['sort_order'] ?? 0);
                $is_active      = !empty($_POST['is_active']) ? 1 : 0;

                if ($candidate_id <= 0 || $position_id <= 0 || $candidate_name === '') {
                    $msg .= '<div class="notice notice-error"><p>Candidate ID, position, and candidate name are required.</p></div>';
                } else {
                    $ok = $wpdb->update(
                        $candidates_table,
                        [
                            'position_id'         => $position_id,
                            'candidate_name'      => $candidate_name,
                            'candidate_member_id' => ($member_id > 0 ? $member_id : null),
                            'bio'                 => $bio,
                            'photo_url'           => $photo_url,
                            'sort_order'          => $sort_order,
                            'is_active'           => $is_active,
                        ],
                        ['id' => $candidate_id],
                        ['%d', '%s', '%d', '%s', '%s', '%d', '%d'],
                        ['%d']
                    );

                    $msg .= ($ok !== false)
                        ? '<div class="notice notice-success"><p>Candidate updated.</p></div>'
                        : '<div class="notice notice-error"><p>Could not update candidate.</p></div>';
                }
            }
        }

        // -------------------------------------------------
        // Remove / delete candidate
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_remove_candidate'])) {
            if (
                empty($_POST['_coai_remove_candidate_nonce']) ||
                !wp_verify_nonce($_POST['_coai_remove_candidate_nonce'], 'coai_remove_candidate')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad candidate remove nonce.</p></div>';
            } else {
                $candidate_id = (int)($_POST['candidate_id'] ?? 0);

                if ($candidate_id <= 0) {
                    $msg .= '<div class="notice notice-error"><p>Invalid candidate ID.</p></div>';
                } else {
                    $vote_items_table = coai_election_table('vote_items');

                    $vote_count = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$vote_items_table} WHERE candidate_id = %d",
                            $candidate_id
                        )
                    );

                    if ($vote_count > 0) {
                        // Candidate already has votes: deactivate only
                        $ok = $wpdb->update(
                            $candidates_table,
                            ['is_active' => 0],
                            ['id' => $candidate_id],
                            ['%d'],
                            ['%d']
                        );

                        $msg .= ($ok !== false)
                            ? '<div class="notice notice-success"><p>Candidate removed from ballot. Existing vote history was preserved.</p></div>'
                            : '<div class="notice notice-error"><p>Could not remove candidate from ballot.</p></div>';
                    } else {
                        // No votes yet: safe to delete
                        $ok = $wpdb->delete(
                            $candidates_table,
                            ['id' => $candidate_id],
                            ['%d']
                        );

                        $msg .= $ok
                            ? '<div class="notice notice-success"><p>Candidate deleted.</p></div>'
                            : '<div class="notice notice-error"><p>Could not delete candidate.</p></div>';
                    }
                }
            }
        }
        
        // -------------------------------------------------
        // Manual ballot entry
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_manual_ballot_entry'])) {
            if (
                empty($_POST['_coai_manual_ballot_entry_nonce']) ||
                !wp_verify_nonce($_POST['_coai_manual_ballot_entry_nonce'], 'coai_manual_ballot_entry')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad manual ballot entry nonce.</p></div>';
            } else {
                $manual_vote_election_id = (int) ($_POST['manual_vote_election_id'] ?? 0);
                $manual_vote_member_id   = (int) ($_POST['manual_vote_member_id'] ?? 0);
                $manual_vote_method      = sanitize_text_field(wp_unslash($_POST['manual_vote_method'] ?? 'mail'));
                $manual_vote_note        = sanitize_textarea_field(wp_unslash($_POST['manual_vote_note'] ?? ''));

                $allowed_methods = ['mail', 'email', 'admin-entered'];
                if (!in_array($manual_vote_method, $allowed_methods, true)) {
                    $manual_vote_method = 'mail';
                }

                if ($manual_vote_election_id <= 0 || $manual_vote_member_id <= 0) {
                    $msg .= '<div class="notice notice-error"><p>Election ID and Member ID are required for manual ballot entry.</p></div>';
                } else {
                    $already_exists = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$votes_table} WHERE election_id = %d AND member_id = %d",
                            $manual_vote_election_id,
                            $manual_vote_member_id
                        )
                    );

                    if ($already_exists > 0) {
                        $msg .= '<div class="notice notice-warning"><p>That member is already marked as having voted in this election.</p></div>';
                    } else {
                        list($eligible_for_vote, $eligibility_reason) = coai_member_is_voting_eligible($manual_vote_member_id);

                        if (!$eligible_for_vote) {
                            $msg .= '<div class="notice notice-error"><p>' . esc_html($eligibility_reason) . '</p></div>';
                        } else {
                            $manual_positions = coai_get_election_positions($manual_vote_election_id);

                            if (empty($manual_positions)) {
                                $msg .= '<div class="notice notice-error"><p>This election does not have any ballot positions configured yet.</p></div>';
                            } else {
                                $selected_map = [];

                                foreach ($manual_positions as $position) {
                                    $group_name = trim((string) ($position['group_name'] ?? ''));

                                    if (!function_exists('coai_group_is_voteable') || !coai_group_is_voteable($group_name)) {
                                        continue;
                                    }

                                    $position_id = (int) ($position['id'] ?? 0);
                                    if ($position_id <= 0) {
                                        continue;
                                    }

                                    $field_key = 'manual_position_' . $position_id;
                                    $candidate_id = isset($_POST[$field_key]) ? (int) $_POST[$field_key] : 0;

                                    if ($candidate_id <= 0) {
                                        continue;
                                    }

                                    $valid_ids = array_map(
                                        static function($c) { return (int) $c['id']; },
                                        coai_get_position_candidates($position_id)
                                    );

                                    if (!in_array($candidate_id, $valid_ids, true)) {
                                        $msg .= '<div class="notice notice-error"><p>One of the selected manual ballot choices was invalid.</p></div>';
                                        $selected_map = [];
                                        break;
                                    }

                                    $selected_map[$position_id] = $candidate_id;
                                }

                                if ($msg === '' || strpos($msg, 'invalid') === false) {
                                    $vote_items_table = coai_election_table('vote_items');

                                    $wpdb->query('START TRANSACTION');

                                    try {
                                        $insert_vote = $wpdb->insert(
                                            $votes_table,
                                            [
                                                'election_id'        => $manual_vote_election_id,
                                                'member_id'          => $manual_vote_member_id,
                                                'submitted_at'       => current_time('mysql'),
                                                'submission_method'  => $manual_vote_method,
                                                'entered_by_user_id' => get_current_user_id(),
                                                'admin_note'         => $manual_vote_note,
                                                'ip_address'         => '',
                                                'user_agent'         => 'manual-ballot-entry',
                                            ],
                                            ['%d','%d','%s','%s','%d','%s','%s','%s']
                                        );

                                        if (!$insert_vote) {
                                            throw new Exception('Could not save manual vote header.');
                                        }

                                        $vote_id = (int) $wpdb->insert_id;
                                        if ($vote_id <= 0) {
                                            throw new Exception('Missing manual vote ID.');
                                        }

                                        foreach ($selected_map as $position_id => $candidate_id) {
                                            $ok = $wpdb->insert(
                                                $vote_items_table,
                                                [
                                                    'vote_id'      => $vote_id,
                                                    'position_id'  => (int) $position_id,
                                                    'candidate_id' => (int) $candidate_id,
                                                ],
                                                ['%d','%d','%d']
                                            );

                                            if (!$ok) {
                                                throw new Exception('Could not save manual vote item.');
                                            }
                                        }

                                        $wpdb->query('COMMIT');
                                        $msg .= '<div class="notice notice-success"><p>Manual ballot entered successfully.</p></div>';

                                    } catch (Throwable $e) {
                                        $wpdb->query('ROLLBACK');
                                        error_log('[COAI ELECTION] manual ballot entry failed: ' . $e->getMessage());
                                        $msg .= '<div class="notice notice-error"><p>Could not save manual ballot entry.</p></div>';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // -------------------------------------------------
        // Remove ballot
        // -------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['coai_remove_ballot'])) {
            if (
                empty($_POST['_coai_remove_ballot_nonce']) ||
                !wp_verify_nonce($_POST['_coai_remove_ballot_nonce'], 'coai_remove_ballot')
            ) {
                $msg .= '<div class="notice notice-error"><p>Bad remove ballot nonce.</p></div>';
            } else {
                $remove_election_id = (int) ($_POST['remove_election_id'] ?? 0);
                $remove_member_id   = (int) ($_POST['remove_member_id'] ?? 0);

                if ($remove_election_id <= 0 || $remove_member_id <= 0) {
                    $msg .= '<div class="notice notice-error"><p>Election ID and Member ID are required to remove a ballot.</p></div>';
                } else {
                    $vote_items_table = coai_election_table('vote_items');

                    $vote_row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$votes_table}
                             WHERE election_id = %d AND member_id = %d
                             ORDER BY submitted_at DESC, id DESC
                             LIMIT 1",
                            $remove_election_id,
                            $remove_member_id
                        ),
                        ARRAY_A
                    );

                    if (empty($vote_row) || empty($vote_row['id'])) {
                        $msg .= '<div class="notice notice-warning"><p>No ballot was found for that member in the selected election.</p></div>';
                    } else {
                        $vote_id = (int) $vote_row['id'];

                        $wpdb->query('START TRANSACTION');

                        try {
                            $delete_items = $wpdb->delete(
                                $vote_items_table,
                                ['vote_id' => $vote_id],
                                ['%d']
                            );

                            if ($delete_items === false) {
                                throw new Exception('Could not delete vote items.');
                            }

                            $delete_vote = $wpdb->delete(
                                $votes_table,
                                ['id' => $vote_id],
                                ['%d']
                            );

                            if ($delete_vote === false || $delete_vote < 1) {
                                throw new Exception('Could not delete vote header.');
                            }

                            $wpdb->query('COMMIT');
                            $msg .= '<div class="notice notice-success"><p>Ballot removed successfully. This member is now marked as Not Voted for this election.</p></div>';

                        } catch (Throwable $e) {
                            $wpdb->query('ROLLBACK');
                            error_log('[COAI ELECTION] remove ballot failed: ' . $e->getMessage());
                            $msg .= '<div class="notice notice-error"><p>Could not remove ballot.</p></div>';
                        }
                    }
                }
            }
        }
        
        // -------------------------------------------------
        // Print blank ballot
        // -------------------------------------------------
        if (isset($_GET['coai_print_blank_ballot']) && (int) $_GET['coai_print_blank_ballot'] === 1) {
            $print_election_id = isset($_GET['coai_report_election_id']) ? (int) $_GET['coai_report_election_id'] : 0;
            $print_member_id   = isset($_GET['print_member_id']) ? (int) $_GET['print_member_id'] : 0;

            if ($print_election_id > 0) {
                $print_election = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$elections_table} WHERE id = %d LIMIT 1",
                        $print_election_id
                    ),
                    ARRAY_A
                );

                $print_positions = coai_get_election_positions($print_election_id);

                $print_member = null;
                if ($print_member_id > 0) {
                    $print_member = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT member_id, first_name, last_name, full_name, COAI_number
                             FROM {$members_table}
                             WHERE member_id = %d
                             LIMIT 1",
                            $print_member_id
                        ),
                        ARRAY_A
                    );
                }

                $grouped_print_positions = function_exists('coai_group_positions_for_ballot')
                    ? coai_group_positions_for_ballot($print_positions)
                    : ['' => $print_positions];

                ob_start();
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title><?php echo esc_html(($print_election['title'] ?? 'Election') . ' - Blank Ballot'); ?></title>
                    <style>
                        body{
                            font-family: Arial, sans-serif;
                            color:#111;
                            margin:24px;
                            line-height:1.35;
                        }
                        .coai-print-wrap{
                            max-width:900px;
                            margin:0 auto;
                        }
                        .coai-print-header{
                            margin-bottom:20px;
                            border-bottom:2px solid #000;
                            padding-bottom:12px;
                        }
                        .coai-print-title{
                            font-size:28px;
                            font-weight:700;
                            margin:0 0 8px;
                        }
                        .coai-print-meta{
                            margin:4px 0;
                            font-size:15px;
                        }
                        .coai-print-voter{
                            margin:18px 0 22px;
                            padding:14px;
                            border:2px solid #000;
                        }
                        .coai-print-voter-row{
                            margin:8px 0;
                            font-size:16px;
                        }
                        .coai-print-group{
                            margin:24px 0 18px;
                        }
                        .coai-print-group-title{
                            font-size:20px;
                            font-weight:700;
                            text-transform:uppercase;
                            border-bottom:1px solid #000;
                            padding-bottom:4px;
                            margin:0 0 12px;
                        }
                        .coai-print-position{
                            margin:0 0 18px;
                            padding:0 0 10px;
                            border-bottom:1px dashed #999;
                        }
                        .coai-print-position-title{
                            font-size:18px;
                            font-weight:700;
                            margin:0 0 8px;
                        }
                        .coai-print-candidate{
                            margin:8px 0;
                            font-size:16px;
                        }
                        .coai-print-circle{
                            display:inline-block;
                            width:16px;
                            height:16px;
                            border:2px solid #000;
                            border-radius:50%;
                            margin-right:10px;
                            vertical-align:middle;
                        }
                        .coai-print-note{
                            margin:24px 0 0;
                            padding:12px;
                            border:1px solid #000;
                            font-size:14px;
                        }
                        .no-print{
                            margin:0 0 20px;
                        }
                        @media print{
                            .no-print{
                                display:none;
                            }
                            body{
                                margin:0.35in;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="coai-print-wrap">
                        <div class="no-print">
                            <button onclick="window.print();">Print Ballot</button>
                            <button onclick="window.close();">Close</button>
                        </div>

                        <div class="coai-print-header">
                            <div class="coai-print-title"><?php echo esc_html($print_election['title'] ?? 'Election Ballot'); ?></div>
                            <?php if (!empty($print_election['description'])): ?>
                                <div class="coai-print-meta"><?php echo wp_kses_post(wpautop($print_election['description'])); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="coai-print-voter">
                            <?php
                            $print_name = '';
                            $print_coai = '';

                            if (!empty($print_member)) {
                                $print_name = trim((string) ($print_member['full_name'] ?? ''));
                                if ($print_name === '') {
                                    $print_name = trim((string) ($print_member['first_name'] ?? '') . ' ' . (string) ($print_member['last_name'] ?? ''));
                                }
                                $print_name = preg_replace('/\s+/', ' ', $print_name);
                                $print_coai = trim((string) ($print_member['COAI_number'] ?? ''));
                            }
                            ?>
                            <div class="coai-print-voter-row"><strong>Voter Name:</strong> <?php echo esc_html($print_name !== '' ? $print_name : '____________________________________________'); ?></div>
                            <div class="coai-print-voter-row"><strong>COAI #:</strong> <?php echo esc_html($print_coai !== '' ? $print_coai : '________________________'); ?></div>
                            <div class="coai-print-voter-row"><strong>Member ID:</strong> <?php echo $print_member_id > 0 ? (int) $print_member_id : '________________'; ?></div>
                        </div>

                        <?php foreach ($grouped_print_positions as $group_name => $group_positions): ?>
                            <?php
                            $group_is_voteable = function_exists('coai_group_is_voteable')
                                ? coai_group_is_voteable($group_name)
                                : true;
                            ?>
                            <?php if (!$group_is_voteable) continue; ?>

                            <div class="coai-print-group">
                                <?php if ($group_name !== ''): ?>
                                    <div class="coai-print-group-title"><?php echo esc_html($group_name); ?></div>
                                <?php endif; ?>

                                <?php foreach ($group_positions as $position): ?>
                                    <?php
                                    $position_id = (int) ($position['id'] ?? 0);
                                    if ($position_id <= 0) continue;

                                    $candidates = coai_get_position_candidates($position_id);
                                    ?>
                                    <div class="coai-print-position">
                                        <div class="coai-print-position-title"><?php echo esc_html($position['position_name']); ?></div>

                                        <?php foreach ($candidates as $candidate): ?>
                                            <div class="coai-print-candidate">
                                                <span class="coai-print-circle"></span>
                                                <?php echo esc_html($candidate['candidate_name']); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="coai-print-note">
                            <strong>Return Instructions:</strong><br>
                            Complete this ballot and return it according to the election instructions. Staff will enter approved mailed ballots into the election system.
                        </div>
                    </div>
                </body>
                </html>
                <?php
                echo ob_get_clean();
                exit;
            }
        }

        // -------------------------------------------------
        // Load data
        // -------------------------------------------------
        $elections = $wpdb->get_results(
            "SELECT * FROM {$elections_table} ORDER BY id DESC",
            ARRAY_A
        );

        $all_positions = $wpdb->get_results(
            "SELECT p.*, e.title AS election_title
             FROM {$positions_table} p
             INNER JOIN {$elections_table} e ON e.id = p.election_id
             ORDER BY
               e.id DESC,
               CASE p.group_name
                 WHEN 'Executive Committee' THEN 1
                 WHEN 'EXECUTIVE COMMITTEE' THEN 1
                 WHEN 'Directors' THEN 2
                 WHEN 'DIRECTORS' THEN 2
                 WHEN 'Regional Vice Presidents' THEN 3
                 WHEN 'REGIONAL VICE PRESIDENTS (RVPS)' THEN 3
                 WHEN 'Appointees' THEN 4
                 WHEN 'APPOINTEES' THEN 4
                 ELSE 99
               END,
               p.sort_order ASC,
               p.id ASC",
            ARRAY_A
        );

        $all_candidates = $wpdb->get_results(
            "SELECT c.*, p.position_name, p.group_name, e.title AS election_title
             FROM {$candidates_table} c
             INNER JOIN {$positions_table} p ON p.id = c.position_id
             INNER JOIN {$elections_table} e ON e.id = p.election_id
             ORDER BY
               e.id DESC,
               CASE p.group_name
                 WHEN 'Executive Committee' THEN 1
                 WHEN 'EXECUTIVE COMMITTEE' THEN 1
                 WHEN 'Directors' THEN 2
                 WHEN 'DIRECTORS' THEN 2
                 WHEN 'Regional Vice Presidents' THEN 3
                 WHEN 'REGIONAL VICE PRESIDENTS (RVPS)' THEN 3
                 WHEN 'Appointees' THEN 4
                 WHEN 'APPOINTEES' THEN 4
                 ELSE 99
               END,
               p.sort_order ASC,
               c.sort_order ASC,
               c.id ASC",
            ARRAY_A
        );
        
        // -------------------------------------------------
        // Voting participation report data
        // -------------------------------------------------
        $report_election_id = isset($_GET['coai_report_election_id']) ? (int) $_GET['coai_report_election_id'] : 0;
        if ($report_election_id <= 0 && !empty($elections)) {
            $report_election_id = (int) $elections[0]['id'];
        }

        $report_status = isset($_GET['coai_vote_status']) ? sanitize_text_field(wp_unslash($_GET['coai_vote_status'])) : 'all';
        $export_action = isset($_GET['coai_export']) ? sanitize_text_field(wp_unslash($_GET['coai_export'])) : '';
        $report_rows   = [];
        $manual_ballot_positions = [];
        $print_member_id = isset($_GET['print_member_id']) ? (int) $_GET['print_member_id'] : 0;
        $print_member_row = null;
        $report_totals = [
            'eligible'     => 0,
            'voted'        => 0,
            'not_voted'    => 0,
            'percent'      => 0,
            'last_voted_at'=> '',
        ];

        if ($report_election_id > 0) {
            $manual_ballot_positions = coai_get_election_positions($report_election_id);
                if ($print_member_id > 0) {
                    $print_member_row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT member_id, first_name, last_name, full_name, email, COAI_number
                             FROM {$members_table}
                             WHERE member_id = %d
                             LIMIT 1",
                            $print_member_id
                        ),
                        ARRAY_A
                    );
                }
            $member_rows = $wpdb->get_results(
                "SELECT member_id, first_name, last_name, full_name, email, COAI_number, status
                 FROM {$members_table}
                 ORDER BY last_name ASC, first_name ASC, member_id ASC",
                ARRAY_A
            );

            $vote_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT member_id, submitted_at, submission_method
                     FROM {$votes_table}
                     WHERE election_id = %d
                     ORDER BY submitted_at DESC, id DESC",
                    $report_election_id
                ),
                ARRAY_A
            );

            $votes_by_member = [];
            $last_voted_at = '';

            foreach ($vote_rows as $vote_row) {
                $mid = (int) ($vote_row['member_id'] ?? 0);
                if ($mid <= 0) {
                    continue;
                }

                if (!isset($votes_by_member[$mid])) {
                    $votes_by_member[$mid] = [
                        'submitted_at'      => (string) ($vote_row['submitted_at'] ?? ''),
                        'submission_method' => (string) ($vote_row['submission_method'] ?? 'online'),
                    ];
                }

                if (!empty($vote_row['submitted_at']) && ($last_voted_at === '' || $vote_row['submitted_at'] > $last_voted_at)) {
                    $last_voted_at = $vote_row['submitted_at'];
                }
            }
            
            foreach ($member_rows as $member) {
                $member_id = (int) ($member['member_id'] ?? 0);
                if ($member_id <= 0) {
                    continue;
                }

                list($eligible_for_vote, $eligibility_reason) = coai_member_is_voting_eligible($member_id);
                if (!$eligible_for_vote) {
                    continue;
                }

                $full_name = trim((string) ($member['full_name'] ?? ''));
                if ($full_name === '') {
                    $full_name = trim(
                        (string) ($member['first_name'] ?? '') . ' ' . (string) ($member['last_name'] ?? '')
                    );
                }
                $full_name = preg_replace('/\s+/', ' ', $full_name);

                $has_voted = isset($votes_by_member[$member_id]);
                $voted_at  = $has_voted ? (string) ($votes_by_member[$member_id]['submitted_at'] ?? '') : '';
                $method    = $has_voted ? (string) ($votes_by_member[$member_id]['submission_method'] ?? 'online') : '';

                $row = [
                    'member_id'    => $member_id,
                    'name'         => $full_name !== '' ? $full_name : ('Member #' . $member_id),
                    'coai_number'  => (string) ($member['COAI_number'] ?? ''),
                    'email'        => (string) ($member['email'] ?? ''),
                    'status_label' => $has_voted ? 'Voted' : 'Not Voted',
                    'voted_at'     => $voted_at,
                    'method'       => $method,
                ];

                $report_totals['eligible']++;

                if ($has_voted) {
                    $report_totals['voted']++;
                } else {
                    $report_totals['not_voted']++;
                }

                if ($report_status === 'voted' && !$has_voted) {
                    continue;
                }
                if ($report_status === 'not_voted' && $has_voted) {
                    continue;
                }

                $report_rows[] = $row;
            }

            $report_totals['last_voted_at'] = $last_voted_at;

            if ($report_totals['eligible'] > 0) {
                $report_totals['percent'] = round(($report_totals['voted'] / $report_totals['eligible']) * 100, 1);
            }
            
            if ($report_election_id > 0 && in_array($export_action, ['all', 'voted', 'not_voted', 'results'], true)) {

    if ($export_action === 'results') {
        $vote_items_table = coai_election_table('vote_items');
        if (empty($vote_items_table)) {
            wp_die('Vote items table could not be resolved.');
        }
        
        $export_election_title = '';
        foreach ($elections as $e) {
            if ((int) $e['id'] === (int) $report_election_id) {
                $export_election_title = (string) $e['title'];
                break;
            }
        }
        
        $positions_for_export = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$positions_table}
                 WHERE election_id = %d
                 ORDER BY
                   CASE group_name
                     WHEN 'Executive Committee' THEN 1
                     WHEN 'EXECUTIVE COMMITTEE' THEN 1
                     WHEN 'Directors' THEN 2
                     WHEN 'DIRECTORS' THEN 2
                     WHEN 'Regional Vice Presidents' THEN 3
                     WHEN 'REGIONAL VICE PRESIDENTS (RVPS)' THEN 3
                     WHEN 'Appointees' THEN 4
                     WHEN 'APPOINTEES' THEN 4
                     ELSE 99
                   END,
                   sort_order ASC,
                   id ASC",
                $report_election_id
            ),
            ARRAY_A
        );

        $slug_base = sanitize_title($export_election_title !== '' ? $export_election_title : 'election');
        $filename = 'coai-election-results-' . $slug_base . '-' . date('Ymd-His') . '.csv';

        while (ob_get_level()) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');

        fputcsv($out, ['Election', $export_election_title !== '' ? $export_election_title : ('Election #' . $report_election_id)]);
        fputcsv($out, ['Export Type', 'RESULTS']);
        fputcsv($out, ['Generated At', current_time('mysql')]);
        fputcsv($out, []);

        fputcsv($out, ['Group', 'Position', 'Candidate', 'Votes']);

        foreach ($positions_for_export as $position) {
            $position_id = (int) ($position['id'] ?? 0);
            if ($position_id <= 0) {
                continue;
            }

            $group_name = trim((string) ($position['group_name'] ?? ''));

            $results_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT c.candidate_name, COUNT(vi.id) AS total_votes
                     FROM {$candidates_table} c
                     LEFT JOIN {$vote_items_table} vi
                       ON vi.candidate_id = c.id
                      AND vi.position_id = c.position_id
                     WHERE c.position_id = %d
                     GROUP BY c.id, c.candidate_name, c.sort_order
                     ORDER BY total_votes DESC, c.sort_order ASC, c.id ASC",
                    $position_id
                ),
                ARRAY_A
            );

            if (empty($results_rows)) {
                fputcsv($out, [
                    $group_name,
                    (string) ($position['position_name'] ?? ''),
                    '',
                    0,
                ]);
                continue;
            }

            foreach ($results_rows as $results_row) {
                fputcsv($out, [
                    $group_name,
                    (string) ($position['position_name'] ?? ''),
                    (string) ($results_row['candidate_name'] ?? ''),
                    (int) ($results_row['total_votes'] ?? 0),
                ]);
            }
        }

        fclose($out);
        exit;
    }

    $export_rows = [];

                foreach ($report_rows as $row) {
                    if ($export_action === 'voted' && $row['status_label'] !== 'Voted') {
                        continue;
                    }
                    if ($export_action === 'not_voted' && $row['status_label'] !== 'Not Voted') {
                        continue;
                    }

                    $export_rows[] = [
                        'Name'      => $row['name'],
                        'COAI #'    => $row['coai_number'],
                        'Email'     => $row['email'],
                        'Status'    => $row['status_label'],
                        'Method'    => $row['method'],
                        'Voted At'  => $row['voted_at'],
                    ];
                }

                $export_election_title = '';
                foreach ($elections as $e) {
                    if ((int) $e['id'] === (int) $report_election_id) {
                        $export_election_title = (string) $e['title'];
                        break;
                    }
                }

                $slug_base = sanitize_title($export_election_title !== '' ? $export_election_title : 'election');
                $filename = 'coai-voting-report-' . $slug_base . '-' . $export_action . '-' . date('Ymd-His') . '.csv';

                while (ob_get_level()) {
                    ob_end_clean();
                }

                nocache_headers();
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');

                $out = fopen('php://output', 'w');

                fputcsv($out, ['Election', $export_election_title !== '' ? $export_election_title : ('Election #' . $report_election_id)]);
                fputcsv($out, ['Export Type', strtoupper(str_replace('_', ' ', $export_action))]);
                fputcsv($out, ['Generated At', current_time('mysql')]);
                fputcsv($out, []);

                fputcsv($out, ['Name', 'COAI #', 'Email', 'Status', 'Method', 'Voted At']);

                foreach ($export_rows as $csv_row) {
                    fputcsv($out, $csv_row);
                }

                fclose($out);
                exit;
            }
        }

        ob_start();
        ?>
        <style>
        .coai-admin-section{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:0;
            margin:0 0 16px;
            overflow:hidden;
        }
        .coai-admin-section summary{
            cursor:pointer;
            list-style:none;
            padding:16px;
            font-weight:700;
            user-select:none;
        }
        .coai-admin-section summary::-webkit-details-marker{display:none;}
        .coai-admin-section summary::after{
            content:'▾';
            float:right;
            color:#6b7280;
        }
        .coai-admin-section:not([open]) summary::after{
            content:'▸';
        }
        .coai-admin-section-body{
            padding:0 16px 16px;
            border-top:1px solid #f3f4f6;
        }
        
        .coai-report-cards{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:12px;
            margin:0 0 16px;
        }
        .coai-report-card{
            background:#f8fafc;
            border:1px solid #e5e7eb;
            border-radius:12px;
            padding:14px;
        }
        .coai-report-card .label{
            display:block;
            color:#6b7280;
            font-size:12px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.04em;
            margin-bottom:6px;
        }
        .coai-report-card .value{
            font-size:1.35rem;
            font-weight:800;
            line-height:1.2;
        }
        .coai-report-filters{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            align-items:end;
            margin:0 0 16px;
        }
        .coai-report-filters select{
            min-width:220px;
        }
        @media (max-width:900px){
            .coai-report-cards{
                grid-template-columns:1fr 1fr;
            }
        }
        @media (max-width:640px){
            .coai-report-cards{
                grid-template-columns:1fr;
            }
        }
        
        .coai-report-actions{
            display:flex;
            flex-wrap:wrap;
           gap:10px;
            margin:14px 0;
        }
        
        .coai-manual-ballot-card{
            margin:0 0 16px;
            padding:14px;
            border:1px solid #e5e7eb;
            border-radius:12px;
            background:#fffaf0;
        }
        .coai-manual-ballot-grid{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:12px;
            align-items:end;
        }
        .coai-manual-ballot-positions{
            margin-top:14px;
            display:grid;
            gap:12px;
        }
        .coai-manual-ballot-position{
            border:1px solid #e5e7eb;
            border-radius:10px;
            padding:12px;
            background:#fff;
        }
        @media (max-width:900px){
            .coai-manual-ballot-grid{
                grid-template-columns:1fr 1fr;
            }
        }
        @media (max-width:640px){
            .coai-manual-ballot-grid{
                grid-template-columns:1fr;
            }
        }
        
        @media (max-width:900px){
            .coai-manual-received-grid{
                grid-template-columns:1fr 1fr;
            }
        }
        @media (max-width:640px){
            .coai-manual-received-grid{
                grid-template-columns:1fr;
            }
        }
        </style>

        <div class="coai-wrap">
            <h2>Election Admin</h2>
            <?php echo $msg; ?>

            <details class="coai-admin-section" open>
                <summary>Create Election</summary>
                <div class="coai-admin-section-body">
                    <form method="post">
                        <?php wp_nonce_field('coai_create_election', '_coai_create_election_nonce'); ?>
                        <p><input type="text" name="title" placeholder="Election title" style="width:100%;max-width:700px;" required></p>
                        <p><textarea name="description" placeholder="Election description/instructions" rows="4" style="width:100%;max-width:700px;"></textarea></p>
                        <p>
                            <label>Status </label>
                            <select name="status">
                                <option value="draft">Draft</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </p>
                        <p><label>Opens At (YYYY-MM-DD HH:MM:SS)</label><br><input type="text" name="opens_at" style="width:320px;"></p>
                        <p><label>Closes At (YYYY-MM-DD HH:MM:SS)</label><br><input type="text" name="closes_at" style="width:320px;"></p>
                        <p><label><input type="checkbox" name="show_results" value="1"> Show results to members after close</label></p>
                        <p><button class="button button-primary" type="submit" name="coai_create_election" value="1">Create Election</button></p>
                    </form>
                </div>
            </details>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <details class="coai-admin-section" open>
                    <summary>Add Position</summary>
                    <div class="coai-admin-section-body">
                        <form method="post">
                            <?php wp_nonce_field('coai_add_position', '_coai_add_position_nonce'); ?>
                            <p>
                                <select name="election_id" required style="width:100%;max-width:420px;">
                                    <option value="">Select election</option>
                                    <?php foreach ($elections as $e): ?>
                                        <option value="<?php echo (int)$e['id']; ?>">
                                            <?php echo esc_html($e['title'] . ' (#' . $e['id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p>
                                <select name="group_name" style="width:100%;max-width:420px;" required>
                                    <option value="">Select group</option>
                                    <option value="Executive Committee">Executive Committee</option>
                                    <option value="Directors">Directors</option>
                                    <option value="Regional Vice Presidents">Regional Vice Presidents</option>
                                    <option value="Appointees">Appointees</option>
                                </select>
                            </p>

                            <p><input type="text" name="position_name" placeholder="Position name" style="width:100%;max-width:420px;" required></p>
                            <p><input type="number" name="max_selections" min="1" value="1" style="width:120px;"> max selections</p>
                            <p><input type="number" name="sort_order" value="0" style="width:120px;"> sort order</p>
                            <p><button class="button" type="submit" name="coai_add_position" value="1">Add Position</button></p>
                        </form>
                    </div>
                </details>

                <details class="coai-admin-section" open>
                    <summary>Add Candidate</summary>
                    <div class="coai-admin-section-body">
                        <form method="post">
                            <?php wp_nonce_field('coai_add_candidate', '_coai_add_candidate_nonce'); ?>

                            <p>
                                <select name="position_id" required style="width:100%;max-width:420px;">
                                    <option value="">Select position</option>
                                    <?php foreach ($all_positions as $p): ?>
                                        <option value="<?php echo (int)$p['id']; ?>">
                                            <?php echo esc_html($p['election_title'] . ' - ' . ($p['group_name'] ?: 'Ungrouped') . ' - ' . $p['position_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p><input type="text" name="candidate_name" placeholder="Candidate name" style="width:100%;max-width:420px;" required></p>
                            <p><input type="number" name="candidate_member_id" placeholder="Member ID (optional)" style="width:180px;"></p>

                            <div style="margin:0 0 12px;">
                                <input type="hidden" name="photo_url" id="coai_candidate_photo_url" value="">

                                <div id="coai_candidate_photo_preview" style="margin:0 0 10px;"></div>

                                <button
                                    type="button"
                                    class="button coai-photo-picker"
                                    data-target="coai_candidate_photo_url"
                                    data-preview="coai_candidate_photo_preview">
                                   Select Candidate Photo from Media Library
                                </button>

                                <button
                                    type="button"
                                    class="button coai-photo-remove"
                                    data-target="coai_candidate_photo_url"
                                    data-preview="coai_candidate_photo_preview"
                                    style="display:none;">
                                    Remove Photo
                                </button>

                                <div style="margin:8px 0 0;color:#6b7280;font-size:13px;">
                                    Upload a new photo or select an existing one, then click <strong>Add Candidate</strong>
                                </div>
                            </div>

                            <p><textarea name="bio" placeholder="Short bio" rows="4" style="width:100%;max-width:420px;"></textarea></p>
                            <p><input type="number" name="sort_order" value="0" style="width:120px;"> sort order</p>
                            <p><button class="button" type="submit" name="coai_add_candidate" value="1">Add Candidate</button></p>
                        </form>
                    </div>
                </details>
            </div>

                <details class="coai-admin-section">
                    <summary>Existing Elections</summary>
                    <div class="coai-admin-section-body">
                        <?php if (empty($elections)): ?>
                            <p>No elections found yet.</p>
                        <?php else: ?>
                            <table class="widefat striped">
                               <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Open</th>
                                        <th>Close</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($elections as $e): ?>
                                    <tr>
                                        <td><?php echo (int)$e['id']; ?></td>
                                        <td><?php echo esc_html($e['title']); ?></td>
                                        <td><?php echo esc_html($e['status']); ?></td>
                                        <td><?php echo esc_html($e['opens_at']); ?></td>
                                        <td><?php echo esc_html($e['closes_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </details>
                
            <details class="coai-admin-section">
                    <summary>Voting Progress Report</summary>
                    <div class="coai-admin-section-body">

                        <form method="get" class="coai-report-filters">
                           <input type="hidden" name="coai_report" value="1">

                            <p style="margin:0;">
                                <label for="coai_report_election_id"><strong>Election</strong></label><br>
                                <select name="coai_report_election_id" id="coai_report_election_id">
                                    <?php foreach ($elections as $e): ?>
                                        <option value="<?php echo (int) $e['id']; ?>" <?php selected($report_election_id, (int) $e['id']); ?>>
                                            <?php echo esc_html($e['title'] . ' (#' . $e['id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p style="margin:0;">
                                <label for="coai_vote_status"><strong>Status Filter</strong></label><br>
                                <select name="coai_vote_status" id="coai_vote_status">
                                     <option value="all" <?php selected($report_status, 'all'); ?>>All Eligible</option>
                                     <option value="voted" <?php selected($report_status, 'voted'); ?>>Voted Only</option>
                                     <option value="not_voted" <?php selected($report_status, 'not_voted'); ?>>Not Voted Only</option>
                                </select>
                            </p>

                            <p style="margin:0;">
                                <button class="button button-primary" type="submit">Refresh Report</button>
                            </p>
                        </form>
                        
                        <form method="get" style="margin:0 0 16px;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;">
                            <input type="hidden" name="coai_report" value="1">
                            <input type="hidden" name="coai_report_election_id" value="<?php echo (int) $report_election_id; ?>">
                            <input type="hidden" name="coai_vote_status" value="<?php echo esc_attr($report_status); ?>">
                            <input type="hidden" name="coai_print_blank_ballot" value="1">

                            <p style="margin:0 0 10px;"><strong>Print Blank Ballot</strong></p>

                            <div class="coai-manual-ballot-grid">
                                <p style="margin:0;">
                                    <label for="print_member_id"><strong>Member ID (optional)</strong></label><br>
                                    <input type="number" name="print_member_id" id="print_member_id" min="1" style="width:100%;" placeholder="Leave blank for generic ballot">
                                </p>

                                <p style="margin:0;">
                                    <label><strong>Election</strong></label><br>
                                    <span style="display:inline-block;padding:8px 10px;background:#fff;border:1px solid #d1d5db;border-radius:6px;width:100%;box-sizing:border-box;">
                                        <?php
                                        $print_election_title = '';
                                        foreach ($elections as $e) {
                                            if ((int) $e['id'] === (int) $report_election_id) {
                                                $print_election_title = $e['title'];
                                                break;
                                            }
                                        }
                                        echo esc_html($print_election_title !== '' ? $print_election_title : 'Selected election');
                                        ?>
                                    </span>
                                </p>

                                <p style="margin:0;">
                                    <label><strong>Output</strong></label><br>
                                    <span style="display:inline-block;padding:8px 10px;background:#fff;border:1px solid #d1d5db;border-radius:6px;width:100%;box-sizing:border-box;">
                                        Printable blank ballot
                                    </span>
                                </p>

                                <p style="margin:0;">
                                    <button class="button button-secondary" type="submit" formtarget="_blank">
                                        Print Blank Ballot
                                    </button>
                                </p>
                            </div>
                        </form>
                        
                        <form method="post" class="coai-manual-ballot-card">
                            <?php wp_nonce_field('coai_manual_ballot_entry', '_coai_manual_ballot_entry_nonce'); ?>

                            <p style="margin:0 0 10px;"><strong>Manual Ballot Entry</strong></p>

                            <div class="coai-manual-ballot-grid">
                                <p style="margin:0;">
                                    <label for="manual_vote_election_id"><strong>Election</strong></label><br>
                                    <select name="manual_vote_election_id" id="manual_vote_election_id" style="width:100%;">
                                        <?php foreach ($elections as $e): ?>
                                            <option value="<?php echo (int) $e['id']; ?>" <?php selected($report_election_id, (int) $e['id']); ?>>
                                                <?php echo esc_html($e['title'] . ' (#' . $e['id'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p style="margin:0;">
                                    <label for="manual_vote_member_id"><strong>Member ID</strong></label><br>
                                    <input type="number" name="manual_vote_member_id" id="manual_vote_member_id" min="1" style="width:100%;" required>
                                </p>

                                <p style="margin:0;">
                                    <label for="manual_vote_method"><strong>Method</strong></label><br>
                                    <select name="manual_vote_method" id="manual_vote_method" style="width:100%;">
                                        <option value="mail">mail</option>
                                        <option value="email">email</option>
                                        <option value="admin-entered">admin-entered</option>
                                    </select>
                                </p>

                                <p style="margin:0;">
                                    <button class="button button-primary" type="submit" name="coai_manual_ballot_entry" value="1">
                                        Save Manual Ballot
                                    </button>
                                </p>
                            </div>

                            <p style="margin:10px 0 0;">
                                <label for="manual_vote_note"><strong>Admin Note (optional)</strong></label><br>
                                <textarea name="manual_vote_note" id="manual_vote_note" rows="2" style="width:100%;max-width:100%;"></textarea>
                            </p>

                            <div class="coai-manual-ballot-positions">
                                <?php
                                $grouped_manual_positions = function_exists('coai_group_positions_for_ballot')
                                    ? coai_group_positions_for_ballot($manual_ballot_positions)
                                    : ['' => $manual_ballot_positions];
                                ?>

                                <?php foreach ($grouped_manual_positions as $group_name => $group_positions): ?>
                                    <?php
                                    $group_is_voteable = function_exists('coai_group_is_voteable')
                                        ? coai_group_is_voteable($group_name)
                                        : true;
                                    ?>
                                    <?php if (!$group_is_voteable) continue; ?>

                                    <?php if ($group_name !== ''): ?>
                                        <h4 style="margin:4px 0 0;"><?php echo esc_html($group_name); ?></h4>
                                    <?php endif; ?>

                                    <?php foreach ($group_positions as $position): ?>
                                        <?php
                                        $position_id = (int) ($position['id'] ?? 0);
                                        $candidates  = coai_get_position_candidates($position_id);
                                        if ($position_id <= 0) continue;
                                        ?>
                                        <div class="coai-manual-ballot-position">
                                            <p style="margin:0 0 8px;"><strong><?php echo esc_html($position['position_name']); ?></strong></p>

                                            <?php if (empty($candidates)): ?>
                                                <p style="margin:0;color:#6b7280;">No active candidates for this office.</p>
                                            <?php else: ?>
                                                <select name="manual_position_<?php echo (int) $position_id; ?>" style="width:100%;">
                                                    <option value="">-- No selection --</option>
                                                        <?php foreach ($candidates as $candidate): ?>
                                                            <option value="<?php echo (int) $candidate['id']; ?>">
                                                                <?php echo esc_html($candidate['candidate_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <form method="post" class="coai-manual-ballot-card" style="background:#fff1f2;border-color:#fecdd3;">
                        <?php wp_nonce_field('coai_remove_ballot', '_coai_remove_ballot_nonce'); ?>

                        <p style="margin:0 0 10px;"><strong>Remove Ballot</strong></p>
                        <p style="margin:0 0 12px;color:#881337;">
                            Use this only to correct an error. This removes the saved ballot and returns the member to Not Voted.
                        </p>

                        <div class="coai-manual-ballot-grid">
                            <p style="margin:0;">
                                <label for="remove_election_id"><strong>Election</strong></label><br>
                                <select name="remove_election_id" id="remove_election_id" style="width:100%;">
                                    <?php foreach ($elections as $e): ?>
                                        <option value="<?php echo (int) $e['id']; ?>" <?php selected($report_election_id, (int) $e['id']); ?>>
                                            <?php echo esc_html($e['title'] . ' (#' . $e['id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>

                            <p style="margin:0;">
                                <label for="remove_member_id"><strong>Member ID</strong></label><br>
                                <input type="number" name="remove_member_id" id="remove_member_id" min="1" style="width:100%;" required>
                            </p>

                            <p style="margin:0;">
                                <label><strong>Action</strong></label><br>
                                <span style="display:inline-block;padding:8px 10px;background:#fff;border:1px solid #fecdd3;border-radius:6px;">
                                    Remove ballot
                                </span>
                            </p>

                            <p style="margin:0;">
                                <button
                                    class="button button-secondary"
                                    type="submit"
                                    name="coai_remove_ballot"
                                    value="1"
                                    onclick="return confirm('Remove this ballot? This will delete the saved vote and return the member to Not Voted.');">
                                    Remove Ballot
                                </button>
                            </p>
                        </div>
                    </form>

                        <div class="coai-report-cards">
                            <div class="coai-report-card">
                            <span class="label">Eligible Voters</span>
                        <div class="value"><?php echo number_format_i18n((int) $report_totals['eligible']); ?></div>
                    </div>

                        <div class="coai-report-card">
                            <span class="label">Ballots Received</span>
                            <div class="value"><?php echo number_format_i18n((int) $report_totals['voted']); ?></div>
                        </div>

                        <div class="coai-report-card">
                            <span class="label">Not Yet Voted</span>
                            <div class="value"><?php echo number_format_i18n((int) $report_totals['not_voted']); ?></div>
                        </div>

                        <div class="coai-report-card">
                            <span class="label">Percent Complete</span>
                            <div class="value"><?php echo esc_html(number_format_i18n((float) $report_totals['percent'], 1)); ?>%</div>
                        </div>
                   </div>

                    <p style="margin:0 0 14px;color:#6b7280;">
                        <strong>Last ballot received:</strong>
                        <?php
                        echo !empty($report_totals['last_voted_at'])
                            ? esc_html(mysql2date('F j, Y g:i a', $report_totals['last_voted_at']))
                            : 'No ballots submitted yet';
                        ?>
                    </p>

                    <div class="coai-report-actions">
                        <button type="button" class="button" id="coai-toggle-members">Show Members</button>

                        <a
                            class="button"
                            href="<?php echo esc_url(add_query_arg([
                                'coai_report'             => '1',
                                'coai_report_election_id' => (int) $report_election_id,
                                'coai_vote_status'        => $report_status,
                                'coai_export'             => 'all',
                            ])); ?>">
                            Export All Eligible
                        </a>

                        <a
                            class="button"
                            href="<?php echo esc_url(add_query_arg([
                                'coai_report'             => '1',
                                'coai_report_election_id' => (int) $report_election_id,
                                'coai_vote_status'        => $report_status,
                                'coai_export'             => 'voted',
                            ])); ?>">
                            Export Voted
                        </a>

                        <a
                            class="button"
                            href="<?php echo esc_url(add_query_arg([
                                'coai_report'             => '1',
                                'coai_report_election_id' => (int) $report_election_id,
                                'coai_vote_status'        => $report_status,
                                'coai_export'             => 'not_voted',
                           ])); ?>">
                           Export Not Voted
                        </a>
                        
                        <a class="button button-primary" href="<?php echo esc_url(add_query_arg([
                            'coai_report'             => '1',
                            'coai_report_election_id' => (int) $report_election_id,
                            'coai_vote_status'        => $report_status,
                            'coai_export'             => 'results',
                        ])); ?>">
                            Export Results by Position
                        </a>
                    </div>

                    <div id="coai-members-wrap" style="display:none;">

                    <?php if (empty($report_rows)): ?>
                        <p>No eligible members found for this report filter.</p>
                    <?php else: ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>COAI #</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Method</th>
                                    <th>Voted At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_rows as $row): ?>
                                    <tr>
                                        <td><?php echo esc_html($row['name']); ?></td>
                                        <td><?php echo esc_html($row['coai_number'] !== '' ? $row['coai_number'] : ''); ?></td>
                                        <td><?php echo esc_html($row['email']); ?></td>
                                        <td>
                                            <td>
                                                <?php if ($row['status_label'] === 'Voted'): ?>
                                                    <strong style="color:#065f46;">Voted</strong>
                                                <?php else: ?>
                                                    <strong style="color:#9a3412;">Not Voted</strong>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo esc_html($row['method'] !== '' ? $row['method'] : ''); ?>
                                            </td>
                                            <td>
                                                <?php
                                                echo !empty($row['voted_at'])
                                                    ? esc_html(mysql2date('F j, Y g:i a', $row['voted_at']))
                                                   : '';
                                                ?>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                     <?php endif; ?>
                </div>
            </details>

            <details class="coai-admin-section">
                <summary>Edit Existing Positions</summary>
                <div class="coai-admin-section-body">
                    <?php if (empty($all_positions)): ?>
                        <p>No positions found yet.</p>
                    <?php else: ?>
                        <?php foreach ($all_positions as $p): ?>
                            <form method="post" style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin:0 0 12px;background:#fafafa;">
                                <?php wp_nonce_field('coai_update_position', '_coai_update_position_nonce'); ?>
                                <input type="hidden" name="position_id" value="<?php echo (int)$p['id']; ?>">

                                <p style="margin:0 0 8px;"><strong><?php echo esc_html($p['election_title']); ?></strong></p>

                                <p>
                                    <select name="group_name" style="width:100%;max-width:320px;">
                                        <option value="">Select group</option>
                                        <option value="Executive Committee" <?php selected($p['group_name'], 'Executive Committee'); ?>>Executive Committee</option>
                                        <option value="Directors" <?php selected($p['group_name'], 'Directors'); ?>>Directors</option>
                                        <option value="Regional Vice Presidents" <?php selected($p['group_name'], 'Regional Vice Presidents'); ?>>Regional Vice Presidents</option>
                                        <option value="Appointees" <?php selected($p['group_name'], 'Appointees'); ?>>Appointees</option>
                                    </select>
                                </p>

                                <p><input type="text" name="position_name" value="<?php echo esc_attr($p['position_name']); ?>" style="width:100%;max-width:420px;"></p>
                                <p><input type="number" name="max_selections" value="<?php echo (int)$p['max_selections']; ?>" style="width:120px;"> max selections</p>
                                <p><input type="number" name="sort_order" value="<?php echo (int)$p['sort_order']; ?>" style="width:120px;"> sort order</p>

                                <p><button class="button button-secondary" type="submit" name="coai_update_position" value="1">Update Position</button></p>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>

            <details class="coai-admin-section">
                <summary>Edit Existing Candidates</summary>
                <div class="coai-admin-section-body">
                    <?php if (empty($all_candidates)): ?>
                        <p>No candidates found yet.</p>
                    <?php else: ?>
                        <?php foreach ($all_candidates as $c): ?>
                            <form method="post" style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;margin:0 0 12px;background:#fafafa;">
                                <?php wp_nonce_field('coai_update_candidate', '_coai_update_candidate_nonce'); ?>
                                <input type="hidden" name="candidate_id" value="<?php echo (int)$c['id']; ?>">

                                <p style="margin:0 0 8px;">
                                    <strong><?php echo esc_html($c['election_title']); ?></strong><br>
                                    <span style="color:#6b7280;"><?php echo esc_html(($c['group_name'] ?: 'Ungrouped') . ' - ' . $c['position_name']); ?></span>
                                </p>

                                <p>
                                    <select name="position_id" style="width:100%;max-width:420px;">
                                        <?php foreach ($all_positions as $p): ?>
                                            <option value="<?php echo (int)$p['id']; ?>" <?php selected((int)$c['position_id'], (int)$p['id']); ?>>
                                                <?php echo esc_html($p['election_title'] . ' - ' . ($p['group_name'] ?: 'Ungrouped') . ' - ' . $p['position_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>

                                <p><input type="text" name="candidate_name" value="<?php echo esc_attr($c['candidate_name']); ?>" style="width:100%;max-width:420px;"></p>
                                <p><input type="number" name="candidate_member_id" value="<?php echo esc_attr((string)$c['candidate_member_id']); ?>" placeholder="Member ID (optional)" style="width:180px;"></p>

                                <div style="margin:0 0 12px;">
                                    <input
                                        type="hidden"
                                        name="photo_url"
                                        id="coai_candidate_photo_url_<?php echo (int)$c['id']; ?>"
                                        value="<?php echo esc_attr($c['photo_url'] ?? ''); ?>">

                                    <div
                                        id="coai_candidate_photo_preview_<?php echo (int)$c['id']; ?>"
                                        style="margin:0 0 10px;">
                                        <?php if (!empty($c['photo_url'])): ?>
                                            <img
                                                src="<?php echo esc_url($c['photo_url']); ?>"
                                                alt=""
                                                style="max-width:140px;height:auto;border:1px solid #e5e7eb;border-radius:10px;padding:4px;background:#fff;">
                                    <?php endif; ?>
                                </div>

                                <button
                                    type="button"
                                    class="button coai-photo-picker"
                                    data-target="coai_candidate_photo_url_<?php echo (int)$c['id']; ?>"
                                    data-preview="coai_candidate_photo_preview_<?php echo (int)$c['id']; ?>">
                                    Select Candidate Photo from Media Library
                                </button>

                                <button
                                    type="button"
                                    class="button coai-photo-remove"
                                    data-target="coai_candidate_photo_url_<?php echo (int)$c['id']; ?>"
                                    data-preview="coai_candidate_photo_preview_<?php echo (int)$c['id']; ?>"
                                    style="<?php echo !empty($c['photo_url']) ? '' : 'display:none;'; ?>">
                                    Remove Photo
                                </button>

                                <div style="margin:8px 0 0;color:#6b7280;font-size:13px;">
                                    Upload a new photo or select an existing one, then click <strong>Update Candidate</strong>
                                </div>
                            </div>

                                <p><textarea name="bio" rows="4" style="width:100%;max-width:420px;"><?php echo esc_textarea($c['bio']); ?></textarea></p>
                                <p><input type="number" name="sort_order" value="<?php echo (int)$c['sort_order']; ?>" style="width:120px;"> sort order</p>
                                <p><label><input type="checkbox" name="is_active" value="1" <?php checked((int)$c['is_active'], 1); ?>> Active</label></p>

                                <p style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <button class="button button-secondary" type="submit" name="coai_update_candidate" value="1">
                                        Update Candidate
                                    </button>
                                </p>

                                <p style="margin-top:8px;">
                                    <?php wp_nonce_field('coai_remove_candidate', '_coai_remove_candidate_nonce'); ?>
                                        <button
                                            class="button button-link-delete"
                                            type="submit"
                                            name="coai_remove_candidate"
                                            value="1"
                                            onclick="return confirm('Remove this candidate? If the candidate already has votes, they will be deactivated instead of deleted.');">
                                            Remove Candidate
                                        </button>
                                </p>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('coai-toggle-members');
            var wrap = document.getElementById('coai-members-wrap');

            if (!btn || !wrap) return;

            btn.addEventListener('click', function() {
                var isHidden = wrap.style.display === 'none' || wrap.style.display === '';

                wrap.style.display = isHidden ? 'block' : 'none';
                btn.textContent = isHidden ? 'Hide Members' : 'Show Members';
            });
        });
        </script>

        <script>
        jQuery(function($){

            $(document).on('click', '.coai-photo-picker', function(e){
                e.preventDefault();
        
                const $btn = $(this);
                const target = $btn.data('target');
                const preview = $btn.data('preview');
                const $removeBtn = $btn.siblings('.coai-photo-remove');

                const frame = wp.media({
                    title: 'Select or Upload Candidate Photo',
                    button: { text: 'Use this image' },
                    library: { type: 'image' },
                    multiple: false
                });

               frame.on('select', function(){
                    const attachment = frame.state().get('selection').first().toJSON();

                    $('#' + target).val(attachment.url);
                    $('#' + preview).html(
                        '<img src="' + attachment.url + '" alt="" style="max-width:140px;height:auto;border:1px solid #e5e7eb;border-radius:10px;padding:4px;background:#fff;">'
                    );
                    $removeBtn.show();
                });

                frame.open();
            });

            $(document).on('click', '.coai-photo-remove', function(e){
                e.preventDefault();

                const $btn = $(this);
                const target = $btn.data('target');
                const preview = $btn.data('preview');

                $('#' + target).val('');
                $('#' + preview).html('');
                $btn.hide();
            });

        });
        </script>
        <?php
        return ob_get_clean();
    }

    add_shortcode('coai_staff_election_admin', 'coai_render_staff_election_admin_shortcode');
}