<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Audience Population Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Audience Population
 *
 * Purpose:
 *     Build Communication audience population facts from
 *     Membership knowledge.
 *
 * Responsibilities:
 *     - Request membership population counts
 *     - Identify normal eligible membership statuses
 *     - Identify excluded membership statuses
 *     - Build a CommunicationAudiencePopulation model
 *
 * Does NOT:
 *     - Query member records directly
 *     - Select Communication recipients
 *     - Assess delivery readiness
 *     - Recommend actions
 *     - Deliver Communications
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_CommunicationAudiencePopulationService
{
    protected SOF_MembershipAudienceService $membership_audience_service;

    public function __construct(
        SOF_MembershipAudienceService $membership_audience_service
    ) {
        $this->membership_audience_service =
            $membership_audience_service;
    }

    /**
     * Resolve the population available to a regional
     * Communication audience.
     */
    public function resolve(
        SOF_CommunicationAudience $audience
    ): SOF_CommunicationAudiencePopulation {

        $status_counts =
            $this->membership_audience_service
                ->resolve_regional_status_counts(
                    $audience->get_region()
                );

        $eligible_counts = [
            'Active' =>
                (int) ($status_counts['Active'] ?? 0),

            'Expired' =>
                (int) ($status_counts['Expired'] ?? 0),

            'Archived' =>
                (int) ($status_counts['Archived'] ?? 0),
        ];

        /*
         * Deceased is intentionally excluded from
         * normal Communication audiences.
         *
         * Membership does not currently expose Deceased
         * through the normal status-count capability, so
         * the excluded count remains zero until that
         * knowledge is explicitly provided.
         */

        $excluded_counts = [
            'Deceased' => 0,
        ];

        return new SOF_CommunicationAudiencePopulation(
            $eligible_counts,
            $excluded_counts
        );
    }
}