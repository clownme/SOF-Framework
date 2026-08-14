<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletters Framework
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Purpose:
 *     Provide the business foundation for creating and managing
 *     structured organizational newsletters.
 *
 * Responsibilities:
 *     - Represent Newsletter business information
 *     - Represent structured Newsletter sections
 *     - Represent Newsletter templates
 *     - Coordinate Newsletter business behavior
 *     - Prepare Newsletter content for Communications
 *
 * Does NOT:
 *     - Discover Communication recipients directly
 *     - Determine Access authorization
 *     - Send email directly
 *     - Communicate with Amazon SES
 *     - Render Newsletter presentation
 *
 * ============================================================
 */

// -------------------------------------------------
// Framework Paths
// -------------------------------------------------

define(
    'SOF_NEWSLETTERS_PATH',
    __DIR__
);

// -------------------------------------------------
// Business Models
// -------------------------------------------------

require_once SOF_NEWSLETTERS_PATH .
    '/Models/NewsletterDesign.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Models/NewsletterSignature.php';

require_once SOF_NEWSLETTERS_PATH .
    '/Models/Newsletter.php';

require_once SOF_NEWSLETTERS_PATH .
    '/Models/NewsletterSection.php';

require_once SOF_NEWSLETTERS_PATH .
    '/Models/NewsletterTemplate.php';

// -------------------------------------------------
// Repositories
// -------------------------------------------------

require_once SOF_NEWSLETTERS_PATH .
    '/Repositories/NewsletterRepository.php';
    
// -------------------------------------------------
// Shortcodes
// -------------------------------------------------

require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Shortcodes/NewsletterLibraryShortcode.php';

// -------------------------------------------------
// Business Services
// -------------------------------------------------

require_once SOF_NEWSLETTERS_PATH .
    '/Services/NewsletterService.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Services/NewsletterTemplateCatalog.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Services/NewsletterSignatureSuggestionService.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Services/NewsletterAudienceService.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Services/NewsletterCommunicationService.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Services/NewsletterLibraryService.php';
    
// -------------------------------------------------
// Workspaces
// -------------------------------------------------

require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Workspaces/NewsletterLibraryWorkspace.php';

// -------------------------------------------------
// Presentation
// -------------------------------------------------

require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Renderers/NewsletterHtmlRenderer.php';

require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Workspaces/NewsletterPreviewWorkspace.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Workspaces/ComposeNewsletterWorkspace.php';

require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Shortcodes/NewsletterPreviewShortcode.php';

require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Shortcodes/ComposeNewsletterShortcode.php';
    
require_once SOF_NEWSLETTERS_PATH .
    '/Presentation/Shortcodes/NewsletterLibraryShortcode.php';
    




