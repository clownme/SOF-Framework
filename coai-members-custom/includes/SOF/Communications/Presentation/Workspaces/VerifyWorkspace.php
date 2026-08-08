<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Verify Communication Workspace
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Workspace:
 *     Verify Communication
 *
 * Purpose:
 *     Present a persisted Communication for human review
 *     before lifecycle verification occurs.
 *
 * Responsibilities:
 *     - Read Communication identity from the request
 *     - Load the persisted Communication
 *     - Present communication details for review
 *     - Preserve separation between Presentation and storage
 *     - Verify the Communication when confirmed
 *     - Persist the verified lifecycle state
 *     - Guide the user toward Test
 *
 * Does NOT:
 *     - Compose communication content
 *     - Resolve audiences
 *     - Query recipients
 *     - Deliver Communications
 *     - Approve communications
 *     - Communicate directly with providers
 *     - Verify the Communication
 *     - Persist lifecycle changes
 *
 * ============================================================
 */

class SOF_VerifyWorkspace
{
    /**
     * Render the Verify Communication Workspace.
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to verify a communication.</p>';
        }

        // -------------------------------------------------
        // Communication Identity
        // -------------------------------------------------

        $communication_id =
            isset($_GET['communication_id'])
                ? absint($_GET['communication_id'])
                : 0;

        if ($communication_id < 1) {
            return '<p>No communication was selected for verification.</p>';
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
        // Verification Result
        // -------------------------------------------------
        
        $verification_message = '';
        $verification_error = '';
        
        // -------------------------------------------------
        // Communication Verification
        // -------------------------------------------------

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['sof_verify_submit'])
        ) {
            $nonce =
                isset($_POST['sof_verify_nonce'])
                    ? sanitize_text_field(
                        wp_unslash($_POST['sof_verify_nonce'])
                    )
                    : '';

            if (
                !$nonce ||
                !wp_verify_nonce(
                    $nonce,
                    'sof_verify_communication'
                )
            ) {
                $verification_error =
                    'The communication could not be verified because the security check failed.';

            } else {

                $communications_service =
                    new SOF_CommunicationsService();

                $verification =
                    $communications_service->verify(
                        $communication,
                        get_current_user_id()
                    );

                if (!$verification['success']) {

                    $verification_error =
                        $verification['message'];

                } else {

                    $saved_communication =
                        $persistence_service->save(
                            $communication
                        );

                    if (!$saved_communication) {

                        $verification_error =
                            'The communication was verified but the updated status could not be saved.';

                    } else {

                        $communication =
                            $saved_communication;

                        $verification_message =
                            'The communication has been verified.';
                    }
                }
            }
        }
        
        // -------------------------------------------------
        // Workspace Navigation
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

        // -------------------------------------------------
        // Presentation
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-communications-workspace sof-verify-workspace">

            <div class="sof-workspace-header">

                <h1>
                    Verify Communication
                </h1>

                <p>
                    Review the communication before continuing.
                </p>

            </div>

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

                        echo esc_html(
                            sprintf(
                                '%s recipients will receive this communication by %s.',
                                number_format_i18n($recipient_count),
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
                        Status
                    </strong>

                    <div>
                        <?php
                        echo esc_html(
                            ucfirst(
                                $communication->get_status()
                            )
                        );
                        ?>
                    </div>
                
                </div>
                
                <?php if ($verification_error !== ''): ?>
                
                    <div class="sof-compose-message sof-compose-message-error">

                        <strong>
                            Communication Not Verified
                        </strong>

                        <p>
                            <?php
                            echo esc_html(
                                $verification_error
                            );
                            ?>
                        </p>

                    </div>

                <?php elseif ($verification_message !== ''): ?>

                    <div class="sof-compose-message sof-compose-message-success">

                        <strong>
                            Communication Verified
                        </strong>

                        <p>
                            <?php
                            echo esc_html(
                                $verification_message
                            );
                            ?>
                        </p>

                    </div>

                <?php endif; ?>
                
                                <div class="sof-compose-actions">

                    <?php if ($communication->is_composed()): ?>

                        <a
                            class="sof-button sof-button-secondary"
                            href="<?php echo esc_url($compose_url); ?>"
                        >
                            Back to Compose
                        </a>

                        <form
                            method="post"
                            style="display: inline;"
                        >

                            <?php
                            wp_nonce_field(
                                'sof_verify_communication',
                                'sof_verify_nonce'
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
                                name="sof_verify_submit"
                                value="1"
                                class="sof-button sof-button-primary"
                            >
                                Verify Communication
                            </button>

                        </form>

                    <?php elseif ($communication->get_status() === 'verified'): ?>

                        <a
                            class="sof-button sof-button-primary"
                            href="<?php echo esc_url($test_url); ?>"
                        >
                            Continue to Test
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <?php

        return (string) ob_get_clean();
    }
}