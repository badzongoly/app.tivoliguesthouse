# Weekly Audit Follow-Up — approved implementation, 21 November 2026

Owner approval: implement the reviewed prototype; keep the flow simple and tied to existing room and area audits.

- [x] Inspect original audit writers, permissions, attachment storage and synthetic test safeguards.
- [x] Add failing real-database tests for source identity, assignment, staff completion, owner verification, unsafe guards, retry and rollback.
- [x] Extend original writers with source-linked follow-ups; preserve original findings and historical completions. Use existing append-only operational events and source claims, no schema migration.
- [x] Add one Follow-Ups tab, named audit duty, own-work completion and owner review; optional private 5 MB evidence. Preserve older entry forms, block their managed-task closure bypass.
- [x] Verify isolated service/HTTP/race paths, relevant regressions and responsive views. Independently review the permission change.
- [x] Record executed evidence, operator instructions and recovery, update delivery and roadmap.

Protected: historical finance/stock rows, financial calculations, original audit identities and actors, legacy free-text ownership. Follow-up closure never changes room readiness. Critical faults use existing Record Repair. No production/database/config/Git mutation or external messaging.

Authority: configured finance owner assigns/verifies/delegates audit duty; active authorized auditors record audits; active assigned staff complete their own work. Never infer identity from names. Pending verification requires return before reassignment. Explicit same-location source reuse only. A later pass never closes an unresolved task.

Persistence: existing security_state transaction lock, finance_events amount zero, finance_claims unique source identity, per-actor durable operation fingerprints; required security_audit inside transaction. Existing area follow-up history remains canonical original evidence; new controls extend it rather than rewrite it.

Verification target only: 127.0.0.1:3307 / tivoli_auth_test / synthetic-auth-only-v1; HTTP 127.0.0.1:8099 with synthetic header. No application fixtures against ordinary localhost database.

Recovery: retain all new journal/claims/operations/files. Do not roll back to a writer that permits closing managed actions without verification; disable affected writes until forward repair. No DDL/backfill required.
