<?php
function coai_render_member_preview($member_id = null) {
    global $wpdb;
    $T = function_exists('coai_tables') ? coai_tables() : [];
    $members_table = $T['members'] ?? '';
    $membership_table = $T['membership_levels'] ?? '';
    $member_id = $member_id ?? ($_SESSION['member_id'] ?? 0);

    if (!$member_id || empty($members_table)) {
        return '<p style="color:red;">Member not found.</p>';
    }

    if (function_exists('coai_sync_region_for_member')) {
        coai_sync_region_for_member($member_id);
    }

    $me = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT m.*, ml.name AS membership_level_name
             FROM {$members_table} m
             LEFT JOIN {$membership_table} ml ON m.membership_level_id = ml.id
             WHERE m.member_id = %d",
            $member_id
        )
    );

    if (!$me) {
        return '<p style="color:red;">Profile not found.</p>';
    }

    ob_start(); ?>
    <div class="coai-preview-card">
        <h3>Membership Details</h3>
        <table class="coai-preview-table">
            <tbody>
                <tr><th>Member Since</th><td><?php echo esc_html($me->created_at ?? '-'); ?></td></tr>
                <tr><th>Membership Level</th><td><?php echo esc_html($me->membership_level_name ?? '—'); ?></td></tr>
                <tr><th>COAI Number</th><td><?php echo esc_html($me->coai_number ?? ''); ?></td></tr>
                <tr><th>Renewal Date</th><td><?php echo esc_html($me->renewal_date ?? ''); ?></td></tr>
                <tr><th>Alley Affiliation</th><td><?php echo esc_html($me->alley_affiliation ?? ''); ?></td></tr>
                <tr><th>Insurance</th><td><?php echo esc_html($me->insurance ?? ''); ?></td></tr>
                <tr><th>Usergroup</th><td><?php echo esc_html($me->usergroup ?? ''); ?></td></tr>
                <tr><th>Status</th><td><?php echo esc_html($me->status ?? ''); ?></td></tr>
                <tr><th>Last Updated</th><td><?php echo esc_html($me->updated_at ?? ''); ?></td></tr>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}