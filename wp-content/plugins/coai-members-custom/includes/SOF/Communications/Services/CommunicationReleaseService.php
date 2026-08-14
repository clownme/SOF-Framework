<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Release Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Release
 *
 * Purpose:
 *     Begin durable queued delivery of an approved
 *     Communication.
 *
 * Responsibilities:
 *     - Confirm the Communication may begin delivery
 *     - Atomically claim the Communication for delivery
 *     - Freeze the final resolved recipient population
 *     - Initialize durable recipient delivery work
 *     - Return the queued delivery result
 *
 * Does NOT:
 *     - Discover Communication audiences
 *     - Determine recipient eligibility
 *     - Approve Communications
 *     - Deliver recipients directly
 *     - Communicate directly with delivery providers
 *     - Process background delivery batches
 *     - Render Presentation
 *
 * ============================================================
 */

class SOF_CommunicationReleaseService
{
    protected SOF_CommunicationsService $communications_service;

    protected SOF_CommunicationDeliveryService $delivery_service;

    protected SOF_CommunicationPersistenceService $persistence_service;

    protected SOF_CommunicationDeliveryQueueService $queue_service;

    public function __construct(
        SOF_CommunicationsService $communications_service,
        SOF_CommunicationDeliveryService $delivery_service,
        SOF_CommunicationPersistenceService $persistence_service,
        ?SOF_CommunicationDeliveryQueueService $queue_service = null
    ) {
        $this->communications_service =
            $communications_service;

        /*
         * Preserve the existing Delivery Service dependency.
         *
         * Delivery is now performed by the worker rather than
         * directly by the Release Service, but retaining this
         * dependency keeps the current constructor contract
         * compatible while the queued delivery architecture
         * is introduced.
         */
        $this->delivery_service =
            $delivery_service;

        $this->persistence_service =
            $persistence_service;

        if ($queue_service) {

            $this->queue_service =
                $queue_service;

        } else {

            $queue_repository =
                new SOF_CommunicationDeliveryQueueRepository();

            $this->queue_service =
                new SOF_CommunicationDeliveryQueueService(
                    $queue_repository
                );
        }
    }

    /**
     * Begin queued delivery of an approved Communication.
     *
     * @return array<string, mixed>
     */
    public function release(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        SOF_CommunicationRecipients $recipients
    ): array {

        // -------------------------------------------------
        // Confirm Delivery May Begin
        // -------------------------------------------------

        $begin_result =
            $this->communications_service
                ->begin_delivery(
                    $communication
                );

        if (!$begin_result['success']) {
            return [
                'success' =>
                    false,

                'status' =>
                    $begin_result['status'],

                'message' =>
                    $begin_result['message'],

                'queued' =>
                    0,

                'delivered' =>
                    0,

                'failed' =>
                    0,

                'errors' =>
                    $begin_result['errors'],
            ];
        }

        // -------------------------------------------------
        // Atomically Claim Communication
        // -------------------------------------------------

        $sending_communication =
            $this->persistence_service
                ->begin_delivery(
                    $communication
                );

        if (!$sending_communication) {
            return [
                'success' =>
                    false,

                'status' =>
                    'delivery_start_failed',

                'message' =>
                    'The communication could not begin delivery because it is no longer available for release.',

                'queued' =>
                    0,

                'delivered' =>
                    0,

                'failed' =>
                    0,

                'errors' => [
                    'The approved Communication could not be claimed for delivery.',
                ],
            ];
        }

        $communication =
            $sending_communication;

        // -------------------------------------------------
        // Final Available Recipients
        // -------------------------------------------------

        $available_recipients =
            $recipients
                ->get_available_recipients();

        if (!$available_recipients) {

            $failure_result =
                $this->communications_service
                    ->fail_delivery(
                        $communication,
                        0,
                        [
                            'No recipients are currently available for delivery.',
                        ]
                    );

            $saved_communication =
                $this->persistence_service
                    ->save(
                        $communication
                    );

            if (!$saved_communication) {
                return [
                    'success' =>
                        false,

                    'status' =>
                        'release_result_persistence_failed',

                    'message' =>
                        'No recipients were available for delivery, but the final communication state could not be saved.',

                    'queued' =>
                        0,

                    'delivered' =>
                        0,

                    'failed' =>
                        0,

                    'errors' => [
                        'The delivery failure state could not be persisted.',
                    ],
                ];
            }

            $failure_result['queued'] = 0;

            return $failure_result;
        }

        // -------------------------------------------------
        // Initialize Durable Delivery Queue
        // -------------------------------------------------

        $queue_result =
            $this->queue_service
                ->initialize(
                    $communication,
                    $recipients
                );

        if (empty($queue_result['success'])) {

            $queue_errors =
                isset($queue_result['errors']) &&
                is_array($queue_result['errors'])
                    ? $queue_result['errors']
                    : [];

            if (!$queue_errors) {
                $queue_errors[] =
                    'The Communication delivery queue could not be initialized.';
            }

            $failure_result =
                $this->communications_service
                    ->fail_delivery(
                        $communication,
                        (int) (
                            $queue_result['failed']
                            ?? 0
                        ),
                        $queue_errors
                    );

            $saved_communication =
                $this->persistence_service
                    ->save(
                        $communication
                    );

            if (!$saved_communication) {
                return [
                    'success' =>
                        false,

                    'status' =>
                        'release_result_persistence_failed',

                    'message' =>
                        'The delivery queue could not be created and the final Communication state could not be saved.',

                    'queued' =>
                        0,

                    'delivered' =>
                        0,

                    'failed' =>
                        (int) (
                            $queue_result['failed']
                            ?? 0
                        ),

                    'errors' =>
                        $queue_errors,
                ];
            }

            $failure_result['queued'] = 0;

            return $failure_result;
        }

        // -------------------------------------------------
        // Delivery Successfully Queued
        // -------------------------------------------------

        $scheduled =
            SOF_CommunicationDeliveryRunnerService::schedule(
                (int) $communication->get_id()
            );

        if (!$scheduled) {

            return [
                'success' =>
                    false,

                'status' =>
                    'delivery_schedule_failed',

                'message' =>
                    'The Communication was queued, but background delivery could not be scheduled.',

                'queued' =>
                    (int) (
                        $queue_result['queued']
                        ?? 0
                    ),

                'delivered' =>
                    0,

                'failed' =>
                    (int) (
                        $queue_result['failed']
                        ?? 0
                    ),

                'errors' => [
                    'The background delivery runner could not be scheduled.',
                ],
            ];
        }

        return [
            'success' =>
                true,

            'status' =>
                'queued',

            'message' =>
                'The Communication has been queued for organizational delivery.',

            'queued' =>
                (int) (
                    $queue_result['queued']
                    ?? 0
                ),

            'delivered' =>
                0,

            'failed' =>
                (int) (
                    $queue_result['failed']
                    ?? 0
                ),

            'errors' =>
                isset($queue_result['errors']) &&
                is_array($queue_result['errors'])
                    ? $queue_result['errors']
                    : [],
        ];
    }
}