<?php
function coai_render_finance_admin_tools($me = null, $members = []) {
    ob_start();

    echo '<div class="coai-finance-tools">';
    echo '<h3>Finance Tools</h3>';

    echo '<p><a class="button" href="' . esc_url(plugin_dir_url(__FILE__) . '../../exports/members-export.csv') . '" download>📤 Export Members CSV</a></p>';

    echo '<p>Bulk renewal actions and finance-specific filters can go here.</p>';

    echo '<ul>';
    foreach ($members as $m) {
        echo '<li>' . esc_html($m->first_name . ' ' . $m->last_name . ' — ' . $m->renewal_date) . '</li>';
    }
    echo '</ul>';

    echo '</div>';

    return ob_get_clean();
}