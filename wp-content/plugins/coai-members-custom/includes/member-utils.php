<?php
function coai_sync_region_for_member($member_id) {
    global $wpdb;
    $T = coai_tables();
    $members_table = $T['members'] ?? '';

    if (!$member_id || !$members_table) return;

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT state, region FROM {$members_table} WHERE member_id = %d",
        $member_id
    ));

    if (!$member) return;

    $expected_region = coai_get_region_by_state($member->state);
    if ($expected_region && $expected_region !== $member->region) {
        $wpdb->update(
            $members_table,
            ['region' => $expected_region],
            ['member_id' => $member_id]
        );
        error_log("✅ Region synced for member {$member_id}: {$expected_region}");
    }
}