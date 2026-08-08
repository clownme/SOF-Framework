<?php
if (!defined('ABSPATH')) exit;

/**
 * COAI SOF Service Loader
 *
 * Loads Service-Oriented Framework service files.
 */

coai_safe_require('includes/services/distribution-service.php');
coai_safe_require('includes/services/distribution-notification-service.php');
coai_safe_require('includes/services/membership-service.php');
coai_safe_require('includes/services/google-service.php');
coai_safe_require('includes/services/communications-service.php');