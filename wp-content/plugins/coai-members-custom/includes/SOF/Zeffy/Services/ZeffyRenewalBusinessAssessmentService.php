<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Business Assessment Service
 * ============================================================
 *
 * Purpose:
 *     Assess what a matched Zeffy Renewal payment means to the
 *     member's current MyCOAI membership.
 *
 * Responsibilities:
 *     - Retrieve the matched member
 *     - Read the current membership expiration
 *     - Calculate the standard renewal expiration
 *     - Compare current and standard expiration dates
 *     - Recommend whether the renewal is ready or requires review
 *
 * Does NOT:
 *     - Update membership expiration
 *     - Update membership status
 *     - Process the renewal
 *     - Resolve member identity
 *     - Send notifications
 *
 * ============================================================
 */

class SOF_ZeffyRenewalBusinessAssessmentService
{
    /**
     * Assess one matched Renewal transaction.
     */
    public function assess(
        SOF_ZeffyTransaction $transaction
    ): array {

        $result = [
            'assessment_status'   => 'cannot_assess',
            'assessment_title'    => 'Renewal Cannot Be Assessed',
            'recommended_path'    => 'Review Renewal',
            'reason'              => '',
            'member'              => null,
            'current_expiration'  => null,
            'renewal_date'        => null,
            'standard_expiration' => null,
        ];

        /*
         * ----------------------------------------------------
         * This service only evaluates Renewal transactions.
         * ----------------------------------------------------
         */

        if ($transaction->business_process !== 'renewal') {
            $result['reason'] =
                'This Zeffy transaction is not part of the '
                . 'Renewal business process.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Payment must have succeeded.
         * ----------------------------------------------------
         */

        if ($transaction->payment_status !== 'succeeded') {
            $result['reason'] =
                'The Zeffy payment has not completed successfully.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Membership product must already be understood.
         * ----------------------------------------------------
         */

        if ($transaction->membership_product === '') {
            $result['reason'] =
                'The Zeffy membership product has not been '
                . 'identified.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Identity must already be established.
         * ----------------------------------------------------
         */

        if (
            $transaction->identity_status !== 'matched'
            || empty($transaction->matched_member_id)
        ) {
            $result['reason'] =
                'The Renewal transaction does not yet have a '
                . 'matched MyCOAI member.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Renewal date must be known.
         * ----------------------------------------------------
         */

        if (empty($transaction->payment_date)) {
            $result['reason'] =
                'The Renewal transaction does not contain a '
                . 'payment date.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Retrieve the matched MyCOAI member.
         * ----------------------------------------------------
         */

        if (!class_exists('SOF_MemberLookupService')) {
            $result['reason'] =
                'The SOF Membership Member Lookup Service '
                . 'is not available.';

            return $result;
        }

        $member_service =
            new SOF_MemberLookupService();

        $member =
            $member_service->find_by_id(
                $transaction->matched_member_id
            );

        if (!$member || !is_array($member)) {
            $result['reason'] =
                'The matched MyCOAI member could not be found.';

            return $result;
        }

        $result['member'] = $member;

        /*
         * ----------------------------------------------------
         * COAI Renewal Rule
         *
         * Standard expiration =
         * renewal date + 1 year - 1 day.
         * ----------------------------------------------------
         */

        try {
            $renewal_date = new DateTimeImmutable(
                $transaction->payment_date
            );

            $standard_expiration =
                $renewal_date
                    ->modify('+1 year')
                    ->modify('-1 day');

        } catch (Exception $exception) {

            $result['reason'] =
                'SOF could not interpret the Renewal payment date.';

            return $result;
        }

        $renewal_day =
            $renewal_date->format('Y-m-d');

        $standard_day =
            $standard_expiration->format('Y-m-d');

        $result['renewal_date'] =
            $renewal_day;

        $result['standard_expiration'] =
            $standard_day;

        /*
         * ----------------------------------------------------
         * Read current member expiration.
         * ----------------------------------------------------
         */

        $current_expiration = trim(
            (string)(
                $member['membership_expiration']
                ?? ''
            )
        );

        /*
         * ----------------------------------------------------
         * No expiration currently recorded.
         * ----------------------------------------------------
         */

        if ($current_expiration === '') {

            $result['assessment_status'] =
                'ready_to_apply';

            $result['assessment_title'] =
                'Renewal Ready to Apply';

            $result['recommended_path'] =
                'Apply Standard Renewal';

            $result['reason'] =
                'This member does not currently have an '
                . 'expiration date. This renewal would establish '
                . 'an expiration date of '
                . $standard_expiration->format('m/d/Y')
                . '.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Interpret the current expiration.
         * ----------------------------------------------------
         */

        try {
            $current_date =
                new DateTimeImmutable($current_expiration);

        } catch (Exception $exception) {

            $result['assessment_status'] =
                'management_review';

            $result['assessment_title'] =
                'Renewal Requires Management Review';

            $result['recommended_path'] =
                'Review Existing Membership';

            $result['reason'] =
                'The member has an expiration date that SOF '
                . 'could not interpret.';

            return $result;
        }

        $current_day =
            $current_date->format('Y-m-d');

        $result['current_expiration'] =
            $current_day;

        /*
         * ----------------------------------------------------
         * Current expiration is earlier than the standard
         * expiration created by this Renewal.
         *
         * This is the normal renewal path.
         *
         * A member may renew before the current membership
         * expires. The Renewal establishes a new expiration
         * based on the Renewal payment date.
         * ----------------------------------------------------
         */

        if ($current_day < $standard_day) {

            $result['assessment_status'] =
                'ready_to_apply';

            $result['review_type'] =
                'standard_renewal';

            $result['assessment_title'] =
                'Renewal Ready to Apply';

            $result['recommended_path'] =
                'Apply Standard Renewal';

            $result['reason'] =
                'This member currently has an expiration date of '
                . $current_date->format('m/d/Y')
                . '. This renewal was received on '
                . $renewal_date->format('m/d/Y')
                . ' and would establish a new expiration date of '
                . $standard_expiration->format('m/d/Y')
                . '.';

            return $result;
        }
        
        /*
         * ----------------------------------------------------
         * Existing expiration exactly equals what this Renewal
         * would establish.
         *
         * The payment may already have been applied.
         * ----------------------------------------------------
         */

        if ($current_day === $standard_day) {

            $result['assessment_status'] =
                'possible_previously_applied';

            $result['assessment_title'] =
                'Renewal Requires Management Review';

            $result['recommended_path'] =
                'Review Existing Membership';

            $result['reason'] =
                'This member already has an expiration date of '
                . $current_date->format('m/d/Y')
                . ', which is the same expiration date this '
                . 'renewal would normally establish. The renewal '
                . 'may already have been applied.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Existing expiration is later than what this Renewal
         * would normally establish.
         *
         * This strongly suggests an earlier/additional renewal
         * or a management extension.
         * ----------------------------------------------------
         */

        $result['assessment_status'] =
            'management_review';

        $result['assessment_title'] =
            'Renewal Requires Management Review';

        $result['recommended_path'] =
            'Review Existing Membership';

        $result['reason'] =
            'This member already has an expiration date of '
            . $current_date->format('m/d/Y')
            . ', which is later than the standard expiration '
            . 'date of '
            . $standard_expiration->format('m/d/Y')
            . ' for this renewal. The member may have already '
            . 'renewed. Management should review the membership '
            . 'before applying this payment.';

        return $result;
    }
}