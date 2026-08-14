<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recipient Selection
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication Recipient Selection
 *
 * Purpose:
 *     Represent the person's decision about which eligible
 *     recipients should receive a Communication.
 *
 * Responsibilities:
 *     - Represent the recipient selection mode
 *     - Store explicitly selected member identifiers
 *     - Distinguish all eligible recipients from a selected subset
 *
 * Does NOT:
 *     - Discover recipients
 *     - Query Membership
 *     - Determine organizational authorization
 *     - Determine current recipient eligibility
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_CommunicationRecipientSelection
{
    public const MODE_ALL =
        'all';

    public const MODE_SELECTED =
        'selected';

    /**
     * Recipient selection mode.
     */
    protected string $mode;

    /**
     * Explicitly selected member identifiers.
     *
     * @var int[]
     */
    protected array $member_ids;

    /**
     * @param string $mode
     * @param int[]  $member_ids
     */
    public function __construct(
        string $mode = self::MODE_ALL,
        array $member_ids = []
    ) {
        $mode =
            strtolower(
                trim($mode)
            );

        if (
            !in_array(
                $mode,
                [
                    self::MODE_ALL,
                    self::MODE_SELECTED,
                ],
                true
            )
        ) {
            $mode =
                self::MODE_ALL;
        }

        $this->mode =
            $mode;

        $this->member_ids =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            'intval',
                            $member_ids
                        ),
                        static function (
                            int $member_id
                        ): bool {
                            return $member_id > 0;
                        }
                    )
                )
            );
    }

    // -------------------------------------------------
    // Selection Mode
    // -------------------------------------------------

    public function get_mode(): string
    {
        return $this->mode;
    }

    public function uses_all_recipients(): bool
    {
        return
            $this->mode ===
            self::MODE_ALL;
    }

    public function uses_selected_recipients(): bool
    {
        return
            $this->mode ===
            self::MODE_SELECTED;
    }

    // -------------------------------------------------
    // Selected Members
    // -------------------------------------------------

    /**
     * @return int[]
     */
    public function get_member_ids(): array
    {
        return $this->member_ids;
    }

    public function get_selected_count(): int
    {
        return count(
            $this->member_ids
        );
    }

    public function has_selected_members(): bool
    {
        return
            $this->get_selected_count() > 0;
    }

    public function includes_member(
        int $member_id
    ): bool
    {
        return in_array(
            $member_id,
            $this->member_ids,
            true
        );
    }
}