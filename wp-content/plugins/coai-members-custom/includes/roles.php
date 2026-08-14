<?php
// roles.php — central role capability map

if (!defined('ABSPATH')) exit;

function coai_user_can($capability, $usergroup = null): bool
{
    $usergroup = strtoupper(trim((string) (
        $usergroup ?? ($_SESSION['usergroup'] ?? '')
    )));

    // Capabilities based on a live organizational assignment.
    if ($capability === 'view_region_members') {
        if (function_exists('current_user_can') && current_user_can('administrator')) {
            return true;
        }

        $wp_user = function_exists('wp_get_current_user')
            ? wp_get_current_user()
            : null;

        $wp_roles = $wp_user
            ? array_map('strtolower', (array) ($wp_user->roles ?? []))
            : [];

        if (in_array('manager', $wp_roles, true)) {
            return true;
        }

        $member_id = 0;

        if ($wp_user && !empty($wp_user->ID)) {
            $member_id = (int) get_user_meta(
                (int) $wp_user->ID,
                'coai_member_id',
                true
            );
        }

        return $member_id > 0
            && function_exists('coai_get_active_rvp_region_for_member')
            && coai_get_active_rvp_region_for_member($member_id) !== '';
    }

    $roles = [
        'view_reports'     => ['ADMIN', 'MANAGER'],
        'export_data'      => ['ADMIN', 'MANAGER'],
        'edit_members'     => ['ADMIN'],
        'view_dashboard'   => ['ADMIN', 'MANAGER', 'FINANCE'],
        'access_finance'   => ['FINANCE', 'ADMIN'],
        'manage_roles'     => ['ADMIN'],
    ];

    return isset($roles[$capability])
        && in_array($usergroup, $roles[$capability], true);
}
