<?php
/**
 * SOF Magazine Scanner
 *
 * Scans wp-content/uploads/magazines/{year}/ PDFs
 * and creates magazine records.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOF_MagazineScanner
{
    public static function scan(): array
    {
        global $wpdb;

        $upload = wp_upload_dir();

        $base_dir = trailingslashit($upload['basedir']) . 'coai-magazines';
        $base_url = trailingslashit($upload['baseurl']) . 'coai-magazines';

        $result = [
            'scanned' => 0,
            'created' => 0,
            'skipped' => 0,
            'missing_folder' => false,
        ];

        if (!is_dir($base_dir)) {
            $result['missing_folder'] = true;
            return $result;
        }

        $table = sof_magazine_table_name();
        $year_dirs = glob($base_dir . '/*', GLOB_ONLYDIR);

        if (empty($year_dirs)) {
            return $result;
        }

        foreach ($year_dirs as $year_dir) {
            $year_folder = basename($year_dir);
            $pdfs = glob($year_dir . '/*.pdf');

            if (empty($pdfs)) {
                continue;
            }

            foreach ($pdfs as $pdf_path) {
                $result['scanned']++;

                $file_name = basename($pdf_path);
                $relative_path = 'magazines/' . $year_folder . '/' . $file_name;
                $file_url = trailingslashit($base_url) . rawurlencode($year_folder) . '/' . rawurlencode($file_name);

                $existing = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT id, cover_attachment_id FROM {$table} WHERE file_path = %s LIMIT 1",
                        $relative_path
                    )
                );

                if ($existing) {
                    if (
                        empty($existing->cover_attachment_id)
                        && class_exists('SOF_MagazineCoverGenerator')
                    ) {
                        $parsed = self::parse_file_name($file_name, $year_folder);

                        $cover_id = SOF_MagazineCoverGenerator::generate_from_pdf(
                            $pdf_path,
                            $parsed['issue_label'] ?: $parsed['title']
                        );

                        if ($cover_id > 0) {
                            $wpdb->update(
                                $table,
                                [
                                    'cover_attachment_id' => $cover_id,
                                    'updated_at'          => current_time('mysql'),
                                ],
                                [
                                    'id' => (int) $existing->id,
                                ],
                                [
                                    '%d',
                                    '%s',
                                ],
                                [
                                    '%d',
                                ]
                            );
                        }
                    }

                    $result['skipped']++;
                    continue;
                }

                $parsed = self::parse_file_name($file_name, $year_folder);

                $wpdb->insert(
                    $table,
                    [
                        'title'            => $parsed['title'],
                        'publication_name' => $parsed['publication_name'],
                        'issue_label'      => $parsed['issue_label'],
                        'volume'           => $parsed['volume'],
                        'issue_number'     => $parsed['issue_number'],
                        'description'      => $parsed['description'],
                        'cover_attachment_id' => SOF_MagazineCoverGenerator::generate_from_pdf(
                            $pdf_path,
                            $parsed['issue_label'] ?: $parsed['title']
                        ),
                        'year_folder'      => $year_folder,
                        'file_name'    => $file_name,
                        'file_path'    => $relative_path,
                        'file_url'     => $file_url,
                        'display_year' => $parsed['display_year'],
                        'start_month'  => $parsed['start_month'],
                        'end_month'    => $parsed['end_month'],
                        'issue_type'   => $parsed['issue_type'],
                        'status'       => $parsed['status'],
                        'notes'        => $parsed['notes'],
                        'created_at'   => current_time('mysql'),
                        'updated_at'   => current_time('mysql'),
                    ],
                    [
                        '%s', '%s', '%s', '%s', '%s',
                        '%s', '%d', '%s', '%s', '%s',
                        '%s', '%d', '%d', '%d', '%s',
                        '%s', '%s', '%s', '%s'
                    ]
                );

                $result['created']++;
            }
        }

        return $result;
    }

    protected static function parse_file_name(string $file_name, string $year_folder): array
    {
        $name = pathinfo($file_name, PATHINFO_FILENAME);
        $clean = str_replace(['_', '-'], ' ', $name);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        $months = [
            'jan' => 1, 'january' => 1,
            'feb' => 2, 'february' => 2,
            'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4,
            'may' => 5,
            'jun' => 6, 'june' => 6,
            'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8,
            'sep' => 9, 'sept' => 9, 'september' => 9,
            'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12,
        ];

        $display_year = is_numeric($year_folder) ? (int) $year_folder : null;

        $lower = strtolower($clean);
        $tokens = preg_split('/[\s\/]+/', $lower);

        $found_months = [];

        foreach ($tokens as $token) {
            $token = trim($token);

            if (isset($months[$token])) {
                $found_months[] = $months[$token];
            }
        }

        $start_month = null;
        $end_month = null;
        $issue_type = 'Unknown';
        $status = 'needs_review';

        if (count($found_months) === 1) {
            $start_month = $found_months[0];
            $end_month = $found_months[0];
            $issue_type = 'Monthly';
            $status = 'imported';
        } elseif (count($found_months) >= 2) {
            $start_month = min($found_months);
            $end_month = max($found_months);
            $issue_type = 'Multi-Month';
            $status = 'imported';
        }

        if (str_contains($lower, 'convention')) {
            $issue_type = 'Convention';
            $status = 'imported';
        }

        return [
            'title'            => self::make_title($clean, $display_year),
            'publication_name' => 'The New Calliope',
            'issue_label'      => self::make_issue_label($start_month, $end_month, $display_year, $clean),
            'volume'           => self::parse_volume($clean),
            'issue_number'     => self::parse_issue_number($clean),
            'description'      => 'Official Publication of Clowns of America, International',
            'display_year' => $display_year,
            'start_month'  => $start_month,
            'end_month'    => $end_month,
            'issue_type'   => $issue_type,
            'status'       => $status,
            'notes'        => $status === 'needs_review' ? 'Filename could not be confidently parsed.' : null,
        ];
    }

    protected static function make_issue_label(?int $start_month, ?int $end_month, ?int $year, string $fallback): string
    {
        $month_names = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        if ($start_month && $end_month && isset($month_names[$start_month], $month_names[$end_month])) {
            $label = $month_names[$start_month];

            if ($end_month !== $start_month) {
                $label .= ' – ' . $month_names[$end_month];
            }

            if ($year) {
                $label .= ' ' . $year;
            }

            return $label;
        }

        return self::make_title($fallback, $year);
    }

    protected static function parse_volume(string $clean): ?string
    {
        if (preg_match('/\bvol(?:ume)?\.?\s*([a-z0-9]+)\b/i', $clean, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    protected static function parse_issue_number(string $clean): ?string
    {
        if (preg_match('/\b(?:no|num|number|issue)\.?\s*([a-z0-9]+)\b/i', $clean, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    protected static function make_title(string $clean, ?int $year): string
    {
        $title = ucwords(strtolower($clean));

        if ($year && !str_contains($title, (string) $year)) {
            $title .= ' ' . $year;
        }

        return $title;
    }
}