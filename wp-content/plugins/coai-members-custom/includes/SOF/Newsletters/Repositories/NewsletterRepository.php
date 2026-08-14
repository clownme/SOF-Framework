<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Repository
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Infrastructure
 *
 * Repository:
 *     Newsletter
 *
 * Purpose:
 *     Store and retrieve Newsletters from persistent storage.
 *
 * Responsibilities:
 *     - Persist Newsletter state
 *     - Retrieve Newsletters by identity
 *     - Preserve structured Newsletter Design
 *     - Preserve structured Newsletter Sections
 *     - Translate storage records into Newsletter objects
 *
 * Does NOT:
 *     - Compose Newsletters
 *     - Render Newsletter HTML
 *     - Determine Newsletter audiences
 *     - Determine Access authorization
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_NewsletterRepository
{
    /**
     * Return the Newsletter table name.
     */
    protected function table_name(): string
    {

        return 'wp_sof_newsletter';
    }

    /**
     * Store a new Newsletter.
     *
     * Returns the persistent Newsletter identity.
     */
    public function create(
        SOF_Newsletter $newsletter,
        ?int $created_by_person_id = null
    ): ?int {
        global $wpdb;

        $inserted =
            $wpdb->insert(
                $this->table_name(),
                [
                    'title' =>
                        $newsletter->get_title(),

                    'subject' =>
                        $newsletter->get_subject(),

                    'template_key' =>
                        $newsletter->get_template_key(),

                    'design_json' =>
                        $this->encode_design(
                            $newsletter->get_design()
                        ),

                    'sections_json' =>
                        $this->encode_sections(
                            $newsletter->get_sections()
                        ),
                        
                    'signature_json' =>
                        $this->encode_signature(
                            $newsletter->get_signature()
                        ),
                        
                    'membership_statuses' =>
                        wp_json_encode(
                            $newsletter->get_membership_statuses()
                        ),

                    'audience_scope_key' =>
                        $newsletter->get_audience_scope_key(),

                    'recipient_selection_mode' =>
                        $newsletter->get_recipient_selection_mode(),

                    'selected_member_ids' =>
                        wp_json_encode(
                            $newsletter->get_selected_member_ids()
                        ),

                    'status' =>
                        $newsletter->get_status(),

                    'created_by_person_id' =>
                        $created_by_person_id,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                ]
            );

        if ($inserted === false) {
            return null;
        }

        $newsletter_id =
            (int) $wpdb->insert_id;

        return $newsletter_id > 0
            ? $newsletter_id
            : null;
    }

    /**
     * Update an existing Newsletter.
     */
    public function update(
        SOF_Newsletter $newsletter
    ): bool {
        global $wpdb;

        $newsletter_id =
            $newsletter->get_id();

        if (!$newsletter_id) {
            return false;
        }

        $updated =
            $wpdb->update(
                $this->table_name(),
                [
                    'title' =>
                        $newsletter->get_title(),

                    'subject' =>
                        $newsletter->get_subject(),

                    'template_key' =>
                        $newsletter->get_template_key(),

                    'design_json' =>
                        $this->encode_design(
                            $newsletter->get_design()
                        ),

                    'sections_json' =>
                        $this->encode_sections(
                            $newsletter->get_sections()
                        ),

                    'signature_json' =>
                        $this->encode_signature(
                            $newsletter->get_signature()
                        ),

                    'membership_statuses' =>
                        wp_json_encode(
                            $newsletter->get_membership_statuses()
                        ),

                    'audience_scope_key' =>
                        $newsletter->get_audience_scope_key(),

                   'recipient_selection_mode' =>
                        $newsletter->get_recipient_selection_mode(),

                    'selected_member_ids' =>
                        wp_json_encode(
                            $newsletter->get_selected_member_ids()
                       ),

                    'status' =>
                        $newsletter->get_status(),
                ],
                [
                    'id' =>
                       $newsletter_id,
                ],
               [
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ],
                [
                    '%d',
                ]
            );

        return $updated !== false;
    }

    /**
     * Find a Newsletter by persistent identity.
     */
    public function find(
        int $newsletter_id
    ): ?SOF_Newsletter {
        global $wpdb;

        if ($newsletter_id <= 0) {
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
                    $newsletter_id
                ),
                ARRAY_A
            );

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }
    
    /**
     * ============================================================
     * Find Newsletters by Person
     * ============================================================
     *
     * Return the Newsletters created by the specified person.
     *
     * Results are ordered by most recently updated first so the
     * person's current work naturally appears before older work.
     *
     * @param int $person_id
     *
     * @return SOF_Newsletter[]
     */
    public function find_by_person(
        int $person_id
    ): array {

        if ($person_id <= 0) {
            return [];
        }

        global $wpdb;

        $table =
            $this->table_name();

        $rows =
            $wpdb->get_results(
                $wpdb->prepare(
                    "
                    SELECT *
                    FROM {$table}
                    WHERE created_by_person_id = %d
                    ORDER BY updated_at DESC, id DESC
                    ",
                    $person_id
                ),
                ARRAY_A
            );

        if (!$rows) {
            return [];
        }

        $newsletters = [];

        foreach ($rows as $row) {

            $newsletter =
                $this->hydrate($row);

            if ($newsletter) {
                $newsletters[] =
                    $newsletter;
            }
        }

        return $newsletters;
    }
    
    /**
     * ============================================================
     * Hydrate Newsletter
     * ============================================================
     *
     * Translate a persistent Newsletter record into the
     * Newsletter business object.
     *
     * @param array $row
     */
    private function hydrate(
        array $row
    ): SOF_Newsletter {

        $design =
            $this->decode_design(
                (string) (
                    $row['design_json']
                    ?? ''
                )
            );

        $sections =
            $this->decode_sections(
                (string) (
                    $row['sections_json']
                    ?? ''
                )
            );

        $signature =
            $this->decode_signature(
                (string) (
                    $row['signature_json']
                    ?? ''
                )
            );

        $membership_statuses =
            json_decode(
                (string) (
                    $row['membership_statuses']
                    ?? ''
                ),
                true
            );

        if (!is_array($membership_statuses)) {
            $membership_statuses = [
                'Active',
            ];
        }
        
                $audience_scope_key =
            isset($row['audience_scope_key']) &&
            trim((string) $row['audience_scope_key']) !== ''
                ? (string) $row['audience_scope_key']
                : null;

        $recipient_selection_mode =
            isset($row['recipient_selection_mode'])
                ? (string) $row['recipient_selection_mode']
                : 'all';

        $selected_member_ids =
            json_decode(
                (string) (
                    $row['selected_member_ids']
                    ?? ''
                ),
                true
            );

        if (!is_array($selected_member_ids)) {
            $selected_member_ids = [];
        }

        return new SOF_Newsletter(
            (int) (
                $row['id']
                ?? 0
            ),
            (string) (
                $row['title']
                ?? ''
            ),
            (string) (
                $row['subject']
                ?? ''
            ),
            (string) (
                $row['template_key']
                ?? ''
            ),
            $design,
            $sections,
            (string) (
                $row['status']
                ?? 'draft'
            ),
            $signature,
            $membership_statuses,
            $audience_scope_key,
            $recipient_selection_mode,
            $selected_member_ids
        );
    }

    /**
     * Encode Newsletter Design for storage.
     */
    private function encode_design(
        SOF_NewsletterDesign $design
    ): string {

        return wp_json_encode(
            [
                'background_color' =>
                    $design->get_background_color(),

                'background_image_attachment_id' =>
                    $design
                        ->get_background_image_attachment_id(),

                'background_image_url' =>
                    $design->get_background_image_url(),

                'content_background_color' =>
                    $design->get_content_background_color(),

                'header_logo_attachment_id' =>
                    $design->get_header_logo_attachment_id(),

                'header_logo_url' =>
                    $design->get_header_logo_url(),

                'header_logo_alt' =>
                    $design->get_header_logo_alt(),
            ]
        ) ?: '{}';
    }

    /**
     * Encode Newsletter Sections for storage.
     *
     * @param SOF_NewsletterSection[] $sections
     */
    private function encode_sections(
        array $sections
    ): string {

        $data = [];

        foreach ($sections as $section) {

            if (!$section instanceof SOF_NewsletterSection) {
                continue;
            }

            $data[] = [
                'type' =>
                    $section->get_type(),

                'heading' =>
                    $section->get_heading(),

                'content' =>
                    $section->get_content(),

                'image_attachment_id' =>
                    $section->get_image_attachment_id(),

                'image_url' =>
                    $section->get_image_url(),

                'image_alt' =>
                    $section->get_image_alt(),

                'image_layout' =>
                    $section->get_image_layout(),

                'link_url' =>
                    $section->get_link_url(),

                'link_label' =>
                    $section->get_link_label(),
            ];
        }

        return wp_json_encode($data) ?: '[]';
    }
    
    /**
     * Encode Newsletter Signature for storage.
     */
    private function encode_signature(
        SOF_NewsletterSignature $signature
    ): string {

        return wp_json_encode(
            [
                'name' =>
                    $signature->get_name(),

                'title' =>
                    $signature->get_title(),

                'image_attachment_id' =>
                    $signature->get_image_attachment_id(),

                'image_url' =>
                    $signature->get_image_url(),
            ]
        ) ?: '{}';
    }

    /**
     * Rebuild Newsletter Design from storage.
     */
    private function decode_design(
        string $json
    ): SOF_NewsletterDesign {

        $data =
            json_decode(
                $json,
                true
            );

        if (!is_array($data)) {
            $data = [];
        }

        return new SOF_NewsletterDesign(
            (string) (
                $data['background_color']
                ?? '#f3f4f6'
            ),
            !empty(
                $data['background_image_attachment_id']
            )
                ? (int)
                    $data['background_image_attachment_id']
                : null,
            !empty($data['background_image_url'])
                ? (string)
                    $data['background_image_url']
                : null,
            (string) (
                $data['content_background_color']
                ?? '#ffffff'
            ),
            !empty(
                $data['header_logo_attachment_id']
            )
                ? (int)
                    $data['header_logo_attachment_id']
                : null,
            !empty($data['header_logo_url'])
                ? (string)
                    $data['header_logo_url']
                : null,
            (string) (
                $data['header_logo_alt']
                ?? ''
            )
        );
    }
    
        /**
     * Rebuild Newsletter Signature from storage.
     */
    private function decode_signature(
        string $json
    ): SOF_NewsletterSignature {

        $data =
            json_decode(
                $json,
                true
            );

        if (!is_array($data)) {
            $data = [];
        }

        return new SOF_NewsletterSignature(
            (string) (
                $data['name']
                ?? ''
            ),
            (string) (
                $data['title']
                ?? ''
            ),
            !empty($data['image_attachment_id'])
                ? (int) $data['image_attachment_id']
                : null,
            !empty($data['image_url'])
                ? (string) $data['image_url']
                : null
        );
    }

    /**
     * Rebuild Newsletter Sections from storage.
     *
     * @return SOF_NewsletterSection[]
     */
    private function decode_sections(
        string $json
    ): array {

        $data =
            json_decode(
                $json,
                true
            );

        if (!is_array($data)) {
            return [];
        }

        $sections = [];

        foreach ($data as $item) {

            if (!is_array($item)) {
                continue;
            }

            $sections[] =
                new SOF_NewsletterSection(
                    (string) (
                        $item['type']
                        ?? 'story'
                    ),
                    (string) (
                        $item['heading']
                        ?? ''
                    ),
                    (string) (
                        $item['content']
                        ?? ''
                    ),
                    !empty(
                        $item['image_attachment_id']
                    )
                        ? (int)
                            $item['image_attachment_id']
                        : null,
                    !empty($item['image_url'])
                        ? (string)
                            $item['image_url']
                        : null,
                    (string) (
                        $item['image_alt']
                        ?? ''
                    ),
                    (string) (
                        $item['image_layout']
                        ?? 'above'
                    ),
                    !empty($item['link_url'])
                        ? (string)
                            $item['link_url']
                        : null,
                    !empty($item['link_label'])
                        ? (string)
                            $item['link_label']
                        : null
                );
        }

        return $sections;
    }
}