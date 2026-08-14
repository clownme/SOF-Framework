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

        $mail_error = null;

        $mail_failed_handler =
            function ($error) use (&$mail_error): void {
                if ($error instanceof WP_Error) {
                    $mail_error = $error;
                }
            };

        add_action(
            'wp_mail_failed',
            $mail_failed_handler
        );

        $sent =
            wp_mail(
                $destination,
                $subject,
                wpautop($body),
                $headers
            );

        remove_action(
            'wp_mail_failed',
            $mail_failed_handler
        );

        if (!$sent) {

            $errors = [];

            if ($mail_error instanceof WP_Error) {
                $errors =
                    $mail_error->get_error_messages();
            }

            if (!$errors) {
                $errors[] =
                    'wp_mail() returned an unsuccessful result.';
            }

            error_log(
                '[SOF Communication Mail Failure] ' .
                implode(
                    ' | ',
                    $errors
                )
            );

            return [
                'success'             => false,
                'provider'            => $this->get_name(),
                'destination'         => $destination,
                'provider_message_id' => '',
                'message'             => implode(
                    ' ',
                    $errors
                ),
                'errors'              => $errors,
            ];
        }

        return [
            'success'             => true,
            'provider'            => $this->get_name(),
            'destination'         => $destination,
            'provider_message_id' => '',
            'message'             =>
                'The Communication was delivered successfully.',
            'errors'              => [],
        ];
    }
}
