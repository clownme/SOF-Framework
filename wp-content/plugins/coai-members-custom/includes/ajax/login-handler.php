<?php
add_action('init', 'coai_process_member_login');

function coai_process_member_login() {
    if (!isset($_POST['login_submit'])) return;

    if (!isset($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_login')) {
        wp_redirect(add_query_arg('coai_err', 'nonce', home_url('/september/member-login')));
        exit;
    }

    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = sanitize_text_field($_POST['password'] ?? '');

    global $wpdb;
    $T = function_exists('coai_tables') ? coai_tables() : [];
    $members_table = $T['members'] ?? '';

    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$members_table} WHERE username = %s OR email = %s",
        $username, $username
    ));

    if (!$member || !password_verify($password, $member->password)) {
        wp_redirect(add_query_arg('coai_err', 'creds', home_url('/september/member-login')));
        exit;
    }

    $_SESSION['member_id'] = $member->member_id;
    wp_redirect(home_url('/member-portal'));
    exit;
}