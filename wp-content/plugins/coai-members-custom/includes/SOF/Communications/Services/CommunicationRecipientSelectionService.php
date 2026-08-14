<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recipient Selection Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Recipient Selection
 *
 * Purpose:
 *     Apply a person's recipient selection to an already
 *     discovered Communication recipient population.
 *
 * Responsibilities:
 *     - Preserve all recipients when selection mode is All
 *     - Narrow available recipients when selection mode is Selected
 *     - Prevent recipient selection from expanding the discovered population
 *     - Preserve unavailable recipient business truth
 *
 * Does NOT:
 *     - Discover recipients
 *     - Query Membership
 *     - Determine organizational authorization
 *     - Persist recipient selection
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_CommunicationRecipientSelectionService
{
    /**
     * Apply recipient selection to a discovered population.
     */
    public function apply(
        SOF_CommunicationRecipients $recipients,
        SOF_CommunicationRecipientSelection $selection
    ): SOF_CommunicationRecipients
    {
        if (
            $selection->uses_all_recipients()
        ) {
            return $recipients;
        }

        $selected_member_ids =
            $selection->get_member_ids();

        if (empty($selected_member_ids)) {
            return new SOF_CommunicationRecipients();
        }

        $available = [];

        foreach (
            $recipients->get_available_recipients()
            as $recipient
        ) {
            $member_id =
                $this->resolve_member_id(
                    $recipient
                );

            if (
                $member_id > 0
                && in_array(
                    $member_id,
                    $selected_member_ids,
                    true
                )
            ) {
                $available[] =
                    $recipient;
            }
        }

        $unavailable = [];

        foreach (
            $recipients->get_unavailable_recipients()
            as $recipient
        ) {
            $member_id =
                $this->resolve_member_id(
                    $recipient
                );

            if (
                $member_id > 0
                && in_array(
                    $member_id,
                    $selected_member_ids,
                    true
                )
            ) {
                $unavailable[] =
                    $recipient;
            }
        }

        return new SOF_CommunicationRecipients(
            $available,
            $unavailable
        );
    }

    /**
     * Resolve the Membership identifier from a recipient row.
     */
    protected function resolve_member_id(
        array $recipient
    ): int
    {
        if (
            isset($recipient['member_id'])
            && (int) $recipient['member_id'] > 0
        ) {
            return
                (int) $recipient['member_id'];
        }

        return 0;
    }
}