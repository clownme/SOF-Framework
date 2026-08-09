<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Compose Workspace
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Workspace:
 *     Compose
 *
 * Purpose:
 *     Provide a clear workspace where an authorized user can
 *     create a business communication.
 *
 * Responsibilities:
 *     - Present the communication composition experience
 *     - Display the selected communication audience
 *     - Collect the communication subject
 *     - Collect the communication message
 *     - Guide the user toward the next communication action
 *
 * Does NOT:
 *     - Determine audience membership
 *     - Validate communication lifecycle rules
 *     - Send communications
 *     - Communicate directly with delivery providers
 *
 * ============================================================
 */

class SOF_ComposeWorkspace
{
    /**
     * Render the Compose Workspace.
     */
    public function render(): string
    {
        // -------------------------------------------------
        // Audience Intent
        // -------------------------------------------------

        $allowed_statuses = [
            'Active',
            'Expired',
            'Archived',
        ];

        $selected_membership_statuses = [
            'Active',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            /*
             * -------------------------------------------------
             * All Eligible Members
             * -------------------------------------------------
             */

            if (
                isset($_POST['sof_all_eligible_members']) &&
                sanitize_text_field(
                    wp_unslash(
                        $_POST['sof_all_eligible_members']
                    )
                ) === '1'
            ) {

                $selected_membership_statuses =
                    $allowed_statuses;

            /*
             * -------------------------------------------------
             * Selected Membership Statuses
             * -------------------------------------------------
             */

            } elseif (
                isset($_POST['sof_membership_statuses']) &&
                is_array($_POST['sof_membership_statuses'])
            ) {

                $requested_statuses =
                    array_map(
                        'sanitize_text_field',
                        wp_unslash(
                            $_POST['sof_membership_statuses']
                        )
                    );

                $selected_membership_statuses =
                    array_values(
                        array_intersect(
                            $allowed_statuses,
                            $requested_statuses
                        )
                    );

                if (!$selected_membership_statuses) {
                    $selected_membership_statuses = [
                        'Active',
                    ];
                }
            }
        }

    $audience_service =
        new SOF_CommunicationAudienceService();

    $audience =
        $audience_service
            ->resolve_current_audience(
                $selected_membership_statuses
            );

    $audience_diagnostic =
        $audience_service
            ->diagnose_current_audience();

    $situation = null;
    
    $audience_population = null;
    
    $status_counts = [
        'Active'    => 0,
        'Expired'   => 0,
        'Archived'  => 0,
    ];

    if ($audience instanceof SOF_CommunicationAudience) {

    // -------------------------------------------------
    // Knowledge Domain Capabilities
    // -------------------------------------------------

    $membership_audience_service =
        new SOF_MembershipAudienceService();
        
    // -------------------------------------------------
    // Audience Population
    // -------------------------------------------------

    $population_service =
        new SOF_CommunicationAudiencePopulationService(
            $membership_audience_service
        );

    $audience_population =
        $population_service->resolve(
            $audience
        );

    $status_counts =
        $audience_population
            ->get_eligible_counts();

    // -------------------------------------------------
    // Communication Situation Services
    // -------------------------------------------------

    $recipients_service =
        new SOF_CommunicationRecipientsService(
            $membership_audience_service
        );

    $assessment_service =
        new SOF_CommunicationAssessmentService();

    $recommendation_service =
        new SOF_CommunicationRecommendationService();

    $available_actions_service =
        new SOF_CommunicationAvailableActionsService();

    $situation_service =
        new SOF_CommunicationSituationService(
            $recipients_service,
            $assessment_service,
            $recommendation_service,
            $available_actions_service
        );

    /*
     * Transitional authorization for the current
     * Communication audience.
     *
     * Audience resolution has already confirmed that the
     * current user may communicate with the assigned members.
     *
     * A dedicated authorization service will eventually
     * provide these actions.
     */
    $authorized_actions = [
        SOF_CommunicationAvailableActionsService::ACTION_COMPOSE,
        SOF_CommunicationAvailableActionsService::ACTION_REVIEW_RECIPIENTS,
    ];

    $situation =
        $situation_service->resolve(
            $audience,
            $audience_population,
            $authorized_actions
        );
}

    $audience_name =
        $situation
            ? $situation->audience()->get_name()
            : 'Assigned Audience';

    $audience_description =
        $situation
            ? $situation->audience()->get_description()
            : 'the audience you are authorized to contact';

    $recipients =
        $situation
            ? $situation->recipients()
            : null;

    $assessment =
        $situation
            ? $situation->assessment()
            : null;

    $recommendation =
        $situation
            ? $situation->recommendation()
            : null;

    $available_actions =
        $situation
            ? $situation->available_actions()
            : null;

    $total_recipient_count =
        $recipients
            ? $recipients->get_total_count()
            : 0;

    $available_recipient_count =
        $recipients
            ? $recipients->get_available_count()
            : 0;

    $unavailable_recipient_count =
        $recipients
            ? $recipients->get_unavailable_count()
            : 0;

    $eligible_population_label =
        $audience_population
            ? sprintf(
                '%s eligible members',
                number_format_i18n(
                    $audience_population
                        ->get_eligible_total()
                )
            )
            : '';
            
    // -------------------------------------------------
    // Membership Population Information
    // -------------------------------------------------

    $active_count =
        (int) ($status_counts['Active'] ?? 0);

    $expired_count =
        (int) ($status_counts['Expired'] ?? 0);

    $archived_count =
        (int) ($status_counts['Archived'] ?? 0);

    $selected_population_count = 0;

    foreach ($selected_membership_statuses as $selected_status) {

        $selected_population_count +=
            (int) (
                $status_counts[$selected_status] ?? 0
            );
    }
            
    // -------------------------------------------------
    // Existing Communication
    // -------------------------------------------------

    $communication_id = 0;

    if (isset($_GET['communication_id'])) {

        $communication_id =
            absint($_GET['communication_id']);

    } elseif (isset($_POST['communication_id'])) {

        $communication_id =
            absint($_POST['communication_id']);
    }

    $existing_communication = null;

    $communication_repository =
        new SOF_CommunicationRepository();

    $persistence_service =
        new SOF_CommunicationPersistenceService(
            $communication_repository
        );

    if ($communication_id > 0) {

        $existing_communication =
            $persistence_service->find(
                $communication_id
            );
    }

    // -------------------------------------------------
    // Recipient Selection
    // -------------------------------------------------

    $recipient_selection =
        $existing_communication
            ? $existing_communication
                ->get_recipient_selection()
            : new SOF_CommunicationRecipientSelection(
                SOF_CommunicationRecipientSelection::MODE_ALL,
                []
            );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $selection_mode =
            isset($_POST['recipient_selection_mode'])
                ? sanitize_key(
                    wp_unslash(
                        $_POST['recipient_selection_mode']
                    )
                )
                : SOF_CommunicationRecipientSelection::MODE_ALL;

        $selected_member_ids =
            isset($_POST['selected_member_ids']) &&
            is_array($_POST['selected_member_ids'])
                ? array_values(
                    array_filter(
                        array_map(
                            'absint',
                            wp_unslash(
                                $_POST['selected_member_ids']
                            )
                        )
                    )
                )
                : [];

        $recipient_selection =
            new SOF_CommunicationRecipientSelection(
                $selection_mode,
                $selected_member_ids
            );
    }

    $selection_service =
        new SOF_CommunicationRecipientSelectionService();

    $eligible_recipients =
        $recipients instanceof SOF_CommunicationRecipients
            ? $recipients
            : new SOF_CommunicationRecipients(
                [],
                []
            );

    $selected_recipients =
        $selection_service->apply(
            $eligible_recipients,
            $recipient_selection
        );

    // -------------------------------------------------
    // Selected Recipient Presentation
    // -------------------------------------------------

    $selected_recipient_count =
        $selected_recipients
            ->get_available_count();

    if (
        $recipient_selection
            ->uses_selected_recipients()
    ) {
        $selected_recipient_phrase =
            $selected_recipient_count === 1
                ? 'One selected member'
                : number_format_i18n(
                    $selected_recipient_count
                ) . ' selected members';

    } else {
        $selected_recipient_phrase =
            $selected_recipient_count === 1
                ? 'One eligible member'
                : number_format_i18n(
                    $selected_recipient_count
                ) . ' eligible members';
    }

    $compose_recommendation_message =
        $selected_recipient_phrase .
        (
            $selected_recipient_count === 1
                ? ' is included in this communication. '
                : ' are included in this communication. '
        ) .
        'Continue preparing your message.';

    // -------------------------------------------------
    // Communication Composition
    // -------------------------------------------------
    
    $communication = null;

    $persisted_communication = null;

    $composition_error = '';

    $subject =
        $existing_communication
            ? $existing_communication->get_subject()
            : '';

    $message =
        $existing_communication
            ? $existing_communication->get_body()
            : '';

    /*
     * Preserve composition content when the audience
     * selection is updated before the Communication
     * is persisted.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (isset($_POST['sof_communication_subject'])) {
            $subject =
                sanitize_text_field(
                    wp_unslash(
                        $_POST['sof_communication_subject']
                    )
                );
        }

        if (isset($_POST['sof_communication_message'])) {
            $message =
                wp_kses_post(
                    wp_unslash(
                        $_POST['sof_communication_message']
                    )
                );
        }
    }
    
    if (
        $situation &&
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['sof_compose_submit'])
    ) {
        $nonce =
            isset($_POST['sof_compose_nonce'])
                ? sanitize_text_field(
                    wp_unslash(
                        $_POST['sof_compose_nonce']
                    )
                )
                : '';

        if (
            !$nonce ||
            !wp_verify_nonce(
                $nonce,
                'sof_compose_communication'
            )
        ) {
            $composition_error =
                'The communication could not be prepared because the security check failed.';

        } else {

            $subject =
                isset($_POST['sof_communication_subject'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_communication_subject']
                        )
                    )
                    : '';

            $message =
                isset($_POST['sof_communication_message'])
                    ? wp_kses_post(
                        wp_unslash(
                            $_POST['sof_communication_message']
                        )
                    )
                    : '';

            // -------------------------------------------------
            // Validate Recipient Selection
            // -------------------------------------------------

            $validated_recipients =
                $selection_service->apply(
                    $eligible_recipients,
                    $recipient_selection
                );

            if (
                $recipient_selection
                    ->uses_selected_recipients() &&
                $validated_recipients
                    ->get_available_count() < 1
            ) {
                $composition_error =
                    'Select at least one eligible member before continuing.';

            } else {

                $validated_member_ids = [];

                foreach (
                    $validated_recipients
                        ->get_available_recipients()
                    as $recipient
                ) {
                    $member_id =
                        isset($recipient['member_id'])
                            ? (int) $recipient['member_id']
                            : 0;

                    if ($member_id > 0) {
                        $validated_member_ids[] =
                            $member_id;
                    }
                }

                if (
                    $recipient_selection
                        ->uses_selected_recipients()
                ) {
                    $recipient_selection =
                        new SOF_CommunicationRecipientSelection(
                            SOF_CommunicationRecipientSelection::MODE_SELECTED,
                            $validated_member_ids
                        );

                } else {
                    $recipient_selection =
                        new SOF_CommunicationRecipientSelection(
                            SOF_CommunicationRecipientSelection::MODE_ALL,
                            []
                        );
                }

                // -------------------------------------------------
                // Compose or Revise Communication
                // -------------------------------------------------

                $composition_service =
                    new SOF_CommunicationCompositionService();

                if ($existing_communication) {

                    $communication =
                        $composition_service->revise(
                            $existing_communication,
                            $subject,
                            $message
                        );

                } else {

                    $communication =
                        $composition_service->compose(
                            $audience,
                            $available_recipient_count,
                            $subject,
                            $message,
                            get_current_user_id()
                        );
                }

                if (!$communication) {

                    $composition_error =
                        'Enter a subject and message before continuing.';

                } else {

                    $communication->set_recipient_selection(
                        $recipient_selection
                    );

                    // -------------------------------------------------
                    // Communication Persistence
                    // -------------------------------------------------

                    if ($existing_communication) {

                        $persisted_communication =
                            $persistence_service->save(
                                $communication
                            );

                    } else {

                        $persisted_communication =
                            $persistence_service->persist(
                                $communication
                            );
                    }

                    if (!$persisted_communication) {

                        $composition_error =
                            'The communication was prepared but could not be saved.';

                    } else {

                        $communication_id =
                            $persisted_communication->get_id();

                        if (!$communication_id) {

                            $composition_error =
                                'The communication was saved but no communication identity was returned.';

                        } else {

                            // -------------------------------------------------
                            // Continue to Verify
                            // -------------------------------------------------

                            $verify_url =
                                add_query_arg(
                                    'communication_id',
                                    $communication_id,
                                    home_url(
                                        '/verify-communication/'
                                    )
                                );

                            wp_safe_redirect(
                                $verify_url
                            );

                            exit;
                        }
                    }
                }
            }
        }
    }

        ob_start();
        ?>

        <div class="sof-workspace sof-compose-workspace">

            <header class="sof-workspace-header">

                <h1>Compose Communication</h1>

                <p>
                    Create a clear message for
                    <?php echo esc_html($audience_description); ?>.
                </p>

            </header>

            <main class="sof-workspace-content">

                <section class="sof-card sof-compose-card">

                    <header class="sof-card-header">

                        <h2 class="sof-card-title">
                            Communication
                        </h2>

                        <p class="sof-card-summary">
                            Enter the information needed to prepare
                            your communication.
                        </p>

                    </header>

                    <div class="sof-card-content">

                        <?php if ($situation): ?>

                            <section class="sof-situation-check">

                                <h3>
                                    Current Communication Situation
                                </h3>

                                <dl>

                                    <div>
                                        <dt>Assessment</dt>

                                        <dd>
                                            <?php
                                            echo esc_html(
                                                $assessment->get_status_label()
                                            );
                                            ?>
                                        </dd>
                                    </div>

                                    <div>
                                        <dt>Summary</dt>

                                        <dd>
                                            <?php
                                            echo nl2br(
                                                esc_html(
                                                    $assessment->get_summary()
                                                )
                                            );
                                            ?>
                                        </dd>
                                    </div>
                                    
            <div>
                <dt>Recommendation</dt>

                <dd>
                    <?php
                    echo esc_html(
                        $recommendation->get_title()
                    );
                    ?>
                </dd>
            </div>

            <div>
                <dt>Recommended Path</dt>

                <dd>
                    <?php
                    echo esc_html(
                        $compose_recommendation_message
                    );
                    ?>
                </dd>
            </div>
            <div>
                <dt>Available Actions</dt>

                <dd>
                    <?php
                    $actions =
                        $available_actions->actions();

                    echo esc_html(
                        !empty($actions)
                            ? implode(
                                ', ',
                                array_map(
                                    static function (
                                        string $action
                                    ): string {
                                        return ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $action
                                            )
                                        );
                                    },
                                    $actions
                                )
                            )
                            : 'No actions are currently available.'
                    );
                    ?>
                </dd>
            </div>

            <div>
                    <dt>Primary Action</dt>

                    <dd>
                        <?php
                        $primary_action =
                            $available_actions
                                ->primary_action();

                        echo esc_html(
                            $primary_action
                                ? ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $primary_action
                                    )
                                )
                                : 'None'
                        );
                        ?>
                    </dd>
                </div>

            </dl>

        </section>

        <?php else: ?>

            <section class="sof-situation-check">

                <h3>
                    Communication Situation Unavailable
                </h3>

                <p>
                    SOF could not resolve a communication audience
                    for the current user.
                </p>
                
                <h4>
                    Audience Resolution Diagnostic
                </h4>
                
                <dl>
                    
                    <div>
                        <dt>Logged In</dt>
                        <dd>
                            <?php echo $audience_diagnostic['logged_in']
                                ? 'Yes'
                                : 'No';
                            ?>
                        </dd>
                    </div>
                    
                    <div>
                <dt>WordPress User ID</dt>
                <dd>
                    <?php
                    echo esc_html(
                        (string)
                        $audience_diagnostic[
                            'wordpress_user_id'
                        ]
                    );
                    ?>
                </dd>
            </div>

            <div>
                <dt>Member Lookup Available</dt>
                <dd>
                    <?php
                    echo $audience_diagnostic[
                        'member_lookup_available'
                    ]
                        ? 'Yes'
                        : 'No';
                    ?>
                </dd>
            </div>

            <div>
                <dt>Member Resolved</dt>
                <dd>
                    <?php
                    echo $audience_diagnostic[
                        'member_resolved'
                    ]
                        ? 'Yes'
                        : 'No';
                    ?>
                </dd>
            </div>

            <div>
                <dt>Member ID</dt>
                <dd>
                    <?php
                    echo esc_html(
                        (string)
                        $audience_diagnostic['member_id']
                    );
                    ?>
                </dd>
            </div>

            <div>
                <dt>User Group</dt>
                <dd>
                    <?php
                    echo esc_html(
                        $audience_diagnostic['usergroup'] !== ''
                            ? $audience_diagnostic['usergroup']
                            : 'None'
                    );
                    ?>
                </dd>
            </div>

                    <div>
                        <dt>Scope Resolver Available</dt>
                        <dd>
                            <?php
                            echo $audience_diagnostic[
                                'scope_resolver_available'
                            ]
                                ? 'Yes'
                                : 'No';
                            ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Resolved Scope</dt>
                        <dd>
                            <?php
                            echo esc_html(
                                $audience_diagnostic[
                                    'resolved_scope'
                                ] !== ''
                                    ? $audience_diagnostic[
                                        'resolved_scope'
                                    ]
                                    : 'None'
                            );
                            ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Permission Function Available</dt>
                        <dd>
                            <?php
                            echo $audience_diagnostic[
                                'permission_function_available'
                            ]
                                ? 'Yes'
                                : 'No';
                            ?>
                        </dd>
                    </div>

                    <div>
                        <dt>Can Access Audience</dt>
                        <dd>
                            <?php
                            echo $audience_diagnostic[
                                'can_access_audience'
                            ]
                                ? 'Yes'
                                : 'No';
                            ?>
                        </dd>
                    </div>

                </dl>

            </section>

        <?php endif; ?>
                        
                        <form
                            class="sof-compose-form"
                            method="post"
                        >
                            
                        <?php
                        wp_nonce_field(
                            'sof_compose_communication',
                            'sof_compose_nonce'
                        );
                        ?>
                        
                        <?php if ($communication_id > 0): ?>

                            <input
                                type="hidden"
                                name="communication_id"
                                value="<?php echo esc_attr($communication_id); ?>"
                            >

                        <?php endif; ?>

                            <div class="sof-form-field">

                                <label for="sof-communication-audience">
                                    Audience
                                </label>

                                <input
                                    type="text"
                                    id="sof-communication-audience"
                                    name="sof_communication_audience"
                                    value="<?php echo esc_attr($audience_name); ?>"
                                    readonly
                                >
                                
                                <?php if ($eligible_population_label !== ''): ?>

                                    <p class="sof-audience-count">
                                        <?php echo esc_html($eligible_population_label); ?>
                                    </p>

                                <?php endif; ?>

                                <p class="sof-form-help">
                                    Your communication will be limited
                                    to the members you are authorized to
                                    contact.
                                </p>

                            </div>
                            
                            <div class="sof-form-field">

                                <label>
                                    Include Membership Statuses
                                </label>

                                <div class="sof-membership-status-options">

                                    <label class="sof-membership-status-all">
                                        <input
                                            type="checkbox"
                                            id="sof_all_eligible_members"
                                            name="sof_all_eligible_members"
                                            value="1"
                                            <?php
                                            checked(
                                                count($selected_membership_statuses) === 3
                                            );
                                            ?>
                                        >
                                        <strong>
                                            All Eligible Statuses
                                        </strong>
                                        <span class="sof-membership-status-count">
                                            <?php
                                            echo esc_html(
                                                number_format_i18n(
                                                    $active_count +
                                                    $expired_count +
                                                    $archived_count
                                                )
                                            );
                                            ?>
                                        </span>
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            class="sof-membership-status-option"
                                            name="sof_membership_statuses[]"
                                            value="Active"
                                            <?php
                                            checked(
                                                in_array(
                                                    'Active',
                                                    $selected_membership_statuses,
                                                    true
                                                )
                                            );
                                            ?>
                                        >
                                        Active
                                        <span class="sof-membership-status-count">
                                            <?php
                                            echo esc_html(
                                                number_format_i18n(
                                                    $active_count
                                                )
                                            );
                                            ?>
                                        </span>
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            class="sof-membership-status-option"
                                            name="sof_membership_statuses[]"
                                            value="Expired"
                                            <?php
                                            checked(
                                                in_array(
                                                    'Expired',
                                                    $selected_membership_statuses,
                                                    true
                                                )
                                            );
                                            ?>
                                        >
                                        Expired
                                        <span class="sof-membership-status-count">
                                            <?php
                                            echo esc_html(
                                                number_format_i18n(
                                                    $expired_count
                                                )
                                            );
                                            ?>
                                        </span>
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            class="sof-membership-status-option"
                                            name="sof_membership_statuses[]"
                                            value="Archived"
                                            <?php
                                            checked(
                                                in_array(
                                                    'Archived',
                                                    $selected_membership_statuses,
                                                    true
                                                )
                                            );
                                            ?>
                                        >
                                        Archived
                                        <span class="sof-membership-status-count">
                                            <?php
                                            echo esc_html(
                                                number_format_i18n(
                                                    $archived_count
                                                )
                                            );
                                            ?>
                                        </span>
                                    </label>

                                </div>

                                <p class="sof-audience-count">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            'Selected audience: %s members',
                                            number_format_i18n(
                                                $selected_population_count
                                            )
                                        )
                                    );
                                    ?>
                                </p>
                            
                            <div class="sof-update-audience-action">

                                <button
                                    type="submit"
                                    name="sof_update_audience"
                                    value="1"
                                    class="sof-button sof-button-secondary sof-update-audience"
                                    formnovalidate
                                >
                                    Update Audience
                                </button>

                            </div>

                            <p class="sof-form-help">
                                Select the membership statuses to include in this communication.
                                Deceased members are excluded from normal communications.
                            </p>

                        </div>
                        
                        <?php
                            echo SOF_CommunicationRecipientSelectionCard::render(
                                $eligible_recipients,
                                $recipient_selection
                            );
                            ?>

                            <?php if ($composition_error !== ''): ?>

                                <div class="sof-compose-message sof-compose-message-error">

                                    <strong>
                                        Communication Not Prepared
                                    </strong>

                                    <p>
                                        <?php
                                        echo esc_html(
                                            $composition_error
                                        );
                                        ?>
                                    </p>

                                </div>

                            <?php endif; ?>

                            <section class="sof-card sof-message-creation-card">

                                <header class="sof-card-header">

                                    <h3 class="sof-card-title">
                                        Create Your Message
                                    </h3>

                                    <p class="sof-card-summary">
                                        Create the message your selected members
                                        will receive.
                                    </p>

                                </header>

                                <div class="sof-card-content">

                                    <div class="sof-form-field">

                                        <label for="sof-communication-subject">
                                            Subject
                                        </label>

                                        <input
                                            type="text"
                                            id="sof-communication-subject"
                                            name="sof_communication_subject"
                                            value="<?php echo esc_attr($subject); ?>"
                                            maxlength="200"
                                            required
                                        >

                                    </div>

                                    <div class="sof-form-field">

                                        <label for="sof-communication-message">
                                            Message
                                        </label>

                                        <textarea
                                            id="sof-communication-message"
                                            name="sof_communication_message"
                                            rows="12"
                                            required
                                        ><?php echo esc_textarea($message); ?></textarea>

                                    </div>

                                </div>

                            </section>
                            
                            <?php if ($composition_error !== ''): ?>

                                <div class="sof-compose-message sof-compose-message-error">

                                    <strong>
                                        Communication Not Prepared
                                    </strong>

                                    <p>
                                        <?php echo esc_html($composition_error); ?>
                                    </p>

                                </div>

                            <?php elseif ($communication instanceof SOF_Communication): ?>

                                <div class="sof-compose-message sof-compose-message-success">

                                    <strong>
                                        Communication Prepared
                                    </strong>

                                    <p>
                                        Your communication has been composed for
                                        <?php
                                        echo esc_html(
                                            number_format_i18n(
                                                $communication->get_recipient_count()
                                            )
                                        );
                                        ?>
                                        recipients and is ready for verification.
                                    </p>

                                </div>

                            <?php endif; ?>

                            <div class="sof-compose-actions">

                                <a
                                    class="sof-button sof-button-secondary"
                                    href="<?php
                                    echo esc_url(
                                        home_url('/communications/')
                                    );
                                    ?>"
                                >
                                    Cancel
                                </a>

                                <button
                                    type="submit"
                                    name="sof_compose_submit"
                                    value="1"
                                    class="sof-button sof-button-primary"
                                    <?php echo !$situation ? 'disabled' : ''; ?>
                                >
                                    Continue to Verify
                                </button>

                            </div>

                        </form>

                    </div>

                </section>

            </main>

        </div>

        <?php

        return (string) ob_get_clean();
    }
}