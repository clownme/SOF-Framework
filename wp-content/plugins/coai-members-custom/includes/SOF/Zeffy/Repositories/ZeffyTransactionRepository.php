<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Transaction Repository
 * ============================================================
 *
 * Purpose:
 *     Provide persistence access to Zeffy transactions already
 *     recorded in the SOF transaction ledger.
 *
 * Does NOT:
 *     - Call the Zeffy API
 *     - Determine business meaning
 *     - Match members
 *     - Update memberships
 *
 * ============================================================
 */

class SOF_ZeffyTransactionRepository
{
    protected string $table = 'wp_sof_zeffy_transactions';

    public function find_by_id(int $id): ?SOF_ZeffyTransaction
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE id = %d
                LIMIT 1
                ",
                $id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return SOF_ZeffyTransaction::from_array($row);
    }

    public function find_by_payment_id(
        string $zeffy_payment_id
    ): ?SOF_ZeffyTransaction {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE zeffy_payment_id = %s
                LIMIT 1
                ",
                $zeffy_payment_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return SOF_ZeffyTransaction::from_array($row);
    }

    public function find_unassessed_renewals(
        int $limit = 100
    ): array {
        global $wpdb;

        $limit = max(1, min(500, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE business_process = 'renewal'
                  AND identity_status = 'unassessed'
                ORDER BY payment_date ASC, id ASC
                LIMIT %d
                ",
                $limit
            ),
            ARRAY_A
        );

        $transactions = [];

        foreach ($rows as $row) {
            $transactions[] =
                SOF_ZeffyTransaction::from_array($row);
        }

        return $transactions;
    }

    /**
     * Return recent Renewal transactions for assessment.
     *
     * No member or transaction records are changed.
     */
    public function find_renewals(
        int $limit = 500
    ): array {
        global $wpdb;

        $limit = max(1, min(500, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE business_process = 'renewal'
                ORDER BY payment_date DESC, id DESC
                LIMIT %d
                ",
                $limit
            ),
            ARRAY_A
        );

        $transactions = [];

        foreach ($rows as $row) {
            $transactions[] =
                SOF_ZeffyTransaction::from_array($row);
        }

        return $transactions;
    }

    /**
     * Return succeeded Renewal transactions whose Zeffy rate
     * has not yet been mapped to a COAI membership product.
     *
     * Diagnostic only.
     */
    public function find_unknown_product_renewals(
        int $limit = 100
    ): array {
        global $wpdb;

        $limit = max(1, min(500, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE business_process = 'renewal'
                  AND payment_status = 'succeeded'
                  AND (
                        membership_product IS NULL
                        OR membership_product = ''
                  )
                ORDER BY payment_date DESC, id DESC
                LIMIT %d
                ",
                $limit
            ),
            ARRAY_A
        );

        $transactions = [];

        foreach ($rows as $row) {
            $transactions[] =
                SOF_ZeffyTransaction::from_array($row);
        }

        return $transactions;
    }

    /**
     * Return Renewal transactions ready for identity assessment.
     *
     * These transactions have:
     * - succeeded payment status
     * - a recognized membership product
     *
     * No member or ledger records are changed.
     */
    public function find_identity_ready_renewals(
        int $limit = 500
    ): array {
        global $wpdb;

        $limit = max(1, min(500, $limit));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE business_process = 'renewal'
                  AND payment_status = 'succeeded'
                  AND membership_product IS NOT NULL
                  AND membership_product <> ''
                ORDER BY payment_date DESC, id DESC
                LIMIT %d
                ",
                $limit
            ),
            ARRAY_A
        );

        $transactions = [];

        foreach ($rows as $row) {
            $transactions[] =
                SOF_ZeffyTransaction::from_array($row);
        }

        return $transactions;
    }
    
    /**
     * Return Renewal transactions whose member identity
     * has already been established.
     *
     * These transactions have:
     * - Renewal business process
     * - succeeded payment
     * - recognized membership product
     * - matched identity
     * - matched member ID
     *
     * No records are changed.
     */
    public function find_matched_renewals(
        int $limit = 500
    ): array {

        global $wpdb;

        $limit = max(1, min(500, $limit));

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$this->table}
                    WHERE business_process = 'renewal'
                      AND payment_status = 'succeeded'
                      AND membership_product IS NOT NULL
                      AND membership_product <> ''
                      AND identity_status = 'matched'
                      AND matched_member_id IS NOT NULL
                      AND matched_member_id > 0
                    ORDER BY payment_date DESC, id DESC
                    LIMIT %d
                    ",
                    $limit
                ),
                ARRAY_A
            );

        $transactions = [];

        foreach ($rows as $row) {
            $transactions[] =
                SOF_ZeffyTransaction::from_array($row);
        }

        return $transactions;
    }
    
        /**
     * Record a confident automatic identity match.
     *
     * This does NOT perform membership processing.
     */
    public function record_automatic_identity_match(
        int $transactionId,
        int $memberId,
        string $matchMethod
    ): bool {
        global $wpdb;

        if (
            $transactionId <= 0
            || $memberId <= 0
            || trim($matchMethod) === ''
        ) {
            return false;
        }

        $result = $wpdb->update(
            $this->table,
            [
                'identity_status'      => 'matched',
                'matched_member_id'    => $memberId,
                'match_method'         => trim($matchMethod),
                'identity_resolved_by' => null,
                'assessed_at'          => current_time('mysql'),
            ],
            [
                'id' => $transactionId,
            ]
        );

        return $result !== false;
    }

    /**
     * Record a non-matched automatic identity assessment.
     *
     * Persists SOF's assessment knowledge so transactions that
     * require human attention do not return to "unassessed"
     * after the current request ends.
     *
     * Supported outcomes:
     * - review_required
     * - ambiguous
     * - unresolved
     *
     * This does NOT:
     * - establish a member identity
     * - perform membership processing
     * - overwrite a human identity decision
     */
    public function record_identity_assessment(
        int $transactionId,
        string $identityStatus,
        string $matchMethod = ''
    ): bool {
        global $wpdb;

        $identityStatus =
            trim($identityStatus);

        $matchMethod =
            trim($matchMethod);

        $allowedStatuses = [
            'review_required',
            'ambiguous',
            'unresolved',
        ];

        if (
            $transactionId <= 0
            || !in_array(
                $identityStatus,
                $allowedStatuses,
                true
            )
        ) {
            return false;
        }

        /*
         * Never overwrite an identity already established
         * by a human.
         */
        $existing =
            $this->find_by_id(
                $transactionId
            );

        if (
            $existing
            && $existing->identity_status === 'matched'
            && $existing->match_method === 'human_review'
            && !empty($existing->matched_member_id)
        ) {
            return true;
        }

        $result = $wpdb->update(
            $this->table,
            [
                'identity_status'      =>
                    $identityStatus,

                'matched_member_id'    =>
                    null,

                'match_method'         =>
                    $matchMethod !== ''
                        ? $matchMethod
                        : null,

                'identity_resolved_by' =>
                    null,

                'assessed_at'          =>
                    current_time('mysql'),
            ],
            [
                'id' => $transactionId,
            ]
        );

        return $result !== false;
    }

    /**
     * Record a human identity resolution.
     *
     * This does NOT perform membership processing.
     */
    public function resolve_identity(
        int $transactionId,
        int $memberId,
        int $resolvedBy
    ): bool {
        global $wpdb;

        if (
            $transactionId <= 0
            || $memberId <= 0
            || $resolvedBy <= 0
        ) {
            return false;
        }

        $result = $wpdb->update(
            $this->table,
            [
                'identity_status'      => 'matched',
                'matched_member_id'    => $memberId,
                'match_method'         => 'human_review',
                'identity_resolved_by' => $resolvedBy,
                'assessed_at'          => current_time('mysql'),
            ],
            [
                'id' => $transactionId,
            ]
        );

        return $result !== false;
    }
}