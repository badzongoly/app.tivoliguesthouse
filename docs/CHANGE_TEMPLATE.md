# Change record — TEMPLATE

Copy for a specific implementation/release; leave this template unchanged. Store sensitive reconciliation outputs elsewhere and link a restricted evidence ID. A small low-risk change may put equivalent evidence in its PR; money/migration work needs a complete record.

## Identity and scope

- Change ID/title:
- Author/date; base and candidate revision:
- Business owner; technical owner; independent reviewer:
- Stage: proposed / implemented / tested / release candidate / deployed:
- Risk and why:
- Requested outcome; explicit non-goals:
- Affected files/endpoints/tables/jobs/callers:
- Observed current behaviour and known defects:
- Supplied source/schema snapshot or deployed revision; relevant R/S review/regression IDs:
- Proposed result and measurable acceptance criteria:

## Contracts and financial policy

- Current URLs/methods, payload types, session/includes, receipts, exports, report definitions:
- Contracts preserved; intentional changes and caller transition:
- Exact money/currency/rounding/rates/time policy affected, or none:
- Charges vs receipts, balance, expense/cash/custody/MoMo effects:
- Protected historical IDs/fields/relationships and preservation proof:
- Correction/approval/closed-period semantics; owner decision reference:
- Unverified assumptions, evidence required, and blockers:

## Design and security

- One authoritative writer and affected legacy bypasses:
- Explicit coverage of `admin_recovery.php`, same-entity legacy writers, original mysqli transaction protections, `admin`/`clerk` session contract and UTC/encoding baseline:
- PDO connection/transaction ownership and engine verification:
- Idempotency key/fingerprint/scope and persistent uniqueness:
- Concurrent operations, lock order/constraints, retry/unknown-commit handling:
- Authentication/resource permissions, CSRF, validation, safe output:
- Durable audit/outbox and external side effects/failure behaviour:
- Secrets/guest data minimisation and evidence access:
- Decision/ADR links; rejected alternatives and reasons:

## Migration and reconciliation — state none if absent

- Migration IDs/checksums; source/target product/schema/version:
- Exact allowed additions/changes; protected source fields:
- Writer/caller inventory, in-flight handling, deployment order:
- Data profiling: duplicates, nulls, orphans, statuses, encoding, precision:
- Batches/checkpoints, source mapping/cutoff, idempotent resume, concurrent capture:
- Rehearsal target/snapshot, measured locks/runtime/storage, outcome:
- Consistent before/after snapshot/cutoff and protected row-level comparisons:
- Exact grouped counts/totals by booking/event/method/staff/date/currency:
- Expected new rows/fields and proof of no legacy/copy double counting:
- Anomalies and resolution; no deletion/fabricated balancing:

## Executed checks

| Acceptance/test ID | Exact command/check | Safe environment/revision | Result | Redacted evidence reference |
| --- | --- | --- | --- | --- |
| | | | Not run | |

Include applicable syntax/static/security, unit, real DB integration/race, contract, workflow, migration, reconciliation, and recovery checks. State blocked/not applicable with why; do not imply they passed.

## Release and recovery — complete before live approval

- Exact production target, artifact/revision, configuration/schema order:
- Named operator, reviewer, business authority, maintenance/staff plan:
- Backup identifier/time/consistency; isolated restore drill/result/timing:
- Agreed recovery time/data-loss objectives and ability to meet them:
- Feature flags/routing and old/new compatibility, including all writers/jobs:
- Stop conditions, baseline/thresholds, health queries/logs, monitor/observation period:
- Code/flag rollback or forward repair procedure, prerequisites, and verification:
- How every event after backup/deployment is preserved and remains usable:
- Outage/uncertain external-payment recording and later reconciliation:
- Exact action for approval; consequences; approval record/date/scope:

Do not fill approval fields from assumption or treat a deployment approval as authority for financial deletion. Production commands are recorded only after the actual host/process is known and reviewed.

## Post-release evidence and handoff

- Actual deployment/migration result and timestamps; deployed revision:
- Executed production read-only/authorised verification and reconciliation:
- Actual exceptions, rollback/forward action, and preserved later events:
- Remaining risks, owner, next action; documents/training updated:
- Distinguish implementation completion from testing/release/deployment status:

