<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * COAI Menu Login Panel
 * ============================================================
 *
 * Purpose:
 *     Provide immediate access to the existing member login
 *     form when a logged-out visitor selects Log In from the
 *     primary navigation menu.
 *
 * Responsibilities:
 *     - Render the existing COAI login form
 *     - Open the form from the navigation Login button
 *     - Position the panel near the upper-right navigation
 *     - Provide accessible keyboard and close behavior
 *     - Preserve the full Member Login page as a fallback
 *
 * Does NOT:
 *     - Authenticate members directly
 *     - Duplicate login business rules
 *     - Replace the Member Login page
 *     - Change logged-in member routing
 * ============================================================
 */

add_action('wp_footer', function () {

    if (is_admin()) {
        return;
    }

    /*
     * -------------------------------------------------
     * Logged-In Account Panel
     * -------------------------------------------------
     */

    if (is_user_logged_in()) {

        $current_user = wp_get_current_user();

        $first_name = function_exists(
            'coai_get_current_person_first_name'
        )
            ? coai_get_current_person_first_name()
            : 'Member';

        ?>
        <div
            id="coai-account-overlay"
            class="coai-account-overlay"
            hidden
        >
            <div
                id="coai-account-panel"
                class="coai-account-panel"
                role="dialog"
                aria-modal="true"
                aria-labelledby="coai-account-title"
                tabindex="-1"
            >
                <button
                    type="button"
                    class="coai-account-close"
                    aria-label="Close account menu"
                >
                    &times;
                </button>

                <div class="coai-account-identity">

        <div class="coai-account-photo-control">

            <a
                class="coai-account-photo-link"
                href="<?php echo esc_url(
                    home_url('/profile/')
                ); ?>"
                aria-label="View or change your profile photo"
                title="View or change your profile photo"
            >
                <span class="coai-account-photo">
                    <?php
                    if (
                        function_exists(
                            'coai_get_profile_photo_html'
                        )
                    ) {
                        echo coai_get_profile_photo_html(
                            $current_user->ID,
                            88,
                            'coai-account-photo-image'
                        );
                    } else {
                        echo '<span aria-hidden="true">👤</span>';
                    }
                    ?>
                </span>

                <span class="coai-account-photo-action">
                       Change photo
                </span>
            </a>

        </div>

        <div>
            <div class="coai-account-welcome">
                Welcome
            </div>

            <h2 id="coai-account-title">
                <?php echo esc_html($first_name); ?>
            </h2>
        </div>

                </div>

                <nav
                    class="coai-account-links"
                    aria-label="My account"
                >
                    <a href="<?php echo esc_url(
                        home_url('/member-portal/')
                    ); ?>">
                        Member Portal
                    </a>

                    <a href="<?php echo esc_url(
                        home_url('/profile/')
                    ); ?>">
                        My Profile
                    </a>

                    <a href="<?php echo esc_url(
                        home_url('/change-password/')
                    ); ?>">
                        Change Password
                    </a>

                    <a href="<?php echo esc_url(
                        home_url('/magazine-vault/')
                    ); ?>">
                        Magazine Library
                    </a>

                    <a
                        class="coai-account-logout"
                        href="<?php echo esc_url(
                            wp_logout_url(
                                home_url('/member-login/')
                            )
                        ); ?>"
                    >
                        Log Out
                    </a>
                </nav>
            </div>
        </div>

        <style>
            body.coai-account-panel-open {
                overflow: hidden;
            }

            .coai-account-overlay {
                position: fixed;
                inset: 0;
                z-index: 999999;
                background: rgba(0, 0, 0, .38);
            }

            .coai-account-overlay[hidden] {
                display: none;
            }

            .coai-account-panel {
                position: absolute;
                top: 88px;
                right: 24px;
                width: min(360px, calc(100vw - 32px));
                padding: 24px;
                background: #ffffff;
                border: 1px solid #d1d5db;
                border-radius: 14px;
                box-shadow:
                    0 20px 45px rgba(0, 0, 0, .22),
                    0 4px 12px rgba(0, 0, 0, .12);
            }

            .coai-account-close {
                position: absolute;
                top: 12px;
                right: 12px;
                width: 40px;
                height: 40px;
                padding: 0;
                border: 1px solid #d1d5db;
                border-radius: 50%;
                background: #f9fafb;
                color: #111827;
                font-size: 28px;
                line-height: 36px;
                cursor: pointer;
            }

            .coai-account-identity {
                display: flex;
                align-items: center;
                gap: 16px;
                padding-right: 40px;
                margin-bottom: 20px;
            }

            .coai-account-photo-control {
                flex: 0 0 96px;
                text-align: center;
            }

            .coai-account-photo-link {
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                gap: 6px;
                color: #4b5563;
                text-decoration: none;
            }

            .coai-account-photo {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 88px;
                height: 88px;
                overflow: hidden;
                border-radius: 50%;
                background: #f3f4f6;
                border: 2px solid #ffffff;
                box-shadow: 0 2px 9px rgba(0, 0, 0, .18);
                font-size: 38px;
                transition:
                    transform .15s ease,
                    box-shadow .15s ease;
            }

            .coai-account-photo-image {
                display: block;
                width: 88px;
                height: 88px;
                object-fit: cover;
                border-radius: 50%;
            }

            .coai-account-photo-action {
                font-size: 12px;
                font-weight: 700;
                line-height: 1.2;
            }

            .coai-account-photo-link:hover,
            .coai-account-photo-link:focus {
                color: #b91c1c;
                text-decoration: none;
            }

            .coai-account-photo-link:hover .coai-account-photo,
            .coai-account-photo-link:focus .coai-account-photo {
                transform: scale(1.04);
                box-shadow:
                    0 0 0 3px rgba(185, 28, 28, .18),
                    0 3px 12px rgba(0, 0, 0, .22);
            }

            .coai-account-photo-link:focus {
                outline: none;
            }

            .coai-account-photo-link:focus-visible {
                outline: 3px solid rgba(37, 99, 235, .35);
                outline-offset: 4px;
                border-radius: 8px;
            }

            .coai-account-welcome {
                color: #6b7280;
                font-size: 14px;
            }

            .coai-account-identity h2 {
                margin: 2px 0 0;
                font-size: 26px;
                line-height: 1.2;
            }

            .coai-account-links {
                display: flex;
                flex-direction: column;
                border-top: 1px solid #e5e7eb;
            }

            .coai-account-links a {
                display: block;
                padding: 13px 4px;
                border-bottom: 1px solid #e5e7eb;
                color: #1f2937;
                font-size: 16px;
                font-weight: 600;
                text-decoration: none;
            }

            .coai-account-links a:hover,
            .coai-account-links a:focus {
                padding-left: 10px;
                background: #f9fafb;
            }

            .coai-account-links .coai-account-logout {
                margin-top: 8px;
                border-bottom: 0;
                color: #b91c1c;
            }

            @media (max-width: 782px) {
                .coai-account-overlay {
                    display: flex;
                    align-items: flex-start;
                    justify-content: center;
                    padding: 16px;
                    overflow-y: auto;
                }

                .coai-account-panel {
                    position: relative;
                    top: auto;
                    right: auto;
                    width: 100%;
                    max-width: 430px;
                    margin: 24px auto;
                }
            }
        </style>

        <script>
            (function () {
                'use strict';

                var overlay = document.getElementById(
                    'coai-account-overlay'
                );

                var panel = document.getElementById(
                    'coai-account-panel'
                );

                if (!overlay || !panel) {
                    return;
                }

                var lastTrigger = null;

                function openPanel(trigger) {
                    lastTrigger = trigger || null;
                    overlay.hidden = false;

                    document.body.classList.add(
                        'coai-account-panel-open'
                    );

                    window.setTimeout(function () {
                        panel.focus();
                    }, 10);
                }

                function closePanel() {
                    overlay.hidden = true;

                    document.body.classList.remove(
                        'coai-account-panel-open'
                    );

                    if (lastTrigger) {
                        lastTrigger.focus();
                    }
                }

                document.addEventListener(
                    'click',
                    function (event) {

                        var trigger = event.target.closest(
                            '.coai-account-menu-trigger > a,' +
                            'a.coai-account-menu-trigger,' +
                            '.coai-account-menu-trigger a'
                        );

                        if (trigger) {
                            event.preventDefault();
                            openPanel(trigger);
                            return;
                        }

                        if (
                            event.target === overlay ||
                            event.target.closest(
                                '.coai-account-close'
                            )
                        ) {
                            closePanel();
                        }
                    }
                );

                document.addEventListener(
                    'keydown',
                    function (event) {

                        if (overlay.hidden) {
                            return;
                        }

                        if (event.key === 'Escape') {
                            event.preventDefault();
                            closePanel();
                        }
                    }
                );
            })();
        </script>
        <?php

        return;
    }

    /*
     * -------------------------------------------------
     * Logged-Out Login Panel
     * -------------------------------------------------
     */

    if (!shortcode_exists('coai_login_box')) {
        return;
    }

    ?>
    <div
        id="coai-menu-login-overlay"
        class="coai-menu-login-overlay"
        hidden
    >
        <div
            id="coai-menu-login-panel"
            class="coai-menu-login-panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="coai-menu-login-title"
            tabindex="-1"
        >
            <div class="coai-menu-login-heading">

                <h2 id="coai-menu-login-title">
                    Member Login
                </h2>

                <button
                    type="button"
                    class="coai-menu-login-close"
                    aria-label="Close member login"
                >
                    &times;
                </button>

            </div>

            <div class="coai-menu-login-content">
                <?php echo do_shortcode('[coai_login_box]'); ?>
            </div>

            <p class="coai-menu-login-fallback">
                Having trouble?
                <a href="<?php echo esc_url(home_url('/member-login/')); ?>">
                    Open the full Member Login page
                </a>
            </p>
        </div>
    </div>

    <style>
        body.coai-login-panel-open {
            overflow: hidden;
        }

        .coai-menu-login-overlay {
            position: fixed;
            inset: 0;
            z-index: 999999;
            background: rgba(0, 0, 0, 0.42);
        }

        .coai-menu-login-overlay[hidden] {
            display: none;
        }

        .coai-menu-login-panel {
            position: absolute;
            top: 88px;
            right: 24px;
            width: min(430px, calc(100vw - 32px));
            max-height: calc(100vh - 112px);
            overflow-y: auto;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            box-shadow:
                0 20px 45px rgba(0, 0, 0, 0.22),
                0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .coai-menu-login-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 10px;
        }

        .coai-menu-login-heading h2 {
            margin: 0;
            font-size: 26px;
            line-height: 1.2;
        }

        .coai-menu-login-close {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            padding: 0;
            border: 1px solid #d1d5db;
            border-radius: 50%;
            background: #f9fafb;
            color: #111827;
            font-size: 30px;
            line-height: 40px;
            cursor: pointer;
        }

        .coai-menu-login-close:hover,
        .coai-menu-login-close:focus {
            background: #f3f4f6;
            outline: 3px solid rgba(37, 99, 235, 0.25);
        }

        .coai-menu-login-content .coai-login > h2 {
            display: none;
        }

        .coai-menu-login-content input[type="text"],
        .coai-menu-login-content input[type="email"],
        .coai-menu-login-content input[type="password"] {
            min-height: 48px;
            font-size: 17px;
        }

        .coai-menu-login-content input[type="submit"] {
            min-height: 46px;
            padding-left: 22px !important;
            padding-right: 22px !important;
            font-size: 17px;
            font-weight: 700;
        }

        .coai-menu-login-fallback {
            margin: 16px 0 0;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 14px;
        }

        @media (max-width: 782px) {
            .coai-menu-login-overlay {
                display: flex;
                align-items: flex-start;
                justify-content: center;
                padding: 16px;
                overflow-y: auto;
            }

            .coai-menu-login-panel {
                position: relative;
                top: auto;
                right: auto;
                width: 100%;
                max-width: 500px;
                max-height: none;
                margin: 24px auto;
                padding: 20px;
            }

            .coai-menu-login-content .form-actions {
                align-items: stretch !important;
                flex-direction: column;
            }

            .coai-menu-login-content .form-actions label {
                margin-right: 0 !important;
            }

            .coai-menu-login-content input[type="submit"] {
                width: 100%;
            }

            .coai-menu-login-content .coai-forgot {
                text-align: center !important;
            }
        }
    </style>

    <script>
        (function () {
            'use strict';

            var overlay = document.getElementById(
                'coai-menu-login-overlay'
            );

            var panel = document.getElementById(
                'coai-menu-login-panel'
            );

            if (!overlay || !panel) {
                return;
            }

            var closeButton = overlay.querySelector(
                '.coai-menu-login-close'
            );

            var lastTrigger = null;

            function getTriggers() {
                return document.querySelectorAll(
                    '.coai-menu-login-trigger > a,' +
                    'a.coai-menu-login-trigger,' +
                    '.coai-menu-login-trigger a'
                );
            }

            function openPanel(trigger) {
                lastTrigger = trigger || null;

                overlay.hidden = false;

                document.body.classList.add(
                    'coai-login-panel-open'
                );

                window.setTimeout(function () {
                    var username = panel.querySelector(
                        'input[name="log"]'
                    );

                    if (username) {
                        username.focus();
                    } else {
                        panel.focus();
                    }
                }, 10);
            }

            function closePanel() {
                overlay.hidden = true;

                document.body.classList.remove(
                    'coai-login-panel-open'
                );

                if (lastTrigger) {
                    lastTrigger.focus();
                }
            }

            document.addEventListener('click', function (event) {

                var trigger = event.target.closest(
                    '.coai-menu-login-trigger > a,' +
                    'a.coai-menu-login-trigger,' +
                    '.coai-menu-login-trigger a'
                );

                if (trigger) {
                    event.preventDefault();
                    openPanel(trigger);
                    return;
                }

                if (
                    event.target === overlay ||
                    event.target.closest('.coai-menu-login-close')
                ) {
                    closePanel();
                }
            });

            document.addEventListener('keydown', function (event) {

                if (overlay.hidden) {
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closePanel();
                }
            });

            if (
                window.location.hash === '#coai-menu-login-panel'
            ) {
                openPanel(null);
            }
        })();
    </script>
    <?php

}, 100);