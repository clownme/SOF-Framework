<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Management Decision
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Model:
 *     Renewal Management Decision
 *
 * Purpose:
 *     Represent a human management decision concerning a
 *     Renewal transaction requiring review.
 *
 * Responsibilities:
 *     - Identify the Zeffy transaction reviewed
 *     - Record the management decision
 *     - Record the person making the decision
 *     - Record when the decision was made
 *     - Preserve optional management notes
 *
 * Does NOT:
 *     - Update membership records
 *     - Change Renewal or expiration dates
 *     - Assess Renewal business rules
 *     - Resolve member identity
 *     - Execute a management decision
 *
 * ============================================================
 */

class SOF_ZeffyRenewalManagementDecision
{
    public ?int $id = null;

    public int $transaction_id = 0;

    public string $decision = '';

    public ?int $decided_by = null;

    public ?string $decided_at = null;

    public string $notes = '';

    public function __construct(
        array $data = []
    ) {
        if (isset($data['id'])) {
            $this->id =
                (int)$data['id'];
        }

        if (isset($data['transaction_id'])) {
            $this->transaction_id =
                (int)$data['transaction_id'];
        }

        if (isset($data['decision'])) {
            $this->decision =
                (string)$data['decision'];
        }

        if (
            isset($data['decided_by'])
            && $data['decided_by'] !== null
        ) {
            $this->decided_by =
                (int)$data['decided_by'];
        }

        if (
            isset($data['decided_at'])
            && $data['decided_at'] !== null
        ) {
            $this->decided_at =
                (string)$data['decided_at'];
        }

        if (isset($data['notes'])) {
            $this->notes =
                (string)$data['notes'];
        }
    }
}