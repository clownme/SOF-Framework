<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Signature Suggestion Service
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Newsletter Signature Suggestion
 *
 * Purpose:
 *     Suggest a Newsletter signature from known information
 *     about the current person and organizational responsibility.
 *
 * Responsibilities:
 *     - Resolve the current person
 *     - Suggest a signature name
 *     - Suggest a known organizational title
 *     - Preserve human control over the final signature
 *
 * Does NOT:
 *     - Persist Newsletter signatures
 *     - Override saved Newsletter signatures
 *     - Invent organizational responsibilities
 *     - Render Newsletter HTML
 *
 * ============================================================
 */

class SOF_NewsletterSignatureSuggestionService
{
    /**
     * Suggest a signature for the current person.
     */
    public function suggest(): SOF_NewsletterSignature
    {
        if (!is_user_logged_in()) {
            return new SOF_NewsletterSignature();
        }

        $user =
            wp_get_current_user();

        if (
            !$user ||
            $user->ID <= 0
        ) {
            return new SOF_NewsletterSignature();
        }

        // -------------------------------------------------
        // Resolve Person Identity
        // -------------------------------------------------

        $member_id =
            (int) get_user_meta(
                $user->ID,
                'coai_member_id',
                true
            );

        $name =
            trim(
                (string) $user->display_name
            );

        if (
            $member_id > 0 &&
            function_exists('coai_get_member_by_id')
        ) {
            $member =
                coai_get_member_by_id(
                    $member_id
                );

            if (is_array($member)) {

                $first_name =
                    trim(
                        (string) (
                            $member['first_name']
                            ?? ''
                        )
                    );

                $last_name =
                    trim(
                        (string) (
                            $member['last_name']
                            ?? ''
                        )
                    );

                $member_name =
                    trim(
                        $first_name .
                        ' ' .
                        $last_name
                    );

                if ($member_name !== '') {
                    $name = $member_name;
                }
            }
        }

        // -------------------------------------------------
        // Resolve Known Organizational Responsibility
        // -------------------------------------------------

        $title = '';

        if (
            $member_id > 0 &&
            function_exists(
                'coai_get_active_rvp_region_for_member'
            )
        ) {
            $region =
                trim(
                    (string)
                    coai_get_active_rvp_region_for_member(
                        $member_id
                    )
                );

            if ($region !== '') {
                $title =
                    $region .
                    ' Regional Vice President';
            }
        }

        return new SOF_NewsletterSignature(
            $name,
            $title
        );
    }
}