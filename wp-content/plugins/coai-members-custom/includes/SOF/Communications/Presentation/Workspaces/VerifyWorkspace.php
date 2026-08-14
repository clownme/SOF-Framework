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

        $recipient_selection =
            $communication
                ->get_recipient_selection();

        $selection_service =
            new SOF_CommunicationRecipientSelectionService();

        $delivery_recipients =
            $selection_service->apply(
                $eligible_recipients,
                $recipient_selection
            );
        
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

                        $verify_url =
                            add_query_arg(
                                'communication_id',
                                $saved_communication->get_id(),
                                home_url(
                                    '/verify-communication/'
                                )
                            );

                        wp_safe_redirect(
                            $verify_url
                        );

                        exit;
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
                $verification_error =
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

                    $verification_error =
                        $revision_result['message'];

                } else {

                    $saved_communication =
                        $persistence_service->save(
                            $communication
                        );

                    if (!$saved_communication) {

                        $verification_error =
                            'The communication was returned for revision, but the updated state could not be saved.';

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
        // Revision Destination
        // -------------------------------------------------

        $compose_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/compose-communication/'
                )
            );

        $compose_label =
            'Revise Communication';

        if (
            $communication->get_source_type() === 'newsletter' &&
            $communication->get_source_id()
        ) {
            $compose_url =
                add_query_arg(
                    'newsletter_id',
                    $communication->get_source_id(),
                    home_url(
                        '/compose-newsletter/'
                    )
                );

            $compose_label =
                'Revise Newsletter';
        }         
        $test_url =
            add_query_arg(
                'communication_id',
                $communication->get_id(),
                home_url(
                    '/test-communication/'
                )
            );

        // -------------------------------------------------
        // Verify Situation
        // -------------------------------------------------

        $eligible_recipient_count =
            $eligible_recipients
                ->get_available_count();

        $delivery_recipient_count =
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

        if ($communication->get_status() === 'verified') {

            $assessment_label =
                'Communication Verified';

            $assessment_summary =
                'The communication has been reviewed and verified. ' .
                'It is ready for recipient review.';

            $recommendation_title =
                'Send Test Communication';

        } else {

            $assessment_label =
                'Communication Prepared for Review';

            $selected_member_phrase =
                $delivery_recipient_count === 1
                    ? 'One selected member'
                    : number_format_i18n(
                        $delivery_recipient_count
                    ) . ' selected members';

            $assessment_summary =
                'The communication has been prepared for verification. ' .
                'It is authorized for the ' .
                $communication->get_audience_name() .
                ', and ' .
                strtolower(
                    $selected_member_phrase
                ) .
                ' will receive it after verification.';

            $recommendation_title =
                'Verify Communication';
        }
        
        // -------------------------------------------------
        // Presentation
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-communications-workspace sof-workspace sof-verify-workspace">

            <header class="sof-workspace-header">

                <h1>
                    Verify Communication
                </h1>

                <p>
                    Review what the organization understands before
                    authorizing the communication to continue.
                </p>

            </header>

            <main class="sof-workspace-content">

                <section class="sof-card sof-verify-assessment-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Assessment
                        </h2>

                        <p class="sof-card-summary">
                            What is the current communication situation?
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

                        </dl>

                    </div>

                </section>

                <section class="sof-card sof-verify-recommendation-card">

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
                                echo esc_html(
                                    $recommendation_title
                                );
                                ?>
                            </div>

                        </div>

                    </div>

                </section>

                <section class="sof-card sof-verify-communication-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Communication
                        </h2>

                        <p class="sof-card-summary">
                            What communication are you reviewing?
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

                <section class="sof-card sof-verify-audience-card">

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
                                echo esc_html(
                                    $included_members_label
                                );
                                ?>
                            </div>

                        </div>

                    </div>

                </section>

                <section class="sof-card sof-verify-recipients-card">

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
                                        $eligible_recipient_count
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
                                        $delivery_recipient_count
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
                                    $delivery_recipient_count === 1
                                        ? 'One selected member'
                                        : number_format_i18n(
                                            $delivery_recipient_count
                                        ) . ' selected members';

                                echo esc_html(
                                    $delivery_member_phrase .
                                    ' will receive this communication by ' .
                                    strtolower(
                                        $channel_label
                                    ) .
                                    '.'
                                );

                                ?>
                            </div>

                        </div>

                    </div>

                </section>

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

                <?php endif; ?>

                <section class="sof-card sof-verify-actions-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Available Actions
                        </h2>

                        <p class="sof-card-summary">

                            <?php if ($communication->is_composed()): ?>

                                Revise the communication or verify it
                                to continue.

                            <?php elseif ($communication->get_status() === 'verified'): ?>

                                Revise the communication or send it
                                for recipient review.

                            <?php endif; ?>

                        </p>

                    </header>

                    <div class="sof-card-content">

                        <div class="sof-compose-actions">

                            <?php if ($communication->is_composed()): ?>

                                <a
                                    class="sof-button sof-button-secondary"
                                    href="<?php echo esc_url($compose_url); ?>"
                                >
                                    <?php
                                    echo esc_html(
                                        $compose_label
                                    );
                                    ?>
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
                                href="<?php echo esc_url($test_url); ?>"
                            >
                                Send Test Communication
                            </a>

                        <?php endif; ?>

                        </div>

                    </div>

                </section>
        
            </main>

        </div>

        <?php

            return (string) ob_get_clean();
        }
    }