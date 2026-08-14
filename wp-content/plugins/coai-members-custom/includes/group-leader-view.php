<?php
error_log(' ✅group-leader-view.php loaded');

function coai_render_group_leader_view() {
    global $wpdb;
    $T = coai_tables();
    $members_table = $T['members'] ?? '';

    if (empty($members_table)) {
        return '<p style="color:red;">Members table not found.</p>';
    }

    // Fetch members (limit to 100 for now)
    $members = $wpdb->get_results("SELECT member_id, first_name, last_name, email, phone, region, usergroup FROM {$members_table} ORDER BY last_name ASC LIMIT 100");

    if (!$members) {
        return '<p>No members found.</p>';
    }

    ob_start();
    echo '<div class="coai-group-leader-view">';
    echo '<table class="coai-table" style="margin-top:1rem;"><thead><tr>';
    echo '<th>Name</th><th>Email</th><th>Phone</th><th>Region</th><th>Usergroup</th>';
    echo '</tr></thead><tbody>';

    foreach ($members as $m) {
        $name = trim(($m->first_name ?? '') . ' ' . ($m->last_name ?? ''));
        echo '<tr>';
        echo '<td>' . esc_html($name) . '</td>';
        echo '<td>' . esc_html($m->email ?? '') . '</td>';
        echo '<td>' . esc_html($m->phone ?? '') . '</td>';
        echo '<td>' . esc_html($m->region ?? '') . '</td>';
        echo '<td>' . esc_html($m->usergroup ?? '') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Optional: Add filter UI or export button
    echo '<div style="margin-top:1rem;">';
    echo '<button class="button-secondary" onclick="alert(\'Exporting filtered list...\')">Export List</button>';
    echo '</div>';

    echo '</div>';
    return ob_get_clean();
}