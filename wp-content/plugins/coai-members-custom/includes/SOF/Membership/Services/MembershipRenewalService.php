<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Service
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Membership Renewal
 *
 * Purpose:
 *     Assess a member's current Membership situation and
 *     determine whether renewal should be offered.
 *
 * Responsibilities:
 *     - Read Membership status
 *     - Read Membership Expiration Date
 *     - Normalize the expiration date
 *     - Determine days remaining
 *     - Apply the organizational renewal window
 *     - Determine whether renewal may be offered
 *     - Return a reusable Membership Renewal Situation
 *
 * Does NOT:
 *     - Render presentation
 *     - Redirect users
 *     - Process payments
 *     - Know about Zeffy
 *     - Modify Membership records
 *     - Determine WordPress access
 *
 * Business Rule:
 *     Renewal may be offered when Membership expires
 *     within 60 days or has already expired.
 *
 * Architectural Principle:
 *
 *     Membership decides whether renewal is appropriate.
 *
 *     Presentation decides how that situation is shown.
 *
 * ============================================================
 */

class SOF_MembershipRenewalService
{
    /**
     * Number of days before expiration when renewal
     * becomes appropriate.
     */
    protected int $renewal_window_days = 60;


    /**
     * Assess a Membership record.
     *
     * @param array<string, mixed> $member
     *
     * @return array<string, mixed>
     */
    public function assess(
        array $member
    ): array {

        // -------------------------------------------------
        // Membership Status
        // -------------------------------------------------

        $status =
            trim(
                (string) (
                    $member['status']
                    ?? ''
                )
            );

        $normalized_status =
            strtolower($status);


        // -------------------------------------------------
        // Deceased Membership
        // -------------------------------------------------

        if ($normalized_status === 'deceased') {

            return [
                'situation' =>
                    'deceased',

                'membership_status' =>
                    $status,

                'expiration_date' =>
                    '',

                'expiration_timestamp' =>
                    null,

                'days_until_expiration' =>
                    null,

                'renewal_window_days' =>
                    $this->renewal_window_days,

                'may_renew' =>
                    false,

                'message' =>
                    'Renewal is not available for this Membership record.',
            ];
        }


        // -------------------------------------------------
        // Membership Expiration
        // -------------------------------------------------

        $expiration_raw =
            trim(
                (string) (
                    $member['membership_expiration']
                    ?? ''
                )
            );

        if ($expiration_raw === '') {

            return $this->unavailable(
                $status
            );
        }


        // -------------------------------------------------
        // Normalize Expiration Date
        // -------------------------------------------------

        $expiration_date =
            $this->normalize_date(
                $expiration_raw
            );

        if ($expiration_date === null) {

            return $this->unavailable(
                $status
            );
        }


        // -------------------------------------------------
        // Current Date
        // -------------------------------------------------

        $today =
            new DateTimeImmutable(
                'today',
                $this->timezone()
            );


        // -------------------------------------------------
        // Days Until Expiration
        // -------------------------------------------------

        $days_until_expiration =
            (int) $today
                ->diff($expiration_date)
                ->format('%r%a');


        // -------------------------------------------------
        // Expired
        // -------------------------------------------------

        if (
            $days_until_expiration < 0 ||
            $normalized_status === 'expired'
        ) {

            return [
                'situation' =>
                    'expired',

                'membership_status' =>
                    $status,

                'expiration_date' =>
                    $expiration_date->format(
                        'Y-m-d'
                    ),

                'expiration_timestamp' =>
                    $expiration_date
                        ->getTimestamp(),

                'days_until_expiration' =>
                    $days_until_expiration,

                'renewal_window_days' =>
                    $this->renewal_window_days,

                'may_renew' =>
                    true,

                'message' =>
                    'Your membership has expired and may be renewed.',
            ];
        }


        // -------------------------------------------------
        // Renewal Window
        // -------------------------------------------------

        if (
            $days_until_expiration <=
                $this->renewal_window_days
        ) {

            return [
                'situation' =>
                    'renewal_window',

                'membership_status' =>
                    $status,

                'expiration_date' =>
                    $expiration_date->format(
                        'Y-m-d'
                    ),

                'expiration_timestamp' =>
                    $expiration_date
                        ->getTimestamp(),

                'days_until_expiration' =>
                    $days_until_expiration,

                'renewal_window_days' =>
                    $this->renewal_window_days,

                'may_renew' =>
                    true,

                'message' =>
                    'Your membership is approaching expiration and may be renewed.',
            ];
        }


        // -------------------------------------------------
        // Current Membership
        // -------------------------------------------------

        return [
            'situation' =>
                'current',

            'membership_status' =>
                $status,

            'expiration_date' =>
                $expiration_date->format(
                    'Y-m-d'
                ),

            'expiration_timestamp' =>
                $expiration_date
                    ->getTimestamp(),

            'days_until_expiration' =>
                $days_until_expiration,

            'renewal_window_days' =>
                $this->renewal_window_days,

            'may_renew' =>
                false,

            'message' =>
                'Your membership is current. There is no need to renew at this time.',
        ];
    }


    /**
     * Return the configured renewal window.
     */
    public function renewal_window_days(): int
    {
        return $this->renewal_window_days;
    }


    /**
     * Normalize a database date or datetime value.
     */
    protected function normalize_date(
        string $value
    ): ?DateTimeImmutable {

        $value =
            trim($value);

        if ($value === '') {
            return null;
        }

        /*
         * Membership dates may be stored as:
         *
         *     2027-08-30
         *
         * or:
         *
         *     2027-08-30 00:00:00
         *
         * Renewal assessment is based upon the calendar
         * date, not the stored time component.
         */

        $calendar_date =
            substr(
                $value,
                0,
                10
            );

        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $calendar_date,
                $this->timezone()
            );

        if (!$date) {
            return null;
        }

        $errors =
            DateTimeImmutable::getLastErrors();

        if (
            is_array($errors) &&
            (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )
        ) {
            return null;
        }

        return $date;
    }


    /**
     * Return an unavailable assessment.
     *
     * @return array<string, mixed>
     */
    protected function unavailable(
        string $status
    ): array {

        return [
            'situation' =>
                'unavailable',

            'membership_status' =>
                $status,

            'expiration_date' =>
                '',

            'expiration_timestamp' =>
                null,

            'days_until_expiration' =>
                null,

            'renewal_window_days' =>
                $this->renewal_window_days,

            'may_renew' =>
                false,

            'message' =>
                'Your membership expiration date could not be determined.',
        ];
    }


    /**
     * Resolve the WordPress site timezone.
     */
    protected function timezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        return new DateTimeZone('UTC');
    }
}