# SOF Zeffy Renewal Operations & Troubleshooting Guide

**Audience:** Developer / Technical Support  
**Framework:** SOF / Zeffy  
**Environment:** Production MyCOAI  
**Recovery Point:** RP45 — Zeffy Renewal Integration & Management  
**Date:** August 13, 2026

## Purpose

This document is a support runbook for a developer who did not participate in the SOF Zeffy implementation.

Do not begin by changing data.

Use the SOF troubleshooting sequence:

> Discover Facts → Assess → Recommend → Present → Receive Human Response → Evaluate → Re-Assess → Continue Until Conclusion.

For incidents:

> Discover Facts → Do Not Modify Data → Identify Pipeline Stage → Inspect Ledger State → Inspect Identity → Inspect Business Assessment → Inspect Management Decision → Inspect Member Record → Correct Cause → Re-run Appropriate Stage → Verify Outcome.

## Architecture

The Renewal lifecycle is intentionally separated into responsibilities.

### Zeffy

Zeffy is the source of payment facts:
- payment ID
- campaign ID
- payment status
- amount
- payment date
- buyer identity facts
- rate/product facts

Zeffy does not decide MyCOAI membership state.

### SOF Transaction Ledger

Primary table:

`wp_sof_zeffy_transactions`

Important fields include:
- `id`
- `zeffy_payment_id`
- `zeffy_campaign_id`
- `business_process`
- `payment_status`
- `payment_amount`
- `payment_date`
- `buyer_first_name`
- `buyer_last_name`
- `buyer_email`
- `coai_number`
- `zeffy_rate_id`
- `membership_product`
- `identity_status`
- `matched_member_id`
- `match_method`
- `identity_resolved_by`
- `processing_status`
- `processing_message`
- `discovered_at`
- `assessed_at`
- `processed_at`

Newly discovered records normally begin:
- `identity_status = unassessed`
- `processing_status = discovered`

### Management Decision Ledger

Table:

`wp_sof_zeffy_renewal_management_decisions`

Fields:
- `id`
- `transaction_id`
- `decision`
- `decided_by`
- `decided_at`
- `notes`

`transaction_id` is unique.

Valid decisions:
- `already_applied`
- `needs_processing`
- `further_review`
- `applied`

A management decision records business knowledge. It does not itself update the member.

### Member Record

Primary membership table:

`wp_members`

Renewal-controlled fields:
- `renewal_date`
- `membership_expiration`

The membership repository exposes a narrow update operation for these values. SOF Application Service performs the business authorization and verification around that update.

## Main Code Locations

### WP-Admin Integration

`coai-zeffy-importer.php`

Responsibilities include:
- Zeffy API connection
- campaign/rate mappings
- transaction-ledger sync
- one-click Renewal Assessment
- persistent Renewal Identity Review presentation
- Diagnostics & Maintenance
- Legacy File Import fallback

### SOF Zeffy Framework

`includes/SOF/Zeffy/`

Important components:

- `zeffy.php` — framework loader
- `Models/ZeffyTransaction.php`
- `Models/ZeffyRenewalManagementDecision.php`
- `Repositories/ZeffyTransactionRepository.php`
- `Repositories/ZeffyRenewalManagementDecisionRepository.php`
- `Services/ZeffyRenewalAssessmentService.php`
- `Services/ZeffyRenewalIdentityService.php`
- `Services/ZeffyIdentityResolutionService.php`
- `Services/ZeffyRenewalBusinessAssessmentService.php`
- `Services/ZeffyRenewalReviewService.php`
- `Services/ZeffyRenewalManagementDecisionService.php`
- `Services/ZeffyRenewalApplicationService.php`
- `Presentation/Workspaces/RenewalManagementReviewWorkspace.php`
- `Presentation/Shortcodes/RenewalManagementReviewShortcode.php`

### Membership Repository

`repositories/member-repository.php`

Contains the narrow member Renewal update operation used by SOF.

### Member Portal

`shortcodes/member-portal.php` or the deployed member-portal file location.

The Admin/Manager Member Portal can surface a Renewal Management Review card when current Renewal situations require attention.

## Pipeline Stages

### Stage 1 — Transaction Discovery

Expected facts:
- `business_process = renewal`
- `payment_status = succeeded`
- recognized `membership_product`

A transaction with an unknown product is not safe for automatic Renewal processing.

### Stage 2 — Identity Assessment

Identity states:
- `unassessed` — identity assessment has not been persisted yet
- `matched` — one member identity has been established
- `review_required` — evidence points to a candidate but a human must confirm
- `ambiguous` — multiple plausible identities
- `unresolved` — no sufficient identity evidence

Common match methods:
- `coai_number`
- `email_name`
- `exact_name`
- `human_review`

Important safeguard:

A human-established `matched / human_review` identity must not be overwritten by automatic reassessment.

Non-matched outcomes must be persisted. This was an important RP45 fix; otherwise a transaction could be counted during assessment but return to `unassessed` after the request.

### Stage 3 — Business Assessment

SOF calculates:

`standard expiration = Renewal payment date + 1 year - 1 day`

Business outcomes:
- `ready_to_apply`
- `possible_previously_applied`
- `management_review`
- `cannot_assess`

Interpretation:

**ready_to_apply**  
Current expiration is missing or earlier than the standard expiration.

**possible_previously_applied**  
Current expiration equals the standard expiration the payment would establish.

**management_review**  
Current expiration is later than the standard expiration or the current expiration cannot be safely interpreted.

**cannot_assess**  
SOF does not have complete evidence required for a safe business conclusion.

### Stage 4 — Human Management Decision

The human may record:
- already applied
- still needs processing
- further review

Ready-to-Apply transactions may proceed without a separate `needs_processing` decision because the current SOF business assessment itself authorizes the proposed standard Renewal.

### Stage 5 — Application

Before updating, `SOF_ZeffyRenewalApplicationService`:
1. Requires an established member identity.
2. Re-assesses the Renewal against current member facts.
3. Requires either:
   - current SOF status `ready_to_apply`, or
   - management decision `needs_processing`.
4. Calculates proposed Renewal Date and Expiration.
5. Refuses to write if the member already has the exact intended values.
6. Updates the member.
7. Reads the member back.
8. Normalizes stored date/datetime values to calendar dates.
9. Verifies the result.
10. Records decision `applied`.

## Proven Diagnostic Queries

### Find a member's Zeffy transaction

```sql
SELECT
    id,
    buyer_first_name,
    buyer_last_name,
    buyer_email,
    payment_date,
    payment_status,
    membership_product,
    matched_member_id
FROM wp_sof_zeffy_transactions
WHERE
    buyer_first_name LIKE '%FIRST%'
    OR buyer_last_name LIKE '%LAST%'
ORDER BY id DESC;
```

### Inspect one transaction completely

```sql
SELECT *
FROM wp_sof_zeffy_transactions
WHERE id = TRANSACTION_ID;
```

### Inspect identity state

```sql
SELECT
    id,
    buyer_first_name,
    buyer_last_name,
    matched_member_id,
    identity_status,
    match_method,
    assessed_at
FROM wp_sof_zeffy_transactions
WHERE id = TRANSACTION_ID;
```

### Compare newest succeeded transactions

```sql
SELECT
    id,
    buyer_first_name,
    buyer_last_name,
    payment_date,
    matched_member_id,
    identity_status,
    match_method
FROM wp_sof_zeffy_transactions
WHERE payment_status = 'succeeded'
ORDER BY id DESC
LIMIT 15;
```

### Inspect a management decision

```sql
SELECT *
FROM wp_sof_zeffy_renewal_management_decisions
WHERE transaction_id = TRANSACTION_ID;
```

### Inspect a member's stored Renewal values

```sql
SELECT
    member_id,
    full_name,
    renewal_date,
    membership_expiration
FROM wp_members
WHERE member_id = MEMBER_ID;
```

### Inspect storage format when verification seems wrong

```sql
SELECT
    member_id,
    full_name,
    renewal_date,
    LENGTH(renewal_date) AS renewal_length,
    HEX(renewal_date) AS renewal_hex,
    membership_expiration,
    LENGTH(membership_expiration) AS expiration_length,
    HEX(membership_expiration) AS expiration_hex
FROM wp_members
WHERE member_id = MEMBER_ID;
```

This query was important in RP45 because `membership_expiration` may be stored as a DATETIME such as `2027-07-28 00:00:00` while SOF business assessment uses the equivalent calendar date `2027-07-28`.

## Symptom-Based Troubleshooting

### Renewal email received, but transaction is not visible in Renewal Management

First determine whether it reached the SOF ledger.

Run the transaction search query.

If no ledger record:
1. Test Zeffy API connection.
2. Check Sync Zeffy Transactions.
3. Verify Zeffy campaign ID.
4. Verify payment status is `succeeded`.
5. Verify the rate ID is mapped.
6. Inspect API retrieval limits/pagination if a transaction is not in the retrieved batch.

Do not run business processing against a transaction that is not in the ledger.

### Transaction exists, but `matched_member_id` is NULL

Inspect:
- `identity_status`
- `match_method`
- `assessed_at`

If `unassessed` after Assess Renewals:
- confirm the one-click assessment handler persisted identity results
- confirm the current `ZeffyTransactionRepository::record_identity_assessment()` exists
- confirm the transaction qualifies for `find_identity_ready_renewals()`

If `review_required`, `ambiguous`, or `unresolved`:
- use the persistent Renewal Identity Review card
- review candidate evidence
- use **This Is the Member**
- confirm on the side-by-side identity screen
- re-run **Assess Renewals**

Do not directly populate `matched_member_id` as the normal resolution.

### Assess Renewals reports an unable-to-assess count

Look for the persistent Renewal Identity Review card first.

If no card appears:
1. Inspect the transaction ledger identity states.
2. Confirm non-matched assessment states are persisted.
3. Confirm presentation is rebuilt from persisted ledger knowledge.
4. Check unknown products.
5. Check for missing/invalid member data required by business assessment.

### Assess Renewal Identities appears to do nothing

The individual identity action is a diagnostic action, not the normal workflow.

In RP45 the important UI fix was to make Identity Review persistent and visible in the main Step 1/2/3 workflow.

Use **Assess Renewals** during normal operation and inspect the main Renewal Identity Review card.

### Transaction is matched but not Ready to Apply

Inspect the business assessment facts:
- Zeffy Renewal Date
- current member expiration
- calculated standard expiration

If current expiration equals standard expiration:
- expected result is Possible Previously Applied.

If current expiration is later:
- expected result is Management Review.

If current expiration is earlier:
- expected result is Ready to Apply.

### Renewal Cannot Be Confirmed

Do not immediately re-apply the transaction.

Inspect:
1. transaction ID
2. current queue/business state
3. management decision ledger
4. member's actual Renewal Date and Expiration
5. application response
6. whether the transaction already completed successfully

A transaction disappearing from Ready to Apply after a successful operation may be correct.

### Member updated but SOF says verification values do not match

Inspect `renewal_date` and `membership_expiration` directly.

Check for date-vs-datetime storage.

The RP45 Application Service fix normalizes both stored and proposed values to `Y-m-d` before comparison.

If the member contains the correct business dates but an older code version still reports a mismatch, deploy the normalized verification logic rather than rewriting the member merely to satisfy string equality.

### Member was updated correctly, but no `applied` decision exists

This is an audit-trail failure.

First prove:
- the transaction
- the member ID
- the expected Renewal Date
- the expected Expiration Date
- the actual stored values
- that the apply action really completed

Then repair the decision only if evidence conclusively proves the Renewal was applied and verified.

Do not use an audit repair to conceal an uncertain application result.

### Queue counts unexpectedly include historical records

Inspect `wp_sof_zeffy_renewal_management_decisions`.

Completed/decided historical transactions should not continue to appear as current actionable work.

The Renewal Management Review page is intended to represent current work requiring attention, not the complete historical ledger.

### Unknown Renewal Product

Inspect:
- `zeffy_rate_id`
- `payment_amount`
- Zeffy campaign
- verified product mapping

Do not guess a product mapping.

Add a rate only after validating both the Zeffy rate identity and COAI membership business meaning.

## Known Production Validation Cases

### Lillian Knight Faison — Identity Exception / Already Applied

Facts:
- Zeffy Renewal received August 13, 2026
- Zeffy email differed from MyCOAI member email
- exact first/last name found one member candidate
- SOF persisted `review_required / exact_name`
- Admin confirmed the identity
- identity became matched through human review
- re-assessment moved the Renewal to Possible Previously Applied
- Member Renewal Date: 08/13/2026
- Current Expiration: 08/12/2027
- Standard Expiration: 08/12/2027
- Admin confirmed Already Applied
- no duplicate membership extension occurred

This is the canonical example of why Identity Assessment and Business Assessment are separate.

### Charlotte Nelson — Ready to Apply

Facts:
- Renewal Date: 08/12/2026
- Current Expiration: 02/05/2027
- Proposed Expiration: 08/11/2027
- SOF classified Ready to Apply
- Admin reviewed Current vs Proposed
- SOF applied the Renewal
- member record was read back and verified
- decision recorded as `applied`
- final Current Renewal Situation returned to all zeroes

## What Not To Do

- Do not delete ledger rows to clear a queue.
- Do not edit identity status manually during normal support.
- Do not set a transaction to `applied` without verified member evidence.
- Do not change member Renewal dates merely to make SOF assessment pass.
- Do not use Legacy File Import as the normal API workflow.
- Do not disable the Possible Previously Applied guardrail.
- Do not convert Management Review into automatic application.
- Do not overwrite a human identity resolution with an automatic match.
- Do not trust display disappearance as proof of successful application; verify the member and decision ledger.

## Recovery Principle

The SOF transaction ledger, management decision ledger, and member record form three different kinds of evidence:

1. **What Zeffy said happened**
2. **What SOF/humans concluded should happen**
3. **What MyCOAI currently contains**

Troubleshooting is the process of reconciling those three evidence sources without guessing.
