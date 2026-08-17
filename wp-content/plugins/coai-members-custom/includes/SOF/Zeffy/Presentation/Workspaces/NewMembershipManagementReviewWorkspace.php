<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy New Membership Management Review Workspace
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Layer:
 *     Presentation
 *
 * Purpose:
 *     Present New Membership transactions requiring management
 *     attention.
 *
 * Responsibilities:
 *     - Present existing members who used New Membership
 *     - Present Zeffy payment evidence
 *     - Present existing member evidence
 *     - Explain SOF's assessment
 *     - Recommend the appropriate next business path
 *
 * Does NOT:
 *     - Convert transactions to Renewals
 *     - Update member records
 *     - Change membership expiration
 *     - Make management decisions
 *
 * ============================================================
 */

class SOF_ZeffyNewMembershipManagementReviewWorkspace
{
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        if (
            !class_exists(
                'SOF_ZeffyNewMembershipReviewService'
            )
            || !class_exists(
                'SOF_MembershipManagementDecisionService'
            )
        ) {
            return '<p>New Membership Management Review is not available.</p>';
        }

        $message = '';

        /*
         * -------------------------------------------------
         * Human Management Decision
         * -------------------------------------------------
         *
         * This records the human business conclusion only.
         *
         * It does NOT:
         *     - Update the member
         *     - Change membership expiration
         *     - Change membership status
         *     - Modify the Zeffy transaction
         * -------------------------------------------------
         */

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset(
                $_POST[
                    'sof_new_membership_management_decision'
                ]
            )
        ) {
            check_admin_referer(
                'sof_new_membership_management_decision'
            );

            $source_transaction_id =
                sanitize_text_field(
                    wp_unslash(
                        $_POST[
                            'source_transaction_id'
                        ]
                        ?? ''
                    )
                );

            $member_id =
                absint(
                    $_POST[
                        'member_id'
                    ]
                    ?? 0
                );

            $decision =
                sanitize_key(
                    wp_unslash(
                        $_POST[
                            'decision'
                        ]
                        ?? ''
                    )
                );

            if (
                $source_transaction_id !== ''
                && $member_id > 0
                && $decision !== ''
            ) {
                $decision_service =
                    new SOF_MembershipManagementDecisionService();

                $notes = '';

                if (
                    $decision ===
                    SOF_MembershipManagementDecisionService::
                        DECISION_ALREADY_REFLECTED
                ) {
                    $notes =
                        'Management confirmed that this payment '
                        . 'is already reflected in the member '
                        . 'record.';
                }

                if (
                    $decision ===
                    SOF_MembershipManagementDecisionService::
                        DECISION_FURTHER_REVIEW
                ) {
                    $notes =
                        'Management determined that additional '
                        . 'review is required before a Membership '
                        . 'conclusion can be established.';
                }

                if (
                    $decision ===
                    SOF_MembershipManagementDecisionService::
                        DECISION_PROCESS_AS_RENEWAL
                ) {
                    $notes =
                        'Management determined that this payment '
                        . 'should continue through the Membership '
                        . 'Renewal business process.';
                }

                $saved =
                    $decision_service->decide(
                        'zeffy',
                        $source_transaction_id,
                        'new_membership',
                        $member_id,
                        $decision,
                        get_current_user_id(),
                        $notes
                    );

                if ($saved) {
                    $message =
                        'Management decision recorded. '
                        . 'No membership record was changed.';
                } else {
                    $message =
                        'The management decision could not '
                        . 'be recorded.';
                }
            }
        }

        /*
         * -------------------------------------------------
         * Membership Renewal Management Decision
         * -------------------------------------------------
         *
         * This is a second-stage Membership decision.
         *
         * The original New Membership conclusion remains
         * preserved as:
         *
         *     new_membership / process_as_renewal
         *
         * This decision is recorded separately under:
         *
         *     renewal
         *
         * No Membership record is changed here.
         * -------------------------------------------------
         */

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset(
                $_POST[
                    'sof_membership_renewal_management_decision'
                ]
            )
        ) {
            check_admin_referer(
                'sof_membership_renewal_management_decision'
            );

            $renewal_source_transaction_id =
                sanitize_text_field(
                    wp_unslash(
                        $_POST[
                            'source_transaction_id'
                        ]
                        ?? ''
                    )
                );

            $renewal_member_id =
                absint(
                    $_POST[
                        'member_id'
                    ]
                    ?? 0
                );

            $renewal_decision =
                sanitize_key(
                    wp_unslash(
                        $_POST[
                            'renewal_decision'
                        ]
                        ?? ''
                    )
                );

            if (
                $renewal_source_transaction_id !== ''
                && $renewal_member_id > 0
                && $renewal_decision !== ''
            ) {
                $renewal_decision_service =
                    new SOF_MembershipManagementDecisionService();

                $renewal_notes = '';

                if (
                    $renewal_decision ===
                    SOF_MembershipManagementDecisionService::
                        DECISION_ALREADY_REFLECTED
                ) {
                    $renewal_notes =
                        'Management confirmed that this Renewal '
                        . 'payment is already reflected in the '
                        . 'member record.';
                }

                if (
                    $renewal_decision ===
                    SOF_MembershipManagementDecisionService::
                        DECISION_APPROVE_RENEWAL
                ) {
                    $renewal_notes =
                        'Management approved this Membership '
                        . 'Renewal for future application. '
                        . 'No Membership record was changed by '
                        . 'this decision.';
                }

                if (
                    $renewal_decision ===
                    SOF_MembershipManagementDecisionService::
                        DECISION_FURTHER_REVIEW
                ) {
                    $renewal_notes =
                        'Management determined that additional '
                        . 'Renewal review is required before a '
                        . 'final Membership conclusion can be '
                        . 'established.';
                }

                $renewal_saved =
                    $renewal_decision_service->decide(
                        'zeffy',
                        $renewal_source_transaction_id,
                        'renewal',
                        $renewal_member_id,
                        $renewal_decision,
                        get_current_user_id(),
                        $renewal_notes
                    );

                if ($renewal_saved) {
                    $message =
                        'Membership Renewal decision recorded. '
                        . 'No membership record was changed.';
                } else {
                    $message =
                        'The Membership Renewal decision could '
                        . 'not be recorded.';
                }
            }
        }

        /*
         * -------------------------------------------------
         * Prepare Membership Renewal Application
         * -------------------------------------------------
         *
         * This creates the controlled pending application
         * record only.
         *
         * It does NOT:
         *     - Update the member
         *     - Change membership expiration
         *     - Change membership status
         *     - Modify the Zeffy transaction
         * -------------------------------------------------
         */

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset(
                $_POST[
                    'sof_prepare_membership_renewal_application'
                ]
            )
        ) {
            check_admin_referer(
                'sof_prepare_membership_renewal_application'
            );

            $application_source_provider =
                sanitize_key(
                    wp_unslash(
                        $_POST[
                            'source_provider'
                        ]
                        ?? ''
                    )
                );

            $application_source_transaction_id =
                sanitize_text_field(
                    wp_unslash(
                        $_POST[
                            'source_transaction_id'
                        ]
                        ?? ''
                    )
                );

            $application_member_id =
                absint(
                    $_POST[
                        'member_id'
                    ]
                    ?? 0
                );

            if (
                $application_source_provider !== ''
                && $application_source_transaction_id !== ''
                && $application_member_id > 0
                && class_exists(
                    'SOF_MembershipRenewalApplicationService'
                )
                && class_exists(
                    'SOF_MembershipManagementDecisionService'
                )
            ) {
                /*
                 * Rebuild the candidate from authoritative
                 * source evidence rather than trusting POST
                 * data for payment or expiration facts.
                 */

                $application_candidate =
                    null;

                if (
                    class_exists(
                        'SOF_ZeffyTransactionRepository'
                    )
                    && class_exists(
                        'SOF_ZeffyMembershipRenewalCandidateAdapter'
                    )
                ) {
                    $application_transaction_repository =
                        new SOF_ZeffyTransactionRepository();

                    $application_transactions =
                        $application_transaction_repository
                            ->find_matched_by_payment_ids(
                                [
                                    $application_source_transaction_id,
                                ],
                                1
                            );

                    $application_transaction =
                        !empty($application_transactions)
                            ? reset($application_transactions)
                            : null;

                    if ($application_transaction) {
                        $application_candidate_adapter =
                            new SOF_ZeffyMembershipRenewalCandidateAdapter();

                        $application_candidate_result =
                            $application_candidate_adapter->assess(
                                $application_transaction
                            );

                        $application_candidate =
                            $application_candidate_result[
                                'candidate'
                            ]
                            ?? null;
                    }
                }

                $application_decision_service =
                    new SOF_MembershipManagementDecisionService();

                $application_decision =
                    $application_decision_service->find(
                        $application_source_provider,
                        $application_source_transaction_id,
                        'renewal'
                    );

                if (
                    $application_candidate
                    instanceof
                    SOF_MembershipRenewalCandidate
                    && (int)$application_candidate->member_id ===
                        $application_member_id
                    && $application_decision
                ) {
                    $application_service =
                        new SOF_MembershipRenewalApplicationService();

                    $application_result =
                        $application_service->prepare(
                            $application_candidate,
                            $application_decision
                        );

                    $message =
                        (string)(
                            $application_result['message']
                            ?? 'Membership Renewal Application '
                                . 'preparation completed.'
                        );
                } else {
                    $message =
                        'SOF could not reconstruct the approved '
                        . 'Membership Renewal from the current '
                        . 'source evidence. No Membership record '
                        . 'was changed.';
                }
            } else {
                $message =
                    'The Membership Renewal Application could '
                    . 'not be prepared. No Membership record '
                    . 'was changed.';
            }
        }

        /*
         * -------------------------------------------------
         * Execute Membership Renewal Application
         * -------------------------------------------------
         *
         * This is the controlled Membership write boundary.
         *
         * The browser supplies identity only.
         *
         * The Execution Service is responsible for:
         *     - Reloading the pending Application
         *     - Reloading current Membership evidence
         *     - Re-validating the prepared values
         *     - Applying the Renewal
         *     - Verifying the resulting Membership state
         *     - Recording the final Application state
         *
         * No Membership business values are trusted from POST.
         * -------------------------------------------------
         */

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset(
                $_POST[
                    'sof_execute_membership_renewal_application'
                ]
            )
        ) {
            check_admin_referer(
                'sof_execute_membership_renewal_application'
            );

            $execution_source_provider =
                sanitize_key(
                    wp_unslash(
                        $_POST[
                            'source_provider'
                        ]
                        ?? ''
                    )
                );

            $execution_source_transaction_id =
                sanitize_text_field(
                    wp_unslash(
                        $_POST[
                            'source_transaction_id'
                        ]
                        ?? ''
                    )
                );

            $execution_member_id =
                absint(
                    $_POST[
                        'member_id'
                    ]
                    ?? 0
                );

            if (
                $execution_source_provider !== ''
                && $execution_source_transaction_id !== ''
                && $execution_member_id > 0
                && class_exists(
                    'SOF_MembershipRenewalApplicationExecutionService'
                )
                && class_exists(
                    'SOF_MembershipManagementDecisionService'
                )
                && class_exists(
                    'SOF_ZeffyTransactionRepository'
                )
                && class_exists(
                    'SOF_ZeffyMembershipRenewalCandidateAdapter'
                )
            ) {
                /*
                 * Reconstruct the Renewal Candidate from
                 * authoritative source evidence.
                 */

                $execution_candidate =
                    null;

                $execution_transaction_repository =
                    new SOF_ZeffyTransactionRepository();

                $execution_transactions =
                    $execution_transaction_repository
                        ->find_matched_by_payment_ids(
                            [
                                $execution_source_transaction_id,
                            ],
                            1
                        );

                $execution_transaction =
                    !empty($execution_transactions)
                        ? reset($execution_transactions)
                        : null;

                if ($execution_transaction) {
                    $execution_candidate_adapter =
                        new SOF_ZeffyMembershipRenewalCandidateAdapter();

                    $execution_candidate_result =
                        $execution_candidate_adapter->assess(
                            $execution_transaction
                        );

                    $execution_candidate =
                        $execution_candidate_result[
                            'candidate'
                        ]
                        ?? null;
                }

                /*
                 * Re-read the authoritative Renewal approval.
                 */

                $execution_decision_service =
                    new SOF_MembershipManagementDecisionService();

                $execution_decision =
                    $execution_decision_service->find(
                        $execution_source_provider,
                        $execution_source_transaction_id,
                        'renewal'
                    );

                if (
                    $execution_candidate
                    instanceof
                    SOF_MembershipRenewalCandidate
                    && (int)$execution_candidate->member_id ===
                        $execution_member_id
                    && $execution_decision
                ) {
                    $execution_service =
                        new SOF_MembershipRenewalApplicationExecutionService();

                    $execution_result =
                        $execution_service->execute(
                            $execution_candidate,
                            $execution_decision
                        );

                    $message =
                        (string)(
                            $execution_result['message']
                            ?? 'Membership Renewal Application '
                                . 'execution completed.'
                        );
                } else {
                    $message =
                        'SOF could not reconstruct the approved '
                        . 'Membership Renewal from the current '
                        . 'source evidence. No Membership record '
                        . 'was changed.';
                }
            } else {
                $message =
                    'The Membership Renewal Application could '
                    . 'not be executed.';
            }
        }

        $review_service =
            new SOF_ZeffyNewMembershipReviewService();

        $review =
            $review_service->review(500);

        $attention =
            (array)(
                $review['attention']
                ?? []
            );

        /*
         * -------------------------------------------------
         * Renewal Candidate Validation
         * -------------------------------------------------
         *
         * Retrieve New Membership source transactions that
         * management has explicitly classified:
         *
         *     process_as_renewal
         *
         * Build and assess provider-independent Membership
         * Renewal Candidates.
         *
         * READ ONLY:
         *     - No Membership update
         *     - No Zeffy update
         *     - No Renewal application
         * -------------------------------------------------
         */

        $renewal_candidate_validations = [];

        if (
            class_exists(
                'SOF_MembershipManagementDecisionService'
            )
            && class_exists(
                'SOF_ZeffyTransactionRepository'
            )
            && class_exists(
                'SOF_ZeffyMembershipRenewalCandidateAdapter'
            )
            && class_exists(
                'SOF_MembershipRenewalManagementReviewService'
            )
        ) {
            $candidate_decision_service =
                new SOF_MembershipManagementDecisionService();

            $candidate_decisions =
                $candidate_decision_service->find_by_decision(
                    'zeffy',
                    'new_membership',
                    SOF_MembershipManagementDecisionService::
                        DECISION_PROCESS_AS_RENEWAL,
                    500
                );

            $payment_ids = [];

            foreach ($candidate_decisions as $candidate_decision) {

                $payment_id =
                    trim(
                        (string)(
                            $candidate_decision
                                ->source_transaction_id
                            ?? ''
                        )
                    );

                if ($payment_id !== '') {
                    $payment_ids[] =
                        $payment_id;
                }
            }

            if (!empty($payment_ids)) {

                $candidate_transaction_repository =
                    new SOF_ZeffyTransactionRepository();

                $candidate_transactions =
                    $candidate_transaction_repository
                        ->find_matched_by_payment_ids(
                            $payment_ids,
                            500
                        );

                $candidate_adapter =
                    new SOF_ZeffyMembershipRenewalCandidateAdapter();

                foreach (
                    $candidate_transactions
                    as $candidate_transaction
                ) {
                    $candidate_assessment =
                        $candidate_adapter->assess(
                            $candidate_transaction
                        );

                    $candidate =
                        $candidate_assessment[
                            'candidate'
                        ]
                        ?? null;

                    $management_review = [];

                    if (
                        $candidate
                        instanceof
                        SOF_MembershipRenewalCandidate
                    ) {
                        $management_review_service =
                            new SOF_MembershipRenewalManagementReviewService();

                        $management_review =
                            $management_review_service->review(
                                $candidate
                            );
                    }

                    $renewal_candidate_validations[] = [
                        'transaction' =>
                            $candidate_transaction,

                        'result' =>
                            $candidate_assessment,

                        'management_review' =>
                            $management_review,
                    ];
                }
            }
        }

        /*
         * -------------------------------------------------
         * Approved Renewal Application Queue
         * -------------------------------------------------
         *
         * Identify Renewal Candidates that Management has
         * approved for Renewal application.
         *
         * The Membership Renewal Application Queue Service
         * re-assesses the current Membership facts before
         * considering the Renewal ready for application.
         *
         * READ ONLY:
         *     - No Membership update
         *     - No expiration change
         *     - No payment update
         *     - No Renewal application
         * -------------------------------------------------
         */

        $renewal_application_queue = [];

        if (
            class_exists(
                'SOF_MembershipRenewalApplicationQueueService'
            )
            && class_exists(
                'SOF_MembershipManagementDecisionService'
            )
        ) {
            $application_queue_service =
                new SOF_MembershipRenewalApplicationQueueService();

            $application_decision_service =
                new SOF_MembershipManagementDecisionService();

            foreach (
                $renewal_candidate_validations
                as $queue_validation
            ) {
                $queue_result =
                    (array)(
                        $queue_validation['result']
                        ?? []
                    );

                $queue_candidate =
                    $queue_result['candidate']
                    ?? null;

                if (
                    !(
                        $queue_candidate
                        instanceof
                        SOF_MembershipRenewalCandidate
                    )
                ) {
                    continue;
                }

                $queue_decision =
                    $application_decision_service->find(
                        $queue_candidate->source_provider,
                        $queue_candidate->source_transaction_id,
                        'renewal'
                    );

                if (
                    !$queue_decision
                    || $queue_decision->decision !==
                        SOF_MembershipManagementDecisionService::
                            DECISION_APPROVE_RENEWAL
                ) {
                    continue;
                }
                
                /*
                 * ---------------------------------------------
                 * Completed Renewal Applications do not belong
                 * in the active Application queue.
                 *
                 * The Application ledger is authoritative for
                 * whether this approved Renewal has reached its
                 * conclusion.
                 * ---------------------------------------------
                 */

                if (
                    class_exists(
                        'SOF_MembershipRenewalApplicationRepository'
                    )
                ) {
                    $completed_application_repository =
                        new SOF_MembershipRenewalApplicationRepository();

                    $completed_application =
                        $completed_application_repository
                            ->find_by_source(
                                $queue_candidate
                                    ->source_provider,
                                $queue_candidate
                                    ->source_transaction_id
                            );

                    if (
                        $completed_application
                        && trim(
                            (string)$completed_application
                                ->application_status
                        ) === 'applied'
                    ) {
                        continue;
                    }
                }

                $queue_evaluation =
                    $application_queue_service->evaluate(
                        $queue_candidate,
                        $queue_decision
                    );

                $renewal_application_queue[] = [
                    'transaction' =>
                        $queue_validation['transaction']
                        ?? null,

                    'candidate' =>
                        $queue_candidate,

                    'decision' =>
                        $queue_decision,

                    'evaluation' =>
                        $queue_evaluation,
                ];
            }
        }

        ob_start();
        ?>

        <div class="sof-zeffy-new-membership-review">

            <h2>
                New Membership Management Review
            </h2>

            <p>
                Review New Membership registrations where SOF
                identified an existing MyCOAI member.
            </p>

            <?php if ($message !== '') : ?>

                <div
                    class="coai-portal-card"
                    style="
                        margin:1rem 0;
                        padding:1rem;
                        border:1px solid #d1d5db;
                        border-radius:8px;
                        background:#f9fafb;
                    "
                >
                    <?php echo esc_html($message); ?>
                </div>

            <?php endif; ?>

            <div
                class="coai-portal-card"
                style="
                    margin:1rem 0;
                    padding:1.25rem;
                    border:1px solid #e5e7eb;
                    border-radius:12px;
                    background:#fff;
                "
            >

                <h3 style="margin:0 0 .75rem;">
                    Current New Membership Situation
                </h3>

                <div
                    style="
                        display:grid;
                        grid-template-columns:
                            repeat(
                                auto-fit,
                                minmax(220px, 1fr)
                            );
                        gap:.75rem 1.5rem;
                        margin-bottom:1rem;
                    "
                >

                    <div>
                        <strong>
                            Requires Management Attention:
                        </strong>

                        <?php
                        echo esc_html(
                            number_format_i18n(
                                (int)(
                                    $review[
                                        'attention_total'
                                    ]
                                    ?? 0
                                )
                            )
                        );
                        ?>
                    </div>

                    <div>
                        <strong>
                            Possible Already Reflected:
                        </strong>

                        <?php
                        echo esc_html(
                            number_format_i18n(
                                (int)(
                                    $review[
                                        'possible_already_applied'
                                    ]
                                    ?? 0
                                )
                            )
                        );
                        ?>
                    </div>

                    <div>
                        <strong>
                            Possible Membership Renewal:
                        </strong>

                        <?php
                        echo esc_html(
                            number_format_i18n(
                                (int)(
                                    $review[
                                        'possible_renewal'
                                    ]
                                    ?? 0
                                )
                            )
                        );
                        ?>
                    </div>

                    <div>
                        <strong>
                            Membership Review Required:
                        </strong>

                        <?php
                        echo esc_html(
                            number_format_i18n(
                                (int)(
                                    $review[
                                        'existing_member_review'
                                    ]
                                    ?? 0
                                )
                            )
                        );
                        ?>
                    </div>

                </div>

                <p style="margin-bottom:0;">
                    SOF identifies New Membership situations
                    tht may require Management attention
                    before any Membership action is taken.
                </p>

            </div>

            <?php
            if (
                !empty(
                    $renewal_candidate_validations
                )
            ) :
            ?>

                <div
                    class="coai-portal-card"
                    style="
                        margin:1rem 0;
                        padding:1.25rem;
                        border:1px solid #e5e7eb;
                        border-radius:12px;
                        background:#fff;
                    "
                >

                    <h3 style="margin:0 0 .5rem;">
                        Membership Renewal Management Review
                    </h3>

                    <p
                        style="
                            margin:0 0 1rem;
                            color:#374151;
                        "
                    >
                        SOF reviews Membership Renewal
                        situations that require a Management
                        decision before any Membership changes
                        are made. There are currently No Membership
                        Renewals requiring Management review.
                    </p>

                    <?php
                    foreach (
                        $renewal_candidate_validations
                        as $validation
                    ) :
                    ?>

                        <?php
                        $validation_transaction =
                            $validation['transaction']
                            ?? null;

                        $validation_result =
                            (array)(
                                $validation['result']
                                ?? []
                            );

                        $validation_candidate =
                            $validation_result[
                                'candidate'
                            ]
                            ?? null;

                        $validation_assessment =
                            (array)(
                                $validation_result[
                                    'assessment'
                                ]
                                ?? []
                            );
                            
                                                $validation_management_review =
                            (array)(
                                $validation[
                                    'management_review'
                                ]
                                ?? []
                            );

                        $validation_situation_title =
                            trim(
                                (string)(
                                    $validation_management_review[
                                        'situation_title'
                                    ]
                                    ?? ''
                                )
                            );

                        $validation_management_path =
                            trim(
                                (string)(
                                    $validation_management_review[
                                        'recommended_path'
                                    ]
                                    ?? ''
                                )
                            );

                        $validation_available_actions =
                            (array)(
                                $validation_management_review[
                                    'available_actions'
                                ]
                                ?? []
                            );

                        if (
                            !$validation_transaction
                            || !(
                                $validation_candidate
                                instanceof
                                SOF_MembershipRenewalCandidate
                            )
                        ) {
                            continue;
                        }

                        $renewal_management_decision =
                            null;

                        if (
                            class_exists(
                                'SOF_MembershipManagementDecisionService'
                            )
                        ) {
                            $renewal_management_decision_service =
                                new SOF_MembershipManagementDecisionService();

                            $renewal_management_decision =
                                $renewal_management_decision_service->find(
                                    $validation_candidate
                                        ->source_provider,
                                    $validation_candidate
                                        ->source_transaction_id,
                                    'renewal'
                                );
                        }

                        $renewal_current_decision =
                            $renewal_management_decision
                                ? (string)$renewal_management_decision
                                    ->decision
                                : '';

                        /*
                         * -----------------------------------------
                         * Concluded Renewal Management work
                         * -----------------------------------------
                         *
                         * Once Management confirms that the Renewal
                         * payment is already reflected, this case is
                         * no longer active work.
                         *
                         * The decision remains preserved in the
                         * Membership decision ledger.
                         * -----------------------------------------
                         */

                        if (
                            in_array(
                                $renewal_current_decision,
                                [
                                    SOF_MembershipManagementDecisionService::
                                        DECISION_ALREADY_REFLECTED,

                                    SOF_MembershipManagementDecisionService::
                                        DECISION_APPROVE_RENEWAL,
                                ],
                                true
                            )
                        ) {
                            continue;
                        }

                        $validation_member =
                            (
                                isset(
                                    $validation_assessment[
                                        'member'
                                    ]
                                )
                                && is_array(
                                    $validation_assessment[
                                        'member'
                                    ]
                                )
                            )
                                ? $validation_assessment[
                                    'member'
                                ]
                                : [];

                        $validation_member_name =
                            trim(
                                (string)(
                                    $validation_member[
                                        'full_name'
                                    ]
                                    ?? ''
                                )
                            );

                        $validation_current_expiration =
                            trim(
                                (string)(
                                    $validation_assessment[
                                        'current_expiration'
                                    ]
                                    ?? ''
                                )
                            );

                        $validation_standard_expiration =
                            trim(
                                (string)(
                                    $validation_assessment[
                                        'standard_expiration'
                                    ]
                                    ?? ''
                                )
                            );

                        $validation_title =
                            trim(
                                (string)(
                                    $validation_assessment[
                                        'assessment_title'
                                    ]
                                    ?? 'Renewal Candidate'
                                )
                            );

                        $validation_path =
                            trim(
                                (string)(
                                    $validation_assessment[
                                        'recommended_path'
                                    ]
                                    ?? 'Review Renewal'
                                )
                            );

                        $validation_reason =
                            trim(
                                (string)(
                                    $validation_assessment[
                                        'reason'
                                    ]
                                    ?? ''
                                )
                            );
                        ?>

                        <div
                            style="
                                margin-top:1rem;
                                padding:1rem;
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                                background:#f9fafb;
                            "
                        >

                            <h4 style="margin:0 0 .75rem;">
                                <?php
                                echo esc_html(
                                    $validation_member_name !== ''
                                        ? $validation_member_name
                                        : 'Membership Renewal Candidate'
                                );
                                ?>
                            </h4>

                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:
                                        repeat(
                                            auto-fit,
                                            minmax(180px, 1fr)
                                        );
                                    gap:.75rem 1.25rem;
                                "
                            >

                                <div>
                                    <div style="color:#6b7280;">
                                        Source Provider
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            ucfirst(
                                                $validation_candidate
                                                    ->source_provider
                                            )
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Original Process
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $validation_candidate
                                                        ->source_business_process
                                                )
                                            )
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Membership Intent
                                    </div>

                                    <strong>
                                        Renewal
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Intent Source
                                    </div>

                                    <strong>
                                        Management Decision
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Payment Date
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            wp_date(
                                                'm/d/Y',
                                                strtotime(
                                                    $validation_candidate
                                                        ->payment_date
                                                )
                                            )
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Payment Amount
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            '$'
                                            . number_format(
                                                $validation_candidate
                                                    ->payment_amount,
                                                2
                                            )
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Expected Expiration
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $validation_standard_expiration !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $validation_standard_expiration
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Current Expiration
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $validation_current_expiration !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $validation_current_expiration
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                            </div>

                            <div
                                style="
                                    margin-top:1rem;
                                    padding-top:1rem;
                                    border-top:1px solid #d1d5db;
                                "
                            >

                                <strong>
                                    Assessment:
                                </strong>

                                 <?php
                                echo esc_html(
                                    $validation_situation_title !== ''
                                        ? $validation_situation_title
                                        : $validation_title
                                );
                                ?>

                                <?php
                                if (
                                    $validation_reason !== ''
                                ) :
                                ?>

                                    <div
                                        style="
                                            margin-top:.5rem;
                                            color:#374151;
                                            line-height:1.5;
                                        "
                                    >
                                        <?php
                                        echo esc_html(
                                            $validation_reason
                                        );
                                        ?>
                                    </div>

                                <?php endif; ?>

                                <div style="margin-top:.75rem;">
                                    <strong>
                                        Recommended Path:
                                    </strong>

                                    <?php
                                    echo esc_html(
                                        $validation_management_path !== ''
                                            ? $validation_management_path
                                            : $validation_path
                                    );
                                    ?>
                                </div>

                            </div>

                            <?php
                            if (
                                !empty(
                                    $validation_available_actions
                                )
                            ) :
                            ?>

                                <div
                                    style="
                                        margin-top:1rem;
                                        padding-top:1rem;
                                        border-top:1px solid #e5e7eb;
                                    "
                                >

                                    <div
                                        style="
                                            font-weight:600;
                                            margin-bottom:.75rem;
                                        "
                                    >
                                        Available Actions
                                    </div>

                                    <div
                                        style="
                                            display:flex;
                                            gap:.75rem;
                                            flex-wrap:wrap;
                                        "
                                    >

                                        <?php
                                        if (
                                            in_array(
                                                'confirm_already_reflected',
                                                $validation_available_actions,
                                                true
                                            )
                                        ) :
                                        ?>

                                            <form
                                                method="post"
                                                style="margin:0;"
                                            >

                                                <?php
                                                wp_nonce_field(
                                                    'sof_membership_renewal_management_decision'
                                                );
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="source_transaction_id"
                                                    value="<?php
                                                        echo esc_attr(
                                                            $validation_candidate
                                                                ->source_transaction_id
                                                        );
                                                    ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="member_id"
                                                    value="<?php
                                                        echo esc_attr(
                                                            (string)$validation_candidate
                                                                ->member_id
                                                        );
                                                    ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="renewal_decision"
                                                    value="<?php
                                                        echo esc_attr(
                                                            SOF_MembershipManagementDecisionService::
                                                                DECISION_ALREADY_REFLECTED
                                                        );
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="sof_membership_renewal_management_decision"
                                                    value="1"
                                                    class="button button-primary"
                                                >
                                                    Confirm Payment Already Reflected
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                        <?php
                                        if (
                                            in_array(
                                                'approve_renewal',
                                                $validation_available_actions,
                                                true
                                            )
                                        ) :
                                        ?>

                                            <form
                                                method="post"
                                                style="margin:0;"
                                            >

                                                <?php
                                                wp_nonce_field(
                                                    'sof_membership_renewal_management_decision'
                                                );
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="source_transaction_id"
                                                    value="<?php
                                                        echo esc_attr(
                                                            $validation_candidate
                                                                ->source_transaction_id
                                                        );
                                                    ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="member_id"
                                                    value="<?php
                                                        echo esc_attr(
                                                            (string)$validation_candidate
                                                                ->member_id
                                                        );
                                                    ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="renewal_decision"
                                                    value="<?php
                                                        echo esc_attr(
                                                            SOF_MembershipManagementDecisionService::
                                                                DECISION_APPROVE_RENEWAL
                                                        );
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="sof_membership_renewal_management_decision"
                                                    value="1"
                                                    class="button"
                                                >
                                                    Approve Renewal
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                        <?php
                                        if (
                                            in_array(
                                                'further_review',
                                                $validation_available_actions,
                                                true
                                            )
                                        ) :
                                        ?>

                                            <form
                                                method="post"
                                                style="margin:0;"
                                            >

                                                <?php
                                                wp_nonce_field(
                                                    'sof_membership_renewal_management_decision'
                                                );
                                                ?>

                                                <input
                                                    type="hidden"
                                                    name="source_transaction_id"
                                                    value="<?php
                                                        echo esc_attr(
                                                            $validation_candidate
                                                                ->source_transaction_id
                                                        );
                                                    ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="member_id"
                                                    value="<?php
                                                        echo esc_attr(
                                                            (string)$validation_candidate
                                                                ->member_id
                                                        );
                                                    ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="renewal_decision"
                                                    value="<?php
                                                        echo esc_attr(
                                                            SOF_MembershipManagementDecisionService::
                                                                DECISION_FURTHER_REVIEW
                                                        );
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="sof_membership_renewal_management_decision"
                                                    value="1"
                                                    class="button"
                                                >
                                                    Further Review
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                    <div
                                        style="
                                            margin-top:.75rem;
                                            color:#6b7280;
                                            font-size:.95rem;
                                        "
                                    >
                                        Recording a Management decision
                                        does not change Membership or
                                        payment records. Renewal application
                                        occurs through a separate business
                                        process.
                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <div
                class="coai-portal-card"
                style="
                    margin:1rem 0;
                    padding:1.25rem;
                    border:1px solid #e5e7eb;
                    border-radius:12px;
                    background:#fff;
                "
            >

                <h3 style="margin:0 0 .5rem;">
                    Approved Renewals — Ready for Application
                </h3>

                <p
                    style="
                        margin:0 0 1rem;
                        color:#374151;
                    "
                >
                    Renewals shown here have been approved by
                    Management and re-assessed against the member's
                    current Membership information before application.
                </p>

                <?php
                if (
                    empty(
                        $renewal_application_queue
                    )
                ) :
                ?>

                    <div
                        style="
                            padding:1rem;
                            border:1px solid #e5e7eb;
                            border-radius:8px;
                            background:#f9fafb;
                        "
                    >

                        <strong>
                            No Renewals Ready for Application
                        </strong>

                        <div
                            style="
                                margin-top:.5rem;
                                color:#6b7280;
                                line-height:1.5;
                            "
                        >
                            There are currently no approved
                            Membership Renewals ready for application.
                        </div>

                    </div>

                <?php else : ?>

                    <div
                        style="
                            display:grid;
                            grid-template-columns:1fr;
                            gap:1rem;
                        "
                    >

                        <?php
                        foreach (
                            $renewal_application_queue
                            as $queue_item
                        ) :
                        ?>

                            <?php
                            $queue_candidate =
                                $queue_item['candidate']
                                ?? null;

                            $queue_evaluation =
                                (array)(
                                    $queue_item[
                                        'evaluation'
                                    ]
                                    ?? []
                                );

                            if (
                                !(
                                    $queue_candidate
                                    instanceof
                                    SOF_MembershipRenewalCandidate
                                )
                            ) {
                                continue;
                            }

                            /*
                             * -------------------------------------
                             * Prepared Renewal Application
                             * -------------------------------------
                             *
                             * Determine whether this approved
                             * Renewal already has an Application
                             * ledger record.
                             *
                             * Presentation only.
                             * No Membership data is changed here.
                             * -------------------------------------
                             */

                            $queue_application =
                                null;

                            if (
                                class_exists(
                                    'SOF_MembershipRenewalApplicationRepository'
                                )
                            ) {
                                $queue_application_repository =
                                    new SOF_MembershipRenewalApplicationRepository();

                                $queue_application =
                                    $queue_application_repository
                                        ->find_by_source(
                                            $queue_candidate
                                                ->source_provider,
                                            $queue_candidate
                                                ->source_transaction_id
                                        );
                            }

                            $queue_application_status =
                                $queue_application
                                    ? trim(
                                        (string)$queue_application
                                            ->application_status
                                    )
                                    : '';

                            $queue_assessment =
                                (array)(
                                    $queue_evaluation[
                                        'assessment'
                                    ]
                                    ?? []
                                );

                            $queue_member =
                                (
                                    isset(
                                        $queue_assessment[
                                            'member'
                                        ]
                                    )
                                    && is_array(
                                        $queue_assessment[
                                            'member'
                                        ]
                                    )
                                )
                                    ? $queue_assessment[
                                        'member'
                                    ]
                                    : [];

                            $queue_member_name =
                                trim(
                                    (string)(
                                        $queue_member[
                                            'full_name'
                                        ]
                                        ?? ''
                                    )
                                );

                            $queue_current_expiration =
                                trim(
                                    (string)(
                                        $queue_assessment[
                                            'current_expiration'
                                        ]
                                        ?? ''
                                    )
                                );

                            $queue_standard_expiration =
                                trim(
                                    (string)(
                                        $queue_assessment[
                                            'standard_expiration'
                                        ]
                                        ?? ''
                                    )
                                );

                            $queue_title =
                                trim(
                                    (string)(
                                        $queue_evaluation[
                                            'queue_title'
                                        ]
                                        ?? 'Approved Renewal'
                                    )
                                );

                            $queue_path =
                                trim(
                                    (string)(
                                        $queue_evaluation[
                                            'recommended_path'
                                        ]
                                        ?? 'Review Renewal'
                                    )
                                );

                            $queue_reason =
                                trim(
                                    (string)(
                                        $queue_evaluation[
                                            'reason'
                                        ]
                                        ?? ''
                                    )
                                );

                            $queue_ready =
                                !empty(
                                    $queue_evaluation[
                                        'ready_for_application'
                                    ]
                                );
                            ?>

                            <div
                                style="
                                    padding:1rem;
                                    border:1px solid #e5e7eb;
                                    border-radius:8px;
                                    background:#f9fafb;
                                "
                            >

                                <h4 style="margin:0 0 .75rem;">
                                    <?php
                                    echo esc_html(
                                        $queue_member_name !== ''
                                            ? $queue_member_name
                                            : 'Approved Membership Renewal'
                                    );
                                    ?>
                                </h4>

                                <div
                                    style="
                                        display:grid;
                                        grid-template-columns:
                                            repeat(
                                                auto-fit,
                                                minmax(180px, 1fr)
                                            );
                                        gap:.75rem 1.25rem;
                                    "
                                >

                                    <div>
                                        <div style="color:#6b7280;">
                                            Source Provider
                                        </div>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                ucfirst(
                                                    $queue_candidate
                                                        ->source_provider
                                                )
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <div style="color:#6b7280;">
                                            Payment Date
                                        </div>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $queue_candidate
                                                            ->payment_date
                                                    )
                                                )
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <div style="color:#6b7280;">
                                            Payment Amount
                                        </div>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                '$'
                                                . number_format(
                                                    $queue_candidate
                                                        ->payment_amount,
                                                    2
                                                )
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <div style="color:#6b7280;">
                                            Expected Expiration
                                        </div>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                $queue_standard_expiration !== ''
                                                    ? wp_date(
                                                        'm/d/Y',
                                                        strtotime(
                                                            $queue_standard_expiration
                                                        )
                                                    )
                                                    : '—'
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <div style="color:#6b7280;">
                                            Current Expiration
                                        </div>

                                        <strong>
                                            <?php
                                            echo esc_html(
                                                $queue_current_expiration !== ''
                                                    ? wp_date(
                                                        'm/d/Y',
                                                        strtotime(
                                                            $queue_current_expiration
                                                        )
                                                    )
                                                    : '—'
                                            );
                                            ?>
                                        </strong>
                                    </div>

                                </div>

                                <div
                                    style="
                                        margin-top:1rem;
                                        padding-top:1rem;
                                        border-top:1px solid #d1d5db;
                                    "
                                >

                                    <strong>
                                        Status:
                                    </strong>

                                    <?php
                                    echo esc_html(
                                        $queue_application_status === 'pending'
                                            ? 'Application Prepared — Pending Execution'
                                            : $queue_title
                                    );
                                    ?>

                                    <?php if ($queue_reason !== '') : ?>

                                        <div
                                            style="
                                                margin-top:.5rem;
                                                color:#374151;
                                                line-height:1.5;
                                            "
                                        >
                                            <?php
                                            echo esc_html(
                                                $queue_reason
                                            );
                                            ?>
                                        </div>

                                    <?php endif; ?>

                                    <div style="margin-top:.75rem;">
                                        <strong>
                                            Recommended Path:
                                        </strong>

                                        <?php
                                        echo esc_html(
                                            $queue_path
                                        );
                                        ?>
                                    </div>

                                </div>

                                <?php
                                if (
                                    $queue_application_status ===
                                    'pending'
                                ) :
                                ?>

                                    <div
                                        style="
                                            margin-top:1rem;
                                            padding:1rem;
                                            border:1px solid #e5e7eb;
                                            border-radius:8px;
                                            background:#f9fafb;
                                        "
                                    >

                                        <strong>
                                            Application Prepared
                                        </strong>

                                        <div
                                            style="
                                                margin-top:.5rem;
                                                color:#374151;
                                                line-height:1.5;
                                            "
                                        >
                                            SOF has created the pending
                                            Membership Renewal Application
                                            ledger record. The Membership
                                            record has not been changed.
                                        </div>

                                        <div
                                            style="
                                                margin-top:.75rem;
                                                color:#6b7280;
                                                font-size:.95rem;
                                            "
                                        >
                                            This Renewal is waiting for the
                                            controlled Membership application
                                            execution step.
                                        </div>
                                        
                                    <div
                                        style="
                                            margin-top:1rem;
                                            padding-top:1rem;
                                            border-top:1px solid #e5e7eb;
                                        "
                                    >

                                        <div
                                            style="
                                                font-weight:600;
                                                margin-bottom:.75rem;
                                            "
                                        >
                                            Available Action
                                        </div>

                                        <form
                                            method="post"
                                            style="margin:0;"
                                        >

                                            <?php
                                            wp_nonce_field(
                                                'sof_execute_membership_renewal_application'
                                            );
                                            ?>

                                            <input
                                                type="hidden"
                                                name="source_provider"
                                                value="<?php
                                                    echo esc_attr(
                                                        $queue_candidate
                                                            ->source_provider
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="source_transaction_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        $queue_candidate
                                                            ->source_transaction_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="member_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$queue_candidate
                                                            ->member_id
                                                    );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="sof_execute_membership_renewal_application"
                                                value="1"
                                                class="button button-primary"
                                            >
                                                Apply Membership Renewal
                                            </button>

                                        </form>

                                        <div
                                            style="
                                                margin-top:.75rem;
                                                color:#6b7280;
                                                font-size:.95rem;
                                                line-height:1.5;
                                            "
                                        >
                                            Applying this Renewal will
                                            update the member's Renewal
                                            Date and Membership Expiration
                                            after SOF completes its final
                                            safety checks.
                                        </div>

                                    </div>                                    

                                    </div>

                                <?php elseif ($queue_ready) : ?>

                                    <div
                                        style="
                                            margin-top:1rem;
                                            color:#6b7280;
                                            font-size:.95rem;
                                        "
                                    >
                                        Approved and ready for the controlled
                                        Membership Renewal Application
                                        preparation step.
                                        No Membership record has been changed.
                                    </div>

                                    <div
                                        style="
                                            margin-top:1rem;
                                            padding-top:1rem;
                                            border-top:1px solid #e5e7eb;
                                        "
                                    >

                                        <div
                                            style="
                                                font-weight:600;
                                                margin-bottom:.75rem;
                                            "
                                        >
                                            Available Action
                                        </div>

                                        <form
                                            method="post"
                                            style="margin:0;"
                                        >

                                            <?php
                                            wp_nonce_field(
                                                'sof_prepare_membership_renewal_application'
                                            );
                                            ?>

                                            <input
                                                type="hidden"
                                                name="source_provider"
                                                value="<?php
                                                    echo esc_attr(
                                                        $queue_candidate
                                                            ->source_provider
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="source_transaction_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        $queue_candidate
                                                            ->source_transaction_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="member_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$queue_candidate
                                                            ->member_id
                                                    );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="sof_prepare_membership_renewal_application"
                                                value="1"
                                                class="button button-primary"
                                            >
                                                Prepare Application
                                            </button>

                                        </form>

                                        <div
                                            style="
                                                margin-top:.75rem;
                                                color:#6b7280;
                                                font-size:.95rem;
                                            "
                                        >
                                            Preparing the application creates
                                            the pending Renewal Application
                                            ledger record only. It does not
                                            change the member or Membership
                                            expiration.
                                        </div>

                                    </div>

                                <?php else : ?>

                                    <div
                                        style="
                                            margin-top:1rem;
                                            color:#6b7280;
                                            font-size:.95rem;
                                        "
                                    >
                                        Management approval exists, but the
                                        current Membership evidence requires
                                        re-review before application.
                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

            <?php if (empty($attention)) : ?>

                <div
                    class="coai-portal-card"
                    style="
                        margin:1rem 0;
                        padding:1.25rem;
                        border:1px solid #e5e7eb;
                        border-radius:12px;
                        background:#fff;
                    "
                >

                    <h3 style="margin:0 0 .5rem;">
                        No New Membership Exceptions
                    </h3>

                    <p style="margin:0;">
                        SOF did not find any New Membership
                        registrations currently requiring
                        management review.
                    </p>

                </div>

            <?php else : ?>

                <div
                    style="
                        display:grid;
                        grid-template-columns:1fr;
                        gap:1rem;
                        margin-top:1rem;
                    "
                >

                    <?php foreach ($attention as $row) : ?>

                        <?php
                        $transaction =
                            $row['transaction']
                            ?? null;

                        $assessment =
                            (array)(
                                $row['assessment']
                                ?? []
                            );

                        $management_decision =
                            $row[
                                'management_decision'
                            ]
                            ?? null;

                        $member =
                            (
                                isset(
                                    $assessment['member']
                                )
                                && is_array(
                                    $assessment['member']
                                )
                            )
                                ? $assessment['member']
                                : [];

                        if (!$transaction) {
                            continue;
                        }

                        $member_id =
                            (int)(
                                $assessment[
                                    'matched_member_id'
                                ]
                                ?? $transaction
                                    ->matched_member_id
                                ?? 0
                            );

                        $member_name =                        trim(
                                (string)(
                                    $member['full_name']
                                    ?? ''
                                )
                            );

                        if ($member_name === '') {
                            $member_name =
                                trim(
                                    (string)(
                                        $member['first_name']
                                        ?? ''
                                    )
                                    . ' '
                                    . (string)(
                                        $member['last_name']
                                        ?? ''
                                    )
                                );
                        }

                        if ($member_name === '') {
                            $member_name =
                                trim(
                                    (string)(
                                        $transaction
                                            ->buyer_first_name
                                        ?? ''
                                    )
                                    . ' '
                                    . (string)(
                                        $transaction
                                            ->buyer_last_name
                                        ?? ''
                                    )
                                );
                        }

                        $coai_number =
                            trim(
                                (string)(
                                    $member['COAI_number']
                                    ?? ''
                                )
                            );

                        $current_expiration =
                            trim(
                                (string)(
                                    $member[
                                        'membership_expiration'
                                    ]
                                    ?? ''
                                )
                            );

                        $standard_expiration =
                            trim(
                                (string)(
                                    $assessment[
                                        'standard_expiration'
                                    ]
                                    ?? ''
                                )
                            );

                        $assessment_title =
                            trim(
                                (string)(
                                    $assessment[
                                        'assessment_title'
                                    ]
                                    ?? ''
                                )
                            );

                        $recommended_path =
                            trim(
                                (string)(
                                    $assessment[
                                        'recommended_path'
                                    ]
                                    ?? ''
                                )
                            );

                        $reason =
                            trim(
                                (string)(
                                    $assessment['reason']
                                    ?? ''
                                )
                            );
                        ?>

                        <div
                            class="coai-portal-card"
                            style="
                                padding:1.25rem;
                                border:1px solid #e5e7eb;
                                border-radius:12px;
                                background:#fff;
                            "
                        >

                            <h3 style="margin:0 0 .35rem;">
                                <?php
                                echo esc_html(
                                    $assessment_title !== ''
                                        ? $assessment_title
                                        : 'New Membership Requires Review'
                                );
                                ?>
                            </h3>

                            <div
                                style="
                                    margin-bottom:1rem;
                                    color:#6b7280;
                                "
                            >
                                <?php
                                echo esc_html(
                                    $member_name !== ''
                                        ? $member_name
                                        : 'Unknown Member'
                                );
                                ?>

                                <?php if ($coai_number !== '') : ?>

                                    —
                                    COAI #
                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $coai_number
                                        );
                                        ?>
                                    </strong>

                                <?php endif; ?>

                            </div>

                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:
                                        repeat(
                                            auto-fit,
                                            minmax(180px, 1fr)
                                        );
                                    gap:.75rem 1.25rem;
                                    margin-bottom:1rem;
                                "
                            >

                                <div>
                                    <div style="color:#6b7280;">
                                        Zeffy Registration
                                    </div>

                                    <strong>
                                        New Membership
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Payment Date
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            !empty(
                                                $transaction
                                                    ->payment_date
                                            )
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $transaction
                                                            ->payment_date
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Payment Amount
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            '$'
                                            . number_format(
                                                (float)(
                                                    $transaction
                                                        ->payment_amount
                                                    ?? 0
                                                ),
                                                2
                                            )
                                        );
                                        ?>
                                    </strong>
                                </div>
                                
                                <div>
                                    <div style="color:#6b7280;">
                                        Expected Expiration
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $standard_expiration !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $standard_expiration
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Current Expiration
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $current_expiration !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $current_expiration
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                            </div>

                            <div
                                style="
                                    padding:1rem;
                                    background:#f9fafb;
                                    border:1px solid #e5e7eb;
                                    border-radius:8px;
                                "
                            >

                                <div
                                    style="
                                        font-weight:600;
                                        margin-bottom:.35rem;
                                    "
                                >
                                    Assessment
                                </div>

                                <div
                                    style="
                                        color:#374151;
                                        line-height:1.5;
                                    "
                                >
                                    <?php
                                    echo esc_html(
                                        $reason !== ''
                                            ? $reason
                                            : 'No assessment reason is available.'
                                    );
                                    ?>
                                </div>

                                <div
                                    style="
                                        margin-top:.75rem;
                                        padding-top:.75rem;
                                        border-top:1px solid #e5e7eb;
                                        color:#374151;
                                        line-height:1.5;
                                    "
                                >
                                    <strong>
                                        Recommended Path:
                                    </strong>

                                    <?php
                                    echo esc_html(
                                        $recommended_path !== ''
                                            ? $recommended_path
                                            : 'Management Review'
                                    );
                                    ?>
                                </div>

                            </div>

                            <?php
                            $assessment_status =
                                (string)(
                                    $assessment[
                                        'assessment_status'
                                    ]
                                    ?? ''
                                );
                            ?>

                            <?php
                            if (
                                in_array(
                                    $assessment_status,
                                    [
                                        'possible_already_applied',
                                        'possible_renewal',
                                    ],
                                    true
                                )
                                && $member_id > 0
                                && !empty(
                                    $transaction
                                        ->zeffy_payment_id
                                )
                            ) :
                            ?>
                            
                            <?php
                            $current_decision =
                                $management_decision
                                    ? (string)$management_decision->decision
                                    : '';
                            ?>

                            <?php
                            if (
                                $current_decision ===
                                SOF_MembershipManagementDecisionService::
                                    DECISION_FURTHER_REVIEW
                            ) :
                            ?>

                                <div
                                    style="
                                        margin-top:1rem;
                                        padding:1rem;
                                        border:1px solid #e5e7eb;
                                        border-radius:8px;
                                        background:#f9fafb;
                                    "
                                >
                                    <strong>
                                        Management Decision:
                                    </strong>

                                    Further Review

                                    <div
                                        style="
                                            margin-top:.35rem;
                                            color:#6b7280;
                                        "
                                    >
                                        Additional investigation is required.
                                        No membership record has been changed.
                                    </div>
                                </div>

                            <?php endif; ?>

                                <div
                                    style="
                                        margin-top:1rem;
                                        padding-top:1rem;
                                        border-top:1px solid #e5e7eb;
                                    "
                                >

                                    <div
                                        style="
                                            font-weight:600;
                                            margin-bottom:.75rem;
                                        "
                                    >
                                        Available Actions
                                    </div>

                                    <div
                                        style="
                                            display:flex;
                                            gap:.75rem;
                                            flex-wrap:wrap;
                                        "
                                    >

                                        <form
                                            method="post"
                                            style="margin:0;"
                                        >

                                            <?php
                                            wp_nonce_field(
                                                'sof_new_membership_management_decision'
                                            );
                                            ?>

                                            <input
                                                type="hidden"
                                                name="source_transaction_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$transaction
                                                            ->zeffy_payment_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="member_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$member_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="decision"
                                                value="<?php
                                                    echo esc_attr(
                                                        SOF_MembershipManagementDecisionService::
                                                            DECISION_ALREADY_REFLECTED
                                                    );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="sof_new_membership_management_decision"
                                                value="1"
                                                class="button button-primary"
                                            >
                                                Confirm Payment Already Reflected
                                            </button>

                                        </form>
                                        
                                        <form
                                            method="post"
                                            style="margin:0;"
                                        >

                                            <?php
                                            wp_nonce_field(
                                                'sof_new_membership_management_decision'
                                            );
                                            ?>

                                            <input
                                                type="hidden"
                                                name="source_transaction_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$transaction
                                                            ->zeffy_payment_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="member_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$member_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="decision"
                                                value="<?php
                                                    echo esc_attr(
                                                        SOF_MembershipManagementDecisionService::
                                                            DECISION_FURTHER_REVIEW
                                                    );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="sof_new_membership_management_decision"
                                                value="1"
                                                class="button"
                                            >
                                                Further Review
                                            </button>

                                        </form>

                                        <form
                                            method="post"
                                            style="margin:0;"
                                        >

                                            <?php
                                            wp_nonce_field(
                                                'sof_new_membership_management_decision'
                                            );
                                            ?>

                                            <input
                                                type="hidden"
                                                name="source_transaction_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$transaction
                                                            ->zeffy_payment_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="member_id"
                                                value="<?php
                                                    echo esc_attr(
                                                        (string)$member_id
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="decision"
                                                value="<?php
                                                    echo esc_attr(
                                                        SOF_MembershipManagementDecisionService::
                                                            DECISION_PROCESS_AS_RENEWAL
                                                    );
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="sof_new_membership_management_decision"
                                                value="1"
                                                class="button"
                                            >
                                                Process as Renewal
                                            </button>

                                        </form>

                                    </div>

                                    </div>

                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <div style="margin-top:1rem;">

                <a
                    class="button"
                    href="<?php
                        echo esc_url(
                            home_url('/member-portal/')
                        );
                    ?>"
                    style="
                        display:inline-block;
                        padding:.6rem .9rem;
                        border:1px solid #d1d5db;
                        border-radius:8px;
                        text-decoration:none;
                    "
                >
                    Return to Member Portal
                </a>

            </div>

        </div>

        <?php

        return ob_get_clean();
    }
}