<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Amazon SES Delivery Provider
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Provider:
 *     Amazon SES Delivery Provider
 *
 * Purpose:
 *     Deliver email Communications through Amazon SES.
 *
 * Responsibilities:
 *     - Provide email delivery through Amazon SES
 *     - Translate Communication information into SES format
 *     - Apply organizational sender information
 *     - Return the SES delivery result
 *
 * Does NOT:
 *     - Select itself as the preferred provider
 *     - Resolve Communication audiences
 *     - Determine recipient eligibility
 *     - Approve or release Communications
 *     - Persist lifecycle state
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AmazonSESDeliveryProvider
    implements SOF_CommunicationDeliveryProvider
{
    /**
     * Return the provider identity.
     */
    public function get_name(): string
    {
        return 'Amazon SES';
    }

    /**
     * Return the supported delivery channel.
     */
    public function get_channel(): string
    {
        return 'email';
    }

    /**
     * Determine whether Amazon SES is available.
     *
     * The SES API integration will be added next.
     */
    public function is_available(): bool
    {
        return false;
    }

    /**
     * Deliver the Communication.
     */
    public function deliver(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        string $destination
    ): array {

        return [
            'success'             => false,
            'provider'            => $this->get_name(),
            'destination'         => $destination,
            'provider_message_id' => '',
            'message'             => 'Amazon SES delivery is not configured yet.',
            'errors'              => [
                'The Amazon SES provider has not yet been configured.'
            ],
        ];
    }
}