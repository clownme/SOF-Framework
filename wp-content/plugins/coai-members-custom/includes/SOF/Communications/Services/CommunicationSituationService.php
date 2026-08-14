<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Situation Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Service:
 *     Communication Situation Service
 *
 * Purpose:
 *     Resolve the complete current communication situation
 *     for a specific communication audience.
 *
 * Responsibilities:
 *     - Resolve recipients for the supplied audience
 *     - Assess the resolved recipients
 *     - Recommend an appropriate business path
 *     - Determine currently available actions
 *     - Assemble and return the Communication Situation
 *
 * Does NOT:
 *     - Discover or select the communication audience
 *     - Determine user authorization
 *     - Query recipients directly
 *     - Contain assessment rules
 *     - Contain recommendation rules
 *     - Contain available-action rules
 *     - Perform communication actions
 *     - Render the communication experience
 *
 * Business Question:
 *     Given this audience and the actions the person is
 *     authorized to perform, what is the complete current
 *     communication situation?
 *
 * ============================================================
 */

class SOF_CommunicationSituationService
{
    // -------------------------------------------------
    // Business Services
    // -------------------------------------------------

    protected SOF_CommunicationRecipientsService $recipients_service;

    protected SOF_CommunicationAssessmentService $assessment_service;

    protected SOF_CommunicationRecommendationService $recommendation_service;

    protected SOF_CommunicationAvailableActionsService $available_actions_service;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    public function __construct(
        SOF_CommunicationRecipientsService $recipients_service,
        SOF_CommunicationAssessmentService $assessment_service,
        SOF_CommunicationRecommendationService $recommendation_service,
        SOF_CommunicationAvailableActionsService $available_actions_service
    ) {
        $this->recipients_service = $recipients_service;
        $this->assessment_service = $assessment_service;
        $this->recommendation_service = $recommendation_service;
        $this->available_actions_service = $available_actions_service;
    }

    // -------------------------------------------------
    // Situation Resolution
    // -------------------------------------------------

    /**
     * Resolve the complete communication situation.
     *
     * @param array<int, string> $authorized_actions
     */
    public function resolve(
        SOF_CommunicationAudience $audience,
        SOF_CommunicationAudiencePopulation $audience_population,
        array $authorized_actions = []
    ): SOF_CommunicationSituation {
        $recipients =
            $this->recipients_service->discover(
                $audience
            );

        // -------------------------------------------------
        // Communication Facts
        // -------------------------------------------------

        $facts =
            new SOF_CommunicationFacts(
                $audience,
                $audience_population,
                $recipients
            );

        $assessment =
            $this->assessment_service->assess(
                $facts
           );

                $recommendation =
                    $this->recommendation_service->recommend(
                        $assessment,
                        $facts
                    );

                $available_actions =
                    $this->available_actions_service->resolve(
                        $assessment,
                        $authorized_actions
                    );

                return new SOF_CommunicationSituation(
                    $audience,
                    $audience_population,
                    $recipients,
                    $assessment,
                    $recommendation,
                    $available_actions
                );
            }
        }