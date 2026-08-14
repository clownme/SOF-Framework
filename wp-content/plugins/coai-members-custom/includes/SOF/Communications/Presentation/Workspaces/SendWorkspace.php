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

        $communication_status =
            $communication->get_status();
            
        if ($communication_status === 'sent') {
            
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
        }

        if (
            !in_array(
                $communication_status,
                [
                    'approved',
                    'sending',
                ],
                true
            )
        ) {
            return '<p>The communication is not currently available for delivery.</p>';
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
        // Queued Delivery Progress
        // -------------------------------------------------
 
        $delivery_started =
            $communication_status === 'sending';

        $delivery_progress = [
            'total' =>
                0,

            'pending' =>
                0,

            'processing' =>
                0,

            'sent' =>
                0,

            'failed' =>
                0,
        ];

        if ($delivery_started) {

            $queue_repository =
                new SOF_CommunicationDeliveryQueueRepository();

            $queue_service =
                new SOF_CommunicationDeliveryQueueService(
                    $queue_repository
                );

            $delivery_progress =
                $queue_service->progress(
                    $communication_id
                );
        }

        // -------------------------------------------------
        // Release Audience
        // -------------------------------------------------

        $recipient_selection =
            $communication->get_recipient_selection();

        $approved_recipient_count =
            $recipient_selection->uses_all_recipients()
                ? $communication->get_recipient_count()
                : count(
                    $recipient_selection->get_member_ids()
                );

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
                $communication->get_audience_key(),
                $communication->get_audience_name(),
                $communication->get_audience_name() . ' members',
                $communication->get_audience_region(),
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
                    $release_status === 'queued') {
                        
                        $send_url =
                            add_query_arg(
                                'communication_id',
                                $communication->get_id(),
                                site_url('/send-communication/')
                            );
                            
                        wp_safe_redirect(
                            $send_url
                        );
                        
                        exit;

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

        $delivery_from_email =
            'newsletter-manager@mycoai.com';

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
        
        <div class="sof-communications-workspace sof-workspace sof-send-workspace">

            <header class="sof-workspace-header">

                <h1>
                    Send Communication
                </h1>

                <p>
                    Review the approved communication before beginning
                    organizational delivery.
                </p>

            </header>

            <main class="sof-workspace-content">

        <!-- ============================================= -->
        <!-- Assessment -->
        <!-- ============================================= -->

        <section class="sof-card sof-send-assessment-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Assessment
                </h2>

                <p class="sof-card-summary">
                    What is the current delivery situation?
                </p>

            </header>

            <div class="sof-card-content">

                <div class="sof-communication-detail">

                    <strong>
                        Assessment
                    </strong>

                    <div>
                        Communication Approved for Delivery
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Summary
                    </strong>

                    <div>

                        <?php

                        echo esc_html(
                            sprintf(
                                $current_recipient_count === 1
                                    ? 'One currently eligible recipient can receive this communication now.'
                                    : '%s currently eligible recipients can receive this communication now.',
                                number_format_i18n(
                                    $current_recipient_count
                                )
                            )
                        );

                        ?>

                    </div>

                </div>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Recommended Path -->
        <!-- ============================================= -->

        <section class="sof-card sof-send-recommendation-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Recommended Path
                </h2>

                <p class="sof-card-summary">
                    What should you do next?
                </p>

            </header>

            <div class="sof-card-content">

                <div class="sof-communication-detail">

                    <strong>
                        Next Action
                    </strong>

                    <div>
                        Send Communication
                    </div>

                </div>

            </div>

        </section>                

        <!-- ============================================= -->
        <!-- Communication -->
        <!-- ============================================= -->

        <section class="sof-card sof-send-communication-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Communication
                </h2>

                <p class="sof-card-summary">
                    What communication are you sending?
                </p>

            </header>

            <div class="sof-card-content">

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
                                $communication->get_body()
                        );
                        ?>
                    </div>

                </div>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Audience -->
        <!-- ============================================= -->

        <section class="sof-card sof-send-audience-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Audience
                </h2>

                <p class="sof-card-summary">
                    Who is this communication intended for?
                </p>

            </header>

            <div class="sof-card-content">

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
                        Membership Status
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

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Recipients -->
        <!-- ============================================= -->

        <section class="sof-card sof-send-recipients-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Recipients
                </h2>

                <p class="sof-card-summary">
                    Who will receive this communication now?
                </p>

            </header>

            <div class="sof-card-content">

                <div class="sof-communication-detail">

                    <strong>
                        Approved Recipients
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            number_format_i18n(
                                $approved_recipient_count
                            )
                        );
                        ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Currently Available
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            number_format_i18n(
                                $current_recipient_count
                            )
                        );
                        ?>
                    </div>

                </div>

                <?php if ($unavailable_recipient_count > 0): ?>

                    <div class="sof-communication-detail">

                        <strong>
                            Currently Unavailable
                        </strong>

                        <div>
                            <?php
                            echo esc_html(
                                number_format_i18n(
                                    $unavailable_recipient_count
                                )
                            );
                            ?>
                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Delivery -->
        <!-- ============================================= -->

        <section class="sof-card sof-send-delivery-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Delivery
                </h2>

                <p class="sof-card-summary">
                    How will this communication be delivered?
                </p>

            </header>

            <div class="sof-card-content">

                <div class="sof-communication-detail">

                    <strong>
                        Prepared By
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            $sender->get_name()
                        );
                        ?>
                    </div>

                    <?php if ($sender->get_display_title() !== ''): ?>

                        <div>
                            <?php
                            echo esc_html(
                                $sender->get_display_title()
                            );
                            ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Delivered By
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            $delivery_from_email
                        );
                        ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Delivery Method
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
                        Delivery Summary
                    </strong>

                    <div>
                        <?php

                        echo esc_html(
                            sprintf(
                                $current_recipient_count === 1
                                    ? 'Sending now will deliver this communication by %s to one currently eligible recipient in the %s.'
                                    : 'Sending now will deliver this communication by %s to %s currently eligible recipients in the %s.',
                                strtolower(
                                    $channel_label
                                ),
                                number_format_i18n(
                                    $current_recipient_count
                                ),
                                $communication->get_audience_name()
                            )
                        );

                        ?>
                    </div>

                </div>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Delivery Messages -->
        <!-- ============================================= -->

        <?php if ($delivery_started): ?>
        
            <?php
            
            $remaining_count =
                (int) $delivery_progress['pending'] +
                (int) $delivery_progress['processing'];
                
            ?>
            
            <section class="sof-card sof-send-progress-card">

        <header class="sof-card-header">

            <h2 class="sof-card-title">
                Communication Delivery Started
            </h2>

            <p class="sof-card-summary">
                SOF is managing delivery to the queued recipients.
            </p>

        </header>

        <div class="sof-card-content">

            <div class="sof-communication-detail">

                <strong>
                    Queued Recipients
                </strong>

                <div>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int) $delivery_progress['total']
                        )
                    );
                    ?>
                </div>

            </div>

            <div class="sof-communication-detail">

                <strong>
                    Sent
                </strong>

                <div>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int) $delivery_progress['sent']
                        )
                    );
                    ?>
                </div>

            </div>

            <div class="sof-communication-detail">

                <strong>
                    Failed
                </strong>

                <div>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int) $delivery_progress['failed']
                        )
                    );
                    ?>
                </div>

            </div>

            <div class="sof-communication-detail">

                <strong>
                    Remaining
                </strong>

                <div>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            $remaining_count
                        )
                    );
                    ?>
                </div>

            </div>

        </div>

    </section>

<?php elseif ($release_warning !== ''): ?>

            <section class="sof-card sof-send-warning-card">

                <header class="sof-card-header">

                    <h2 class="sof-card-title">
                        Delivery Requires Review
                    </h2>

                    <p class="sof-card-summary">
                        The delivery result needs attention before any further action.
                    </p>

                </header>

                <div class="sof-card-content">

                    <div class="sof-compose-message sof-compose-message-warning">

                        <strong>
                            Delivery Result Requires Review
                        </strong>

                        <p>
                            <?php
                            echo esc_html(
                                $release_warning
                            );
                            ?>
                        </p>

                        <p>
                            <strong>
                                Do not send this communication again until its
                                delivery status has been reviewed.
                            </strong>
                        </p>

                    </div>

                </div>

            </section>

        <?php elseif ($release_error !== ''): ?>

            <section class="sof-card sof-send-error-card">

                <header class="sof-card-header">

                    <h2 class="sof-card-title">
                        Delivery Not Started
                    </h2>

                    <p class="sof-card-summary">
                        The communication could not be delivered.
                    </p>

                </header>

                <div class="sof-card-content">

                    <div class="sof-compose-message sof-compose-message-error">

                        <strong>
                            Communication Not Sent
                        </strong>

                        <p>
                            <?php
                            echo esc_html(
                                $release_error
                            );
                            ?>
                        </p>

                    </div>

                </div>

            </section>

        <?php elseif ($release_message !== ''): ?>

            <section class="sof-card sof-send-success-card">

                <header class="sof-card-header">

                    <h2 class="sof-card-title">
                        Delivery Complete
                    </h2>

                    <p class="sof-card-summary">
                        The communication was delivered successfully.
                    </p>

                </header>

                <div class="sof-card-content">

                    <div class="sof-compose-message sof-compose-message-success">

                        <strong>
                            Communication Sent
                        </strong>

                        <p>
                            <?php
                            echo esc_html(
                                $release_message
                            );
                            ?>
                        </p>

                    </div>

                </div>

            </section>

        <?php endif; ?>

        <!-- ============================================= -->
        <!-- Available Actions -->
        <!-- ============================================= -->

        <section class="sof-card sof-send-actions-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Available Actions
                </h2>

                <p class="sof-card-summary">

                    <?php if ($delivery_started): ?>
                    
                        Delivery is continuing in the background. You may remain on this page
                        to review progress or return to the Member Portal. Leaving this page 
                        will not stop delivery.
                        
                    <?php elseif ($release_warning !== ''): ?>

                        Return to approval and review the delivery status
                        before taking further action.

                    <?php else: ?>

                        Return to approval or send this communication now.

                    <?php endif; ?>

                </p>

            </header>

<div class="sof-card-content">

    <div class="sof-test-actions">

        <?php if ($delivery_started): ?>

            <form
                method="get"
                action="<?php
                echo esc_url(
                    home_url('/member-portal/')
                );
                ?>"
                style="display:inline;"
            >

                <button
                    type="submit"
                    class="sof-button sof-button-secondary"
                >
                    Return to Member Portal
                </button>

            </form>

        <?php else: ?>

            <form
                method="get"
                action="<?php
                echo esc_url(
                    home_url('/approve-communication/')
                );
                ?>"
                style="display:inline;"
            >

                <input
                    type="hidden"
                    name="communication_id"
                    value="<?php
                    echo esc_attr(
                        (string)
                        $communication->get_id()
                    );
                    ?>"
                >

                <button
                    type="submit"
                    class="sof-button sof-button-secondary"
                >
                    Return to Approval
                </button>

            </form>

            <?php if ($release_warning === ''): ?>

                <form
                    method="post"
                    style="display:inline;"
                >

                    <?php
                    wp_nonce_field(
                        'sof_send_communication',
                        'sof_send_nonce'
                    );
                    ?>

                    <input
                        type="hidden"
                        name="communication_id"
                        value="<?php
                        echo esc_attr(
                            (string)
                            $communication->get_id()
                        );
                        ?>"
                    >

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

        <?php endif; ?>

    </div>

</div>

        </section>

    </main>

</div>

<?php if ($delivery_started): ?>

    <script>
        (function () {
            'use strict';

            window.setTimeout(
                function () {
                    window.location.reload();
                },
                5000
            );
        }());
    </script>

<?php endif; ?>

        <?php

        return ob_get_clean();
    }
}
