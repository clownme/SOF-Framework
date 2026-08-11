<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Delivery Worker Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Delivery Worker
 *
 * Purpose:
 *     Process a controlled batch of queued Communication
 *     recipient deliveries.
 *
 * Responsibilities:
 *     - Claim pending queue items
 *     - Deliver one queued recipient at a time
 *     - Record successful delivery
 *     - Record failed delivery
 *     - Return batch delivery progress
 *
 * Does NOT:
 *     - Discover Communication audiences
 *     - Determine recipient eligibility
 *     - Create the delivery queue
 *     - Approve Communications
 *     - Schedule background execution
 *     - Render Presentation
 *
 * ============================================================
 */

class SOF_CommunicationDeliveryWorkerService
{
    protected SOF_CommunicationDeliveryQueueRepository $queue_repository;

    protected SOF_CommunicationDeliveryQueueService $queue_service;

    protected SOF_CommunicationDeliveryService $delivery_service;

    public function __construct(
        SOF_CommunicationDeliveryQueueRepository $queue_repository,
        SOF_CommunicationDeliveryQueueService $queue_service,
        SOF_CommunicationDeliveryService $delivery_service
    ) {
        $this->queue_repository =
            $queue_repository;

        $this->queue_service =
            $queue_service;

        $this->delivery_service =
            $delivery_service;
    }

    /**
     * Process the next queued delivery batch.
     *
     * @return array<string, mixed>
     */
    public function process_batch(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        int $batch_size = 25
    ): array {
        $communication_id =
            (int) (
                $communication->get_id()
                ?? 0
            );

        if ($communication_id < 1) {
            return $this->failure(
                'invalid_communication',
                'A persisted Communication is required before queued delivery can be processed.'
            );
        }

        // -------------------------------------------------
        // Batch Size
        // -------------------------------------------------

        $batch_size =
            max(
                1,
                min(
                    100,
                    $batch_size
                )
            );

        // -------------------------------------------------
        // Claim Pending Work
        // -------------------------------------------------

        $queue_items =
            $this->queue_repository
                ->claim_pending_batch(
                    $communication_id,
                    $batch_size
                );

        if (!$queue_items) {

            $progress =
                $this->queue_service
                    ->progress(
                        $communication_id
                    );

            return [
                'success' =>
                    true,

                'status' =>
                    $this->queue_service
                        ->is_complete(
                            $communication_id
                        )
                            ? 'complete'
                            : 'no_pending_work',

                'message' =>
                    $this->queue_service
                        ->is_complete(
                            $communication_id
                        )
                            ? 'The Communication delivery queue is complete.'
                            : 'No pending Communication delivery work is currently available.',

                'processed' =>
                    0,

                'sent' =>
                    0,

                'failed' =>
                    0,

                'progress' =>
                    $progress,

                'errors' =>
                    [],
            ];
        }

        // -------------------------------------------------
        // Process Claimed Work
        // -------------------------------------------------

        $processed_count = 0;

        $sent_count = 0;

        $failed_count = 0;

        $errors = [];

        foreach ($queue_items as $queue_item) {

            $queue_id =
                (int) (
                    $queue_item->get_id()
                    ?? 0
                );

            if ($queue_id < 1) {
                continue;
            }

            $processed_count++;

            $destination =
                sanitize_email(
                    $queue_item->get_email()
                );

            if (
                $destination === '' ||
                !is_email($destination)
            ) {

                $failed_count++;

                $error_message =
                    'The queued recipient does not contain a valid email address.';

                $this->queue_repository
                    ->mark_failed(
                        $queue_id,
                        $error_message
                    );

                $errors[] =
                    sprintf(
                        'Queue item %d: %s',
                        $queue_id,
                        $error_message
                    );

                continue;
            }

            // -------------------------------------------------
            // Provider Delivery
            // -------------------------------------------------

            $delivery_result =
                $this->delivery_service
                    ->deliver(
                        $communication,
                        $sender,
                        $destination,
                        $communication->get_channel()
                    );

            if (!empty($delivery_result['success'])) {

                $provider_message_id =
                    isset(
                        $delivery_result[
                            'provider_message_id'
                        ]
                    )
                        ? (string) $delivery_result[
                            'provider_message_id'
                        ]
                        : '';

                $recorded =
                    $this->queue_repository
                        ->mark_sent(
                            $queue_id,
                            $provider_message_id
                        );

                if (!$recorded) {

                    $failed_count++;

                    $errors[] =
                        sprintf(
                            'Queue item %d was delivered, but its successful delivery state could not be recorded.',
                            $queue_id
                        );

                    continue;
                }

                $sent_count++;

                continue;
            }

            // -------------------------------------------------
            // Failed Provider Delivery
            // -------------------------------------------------

            $error_message =
                isset($delivery_result['message'])
                    ? trim(
                        (string) $delivery_result['message']
                    )
                    : '';

            if ($error_message === '') {
                $error_message =
                    'The Communication could not be delivered to this recipient.';
            }

            $recorded =
                $this->queue_repository
                    ->mark_failed(
                        $queue_id,
                        $error_message
                    );

            if (!$recorded) {
                $errors[] =
                    sprintf(
                        'Queue item %d failed delivery and its failure state could not be recorded.',
                        $queue_id
                    );
            } else {
                $errors[] =
                    sprintf(
                        'Queue item %d: %s',
                        $queue_id,
                        $error_message
                    );
            }

            $failed_count++;
        }

        // -------------------------------------------------
        // Current Queue Progress
        // -------------------------------------------------

        $progress =
            $this->queue_service
                ->progress(
                    $communication_id
                );

        $complete =
            $this->queue_service
                ->is_complete(
                    $communication_id
                );

        return [
            'success' =>
                true,

            'status' =>
                $complete
                    ? 'complete'
                    : 'batch_processed',

            'message' =>
                $complete
                    ? 'The Communication delivery queue is complete.'
                    : 'The next Communication delivery batch was processed.',

            'processed' =>
                $processed_count,

            'sent' =>
                $sent_count,

            'failed' =>
                $failed_count,

            'progress' =>
                $progress,

            'errors' =>
                $errors,
        ];
    }

    /**
     * Build a failed worker result.
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

            'processed' =>
                0,

            'sent' =>
                0,

            'failed' =>
                0,

            'progress' => [
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
            ],

            'errors' =>
                $errors,
        ];
    }
}