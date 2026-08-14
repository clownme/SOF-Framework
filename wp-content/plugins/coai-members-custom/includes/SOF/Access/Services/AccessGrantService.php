<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Grant Service
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Access Grant
 *
 * Purpose:
 *     Coordinate the assignment of SOF access to a person.
 *
 * Responsibilities:
 *     - Persist the selected Access Profile
 *     - Replace existing capability grants
 *     - Create business capability grants
 *     - Create platform capability grants
 *     - Apply organizational scope to grants
 *
 * Does NOT:
 *     - Authenticate users
 *     - Modify legacy usergroups
 *     - Define capability meaning
 *     - Determine organizational responsibility
 *     - Render presentation
 *
 * ============================================================
 */

class SOF_AccessGrantService
{
    protected SOF_AccessAssignmentRepository
        $assignment_repository;

    protected SOF_AccessGrantRepository
        $grant_repository;

    public function __construct()
    {
        $this->assignment_repository =
            new SOF_AccessAssignmentRepository();

        $this->grant_repository =
            new SOF_AccessGrantRepository();
    }

    /**
     * Save the complete SOF access configuration
     * for one person.
     *
     * The assignment and all capability grants are
     * persisted as one atomic operation.
     *
     * @param array<int, string> $business_capabilities
     * @param array<int, string> $platform_capabilities
     */
    public function save(
        int $person_id,
        SOF_AccessProfile $profile,
        array $business_capabilities,
        array $platform_capabilities,
        ?SOF_OrganizationalScope $scope
    ): bool {
        global $wpdb;

        if ($person_id <= 0) {
            return false;
        }

        /*
         * Every profile except Member requires
         * an organizational scope.
         */
        if (
            $profile->get_key() !== 'member' &&
            !$scope
        ) {
            return false;
        }

        // -------------------------------------------------
        // Begin Atomic Save
        // -------------------------------------------------

        $transaction_started =
            $wpdb->query('START TRANSACTION');

        if ($transaction_started === false) {
            return false;
        }

        try {

            // -------------------------------------------------
            // Save Profile Assignment
            // -------------------------------------------------

            $assignment =
                new SOF_AccessAssignment(
                    $person_id,
                    $profile->get_key()
                );

            if (
                !$this->assignment_repository
                    ->save($assignment)
            ) {
                throw new RuntimeException(
                    'Unable to save Access Assignment.'
                );
            }

            // -------------------------------------------------
            // Remove Existing Grants
            // -------------------------------------------------

            if (
                !$this->grant_repository
                    ->delete_for_person($person_id)
            ) {
                throw new RuntimeException(
                    'Unable to remove existing Access Grants.'
                );
            }

            // -------------------------------------------------
            // Member
            // -------------------------------------------------

            /*
             * Member has an Access Assignment but does not
             * receive organizational capability grants.
             */
            if ($profile->get_key() !== 'member') {

                // -------------------------------------------------
                // Business Grants
                // -------------------------------------------------

                foreach (
                    $business_capabilities
                    as $capability
                ) {
                    $grant =
                        new SOF_AccessGrant(
                            $person_id,
                            $capability,
                            SOF_AccessGrant::CATEGORY_BUSINESS,
                            $scope
                        );

                    if (
                        !$this->grant_repository
                            ->insert($grant)
                    ) {
                        throw new RuntimeException(
                            'Unable to save business Access Grant.'
                        );
                    }
                }

                // -------------------------------------------------
                // Platform Grants
                // -------------------------------------------------

                foreach (
                    $platform_capabilities
                    as $capability
                ) {
                    $grant =
                        new SOF_AccessGrant(
                            $person_id,
                            $capability,
                            SOF_AccessGrant::CATEGORY_PLATFORM,
                            $scope
                        );

                    if (
                        !$this->grant_repository
                            ->insert($grant)
                    ) {
                        throw new RuntimeException(
                            'Unable to save platform Access Grant.'
                        );
                    }
                }
            }

            // -------------------------------------------------
            // Commit
            // -------------------------------------------------

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException(
                    'Unable to commit Access changes.'
                );
            }

            return true;

        } catch (Throwable $exception) {

            // -------------------------------------------------
            // Escape Clause
            // -------------------------------------------------

            $wpdb->query('ROLLBACK');

            error_log(
                '[SOF Access] Save failed for person ' .
                $person_id .
                ': ' .
                $exception->getMessage()
            );

            return false;
        }
    }
    
        /**
     * Retrieve all persisted access grants for a person.
     *
     * @return array<int, SOF_AccessGrant>
     */
    public function grants_for_person(
        int $person_id
    ): array {
        if ($person_id <= 0) {
            return [];
        }

        return $this->grant_repository
            ->find_for_person($person_id);
    }

    /**
     * Determine whether a person has a capability grant.
     */
    public function person_has_capability(
        int $person_id,
        string $capability
    ): bool {
        $capability =
            sanitize_key(
                $capability
            );

        if (
            $person_id <= 0 ||
            $capability === ''
        ) {
            return false;
        }

        $grants =
            $this->grants_for_person(
                $person_id
            );

        foreach ($grants as $grant) {

            if (
                $grant->get_capability() ===
                    $capability
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the scope for a granted capability.
     */
    public function scope_for_capability(
        int $person_id,
        string $capability
    ): ?SOF_OrganizationalScope {
        $capability =
            sanitize_key(
                $capability
            );

        if (
            $person_id <= 0 ||
            $capability === ''
        ) {
            return null;
        }

        $grants =
            $this->grants_for_person(
                $person_id
            );

        foreach ($grants as $grant) {

            if (
                $grant->get_capability() ===
                    $capability
            ) {
                return $grant->get_scope();
            }
        }

        return null;
    }
}