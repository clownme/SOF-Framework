<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Delivery Queue Repository
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Infrastructure
 *
 * Repository:
 *     Communication Delivery Queue
 *
 * Purpose:
 *     Persist and manage recipient delivery queue records.
 *
 * Responsibilities:
 *     - Queue Communication recipients
 *     - Retrieve queue records
 *     - Claim pending delivery work
 *     - Record successful delivery
 *     - Record failed delivery
 *     - Count delivery states
 *
 * Does NOT:
 *     - Discover Communication audiences
 *     - Determine recipient eligibility
 *     - Deliver Communications
 *     - Communicate directly with providers
 *     - Render Presentation
 *
 * ============================================================
 */

class SOF_CommunicationDeliveryQueueRepository
{
    /**
     * Return the Communication delivery queue table name.
     */
    protected function table_name(): string
    {
        return 'wp_sof_communication_delivery_queue';
    }

    /**
     * Queue one recipient.
     */
    public function create(
        SOF_CommunicationDeliveryQueueItem $item
    ): ?int {
        global $wpdb;

        $data =
            $item->to_array();

        unset($data['id']);

        $inserted =
            $wpdb->insert(
                $this->table_name(),
                $data
            );

        if ($inserted === false) {
            return null;
        }

        $queue_id =
            (int) $wpdb->insert_id;

        return $queue_id > 0
            ? $queue_id
            : null;
    }

    /**
     * Find one queue item.
     */
    public function find(
        int $queue_id
    ): ?SOF_CommunicationDeliveryQueueItem {
        global $wpdb;

        if ($queue_id < 1) {
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
                    $queue_id
                ),
                ARRAY_A
            );

        if (!$row) {
            return null;
        }

        return new SOF_CommunicationDeliveryQueueItem(
            $row
        );
    }

    /**
     * Return all queue items for a Communication.
     *
     * @return SOF_CommunicationDeliveryQueueItem[]
     */
    public function find_for_communication(
        int $communication_id
    ): array {
        global $wpdb;

        if ($communication_id < 1) {
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
                    WHERE communication_id = %d
                    ORDER BY id ASC
                    ",
                    $communication_id
                ),
                ARRAY_A
            );

        if (!$rows) {
            return [];
        }

        return array_map(
            static function (
                array $row
            ): SOF_CommunicationDeliveryQueueItem {
                return new SOF_CommunicationDeliveryQueueItem(
                    $row
                );
            },
            $rows
        );
    }

    /**
     * Claim the next pending queue items.
     *
     * @return SOF_CommunicationDeliveryQueueItem[]
     */
    public function claim_pending_batch(
        int $communication_id,
        int $limit = 25
    ): array {
        global $wpdb;

        $communication_id =
            max(
                0,
                $communication_id
            );

        $limit =
            max(
                1,
                min(
                    100,
                    $limit
                )
            );

        if ($communication_id < 1) {
            return [];
        }

        $table =
            $this->table_name();

        $ids =
            $wpdb->get_col(
                $wpdb->prepare(
                    "
                    SELECT id
                    FROM {$table}
                    WHERE communication_id = %d
                      AND status = %s
                    ORDER BY id ASC
                    LIMIT %d
                    ",
                    $communication_id,
                    SOF_CommunicationDeliveryQueueItem::STATUS_PENDING,
                    $limit
                )
            );

        if (!$ids) {
            return [];
        }

        $claimed_ids = [];

        foreach ($ids as $queue_id) {

            $queue_id =
                (int) $queue_id;

            $updated =
                $wpdb->query(
                    $wpdb->prepare(
                        "
                        UPDATE {$table}
                        SET status = %s,
                            attempts = attempts + 1,
                            started_at =
                                COALESCE(
                                    started_at,
                                    %s
                                ),
                            last_attempt_at = %s,
                            error_message = NULL
                        WHERE id = %d
                          AND status = %s
                        ",
                        SOF_CommunicationDeliveryQueueItem::STATUS_PROCESSING,
                        current_time('mysql'),
                        current_time('mysql'),
                        $queue_id,
                        SOF_CommunicationDeliveryQueueItem::STATUS_PENDING
                    )
                );

            if ($updated === 1) {
                $claimed_ids[] =
                    $queue_id;
            }
        }

        if (!$claimed_ids) {
            return [];
        }

        $items = [];

        foreach ($claimed_ids as $queue_id) {

            $item =
                $this->find(
                    $queue_id
                );

            if ($item) {
                $items[] =
                    $item;
            }
        }

        return $items;
    }

    /**
     * Mark one queue item successfully delivered.
     */
    public function mark_sent(
        int $queue_id,
        string $provider_message_id = ''
    ): bool {
        global $wpdb;

        if ($queue_id < 1) {
            return false;
        }

        $updated =
            $wpdb->update(
                $this->table_name(),
                [
                    'status' =>
                        SOF_CommunicationDeliveryQueueItem::STATUS_SENT,

                    'provider_message_id' =>
                        $provider_message_id !== ''
                            ? $provider_message_id
                            : null,

                    'error_message' =>
                        null,

                    'sent_at' =>
                        current_time('mysql'),

                    'last_attempt_at' =>
                        current_time('mysql'),
                ],
                [
                    'id' =>
                        $queue_id,

                    'status' =>
                        SOF_CommunicationDeliveryQueueItem::STATUS_PROCESSING,
                ]
            );

        return $updated !== false;
    }

    /**
     * Mark one queue item as failed.
     */
    public function mark_failed(
        int $queue_id,
        string $error_message
    ): bool {
        global $wpdb;

        if ($queue_id < 1) {
            return false;
        }

        $updated =
            $wpdb->update(
                $this->table_name(),
                [
                    'status' =>
                        SOF_CommunicationDeliveryQueueItem::STATUS_FAILED,

                    'error_message' =>
                        $error_message,

                    'last_attempt_at' =>
                        current_time('mysql'),
                ],
                [
                    'id' =>
                        $queue_id,

                    'status' =>
                        SOF_CommunicationDeliveryQueueItem::STATUS_PROCESSING,
                ]
            );

        return $updated !== false;
    }

    /**
     * Count queue items in one status.
     */
    public function count_by_status(
        int $communication_id,
        string $status
    ): int {
        global $wpdb;

        if ($communication_id < 1) {
            return 0;
        }

        $table =
            $this->table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$table}
                WHERE communication_id = %d
                  AND status = %s
                ",
                $communication_id,
                $status
            )
        );
    }

    /**
     * Count every queue item for one Communication.
     */
    public function count_for_communication(
        int $communication_id
    ): int {
        global $wpdb;

        if ($communication_id < 1) {
            return 0;
        }

        $table =
            $this->table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*)
                FROM {$table}
                WHERE communication_id = %d
                ",
                $communication_id
            )
        );
    }

    /**
     * Return Communication IDs that still have pending delivery work.
     *
     * @return int[]
     */
    public function find_communication_ids_with_pending_work(): array
    {
        global $wpdb;

        $table =
            $this->table_name();

        $ids =
            $wpdb->get_col(
                $wpdb->prepare(
                    "
                    SELECT DISTINCT communication_id
                    FROM {$table}
                    WHERE status = %s
                    ORDER BY communication_id ASC
                    ",
                    SOF_CommunicationDeliveryQueueItem::STATUS_PENDING
                )
            );

        if (!$ids) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    'intval',
                    $ids
                )
            )
        );
    }
}