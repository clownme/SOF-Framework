<?php
/**
 * SOF Magazine Model
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOF_Magazine
{
    public string $title = '';
    public ?string $publication_name = null;
    public ?string $issue_label = null;
    public ?string $volume = null;
    public ?string $issue_number = null;
    public ?string $description = null;
    public string $year_folder = '';
    public string $file_name = '';
    public string $file_path = '';
    public string $file_url = '';
    public ?int $display_year = null;
    public ?int $start_month = null;
    public ?int $end_month = null;
    public ?string $issue_type = null;
    public string $status = 'imported';
    public ?string $notes = null;
}
