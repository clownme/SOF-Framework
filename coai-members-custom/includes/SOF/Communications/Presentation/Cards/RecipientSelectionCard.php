<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Recipient Selection Card
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Presentation
 *
 * Card:
 *     Recipient Selection
 *
 * Purpose:
 *     Present the recipient population available to the
 *     current Communication and allow the person to choose
 *     whether all eligible recipients or a selected subset
 *     should receive it.
 *
 * Responsibilities:
 *     - Present eligible recipient count
 *     - Present current recipient selection mode
 *     - Present eligible members for individual selection
 *     - Preserve the selected recipient identifiers in the form
 *
 * Does NOT:
 *     - Discover recipients
 *     - Determine organizational authorization
 *     - Determine recipient eligibility
 *     - Persist the Communication
 *     - Verify the Communication
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_CommunicationRecipientSelectionCard
{
    /**
     * Render recipient selection.
     */
    public static function render(
        SOF_CommunicationRecipients $eligible_recipients,
        SOF_CommunicationRecipientSelection $selection
    ): string {

        $available_recipients =
            $eligible_recipients
                ->get_available_recipients();

        $selected_member_ids =
            $selection
                ->get_member_ids();

        $eligible_count =
            count(
                $available_recipients
            );

        $selected_count =
            count(
                $selected_member_ids
            );

        ob_start();
        ?>

        <section class="sof-recipient-selection-experience">

            <section class="sof-card sof-person-selection-situation-card">

                <header class="sof-card-header">

                    <h2 class="sof-card-title">
                        Recipient Selection
                    </h2>

                    <p class="sof-card-summary">
                        Choose who should receive this communication.
                    </p>

                </header>

                <div class="sof-card-content">

                    <div class="sof-selection-situation">

                        <div>

                            <strong>
                                Eligible Members
                            </strong>

                            <span>
                                <?php
                                echo esc_html(
                                    number_format_i18n(
                                        $eligible_count
                                    )
                                );
                                ?>
                            </span>

                        </div>

                        <div>

                            <strong>
                                Current Selection
                            </strong>

                            <span
                                id="sof-recipient-selection-summary"
                            >
                                <?php
                                if (
                                    $selection
                                        ->uses_all_recipients()
                                ) {
                                    echo esc_html(
                                        sprintf(
                                            '%s eligible members selected for this communication.',
                                            number_format_i18n(
                                                $eligible_count
                                            )
                                        )
                                    );

                                } elseif ($selected_count === 1) {

                                    echo esc_html(
                                        '1 eligible member selected for this communication.'
                                    );

                                } else {

                                    echo esc_html(
                                        sprintf(
                                            '%s eligible members selected for this communication.',
                                            number_format_i18n(
                                                $selected_count
                                            )
                                        )
                                    );
                                }
                                ?>
                            </span>

                        </div>

                    </div>

                </div>

            </section>

            <section class="sof-card sof-person-selection-decision-card">

                <header class="sof-card-header">

                    <h3 class="sof-card-title">
                        Choose Recipients
                    </h3>

                    <p class="sof-card-summary">
                        Send this communication to everyone in the
                        eligible audience or choose specific members.
                    </p>

                </header>

                <div class="sof-card-content">

                    <label class="sof-recipient-selection-option">

                        <input
                            type="radio"
                            name="recipient_selection_mode"
                            value="all"
                            <?php
                            checked(
                                $selection
                                    ->uses_all_recipients()
                            );
                            ?>
                        >

                        <span>

                            <strong>
                                All Eligible Members
                            </strong>

                            <small>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        '%s members',
                                        number_format_i18n(
                                            $eligible_count
                                        )
                                    )
                                );
                                ?>
                            </small>

                        </span>

                    </label>

                    <label class="sof-recipient-selection-option">

                        <input
                            type="radio"
                            name="recipient_selection_mode"
                            value="selected"
                            <?php
                            checked(
                                $selection
                                    ->uses_selected_recipients()
                            );
                            ?>
                        >

                        <span>

                            <strong>
                                Select Specific Members
                            </strong>

                            <small>
                                Find and choose individual members
                                from the eligible audience.
                            </small>

                        </span>

                    </label>

                </div>

            </section>

            <section
                id="sof-person-selection-members"
                class="sof-card sof-person-selection-members-card"
                <?php
                if (
                    !$selection
                        ->uses_selected_recipients()
                ) {
                    echo 'hidden';
                }
                ?>
            >

                <header class="sof-card-header">

                    <h3 class="sof-card-title">
                        Find Members
                    </h3>

                    <p class="sof-card-summary">
                        Search by member number, name, or email address.
                    </p>

                </header>

                <div class="sof-card-content">

                    <?php if (!$available_recipients) : ?>

                        <p>
                            No eligible members are available for selection.
                        </p>

                    <?php else : ?>

                        <div class="sof-person-selection-toolbar">

                            <label for="sof-person-selection-search">
                                Search Members
                            </label>

                            <input
                                type="search"
                                id="sof-person-selection-search"
                                placeholder="Search members..."
                                autocomplete="off"
                            >

                            <p
                                id="sof-person-selection-result-count"
                                class="sof-person-selection-result-count"
                            >
                                <?php
                                echo esc_html(
                                    sprintf(
                                        'Showing %s eligible members',
                                        number_format_i18n(
                                            $eligible_count
                                        )
                                    )
                                );
                                ?>
                            </p>

                        </div>

                        <div class="sof-person-selection-table-wrap">

                            <table class="sof-person-selection-table">

                                <thead>

                                    <tr>

                                        <th class="sof-person-selection-check">
                                            Select
                                        </th>

                                        <th
                                            class="sof-person-selection-sort"
                                            data-sort="member-number"
                                        >
                                            Member #
                                        </th>

                                        <th
                                            class="sof-person-selection-sort"
                                            data-sort="name"
                                        >
                                            Name
                                        </th>

                                        <th
                                            class="sof-person-selection-sort"
                                            data-sort="email"
                                        >
                                            Email
                                        </th>

                                    </tr>

                                </thead>

                                <tbody
                                    id="sof-person-selection-results"
                                >

                                    <?php foreach (
                                        $available_recipients
                                        as $recipient
                                    ) : ?>

                                        <?php

                                        $member_id =
                                            isset(
                                                $recipient['member_id']
                                            )
                                                ? (int) $recipient['member_id']
                                                : 0;

                                        if ($member_id <= 0) {
                                            continue;
                                        }

                                        $member_number =
                                            trim(
                                                (string) (
                                                    $recipient['member_number']
                                                    ?? ''
                                                )
                                            );

                                        $first_name =
                                            trim(
                                                (string) (
                                                    $recipient['first_name']
                                                    ?? ''
                                                )
                                            );

                                        $last_name =
                                            trim(
                                                (string) (
                                                    $recipient['last_name']
                                                    ?? ''
                                                )
                                            );

                                        $name =
                                            trim(
                                                $last_name .
                                                (
                                                    $last_name !== ''
                                                    && $first_name !== ''
                                                        ? ', '
                                                        : ''
                                                ) .
                                                $first_name
                                            );

                                        if ($name === '') {
                                            $name =
                                                'Member #' .
                                                $member_id;
                                        }

                                        $email =
                                            trim(
                                                (string) (
                                                    $recipient['email']
                                                    ?? ''
                                                )
                                            );

                                        ?>

                                        <tr
                                            class="sof-person-selection-row"
                                            data-member-number="<?php
                                            echo esc_attr(
                                                strtolower(
                                                    $member_number
                                                )
                                            );
                                            ?>"
                                            data-name="<?php
                                            echo esc_attr(
                                                strtolower(
                                                    $name
                                                )
                                            );
                                            ?>"
                                            data-email="<?php
                                            echo esc_attr(
                                                strtolower(
                                                    $email
                                                )
                                            );
                                            ?>"
                                        >

                                            <td class="sof-person-selection-check">

                                                <input
                                                    type="checkbox"
                                                    class="sof-person-selection-checkbox"
                                                    name="selected_member_ids[]"
                                                    value="<?php
                                                    echo esc_attr(
                                                        (string) $member_id
                                                    );
                                                    ?>"
                                                    <?php
                                                    checked(
                                                        in_array(
                                                            $member_id,
                                                            $selected_member_ids,
                                                            true
                                                        )
                                                    );
                                                    ?>
                                                >

                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $member_number
                                                );
                                                ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?php
                                                    echo esc_html(
                                                        $name
                                                    );
                                                    ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?php
                                                echo esc_html(
                                                    $email
                                                );
                                                ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <div class="sof-person-selection-pagination">

                            <button
                                type="button"
                                id="sof-person-selection-previous"
                                class="sof-button sof-button-secondary"
                            >
                                Previous
                            </button>

                            <span
                                id="sof-person-selection-page-status"
                                class="sof-person-selection-page-status"
                            >
                                Page 1
                            </span>

                            <button
                                type="button"
                                id="sof-person-selection-next"
                                class="sof-button sof-button-secondary"
                            >
                                Next
                            </button>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <section
                id="sof-person-selection-selected-card"
                class="sof-card sof-person-selection-selected-card"
                <?php
                if (
                    !$selection
                        ->uses_selected_recipients()
                ) {
                    echo 'hidden';
                }
                ?>
            >

                <header class="sof-card-header">

                    <h3 class="sof-card-title">
                        Selected Members
                    </h3>

                    <p
                        id="sof-person-selection-selected-count"
                        class="sof-card-summary"
                    >
                        <?php
                        echo esc_html(
                            sprintf(
                                $selected_count === 1
                                    ? '%s eligible member selected for this communication.'
                                    : '%s eligible members selected for this communication.',
                                number_format_i18n(
                                    $selected_count
                                )
                            )
                        );
                        ?>
                    </p>

                </header>

                <div class="sof-card-content">

                    <div
                        id="sof-person-selection-selected-list"
                        class="sof-person-selection-selected-list"
                    ></div>

                </div>

            </section>

        </section>

        <script>
        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const modeInputs =
                    document.querySelectorAll(
                        'input[name="recipient_selection_mode"]'
                    );

                const membersCard =
                    document.getElementById(
                        'sof-person-selection-members'
                    );

                const selectedCard =
                    document.getElementById(
                        'sof-person-selection-selected-card'
                    );

                const searchInput =
                    document.getElementById(
                        'sof-person-selection-search'
                    );

                const rows =
                    Array.from(
                        document.querySelectorAll(
                            '.sof-person-selection-row'
                        )
                    );

                const checkboxes =
                    Array.from(
                        document.querySelectorAll(
                            '.sof-person-selection-checkbox'
                        )
                    );

                const resultCount =
                    document.getElementById(
                        'sof-person-selection-result-count'
                    );

                const previousButton =
                    document.getElementById(
                        'sof-person-selection-previous'
                    );

                const nextButton =
                    document.getElementById(
                        'sof-person-selection-next'
                    );

                const pageStatus =
                    document.getElementById(
                        'sof-person-selection-page-status'
                    );

                const selectedCount =
                    document.getElementById(
                        'sof-person-selection-selected-count'
                    );

                const pageSize =
                    25;

                let currentPage =
                    1;

                let filteredRows =
                    rows;

                const selectedList =
                    document.getElementById(
                        'sof-person-selection-selected-list'
                    );

                const selectionSummary =
                    document.getElementById(
                        'sof-recipient-selection-summary'
                    );

                const eligibleCount =
                    <?php
                    echo (int) $eligible_count;
                    ?>;

                function selectedMode()
                {
                    const selected =
                        document.querySelector(
                            'input[name="recipient_selection_mode"]:checked'
                        );

                    return selected
                        ? selected.value
                        : 'all';
                }

                function updateMode()
                {
                    const isSelected =
                        selectedMode() === 'selected';

                    if (membersCard) {
                        membersCard.hidden =
                            !isSelected;
                    }

                    if (selectedCard) {
                        selectedCard.hidden =
                            !isSelected;
                    }

                    updateSelection();
                }

                function updateSelection()
                {
                    const checked =
                        checkboxes.filter(
                            function (checkbox) {
                                return checkbox.checked;
                            }
                        );

                    const count =
                        checked.length;

                    if (
                        selectedMode() === 'all'
                    ) {
                        if (selectionSummary) {
                            selectionSummary.textContent =
                                eligibleCount.toLocaleString() +
                                ' eligible members selected for this communication.';
                        }

                        return;
                    }

                    const phrase =
                        count === 1
                            ? '1 eligible member selected for this communication.'
                            : count.toLocaleString() +
                                ' eligible members selected for this communication.';

                    if (selectedCount) {
                        selectedCount.textContent =
                            phrase;
                    }

                    if (selectionSummary) {
                        selectionSummary.textContent =
                            phrase;
                    }

                    if (selectedList) {

                        selectedList.innerHTML = '';

                        checked.forEach(
                            function (checkbox) {

                                const row =
                                    checkbox.closest(
                                        '.sof-person-selection-row'
                                    );

                                if (!row) {
                                    return;
                                }

                                const nameCell =
                                    row.querySelector(
                                        'td:nth-child(3)'
                                    );

                                if (!nameCell) {
                                    return;
                                }

                                const item =
                                    document.createElement(
                                        'div'
                                    );

                                item.className =
                                    'sof-person-selection-selected-item';

                                item.textContent =
                                    nameCell.textContent.trim();

                                selectedList.appendChild(
                                    item
                                );
                            }
                        );
                    }
                }

                function renderPage()
                {
                    const totalResults =
                        filteredRows.length;

                    const totalPages =
                        Math.max(
                            1,
                            Math.ceil(
                                totalResults / pageSize
                            )
                        );

                    if (currentPage > totalPages) {
                        currentPage =
                            totalPages;
                    }

                    const start =
                        (currentPage - 1) * pageSize;

                    const end =
                        start + pageSize;

                    rows.forEach(
                        function (row) {
                            row.hidden =
                                true;
                        }
                    );

                    filteredRows
                        .slice(
                            start,
                            end
                        )
                        .forEach(
                            function (row) {
                                row.hidden =
                                    false;
                            }
                        );

                    if (pageStatus) {
                        pageStatus.textContent =
                            'Page ' +
                            currentPage.toLocaleString() +
                            ' of ' +
                            totalPages.toLocaleString();
                    }

                    if (previousButton) {
                        previousButton.disabled =
                            currentPage <= 1;
                    }

                    if (nextButton) {
                        nextButton.disabled =
                            currentPage >= totalPages;
                    }
                }

                function filterMembers()
                {
                    if (!searchInput) {
                        return;
                    }

                    const query =
                        searchInput.value
                            .trim()
                            .toLowerCase();

                    filteredRows =
                        rows.filter(
                            function (row) {

                                const haystack =
                                    [
                                        row.dataset.memberNumber || '',
                                        row.dataset.name || '',
                                        row.dataset.email || ''
                                    ].join(' ');

                                return (
                                    query === '' ||
                                    haystack.includes(
                                        query
                                    )
                                );
                            }
                        );

                    currentPage =
                        1;

                    if (resultCount) {

                        if (query === '') {

                            resultCount.textContent =
                                'Showing ' +
                                Math.min(
                                    pageSize,
                                    filteredRows.length
                                ).toLocaleString() +
                                ' of ' +
                                filteredRows.length.toLocaleString() +
                                ' eligible members';

                        } else {

                            resultCount.textContent =
                                filteredRows.length === 1
                                    ? '1 matching member'
                                    : filteredRows.length.toLocaleString() +
                                        ' matching members';
                        }
                    }

                    renderPage();
                }

                modeInputs.forEach(
                    function (input) {
                        input.addEventListener(
                            'change',
                            updateMode
                        );
                    }
                );

                checkboxes.forEach(
                    function (checkbox) {
                        checkbox.addEventListener(
                            'change',
                            updateSelection
                        );
                    }
                );

                if (searchInput) {
                    searchInput.addEventListener(
                        'input',
                        filterMembers
                    );
                }

                if (previousButton) {
                    previousButton.addEventListener(
                        'click',
                        function () {

                            if (currentPage > 1) {
                                currentPage--;

                                renderPage();
                            }
                        }
                    );
                }

                if (nextButton) {
                    nextButton.addEventListener(
                        'click',
                        function () {

                            const totalPages =
                                Math.max(
                                    1,
                                    Math.ceil(
                                        filteredRows.length /
                                        pageSize
                                    )
                                );

                            if (
                                currentPage <
                                totalPages
                            ) {
                                currentPage++;

                                renderPage();
                            }
                        }
                    );
                }

                updateMode();
                filterMembers();
            }
        );
        </script>

        <?php

        return (string) ob_get_clean();
    }
}