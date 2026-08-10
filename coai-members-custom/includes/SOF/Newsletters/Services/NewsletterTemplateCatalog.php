<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Newsletter Template Catalog
 * ============================================================
 *
 * Framework:
 *     Newsletters
 *
 * Layer:
 *     Business
 *
 * Service:
 *     Newsletter Template Catalog
 *
 * Purpose:
 *     Describe the Newsletter templates available to the
 *     organization.
 *
 * Responsibilities:
 *     - Provide available Newsletter templates
 *     - Resolve templates by stable template key
 *
 * Does NOT:
 *     - Render Newsletter HTML
 *     - Store Newsletter content
 *     - Determine Access
 *     - Deliver Communications
 *
 * ============================================================
 */

class SOF_NewsletterTemplateCatalog
{
    /**
     * Return all available Newsletter templates.
     *
     * @return SOF_NewsletterTemplate[]
     */
    public function all(): array
    {
        return [
            new SOF_NewsletterTemplate(
                'regional',
                'Regional Newsletter',
                'A branded newsletter for communicating with an organizational region.'
            ),
        ];
    }

    /**
     * Find a template by key.
     */
    public function find(
        string $key
    ): ?SOF_NewsletterTemplate {

        foreach ($this->all() as $template) {
            if ($template->get_key() === $key) {
                return $template;
            }
        }

        return null;
    }
}