# Corrections After Deposit or Handover Implementation Plan

Goal: implement workflow 2 in the existing Financial Corrections forms, retaining original actual-money events and conserving every cash/wallet allocation.

Architecture: existing ReceiptCorrectionService transaction, durable operation identity and owner approval; append-only current-period allocation views for immutable cash events; explicit bounded wallet-match allocations. No schema migration, new main menu or financial history rewrite. Execute inline under the existing approved sequence; independent source review before handoff.

Spec: approved ten-workflow design, workflow 2, and the operating decisions below. Workflow 1 remains implemented.

Constraints: cash/MoMo only, exact money, fixed closed snapshots, source/funding identities must be evidenced, no extra collection/refund/write-off, private proposals/files, configured owner authority and complete transaction/audit/idempotency bundle.

Implementation decisions: cash decreases/reclassification/duplicate compensation may need an existing unused cash receipt collected by the same person before the first affected cash movement. Rebuild funding through the actual handover/deposit/partial-ack/return graph, validate custody and show changed allocations before approval. Never merely suppress dependency guards. Increased receipts and decreases confined to uncommitted held cash can retain actual movement allocations. Existing original cash-return deadlines and physical movement amounts remain.

Wallet reallocation uses an unambiguous single independently verified credit pool and a real existing eligible receipt with unused matching capacity. Original merchant proof/reference claim remains retained; reallocating evidence is not a provider reversal or new receipt. Ambiguous multiple pools/reversals/unsupported history remains blocked for specific evidence reconciliation. Correcting a matched reference to its own known merchant reference retains verification; correction does not invent fresh verification.

- [x] 1. Guarded integration characterization and failing dependency-correction tests.
- [x] 2. Pure cash funding graph and append-only allocation projections; replay original movements with conserved amounts and valid custody.
- [x] 3. Source/dependency review, wallet allocation bounds, current-period posting and shared writer/read integrations.
- [x] 4. Existing request/preview/detail UI, original/current allocation evidence, supporting-receipt choices and focused actions.
- [x] 5. Integration/concurrency/HTTP/rollback/privacy/as-of/browser checks, independent review, evidence/staff guide/delivery update.

Review focus: donor reused by a competing writer; split/partial custody paths; reversed or returned deposit portions; old wallet proof credited twice; source/support/dependency changes after request; old/as-of reports; private-note leakage; atomic audit/operation failure.

No Git mutation, ordinary-database tests, migration, push or production deployment. User has already authorized implementation in this order; do not request the same approval again.


Completed locally 18 November 2026. See delivery/changes/2026-11-18-receipt-reconciliation.md for executed evidence and supported limits. No production deployment.
