<?php
add_shortcode('coai_member_edit_form', 'coai_render_member_edit_form');

function coai_render_member_edit_form() {
    global $wpdb;
    $T = function_exists('coai_tables') ? coai_tables() : [];
    $members_table = $T['members'] ?? '';
    $member_id = get_query_var('coai_edit_member');

    if (!$member_id || empty($members_table)) {
        return '<p style="color:red;">Member not found.</p>';
    }

    $me = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$members_table} WHERE member_id = %d", $member_id));
    if (!$me) {
        return '<p style="color:red;">Profile not found.</p>';
    }

    ob_start(); ?>
    <div class="coai-member-edit" data-id="<?php echo esc_attr($member_id); ?>">
        <h2>Edit Member: <?php echo esc_html($me->first_name . ' ' . $me->last_name); ?></h2>

        <label for="first_name">First Name *</label>
        <input type="text" name="first_name" id="first_name" class="coai-input" placeholder="e.g. Bubbles" value="<?php echo esc_attr($me->first_name); ?>" required>

        <label for="last_name">Last Name *</label>
        <input type="text" name="last_name" id="last_name" class="coai-input" placeholder="e.g. Bubbles" value="<?php echo esc_attr($me->last_name); ?>"> required>

        <label for="email">Email *</label>
        <input type="email" name="email" id="email" class="coai-input" placeholder="e.g. Bubbles" value="<?php echo esc_attr($me->email); ?>"> required>

        <label for="address">Address *</label>
        <input type="text" name="address" id="address" class="coai-input" placeholder="e.g. Bubbles" value="<?php echo esc_attr($me->address); ?>">

        <label for="membership_level">Membership Level *</label>
        <input type="text" name="membership_level" id="membership_level" class="coai-input" placeholder="e.g. Bubbles" value="<?php echo esc_attr($me->membership_level); ?>">

        <label for="coai_number">COAI Number *</label></label>
        <input type="text" name="coai_number" id="coai_number" class="coai-input" placeholder="e.g. Bubbles" value="<?php echo esc_attr($me->coai_number); ?>">

        <label for="state">State *</label>
        <input type="text" name="state" id="state" class="coai-input" placeholder="e.g. VA" value="<?php echo esc_attr($me->state ?? ''); ?>" required>

        <label for="region">Region *</label>
        <input type="text" name="region" id="region" class="coai-input" placeholder="e.g. Bubbles" value="<?php echo esc_attr($me->region); ?>">

        <p><button class="button-primary coai-save-member">💾 Save Changes</button></p>
    </div>
    <?php
    return ob_get_clean();
}