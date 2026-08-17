<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Application Service
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business
 *
 * Purpose:
 *     Prepare an approved Membership Renewal for controlled
 *     application.
 *
 * Responsibilities:
 *     - Validate the Renewal Candidate
 *     - Validate the approving management decision
 *     - Re-assess current Membership facts
 *     - Refuse stale or unsafe application attempts
 *     - Prevent duplicate application preparation
 *     - Create a pending Renewal Application ledger record
 *
 * Does NOT:
 *     - Call a payment provider
 *     - Make a management decision
 *     - Update a member
 *     - Change Membership expiration
 *     - Apply the Renewal
 *
 * ============================================================
 */

class SOF_MembershipRenewalApplicationService
{
    /**
     * Prepare one approved Renewal for future application.
     *
     * @return array<string, mixed>
     */
    public function prepare(
        SOF_MembershipRenewalCandidate $candidate,
        ?SOF_MembershipManagementDecision $decision
    ): array {

        $result = [
            'success' =>
                false,

            'status' =>
                'not_prepared',

            'application' =>
                null,

            'assessment' =>
                [],

            'message' =>
                '',
        ];

        /*
         * -------------------------------------------------
         * Required services.
         * -------------------------------------------------
         */

        if (
            !class_exists(
                'SOF_MembershipRenewalApplicationRepository'
            )
            || !class_exists(
                'SOF_MembershipRenewalAssessmentService'
            )
        ) {
            $result['message'] =
                'The required Membership Renewal Application '
                . 'services are not available.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Candidate must be valid.
         * -------------------------------------------------
         */

        if (!$candidate->is_valid()) {
            $result['message'] =
                'The Membership Renewal Candidate does not '
                . 'contain complete Renewal evidence.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Management approval is required.
         * -------------------------------------------------
         */

        if (!$decision) {
            $result['message'] =
                'Management approval is required before a '
                . 'Renewal Application can be prepared.';

            return $result;
        }

        if (
            $decision->decision !==
            SOF_MembershipManagementDecisionService::
                DECISION_APPROVE_RENEWAL
        ) {
            $result['message'] =
                'The current Membership management decision '
                . 'does not authorize Renewal application.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Approval must belong to this same candidate.
         * -------------------------------------------------
         */

        if (
            $decision->source_provider !==
                $candidate->source_provider
            || $decision->source_transaction_id !==
                $candidate->source_transaction_id
            || $decision->member_id !==
                $candidate->member_id
        ) {
            $result['message'] =
                'The Renewal approval does not match the '
                . 'Membership Renewal Candidate.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Duplicate protection.
         * -------------------------------------------------
         */

        $repository =
            new SOF_MembershipRenewalApplicationRepository();

        $existing =
            $repository->find_by_source(
                $candidate->source_provider,
                $candidate->source_transaction_id
            );

        if ($existing) {
            $result['application'] =
                $existing;

            $result['status'] =
                $existing->application_status;

            $result['message'] =
                'This source payment already has a Membership '
                . 'Renewal Application record.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Re-assess immediately before preparation.
         *
         * Approval alone is never enough.
         * -------------------------------------------------
         */

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
                    $assessment[
                        'assessment_status'
                    ]
                    ?? ''
                )
            );

        if ($assessment_status !== 'ready_to_apply') {
            $result['message'] =
                'The current Membership evidence does not '
                . 'support preparing this Renewal for '
                . 'application.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Retrieve current and proposed Membership facts.
         * -------------------------------------------------
         */

        $member =
            (
                isset(
                    $assessment['member']
                )
                && is_array(
                    $assessment['member']
                )
            )
                ? $assessment['member']
                : [];

        if (!$member) {
            $result['message'] =
                'The affected Membership record could not '
                . 'be retrieved.';

            return $result;
        }
        
        $previous_renewal_date =
            trim(
                (string)(
                    $member[
                        'renewal_date'
                    ]
                    ?? ''
                )
            );

        $applied_renewal_date =
            trim(
                (string)(
                    $assessment[
                        'renewal_date'
                    ]
                    ?? ''
                )
            );

        $previous_expiration =
            trim(
                (string)(
                    $member[
                        'membership_expiration'
                    ]
                    ?? ''
                )
            );

        $applied_expiration =
            trim(
                (string)(
                    $assessment[
                        'standard_expiration'
                    ]
                    ?? ''
                )
            );

        if ($applied_expiration === '') {
            $result['message'] =
                'SOF could not determine the expiration date '
                . 'that this Renewal would establish.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Build the pending Application business object.
         * -------------------------------------------------
         */

        $application =
            new SOF_MembershipRenewalApplication();

        $application->source_provider =
            $candidate->source_provider;

        $application->source_transaction_id =
            $candidate->source_transaction_id;

        $application->member_id =
            $candidate->member_id;

        $application->approval_decision_id =
            (int)$decision->id;

        $application->payment_date =
            $candidate->payment_date;

        $application->payment_amount =
            $candidate->payment_amount;

        $application->previous_renewal_date =
            $previous_renewal_date;

        $application->applied_renewal_date =
            $applied_renewal_date;

        $application->previous_expiration =
            $previous_expiration;

        $application->applied_expiration =
            $applied_expiration;

        $application->application_status =
            'pending';

        $application->notes =
            'Membership Renewal prepared after Management '
            . 'approval and fresh Membership assessment.';

        if (!$application->is_valid_for_application()) {
            $result['message'] =
                'SOF could not build a valid Membership '
                . 'Renewal Application.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Persist pending application.
         *
         * NO Membership update occurs here.
         * -------------------------------------------------
         */

        $created =
            $repository->create_pending(
                $application
            );

        if (!$created) {
            $result['message'] =
                'SOF could not create the pending Membership '
                . 'Renewal Application record.';

            return $result;
        }

        $stored =
            $repository->find_by_source(
                $candidate->source_provider,
                $candidate->source_transaction_id
            );

        if (!$stored) {
            $result['message'] =
                'The pending Renewal Application was created '
                . 'but could not be retrieved for verification.';

            return $result;
        }

        $result['success'] =
            true;

        $result['status'] =
            'pending';

        $result['application'] =
            $stored;

        $result['message'] =
            'Membership Renewal Application prepared. '
            . 'No Membership record was changed.';

        return $result;
    }
}