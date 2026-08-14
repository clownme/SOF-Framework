<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Communications Framework
 * ============================================================
 *
 * Framework:
 *     Communications
 *
 * Purpose:
 *     Deliver business communications to the appropriate
 *     audience through one or more communication channels.
 *
 * Responsibilities:
 *     - Build communication audiences
 *     - Deliver communications
 *     - Manage communication templates
 *     - Record communication history
 *     - Support multiple delivery providers
 *
 * Does NOT:
 *     - Determine business rules
 *     - Query unrelated business data
 *     - Render dashboards
 *
 * ============================================================
 */

// -------------------------------------------------
// Framework Paths
// -------------------------------------------------

define('SOF_COMMUNICATIONS_PATH', __DIR__);

// -------------------------------------------------
// Business Objects
// -------------------------------------------------

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/Communication.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationSender.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationAudience.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationAudiencePopulation.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationFacts.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationRecipients.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationRecipientSelection.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationDeliveryQueueItem.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationAssessment.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationRecommendation.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationAvailableActions.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Models/CommunicationSituation.php';
    
// -------------------------------------------------
// Repositories
// -------------------------------------------------

require_once SOF_COMMUNICATIONS_PATH .
    '/Repositories/CommunicationRepository.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Repositories/CommunicationDeliveryQueueRepository.php';

// -------------------------------------------------
// Delivery Providers
// -------------------------------------------------

require_once SOF_COMMUNICATIONS_PATH .
    '/Providers/CommunicationDeliveryProvider.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Providers/WordPressMailDeliveryProvider.php';
    
// -------------------------------------------------
// Business Services
// -------------------------------------------------

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationsService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationAudiencePopulationService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationAudienceService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationCompositionService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationAudienceService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationSenderService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationCompositionService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationPersistenceService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationDeliveryService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationDeliveryQueueService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationDeliveryWorkerService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationDeliveryRunnerService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationTestDeliveryService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationReleaseService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationRecipientEligibilityService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationRecipientsService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationAssessmentService.php';
    
require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationRecipientSelectionService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationRecommendationService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationAvailableActionsService.php';

require_once SOF_COMMUNICATIONS_PATH .
    '/Services/CommunicationSituationService.php';

// -------------------------------------------------
// Presentation
// -------------------------------------------------

require_once SOF_COMMUNICATIONS_PATH .
    '/Presentation/presentation.php';

// -------------------------------------------------
// Background Delivery
// -------------------------------------------------

add_action(
    SOF_CommunicationDeliveryRunnerService::CRON_HOOK,
    [
        SOF_CommunicationDeliveryRunnerService::class,
        'handle',
    ],
    10,
    1
);

// -------------------------------------------------
// Background Delivery Recovery
// -------------------------------------------------

add_filter(
    'cron_schedules',
    static function (
        array $schedules
    ): array {

        if (!isset($schedules['sof_every_minute'])) {

            $schedules['sof_every_minute'] = [
                'interval' =>
                    60,

                'display' =>
                    'SOF Every Minute',
            ];
        }

        return $schedules;
    }
);

add_action(
    SOF_CommunicationDeliveryRunnerService::RECOVERY_CRON_HOOK,
    [
        SOF_CommunicationDeliveryRunnerService::class,
        'recover_pending_deliveries',
    ]
);

if (
    !wp_next_scheduled(
        SOF_CommunicationDeliveryRunnerService::RECOVERY_CRON_HOOK
    )
) {

    wp_schedule_event(
        time() + 5,
        'sof_every_minute',
        SOF_CommunicationDeliveryRunnerService::RECOVERY_CRON_HOOK
    );
}
