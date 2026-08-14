<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Communication Service
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Newsletter Communication
 *
 * Purpose:
 *     Prepare a persisted Newsletter for the Communications
 *     lifecycle.
 *
 * Responsibilities:
 *     - Accept a Newsletter and authorized Communication audience
 *     - Render the Newsletter as email HTML
 *     - Create a composed Communication
 *     - Persist the Communication
 *     - Return the persistent Communication identity
 *
 * Does NOT:
 *     - Determine Access authorization
 *     - Discover the Newsletter audience
 *     - Verify Communications
 *     - Test Communications
 *     - Approve Communications
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_NewsletterCommunicationService
{
    public function prepare(
        SOF_Newsletter $newsletter,
        SOF_CommunicationAudience $audience,
        int $created_by
    ): ?SOF_Communication {

        if (!$newsletter->get_id()) {

            error_log(
                '[SOF Newsletter Handoff] ' .
                'Stopped: Newsletter has no persistent ID.'
            );

            return null;
        }

        if (trim($newsletter->get_subject()) === '') {

            error_log(
                '[SOF Newsletter Handoff] ' .
                'Stopped: Newsletter subject is empty.'
            );

            return null;
        }

        if ($created_by < 1) {

            error_log(
                '[SOF Newsletter Handoff] ' .
                'Stopped: creator identity is unavailable.'
            );

            return null;
        }
        
        // -------------------------------------------------
        // Discover Communication Recipients
        // -------------------------------------------------

        $membership_audience_service =
            new SOF_MembershipAudienceService();

        $recipients_service =
            new SOF_CommunicationRecipientsService(
                $membership_audience_service
            );

        $recipients =
            $recipients_service->discover(
                $audience
            );

        $available_recipients =
            $recipients->get_available_recipients();

        $recipient_count =
            count(
                $available_recipients
            );

        error_log(
            '[SOF Newsletter Handoff] ' .
            'Audience Name: ' .
            $audience->get_name() .
            ' | Region: ' .
            $audience->get_region() .
            ' | Statuses: ' .
            wp_json_encode(
                $audience->get_membership_statuses()
            ) .
            ' | Available Recipients: ' .
            $recipient_count
        );

        if ($recipient_count < 1) {

            error_log(
                '[SOF Newsletter Handoff] ' .
                'Stopped: no available recipients.'
            );

            return null;
        }
        // -------------------------------------------------
        // Render Newsletter Content
        // -------------------------------------------------

        $renderer =
            new SOF_NewsletterHtmlRenderer();

        $html =
            $renderer->render($newsletter);

            if (trim($html) === '') {

            error_log(
                '[SOF Newsletter Handoff] ' .
                'Stopped: rendered Newsletter HTML is empty.'
            );

            return null;
        }

        // -------------------------------------------------
        // Compose Communication
        // -------------------------------------------------

        $composition_service =
            new SOF_CommunicationCompositionService();

        $communication =
            $composition_service->compose(
                $audience,
                $recipient_count,
                $newsletter->get_subject(),
                $html,
                $created_by
            );

        if (!$communication) {
            return null;
        }

        // -------------------------------------------------
        // Preserve Newsletter Recipient Selection
        // -------------------------------------------------

        $newsletter_selection =
            new SOF_CommunicationRecipientSelection(
                $newsletter->get_recipient_selection_mode(),
                $newsletter->get_selected_member_ids()
            );

        $selection_service =
            new SOF_CommunicationRecipientSelectionService();

        $validated_delivery_recipients =
            $selection_service->apply(
                $recipients,
                $newsletter_selection
            );

        if (
            $newsletter_selection->uses_selected_recipients()
        ) {

            $validated_member_ids = [];

            foreach (
                $validated_delivery_recipients
                    ->get_available_recipients()
                as $recipient
            ) {
                $member_id =
                    isset($recipient['member_id'])
                        ? (int) $recipient['member_id']
                        : 0;

                if ($member_id > 0) {
                    $validated_member_ids[] =
                        $member_id;
                }
            }

            $newsletter_selection =
                new SOF_CommunicationRecipientSelection(
                    SOF_CommunicationRecipientSelection::MODE_SELECTED,
                    $validated_member_ids
                );
        }

        $communication->set_recipient_selection(
            $newsletter_selection
        );
        
        // -------------------------------------------------
        // Identify Communication Source
        // -------------------------------------------------

        $communication->set_source(
            'newsletter',
            (int) $newsletter->get_id()
        );
        
        // -------------------------------------------------
        // Persist Communication
        // -------------------------------------------------

        $repository =
            new SOF_CommunicationRepository();

        $persistence_service =
            new SOF_CommunicationPersistenceService(
                $repository
            );

        $persisted =
            $persistence_service->persist(
                $communication
            );

        if (!$persisted) {

            error_log(
                '[SOF Newsletter Handoff] ' .
                'Stopped: Communication persistence failed.'
            );

            return null;
        }

        error_log(
            '[SOF Newsletter Handoff] ' .
            'Communication created: ' .
            $persisted->get_id()
        );

        return $persisted;
    }
}