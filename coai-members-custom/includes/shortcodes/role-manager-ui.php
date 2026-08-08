<?php
add_shortcode('coai_role_manager_ui', 'coai_render_role_manager_ui');

function coai_render_role_manager_ui() {
    ob_start();

    echo '<div id="coai-role-manager" style="display:none;">';
    echo '<h3>Role Manager</h3>';
    echo '<p>This block will contain role assignment tools for admins. You can toggle it using the "Manage Roles" button.</p>';
    echo '</div>';

    return ob_get_clean();
}