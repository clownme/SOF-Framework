<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Available Actions Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Service:
 *     Communication Available Actions Service
 *
 * Purpose:
 *     Determine which authorized communication actions are
 *     appropriate for the current business assessment.
 *
 * Responsibilities:
 *     - Interpret the current communication assessment
 *     - Identify actions appropriate for that assessment
 *     - Limit available actions to those already authorized
 *     - Identify the most appropriate primary action
 *     - Return Communication Available Actions
 *
 * Does NOT:
 *     - Determine user permissions
 *     - Resolve the communication audience
 *     - Resolve communication recipients
 *     - Assess communication readiness
 *     - Recommend a business path
 *     - Perform communication actions
 *     - Render action controls
 *
 * Business Question:
 *     Given what is currently true and what the person is
 *     authorized to do, what can they appropriately do now?
 *
 * ============================================================
 */

class SOF_CommunicationAvailableActionsService
{
    // -------------------------------------------------
    // Action Identifiers
    // -------------------------------------------------

    public const ACTION_COMPOSE = 'compose';

    public const ACTION_REVIEW_RECIPIENTS = 'review_recipients';

    // -------------------------------------------------
    // Assessment Statuses
    // -------------------------------------------------

    public const STATUS_READY = 'ready';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    public const STATUS_NOT_READY = 'not_ready';

    // -------------------------------------------------
    // Resolution
    // -------------------------------------------------

    /**
     * Determine the currently available communication actions.
     *
     * @param array<int, string> $authorized_actions
     */
    public function resolve(
        SOF_CommunicationAssessment $assessment,
        array $authorized_actions = []
    ): SOF_CommunicationAvailableActions {
        $authorized_actions =
            $this->normalize_actions($authorized_actions);

        $appropriate_actions =
            $this->appropriate_actions_for($assessment);

        $available_actions = array_values(
            array_intersect(
                $appropriate_actions,
                $authorized_actions
            )
        );

        $primary_action =
            $this->resolve_primary_action(
                $assessment,
                $available_actions
            );

        return new SOF_CommunicationAvailableActions(
            $available_actions,
            $primary_action
        );
    }

    // -------------------------------------------------
    // Appropriate Actions
    // -------------------------------------------------

    /**
     * Determine which actions are appropriate for the
     * current assessment.
     *
     * @return array<int, string>
     */
    protected function appropriate_actions_for(
        SOF_CommunicationAssessment $assessment
    ): array {
        $status = sanitize_key($assessment->get_status());

        switch ($status) {
            case self::STATUS_READY:
                return [
                    self::ACTION_COMPOSE,
                ];

            case self::STATUS_NEEDS_ATTENTION:
                return [
                    self::ACTION_REVIEW_RECIPIENTS,
                    self::ACTION_COMPOSE,
                ];

            case self::STATUS_NOT_READY:
            default:
                return [];
        }
    }

    // -------------------------------------------------
    // Primary Action
    // -------------------------------------------------

    /**
     * Determine the most appropriate next action.
     *
     * @param array<int, string> $available_actions
     */
    protected function resolve_primary_action(
        SOF_CommunicationAssessment $assessment,
        array $available_actions
    ): ?string {
        $status = sanitize_key($assessment->get_status());

        if (
            $status === self::STATUS_NEEDS_ATTENTION &&
            in_array(
                self::ACTION_REVIEW_RECIPIENTS,
                $available_actions,
                true
            )
        ) {
            return self::ACTION_REVIEW_RECIPIENTS;
        }

        if (
            $status === self::STATUS_READY &&
            in_array(
                self::ACTION_COMPOSE,
                $available_actions,
                true
            )
        ) {
            return self::ACTION_COMPOSE;
        }

        if (
            $status === self::STATUS_NEEDS_ATTENTION &&
            in_array(
                self::ACTION_COMPOSE,
                $available_actions,
                true
            )
        ) {
            return self::ACTION_COMPOSE;
        }

        return null;
    }

    // -------------------------------------------------
    // Normalization
    // -------------------------------------------------

    /**
     * Normalize authorized action identifiers.
     *
     * @param array<int, mixed> $actions
     *
     * @return array<int, string>
     */
    protected function normalize_actions(array $actions): array
    {
        $normalized = [];

        foreach ($actions as $action) {
            if (!is_string($action)) {
                continue;
            }

            $action = sanitize_key($action);

            if ($action === '') {
                continue;
            }

            $normalized[] = $action;
        }

        return array_values(array_unique($normalized));
    }
}