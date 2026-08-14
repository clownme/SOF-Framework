<?php
if (!defined('ABSPATH')) exit;

/**
 * COAI Admin Loader
 */

if (is_admin()) {
    coai_safe_require('includes/dashboard/admin-docs.php');
}