<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Review Service
 * ============================================================
 *
 * Purpose:
 *     Assemble the current Renewal business-review situation
 *     for management.
 *
 * Responsibilities:
 *     - Retrieve matched Renewal transactions
 *     - Assess the business effect of each Renewal
 *     - Count Renewal assessment outcomes
 *     - Identify transactions requiring management attention
 *     - Provide one reusable Renewal review result
 *
 * Does NOT:
 *     - Update membership records
 *     - Change expiration dates
 *     - Resolve member identity
 *     - Modify Zeffy transactions
 *     - Make management decisions
 *
 * ============================================================
 */

class SOF_ZeffyRenewalReviewService
{
    /**
     * Build the current Renewal review situation.
     */
    public function review(
        int $limit = 500
    ): array {

        $result = [
            'total'                        => 0,
            'ready_to_apply'               => 0,
            'possible_previously_applied'  => 0,
            'management_review'            => 0,
            'cannot_assess'                => 0,
            'needs_processing'             => 0,
            'further_review'               => 0,
            'attention_total'              => 0,
            'results'                      => [],
            'attention'                    => [],
            'processing'                   => [],
            'further_review_results'       => [],
        ];

        if (
            !class_exists('SOF_ZeffyTransactionRepository')
            || !class_exists(
                'SOF_ZeffyRenewalBusinessAssessmentService'
            )
        ) {
            return $result;
        }

        $repository =
            new SOF_ZeffyTransactionRepository();

        $assessment_service =
            new SOF_ZeffyRenewalBusinessAssessmentService();

        $decision_service = null;

        if (
            class_exists(
                'SOF_ZeffyRenewalManagementDecisionService'
            )
        ) {
            $decision_service =
                new SOF_ZeffyRenewalManagementDecisionService();
        }

        $transactions =
            $repository->find_matched_renewals($limit);

        foreach ($transactions as $transaction) {

            $assessment =
                $assessment_service->assess(
                    $transaction
                );

            $status = (string)(
                $assessment['assessment_status']
                ?? 'cannot_assess'
            );

            $result['total']++;

            $decision = null;

            if ($decision_service) {
                $decision =
                    $decision_service->find(
                        (int)$transaction->id
                    );
            }

            $row = [
                'transaction' => $transaction,
                'assessment'  => $assessment,
                'decision'    => $decision,
            ];

            /*
             * ----------------------------------------------------
             * Human management decisions are established
             * business knowledge.
             * ----------------------------------------------------
             */

            if (
                $decision
                && in_array(
                    $decision->decision,
                    [
                        SOF_ZeffyRenewalManagementDecisionService::
                            DECISION_ALREADY_APPLIED,

                        SOF_ZeffyRenewalManagementDecisionService::
                            DECISION_APPLIED,
                    ],
                    true
                )
            ) {
                continue;
            }

            /*
             * Management determined that this Renewal still
             * needs to be processed.
             *
             * Move it out of its assessment queue and into the
             * dedicated processing queue.
             */
            if (
                $decision
                && $decision->decision ===
                    SOF_ZeffyRenewalManagementDecisionService::
                        DECISION_NEEDS_PROCESSING
            ) {
                $result['needs_processing']++;

                $result['processing'][] =
                    $row;

                $result['attention'][] =
                    $row;

                $result['attention_total']++;

                continue;
            }

            /*
             * Management determined that additional human
             * investigation is required.
             *
             * Keep the Renewal unresolved, but move it into
             * its own Further Review queue.
             */
            if (
                $decision
                && $decision->decision ===
                    SOF_ZeffyRenewalManagementDecisionService::
                        DECISION_FURTHER_REVIEW
            ) {
                $result['further_review']++;

                $result['further_review_results'][] =
                    $row;

                $result['attention'][] =
                    $row;

                $result['attention_total']++;

                continue;
            }

            /*
             * ----------------------------------------------------
             * No management decision has replaced the current
             * SOF assessment.
             * ----------------------------------------------------
             */

            if (
                array_key_exists(
                    $status,
                    $result
                )
                && is_int($result[$status])
            ) {
                $result[$status]++;
            } else {
                $status = 'cannot_assess';
                $result['cannot_assess']++;
            }

            $result['results'][] =
                $row;

            if ($status !== 'ready_to_apply') {
                $result['attention'][] =
                    $row;

                $result['attention_total']++;
            }
        }

        return $result;
    }
}