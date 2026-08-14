<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Identity Resolution Service
 * ============================================================
 *
 * Purpose:
 *     Receive and record an authorized human decision about
 *     which MyCOAI member owns a Zeffy transaction.
 *
 * Responsibilities:
 *     - Validate transaction
 *     - Validate selected member
 *     - Record the human identity resolution
 *     - Append an audit note to member internal_comments
 *
 * Does NOT:
 *     - Renew membership
 *     - Change expiration
 *     - Change membership status
 *     - Call Zeffy
 *
 * ============================================================
 */

class SOF_ZeffyIdentityResolutionService
{
    protected SOF_ZeffyTransactionRepository $transactions;

    public function __construct()
    {
        $this->transactions =
            new SOF_ZeffyTransactionRepository();
    }

    public function resolve(
        int $transactionId,
        int $memberId
    ): array {

        if ($transactionId <= 0 || $memberId <= 0) {
            return [
                'success' => false,
                'message' => 'A valid transaction and member are required.',
            ];
        }

        $transaction =
            $this->transactions->find_by_id($transactionId);

        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'The Zeffy transaction could not be found.',
            ];
        }

        /*
         * Human identity resolution is currently limited
         * to Renewal transactions.
         */
        if ($transaction->business_process !== 'renewal') {
            return [
                'success' => false,
                'message' =>
                    'This transaction is not part of the Renewal process.',
            ];
        }

        $member = coai_get_member_by_id($memberId);

        if (!$member) {
            return [
                'success' => false,
                'message' =>
                    'The selected MyCOAI member could not be found.',
            ];
        }

        $resolvedBy = get_current_user_id();

        if ($resolvedBy <= 0) {
            return [
                'success' => false,
                'message' =>
                    'The current WordPress user could not be identified.',
            ];
        }

        $saved = $this->transactions->resolve_identity(
            $transactionId,
            $memberId,
            $resolvedBy
        );

        if (!$saved) {
            return [
                'success' => false,
                'message' =>
                    'SOF could not record the identity resolution.',
            ];
        }

        $currentUser = wp_get_current_user();

        $resolvedByName = trim(
            (string)$currentUser->display_name
        );

        if ($resolvedByName === '') {
            $resolvedByName =
                'WordPress user #' . $resolvedBy;
        }

        $buyerName = trim(
            $transaction->buyer_first_name . ' ' .
            $transaction->buyer_last_name
        );

        $note = sprintf(
            '[%s] Zeffy identity review: Renewal payment %s for %s was manually matched to this member by %s.',
            wp_date('Y-m-d'),
            $transaction->zeffy_payment_id,
            $buyerName !== '' ? $buyerName : 'unknown buyer',
            $resolvedByName
        );

        $commentSaved =
            coai_append_member_internal_comment(
                $memberId,
                $note
            );

        if (!$commentSaved) {
            return [
                'success' => false,
                'message' =>
                    'The identity was recorded in the Zeffy ledger, but the member internal comment could not be updated.',
            ];
        }

        return [
            'success' => true,
            'message' => sprintf(
                'Identity resolved: Zeffy payment for %s is now matched to member #%d.',
                $buyerName !== ''
                    ? $buyerName
                    : 'this buyer',
                $memberId
            ),
        ];
    }
}