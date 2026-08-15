<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Shortcode
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Presentation
 *
 * Shortcode:
 *     [sof_membership_renewal]
 *
 * Purpose:
 *     Present the current Membership Renewal situation
 *     for the person using MyCOAI.
 *
 * Responsibilities:
 *     - Require Membership identity
 *     - Resolve the logged-in COAI member
 *     - Request a renewal assessment
 *     - Present the expiration date
 *     - Present the recommended business path
 *     - Offer renewal only when SOF permits renewal
 *
 * Does NOT:
 *     - Determine renewal eligibility
 *     - Modify Membership records
 *     - Process payment
 *     - Calculate expiration dates
 *
 * ============================================================
 */

class SOF_MembershipRenewalShortcode
{
    /**
     * COAI Zeffy Membership Renewal destination.
     */
    protected const RENEWAL_URL =
        'https://www.zeffy.com/en-US/ticketing/clowns-of-america-international-incs-renewal-membership';


    /**
     * Register shortcode.
     */
    public static function register(): void
    {
        add_shortcode(
            'sof_membership_renewal',
            [
                self::class,
                'render',
            ]
        );
    }


    /**
     * Render Membership Renewal Experience.
     */
    public static function render(): string
    {
        // -------------------------------------------------
        // Not Logged In
        // -------------------------------------------------

        if (!is_user_logged_in()) {

            $renewal_page =
                home_url(
                    '/renew-membership/'
                );

            $login_url =
                add_query_arg(
                    'redirect_to',
                    $renewal_page,
                    home_url(
                        '/member-login/'
                    )
                );

            ob_start();
            ?>

        <div class="sof-membership-renewal">

            <div class="sof-membership-renewal-workspace">

                <div class="sof-card sof-membership-renewal-card">

                    <h2>
                        Check Your Membership Before Renewing
                    </h2>

                    <p>
                        Before renewing, please log in to MyCOAI so
                        we can check your current membership expiration
                        date.
                    </p>

                    <p>
                        We’ll let you know whether it’s time to renew.
                    </p>

                    <div class="wp-block-buttons sof-card-action">

                        <a
                            class="wp-block-button_link wp_element-button"
                            href="<?php echo esc_url($login_url); ?>"
                        >
                            Existing Member — Log In to Check Renewal
                        </a>

                    </div>

                    <p class="sof-membership-renewal-help">
                        Having trouble logging in?
                        <a href="<?php echo esc_url(
                            home_url(
                                '/member-reset-password-2/'
                            )
                        ); ?>">
                            Reset your password
                        </a>
                        or contact the COAI Office for assistance.
                    </p>

                </div>

            </div>
            
            </div>

            <?php
            return ob_get_clean();
        }


        // -------------------------------------------------
        // Resolve Real Member
        // -------------------------------------------------

        $resolver =
            new SOF_WordPressMemberResolver();

        $member =
            $resolver->resolve_current_member();

        if (!$member) {

            return self::render_unavailable(
                'We could not match your MyCOAI login to a COAI membership record.'
            );
        }


        // -------------------------------------------------
        // Assess Renewal Situation
        // -------------------------------------------------

        $service =
            new SOF_MembershipRenewalService();

        $assessment =
            $service->assess(
                [
                    'status' =>
                        $member['status']
                        ?? '',

                    'membership_expiration' =>
                        $member['membership_expiration']
                        ?? '',
                ]
            );


        // -------------------------------------------------
        // Presentation Values
        // -------------------------------------------------

        $situation =
            (string) (
                $assessment['situation']
                ?? 'unavailable'
            );

        $may_renew =
            !empty(
                $assessment['may_renew']
            );

        $expiration_date =
            self::format_expiration_date(
                (string) (
                    $assessment['expiration_date']
                    ?? ''
                )
            );


        // -------------------------------------------------
        // Human-Facing Situation
        // -------------------------------------------------

        $title =
            'Membership Renewal';

        $summary =
            (string) (
                $assessment['message']
                ?? ''
            );

        if ($situation === 'current') {

            $title =
                'Your Membership Is Current';

            $summary =
                'There is no need to renew your membership at this time.';

        } elseif (
            $situation === 'renewal_window'
        ) {

            $title =
                'It’s Time to Renew';

            $summary =
                'Your membership is approaching its expiration date and may now be renewed.';

        } elseif (
            $situation === 'expired'
        ) {

            $title =
                'Your Membership Has Expired';

            $summary =
                'Your membership has expired and may now be renewed.';

        } elseif (
            $situation === 'deceased'
        ) {

            return self::render_unavailable(
                'Renewal is not available for this membership record.'
            );

        } elseif (
            $situation === 'unavailable'
        ) {

            return self::render_unavailable(
                'We could not determine your current membership expiration date. Please contact the COAI Office.'
            );
        }

        // -------------------------------------------------
        // Render
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-membership-renewal">

            <div class="sof-card sof-membership-renewal-card">

                <h2>
                    <?php echo esc_html($title); ?>
                </h2>

                <?php
                if ($expiration_date !== '') {
                    ?>

                    <div class="sof-membership-renewal-evidence">

                        <div class="sof-membership-expiration-label">
                            Membership Expiration Date
                        </div>

                        <div class="sof-membership-expiration-date">
                            <?php
                            echo esc_html(
                                $expiration_date
                            );
                            ?>
                        </div>

                    </div>

                    <?php
                }
                ?>

                <p>
                    <?php echo esc_html($summary); ?>
                </p>

                <div class="wp-block-buttons sof-card-action">

                    <?php
                    if ($may_renew) {
                        ?>

                        <a
                            class="wp-block-button__link wp-element-button"
                            href="<?php
                            echo esc_url(
                                self::RENEWAL_URL
                            );
                            ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            Renew My Membership
                        </a>

                        <a
                            class="wp-block-button__link wp-element-button sof-membership-secondary-action"
                            href="<?php
                            echo esc_url(
                                home_url(
                                    '/member-portal/'
                                )
                            );
                            ?>"
                        >
                            Return to Member Portal
                        </a>

                        <?php
                    } else {
                        ?>

                        <a
                            class="wp-block-button__link wp-element-button"
                            href="<?php
                            echo esc_url(
                                home_url(
                                    '/member-portal/'
                                )
                            );
                            ?>"
                        >
                            Return to Member Portal
                        </a>

                        <?php
                    }
                    ?>

                </div>

            </div>

        </div>

        <?php
        return ob_get_clean();
    }


    /**
     * Render a situation SOF cannot safely assess.
     */
    protected static function render_unavailable(
        string $message
    ): string {

        ob_start();
        ?>

        <div class="sof-membership-renewal">

            <div class="sof-membership-renewal-card">

                <h2>
                    Membership Renewal
                </h2>

                <p>
                    <?php echo esc_html($message); ?>
                </p>

                <div class="sof-membership-renewal-actions">

                    <a
                        class="button"
                        href="mailto:coaioffice@mycoai.com"
                    >
                        Contact the COAI Office
                    </a>

                </div>

            </div>

        </div>

        <?php
        return ob_get_clean();
    }


    /**
     * Format normalized calendar date for humans.
     */
    protected static function format_expiration_date(
        string $date
    ): string {

        $date =
            trim($date);

        if ($date === '') {
            return '';
        }

        $timestamp =
            strtotime($date);

        if (!$timestamp) {
            return '';
        }

        return wp_date(
            get_option(
                'date_format'
            ),
            $timestamp
        );
    }
}


SOF_MembershipRenewalShortcode::register();