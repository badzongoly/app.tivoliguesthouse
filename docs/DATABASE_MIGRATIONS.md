# Database migration and legacy compatibility policy

The supplied export's DDL was inspected; no live schema parity was verified and no migration is included. Read [legacy schema/contracts](LEGACY_CONTRACTS.md), [financial integrity](FINANCIAL_INTEGRITY.md), [testing](TESTING.md), and [operations](OPERATIONS.md).

## Observed schema hazards to plan around

- All nine tables are InnoDB; export header reports MySQL `8.0.46-37`. Most tables are `utf8mb3_unicode_ci`; `stock_movements` uses `utf8mb4_0900_ai_ci`. Preserve this baseline until a separately tested encoding/version transition.
- `payments` and `booking_extras` cascade-delete from bookings. Plan a specific restrictive-FK change after writer/data/grant inventory and rehearsal; it is not implemented by this document. Do not disable FK checks, delete dependent records or change booking IDs.
- `bookings.rate_per_night` is nullable and 11 snapshot rows lack it. Never fill those with current `rooms.rate`. Rate/charge snapshot design needs evidenced legacy handling and an approved new-write cutover.
- `stock_movements.ref_id` is not a foreign key; 2,664 exported booking-extra movements refer to zero. Adding an FK or auto-filling links from timing/order would conceal uncertainty or fail. Preserve original rows and investigate a separate verified mapping model.
- `payments`/`bookings` lack recording actor and durable command identity; no audit/refund/receipt/cash-session/reconciliation tables exist. Add controls prospectively and retain null/unknown provenance for old entries. Do not pretend a guessed backfill is recorded history.
- `expenses.user_id` and `stock_movements.created_by` are non-FK references; users have no deactivation flag. Introduce compatible identity retention before restricting existing deletion, with a controlled operational replacement.
- Preserve existing enums: `admin`/`clerk`, booking and room statuses, payment `cash`/`momo`/`card`, and broader expense methods. A new permission model is not a reason to rewrite stored values.

## Before designing a migration

Confirm export-to-live parity, grants, SQL mode/isolation, triggers/jobs not supplied, data growth and backup method. Inventory **every writer**, starting with `checkin.php`, `checkout.php`, `admin_recovery.php`, `expenses.php`, `stock.php`, room/catalog/user handlers, then hosting jobs/imports/callbacks/manual SQL not included in the archive.

Classify historical source fields, mutable operational fields, projections, new evidence, and sensitive data. Define the exact protected fields and expected additions. Unknown ownership or accounting meaning blocks a risky migration, not documentation/discovery work.

Use a change record with stable migration ID, checksum/version, source/target schema, prerequisites, expected lock/runtime/storage impact, rehearsal evidence, backup/restore reference, postchecks, and recovery plan. Never edit an already-applied migration to change its meaning; add a new one.

## Expand → transition → contract

| Stage | Required behaviour | Exit evidence |
| --- | --- | --- |
| Expand | Add compatible tables/nullable fields/indexes as designed; old code continues working. New constraints only after profiling legacy data. | Old code on new schema passes; no hidden defaults or rewritten history |
| Transition | Introduce one authoritative writer and explicit source mapping/cutoff. Backfill only approved derived/new data; compare reads safely. | Every writer accounted for; row-level and aggregate comparisons pass; retry/resume proven |
| Switch | Enable the scoped path after tested flags/routing and verified compatibility. Deal with in-flight requests/jobs. | Critical workflows and reconciliation pass; stop/fallback verified |
| Contract | Remove redundant implementation/schema only in a separate reviewed release after all consumers have migrated. | Dependency/usage evidence, retention decision, recovery plan; no historical financial evidence removed |

Do not combine a rename/drop/type conversion and write-path replacement just because one deployment seems convenient. Compatibility views/adapters can help only when their write semantics, permissions, and all callers are tested. A feature flag is not a substitute for compatibility.

## Hard data rules

- Never delete, truncate, reset, renumber, overwrite, or hide historical financial source rows. Do not use cascade deletes, `REPLACE`, upsert updates, soft-delete filters, deduplication, or destructive “down” migrations to bypass preservation.
- Do not disable constraints/foreign-key checks to force a live migration. Identify orphan/duplicate/bad-data conditions, preserve evidence, and design a reviewed resolution. Preserve legitimate legacy unknowns instead of making up actors/references/times to satisfy a new `NOT NULL`.
- Do not change money scale, precision, sign, rounding, rate snapshots, currency, IDs, or timestamp meaning without a separately approved conversion specification, representative tests, and zero unexplained differences.
- Do not import a historical SQL dump over production or run bootstrap schema/seed/reset commands against the live system. A restored copy is isolated until a separately approved recovery/cutover.
- Default backfills write only new tables/columns/projections; original financial fields remain untouched. A necessary operational correction must use the approved audited workflow, not an opaque maintenance update.
- Give each imported/mapped legacy event a stable source identity and unique mapping. Preserve original event time and separately record import time. Prevent old source plus mapped copy from being counted twice.

## Backfill requirements

1. Dry-run on a protected authorised snapshot in isolation; external jobs/integrations are disabled. Ordinary tests remain synthetic.
2. Use bounded batches with a stable key/cutoff, resumable checkpoints, explicit progress, and idempotent reruns. Do not rely only on `OFFSET` across a changing dataset.
3. Record source revision/snapshot, source-to-target mapping, batch ranges/counts, skipped/anomalous rows, exact totals, and errors in restricted evidence. No guest details in Git.
4. Limit lock duration and resource usage; test concurrent old/new operations. Define how records inserted/changed during the backfill are captured. An assumed timestamp cutoff is insufficient if legacy writers can backdate or update rows.
5. Stop on unexpected counts, duplicates, orphan links, conversion/rounding differences, lock impact, or unknown state. Resume only after the cause and safe action are established.
6. Reconcile protected historical fields and grouped financial totals against the same consistent source snapshot. New evidence must not pretend to know an absent historical actor/verification.

## Transactions and DDL reality

PDO transaction support depends on the actual engine/tables. A successful begin does not protect writes to a nontransactional table. DDL commonly causes implicit commits in MySQL; `ROLLBACK` is not a general undo for schema changes. MySQL and MariaDB versions differ in DDL features, lock behaviour, and recovery semantics.

Do not run DDL inside a payment/booking request. Assess metadata locks, blocking of ongoing transactions, copy/rebuild cost, disk space, replicas/backups if present, and the deployment order. An “online” option must be verified for the exact product/version/operation; do not assume zero downtime. Use a controlled maintenance window if required and approved.

References: [PHP transactions](https://www.php.net/manual/en/pdo.transactions.php) and [MySQL implicit commits](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html). The latter is a general reference, not the snapshot's MySQL 8.0 release; consult the actual deployed version's documentation before implementing.

## Release and recovery checklist

- Review source/target versions, exact script checksum and bounded scope; rehearse on representative isolated data.
- Prove old code/new schema and new code/new schema behaviour, plus in-flight/old writer handling and real constraint/race behaviour.
- Take a consistent verified backup through the hosting-approved method and complete a restore/reconciliation drill. Record recovery objectives and timings; a backup file's existence is insufficient.
- Define whether recovery is code rollback, flag/routing change, additive fix, resumed backfill, or isolated restore plus deliberate replay/cutover. Never automatically drop newly written events as a “down” migration.
- Preserve financial events created after the backup/deployment. Old code must still see/manage them or writes remain safely paused until a forward repair. Never overwrite live data with an older snapshot to make code rollback easy.
- Obtain explicit target-specific approval for the concrete live action. After execution, record migration state and row-level/aggregate results before continuing rollout.

