<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Candidate
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Business Model
 *
 * Purpose:
 *     Represent a Membership payment or transaction that has
 *     been established as a candidate for Renewal processing.
 *
 *     The original payment-provider evidence remains unchanged.
 *     This model represents what Membership understands the
 *     transaction may mean to the organization.
 *
 * Responsibilities:
 *     - Identify the source provider
 *     - Identify the source transaction
 *     - Identify the affected member
 *     - Preserve payment evidence needed for Renewal assessment
 *     - Preserve the Membership business intent
 *
 * Does NOT:
 *     - Call a payment provider
 *     - Change source transaction data
 *     - Update a member
 *     - Change Membership expiration
 *     - Apply a Renewal
 *     - Make a management decision
 *
 * ============================================================
 */

class SOF_MembershipRenewalCandidate
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
     * Original source business process.
     *
     * Examples:
     *     renewal
     *     new_membership
     *
     * This preserves what actually happened at the provider.
     */
    public string $source_business_process = '';

    /**
     * MyCOAI member established for this transaction.
     */
    public int $member_id = 0;

    /**
     * Payment date normalized to YYYY-MM-DD when available.
     */
    public string $payment_date = '';

    /**
     * Payment amount.
     */
    public float $payment_amount = 0.0;

    /**
     * Membership product or level when known.
     *
     * Examples:
     *     Individual
     *     Senior
     *     International
     */
    public string $membership_product = '';

    /**
     * Membership business intent established for this candidate.
     *
     * For this model the expected value is:
     *
     *     renewal
     */
    public string $membership_intent = 'renewal';

    /**
     * How Renewal intent was established.
     *
     * Examples:
     *     provider
     *     management_decision
     *     migration
     */
    public string $intent_source = '';

    /**
     * Optional business note explaining the candidate.
     */
    public string $reason = '';

    /**
     * Determine whether the candidate contains the minimum
     * evidence required for Renewal business assessment.
     */
    public function is_valid(): bool
    {
        return (
            trim($this->source_provider) !== ''
            && trim($this->source_transaction_id) !== ''
            && $this->member_id > 0
            && trim($this->payment_date) !== ''
            && $this->payment_amount >= 0
            && $this->membership_intent === 'renewal'
        );
    }

    /**
     * Return the candidate as portable business data.
     *
     * Useful for presentation, diagnostics, adapters,
     * and future non-WordPress implementations.
     */
    public function to_array(): array
    {
        return [
            'source_provider' =>
                $this->source_provider,

            'source_transaction_id' =>
                $this->source_transaction_id,

            'source_business_process' =>
                $this->source_business_process,

            'member_id' =>
                $this->member_id,

            'payment_date' =>
                $this->payment_date,

            'payment_amount' =>
                $this->payment_amount,

            'membership_product' =>
                $this->membership_product,

            'membership_intent' =>
                $this->membership_intent,

            'intent_source' =>
                $this->intent_source,

            'reason' =>
                $this->reason,
        ];
    }
}