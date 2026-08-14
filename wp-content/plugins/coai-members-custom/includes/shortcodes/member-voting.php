<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('coai_member_voting_styles')) {
    function coai_member_voting_styles() {
        ob_start(); ?>
        <style>
        .coai-vote-wrap{max-width:900px;margin:1.5rem auto;padding:1rem}

        .coai-vote-card{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:14px;
            padding:1rem 1.1rem;
            margin:0 0 1rem
        }

        .coai-vote-title{margin:0 0 .4rem;font-size:1.5rem}
        .coai-vote-muted{color:#6b7280}

        /* 🔷 GROUP SECTION (UPGRADED) */
        .coai-vote-group{
            background:#ffffff;
            border:1px solid #d1d5db;
            border-left:6px solid #2563eb;
            border-radius:14px;
            padding:18px;
            margin:0 0 24px;
            box-shadow:0 2px 6px rgba(0,0,0,0.04);
        }

        /* 🔷 GROUP TITLE (UPGRADED) */
        .coai-vote-group-title{
            margin:0 0 12px;
            padding-bottom:8px;
            font-size:1.25rem;
            font-weight:800;
            text-transform:uppercase;
            color:#1e3a8a;
            letter-spacing:0.5px;
            border-bottom:2px solid #e5e7eb;
        }

        /* 🔷 NOTE */
        .coai-vote-note{
            margin:.5rem 0 1rem;
            padding:.7rem .85rem;
            background:#eff6ff;
            border:1px solid #bfdbfe;
            border-radius:10px;
            color:#1e3a8a
        }

        .coai-vote-notice{
            padding:.85rem 1rem;
            border-radius:10px;
            margin:0 0 1rem;
            border:1px solid
        }

        .coai-vote-ok{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
        .coai-vote-warn{background:#fff7ed;border-color:#fdba74;color:#9a3412}
        .coai-vote-err{background:#fef2f2;border-color:#fca5a5;color:#991b1b}

        /* 🔷 POSITION CARD (SOFTER) */
        .coai-position{
            margin:0 0 14px;
            padding:14px;
            border:1px solid #e5e7eb;
            border-radius:12px;
            background:#f9fafb;
        }

        .coai-position h3{margin:0 0 .75rem}

        /* 🔷 CANDIDATE */
        .coai-candidate{
            display:block;
            padding:.5rem .75rem;
            margin:.4rem 0;
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:10px
        }
        
        .coai-candidate{
            display:block;
            padding:.75rem .85rem;
            margin:.5rem 0;
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:10px
        }

        .coai-candidate-row{
            display:grid;
            grid-template-columns:90px 1fr;
            gap:14px;
            align-items:start;
        }

        .coai-candidate-photo img{
            width:90px;
            height:90px;
            object-fit:cover;
            border-radius:10px;
            display:block;
            border:1px solid #d1d5db;
            background:#f9fafb;
        }

        .coai-candidate-name{
            margin:0;
        }

        .coai-candidate-bio{
            margin:.35rem 0 0;
        }

        @media (max-width:640px){
            .coai-candidate-row{
                grid-template-columns:1fr;
            }

        .coai-candidate-photo img{
                width:100%;
                max-width:160px;
                height:auto;
            }
        }

        /* 🔷 BUTTON */
        .coai-vote-submit{
            display:inline-block;
            border:0;
            border-radius:10px;
            padding:.8rem 1.1rem;
            font-weight:700;
            cursor:pointer;
            background:#2563eb;
            color:#fff
        }
        
        /* 🔷 Candidate hover */
        .coai-candidate:hover{
            border-color:#93c5fd;
            background:#eff6ff;
            cursor:pointer;
        }
 
        /* 🔷 Selected candidate */
        .coai-candidate input[type="radio"]:checked + strong{
            font-weight:800;
        }

        .coai-candidate input[type="radio"]:checked{
            accent-color:#2563eb;
        }

        /* 🔷 FULL ROW highlight when selected */
        .coai-candidate:has(input[type="radio"]:checked){
            border-color:#2563eb;
            background:#dbeafe;
            box-shadow:0 0 0 2px rgba(37,99,235,0.15);
        }

        .coai-vote-submit:hover{opacity:.95}
        </style>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('coai_get_position_note')) {
    function coai_get_position_note(array $position) {
        $name = trim((string)($position['position_name'] ?? ''));

        if (stripos($name, 'Regional Vice President') !== false) {
            return 'Please vote only for the Regional Vice President in your own region.';
        }

        return '';
    }
}

if (!function_exists('coai_get_group_note')) {
    function coai_get_group_note($group_name) {
        $group_name = trim((string)$group_name);

        if (
            strcasecmp($group_name, 'Regional Vice Presidents') === 0 ||
            strcasecmp($group_name, 'REGIONAL VICE PRESIDENTS (RVPS)') === 0
        ) {
            return 'Vote only for your regional representative.';
        }

        if (
            strcasecmp($group_name, 'Appointees') === 0 ||
            strcasecmp($group_name, 'APPOINTEES') === 0
        ) {
            return 'These positions are appointed at the first board meeting and are not part of member voting.';
        }

        return '';
    }
}

if (!function_exists('coai_group_is_voteable')) {
    function coai_group_is_voteable($group_name) {
        $group_name = trim((string)$group_name);

        return !(
            strcasecmp($group_name, 'Appointees') === 0 ||
            strcasecmp($group_name, 'APPOINTEES') === 0
        );
    }
}

if (!function_exists('coai_ballot_group_order')) {
    function coai_ballot_group_order() {
        return [
            'Executive Committee',
            'EXECUTIVE COMMITTEE',
            'Directors',
            'DIRECTORS',
            'Regional Vice Presidents',
            'REGIONAL VICE PRESIDENTS (RVPS)',
            'Appointees',
            'APPOINTEES',
            '',
        ];
    }
}

if (!function_exists('coai_group_positions_for_ballot')) {
    function coai_group_positions_for_ballot(array $positions) {
        $grouped = [];

        foreach ($positions as $position) {
            $group_name = trim((string)($position['group_name'] ?? ''));

            if (!isset($grouped[$group_name])) {
                $grouped[$group_name] = [];
            }

            $grouped[$group_name][] = $position;
        }

        $ordered = [];
        foreach (coai_ballot_group_order() as $wanted_group) {
            if (isset($grouped[$wanted_group])) {
                $ordered[$wanted_group] = $grouped[$wanted_group];
                unset($grouped[$wanted_group]);
            }
        }

        foreach ($grouped as $group_name => $group_positions) {
            $ordered[$group_name] = $group_positions;
        }

        return $ordered;
    }
}
if (!function_exists('coai_handle_member_vote_submission')) {
    function coai_handle_member_vote_submission($election, $member_id) {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return '';
        }

        if (empty($_POST['coai_submit_vote'])) {
            return '';
        }

        if (
            empty($_POST['_coai_vote_nonce']) ||
            !wp_verify_nonce($_POST['_coai_vote_nonce'], 'coai_submit_vote_' . (int)$election['id'])
        ) {
            return '<div class="coai-vote-notice coai-vote-err">Security check failed. Please reload and try again.</div>';
        }

        if (coai_member_has_voted((int)$election['id'], (int)$member_id)) {
            return '<div class="coai-vote-notice coai-vote-warn">Your ballot has already been submitted for this election.</div>';
        }

        list($eligible, $reason) = coai_member_is_voting_eligible($member_id);
        if (!$eligible) {
            return '<div class="coai-vote-notice coai-vote-err">' . esc_html($reason) . '</div>';
        }

        $positions = coai_get_election_positions((int)$election['id']);
        if (!$positions) {
            return '<div class="coai-vote-notice coai-vote-err">This election does not have any ballot positions configured yet.</div>';
        }

        $selected_map = [];
        foreach ($positions as $position) {
            $group_name = trim((string)($position['group_name'] ?? ''));

            if (!coai_group_is_voteable($group_name)) {
                continue;
            }

            $position_id  = (int)$position['id'];
            $field_key    = 'position_' . $position_id;
            $candidate_id = isset($_POST[$field_key]) ? (int)$_POST[$field_key] : 0;

            if ($candidate_id <= 0) {
                continue;
            }

            $valid_ids = array_map(
                static function($c) { return (int)$c['id']; },
                coai_get_position_candidates($position_id)
            );

            if (!in_array($candidate_id, $valid_ids, true)) {
                return '<div class="coai-vote-notice coai-vote-err">One of your selections was invalid. Please try again.</div>';
            }

            $selected_map[$position_id] = $candidate_id;
        }

        $votes_table      = coai_election_table('votes');
        $vote_items_table = coai_election_table('vote_items');

        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_textarea_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $wpdb->query('START TRANSACTION');

        try {
            $insert_vote = $wpdb->insert(
                $votes_table,
                [
                    'election_id'         => (int)$election['id'],
                    'member_id'           => (int)$member_id,
                    'submitted_at'        => current_time('mysql'),
                    'submission_method'   => 'online',
                    'entered_by_user_id'  => null,
                    'admin_note'          => null,
                    'ip_address'          => $ip_address,
                    'user_agent'          => $user_agent,
                ],
                ['%d','%d','%s','%s','%d','%s','%s','%s']
            );

            if (!$insert_vote) {
                throw new Exception('Could not save vote header.');
            }

            $vote_id = (int)$wpdb->insert_id;
            if ($vote_id <= 0) {
                throw new Exception('Missing vote ID.');
            }

            foreach ($selected_map as $position_id => $candidate_id) {
                $ok = $wpdb->insert(
                    $vote_items_table,
                    [
                        'vote_id'      => $vote_id,
                        'position_id'  => (int)$position_id,
                        'candidate_id' => (int)$candidate_id,
                    ],
                    ['%d','%d','%d']
                );

                if (!$ok) {
                    throw new Exception('Could not save vote item.');
                }
            }

            $wpdb->query('COMMIT');
            error_log('[COAI ELECTION] vote submitted election_id=' . (int)$election['id'] . ' member_id=' . (int)$member_id);

            return '<div class="coai-vote-notice coai-vote-ok">Thank you. Your ballot has been submitted successfully.</div>';

        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('[COAI ELECTION] vote submit failed: ' . $e->getMessage());
            return '<div class="coai-vote-notice coai-vote-err">We could not save your ballot. Please try again or contact staff.</div>';
        }
    }
}

if (!function_exists('coai_render_member_voting_shortcode')) {
    function coai_render_member_voting_shortcode() {
        if (!is_user_logged_in()) {
            return '<div class="coai-vote-wrap"><div class="coai-vote-notice coai-vote-warn">Please log in to access the member voting page.</div></div>';
        }

        $member_id = coai_get_logged_in_member_id();
        if ($member_id <= 0) {
            return '<div class="coai-vote-wrap"><div class="coai-vote-notice coai-vote-err">We could not load your member information.</div></div>';
        }

        $member_row = coai_get_member_row_for_voting($member_id);
        if (!$member_row) {
            return '<div class="coai-vote-wrap"><div class="coai-vote-notice coai-vote-err">We could not load your member information.</div></div>';
        }

        $election = coai_get_open_election();
        if (!$election) {
            return '<div class="coai-vote-wrap">' . coai_member_voting_styles() . '
                <div class="coai-vote-notice coai-vote-warn">
                    There is no open election at this time.
                </div>
            </div>';
        }

        list($eligible, $reason) = coai_member_is_voting_eligible($member_id);
        $positions = coai_get_election_positions((int)$election['id']);
        $grouped_positions = coai_group_positions_for_ballot($positions);
        $notice = coai_handle_member_vote_submission($election, $member_id);
        $already_voted = coai_member_has_voted((int)$election['id'], (int)$member_id);

        ob_start();
        echo coai_member_voting_styles();
        ?>
        <div class="coai-vote-wrap">
            <div class="coai-vote-card">
                <h2 class="coai-vote-title"><?php echo esc_html($election['title']); ?></h2>
                <div class="coai-vote-notice coai-vote-warn" style="margin-top:.75rem;">
                  <strong>You may vote for only one candidate per each elected position.</strong>
            </div>
                
        <?php
        $member_name = trim((string)(
            $member_row['full_name']
            ?? (($member_row['first_name'] ?? '') . ' ' . ($member_row['last_name'] ?? ''))
        ));
        $member_name = preg_replace('/\s+/', ' ', $member_name);
        $coai_number = trim((string)($member_row['COAI_number'] ?? $member_row['coai_number'] ?? ''));
        ?>

        <div class="coai-voter-info" style="margin:.85rem 0 0; padding:.85rem 1rem; background:#f8fafc; border:1px solid #dbeafe; border-radius:10px;">
            <div><strong>Voter Name:</strong> <?php echo esc_html($member_name !== '' ? $member_name : 'Unknown Member'); ?></div>
            <div><strong>COAI #:</strong> <?php echo esc_html($coai_number !== '' ? $coai_number : 'Not Available'); ?></div>
        </div>        

                <?php if (!empty($election['description'])): ?>
                    <div class="coai-vote-muted" style="margin:.35rem 0 0;">
                        <?php echo wp_kses_post(wpautop($election['description'])); ?>
                    </div>
                <?php endif; ?>

                <?php
                $closes_display = 'Until closed';

                if (!empty($election['closes_at'])) {
                    $closes_dt = new DateTime($election['closes_at'], new DateTimeZone('UTC'));
                    $closes_dt->setTimezone(new DateTimeZone('America/New_York'));
                    $closes_display = $closes_dt->format('F j, Y \a\t g:i A T');
                }
                ?>

                <p class="coai-vote-muted" style="margin:.75rem 0 0;">
                    <strong>Voting closes:</strong>
                    <?php echo esc_html($closes_display); ?>
                </p>
            </div>

            <?php echo $notice; ?>

            <?php if (!$eligible): ?>
                <div class="coai-vote-notice coai-vote-err"><?php echo esc_html($reason); ?></div>
            <?php elseif ($already_voted): ?>
                <div class="coai-vote-notice coai-vote-ok">Our records show that you have already voted in this election.</div>
            <?php else: ?>
                <?php if (empty($positions)): ?>
                    <div class="coai-vote-notice coai-vote-warn">This ballot is not configured yet.</div>
                <?php else: ?>
                    <form method="post">
                        <?php wp_nonce_field('coai_submit_vote_' . (int)$election['id'], '_coai_vote_nonce'); ?>

                        <?php foreach ($grouped_positions as $group_name => $group_positions): ?>
                            <?php
                            $group_note = coai_get_group_note($group_name);
                            $group_is_voteable = coai_group_is_voteable($group_name);
                            ?>
                            <div class="coai-vote-group">

                                <?php if ($group_name !== ''): ?>
                                    <h2 class="coai-vote-group-title"><?php echo esc_html($group_name); ?></h2>
                                <?php endif; ?>

                                <?php if ($group_note !== ''): ?>
                                    <div class="coai-vote-note"><?php echo esc_html($group_note); ?></div>
                                <?php endif; ?>

                                <?php foreach ($group_positions as $position): ?>
                                    <?php
                                    $position_id = (int)$position['id'];
                                    $candidates  = coai_get_position_candidates($position_id);
                                    $note        = coai_get_position_note($position);
                                    ?>
                                    <div class="coai-position">
                                        <h3><?php echo esc_html($position['position_name']); ?></h3>

                                        <?php if ($note !== ''): ?>
                                            <div class="coai-vote-note"><?php echo esc_html($note); ?></div>
                                        <?php endif; ?>

                                <?php if (empty($candidates)): ?>
                                    <p class="coai-vote-muted">No active candidates have been added for this office.</p>
                                <?php else: ?>
                                    <?php if (!$group_is_voteable): ?>
                                        <div class="coai-vote-note" style="margin-bottom:10px;">
                                            This office is informational only and is not voted on by members.
                                        </div>
                                <?php endif; ?>

                               <?php foreach ($candidates as $candidate): ?>
                                   <?php if ($group_is_voteable): ?>
                                       <label class="coai-candidate">
                                           <input
                                               type="radio"
                                               name="position_<?php echo (int)$position_id; ?>"
                                               value="<?php echo (int)$candidate['id']; ?>"
                                           >

                                           <div class="coai-candidate-row">
                                               <?php if (!empty($candidate['photo_url'])): ?>
                                                   <div class="coai-candidate-photo">
                                                       <img
                                                           src="<?php echo esc_url($candidate['photo_url']); ?>"
                                                           alt="<?php echo esc_attr($candidate['candidate_name']); ?>"
                                                   >
                                               </div>
                                           <?php endif; ?>

                                           <div class="coai-candidate-content">
                                               <strong class="coai-candidate-name"><?php echo esc_html($candidate['candidate_name']); ?></strong>

                                               <?php if (!empty($candidate['bio'])): ?>
                                                   <div class="coai-vote-muted coai-candidate-bio">
                                                       <?php echo wp_kses_post(wpautop($candidate['bio'])); ?>
                                                   </div>
                                               <?php endif; ?>
                                           </div>
                                       </div>
                                   </label>
                               <?php else: ?>
                                   <div class="coai-candidate" style="cursor:default;">
                                       <div class="coai-candidate-row">
                                           <?php if (!empty($candidate['photo_url'])): ?>
                                               <div class="coai-candidate-photo">
                                                   <img
                                                       src="<?php echo esc_url($candidate['photo_url']); ?>"
                                                       alt="<?php echo esc_attr($candidate['candidate_name']); ?>"
                                                   >
                                               </div>
                                           <?php endif; ?>

                                           <div class="coai-candidate-content">
                                               <strong class="coai-candidate-name"><?php echo esc_html($candidate['candidate_name']); ?></strong>

                                           <?php if (!empty($candidate['bio'])): ?>
                                               <div class="coai-vote-muted coai-candidate-bio">
                                                   <?php echo wp_kses_post(wpautop($candidate['bio'])); ?>
                                               </div>
                                           <?php endif; ?>
                                       </div>
                                   </div>
                               </div>
                               <?php endif; ?>
                           <?php endforeach; ?>
                       <?php endif; ?>
                   </div>
               <?php endforeach; ?>
           </div>
       <?php endforeach; ?>

                        <?php
                        $has_voteable = false;
                        foreach ($positions as $p) {
                            if (coai_group_is_voteable($p['group_name'] ?? '')) {
                                $has_voteable = true;
                                break;
                            }
                        }
                        ?>

                        <?php if ($has_voteable): ?>
                            <div style="margin-top:1rem;">
                                <button class="coai-vote-submit" type="submit" name="coai_submit_vote" value="1">
                                    Submit Ballot
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
                </div>

        <script>
        document.addEventListener('change', function(e) {
            if (!e.target.matches('input[type="radio"]')) return;

            const name = e.target.name;

            // clear all in same group
            document.querySelectorAll('input[name="'+name+'"]').forEach(radio => {
                const row = radio.closest('.coai-candidate');
                if (row) row.classList.remove('selected');
            });

            // add selected
            const selectedRow = e.target.closest('.coai-candidate');
            if (selectedRow) selectedRow.classList.add('selected');
        });
        </script>

        <?php
        return ob_get_clean();
    }

    add_shortcode('coai_member_voting', 'coai_render_member_voting_shortcode');
}