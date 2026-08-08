<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF WordPress Mail Delivery Provider
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Provider:
 *     WordPress Mail Delivery Provider
 *
 * Purpose:
 *     Deliver email Communications through WordPress mail.
 *
 * Responsibilities:
 *     - Provide email delivery through wp_mail()
 *     - Translate Communication information into email format
 *     - Apply organizational sender information
 *     - Return the delivery result
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

class SOF_WordPressMailDeliveryProvider
    implements SOF_CommunicationDeliveryProvider
{
    /**
     * Return the provider identity.
     */
    public function get_name(): string
    {
        return 'WordPress Mail';
    }

    /**
     * Return the supported delivery channel.
     */
    public function get_channel(): string
    {
        return 'email';
    }

    /**
     * Determine whether WordPress mail is available.
     */
    public function is_available(): bool
    {
        return function_exists('wp_mail');
    }

    /**
     * Deliver the Communication.
     */
    public function deliver(
        SOF_Communication $communication,
        SOF_CommunicationSender $sender,
        string $destination
    ): array {

        if (!$this->is_available()) {
            return [
                'success'             => false,
                'provider'            => $this->get_name(),
                'destination'         => $destination,
                'provider_message_id' => '',
                'message'             => 'WordPress Mail is not available.',
                'errors'              => [
                    'wp_mail() is not available.'
                ],
            ];
        }

        $destination =
            sanitize_email(
                $destination
            );

        if (!$destination || !is_email($destination)) {
            return [
                'success'             => false,
                'provider'            => $this->get_name(),
                'destination'         => $destination,
                'provider_message_id' => '',
                'message'             => 'The delivery destination is invalid.',
                'errors'              => [
                    'A valid email address is required.'
                ],
            ];
        }

        $subject =
            $communication->get_subject();

        $body =
            $communication->get_body();

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        /*
         * -----------------------------------------------------
         * Sender
         * -----------------------------------------------------
         *
         * Use organizational sender identity when available.
         */

        $sender_name =
            $sender->get_name();

        $sender_email =
            $sender->get_email();

        if (
            $sender_email &&
            is_email($sender_email)
        ) {
            
            /*
             * FluentSMTP owns the physical From address used
             * for organizational delivery through Amazon SES.
             *
             * SOF provides the human sender identity and the
             * destination for replies.
             */
            $headers[] =
                sprintf(
                    'From: %s <%s>',
                    $sender_name,
                    $sender_email
                );
        }
        
        if (
            $sender_email &&
            is_email($sender_email)
        ) {
            $headers[] =
                sprintf(
                    'Reply-To: %s <%s>',
                    $sender_name,
                    $sender_email
                );
        }

        /*
         * -----------------------------------------------------
         * Delivery
         * -----------------------------------------------------
         */

        $sent =
            wp_mail(
                $destination,
                $subject,
                wpautop($body),
                $headers
            );

        if (!$sent) {
            return [
                'success'             => false,
                'provider'            => $this->get_name(),
                'destination'         => $destination,
                'provider_message_id' => '',
                'message'             => 'The Communication could not be delivered.',
                'errors'              => [
                    'wp_mail() returned an unsuccessful result.'
                ],
            ];
        }

        return [
            'success'             => true,
            'provider'            => $this->get_name(),
            'destination'         => $destination,
            'provider_message_id' => '',
            'message'             => 'The Communication was delivered successfully.',
            'errors'              => [],
        ];
    }
}