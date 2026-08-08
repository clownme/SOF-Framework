<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Send Communication Workspace
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Workspace:
 *     Send Communication
 *
 * Purpose:
 *     Present an approved Communication for immediate release.
 *
 * Responsibilities:
 *     - Load a persisted approved Communication
 *     - Present the final delivery details
 *     - Present sender identity
 *     - Confirm immediate release intent
 *
 * Does NOT:
 *     - Compose communication content
 *     - Verify communications
 *     - Test communications
 *     - Approve communications
 *     - Deliver communications directly
 *     - Communicate directly with providers
 *
 * ============================================================
 */

class SOF_SendWorkspace
{
    /**
     * Render the Send Communication Workspace.
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to send a communication.</p>';
        }

        // -------------------------------------------------
        // Communication Identity
        // -------------------------------------------------

        $communication_id =
            isset($_GET['communication_id'])
                ? absint($_GET['communication_id'])
                : 0;

        if ($communication_id < 1) {
            return '<p>No communication was selected for delivery.</p>';
        }

        // -------------------------------------------------
        // Communication Persistence
        // -------------------------------------------------

        $repository =
            new SOF_CommunicationRepository();

        $persistence_service =
            new SOF_CommunicationPersistenceService(
                $repository
            );

        $communication =
            $persistence_service->find(
                $communication_id
            );

        if (!$communication) {
            return '<p>The communication could not be found.</p>';
        }

        // -------------------------------------------------
        // Lifecycle Guard
        // -------------------------------------------------

        if ($communication->get_status() !== 'approved') {
            return '<p>The communication must be approved before it can be released.</p>';
        }

        // -------------------------------------------------
        // Sender
        // -------------------------------------------------

        $audience_service =
            new SOF_CommunicationAudienceService();

        $sender_service =
            new SOF_CommunicationSenderService(
                $audience_service
            );

        $sender =
            $sender_service->resolve_current_sender();

        if (!$sender) {
            return '<p>The communication sender could not be resolved.</p>';
        }
        
        // -------------------------------------------------
        // Release Result
        // -------------------------------------------------

        $release_message = '';
        $release_warning = '';
        $release_error = '';
        $release_result = null;


        // -------------------------------------------------
        // Release Audience
        // -------------------------------------------------

        $approved_recipient_count =
            $communication->get_recipient_count();

        $membership_audience_service =
            new SOF_MembershipAudienceService();

        $eligibility_service =
            new SOF_CommunicationRecipientEligibilityService();

        $recipients_service =
            new SOF_CommunicationRecipientsService(
                $membership_audience_service,
                $eligibility_service
            );

        $audience =
            new SOF_CommunicationAudience(
                'regional_members',
                $communication->get_audience_name(),
                $communication->get_audience_name() . ' members',
                $communication->get_audience_name(),
                $communication->get_membership_statuses(),
                $approved_recipient_count,
                true
            );

        $current_recipients =
            $recipients_service->discover_for_communication(
                $audience,
                $communication
            );

        $current_recipient_count =
            $current_recipients->get_available_count();

        $unavailable_recipient_count =
            $current_recipients->get_unavailable_count();
            
        // -------------------------------------------------
        // Communication Release
        // -------------------------------------------------

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['sof_send_submit'])
        ) {
            $nonce =
                isset($_POST['sof_send_nonce'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_send_nonce']
                         )
                    )
                    : '';

            if (
                !$nonce ||
                !wp_verify_nonce(
                    $nonce,
                    'sof_send_communication'
                )
            ) {
                $release_error =
                    'The communication could not be released because the security check failed.';

            } else {

                $communications_service =
                    new SOF_CommunicationsService();

                $delivery_service =
                    new SOF_CommunicationDeliveryService();

                $release_service =
                    new SOF_CommunicationReleaseService(
                        $communications_service,
                        $delivery_service,
                        $persistence_service
                    );

                $release_result =
                    $release_service->release(
                       $communication,
                       $sender,
                       $current_recipients
                    );
                    
                // -------------------------------------------------
                // Release Result Navigation
                // -------------------------------------------------

                $release_status =
                    (string) (
                        $release_result['status'] ?? ''
                    );

                if (
                    $release_status ===
                    'release_result_persistence_failed'
                ) {

                    $release_warning =
                        $release_result['message']
                            ?? 'The communication was processed, but the final release result could not be saved.';

                } elseif (
                    in_array(
                        $release_status,
                        [
                            'sent',
                            'delivery_failed',
                            'completed',
                        ],
                        true
                    )
                ) {

                    $confirm_url =
                        add_query_arg(
                            'communication_id',
                            $communication->get_id(),
                            site_url('/confirm-communication/')
                        );

                    wp_safe_redirect(
                        $confirm_url
                    );

                    exit;

                } else {

                    $release_error =
                        $release_result['message']
                            ?? 'The communication could not be released.';
                }
                
            }
            
        }

        // -------------------------------------------------
        // Presentation Information
        // -------------------------------------------------

        $channel =
            $communication->get_channel();

                $channel_label =
                    $channel !== ''
                        ? ucwords(
                            str_replace(
                                '_',
                                ' ',
                                $channel
                            )
                        )
                        : 'Communication';

        // -------------------------------------------------
        // Navigation
        // -------------------------------------------------

        $approve_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/approve-communication/'
                )
            );

        // -------------------------------------------------
        // Presentation
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-communications-workspace sof-send-workspace">

            <header class="sof-workspace-header">

                <h1>
                    Send Communication
                </h1>

                <p>
                    Review the approved communication before releasing
                    it to the audience.
                </p>

            </header>

            <div class="sof-workspace-card">

                <h2>
                    Release Details
                </h2>

                <div class="sof-communication-detail">

                    <strong>
                        Audience
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            $communication->get_audience_name()
                        );
                        ?>
                    </div>

                </div>
                
                <div class="sof-communication-detail">

                    <strong>
                        Include Members
                    </strong>

                <div>
                    <?php

                    $membership_statuses =
                        $communication->get_membership_statuses();

                    echo esc_html(
                        !empty($membership_statuses)
                            ? implode(
                                ', ',
                                $membership_statuses
                            )
                            : 'Active'
                        );

                        ?>
                </div>

            </div>

                <div class="sof-communication-detail">

                    <strong>
                        Delivery
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            $channel_label
                        );
                        ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Approved Audience
                    </strong>

                    <div>
                        <?php
                            echo esc_html(
                                sprintf(
                                    '%s recipients were approved for this communication.',
                                    number_format_i18n(
                                        $approved_recipient_count
                                    )
                                )
                            );
                            ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Current Delivery Audience
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            sprintf(
                                '%s recipients can receive this communication now.',
                                number_format_i18n(
                                    $current_recipient_count
                                )
                           )
                        );
                        ?>
                    </div>

                    <?php if ($unavailable_recipient_count > 0) : ?>

                        <div>
                        <?php
                        echo esc_html(
                            sprintf(
                                '%s recipients are currently unavailable for delivery.',
                                number_format_i18n(
                                    $unavailable_recipient_count
                                )
                            )
                        );
                        ?>
                    </div>

                <?php endif; ?>

            </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Subject
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            $communication->get_subject()
                        );
                        ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Message
                    </strong>

                    <div>
                        <?php
                        echo wp_kses_post(
                            wpautop(
                                $communication->get_body()
                            )
                        );
                        ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Sender
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            $sender->get_name()
                        );
                        ?>
                    </div>

                    <div>
                        <?php
                        echo esc_html(
                            $sender->get_display_title()
                        );
                        ?>
                    </div>

                    <div>
                        <?php
                        echo esc_html(
                            $sender->get_email()
                        );
                        ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Ready for Release
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            sprintf(
                                'Sending now will immediately release this communication by %s to %s currently eligible recipients in the %s.',
                                $channel_label,
                                number_format_i18n(
                                    $current_recipient_count
                                ),
                                $communication->get_audience_name()
                            )
                        );
                        ?>
                    </div>

                </div>
                
                <?php if ($release_warning !== ''): ?>

                    <div class="sof-compose-message sof-compose-message-warning">

                        <strong>
                            Delivery Result Requires Review
                        </strong>

                        <p>
                            <?php echo esc_html($release_warning); ?>
                        </p>

                        <p>
                            <strong>
                                Do not send this communication again until its delivery status has been reviewed.
                            </strong>
                        </p>

                    </div>

                <?php elseif ($release_error !== ''): ?>

                    <div class="sof-compose-message sof-compose-message-error">

                        <strong>
                            Communication Not Released
                        </strong>

                        <p>
                            <?php echo esc_html($release_error); ?>
                        </p>

                    </div>

                <?php elseif ($release_message !== ''): ?>

                    <div class="sof-compose-message sof-compose-message-success">

                        <strong>
                            Communication Released
                        </strong>

                        <p>
                            <?php echo esc_html($release_message); ?>
                        </p>

                    </div>

                <?php endif; ?>

                <div class="sof-test-actions">

                    <a
                        class="sof-button sof-button-secondary"
                        href="<?php echo esc_url($approve_url); ?>"
                    >
                        Back to Approval
                    </a>

                    <?php if ($release_warning === ''): ?>

                        <form
                            method="post"
                            style="display: inline;"
                        >

                            <?php
                            wp_nonce_field(
                                'sof_send_communication',
                                'sof_send_nonce'
                            );
                            ?>

                            <button
                                type="submit"
                                name="sof_send_submit"
                                value="1"
                                class="sof-button sof-button-primary"
                            >
                                Send Communication
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <?php

        return (string) ob_get_clean();
    }
}