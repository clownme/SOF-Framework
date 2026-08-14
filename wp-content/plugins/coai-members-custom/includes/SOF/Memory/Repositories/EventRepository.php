<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Event Repository
 * ============================================================
 *
 * Framework:
 *     Memory
 *
 * Layer:
 *     Infrastructure
 *
 * Repository:
 *     Event
 *
 * Purpose:
 *     Store and retrieve immutable organizational Events.
 *
 * Responsibilities:
 *     - Append new Events to persistent storage
 *     - Retrieve an Event by persistent identity
 *     - Retrieve Events associated with a business object
 *     - Translate storage records into Event objects
 *     - Preserve chronological Event order
 *
 * Does NOT:
 *     - Update Events
 *     - Delete Events
 *     - Interpret Events
 *     - Assemble organizational Memory
 *     - Assess Situations
 *     - Recommend business actions
 *     - Render presentation
 *
 * Principle:
 *     Events are appended.
 *     Events are never rewritten.
 *
 * ============================================================
 */

class SOF_EventRepository
{
    /**
     * Return the Events table name.
     */
    protected function table_name(): string
    {
        return 'wp_sof_events';
    }

    /**
     * Append a new Event.
     *
     * Returns the persistent Event identity when successful.
     */
    public function create(
        SOF_Event $event
    ): ?int {
        global $wpdb;

        if (!$event->is_valid()) {
            return null;
        }

        $metadata =
            wp_json_encode(
                $event->get_metadata()
            );

        if ($metadata === false) {
            $metadata = '{}';
        }

        $inserted =
            $wpdb->insert(
                $this->table_name(),
                [
                    'domain' =>
                        $event->get_domain(),

                    'entity_type' =>
                        $event->get_entity_type(),

                    'entity_id' =>
                        $event->get_entity_id(),

                    'event_type' =>
                        $event->get_event_type(),

                    'actor_id' =>
                        $event->get_actor_id(),

                    'occurred_at' =>
                        $event->get_occurred_at(),

                    'summary' =>
                        $event->get_summary(),

                    'metadata' =>
                        $metadata,

                    'created_at' =>
                        current_time('mysql'),
                ],
                [
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );

        if ($inserted === false) {
            return null;
        }

        $event_id =
            (int) $wpdb->insert_id;

        return $event_id > 0
            ? $event_id
            : null;
    }

    /**
     * Find an Event by persistent identity.
     */
    public function find(
        int $event_id
    ): ?SOF_Event {
        global $wpdb;

        if ($event_id < 1) {
            return null;
        }

        $table =
            $this->table_name();

        $row =
            $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$table}
                    WHERE id = %d
                    LIMIT 1
                    ",
                    $event_id
                ),
                ARRAY_A
            );

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Retrieve all Events associated with one business object.
     *
     * Events are returned in chronological order.
     *
     * @return SOF_Event[]
     */
    public function find_for_entity(
        string $domain,
        string $entity_type,
        int $entity_id
    ): array {
        global $wpdb;

        $domain =
            sanitize_key($domain);

        $entity_type =
            sanitize_key($entity_type);

        if (
            $domain === ''
            || $entity_type === ''
            || $entity_id < 1
        ) {
            return [];
        }

        $table =
            $this->table_name();

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$table}
                    WHERE domain = %s
                      AND entity_type = %s
                      AND entity_id = %d
                    ORDER BY occurred_at ASC,
                             id ASC
                    ",
                    $domain,
                    $entity_type,
                    $entity_id
                ),
                ARRAY_A
            );

        if (!$rows) {
            return [];
        }

        return array_map(
            function (
                array $row
            ): SOF_Event {
                return $this->hydrate($row);
            },
            $rows
        );
    }

    /**
     * Translate a persistent storage row into an Event.
     *
     * @param array<string, mixed> $row
     */
    protected function hydrate(
        array $row
    ): SOF_Event {
        $metadata =
            $row['metadata']
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

        $row['metadata'] =
            $metadata;

        return new SOF_Event($row);
    }
}