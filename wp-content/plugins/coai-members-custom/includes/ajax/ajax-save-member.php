<?php
add_action('wp_ajax_coai_save_member', 'coai_handle_save_member');

function coai_handle_save_member() {
    global $wpdb;
    $T = function_exists('coai_tables') ? coai_tables() : [];
    $members_table = $T['members'] ?? '';

    if (empty($members_table)) {
        wp_send_json_error('Members table not found.');
    }

    $member_id = intval($_POST['member_id'] ?? 0);
    if (!$member_id) {
        wp_send_json_error('Invalid member ID.');
    }

    // ✅ Auto-fill region based on state
    $state = sanitize_text_field($_POST['state'] ?? '');
    $region = function_exists('coai_region_from_state') ? coai_region_from_state($state) : '';

    // ✅ Build data array
    $data = [
        'first_name'        => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name'         => sanitize_text_field($_POST['last_name'] ?? ''),
        'email'             => sanitize_email($_POST['email'] ?? ''),
        'address'           => sanitize_text_field($_POST['address'] ?? ''),
        'membership_level'  => sanitize_text_field($_POST['membership_level'] ?? ''),
        'coai_number'       => sanitize_text_field($_POST['coai_number'] ?? ''),
        'region'            => sanitize_text_field($_POST['region'] ?? ''),
        'updated_at'        => current_time('mysql'),
    ];

    $updated = $wpdb->update($members_table, $data, ['member_id' => $member_id]);

    if ($updated !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Database update failed.');
    }
}