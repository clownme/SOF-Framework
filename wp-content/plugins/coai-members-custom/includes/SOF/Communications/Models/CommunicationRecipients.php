<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recipients
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication Recipients
 *
 * Purpose:
 *     Represent the recipient population discovered for an
 *     authorized communication audience.
 *
 * Responsibilities:
 *     - Store the total recipient count
 *     - Store available recipient information
 *     - Store unavailable recipient information
 *     - Represent recipient business truth
 *
 * Does NOT:
 *     - Query member data
 *     - Validate email addresses
 *     - Assess communication readiness
 *     - Recommend an action
 *     - Apply authorization
 *     - Render presentation
 */
class SOF_CommunicationRecipients
{
    // -------------------------------------------------
    // Recipient Counts
    // -------------------------------------------------

    /**
     * Total recipients discovered for the audience.
     */
    protected int $total_count;

    /**
     * Recipients currently available to receive the
     * communication.
     */
    protected int $available_count;

    /**
     * Recipients currently unavailable to receive the
     * communication.
     */
    protected int $unavailable_count;

    // -------------------------------------------------
    // Recipient Collections
    // -------------------------------------------------

    /**
     * Recipients available to receive the communication.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $available_recipients;

    /**
     * Recipients unavailable to receive the communication.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $unavailable_recipients;

    // -------------------------------------------------
    // Construction
    // -------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $available_recipients
     * @param array<int, array<string, mixed>> $unavailable_recipients
     */
    public function __construct(
        array $available_recipients = [],
        array $unavailable_recipients = []
    ) {
        $this->available_recipients =
            array_values($available_recipients);

        $this->unavailable_recipients =
            array_values($unavailable_recipients);

        $this->available_count =
            count($this->available_recipients);

        $this->unavailable_count =
            count($this->unavailable_recipients);

        $this->total_count =
            $this->available_count +
            $this->unavailable_count;
    }

    // -------------------------------------------------
    // Recipient Counts
    // -------------------------------------------------

    public function get_total_count(): int
    {
        return $this->total_count;
    }

    public function get_available_count(): int
    {
        return $this->available_count;
    }

    public function get_unavailable_count(): int
    {
        return $this->unavailable_count;
    }

    // -------------------------------------------------
    // Recipient Collections
    // -------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_available_recipients(): array
    {
        return $this->available_recipients;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_unavailable_recipients(): array
    {
        return $this->unavailable_recipients;
    }

    // -------------------------------------------------
    // Recipient State
    // -------------------------------------------------

    public function has_recipients(): bool
    {
        return $this->total_count > 0;
    }

    public function has_available_recipients(): bool
    {
        return $this->available_count > 0;
    }

    public function has_unavailable_recipients(): bool
    {
        return $this->unavailable_count > 0;
    }

    public function all_recipients_are_available(): bool
    {
        return (
            $this->total_count > 0 &&
            $this->unavailable_count === 0
        );
    }
}