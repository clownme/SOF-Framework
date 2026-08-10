<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Service
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Access
 *
 * Purpose:
 *     Determine whether granted access provides a requested
 *     business capability.
 *
 * Responsibilities:
 *     - Evaluate capability grants
 *     - Evaluate Access Profile capabilities
 *
 * Does NOT:
 *     - Authenticate users
 *     - Define organizational roles
 *     - Discover organizational assignments
 *     - Determine organizational scope
 *     - Render access controls
 *
 * ============================================================
 */

class SOF_AccessService
{
    public function profile_allows(
        SOF_AccessProfile $profile,
        string $capability
    ): bool {
        return $profile->has_capability(
            $capability
        );
    }

    /**
     * @param array<int, string> $granted_capabilities
     */
    public function grants_allow(
        array $granted_capabilities,
        string $capability
    ): bool {
        return in_array(
            $capability,
            $granted_capabilities,
            true
        );
    }
    
    /**
     * Resolve the person's current SOF Access Profile.
     *
     * SOF Access Assignment is authoritative when present.
     * Legacy usergroup is used only as a fallback.
     */
    public function resolve_profile_for_person(
        int $person_id,
        string $legacy_usergroup,
        SOF_AccessProfileService $profile_service
    ): ?SOF_AccessProfile {
        if ($person_id <= 0) {
            return null;
        }

        $assignment_repository =
            new SOF_AccessAssignmentRepository();

        $assignment =
            $assignment_repository
                ->find_for_person($person_id);

        if ($assignment) {
            $profile =
                $profile_service->find(
                    $assignment->get_profile_key()
                );

            if ($profile) {
                return $profile;
            }
        }

        return $profile_service
            ->resolve_legacy_profile(
                $legacy_usergroup
            );
    }

    /**
     * Retrieve the person's persisted SOF Access Grants.
     *
     * @return array<int, SOF_AccessGrant>
     */
    public function grants_for_person(
        int $person_id
    ): array {
        if ($person_id <= 0) {
            return [];
        }

        $grant_repository =
            new SOF_AccessGrantRepository();

        return $grant_repository
            ->find_for_person($person_id);
    }
}