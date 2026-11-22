# Purchase & Provider Corrections Implementation Plan

Goal: implement approved workflow4 inline, reusing this checkout and preserving all uncommitted work.

Architecture: PurchaseAccounting read projections plus PurchaseCorrectionService/Posting over existing events/operations/audit; existing purchase/provider/expense entry points route to Financial Corrections. PHP8.2/PDO, integer minor/quantity units and existing HTML/CSS/JS. No new schema.

Spec: ../specs/2026-11-18-purchase-corrections-design.md and approved prototype purchase workflow.

Global safeguards: verified127.0.0.1:3307/tivoli_auth_test/synthetic-auth-only-v1 only; marked HTTP8099 and dedicated browser9236; financial suites sequential. No ordinary DB fixtures/imports/migrations, original financial-row changes/deletion, Git-state changes, push or production deployment. Cash/MoMo only, configured owner, exact source links, fixed closed snapshots, gross cash deposits, private notes and durable idempotency.

Review focus: partial payout after cost reduction; real overpayment mislabelled recording error; stock received versus actually returned; old provider payer unknown; stock count/sale overlap; multiple current/old readers duplicating cost; current approval cap/source versions; audit failures and retry collisions.

- [x] 1. RED guarded source/cost/payment tests; implement PurchaseAccounting and typed correction case service with atomic posting/audit/idempotency.
- [x] 2. Linked stock corrections/returns, private evidence, current purchase/provider settlement guards and all financial read projections; prove exact source preservation, supplier balance, stock concurrency and period/as-of behavior.
- [x] 3. Existing source links / correction tab, immediate preview, owner decisions/history/notices and private attachments; visible unresolved supplier positions, owner attention and handover.
- [x] 4. Full affected regressions, HTTP/role/privacy, responsive browser and independent review/fixes. Staff guide, delivery evidence and recovery notes. No activation/deployment.


Completed locally 19 November 2026. Executed evidence and recovery: docs/delivery/changes/2026-11-19-purchase-corrections.md. No production deployment.
