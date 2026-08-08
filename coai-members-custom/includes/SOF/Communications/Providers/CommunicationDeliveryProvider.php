<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Delivery Provider
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Provider:
 *     Communication Delivery Provider
 *
 * Purpose:
 *     Define the provider boundary used by Communications
 *     to deliver a Communication through a delivery channel.
 *
 * Responsibilities:
 *     - Identify the provider
 *     - Identify the supported delivery channel
 *     - Determine whether the provider is available
 *     - Deliver a Communication to a destination
 *     - Return the delivery result
 *
 * Does NOT:
 *     - Choose which provider should be used
 *     - Resolve the Communication audience
 *     - Determine recipient eligibility
 *     - Approve a Communication
 *     - Release a Communication
 *     - Persist lifecycle state
 *     - Render presentation
 *
 * ============================================================
 */

interface SOF_CommunicationDeliveryProvider
{
    /**
     * Return the provider identity.
     */
    public function get_name(): string;

    /**
     * Return the delivery channel supported by this provider.
     *
     * Examples:
     *
     * email
     * sms
     * push
     */
    public function get_channel(): string;

    /**
     * Determine whether this provider is currently available.
     */
    public function is_available(): bool;

    /**
     * Deliver a Communication to a destination.
     *
     * @return array{
     *     success: bool,
     *     provider: string,
     *     destination: string,
     *     provider_message_id: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function deliver(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        string $destination
    ): array;
}