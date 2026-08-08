<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Available Actions
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Object:
 *     Communication Available Actions
 *
 * Purpose:
 *     Represent the business actions that are currently
 *     appropriate for a communication situation.
 *
 * Responsibilities:
 *     - Hold the currently available business actions
 *     - Identify the primary available action
 *     - Determine whether a specific action is available
 *     - Report whether any appropriate action exists
 *
 * Does NOT:
 *     - Determine user permissions
 *     - Assess communication readiness
 *     - Recommend a business path
 *     - Perform communication actions
 *     - Render action controls
 *
 * Business Question:
 *     What can the person appropriately do now?
 *
 * ============================================================
 */

class SOF_CommunicationAvailableActions
{
    // -------------------------------------------------
    // Available Actions
    // -------------------------------------------------

    /**
     * Actions currently appropriate for the situation.
     *
     * @var array<int, string>
     */
    protected array $actions;

    // -------------------------------------------------
    // Primary Action
    // -------------------------------------------------

    /**
     * The most appropriate next action.
     */
    protected ?string $primary_action;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    /**
     * @param array<int, string> $actions
     */
    public function __construct(
        array $actions = [],
        ?string $primary_action = null
    ) {
        $this->actions = $this->normalize_actions($actions);

        $this->primary_action =
            $primary_action !== null &&
            in_array($primary_action, $this->actions, true)
                ? $primary_action
                : null;
    }

    // -------------------------------------------------
    // Available Actions
    // -------------------------------------------------

    /**
     * Return all currently available actions.
     *
     * @return array<int, string>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    /**
     * Return the primary available action.
     */
    public function primary_action(): ?string
    {
        return $this->primary_action;
    }

    // -------------------------------------------------
    // Business State
    // -------------------------------------------------

    /**
     * Determine whether an action is currently available.
     */
    public function has(string $action): bool
    {
        return in_array($action, $this->actions, true);
    }

    /**
     * Determine whether at least one action is available.
     */
    public function has_actions(): bool
    {
        return !empty($this->actions);
    }

    /**
     * Determine whether no actions are currently available.
     */
    public function is_empty(): bool
    {
        return empty($this->actions);
    }

    /**
     * Return the number of available actions.
     */
    public function count(): int
    {
        return count($this->actions);
    }

    // -------------------------------------------------
    // Normalization
    // -------------------------------------------------

    /**
     * Normalize action identifiers.
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