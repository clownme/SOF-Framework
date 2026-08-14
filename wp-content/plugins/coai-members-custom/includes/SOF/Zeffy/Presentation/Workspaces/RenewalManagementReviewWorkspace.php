<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Management Review Workspace
 * ============================================================
 *
 * Framework:
 *     Zeffy
 *
 * Layer:
 *     Presentation
 *
 * Purpose:
 *     Present Renewal transactions requiring management
 *     attention in a clear business workspace.
 *
 * Responsibilities:
 *     - Present the current Renewal review situation
 *     - Separate possible previously applied Renewals from
 *       Renewals requiring a management decision
 *     - Present member and Renewal evidence
 *     - Explain why management attention is required
 *     - Guide management toward the appropriate next action
 *
 * Does NOT:
 *     - Assess Renewal business rules
 *     - Resolve member identity
 *     - Update membership records
 *     - Change expiration dates
 *     - Process Zeffy transactions
 *
 * ============================================================
 */

class SOF_ZeffyRenewalManagementReviewWorkspace
{
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        if (
            !class_exists(
                'SOF_ZeffyRenewalReviewService'
            )
        ) {
            return '<p>Renewal Management Review is not available.</p>';
        }

        $review_service =
            new SOF_ZeffyRenewalReviewService();

        $view = isset($_GET['view'])
            ? sanitize_key(wp_unslash($_GET['view']))
            : '';
            
        $confirm_transaction_id =
            isset($_GET['transaction_id'])
                ? (int)$_GET['transaction_id']
                : 0;

        $decision_message = '';

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['sof_zeffy_renewal_decision'])
        ) {
            $transaction_id =
                isset($_POST['transaction_id'])
                    ? (int)$_POST['transaction_id']
                    : 0;

            $decision =
                isset($_POST['decision'])
                    ? sanitize_key(
                        wp_unslash($_POST['decision'])
                    )
                    : '';

            check_admin_referer(
                'sof_zeffy_renewal_decision_' .
                $transaction_id
            );

            if (
                class_exists(
                    'SOF_ZeffyRenewalManagementDecisionService'
                )
            ) {
                $decision_service =
                    new SOF_ZeffyRenewalManagementDecisionService();

                $saved =
                    $decision_service->decide(
                        $transaction_id,
                        $decision,
                        get_current_user_id()
                    );

                $decision_message =
                    $saved
                        ? 'Management decision recorded.'
                        : 'SOF could not record the management decision.';
            }
        }

        $application_message = '';
        $application_success = false;

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['sof_zeffy_apply_renewal'])
            && isset($_POST['sof_zeffy_confirmation_workspace'])
            && sanitize_key(
                wp_unslash(
                    $_POST['sof_zeffy_confirmation_workspace']
                )
            ) === 'confirm_processing'
        ) {
            $transaction_id =
                isset($_POST['transaction_id'])
                    ? (int)$_POST['transaction_id']
                    : 0;
            check_admin_referer(
                'sof_zeffy_apply_renewal_' .
                $transaction_id
            );

            if (
                $transaction_id > 0
                && class_exists(
                    'SOF_ZeffyTransactionRepository'
                )
                && class_exists(
                    'SOF_ZeffyRenewalApplicationService'
                )
            ) {
                $transaction_repository =
                    new SOF_ZeffyTransactionRepository();

                $application_transaction =
                    $transaction_repository->find_by_id(
                        $transaction_id
                    );

                if ($application_transaction) {
                    $application_service =
                        new SOF_ZeffyRenewalApplicationService();

                    $application_result =
                        $application_service->apply(
                            $application_transaction
                        );

                    $application_success =
                        !empty(
                            $application_result['success']
                        );

                    $application_message =
                        (string)(
                            $application_result['message']
                            ?? ''
                        );
                } else {
                    $application_message =
                        'SOF could not retrieve the Renewal transaction.';
                }
            } else {
                $application_message =
                    'SOF could not prepare this Renewal for application.';
            }
        }

        /*
         * Build the Renewal review only after any management
         * decision or Renewal application from this request
         * has been completed.
         */
        $review =
            $review_service->review(500);

        ob_start();
        ?>
        
        <?php if ($application_message !== '') : ?>

            <div
                class="coai-portal-card"
                style="
                    margin:1rem 0;
                    padding:1rem 1.25rem;
                    border:1px solid #e5e7eb;
                    border-radius:12px;
                    background:#fff;
                "
            >

                <strong>
                    <?php
                    echo esc_html(
                        $application_success
                            ? 'Renewal Applied'
                            : 'Renewal Not Applied'
                    );
                    ?>
                </strong>

                <p style="margin:.5rem 0 0;">
                    <?php
                    echo esc_html(
                        $application_message
                    );
                    ?>
                </p>
                <?php if ($application_success) : ?>

                    <div style="margin-top:1rem;">

                        <a
                            class="button"
                            href="<?php
                                echo esc_url(
                                    add_query_arg(
                                        'view',
                                        'ready_to_apply',
                                        home_url(
                                            '/renewal-management-review/'
                                        )
                                    )
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
                            Return to Ready to Apply
                        </a>

                    </div>

                <?php endif; ?>
            </div>

        <?php endif; ?>

        <div class="sof-zeffy-renewal-review">

            <h2>Renewal Management Review</h2>

            <p>
                Review Renewal transactions that require
                management attention before membership action
                occurs.
            </p>

            <div class="coai-portal-card"
                 style="
                    margin:1rem 0;
                    padding:1.25rem;
                    border:1px solid #e5e7eb;
                    border-radius:12px;
                    background:#fff;
                 ">

                <h3 style="margin:0 0 .75rem;">
                    Current Renewal Situation
                </h3>

                <p>
                    <strong>Requires Management Attention:</strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int)(
                                $review['attention_total']
                                ?? 0
                            )
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Possible Previously Applied:</strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int)(
                                $review[
                                    'possible_previously_applied'
                                ]
                                ?? 0
                            )
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Management Review:</strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int)(
                                $review['management_review']
                                ?? 0
                            )
                        )
                    );
                    ?>
                </p>
                
                <p>
                    <strong>Needs Processing:</strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int)(
                                $review['needs_processing']
                               ?? 0
                            )
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Further Review:</strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int)(
                                $review['further_review']
                                ?? 0
                            )
                        )
                    );
                    ?>
                </p>

                <p>
                    <strong>Ready to Apply:</strong>
                    <?php
                    echo esc_html(
                        number_format_i18n(
                            (int)(
                                $review['ready_to_apply']
                                ?? 0
                            )
                        )
                    );
                    ?>
                </p>
                
                <div
                    style="
                        display:flex;
                        flex-wrap:wrap;
                        gap:.75rem;
                        margin-top:1rem;
                    "
                >

                    <a
                        class="button"
                        href="<?php
                            echo esc_url(
                                add_query_arg(
                                    'view',
                                    'possible_previously_applied',
                                    home_url('/renewal-management-review/')
                                )
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
                        Review Possible Previously Applied
                        (<?php
                            echo esc_html(
                                number_format_i18n(
                                    (int)(
                                        $review['possible_previously_applied']
                                        ?? 0
                                    )
                                )
                            );
                        ?>)
                    </a>

                    <a
                        class="button"
                        href="<?php
                            echo esc_url(
                                add_query_arg(
                                    'view',
                                    'management_review',
                                    home_url('/renewal-management-review/')
                                )
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
                        Review Management Decisions
                        (<?php
                            echo esc_html(
                                number_format_i18n(
                                    (int)(
                                        $review['management_review']
                                        ?? 0
                                    )
                                )
                            );
                        ?>)
                    </a>

                    <a
                        class="button"
                        href="<?php
                            echo esc_url(
                                add_query_arg(
                                    'view',
                                    'ready_to_apply',
                                    home_url('/renewal-management-review/')
                                )
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
                        View Ready to Apply
                        (<?php
                            echo esc_html(
                                number_format_i18n(
                                    (int)(
                                        $review['ready_to_apply']
                                        ?? 0
                                    )
                                )
                            );
                        ?>)
                    </a>
                    
                    <a
                        class="button"
                        href="<?php
                            echo esc_url(
                                add_query_arg(
                                    'view',
                                    'needs_processing',
                                    home_url(
                                        '/renewal-management-review/'
                                    )
                                )
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
                        Needs Processing
                        (<?php
                            echo esc_html(
                                number_format_i18n(
                                    (int)(
                                        $review['needs_processing']
                                        ?? 0
                                    )
                                )
                            );
                        ?>)
                    </a>

                    <a
                        class="button"
                        href="<?php
                            echo esc_url(
                                add_query_arg(
                                    'view',
                                    'further_review',
                                    home_url(
                                        '/renewal-management-review/'
                                    )
                                )
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
                        Further Review
                        (<?php
                            echo esc_html(
                                number_format_i18n(
                                    (int)(
                                        $review['further_review']
                                        ?? 0
                                    )
                                )
                            );
                        ?>)
                    </a>                    

                    <a
                        class="button"
                        href="<?php echo esc_url(home_url('/member-portal/')); ?>"
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
            
        <?php if ($view === 'possible_previously_applied') : ?>

            <?php
            $possible_applied_results = array_filter(
                (array)($review['results'] ?? []),
                function ($row) {
                    return (
                        (string)(
                            $row['assessment']['assessment_status']
                            ?? ''
                        )
                        === 'possible_previously_applied'
                    );
                }
            );
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
                    Possible Previously Applied Renewals
                </h3>

                <p style="margin:0 0 .75rem;color:#374151;">
                    SOF found Renewal transactions where the member's
                    current expiration already matches the standard
                    expiration this payment would establish.
                </p>

                <p style="margin:0 0 1rem;color:#374151;">
                    <strong>
                        <?php
                        echo esc_html(
                            number_format_i18n(
                                count($possible_applied_results)
                            )
                        );
                        ?>
                        Renewal transaction(s) require confirmation.
                    </strong>
                </p>

        <div
            style="
                display:grid;
                grid-template-columns:1fr;
                gap:1rem;
                margin-top:1rem;
            "
        >

            <?php
            foreach (
                $possible_applied_results
                as $row
            ) :
            ?>

                <?php
                $transaction =
                    $row['transaction'] ?? null;

                $assessment =
                    (array)(
                        $row['assessment']
                        ?? []
                    );

                $member =
                    (
                        isset($assessment['member'])
                        && is_array(
                            $assessment['member']
                        )
                    )
                        ? $assessment['member']
                        : [];

                $member_name = trim(
                    (string)(
                        $member['full_name']
                        ?? ''
                    )
                );

                if ($member_name === '') {
                    $member_name = trim(
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

                if (
                    $member_name === ''
                    && $transaction
                ) {
                    $member_name = trim(
                        (string)(
                            $transaction->buyer_first_name
                            ?? ''
                        )
                        . ' '
                        . (string)(
                            $transaction->buyer_last_name
                            ?? ''
                        )
                    );
                }

                $coai_number = trim(
                    (string)(
                        $member['COAI_number']
                        ?? ''
                    )
                );

                $renewal_date = trim(
                    (string)(
                        $assessment['renewal_date']
                        ?? ''
                    )
                );

                $current_expiration = trim(
                    (string)(
                        $assessment[
                            'current_expiration'
                        ]
                        ?? ''
                    )
                );

                $standard_expiration = trim(
                    (string)(
                        $assessment[
                            'standard_expiration'
                        ]
                        ?? ''
                    )
                );

                $membership_product =
                    $transaction
                        ? trim(
                            (string)(
                                $transaction
                                    ->membership_product
                                ?? ''
                            )
                        )
                        : '';

                $reason = trim(
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

                    <h4
                        style="
                            margin:0 0 .25rem;
                            font-size:1.15rem;
                        "
                    >
                        <?php
                        echo esc_html(
                            $member_name !== ''
                                ? $member_name
                                : 'Unknown Member'
                        );
                        ?>
                    </h4>

                    <div
                        style="
                            color:#6b7280;
                            margin-bottom:1rem;
                        "
                    >
                        COAI Number:
                        <strong>
                            <?php
                            echo esc_html(
                                $coai_number !== ''
                                    ? $coai_number
                                    : '—'
                            );
                            ?>
                        </strong>
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
                                Zeffy Renewal Date
                            </div>

                            <strong>
                                <?php
                                echo esc_html(
                                    $renewal_date !== ''
                                        ? wp_date(
                                            'm/d/Y',
                                            strtotime(
                                                $renewal_date
                                            )
                                        )
                                        : '—'
                                );
                                ?>
                            </strong>
                        </div>

                        <div>
                            <div style="color:#6b7280;">
                                Member Renewal Date
                            </div>

                            <strong>
                                <?php
                                $member_renewal_date =
                                    trim(
                                        (string)(
                                            $member['renewal_date']
                                            ?? ''
                                        )
                                    );

                                echo esc_html(
                                    $member_renewal_date !== ''
                                        ? wp_date(
                                            'm/d/Y',
                                            strtotime(
                                                $member_renewal_date
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

                        <div>
                            <div
                                style="
                                    font-size:.85rem;
                                    color:#6b7280;
                                "
                            >
                                Standard Expiration
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
                            <div
                                style="
                                    font-size:.85rem;
                                    color:#6b7280;
                                "
                            >
                                Membership Product
                            </div>

                            <strong>
                                <?php
                                echo esc_html(
                                    $membership_product !== ''
                                        ? $membership_product
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
                            Why SOF Needs Confirmation
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
                            <strong>Recommended Path:</strong>
                            No membership changes have been made.
                            Review this renewal and confirm whether
                            the payment has already been applied
                            before making any changes to the member
                            record.
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
                Available Actions
            </div>

            <div
                style="
                    display:flex;
                    flex-wrap:wrap;
                    gap:.75rem;
                "
            >

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'sof_zeffy_renewal_decision_' .
                        (int)$transaction->id
                    );
                    ?>

                    <input
                        type="hidden"
                        name="sof_zeffy_renewal_decision"
                        value="1"
                    >

                    <input
                        type="hidden"
                        name="transaction_id"
                        value="<?php
                            echo esc_attr(
                                (string)$transaction->id
                            );
                        ?>"
                    >

                    <input
                        type="hidden"
                        name="decision"
                        value="already_applied"
                    >

                    <button
                        type="submit"
                        class="button"
                    >
                        Confirm Already Applied
                    </button>

                </form>

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'sof_zeffy_renewal_decision_' .
                        (int)$transaction->id
                    );
                    ?>

                    <input
                        type="hidden"
                        name="sof_zeffy_renewal_decision"
                        value="1"
                    >

                    <input
                        type="hidden"
                        name="transaction_id"
                        value="<?php
                            echo esc_attr(
                                (string)$transaction->id
                            );
                        ?>"
                    >

                    <input
                        type="hidden"
                        name="decision"
                        value="needs_processing"
                    >

                    <button
                        type="submit"
                        class="button"
                    >
                        Renewal Still Needs Processing
                    </button>

                </form>

                <form method="post">

                    <?php
                    wp_nonce_field(
                        'sof_zeffy_renewal_decision_' .
                        (int)$transaction->id
                    );
                    ?>

                    <input
                        type="hidden"
                        name="sof_zeffy_renewal_decision"
                        value="1"
                    >

                    <input
                        type="hidden"
                        name="transaction_id"
                        value="<?php
                            echo esc_attr(
                                (string)$transaction->id
                            );
                        ?>"
                    >

                    <input
                        type="hidden"
                        name="decision"
                        value="further_review"
                    >

                    <button
                        type="submit"
                        class="button"
                    >
                        Needs Further Review
                    </button>

                </form>

            </div>

        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>
            </div>
            
        <?php elseif ($view === 'needs_processing') : ?>

            <?php
            $processing_results =
                (array)(
                    $review['processing']
                    ?? []
                );
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
                    Renewals Needing Processing
                </h3>

                <p style="margin:0 0 .75rem;color:#374151;">
                    Management has confirmed that these Renewal
                    payments still need to be applied to the
                    member record.
                </p>

                <p style="margin:0;color:#374151;">
                    Review the proposed membership changes below.
                    No membership changes have been made.
                </p>

            </div>

            <div
                style="
                    display:grid;
                    grid-template-columns:1fr;
                    gap:1rem;
                    margin-top:1rem;
                "
            >

                <?php
                foreach (
                    $processing_results
                    as $row
                ) :
                ?>

                    <?php
                    $transaction =
                        $row['transaction'] ?? null;

                    $assessment =
                        (array)(
                            $row['assessment']
                            ?? []
                        );

                    $member =
                        (
                            isset($assessment['member'])
                            && is_array(
                                $assessment['member']
                            )
                        )
                            ? $assessment['member']
                            : [];

                    $member_name = trim(
                        (string)(
                            $member['full_name']
                            ?? ''
                        )
                    );

                    if ($member_name === '') {
                        $member_name = trim(
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

                    if (
                        $member_name === ''
                        && $transaction
                    ) {
                        $member_name = trim(
                            (string)(
                                $transaction->buyer_first_name
                                ?? ''
                            )
                            . ' '
                            . (string)(
                                $transaction->buyer_last_name
                                ?? ''
                            )
                        );
                    }

                    $coai_number = trim(
                        (string)(
                            $member['COAI_number']
                            ?? ''
                        )
                    );

                    $renewal_date = trim(
                        (string)(
                            $assessment['renewal_date']
                            ?? ''
                        )
                    );

                    $current_expiration = trim(
                        (string)(
                            $assessment[
                                'current_expiration'
                            ]
                            ?? ''
                        )
                    );

                    $standard_expiration = trim(
                        (string)(
                            $assessment[
                                'standard_expiration'
                            ]
                            ?? ''
                        )
                    );

                    $membership_product =
                        $transaction
                            ? trim(
                                (string)(
                                    $transaction
                                        ->membership_product
                                    ?? ''
                                )
                            )
                            : '';
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

                        <h4
                            style="
                                margin:0 0 .25rem;
                                font-size:1.15rem;
                            "
                        >
                            <?php
                            echo esc_html(
                                $member_name !== ''
                                    ? $member_name
                                    : 'Unknown Member'
                            );
                            ?>
                        </h4>

                        <div
                            style="
                                color:#6b7280;
                                margin-bottom:1rem;
                            "
                        >
                            COAI Number:
                            <strong>
                                <?php
                                echo esc_html(
                                    $coai_number !== ''
                                        ? $coai_number
                                        : '—'
                                );
                                ?>
                            </strong>
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
                                    Membership Product
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $membership_product !== ''
                                            ? $membership_product
                                            : '—'
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <div style="color:#6b7280;">
                                    Renewal Payment Date
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $renewal_date !== ''
                                            ? wp_date(
                                                'm/d/Y',
                                                strtotime(
                                                    $renewal_date
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
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                                background:#f9fafb;
                                margin-bottom:1rem;
                            "
                        >

                            <h4 style="margin:0 0 .75rem;">
                                Proposed Membership Change
                            </h4>

                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:
                                        repeat(
                                            auto-fit,
                                            minmax(220px, 1fr)
                                        );
                                    gap:1rem;
                                "
                            >

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

                                <div>
                                    <div style="color:#6b7280;">
                                        Proposed Renewal Date
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $renewal_date !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $renewal_date
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Proposed Expiration
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

                            </div>

                        </div>

                        <div
                            style="
                                padding:1rem;
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                            "
                        >
                            <strong>Recommended Path:</strong>
                            Review the proposed Renewal Date and
                            Expiration Date. No membership changes
                            will occur until management explicitly
                            confirms the proposed change.
                        </div>

                        <div
                            style="
                                margin-top:1rem;
                                padding-top:1rem;
                                border-top:1px solid #e5e7eb;
                            "
                        >

                            <strong>Available Actions</strong>

                            <div
                                style="
                                    display:flex;
                                    flex-wrap:wrap;
                                    gap:.75rem;
                                    margin-top:.75rem;
                                "
                            >

                                <a
                                    class="button"
                                    href="<?php
                                        echo esc_url(
                                            add_query_arg(
                                                [
                                                    'view' =>
                                                        'confirm_processing',
                                                    'transaction_id' =>
                                                        (int)$transaction->id,
                                                ],
                                                home_url(
                                                    '/renewal-management-review/'
                                                )
                                            )
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
                                    Review Proposed Change
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>
            
        <?php elseif (
            $view === 'confirm_processing'
            && !$application_success
        ) : ?>

            <?php
            $confirm_row = null;

            /*
             * ----------------------------------------------------
             * A Renewal can reach confirmation through either:
             *
             * 1. Management determined that it Needs Processing
             * 2. SOF assessed it as Ready to Apply
             *
             * Both paths use the same confirmation workspace.
             * ----------------------------------------------------
             */

            $confirmation_rows =
                (array)(
                    $review['processing']
                    ?? []
                );

            foreach (
                (array)(
                    $review['results']
                    ?? []
                )
                as $result_row
            ) {
                $result_assessment =
                    (array)(
                        $result_row['assessment']
                        ?? []
                    );

                if (
                    (string)(
                        $result_assessment[
                            'assessment_status'
                        ]
                        ?? ''
                    )
                    === 'ready_to_apply'
                ) {
                    $confirmation_rows[] =
                        $result_row;
                }
            }

            foreach (
                $confirmation_rows
                as $confirmation_row
            ) {
                $confirmation_transaction =
                    $confirmation_row['transaction']
                    ?? null;

                if (
                    $confirmation_transaction
                    && (int)$confirmation_transaction->id
                        === $confirm_transaction_id
                ) {
                    $confirm_row =
                        $confirmation_row;

                    break;
                }
            }
            ?>

            <?php if (!$confirm_row) : ?>

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
                        Renewal Cannot Be Confirmed
                    </h3>

                    <p style="margin:0 0 1rem;color:#374151;">
                        SOF could not find this Renewal in the
                        current Needs Processing queue.
                    </p>

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        add_query_arg(
                                            'view',
                                            'needs_processing',
                                            home_url(
                                                '/renewal-management-review/'
                                            )
                                        )
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
                                Return to Needs Processing
                            </a>

                </div>

            <?php else : ?>

                <?php
                $transaction =
                    $confirm_row['transaction'];

                $assessment =
                    (array)(
                        $confirm_row['assessment']
                        ?? []
                    );

                $member =
                    (
                        isset($assessment['member'])
                        && is_array(
                            $assessment['member']
                        )
                    )
                        ? $assessment['member']
                        : [];

                $member_name = trim(
                    (string)(
                        $member['full_name']
                        ?? ''
                    )
                );

                if ($member_name === '') {
                    $member_name = trim(
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

                $coai_number = trim(
                    (string)(
                        $member['COAI_number']
                        ?? ''
                    )
                );

                $current_renewal_date = trim(
                    (string)(
                        $member['renewal_date']
                        ?? ''
                    )
                );

                $current_expiration = trim(
                    (string)(
                        $assessment[
                            'current_expiration'
                        ]
                        ?? ''
                    )
                );

                $proposed_renewal_date = trim(
                    (string)(
                        $assessment['renewal_date']
                        ?? ''
                    )
                );

                $proposed_expiration = trim(
                    (string)(
                        $assessment[
                            'standard_expiration'
                        ]
                        ?? ''
                    )
                );

                $renewal_date_changes =
                    $current_renewal_date !==
                    $proposed_renewal_date;

                $expiration_changes =
                    $current_expiration !==
                    $proposed_expiration;
                    
                $membership_change_needed =
                    $renewal_date_changes
                    || $expiration_changes;
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
                        Confirm Membership Change
                    </h3>

                    <p style="margin:0;color:#374151;">
                        Review the current membership information
                        and the exact values SOF proposes to use.
                        No membership changes have been made.
                    </p>

                </div>

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

                    <h4
                        style="
                            margin:0 0 .25rem;
                            font-size:1.15rem;
                        "
                    >
                        <?php
                        echo esc_html(
                            $member_name !== ''
                                ? $member_name
                                : 'Unknown Member'
                        );
                        ?>
                    </h4>

                    <div
                        style="
                            color:#6b7280;
                            margin-bottom:1rem;
                        "
                    >
                        COAI Number:
                        <strong>
                            <?php
                            echo esc_html(
                                $coai_number !== ''
                                    ? $coai_number
                                    : '—'
                            );
                            ?>
                        </strong>
                    </div>

                    <div
                        style="
                            display:grid;
                            grid-template-columns:
                                repeat(
                                    auto-fit,
                                    minmax(220px, 1fr)
                                );
                            gap:1rem;
                        "
                    >

                        <div
                            style="
                                padding:1rem;
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                                background:#f9fafb;
                            "
                        >

                            <h4 style="margin:0 0 .75rem;">
                                Renewal Date
                            </h4>

                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:1fr 1fr;
                                    gap:1rem;
                                "
                            >

                                <div>
                                    <div style="color:#6b7280;">
                                        Current
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $current_renewal_date !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $current_renewal_date
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                                <div>
                                    <div style="color:#6b7280;">
                                        Proposed
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $proposed_renewal_date !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $proposed_renewal_date
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                            </div>

                            <p
                                style="
                                    margin:.75rem 0 0;
                                    color:#374151;
                                "
                            >
                                <?php if ($renewal_date_changes) : ?>

                                    This value will change.

                                <?php else : ?>

                                    This value is already correct
                                    and will remain unchanged.

                                <?php endif; ?>
                            </p>

                        </div>

                        <div
                            style="
                                padding:1rem;
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                                background:#f9fafb;
                            "
                        >

                            <h4 style="margin:0 0 .75rem;">
                                Expiration Date
                            </h4>

                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:1fr 1fr;
                                    gap:1rem;
                                "
                            >

                                <div>
                                    <div style="color:#6b7280;">
                                        Current
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

                                <div>
                                    <div style="color:#6b7280;">
                                        Proposed
                                    </div>

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $proposed_expiration !== ''
                                                ? wp_date(
                                                    'm/d/Y',
                                                    strtotime(
                                                        $proposed_expiration
                                                    )
                                                )
                                                : '—'
                                        );
                                        ?>
                                    </strong>
                                </div>

                            </div>

                            <p
                                style="
                                    margin:.75rem 0 0;
                                    color:#374151;
                                "
                            >
                                <?php if ($expiration_changes) : ?>

                                    This value will change.

                                <?php else : ?>

                                    This value is already correct
                                    and will remain unchanged.

                                <?php endif; ?>
                            </p>

                        </div>

                    </div>

                    <?php if (!$membership_change_needed) : ?>

                        <div
                            style="
                                margin-top:1rem;
                                padding:1rem;
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                                background:#f9fafb;
                            "
                        >

                            <h4 style="margin:0 0 .5rem;">
                                Renewal Already Reflected in Membership
                            </h4>

                            <p
                                style="
                                    margin:0;
                                    color:#374151;
                                    line-height:1.5;
                                "
                            >
                                The member's current Renewal Date
                                and Expiration Date already match
                                the values this Renewal payment
                                would establish.
                            </p>

                            <p
                                style="
                                    margin:.75rem 0 0;
                                    color:#374151;
                                    line-height:1.5;
                                "
                            >
                                <strong>Recommended Path:</strong>
                                Confirm that this Renewal was already
                                applied. No membership changes are
                                necessary.
                            </p>

                        </div>

                    <?php else : ?>

                        <div
                            style="
                                margin-top:1rem;
                                padding:1rem;
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                            "
                        >

                            <strong>Recommended Path:</strong>

                            Confirm that the proposed values accurately
                            reflect this Renewal payment before allowing
                            SOF to update the member record.

                        </div>

                    <?php endif; ?>

                    <div
                        style="
                            margin-top:1rem;
                            padding-top:1rem;
                            border-top:1px solid #e5e7eb;
                        "
                    >

                        <strong>Available Actions</strong>

                        <div
                            style="
                                display:flex;
                                flex-wrap:wrap;
                                gap:.75rem;
                                margin-top:.75rem;
                            "
                        >

                            <?php if (!$membership_change_needed) : ?>

                                <form method="post">

                                    <?php
                                    wp_nonce_field(
                                        'sof_zeffy_renewal_decision_' .
                                        (int)$transaction->id
                                    );
                                    ?>

                                    <input
                                        type="hidden"
                                        name="sof_zeffy_renewal_decision"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="transaction_id"
                                        value="<?php
                                            echo esc_attr(
                                                (string)$transaction->id
                                            );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="decision"
                                        value="already_applied"
                                    >

                                    <button
                                        type="submit"
                                        class="button"
                                    >
                                        Confirm Already Applied
                                    </button>

                                </form>

                            <?php else : ?>

                                <form method="post">

                                    <?php
                                    wp_nonce_field(
                                        'sof_zeffy_apply_renewal_' .
                                        (int)$transaction->id
                                    );
                                    ?>

                                    <input
                                        type="hidden"
                                        name="sof_zeffy_apply_renewal"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="sof_zeffy_confirmation_workspace"
                                        value="confirm_processing"
                                    >

                                    <input
                                        type="hidden"
                                        name="transaction_id"
                                        value="<?php
                                            echo esc_attr(
                                                (string)$transaction->id
                                            );
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="button"
                                    >
                                        Confirm and Apply Renewal
                                    </button>
                                </form>

                            <?php endif; ?>

                            <a
                                class="button"
                                href="<?php
                                    echo esc_url(
                                        add_query_arg(
                                            'view',
                                            'needs_processing',
                                            home_url(
                                                '/renewal-management-review/'
                                            )
                                        )
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
                                Return to Needs Processing
                            </a>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        <?php elseif ($view === 'further_review') : ?>

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
                    Renewals Needing Further Review
                </h3>

                <p style="margin:0 0 .75rem;color:#374151;">
                    Management has determined that these Renewal
                    transactions require additional investigation
                    before a processing decision can be made.
                </p>

                <p style="margin:0;color:#374151;">
                    No membership changes have been made.
                </p>

            </div>

        <?php elseif ($view === 'management_review') : ?>

            <?php
            $management_results =
                array_filter(
                    (array)(
                        $review['results']
                        ?? []
                    ),
                    function ($row) {
                        $assessment =
                            (array)(
                                $row['assessment']
                                ?? []
                            );

                        return (
                            (string)(
                                $assessment['assessment_status']
                                ?? ''
                            )
                            === 'management_review'
                        );
                    }
                );
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
                    Management Review
                </h3>

                <p style="margin:0;color:#374151;">
                    SOF found Renewal transactions where the
                    member's current membership situation requires
                    management review before any Renewal action
                    should occur.
                </p>

                <p
                    style="
                        margin:.75rem 0 0;
                        color:#374151;
                    "
                >
                    Review the available membership evidence and
                    determine the appropriate business action for
                    each Renewal.
                </p>

            </div>

            <div
                style="
                    display:grid;
                    grid-template-columns:1fr;
                    gap:1rem;
                    margin-top:1rem;
                "
            >

                <?php foreach ($management_results as $row) : ?>

                    <?php
                    $transaction =
                        $row['transaction']
                        ?? null;

                    $assessment =
                        (array)(
                            $row['assessment']
                            ?? []
                        );

                    if (!$transaction) {
                        continue;
                    }

                    $member =
                        (
                            isset($assessment['member'])
                            && is_array(
                                $assessment['member']
                            )
                        )
                            ? $assessment['member']
                            : [];

                    $member_name = trim(
                        (string)(
                            $member['full_name']
                            ?? ''
                        )
                    );

                    if ($member_name === '') {
                        $member_name = trim(
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
                        $member_name = trim(
                            (string)(
                                $transaction->buyer_first_name
                                ?? ''
                            )
                            . ' '
                            . (string)(
                                $transaction->buyer_last_name
                                ?? ''
                            )
                        );
                    }

                    $coai_number = trim(
                        (string)(
                            $member['COAI_number']
                            ?? ''
                        )
                    );

                    $renewal_date = trim(
                        (string)(
                            $assessment['renewal_date']
                            ?? ''
                        )
                    );

                    $current_expiration = trim(
                        (string)(
                            $assessment[
                                'current_expiration'
                            ]
                            ?? ''
                        )
                    );

                    $standard_expiration = trim(
                        (string)(
                            $assessment[
                                'standard_expiration'
                            ]
                            ?? ''
                        )
                    );

                    $membership_product = trim(
                        (string)(
                            $transaction->membership_product
                            ?? ''
                        )
                    );

                    $reason = trim(
                        (string)(
                            $assessment['reason']
                            ?? ''
                        )
                    );

                    $recommended_path = trim(
                        (string)(
                            $assessment['recommended_path']
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

                        <h4
                            style="
                                margin:0 0 .25rem;
                                font-size:1.15rem;
                            "
                        >
                            <?php
                            echo esc_html(
                                $member_name !== ''
                                    ? $member_name
                                    : 'Unknown Member'
                            );
                            ?>
                        </h4>

                        <div
                            style="
                                color:#6b7280;
                                margin-bottom:1rem;
                            "
                        >
                            COAI Number:
                            <strong>
                                <?php
                                echo esc_html(
                                    $coai_number !== ''
                                        ? $coai_number
                                        : '—'
                                );
                                ?>
                            </strong>
                        </div>

                        <div
                            style="
                                display:grid;
                                grid-template-columns:
                                    repeat(
                                        auto-fit,
                                        minmax(180px,1fr)
                                    );
                                gap:.75rem 1.25rem;
                            "
                        >

                            <div>
                                <div style="color:#6b7280;">
                                    Zeffy Renewal Date
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $renewal_date !== ''
                                            ? wp_date(
                                                'm/d/Y',
                                                strtotime(
                                                    $renewal_date
                                                )
                                            )
                                            : '—'
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <div style="color:#6b7280;">
                                    Member Renewal Date
                                </div>

                                <strong>
                                    <?php
                                    $member_renewal_date =
                                        trim(
                                            (string)(
                                                $member['renewal_date']
                                                ?? ''
                                            )
                                        );

                                    echo esc_html(
                                        $member_renewal_date !== ''
                                            ? wp_date(
                                                'm/d/Y',
                                                strtotime(
                                                    $member_renewal_date
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

                            <div>
                                <div style="color:#6b7280;">
                                    Standard Expiration
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
                                    Membership Product
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $membership_product !== ''
                                            ? $membership_product
                                            : '—'
                                    );
                                    ?>
                                </strong>
                            </div>

                        </div>

                        <?php if ($reason !== '') : ?>

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
                                    Why SOF Needs Management Review
                                </strong>

                                <p
                                    style="
                                        margin:.5rem 0 0;
                                        color:#374151;
                                        line-height:1.5;
                                    "
                                >
                                    <?php
                                    echo esc_html(
                                        $reason
                                    );
                                    ?>
                                </p>
                            </div>

                        <?php endif; ?>

                        <?php if ($recommended_path !== '') : ?>

                            <div
                                style="
                                    margin-top:1rem;
                                    padding:1rem;
                                    border:1px solid #e5e7eb;
                                    border-radius:8px;
                                "
                            >
                                <strong>
                                    Recommended Path:
                                </strong>

                                <?php
                                echo esc_html(
                                    $recommended_path
                                );
                                ?>
                            </div>

                        <?php endif; ?>

                        <div
                            style="
                                margin-top:1rem;
                                padding-top:1rem;
                                border-top:1px solid #e5e7eb;
                            "
                        >

                            <strong>
                                Available Actions
                            </strong>

                            <div
                                style="
                                    display:flex;
                                    flex-wrap:wrap;
                                    gap:.75rem;
                                    margin-top:.75rem;
                                "
                            >

                                <form method="post">

                                    <?php
                                    wp_nonce_field(
                                        'sof_zeffy_renewal_decision_' .
                                        (int)$transaction->id
                                    );
                                    ?>

                                    <input
                                        type="hidden"
                                        name="sof_zeffy_renewal_decision"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="transaction_id"
                                        value="<?php
                                            echo esc_attr(
                                                (string)$transaction->id
                                            );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="decision"
                                        value="already_applied"
                                    >

                                    <button
                                        type="submit"
                                        class="button"
                                    >
                                        Confirm Already Applied
                                    </button>

                                </form>

                                <form method="post">

                                    <?php
                                    wp_nonce_field(
                                        'sof_zeffy_renewal_decision_' .
                                        (int)$transaction->id
                                    );
                                    ?>

                                    <input
                                        type="hidden"
                                        name="sof_zeffy_renewal_decision"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="transaction_id"
                                        value="<?php
                                            echo esc_attr(
                                                (string)$transaction->id
                                            );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="decision"
                                        value="needs_processing"
                                    >

                                    <button
                                        type="submit"
                                        class="button"
                                    >
                                        Renewal Still Needs Processing
                                    </button>

                                </form>

                                <form method="post">

                                    <?php
                                    wp_nonce_field(
                                        'sof_zeffy_renewal_decision_' .
                                        (int)$transaction->id
                                    );
                                    ?>

                                    <input
                                        type="hidden"
                                        name="sof_zeffy_renewal_decision"
                                        value="1"
                                    >

                                    <input
                                        type="hidden"
                                        name="transaction_id"
                                        value="<?php
                                            echo esc_attr(
                                                (string)$transaction->id
                                            );
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="decision"
                                        value="further_review"
                                    >

                                    <button
                                        type="submit"
                                        class="button"
                                    >
                                        Needs Further Review
                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php elseif ($view === 'ready_to_apply') : ?>

            <?php
            $ready_results =
                array_filter(
                    (array)(
                        $review['results']
                        ?? []
                    ),
                    function ($row) {
                        $assessment =
                            (array)(
                                $row['assessment']
                                ?? []
                            );

                        return (
                            (string)(
                                $assessment['assessment_status']
                                ?? ''
                            )
                            === 'ready_to_apply'
                        );
                    }
                );
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
                    Renewals Ready to Apply
                </h3>

                <p style="margin:0;color:#374151;">
                    SOF found no conflicting membership evidence
                    for these Renewal transactions.
                </p>

                <p
                    style="
                        margin:.75rem 0 0;
                        color:#374151;
                    "
                >
                    Review the proposed membership change before
                    allowing SOF to update the member record.
                    No membership changes have been made.
                </p>
            </div>

            <div
                style="
                    display:grid;
                    grid-template-columns:1fr;
                    gap:1rem;
                    margin-top:1rem;
                "
            >

                <?php foreach ($ready_results as $row) : ?>

                    <?php
                    $transaction =
                        $row['transaction']
                        ?? null;

                    $assessment =
                        (array)(
                            $row['assessment']
                            ?? []
                        );

                    if (!$transaction) {
                        continue;
                    }

                    $member =
                        (
                            isset($assessment['member'])
                            && is_array($assessment['member'])
                        )
                            ? $assessment['member']
                            : [];

                    $member_name = trim(
                        (string)(
                            $member['full_name']
                            ?? ''
                        )
                    );

                    if ($member_name === '') {
                        $member_name = trim(
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
                        $member_name = trim(
                            (string)(
                                $transaction->buyer_first_name
                                ?? ''
                            )
                            . ' '
                            . (string)(
                                $transaction->buyer_last_name
                                ?? ''
                            )
                        );
                    }

                    $coai_number = trim(
                        (string)(
                            $member['COAI_number']
                            ?? ''
                        )
                    );

                    $renewal_date = trim(
                        (string)(
                            $assessment['renewal_date']
                            ?? ''
                        )
                    );

                    $current_expiration = trim(
                        (string)(
                            $assessment['current_expiration']
                            ?? ''
                        )
                    );

                    $standard_expiration = trim(
                        (string)(
                            $assessment['standard_expiration']
                            ?? ''
                        )
                    );

                    $membership_product = trim(
                        (string)(
                            $transaction->membership_product
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

                        <h4
                            style="
                                margin:0 0 .25rem;
                                font-size:1.15rem;
                            "
                        >
                            <?php
                            echo esc_html(
                                $member_name !== ''
                                    ? $member_name
                                    : 'Member'
                            );
                            ?>
                        </h4>

                        <?php if ($coai_number !== '') : ?>

                            <p style="margin:.25rem 0 1rem;">
                                COAI Number:
                                <strong>
                                    <?php
                                    echo esc_html(
                                        $coai_number
                                    );
                                    ?>
                                </strong>
                            </p>

                        <?php endif; ?>

                        <div
                            style="
                                display:grid;
                                grid-template-columns:
                                    repeat(
                                        auto-fit,
                                        minmax(180px, 1fr)
                                    );
                                gap:1rem;
                            "
                        >

                            <div>
                                <div>
                                    Renewal Date
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $renewal_date
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <div>
                                    Current Expiration
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $current_expiration
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <div>
                                    Proposed Expiration
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $standard_expiration
                                    );
                                    ?>
                                </strong>
                            </div>

                            <div>
                                <div>
                                    Membership Product
                                </div>

                                <strong>
                                    <?php
                                    echo esc_html(
                                        $membership_product
                                    );
                                    ?>
                                </strong>
                            </div>

                        </div>

                        <div
                            style="
                                margin-top:1rem;
                                padding:1rem;
                                border:1px solid #e5e7eb;
                                border-radius:8px;
                            "
                        >
                            <strong>
                                Recommended Path:
                            </strong>

                            Review the proposed Renewal Date and
                            Expiration Date before allowing SOF to
                            update the member record.
                        </div>

                        <div
                            style="
                                margin-top:1rem;
                                padding-top:1rem;
                                border-top:1px solid #e5e7eb;
                            "
                        >
                            <strong>
                                Available Actions
                            </strong>

                            <div style="margin-top:.75rem;">

                                <a
                                    class="button"
                                    href="<?php
                                        echo esc_url(
                                            add_query_arg(
                                                [
                                                    'view' =>
                                                        'confirm_processing',
                                                    'transaction_id' =>
                                                        (int)$transaction->id,
                                                ],
                                                home_url(
                                                    '/renewal-management-review/'
                                                )
                                            )
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
                                    Review Proposed Change
                                </a>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php endif; ?>

        </div>

        <?php

        return ob_get_clean();
    }
}