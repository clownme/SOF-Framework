<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Approve Communication Workspace
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Workspace:
 *     Approve Communication
 *
 * Purpose:
 *     Present a tested Communication for final human approval
 *     before release.
 *
 * Responsibilities:
 *     - Load a persisted Communication by identity
 *     - Present the tested Communication
 *     - Show delivery intent
 *     - Record human approval
 *     - Persist the approved lifecycle state
 *     - Guide the user toward release
 *
 * Does NOT:
 *     - Compose communication content
 *     - Resolve audiences
 *     - Deliver communications
 *     - Schedule communications
 *     - Communicate directly with providers
 *
 * ============================================================
 */

class SOF_ApproveWorkspace
{
    /**
     * Render the Approve Communication Workspace.
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to approve a communication.</p>';
        }

        // -------------------------------------------------
        // Communication Identity
        // -------------------------------------------------

        $communication_id =
            isset($_GET['communication_id'])
                ? absint($_GET['communication_id'])
                : 0;

        if ($communication_id < 1) {
            return '<p>No communication was selected for approval.</p>';
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

        $approval_statuses = [
            'tested',
            'approved',
        ];

        if (
            !in_array(
                $communication->get_status(),
                $approval_statuses,
                true
            )
        ) {
            return '<p>The communication is not available for approval.</p>';
        }

        // -------------------------------------------------
        // Approval Result
        // -------------------------------------------------

        $approval_message =
            isset($_GET['approved']) &&
            $_GET['approved'] === '1'
                ? 'The communication has been approved and is ready for release.'
                : '';

        $approval_error = '';

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
        // Communication Approval
        // -------------------------------------------------

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['sof_approve_submit'])
        ) {
            $nonce =
                isset($_POST['sof_approve_nonce'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_approve_nonce']
                        )
                    )
                    : '';

            if (
                !$nonce ||
                !wp_verify_nonce(
                    $nonce,
                    'sof_approve_communication'
                )
            ) {
                $approval_error =
                    'The communication could not be approved because the security check failed.';

            } elseif ($communication->get_status() !== 'tested') {

                $approval_error =
                    'Only a successfully tested communication can be approved.';

            } else {

                $communications_service =
                    new SOF_CommunicationsService();

                $approval_result =
                    $communications_service->approve(
                        $communication,
                        get_current_user_id()
                    );

                if (!$approval_result['success']) {

                    $approval_error =
                        $approval_result['message'];

                } else {

                    $saved_communication =
                        $persistence_service->save(
                            $communication
                        );

                    if (!$saved_communication) {

                        $approval_error =
                            'The communication was approved but the updated state could not be saved.';

                    } else {

                        $approved_url =
                            add_query_arg(
                                [
                                    'communication_id' =>
                                        $saved_communication->get_id(),

                                    'approved' => '1',
                                ],
                                home_url(
                                    '/approve-communication/'
                                )
                            );

                        wp_safe_redirect(
                            $approved_url
                        );

                        exit;
                    }
                }
            }
        }
        
        // -------------------------------------------------
        // Communication Revision
        // -------------------------------------------------

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['sof_revise_submit'])
        ) {
            $nonce =
                isset($_POST['sof_revise_nonce'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_revise_nonce']
                        )
                    )
                    : '';

            if (
                !$nonce ||
                !wp_verify_nonce(
                    $nonce,
                    'sof_revise_communication'
                )
            ) {
                $approval_error =
                    'The communication could not be returned for revision because the security check failed.';

            } else {

                $communications_service =
                    new SOF_CommunicationsService();

                $revision_result =
                    $communications_service
                        ->return_for_revision(
                            $communication
                        );

               if (!$revision_result['success']) {

                    $approval_error =
                       $revision_result['message'];

                } else {

                    $saved_communication =
                        $persistence_service->save(
                            $communication
                       );

                    if (!$saved_communication) {

                       $approval_error =
                            'The communication was returned for revision, but the updated lifecycle state could not be saved.';

                    } else {

                        $compose_url =
                            add_query_arg(
                                'communication_id',
                                $saved_communication->get_id(),
                                home_url(
                                    '/compose-communication/'
                                )
                            );

                        wp_safe_redirect(
                            $compose_url
                        );

                        exit;
                    }
                }
            }
        }

        // -------------------------------------------------
        // Navigation
        // -------------------------------------------------

        $compose_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/compose-communication/'
                )
            );
        
        $test_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/test-communication/'
                )
            );

        $send_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/send-communication/'
                )
            );

        $schedule_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/schedule-communication/'
                )
            );

        // -------------------------------------------------
        // Presentation
        // -------------------------------------------------

        $membership_audience_service =
            new SOF_MembershipAudienceService();

        $recipients_service =
            new SOF_CommunicationRecipientsService(
                $membership_audience_service
            );

        $audience =
            new SOF_CommunicationAudience(
                $communication->get_audience_key(),
                $communication->get_audience_name(),
                $communication->get_audience_name() . ' members',
                $communication->get_audience_region(),
                $communication->get_membership_statuses(),
                $communication->get_recipient_count(),
                true
            );

        $eligible_recipients =
            $recipients_service->discover(
                $audience
            );

        $selection_service =
            new SOF_CommunicationRecipientSelectionService();

        $delivery_recipients =
            $selection_service->apply(
                $eligible_recipients,
                $communication->get_recipient_selection()
            );

        $recipient_count =
            $delivery_recipients
                ->get_available_count();

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


        ob_start();
        ?>

        <div class="sof-communications-workspace sof-approve-workspace">

            <header class="sof-workspace-header">

                <h1>
                    Approve Communication
                </h1>

                <p>
                    Review the tested communication before approving
                    it for organizational delivery.
                </p>

            </header>

            <main class="sof-workspace-content">

        <!-- ============================================= -->
        <!-- Assessment -->
        <!-- ============================================= -->

        <section class="sof-card sof-approve-assessment-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Assessment
                </h2>

                <p class="sof-card-summary">
                    What is the current communication situation?
                </p>

            </header>

            <div class="sof-card-content">

                <div class="sof-communication-detail">

                    <strong>
                        Assessment
                    </strong>

                    <div>

                        <?php
                        if (
                            $communication->get_status() === 'approved'
                        ) {
                            echo esc_html(
                                'Communication Approved'
                            );
                        } else {
                            echo esc_html(
                                'Communication Ready for Approval'
                            );
                        }
                        ?>

                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Summary
                    </strong>

                    <div>

                        <?php

                        if (
                            $communication->get_status() === 'approved'
                        ) {

                            echo esc_html(
                                'This communication has been approved for organizational delivery.'
                            );

                        } else {

                            echo esc_html(
                                sprintf(
                                    'The communication has been successfully reviewed and tested. %s selected recipient%s will receive this communication after approval.',
                                    number_format_i18n(
                                        $recipient_count
                                    ),
                                    $recipient_count === 1
                                        ? ''
                                        : 's'
                                )
                            );

                        }

                        ?>

                    </div>

                </div>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Recommended Path -->
        <!-- ============================================= -->

        <section class="sof-card sof-approve-recommendation-card">

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

                        <?php

                        if (
                            $communication->get_status() === 'approved'
                        ) {

                            echo esc_html(
                                'Send Communication Now'
                            );

                        } else {

                            echo esc_html(
                                'Approve Communication'
                            );

                        }

                        ?>

                    </div>

                </div>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Communication -->
        <!-- ============================================= -->

        <section class="sof-card sof-approve-communication-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Communication
                </h2>

                <p class="sof-card-summary">
                    What communication are you approving?
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

                        $communication_body =
                            $communication->get_body();

                        $is_structured_html =
                            stripos(
                                $communication_body,
                                '<table'
                            ) !== false ||
                            stripos(
                                $communication_body,
                                'role="presentation"'
                            ) !== false;

                        if ($is_structured_html) {

                            echo wp_kses_post(
                                $communication_body
                            );

                        } else {

                            echo wp_kses_post(
                                wpautop(
                                    $communication_body
                                )
                            );

                        }

                        ?>

                    </div>

                </div>

            </div>

        </section>      

        <!-- ============================================= -->
        <!-- Audience -->
        <!-- ============================================= -->

        <section class="sof-card sof-approve-audience-card">

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

        <section class="sof-card sof-approve-recipients-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Recipients
                </h2>

                <p class="sof-card-summary">
                    Who will receive this communication?
                </p>

            </header>

            <div class="sof-card-content">

                <div class="sof-communication-detail">

                    <strong>
                        Eligible Members
                    </strong>

                    <div>

                        <?php
                        echo esc_html(
                            number_format_i18n(
                                $eligible_recipients
                                    ->get_available_count()
                            )
                        );
                        ?>

                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Selected Members
                    </strong>

                    <div>

                        <?php
                        echo esc_html(
                            number_format_i18n(
                                $recipient_count
                            )
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

                        if ($recipient_count === 1) {

                            printf(
                                'One selected member will receive this communication by %s.',
                                esc_html(
                                    strtolower(
                                        $channel_label
                                    )
                                )
                            );

                        } else {

                            printf(
                                '%s selected members will receive this communication by %s.',
                                esc_html(
                                    number_format_i18n(
                                        $recipient_count
                                    )
                                ),
                                esc_html(
                                    strtolower(
                                        $channel_label
                                    )
                                )
                            );

                        }

                        ?>

                    </div>

                </div>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Test Results -->
        <!-- ============================================= -->

        <section class="sof-card sof-approve-test-results-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Test Results
                </h2>

                <p class="sof-card-summary">
                    What was confirmed during recipient review?
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

                    <div>

                        <?php
                        echo esc_html(
                            $sender->get_display_title()
                        );
                        ?>

                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Delivered By
                    </strong>

                    <div>

                        newsletter-manager@mycoai.com

                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Test Recipient
                    </strong>

                    <div>

                        <?php

                        echo esc_html(
                            $communication->get_test_recipient() !== ''
                                ? $communication->get_test_recipient()
                                : 'Current User'
                        );

                        ?>

                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Result
                    </strong>

                    <div>

                        Test delivery completed successfully.

                    </div>

                </div>

            </div>

        </section>

        <!-- ============================================= -->
        <!-- Available Actions -->
        <!-- ============================================= -->

        <section class="sof-card sof-approve-actions-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Available Actions
                </h2>

                <p class="sof-card-summary">

                    <?php
                    if (
                        $communication->get_status() === 'tested'
                    ) {
                        ?>
                        Return for additional testing or approve this communication for organizational delivery.
                        <?php
                    } else {
                        ?>
                        Revise this communication, send it now, or schedule delivery for later.
                        <?php
                    }
                    ?>

                </p>

            </header>

            <div class="sof-card-content">

                <?php if ($communication->get_status() === 'tested'): ?>

                    <div class="sof-test-actions">

                        <form
                            method="get"
                            action="<?php
                            echo esc_url(
                                home_url(
                                    '/test-communication/'
                                )
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
                                Return to Test
                            </button>

                        </form>

                        <form
                            method="post"
                            style="display:inline;"
                        >

                            <?php
                            wp_nonce_field(
                                'sof_approve_communication',
                                'sof_approve_nonce'
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
                                name="sof_approve_submit"
                                value="1"
                                class="sof-button sof-button-primary"
                            >
                                Approve Communication
                            </button>

                        </form>

                    </div>

                <?php else: ?>

                    <div class="sof-test-actions">

                        <form
                            method="post"
                            style="display:inline;"
                        >

                            <?php
                            wp_nonce_field(
                                'sof_revise_communication',
                                'sof_revise_nonce'
                            );
                            ?>

                            <button
                                type="submit"
                                name="sof_revise_submit"
                                value="1"
                                class="sof-button sof-button-secondary"
                            >
                                Revise Communication
                            </button>

                        </form>
                        
                        <form
                            method="get"
                            action="<?php
                            echo esc_url(
                                home_url('/send-communication/')
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
                            class="sof-button sof-button-primary"
                        >
                            Send Communication Now
                        </button>

                        </form>
                        
                        <form
                            method="get"
                            action="<?php 
                            echo esc_url(
                                home_url('/schedule_communication/')
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
                                Schedule Delivery Later
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

<?php

return (string) ob_get_clean();
    }
}