# Release, backup, recovery, and incident runbook

Policy and procedure outline, not verified hosting commands. No production access or action is authorised by this document. Fill the missing environment/owner details through safe discovery before a live release.

## Source-specific handling before any live change

The attached archive embeds credentials in `db.php` and includes `error_log`/`auth/error_log`; the SQL dump contains private operational/account data. Keep originals restricted and unchanged; never place them, this review's extracted working copy, or unrestricted documentation in the public web root. The updated pack contains Markdown only. Supplied `.htaccess` proves an HTTPS redirect rule exists, not that logs/dumps/docs are inaccessible upstream.

Do not run a PHP page for discovery: its includes can connect using real credentials. No PHP execution or SQL import was performed for this review. Confirm a safe separate configuration and target before any future test; syntax-only lint is different from executing a page.

Priority containment must include direct endpoint role enforcement, expense edit/delete handling, CSRF and existing admin recovery. Preserve a legitimate authorised correction/recovery route and all original rows. Disabling a menu entry alone does not contain the handler; removing the historical recovery tool without a replacement can block legitimate accounting repair.

Use the snapshot's nine-table/row profile only at that snapshot's cutoff; live transactions will continue. Preserve current UTC/session/charset contracts during release and prove the host has required mysqli/mysqlnd and future PDO extensions. No source release ID or backup/restore proof has yet been established.

## Assign before release

| Responsibility/configuration | Current status |
| --- | --- |
| Business/financial owner | Eugene; any delegate must be explicit |
| Technical release operator and independent reviewer | Not assigned in this pack |
| Production host, served root, DB identity, deployed revision | Unverified; store secrets separately |
| Backup method/location, keys, restore operator, monitoring | Unverified |
| Maximum acceptable downtime/data loss (RTO/RPO) | Owner decision needed; do not invent targets |
| Maintenance window, staff notification, monitored period | Per-release decision |
| Approved outage recording and later reconciliation procedure | Must be agreed with reception/owner before a risky release |

## Backup and restore proof

1. Identify a hosting-compatible consistent backup method covering transactional data and the required schema, migrations, code revision, configuration references, receipt assets/files, and restore prerequisites. Mixed/nontransactional tables need explicit treatment; do not assume a single-transaction dump covers everything.
2. Encrypt and restrict backups; keep an independently protected/offsite copy and required decryption/access material with authorised custodians. Verify monitoring detects failed/stale backups. Retention is an owner-approved policy; do not prune financial history as routine cleanup.
3. Restore into an isolated target with production integrations, jobs, callbacks, outbound messages, and money movement disabled. Restrict real guest data; do not restore into an exposed development site.
4. Verify schema, latest expected event/cutoff, IDs/protected fields/links, row counts, exact grouped totals, receipts, and representative read workflows. Compare to the backup's consistent cutoff, not a live database that has since changed.
5. Measure elapsed restore time, actual data-loss window, and evidence needed to preserve/replay later events. Record who ran the drill, when, source backup identifier, target identity, commands (without secrets), results, and failures.

**A successful backup job is not a successful restore test.** Before a high-risk release, evidence must be relevant to the actual current schema and recovery method.

## Prepare a concrete release record

Use [CHANGE_TEMPLATE.md](CHANGE_TEMPLATE.md). Include:

- Exact production target and deployable revision/artifact; PHP/dependency/schema compatibility and deployment order.
- Completed tests, independent review, security/financial reconciliation, migration rehearsal, backup/restore evidence, unresolved blockers.
- Scoped feature flags and default state, all writers/jobs/in-flight requests affected, maintenance/read-only handling where necessary, and staff instructions.
- Expected changes, health signals/baseline and thresholds, named monitor, stop conditions, code/flag/forward-repair path, and post-release review.
- Explicit protection of events created after backup or deployment; no destructive schema rollback or old snapshot overwrite.

Before seeking production approval, finish all authorised preparation so the owner reviews a real candidate. Record target, exact commands/workflow, effects, verification, and recovery. A general wish to refactor is not deployment approval.

## Controlled deployment sequence

1. Confirm the exact target, artifact/checksum, approval scope, operator, reviewer, and recovery readiness. Do not print secrets while checking configuration.
2. Verify backup freshness/consistency and relevant restore evidence. Capture a protected baseline using a consistent cutoff: event IDs/counts/totals, booking state, audit/idempotency health, cash/MoMo outstanding exceptions, error rates, and DB performance.
3. Apply only the reviewed additive steps in the documented order. No exploratory production fixes, surprise bulk updates, or unrelated dependency/runtime changes.
4. Enable a limited coherent slice where the architecture supports it. Payment write routing must have one authority; random old/new writer splitting can break constraints/audit. A flag cannot make incompatible data safe.
5. Check read-only health and relevant real operation evidence. Do not create fake live bookings/payments, delete probe transactions, or send real money as a smoke test. If a special live operation is necessary, obtain specific approval, label/account for it, and preserve the full audit/correction trail.
6. Monitor logs, critical journeys, reconciliation, locks, and operational feedback for the agreed period. Expand only when agreed signals hold. Record actual results; stop on any integrity failure.

## Stop conditions

Stop the affected rollout/write path for unexplained financial changes, missing audit/provenance, duplicate payments/receipts, unauthorised access, conflicting room allocations, cash/MoMo mismatch attributable to the change, unknown commit outcome, unacceptable lock/latency/error impact, or an unverified recovery path. Set numeric operational thresholds from baseline per release; do not invent them here. Investigate existing mismatches without editing data to clear an alert.

## Recovery without losing new transactions

Code rollback, schema rollback, and data restore are different operations. Prefer a tested flag/code rollback only when the prior version can safely read/write all events produced by the new version and still enforce required controls. Otherwise pause only affected writes and perform a reviewed forward fix.

Never restore an old backup over the live database to “undo” a release after new transactions exist. Restore to an isolated environment, identify all post-backup events, reconcile them, and design an authorised recovery/replay/cutover. Replay must be idempotent and preserve IDs/provenance or an explicit stable mapping. Binary logs or another recovery mechanism may help only if configured and tested; do not assume they exist.

Preserve receipts and any approved outage records. Pending/uncertain external payments must be reconciled with the merchant/provider before retry/refund. A degraded-mode paper register, if approved, needs unique temporary references, responsible actors, custody tracking, and controlled later entry with duplicate checks; never operate an untracked second ledger.

## Incident procedure

1. Protect guests and funds by isolating the affected path with the least necessary disruption. Retain unaffected safe reception operations when possible.
2. Notify the authorised owner/operator using the established private channel. Preserve event/audit IDs, relevant revisions, redacted logs, and consistent snapshots. Restrict access; do not erase logs or “repair” totals during investigation.
3. Establish the affected time range, actors/resources, financial exposure, external payment status, and latest trustworthy recovery point. Mark unknowns explicitly; an anomaly is evidence for review, not proof a named staff member committed fraud.
4. Reconcile original events and independent cash/merchant evidence; make any necessary correction through the authorised linked workflow. Secret rotation, live recovery, and data remediation require specific authority.
5. Verify containment/fix with relevant tests and reconciliation, then restore the path through a reviewed release. Record cause, actual impact, preserved evidence, approved corrections, and prevention tasks without publishing private data.

## Daily/shift accountability — proposed operating view

Show charges/revenue under the approved definition, cash receipts, staff-recorded/verified MoMo, refunds/credits, expenses vs paid cash outflows, guest balances, custody transfers, counted/expected cash, variance, unmatched merchant entries, and approval/audit exceptions. Keep original rows accessible with role-appropriate access.

Alerts need a named reviewer, evidence link, status, and resolution reason. No automatic balancing, blame assignment, AI posting, or silent exception dismissal. Agree reporting cadence, owners, and thresholds separately; this pack creates no scheduled task or integration.

