<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Management Decision Service
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Service:
 *     Renewal Management Decision
 *
 * Purpose:
 *     Receive and validate human management decisions for
 *     Renewal transactions requiring review.
 *
 * Responsibilities:
 *     - Define valid management decisions
 *     - Validate a requested decision
 *     - Record the decision through the repository
 *     - Preserve the identity of the person making the decision
 *     - Retrieve an existing decision for a transaction
 *
 * Does NOT:
 *     - Update membership records
 *     - Change Renewal dates
 *     - Change expiration dates
 *     - Execute Renewal processing
 *     - Assess Renewal business rules
 *
 * ============================================================
 */

class SOF_ZeffyRenewalManagementDecisionService
{
    public const DECISION_ALREADY_APPLIED =
        'already_applied';

    public const DECISION_NEEDS_PROCESSING =
        'needs_processing';

    public const DECISION_FURTHER_REVIEW =
        'further_review';
        
    public const DECISION_APPLIED =
        'applied';

    protected
        SOF_ZeffyRenewalManagementDecisionRepository
        $repository;

    public function __construct()
    {
        $this->repository =
            new SOF_ZeffyRenewalManagementDecisionRepository();
    }

    /**
     * Return the decisions management is currently
     * permitted to make.
     */
    public function allowed_decisions(): array
    {
        return [
            self::DECISION_ALREADY_APPLIED,
            self::DECISION_NEEDS_PROCESSING,
            self::DECISION_FURTHER_REVIEW,
            self::DECISION_APPLIED,
        ];
    }

    /**
     * Determine whether a supplied decision is valid.
     */
    public function is_allowed(
        string $decision
    ): bool {

        return in_array(
            $decision,
            $this->allowed_decisions(),
            true
        );
    }

    /**
     * Retrieve an existing management decision.
     */
    public function find(
        int $transaction_id
    ): ?SOF_ZeffyRenewalManagementDecision {

        return $this->repository
            ->find_by_transaction_id(
                $transaction_id
            );
    }

    /**
     * Record a management decision.
     *
     * This records business knowledge only.
     * It does not execute membership changes.
     */
    public function decide(
        int $transaction_id,
        string $decision,
        int $decided_by,
        string $notes = ''
    ): bool {

        $decision =
            sanitize_key($decision);

        $notes =
            sanitize_textarea_field($notes);

        if (
            $transaction_id <= 0
            || $decided_by <= 0
            || !$this->is_allowed($decision)
        ) {

            error_log(
                '[SOF Zeffy Decision] Decision rejected before repository. ' .
                'transaction_id=' .
                $transaction_id .
                ' decision=' .
                $decision .
                ' decided_by=' .
                $decided_by
            );

            return false;
        }

        return $this->repository->save(
            $transaction_id,
            $decision,
            $decided_by,
            $notes
        );
    }
}