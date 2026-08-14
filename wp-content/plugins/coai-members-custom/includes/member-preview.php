function coai_render_member_preview($member_id = null) {
    global $wpdb;
    $T = coai_tables();
    $members_table = $T['members'] ?? '';
    $member_id = $member_id ?? ($_SESSION['member_id'] ?? 0);

    if (!$member_id || empty($members_table)) {
        return '<p style="color:red;">Member not found.</p>';
    }

    $me = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$members_table} WHERE member_id = %d", $member_id));
    if (!$me) {
        return '<p style="color:red;">Profile not found.</p>';
    }

    ob_start(); ?>
    <div class="coai-preview-card">
        <h3>Member Profile Preview</h3>
        <table class="coai-preview-table">
            <tbody>
                <tr><th>Username</th><td><?php echo esc_html($me->username); ?></td></tr>
                <tr><th>First Name</th><td><?php echo esc_html($me->first_name); ?></td></tr>
                <tr><th>Last Name</th><td><?php echo esc_html($me->last_name); ?></td></tr>
                <tr><th>Email</th><td><?php echo esc_html($me->email); ?></td></tr>
                <tr><th>Phone</th><td><?php echo esc_html($me->phone); ?></td></tr>
                <tr><th>Address</th><td><?php echo esc_html($me->address); ?></td></tr>
                <tr><th>City</th><td><?php echo esc_html($me->city); ?></td></tr>
                <tr><th>State</th><td><?php echo esc_html($me->state); ?></td></tr>
                <tr><th>Zip</th><td><?php echo esc_html($me->zip); ?></td></tr>
                <tr><th>Country</th><td><?php echo esc_html($me->country); ?></td></tr>
                <tr><th>Region</th><td><?php echo esc_html($me->region); ?></td></tr>
                <tr><th>Member Since</th><td><?php echo esc_html($me->member_since); ?></td></tr>
                <tr><th>COAI #</th><td><?php echo esc_html($me->coai_number); ?></td></tr>
                <tr><th>Renewal Date</th><td><?php echo esc_html($me->renewal_date); ?></td></tr>
                <tr><th>Alley Affiliation</th><td><?php echo esc_html($me->alley_affiliation); ?></td></tr>
                <tr><th>Insurance</th><td><?php echo esc_html($me->insurance); ?></td></tr>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}