<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Test Delivery Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Service:
 *     Communication Test Delivery
 *
 * Purpose:
 *     Prepare and request delivery of a test Communication
 *     through the common Communication Delivery Service.
 *
 * Responsibilities:
 *     - Prepare test delivery
 *     - Add the Test subject prefix
 *     - Request delivery through Communication Delivery Service
 *     - Return the delivery result
 *
 * Does NOT:
 *     - Deliver email directly
 *     - Select a delivery provider
 *     - Resolve audiences
 *     - Approve Communications
 *     - Release Communications
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_CommunicationTestDeliveryService
{
    protected SOF_CommunicationDeliveryService $delivery_service;

    public function __construct(
        ?SOF_CommunicationDeliveryService $delivery_service = null
    ) {
        $this->delivery_service =
            $delivery_service ??
            new SOF_CommunicationDeliveryService();
    }

    /**
     * Send a test Communication.
     */
    public function send_test(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        string $destination
    ): array {

        /*
         * -----------------------------------------------------
         * Test Communication
         * -----------------------------------------------------
         *
         * Preserve the persisted Communication while creating
         * a temporary delivery representation for the test.
         */

        $test_communication =
            clone $communication;

        $test_communication->set_subject(
            'Test: ' .
            $communication->get_subject()
        );

        return $this->delivery_service->deliver(
            $test_communication,
            $sender,
            $destination,
            'email'
        );
    }
}