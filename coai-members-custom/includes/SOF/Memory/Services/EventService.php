<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Event Service
 * ============================================================
 *
 * Framework:
 *     Memory
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Event
 *
 * Purpose:
 *     Provide the business-facing gateway for recording
 *     immutable organizational Events.
 *
 * Responsibilities:
 *     - Accept a complete Event
 *     - Confirm the Event is valid
 *     - Delegate Event persistence to the Event Repository
 *     - Retrieve and return the stored Event
 *
 * Does NOT:
 *     - Construct domain-specific Events
 *     - Interpret Events
 *     - Update Events
 *     - Delete Events
 *     - Assemble a Timeline
 *     - Construct organizational Memory
 *     - Assess Situations
 *     - Recommend business actions
 *     - Render presentation
 *
 * Principle:
 *     Business domains record Events through the Event Service.
 *     Business domains do not communicate directly with
 *     Event persistence.
 *
 * ============================================================
 */

class SOF_EventService
{
    /**
     * Event persistence repository.
     */
    protected SOF_EventRepository $repository;

    /**
     * Construct the Event Service.
     */
    public function __construct(
        ?SOF_EventRepository $repository = null
    ) {
        $this->repository =
            $repository
            ?? new SOF_EventRepository();
    }

    /**
     * Record an immutable organizational Event.
     *
     * Returns the stored Event after persistence and retrieval.
     */
    public function record(
        SOF_Event $event
    ): ?SOF_Event {
        if (!$event->is_valid()) {
            return null;
        }

        $event_id =
            $this->repository->create(
                $event
            );

        if ($event_id === null) {
            return null;
        }

        return $this->repository->find(
            $event_id
        );
    }
}