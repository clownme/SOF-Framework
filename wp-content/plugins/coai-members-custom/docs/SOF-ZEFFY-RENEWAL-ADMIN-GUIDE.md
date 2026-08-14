# SOF Zeffy Renewal Admin Guide

**Framework:** SOF / Zeffy  
**Environment:** Production MyCOAI  
**Recovery Point:** RP45 — Zeffy Renewal Integration & Management  
**Date:** August 13, 2026

## Purpose

This guide defines the normal Admin process for bringing Zeffy membership Renewal payments into MyCOAI through SOF.

The operating principle is:

> Zeffy provides payment facts. SOF determines business meaning. Humans resolve uncertainty and authorize consequential membership changes.

The normal Renewal process no longer requires downloading a Zeffy spreadsheet, converting it to CSV, uploading it, running a dry run, and then performing a final import. The API-based workflow is now the normal production process.

## Normal Admin Workflow

### Step 1 — Retrieve Zeffy Transactions

In WordPress Admin, open **Zeffy Import**.

Use:

**Sync Zeffy Transactions**

SOF retrieves recent Zeffy payments and records/refeshes them in the SOF transaction ledger.

Normal result:
- New payments are inserted into `wp_sof_zeffy_transactions`.
- Previously discovered payments are refreshed without destroying established SOF assessment or processing knowledge.
- Renewal transactions begin with `processing_status = discovered`.
- New identities begin with `identity_status = unassessed`.

### Step 2 — Assess Renewals

Use:

**Assess Renewals**

This is the normal one-click assessment action. It performs the Renewal assessment sequence:

1. Transaction assessment
2. Member identity assessment
3. Renewal business assessment

The summary reports:
- Renewal transactions assessed
- Member identities matched
- Ready to Apply
- Possible Previously Applied
- Requiring Management Review
- Unable to Assess

A healthy completed assessment should normally have **0 unable to assess**. If it does not, inspect the Renewal Identity Review or use the troubleshooting guide.

## Renewal Identity Review

If SOF cannot safely identify one MyCOAI member, a **Renewal Identity Review** card appears directly between Step 2 and Step 3.

Do not bypass this condition.

SOF may show:
- Zeffy buyer name
- Zeffy email
- Membership product
- Identity result
- Match method
- Reason SOF stopped
- Candidate MyCOAI member evidence

Example validated in Production:

- Zeffy buyer: Lillian Knight Faison
- Zeffy email differed from the MyCOAI email
- Exact first and last name identified one candidate
- SOF recorded `review_required`
- Admin reviewed the evidence and selected **This Is the Member**
- Confirmation screen displayed Zeffy Payment vs Selected MyCOAI Member
- Admin used **Confirm Match**
- No membership date or membership status changed during identity confirmation

After confirming an identity, run **Assess Renewals** again so SOF can evaluate the newly established member identity against current membership facts.

## Step 3 — Manage Renewals in MyCOAI

Use:

**Open Renewal Management**

The MyCOAI Renewal Management Review page presents only the current business situations needing action.

Possible queues:

### Possible Previously Applied

SOF found that the member's current expiration already equals the standard expiration this Renewal would establish.

Available actions include:
- **Confirm Already Applied**
- **Renewal Still Needs Processing**
- **Needs Further Review**

Use **Confirm Already Applied** only after reviewing the evidence and confirming that the Renewal has already been reflected in the member record.

This decision does not extend the membership again.

### Management Review

SOF found conflicting membership evidence, such as an existing expiration later than the standard expiration for this payment.

Review the current member facts before deciding whether the Renewal was already applied, still needs processing, or requires further review.

### Ready to Apply

SOF found no conflicting membership evidence and calculated a proposed Renewal Date and Expiration Date.

Use:
1. **Review Proposed Change**
2. Compare Current vs Proposed values
3. **Confirm and Apply Renewal**

SOF then:
- Re-reads the current member
- Re-assesses immediately before the update
- Updates only the approved Renewal-controlled fields
- Reads the member back
- Normalizes date-vs-datetime storage for verification
- Verifies the stored values
- Records the completed decision as `applied`

Successful result:

> Renewal Applied  
> The Renewal was applied and the member record was verified.

### Needs Processing

This queue contains transactions management explicitly determined still require processing.

Review the proposed membership change before allowing SOF to update the member.

### Further Review

Use when the available evidence is not sufficient for a safe management decision.

Do not manually alter member dates simply to clear this queue.

## Successful End State

A fully completed processing cycle should return the Current Renewal Situation to:

- Requires Management Attention: 0
- Possible Previously Applied: 0
- Management Review: 0
- Needs Processing: 0
- Further Review: 0
- Ready to Apply: 0

This all-zero state was validated in Production on August 13, 2026.

## Diagnostics & Maintenance

The following WP-Admin controls are retained for technical troubleshooting and are **not part of the normal Admin process**:

- Test Zeffy API Connection
- Discover Zeffy Campaigns
- Assess Renewal Identities
- Assess Renewal Business
- Show Unknown Renewal Products
- Retrieve Recent Renewal Payments
- Dry-Run Renewal API Import

Normal Admin processing should use:

**Sync Zeffy Transactions → Assess Renewals → Resolve Identity Review if shown → Open Renewal Management**

## Legacy File Import

The old File Import process remains available under:

**Legacy File Import — Emergency Use Only**

Use it only when:
- the Zeffy API is unavailable, or
- a historical transaction must be processed manually and cannot be obtained through the current API workflow.

Do not use the legacy File Import as the normal Renewal-processing path.

## Safety Rules

1. Never manually change SOF ledger states simply to make a transaction advance.
2. Never manually set `matched_member_id` unless performing a documented recovery supervised by a developer.
3. Never apply a Renewal merely because it disappeared from a queue.
4. Never extend a membership twice when SOF reports Possible Previously Applied.
5. Never bypass Identity Review when SOF cannot confidently establish the member.
6. Before a consequential update, use the MyCOAI confirmation screen and review Current vs Proposed values.
7. After an apply action, require SOF verification and an `applied` decision record.
8. Use the Developer Troubleshooting Guide before making direct database repairs.

## Daily/Periodic Admin Checklist

1. Open WP-Admin → Zeffy Import.
2. Click **Sync Zeffy Transactions**.
3. Click **Assess Renewals**.
4. Resolve any visible **Renewal Identity Review** items.
5. If an identity was confirmed, click **Assess Renewals** again.
6. Click **Open Renewal Management**.
7. Process each current queue according to SOF's evidence and Recommended Path.
8. Confirm successful Apply results.
9. Return to Current Renewal Situation.
10. Finish only when all expected work is resolved or deliberately placed into Further Review.
