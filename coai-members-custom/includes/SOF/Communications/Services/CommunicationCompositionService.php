<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Composition Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Composition
 *
 * Purpose:
 *     Prepare communication content for an authorized
 *     communication audience.
 *
 * Responsibilities:
 *     - Accept communication subject and message content
 *     - Normalize communication content
 *     - Validate required composition information
 *     - Build a Communication
 *     - Move the Communication to composed state
 *
 * Does NOT:
 *     - Discover audiences
 *     - Discover recipients
 *     - Assess recipient readiness
 *     - Approve communications
 *     - Schedule communications
 *     - Send communications
 *     - Render presentation
 *
 * Business Question:
 *     Is there enough valid communication content to prepare
 *     this communication for the next experience step?
 *
 * ============================================================
 */

class SOF_CommunicationCompositionService
{
    /**
     * Prepare communication content for an audience.
     */
    public function compose(
        SOF_CommunicationAudience $audience,
        int $recipient_count,
        string $subject,
        string $message,
        int $created_by
    ): ?SOF_Communication {
        $subject =
            $this->normalize_subject($subject);

        $message =
            $this->normalize_message($message);

        if (
            $subject === '' ||
            $message === ''
        ) {
            return null;
        }

        $communication =
            new SOF_Communication([
                'type'                => 'regional_update',
                'status'              => 'draft',
                'subject'             => $subject,
                'body'                => $message,
                'audience_key'        => $audience->get_key(),
                'audience_name'       => $audience->get_name(),
                'membership_statuses' =>
                    $audience->get_membership_statuses(),
                'recipient_count'     => max(
                    0,
                    $recipient_count
                ),
                'channel'             => 'email',
                'created_by'          => $created_by,
            ]);

        $communication->mark_composed();

        return $communication;
    }
    
    /**
     * Revise an existing composed Communication.
     */
    public function revise(
        SOF_Communication $communication,
        string $subject,
        string $message
    ): ?SOF_Communication {

        if (!$communication->is_composed()) {
            return null;
        }

        $subject =
            sanitize_text_field(
                wp_unslash($subject)
            );

        $message =
            wp_kses_post(
                wp_unslash($message)
            );

        if ($subject === '' || $message === '') {
            return null;
        }

        $communication->set_subject(
            $subject
        );

        $communication->set_body(
            $message
        );

        return $communication;
    }

    // -------------------------------------------------
    // Content Normalization
    // -------------------------------------------------

    /**
     * Normalize the communication subject.
     */
    protected function normalize_subject(
        string $subject
    ): string {
        return trim(
            wp_strip_all_tags($subject)
        );
    }

    /**
     * Normalize the communication message.
     */
    protected function normalize_message(
        string $message
    ): string {
        return trim(
            wp_kses_post($message)
        );
    }
}