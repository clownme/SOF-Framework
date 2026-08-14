<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Audience Model
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication Audience
 *
 * Purpose:
 *     Represent the business audience intended to receive a
 *     communication.
 *
 * Responsibilities:
 *     - Identify the communication audience
 *     - Provide a user-friendly audience name
 *     - Describe the audience
 *     - Record the resolved recipient count
 *     - Record the organizational scope of the audience
 *
 * Does NOT:
 *     - Query member records
 *     - Determine the current user's assignment
 *     - Resolve audience membership
 *     - Apply communication permissions
 *     - Deliver communications
 *     - Render presentation components
 *
 * ============================================================
 */

class SOF_CommunicationAudience
{
    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

    /**
     * Business audience identifier.
     *
     * Examples:
     *
     * regional_active_members
     * regional_officers
     * all_active_members
     */
    protected string $key;

    /**
     * User-friendly audience name.
     *
     * Example:
     *
     * North Central Region
     */
    protected string $name;

    /**
     * User-friendly audience description.
     *
     * Example:
     *
     * North Central Region members
     */
    protected string $description;

    // -------------------------------------------------
    // Organizational Scope
    // -------------------------------------------------

    /**
     * Organization region associated with the audience.
     */
    protected string $region;

    // -------------------------------------------------
    // Membership Status Intent
    // -------------------------------------------------

    /**
     * Membership statuses intentionally included
     * in this Communication audience.
     *
     * Examples:
     *
     * Active
     * Expired
     * Archived
     */
    protected array $membership_statuses;
    
    // -------------------------------------------------
    // Audience Resolution
    // -------------------------------------------------

    /**
     * Number of recipients currently resolved for the audience.
     */
    protected int $recipient_count;

    /**
     * Whether the audience has been successfully resolved.
     */
    protected bool $resolved;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    public function __construct(
        string $key,
        string $name,
        string $description,
        string $region = '',
        array $membership_statuses = ['Active'],
        int $recipient_count = 0,
        bool $resolved = false
    ) {
        $this->key = trim($key);
        $this->name = trim($name);
        $this->description = trim($description);
        $this->region = trim($region);

        $this->membership_statuses =
            $this->normalize_membership_statuses(
                $membership_statuses
            );

        $this->recipient_count = max(0, $recipient_count);
        $this->resolved = $resolved;
    }

    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

    public function get_key(): string
    {
        return $this->key;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_description(): string
    {
        return $this->description;
    }

    // -------------------------------------------------
    // Organizational Scope
    // -------------------------------------------------

    public function get_region(): string
    {
        return $this->region;
    }
    
    // -------------------------------------------------
    // Membership Status Intent
    // -------------------------------------------------

    /**
     * Return the membership statuses included
     * in this audience.
     *
     * @return array<int, string>
     */
    public function get_membership_statuses(): array
    {
        return $this->membership_statuses;
    }

    /**
     * Determine whether this audience includes
     * a membership status.
     */
    public function includes_membership_status(
        string $status
    ): bool {
        $status =
           strtolower(
                trim($status)
            );

        foreach ($this->membership_statuses as $included_status) {

            if (
                strtolower($included_status) === $status
            ) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------
    // Audience Resolution
    // -------------------------------------------------

    public function get_recipient_count(): int
    {
        return $this->recipient_count;
    }

    public function is_resolved(): bool
    {
        return $this->resolved;
    }

    public function has_recipients(): bool
    {
        return $this->recipient_count > 0;
    }

    // -------------------------------------------------
    // Presentation Information
    // -------------------------------------------------

    /**
     * Return a readable recipient-count label.
     *
     * Examples:
     *
     * 1 Member
     * 426 Members
     */
    public function get_recipient_count_label(): string
    {
        $label = $this->recipient_count === 1
            ? 'Member'
            : 'Members';

        return number_format_i18n($this->recipient_count) .
            ' ' .
            $label;
    }
    
    // -------------------------------------------------
    // Membership Status Normalization
    // -------------------------------------------------

    /**
     * Normalize the membership statuses carried
     * by the audience.
     *
     * Deceased is intentionally excluded from
     * normal Communication audiences.
     *
     * @param array<int, mixed> $statuses
     *
     * @return array<int, string>
     */
    protected function normalize_membership_statuses(
        array $statuses
    ): array {
        $allowed = [
            'Active',
            'Expired',
            'Archived',
       ];

        $normalized = [];

        foreach ($statuses as $status) {

            $status =
                trim(
                    (string) $status
                );

            foreach ($allowed as $allowed_status) {

                if (
                    strcasecmp(
                        $status,
                        $allowed_status
                    ) === 0
                ) {
                    $normalized[] =
                        $allowed_status;

                    break;
                }
           }
        }

        $normalized =
            array_values(
                array_unique(
                    $normalized
                )
            );

        /*
         * Preserve existing behavior when no valid
         * membership status was supplied.
         */

        if (!$normalized) {
            return ['Active'];
        }

        return $normalized;
    }

    // -------------------------------------------------
    // Serialization
    // -------------------------------------------------

    /**
     * Convert the audience to a transferable array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'key'                 => $this->key,
            'name'                => $this->name,
            'description'         => $this->description,
            'region'              => $this->region,
            'membership_statuses' => $this->membership_statuses,
            'recipient_count'     => $this->recipient_count,
            'resolved'            => $this->resolved,
        ];
    }
}