<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recommendation
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication Recommendation
 *
 * Purpose:
 *     Represent a recommended path based on the current
 *     communication assessment.
 *
 * Responsibilities:
 *     - Identify the recommended path
 *     - Explain the recommended action
 *     - Record whether the user may proceed
 *     - Record whether user attention is required
 *     - Provide a user-friendly action label
 *
 * Does NOT:
 *     - Discover communication audiences
 *     - Discover communication recipients
 *     - Assess communication facts
 *     - Perform the recommended action
 *     - Render presentation
 *     - Send communications
 *
 * ============================================================
 */

class SOF_CommunicationRecommendation
{
    // -------------------------------------------------
    // Recommendation Keys
    // -------------------------------------------------

    public const RECOMMEND_PROCEED =
        'proceed';

    public const RECOMMEND_REVIEW =
        'review';

    public const RECOMMEND_STOP =
        'stop';

    // -------------------------------------------------
    // Recommendation
    // -------------------------------------------------

    /**
     * Machine-safe recommendation identifier.
     */
    protected string $key;

    /**
     * User-friendly recommendation title.
     */
    protected string $title;

    /**
     * User-friendly explanation of the recommended path.
     */
    protected string $message;

    /**
     * User-friendly action label.
     */
    protected string $action_label;

    /**
     * Whether the user may proceed with the communication.
     */
    protected bool $can_proceed;

    /**
     * Whether the situation requires user attention.
     */
    protected bool $requires_attention;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    public function __construct(
        string $key,
        string $title,
        string $message,
        string $action_label,
        bool $can_proceed,
        bool $requires_attention
    ) {
        $this->key =
            $this->normalize_key($key);

        $this->title =
            trim($title);

        $this->message =
            trim($message);

        $this->action_label =
            trim($action_label);

        $this->can_proceed =
            $can_proceed;

        $this->requires_attention =
            $requires_attention;
    }

    // -------------------------------------------------
    // Recommendation Information
    // -------------------------------------------------

    public function get_key(): string
    {
        return $this->key;
    }

    public function get_title(): string
    {
        return $this->title;
    }

    public function get_message(): string
    {
        return $this->message;
    }

    public function get_action_label(): string
    {
        return $this->action_label;
    }

    // -------------------------------------------------
    // Recommendation State
    // -------------------------------------------------

    public function can_proceed(): bool
    {
        return $this->can_proceed;
    }

    public function requires_attention(): bool
    {
        return $this->requires_attention;
    }

    public function is_proceed_recommendation(): bool
    {
        return (
            $this->key ===
            self::RECOMMEND_PROCEED
        );
    }

    public function is_review_recommendation(): bool
    {
        return (
            $this->key ===
            self::RECOMMEND_REVIEW
        );
    }

    public function is_stop_recommendation(): bool
    {
        return (
            $this->key ===
            self::RECOMMEND_STOP
        );
    }

    // -------------------------------------------------
    // Validation
    // -------------------------------------------------

    protected function normalize_key(
        string $key
    ): string {
        $key = strtolower(trim($key));

        $allowed_keys = [
            self::RECOMMEND_PROCEED,
            self::RECOMMEND_REVIEW,
            self::RECOMMEND_STOP,
        ];

        if (!in_array(
            $key,
            $allowed_keys,
            true
        )) {
            return self::RECOMMEND_STOP;
        }

        return $key;
    }

    // -------------------------------------------------
    // Serialization
    // -------------------------------------------------

    /**
     * Convert the recommendation to a transferable array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'key'                => $this->key,
            'title'              => $this->title,
            'message'            => $this->message,
            'action_label'       => $this->action_label,
            'can_proceed'        => $this->can_proceed,
            'requires_attention' =>
                $this->requires_attention,
        ];
    }
}