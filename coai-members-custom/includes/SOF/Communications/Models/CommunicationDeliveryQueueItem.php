<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Delivery Queue Item
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Communication Delivery Queue Item
 *
 * Purpose:
 *     Represent one recipient delivery within a queued
 *     Communication delivery.
 *
 * Responsibilities:
 *     - Identify the Communication
 *     - Identify the recipient
 *     - Preserve the delivery destination
 *     - Represent queue delivery state
 *     - Track delivery attempts
 *     - Preserve provider delivery information
 *     - Preserve delivery error information
 *
 * Does NOT:
 *     - Query recipients
 *     - Send Communications
 *     - Communicate directly with providers
 *     - Persist itself
 *     - Render Presentation
 *
 * ============================================================
 */

class SOF_CommunicationDeliveryQueueItem
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected ?int $id;

    protected int $communication_id;

    protected int $member_id;

    protected string $email;

    protected string $status;

    protected int $attempts;

    protected ?string $provider_message_id;

    protected ?string $error_message;

    protected string $created_at;

    protected ?string $started_at;

    protected ?string $last_attempt_at;

    protected ?string $sent_at;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        array $data = []
    ) {
        $this->id =
            isset($data['id'])
                ? (int) $data['id']
                : null;

        $this->communication_id =
            (int) (
                $data['communication_id']
                ?? 0
            );

        $this->member_id =
            (int) (
                $data['member_id']
                ?? 0
            );

        $this->email =
            sanitize_email(
                (string) (
                    $data['email']
                    ?? ''
                )
            );

        $this->status =
            (string) (
                $data['status']
                ?? self::STATUS_PENDING
            );

        $this->attempts =
            max(
                0,
                (int) (
                    $data['attempts']
                    ?? 0
                )
            );

        $this->provider_message_id =
            isset($data['provider_message_id']) &&
            $data['provider_message_id'] !== ''
                ? (string) $data['provider_message_id']
                : null;

        $this->error_message =
            isset($data['error_message']) &&
            $data['error_message'] !== ''
                ? (string) $data['error_message']
                : null;

        $this->created_at =
            (string) (
                $data['created_at']
                ?? current_time('mysql')
            );

        $this->started_at =
            isset($data['started_at']) &&
            $data['started_at'] !== ''
                ? (string) $data['started_at']
                : null;

        $this->last_attempt_at =
            isset($data['last_attempt_at']) &&
            $data['last_attempt_at'] !== ''
                ? (string) $data['last_attempt_at']
                : null;

        $this->sent_at =
            isset($data['sent_at']) &&
            $data['sent_at'] !== ''
                ? (string) $data['sent_at']
                : null;
    }

    public function get_id(): ?int
    {
        return $this->id;
    }

    public function get_communication_id(): int
    {
        return $this->communication_id;
    }

    public function get_member_id(): int
    {
        return $this->member_id;
    }

    public function get_email(): string
    {
        return $this->email;
    }

    public function get_status(): string
    {
        return $this->status;
    }

    public function get_attempts(): int
    {
        return $this->attempts;
    }

    public function get_provider_message_id(): ?string
    {
        return $this->provider_message_id;
    }

    public function get_error_message(): ?string
    {
        return $this->error_message;
    }

    public function get_created_at(): string
    {
        return $this->created_at;
    }

    public function get_started_at(): ?string
    {
        return $this->started_at;
    }

    public function get_last_attempt_at(): ?string
    {
        return $this->last_attempt_at;
    }

    public function get_sent_at(): ?string
    {
        return $this->sent_at;
    }

    public function is_pending(): bool
    {
        return $this->status ===
            self::STATUS_PENDING;
    }

    public function is_processing(): bool
    {
        return $this->status ===
            self::STATUS_PROCESSING;
    }

    public function is_sent(): bool
    {
        return $this->status ===
            self::STATUS_SENT;
    }

    public function is_failed(): bool
    {
        return $this->status ===
            self::STATUS_FAILED;
    }

    /**
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'id' =>
                $this->id,

            'communication_id' =>
                $this->communication_id,

            'member_id' =>
                $this->member_id,

            'email' =>
                $this->email,

            'status' =>
                $this->status,

            'attempts' =>
                $this->attempts,

            'provider_message_id' =>
                $this->provider_message_id,

            'error_message' =>
                $this->error_message,

            'created_at' =>
                $this->created_at,

            'started_at' =>
                $this->started_at,

            'last_attempt_at' =>
                $this->last_attempt_at,

            'sent_at' =>
                $this->sent_at,
        ];
    }
}