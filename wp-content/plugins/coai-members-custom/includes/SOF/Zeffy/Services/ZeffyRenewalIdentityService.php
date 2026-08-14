<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * SOF Zeffy Renewal Identity Service
 * ============================================================
 *
 * Purpose:
 *     Evaluate member identity evidence for a succeeded,
 *     understood Zeffy Renewal transaction.
 *
 * Responsibilities:
 *     - Prefer exact COAI number evidence
 *     - Evaluate email + name evidence
 *     - Recognize shared email addresses
 *     - Identify ambiguous or unresolved people
 *
 * Does NOT:
 *     - Create members
 *     - Renew memberships
 *     - Update Zeffy transactions
 *     - Query Zeffy
 *
 * ============================================================
 */

class SOF_ZeffyRenewalIdentityService
{
    public function assess(
        SOF_ZeffyTransaction $transaction
    ): array {

        /*
         * ----------------------------------------------------
         * Strongest evidence: COAI number
         * ----------------------------------------------------
         */

        if ($transaction->coai_number !== '') {

            $member = coai_get_member_by_coai_number(
                $transaction->coai_number
            );

            if ($member) {
                return [
                    'identity_status' => 'matched',
                    'matched_member_id' =>
                        (int)$member['member_id'],
                    'match_method' => 'coai_number',
                    'reason' =>
                        'Existing member matched by exact COAI number.',
                    'member' => $member,
                ];
            }
        }

        /*
         * ----------------------------------------------------
         * Next evidence: email + person name
         * ----------------------------------------------------
         */

        $emailCandidates = coai_find_members_by_email(
            $transaction->buyer_email
        );

        $incomingFirst = $this->normalize_name(
            $transaction->buyer_first_name
        );

        $incomingLast = $this->normalize_name(
            $transaction->buyer_last_name
        );

        /*
         * ----------------------------------------------------
         * No email match:
         * use exact first + last name as candidate evidence.
         *
         * Name alone does NOT automatically identify a member.
         * ----------------------------------------------------
         */

        if (empty($emailCandidates)) {

            $nameCandidates = coai_find_members_by_exact_name(
                $transaction->buyer_first_name,
                $transaction->buyer_last_name
            );

            /*
             * One exact-name candidate is useful evidence,
             * but requires human review because the email does
             * not agree with the Zeffy transaction.
             */
            if (count($nameCandidates) === 1) {
                return [
                    'identity_status' => 'review_required',
                    'matched_member_id' => null,
                    'match_method' => 'exact_name',
                    'reason' =>
                        'One existing member matches the Renewal buyer by exact first and last name, but the Zeffy email does not match the MyCOAI member email.',
                    'member' => null,
                    'candidates' => $nameCandidates,
                ];
            }

            /*
             * Multiple exact-name records cannot be resolved
             * automatically.
             */
            if (count($nameCandidates) > 1) {
                return [
                    'identity_status' => 'ambiguous',
                    'matched_member_id' => null,
                    'match_method' => 'exact_name',
                    'reason' =>
                        'Multiple existing members match the Renewal buyer by exact first and last name.',
                    'member' => null,
                    'candidates' => $nameCandidates,
                ];
            }

            return [
                'identity_status' => 'unresolved',
                'matched_member_id' => null,
                'match_method' => '',
                'reason' =>
                    'No existing member matched the supplied COAI number, email, or exact first and last name.',
                'member' => null,
                'candidates' => [],
            ];
        }

        $nameMatches = [];

        foreach ($emailCandidates as $candidate) {

            $candidateFirst = $this->normalize_name(
                (string)($candidate['first_name'] ?? '')
            );

            $candidateLast = $this->normalize_name(
                (string)($candidate['last_name'] ?? '')
            );

            if (
                $incomingFirst !== ''
                && $incomingLast !== ''
                && $incomingFirst === $candidateFirst
                && $incomingLast === $candidateLast
            ) {
                $nameMatches[] = $candidate;
            }
        }

        /*
         * One person sharing this email also matches the name.
         */
        if (count($nameMatches) === 1) {

            $member = $nameMatches[0];

            return [
                'identity_status' => 'matched',
                'matched_member_id' =>
                    (int)$member['member_id'],
                'match_method' => 'email_name',
                'reason' =>
                    'Existing member matched by email and exact first/last name.',
                'member' => $member,
            ];
        }

        /*
         * More than one person still matches.
         */
        if (count($nameMatches) > 1) {
            return [
                'identity_status' => 'ambiguous',
                'matched_member_id' => null,
                'match_method' => 'email_name',
                'reason' =>
                    'Multiple existing members match the supplied email and name evidence.',
                'member' => null,
                'candidates' => $nameMatches,
            ];
        }

        /*
         * The email exists, but it belongs to another person
         * or several household members.
         */
        return [
            'identity_status' => 'review_required',
            'matched_member_id' => null,
            'match_method' => 'shared_email',
            'reason' =>
                'The email exists in MyCOAI, but the Renewal buyer name does not uniquely identify an existing member.',
            'member' => null,
            'candidates' => $emailCandidates,
        ];
    }

    protected function normalize_name(string $value): string
    {
        return strtolower(
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $value
                )
            )
        );
    }
}