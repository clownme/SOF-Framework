<?php
/**
 * SOF Database Helper
 *
 * Centralizes custom table names.
 */

if (!defined('ABSPATH')) exit;

function coai_region_officers_table(): string
{
    return 'wp_coai_region_officers';
}

/**
 * Resolve an SOF-owned business table name.
 *
 * SOF business tables use the stable "wp_sof_" namespace
 * independent of the current WordPress installation prefix.
 */

/*
 * SOF Persistence Namespace
 *
 * SOF-owned business tables use a stable wp_sof_ namespace.
 *
 * The active WordPress installation prefix is infrastructure
 * configuration and does not define SOF business persistence.
 */

function sof_table(string $name): string
{
    $name =
        sanitize_key(
            trim($name)
        );

    if ($name === '') {
        return '';
    }

    return 'wp_sof_' . $name;
}