<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('coai_group_positions_for_results')) {
    function coai_group_positions_for_results(array $positions) {
        $grouped = [];

        foreach ($positions as $position) {
            $group_name = trim((string)($position['group_name'] ?? ''));

            if (!isset($grouped[$group_name])) {
                $grouped[$group_name] = [];
            }

            $grouped[$group_name][] = $position;
        }

        return $grouped;
    }
}

if (!function_exists('coai_render_staff_election_results_shortcode')) {
    function coai_render_staff_election_results_shortcode() {
        if (!is_user_logged_in() || !coai_user_can_manage_elections()) {
            return '<div class="notice notice-error"><p>Access denied.</p></div>';
        }

        global $wpdb;

        $elections_table  = coai_election_table('elections');
        $positions_table  = coai_election_table('positions');
        $candidates_table = coai_election_table('candidates');
        $vote_items_table = coai_election_table('vote_items');
        $votes_table      = coai_election_table('votes');

        $election_id = isset($_GET['election_id']) ? (int) $_GET['election_id'] : 0;

        $elections = $wpdb->get_results(
            "SELECT * FROM {$elections_table} ORDER BY id DESC",
            ARRAY_A
        );

        ob_start();
        ?>
        <div class="coai-wrap">
            <h2>Election Results</h2>

            <form method="get" style="margin:0 0 16px;">
                <?php if (isset($_GET['page_id'])): ?>
                    <input type="hidden" name="page_id" value="<?php echo esc_attr(wp_unslash($_GET['page_id'])); ?>">
                <?php endif; ?>

                <select name="election_id" onchange="this.form.submit()" style="min-width:320px;">
                    <option value="">Select election</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>" <?php selected($election_id, (int)$e['id']); ?>>
                            <?php echo esc_html($e['title'] . ' (#' . $e['id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($election_id <= 0): ?>
                <p>Please choose an election.</p>
            <?php else: ?>
                <?php
                $election = coai_get_election($election_id);

                if (!$election) {
                    echo '<div class="notice notice-error"><p>Election not found.</p></div>';
                } else {
                    $total_ballots = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$votes_table} WHERE election_id = %d",
                        $election_id
                    ));

                    echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:0 0 16px;">';
                    echo '<h3 style="margin-top:0;">' . esc_html($election['title']) . '</h3>';
                    echo '<p><strong>Status:</strong> ' . esc_html($election['status']) . '</p>';
                    echo '<p><strong>Total Ballots:</strong> ' . $total_ballots . '</p>';
                    echo '</div>';

                    $positions = $wpdb->get_results($wpdb->prepare(
                        "SELECT *
                         FROM {$positions_table}
                         WHERE election_id = %d
                         ORDER BY
                           CASE WHEN group_name IS NULL OR TRIM(group_name) = '' THEN 1 ELSE 0 END,
                           group_name ASC,
                           sort_order ASC,
                           id ASC",
                        $election_id
                    ), ARRAY_A);

                    if (empty($positions)) {
                        echo '<p>No positions found for this election.</p>';
                    } else {
                        $grouped_positions = [];

                        foreach ($positions as $position) {
                            $group_name = trim((string)($position['group_name'] ?? ''));

                            if (!isset($grouped_positions[$group_name])) {
                                $grouped_positions[$group_name] = [];
                            }

                            $grouped_positions[$group_name][] = $position;
                        }

                        foreach ($grouped_positions as $group_name => $group_positions) {
                            echo '<div style="background:#f8fafc;border:1px solid #dbe3ef;border-radius:14px;padding:16px;margin:0 0 20px;">';

                            if ($group_name !== '') {
                                echo '<h3 style="margin:0 0 10px;padding-bottom:6px;font-size:1.2rem;font-weight:700;text-transform:uppercase;color:#1f2937;border-bottom:2px solid #e5e7eb;">'
                                      . esc_html($group_name)
                                      . '</h3>';
                            }

                            if (strcasecmp($group_name, 'Regional Vice Presidents') === 0) {
                                echo '<p style="color:#6b7280;margin-top:0;">Vote only for your regional representative.</p>';
                            } elseif (strcasecmp($group_name, 'Appointees') === 0) {
                                echo '<p style="color:#6b7280;margin-top:0;">These positions are appointed at the first board meeting.</p>';
                            }

                            foreach ($group_positions as $position) {
                                $position_id = (int)$position['id'];

                                $results = $wpdb->get_results($wpdb->prepare(
                                    "SELECT c.id, c.candidate_name, COUNT(vi.id) AS total_votes
                                     FROM {$candidates_table} c
                                     LEFT JOIN {$vote_items_table} vi
                                       ON vi.candidate_id = c.id
                                      AND vi.position_id = c.position_id
                                     WHERE c.position_id = %d
                                     GROUP BY c.id, c.candidate_name
                                     ORDER BY total_votes DESC, c.sort_order ASC, c.id ASC",
                                    $position_id
                                ), ARRAY_A);

                                echo '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:0 0 16px;">';
                                echo '<h4 style="margin-top:0;">' . esc_html($position['position_name']) . '</h4>';

                                if (stripos((string)$position['position_name'], 'Regional Vice President') !== false) {
                                    echo '<p style="color:#6b7280;margin-top:0;">Please vote only for the Regional Vice President in your own region.</p>';
                                }

                                if (empty($results)) {
                                    echo '<p>No candidates found.</p>';
                                } else {
                                    echo '<table class="widefat striped">';
                                    echo '<thead><tr><th>Candidate</th><th>Votes</th></tr></thead><tbody>';

                                    foreach ($results as $row) {
                                        echo '<tr>';
                                        echo '<td>' . esc_html($row['candidate_name']) . '</td>';
                                        echo '<td>' . (int)$row['total_votes'] . '</td>';
                                        echo '</tr>';
                                    }

                                    echo '</tbody></table>';
                                }

                                echo '</div>';
                            }

                            echo '</div>';
                        }
                    }
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    add_shortcode('coai_staff_election_results', 'coai_render_staff_election_results_shortcode');
}