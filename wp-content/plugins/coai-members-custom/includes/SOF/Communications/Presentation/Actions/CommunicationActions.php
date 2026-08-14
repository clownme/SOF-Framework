<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communication Actions
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Layer:
 *     Experience
 *
 * Presentation:
 *     Communication Actions
 *
 * Purpose:
 *     Translate the current communication state into the
 *     business action available to the user.
 *
 * Responsibilities:
 *     - Identify the next available communication action
 *     - Provide user-friendly action labels
 *     - Provide clear explanations of each action
 *     - Provide action identifiers for the workspace
 *
 * Does NOT:
 *     - Change communication state
 *     - Execute communication actions
 *     - Validate communications
 *     - Deliver communications
 *     - Apply business lifecycle rules
 *
 * ============================================================
 */

class SOF_CommunicationActions
{
    /**
     * Return the action currently available to the user.
     *
     * @return array<string, string>|null
     */
    public function get_available_action(
        SOF_Communication $communication
    ): ?array {
        $status = $communication->get_status();

        switch ($status) {
            case 'draft':
                return $this->action(
                    'compose',
                    'Compose Communication',
                    'Complete the audience, subject, message, and required information.'
                );

            case 'composed':
                return $this->action(
                    'verify',
                    'Verify Communication',
                    'Check the communication for completeness, accuracy, audience, and delivery requirements.'
                );

            case 'verified':
                return $this->action(
                    'test',
                    'Send Test Communication',
                    'Send a test so you can confirm how the communication will appear before approving it.'
                );

            case 'tested':
                return $this->action(
                    'approve',
                    'Approve Communication',
                    'Review the successful test and approve the communication for final delivery.'
                );

            case 'approved':
                return $this->action(
                    'deliver',
                    'Deliver Communication',
                    'Send the approved communication to its intended audience.'
                );

            case 'sent':
                return $this->action(
                    'review',
                    'Review Delivery Results',
                    'Review who received the communication and whether any deliveries need attention.'
                );

            case 'completed':
                return $this->action(
                    'archive',
                    'Archive Communication',
                    'Move the completed communication into communication history when it is no longer active.'
                );

            case 'verification_failed':
                return $this->action(
                    'verify',
                    'Correct and Verify Again',
                    'Correct the identified issues and verify the communication again.'
                );

            case 'test_failed':
                return $this->action(
                    'test',
                    'Correct and Send Another Test',
                    'Correct the test delivery issue before sending another test communication.'
                );

            case 'delivery_failed':
                return $this->action(
                    'deliver',
                    'Review Delivery Problem',
                    'Review and correct the delivery problem before attempting delivery again.'
                );

            case 'sending':
            case 'cancelled':
            case 'archived':
            default:
                return null;
        }
    }

    /**
     * Build a consistent action definition.
     *
     * @return array<string, string>
     */
    protected function action(
        string $id,
        string $label,
        string $description
    ): array {
        return [
            'id'          => $id,
            'label'       => $label,
            'description' => $description,
        ];
    }
}