<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Model
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Model:
 *     Communication
 *
 * Purpose:
 *     Represent information intended to be delivered to an
 *     audience through one or more communication channels.
 *
 * Responsibilities:
 *     - Describe the communication content
 *     - Identify the intended audience
 *     - Record communication ownership
 *     - Track communication lifecycle status
 *     - Store delivery summary information
 *
 * Does NOT:
 *     - Determine audience membership
 *     - Query member records
 *     - Deliver email or other messages
 *     - Select a communication provider
 *     - Render presentation components
 *
 * ============================================================
 */

class SOF_Communication
{
    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

    /**
     * Communication identifier.
     */
    protected ?int $id = null;

    /**
     * Business communication type.
     *
     * Examples:
     *
     * regional_update
     * membership_reminder
     * event_announcement
     * magazine_notification
     */
    protected string $type = 'general';

    /**
     * Current communication lifecycle status.
     *
     * Lifecycle:
     *
     * draft
     * composed
     * verified
     * tested
     * approved
     * sending
     * sent
     * completed
     *
     * Exception states:
     *
     * verification_failed
     * test_failed
     * delivery_failed
     * cancelled
     * archived
     */
    protected string $status = 'draft';

    // -------------------------------------------------
    // Content
    // -------------------------------------------------

    /**
     * Communication subject or title.
     */
    protected string $subject = '';

    /**
     * Primary communication body.
     */
    protected string $body = '';

    /**
     * Optional communication summary.
     */
    protected string $summary = '';

    // -------------------------------------------------
    // Audience
    // -------------------------------------------------
   
    /**
     * Business audience identifier.
     *
     * Identifies the organizational audience intended
     * to receive this communication.
     *
     * Examples:
     *
     * regional_active_members
     * regional_officers
     * all_active_members
     */
    protected string $audience_key = '';

    /**
     * User-friendly audience name.
     *
     * Examples:
     *
     * South Central Region
     * International Region
     * All Active Members
     */
    protected string $audience_name = '';
    
    /**
     * Organizational scope of the intended audience.
     *
     * Example:
     *
     * South Central Region
     */
    protected string $audience_region = '';

    /**
     * Membership statuses intentionally included
     * in this Communication audience.
     *
     * @var array<int, string>
     */
    protected array $membership_statuses = ['Active'];

    /**
     * Number of recipients resolved for the audience.
     */
    protected int $recipient_count = 0;

    // -------------------------------------------------
    // Delivery
    // -------------------------------------------------

    /**
     * Intended delivery channel.
     *
     * Examples:
     *
     * email
     * portal
     * sms
     * print
     */
    protected string $channel = 'email';

    /**
     * Provider used to deliver the communication.
     */
    protected string $provider = '';

    /**
     * Scheduled delivery date and time.
     */
    protected ?string $scheduled_at = null;

    /**
     * Actual delivery date and time.
     */
    protected ?string $sent_at = null;
    
    // -------------------------------------------------
    // Verification and Approval
    // -------------------------------------------------
   
    /**
     * Date and time the communication was verified.
     */
    protected ?string $verified_at = null;

    /**
     * WordPress user ID of the person who verified it.
     */
    protected ?int $verified_by = null;

    /**
     * Date and time the test communication was delivered.
     */
    protected ?string $tested_at = null;

    /**
     * WordPress user ID that received or initiated the test.
     */
    protected ?int $tested_by = null;

    /**
     * Email address used for the test delivery.
     */
    protected string $test_recipient = '';

    /**
     * Date and time the communication was approved.
     */
    protected ?string $approved_at = null;

    /**
     * WordPress user ID of the person who approved delivery.
     */
    protected ?int $approved_by = null;

    // -------------------------------------------------
    // Ownership
    // -------------------------------------------------

    /**
     * WordPress user ID of the communication creator.
     */
    protected ?int $created_by = null;

    /**
     * Communication creation date and time.
     */
    protected ?string $created_at = null;
    
    /**
     * Communication last updated date and time.
     */
    protected ?string $updated_at = null;

    // -------------------------------------------------
    // Delivery Results
    // -------------------------------------------------

    /**
     * Number of successful deliveries.
     */
    protected int $delivered_count = 0;

    /**
     * Number of failed deliveries.
     */
    protected int $failed_count = 0;

    // -------------------------------------------------
    // Constructor
    // -------------------------------------------------

    /**
     * Create a Communication model.
     *
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->id = isset($data['id'])
            ? (int) $data['id']
            : null;

        $this->type = sanitize_key(
            (string) ($data['type'] ?? 'general')
        );

        $this->status = sanitize_key(
            (string) ($data['status'] ?? 'draft')
        );

        $this->subject = sanitize_text_field(
            (string) ($data['subject'] ?? '')
        );

        $this->body = isset($data['body'])
            ? wp_kses_post((string) $data['body'])
            : '';

        $this->summary = sanitize_textarea_field(
            (string) ($data['summary'] ?? '')
        );

        $this->audience_key = sanitize_key(
            (string) ($data['audience_key'] ?? '')
        );

        $this->audience_name = sanitize_text_field(
            (string) ($data['audience_name'] ?? '')
        );
        
        $this->audience_region = sanitize_text_field(
            (string) ($data['audience_region'] ?? '')
        );
        
        $membership_statuses =
            $data['membership_statuses'] ?? ['Active'];

        if (is_string($membership_statuses)) {

            $decoded_statuses =
                json_decode(
                    $membership_statuses,
                    true
                );

            $membership_statuses =
                is_array($decoded_statuses)
                    ? $decoded_statuses
                    : ['Active'];
        }

        $this->membership_statuses =
            $this->normalize_membership_statuses(
                is_array($membership_statuses)
                    ? $membership_statuses
                    : ['Active']
            );

        $this->recipient_count = max(
            0,
            (int) ($data['recipient_count'] ?? 0)
        );

        $this->channel = sanitize_key(
            (string) ($data['channel'] ?? 'email')
        );

        $this->provider = sanitize_key(
            (string) ($data['provider'] ?? '')
        );

        $this->scheduled_at = $this->nullable_string(
            $data['scheduled_at'] ?? null
        );

        $this->sent_at = $this->nullable_string(
            $data['sent_at'] ?? null
        );
        
        $this->verified_at = $this->nullable_string(
        $data['verified_at'] ?? null
        );

        $this->verified_by = isset($data['verified_by'])
            ? (int) $data['verified_by']
            : null;

        $this->tested_at = $this->nullable_string(
            $data['tested_at'] ?? null
        );

        $this->tested_by = isset($data['tested_by'])
            ? (int) $data['tested_by']
            : null;

        $this->test_recipient = sanitize_email(
            (string) ($data['test_recipient'] ?? '')
        );

        $this->approved_at = $this->nullable_string(
            $data['approved_at'] ?? null
        );

        $this->approved_by = isset($data['approved_by'])
            ? (int) $data['approved_by']
            : null;

        $this->created_by = isset($data['created_by'])
            ? (int) $data['created_by']
            : null;

        $this->created_at = $this->nullable_string(
            $data['created_at'] ?? null
        );

        $this->updated_at = $this->nullable_string(
            $data['updated_at'] ?? null
        );

        $this->delivered_count = max(
            0,
            (int) ($data['delivered_count'] ?? 0)
        );

        $this->failed_count = max(
            0,
            (int) ($data['failed_count'] ?? 0)
        );
    }

    // -------------------------------------------------
    // Identity
    // -------------------------------------------------

    public function get_id(): ?int
    {
        return $this->id;
    }

    public function get_type(): string
    {
        return $this->type;
    }

    public function get_status(): string
    {
        return $this->status;
    }

    // -------------------------------------------------
    // Content
    // -------------------------------------------------

    public function get_subject(): string
    {
        return $this->subject;
    }

    public function get_body(): string
    {
        return $this->body;
    }
    
    public function set_subject(
        string $subject
    ): void {
        $this->subject =
            sanitize_text_field(
                $subject
            );

        $this->touch();
    }

    public function set_body(
        string $body
    ): void {
        $this->body =
            wp_kses_post(
                $body
            );
    
        $this->touch();
    }

    public function get_summary(): string
    {
        return $this->summary;
    }

    // -------------------------------------------------
    // Audience
    // -------------------------------------------------

    public function get_audience_key(): string
    {
        return $this->audience_key;
    }

    public function get_audience_name(): string
    {
        return $this->audience_name;
    }
    
    public function get_audience_region(): string
    {
        return $this->audience_region;
    }

    /**
     * @return array<int, string>
     */
    public function get_membership_statuses(): array
    {
        return $this->membership_statuses;
    }

    public function includes_membership_status(
        string $status
    ): bool {
        foreach ($this->membership_statuses as $included_status) {

            if (
                strcasecmp(
                    $included_status,
                    trim($status)
                ) === 0
           ) {
                return true;
           }
        }

        return false;
    }

    public function get_recipient_count(): int
    {
        return $this->recipient_count;
    }

    // -------------------------------------------------
    // Delivery
    // -------------------------------------------------

    public function get_channel(): string
    {
        return $this->channel;
    }

    public function get_provider(): string
    {
        return $this->provider;
    }

    public function get_scheduled_at(): ?string
    {
        return $this->scheduled_at;
    }

    public function get_sent_at(): ?string
    {
        return $this->sent_at;
    }
    
    // -------------------------------------------------
    // Verification and Approval
    // -------------------------------------------------

    public function get_verified_at(): ?string
    {
        return $this->verified_at;
    }

    public function get_verified_by(): ?int
    {
        return $this->verified_by;
    }

    public function get_tested_at(): ?string
    {
        return $this->tested_at;
    }

    public function get_tested_by(): ?int
    {
        return $this->tested_by;
    }

    public function get_test_recipient(): string
    {
        return $this->test_recipient;
    }

    public function get_approved_at(): ?string
    {
        return $this->approved_at;
    }

    public function get_approved_by(): ?int
    {
        return $this->approved_by;
    }

    // -------------------------------------------------
    // Ownership
    // -------------------------------------------------

    public function get_created_by(): ?int
    {
        return $this->created_by;
    }

    public function get_created_at(): ?string
    {
        return $this->created_at;
    }

    public function get_updated_at(): ?string
    {
        return $this->updated_at;
    }

    // -------------------------------------------------
    // Delivery Results
    // -------------------------------------------------

    public function get_delivered_count(): int
    {
        return $this->delivered_count;
    }

    public function get_failed_count(): int
    {
        return $this->failed_count;
    }

    // -------------------------------------------------
    // Lifecycle
    // -------------------------------------------------

    public function is_draft(): bool
    {
        return $this->status === 'draft';
    }

    public function is_composed(): bool
    {
        return $this->status === 'composed';
    }

    public function is_verified(): bool
    {
        return in_array(
            $this->status,
            [
                'verified',
                'tested',
                'approved',
                'sending',
                'sent',
                'completed',
            ],
            true
        );
    }
 
    public function is_tested(): bool
    {
        return in_array(
            $this->status,
            [
                'tested',
                'approved',
                'sending',
                'sent',
               'completed',
            ],
            true
        );
    }

    public function is_approved(): bool
    {
        return in_array(
            $this->status,
            [
                'approved',
                'sending',
                'sent',
                'completed',
            ],
            true
        );
    }

    public function is_sending(): bool
    {
        return $this->status === 'sending';
    }

    public function is_sent(): bool
    {
        return in_array(
            $this->status,
            ['sent', 'completed'],
            true
        );
    }

    public function is_completed(): bool
    {
        return $this->status === 'completed';
    }

    public function has_failures(): bool
    {
        return $this->failed_count > 0;
    }
    
    // -------------------------------------------------
    // Lifecycle Transitions
    // -------------------------------------------------

    public function mark_composed(): void
    {
        $this->status = 'composed';
        $this->touch();
    }

    public function mark_verified(int $user_id): void
    {
        $this->status = 'verified';
        $this->verified_by = $user_id;
        $this->verified_at = current_time('mysql');
        $this->touch();
    }

    public function mark_verification_failed(): void
    {
        $this->status = 'verification_failed';
        $this->touch();
    }

    public function mark_tested(
        int $user_id,
        string $recipient
    ): void {
        $this->status = 'tested';
        $this->tested_by = $user_id;
        $this->tested_at = current_time('mysql');
        $this->test_recipient = sanitize_email($recipient);
        $this->touch();
    }

    public function mark_test_failed(): void
    {
        $this->status = 'test_failed';
        $this->touch();
    }
    
    public function return_for_revision(): void
    {
        $this->status = 'composed';

        $this->verified_at = null;
        $this->verified_by = null;

        $this->tested_at = null;
        $this->tested_by = null;
        $this->test_recipient = '';

        $this->approved_at = null;
        $this->approved_by = null;

        $this->touch();
    }

    public function mark_approved(int $user_id): void
    {
        $this->status = 'approved';
        $this->approved_by = $user_id;
        $this->approved_at = current_time('mysql');
        $this->touch();
    }

    public function mark_sending(): void
    {
        $this->status = 'sending';
        $this->touch();
    }

    public function mark_sent(
        int $delivered_count,
        int $failed_count = 0
    ): void {
        $this->status = 'sent';
        $this->sent_at = current_time('mysql');
        $this->delivered_count = max(0, $delivered_count);
        $this->failed_count = max(0, $failed_count);
        $this->touch();
    }

    public function mark_delivery_failed(
        int $failed_count = 0
    ): void {
        $this->status = 'delivery_failed';
        $this->failed_count = max(0, $failed_count);
        $this->touch();
    }

    public function mark_completed(): void
    {
        $this->status = 'completed';
        $this->touch();
    }

    public function mark_cancelled(): void
    {
        $this->status = 'cancelled';
        $this->touch();
    }

    public function mark_archived(): void
    {
        $this->status = 'archived';
        $this->touch();
    }

    // -------------------------------------------------
    // Serialization
    // -------------------------------------------------

    /**
     * Convert the model to an array.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'id'                  => $this->id,
            'type'                => $this->type,
            'status'              => $this->status,
            'subject'             => $this->subject,
            'body'                => $this->body,
            'summary'             => $this->summary,
            'audience_key'        => $this->audience_key,
            'audience_name'       => $this->audience_name,
            'audience_region'     => $this->audience_region,
            'membership_statuses' => wp_json_encode(
                $this->membership_statuses
            ),
            'recipient_count'     => $this->recipient_count,
            'channel'             => $this->channel,
            'provider'            => $this->provider,
            'scheduled_at'        => $this->scheduled_at,
            'sent_at'             => $this->sent_at,
            'verified_at'         => $this->verified_at,
            'verified_by'         => $this->verified_by,
            'tested_at'           => $this->tested_at,
            'tested_by'           => $this->tested_by,
            'test_recipient'      => $this->test_recipient,
            'approved_at'         => $this->approved_at,
            'approved_by'         => $this->approved_by,
            'created_by'          => $this->created_by,
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
            'delivered_count'     => $this->delivered_count,
            'failed_count'        => $this->failed_count,
        ];
    }

    // -------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------

    /**
     * Normalize membership statuses allowed in normal
     * Communication audiences.
     *
     * Deceased is intentionally excluded.
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

        return $normalized ?: ['Active'];
    }
    
    /**
     * Convert an optional value to a nullable string.
     *
     * @param mixed $value
     */
    protected function nullable_string($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Record that the communication was changed.
     */
    protected function touch(): void
    {
        $this->updated_at = current_time('mysql');

        if ($this->created_at === null) {
            $this->created_at = $this->updated_at;
        }
    }
}