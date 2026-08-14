<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Service
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Newsletter
 *
 * Purpose:
 *     Coordinate Newsletter business behavior.
 *
 * Responsibilities:
 *     - Create Newsletter business objects
 *     - Coordinate Newsletter preparation
 *     - Prepare Newsletter information for Communications
 *
 * Does NOT:
 *     - Determine Access authorization
 *     - Discover Membership audiences
 *     - Deliver email
 *     - Communicate directly with providers
 *
 * ============================================================
 */

class SOF_NewsletterService
{
    /**
     * Create a new draft Newsletter.
     */
    public function create_draft(
        string $title,
        string $subject,
        string $template_key = 'standard'
    ): SOF_Newsletter {

        return new SOF_Newsletter(
            null,
            $title,
            $subject,
            $template_key,
            null,
            [],
            'draft'
        );
    }
}