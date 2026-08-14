<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Compose Workspace Shortcode
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Presentation:
 *     Compose Workspace Shortcode
 *
 * Purpose:
 *     Provide a WordPress entry point for the SOF
 *     Compose Workspace.
 *
 * Responsibilities:
 *     - Register the Compose Workspace shortcode
 *     - Confirm the user is logged in
 *     - Render the Compose Workspace
 *
 * Does NOT:
 *     - Determine communication audiences
 *     - Apply communication lifecycle rules
 *     - Process submitted communication information
 *     - Send communications
 *     - Build the workspace experience
 *
 * ============================================================
 */

class SOF_ComposeWorkspaceShortcode
{
    /**
     * WordPress shortcode name.
     */
    protected const SHORTCODE = 'sof_compose_workspace';

    /**
     * Register the shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            self::SHORTCODE,
            [self::class, 'render']
        );
    }

    /**
     * Render the shortcode.
     *
     * @param array<string, mixed> $attributes
     */
    public static function render(
        array $attributes = []
    ): string {
        if (!is_user_logged_in()) {
            return self::render_login_required();
        }

        $workspace = new SOF_ComposeWorkspace();

        return $workspace->render();
    }

    /**
     * Render the login-required message.
     */
    protected static function render_login_required(): string
    {
        $login_url = home_url('/member-login/');

        ob_start();
        ?>

        <section class="sof-card sof-access-required-card">

            <h2>Member Access Required</h2>

            <p>
                Please log in to compose a communication.
            </p>

            <a
                class="sof-button sof-button-primary"
                href="<?php echo esc_url($login_url); ?>"
            >
                Member Login
            </a>

        </section>

        <?php

        return (string) ob_get_clean();
    }
}