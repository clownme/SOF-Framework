<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Audience Population
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication Audience Population
 *
 * Purpose:
 *     Represent the membership population available to a
 *     Communication audience before recipient selection.
 *
 * Responsibilities:
 *     - Represent eligible membership population by status
 *     - Represent excluded membership population
 *     - Provide total eligible population
 *     - Provide audience population business truth
 *
 * Does NOT:
 *     - Query member records
 *     - Determine organizational scope
 *     - Select Communication recipients
 *     - Assess delivery readiness
 *     - Recommend business actions
 *     - Deliver Communications
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_CommunicationAudiencePopulation
{
    /**
     * Eligible membership populations.
     *
     * @var array<string, int>
     */
    protected array $eligible_counts;

    /**
     * Excluded membership populations.
     *
     * @var array<string, int>
     */
    protected array $excluded_counts;

    /**
     * @param array<string, int> $eligible_counts
     * @param array<string, int> $excluded_counts
     */
    public function __construct(
        array $eligible_counts = [],
        array $excluded_counts = []
    ) {
        $this->eligible_counts =
            $this->normalize_counts(
                $eligible_counts
            );

        $this->excluded_counts =
            $this->normalize_counts(
                $excluded_counts
            );
    }

    // -------------------------------------------------
    // Eligible Population
    // -------------------------------------------------

    /**
     * @return array<string, int>
     */
    public function get_eligible_counts(): array
    {
        return $this->eligible_counts;
    }

    public function get_eligible_count(
        string $status
    ): int {
        return (int) (
            $this->eligible_counts[$status]
            ?? 0
        );
    }

    public function get_eligible_total(): int
    {
        return array_sum(
            $this->eligible_counts
        );
    }

    // -------------------------------------------------
    // Excluded Population
    // -------------------------------------------------

    /**
     * @return array<string, int>
     */
    public function get_excluded_counts(): array
    {
        return $this->excluded_counts;
    }

    public function get_excluded_count(
        string $status
    ): int {
        return (int) (
            $this->excluded_counts[$status]
            ?? 0
        );
    }

    public function get_excluded_total(): int
    {
        return array_sum(
            $this->excluded_counts
        );
    }

    // -------------------------------------------------
    // Population State
    // -------------------------------------------------

    public function has_eligible_members(): bool
    {
        return $this->get_eligible_total() > 0;
    }

    public function has_excluded_members(): bool
    {
        return $this->get_excluded_total() > 0;
    }

    // -------------------------------------------------
    // Serialization
    // -------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'eligible_counts' => $this->eligible_counts,
            'eligible_total'  => $this->get_eligible_total(),
            'excluded_counts' => $this->excluded_counts,
            'excluded_total'  => $this->get_excluded_total(),
        ];
    }

    // -------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------

    /**
     * @param array<string, mixed> $counts
     *
     * @return array<string, int>
     */
    protected function normalize_counts(
        array $counts
    ): array {
        $normalized = [];

        foreach ($counts as $status => $count) {

            $status =
                trim(
                    (string) $status
                );

            if ($status === '') {
                continue;
            }

            $normalized[$status] =
                max(
                    0,
                    (int) $count
                );
        }

        return $normalized;
    }
}