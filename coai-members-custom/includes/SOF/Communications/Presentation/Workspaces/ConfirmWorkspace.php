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
                    'Communication Sent';

                $result_message =
                    sprintf(
                        'Your communication was sent to %d %s.',
                        $delivered_count,
                        $delivered_count === 1
                            ? 'member'
                            : 'members'
                    );
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
        // Presentation
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-communications-workspace sof-confirm-workspace">

            <div class="sof-workspace-header">

                <h1>
                    Confirm Communication
                </h1>

                <p>
                    Review the final result of this communication.
                </p>

            </div>

            <div class="sof-communication-card">

                <div class="sof-communication-status">

                    <h2>
                        <?php echo esc_html($result_title); ?>
                    </h2>

                    <p>
                        <?php echo esc_html($result_message); ?>
                    </p>

                </div>

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
                        echo esc_html(
                            $membership_status_label
                        );
                        ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Delivery
                    </strong>

                    <div>
                        <?php echo esc_html($channel_label); ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Attempted
                    </strong>

                    <div>
                        <?php echo esc_html((string) $attempted_count); ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Delivered
                    </strong>

                    <div>
                        <?php echo esc_html((string) $delivered_count); ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Failed
                    </strong>

                    <div>
                        <?php echo esc_html((string) $failed_count); ?>
                    </div>

                </div>

                <div class="sof-communication-detail">

                    <strong>
                        Sent
                    </strong>

                    <div>
                        <?php echo esc_html($sent_label); ?>
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

            </div>

        </div>

        <?php

        return (string) ob_get_clean();
    }
}