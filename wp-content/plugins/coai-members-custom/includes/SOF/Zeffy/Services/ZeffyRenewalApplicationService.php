<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Application Service
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Service:
 *     Renewal Application
 *
 * Purpose:
 *     Safely apply one management-approved Renewal to the
 *     matched MyCOAI member.
 *
 * Responsibilities:
 *     - Re-read the current member immediately before update
 *     - Re-assess the Renewal against current member facts
 *     - Refuse an update when no membership change is needed
 *     - Update only approved Renewal-controlled membership data
 *     - Verify that the expected values were stored
 *
 * Does NOT:
 *     - Resolve identity
 *     - Decide whether management approval is appropriate
 *     - Apply unresolved Renewal transactions
 *
 * ============================================================
 */

class SOF_ZeffyRenewalApplicationService
{
    public function apply(
        SOF_ZeffyTransaction $transaction
    ): array {

        $result = [
            'success'             => false,
            'status'              => 'not_applied',
            'message'             => '',
            'member_id'           => 0,
            'renewal_date'        => '',
            'expiration_date'     => '',
        ];

        if (
            $transaction->id <= 0
            || $transaction->matched_member_id <= 0
        ) {
            $result['message'] =
                'The Renewal does not have an established member identity.';

            return $result;
        }

        if (
            !class_exists(
                'SOF_ZeffyRenewalBusinessAssessmentService'
            )
            || !class_exists(
                'SOF_ZeffyRenewalManagementDecisionService'
            )
            || !function_exists(
                'coai_get_member_by_id'
            )
            || !function_exists(
                'coai_update_member_renewal_fields'
            )
        ) {
            $result['message'] =
                'The required Membership application services are not available.';

            return $result;
        }

        /*
         * Re-assess immediately before execution.
         *
         * The member record may have changed since management
         * first reviewed this Renewal.
         *
         * This fresh assessment is also the authorization
         * evidence for SOF Ready to Apply Renewals.
         */
        $assessment_service =
            new SOF_ZeffyRenewalBusinessAssessmentService();

        $assessment =
            $assessment_service->assess(
                $transaction
            );

        $assessment_status =
            (string)(
                $assessment['assessment_status']
                ?? ''
            );

        $decision_service =
            new SOF_ZeffyRenewalManagementDecisionService();

        $decision =
            $decision_service->find(
                (int)$transaction->id
            );

        $management_authorized =
            (
                $decision
                && $decision->decision ===
                    SOF_ZeffyRenewalManagementDecisionService::
                        DECISION_NEEDS_PROCESSING
            );

        $sof_authorized =
            (
                $assessment_status ===
                'ready_to_apply'
            );

        if (
            !$management_authorized
            && !$sof_authorized
        ) {
            $result['message'] =
                'This Renewal is no longer authorized for processing based on the current membership evidence.';

            return $result;
        }

        $member =
            (
                isset($assessment['member'])
                && is_array(
                    $assessment['member']
                )
            )
                ? $assessment['member']
                : [];

        if (!$member) {
            $result['message'] =
                'The matched MyCOAI member could not be retrieved.';

            return $result;
        }

        $member_id =
            (int)(
                $member['member_id']
                ?? 0
            );

        $proposed_renewal_date =
            trim(
                (string)(
                    $assessment['renewal_date']
                    ?? ''
                )
            );

        $proposed_expiration =
            trim(
                (string)(
                    $assessment['standard_expiration']
                    ?? ''
                )
            );

        if (
            $member_id <= 0
            || $proposed_renewal_date === ''
            || $proposed_expiration === ''
        ) {
            $result['message'] =
                'SOF could not determine complete Renewal values.';

            return $result;
        }

        $current_renewal_date =
            trim(
                (string)(
                    $member['renewal_date']
                    ?? ''
                )
            );

        $current_expiration =
            trim(
                (string)(
                    $member['membership_expiration']
                    ?? ''
                )
            );

        /*
         * Guardrail:
         * never write when the member already contains the
         * exact values this Renewal would establish.
         */
        if (
            $current_renewal_date ===
                $proposed_renewal_date
            && $current_expiration ===
                $proposed_expiration
        ) {
            $result['status'] =
                'already_applied';

            $result['message'] =
                'The member already contains the Renewal Date and Expiration Date this payment would establish.';

            $result['member_id'] =
                $member_id;

            $result['renewal_date'] =
                $proposed_renewal_date;

            $result['expiration_date'] =
                $proposed_expiration;

            return $result;
        }

        $updated =
            coai_update_member_renewal_fields(
                $member_id,
                $proposed_renewal_date,
                $proposed_expiration
            );

        if (!$updated) {
            $result['message'] =
                'SOF could not update the member record.';

            return $result;
        }

        /*
         * Verify the database after the write.
         */
        $verified_member =
            coai_get_member_by_id(
                $member_id
            );

        if (!$verified_member) {
            $result['message'] =
                'The member was updated, but SOF could not verify the resulting record.';

            return $result;
        }

        $verified_renewal_date =
            trim(
                (string)(
                    $verified_member['renewal_date']
                    ?? ''
                )
            );

        $verified_expiration =
            trim(
                (string)(
                    $verified_member['membership_expiration']
                    ?? ''
                )
            );

        /*
         * Normalize stored member dates before verification.
         *
         * MyCOAI may store membership_expiration as a DATETIME
         * value such as:
         *
         *     2027-07-28 00:00:00
         *
         * while SOF business assessment uses the calendar date:
         *
         *     2027-07-28
         *
         * These represent the same business date and should
         * therefore verify as equal.
         */
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
            $proposed_renewal_date !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $proposed_renewal_date
                    )
                )
                : '';

        $proposed_expiration_day =
            $proposed_expiration !== ''
                ? wp_date(
                    'Y-m-d',
                    strtotime(
                        $proposed_expiration
                    )
                )
                : '';

        if (
            $verified_renewal_day !==
                $proposed_renewal_day
            || $verified_expiration_day !==
                $proposed_expiration_day
        ) {
            $result['message'] =
                'SOF updated the member but the verification values do not match the approved Renewal.';

            return $result;
        }

        $decision_recorded =
            $decision_service->decide(
                (int)$transaction->id,
                SOF_ZeffyRenewalManagementDecisionService::
                    DECISION_APPLIED,
                get_current_user_id(),
                'Renewal applied by SOF and verified against the member record.'
            );

        if (!$decision_recorded) {
            $result['message'] =
                'The member was updated and verified, but SOF could not record the completed Renewal decision.';

            return $result;
        }

        $result['success'] =
            true;

        $result['status'] =
            'applied';

        $result['message'] =
            'The Renewal was applied and the member record was verified.';

        $result['member_id'] =
            $member_id;

        $result['renewal_date'] =
            $verified_renewal_day;

        $result['expiration_date'] =
            $verified_expiration_day;

        return $result;
    }
}