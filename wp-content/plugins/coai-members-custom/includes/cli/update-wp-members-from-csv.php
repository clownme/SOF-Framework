<?php
if (!defined('WP_CLI') || !WP_CLI) return;

class COAI_Update_Members_From_CSV {

    private $wpdb;
    private $members_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->members_table = function_exists('coai_get_members_table') ? coai_get_members_table() : $wpdb->prefix . 'members';
    }

    private function parse_date($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return null;
        $ts = strtotime($raw);
        if ($ts === false) return null;
        return date('Y-m-d H:i:s', $ts);
    }

    public function run($args, $assoc) {
        $file = $assoc['file'] ?? '';
        if (!$file) {
            WP_CLI::error("Usage: wp coai update-members --file=/full/path/to/file.csv [--dry-run] [--match=email]");
            return;
        }
        $dry_run = isset($assoc['dry-run']);
        $match_by_email = isset($assoc['match']) && $assoc['match'] === 'email';

        if (! file_exists($file)) {
            WP_CLI::error("File not found: $file");
            return;
        }

        $fh = fopen($file, 'r');
        if (!$fh) {
            WP_CLI::error("Unable to open file: $file");
            return;
        }

        // detect delimiter (tabs vs comma)
        $first = fgets($fh);
        rewind($fh);
        $delimiter = (strpos($first, "\t") !== false) ? "\t" : ',';

        // read header
        $header = fgetcsv($fh, 0, $delimiter);
        if (!$header) {
            WP_CLI::error("CSV header not found");
            fclose($fh);
            return;
        }

        // normalize header names to lowercase, trim
        $header_map = array_map(function($h){ return strtolower(trim($h)); }, $header);

        // required columns in your CSV
        $required = ['username','registered_date','membership_expiration','status','name','membership_level_id'];
        $miss = array_filter($required, function($r) use ($header_map) { return !in_array($r, $header_map, true); });
        if (!empty($miss)) {
            WP_CLI::error("Missing required columns: " . implode(', ', $miss));
            fclose($fh);
            return;
        }

        $rownum = 1;
        $updated = 0;
        $not_found = 0;
        $errors = [];
        $actions = []; // record actions for dry-run output (first N)

        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            $rownum++;
            // build associative row
            $r = [];
            foreach ($header_map as $i => $col) {
                $r[$col] = isset($row[$i]) ? trim($row[$i]) : '';
            }

            $username = $r['username'] ?? '';
            $email = $r['email'] ?? '';
            $reg_raw = $r['registered_date'] ?? '';
            $exp_raw = $r['membership_expiration'] ?? '';
            $status = $r['status'] ?? '';
            $name = $r['name'] ?? '';
            $level_id = $r['membership_level_id'] ?? '';

            // Normalize
            $reg = $this->parse_date($reg_raw);
            $exp = $this->parse_date($exp_raw);
            $level_id = ($level_id === '' || strtolower($level_id) === 'nan') ? null : (is_numeric($level_id) ? (int)$level_id : null);

            // Find target row by username (or email if requested)
            $where_sql = '';
            $where_val = null;
            if ($match_by_email && $email !== '') {
                $where_sql = "email = %s";
                $where_val = $email;
            } else {
                if ($username === '') {
                    $not_found++;
                    $errors[] = "Row {$rownum}: no username provided; skipped.";
                    continue;
                }
                $where_sql = "username = %s";
                $where_val = $username;
            }

            $sql = $this->wpdb->prepare("SELECT member_id FROM `{$this->members_table}` WHERE {$where_sql} LIMIT 1", $where_val);
            $member_id = $this->wpdb->get_var($sql);
            if (!$member_id) {
                $not_found++;
                $errors[] = "Row {$rownum}: member not found for " . ($match_by_email ? "email={$email}" : "username={$username}");
                continue;
            }

            // Build data to update: only update columns that are non-empty in CSV
            $data = [];
            $format = [];
            if ($reg !== null) { $data['registered_date'] = $reg; $format[] = '%s'; }
            if ($exp !== null) { $data['membership_expiration'] = $exp; $format[] = '%s'; }
            if ($status !== '') { $data['status'] = sanitize_text_field($status); $format[] = '%s'; }
            if ($name !== '') { $data['full_name'] = sanitize_text_field($name); $format[] = '%s'; }
            if ($level_id !== null) { $data['membership_level_id'] = (int)$level_id; $format[] = '%d'; }
            // always set updated_at
            $data['updated_at'] = current_time('mysql');
            $format[] = '%s';

            if (empty($data)) {
                $errors[] = "Row {$rownum}: nothing to update for member_id {$member_id}";
                continue;
            }

            if ($dry_run) {
                $actions[] = [
                    'member_id' => (int)$member_id,
                    'username' => $username,
                    'email' => $email,
                    'updates' => $data,
                ];
                // keep scanning
                continue;
            }

            $where = ['member_id' => (int)$member_id];
            $ok = $this->wpdb->update($this->members_table, $data, $where, $format, ['%d']);
            if ($ok === false) {
                $errors[] = "Row {$rownum}: DB update failed for member_id {$member_id}";
            } else {
                $updated++;
            }
        } // end while

        fclose($fh);

        if ($dry_run) {
            WP_CLI::success("Dry-run complete. Rows scanned: {$rownum}. Preview of updates (first 25):");
            $preview = array_slice($actions, 0, 25);
            foreach ($preview as $a) {
                WP_CLI::line(sprintf(" member_id=%d username=%s email=%s updates=%s",
                    $a['member_id'], $a['username'], $a['email'], json_encode($a['updates'])
                ));
            }
            if (!empty($errors)) {
                WP_CLI::warning("Dry-run warnings/errors ({$rownum} rows):");
                foreach ($errors as $e) WP_CLI::line(" - $e");
            }
            return;
        }

        // done
        WP_CLI::success("Update finished. Rows scanned: {$rownum}. Updated: {$updated}. Not found/skipped: {$not_found}.");
        if (!empty($errors)) {
            WP_CLI::warning("Some rows had issues:");
            foreach ($errors as $e) WP_CLI::line(" - $e");
        }
    }
}

WP_CLI::add_command('coai update-members', function($args,$assoc){
    $cmd = new COAI_Update_Members_From_CSV();
    $cmd->run($args,$assoc);
});
