<?php
if (!defined('ABSPATH')) exit;

function coai_render_admin_tools($me = null) {
    ob_start();

    echo '<div class="coai-admin-tools">';
    echo '<h3>Admin Tools</h3>';

    echo '<p><a class="button" id="coai-manage-roles" href="#">🛠 Manage Roles</a></p>';
    echo do_shortcode('[coai_role_manager_ui]');

    echo '<p><a class="button" href="' . esc_url(coai_page('member-reports')) . '">📋 View Full Member Directory</a></p>';

    // Admin/Manager only: link to Manual Add Member page
    if (function_exists('coai_staff_can') && coai_staff_can('manage')) {
        echo '<p><a class="button" href="' . esc_url(home_url('/manual-add-member/')) . '">➕ Manual Add Member (Check)</a></p>';
    }

    echo '<p><a class="button" href="' . esc_url(plugin_dir_url(__FILE__) . '../../exports/members-export.csv') . '" download>📤 Export Members CSV</a></p>';

    echo '</div>';

    return ob_get_clean();
}
