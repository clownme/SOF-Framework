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

        $recipient_count =
            $communication->get_recipient_count();

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
                    it for delivery.
                </p>

            </header>

            <div class="sof-workspace-card">

                <h2>
                    Communication Details
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
                            sprintf(
                                '%s recipients will receive this communication by %s.',
                                number_format_i18n(
                                    $recipient_count
                                ),
                                $channel_label
                            )
                        );
                        ?>
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
                        Test Result
                    </strong>

                    <?php if ($communication->get_test_recipient() !== ''): ?>

                        <div>
                            Test delivered successfully to
                            <?php
                            echo esc_html(
                                $communication->get_test_recipient()
                            );
                            ?>.
                        </div>

                    <?php else: ?>

                        <div>
                            Test delivered successfully.
                        </div>

                    <?php endif; ?>

                </div>

                <?php if ($communication->get_status() === 'tested'): ?>

                    <div class="sof-communication-detail sof-approval-confirmation">

                        <strong>
                            Ready for Approval
                        </strong>

                        <div>
                            <?php
                            echo esc_html(
                                sprintf(
                                    'By approving this communication, you confirm that it is ready to be delivered by %s to %s recipients in the %s. After approval, you can send it now or schedule it for delivery.',
                                    $channel_label,
                                    number_format_i18n(
                                        $recipient_count
                                    ),
                                    $communication->get_audience_name()
                                )
                            );
                            ?>
                        </div>

                    </div>

                <?php elseif ($communication->get_status() === 'approved'): ?>

                    <div class="sof-communication-detail sof-approval-confirmation">

                        <strong>
                            Communication Approved
                        </strong>

                        <div>
                            This communication is approved and ready for release.
                        </div>

                    </div>

                <?php endif; ?>

                <?php if ($approval_error !== ''): ?>

                    <div class="sof-compose-message sof-compose-message-error">

                        <strong>
                            Communication Not Approved
                        </strong>

                        <p>
                            <?php
                            echo esc_html(
                                $approval_error
                            );
                            ?>
                        </p>

                    </div>
                    
                <?php endif; ?>

                <?php if ($communication->get_status() === 'tested'): ?>

                    <div class="sof-test-actions">

                        <a
                            class="sof-button sof-button-secondary"
                            href="<?php echo esc_url($test_url); ?>"
                        >
                            Back to Test
                        </a>

                        <form
                            method="post"
                            style="display: inline;"
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

                <?php elseif ($communication->get_status() === 'approved'): ?>

                    <div class="sof-test-actions">

                        <form
                            method="post"
                            style="display: inline;"
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
                        
                        <a
                        
                            class="sof-button sof-button-primary"
                            href="<?php echo esc_url($send_url); ?>"
                        >
                            Send Now
                        </a>
                        
                        <a
                        
                            class="sof-button sof-button-secondary"
                            href="<?php echo esc_url($schedule_url); ?>"
                        >
                            Schedule Delivery
                        </a>
                        
                    </div>

                <?php endif; ?>

            </div>

        </div>

        <?php

        return (string) ob_get_clean();
    }
}