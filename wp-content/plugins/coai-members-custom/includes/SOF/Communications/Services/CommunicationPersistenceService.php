<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Persistence Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Service:
 *     Communication Persistence
 *
 * Purpose:
 *     Persist and retrieve Communication business objects.
 *
 * Responsibilities:
 *     - Persist composed Communications
 *     - Return persistent Communication identity
 *     - Retrieve persisted Communications
 *     - Protect persistence implementation from Presentation
 *
 * Does NOT:
 *     - Compose communication content
 *     - Determine audiences
 *     - Resolve recipients
 *     - Verify communications
 *     - Render workspaces
 *     - Expose database implementation
 *
 * ============================================================
 */

class SOF_CommunicationPersistenceService
{
/**
 * Communication repository.
 */
protected SOF_CommunicationRepository $repository;

/**
 * Organizational Event service.
 */
protected SOF_EventService $event_service;

/**
 * Constructor.
 */
 
public function __construct(
    SOF_CommunicationRepository $repository,
    ?SOF_EventService $event_service = null
) {
    $this->repository =
        $repository;

    $this->event_service =
        $event_service
        ?? new SOF_EventService();
}

    /**
     * Persist a new Communication.
     *
     * A persisted Communication is returned so the caller
     * receives the database-backed business object containing
     * its persistent identity.
     */
    public function persist(
        SOF_Communication $communication
    ): ?SOF_Communication {
        $communication_id =
            $this->repository->create(
                $communication
            );

        if (!$communication_id) {
            return null;
        }

        $persisted_communication =
            $this->repository->find(
                $communication_id
            );
 
        if (!$persisted_communication) {
            return null;
        }

    // -------------------------------------------------
    // Organizational Memory
    // -------------------------------------------------

    $event =
        new SOF_Event([
            'domain' =>
                'communications',

            'entity_type' =>
                'communication',

            'entity_id' =>
                $communication_id,

            'event_type' =>
                'communication.created',

            'actor_id' =>
                $persisted_communication
                    ->get_created_by(),

            'occurred_at' =>
                $persisted_communication
                    ->get_created_at()
                ?: current_time('mysql'),

            'summary' =>
                'Communication created.',

            'metadata' => [
                'subject' =>
                    $persisted_communication
                        ->get_subject(),

                'audience_key' =>
                    $persisted_communication
                        ->get_audience_key(),

                'audience_name' =>
                    $persisted_communication
                        ->get_audience_name(),

                'channel' =>
                    $persisted_communication
                        ->get_channel(),

                'recipient_count' =>
                    $persisted_communication
                        ->get_recipient_count(),
            ],
        ]);

    $recorded_event =
        $this->event_service->record(
            $event
        );

    if (!$recorded_event) {
        error_log(
            sprintf(
                '[SOF Memory] Communication created, but Event recording failed. Communication ID: %d',
                $communication_id
            )
        );
    }

    return $persisted_communication;
}
    
    /**
     * Save changes to an existing persisted Communication.
     *
     * Returns the refreshed persisted Communication when
     * successful.
     */
    public function save(
        SOF_Communication $communication
    ): ?SOF_Communication {
        $communication_id =
            $communication->get_id();

        if (!$communication_id) {
            return null;
        }

        $saved =
            $this->repository->update(
                $communication
            );

        if (!$saved) {
            return null;
        }

        return $this->repository->find(
            $communication_id
       );
    }
    
    /**
     * Atomically claim an approved Communication for delivery.
     *
     * Returns the refreshed persisted Communication when
     * successful.
     */
    public function begin_delivery(
        SOF_Communication $communication
    ): ?SOF_Communication {
        $communication_id =
            $communication->get_id();

        if (!$communication_id) {
            return null;
        }

        $claimed =
            $this->repository->begin_delivery(
                $communication_id
            );

        if (!$claimed) {
            return null;
        }

        return $this->repository->find(
            $communication_id
        );
    }

    /**
     * Retrieve a persisted Communication by identity.
     */
    public function find(
        int $communication_id
    ): ?SOF_Communication {
        if ($communication_id <= 0) {
            return null;
        }

        return $this->repository->find(
            $communication_id
        );
    }
}