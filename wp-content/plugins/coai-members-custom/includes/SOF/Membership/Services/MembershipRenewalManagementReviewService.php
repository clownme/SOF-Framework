<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Management Review Service
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business
 *
 * Purpose:
 *     Translate a Membership Renewal assessment into the
 *     management situation that a human needs to understand
 *     and act upon.
 *
 * Responsibilities:
 *     - Receive a provider-independent Renewal Candidate
 *     - Ask Membership to assess the candidate
 *     - Interpret the Membership assessment for management
 *     - Determine the available management decisions
 *     - Present a complete management-review situation
 *
 * Does NOT:
 *     - Call a payment provider
 *     - Interpret provider-specific fields
 *     - Change source transaction evidence
 *     - Update a member
 *     - Change Membership expiration
 *     - Apply a Renewal
 *     - Persist a management decision
 *
 * ============================================================
 */

class SOF_MembershipRenewalManagementReviewService
{
    /**
     * Build the management-review situation for one
     * Membership Renewal Candidate.
     *
     * @return array<string, mixed>
     */
    public function review(
        SOF_MembershipRenewalCandidate $candidate
    ): array {

        $result = [
            'success'             => false,
            'candidate'           => $candidate,
            'assessment'          => [],
            'situation'           => 'cannot_assess',
            'situation_title'     => 'Renewal Cannot Be Assessed',
            'recommended_path'    => 'Review Renewal',
            'reason'              => '',
            'available_actions'   => [],
        ];

        /*
         * -------------------------------------------------
         * Membership owns Renewal assessment.
         * -------------------------------------------------
         */

        if (
            !class_exists(
                'SOF_MembershipRenewalAssessmentService'
            )
        ) {
            $result['reason'] =
                'The SOF Membership Renewal Assessment Service '
                . 'is not available.';

            return $result;
        }

        $assessment_service =
            new SOF_MembershipRenewalAssessmentService();

        $assessment =
            $assessment_service->assess(
                $candidate
            );

        $result['assessment'] =
            $assessment;

        $assessment_status =
            trim(
                (string)(
                    $assessment['assessment_status']
                    ?? 'cannot_assess'
                )
            );

        $result['reason'] =
            trim(
                (string)(
                    $assessment['reason']
                    ?? ''
                )
            );

        /*
         * -------------------------------------------------
         * Renewal ready to apply.
         * -------------------------------------------------
         *
         * Membership has determined that applying the
         * standard Renewal is the appropriate business path.
         *
         * Management may authorize that future action.
         *
         * This service still DOES NOT apply the Renewal.
         * -------------------------------------------------
         */

        if ($assessment_status === 'ready_to_apply') {

            $result['success'] =
                true;

            $result['situation'] =
                'ready_to_apply';

            $result['situation_title'] =
                'Renewal Ready to Apply';

            $result['recommended_path'] =
                'Approve Membership Renewal';

            $result['available_actions'] = [
                'approve_renewal',
                'further_review',
            ];

            return $result;
        }

        /*
         * -------------------------------------------------
         * Possible previously applied Renewal.
         * -------------------------------------------------
         *
         * Current expiration exactly matches the expiration
         * this payment would establish.
         *
         * Human confirmation is required before any
         * Membership change occurs.
         * -------------------------------------------------
         */

        if (
            $assessment_status ===
            'possible_previously_applied'
        ) {
            $result['success'] =
                true;

            $result['situation'] =
                'possible_previously_applied';

            $result['situation_title'] =
                'Renewal May Already Be Reflected';

            $result['recommended_path'] =
                'Confirm Whether Renewal Is Already Reflected';

            $result['available_actions'] = [
                'confirm_already_reflected',
                'approve_renewal',
                'further_review',
            ];

            return $result;
        }

        /*
         * -------------------------------------------------
         * Membership requires management review.
         * -------------------------------------------------
         *
         * Current Membership evidence does not support
         * automatic application of the Renewal.
         * -------------------------------------------------
         */

        if ($assessment_status === 'management_review') {

            $result['success'] =
                true;

            $result['situation'] =
                'management_review';

            $result['situation_title'] =
                'Renewal Requires Management Review';

            $result['recommended_path'] =
                'Review Existing Membership';

            $result['available_actions'] = [
                'approve_renewal',
                'further_review',
            ];

            return $result;
        }

        /*
         * -------------------------------------------------
         * Candidate could not be safely assessed.
         * -------------------------------------------------
         *
         * No positive Membership decision should be offered.
         * -------------------------------------------------
         */

        $result['situation'] =
            'cannot_assess';

        $result['situation_title'] =
            trim(
                (string)(
                    $assessment['assessment_title']
                    ?? 'Renewal Cannot Be Assessed'
                )
            );

        $result['recommended_path'] =
            trim(
                (string)(
                    $assessment['recommended_path']
                    ?? 'Review Renewal'
                )
            );

        $result['available_actions'] = [
            'further_review',
        ];

        return $result;
    }
}