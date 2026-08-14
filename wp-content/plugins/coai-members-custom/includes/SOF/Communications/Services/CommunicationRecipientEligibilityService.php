<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recipient Eligibility Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Recipient Eligibility
 *
 * Purpose:
 *     Determine whether a potential recipient is currently
 *     eligible to receive a Communication.
 *
 * Responsibilities:
 *     - Evaluate recipient eligibility in Communication context
 *     - Evaluate Membership status
 *     - Evaluate delivery channel requirements
 *     - Explain why a recipient is unavailable
 *
 * Does NOT:
 *     - Discover audience members
 *     - Select delivery providers
 *     - Deliver Communications
 *     - Approve Communications
 *     - Release Communications
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_CommunicationRecipientEligibilityService
{
    /**
     * Determine whether a recipient is currently eligible
     * to receive the Communication.
     */
    public function is_eligible(
        array $member,
        SOF_Communication $communication
    ): bool {
        return empty(
            $this->get_ineligibility_reasons(
                $member,
                $communication
            )
        );
    }

    /**
     * Return the reasons a recipient is not currently eligible.
     *
     * @return array<int, string>
     */
    public function get_ineligibility_reasons(
        array $member,
        SOF_Communication $communication
    ): array {
        $reasons = [];

        /*
         * -----------------------------------------------------
         * Membership Eligibility
         * -----------------------------------------------------
         *
         * Current RVP regional Communications target
         * active members.
         *
         * This rule belongs to the current Communication
         * experience and may evolve as Communication purpose
         * becomes more explicit.
         */

        $status =
            strtolower(
                trim(
                    (string) (
                        $member['status']
                        ?? $member['member_status']
                        ?? ''
                    )
                )
            );

        if ($status !== 'active') {
            $reasons[] =
                $this->get_status_reason(
                    $status
                );
        }

        /*
         * -----------------------------------------------------
         * Channel Eligibility
         * -----------------------------------------------------
         */

        $channel =
            strtolower(
                trim(
                    $communication->get_channel()
                )
            );

        switch ($channel) {

            case 'email':

                $email =
                    trim(
                        (string) (
                            $member['email']
                            ?? ''
                        )
                    );

                if ($email === '') {
                    $reasons[] =
                        'Missing email address';

                } elseif (!is_email($email)) {
                    $reasons[] =
                        'Invalid email address';
                }

                break;

            default:

                $reasons[] =
                    sprintf(
                        'Unsupported delivery channel: %s',
                        $channel !== ''
                            ? $channel
                            : 'unknown'
                    );

                break;
        }

        return array_values(
            array_unique(
                $reasons
            )
        );
    }

    /**
     * Translate Membership status into a useful
     * recipient eligibility explanation.
     */
    protected function get_status_reason(
        string $status
    ): string {
        switch ($status) {

            case 'expired':
                return 'Membership expired';

            case 'archived':
                return 'Member archived';

            case 'deceased':
                return 'Member deceased';

            case '':
                return 'Membership status unavailable';

            default:
                return sprintf(
                    'Membership status is %s',
                    $status
                );
        }
    }
}