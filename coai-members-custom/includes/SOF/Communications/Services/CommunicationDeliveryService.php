<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Delivery Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Service:
 *     Communication Delivery
 *
 * Purpose:
 *     Provide the common delivery capability used by
 *     Communication experiences.
 *
 * Responsibilities:
 *     - Accept a Communication delivery request
 *     - Select an available provider for the requested channel
 *     - Request delivery through that provider
 *     - Return the delivery result
 *
 * Does NOT:
 *     - Resolve audiences
 *     - Determine recipient eligibility
 *     - Approve Communications
 *     - Release Communications
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_CommunicationDeliveryService
{
    /**
     * @var SOF_CommunicationDeliveryProvider[]
     */
    protected array $providers = [];

    public function __construct(
        array $providers = []
    ) {
        if (empty($providers)) {
            $providers = [
                new SOF_WordPressMailDeliveryProvider(),
            ];
        }

        foreach ($providers as $provider) {
            if ($provider instanceof SOF_CommunicationDeliveryProvider) {
                $this->providers[] = $provider;
            }
        }
    }

    /**
     * Deliver a Communication.
     */
    public function deliver(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        string $destination,
        string $channel = 'email'
    ): array {

        $provider =
            $this->find_provider(
                $channel
            );

        if (!$provider) {
            return [
                'success'             => false,
                'provider'            => '',
                'destination'         => $destination,
                'provider_message_id' => '',
                'message'             => 'No delivery provider is available for this channel.',
                'errors'              => [
                    sprintf(
                        'No available provider supports the %s channel.',
                        $channel
                    ),
                ],
            ];
        }

        return $provider->deliver(
            $communication,
            $sender,
            $destination
        );
    }

    /**
     * Find an available provider for a channel.
     */
    protected function find_provider(
        string $channel
    ): ?SOF_CommunicationDeliveryProvider {

        $channel =
            strtolower(
                trim($channel)
            );

        foreach ($this->providers as $provider) {
            if (
                strtolower($provider->get_channel()) === $channel &&
                $provider->is_available()
            ) {
                return $provider;
            }
        }

        return null;
    }
}