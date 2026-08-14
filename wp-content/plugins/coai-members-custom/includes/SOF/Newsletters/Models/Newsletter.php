<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Model:
 *     Newsletter
 *
 * Purpose:
 *     Represent a structured organizational Newsletter.
 *
 * Responsibilities:
 *     - Identify the Newsletter
 *     - Represent its title and subject
 *     - Represent its template
 *     - Contain Newsletter sections
 *     - Represent its current business state
 *
 * Does NOT:
 *     - Determine the Newsletter audience
 *     - Determine Access authorization
 *     - Send email
 *     - Render HTML
 *     - Persist itself
 *
 * ============================================================
 */

class SOF_Newsletter
{
    private ?int $id;

    private string $title;

    private string $subject;

    private string $template_key;
    
    private SOF_NewsletterDesign $design;

    /**
     * @var SOF_NewsletterSection[]
     */
    private array $sections;

    private string $status;
    
    private SOF_NewsletterSignature $signature;
    
    /**
     * Membership statuses intentionally included
     * in this Newsletter audience.
     *
     * @var array<int, string>
     */
    private array $membership_statuses;

    /**
     * Optional organizational scope selected within the
     * person's authorized Newsletter audience.
     *
     * Null means use the person's full authorized scope.
     */
    private ?string $audience_scope_key;

    /**
     * Recipient selection mode.
     *
     * Supported values:
     *     all
     *     selected
     */
    private string $recipient_selection_mode;

    /**
     * Member IDs intentionally selected from within the
     * authorized Newsletter audience.
     *
     * @var array<int, int>
     */
    private array $selected_member_ids;

    public function __construct(
        ?int $id,
        string $title,
        string $subject,
        string $template_key = 'standard',
        ?SOF_NewsletterDesign $design = null,
        array $sections = [],
        string $status = 'draft',
        ?SOF_NewsletterSignature $signature = null,
        array $membership_statuses = ['Active'],
        ?string $audience_scope_key = null,
        string $recipient_selection_mode = 'all',
        array $selected_member_ids = []
    ) {
        $this->id = $id;
        $this->title = trim($title);
        $this->subject = trim($subject);
        $this->template_key = trim($template_key);
        
        $this->design =
            $design ?? new SOF_NewsletterDesign();
            
        $this->sections = $sections;
        $this->status = trim($status);
        
        $this->signature =
            $signature ?? new SOF_NewsletterSignature();
            
        $this->membership_statuses =
            $this->normalize_membership_statuses(
                $membership_statuses
            );

        $this->audience_scope_key =
            $this->normalize_audience_scope_key(
                $audience_scope_key
            );

        $this->recipient_selection_mode =
            $this->normalize_recipient_selection_mode(
                $recipient_selection_mode
            );

        $this->selected_member_ids =
            $this->normalize_selected_member_ids(
                $selected_member_ids
            );
    }
    


    public function get_id(): ?int
    {
        return $this->id;
    }

    public function get_title(): string
    {
        return $this->title;
    }

    public function get_subject(): string
    {
        return $this->subject;
    }

    public function get_template_key(): string
    {
        return $this->template_key;
    }
    
    public function get_design(): SOF_NewsletterDesign
    {
        return $this->design;
    }

    /**
     * @return SOF_NewsletterSection[]
     */
    public function get_sections(): array
    {
        return $this->sections;
    }

    public function get_status(): string
    {
        return $this->status;
    }
    
    public function get_signature(): SOF_NewsletterSignature
    {
        return $this->signature;
    }
    
    /**
     * Return Membership statuses intentionally included
     * in the Newsletter audience.
     *
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
    
    public function get_audience_scope_key(): ?string
    {
        return $this->audience_scope_key;
    }

    public function get_recipient_selection_mode(): string
    {
        return $this->recipient_selection_mode;
    }

    /**
     * @return array<int, int>
     */
    public function get_selected_member_ids(): array
    {
        return $this->selected_member_ids;
    }

    public function uses_selected_members(): bool
    {
        return
            $this->recipient_selection_mode ===
                'selected';
    }

    public function add_section(
        SOF_NewsletterSection $section
    ): void {
        $this->sections[] = $section;
    }
    
    /**
     * Normalize Membership statuses allowed for Newsletter
     * audience intent.
     *
     * Deceased is intentionally excluded.
     *
     * @param array<int, mixed> $statuses
     *
     * @return array<int, string>
     */
    private function normalize_membership_statuses(
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
    
    private function normalize_audience_scope_key(
        ?string $scope_key
    ): ?string {

        if ($scope_key === null) {
            return null;
        }

        $scope_key =
            trim($scope_key);

        return
            $scope_key !== ''
                ? $scope_key
                : null;
    }

    private function normalize_recipient_selection_mode(
        string $mode
    ): string {

        $mode =
            strtolower(
                trim($mode)
            );

        return
            $mode === 'selected'
                ? 'selected'
                : 'all';
    }

    /**
     * @param array<int, mixed> $member_ids
     *
     * @return array<int, int>
     */
    private function normalize_selected_member_ids(
        array $member_ids
    ): array {

        $normalized = [];

        foreach ($member_ids as $member_id) {

            $member_id =
                (int) $member_id;

            if ($member_id > 0) {
                $normalized[] =
                    $member_id;
            }
        }

        return
            array_values(
                array_unique(
                    $normalized
                )
            );
    }
}