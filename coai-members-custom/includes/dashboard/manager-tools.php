<?php
function coai_render_manager_tools($me) {
    error_log('📞 coai_render_manager_tools() called for: ' . ($me->first_name ?? 'unknown'));

    ob_start();

    echo '<div class="manager-tools-wrapper">';
    echo '<h3>Manager Tools</h3>';
    echo '<p>Welcome, ' . esc_html($me->first_name) . '!</p>';
    echo '<ul>';
    echo '<li><a href="#">📋 Review group attendance</a></li>';
    echo '<li><a href="#">📎 Submit monthly reports</a></li>';
    echo '<li><a href="#">📊 View group performance</a></li>';
    echo '</ul>';
    echo '</div>';

    return ob_get_clean();
}