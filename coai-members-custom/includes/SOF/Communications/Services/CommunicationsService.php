<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communications Service
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Service:
 *     Communications Service
 *
 * Purpose:
 *     Coordinate and protect the lifecycle of business
 *     communications.
 *
 * Responsibilities:
 *     - Validate communication information
 *     - Enforce communication lifecycle rules
 *     - Verify communication readiness
 *     - Record communication testing
 *     - Approve communications for delivery
 *     - Prepare approved communications for providers
 *     - Record delivery results
 *     - Return business-readable results
 *
 * Does NOT:
 *     - Render communication workspaces
 *     - Query regional members directly
 *     - Determine audience membership
 *     - Send email directly
 *     - Depend on a specific delivery provider
 *
 * ============================================================
 */

class SOF_CommunicationsService
{
    // -------------------------------------------------
    // Validation
    // -------------------------------------------------

    /**
     * Validate the required communication information.
     *
     * @return array{
     *     valid: bool,
     *     errors: array<int, string>
     * }
     */
    public function validate(
        SOF_Communication $communication
    ): array {
        $errors = [];

        if (trim($communication->get_subject()) === '') {
            $errors[] = 'A communication subject is required.';
        }

        if (trim($communication->get_body()) === '') {
            $errors[] = 'A communication message is required.';
        }

        if (trim($communication->get_audience_key()) === '') {
            $errors[] = 'A communication audience is required.';
        }

        if (trim($communication->get_channel()) === '') {
            $errors[] = 'A communication channel is required.';
        }

        if ($communication->get_recipient_count() < 1) {
            $errors[] =
                'The communication audience does not contain any recipients.';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    // -------------------------------------------------
    // Compose
    // -------------------------------------------------

    /**
     * Mark a valid draft as composed.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function compose(
        SOF_Communication $communication
    ): array {
        if (!$communication->is_draft()) {
            return $this->failure(
                'invalid_status',
                'Only a draft communication can be composed.'
            );
        }

        $validation = $this->validate($communication);

        if (!$validation['valid']) {
            return $this->failure(
                'incomplete',
                'The communication needs additional information before it can be composed.',
                $validation['errors']
            );
        }

        $communication->mark_composed();

        return $this->success(
            'composed',
            'The communication has been composed.'
        );
    }

    // -------------------------------------------------
    // Verification
    // -------------------------------------------------

    /**
     * Verify a composed communication.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function verify(
        SOF_Communication $communication,
        int $user_id
    ): array {
        if (!$communication->is_composed()) {
            return $this->failure(
                'not_composed',
                'The communication must be composed before it can be verified.'
            );
        }

        if ($user_id < 1) {
            return $this->failure(
                'invalid_user',
                'A valid user is required to verify the communication.'
            );
        }

        $validation = $this->validate($communication);

        if (!$validation['valid']) {
            $communication->mark_verification_failed();

            return $this->failure(
                'verification_failed',
                'The communication could not be verified.',
                $validation['errors']
            );
        }

        $communication->mark_verified($user_id);

        return $this->success(
            'verified',
            'The communication has been verified.'
        );
    }

    // -------------------------------------------------
    // Testing
    // -------------------------------------------------

    /**
     * Determine whether a communication may be test delivered.
     */
    public function can_test(
        SOF_Communication $communication
    ): bool {
        return $communication->get_status() === 'verified';
    }

    /**
     * Record a successful test delivery.
     *
     * The provider performs the actual test delivery.
     * This method records the successful business result.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function record_test_success(
        SOF_Communication $communication,
        int $user_id,
        string $recipient
    ): array {
        if (!$this->can_test($communication)) {
            return $this->failure(
                'not_verified',
                'The communication must be verified before it can be tested.'
            );
        }

        if ($user_id < 1) {
            return $this->failure(
                'invalid_user',
                'A valid user is required to record the communication test.'
            );
        }

        $recipient = sanitize_email($recipient);

        if ($recipient === '' || !is_email($recipient)) {
            return $this->failure(
                'invalid_test_recipient',
                'A valid test recipient email address is required.'
            );
        }

        $communication->mark_tested(
            $user_id,
            $recipient
        );

        return $this->success(
            'tested',
            'The communication test was successful.'
        );
    }

    /**
     * Record a failed test delivery.
     *
     * @param array<int, string> $errors
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function record_test_failure(
        SOF_Communication $communication,
        array $errors = []
    ): array {
        if (!$this->can_test($communication)) {
            return $this->failure(
                'not_verified',
                'The communication must be verified before a test result can be recorded.'
            );
        }

        $communication->mark_test_failed();

        if (empty($errors)) {
            $errors[] = 'The test communication could not be delivered.';
        }

        return $this->failure(
            'test_failed',
            'The communication test failed.',
            $errors
        );
    }
    
    /**
     * Return a tested Communication to composition
     * for revision.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function return_for_revision(
        SOF_Communication $communication
    ): array {
        if (
            !in_array(
               $communication->get_status(),
                [
                    'tested',
                    'test_failed',
                    'approved',
                ],
                true
            )
        ) {
            return $this->failure(
                'invalid_status',
                'This communication cannot be returned for revision from its current lifecycle state.'
            );
        }

        $communication->return_for_revision();

        return $this->success(
            'composed',
            'The communication has been returned for revision.'
        );
    }

    // -------------------------------------------------
    // Approval
    // -------------------------------------------------

    /**
     * Approve a successfully tested communication.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function approve(
        SOF_Communication $communication,
        int $user_id
    ): array {
        if ($communication->get_status() !== 'tested') {
            return $this->failure(
                'not_tested',
                'A successful communication test is required before approval.'
            );
        }

        if ($user_id < 1) {
            return $this->failure(
                'invalid_user',
                'A valid user is required to approve the communication.'
            );
        }

        $communication->mark_approved($user_id);

        return $this->success(
            'approved',
            'The communication has been approved for delivery.'
        );
    }

    // -------------------------------------------------
    // Delivery Readiness
    // -------------------------------------------------

    /**
     * Determine whether a communication is ready for delivery.
     */
    public function is_ready(
        SOF_Communication $communication
    ): bool {
        if (!$communication->is_approved()) {
            return false;
        }

        $validation = $this->validate($communication);

        return $validation['valid'];
    }

    /**
     * Return a business-readable delivery readiness assessment.
     *
     * @return array{
     *     ready: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function assess_readiness(
        SOF_Communication $communication
    ): array {
        $validation = $this->validate($communication);

        if (!$validation['valid']) {
            return [
                'ready'   => false,
                'status'  => 'incomplete',
                'message' =>
                    'The communication needs additional information before it can be delivered.',
                'errors'  => $validation['errors'],
            ];
        }

        if (!$communication->is_verified()) {
            return [
                'ready'   => false,
                'status'  => 'not_verified',
                'message' =>
                    'The communication must be verified before it can be delivered.',
                'errors'  => [],
            ];
        }

        if (!$communication->is_tested()) {
            return [
                'ready'   => false,
                'status'  => 'not_tested',
                'message' =>
                    'The communication must be successfully tested before it can be delivered.',
                'errors'  => [],
            ];
        }

        if (!$communication->is_approved()) {
            return [
                'ready'   => false,
                'status'  => 'not_approved',
                'message' =>
                    'The communication must be approved before it can be delivered.',
                'errors'  => [],
            ];
        }

        return [
            'ready'   => true,
            'status'  => 'ready',
            'message' => 'The communication is ready for delivery.',
            'errors'  => [],
        ];
    }

    // -------------------------------------------------
    // Delivery Preparation
    // -------------------------------------------------

    /**
     * Prepare an approved communication for a provider.
     *
     * This does not perform delivery.
     *
     * @return array{
     *     success: bool,
     *     communication: array<string, mixed>|null,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function prepare_for_delivery(
        SOF_Communication $communication
    ): array {
        $readiness = $this->assess_readiness($communication);

        if (!$readiness['ready']) {
            return [
                'success'       => false,
                'communication' => null,
                'message'       => $readiness['message'],
                'errors'        => $readiness['errors'],
            ];
        }

        return [
            'success'       => true,
            'communication' => $communication->to_array(),
            'message'       =>
                'The communication has been prepared for delivery.',
            'errors'        => [],
        ];
    }

    // -------------------------------------------------
    // Delivery Lifecycle
    // -------------------------------------------------

    /**
     * Begin delivery through a provider.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function begin_delivery(
        SOF_Communication $communication
    ): array {

        // -------------------------------------------------
        // Confirm Delivery May Begin
        // -------------------------------------------------

        if ($communication->get_status() !== 'approved') {
            return $this->failure(
                'delivery_not_allowed',
                'Communication delivery may begin only from the approved state.',
                []
            );
        }

        // -------------------------------------------------
        // Confirm Delivery Readiness
        // -------------------------------------------------

        $readiness =
            $this->assess_readiness(
                $communication
            );

        if (!$readiness['ready']) {
            return $this->failure(
                $readiness['status'],
                $readiness['message'],
                $readiness['errors']
            );
        }

        // -------------------------------------------------
        // Delivery May Begin
        // -------------------------------------------------

        return $this->success(
            'approved',
            'Communication is approved and ready to begin delivery.'
        );
    }

    /**
     * Record completed provider delivery.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     delivered: int,
     *     failed: int,
     *     errors: array<int, string>
     * }
     */
    public function complete_delivery(
        SOF_Communication $communication,
        int $delivered_count,
        int $failed_count = 0
    ): array {
        if (!$communication->is_sending()) {
            return [
                'success'   => false,
                'status'    => 'not_sending',
                'message'   =>
                    'Communication delivery has not been started.',
                'delivered' => 0,
                'failed'    => 0,
                'errors'    => [],
            ];
        }

        $delivered_count = max(0, $delivered_count);
        $failed_count = max(0, $failed_count);

        $communication->mark_sent(
            $delivered_count,
            $failed_count
        );

        return [
            'success'   => true,
            'status'    => 'sent',
            'message'   => $failed_count > 0
                ? 'The communication was delivered with some failures.'
                : 'The communication was delivered successfully.',
            'delivered' => $delivered_count,
            'failed'    => $failed_count,
            'errors'    => [],
        ];
    }

    /**
     * Record failed provider delivery.
     *
     * @param array<int, string> $errors
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     delivered: int,
     *     failed: int,
     *     errors: array<int, string>
     * }
     */
    public function fail_delivery(
        SOF_Communication $communication,
        int $failed_count = 0,
        array $errors = []
    ): array {
        if (!$communication->is_sending()) {
            return [
                'success'   => false,
                'status'    => 'not_sending',
                'message'   =>
                    'Communication delivery has not been started.',
                'delivered' => 0,
                'failed'    => 0,
                'errors'    => [],
            ];
        }

        $failed_count = max(0, $failed_count);

        $communication->mark_delivery_failed(
            $failed_count
        );

        if (empty($errors)) {
            $errors[] = 'The communication could not be delivered.';
        }

        return [
            'success'   => false,
            'status'    => 'delivery_failed',
            'message'   => 'Communication delivery failed.',
            'delivered' => 0,
            'failed'    => $failed_count,
            'errors'    => $errors,
        ];
    }

    /**
     * Mark a sent communication as completed.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    public function complete(
        SOF_Communication $communication
    ): array {
        if ($communication->get_status() !== 'sent') {
            return $this->failure(
                'not_sent',
                'The communication must be sent before it can be completed.'
            );
        }

        $communication->mark_completed();

        return $this->success(
            'completed',
            'The communication lifecycle has been completed.'
        );
    }

    // -------------------------------------------------
    // Provider Coordination
    // -------------------------------------------------

    /**
     * Delivery requires a provider.
     *
     * This method remains as the provider boundary until the
     * communication provider contract is introduced.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     delivered: int,
     *     failed: int,
     *     errors: array<int, string>
     * }
     */
    public function deliver(
        SOF_Communication $communication
    ): array {
        $prepared = $this->prepare_for_delivery($communication);

        if (!$prepared['success']) {
            return [
                'success'   => false,
                'status'    => 'not_ready',
                'message'   => $prepared['message'],
                'delivered' => 0,
                'failed'    => 0,
                'errors'    => $prepared['errors'],
            ];
        }

        return [
            'success'   => false,
            'status'    => 'provider_required',
            'message'   =>
                'A communication provider is required before delivery can occur.',
            'delivered' => 0,
            'failed'    => 0,
            'errors'    => [
                'No communication delivery provider has been configured.',
            ],
        ];
    }

    // -------------------------------------------------
    // Summary
    // -------------------------------------------------

    /**
     * Build a presentation-safe communication summary.
     *
     * @return array{
     *     subject: string,
     *     audience: string,
     *     recipients: int,
     *     channel: string,
     *     status: string,
     *     composed: bool,
     *     verified: bool,
     *     tested: bool,
     *     approved: bool,
     *     sent: bool,
     *     ready: bool
     * }
     */
    public function summarize(
        SOF_Communication $communication
    ): array {
        return [
            'subject'    => $communication->get_subject(),
            'audience'   => $communication->get_audience_key(),
            'recipients' => $communication->get_recipient_count(),
            'channel'    => $communication->get_channel(),
            'status'     => $communication->get_status(),
            'composed'   => $communication->is_composed(),
            'verified'   => $communication->is_verified(),
            'tested'     => $communication->is_tested(),
            'approved'   => $communication->is_approved(),
            'sent'       => $communication->is_sent(),
            'ready'      => $this->is_ready($communication),
        ];
    }

    // -------------------------------------------------
    // Result Helpers
    // -------------------------------------------------

    /**
     * Build a successful lifecycle result.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    protected function success(
        string $status,
        string $message
    ): array {
        return [
            'success' => true,
            'status'  => $status,
            'message' => $message,
            'errors'  => [],
        ];
    }

    /**
     * Build a failed lifecycle result.
     *
     * @param array<int, string> $errors
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     message: string,
     *     errors: array<int, string>
     * }
     */
    protected function failure(
        string $status,
        string $message,
        array $errors = []
    ): array {
        return [
            'success' => false,
            'status'  => $status,
            'message' => $message,
            'errors'  => $errors,
        ];
    }
}