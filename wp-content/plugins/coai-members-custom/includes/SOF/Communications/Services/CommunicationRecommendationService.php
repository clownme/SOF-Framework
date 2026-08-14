<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recommendation Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Recommendation
 *
 * Purpose:
 *     Recommend a business path based on the current
 *     communication assessment.
 *
 * Responsibilities:
 *     - Evaluate the current communication assessment
 *     - Determine the recommended business path
 *     - Explain what the user should consider doing next
 *     - Determine whether the communication may proceed
 *     - Determine whether user attention is required
 *     - Build a CommunicationRecommendation model
 *
 * Does NOT:
 *     - Discover communication audiences
 *     - Discover communication recipients
 *     - Assess communication facts
 *     - Make a decision for the user
 *     - Perform the recommended action
 *     - Render presentation
 *     - Send communications
 *
 * ============================================================
 */

class SOF_CommunicationRecommendationService
{
    /**
     * Recommend a path based on the current assessment.
     */
    public function recommend(
        SOF_CommunicationAssessment $assessment,
        SOF_CommunicationFacts $facts
    ): SOF_CommunicationRecommendation {
        if ($assessment->is_ready()) {
            return $this->build_proceed_recommendation(
                $assessment,
                $facts
            );
        }

        if ($assessment->needs_attention()) {
            return $this->build_review_recommendation(
                $assessment
            );
        }

        return $this->build_stop_recommendation(
            $assessment
        );
    }

    // -------------------------------------------------
    // Proceed Recommendation
    // -------------------------------------------------

    /**
     * Build a recommendation when the communication
     * is ready to continue.
     */
    protected function build_proceed_recommendation(
        SOF_CommunicationAssessment $assessment,
        SOF_CommunicationFacts $facts
    ): SOF_CommunicationRecommendation {

        $eligible_total =
            $facts->get_eligible_population_count();

        $selected_count =
            $facts->get_selected_recipient_count();

        /*
         * All eligible members are currently selected.
         */
        if (
            $eligible_total > 0 &&
            $selected_count === $eligible_total
        ) {
            return new SOF_CommunicationRecommendation(
                SOF_CommunicationRecommendation::RECOMMEND_PROCEED,
                'Continue Preparing the Communication',
                'All eligible members are selected for this communication. Continue preparing your message.',
                'Continue',
               true,
                false
            );
        }

        /*
         * A subset of the eligible audience is selected.
         */
        if ($selected_count > 0) {
            return new SOF_CommunicationRecommendation(
                SOF_CommunicationRecommendation::RECOMMEND_PROCEED,
                'Continue Preparing the Communication',
                sprintf(
                    '%s selected members are included in this communication. Continue preparing your message.',
                    number_format_i18n(
                        $selected_count
                    )
                ),
                'Continue',
                true,
                false
            );
        }

        /*
         * No audience selection has been established yet.
         */
        return new SOF_CommunicationRecommendation(
            SOF_CommunicationRecommendation::RECOMMEND_PROCEED,
            'Choose the Members to Include',
            'Prepare this communication for all eligible members, or make your selection below.',
            'Continue',
            true,
            false
        );
    }

    // -------------------------------------------------
    // Review Recommendation
    // -------------------------------------------------

    /**
     * Build a recommendation when the communication may
     * continue, but part of the situation deserves review.
     */
    protected function build_review_recommendation(
        SOF_CommunicationAssessment $assessment
    ): SOF_CommunicationRecommendation {
        return new SOF_CommunicationRecommendation(
            SOF_CommunicationRecommendation::RECOMMEND_REVIEW,
            'Review the Recipient Situation',
            $this->build_review_message(
                $assessment
            ),
            'Review Recipients',
            true,
            true
        );
    }

    // -------------------------------------------------
    // Stop Recommendation
    // -------------------------------------------------

    /**
     * Build a recommendation when the communication
     * cannot currently continue.
     */
    protected function build_stop_recommendation(
        SOF_CommunicationAssessment $assessment
    ): SOF_CommunicationRecommendation {
        return new SOF_CommunicationRecommendation(
            SOF_CommunicationRecommendation::RECOMMEND_STOP,
            'Resolve the Recipient Situation',
            $this->build_stop_message(
                $assessment
            ),
            'Review the Situation',
            false,
            true
        );
    }

    // -------------------------------------------------
    // Business Language
    // -------------------------------------------------

    /**
     * Build the recommendation message for an assessment
     * that requires attention but may still proceed.
     */
    protected function build_review_message(
        SOF_CommunicationAssessment $assessment
    ): string {
        $reason =
            $this->get_primary_reason($assessment);

        if ($reason === '') {
            return (
                'Some recipients require attention. ' .
                'Review the recipient situation, or continue ' .
                'with the recipients who are currently available.'
            );
        }

        return sprintf(
            '%s Review the recipient situation, or continue ' .
            'with the recipients who are currently available.',
            $reason
        );
    }

    /**
     * Build the recommendation message for an assessment
     * that cannot currently proceed.
     */
    protected function build_stop_message(
        SOF_CommunicationAssessment $assessment
    ): string {
        $reason =
            $this->get_primary_reason($assessment);

        if ($reason === '') {
            return (
                'The communication cannot currently proceed. ' .
                'Review the recipient situation before continuing.'
            );
        }

        return sprintf(
            '%s The communication cannot currently proceed. ' .
            'Review the recipient situation before continuing.',
            $reason
        );
    }

    /**
     * Return the first business reason supporting the
     * current assessment.
     */
    protected function get_primary_reason(
        SOF_CommunicationAssessment $assessment
    ): string {
        $reasons =
            $assessment->get_reasons();

        if (empty($reasons)) {
            return '';
        }

        return trim(
            (string) $reasons[0]
        );
    }
}