<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Situation
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Object:
 *     Communication Situation
 *
 * Purpose:
 *     Represent the complete current business situation for
 *     communicating with a specific audience.
 *
 * Responsibilities:
 *     - Hold the communication audience
 *     - Hold the resolved communication recipients
 *     - Hold the current communication assessment
 *     - Hold the current communication recommendation
 *     - Hold the currently available communication actions
 *     - Provide convenient business-state questions
 *
 * Does NOT:
 *     - Resolve the communication audience
 *     - Resolve communication recipients
 *     - Assess communication readiness
 *     - Recommend a business path
 *     - Determine available actions
 *     - Perform communication actions
 *     - Render the communication experience
 *
 * Business Question:
 *     What is the complete current communication situation?
 *
 * ============================================================
 */

class SOF_CommunicationSituation
{
    // -------------------------------------------------
    // Audience
    // -------------------------------------------------

    protected SOF_CommunicationAudience $audience;
    
    // -------------------------------------------------
    // Audience Population
    // -------------------------------------------------

    protected SOF_CommunicationAudiencePopulation $audience_population;

    // -------------------------------------------------
    // Recipients
    // -------------------------------------------------

    protected SOF_CommunicationRecipients $recipients;

    // -------------------------------------------------
    // Assessment
    // -------------------------------------------------

    protected SOF_CommunicationAssessment $assessment;

    // -------------------------------------------------
    // Recommendation
    // -------------------------------------------------

    protected SOF_CommunicationRecommendation $recommendation;

    // -------------------------------------------------
    // Available Actions
    // -------------------------------------------------

    protected SOF_CommunicationAvailableActions $available_actions;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------
    
    public function __construct(
        SOF_CommunicationAudience $audience,
        SOF_CommunicationAudiencePopulation $audience_population,
        SOF_CommunicationRecipients $recipients,
        SOF_CommunicationAssessment $assessment,
        SOF_CommunicationRecommendation $recommendation,
        SOF_CommunicationAvailableActions $available_actions
    ) {
        $this->audience = $audience;
        $this->audience_population = $audience_population;
        $this->recipients = $recipients;
        $this->assessment = $assessment;
        $this->recommendation = $recommendation;
        $this->available_actions = $available_actions;
    }

    // -------------------------------------------------
    // Business Information
    // -------------------------------------------------

    public function audience(): SOF_CommunicationAudience
    {
        return $this->audience;
    }

    public function audience_population():
        SOF_CommunicationAudiencePopulation
    {
        return $this->audience_population;
    }

    public function recipients(): SOF_CommunicationRecipients
    {
        return $this->recipients;
    }

    public function assessment(): SOF_CommunicationAssessment
    {
        return $this->assessment;
    }

    public function recommendation(): SOF_CommunicationRecommendation
    {
        return $this->recommendation;
    }

    public function available_actions(): SOF_CommunicationAvailableActions
    {
        return $this->available_actions;
    }

    // -------------------------------------------------
    // Business State
    // -------------------------------------------------

    /**
     * Determine whether the recommended path may proceed.
     */
    public function can_proceed(): bool
    {
        return $this->recommendation->can_proceed();
    }

    /**
     * Determine whether the situation requires attention.
     */
    public function requires_attention(): bool
    {
        return $this->recommendation->requires_attention();
    }

    /**
     * Determine whether the current assessment is ready.
     */
    public function is_ready(): bool
    {
        return sanitize_key(
            $this->assessment->get_status()
        ) === SOF_CommunicationAssessment::STATUS_READY;
    }

    /**
     * Determine whether the current assessment blocks progress.
     */
    public function is_blocked(): bool
    {
        return sanitize_key(
            $this->assessment->get_status()
        ) === SOF_CommunicationAssessment::STATUS_NOT_READY;
    }

    /**
     * Determine whether the situation contains everything
     * needed to support a business experience.
     */
    public function is_complete(): bool
    {
        return true;
    }
}