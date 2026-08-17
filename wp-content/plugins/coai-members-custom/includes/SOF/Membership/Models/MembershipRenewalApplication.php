<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Application
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business Model
 *
 * Purpose:
 *     Represent the actual application of an approved Membership
 *     Renewal to a member record.
 *
 *     A Renewal Application is distinct from:
 *
 *         - Payment evidence
 *         - Renewal Candidate
 *         - Renewal Assessment
 *         - Management Approval
 *
 *     Management Approval authorizes application.
 *     Renewal Application records what SOF actually changed.
 *
 * Responsibilities:
 *     - Identify the source payment transaction
 *     - Identify the affected member
 *     - Preserve the approving management decision
 *     - Preserve the Membership expiration before application
 *     - Preserve the Membership expiration after application
 *     - Record who applied the Renewal
 *     - Record when the Renewal was applied
 *     - Record the application status
 *
 * Does NOT:
 *     - Call a payment provider
 *     - Determine whether Management should approve a Renewal
 *     - Calculate Membership business rules
 *     - Update a member by itself
 *     - Change payment-provider evidence
 *     - Make a management decision
 *
 * ============================================================
 */

class SOF_MembershipRenewalApplication
{
    /**
     * Provider that supplied the original payment evidence.
     *
     * Examples:
     *     zeffy
     *     paypal
     *     zelle
     *     check
     */
    public string $source_provider = '';

    /**
     * Provider-owned transaction identifier.
     */
    public string $source_transaction_id = '';

    /**
     * MyCOAI member receiving the Renewal application.
     */
    public int $member_id = 0;

    /**
     * Membership management decision that authorized application.
     *
     * This should point to the Renewal-stage:
     *
     *     approve_renewal
     *
     * decision.
     */
    public int $approval_decision_id = 0;

    /**
     * Date the Renewal payment was received.
     *
     * Normalized to YYYY-MM-DD.
     */
    public string $payment_date = '';

    /**
     * Payment amount associated with the Renewal.
     */
    public float $payment_amount = 0.0;

    /**
     * Membership Renewal Date immediately before application.
     *
     * May be blank if the member had no Renewal Date.
     */
    public string $previous_renewal_date = '';

    /**
     * Membership Renewal Date established by this application.
     */
    public string $applied_renewal_date = '';

    /**
     * Membership expiration immediately before application.
     *
     * May be blank if the member had no expiration.
     */
    public string $previous_expiration = '';

    /**
     * Membership expiration established by this application.
     */
    public string $applied_expiration = '';

    /**
     * Application status.
     *
     * Expected values:
     *
     *     pending
     *     applied
     *     rejected
     *     failed
     */
    public string $application_status = 'pending';

    /**
     * WordPress/MyCOAI user who performed the application.
     */
    public int $applied_by = 0;

    /**
     * Application timestamp.
     *
     * Expected format:
     *     Y-m-d H:i:s
     */
    public string $applied_at = '';

    /**
     * Optional business/audit note.
     */
    public string $notes = '';

    /**
     * Determine whether the application contains the minimum
     * identity and authorization evidence required before an
     * application attempt may occur.
     */
    public function is_valid_for_application(): bool
    {
        return (
            trim($this->source_provider) !== ''
            && trim($this->source_transaction_id) !== ''
            && $this->member_id > 0
            && $this->approval_decision_id > 0
            && trim($this->payment_date) !== ''
            && trim($this->applied_renewal_date) !== ''
            && trim($this->applied_expiration) !== ''
        );
    }
    /**
     * Determine whether this Renewal has been successfully
     * applied.
     */
    public function is_applied(): bool
    {
        return (
            $this->application_status === 'applied'
            && $this->applied_by > 0
            && trim($this->applied_at) !== ''
        );
    }

    /**
     * Return portable application evidence.
     */
    public function to_array(): array
    {
        return [
            'source_provider' =>
                $this->source_provider,

            'source_transaction_id' =>
                $this->source_transaction_id,

            'member_id' =>
                $this->member_id,

            'approval_decision_id' =>
                $this->approval_decision_id,

            'payment_date' =>
                $this->payment_date,

            'payment_amount' =>
                $this->payment_amount,

            'previous_renewal_date' =>
                $this->previous_renewal_date,

            'applied_renewal_date' =>
                $this->applied_renewal_date,

            'previous_expiration' =>
                $this->previous_expiration,

            'applied_expiration' =>
                $this->applied_expiration,

            'application_status' =>
                $this->application_status,

            'applied_by' =>
                $this->applied_by,

            'applied_at' =>
                $this->applied_at,

            'notes' =>
                $this->notes,
        ];
    }

    /**
     * Build a Renewal Application from persisted data.
     */
    public static function from_array(
        array $data
    ): SOF_MembershipRenewalApplication {

        $application =
            new SOF_MembershipRenewalApplication();

        $application->source_provider =
            trim(
                (string)(
                    $data['source_provider']
                    ?? ''
                )
            );

        $application->source_transaction_id =
            trim(
                (string)(
                    $data['source_transaction_id']
                    ?? ''
                )
            );

        $application->member_id =
            (int)(
                $data['member_id']
                ?? 0
            );

        $application->approval_decision_id =
            (int)(
                $data['approval_decision_id']
                ?? 0
            );

        $application->payment_date =
            trim(
                (string)(
                    $data['payment_date']
                    ?? ''
                )
            );

        $application->payment_amount =
            (float)(
                $data['payment_amount']
                ?? 0
            );

        $application->previous_renewal_date =
            trim(
                (string)(
                    $data['previous_renewal_date']
                    ?? ''
                )
            );

        $application->applied_renewal_date =
            trim(
                (string)(
                    $data['applied_renewal_date']
                    ?? ''
                )
            );

        $application->previous_expiration =
            trim(
                (string)(
                    $data['previous_expiration']
                    ?? ''
                )
            );

        $application->applied_expiration =
            trim(
                (string)(
                    $data['applied_expiration']
                    ?? ''
                )
            );

        $application->application_status =
            trim(
                (string)(
                    $data['application_status']
                    ?? 'pending'
                )
            );

        $application->applied_by =
            (int)(
                $data['applied_by']
                ?? 0
            );

        $application->applied_at =
            trim(
                (string)(
                    $data['applied_at']
                    ?? ''
                )
            );

        $application->notes =
            trim(
                (string)(
                    $data['notes']
                    ?? ''
                )
            );

        return $application;
    }
}