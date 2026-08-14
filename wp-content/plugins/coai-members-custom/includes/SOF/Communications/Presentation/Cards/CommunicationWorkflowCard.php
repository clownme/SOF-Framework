<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Workflow Card
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Presentation:
 *     Communication Workflow Card
 *
 * Purpose:
 *     Show users where a communication is within its
 *     lifecycle and explain what should happen next.
 *
 * Responsibilities:
 *     - Display communication lifecycle steps
 *     - Identify completed, current, and upcoming steps
 *     - Identify failed workflow steps
 *     - Explain the communication's current situation
 *     - Tell the user what should happen next
 *
 * Does NOT:
 *     - Change communication status
 *     - Validate communication information
 *     - Execute workflow actions
 *     - Deliver communications
 *     - Apply business lifecycle rules
 *
 * ============================================================
 */

class SOF_CommunicationWorkflowCard
{
    /**
     * Communication lifecycle steps.
     *
     * @var array<string, string>
     */
    protected array $steps = [
        'compose' => 'Compose',
        'verify'  => 'Verify',
        'test'    => 'Test',
        'approve' => 'Approve',
        'deliver' => 'Deliver',
        'review'  => 'Review',
        'archive' => 'Archive',
    ];

    /**
     * Render the communication workflow card.
     */
    public function render(
        SOF_Communication $communication
    ): string {
        $status = $communication->get_status();

        $current_step = $this->get_current_step($status);
        $failed_step = $this->get_failed_step($status);
        $current_position = $this->get_step_position($current_step);

        $message = $this->get_status_message($status);
        $next_action = $this->get_next_action($status);

        ob_start();
        ?>

        <section class="sof-card sof-communication-workflow-card">

            <header class="sof-card-header">

                <h2 class="sof-card-title">
                    Communication Lifecycle
                </h2>

                <p class="sof-card-summary">
                    Follow each step to prepare, verify, test,
                    approve, and safely deliver your communication.
                </p>

            </header>

            <div
                class="sof-communication-workflow"
                aria-label="Communication lifecycle progress"
            >

                <?php
                $step_number = 0;

                foreach ($this->steps as $step => $label) :
                    $step_number++;

                    $state = $this->get_step_state(
                        $step,
                        $current_step,
                        $failed_step,
                        $current_position
                    );

                    $symbol = $this->get_step_symbol($state);
                    ?>

                    <div
                        class="
                            sof-workflow-step
                            sof-workflow-step-<?php
                            echo esc_attr($state);
                            ?>
                        "
                        aria-current="<?php
                        echo $state === 'current'
                            ? 'step'
                            : 'false';
                        ?>"
                    >

                        <span
                            class="sof-workflow-step-symbol"
                            aria-hidden="true"
                        >
                            <?php echo esc_html($symbol); ?>
                        </span>

                        <span class="sof-workflow-step-label">
                            <?php echo esc_html($label); ?>
                        </span>

                        <span class="screen-reader-text">
                            <?php
                            echo esc_html(
                                $this->get_accessible_state_label(
                                    $label,
                                    $state
                                )
                            );
                            ?>
                        </span>

                    </div>

                    <?php if ($step_number < count($this->steps)) : ?>

                        <span
                            class="sof-workflow-connector"
                            aria-hidden="true"
                        >
                            ─
                        </span>

                    <?php endif; ?>

                <?php endforeach; ?>

            </div>

            <div
                class="
                    sof-communication-workflow-status
                    sof-communication-workflow-status-<?php
                    echo esc_attr(
                        $failed_step !== null
                            ? 'attention'
                            : 'normal'
                    );
                    ?>
                "
            >

                <h3>
                    <?php
                    echo esc_html(
                        $this->get_current_heading($status)
                    );
                    ?>
                </h3>

                <p>
                    <?php echo esc_html($message); ?>
                </p>

                <?php if ($next_action !== '') : ?>

                    <p class="sof-workflow-next-action">
                        <strong>Next:</strong>
                        <?php echo esc_html($next_action); ?>
                    </p>

                <?php endif; ?>

            </div>

        </section>

        <?php

        return (string) ob_get_clean();
    }

    // -------------------------------------------------
    // Workflow State
    // -------------------------------------------------

    /**
     * Determine the workflow step that currently needs attention.
     */
    protected function get_current_step(
        string $status
    ): string {
        switch ($status) {
            case 'draft':
                return 'compose';

            case 'composed':
                return 'verify';

            case 'verified':
                return 'test';

            case 'tested':
                return 'approve';

            case 'approved':
            case 'sending':
                return 'deliver';

            case 'sent':
            case 'completed':
                return 'review';

            case 'archived':
                return 'archive';

            case 'verification_failed':
                return 'verify';

            case 'test_failed':
                return 'test';

            case 'delivery_failed':
                return 'deliver';

            case 'cancelled':
                return 'compose';

            default:
                return 'compose';
        }
    }

    /**
     * Determine whether the current status represents a failed step.
     */
    protected function get_failed_step(
        string $status
    ): ?string {
        switch ($status) {
            case 'verification_failed':
                return 'verify';

            case 'test_failed':
                return 'test';

            case 'delivery_failed':
                return 'deliver';

            default:
                return null;
        }
    }

    /**
     * Determine the visual state of a workflow step.
     */
    protected function get_step_state(
        string $step,
        string $current_step,
        ?string $failed_step,
        int $current_position
    ): string {
        if ($failed_step === $step) {
            return 'failed';
        }

        $step_position = $this->get_step_position($step);

        if ($step_position < $current_position) {
            return 'completed';
        }

        if ($step === $current_step) {
            return 'current';
        }

        return 'upcoming';
    }

    /**
     * Return the numeric position of a workflow step.
     */
    protected function get_step_position(
        string $step
    ): int {
        $steps = array_keys($this->steps);
        $position = array_search($step, $steps, true);

        return $position === false
            ? 0
            : (int) $position;
    }

    /**
     * Return the symbol for a workflow state.
     */
    protected function get_step_symbol(
        string $state
    ): string {
        switch ($state) {
            case 'completed':
                return '✓';

            case 'failed':
                return '!';

            case 'current':
                return '●';

            default:
                return '○';
        }
    }

    // -------------------------------------------------
    // User Guidance
    // -------------------------------------------------

    /**
     * Return a user-friendly heading for the current status.
     */
    protected function get_current_heading(
        string $status
    ): string {
        switch ($status) {
            case 'draft':
                return 'Compose Your Communication';

            case 'composed':
                return 'Ready for Verification';

            case 'verified':
                return 'Ready for Testing';

            case 'tested':
                return 'Ready for Approval';

            case 'approved':
                return 'Ready for Delivery';

            case 'sending':
                return 'Delivery in Progress';

            case 'sent':
                return 'Ready for Review';

            case 'completed':
                return 'Communication Completed';

            case 'archived':
                return 'Communication Archived';

            case 'verification_failed':
                return 'Verification Needs Attention';

            case 'test_failed':
                return 'Test Delivery Needs Attention';

            case 'delivery_failed':
                return 'Delivery Needs Attention';

            case 'cancelled':
                return 'Communication Cancelled';

            default:
                return 'Communication Status';
        }
    }

    /**
     * Explain the communication's current situation.
     */
    protected function get_status_message(
        string $status
    ): string {
        switch ($status) {
            case 'draft':
                return 'Your communication is still being prepared. Complete the audience, subject, message, and other required information before continuing.';

            case 'composed':
                return 'Your communication has been composed and is ready to be checked for completeness, accuracy, audience, and delivery requirements.';

            case 'verified':
                return 'Your communication passed verification and is ready to be sent as a test before final approval.';

            case 'tested':
                return 'The test communication was delivered successfully. Review the test and approve the communication when you are satisfied with the result.';

            case 'approved':
                return 'Your communication has been verified, successfully tested, and approved for final delivery.';

            case 'sending':
                return 'Your communication is currently being delivered. Delivery results will be available when the process finishes.';

            case 'sent':
                return 'Your communication has been delivered. Review the results to confirm how many recipients were reached and whether any deliveries failed.';

            case 'completed':
                return 'The communication lifecycle is complete. You may continue reviewing the delivery results or archive the communication when it is no longer active.';

            case 'archived':
                return 'This communication has completed its active lifecycle and is now stored as part of the communication history.';

            case 'verification_failed':
                return 'The communication could not be verified. Review the identified issues, correct the communication, and verify it again.';

            case 'test_failed':
                return 'The test communication was not delivered successfully. Review the test result and correct the problem before trying again.';

            case 'delivery_failed':
                return 'The final communication could not be delivered successfully. Review the delivery result before attempting another delivery.';

            case 'cancelled':
                return 'This communication was cancelled before its lifecycle was completed.';

            default:
                return 'The communication status is not currently recognized. Review the communication before continuing.';
        }
    }

    /**
     * Tell the user what should happen next.
     */
    protected function get_next_action(
        string $status
    ): string {
        switch ($status) {
            case 'draft':
                return 'Finish composing the communication.';

            case 'composed':
                return 'Verify the communication.';

            case 'verified':
                return 'Send a test communication.';

            case 'tested':
                return 'Review the test and approve the communication.';

            case 'approved':
                return 'Deliver the communication to its audience.';

            case 'sending':
                return 'Allow delivery to finish before reviewing the results.';

            case 'sent':
                return 'Review the communication delivery results.';

            case 'completed':
                return 'Archive the communication when it is no longer active.';

            case 'verification_failed':
                return 'Correct the verification issues and verify it again.';

            case 'test_failed':
                return 'Correct the test delivery issue and send another test.';

            case 'delivery_failed':
                return 'Review the delivery issue before trying again.';

            case 'cancelled':
            case 'archived':
                return '';

            default:
                return 'Review the communication before continuing.';
        }
    }

    /**
     * Return an accessible description of a workflow step.
     */
    protected function get_accessible_state_label(
        string $label,
        string $state
    ): string {
        switch ($state) {
            case 'completed':
                return $label . ' completed';

            case 'current':
                return $label . ' is the current step';

            case 'failed':
                return $label . ' needs attention';

            default:
                return $label . ' is an upcoming step';
        }
    }
}