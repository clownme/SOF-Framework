<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Compose Newsletter Workspace
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Presentation
 *
 * Workspace:
 *     Compose Newsletter
 *
 * Purpose:
 *     Provide a human-friendly workspace for creating and
 *     designing a Newsletter.
 *
 * Responsibilities:
 *     - Collect Newsletter details
 *     - Collect Newsletter design choices
 *     - Collect structured Newsletter content
 *     - Build Newsletter business information
 *     - Save Newsletter drafts
 *     - Reload existing Newsletter drafts
 *     - Present a rendered preview
 *
 * Does NOT:
 *     - Determine recipients
 *     - Deliver Communications
 *     - Communicate directly with providers
 *
 * ============================================================
 */

class SOF_ComposeNewsletterWorkspace
{
    /**
     * Render the Compose Newsletter Workspace.
     */
    public static function render(): string
    {
        $newsletter_id = isset($_GET['newsletter_id'])
            ? absint($_GET['newsletter_id'])
            : 0;

        $newsletter_status = 'draft';
        
        $existing_newsletter = null;
        $newsletter = null;

        $title = '';
        $subject = '';

        $background_color = '#f3f4f6';
        $content_background_color = '#ffffff';
        
        $header_logo_attachment_id = 0;
        
        $signature_name = '';
        $signature_title = '';
        $signature_image_attachment_id = 0;
        
        $membership_statuses = [
            'Active',
        ];

        $audience_scope_key = null;

        $recipient_selection_mode = 'all';

        $selected_member_ids = [];
        
        // -------------------------------------------------
        // Suggest Signature for New Newsletter
        // -------------------------------------------------

        if ($newsletter_id <= 0) {

            $signature_service =
                new SOF_NewsletterSignatureSuggestionService();

            $suggested_signature =
                $signature_service->suggest();

            $signature_name =
                $suggested_signature->get_name();

            $signature_title =
                $suggested_signature->get_title();
        }        

        $sections = [         [
                'heading' => '',
                'content' => '',
                'image_attachment_id' => 0,
                'image_layout' => 'medium',
                'link_label' => '',
                'link_url' => '',
            ],
        ];
        
        // -------------------------------------------------
        // Load Existing Newsletter
        // -------------------------------------------------

        if ($newsletter_id > 0) {

            $repository =
                new SOF_NewsletterRepository();

            $existing_newsletter =
                $repository->find($newsletter_id);

            if ($existing_newsletter) {

                $title =
                    $existing_newsletter->get_title();

                $subject =
                    $existing_newsletter->get_subject();

                $newsletter_status =
                    $existing_newsletter->get_status();

                $membership_statuses =
                    $existing_newsletter
                        ->get_membership_statuses();
                        
                if (
                    !is_array($membership_statuses) ||
                    empty($membership_statuses)
                ) {
                    $membership_statuses = [
                        'Active',
                    ];
                }

                $audience_scope_key =
                    $existing_newsletter
                        ->get_audience_scope_key();

                $recipient_selection_mode =
                    $existing_newsletter
                        ->get_recipient_selection_mode();

                $selected_member_ids =
                    $existing_newsletter
                        ->get_selected_member_ids();
                
                $existing_design =
                    $existing_newsletter->get_design();
                $background_color =
                    $existing_design
                        ->get_background_color();


                $content_background_color =
                    $existing_design
                        ->get_content_background_color();
                        
                $header_logo_attachment_id =
                    $existing_design
                        ->get_header_logo_attachment_id() ?? 0;
                        
                $existing_signature =
                    $existing_newsletter->get_signature();

                $signature_name =
                    $existing_signature->get_name();

                $signature_title =
                    $existing_signature->get_title();

                $signature_image_attachment_id =
                    $existing_signature
                        ->get_image_attachment_id() ?? 0;

                // -------------------------------------------------
                // Suggest Signature When None Has Been Saved
                // -------------------------------------------------

                if (!$existing_signature->has_content()) {

                    $signature_service =
                        new SOF_NewsletterSignatureSuggestionService();

                    $suggested_signature =
                        $signature_service->suggest();

                    $signature_name =
                        $suggested_signature->get_name();

                    $signature_title =
                        $suggested_signature->get_title();
                }

                $sections = [];
                foreach (
                    $existing_newsletter->get_sections()
                    as $existing_section
                ) {
                    $sections[] = [
                        'heading' =>
                            $existing_section->get_heading(),

                        'content' =>
                            $existing_section->get_content(),

                        'image_attachment_id' =>
                            $existing_section
                                ->get_image_attachment_id() ?? 0,
                                
                        'image_layout' =>
                            in_array(
                                $existing_section->get_image_layout(),
                                [
                                    'small',
                                    'medium',
                                    'large',
                                    'full',
                                ],
                                true
                            )
                                ?$existing_section->get_image_layout()
                                : 'medium',

                        'link_label' =>
                            $existing_section
                                ->get_link_label() ?? '',

                        'link_url' =>
                            $existing_section
                                ->get_link_url() ?? '',
                    ];
                }

                if (empty($sections)) {
                    $sections[] = [
                        'heading' => '',
                        'content' => '',
                        'image_attachment_id' => 0,
                        'image_layout' => 'medium',
                        'link_label' => '',
                        'link_url' => '',
                    ];
                }
            }
        }        

        $newsletter_html = '';
        $save_message = '';

        // -------------------------------------------------
        // Present Existing Newsletter Preview
        // -------------------------------------------------

        if ($existing_newsletter instanceof SOF_Newsletter) {

            $renderer =
                new SOF_NewsletterHtmlRenderer();

            $newsletter_html =
                $renderer->render(
                    $existing_newsletter
                );
        }

        // -------------------------------------------------
        // Saved Draft Confirmation
        // -------------------------------------------------

        if (
            isset($_GET['saved']) &&
            $_GET['saved'] === '1' &&
            $newsletter_id > 0
        ) {
            $save_message =
                'Newsletter draft saved.';
        }

        // -------------------------------------------------
        // Receive Preview or Save Request
        // -------------------------------------------------
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            (
                isset($_POST['sof_newsletter_preview']) ||
                isset($_POST['sof_newsletter_save']) ||
                isset($_POST['sof_newsletter_continue'])
            )
        ) {
            if (
                !isset($_POST['sof_newsletter_nonce']) ||
                !wp_verify_nonce(
                    sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_newsletter_nonce']
                        )
                    ),
                    'sof_compose_newsletter'
                )
            ) {
                return '<p>Newsletter preview could not be verified.</p>';
            }

            $title = isset($_POST['newsletter_title'])
                ? sanitize_text_field(
                    wp_unslash($_POST['newsletter_title'])
                )
                : '';

            $subject = isset($_POST['newsletter_subject'])
                ? sanitize_text_field(
                    wp_unslash($_POST['newsletter_subject'])
                )
                : '';

            $background_color =
                self::sanitize_color(
                    $_POST['background_color'] ?? '',
                    '#f3f4f6'
                );

            $content_background_color =
                self::sanitize_color(
                    $_POST['content_background_color'] ?? '',
                    '#ffffff'
                );
                
            $header_logo_attachment_id =
                isset($_POST['header_logo_attachment_id'])
                    ? absint(
                        $_POST['header_logo_attachment_id']
                    )
                    : 0;
                    
            // -------------------------------------------------
            // Newsletter Audience Intent
            // -------------------------------------------------

            $posted_membership_statuses =
                isset($_POST['membership_statuses']) &&
                is_array($_POST['membership_statuses'])
                    ? wp_unslash(
                        $_POST['membership_statuses']
                    )
                    : [];

            $membership_statuses = [];

            $allowed_membership_statuses = [
                'Active',
                'Expired',
                'Archived',
            ];

            foreach (
                $posted_membership_statuses
                as $posted_status
            ) {
                $posted_status =
                    sanitize_text_field(
                        $posted_status
                    );

                if (
                    in_array(
                        $posted_status,
                        $allowed_membership_statuses,
                        true
                    )
                ) {
                    $membership_statuses[] =
                        $posted_status;
                }
            }

            if (empty($membership_statuses)) {
                $membership_statuses = [
                    'Active',
                ];
            }
            
                        // -------------------------------------------------
            // Organizational Scope Intent
            // -------------------------------------------------

            $audience_scope_key =
                isset($_POST['audience_scope_key'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['audience_scope_key']
                        )
                    )
                    : '';

            $audience_scope_key =
                trim($audience_scope_key) !== ''
                    ? $audience_scope_key
                    : null;

            // -------------------------------------------------
            // Recipient Selection Intent
            // -------------------------------------------------

            $recipient_selection_mode =
                isset($_POST['recipient_selection_mode'])
                    ? sanitize_key(
                        wp_unslash(
                            $_POST['recipient_selection_mode']
                        )
                    )
                    : 'all';

            if (
                !in_array(
                    $recipient_selection_mode,
                    [
                        'all',
                        'selected',
                    ],
                    true
                )
            ) {
                $recipient_selection_mode = 'all';
            }

            // -------------------------------------------------
            // Selected Members
            // -------------------------------------------------

            $posted_selected_member_ids =
                isset($_POST['selected_member_ids']) &&
                is_array($_POST['selected_member_ids'])
                    ? wp_unslash(
                        $_POST['selected_member_ids']
                    )
                    : [];

            $selected_member_ids = [];

            foreach (
                $posted_selected_member_ids
                as $selected_member_id
            ) {
                $selected_member_id =
                    absint(
                        $selected_member_id
                    );

                if ($selected_member_id > 0) {
                    $selected_member_ids[] =
                        $selected_member_id;
                }
            }

            $selected_member_ids =
                array_values(
                    array_unique(
                        $selected_member_ids
                    )
                );

            if (
                $recipient_selection_mode !==
                    'selected'
            ) {
                $selected_member_ids = [];
            }
                
            // -------------------------------------------------
            // Newsletter Signature
            // -------------------------------------------------

            $signature_name =
                isset($_POST['signature_name'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['signature_name']
                        )
                    )
                    : '';

            $signature_title =
                isset($_POST['signature_title'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['signature_title']
                        )
                    )
                    : '';

            $signature_image_attachment_id =
                isset($_POST['signature_image_attachment_id'])
                    ? absint(
                        $_POST['signature_image_attachment_id']
                    )
                    : 0;

            // -------------------------------------------------
            // Apply Suggested Signature When Blank
            // -------------------------------------------------

            if (
                trim($signature_name) === '' &&
                trim($signature_title) === '' &&
                $signature_image_attachment_id <= 0
            ) {

                $signature_service =
                    new SOF_NewsletterSignatureSuggestionService();

                $suggested_signature =
                    $signature_service->suggest();

                $signature_name =
                    $suggested_signature->get_name();

                $signature_title =
                    $suggested_signature->get_title();
            }

            // -------------------------------------------------
            // Newsletter Sections
            // -------------------------------------------------
            
            $sections = [];

            $posted_sections =
                isset($_POST['sections']) &&
                is_array($_POST['sections'])
                    ? wp_unslash($_POST['sections'])
                    : [];

            foreach ($posted_sections as $posted_section) {

                if (!is_array($posted_section)) {
                    continue;
                }

                $heading =
                    isset($posted_section['heading'])
                        ? sanitize_text_field(
                            $posted_section['heading']
                        )
                        : '';

                $content =
                    isset($posted_section['content'])
                        ? wp_kses_post(
                            $posted_section['content']
                        )
                        : '';

                $image_attachment_id =
                    isset($posted_section['image_attachment_id'])
                        ? absint(
                            $posted_section['image_attachment_id']
                        )
                        : 0;
                        
                $image_layout =
                    isset($posted_section['image_layout'])
                        ? sanitize_key(
                            $posted_section['image_layout']
                        )
                        : 'medium';
                        
                if (
                    !in_array(
                        $image_layout,
                        [
                            'small',
                            'medium',
                            'large',
                            'full',
                        ],
                        true
                        
                    )
                ) {
                    $image_layout = 'medium';
                }

                $link_label =
                    isset($posted_section['link_label'])
                        ? sanitize_text_field(
                            $posted_section['link_label']
                        )
                        : '';

                $link_url =
                    isset($posted_section['link_url'])
                        ? esc_url_raw(
                            $posted_section['link_url']
                        )
                        : '';

                $sections[] = [
                    'heading' => $heading,
                    'content' => $content,
                    'image_attachment_id' =>
                        $image_attachment_id,
                    'image_layout' =>
                        $image_layout,
                    'link_label' => $link_label,
                    'link_url' => $link_url,
                ];
            }

            if (empty($sections)) {
                $sections[] = [
                    'heading' => '',
                    'content' => '',
                    'image_attachment_id' => 0,
                    'image_layout' => 'medium',
                    'link_label' => '',
                    'link_url' => '',
                ];
            }

            // -------------------------------------------------
            // Build Newsletter Design
            // -------------------------------------------------

            $header_logo_alt = '';

            if ($header_logo_attachment_id > 0) {

                $header_logo_alt =
                    (string) get_post_meta(
                        $header_logo_attachment_id,
                        '_wp_attachment_image_alt',
                        true
                    );

                if (trim($header_logo_alt) === '') {

                    $header_logo_alt =
                        (string) get_the_title(
                            $header_logo_attachment_id
                        );
                }
            }

            $design = new SOF_NewsletterDesign(
                $background_color,
                null,
                null,
                $content_background_color,
                $header_logo_attachment_id > 0
                    ? $header_logo_attachment_id
                    : null,
                null,
                $header_logo_alt
            );
            
            // -------------------------------------------------
            // Build Temporary Newsletter
            // -------------------------------------------------

            $signature =
                new SOF_NewsletterSignature(
                    $signature_name,
                    $signature_title,
                    $signature_image_attachment_id > 0
                        ? $signature_image_attachment_id
                        : null,
                    null
                );
            
            $newsletter = new SOF_Newsletter(
                $newsletter_id > 0
                    ? $newsletter_id
                    : null,
                $title,
                $subject,
                'regional',
                $design,
                [],
                $newsletter_status,
                $signature,
                $membership_statuses,
                $audience_scope_key,
                $recipient_selection_mode,
                $selected_member_ids
            );
            
            foreach ($sections as $section) {

                $has_content =
                    $section['heading'] !== '' ||
                    $section['content'] !== '' ||
                    $section['image_attachment_id'] > 0 ||
                    $section['link_label'] !== '' ||
                    $section['link_url'] !== '';

                if (!$has_content) {
                    continue;
                }

                $image_alt = '';

                if ($section['image_attachment_id'] > 0) {
                    $image_alt = (string) get_post_meta(
                        $section['image_attachment_id'],
                        '_wp_attachment_image_alt',
                        true
                    );
                }

                $newsletter->add_section(
                    new SOF_NewsletterSection(
                        'story',
                        $section['heading'],
                        $section['content'],
                        $section['image_attachment_id'] > 0
                            ? $section['image_attachment_id']
                            : null,
                        null,
                        $image_alt,
                        $section['image_layout'],
                        $section['link_url'] !== ''
                            ? $section['link_url']
                            : null,
                        $section['link_label'] !== ''
                            ? $section['link_label']
                            : null
                    )
                );
            }

            // -------------------------------------------------
            // Save Draft
            // -------------------------------------------------

            if (
                isset($_POST['sof_newsletter_save']) ||
                isset($_POST['sof_newsletter_continue'])
            ) {

                $repository =
                    new SOF_NewsletterRepository();

                if ($newsletter_id > 0) {

                    $saved =
                        $repository->update($newsletter);

                    if (!$saved) {
                        $save_message =
                            'Newsletter draft could not be saved.';
                    }

                } else {

                    $wp_user =
                        wp_get_current_user();

                    $person_id =
                        (int) get_user_meta(
                            $wp_user->ID,
                            'coai_member_id',
                            true
                        );

                    $saved_id =
                        $repository->create(
                            $newsletter,
                            $person_id > 0
                                ? $person_id
                                : null
                        );

                    if ($saved_id) {

                        $redirect_url =
                            add_query_arg(
                                [
                                    'newsletter_id' =>
                                        $saved_id,

                                    'saved' =>
                                        '1',
                                ],
                                get_permalink()
                            );

                        wp_safe_redirect(
                            $redirect_url
                        );

                        exit;
                    }

                    $save_message =
                        'Newsletter draft could not be saved.';
                }

                if (
                    $newsletter_id > 0 && 
                    $saved &&
                    !isset($_POST['sof_newsletter_continue'])
                ){

                    $redirect_url =
                        add_query_arg(
                            [
                                'newsletter_id' =>
                                    $newsletter_id,

                                'saved' =>
                                    '1',
                            ],
                            get_permalink()
                        );

                    wp_safe_redirect(
                        $redirect_url
                    );

                    exit;
                }
            }
            
                        // -------------------------------------------------
            // Continue Newsletter to Communication Verification
            // -------------------------------------------------

            if (
                isset($_POST['sof_newsletter_continue']) &&
                $newsletter_id > 0
            ) {
                $audience_service =
                    new SOF_NewsletterAudienceService();

                $audience =
                    $audience_service
                        ->resolve_current_audience(
                            $newsletter
                        );
                if (!$audience) {
                    return '<p>No authorized Newsletter audience could be resolved.</p>';
                }

                $communication_service =
                    new SOF_NewsletterCommunicationService();

                $communication =
                    $communication_service->prepare(
                        $newsletter,
                        $audience,
                        get_current_user_id()
                    );

                if (!$communication) {
                    return '<p>The Newsletter could not be prepared for verification.</p>';
                }

                $communication_id =
                    $communication->get_id();

                if (!$communication_id) {
                    return '<p>The Communication could not be identified after it was created.</p>';
                }

                $verify_url =
                    add_query_arg(
                        'communication_id',
                        $communication_id,
                        home_url(
                            '/verify-communication/'
                        )
                    );

                wp_safe_redirect(
                    $verify_url
                );

                exit;
            }

            // -------------------------------------------------
            // Render Preview
            // -------------------------------------------------

            $renderer =
                new SOF_NewsletterHtmlRenderer();

            $newsletter_html =
                $renderer->render($newsletter);
        }
        
        // -------------------------------------------------
        // Newsletter Recipient Selection Experience
        // -------------------------------------------------

        $newsletter_eligible_recipients =
            new SOF_CommunicationRecipients(
                [],
                []
            );

        $newsletter_selection_mode =
            $recipient_selection_mode === 'selected'
                ? SOF_CommunicationRecipientSelection::MODE_SELECTED
                : SOF_CommunicationRecipientSelection::MODE_ALL;

        $newsletter_recipient_selection =
            new SOF_CommunicationRecipientSelection(
                $newsletter_selection_mode,
                $selected_member_ids
            );

        $newsletter_audience_service =
            new SOF_NewsletterAudienceService();

        /*
         * A new Newsletter does not yet have a persisted Newsletter
         * object on the initial page load.
         *
         * Build a temporary business object so the Audience Service
         * can resolve the person's authorized Newsletter audience.
         */
        $audience_newsletter =
            $newsletter instanceof SOF_Newsletter
                ? $newsletter
                : new SOF_Newsletter(
                    $newsletter_id > 0
                        ? $newsletter_id
                        : null,
                    $title,
                    $subject,
                    'regional',
                    null,
                    [],
                    $newsletter_status,
                    null,
                   $membership_statuses,
                    $audience_scope_key,
                    $recipient_selection_mode,
                    $selected_member_ids
                );

        $authorized_newsletter_audience =
            $newsletter_audience_service
                ->resolve_current_audience(
                    $audience_newsletter
                );

        if (
            $authorized_newsletter_audience instanceof
                SOF_CommunicationAudience
        ) {
            
            /*
             * Preserve the person's authorized organizational
             * boundary, but apply the Membership statuses
             * chosen for this Newsletter.
             */
            $newsletter_audience =
                new SOF_CommunicationAudience(
                    $authorized_newsletter_audience->get_key(),
                    $authorized_newsletter_audience->get_name(),
                    $authorized_newsletter_audience->get_description(),
                    $authorized_newsletter_audience->get_region(),
                    $membership_statuses,
                    0,
                    false
                );

            $membership_audience_service =
                new SOF_MembershipAudienceService();

            $newsletter_recipients_service =
                new SOF_CommunicationRecipientsService(
                    $membership_audience_service
                );

            $newsletter_eligible_recipients =
                $newsletter_recipients_service
                    ->discover(
                        $newsletter_audience
                    );

            /*
             * Validate selected identifiers against the
             * authorized and eligible recipient population.
             *
             * Selection may narrow authorization.
             * It may never expand authorization.
             */
            $selection_service =
                new SOF_CommunicationRecipientSelectionService();

            $validated_recipients =
                $selection_service->apply(
                    $newsletter_eligible_recipients,
                    $newsletter_recipient_selection
                );

            if (
                $newsletter_recipient_selection
                    ->uses_selected_recipients()
            ) {
                $validated_member_ids = [];

                foreach (
                    $validated_recipients
                        ->get_available_recipients()
                    as $recipient
                ) {
                    $member_id =
                        isset($recipient['member_id'])
                            ? (int) $recipient['member_id']
                            : 0;

                    if ($member_id > 0) {
                        $validated_member_ids[] =
                            $member_id;
                    }
                }

                $selected_member_ids =
                    $validated_member_ids;

                $newsletter_recipient_selection =
                    new SOF_CommunicationRecipientSelection(
                        SOF_CommunicationRecipientSelection::MODE_SELECTED,
                        $selected_member_ids
                    );
            }
        }

        // -------------------------------------------------
        // Present Workspace
        // -------------------------------------------------

        ob_start();
        ?>

        <div class="sof-compose-newsletter-workspace">

            <header class="sof-compose-newsletter-header">

                <h1>
                    Compose Newsletter
                </h1>

                <p>
                    Create and design a Newsletter for your audience.
                </p>

            </header>
            
            <?php if ($save_message !== '') : ?>

                <div class="sof-newsletter-save-message">

                    <?php
                    echo esc_html(
                        $save_message
                    );
                    ?>

                </div>

            <?php endif; ?>            

                <div class="sof-compose-newsletter-layout">
                    
                    <div class="sof-compose-newsletter-editor">

                    <form method="post">

                <?php
                wp_nonce_field(
                    'sof_compose_newsletter',
                    'sof_newsletter_nonce'
                );
                ?>

                <section class="sof-newsletter-compose-section">

                    <h2>
                        Newsletter Details
                    </h2>
                    
                    <p class="sof-newsletter-section-introduction">
                        Give your Newsletter a title and enter the subject
                        members will see in their email.
                    </p>                    

                    <p>
                        <label>
                            <strong>Newsletter Title</strong>
                        </label>
                    </p>

                    <input
                        type="text"
                        name="newsletter_title"
                        value="<?php echo esc_attr($title); ?>"
                        placeholder="Enter Newsletter title..."
                    >

                    <p>
                        <label>
                            <strong>Email Subject</strong>
                        </label>
                    </p>

                    <input
                        type="text"
                        name="newsletter_subject"
                        value="<?php echo esc_attr($subject); ?>"
                        placeholder="Enter email subject..."
                    >

                </section>

                <section class="sof-newsletter-compose-section">

                    <h2>
                        Newsletter Design
                    </h2>

                    <p>
                        Choose the background colors for the
                        Newsletter.
                    </p>

                    <div class="sof-newsletter-color-fields">

                        <label>
                            <strong>
                                Background Color
                            </strong>

                            <input
                                type="color"
                                name="background_color"
                                value="<?php
                                    echo esc_attr(
                                        $background_color
                                    );
                                ?>"
                            >
                        </label>

                        <label>
                            <strong>
                                Content Background
                            </strong>

                            <input
                                type="color"
                                name="content_background_color"
                                value="<?php
                                    echo esc_attr(
                                        $content_background_color
                                    );
                                ?>"
                            >
                        </label>

                    </div>
                    
                    <div class="sof-newsletter-header-logo-field">

                        <div class="sof-newsletter-header-logo-heading">

                            <div>

                                <h3>
                                    Header Logo
                                </h3>

                                <p>
                                    Optional. Add an organization
                                    or Newsletter logo above the title.
                                </p>

                            </div>

                        </div>

                        <input
                            type="hidden"
                            id="sof-newsletter-header-logo-id"
                            name="header_logo_attachment_id"
                            value="<?php
                                echo esc_attr(
                                    (string)
                                    $header_logo_attachment_id
                                );
                            ?>"
                        >

                        <div
                            id="sof-newsletter-header-logo-preview"
                            class="sof-newsletter-header-logo-preview"
                        >
                            <?php
                            if ($header_logo_attachment_id > 0) {

                                echo wp_get_attachment_image(
                                    $header_logo_attachment_id,
                                    'medium',
                                    false,
                                    [
                                        'class' =>
                                            'sof-newsletter-selected-logo',
                                    ]
                                );
                            }
                            ?>
                        </div>

                        <div class="sof-newsletter-image-actions">

                            <button
                                type="button"
                                class="sof-newsletter-choose-image"
                                data-target-id="sof-newsletter-header-logo-id"
                                data-preview-id="sof-newsletter-header-logo-preview"
                            >
                                Choose Logo
                            </button>

                            <button
                                type="button"
                                class="sof-newsletter-remove-image"
                                data-target-id="sof-newsletter-header-logo-id"
                                data-preview-id="sof-newsletter-header-logo-preview"
                                <?php
                                echo $header_logo_attachment_id > 0
                                    ? ''
                                    : 'hidden';
                                ?>
                            >
                                Remove Logo
                            </button>

                        </div>

                    </div>

                </section>
                
<section class="sof-newsletter-compose-section">

    <h2>
        Audience
    </h2>

    <p class="sof-newsletter-section-introduction">
        Choose which authorized members should receive
        this Newsletter.
    </p>

    <div class="sof-newsletter-audience-group">

        <h3>
            Membership Status
        </h3>

        <p>
            Choose which Membership statuses should be included.
        </p>

        <div class="sof-newsletter-membership-statuses">

            <?php

            $status_options = [
                'Active',
                'Expired',
                'Archived',
            ];

            foreach ($status_options as $status_option) :

                $field_id =
                    'sof-newsletter-status-' .
                    sanitize_key(
                        $status_option
                    );

            ?>

                <label
                    class="sof-newsletter-membership-status"
                    for="<?php
                        echo esc_attr(
                            $field_id
                        );
                    ?>"
                >

                    <input
                        type="checkbox"
                        id="<?php
                            echo esc_attr(
                               $field_id
                            );
                        ?>"
                        name="membership_statuses[]"
                        value="<?php
                            echo esc_attr(
                                $status_option
                            );
                        ?>"
                        <?php
                        checked(
                            in_array(
                                $status_option,
                                is_array($membership_statuses)
                                    ? $membership_statuses
                                    : ['Active'],
                                true
                            )
                        );
                        ?>
                    >

                    <span>
                        <?php
                        echo esc_html(
                            $status_option
                        );
                        ?>
                    </span>

                </label>

            <?php endforeach; ?>

        </div>

    </div>

</section>

        <?php

        if (
            class_exists(
                'SOF_CommunicationRecipientSelectionCard'
            )
        ) {
            echo SOF_CommunicationRecipientSelectionCard::render(
                $newsletter_eligible_recipients,
                $newsletter_recipient_selection
            );
        }

        ?>

                <section class="sof-newsletter-compose-section">

                    <h2>
                        Content
                    </h2>

                    <p>
                        Add stories, announcements, events,
                        and other Newsletter sections.
                    </p>

                    <div
                        id="sof-newsletter-sections"
                        class="sof-newsletter-sections"
                    >

                        <?php foreach ($sections as $index => $section) : ?>

                            <div
                                class="sof-newsletter-content-section"
                                data-section-index="<?php
                                    echo esc_attr(
                                        (string) $index
                                    );
                                ?>"
                            >

                                <div class="sof-newsletter-content-section-heading">

                                    <h3>
                                        Section
                                        <span class="sof-newsletter-section-number">
                                            <?php
                                            echo esc_html(
                                                (string) ($index + 1)
                                            );
                                            ?>
                                        </span>
                                    </h3>

                                    <button
                                        type="button"
                                        class="sof-newsletter-remove-section"
                                    >
                                        Remove
                                    </button>

                                </div>

                                <p>
                                    <label>
                                        <strong>
                                            Section Heading
                                        </strong>
                                    </label>
                                </p>

                                <input
                                    type="text"
                                    name="sections[<?php
                                        echo esc_attr(
                                            (string) $index
                                        );
                                    ?>][heading]"
                                    value="<?php
                                        echo esc_attr(
                                            $section['heading']
                                        );
                                    ?>"
                                    placeholder="Around the Region"
                                >

                                <p>
                                    <label>
                                        <strong>
                                            Section Content
                                        </strong>
                                    </label>
                                </p>

                                <?php

                                $editor_id =
                                    'sof_newsletter_section_content_' .
                                    $index;

                                wp_editor(
                                    (string) $section['content'],
                                    $editor_id,
                                    [
                                        'textarea_name' =>
                                            'sections[' .
                                            $index .
                                            '][content]',

                                        'textarea_rows' =>
                                            10,

                                        'media_buttons' =>
                                            false,

                                        'teeny' =>
                                            false,

                                        'quicktags' =>
                                            true,

                                        'tinymce' => [
                                            'toolbar1' =>
                                                'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo',

                                            'toolbar2' =>
                                                'forecolor,removeformat,charmap,outdent,indent',
                                        ],
                                    ]
                                );

                                ?>

                                <?php
                                $image_id =
                                    (int) $section['image_attachment_id'];

                                $image_input_id =
                                    'sof-newsletter-section-image-id-' .
                                    $index;

                                $image_preview_id =
                                    'sof-newsletter-section-image-preview-' .
                                    $index;
                                ?>

                                <div class="sof-newsletter-image-field">

                                    <label>
                                        <strong>
                                            Section Image
                                        </strong>
                                    </label>

                                    <p>
                                        Optional. Add an image
                                        to this section.
                                    </p>

                                    <input
                                        type="hidden"
                                        id="<?php
                                            echo esc_attr(
                                                $image_input_id
                                            );
                                        ?>"
                                        name="sections[<?php
                                            echo esc_attr(
                                                (string) $index
                                            );
                                        ?>][image_attachment_id]"
                                        value="<?php
                                            echo esc_attr(
                                                (string) $image_id
                                            );
                                        ?>"
                                    >

                                    <div
                                        id="<?php
                                            echo esc_attr(
                                                $image_preview_id
                                            );
                                        ?>"
                                        class="sof-newsletter-image-preview"
                                    >
                                        <?php
                                        if ($image_id > 0) {
                                            echo wp_get_attachment_image(
                                                $image_id,
                                                'medium',
                                                false,
                                                [
                                                    'class' =>
                                                        'sof-newsletter-selected-image',
                                                ]
                                            );
                                        }
                                        ?>
                                    </div>

                                    <div class="sof-newsletter-image-actions">

                                        <button
                                            type="button"
                                            class="sof-newsletter-choose-image"
                                            data-target-id="<?php
                                                echo esc_attr(
                                                    $image_input_id
                                                );
                                            ?>"
                                            data-preview-id="<?php
                                                echo esc_attr(
                                                    $image_preview_id
                                                );
                                            ?>"
                                        >
                                            Choose Image
                                        </button>

                                        <button
                                            type="button"
                                            class="sof-newsletter-remove-image"
                                            data-target-id="<?php
                                                echo esc_attr(
                                                    $image_input_id
                                                );
                                            ?>"
                                            data-preview-id="<?php
                                                echo esc_attr(
                                                    $image_preview_id
                                                );
                                            ?>"
                                            <?php
                                            echo $image_id > 0
                                                ? ''
                                                : 'hidden';
                                            ?>
                                        >
                                            Remove Image
                                        </button>

                                    </div>
                                    
                                                                    <div class="sof-newsletter-image-size-field">

                                    <p>
                                        <label>
                                            <strong>
                                                Image Size
                                            </strong>
                                        </label>
                                    </p>

                                    <p>
                                        Choose how large this image should appear
                                        in the Newsletter.
                                    </p>

                                    <?php

                                    $image_layout =
                                        isset($section['image_layout'])
                                            ? (string) $section['image_layout']
                                            : 'medium';

                                    if (
                                        !in_array(
                                            $image_layout,
                                            [
                                                'small',
                                                'medium',
                                                'large',
                                                'full',
                                            ],
                                            true
                                        )
                                    ) {
                                        $image_layout = 'medium';
                                    }

                                    ?>

                                    <select
                                        name="sections[<?php
                                            echo esc_attr(
                                                (string) $index
                                            );
                                        ?>][image_layout]"
                                        class="sof-newsletter-image-size"
                                    >
                                        <option
                                            value="small"
                                            <?php selected(
                                                $image_layout,
                                                'small'
                                            ); ?>
                                        >
                                            Small — Portrait / Headshot
                                        </option>

                                        <option
                                            value="medium"
                                            <?php selected(
                                                $image_layout,
                                                'medium'
                                            ); ?>
                                        >
                                            Medium — Story Photo
                                        </option>

                                        <option
                                            value="large"
                                            <?php selected(
                                                $image_layout,
                                                'large'
                                            ); ?>
                                        >
                                            Large — Feature Photo
                                        </option>

                                        <option
                                            value="full"
                                            <?php selected(
                                                $image_layout,
                                                'full'
                                            ); ?>
                                        >
                                            Full Width — Banner / Wide Image
                                        </option>
                                    </select>

                                </div>

                                </div>

                                <div class="sof-newsletter-link-fields">

                                    <h4>
                                        Optional Button
                                    </h4>

                                    <p>
                                        <label>
                                            <strong>
                                                Button Label
                                            </strong>
                                        </label>
                                    </p>

                                    <input
                                        type="text"
                                        name="sections[<?php
                                            echo esc_attr(
                                                (string) $index
                                            );
                                        ?>][link_label]"
                                        value="<?php
                                            echo esc_attr(
                                                $section['link_label']
                                            );
                                        ?>"
                                        placeholder="Learn More"
                                    >

                                    <p>
                                        <label>
                                            <strong>
                                                Button Link
                                            </strong>
                                        </label>
                                    </p>

                                    <input
                                        type="url"
                                        name="sections[<?php
                                            echo esc_attr(
                                                (string) $index
                                            );
                                        ?>][link_url]"
                                        value="<?php
                                            echo esc_attr(
                                                $section['link_url']
                                            );
                                        ?>"
                                        placeholder="https://"
                                    >

                                </div>

                            </div>

                        <?php endforeach; ?>
                    </div>

                    <div class="sof-newsletter-add-section-action">

                        <button
                            type="button"
                            id="sof-newsletter-add-section"
                        >
                            + Add Section
                        </button>

                    </div>

                </section>
                
                <section class="sof-newsletter-compose-section">

                    <div class="sof-newsletter-signature-heading">

                        <div>

                            <h2>
                                Signature
                            </h2>

                            <p>
                                Add an optional closing signature
                                to the Newsletter.
                            </p>

                        </div>

                    </div>

                    <div class="sof-newsletter-signature-fields">

                        <div>

                            <label>
                                <strong>
                                    Name
                                </strong>
                            </label>

                            <input
                                type="text"
                                name="signature_name"
                                value="<?php
                                    echo esc_attr(
                                        $signature_name
                                    );
                                ?>"
                                placeholder="Mike Britt"
                            >

                        </div>

                        <div>

                            <label>
                                <strong>
                                    Title
                                </strong>
                            </label>

                            <input
                                type="text"
                                name="signature_title"
                                value="<?php
                                    echo esc_attr(
                                        $signature_title
                                    );
                                ?>"
                                placeholder="Regional Vice President"
                            >

                        </div>

                    </div>

                    <div class="sof-newsletter-image-field">

                        <label>
                            <strong>
                                Signature Image
                            </strong>
                        </label>

                        <p>
                            Optional. Add a signature image,
                            portrait, or other closing image.
                        </p>

                        <input
                            type="hidden"
                            id="sof-newsletter-signature-image-id"
                            name="signature_image_attachment_id"
                            value="<?php
                                echo esc_attr(
                                    (string)
                                    $signature_image_attachment_id
                                );
                            ?>"
                        >

                        <div
                            id="sof-newsletter-signature-image-preview"
                            class="sof-newsletter-image-preview"
                        >
                            <?php
                            if ($signature_image_attachment_id > 0) {
                                echo wp_get_attachment_image(
                                    $signature_image_attachment_id,
                                    'medium',
                                    false,
                                    [
                                        'class' =>
                                            'sof-newsletter-selected-image',
                                    ]
                                );
                            }
                            ?>
                        </div>

                        <div class="sof-newsletter-image-actions">

                            <button
                                type="button"
                                class="sof-newsletter-choose-image"
                                data-target-id="sof-newsletter-signature-image-id"
                                data-preview-id="sof-newsletter-signature-image-preview"
                            >
                                Choose Image
                            </button>

                            <button
                                type="button"
                                class="sof-newsletter-remove-image"
                                data-target-id="sof-newsletter-signature-image-id"
                                data-preview-id="sof-newsletter-signature-image-preview"
                                <?php
                                echo $signature_image_attachment_id > 0
                                    ? ''
                                    : 'hidden';
                                ?>
                            >
                                Remove Image
                            </button>

                        </div>

                    </div>

                </section>

            <div class="sof-newsletter-compose-actions">

                <div class="sof-newsletter-primary-actions">

                    <button
                        type="submit"
                        name="sof_newsletter_save"
                        value="1"
                        class="sof-newsletter-save-draft"
                    >
                        Save Draft
                    </button>

                    <button
                        type="submit"
                        name="sof_newsletter_preview"
                        value="1"
                    >
                        Preview Newsletter
                    </button>

                    <?php if ($newsletter_id > 0) : ?>

                        <button
                            type="submit"
                            name="sof_newsletter_continue"
                            value="1"
                            class="sof-newsletter-continue"
                        >
                            Continue to Verify
                        </button>

                    <?php endif; ?>

                </div>

                <?php if ($newsletter_id > 0) : ?>

                    <div class="sof-newsletter-navigation-actions">

                        <a
                            class="sof-newsletter-action-link"
                            href="<?php
                            echo esc_url(
                                home_url(
                                    '/compose-newsletter/'
                                )
                            );
                            ?>"
                        >
                            Start New Newsletter
                        </a>

                        <a
                            class="sof-newsletter-action-link"
                            href="<?php
                            echo esc_url(
                                home_url(
                                    '/newsletters/'
                                )
                            );
                            ?>"
                        >
                            Back to Newsletters
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </form>

                </div>

                <div class="sof-compose-newsletter-preview-column">

                    <div class="sof-compose-newsletter-preview-header">

                        <h2>
                            Newsletter Preview
                        </h2>

                        <p>
                            See how your Newsletter will appear
                            to members.
                        </p>

                    </div>

                    <?php if ($newsletter_html !== '') : ?>

                        <section class="sof-newsletter-compose-preview">

                            <?php
                            echo $newsletter_html;
                            ?>

                        </section>

                    <?php else : ?>

                        <div class="sof-newsletter-empty-preview">

                            <strong>
                                Your Newsletter preview will
                                appear here.
                            </strong>

                            <p>
                                Add your Newsletter details and
                                content, then select Preview
                                Newsletter.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <?php

        return ob_get_clean();
    }

    /**
     * Sanitize a six-digit hex color.
     */
    private static function sanitize_color(
        $value,
        string $fallback
    ): string {

        $value = sanitize_hex_color(
            wp_unslash(
                (string) $value
            )
        );

        return $value ?: $fallback;
    }
}