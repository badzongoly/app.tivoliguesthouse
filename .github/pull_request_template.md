# Tivoli POS change review

## Problem and resulting behaviour

Describe the concrete trigger and outcome. Link the change record and acceptance criteria.

For source-derived fixes, cite the R finding/S regression case in `docs/CODEBASE_REVIEW.md` / `docs/TESTING.md`. Identify intended behaviour changes separately from structural extraction.

## Scope and compatibility

- Paths/endpoints/callers affected:
- Structural refactor, defect fix, financial-policy change, or feature:
- URLs, payloads/types, session keys, receipts, exports, statuses, and legacy callers preserved or deliberately migrated:
- Relevant runtime/database versions actually verified:

## Financial and security impact

- Historical events/IDs/attribution preserved; no deletion, concealment, or silent rewrite:
- Money/rates/revenue/receipts/balances affected:
- Permissions/CSRF/validation/audit changes:
- Atomicity, idempotency, uniqueness, and concurrency controls:
- Data migration/backfill/cutover: none, or link reviewed plan:

## Verification evidence

List exact commands, environment, results, and evidence locations. Separate executed, blocked, and not applicable checks. Do not paste sensitive output.

| Check | Result/evidence |
| --- | --- |
| Supported-runtime lint and relevant static checks | |
| Applicable unit/integration/contract/regression tests | |
| Negative, failure, duplicate, and concurrency tests | |
| Financial and historical row-level reconciliation | |
| Receipt/report/export compatibility | |
| Migration rehearsal and restore/rollback, if applicable | |

## Release and recovery

- Feature flag/cutover and legacy-writer handling:
- Release owner and independent reviewer:
- Backup/restore evidence and treatment of post-deployment transactions:
- Stop conditions/monitoring/recovery path:
- Outstanding risks and blockers:

Do not mark production approval or deployment complete unless they actually occurred. A merged PR does not authorise live data changes.

