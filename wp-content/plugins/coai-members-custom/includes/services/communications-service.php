<?php
if (!defined('ABSPATH')) exit;

function coai_comm_get_region_email_contacts(string $region): array
{
    global $wpdb;

    $region = trim($region);
    if ($region === '') return [];

    $officers_table = 'wp_coai_region_officers';
    $members_table  = coai_get_members_table();

   $sql = $wpdb->prepare("
    SELECT 
        o.id AS officer_id,
        o.coai_region,
        o.member_id,
        o.contact_title,
        m.full_name,
        m.first_name,
        m.last_name,
        m.email
    FROM {$officers_table} o
    INNER JOIN {$members_table} m
        ON m.member_id = o.member_id
    WHERE o.coai_region = %s
      AND o.is_active = 1
      AND o.notify_email = 1
      AND m.email <> ''
    ORDER BY o.contact_title, m.last_name, m.first_name
", $region);

    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

function coai_comm_notify_region_export(array $export_result): array
{
    $region = trim((string)($export_result['region'] ?? ''));

    if ($region === '') {
        return [
            'success' => false,
            'sent'    => 0,
            'message' => 'No region provided for notification.',
        ];
    }

    if (empty($export_result['success'])) {
        return [
            'success' => false,
            'sent'    => 0,
            'message' => 'Export was not successful; notification not sent.',
        ];
    }

    if (function_exists('coai_distribution_get_notification_contacts_for_export')) {
        $contacts = coai_distribution_get_notification_contacts_for_export($region);
    } else {
        $contacts = coai_comm_get_region_email_contacts($region);
    }

    if (empty($contacts)) {
        return [
            'success' => false,
            'sent'    => 0,
            'message' => 'No active email contacts found for this region.',
        ];
    }

    $filename = (string)($export_result['filename'] ?? '');
    $count    = (int)($export_result['count'] ?? 0);
    
    $link = (string)(
        $export_result['google_file_link']
        ?? $export_result['file_link']
        ?? $export_result['upload']['file_link']
        ?? $export_result['upload']['webViewLink']
        ?? $export_result['folder_url']
        ?? ''
    );
    
    $subject = 'COAI Region Export Available - ' . $region;

    $body = "Hello,\n\n";
    $body .= "The latest COAI member export for {$region} has been uploaded to Google Drive.\n\n";
    $body .= "File: {$filename}\n";
    $body .= "Member Records: {$count}\n";

    if ($link !== '') {
        $body .= "Open File in Google Drive: {$link}\n";
    } else {
        $body .= "Google Drive file link unavailable. Please contact the COAI Office.\n";
    }

    $body .= "\nThank you,\n";
    $body .= "COAI Membership Team\n";
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];

    $sent = 0;
    $errors = [];
    $recipients = [];

    foreach ($contacts as $contact) {
        $to = trim((string)($contact['email'] ?? ''));

        if (defined('COAI_COMM_TEST_MODE') && COAI_COMM_TEST_MODE && defined('COAI_COMM_TEST_EMAIL')) {
            $to = COAI_COMM_TEST_EMAIL;
        }

        if ($to === '') {
            continue;
        }

        $ok = wp_mail($to, $subject, $body, $headers);

        if ($ok) {
            $sent++;

            $recipients[] = trim(
                (string)($contact['coai_region'] ?? $region) .
                ' - ' .
                (string)($contact['full_name'] ?? 'Unknown Contact') .
                ' - ' .
                $to
            );
        } else {
            $errors[] = $to;
        }
    }

    return [
        'success'    => $sent > 0,
        'sent'       => $sent,
        'recipients' => $recipients,
        'errors'     => $errors,
        'message'    => $sent > 0
            ? 'Notification(s) sent successfully.'
            : 'No notifications were sent.',
    ];
}