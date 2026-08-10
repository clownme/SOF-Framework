<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Library Workspace
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Presentation
 *
 * Workspace:
 *     Newsletter Library
 *
 * Purpose:
 *     Present the current person's Newsletter history and
 *     provide entry points for continued Newsletter work.
 *
 * Responsibilities:
 *     - Resolve the current person
 *     - Retrieve Newsletters created by that person
 *     - Present available Newsletter history
 *     - Provide a starting point for future reuse workflows
 *
 * Does NOT:
 *     - Persist Newsletters
 *     - Modify historical Newsletters
 *     - Render Newsletter delivery HTML
 *     - Determine Communication recipients
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_NewsletterLibraryWorkspace
{
    /**
     * Render the Newsletter Library.
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $wp_user =
            wp_get_current_user();

        $person_id =
            (int) get_user_meta(
                $wp_user->ID,
                'coai_member_id',
                true
            );

        if ($person_id <= 0) {
            return
                '<div class="sof-newsletter-library">' .
                    '<p>Newsletter history is not available for this account.</p>' .
                '</div>';
        }

        $repository =
            new SOF_NewsletterRepository();

        $service =
            new SOF_NewsletterLibraryService(
                $repository
            );

        $newsletters =
            $service->newsletters_for_person(
                $person_id
            );
            
        $created_newsletter_id =
            null;

        $copy_error =
            false;

        if (
            isset($_POST['sof_newsletter_library_action'])
            && $_POST['sof_newsletter_library_action']
                === 'copy_previous'
        ) {

            $nonce =
                isset($_POST['sof_newsletter_library_nonce'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_newsletter_library_nonce']
                        )
                    )
                    : '';

            if (
                !wp_verify_nonce(
                    $nonce,
                    'sof_newsletter_library_copy'
                )
            ) {
                $copy_error =
                    true;
            } else {

                $source_newsletter_id =
                    isset($_POST['newsletter_id'])
                        ? (int) $_POST['newsletter_id']
                        : 0;

                $created_newsletter_id =
                    $service->copy_as_new_draft(
                        $source_newsletter_id,
                        $person_id
                    );

                if (!$created_newsletter_id) {
                    $copy_error =
                        true;
                }
            }
        }

        ob_start();
        ?>

            <div class="sof-newsletter-library-header">

                <h2>
                    Newsletters
                </h2>

                <p>
                    Create a new Newsletter or continue with one
                    you created before.
                </p>

            </div>

            <div class="sof-newsletter-library-card">

                <div class="sof-newsletter-library-card-header">

                    <h3>
                        Newsletter Actions
                    </h3>

                    <p>
                        Start a new Newsletter or continue with one
                        you created before.
                    </p>

                </div>

                <div class="sof-newsletter-library-actions">

                    <a
                        href="<?php
                        echo esc_url(
                            home_url('/compose-newsletter/')
                        );
                        ?>"
                        class="sof-button sof-button-primary"
                    >
                        Start New Newsletter
                    </a>

                    <a
                        href="?select_previous=1"
                        class="sof-button sof-button-secondary"
                    >
                        Select Previous Newsletter
                    </a>

                </div>

            </div>
            
            <?php if ($created_newsletter_id) : ?>

                <div class="sof-newsletter-library-result">

                    <strong>
                        New Newsletter Draft Created
                    </strong>

                    <p>
                        Your previous Newsletter was copied into
                        a new draft. The original Newsletter was
                        not changed.
                    </p>

                    <a
                        href="<?php
                        echo esc_url(
                            add_query_arg(
                                'newsletter_id',
                                $created_newsletter_id,
                                home_url('/compose-newsletter/')
                            )
                        );
                        ?>"
                        class="sof-button"
                    >
                        Continue Editing
                    </a>

                </div>

            <?php elseif ($copy_error) : ?>

                <div class="sof-newsletter-library-result">

                    <strong>
                        Newsletter Could Not Be Created
                    </strong>

                    <p>
                        The selected Newsletter could not be used
                        as the starting point for a new draft.
                    </p>

                </div>

            <?php endif; ?>
            
            <?php if (!$newsletters) : ?>

                <p>
                    You have not created any Newsletters yet.
                </p>

            <?php elseif (
                isset($_GET['select_previous'])
                || $created_newsletter_id
                || $copy_error
            ) : ?>

                <section class="sof-newsletter-library-previous">

                    <div class="sof-newsletter-library-previous-header">

                        <h3>
                            Select Previous Newsletter
                        </h3>

                        <p>
                            Choose a Newsletter you created before
                            and use it as the starting point for a
                            new Newsletter.
                        </p>

                    </div>

                    <div class="sof-newsletter-library-items">

                        <?php foreach ($newsletters as $newsletter) : ?>

                            <article class="sof-newsletter-library-item">

                                <div class="sof-newsletter-library-item-header">

                                    <h4>
                                        <?php
                                        echo esc_html(
                                            $newsletter->get_title()
                                        );
                                        ?>
                                    </h4>

                                </div>

                                <div class="sof-newsletter-library-item-details">

                                    <div class="sof-newsletter-library-detail">

                                        <strong>
                                            Subject
                                        </strong>

                                        <span>
                                            <?php
                                            echo esc_html(
                                                $newsletter->get_subject()
                                            );
                                            ?>
                                        </span>

                                    </div>

                                    <div class="sof-newsletter-library-detail">

                                        <strong>
                                            Status
                                        </strong>

                                        <span>
                                            <?php
                                            echo esc_html(
                                                ucfirst(
                                                    $newsletter->get_status()
                                                )
                                            );
                                            ?>
                                        </span>

                                    </div>

                                </div>

                                <?php
                                $newsletter_status =
                                    strtolower(
                                        $newsletter->get_status()
                                    );
                                ?>

                                <?php if ($newsletter_status === 'draft') : ?>

                                    <div class="sof-newsletter-library-select">

                                        <a
                                            href="<?php
                                            echo esc_url(
                                                add_query_arg(
                                                    'newsletter_id',
                                                    $newsletter->get_id(),
                                                    home_url(
                                                        '/compose-newsletter/'
                                                    )
                                                )
                                            );
                                            ?>"
                                            class="sof-button sof-button-secondary"
                                        >
                                            Continue Editing
                                        </a>

                                    </div>

                                <?php else : ?>

                                    <form
                                        method="post"
                                        class="sof-newsletter-library-select"
                                    >

                                        <?php
                                        wp_nonce_field(
                                            'sof_newsletter_library_copy',
                                            'sof_newsletter_library_nonce'
                                        );
                                        ?>

                                        <input
                                            type="hidden"
                                            name="sof_newsletter_library_action"
                                            value="copy_previous"
                                        >

                                        <input
                                            type="hidden"
                                            name="newsletter_id"
                                            value="<?php
                                            echo esc_attr(
                                                $newsletter->get_id()
                                            );
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="sof-button sof-button-secondary"
                                        >
                                            Use as New Newsletter
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>

        </div>

        <?php

        return (string) ob_get_clean();
    }
}