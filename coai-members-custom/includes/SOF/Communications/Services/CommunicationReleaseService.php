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
 *     Coordinate the immediate release of an approved
 *     Communication to its currently eligible recipients.
 *
 * Responsibilities:
 *     - Confirm the Communication may begin delivery
 *     - Begin the Communication delivery lifecycle
 *     - Deliver the Communication to each available recipient
 *     - Record successful and failed deliveries
 *     - Complete the Communication delivery lifecycle
 *     - Return the release result
 *
 * Does NOT:
 *     - Discover Communication audiences
 *     - Determine recipient eligibility
 *     - Approve Communications
 *     - Communicate directly with delivery providers
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_CommunicationReleaseService
{
    protected SOF_CommunicationsService $communications_service;

    protected SOF_CommunicationDeliveryService $delivery_service;
    
    protected SOF_CommunicationPersistenceService $persistence_service;

    public function __construct(
        SOF_CommunicationsService $communications_service,
        SOF_CommunicationDeliveryService $delivery_service,
        SOF_CommunicationPersistenceService $persistence_service
    ) {
        $this->communications_service =
            $communications_service;

        $this->delivery_service =
            $delivery_service;
    
        $this->persistence_service =
        $persistence_service;
    }

    /**
     * Release an approved Communication immediately.
     *
     * @return array<string, mixed>
     */
    public function release(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        SOF_CommunicationRecipients $recipients
    ): array {

        // -------------------------------------------------
        // Begin Delivery
        // -------------------------------------------------

        $begin_result =
            $this->communications_service
                ->begin_delivery(
                    $communication
                );

        if (!$begin_result['success']) {
            return [
                'success' => false,
                'status' => $begin_result['status'],
                'message' => $begin_result['message'],
                'delivered' => 0,
                'failed' => 0,
                'errors' => $begin_result['errors'],
            ];
        }
        
        // -------------------------------------------------
        // Atomically Begin Delivery
        // -------------------------------------------------

        $sending_communication =
            $this->persistence_service
                ->begin_delivery(
                    $communication
                );

        if (!$sending_communication) {
            return [
                'success' => false,
                'status' => 'delivery_start_failed',
                'message' =>
                    'The communication could not begin delivery because it is no longer available for release.',
                'delivered' => 0,
                'failed' => 0,
                'errors' => [
                    'The approved Communication could not be claimed for delivery.',
                ],
            ];
        }

        $communication =
            $sending_communication;

        // -------------------------------------------------
        // Available Recipients
        // -------------------------------------------------

        $available_recipients =
            $recipients->get_available_recipients();

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
                    'success' => false,
                    'status' => 'release_result_persistence_failed',
                    'message' =>
                        'No recipients were available for delivery, but the final communication state could not be saved.',
                    'delivered' => 0,
                    'failed' => 0,
                    'errors' => [
                        'The delivery failure state could not be persisted.',
                    ],
                ];
            }

            return $failure_result;
        }

        // -------------------------------------------------
        // Delivery
        // -------------------------------------------------

        $delivered_count = 0;
        $failed_count = 0;
        $errors = [];

        foreach ($available_recipients as $recipient) {

            $destination =
                sanitize_email(
                    (string) (
                        $recipient['email'] ?? ''
                    )
                );

            if (
                $destination === '' ||
                !is_email($destination)
            ) {
                $failed_count++;

                $errors[] =
                    'A recipient could not be delivered because no valid email address was available.';

                continue;
            }

            $delivery_result =
                $this->delivery_service
                    ->deliver(
                        $communication,
                        $sender,
                        $destination,
                        $communication->get_channel()
                    );

            if (!empty($delivery_result['success'])) {

                $delivered_count++;

            } else {

                $failed_count++;

                $errors[] =
                    (string) (
                        $delivery_result['message']
                        ?? 'A recipient delivery failed.'
                    );
            }
        }

        // -------------------------------------------------
        // Delivery Result
        // -------------------------------------------------

        if ($delivered_count < 1) {

            $release_result =
                $this->communications_service
                    ->fail_delivery(
                        $communication,
                        $failed_count,
                        $errors
                    );

        } else {

            $release_result =
                $this->communications_service
                    ->complete_delivery(
                        $communication,
                        $delivered_count,
                        $failed_count
                    );
        }

        // -------------------------------------------------
        // Persist Delivery Result
        // -------------------------------------------------

        $saved_communication =
            $this->persistence_service
                ->save(
                    $communication
                );

        if (!$saved_communication) {
            return [
                'success' => false,
                'status' => 'release_result_persistence_failed',
                'message' =>
                    'The communication delivery was processed, but the final lifecycle result could not be saved.',
                'delivered' => $delivered_count,
                'failed' => $failed_count,
                'errors' => [
                    'The final communication delivery state could not be persisted.',
                ],
            ];
        }

        return $release_result;
    }
}