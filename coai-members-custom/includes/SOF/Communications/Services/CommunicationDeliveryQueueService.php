<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Delivery Queue Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Delivery Queue
 *
 * Purpose:
 *     Prepare and manage durable recipient delivery work for
 *     a Communication.
 *
 * Responsibilities:
 *     - Queue resolved Communication recipients
 *     - Prevent duplicate queue initialization
 *     - Validate recipient delivery destinations
 *     - Report queue delivery progress
 *     - Expose pending, processing, sent, and failed counts
 *
 * Does NOT:
 *     - Discover Communication audiences
 *     - Determine recipient eligibility
 *     - Deliver Communications
 *     - Communicate directly with providers
 *     - Manage background scheduling
 *     - Render Presentation
 *
 * ============================================================
 */

class SOF_CommunicationDeliveryQueueService
{
    protected SOF_CommunicationDeliveryQueueRepository $repository;

    public function __construct(
        SOF_CommunicationDeliveryQueueRepository $repository
    ) {
        $this->repository =
            $repository;
    }

    /**
     * Queue the final resolved recipient population.
     *
     * @return array<string, mixed>
     */
    public function initialize(
        SOF_Communication $communication,
        SOF_CommunicationRecipients $recipients
    ): array {
        $communication_id =
            (int) (
                $communication->get_id()
                ?? 0
            );

        if ($communication_id < 1) {
            return $this->failure(
                'invalid_communication',
                'The Communication must be persisted before delivery can be queued.'
            );
        }

        // -------------------------------------------------
        // Protect Existing Queue
        // -------------------------------------------------

        $existing_count =
            $this->repository
                ->count_for_communication(
                    $communication_id
                );

        if ($existing_count > 0) {
            return [
                'success' =>
                    true,

                'status' =>
                    'already_queued',

                'message' =>
                    'The Communication delivery queue already exists.',

                'queued' =>
                    $existing_count,

                'failed' =>
                    0,

                'errors' =>
                    [],
            ];
        }

        // -------------------------------------------------
        // Available Recipients
        // -------------------------------------------------

        $available_recipients =
            $recipients
                ->get_available_recipients();

        if (!$available_recipients) {
            return $this->failure(
                'no_recipients',
                'No recipients are currently available for queued delivery.'
            );
        }

        // -------------------------------------------------
        // Queue Recipients
        // -------------------------------------------------

        $queued_count = 0;

        $failed_count = 0;

        $errors = [];

        foreach ($available_recipients as $recipient) {

            $member_id =
                isset($recipient['member_id'])
                    ? (int) $recipient['member_id']
                    : 0;

            $email =
                isset($recipient['email'])
                    ? sanitize_email(
                        (string) $recipient['email']
                    )
                    : '';

            if ($member_id < 1) {

                $failed_count++;

                $errors[] =
                    'A recipient could not be queued because no valid member identity was available.';

                continue;
            }

            if (
                $email === '' ||
                !is_email($email)
            ) {

                $failed_count++;

                $errors[] =
                    sprintf(
                        'Member %d could not be queued because no valid email address was available.',
                        $member_id
                    );

                continue;
            }

            $item =
                new SOF_CommunicationDeliveryQueueItem([
                    'communication_id' =>
                        $communication_id,

                    'member_id' =>
                        $member_id,

                    'email' =>
                        $email,

                    'status' =>
                        SOF_CommunicationDeliveryQueueItem::STATUS_PENDING,

                    'attempts' =>
                        0,

                    'created_at' =>
                        current_time('mysql'),
                ]);

            $queue_id =
                $this->repository
                    ->create(
                        $item
                    );

            if (!$queue_id) {

                $failed_count++;

                $errors[] =
                    sprintf(
                        'Member %d could not be added to the Communication delivery queue.',
                        $member_id
                    );

                continue;
            }

            $queued_count++;
        }

        // -------------------------------------------------
        // Queue Result
        // -------------------------------------------------

        if ($queued_count < 1) {
            return [
                'success' =>
                    false,

                'status' =>
                    'queue_failed',

                'message' =>
                    'The Communication delivery queue could not be created.',

                'queued' =>
                    0,

                'failed' =>
                    $failed_count,

                'errors' =>
                    $errors,
            ];
        }

        return [
            'success' =>
                true,

            'status' =>
                $failed_count > 0
                    ? 'queued_with_failures'
                    : 'queued',

            'message' =>
                $failed_count > 0
                    ? 'The Communication was queued with some recipient failures.'
                    : 'The Communication delivery queue was created successfully.',

            'queued' =>
                $queued_count,

            'failed' =>
                $failed_count,

            'errors' =>
                $errors,
        ];
    }

    /**
     * Return delivery queue progress.
     *
     * @return array<string, int>
     */
    public function progress(
        int $communication_id
    ): array {
        if ($communication_id < 1) {
            return [
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
        }

        return [
            'total' =>
                $this->repository
                    ->count_for_communication(
                        $communication_id
                    ),

            'pending' =>
                $this->repository
                    ->count_by_status(
                        $communication_id,
                        SOF_CommunicationDeliveryQueueItem::STATUS_PENDING
                    ),

            'processing' =>
                $this->repository
                    ->count_by_status(
                        $communication_id,
                        SOF_CommunicationDeliveryQueueItem::STATUS_PROCESSING
                    ),

            'sent' =>
                $this->repository
                    ->count_by_status(
                        $communication_id,
                        SOF_CommunicationDeliveryQueueItem::STATUS_SENT
                    ),

            'failed' =>
                $this->repository
                    ->count_by_status(
                        $communication_id,
                        SOF_CommunicationDeliveryQueueItem::STATUS_FAILED
                    ),
        ];
    }

    /**
     * Determine whether every queued recipient has reached
     * a final delivery state.
     */
    public function is_complete(
        int $communication_id
    ): bool {
        $progress =
            $this->progress(
                $communication_id
            );

        if ($progress['total'] < 1) {
            return false;
        }

        return (
            $progress['pending'] === 0 &&
            $progress['processing'] === 0
        );
    }

    /**
     * Build a failed queue result.
     *
     * @return array<string, mixed>
     */
    protected function failure(
        string $status,
        string $message,
        array $errors = []
    ): array {
        return [
            'success' =>
                false,

            'status' =>
                $status,

            'message' =>
                $message,

            'queued' =>
                0,

            'failed' =>
                0,

            'errors' =>
                $errors,
        ];
    }
}