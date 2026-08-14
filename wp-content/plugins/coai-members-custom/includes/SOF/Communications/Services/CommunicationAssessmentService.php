<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Assessment Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Communication Assessment
 *
 * Purpose:
 *     Assess the current communication recipient facts and
 *     determine what those facts mean for the business.
 *
 * Responsibilities:
 *     - Evaluate the current recipient population
 *     - Determine the communication assessment status
 *     - Summarize the current communication situation
 *     - Record the reasons supporting the assessment
 *     - Determine confidence in the assessment
 *     - Build a CommunicationAssessment model
 *
 * Does NOT:
 *     - Discover communication audiences
 *     - Discover communication recipients
 *     - Query member records
 *     - Recommend a business action
 *     - Render presentation
 *     - Send communications
 *
 * ============================================================
 */

class SOF_CommunicationAssessmentService
{
    /**
     * Assess the current communication recipient facts.
     */
    public function assess(
        SOF_CommunicationFacts $facts
    ): SOF_CommunicationAssessment {
        $recipients =
           $facts->recipients();
            if (!$recipients->has_recipients()) {
                return $this->build_not_ready_assessment();
            }

            if (!$recipients->has_available_recipients()) {
                return $this->build_no_available_recipients_assessment(
                    $recipients
                );
           }

            if ($recipients->has_unavailable_recipients()) {
                return $this->build_needs_attention_assessment(
                    $recipients
                );
            }

            return $this->build_ready_assessment(
                $facts
            );
        }

    // -------------------------------------------------
    // Ready Assessment
    // -------------------------------------------------

    /**
     * Build an assessment for a recipient population
     * that is fully available.
     */
    protected function build_ready_assessment(
        SOF_CommunicationFacts $facts
    ): SOF_CommunicationAssessment {
        $population =
            $facts->audience_population();

        $active_count =
            $population->get_eligible_count(
                'Active'
            );

        $expired_count =
            $population->get_eligible_count(
                'Expired'
            );

        $archived_count =
            $population->get_eligible_count(
                'Archived'
            );

        $eligible_total =
            $population->get_eligible_total();

        $summary =
            sprintf(
                '%s has %s eligible members: %s Active, %s Expired, and %s Archived.',
                $facts->get_audience_name(),
                number_format_i18n(
                    $eligible_total
                ),
                number_format_i18n(
                    $active_count
                ),
                number_format_i18n(
                    $expired_count
                ),
                number_format_i18n(
                    $archived_count
                )
            );

        return new SOF_CommunicationAssessment(
            SOF_CommunicationAssessment::STATUS_READY,
            $summary,
            [],
            SOF_CommunicationAssessment::CONFIDENCE_HIGH
        );
    }

    // -------------------------------------------------
    // Needs Attention Assessment
    // -------------------------------------------------

    /**
     * Build an assessment for a recipient population
     * containing both available and unavailable recipients.
     */
    protected function build_needs_attention_assessment(
        SOF_CommunicationRecipients $recipients
    ): SOF_CommunicationAssessment {
        $available_count =
            $recipients->get_available_count();

        $unavailable_count =
            $recipients->get_unavailable_count();

        return new SOF_CommunicationAssessment(
            SOF_CommunicationAssessment::STATUS_NEEDS_ATTENTION,
            $this->build_available_summary(
                $available_count,
                'can receive this communication.'
            ),
            [
                $this->build_unavailable_reason(
                    $unavailable_count
                ),
            ],
            SOF_CommunicationAssessment::CONFIDENCE_HIGH
        );
    }

    // -------------------------------------------------
    // Not Ready Assessments
    // -------------------------------------------------

    /**
     * Build an assessment when no recipients were
     * discovered for the audience.
     */
    protected function build_not_ready_assessment():
        SOF_CommunicationAssessment
    {
        return new SOF_CommunicationAssessment(
            SOF_CommunicationAssessment::STATUS_NOT_READY,
            'No recipients are currently available for this communication.',
            [
                'No recipients were discovered for the communication audience.',
            ],
            SOF_CommunicationAssessment::CONFIDENCE_HIGH
        );
    }

    /**
     * Build an assessment when recipients were discovered,
     * but none are currently available.
     */
    protected function build_no_available_recipients_assessment(
        SOF_CommunicationRecipients $recipients
    ): SOF_CommunicationAssessment {
        $unavailable_count =
            $recipients->get_unavailable_count();

        return new SOF_CommunicationAssessment(
            SOF_CommunicationAssessment::STATUS_NOT_READY,
            'The discovered recipients cannot currently receive this communication.',
            [
                $this->build_unavailable_reason(
                    $unavailable_count
                ),
            ],
            SOF_CommunicationAssessment::CONFIDENCE_HIGH
        );
    }

    // -------------------------------------------------
    // Business Language
    // -------------------------------------------------

    /**
     * Build a readable summary for available recipients.
     */
    protected function build_available_summary(
        int $count,
        string $message
    ): string {
        return sprintf(
            '%s %s %s',
            number_format_i18n($count),
            $this->get_member_label($count),
            $message
        );
    }

    /**
     * Build a readable reason for unavailable recipients.
     */
    protected function build_unavailable_reason(
        int $count
    ): string {
        return sprintf(
            '%s %s cannot currently receive this communication.',
            number_format_i18n($count),
            $this->get_member_label($count)
        );
    }

    /**
     * Return the correct member label for a count.
     */
    protected function get_member_label(
        int $count
    ): string {
        return $count === 1
            ? 'member'
            : 'members';
    }
}