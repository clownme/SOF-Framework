<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communications Workspace
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Workspace:
 *     Communications
 *
 * Purpose:
 *     Provide a user-friendly workspace for creating and
 *     managing business communications.
 *
 * Responsibilities:
 *     - Present the communication lifecycle
 *     - Assemble communication cards
 *     - Show the communication's current situation
 *     - Guide the user toward the next business action
 *
 * Does NOT:
 *     - Apply communication lifecycle rules
 *     - Validate communication information
 *     - Query communication audiences
 *     - Deliver communications
 *     - Communicate directly with providers
 *
 * ============================================================
 */

class SOF_CommunicationsWorkspace
{
    /**
     * Render the Communications Workspace.
     */
    public function render(): string
    {
        $communication = $this->create_preview_communication();

        $workflow_card = new SOF_CommunicationWorkflowCard();
        $communication_actions = new SOF_CommunicationActions();

        $available_action = $communication_actions->get_available_action(
            $communication
        );

        ob_start();
        ?>

        <div class="sof-workspace sof-communications-workspace">

            <header class="sof-workspace-header">

                <h1>Communications</h1>

                <p>
                    Create and deliver clear communications to
                    the people you are responsible for.
                </p>

            </header>

            <main class="sof-workspace-content">

                <?php
                echo $workflow_card->render(
                    $communication
                );
                ?>
    
                <?php if ($available_action !== null) : ?>

                    <section class="sof-card sof-communication-action-card">

                        <header class="sof-card-header">

                            <h2 class="sof-card-title">
                                What You Should Do Next
                            </h2>

                        </header>

                        <div class="sof-card-content">

                            <h3>
                                <?php
                                echo esc_html(
                                    $available_action['label']
                                );
                                ?>
                            </h3>
    
                            <p>
                                <?php
                                echo esc_html(
                                    $available_action['description']
                                );
                                ?>
                            </p>
    
                            <button
                                type="button"
                                class="sof-button sof-button-primary"
                                data-sof-action="<?php
                                echo esc_attr(
                                    $available_action['id']
                                );
                                ?>"
                                disabled
                            >
                                <?php
                                echo esc_html(
                                    $available_action['label']
                                );
                                ?>
                            </button>
    
                            <p class="sof-action-note">
                                This action will become available when the
                                communication workflow is connected.
                            </p>

                        </div>

                    </section>

                <?php endif; ?>

            </main>

        </div>

        <?php
    
        return (string) ob_get_clean();
    }

    /**
     * Create a temporary communication so the workspace
     * plumbing can be tested before persistence is introduced.
     */
    protected function create_preview_communication(): SOF_Communication
    {
        return new SOF_Communication([
            'type'            => 'regional_communication',
            'status'          => 'draft',
            'subject'         => '',
            'body'            => '',
            'audience'        => 'Regional Members',
            'recipient_count' => 0,
            'channel'         => 'email',
            'created_by'      => get_current_user_id(),
        ]);
    }
}