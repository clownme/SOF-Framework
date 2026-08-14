<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Confirm Communication Workspace
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Workspace:
 *     Confirm Communication
 *
 * Purpose:
 *     Present the persisted result of a Communication release.
 *
 * Responsibilities:
 *     - Load a persisted Communication
 *     - Present the final Communication lifecycle result
 *     - Present successful and failed delivery counts
 *     - Present the audience that was released
 *     - Present the delivery channel
 *     - Present when the Communication was sent
 *     - Help the user understand the release outcome
 *
 * Does NOT:
 *     - Release Communications
 *     - Retry failed deliveries
 *     - Change Communication audiences
 *     - Determine recipient eligibility
 *     - Communicate directly with delivery providers
 *
 * ============================================================
 */

class SOF_ConfirmWorkspace
{
    /**
     * Render the Confirm Communication Workspace.
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to view a communication result.</p>';
        }

        // -------------------------------------------------
        // Communication Identity
        // -------------------------------------------------

        $communication_id =
            isset($_GET['communication_id'])
                ? absint($_GET['communication_id'])
                : 0;

        if ($communication_id < 1) {
            return '<p>No communication was selected.</p>';
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
        // Delivery Result
        // -------------------------------------------------

        $status =
            $communication->get_status();

        $delivered_count =
            $communication->get_delivered_count();

        $failed_count =
            $communication->get_failed_count();

        $attempted_count =
            $delivered_count + $failed_count;

        // -------------------------------------------------
        // Result Understanding
        // -------------------------------------------------

        $result_title = '';
        $result_message = '';

        if ($status === 'sent') {

            if ($failed_count > 0) {

                $result_title =
                    'Communication Sent with Delivery Issues';

                $result_message =
                    sprintf(
                        '%d of %d messages were successfully submitted for delivery. %d could not be delivered.',
                        $delivered_count,
                        $attempted_count,
                        $failed_count
                    );

            } else {

                $result_title =
                    'Communication Delivered';

                if ($delivered_count === 1) {

                    $result_message =
                        'The communication was successfully delivered to one selected member.';

                } else {

                    $result_message =
                        sprintf(
                            'The communication was successfully delivered to %s selected members.',
                            number_format_i18n(
                                $delivered_count
                            )
                        );
                }
            }

        } elseif ($status === 'delivery_failed') {

            $result_title =
                'Communication Could Not Be Sent';

            $result_message =
                sprintf(
                    'Delivery was attempted for %d %s, but none were successfully submitted for delivery.',
                    $failed_count,
                    $failed_count === 1
                        ? 'recipient'
                        : 'recipients'
                );

        } elseif ($status === 'completed') {

            $result_title =
                'Communication Complete';

            $result_message =
                'This communication has completed its delivery lifecycle.';

        } else {

            return '<p>A final delivery result is not yet available for this communication.</p>';
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

        $sent_at =
            $communication->get_sent_at();

        $sent_label =
            $sent_at
                ? wp_date(
                    get_option('date_format') .
                    ' ' .
                    get_option('time_format'),
                    strtotime($sent_at)
                )
                : 'Not recorded';

        $membership_statuses =
            $communication->get_membership_statuses();

        $membership_status_label =
            $membership_statuses
                ? implode(', ', $membership_statuses)
                : 'Not specified';

        // -------------------------------------------------
        // Navigation
        // -------------------------------------------------

        $create_communication_url =
            home_url(
                '/compose-communication/'
            );

        $member_portal_url =
            home_url(
                '/member-portal/'
            );

        // -------------------------------------------------
        // Presentation
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-communications-workspace sof-workspace sof-confirm-workspace">

            <header class="sof-workspace-header">

                <h1>
                    Confirm Communication
                </h1>

                <p>
                    Review the final outcome of this communication.
                </p>

            </header>

            <main class="sof-workspace-content">

                <!-- ============================================= -->
                <!-- Final Assessment -->
                <!-- ============================================= -->

                <section class="sof-card sof-confirm-assessment-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Final Assessment
                        </h2>

                        <p class="sof-card-summary">
                            What was the final communication outcome?
                        </p>

                    </header>

                    <div class="sof-card-content">

                        <div class="sof-communication-detail">

                            <strong>
                                Final Assessment
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    $result_title
                                );
                                ?>
                            </div>

                        </div>

                        <div class="sof-communication-detail">

                            <strong>
                                Summary
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    $result_message
                                );
                                ?>
                            </div>

                        </div>

                    </div>

                </section>
                
                <!-- ============================================= -->
                <!-- Communication -->
                <!-- ============================================= -->

                <section class="sof-card sof-confirm-communication-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Communication
                        </h2>

                        <p class="sof-card-summary">
                            What communication was delivered?
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
                                    wpautop(
                                        $communication->get_body()
                                    )
                                );
                                ?>
                            </div>

                        </div>

                    </div>

                </section>
                
                <!-- ============================================= -->
                <!-- Audience -->
                <!-- ============================================= -->

                <section class="sof-card sof-confirm-audience-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Audience
                        </h2>

                        <p class="sof-card-summary">
                            Who was this communication intended for?
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
                                    $membership_status_label
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

                    </div>

                </section>

                <!-- ============================================= -->
                <!-- Delivery Results -->
                <!-- ============================================= -->

                <section class="sof-card sof-confirm-delivery-results-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Delivery Results
                        </h2>

                        <p class="sof-card-summary">
                            What happened during organizational delivery?
                        </p>

                    </header>

                    <div class="sof-card-content">

                        <div class="sof-communication-detail">

                            <strong>
                                Attempted
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    number_format_i18n(
                                        $attempted_count
                                    )
                                );
                                ?>
                            </div>

                        </div>

                        <div class="sof-communication-detail">

                            <strong>
                                Delivered
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    number_format_i18n(
                                        $delivered_count
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
                                        $failed_count
                                    )
                                );
                                ?>
                            </div>

                        </div>

                        <div class="sof-communication-detail">

                            <strong>
                                Completed
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    $sent_label
                                );
                                ?>
                            </div>

                        </div>

                    </div>

                </section>
                
                <!-- ============================================= -->
                <!-- Available Actions -->
                <!-- ============================================= -->

                <section class="sof-card sof-confirm-actions-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Available Actions
                        </h2>

                        <p class="sof-card-summary">
                            What would you like to do next?
                        </p>

                    </header>

                    <div class="sof-card-content">

                        <div class="sof-test-actions">

                            <form
                                method="get"
                                action="<?php
                                echo esc_url(
                                    $create_communication_url
                                );
                                ?>"
                                style="display:inline;"
                            >

                                <button
                                    type="submit"
                                    class="sof-button sof-button-primary"
                                >
                                    Create Another Communication
                                </button>

                            </form>

                            <form
                                method="get"
                                action="<?php
                                echo esc_url(
                                    $member_portal_url
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

                        </div>

                    </div>

                </section>

            </main>

        </div>

        <?php

        return (string) ob_get_clean();
    }
}