<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Audience Service
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Newsletter Audience
 *
 * Purpose:
 *     Resolve the Communication audience available to the
 *     current Newsletter author from persisted SOF Access.
 *
 * Responsibilities:
 *     - Resolve current Person identity
 *     - Confirm manage_newsletters capability
 *     - Resolve persisted organizational scope
 *     - Translate organizational scope into a
 *       Communication Audience
 *
 * Does NOT:
 *     - Discover Communication recipients
 *     - Apply membership-status rules
 *     - Deliver Communications
 *     - Persist Access grants
 *     - Infer organizational responsibility
 *
 * ============================================================
 */

class SOF_NewsletterAudienceService
{
    /**
     * Resolve the audience authorized for the current person.
     */
    public function resolve_current_audience(
        SOF_Newsletter $newsletter
    ):  ?SOF_CommunicationAudience
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $user =
            wp_get_current_user();

        if (
            !$user ||
            $user->ID <= 0
        ) {
            return null;
        }

        // -------------------------------------------------
        // Resolve WordPress User → Person
        // -------------------------------------------------

        $person_id =
            (int) get_user_meta(
                $user->ID,
                'coai_member_id',
                true
            );

        if ($person_id <= 0) {
            return null;
        }

        if (!class_exists('SOF_AccessService')) {
            return null;
        }

        // -------------------------------------------------
        // Resolve Persisted Access Grants
        // -------------------------------------------------

        $access_service =
            new SOF_AccessService();

        $grants =
            $access_service
                ->grants_for_person(
                    $person_id
                );

        if (!$grants) {
            return null;
        }

        // -------------------------------------------------
        // Find Newsletter Capability
        // -------------------------------------------------

        $newsletter_scope = null;

        foreach ($grants as $grant) {

            if (
                $grant->get_capability() !==
                    'manage_newsletters'
            ) {
                continue;
            }

            $newsletter_scope =
                $grant->get_scope();

            break;
        }

        if (!$newsletter_scope) {
            return null;
        }

        // -------------------------------------------------
        // Resolve Organizational Scope
        // -------------------------------------------------

        $scope_key =
            trim(
                (string)
                $newsletter_scope->get_key()
            );

        $scope_type =
            trim(
                (string)
                $newsletter_scope->get_type()
            );

        $scope_name =
            trim(
                (string)
                $newsletter_scope->get_name()
            );

        if ($scope_name === '') {
            return null;
        }
        
        $membership_statuses =
            $newsletter->get_membership_statuses();

        // -------------------------------------------------
        // Organization-Wide Scope
        // -------------------------------------------------

        if ($scope_type === 'organization') {

            return new SOF_CommunicationAudience(
                'organization_members',
                $scope_name,
                $scope_name . ' members',
                '',
                $membership_statuses,
                0,
                false
            );
        }

        // -------------------------------------------------
        // Assigned Organizational Scope
        // -------------------------------------------------

        return new SOF_CommunicationAudience(
            'assigned_audience',
            $scope_name,
            $scope_name . ' members',
            $scope_name,
            $membership_statuses,
            0,
            false
        );
    }
}