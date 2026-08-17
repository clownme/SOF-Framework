<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy New Membership Business Assessment Service
 * ============================================================
 *
 * Purpose:
 *     Assess what a matched Zeffy New Membership payment means
 *     to the current MyCOAI membership situation.
 *
 * Responsibilities:
 *     - Recognize New Membership transactions
 *     - Evaluate established member identity
 *     - Detect when an existing member used New Membership
 *     - Recommend the appropriate business path
 *
 * Does NOT:
 *     - Change the Zeffy business process
 *     - Convert a New Membership into a Renewal
 *     - Update membership records
 *     - Change expiration dates
 *     - Process payments
 *     - Make management decisions
 *
 * ============================================================
 */

class SOF_ZeffyNewMembershipBusinessAssessmentService
{
    /**
     * Assess one New Membership transaction.
     */
    public function assess(
        SOF_ZeffyTransaction $transaction
    ): array {

        $result = [
            'assessment_status' =>
                'cannot_assess',

            'assessment_title' =>
                'New Membership Cannot Be Assessed',

            'recommended_path' =>
                'Review New Membership',

            'reason' =>
                '',

            'member' =>
                null,

            'payment_date' =>
                null,

            'current_expiration' =>
                null,

            'standard_expiration' =>
                null,
        ];

        /*
         * ----------------------------------------------------
         * This service evaluates only New Membership
         * transactions.
         * ----------------------------------------------------
         */

        if (
            $transaction->business_process
            !== 'new_membership'
        ) {
            $result['reason'] =
                'This Zeffy transaction is not part of the '
                . 'New Membership business process.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Payment must have succeeded.
         * ----------------------------------------------------
         */

        if (
            $transaction->payment_status
            !== 'succeeded'
        ) {
            $result['reason'] =
                'The Zeffy payment has not completed '
                . 'successfully.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Identity must already have been assessed.
         * ----------------------------------------------------
         */

        if (
            $transaction->identity_status
            === 'unassessed'
        ) {
            $result['reason'] =
                'The New Membership transaction has not yet '
                . 'completed identity assessment.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Existing MyCOAI member detected.
         *
         * Zeffy says New Membership.
         * SOF knows the payer is already a member.
         *
         * Membership owns the authoritative Membership period
         * rule. This service compares that Membership period
         * with the member's current expiration.
         *
         * No member records are changed.
         * ----------------------------------------------------
         */

        if (
            $transaction->identity_status
            === 'matched'
            && !empty(
                $transaction->matched_member_id
            )
        ) {

            $member = null;

            if (
                class_exists(
                    'SOF_MemberLookupService'
                )
            ) {
                $member_service =
                    new SOF_MemberLookupService();

                $member =
                    $member_service->find_by_id(
                        $transaction
                            ->matched_member_id
                    );
            }

            $result['member'] =
                is_array($member)
                    ? $member
                    : null;

            /*
             * Member evidence is required for comparison.
             */
            if (
                !$member
                || !is_array($member)
            ) {
                $result['assessment_status'] =
                    'existing_member_review';

                $result['assessment_title'] =
                    'Existing Member Used New Membership Registration';

                $result['recommended_path'] =
                    'Review Existing Membership';

                $result['reason'] =
                    'SOF matched this New Membership payment '
                    . 'to an existing MyCOAI member, but the '
                    . 'current membership record could not be '
                    . 'retrieved for comparison.';

                return $result;
            }

            /*
             * Membership Period Service must own the date rule.
             */
            if (
                !class_exists(
                    'SOF_MembershipPeriodService'
                )
            ) {
                $result['assessment_status'] =
                    'existing_member_review';

                $result['assessment_title'] =
                    'Existing Member Used New Membership Registration';

                $result['recommended_path'] =
                    'Review Existing Membership';

                $result['reason'] =
                    'SOF matched this payment to an existing '
                    . 'member, but the Membership Period Service '
                    . 'is not available.';

                return $result;
            }

            $period_service =
                new SOF_MembershipPeriodService();

            $period =
                $period_service->calculate(
                    (string)(
                        $transaction->payment_date
                        ?? ''
                    )
                );

            if (
                empty(
                    $period['success']
                )
            ) {
                $result['assessment_status'] =
                    'existing_member_review';

                $result['assessment_title'] =
                    'Existing Member Used New Membership Registration';

                $result['recommended_path'] =
                    'Review Existing Membership';

                $result['reason'] =
                    (string)(
                        $period['message']
                        ?? 'SOF could not determine the Membership period.'
                    );

                return $result;
            }

            $payment_day =
                trim(
                    (string)(
                        $period['start_date']
                        ?? ''
                    )
                );

            $standard_day =
                trim(
                    (string)(
                        $period['expiration_date']
                        ?? ''
                    )
                );

            $result['payment_date'] =
                $payment_day;

            $result['standard_expiration'] =
                $standard_day;

            /*
             * Read the existing member expiration.
             */
            $current_expiration =
                trim(
                    (string)(
                        $member[
                            'membership_expiration'
                        ]
                        ?? ''
                    )
                );

            if ($current_expiration === '') {

                $result['assessment_status'] =
                    'possible_renewal';

                $result['assessment_title'] =
                    'Existing Member Used New Membership Registration';

                $result['recommended_path'] =
                    'Review as Possible Membership Renewal';

                $result['reason'] =
                    'SOF matched this New Membership payment '
                    . 'to an existing member who does not '
                    . 'currently have a Membership expiration '
                    . 'date. Management should determine whether '
                    . 'the payment should be treated as a renewal.';

                return $result;
            }

            try {

                $current_date =
                    new DateTimeImmutable(
                        $current_expiration
                    );

            } catch (Exception $exception) {

                $result['assessment_status'] =
                    'existing_member_review';

                $result['assessment_title'] =
                    'Existing Member Used New Membership Registration';

                $result['recommended_path'] =
                    'Review Existing Membership';

                $result['reason'] =
                    'SOF matched this payment to an existing '
                    . 'member, but the current Membership '
                    . 'expiration date could not be interpreted.';

                return $result;
            }

            $current_day =
                $current_date->format(
                    'Y-m-d'
                );

            $result['current_expiration'] =
                $current_day;

            /*
             * ------------------------------------------------
             * Exact match.
             *
             * The member already has exactly the expiration
             * this payment would establish.
             * ------------------------------------------------
             */

            if (
                $current_day ===
                $standard_day
            ) {
                $result['assessment_status'] =
                    'possible_already_applied';

                $result['assessment_title'] =
                    'New Membership Payment May Already Be Applied';

                $result['recommended_path'] =
                    'Confirm Whether Payment Is Already Reflected';

                $result['reason'] =
                    'SOF matched this New Membership payment '
                    . 'to an existing member. The member currently '
                    . 'expires on '
                    . $current_date->format('m/d/Y')
                    . ', which is exactly the expiration date '
                    . 'this payment would establish under the '
                    . 'COAI Membership period rule.';

                return $result;
            }

            /*
             * ------------------------------------------------
             * Legacy Membership Period Convention
             *
             * Historical COAI records may have been created
             * under the previous Membership rule:
             *
             *     start date + 1 year
             *
             * The current rule is:
             *
             *     start date + 1 year - 1 day
             *
             * If the stored expiration is exactly one day
             * later than the current standard, treat that as
             * strong evidence that the payment was already
             * reflected under the legacy rule.
             * ------------------------------------------------
             */

            try {

                $legacy_expiration =
                    (
                        new DateTimeImmutable(
                            $standard_day
                        )
                    )
                        ->modify('+1 day');

            } catch (Exception $exception) {

                $legacy_expiration =
                    null;
            }

            if (
                $legacy_expiration
                && $current_day ===
                    $legacy_expiration->format(
                        'Y-m-d'
                    )
            ) {

                $result['assessment_status'] =
                    'possible_already_applied';

                $result['assessment_title'] =
                    'New Membership Payment May Already Be Applied';

                $result['recommended_path'] =
                    'Confirm Whether Payment Is Already Reflected';

                $result['reason'] =
                    'SOF matched this New Membership payment '
                    . 'to an existing member. The member currently '
                    . 'expires on '
                    . $current_date->format('m/d/Y')
                    . ', which is one day later than the '
                    . 'expiration produced by the current COAI '
                    . 'Membership period rule. This matches the '
                    . 'previous COAI Membership convention of '
                    . 'using the same calendar date one year '
                    . 'later. The payment may already be reflected '
                    . 'in the member record.';

                return $result;
            }

            /*
             * ------------------------------------------------
             * Current expiration is later for some other reason.
             *
             * Never recommend automatic application.
             * ------------------------------------------------
             */

            $result['assessment_status'] =
                'existing_member_review';

            $result['assessment_title'] =
                'Existing Member Used New Membership Registration';

            $result['recommended_path'] =
                'Review Existing Membership';

            $result['reason'] =
                'SOF matched this New Membership payment '
                . 'to an existing member whose current expiration '
                . 'is later than both the current COAI Membership '
                . 'period and the recognized legacy one-year '
                . 'Membership convention. Management should review '
                . 'the membership before taking any action.';

            return $result;

            /*
             * ------------------------------------------------
             * Current expiration is later.
             *
             * Never recommend automatic application.
             * ------------------------------------------------
             */

            $result['assessment_status'] =
                'existing_member_review';

            $result['assessment_title'] =
                'Existing Member Used New Membership Registration';

            $result['recommended_path'] =
                'Review Existing Membership';

            $result['reason'] =
                'SOF matched this New Membership payment '
                . 'to an existing member whose current expiration '
                . 'is later than the expiration this payment '
                . 'would establish. Management should review '
                . 'the membership before taking any action.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * No existing member was established.
         *
         * This remains a New Membership situation.
         * ----------------------------------------------------
         */

        $result['assessment_status'] =
            'new_membership';

        $result['assessment_title'] =
            'New Membership Registration';

        $result['recommended_path'] =
            'Process New Membership';

        $result['reason'] =
            'SOF did not establish that this payer is an '
            . 'existing MyCOAI member. The transaction remains '
            . 'in the New Membership business process.';

        return $result;
    }
}