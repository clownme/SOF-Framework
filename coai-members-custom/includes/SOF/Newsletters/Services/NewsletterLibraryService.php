<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Library Service
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Newsletter Library
 *
 * Purpose:
 *     Provide a person's available Newsletter history for
 *     reuse and continued Newsletter work.
 *
 * Responsibilities:
 *     - Retrieve Newsletters created by a person
 *     - Preserve Newsletter history
 *     - Support future Newsletter reuse workflows
 *
 * Does NOT:
 *     - Persist Newsletters directly
 *     - Render Newsletter HTML
 *     - Determine Communication recipients
 *     - Deliver Communications
 *     - Modify historical Newsletters
 *
 * ============================================================
 */

class SOF_NewsletterLibraryService
{
    private SOF_NewsletterRepository $repository;

    public function __construct(
        SOF_NewsletterRepository $repository
    ) {
        $this->repository =
            $repository;
    }

    /**
     * ============================================================
     * Newsletters for Person
     * ============================================================
     *
     * Return the Newsletters previously created by the
     * specified person.
     *
     * @param int $person_id
     *
     * @return SOF_Newsletter[]
     */
    public function newsletters_for_person(
        int $person_id
    ): array {

        if ($person_id <= 0) {
            return [];
        }

        return $this->repository
            ->find_by_person(
                $person_id
            );
    }
    
    /**
     * ============================================================
     * Copy as New Draft
     * ============================================================
     *
     * Create a new Newsletter draft using one of the person's
     * existing Newsletters as the starting point.
     *
     * The source Newsletter remains unchanged.
     *
     * @param int $newsletter_id
     * @param int $person_id
     *
     * @return int|null
     */
    public function copy_as_new_draft(
        int $newsletter_id,
        int $person_id
    ): ?int {

        if (
            $newsletter_id <= 0
            || $person_id <= 0
        ) {
            return null;
        }

        $available_newsletters =
            $this->newsletters_for_person(
                $person_id
            );

        $source = null;

        foreach (
            $available_newsletters
            as $candidate
        ) {

            if (
                $candidate->get_id()
                === $newsletter_id
            ) {
                $source =
                    $candidate;

                break;
            }
        }

        if (!$source) {
            return null;
        }

        $newsletter =
            new SOF_Newsletter(
                null,
                $source->get_title(),
                $source->get_subject(),
                $source->get_template_key(),
                $source->get_design(),
                $source->get_sections(),
                'draft',
                $source->get_signature(),
                $source->get_membership_statuses()
            );

        return $this->repository->create(
            $newsletter,
            $person_id
        );
    }  
}