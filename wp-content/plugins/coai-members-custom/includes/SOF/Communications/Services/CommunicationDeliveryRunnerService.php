<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Delivery Runner Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Delivery Runner
 *
 * Purpose:
 *     Coordinate one background execution of a queued
 *     Communication delivery.
 *
 * Responsibilities:
 *     - Load the persisted Communication
 *     - Confirm the Communication is being delivered
 *     - Resolve the persisted sender identity
 *     - Process one controlled delivery batch
 *     - Finalize completed delivery
 *     - Schedule the next batch when work remains
 *
 * Does NOT:
 *     - Discover audiences
 *     - Build the recipient queue
 *     - Approve Communications
 *     - Render Presentation
 *
 * ============================================================
 */

class SOF_CommunicationDeliveryRunnerService
{
    public const CRON_HOOK =
        'sof_process_communication_delivery';

    public const RECOVERY_CRON_HOOK =
        'sof_recover_communication_deliveries';

    public const BATCH_SIZE =
        25;

    public const NEXT_BATCH_DELAY =
        5;

    /**
     * Handle one scheduled background execution.
     */
    public static function handle(
        int $communication_id
    ): void {
        
        $communication_id =
            absint(
                $communication_id
            );

        if ($communication_id < 1) {
            return;
        }

        // -------------------------------------------------
        // Communication
        // -------------------------------------------------

        $communication_repository =
            new SOF_CommunicationRepository();

        $persistence_service =
            new SOF_CommunicationPersistenceService(
                $communication_repository
            );

        $communication =
            $persistence_service->find(
                $communication_id
            );

        if (!$communication) {
            return;
        }

        if (!$communication->is_sending()) {
            return;
        }

        // -------------------------------------------------
        // Persisted Sender Identity
        // -------------------------------------------------

        $sender_user_id =
            (int) (
                $communication->get_created_by()
                ?? 0
            );

        if ($sender_user_id < 1) {

            error_log(
                sprintf(
                    '[SOF Communications] Delivery runner could not resolve a sender user for Communication %d.',
                    $communication_id
                )
            );

            return;
        }

        $audience_service =
            new SOF_CommunicationAudienceService();

        $sender_service =
            new SOF_CommunicationSenderService(
                $audience_service
            );

        $sender =
            $sender_service
                ->resolve_sender_for_user(
                    $sender_user_id
                );

        if (!$sender) {

            error_log(
                sprintf(
                    '[SOF Communications] Delivery runner could not resolve sender identity for Communication %d.',
                    $communication_id
                )
            );

            return;
        }

        // -------------------------------------------------
        // Queue Services
        // -------------------------------------------------

        $queue_repository =
            new SOF_CommunicationDeliveryQueueRepository();

        $queue_service =
            new SOF_CommunicationDeliveryQueueService(
                $queue_repository
            );

        $delivery_service =
            new SOF_CommunicationDeliveryService();

        $worker_service =
            new SOF_CommunicationDeliveryWorkerService(
                $queue_repository,
                $queue_service,
                $delivery_service
            );

        // -------------------------------------------------
        // Process One Batch
        // -------------------------------------------------

        $worker_result =
            $worker_service->process_batch(
                $communication,
                $sender,
                self::BATCH_SIZE
            );

        if (empty($worker_result['success'])) {

            error_log(
                sprintf(
                    '[SOF Communications] Delivery worker failed for Communication %d: %s',
                    $communication_id,
                    (string) (
                        $worker_result['message']
                        ?? 'Unknown worker failure.'
                    )
                )
            );

            return;
        }

        // -------------------------------------------------
        // Current Progress
        // -------------------------------------------------

        $progress =
            $queue_service->progress(
                $communication_id
            );

        $remaining =
            (int) $progress['pending'] +
            (int) $progress['processing'];

        // -------------------------------------------------
        // More Work Remains
        // -------------------------------------------------

        if ($remaining > 0) {

            self::schedule(
                $communication_id
            );

            return;
        }

        // -------------------------------------------------
        // Complete Communication Delivery
        // -------------------------------------------------

        $communications_service =
            new SOF_CommunicationsService();

        $completion_result =
            $communications_service
                ->complete_delivery(
                    $communication,
                    (int) $progress['sent'],
                    (int) $progress['failed']
                );

        if (empty($completion_result['success'])) {

            error_log(
                sprintf(
                    '[SOF Communications] Delivery completion failed for Communication %d: %s',
                    $communication_id,
                    (string) (
                        $completion_result['message']
                        ?? 'Unknown completion failure.'
                    )
                )
            );

            return;
        }

        $saved =
            $persistence_service->save(
                $communication
            );

        if (!$saved) {

            error_log(
                sprintf(
                    '[SOF Communications] Final delivery state could not be persisted for Communication %d.',
                    $communication_id
                )
            );
        }
    }
    
    /**
     * Recover queued Communications whose individual runner
     * event is no longer scheduled.
     */
    public static function recover_pending_deliveries(): void
    {
        $queue_repository =
            new SOF_CommunicationDeliveryQueueRepository();

        $communication_ids =
            $queue_repository
                ->find_communication_ids_with_pending_work();

        if (!$communication_ids) {
            return;
        }

        $communication_repository =
            new SOF_CommunicationRepository();

        $persistence_service =
            new SOF_CommunicationPersistenceService(
                $communication_repository
            );

        foreach ($communication_ids as $communication_id) {

            $communication =
                $persistence_service->find(
                    $communication_id
                );

            if (!$communication) {
                continue;
            }

            if (!$communication->is_sending()) {
                continue;
            }

            $args = [
                $communication_id,
            ];

            if (
                wp_next_scheduled(
                    self::CRON_HOOK,
                    $args
                )
            ) {
                continue;
            }

            self::schedule(
                $communication_id
            );
        }
    }


    /**
     * Schedule the next delivery batch.
     */
    public static function schedule(
        int $communication_id
    ): bool {
        $communication_id =
            absint(
                $communication_id
           );

        if ($communication_id < 1) {
            return false;
        }

        $args = [
            $communication_id,
        ];

        if (
            wp_next_scheduled(
                self::CRON_HOOK,
                $args
            )
        ) {
            return true;
        }

        return (bool) wp_schedule_single_event(
            time() + self::NEXT_BATCH_DELAY,
            self::CRON_HOOK,
            $args
        );
    }
}