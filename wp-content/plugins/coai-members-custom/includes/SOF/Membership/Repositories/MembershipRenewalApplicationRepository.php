<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Membership Renewal Application Repository
 * ============================================================
 *
 * Framework:
 *     Membership
 *
 * Layer:
 *     Persistence
 *
 * Purpose:
 *     Persist and retrieve Membership Renewal Application
 *     records.
 *
 * Responsibilities:
 *     - Find an application by source provider + transaction
 *     - Save a pending application record
 *     - Update application status after execution
 *     - Prevent duplicate application records for the same
 *       source payment
 *
 * Does NOT:
 *     - Call a payment provider
 *     - Decide whether a Renewal should be approved
 *     - Calculate Membership expiration
 *     - Update member records
 *     - Apply a Renewal
 *
 * ============================================================
 */

class SOF_MembershipRenewalApplicationRepository
{
    /**
     * Persistent SOF table.
     */
    protected string $table =
        'wp_sof_membership_renewal_applications';

    /**
     * Find an application by original source payment.
     */
    public function find_by_source(
        string $source_provider,
        string $source_transaction_id
    ): ?SOF_MembershipRenewalApplication {

        global $wpdb;

        $source_provider =
            trim($source_provider);

        $source_transaction_id =
            trim($source_transaction_id);

        if (
            $source_provider === ''
            || $source_transaction_id === ''
        ) {
            return null;
        }

        $row =
            $wpdb->get_row(
                $wpdb->prepare(
                    "
                        SELECT *
                        FROM {$this->table}
                        WHERE source_provider = %s
                          AND source_transaction_id = %s
                        LIMIT 1
                    ",
                    $source_provider,
                    $source_transaction_id
                ),
                ARRAY_A
            );

        if (!$row) {
            return null;
        }

        return
            SOF_MembershipRenewalApplication::from_array(
                $row
            );
    }

    /**
     * Determine whether a source payment already has an
     * application record.
     */
    public function exists_for_source(
        string $source_provider,
        string $source_transaction_id
    ): bool {

        return (
            $this->find_by_source(
                $source_provider,
                $source_transaction_id
            ) !== null
        );
    }

    /**
     * Create one pending Renewal Application record.
     *
     * Duplicate protection is enforced here before INSERT.
     *
     * The database UNIQUE KEY provides a second protection.
     */
    public function create_pending(
        SOF_MembershipRenewalApplication $application
    ): bool {

        global $wpdb;

        if (!$application->is_valid_for_application()) {
            return false;
        }

        if (
            $this->exists_for_source(
                $application->source_provider,
                $application->source_transaction_id
            )
        ) {
            return false;
        }

        $inserted =
            $wpdb->insert(
                $this->table,
                [
                    'source_provider' =>
                        $application->source_provider,

                    'source_transaction_id' =>
                        $application->source_transaction_id,

                    'member_id' =>
                        $application->member_id,

                    'approval_decision_id' =>
                        $application->approval_decision_id,

                    'payment_date' =>
                        $application->payment_date,

                    'payment_amount' =>
                        $application->payment_amount,

                    'previous_renewal_date' =>
                        (
                            $application->previous_renewal_date !== ''
                                ? $application->previous_renewal_date
                                : null
                        ),

                    'applied_renewal_date' =>
                        $application->applied_renewal_date,

                    'previous_expiration' =>
                        (
                            $application->previous_expiration !== ''
                                ? $application->previous_expiration
                                : null
                        ),

                    'applied_expiration' =>
                        $application->applied_expiration,

                    'application_status' =>
                        'pending',

                    'applied_by' =>
                        null,

                    'applied_at' =>
                        null,

                    'notes' =>
                        $application->notes,
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%s',
                    '%f',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                ]
            );

        return (
            $inserted !== false
        );
    }

    /**
     * Mark an existing application as successfully applied.
     */
    public function mark_applied(
        string $source_provider,
        string $source_transaction_id,
        int $applied_by,
        string $applied_at,
        string $notes = ''
    ): bool {

        global $wpdb;

        $updated =
            $wpdb->update(
                $this->table,
                [
                    'application_status' =>
                        'applied',

                    'applied_by' =>
                        $applied_by,

                    'applied_at' =>
                        $applied_at,

                    'notes' =>
                        $notes,
                ],
                [
                    'source_provider' =>
                        $source_provider,

                    'source_transaction_id' =>
                        $source_transaction_id,
                ],
                [
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                ],
                [
                    '%s',
                    '%s',
                ]
            );

        return (
            $updated !== false
        );
    }

    /**
     * Mark an application as requiring renewed Management review.
     *
     * This is not a technical failure.
     *
     * SOF successfully stopped execution because the current
     * business facts no longer support the prepared Application.
     */
    public function mark_requires_review(
        string $source_provider,
        string $source_transaction_id,
        string $notes
    ): bool {

        global $wpdb;

        $updated =
            $wpdb->update(
                $this->table,
                [
                    'application_status' =>
                        'requires_review',

                    'notes' =>
                        $notes,
                ],
                [
                    'source_provider' =>
                        $source_provider,

                    'source_transaction_id' =>
                        $source_transaction_id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%s',
                    '%s',
                ]
            );

        return (
            $updated !== false
        );
    }

    /**
     * Mark an application as failed.
     */
    public function mark_failed(
        string $source_provider,
        string $source_transaction_id,
        string $notes
    ): bool {

        global $wpdb;

        $updated =
            $wpdb->update(
                $this->table,
                [
                    'application_status' =>
                        'failed',

                    'notes' =>
                        $notes,
                ],
                [
                    'source_provider' =>
                        $source_provider,

                    'source_transaction_id' =>
                        $source_transaction_id,
                ],
                [
                    '%s',
                    '%s',
                ],
                [
                    '%s',
                    '%s',
                ]
            );

        return (
            $updated !== false
        );
    }
}