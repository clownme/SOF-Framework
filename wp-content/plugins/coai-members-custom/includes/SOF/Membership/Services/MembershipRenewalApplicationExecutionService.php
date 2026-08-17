<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Application Execution Service
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business Execution
 *
 * Purpose:
 *     Safely execute one prepared Membership Renewal Application.
 *
 * Responsibilities:
 *     - Require an existing pending Application record
 *     - Require the original approve_renewal decision
 *     - Re-read current Membership facts immediately before write
 *     - Re-assess the Renewal before execution
 *     - Refuse stale, duplicate, or unsafe application attempts
 *     - Update approved Membership Renewal fields
 *     - Verify the stored Membership values after the write
 *     - Mark the Application ledger applied only after verification
 *
 * Does NOT:
 *     - Call a payment provider
 *     - Decide whether Management should approve a Renewal
 *     - Change payment-provider evidence
 *
 * ============================================================
 */

class SOF_MembershipRenewalApplicationExecutionService
{
    /**
     * Execute one prepared Membership Renewal Application.
     *
     * @return array<string, mixed>
     */
    public function execute(
        SOF_MembershipRenewalCandidate $candidate,
        ?SOF_MembershipManagementDecision $decision
    ): array {

        $result = [
            'success' =>
                false,

            'status' =>
                'not_applied',

            'application' =>
                null,

            'member_id' =>
                0,

            'renewal_date' =>
                '',

            'expiration_date' =>
                '',

            'message' =>
                '',
        ];

        /*
         * -------------------------------------------------
         * Required Membership capabilities.
         * -------------------------------------------------
         */

        $missing_capabilities = [];

        if (
            !class_exists(
                'SOF_MembershipRenewalApplicationRepository'
            )
        ) {
            $missing_capabilities[] =
                'SOF_MembershipRenewalApplicationRepository';
        }

        if (
            !class_exists(
                'SOF_MembershipRenewalAssessmentService'
            )
        ) {
            $missing_capabilities[] =
                'SOF_MembershipRenewalAssessmentService';
        }

        if (
            !function_exists(
                'coai_get_member_by_id'
            )
        ) {
            $missing_capabilities[] =
                'coai_get_member_by_id';
        }

        if (
            !function_exists(
                'coai_update_member_renewal_fields'
            )
        ) {
            $missing_capabilities[] =
                'coai_update_member_renewal_fields';
        }

        if (!empty($missing_capabilities)) {
            $result['message'] =
                'The required Membership Renewal execution '
                . 'services are not available: '
                . implode(
                    ', ',
                    $missing_capabilities
                )
                . '.';

            return $result;
        }
        
        /*
         * -------------------------------------------------
         * Candidate and approval must still be valid.
         * -------------------------------------------------
         */

        if (!$candidate->is_valid()) {
            $result['message'] =
                'The Membership Renewal Candidate is not valid.';

            return $result;
        }

        if (
            !$decision
            || $decision->decision !==
                SOF_MembershipManagementDecisionService::
                    DECISION_APPROVE_RENEWAL
        ) {
            $result['message'] =
                'Management approval is required before '
                . 'Membership Renewal execution.';

            return $result;
        }

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
         * Pending Application record must already exist.
         * -------------------------------------------------
         */

        $repository =
            new SOF_MembershipRenewalApplicationRepository();

        $application =
            $repository->find_by_source(
                $candidate->source_provider,
                $candidate->source_transaction_id
            );

        if (!$application) {
            $result['message'] =
                'A prepared Membership Renewal Application '
                . 'record does not exist.';

            return $result;
        }

        $result['application'] =
            $application;

        if ($application->application_status === 'applied') {
            $result['status'] =
                'already_applied';

            $result['message'] =
                'This Membership Renewal Application has '
                . 'already been applied.';

            return $result;
        }

        if ($application->application_status !== 'pending') {
            $result['message'] =
                'This Membership Renewal Application is not '
                . 'currently pending execution.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Application ledger must match current authorization.
         * -------------------------------------------------
         */

        if (
            $application->member_id !==
                $candidate->member_id
            || $application->approval_decision_id !==
                (int)$decision->id
        ) {
            $result['message'] =
                'The prepared Application does not match the '
                . 'current Renewal authorization.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Fresh Membership assessment immediately before write.
         * -------------------------------------------------
         */

        $assessment_service =
            new SOF_MembershipRenewalAssessmentService();

        $assessment =
            $assessment_service->assess(
                $candidate
            );

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
            $repository->mark_requires_review(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                'Execution stopped because current Membership '
                . 'evidence no longer supports application.'
            );

            $result['status'] =
                'requires_review';

            $result['message'] =
                'The current Membership evidence no longer '
                . 'supports applying this Renewal. '
                . 'Management review is required.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Fresh execution values must match prepared values.
         *
         * The current Membership assessment must still produce
         * exactly the same Renewal dates that were recorded when
         * this Application was prepared.
         *
         * This prevents a prepared Application from executing
         * stale values if Membership business rules change
         * between preparation and execution.
         * -------------------------------------------------
         */

        $fresh_renewal_date =
            trim(
                (string)(
                    $assessment[
                        'renewal_date'
                    ]
                    ?? ''
                )
            );

        $fresh_expiration =
            trim(
                (string)(
                    $assessment[
                        'standard_expiration'
                    ]
                    ?? ''
                )
            );

        $prepared_renewal_date =
            trim(
                (string)$application
                    ->applied_renewal_date
            );

        $prepared_expiration =
            trim(
                (string)$application
                    ->applied_expiration
            );

        $fresh_renewal_day =
            $fresh_renewal_date !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $fresh_renewal_date
                    )
                )
                : '';

        $fresh_expiration_day =
            $fresh_expiration !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $fresh_expiration
                    )
                )
                : '';

        $prepared_renewal_day =
            $prepared_renewal_date !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $prepared_renewal_date
                    )
                )
                : '';

        $prepared_expiration_day =
            $prepared_expiration !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $prepared_expiration
                    )
                )
                : '';

        if (
            $fresh_renewal_day === ''
            || $fresh_expiration_day === ''
            || $prepared_renewal_day === ''
            || $prepared_expiration_day === ''
            || $fresh_renewal_day !==
                $prepared_renewal_day
            || $fresh_expiration_day !==
                $prepared_expiration_day
        ) {
            $repository->mark_requires_review(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                'Execution stopped because the current '
                . 'Membership assessment no longer produces '
                . 'the same Renewal values that were prepared.'
            );

            $result['status'] =
                'requires_review';

            $result['message'] =
                'The Membership Renewal values have changed '
                . 'since this Application was prepared. '
                . 'Management review is required.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Re-read member immediately before write.
         * -------------------------------------------------
         */

        $member =
            coai_get_member_by_id(
                $candidate->member_id
            );

        if (!$member || !is_array($member)) {
            $result['message'] =
                'The Membership record could not be retrieved '
                . 'immediately before application.';

            return $result;
        }

        $member_id =
            (int)(
                $member['member_id']
                ?? $candidate->member_id
            );

        $current_renewal_date =
            trim(
                (string)(
                    $member[
                        'renewal_date'
                    ]
                    ?? ''
                )
            );

        $current_expiration =
            trim(
                (string)(
                    $member[
                        'membership_expiration'
                    ]
                    ?? ''
                )
            );

        /*
         * -------------------------------------------------
         * Guard against stale prepared applications.
         *
         * The BEFORE values must still match what SOF recorded
         * when the pending Application was prepared.
         * -------------------------------------------------
         */

        $current_renewal_day =
            $current_renewal_date !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $current_renewal_date
                    )
                )
                : '';

        $current_expiration_day =
            $current_expiration !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $current_expiration
                    )
                )
                : '';

        $prepared_previous_renewal_day =
            $application->previous_renewal_date !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $application->previous_renewal_date
                    )
                )
                : '';

        $prepared_previous_expiration_day =
            $application->previous_expiration !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $application->previous_expiration
                    )
                )
                : '';

        if (
            $current_renewal_day !==
                $prepared_previous_renewal_day
            || $current_expiration_day !==
                $prepared_previous_expiration_day
        ) {
            $repository->mark_failed(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                'Execution stopped because the Membership '
                . 'record changed after Application preparation.'
            );

            $result['status'] =
                'failed';

            $result['message'] =
                'The Membership record changed after this '
                . 'Application was prepared. Management review '
                . 'is required before proceeding.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Approved values come from the prepared ledger.
         * -------------------------------------------------
         */

        $proposed_renewal_date =
            trim(
                $application->applied_renewal_date
            );

        $proposed_expiration =
            trim(
                $application->applied_expiration
            );

        if (
            $member_id <= 0
            || $proposed_renewal_date === ''
            || $proposed_expiration === ''
        ) {
            $result['message'] =
                'SOF could not determine complete approved '
                . 'Renewal values.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Final duplicate guard.
         * -------------------------------------------------
         */

        if (
            $current_renewal_day ===
                wp_date(
                    'Y-m-d',
                    strtotime(
                        $proposed_renewal_date
                    )
                )
            && $current_expiration_day ===
                wp_date(
                    'Y-m-d',
                    strtotime(
                        $proposed_expiration
                    )
                )
        ) {
            $result['status'] =
                'already_applied';

            $result['message'] =
                'The member already contains the Renewal values '
                . 'this Application would establish.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * THE MEMBERSHIP WRITE.
         * -------------------------------------------------
         */

        $updated =
            coai_update_member_renewal_fields(
                $member_id,
                $proposed_renewal_date,
                $proposed_expiration
            );

        if (!$updated) {
            $repository->mark_failed(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                'SOF could not update the Membership record.'
            );

            $result['status'] =
                'failed';

            $result['message'] =
                'SOF could not update the Membership record.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Verify database after write.
         * -------------------------------------------------
         */

        $verified_member =
            coai_get_member_by_id(
                $member_id
            );

        if (!$verified_member || !is_array($verified_member)) {
            $repository->mark_verification_required(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                'The Membership record was updated but SOF '
                . 'could not verify the resulting record.'
            );

            $result['status'] =
                'verification_required';

            $result['message'] =
                'The Membership record was updated, but SOF '
                . 'could not verify the resulting record.';

            return $result;
        }

        $verified_renewal_date =
            trim(
                (string)(
                    $verified_member[
                        'renewal_date'
                    ]
                    ?? ''
                )
            );

        $verified_expiration =
            trim(
                (string)(
                    $verified_member[
                        'membership_expiration'
                    ]
                    ?? ''
                )
            );

        $verified_renewal_day =
            $verified_renewal_date !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $verified_renewal_date
                    )
                )
                : '';

        $verified_expiration_day =
            $verified_expiration !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $verified_expiration
                    )
                )
                : '';

        $proposed_renewal_day =
            wp_date(
                'Y-m-d',
                strtotime(
                    $proposed_renewal_date
                )
            );

        $proposed_expiration_day =
            wp_date(
                'Y-m-d',
                strtotime(
                    $proposed_expiration
                )
            );

        if (
            $verified_renewal_day !==
                $proposed_renewal_day
            || $verified_expiration_day !==
                $proposed_expiration_day
        ) {
            $repository->mark_verification_required(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                'The Membership update completed but post-write '
                . 'verification did not match the approved values.'
            );

            $result['status'] =
                'verification_required';

            $result['message'] =
                'SOF updated the Membership record, but the '
                . 'verification values do not match the '
                . 'approved Renewal.';

            return $result;
        }

        /*
         * -------------------------------------------------
         * Mark ledger APPLIED only after successful verification.
         * -------------------------------------------------
         */

        $marked_applied =
            $repository->mark_applied(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                get_current_user_id(),
                current_time(
                    'mysql'
                ),
                'Membership Renewal applied by SOF and verified '
                . 'against the Membership record.'
            );

        if (!$marked_applied) {
            $repository->mark_verification_required(
                $candidate->source_provider,
                $candidate->source_transaction_id,
                'The Membership Renewal was applied and verified, '
                . 'but SOF could not finalize the Application '
                . 'ledger as applied.'
            );

            $result['status'] =
                'verification_required';

            $result['message'] =
                'The Membership Renewal was applied and verified, '
                . 'but SOF could not finalize the Application '
                . 'ledger. Manual verification is required.';

            return $result;
        }

        $result['success'] =
            true;

        $result['status'] =
            'applied';

        $result['member_id'] =
            $member_id;

        $result['renewal_date'] =
            $verified_renewal_day;

        $result['expiration_date'] =
            $verified_expiration_day;

        $result['message'] =
            'The Membership Renewal was applied and verified.';

        return $result;
    }
}