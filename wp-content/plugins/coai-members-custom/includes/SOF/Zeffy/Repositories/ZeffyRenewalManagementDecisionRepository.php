<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Management Decision Repository
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Purpose:
 *     Persist management decisions for Renewal transactions.
 *
 * Responsibilities:
 *     - Find a decision by Zeffy transaction ID
 *     - Record or replace a management decision
 *     - Preserve who made the decision and when
 *
 * Does NOT:
 *     - Update membership records
 *     - Change expiration dates
 *     - Execute Renewal processing
 *     - Assess Renewal business rules
 *
 * ============================================================
 */

class SOF_ZeffyRenewalManagementDecisionRepository
{
    protected string $table =
        'wp_sof_zeffy_renewal_management_decisions';

    public function find_by_transaction_id(
        int $transaction_id
    ): ?SOF_ZeffyRenewalManagementDecision {

        global $wpdb;

        if ($transaction_id <= 0) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM {$this->table}
                WHERE transaction_id = %d
                LIMIT 1
                ",
                $transaction_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return new SOF_ZeffyRenewalManagementDecision(
            $row
        );
    }

    public function save(
        int $transaction_id,
        string $decision,
        int $decided_by,
        string $notes = ''
    ): bool {

        global $wpdb;

        if (
            $transaction_id <= 0
            || trim($decision) === ''
            || $decided_by <= 0
        ) {
            return false;
        }

        $existing =
            $this->find_by_transaction_id(
                $transaction_id
            );

        $data = [
            'transaction_id' => $transaction_id,
            'decision'       => trim($decision),
            'decided_by'     => $decided_by,
            'decided_at'     => current_time('mysql'),
            'notes'          => trim($notes),
        ];

        if ($existing && $existing->id) {

            $result = $wpdb->update(
                $this->table,
                $data,
                [
                    'id' => (int)$existing->id,
                ]
            );

            if ($result === false) {

                error_log(
                    '[SOF Zeffy Decision] UPDATE failed. ' .
                    'transaction_id=' .
                    $transaction_id .
                    ' decision=' .
                    $decision .
                    ' decided_by=' .
                    $decided_by .
                    ' error=' .
                    $wpdb->last_error
                );

                return false;
            }

            return true;
        }

        $result = $wpdb->insert(
            $this->table,
            $data
        );

        if ($result === false) {

            error_log(
                '[SOF Zeffy Decision] INSERT failed. ' .
                'transaction_id=' .
                $transaction_id .
                ' decision=' .
                $decision .
                ' decided_by=' .
                $decided_by .
                ' error=' .
                $wpdb->last_error
            );

            return false;
        }

        return true;
    }
}