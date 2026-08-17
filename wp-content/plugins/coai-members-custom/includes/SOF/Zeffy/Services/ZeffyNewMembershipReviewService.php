<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy New Membership Review Service
 * ============================================================
 *
 * Purpose:
 *     Assemble the current New Membership business-review
 *     situation for management.
 *
 * Responsibilities:
 *     - Retrieve matched New Membership transactions
 *     - Assess the business meaning of each transaction
 *     - Identify existing members who used New Membership
 *     - Provide one reusable review result
 *
 * Does NOT:
 *     - Update membership records
 *     - Convert transactions to Renewals
 *     - Change expiration dates
 *     - Make management decisions
 *     - Process New Memberships
 *
 * ============================================================
 */

class SOF_ZeffyNewMembershipReviewService
{
    /**
     * Build the current New Membership review situation.
     */
    public function review(
        int $limit = 500
    ): array {

        $result = [
            'total'                    => 0,
            'possible_already_applied' => 0,
            'possible_renewal'         => 0,
            'existing_member_review'   => 0,
            'new_membership'           => 0,
            'cannot_assess'            => 0,
            'attention_total'          => 0,
            'results'                  => [],
            'attention'                => [],
        ];

        if (
            !class_exists(
                'SOF_ZeffyTransactionRepository'
            )
            || !class_exists(
                'SOF_ZeffyNewMembershipBusinessAssessmentService'
            )
        ) {
            return $result;
        }

        $repository =
            new SOF_ZeffyTransactionRepository();

        $assessment_service =
            new SOF_ZeffyNewMembershipBusinessAssessmentService();

        $decision_service =
            class_exists(
                'SOF_MembershipManagementDecisionService'
            )
                ? new SOF_MembershipManagementDecisionService()
                : null;

        $application_repository =
            class_exists(
                'SOF_MembershipRenewalApplicationRepository'
            )
                ? new SOF_MembershipRenewalApplicationRepository()
                : null;

        $transactions =
            $repository->find_matched_new_memberships(
                $limit
            );

        foreach ($transactions as $transaction) {
            
            /*
             * ----------------------------------------------------
             * Concluded Membership Renewal Applications
             *
             * A source transaction that SOF has already applied
             * and verified is historical evidence, not active
             * New Membership review work.
             * ----------------------------------------------------
             */

            if (
                $application_repository
                && !empty(
                    $transaction->zeffy_payment_id
                )
            ) {
                $renewal_application =
                    $application_repository->find_by_source(
                        'zeffy',
                        (string)$transaction->zeffy_payment_id
                    );

                if (
                    $renewal_application
                    && $renewal_application
                        ->application_status === 'applied'
                ) {
                    continue;
                }
            }

            $assessment =
                $assessment_service->assess(
                    $transaction
                );

            $assessment =
                $assessment_service->assess(
                    $transaction
                );

            $status =
                (string)(
                    $assessment['assessment_status']
                    ?? 'cannot_assess'
                );

            $result['total']++;

            if (
                array_key_exists(
                    $status,
                    $result
                )
                && is_int(
                    $result[$status]
                )
            ) {
                $result[$status]++;
            } else {
                $status =
                    'cannot_assess';

                $result['cannot_assess']++;
            }

            /*
             * ----------------------------------------------------
             * Membership Management Decision
             *
             * Zeffy supplies the source transaction.
             * Membership owns the human business conclusion.
             * ----------------------------------------------------
             */

            $management_decision =
                null;

            if (
                $decision_service
                && !empty(
                    $transaction->zeffy_payment_id
                )
            ) {
                $management_decision =
                    $decision_service->find(
                        'zeffy',
                        (string)$transaction->zeffy_payment_id,
                        'new_membership'
                    );
            }

            $row = [
                'transaction' =>
                    $transaction,

                'assessment' =>
                    $assessment,

                'management_decision' =>
                    $management_decision,
            ];

            $result['results'][] =
                $row;

            /*
             * A transaction remains active management work
             * only while no concluding human decision exists.
             *
             * further_review deliberately remains active.
             */
            $decision =
                $management_decision
                    ? (string)$management_decision->decision
                    : '';

            $is_concluded =
                in_array(
                    $decision,
                    [
                        SOF_MembershipManagementDecisionService::
                            DECISION_ALREADY_REFLECTED,

                        SOF_MembershipManagementDecisionService::
                            DECISION_PROCESS_AS_RENEWAL,
                    ],
                    true
                );

            if (
                in_array(
                    $status,
                    [
                        'possible_already_applied',
                        'possible_renewal',
                        'existing_member_review',
                    ],
                    true
                )
                && !$is_concluded
            ) {
                $result['attention'][] =
                    $row;

                $result['attention_total']++;
            }
        }

        return $result;
    }
}