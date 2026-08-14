<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Organizational Scope Service
 * ============================================================
 *
 * Framework:
 *     Organization
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Organizational Scope
 *
 * Purpose:
 *     Provide organizational scopes that may be used
 *     by SOF business experiences.
 *
 * Responsibilities:
 *     - Provide available organizational scopes
 *     - Translate organization-specific scope data
 *       into SOF Organizational Scope models
 *
 * Does NOT:
 *     - Grant access
 *     - Determine capabilities
 *     - Persist access grants
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_OrganizationalScopeService
{
    /**
     * @return array<int, SOF_OrganizationalScope>
     */
    public function available_scopes(): array
    {
        $scopes = [];

        if (
            !class_exists(
                'SOF_MembershipRegionKnowledge'
            )
        ) {
            return $scopes;
        }

        $region_knowledge =
            new SOF_MembershipRegionKnowledge();

        $regions =
            $region_knowledge->regions();

        foreach (
            array_keys($regions)
            as $region_name
        ) {
            $name =
                trim(
                    (string) $region_name
                );

            if ($name === '') {
                continue;
            }

            $scopes[] =
                new SOF_OrganizationalScope(
                    sanitize_title($name),
                    'region',
                    $name
                );
        }
        
        return $scopes;
    }
}