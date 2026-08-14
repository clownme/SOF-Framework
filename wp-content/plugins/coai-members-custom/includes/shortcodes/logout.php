<?php
add_shortcode('member_logout', function () {
    if (isset($_GET['logout']) && $_GET['logout'] === '1') {
        session_destroy();
        wp_redirect(home_url('/september/member-login/'));
        exit;
    }

    ob_start(); ?>
    <div class="coai-card" style="margin-top:2rem;">
        <form method="get">
            <input type="hidden" name="logout" value="1">
            <button type="submit" class="button-secondary">Log Out</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
});