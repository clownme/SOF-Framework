<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Transaction
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Purpose:
 *     Represent one Zeffy payment transaction known to SOF.
 *
 * Responsibilities:
 *     - Represent Zeffy payment identity
 *     - Represent the classified business process
 *     - Represent payment state
 *     - Represent buyer identity evidence
 *     - Represent current SOF assessment state
 *
 * Does NOT:
 *     - Query Zeffy
 *     - Query members
 *     - Update membership
 *     - Determine presentation
 *
 * ============================================================
 */

class SOF_ZeffyTransaction
{
    public int $id = 0;

    public string $zeffy_payment_id = '';
    public string $zeffy_campaign_id = '';

    public string $business_process = '';
    public string $payment_status = '';

    public float $payment_amount = 0.00;
    public ?string $payment_date = null;

    public string $buyer_first_name = '';
    public string $buyer_last_name = '';
    public string $buyer_email = '';
    public string $coai_number = '';

    public string $zeffy_rate_id = '';
    public string $membership_product = '';

    public string $identity_status = 'unassessed';
    public ?int $matched_member_id = null;
    public string $match_method = '';

    public string $processing_status = 'discovered';
    public string $processing_message = '';

    public ?string $discovered_at = null;
    public ?string $assessed_at = null;
    public ?string $processed_at = null;

    public static function from_array(array $row): self
    {
        $transaction = new self();

        $transaction->id =
            isset($row['id']) ? (int) $row['id'] : 0;

        $transaction->zeffy_payment_id =
            trim((string)($row['zeffy_payment_id'] ?? ''));

        $transaction->zeffy_campaign_id =
            trim((string)($row['zeffy_campaign_id'] ?? ''));

        $transaction->business_process =
            trim((string)($row['business_process'] ?? ''));

        $transaction->payment_status =
            trim((string)($row['payment_status'] ?? ''));

        $transaction->payment_amount =
            isset($row['payment_amount'])
                ? (float) $row['payment_amount']
                : 0.00;

        $transaction->payment_date =
            !empty($row['payment_date'])
                ? (string) $row['payment_date']
                : null;

        $transaction->buyer_first_name =
            trim((string)($row['buyer_first_name'] ?? ''));

        $transaction->buyer_last_name =
            trim((string)($row['buyer_last_name'] ?? ''));

        $transaction->buyer_email =
            trim((string)($row['buyer_email'] ?? ''));

        $transaction->coai_number =
            trim((string)($row['coai_number'] ?? ''));

        $transaction->zeffy_rate_id =
            trim((string)($row['zeffy_rate_id'] ?? ''));

        $transaction->membership_product =
            trim((string)($row['membership_product'] ?? ''));

        $transaction->identity_status =
            trim((string)($row['identity_status'] ?? 'unassessed'));

        $transaction->matched_member_id =
            !empty($row['matched_member_id'])
                ? (int) $row['matched_member_id']
                : null;

        $transaction->match_method =
            trim((string)($row['match_method'] ?? ''));

        $transaction->processing_status =
            trim((string)($row['processing_status'] ?? 'discovered'));

        $transaction->processing_message =
            trim((string)($row['processing_message'] ?? ''));

        $transaction->discovered_at =
            !empty($row['discovered_at'])
                ? (string) $row['discovered_at']
                : null;

        $transaction->assessed_at =
            !empty($row['assessed_at'])
                ? (string) $row['assessed_at']
                : null;

        $transaction->processed_at =
            !empty($row['processed_at'])
                ? (string) $row['processed_at']
                : null;

        return $transaction;
    }
}