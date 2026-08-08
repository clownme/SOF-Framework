<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Assessment
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication Assessment
 *
 * Purpose:
 *     Represent the current business assessment of a
 *     communication situation.
 *
 * Responsibilities:
 *     - Record the current assessment status
 *     - Summarize what the current facts mean
 *     - Record the reasons supporting the assessment
 *     - Record the confidence of the assessment
 *
 * Does NOT:
 *     - Discover communication audiences
 *     - Discover communication recipients
 *     - Evaluate recipient records
 *     - Recommend a business action
 *     - Render presentation
 *     - Send communications
 *
 * ============================================================
 */

class SOF_CommunicationAssessment
{
    // -------------------------------------------------
    // Assessment Statuses
    // -------------------------------------------------

    public const STATUS_READY =
        'ready';

    public const STATUS_NEEDS_ATTENTION =
        'needs_attention';

    public const STATUS_NOT_READY =
        'not_ready';

    // -------------------------------------------------
    // Assessment Confidence
    // -------------------------------------------------

    public const CONFIDENCE_HIGH =
        'high';

    public const CONFIDENCE_MEDIUM =
        'medium';

    public const CONFIDENCE_LOW =
        'low';

    // -------------------------------------------------
    // Assessment
    // -------------------------------------------------

    /**
     * Current assessment status.
     */
    protected string $status;

    /**
     * User-friendly assessment summary.
     */
    protected string $summary;

    /**
     * Business reasons supporting the assessment.
     *
     * @var array<int, string>
     */
    protected array $reasons;

    /**
     * Confidence in the current assessment.
     */
    protected string $confidence;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    /**
     * @param array<int, string> $reasons
     */
    public function __construct(
        string $status,
        string $summary,
        array $reasons = [],
        string $confidence = self::CONFIDENCE_HIGH
    ) {
        $this->status =
            $this->normalize_status($status);

        $this->summary =
            trim($summary);

        $this->reasons =
            $this->normalize_reasons($reasons);

        $this->confidence =
            $this->normalize_confidence($confidence);
    }

    // -------------------------------------------------
    // Assessment Information
    // -------------------------------------------------

    public function get_status(): string
    {
        return $this->status;
    }

    public function get_summary(): string
    {
        return $this->summary;
    }

    /**
     * @return array<int, string>
     */
    public function get_reasons(): array
    {
        return $this->reasons;
    }

    public function get_confidence(): string
    {
        return $this->confidence;
    }

    // -------------------------------------------------
    // Assessment State
    // -------------------------------------------------

    public function is_ready(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function needs_attention(): bool
    {
        return (
            $this->status ===
            self::STATUS_NEEDS_ATTENTION
        );
    }

    public function is_not_ready(): bool
    {
        return $this->status === self::STATUS_NOT_READY;
    }

    public function has_reasons(): bool
    {
        return !empty($this->reasons);
    }

    // -------------------------------------------------
    // Presentation Information
    // -------------------------------------------------

    public function get_status_label(): string
    {
        switch ($this->status) {
            case self::STATUS_READY:
                return 'Ready';

            case self::STATUS_NEEDS_ATTENTION:
                return 'Needs Attention';

            case self::STATUS_NOT_READY:
                return 'Not Ready';

            default:
                return 'Not Ready';
        }
    }

    public function get_confidence_label(): string
    {
        switch ($this->confidence) {
            case self::CONFIDENCE_HIGH:
                return 'High';

            case self::CONFIDENCE_MEDIUM:
                return 'Medium';

            case self::CONFIDENCE_LOW:
                return 'Low';

            default:
                return 'Low';
        }
    }

    // -------------------------------------------------
    // Validation
    // -------------------------------------------------

    protected function normalize_status(
        string $status
    ): string {
        $status = strtolower(trim($status));

        $allowed_statuses = [
            self::STATUS_READY,
            self::STATUS_NEEDS_ATTENTION,
            self::STATUS_NOT_READY,
        ];

        if (!in_array(
            $status,
            $allowed_statuses,
            true
        )) {
            return self::STATUS_NOT_READY;
        }

        return $status;
    }

    protected function normalize_confidence(
        string $confidence
    ): string {
        $confidence = strtolower(trim($confidence));

        $allowed_confidence_levels = [
            self::CONFIDENCE_HIGH,
            self::CONFIDENCE_MEDIUM,
            self::CONFIDENCE_LOW,
        ];

        if (!in_array(
            $confidence,
            $allowed_confidence_levels,
            true
        )) {
            return self::CONFIDENCE_LOW;
        }

        return $confidence;
    }

    /**
     * @param array<int, string> $reasons
     *
     * @return array<int, string>
     */
    protected function normalize_reasons(
        array $reasons
    ): array {
        $normalized_reasons = [];

        foreach ($reasons as $reason) {
            $reason = trim((string) $reason);

            if ($reason === '') {
                continue;
            }

            $normalized_reasons[] = $reason;
        }

        return array_values(
            array_unique($normalized_reasons)
        );
    }

    // -------------------------------------------------
    // Serialization
    // -------------------------------------------------

    /**
     * Convert the assessment to a transferable array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'status'           => $this->status,
            'status_label'     => $this->get_status_label(),
            'summary'          => $this->summary,
            'reasons'          => $this->reasons,
            'confidence'       => $this->confidence,
            'confidence_label' =>
                $this->get_confidence_label(),
        ];
    }
}