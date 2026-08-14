<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the current member's saved profile-photo attachment ID.
 */
if (!function_exists('coai_get_profile_photo_id')) {
    function coai_get_profile_photo_id(int $user_id = 0): int {

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        if ($user_id <= 0) {
            return 0;
        }

        return (int) get_user_meta(
            $user_id,
            'coai_profile_photo_id',
            true
        );
    }
}

/**
 * Return markup for a member's profile photo.
 *
 * Falls back to a simple person icon when no photo exists.
 */
if (!function_exists('coai_get_profile_photo_html')) {
    function coai_get_profile_photo_html(
        int $user_id = 0,
        int $size = 96,
        string $class = ''
    ): string {

        if ($user_id <= 0) {
            $user_id = get_current_user_id();
        }

        $photo_id = coai_get_profile_photo_id($user_id);

        if ($photo_id > 0) {
            $position_x = (int) get_user_meta(
                $user_id,
                'coai_profile_photo_x',
                true
            );

            $position_y = (int) get_user_meta(
                $user_id,
                'coai_profile_photo_y',
                true
            );

            if ($position_x < 0 || $position_x > 100) {
                $position_x = 50;
            }

            if ($position_y < 0 || $position_y > 100) {
                $position_y = 50;
            }

            $image = wp_get_attachment_image(
                $photo_id,
                [$size, $size],
                false,
                [
                    'class'   => trim(
                        'coai-profile-photo ' . $class
                    ),
                    'loading' => 'lazy',
                    'alt'     => 'Member profile photo',
                    'style'   => sprintf(
                        'object-fit:cover;object-position:%d%% %d%%;',
                        $position_x,
                        $position_y
                    ),
                ]
            );

            if ($image !== '') {
                return $image;
            }
        }

        return sprintf(
            '<span class="%1$s" aria-hidden="true" style="%2$s">👤</span>',
            esc_attr(
                trim(
                    'coai-profile-photo-placeholder ' . $class
                )
            ),
            esc_attr(
                'display:inline-flex;' .
                'align-items:center;' .
                'justify-content:center;' .
                'width:' . $size . 'px;' .
                'height:' . $size . 'px;' .
                'border-radius:50%;' .
                'background:#f3f4f6;' .
                'border:1px solid #d1d5db;' .
                'font-size:' . round($size * 0.48) . 'px;'
            )
        );
    }
}

/** Resolve members table (prefer shared helper) */

if (!function_exists('coai_pf_get_members_table')) {
    function coai_pf_get_members_table(): string {
        global $wpdb;
        if (function_exists('coai_get_members_table')) return coai_get_members_table();
        if (defined('COAI_MEMBERS_TABLE') && COAI_MEMBERS_TABLE) return COAI_MEMBERS_TABLE;
        if (function_exists('coai_tables')) {
            $T = coai_tables();
            if (!empty($T['members'])) return (string)$T['members'];
        }
        return $wpdb->prefix . 'members';
    }
}

if (!function_exists('coai_pf_family_members_table_name')) {
    function coai_pf_family_members_table_name(): string {
        return 'wp_member_family_members';
    }
}

if (!function_exists('coai_pf_get_family_members_for_member')) {
    function coai_pf_get_family_members_for_member(int $member_id): array {
        global $wpdb;

        if ($member_id <= 0) return [];

        $family_table = coai_pf_family_members_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM `$family_table`
                 WHERE primary_member_id = %d
                 ORDER BY last_name ASC, first_name ASC, id ASC",
                $member_id
            ),
            ARRAY_A
        );
    }
}

if (!function_exists('coai_render_member_profile_form')) {
    function coai_render_member_profile_form() {
        if (!is_user_logged_in()) return '<p>Please log in to view your profile.</p>';

        global $wpdb;
        $table  = coai_pf_get_members_table();
        $u      = wp_get_current_user();
        $wp_uid = (int)$u->ID;

        // Find member_id (user meta → email/username fallback)
        $member_id = (int) get_user_meta($wp_uid, 'coai_member_id', true);
        if (!$member_id) {
            $tmp = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT member_id FROM `$table` WHERE email=%s OR username=%s LIMIT 1",
                    $u->user_email,
                    $u->user_login
                ),
                ARRAY_A
            );
            if ($tmp && !empty($tmp['member_id'])) {
                $member_id = (int)$tmp['member_id'];
                update_user_meta($wp_uid, 'coai_member_id', $member_id);
            }
        }
        if (!$member_id) return '<p style="color:#b91c1c;">Member not found.</p>';

        // Editable fields → exact column names in your table
        $editable_cols = [
            'username',
            'email',
            'full_name',
            'first_name',
            'last_name',
            // NOTE: UI label "Phone" binds to column "mobile"
            'mobile',
            'address',
            'address2',
            'city',
            'state',
            'zip',
            'country',
            'clown_name',
            'birthday',           // DATE (YYYY-MM-DD)
            'alley_membership',
        ];

        // Determine actual columns once (prevents unknown-column SQL errors)
        $cols_lc = array_map('strtolower', (array)$wpdb->get_col("DESC `$table`", 0));

        $updated = false;
        $notice  = '';
        $photo_error = '';

        // Handle POST update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coai_profile_submit'])) {
            if (empty($_POST['_coai_nonce']) || !wp_verify_nonce($_POST['_coai_nonce'], 'coai_update_profile')) {
                return '<div style="padding:.5rem;border:1px solid #ef4444;background:#fee2e2;border-radius:8px;">Security check failed. Please try again.</div>';
            }

            $data   = [];
            $format = [];

            /*
             * -------------------------------------------------
             * Profile Photo
             * -------------------------------------------------
             */

            if (!empty($_POST['coai_remove_profile_photo'])) {

                $existing_photo_id = coai_get_profile_photo_id(
                    $wp_uid
                );

                delete_user_meta(
                    $wp_uid,
                    'coai_profile_photo_id'
                );

                /*
                 * Remove the Media Library attachment because
                 * this upload belongs specifically to this
                 * member profile.
                 */
                if ($existing_photo_id > 0) {
                    wp_delete_attachment(
                        $existing_photo_id,
                        true
                    );
                }

                $updated = true;
                $notice = 'Profile photo removed.';
            }

            if (
                !empty($_FILES['coai_profile_photo']) &&
                !empty($_FILES['coai_profile_photo']['name'])
            ) {
                $upload_error = (int) (
                    $_FILES['coai_profile_photo']['error'] ??
                    UPLOAD_ERR_NO_FILE
                );

                if ($upload_error === UPLOAD_ERR_OK) {

                    $max_photo_size = 5 * 1024 * 1024;

                    if (
                        (int) $_FILES['coai_profile_photo']['size'] >
                        $max_photo_size
                    ) {
                        $photo_error =
                            'The profile photo must be 5 MB or smaller.';
                    } else {

                        require_once ABSPATH .
                            'wp-admin/includes/file.php';

                        require_once ABSPATH .
                            'wp-admin/includes/media.php';

                        require_once ABSPATH .
                            'wp-admin/includes/image.php';

                        $allowed_types = [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ];

                        $checked_file = wp_check_filetype_and_ext(
                            $_FILES['coai_profile_photo']['tmp_name'],
                            $_FILES['coai_profile_photo']['name']
                        );

                        $detected_type =
                            $checked_file['type'] ?? '';

                        if (
                            !in_array(
                                $detected_type,
                                $allowed_types,
                                true
                            )
                        ) {
                            $photo_error =
                                'Please select a JPG, PNG, or WebP image.';
                        } else {

                            $old_photo_id =
                                coai_get_profile_photo_id(
                                    $wp_uid
                                );

                            $attachment_id = media_handle_upload(
                                'coai_profile_photo',
                                0,
                                [
                                    'post_title' =>
                                        trim(
                                            ($u->display_name ?: 'Member') .
                                            ' Profile Photo'
                                        ),
                                ]
                            );

                            if (is_wp_error($attachment_id)) {
                                $photo_error =
                                    'The profile photo could not be uploaded. ' .
                                    $attachment_id->get_error_message();

                                error_log(
                                    '[COAI Profile Photo] Upload failed for user ' .
                                    $wp_uid .
                                    ': ' .
                                    $attachment_id->get_error_message()
                                );
                            } else {

                                $meta_saved = update_user_meta(
                                    $wp_uid,
                                    'coai_profile_photo_id',
                                    (int) $attachment_id
                                );

                                error_log(
                                    '[COAI Profile Photo] Attachment ID ' .
                                    (int) $attachment_id .
                                    ' saved for WordPress user ID ' .
                                    $wp_uid .
                                    '. User meta result: ' .
                                    var_export($meta_saved, true)
                                );
                                
                                /*
                                 * Start newly uploaded photos in the center.
                                 * The member may reposition the photo before saving.
                                 */
                                if (
                                    !isset($_POST['coai_profile_photo_x']) ||
                                    !isset($_POST['coai_profile_photo_y'])
                                ) {
                                    update_user_meta(
                                        $wp_uid,
                                        'coai_profile_photo_x',
                                        50
                                    );

                                    update_user_meta(
                                        $wp_uid,
                                        'coai_profile_photo_y',
                                        50
                                    );
                                }

                                if (
                                    $old_photo_id > 0 &&
                                    $old_photo_id !==
                                        (int) $attachment_id
                                ) {
                                    wp_delete_attachment(
                                        $old_photo_id,
                                        true
                                    );
                                }

                                $updated = true;
                                $notice =
                                    'Profile photo saved.';
                            }
                        }
                    }
                } elseif (
                    $upload_error !== UPLOAD_ERR_NO_FILE
                ) {
                    $photo_error =
                        'The profile photo could not be uploaded. ' .
                        'Upload error code: ' .
                        $upload_error;
                }
            }
            
            /*
             * -------------------------------------------------
             * Profile Photo Position
             * -------------------------------------------------
             */
             
            $photo_position_x = isset(
                $_POST['coai_profile_photo_x']
            )
                ? (int) $_POST['coai_profile_photo_x']
                : 50;

            $photo_position_y = isset(
                $_POST['coai_profile_photo_y']
            )
                ? (int) $_POST['coai_profile_photo_y']
                : 50;

            $photo_position_x = max(
                0,
                min(100, $photo_position_x)
            );

            $photo_position_y = max(
                0,
                min(100, $photo_position_y)
            );

            update_user_meta(
                $wp_uid,
                'coai_profile_photo_x',
                $photo_position_x
            );

            update_user_meta(
                $wp_uid,
                'coai_profile_photo_y',
                $photo_position_y
            );

            foreach ($editable_cols as $col) {
                if (!isset($_POST[$col])) continue;

                $val = wp_unslash($_POST[$col]);

                if ($col === 'email') {
                    $val = sanitize_email($val);
                } elseif ($col === 'birthday') {
                    // HTML date input posts YYYY-MM-DD; keep simple sanitation
                    $val = preg_replace('~[^0-9\-]~', '', (string)$val);
                } else {
                    $val = sanitize_text_field($val);
                }

                // Only include if the column actually exists
                if (!in_array(strtolower($col), $cols_lc, true)) continue;

                $data[$col] = $val;
                $format[]   = '%s';
            }

            // Normalize country to code if helper exists (and column exists)
            if (in_array('country', $cols_lc, true)) {
                $raw_country = isset($_POST['country']) ? wp_unslash($_POST['country']) : '';
                $raw_country = sanitize_text_field((string)$raw_country);
                if (function_exists('coai_country')) {
                    $data['country'] = (string)coai_country($raw_country, 'code'); // e.g. United States -> US
                } elseif (isset($data['country'])) {
                    // Keep whatever user typed
                    $data['country'] = (string)$data['country'];
                }
            }

            // Auto-calc region server-side (if column exists + helper exists)
            if (in_array('region', $cols_lc, true) && function_exists('coai_region_from_location')) {
                $state = isset($data['state']) ? (string)$data['state'] : (string)($_POST['state'] ?? '');
                $state = strtoupper(trim(sanitize_text_field(wp_unslash($state))));

                $country_code = '';
                if (isset($data['country'])) {
                    $country_code = strtoupper(trim((string)$data['country']));
                } else {
                    $country_code = strtoupper(trim(sanitize_text_field((string)($_POST['country'] ?? ''))));
                }
                if ($country_code === 'USA') $country_code = 'US';
                if ($country_code === '') $country_code = 'US';

                $calc = (string)coai_region_from_location($state, $country_code);
                if ($calc !== '') {
                    $data['region'] = $calc;
                    // Add format if region wasn't already included
                    if (!in_array('region', array_map('strtolower', array_keys($data)), true)) {
                        $format[] = '%s';
                    }
                }
            }
            
                        // Save existing family members.
            if (!empty($_POST['family_existing']) && is_array($_POST['family_existing'])) {
                $family_table = coai_pf_family_members_table_name();

                foreach ($_POST['family_existing'] as $family_id_raw => $family_post_raw) {
                    $family_id = (int) $family_id_raw;
                    if ($family_id <= 0 || !is_array($family_post_raw)) continue;

                    $family_post = wp_unslash($family_post_raw);

                    if (!empty($family_post['delete'])) {
                        $wpdb->delete(
                            $family_table,
                            [
                                'id'                => $family_id,
                                'primary_member_id' => $member_id,
                            ],
                            ['%d', '%d']
                        );
                        continue;
                    }

                    $family_first = sanitize_text_field($family_post['first_name'] ?? '');
                    $family_last  = sanitize_text_field($family_post['last_name'] ?? '');

                    if ($family_first === '' && $family_last === '') continue;

                    $family_birthday = '';
                    if (!empty($family_post['birthday'])) {
                        $family_birthday = preg_replace('~[^0-9\-]~', '', (string)$family_post['birthday']);
                    }

                    $wpdb->update(
                        $family_table,
                        [
                            'first_name'   => $family_first,
                            'last_name'    => $family_last,
                            'relationship' => sanitize_text_field($family_post['relationship'] ?? ''),
                            'email'        => sanitize_email($family_post['email'] ?? ''),
                            'phone'        => sanitize_text_field($family_post['phone'] ?? ''),
                            'birthday'     => $family_birthday !== '' ? $family_birthday : null,
                            'status'       => 'ACTIVE',
                        ],
                        [
                            'id'                => $family_id,
                            'primary_member_id' => $member_id,
                        ],
                        ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                        ['%d', '%d']
                    );
                }

                $updated = true;
                $notice = 'Saved.';
            }

            // Add new family member if entered.
            $family_new_first = sanitize_text_field(wp_unslash($_POST['family_new_first_name'] ?? ''));
            $family_new_last  = sanitize_text_field(wp_unslash($_POST['family_new_last_name'] ?? ''));

            if ($family_new_first !== '' || $family_new_last !== '') {
                $family_table = coai_pf_family_members_table_name();

                $family_new_birthday = '';
                if (!empty($_POST['family_new_birthday'])) {
                    $family_new_birthday = preg_replace('~[^0-9\-]~', '', (string)wp_unslash($_POST['family_new_birthday']));
                }

                $wpdb->insert(
                    $family_table,
                    [
                        'primary_member_id' => $member_id,
                        'first_name'        => $family_new_first,
                        'last_name'         => $family_new_last,
                        'relationship'      => sanitize_text_field(wp_unslash($_POST['family_new_relationship'] ?? '')),
                        'email'             => sanitize_email(wp_unslash($_POST['family_new_email'] ?? '')),
                        'phone'             => sanitize_text_field(wp_unslash($_POST['family_new_phone'] ?? '')),
                        'birthday'          => $family_new_birthday !== '' ? $family_new_birthday : null,
                        'status'            => 'ACTIVE',
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );

                $updated = true;
                $notice = 'Saved.';
            }

            if (!empty($data)) {
                $ok = $wpdb->update(
                    $table,
                    $data,
                    ['member_id' => $member_id],
                    $format,
                    ['%d']
                );

                if ($ok !== false) {
                    $updated = true;

                    if ($photo_error === '') {
                        $notice = 'Saved.';
                    }

                    // Keep WP display_name sensible
                    if (!empty($data['full_name'])) {
                        wp_update_user([
                            'ID'           => $wp_uid,
                            'display_name' => $data['full_name'],
                        ]);
                    } elseif (
                        !empty($data['first_name']) ||
                        !empty($data['last_name'])
                    ) {
                        $dn = trim(
                            ($data['first_name'] ?? '') .
                            ' ' .
                            ($data['last_name'] ?? '')
                        );

                        if ($dn !== '') {
                            wp_update_user([
                                'ID'           => $wp_uid,
                                'display_name' => $dn,
                            ]);
                        }
                    } elseif (!empty($data['username'])) {
                        wp_update_user([
                            'ID'           => $wp_uid,
                            'display_name' => $data['username'],
                        ]);
                    }
                } else {
                    $notice =
                        'No changes saved, or the profile update failed.';
                }
            } elseif ($photo_error === '' && !$updated) {
                $notice = 'No editable changes detected.';
            }

            /*
             * A photo-upload failure must take priority over
             * regular profile-save messages.
             */
            if ($photo_error !== '') {
                $notice = $photo_error;
                $updated = false;
            }
        }

        // Fetch fresh row for display
        
        $me = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `$table` WHERE member_id=%d LIMIT 1", $member_id),
            ARRAY_A
        );
        if (!$me) return '<p style="color:#b91c1c;">Profile not found.</p>';

        // Helpers
        $v = fn($k) => esc_attr($me[$k] ?? '');
        $fmtDate = function ($raw) {
            if (empty($raw)) return '—';
            $ts = strtotime((string)$raw);
            return $ts ? date('M j, Y', $ts) : esc_html((string)$raw);
        };

        // Friendly read-only membership info (from your exact column names)
        $coai_no    = $me['COAI_number'] ?? ($me['coai_number'] ?? null);
        $pay_amount = $me['payment_amount'] ?? null;
        $expires    = $me['membership_expiration'] ?? null;   // datetime
        $ins_status = $me['insurance_status'] ?? null;

        // Membership level name (fixed)
        $level_name = '';
        $level_id   = isset($me['membership_level_id']) ? (int)$me['membership_level_id'] : 0;
        if ($level_id > 0) {
            if (function_exists('coai_get_level_name')) {
                $level_name = (string)coai_get_level_name($level_id);
            } else {
                // Fallback: direct lookup with tolerant PK detection (ID/id)
                $levels_table = function_exists('coai_get_levels_table') ? coai_get_levels_table() : ($wpdb->prefix . 'membership_levels');
                $pk = function_exists('coai_get_levels_pk') ? coai_get_levels_pk() : 'ID';
                $level_name = (string)$wpdb->get_var(
                    $wpdb->prepare("SELECT name FROM `$levels_table` WHERE `$pk`=%d LIMIT 1", $level_id)
                );
            }
        }

        $full_name_display = $me['full_name'] ?? trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
        $family_members = coai_pf_get_family_members_for_member((int)$member_id);

        ob_start();
        ?>
        <div class="coai-profile-card" style="max-width:760px;margin:2rem auto 1rem;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
          <h2 style="margin:0 0 .75rem;">Your Profile</h2>

          <p style="margin:.25rem 0 1rem;">
            <a class="button" href="<?php echo esc_url(home_url('/member-portal/')); ?>" style="text-decoration:none;">← Back to Portal</a>
          </p>

          <?php if ($updated): ?>
            <div style="padding:.5rem;border:1px solid #16a34a;background:#dcfce7;border-radius:8px;margin-bottom:.75rem;"><?php echo esc_html($notice ?: 'Saved.'); ?></div>
          <?php elseif ($notice !== ''): ?>
            <div style="padding:.5rem;border:1px solid #d1d5db;background:#f8fafc;border-radius:8px;margin-bottom:.75rem;"><?php echo esc_html($notice); ?></div>
          <?php endif; ?>

          <form
              method="post"
              enctype="multipart/form-data"
          >
            <?php
            wp_nonce_field(
                'coai_update_profile',
                '_coai_nonce'
            );
            ?>
            
            <!-- Profile Photo -->
            <section
                style="
                    display:flex;
                    align-items:center;
                    gap:20px;
                    margin-bottom:1.25rem;
                    padding:18px;
                    border:1px solid #e5e7eb;
                    border-radius:12px;
                    background:#f9fafb;
                    flex-wrap:wrap;
                "
            >
            <div
                style="
                    flex:0 0 170px;
                    text-align:center;
                "
            >
                <?php
                $stored_photo_x = get_user_meta(
                    $wp_uid,
                    'coai_profile_photo_x',
                    true
                );

                $stored_photo_y = get_user_meta(
                    $wp_uid,
                    'coai_profile_photo_y',
                    true
                );

                $profile_photo_x = (
                    $stored_photo_x === '' ||
                    (int) $stored_photo_x < 0 ||
                    (int) $stored_photo_x > 100
                )
                    ? 50
                    : (int) $stored_photo_x;

                $profile_photo_y = (
                    $stored_photo_y === '' ||
                    (int) $stored_photo_y < 0 ||
                    (int) $stored_photo_y > 100
                )
                    ? 50
                    : (int) $stored_photo_y;
                ?>

                <input
                    id="coai-profile-photo-x"
                    type="hidden"
                    name="coai_profile_photo_x"
                    value="<?php echo esc_attr($profile_photo_x); ?>"
                >

                <input
                    id="coai-profile-photo-y"
                    type="hidden"
                    name="coai_profile_photo_y"
                    value="<?php echo esc_attr($profile_photo_y); ?>"
                >

                <div
                    id="coai-profile-photo-preview"
                    class="coai-profile-photo-positioner"
                    role="group"
                    aria-label="Profile photo position preview"
                    title="Drag the photo to reposition it"
                >
                    <?php
                    echo coai_get_profile_photo_html(
                        $wp_uid,
                        120,
                        'coai-profile-photo-preview-image'
                    );
                    ?>
                </div>

                <p class="coai-profile-photo-instructions">
                    Drag the photo inside the circle.
                </p>

                <button
                    type="button"
                    id="coai-profile-photo-center"
                    class="button"
                >
                    Center Photo
                </button>
            </div>
            
                <div style="flex:1 1 280px;">
                    <h3 style="margin:0 0 .4rem;">
                        Profile Photo
                    </h3>

                    <p
                        style="
                            margin:0 0 .75rem;
                            color:#4b5563;
                        "
                    >
                        Upload a favorite photo of yourself or
                        your clown character.
                    </p>

                    <label
                        for="coai_profile_photo"
                        style="
                            display:block;
                            margin-bottom:.3rem;
                            font-weight:600;
                        "
                    >
                        Choose Photo
                    </label>

                    <input
                        id="coai_profile_photo"
                        type="file"
                        name="coai_profile_photo"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <small
                        style="
                            display:block;
                            margin-top:.4rem;
                            color:#6b7280;
                        "
                    >
                        JPG, PNG, or WebP. Maximum file size:
                        5 MB. Square photos work best.
                    </small>

                    <?php
                    if (
                        coai_get_profile_photo_id($wp_uid) > 0
                    ):
                    ?>
                        <label
                            style="
                                display:flex;
                                align-items:center;
                                gap:.45rem;
                                margin-top:.8rem;
                                color:#b91c1c;
                            "
                        >
                            <input
                                type="checkbox"
                                name="coai_remove_profile_photo"
                                value="1"
                            >

                            Remove current photo
                        </label>
                    <?php endif; ?>

                    <div
                        style="
                            margin-top:1rem;
                            display:flex;
                            gap:.5rem;
                            flex-wrap:wrap;
                        "
                    >
                        <button
                            type="submit"
                            name="coai_profile_submit"
                            value="1"
                            class="button button-primary"
                            style="
                                padding:.6rem 1rem;
                                border-radius:8px;
                                border:1px solid #d1d5db;
                                background:#f8fafc;
                                font-weight:700;
                            "
                        >
                            Save Profile Photo
                        </button>
                    </div>

                    <small
                        style="
                            display:block;
                            margin-top:.5rem;
                            color:#6b7280;
                        "
                    >
                        Save after choosing, positioning, replacing,
                        or removing your photo.
                    </small>
                </div>
            </section>

            <style>
                .coai-profile-photo-positioner {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 120px;
                    height: 120px;
                    overflow: hidden;
                    border: 2px solid #ffffff;
                    border-radius: 50%;
                    background: #ffffff;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, .16);
                    cursor: grab;
                    touch-action: none;
                    user-select: none;
                }

                .coai-profile-photo-positioner.is-dragging {
                    cursor: grabbing;
                    box-shadow:
                        0 0 0 3px rgba(185, 28, 28, .18),
                        0 3px 12px rgba(0, 0, 0, .2);
                }

                .coai-profile-photo-preview-image {
                    display: block;
                    width: 120px;
                    height: 120px;
                    max-width: none;
                    flex: 0 0 120px;
                    object-fit: cover;
                    border-radius: 50%;
                    pointer-events: none;
                    user-select: none;
                }

                #coai-profile-photo-preview
                .coai-profile-photo-placeholder {
                    width: 120px !important;
                    height: 120px !important;
                    border: 0 !important;
                    pointer-events: none;
                }

                .coai-profile-photo-instructions {
                    margin: 10px 0 6px;
                    color: #4b5563;
                    font-size: 14px;
                }
                
                #coai-profile-photo-center {
                    margin-top: 2px;
                }

                @media (max-width: 600px) {
                #coai-profile-photo-preview {
                    margin-left: auto;
                    margin-right: auto;
                }

                .coai-profile-photo-instructions {
                    text-align: center;
                }
            }
        </style>
            
        <script>
    (function () {
    'use strict';

    var fileInput = document.getElementById(
        'coai_profile_photo'
    );

    var preview = document.getElementById(
        'coai-profile-photo-preview'
    );

    var positionXInput = document.getElementById(
        'coai-profile-photo-x'
    );

    var positionYInput = document.getElementById(
        'coai-profile-photo-y'
    );

    var centerButton = document.getElementById(
        'coai-profile-photo-center'
    );

    if (
        !fileInput ||
        !preview ||
        !positionXInput ||
        !positionYInput
    ) {
        return;
    }

    var isDragging = false;
    var startPointerX = 0;
    var startPointerY = 0;
    var startPositionX = 50;
    var startPositionY = 50;

    function clamp(value) {
        return Math.max(
            0,
            Math.min(100, value)
        );
    }

    function getPreviewImage() {
        return preview.querySelector(
            '.coai-profile-photo-preview-image'
        );
    }

    function applyPosition() {
        var image = getPreviewImage();

        if (!image) {
            return;
        }

        var positionX = clamp(
            parseFloat(positionXInput.value)
        );

        var positionY = clamp(
            parseFloat(positionYInput.value)
        );

        if (Number.isNaN(positionX)) {
            positionX = 50;
        }

        if (Number.isNaN(positionY)) {
            positionY = 50;
        }

        image.style.objectFit = 'cover';

        image.style.objectPosition =
            positionX + '% ' + positionY + '%';
    }

    function centerPhoto() {
        positionXInput.value = '50';
        positionYInput.value = '50';

        applyPosition();
    }

    fileInput.addEventListener('change', function () {
        var file = fileInput.files &&
            fileInput.files.length
                ? fileInput.files[0]
                : null;

        if (!file) {
            return;
        }

        var allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        if (!allowedTypes.includes(file.type)) {
            window.alert(
                'Please select a JPG, PNG, or WebP image.'
            );

            fileInput.value = '';
            return;
        }

        var maximumSize = 5 * 1024 * 1024;

        if (file.size > maximumSize) {
            window.alert(
                'The profile photo must be 5 MB or smaller.'
            );

            fileInput.value = '';
            return;
        }

        var reader = new FileReader();

        reader.addEventListener('load', function (event) {
            var image = document.createElement('img');

            image.src = event.target.result;
            image.alt = 'Selected profile photo';
            image.className =
                'coai-profile-photo-preview-image';

            preview.innerHTML = '';
            preview.appendChild(image);

            centerPhoto();
        });

        reader.readAsDataURL(file);
    });

    preview.addEventListener(
        'pointerdown',
        function (event) {
            if (!getPreviewImage()) {
                return;
            }

            isDragging = true;

            startPointerX = event.clientX;
            startPointerY = event.clientY;

            startPositionX =
                parseFloat(positionXInput.value);

            startPositionY =
                parseFloat(positionYInput.value);

            if (Number.isNaN(startPositionX)) {
                startPositionX = 50;
            }

            if (Number.isNaN(startPositionY)) {
                startPositionY = 50;
            }

            preview.classList.add('is-dragging');

            preview.setPointerCapture(
                event.pointerId
            );

            event.preventDefault();
        }
    );

    preview.addEventListener(
        'pointermove',
        function (event) {
            if (!isDragging) {
                return;
            }

            var bounds = preview.getBoundingClientRect();

            var changeX =
                (
                    event.clientX -
                    startPointerX
                ) /
                bounds.width *
                100;

            var changeY =
                (
                    event.clientY -
                    startPointerY
                ) /
                bounds.height *
                100;

            positionXInput.value = String(
                Math.round(
                    clamp(startPositionX - changeX)
                )
            );

            positionYInput.value = String(
                Math.round(
                    clamp(startPositionY - changeY)
                )
            );

            applyPosition();
        }
    );

    function stopDragging(event) {
        if (!isDragging) {
            return;
        }

        isDragging = false;

        preview.classList.remove('is-dragging');

        if (
            event &&
            preview.hasPointerCapture(event.pointerId)
        ) {
            preview.releasePointerCapture(
                event.pointerId
            );
        }
    }

    preview.addEventListener(
        'pointerup',
        stopDragging
    );

    preview.addEventListener(
        'pointercancel',
        stopDragging
    );

    if (centerButton) {
        centerButton.addEventListener(
            'click',
            centerPhoto
        );
    }

    applyPosition();
})();
</script>

            <!-- Basics -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
              <div><label>Username</label><input type="text" name="username" value="<?php echo $v('username'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>
              <div><label>Email</label><input type="email" name="email" value="<?php echo $v('email'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>

              <div><label>Full Name</label><input type="text" name="full_name" value="<?php echo esc_attr($full_name_display); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>
              <div><label>Clown Name</label><input type="text" name="clown_name" value="<?php echo $v('clown_name'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>

              <div><label>First Name</label><input type="text" name="first_name" value="<?php echo $v('first_name'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>
              <div><label>Last Name</label><input type="text" name="last_name" value="<?php echo $v('last_name'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>

              <div><label>Phone</label><input type="text" name="mobile" value="<?php echo $v('mobile'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>

              <div><label>Birthday</label><input type="date" name="birthday" value="<?php echo $v('birthday'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>
              <div><label>Alley Membership</label><input type="text" name="alley_membership" value="<?php echo $v('alley_membership'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>
            </div>

            <!-- Address -->
            <div style="margin-top:1rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
              <div style="grid-column:1 / -1;"><label>Address</label><input type="text" name="address" value="<?php echo $v('address'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>
              <div style="grid-column:1 / -1;"><label>Address 2</label><input type="text" name="address2" value="<?php echo $v('address2'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;"></div>

              <div><label>City</label>
                <input type="text" name="city" value="<?php echo $v('city'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
              </div>

              <div><label>State</label>
                <input id="coai_state" type="text" name="state" value="<?php echo $v('state'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
              </div>

              <div><label>Zip</label>
                <input type="text" name="zip" value="<?php echo $v('zip'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
              </div>

              <div>
                <label>Country</label>
                <input id="coai_country" type="text" name="country" value="<?php echo $v('country'); ?>" style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                <?php if (function_exists('coai_country')): ?>
                  <small style="display:block;opacity:.75;margin-top:.25rem;"><?php echo esc_html(coai_country($me['country'] ?? '', 'label') ?: ''); ?></small>
                <?php endif; ?>
              </div>

              <div><label>Region (auto)</label>
                <input id="coai_region" type="text" name="region" value="<?php echo $v('region'); ?>" readonly
                       style="background:#f9fafb;width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
              </div>
            </div>
            
                        <!-- Family Members -->
            <hr style="margin:1.25rem 0;border:none;border-top:1px solid #e5e7eb;">
            <h3 style="margin:0 0 .5rem;">Family Members</h3>
            <p style="margin:0 0 .75rem;color:#6b7280;font-size:.9rem;">
              Add or update the family members connected to your membership.
            </p>

            <?php if (!empty($family_members)): ?>
              <?php foreach ($family_members as $family): ?>
                <div style="border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin-bottom:12px;background:#f9fafb;">
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div>
                      <label>Family First Name</label>
                      <input name="family_existing[<?php echo esc_attr((int)$family['id']); ?>][first_name]" type="text"
                             value="<?php echo esc_attr($family['first_name'] ?? ''); ?>"
                             style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>

                    <div>
                      <label>Family Last Name</label>
                      <input name="family_existing[<?php echo esc_attr((int)$family['id']); ?>][last_name]" type="text"
                             value="<?php echo esc_attr($family['last_name'] ?? ''); ?>"
                             style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>

                    <div>
                      <label>Relationship</label>
                      <input name="family_existing[<?php echo esc_attr((int)$family['id']); ?>][relationship]" type="text"
                             value="<?php echo esc_attr($family['relationship'] ?? ''); ?>"
                             placeholder="Spouse, Child, etc."
                             style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>

                    <div>
                      <label>Email</label>
                      <input name="family_existing[<?php echo esc_attr((int)$family['id']); ?>][email]" type="email"
                             value="<?php echo esc_attr($family['email'] ?? ''); ?>"
                             style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>

                    <div>
                      <label>Phone</label>
                      <input name="family_existing[<?php echo esc_attr((int)$family['id']); ?>][phone]" type="text"
                             value="<?php echo esc_attr($family['phone'] ?? ''); ?>"
                             style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>

                    <div>
                      <label>Birthday</label>
                      <input name="family_existing[<?php echo esc_attr((int)$family['id']); ?>][birthday]" type="date"
                             value="<?php echo esc_attr(!empty($family['birthday']) ? date('Y-m-d', strtotime((string)$family['birthday'])) : ''); ?>"
                             style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                    </div>
                  </div>

                  <label style="margin-top:.65rem;display:flex;align-items:center;gap:.4rem;color:#b91c1c;">
                    <input type="checkbox" name="family_existing[<?php echo esc_attr((int)$family['id']); ?>][delete]" value="1">
                    Remove this family member
                  </label>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <div style="border:1px dashed #d1d5db;border-radius:10px;padding:12px;margin-top:12px;">
              <h4 style="margin:0 0 .75rem;">Add Family Member</h4>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div>
                  <label>Family First Name</label>
                  <input name="family_new_first_name" type="text"
                         style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                </div>

                <div>
                  <label>Family Last Name</label>
                  <input name="family_new_last_name" type="text"
                         style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                </div>

                <div>
                  <label>Relationship</label>
                  <input name="family_new_relationship" type="text" placeholder="Spouse, Child, etc."
                         style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                </div>

                <div>
                  <label>Email</label>
                  <input name="family_new_email" type="email"
                         style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                </div>

                <div>
                  <label>Phone</label>
                  <input name="family_new_phone" type="text"
                         style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                </div>

                <div>
                  <label>Birthday</label>
                  <input name="family_new_birthday" type="date"
                         style="width:100%;padding:.5rem;border:1px solid #d1d5db;border-radius:8px;">
                </div>
              </div>
            </div>

            <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;">
              <button type="submit" name="coai_profile_submit" value="1"
                      class="button button-primary"
                      style="padding:.5rem .75rem;border-radius:8px;border:1px solid #d1d5db;background:#f8fafc;">
                Save Changes
              </button>

              <a class="button" href="<?php echo esc_url(home_url('/member-portal/')); ?>"
                 style="text-decoration:none;padding:.5rem .75rem;border-radius:8px;border:1px solid #d1d5db;background:#fff;">
                ← Back to Portal
              </a>
            </div>

            <input type="hidden" id="coai_region_ajax_nonce" value="<?php echo esc_attr(wp_create_nonce('coai_region_ajax')); ?>">
          </form>

          <script>
          (function(){
            var st = document.getElementById('coai_state');
            var co = document.getElementById('coai_country');
            var rg = document.getElementById('coai_region');
            var n  = document.getElementById('coai_region_ajax_nonce');
            if (!st || !co || !rg || !n) return;

            function debounce(fn, ms){ let t; return function(){ clearTimeout(t); t=setTimeout(()=>fn.apply(this, arguments), ms); }; }

            function updateRegion(){
              var state   = (st.value || '').trim().toUpperCase();
              var country = (co.value || '').trim().toUpperCase() || 'US';
              if (country === 'USA') country = 'US';
              var nonce   = n.value;

              var params = new URLSearchParams();
              params.set('action', 'coai_region_lookup');
              params.set('nonce',  nonce);
              params.set('state',  state);
              params.set('country',country);

              fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: params.toString()
              })
              .then(r => r.json())
              .then(data => { if (data && data.success && data.data) { rg.value = data.data.region || ''; } })
              .catch(()=>{});
            }

            var onChange = debounce(updateRegion, 250);
            st.addEventListener('input', onChange);
            co.addEventListener('input', onChange);
            st.addEventListener('change', onChange);
            co.addEventListener('change', onChange);
          })();
          </script>
        </div>

        <!-- Read-only membership info -->
        <div class="coai-profile-card" style="max-width:760px;margin:0 auto 2rem;padding:1.25rem;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
          <h3 style="margin:0 0 .5rem;">Membership Info</h3>
          <dl style="display:grid;grid-template-columns:180px 1fr;gap:.5rem 1rem;margin:0;">
            <dt>COAI #</dt><dd><?php echo $coai_no ? esc_html($coai_no) : '—'; ?></dd>
            <dt>Membership Level</dt><dd><?php echo $level_name !== '' ? esc_html($level_name) : '—'; ?></dd>
            <dt>Payment Amount</dt>
            <dd><?php echo ($pay_amount !== null && $pay_amount !== '') ? esc_html(number_format((float)$pay_amount, 2)) : '—'; ?></dd>
            <dt>Membership Expiration</dt><dd><?php echo $expires ? $fmtDate($expires) : '—'; ?></dd>
            <dt>Insurance Status</dt><dd><?php echo $ins_status ? esc_html($ins_status) : '—'; ?></dd>
          </dl>
        </div>
        <?php
        return ob_get_clean();
    }
}

add_shortcode('coai_profile_form', 'coai_render_member_profile_form');
add_shortcode('member_profile_form', 'coai_render_member_profile_form'); // legacy alias
