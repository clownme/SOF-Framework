<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Access Workspace
 * ============================================================
 *
 * Framework:
 *     Access
 *
 * Layer:
 *     Presentation
 *
 * Workspace:
 *     Access
 *
 * Purpose:
 *     Provide a business-friendly workspace for managing
 *     what people are authorized to do.
 *
 * Responsibilities:
 *     - Present access management
 *     - Guide selection of a person
 *     - Present Access Profiles
 *     - Present business and platform capabilities
 *     - Present organizational scope
 *
 * Does NOT:
 *     - Define capability meaning
 *     - Determine organizational responsibility
 *     - Persist access grants directly
 *     - Authenticate users
 *
 * ============================================================
 */

class SOF_AccessWorkspace
{
    public function render(): string
    {
        
        // -------------------------------------------------
        // Access Authorization
        // -------------------------------------------------

        if (!is_user_logged_in()) {
            return '
                <div class="sof-access-workspace">
                    <section class="sof-access-section">
                        <h2>
                            Access Management Unavailable
                        </h2>

                        <p>
                            You must be logged in to manage access.
                        </p>
                    </section>
                </div>
            ';
        }

        $current_user =
            wp_get_current_user();

        $current_member = null;

        if (
            $current_user &&
            $current_user->ID > 0
        ) {
            if (
                function_exists(
                    'coai_get_member_by_id'
                )
            ) {
                $current_member_id =
                    (int) get_user_meta(
                        $current_user->ID,
                        'coai_member_id',
                        true
                    );

                if ($current_member_id > 0) {
                    $current_member =
                        coai_get_member_by_id(
                            $current_member_id
                        );
                }
            }
        }

        $current_usergroup =
            strtoupper(
                trim(
                    (string) (
                        $current_member['usergroup']
                            ?? ''
                    )
                )
            );

        $can_manage_access =
            in_array(
                $current_usergroup,
                [
                    'ADMIN',
                ],
                true
            );

        if (!$can_manage_access) {
            return '
                <div class="sof-access-workspace">
                    <section class="sof-access-section">
                        <h2>
                            Access Management Unavailable
                        </h2>

                        <p>
                            You do not have permission to manage access.
                        </p>
                    </section>
                </div>
            ';
        }
        
        // -------------------------------------------------
        // Save Access
        // -------------------------------------------------

        $save_message = '';
        
        $saved_confirmation = false;

        if (
            isset($_GET['access_saved']) &&
            sanitize_key(
                wp_unslash(
                    $_GET['access_saved']
                )
            ) === '1'
        ) {
            $saved_confirmation = true;

            $save_message =
                'Access was saved successfully.';
        }        

        $save_success = false;

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_POST['sof_access_save'])
        ) {
            $nonce =
                isset($_POST['sof_access_nonce'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['sof_access_nonce']
                        )
                    )
                    : '';

            if (
                !wp_verify_nonce(
                    $nonce,
                    'sof_save_access'
                )
            ) {
                $save_message =
                    'Access could not be saved because the request could not be verified.';

            } else {

                $save_person_id =
                    isset($_POST['person_id'])
                        ? absint($_POST['person_id'])
                        : 0;

                $save_profile_key =
                    isset($_POST['profile_key'])
                        ? sanitize_key(
                            wp_unslash(
                                $_POST['profile_key']
                            )
                        )
                        : '';

                $save_scope_type =
                    isset($_POST['scope_type'])
                        ? sanitize_key(
                            wp_unslash(
                                $_POST['scope_type']
                            )
                        )
                        : '';

                $save_specific_scope =
                    isset($_POST['specific_scope'])
                        ? sanitize_key(
                            wp_unslash(
                                $_POST['specific_scope']
                            )
                        )
                        : '';

                $save_business_capabilities =
                    isset($_POST['business_capabilities']) &&
                    is_array($_POST['business_capabilities'])
                        ? array_values(
                            array_unique(
                                array_map(
                                    'sanitize_key',
                                    wp_unslash(
                                        $_POST['business_capabilities']
                                    )
                                )
                            )
                        )
                        : [];

                $save_platform_capabilities =
                    isset($_POST['platform_capabilities']) &&
                    is_array($_POST['platform_capabilities'])
                        ? array_values(
                            array_unique(
                                array_map(
                                    'sanitize_key',
                                    wp_unslash(
                                        $_POST['platform_capabilities']
                                    )
                                )
                            )
                        )
                        : [];

                $profile_service =
                    new SOF_AccessProfileService();

                $save_profile =
                    $profile_service->find(
                        $save_profile_key
                    );

                $save_scope = null;

                if (
                    $save_profile &&
                    $save_profile->get_key() !== 'member'
                ) {
                    if (
                        $save_scope_type ===
                            SOF_AccessScope::TYPE_ORGANIZATION
                    ) {
                        $save_scope =
                            new SOF_OrganizationalScope(
                                'organization',
                                'organization',
                                'Entire Organization'
                            );

                    } elseif (
                        $save_scope_type ===
                            SOF_AccessScope::TYPE_SPECIFIC_SCOPE &&
                        $save_specific_scope !== ''
                    ) {
                        $organization_scope_service =
                            new SOF_OrganizationalScopeService();

                        foreach (
                            $organization_scope_service
                                ->available_scopes()
                            as $scope
                        ) {
                            if (
                                $scope->get_key() ===
                                    $save_specific_scope
                            ) {
                                $save_scope =
                                    $scope;

                                break;
                            }
                        }
                    }
                }

                if (
                    !$save_profile ||
                    $save_person_id <= 0 ||
                    (
                        $save_profile->get_key() !== 'member' &&
                        !$save_scope
                    )
                ) {
                    $save_message =
                        'Access could not be saved because the configuration is incomplete.';

                } else {

                    $grant_service =
                        new SOF_AccessGrantService();

                    $save_success =
                        $grant_service->save(
                            $save_person_id,
                            $save_profile,
                            $save_business_capabilities,
                            $save_platform_capabilities,
                            $save_scope
                        );

                    if ($save_success) {

                        $redirect_url =
                            add_query_arg(
                                [
                                    'person_id' =>
                                        $save_person_id,

                                    'access_saved' =>
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
                        'Access could not be saved.';
                }
            }
        }
        
        // ----------------------------------------
        // Person Search
        // ----------------------------------------
        
        $search_term = '';

            $search_results = [];

            if (isset($_GET['access_search'])) {

                $search_term =
                    sanitize_text_field(
                        wp_unslash(
                            $_GET['access_search']
                        )
                    );

                if ($search_term !== '') {

                    $member_search_service =
                        new SOF_MemberSearchService();

                    $search_results =
                        $member_search_service->search(
                            $search_term
                        );
                }
            }
            
        // -------------------------------------------------
        // Selected Person
        // -------------------------------------------------

        $selected_person_id = 0;

        $selected_person = null;

        if (isset($_GET['person_id'])) {

            $selected_person_id =
               absint($_GET['person_id']);

            if (
                $selected_person_id > 0 &&
                function_exists('coai_get_member_by_id')
            ) {
                $selected_person =
                    coai_get_member_by_id(
                        $selected_person_id
                    );
            }
        }
        
        // -------------------------------------------------
        // Access Profile
        // -------------------------------------------------

        $current_profile = null;

        $available_profiles = [];

        $persisted_grants = [];

        $persisted_scope = null;

        if ($selected_person) {

            $profile_service =
                new SOF_AccessProfileService();

            $access_service =
                new SOF_AccessService();

            $available_profiles =
                $profile_service->profiles();

            $current_profile =
                $access_service
                    ->resolve_profile_for_person(
                        $selected_person_id,
                        (string) (
                            $selected_person['usergroup'] ?? ''
                        ),
                        $profile_service
                    );

            $persisted_grants =
                $access_service
                    ->grants_for_person(
                        $selected_person_id
                    );

            if ($persisted_grants) {
                $persisted_scope =
                    $persisted_grants[0]
                        ->get_scope();
            }
        }
        
        // -------------------------------------------------
        // Profile Preview
        // -------------------------------------------------

        $selected_profile = $current_profile;

        if (
            $selected_person &&
            isset($_GET['access_profile'])
        ) {
            $requested_profile_key =
                sanitize_key(
                    wp_unslash(
                        $_GET['access_profile']
                    )
                );

            $requested_profile =
                $profile_service->find(
                    $requested_profile_key
                );

            if ($requested_profile) {
                $selected_profile =
                    $requested_profile;
            }
        }
        
        // -------------------------------------------------
        // Capability Preview
        // -------------------------------------------------

        $business_capabilities = [];

        $platform_capabilities = [];

        $business_capability_catalog = [];

        $platform_capability_catalog = [];

        $selected_custom_capabilities = [];

        $capability_service =
            new SOF_AccessCapabilityService();

        $business_capability_catalog =
            $capability_service
                ->business_capabilities();

        $platform_capability_catalog =
            $capability_service
                ->platform_capabilities();

        if (
            $selected_profile &&
            $selected_profile->get_key() === 'custom'
        ) {

            if (
                isset($_GET['custom_capabilities']) &&
                is_array($_GET['custom_capabilities'])
            ) {
                $selected_custom_capabilities =
                    array_values(
                        array_unique(
                            array_map(
                                'sanitize_text_field',
                                wp_unslash(
                                    $_GET['custom_capabilities']
                                )
                            )
                        )
                    );

            } elseif (
                $current_profile &&
                $current_profile->get_key() === 'custom' &&
                $persisted_grants
            ) {
                foreach ($persisted_grants as $grant) {

                    $selected_custom_capabilities[] =
                        $grant->get_capability();
                }

                $selected_custom_capabilities =
                    array_values(
                        array_unique(
                            $selected_custom_capabilities
                        )
                    );
            }
            foreach (
                $selected_custom_capabilities
                as $capability
            ) {
                if (
                    array_key_exists(
                        $capability,
                        $platform_capability_catalog
                    )
                ) {
                    $platform_capabilities[] =
                        $capability;

                } elseif (
                    array_key_exists(
                        $capability,
                        $business_capability_catalog
                    )
                ) {
                    $business_capabilities[] =
                        $capability;
                }
            }

        } elseif ($selected_profile) {

            foreach (
                $selected_profile->get_capabilities()
                as $capability
            ) {
                if (
                    array_key_exists(
                        $capability,
                        $platform_capability_catalog
                    )
                ) {
                    $platform_capabilities[] =
                        $capability;
                } else {
                    $business_capabilities[] =
                        $capability;
                }
            }
        }
        
        // -------------------------------------------------
        // Scope Preview
        // -------------------------------------------------

        $available_scopes = [];

        $selected_scope = null;

        if ($selected_profile) {

            $scope_service =
                new SOF_AccessScopeService();

            $available_scopes =
                $scope_service->scopes();

            $selected_scope =
                $scope_service->default_for_profile(
                    $selected_profile
                );
                
            if (
                $current_profile &&
                $selected_profile->get_key() ===
                    $current_profile->get_key() &&
                $persisted_scope
            ) {
                if (
                    $persisted_scope->get_type() ===
                        'organization'
                ) {
                    $selected_scope =
                        $scope_service->find(
                            SOF_AccessScope::TYPE_ORGANIZATION
                        );

                } else {
                    $selected_scope =
                        $scope_service->find(
                            SOF_AccessScope::TYPE_SPECIFIC_SCOPE
                        );
                }
            }

            if (isset($_GET['access_scope'])) {

                $requested_scope_type =
                    sanitize_text_field(
                        wp_unslash(
                            $_GET['access_scope']
                        )
                    );

                $requested_scope =
                    $scope_service->find(
                        $requested_scope_type
                    );

                if ($requested_scope) {
                    $selected_scope =
                        $requested_scope;
                }
            }
        }
        
        // -------------------------------------------------
        // Available Organizational Scopes
        // -------------------------------------------------

        $specific_scopes = [];

        if (
            class_exists(
                'SOF_OrganizationalScopeService'
            )
        ) {
            $organization_scope_service =
                new SOF_OrganizationalScopeService();

            $specific_scopes =
                $organization_scope_service
                    ->available_scopes();
        }
        
        // -------------------------------------------------
        // Selected Specific Scope
        // -------------------------------------------------

        $selected_specific_scope = '';

        if (
            $persisted_scope &&
            $persisted_scope->get_type() !==
                'organization'
        ) {
            $selected_specific_scope =
                $persisted_scope->get_key();
        }

        if (isset($_GET['specific_scope'])) {
            $selected_specific_scope =
                sanitize_key(
                    wp_unslash(
                        $_GET['specific_scope']
                    )
                );
        }
        
        // -------------------------------------------------
        // Access Change State
        // -------------------------------------------------

        $is_access_preview =
            isset($_GET['access_profile']) ||
            isset($_GET['custom_capabilities']) ||
            isset($_GET['access_scope']) ||
            isset($_GET['specific_scope']);
        
        // -------------------------------------------------
        // Access Review
        // -------------------------------------------------

        $selected_specific_scope_name = '';

        if (
            $selected_specific_scope !== '' &&
            $specific_scopes
        ) {
            foreach ($specific_scopes as $scope) {

                if (
                    $scope->get_key() ===
                        $selected_specific_scope
                ) {
                    $selected_specific_scope_name =
                        $scope->get_name();

                    break;
                }
            }
        }

        $access_ready_to_save =
            $selected_person &&
            $selected_profile;

        if (
            $access_ready_to_save &&
            $selected_profile->get_key() !== 'member'
        ) {
            $access_ready_to_save =
                $selected_scope !== null;

            if (
                $access_ready_to_save &&
                $selected_scope->get_type() ===
                    SOF_AccessScope::TYPE_SPECIFIC_SCOPE
            ) {
                $access_ready_to_save =
                    $selected_specific_scope_name !== '';
            }
        }
        
        // -------------------------------------------------
        // Confirmation Actions
        // -------------------------------------------------

        $manage_another_url =
            remove_query_arg(
                [
                    'person_id',
                    'access_saved',
                    'access_profile',
                    'custom_capabilities',
                    'access_scope',
                    'specific_scope',
                ]
            );

        $exit_access_url =
            home_url(
                '/member-portal/'
            );

        $cancel_changes_url =
            add_query_arg(
                'person_id',
                $selected_person_id,
                remove_query_arg(
                    [
                        'access_profile',
                        'custom_capabilities',
                        'access_scope',
                        'specific_scope',
                    ]
                )
            );
        
        ob_start();
        ?>

        <div class="sof-access-workspace">

            <?php if ($saved_confirmation && $selected_person): ?>

                <header class="sof-access-header">

                    <h1>
                        Access Updated
                    </h1>

                    <p>
                        <?php
                        echo esc_html(
                            trim(
                                (string) ($selected_person['first_name'] ?? '') .
                                ' ' .
                                (string) ($selected_person['last_name'] ?? '')
                            )
                        );
                        ?>'s access was saved successfully.
                    </p>

                </header>

                <section class="sof-access-section sof-access-review">

                    <h2>
                        Current Access
                    </h2>

                    <div class="sof-access-review-item">

                        <strong>
                            Profile
                        </strong>

                        <div>
                            <?php
                            echo esc_html(
                                $current_profile->get_name()
                            );
                            ?>
                        </div>

                    </div>

                    <div class="sof-access-review-item">

                        <strong>
                            Business Capabilities
                        </strong>

                        <?php if ($business_capabilities): ?>

                            <ul>

                                <?php foreach ($business_capabilities as $capability): ?>

                                    <li>
                                        <?php
                                        echo esc_html(
                                            $business_capability_catalog[$capability]
                                                ?? ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $capability
                                                    )
                                                )
                                        );
                                        ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div>
                                None
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="sof-access-review-item">

                        <strong>
                            Platform Capabilities
                        </strong>

                        <?php if ($platform_capabilities): ?>

                            <ul>

                                <?php foreach ($platform_capabilities as $capability): ?>

                                    <li>
                                        <?php
                                        echo esc_html(
                                            $platform_capability_catalog[$capability]
                                                ?? ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $capability
                                                    )
                                                )
                                        );
                                        ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div>
                                None
                            </div>

                        <?php endif; ?>

                    </div>

                    <?php if ($selected_scope): ?>

                        <div class="sof-access-review-item">

                            <strong>
                                Scope
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    $selected_scope->get_name()
                                );
                                ?>
                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if ($selected_specific_scope_name !== ''): ?>

                        <div class="sof-access-review-item">

                            <strong>
                                Organizational Scope
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    $selected_specific_scope_name
                                );
                                ?>
                            </div>

                        </div>

                    <?php endif; ?>

                    <div class="sof-access-confirmation-actions">

                        <a
                            class="sof-button sof-button-secondary"
                            href="<?php echo esc_url($manage_another_url); ?>"
                        >
                            Manage Another Person
                        </a>

                        <a
                            class="sof-button sof-button-primary"
                            href="<?php echo esc_url($exit_access_url); ?>"
                        >
                            Exit Access
                        </a>

                    </div>

                </section>

            <?php else: ?>

            <header class="sof-access-header">

                <h1>
                    Access
                </h1>

                <p>
                    Manage what people are authorized to do
                    and where that access applies.
                </p>

            </header>

            <section class="sof-access-section">

                <h2>
                    Select Person
                </h2>

                <p>
                    Search for the person whose access you want
                    to review or manage.
                </p>

                <form
                    method="get"
                    class="sof-access-person-search"
                >

                    <label for="sof-access-search">
                        Find Person
                    </label>

                    <div class="sof-access-search-row">

                        <input
                            type="search"
                            id="sof-access-search"
                            name="access_search"
                            value="<?php echo esc_attr($search_term); ?>"
                            placeholder="Name, member number, username, or email"
                        >

                        <button
                            type="submit"
                            class="sof-button sof-button-primary"
                        >
                            Find
                        </button>
                        
                    <?php endif; ?>

                    </div>

                </form>

                <?php if ($search_term !== ''): ?>

                    <div class="sof-access-search-results">

                        <?php if ($search_results): ?>

                            <?php foreach ($search_results as $member): ?>

                                <?php
                                $member_id =
                                    (int) ($member['member_id'] ?? 0);

                                $member_name =
                                    trim(
                                        (string) ($member['first_name'] ?? '') .
                                        ' ' .
                                        (string) ($member['last_name'] ?? '')
                                    );

                                $manage_url =
                                    add_query_arg(
                                        'person_id',
                                        $member_id,
                                        remove_query_arg(
                                            'access_search'
                                        )
                                    );
                                ?>
 
                                <div class="sof-access-person-result">

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $member_name !== ''
                                                ? $member_name
                                                : 'Unnamed Member'
                                        );
                                        ?>
                                    </strong>

                                    <?php if (!empty($member['member_number'])): ?>

                                        <div>
                                            Member #
                                            <?php
                                            echo esc_html(
                                                (string) $member['member_number']
                                            );
                                            ?>
                                        </div>
 
                                    <?php endif; ?>

                                    <?php if (!empty($member['email'])): ?>

                                        <div>
                                            <?php
                                            echo esc_html(
                                                (string) $member['email']
                                            );
                                            ?>
                                        </div>

                                    <?php endif; ?>

                                    <a
                                        class="sof-button sof-button-secondary"
                                        href="<?php echo esc_url($manage_url); ?>"
                                    >
                                        Manage Access
                                    </a>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>
 
                            <p>
                                No people were found matching that search.
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </section>

            <?php if ($selected_person): ?>

                <section class="sof-access-section sof-access-selected-person">

                    <h2>
                        Managing Access For
                   </h2>

                   <p>
                        <strong>
                            <?php
                            echo esc_html(
                                trim(
                                    (string) ($selected_person['first_name'] ?? '') .
                                    ' ' .
                                    (string) ($selected_person['last_name'] ?? '')
                                )
                            );
                            ?>
                        </strong>
                    </p>

                    <?php if (!empty($selected_person['member_number'])): ?>

                        <p>
                            Member #
                            <?php
                            echo esc_html(
                                (string) $selected_person['member_number']
                            );
                            ?>
                        </p>

                    <?php endif; ?>

                    <?php if (!empty($selected_person['email'])): ?>

                        <p>
                            <?php
                            echo esc_html(
                                (string) $selected_person['email']
                            );
                            ?>
                        </p>

                    <?php endif; ?>

            </section>

            <?php endif; ?>

            <section class="sof-access-section">

                <h2>
                    Access Profile
                </h2>

                <?php if ($selected_person && $current_profile): ?>

                    <div class="sof-access-current-profile">

                        <strong>
                            Current SOF Profile
                        </strong>

                        <div>
                            <?php
                            echo esc_html(
                                $current_profile->get_name()
                            );
                            ?>
                        </div>

                        <p>
                            <?php
                            echo esc_html(
                                $current_profile->get_description()
                            );
                            ?>
                        </p>

                    </div>
                    
                    <?php
                    if (
                        $selected_profile &&
                        $selected_profile->get_key() !==
                            $current_profile->get_key()
                    ):
                    ?>

                        <div class="sof-access-preview-profile">

                            <strong>
                                Previewing Profile
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    $selected_profile->get_name()
                                );
                                ?>
                            </div>

                            <p>
                                <?php
                                echo esc_html(
                                     $selected_profile->get_description()
                                );
                        ?>
                           </p>

                        </div>

                    <?php endif; ?>

                            <form method="get">

                                <input
                                    type="hidden"
                                    name="person_id"
                                    value="<?php echo esc_attr($selected_person_id); ?>"
                                >

                                <div class="sof-access-profile-options">

                                    <?php foreach ($available_profiles as $profile): ?>

                                        <label class="sof-access-profile-option">

                                            <input
                                                type="radio"
                                                name="access_profile"
                                                value="<?php
                                                echo esc_attr(
                                                    $profile->get_key()
                                                );
                                                ?>"
                                                <?php
                                                checked(
                                                    $selected_profile->get_key(),
                                                    $profile->get_key()
                                                );
                                                ?>
                                            >

                                            <strong>
                                                <?php
                                                echo esc_html(
                                                    $profile->get_name()
                                                );
                                                ?>
                                            </strong>

                                            <span>
                                                <?php
                                                echo esc_html(
                                                    $profile->get_description()
                                                );
                                                ?>
                                            </span>

                                        </label>

                                    <?php endforeach; ?>

                                </div>

                                <button
                                    type="submit"
                                    class="sof-button sof-button-secondary"
                                >
                                    Preview Access
                                </button>

                            </form>

               <?php else: ?>

                    <p>
                        Select a person to review their access profile.
                    </p>

                <?php endif; ?>

            </section>

            <?php
            if (
                $selected_profile &&
                $selected_profile->get_key() === 'custom'
            ):
            ?>

                <form method="get">

                    <input
                        type="hidden"
                        name="person_id"
                        value="<?php echo esc_attr($selected_person_id); ?>"
                    >

                    <input
                        type="hidden"
                        name="access_profile"
                        value="custom"
                    >

                    <section class="sof-access-section">

                        <h2>
                            Business Capabilities
                        </h2>

                        <p>
                            Select the organizational work this person
                            is authorized to perform.
                        </p>

                        <div class="sof-access-capability-options">

                            <?php
                            foreach (
                                $business_capability_catalog
                                as $capability_key => $capability_label
                            ):
                            ?>

                                <label class="sof-access-capability-option">

                                    <input
                                        type="checkbox"
                                        name="custom_capabilities[]"
                                        value="<?php
                                        echo esc_attr(
                                            $capability_key
                                        );
                                        ?>"
                                        <?php
                                        checked(
                                            in_array(
                                                $capability_key,
                                                $selected_custom_capabilities,
                                                true
                                            )
                                        );
                                        ?>
                                    >

                                    <?php
                                    echo esc_html(
                                        $capability_label
                                    );
                                    ?>

                                </label>

                            <?php endforeach; ?>

                        </div>

                    </section>

                    <section class="sof-access-section">

                        <h2>
                            Platform Capabilities
                        </h2>

                        <p>
                            Select any platform access required to
                            support this person's organizational work.
                        </p>

                        <div class="sof-access-capability-options">

                            <?php
                            foreach (
                                $platform_capability_catalog
                                as $capability_key => $capability_label
                            ):
                            ?>

                                <label class="sof-access-capability-option">

                                    <input
                                        type="checkbox"
                                        name="custom_capabilities[]"
                                        value="<?php
                                        echo esc_attr(
                                            $capability_key
                                        );
                                        ?>"
                                        <?php
                                        checked(
                                            in_array(
                                                $capability_key,
                                                $selected_custom_capabilities,
                                                true
                                            )
                                        );
                                        ?>
                                    >

                                    <?php
                                    echo esc_html(
                                        $capability_label
                                    );
                                    ?>

                                </label>

                            <?php endforeach; ?>

                        </div>

                    </section>

                    <section class="sof-access-section">

                        <h2>
                            Where Does This Access Apply?
                        </h2>

                        <p>
                            Choose the organizational area where this
                            person's access is authorized.
                        </p>

                        <div class="sof-access-scope-options">

                            <?php foreach ($available_scopes as $scope): ?>

                                <label class="sof-access-scope-option">

                                    <input
                                        type="radio"
                                        name="access_scope"
                                        value="<?php
                                        echo esc_attr(
                                            $scope->get_type()
                                        );
                                        ?>"
                                        <?php
                                        checked(
                                            $selected_scope
                                                ? $selected_scope->get_type()
                                                : '',
                                            $scope->get_type()
                                        );
                                        ?>
                                    >

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $scope->get_name()
                                        );
                                        ?>
                                    </strong>

                                </label>

                            <?php endforeach; ?>

                        </div>

                        <div class="sof-access-specific-scope">

                            <label for="sof-specific-scope">
                                Specific Organizational Area
                            </label>

                            <p>
                                Select an organizational area only when
                                Specific Scope applies. Entire Organization
                                does not require a selection here.
                            </p>

                            <select
                                id="sof-specific-scope"
                                name="specific_scope"
                            >

                                <option value="">
                                    Select organizational area
                                </option>

                                <?php foreach ($specific_scopes as $scope): ?>

                                    <option
                                        value="<?php
                                        echo esc_attr(
                                            $scope->get_key()
                                        );
                                        ?>"
                                        <?php
                                        selected(
                                            $selected_specific_scope,
                                            $scope->get_key()
                                        );
                                        ?>
                                    >
                                        <?php
                                        echo esc_html(
                                            $scope->get_name()
                                        );
                                        ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </section>

                    <div class="sof-access-confirmation-actions">

                        <button
                            type="submit"
                            class="sof-button sof-button-primary"
                        >
                            Review Access
                        </button>

                        <?php if (isset($cancel_changes_url)): ?>

                            <a
                                class="sof-button sof-button-secondary"
                                href="<?php echo esc_url($cancel_changes_url); ?>"
                            >
                                Cancel Changes
                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            <?php else: ?>

                <section class="sof-access-section">

                    <h2>
                        Business Capabilities
                    </h2>

                    <?php if ($selected_profile): ?>

                        <?php if ($business_capabilities): ?>

                            <ul class="sof-access-capability-list">

                                <?php foreach ($business_capabilities as $capability): ?>

                                    <li>
                                        <?php
                                        echo esc_html(
                                            $business_capability_catalog[$capability]
                                                ?? ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $capability
                                                    )
                                                )
                                        );
                                        ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <p>
                                No business capabilities are included.
                            </p>

                        <?php endif; ?>

                    <?php else: ?>

                        <p>
                            Select a person to review their capabilities.
                        </p>

                    <?php endif; ?>

                </section>

                <section class="sof-access-section">

                    <h2>
                        Platform Capabilities
                    </h2>

                    <?php if ($selected_profile): ?>

                        <?php if ($platform_capabilities): ?>

                            <ul class="sof-access-capability-list">

                                <?php foreach ($platform_capabilities as $capability): ?>

                                    <li>
                                        <?php
                                        echo esc_html(
                                            $platform_capability_catalog[$capability]
                                                ?? ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $capability
                                                    )
                                                )
                                        );
                                        ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <p>
                                No platform capabilities are included.
                            </p>

                        <?php endif; ?>

                    <?php else: ?>

                        <p>
                            Select a person to review their capabilities.
                        </p>

                    <?php endif; ?>

                </section>

            <?php endif; ?>
            
            <?php
            if (
                !$selected_profile ||
                $selected_profile->get_key() !== 'custom'
            ):
            ?>

            <section class="sof-access-section">

                <h2>
                    Scope
                </h2>

                <?php if ($selected_person && $selected_scope): ?>

                    <div class="sof-access-current-scope">

                        <strong>
                            Selected Scope
                        </strong>

                        <div>
                            <?php
                            echo esc_html(
                                $selected_scope->get_name()
                            );
                            ?>
                        </div>

                    </div>

                    <form method="get">

                        <input
                            type="hidden"
                            name="person_id"
                            value="<?php echo esc_attr($selected_person_id); ?>"
                        >

                        <?php if ($selected_profile): ?>

                            <input
                                type="hidden"
                                name="access_profile"
                                value="<?php
                                echo esc_attr(
                                    $selected_profile->get_key()
                                );
                                ?>"
                            >

                        <?php endif; ?>

                        <?php foreach ($selected_custom_capabilities as $capability): ?>

                            <input
                                type="hidden"
                                name="custom_capabilities[]"
                                value="<?php echo esc_attr($capability); ?>"
                            >

                        <?php endforeach; ?>

                        <div class="sof-access-scope-options">

                            <?php foreach ($available_scopes as $scope): ?>

                                <label class="sof-access-scope-option">

                                    <input
                                        type="radio"
                                        name="access_scope"
                                        value="<?php
                                        echo esc_attr(
                                            $scope->get_type()
                                        );
                                        ?>"
                                        <?php
                                        checked(
                                            $selected_scope->get_type(),
                                            $scope->get_type()
                                        );
                                        ?>
                                    >

                                    <strong>
                                        <?php
                                        echo esc_html(
                                            $scope->get_name()
                                        );
                                        ?>
                                    </strong>

                                </label>

                            <?php endforeach; ?>

                        </div>

                        <?php
                        if (
                            $selected_scope->get_type() ===
                                SOF_AccessScope::TYPE_SPECIFIC_SCOPE
                        ):
                        ?>

                            <div class="sof-access-specific-scope">

                                <label for="sof-specific-scope">
                                    Specific Scope
                                </label>

                                <select
                                    id="sof-specific-scope"
                                    name="specific_scope"
                                >

                                    <option value="">
                                        Select scope
                                    </option>

                                    <?php foreach ($specific_scopes as $scope): ?>

                                        <option
                                            value="<?php
                                            echo esc_attr(
                                                $scope->get_key()
                                            );
                                            ?>"
                                            <?php
                                            selected(
                                                $selected_specific_scope,
                                                $scope->get_key()
                                            );
                                            ?>
                                        >
                                            <?php
                                            echo esc_html(
                                                $scope->get_name()
                                            );
                                            ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        <?php endif; ?>

                        <button
                            type="submit"
                            class="sof-button sof-button-secondary"
                        >
                            Apply Scope
                        </button>
                        
                    </form>

                <?php elseif ($selected_person): ?>

                    <p>
                        Scope does not apply to standard member access.
                    </p>

                <?php else: ?>

                    <p>
                        Select a person to review their access scope.
                    </p>

                <?php endif; ?>

            </section>
            
            <?php endif; ?>
            
            <?php if ($selected_person && $selected_profile): ?>

                <section class="sof-access-section sof-access-review">

                    <h2>
                        <?php
                        echo esc_html(
                            $is_access_preview
                                ? 'Access to Save'
                                : 'Current Access'
                        );
                        ?>
                    </h2>
                    <p>
                        <?php
                        echo esc_html(
                            $is_access_preview
                                ? 'Review the access that will be assigned to this person.'
                                : 'This is the access currently assigned to this person.'
                        );
                        ?>
                    </p>
                    
                    <div class="sof-access-review-item">

                        <strong>
                            Profile
                        </strong>

                        <div>
                            <?php
                            echo esc_html(
                                $selected_profile->get_name()
                            );
                            ?>
                        </div>

                    </div>

                    <div class="sof-access-review-item">

                        <strong>
                            Business Capabilities
                        </strong>

                        <?php if ($business_capabilities): ?>

                            <ul>

                                <?php foreach ($business_capabilities as $capability): ?>

                                    <li>
                                        <?php
                                        echo esc_html(
                                            $business_capability_catalog[$capability]
                                                ?? ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $capability
                                                    )
                                                )
                                        );
                                        ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div>
                                None
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="sof-access-review-item">

                        <strong>
                            Platform Capabilities
                        </strong>

                        <?php if ($platform_capabilities): ?>

                            <ul>

                                <?php foreach ($platform_capabilities as $capability): ?>

                                    <li>
                                        <?php
                                        echo esc_html(
                                            $platform_capability_catalog[$capability]
                                                ?? ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $capability
                                                    )
                                                )
                                        );
                                        ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        <?php else: ?>

                            <div>
                                None
                            </div>

                        <?php endif; ?>

                    </div>

                    <?php if ($selected_scope): ?>

                        <div class="sof-access-review-item">

                            <strong>
                                Scope
                            </strong>

                            <div>
                                <?php
                                echo esc_html(
                                    $selected_scope->get_name()
                                );
                                ?>
                            </div>

                        </div>

                        <?php
                        if (
                            $selected_scope->get_type() ===
                                SOF_AccessScope::TYPE_SPECIFIC_SCOPE
                        ):
                        ?>

                            <div class="sof-access-review-item">

                                <strong>
                                    Organizational Scope
                                </strong>

                                <div>
                                    <?php
                                    echo esc_html(
                                        $selected_specific_scope_name !== ''
                                            ? $selected_specific_scope_name
                                            : 'Not selected'
                                    );
                                    ?>
                                </div>

                            </div>

                        <?php endif; ?>

                    <?php else: ?>

                        <div class="sof-access-review-item">

                            <strong>
                                Scope
                            </strong>

                            <div>
                                Not applicable
                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if (
                        $access_ready_to_save &&
                        $is_access_preview
                    ): ?>

                        <form method="post">

                            <?php
                            wp_nonce_field(
                                'sof_save_access',
                                'sof_access_nonce'
                            );
                            ?>

                            <input
                                type="hidden"
                                name="person_id"
                                value="<?php echo esc_attr($selected_person_id); ?>"
                            >

                            <input
                                type="hidden"
                                name="profile_key"
                                value="<?php
                                echo esc_attr(
                                    $selected_profile->get_key()
                                );
                                ?>"
                            >

                            <?php if ($selected_scope): ?>

                                <input
                                    type="hidden"
                                    name="scope_type"
                                    value="<?php
                                    echo esc_attr(
                                        $selected_scope->get_type()
                                    );
                                    ?>"
                                >

                            <?php endif; ?>

                            <?php if ($selected_specific_scope !== ''): ?>

                                <input
                                    type="hidden"
                                    name="specific_scope"
                                    value="<?php
                                    echo esc_attr(
                                        $selected_specific_scope
                                    );
                                    ?>"
                                >

                            <?php endif; ?>

                            <?php foreach ($business_capabilities as $capability): ?>

                                <input
                                    type="hidden"
                                    name="business_capabilities[]"
                                    value="<?php echo esc_attr($capability); ?>"
                                >

                            <?php endforeach; ?>

                            <?php foreach ($platform_capabilities as $capability): ?>

                                <input
                                    type="hidden"
                                    name="platform_capabilities[]"
                                    value="<?php echo esc_attr($capability); ?>"
                                >

                            <?php endforeach; ?>

                            <button
                                type="submit"
                                name="sof_access_save"
                                value="1"
                                class="sof-button sof-button-primary"
                            >
                                Save Access
                            </button>

                        </form>

                    <?php elseif ($is_access_preview): ?>

                        <p>
                            Complete the access configuration
                            before saving.
                        </p>

                    <?php endif; ?>
                    
                    <?php if (!$is_access_preview): ?>

                        <div class="sof-access-confirmation-actions">

                            <a
                                class="sof-button sof-button-secondary"
                                href="<?php echo esc_url($manage_another_url); ?>"
                            >
                                Manage Another Person
                            </a>

                            <a
                                class="sof-button sof-button-primary"
                                href="<?php echo esc_url($exit_access_url); ?>"
                            >
                                Exit Access
                            </a>

                        </div>

                    <?php endif; ?>
                    
                </section>

            <?php endif; ?>
            
        </div>

        <?php

        return (string) ob_get_clean();
    }
}