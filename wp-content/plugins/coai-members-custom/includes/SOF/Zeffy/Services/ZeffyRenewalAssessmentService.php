<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Assessment Service
 * ============================================================
 *
 * Purpose:
 *     Determine whether a Zeffy ledger transaction is eligible
 *     to proceed into Renewal identity assessment.
 *
 * Responsibilities:
 *     - Confirm Renewal business process
 *     - Confirm succeeded payment status
 *     - Confirm known membership product
 *     - Explain why a transaction should proceed or wait
 *
 * Does NOT:
 *     - Match members
 *     - Update membership
 *     - Modify the ledger
 *     - Call Zeffy
 *
 * ============================================================
 */

class SOF_ZeffyRenewalAssessmentService
{
    public function assess(
        SOF_ZeffyTransaction $transaction
    ): array {

        if ($transaction->business_process !== 'renewal') {
            return [
                'assessment' => 'not_renewal',
                'ready'      => false,
                'reason'     => 'This Zeffy transaction does not belong to the Renewal business process.',
            ];
        }

        if ($transaction->payment_status !== 'succeeded') {
            return [
                'assessment' => 'payment_not_complete',
                'ready'      => false,
                'reason'     => sprintf(
                    'The Zeffy payment status is %s. Only succeeded payments may proceed to Renewal assessment.',
                    $transaction->payment_status !== ''
                        ? $transaction->payment_status
                        : 'unknown'
                ),
            ];
        }

        if ($transaction->membership_product === '') {
            return [
                'assessment' => 'membership_product_unknown',
                'ready'      => false,
                'reason'     => 'The Zeffy rate has not yet been mapped to a known COAI membership product.',
            ];
        }

        return [
            'assessment' => 'ready_for_identity_assessment',
            'ready'      => true,
            'reason'     => 'This is a succeeded COAI Renewal payment with a recognized membership product.',
        ];
    }
}