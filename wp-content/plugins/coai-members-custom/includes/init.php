<?php
// ✅ (already inside your init callback) Rewrite for /coai/edit-member/{id}
add_rewrite_tag('%coai_edit_member%', '([^&]+)');
add_rewrite_rule('^coai/edit-member/([^/]+)/?', 'index.php?coai_edit_member=$matches[1]', 'top');

// ⛔️ Legacy login handler is fully removed.
// Previously:
// add_action('init', 'coai_process_member_login');
// function coai_process_member_login() { ... direct SQL against zweam_coai_members ... }
// That code is deleted on purpose. Do NOT re-enable it.
