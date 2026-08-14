<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recipients Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Recipients
 *
 * Purpose:
 *     Discover the current communication recipients for an
 *     authorized communication audience.
 *
 * Responsibilities:
 *     - Resolve audience members
 *     - Determine recipient availability
 *     - Build a CommunicationRecipients model
 *
 * Does NOT:
 *     - Assess communication readiness
 *     - Recommend business actions
 *     - Render presentation
 *     - Send communications
 *
 * ============================================================
 */

class SOF_CommunicationRecipientsService
{
    // -------------------------------------------------
    // Domain Capabilities
    // -------------------------------------------------

    protected SOF_MembershipAudienceService $membership_audience_service;
    
    protected SOF_CommunicationRecipientEligibilityService $eligibility_service;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    public function __construct(
        SOF_MembershipAudienceService $membership_audience_service,
        ?SOF_CommunicationRecipientEligibilityService $eligibility_service = null
    ) {
        $this->membership_audience_service =
            $membership_audience_service;
            
        $this->eligibility_service =
            $eligibility_service ??
            new SOF_CommunicationRecipientEligibilityService();
    }

    /**
     * Discover the recipients for a communication audience.
     */
    public function discover(
        SOF_CommunicationAudience $audience
    ): SOF_CommunicationRecipients
    {
        $members = $this->resolve_members($audience);

        $available = [];
        $unavailable = [];

        foreach ($members as $member) {

            if ($this->is_recipient_available($member)) {

                $available[] = $member;

            } else {

                $member['reason'] =
                    $this->determine_unavailable_reason($member);

                $unavailable[] = $member;
            }
        }

        return new SOF_CommunicationRecipients(
            $available,
            $unavailable
        );
    }
    
    /**
     * Discover the current recipients eligible for a
     * specific persisted Communication.
     *
     * Release discovery intentionally evaluates the broader
     * organizational audience so changes in Membership state
     * can be considered at delivery time.
     */
    public function discover_for_communication(
        SOF_CommunicationAudience $audience,
        SOF_Communication $communication
    ): SOF_CommunicationRecipients
    {
        if (
            $audience->get_key() ===
            'organization_members'
        ) {
            $members =
                $this->membership_audience_service
                    ->resolve_organizational_members(
                        $audience->get_membership_statuses()
                    );

        } else {

            $members =
                $this->membership_audience_service
                    ->resolve_regional_members(
                        $audience->get_region(),
                        $audience->get_membership_statuses()
                    );
        }

        $available = [];
        $unavailable = [];

        foreach ($members as $member) {

            $reasons =
                $this->eligibility_service
                    ->get_ineligibility_reasons(
                        $member,
                        $communication
                    );

            if (empty($reasons)) {

                $available[] =
                    $member;

            } else {

                $member['reasons'] =
                    $reasons;

                $member['reason'] =
                    implode(
                        '; ',
                        $reasons
                    );

                $unavailable[] =
                    $member;
            }
        }

        $recipients =
            new SOF_CommunicationRecipients(
                $available,
                $unavailable
            );

        $selection_service =
            new SOF_CommunicationRecipientSelectionService();

        return $selection_service->apply(
            $recipients,
            $communication->get_recipient_selection()
        );
    }

        /**
     * Resolve members belonging to the audience
     * through the Membership Knowledge Domain.
     *
     * @return array<int, array<string,mixed>>
     */
    protected function resolve_members(
        SOF_CommunicationAudience $audience
    ): array
    {
        if (
            $audience->get_key() ===
            'organization_members'
        ) {
            return $this->membership_audience_service
                ->resolve_organizational_members(
                    $audience->get_membership_statuses()
                );
        }

        return $this->membership_audience_service
            ->resolve_regional_members(
                $audience->get_region(),
                $audience->get_membership_statuses()
            );
    }

    /**
     * Determine whether a member can currently
     * receive communications.
     */
    protected function is_recipient_available(
        array $member
    ): bool
    {
        return !empty(trim(
            (string) ($member['email'] ?? '')
        ));
    }

    /**
     * Determine why a recipient is unavailable.
     */
    protected function determine_unavailable_reason(
        array $member
    ): string
    {
        if (empty(trim(
            (string) ($member['email'] ?? '')
        ))) {
            return 'Missing email address';
        }

        return 'Unavailable';
    }
}