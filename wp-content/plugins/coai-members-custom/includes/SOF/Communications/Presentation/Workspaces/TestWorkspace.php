<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Test Communication Workspace
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Workspace:
 *     Test Communication
 *
 * Purpose:
 *     Present a verified Communication and prepare it for
 *     test delivery.
 *
 * Responsibilities:
 *     - Load a persisted Communication by identity
 *     - Present the verified Communication
 *     - Collect a test recipient
 *     - Guide the user toward test delivery
 *
 * Does NOT:
 *     - Compose communication content
 *     - Determine audience membership
 *     - Resolve recipients
 *     - Deliver email directly
 *     - Approve communications
 *     - Communicate directly with providers
 *
 * ============================================================
 */

class SOF_TestWorkspace
{
    /**
     * Render the Test Communication Workspace.
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to test a communication.</p>';
        }

        // -------------------------------------------------
        // Communication Identity
        // -------------------------------------------------

        $communication_id =
            isset($_GET['communication_id'])
                ? absint($_GET['communication_id'])
                : 0;

        if ($communication_id < 1) {
            return '<p>No communication was selected for testing.</p>';
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
        // Recipient Population
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

        // -------------------------------------------------
        // Lifecycle Guard
        // -------------------------------------------------

        $testable_statuses = [
            'verified',
            'test_failed',
            'tested',
        ];

        if (
            !in_array(
                $communication->get_status(),
                $testable_statuses,
                true
            )
        ) {
            return '<p>The communication is not available for testing.</p>';
        }
        
        // -------------------------------------------------
        // Test Recipient
        // -------------------------------------------------

        $audience_service =
            new SOF_CommunicationAudienceService();

        $current_member =
            $audience_service->resolve_current_member();

        if (!$current_member) {
            return '<p>Your member information could not be resolved for test delivery.</p>';
        }
        
        $sender_service =
            new SOF_CommunicationSenderService(
                $audience_service
            );

        $sender =
            $sender_service->resolve_current_sender();

        if (!$sender) {
            return '<p>Your sender information could not be resolved for test delivery.</p>';
        }

        $test_recipient_name =
            trim(
                (string) ($current_member['full_name'] ?? '')
            );

        if ($test_recipient_name === '') {

            $test_recipient_name =
                trim(
                    (string) ($current_member['first_name'] ?? '') .
                    ' ' .
                    (string) ($current_member['last_name'] ?? '')
                );
        }

        $test_recipient_email =
            sanitize_email(
                (string) ($current_member['email'] ?? '')
           );

        $test_recipient_member_id =
            (int) ($current_member['member_id'] ?? 0);
            
        // -------------------------------------------------
        // Test Result
        // -------------------------------------------------

        $test_delivery_success =
            isset($_GET['test_sent']) &&
            $_GET['test_sent'] === '1';

        $test_error = '';

        // -------------------------------------------------
        // Test Delivery
        // -------------------------------------------------

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['sof_test_submit'])
        ) {
            if (
                !in_array(
                    $communication->get_status(),
                    [
                        'verified',
                        'test_failed',
                    ],
                    true
                )
            ) {
                $clean_test_url =
                    add_query_arg(
                        'communication_id',
                        $communication->get_id(),
                        home_url(
                            '/test-communication/'
                        )
                    );

                wp_safe_redirect(
                    $clean_test_url
                );

                exit;
            }

            $nonce =
                isset($_POST['sof_test_nonce'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_test_nonce']
                        )
                    )
                    : '';

            if (
                !$nonce ||
                !wp_verify_nonce(
                    $nonce,
                    'sof_test_communication'
                )
            ) {
                $test_error =
                    'The test communication could not be sent because the security check failed.';

            } else {

                $submitted_email =
                    isset($_POST['sof_test_recipient_email'])
                        ? sanitize_email(
                            wp_unslash(
                                $_POST['sof_test_recipient_email']
                            )
                        )
                        : '';

                if (
                    $submitted_email === '' ||
                    !is_email($submitted_email)
                ) {
                    $test_error =
                        'Enter a valid email address for the test communication.';

                } else {

                    $test_recipient_email =
                        $submitted_email;

                    $test_delivery_service =
                        new SOF_CommunicationTestDeliveryService();

                    $delivery_result =
                        $test_delivery_service->send_test(
                            $communication,
                            $sender,
                            $test_recipient_email
                        );

                    $communications_service =
                        new SOF_CommunicationsService();

                    if ($delivery_result['success']) {

                        $test_result =
                            $communications_service
                                ->record_test_success(
                                    $communication,
                                    get_current_user_id(),
                                    $test_recipient_email
                                );

                        if (!$test_result['success']) {

                            $test_error =
                                $test_result['message'];

                        } else {

                            $saved_communication =
                                $persistence_service->save(
                                    $communication
                                );

                            if (!$saved_communication) {

                                $test_error =
                                    'The test was sent but the Communication test result could not be saved.';

                            } else {

                                // -------------------------------------------------
                                // Test Completed
                                // -------------------------------------------------

                                $test_url =
                                    add_query_arg(
                                        [
                                            'communication_id' =>
                                                $saved_communication->get_id(),

                                            'test_sent' => '1',
                                        ],
                                        home_url(
                                            '/test-communication/'
                                        )
                                    );

                                wp_safe_redirect(
                                    $test_url
                                );

                                exit;
                            }
                        }

                    } else {

                        $communications_service
                            ->record_test_failure(
                                $communication,
                                $delivery_result['errors']
                            );

                        $saved_communication =
                            $persistence_service->save(
                                $communication
                            );

                        $test_error =
                            $delivery_result['message'];
                    }
                }
            }
        }

        // -------------------------------------------------
        // Return for Revision
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
                $test_error =
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

                    $test_error =
                        $revision_result['message'];

                } else {

                    $saved_communication =
                        $persistence_service->save(
                            $communication
                        );

                    if (!$saved_communication) {

                        $test_error =
                            'The communication was returned for revision but the updated state could not be saved.';

                    } else {

                        wp_safe_redirect(
                            add_query_arg(
                                'communication_id',
                                $saved_communication->get_id(),
                                home_url(
                                    '/compose-communication/'
                                )
                            )
                        );

                        exit;
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

        $approve_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/approve-communication/'
                )
            );
            
        // -------------------------------------------------
        // Test Situation
        // -------------------------------------------------

        $recipient_count =
            $delivery_recipients
                ->get_available_count();

        $membership_statuses =
            $communication->get_membership_statuses();

        $included_members_label =
            !empty($membership_statuses)
                ? implode(
                    ', ',
                    $membership_statuses
                )
                : 'Active';

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

        if ($communication->get_status() === 'tested') {

            $assessment_label =
                'Recipient Review Complete';

            $test_delivery_email =
                $communication->get_test_recipient();

            $assessment_summary =
                'The communication was successfully delivered to ' .
                $test_delivery_email .
                ' for recipient review.';

            $recommendation_title =
                'Approve Communication';

            $recommendation_message =
                'Review the delivered communication exactly as your ' .
                'selected members will experience it. If it accurately ' .
                'represents the experience you want them to receive, ' .
                'approve the communication for organizational delivery.';

        } else {

            $assessment_label =
                'Communication Ready for Recipient Review';

            $assessment_summary =
                'The communication has been verified and is ready for ' .
                'recipient review. Review the communication exactly as ' .
                'your selected members will experience it before approving ' .
                'organizational delivery.';

            $recommendation_title =
                'Send Test Communication';

            $recommendation_message =
                'Send the communication to yourself and review the subject, ' .
                'message, formatting, links, images, and overall recipient ' .
                'experience before approval.';
        }

        // -------------------------------------------------
        // Presentation
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-communications-workspace sof-workspace sof-test-workspace">

            <header class="sof-workspace-header">

                <h1>
                    Review Communication
                </h1>

                <p>
                    Review the communication exactly as it will
                    be delivered before organizational approval.
                </p>

            </header>

            <main class="sof-workspace-content">

                <section class="sof-card sof-test-situation-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Current Communication Situation
                        </h2>

                        <p class="sof-card-summary">
                            Review the communication exactly as it will be
                            experienced before organizational approval.
                        </p>

                    </header>

                    <div class="sof-card-content">

                        <dl class="sof-situation-details">

                            <div>

                                <dt>
                                    Assessment
                                </dt>

                                <dd>
                                    <?php
                                    echo esc_html(
                                        $assessment_label
                                    );
                                    ?>
                                </dd>

                            </div>

                            <div>

                                <dt>
                                    Summary
                                </dt>

                                <dd>
                                    <?php
                                    echo esc_html(
                                        $assessment_summary
                                    );
                                    ?>
                                </dd>

                            </div>

                            <div>

                                <dt>
                                    Recommended Path
                                </dt>

                                <dd>
                                    <?php
                                    echo esc_html(
                                        $recommendation_title
                                    );
                                    ?>
                                </dd>

                            </div>

                            <div>

                                <dt>
                                    Reason
                                </dt>

                                <dd>
                                    <?php
                                    echo esc_html(
                                        $recommendation_message
                                    );
                                    ?>
                                </dd>

                            </div>

                        </dl>

                    </div>

                </section>

                <section class="sof-card sof-test-evidence-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Communication Evidence
                        </h2>

                        <p class="sof-card-summary">
                            Review the facts supporting the current
                            assessment and recommendation.
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

                            $delivery_member_phrase =
                                $recipient_count === 1
                                    ? 'One selected member'
                                    : number_format_i18n(
                                        $recipient_count
                                    ) . ' selected members';

                            echo esc_html(
                                $delivery_member_phrase .
                                ' will receive this communication by email.'
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
                                    $communication->get_body()
                                    );
                                ?>
                            </div>

                        </div>

                    </div>

                </section>

                <?php if ($communication->get_status() !== 'tested'): ?>

                    <section class="sof-card sof-test-recipient-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Test Recipient
                        </h2>

                        <p class="sof-card-summary">
                            Who will receive the review copy?
                        </p>

                    </header>
                    
                        <div class="sof-card-content">

                            <form
                                method="post"
                                class="sof-test-form"
                            >

                                <?php
                                wp_nonce_field(
                                    'sof_test_communication',
                                    'sof_test_nonce'
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

                                <input
                                    type="hidden"
                                    name="test_recipient_member_id"
                                    value="<?php
                                    echo esc_attr(
                                        (string)
                                        $test_recipient_member_id
                                    );
                                    ?>"
                                >

                                <div class="sof-communication-detail">

                                    <strong>
                                        Recipient
                                    </strong>

                                    <div>
                                        <?php
                                        echo esc_html(
                                            $test_recipient_name
                                        );
                                        ?>
                                    </div>

                                </div>

                                <div class="sof-form-field">

                                    <label for="sof-test-recipient-email">
                                        Email Address
                                    </label>

                                    <input
                                        type="email"
                                        id="sof-test-recipient-email"
                                        name="sof_test_recipient_email"
                                        value="<?php
                                        echo esc_attr(
                                            $test_recipient_email
                                        );
                                        ?>"
                                        required
                                    >

                                    <p class="sof-form-help">
                                        This email address is used only for
                                        this review. It does not change the
                                        member record.
                                    </p>

                                </div>

                                <?php if ($test_error !== ''): ?>

                                    <div class="sof-compose-message sof-compose-message-error">

                                        <strong>
                                            Communication Not Sent
                                        </strong>

                                        <p>
                                            <?php
                                            echo esc_html(
                                                $test_error
                                            );
                                            ?>
                                        </p>

                                    </div>

                                <?php endif; ?>

                                <?php if (
                                    in_array(
                                        $communication->get_status(),
                                        [
                                            'verified',
                                            'test_failed',
                                        ],
                                        true
                                    )
                                ): ?>

                                    <div class="sof-test-actions">

                                        <button
                                            type="submit"
                                            name="sof_test_submit"
                                            value="1"
                                            class="sof-button sof-button-primary"
                                        >
                                            Send Test Communication
                                        </button>

                                    </div>

                                <?php endif; ?>
                                
                            </form>

                        </div>

                    </section>

                <?php endif; ?>

                <?php if ($communication->get_status() === 'tested'): ?>

                    <section class="sof-card sof-test-actions-card">

                        <header class="sof-card-header">

                            <h2 class="sof-card-title">
                                Available Actions
                            </h2>

                            <p class="sof-card-summary">
                                Revise the communication or continue to organizational approval.
                            </p>

                        </header>

                        <div class="sof-card-content">

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
                                        name="sof_revise_submit"
                                        value="1"
                                        class="sof-button sof-button-secondary"
                                    >
                                        Revise Communication
                                    </button>

                                </form>

                                <a
                                    class="sof-button sof-button-primary"
                                    href="<?php echo esc_url($approve_url); ?>"
                                >
                                    Approve Communication
                                </a>

                            </div>

                        </div>

                    </section>

                <?php endif; ?>

            </main>
            
        </div>

        <?php

        return (string) ob_get_clean();
    }

}