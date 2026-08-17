<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Assessment Service
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business
 *
 * Purpose:
 *     Assess what a provider-independent Membership Renewal
 *     Candidate means to the member's current membership.
 *
 * Responsibilities:
 *     - Validate the Renewal Candidate
 *     - Retrieve the affected member
 *     - Read the current Membership expiration
 *     - Calculate the standard Membership expiration
 *     - Compare current and standard expiration dates
 *     - Recommend whether the Renewal is ready or requires review
 *
 * Does NOT:
 *     - Call a payment provider
 *     - Interpret provider-specific fields
 *     - Change source transaction evidence
 *     - Update Membership expiration
 *     - Update Membership status
 *     - Apply the Renewal
 *     - Make a management decision
 *
 * ============================================================
 */

class SOF_MembershipRenewalAssessmentService
{
    /**
     * Assess one Membership Renewal Candidate.
     *
     * @return array<string, mixed>
     */
    public function assess(
        SOF_MembershipRenewalCandidate $candidate
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
         * Candidate must contain minimum Renewal evidence.
         * ----------------------------------------------------
         */

        if (!$candidate->is_valid()) {
            $result['reason'] =
                'The Membership Renewal Candidate does not '
                . 'contain complete Renewal evidence.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Membership intent must be Renewal.
         * ----------------------------------------------------
         */

        if ($candidate->membership_intent !== 'renewal') {
            $result['reason'] =
                'The Membership Candidate has not been '
                . 'established for Renewal processing.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Retrieve the affected MyCOAI member.
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
                $candidate->member_id
            );

        if (!$member || !is_array($member)) {
            $result['reason'] =
                'The affected MyCOAI member could not be found.';

            return $result;
        }

        $result['member'] =
            $member;

        /*
         * ----------------------------------------------------
         * COAI Membership Period Rule
         *
         * Membership owns the authoritative rule for determining
         * the expiration date established by a Membership start
         * date.
         *
         * The provider supplies payment evidence.
         * Membership determines the Membership period.
         * ----------------------------------------------------
         */

        if (
            !class_exists(
                'SOF_MembershipPeriodService'
            )
        ) {
            $result['reason'] =
                'The SOF Membership Period Service '
                . 'is not available.';

            return $result;
        }

        $membership_period_service =
            new SOF_MembershipPeriodService();

        $membership_period =
            $membership_period_service->calculate(
                $candidate->payment_date
            );

        if (
            empty(
                $membership_period['success']
            )
        ) {
            $result['reason'] =
                (string)(
                    $membership_period['message']
                    ?? 'SOF could not determine the Membership period.'
                );

            return $result;
        }

        $renewal_day =
            trim(
                (string)(
                    $membership_period['start_date']
                    ?? ''
                )
            );

        $standard_day =
            trim(
                (string)(
                    $membership_period['expiration_date']
                    ?? ''
                )
            );

        if (
            $renewal_day === ''
            || $standard_day === ''
        ) {
            $result['reason'] =
                'SOF could not determine complete Membership '
                . 'period dates.';

            return $result;
        }

        /*
         * ----------------------------------------------------
         * Retain date objects for presentation and comparison.
         * ----------------------------------------------------
         */

        try {

            $renewal_date =
                new DateTimeImmutable(
                    $renewal_day
                );

            $standard_expiration =
                new DateTimeImmutable(
                    $standard_day
                );

        } catch (Exception $exception) {

            $result['reason'] =
                'SOF could not interpret the calculated '
                . 'Membership period.';

            return $result;
        }

        $result['renewal_date'] =
            $renewal_day;

        $result['standard_expiration'] =
            $standard_day;

        /*
         * ----------------------------------------------------
         * Read current member expiration.
         * ----------------------------------------------------
         */

        $current_expiration =
            trim(
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

            $result['review_type'] =
                'standard_renewal';

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
         * Interpret current expiration.
         * ----------------------------------------------------
         */

        try {

            $current_date =
                new DateTimeImmutable(
                    $current_expiration
                );

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
            $current_date->format(
                'Y-m-d'
            );

        $result['current_expiration'] =
            $current_day;

        /*
         * ----------------------------------------------------
         * Current expiration is earlier than the standard
         * expiration created by this Renewal.
         *
         * This is the normal Renewal path.
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
         * This may indicate an earlier/additional Renewal or
         * a management extension.
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