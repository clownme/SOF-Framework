<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Sender Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Service:
 *     Communication Sender
 *
 * Purpose:
 *     Resolve the organizational identity of the current
 *     Communication sender.
 *
 * Responsibilities:
 *     - Resolve the authenticated person's member identity
 *     - Resolve organizational role
 *     - Resolve organizational scope
 *     - Build the Communication Sender
 *
 * Does NOT:
 *     - Authenticate users
 *     - Format signatures
 *     - Compose communications
 *     - Deliver communications
 *
 * ============================================================
 */

class SOF_CommunicationSenderService
{
    protected SOF_CommunicationAudienceService
        $audience_service;

    public function __construct(
        SOF_CommunicationAudienceService $audience_service
    ) {
        $this->audience_service =
            $audience_service;
    }

    /**
     * Resolve the current Communication sender.
     */
    public function resolve_current_sender():
        ?SOF_CommunicationSender {

        $member =
            $this->audience_service
                ->resolve_current_member();

        return $this->build_sender_from_member(
            $member
        );
    }

    /**
     * Resolve a Communication sender for a specific
     * WordPress user.
     *
     * This path is safe for background processing because
     * it does not depend on an authenticated browser session.
     */
    public function resolve_sender_for_user(
        int $user_id
    ): ?SOF_CommunicationSender {

        if ($user_id <= 0) {
            return null;
        }

        $member =
            $this->audience_service
                ->resolve_member_for_user(
                    $user_id
                );

        return $this->build_sender_from_member(
            $member
        );
    }

    /**
     * Build a Communication Sender from a resolved
     * member record.
     *
     * @param array<string, mixed>|null $member
     */
    protected function build_sender_from_member(
        ?array $member
    ): ?SOF_CommunicationSender {

        if (!$member) {
            return null;
        }

        $member_id =
            (int) (
                $member['member_id']
                ?? 0
            );

        if ($member_id <= 0) {
            return null;
        }

        $name =
            trim(
                (string) (
                    $member['full_name']
                    ?? ''
                )
            );

        if ($name === '') {

            $name =
                trim(
                    (string) (
                        $member['first_name']
                        ?? ''
                    ) .
                    ' ' .
                    (string) (
                        $member['last_name']
                        ?? ''
                    )
                );
        }

        $email =
            sanitize_email(
                (string) (
                    $member['email']
                    ?? ''
                )
            );

        // -------------------------------------------------
        // Organizational Assignment
        // -------------------------------------------------

        global $wpdb;

        $table =
            'wp_coai_region_officers';

        $assignment =
            $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT
                        coai_region,
                        contact_title
                    FROM {$table}
                    WHERE member_id = %d
                      AND is_active = 1
                    LIMIT 1
                    ",
                    $member_id
                ),
                ARRAY_A
            );

        $scope = '';

        $role = '';

        if ($assignment) {

            $scope =
                trim(
                    (string) (
                        $assignment['coai_region']
                        ?? ''
                    )
                );

            $role =
                trim(
                    (string) (
                        $assignment['contact_title']
                        ?? ''
                    )
                );
        }

        return new SOF_CommunicationSender(
            $member_id,
            $name,
            $role,
            $scope,
            $email
        );
    }
}