<?php
add_action('template_redirect', function () {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) return;

    if (get_query_var('coai_edit_member')) {
        status_header(200);
        nocache_headers();

        echo '<!DOCTYPE html><html><head>';
        echo '<meta charset="UTF-8"><title>Edit Member</title>';
        wp_head();
        echo '</head><body><div class="wrap">';
        echo do_shortcode('[coai_member_edit_form]');
        echo '</div>';
        wp_footer();
        echo '</body></html>';

        exit;
    }
});