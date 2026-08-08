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
     * Constructor.
     */
    public function __construct(
        SOF_CommunicationRepository $repository
    ) {
        $this->repository = $repository;
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

        return $this->repository->find(
            $communication_id
        );
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