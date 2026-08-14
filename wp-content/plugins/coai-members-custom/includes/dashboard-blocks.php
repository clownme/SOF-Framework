<?php
// Modular dashboard blocks for COAI member portal

function coai_render_reports_block() {
    if (!coai_user_can('view_reports')) return '';
    ob_start(); ?>
    <div class="coai-dashboard-block">
        <h3>Reports</h3>
        <a href="/wp-admin/admin.php?page=coai_reports" class="button">View Reports</a>
    </div>
    <?php return ob_get_clean();
}

function coai_render_export_block() {
    if (!coai_user_can('export_data')) return '';
    ob_start(); ?>
    <div class="coai-dashboard-block">
        <h3>Export Members</h3>
        <button id="coai-export-button" class="button">Export Member Data</button>
    </div>
    <?php return ob_get_clean();
}

function coai_render_welcome_block($me) {
    ob_start(); ?>
    <div class="coai-dashboard-block">
        <h3>Welcome, <?php echo esc_html($me->full_name ?? 'Member'); ?>!</h3>
        <p>Your role: <?php echo esc_html($_SESSION['usergroup'] ?? 'Unknown'); ?></p>
    </div>
    <?php return ob_get_clean();
}

function coai_render_newsletters_block() {
    // Admin/Manager only (manage staff)
    $can_manage = function_exists('coai_staff_can') && coai_staff_can('manage');

    // Fallback if your site uses coai_user_can permissions instead of coai_staff_can
    if (!$can_manage && function_exists('coai_user_can')) {
        // If you already have a better permission string for staff tools, swap it in here.
        // Example: coai_user_can('manage_staff_tools') or coai_user_can('export_data')
        $can_manage = coai_user_can('export_data'); // fallback
    }

    if (!$can_manage) return '';

    $url = site_url('/staff-newsletters/');

    ob_start(); ?>
    <div class="coai-dashboard-block">
        <h3>📰 Newsletters</h3>
        <p>Create newsletters & announcements.</p>
        <a href="<?php echo esc_url($url); ?>" class="button">Open Newsletter Center</a>
    </div>
    <?php return ob_get_clean();
}
