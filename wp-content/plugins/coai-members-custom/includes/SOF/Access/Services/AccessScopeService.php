<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Scope Service
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Access Scope
 *
 * Purpose:
 *     Provide the scope choices available when access
 *     is configured for a person.
 *
 * Does NOT:
 *     - Grant capabilities
 *     - Discover organization-specific assignments
 *     - Persist access grants
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AccessScopeService
{
    /**
     * Return the available Access scope types.
     *
     * @return array<string, SOF_AccessScope>
     */
    public function scopes(): array
    {
        return [

            SOF_AccessScope::TYPE_ORGANIZATION =>
                new SOF_AccessScope(
                    SOF_AccessScope::TYPE_ORGANIZATION,
                    'Entire Organization'
                ),

            SOF_AccessScope::TYPE_SPECIFIC_SCOPE =>
                new SOF_AccessScope(
                    SOF_AccessScope::TYPE_SPECIFIC_SCOPE,
                    'Specific Scope'
                ),
        ];
    }

    /**
     * Resolve one Access scope type.
     */
    public function find(
        string $type
    ): ?SOF_AccessScope {
        $scopes =
            $this->scopes();

        return $scopes[$type] ?? null;
    }

    /**
     * Resolve the default Access scope for a profile.
     */
    public function default_for_profile(
        SOF_AccessProfile $profile
    ): ?SOF_AccessScope {
        $profile_key =
            $profile->get_key();

        if ($profile_key === 'member') {
            return null;
        }

        if (
            in_array(
                $profile_key,
                [
                    'super_admin',
                    'admin',
                    'manager',
                ],
                true
            )
        ) {
            return $this->find(
                SOF_AccessScope::TYPE_ORGANIZATION
            );
        }

        return $this->find(
            SOF_AccessScope::TYPE_SPECIFIC_SCOPE
        );
    }
}