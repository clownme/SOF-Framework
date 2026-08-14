<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Event
 * ============================================================
 *
 * Framework:
 *     Memory
 *
 * Model:
 *     Event
 *
 * Purpose:
 *     Represent a meaningful occurrence within the
 *     organization.
 *
 * Responsibilities:
 *     - Identify the business domain in which something occurred
 *     - Identify the business object affected by the occurrence
 *     - Describe what happened
 *     - Identify who caused or recorded the occurrence
 *     - Record when the occurrence happened
 *     - Preserve a human-readable summary
 *     - Preserve supporting event metadata
 *
 * Does NOT:
 *     - Interpret the meaning of an Event
 *     - Assess the current Situation
 *     - Recommend a business response
 *     - Assemble a Timeline
 *     - Construct organizational Memory
 *     - Render presentation
 *
 * Principle:
 *     Events record what happened.
 *     Events do not determine what it means.
 *
 * ============================================================
 */

class SOF_Event
{
    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

    /**
     * Persistent Event identifier.
     */
    protected ?int $id = null;

    /**
     * Business domain in which the Event occurred.
     *
     * Examples:
     *
     * communications
     * membership
     * access
     * identity
     */
    protected string $domain = '';

    /**
     * Type of business object affected by the Event.
     *
     * Examples:
     *
     * communication
     * member
     * access_grant
     * person
     */
    protected string $entity_type = '';

    /**
     * Persistent identity of the affected business object.
     */
    protected int $entity_id = 0;

    /**
     * Stable business identifier describing what happened.
     *
     * Examples:
     *
     * communication.created
     * communication.approved
     * communication.delivered
     * membership.renewed
     * access.granted
     */
    protected string $event_type = '';

    // -------------------------------------------------
    // Occurrence
    // -------------------------------------------------

    /**
     * WordPress user ID of the person associated with
     * the Event.
     */
    protected ?int $actor_id = null;

    /**
     * Date and time the Event occurred.
     */
    protected string $occurred_at = '';

    // -------------------------------------------------
    // Meaningful Description
    // -------------------------------------------------

    /**
     * Human-readable explanation of what happened.
     */
    protected string $summary = '';

    /**
     * Additional structured facts associated with the Event.
     *
     * Metadata supplements the Event but does not change its
     * identity or meaning.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Construct an Event from supplied Event information.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(
        array $data = []
    ) {
        $this->id =
            isset($data['id'])
                ? (int) $data['id']
                : null;

        if (
            $this->id !== null
            && $this->id < 1
        ) {
            $this->id = null;
        }

        $this->domain =
            $this->normalize_key(
                $data['domain'] ?? ''
            );

        $this->entity_type =
            $this->normalize_key(
                $data['entity_type'] ?? ''
            );

        $this->entity_id =
            max(
                0,
                (int) ($data['entity_id'] ?? 0)
            );

        $this->event_type =
            $this->normalize_key(
                $data['event_type'] ?? ''
            );

        $this->actor_id =
            isset($data['actor_id'])
                ? (int) $data['actor_id']
                : null;

        if (
            $this->actor_id !== null
            && $this->actor_id < 1
        ) {
            $this->actor_id = null;
        }

        $this->occurred_at =
            trim(
                (string) (
                    $data['occurred_at']
                    ?? ''
                )
            );

        $this->summary =
            trim(
                (string) (
                    $data['summary']
                    ?? ''
                )
            );

        $metadata =
            $data['metadata']
            ?? [];

        if (is_string($metadata)) {
            $decoded =
                json_decode(
                    $metadata,
                    true
                );

            $metadata =
                is_array($decoded)
                    ? $decoded
                    : [];
        }

        $this->metadata =
            is_array($metadata)
                ? $metadata
                : [];
    }

    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

    public function get_id(): ?int
    {
        return $this->id;
    }

    public function get_domain(): string
    {
        return $this->domain;
    }

    public function get_entity_type(): string
    {
        return $this->entity_type;
    }

    public function get_entity_id(): int
    {
        return $this->entity_id;
    }

    public function get_event_type(): string
    {
        return $this->event_type;
    }

    // -------------------------------------------------
    // Occurrence
    // -------------------------------------------------

    public function get_actor_id(): ?int
    {
        return $this->actor_id;
    }

    public function get_occurred_at(): string
    {
        return $this->occurred_at;
    }

    // -------------------------------------------------
    // Description
    // -------------------------------------------------

    public function get_summary(): string
    {
        return $this->summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function get_metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Return one metadata value.
     *
     * @return mixed
     */
    public function get_metadata_value(
        string $key,
        $default = null
    ) {
        return
            array_key_exists(
                $key,
                $this->metadata
            )
                ? $this->metadata[$key]
                : $default;
    }

    // -------------------------------------------------
    // State
    // -------------------------------------------------

    /**
     * Determine whether the Event contains the minimum
     * information required to be recorded.
     */
    public function is_valid(): bool
    {
        return
            $this->domain !== ''
            && $this->entity_type !== ''
            && $this->entity_id > 0
            && $this->event_type !== ''
            && $this->occurred_at !== ''
            && $this->summary !== '';
    }

    /**
     * Return the Event as persistent storage data.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'id' =>
                $this->id,

            'domain' =>
                $this->domain,

            'entity_type' =>
                $this->entity_type,

            'entity_id' =>
                $this->entity_id,

            'event_type' =>
                $this->event_type,

            'actor_id' =>
                $this->actor_id,

            'occurred_at' =>
                $this->occurred_at,

            'summary' =>
                $this->summary,

            'metadata' =>
                $this->metadata,
        ];
    }

    // -------------------------------------------------
    // Normalization
    // -------------------------------------------------

    /**
     * Normalize a stable Event identifier.
     */
    protected function normalize_key(
        string $value
    ): string {
        $value =
            strtolower(
                trim($value)
            );

        $value =
            preg_replace(
                '/[^a-z0-9_.-]+/',
                '_',
                $value
            );

        return trim(
            (string) $value,
            '_'
        );
    }
}