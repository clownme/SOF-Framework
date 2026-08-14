<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Repository
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Infrastructure
 *
 * Repository:
 *     Communication
 *
 * Purpose:
 *     Store and retrieve Communications from persistent
 *     storage.
 *
 * Responsibilities:
 *     - Persist Communication state
 *     - Retrieve Communications by identity
 *     - Translate storage records into Communication objects
 *     - Keep database implementation behind Communications
 *
 * Does NOT:
 *     - Compose communications
 *     - Determine audiences
 *     - Resolve recipients
 *     - Verify communications
 *     - Approve communications
 *     - Send communications
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_CommunicationRepository
{
    /**
     * Return the Communications table name.
     */
    protected function table_name(): string
    {
        return 'wp_sof_communications';
    }

    /**
     * Store a new Communication.
     *
     * Returns the persistent Communication identity when
     * successful.
     */
    public function create(
        SOF_Communication $communication
    ): ?int {
        global $wpdb;

        $data = $communication->to_array();

        unset($data['id']);

        $inserted =
            $wpdb->insert(
                $this->table_name(),
                $data
            );

        if ($inserted === false) {
            return null;
        }

        $communication_id =
            (int) $wpdb->insert_id;

        return $communication_id > 0
            ? $communication_id
            : null;
    }

    /**
     * Update an existing Communication.
     */
    public function update(
        SOF_Communication $communication
    ): bool {
        global $wpdb;

        $communication_id =
            $communication->get_id();

        if (!$communication_id) {
            return false;
        }

        $data =
            $communication->to_array();

        unset($data['id']);

        $updated =
            $wpdb->update(
                $this->table_name(),
                $data,
                [
                    'id' => $communication_id,
                ]
            );

        return $updated !== false;
    }

    /**
     * Atomically claim an approved Communication for delivery.
     *
     * Only one request may successfully transition the
     * Communication from approved to sending.
     */
    public function begin_delivery(
        int $communication_id
    ): bool {
        global $wpdb;

        if ($communication_id < 1) {
            return false;
        }

        $table =
            $this->table_name();

        $updated =
            $wpdb->query(
                $wpdb->prepare(
                    "
                    UPDATE {$table}
                    SET status = %s,
                        updated_at = %s
                    WHERE id = %d
                      AND status = %s
                    ",
                    'sending',
                    current_time('mysql'),
                    $communication_id,
                    'approved'
                )
            );

        return $updated === 1;
    }

    /**
     * Find a Communication by persistent identity.
     */
    public function find(
        int $communication_id
    ): ?SOF_Communication {
        global $wpdb;

        if ($communication_id <= 0) {
            return null;
        }

        $table = $this->table_name();

        $row =
            $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$table}
                    WHERE id = %d
                    LIMIT 1
                    ",
                    $communication_id
                ),
                ARRAY_A
            );

        if (!$row) {
            return null;
        }

        return new SOF_Communication($row);
    }

    /**
     * Find the current person's most recent active or
     * recently completed Communication delivery.
     */
    public function find_latest_delivery_for_creator(
        int $user_id
    ): ?SOF_Communication {
        global $wpdb;

        if ($user_id < 1) {
            return null;
        }

        $table =
            $this->table_name();

        $completed_since =
            gmdate(
                'Y-m-d H:i:s',
                time() - (7 * DAY_IN_SECONDS)
            );

        $row =
            $wpdb->get_row(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$table}
                    WHERE created_by = %d
                      AND (
                            status = %s
                            OR (
                                status = %s
                                AND sent_at IS NOT NULL
                                AND sent_at >= %s
                            )
                          )
                    ORDER BY id DESC
                    LIMIT 1
                    ",
                    $user_id,
                    'sending',
                    'sent',
                    $completed_since
                ),
                ARRAY_A
            );

        if (!$row) {
            return null;
        }

        return new SOF_Communication(
            $row
        );
    }
}