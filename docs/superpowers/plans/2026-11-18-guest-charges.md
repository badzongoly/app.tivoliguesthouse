# Guest Charge Corrections & Refunds Implementation Plan

Execute inline using the approved design; preserve this checkout and all uncommitted work. No Git mutation or production action.

Goal: correct actual guest charges without rewriting original financial facts; separately record evidenced completed owner refunds.

Architecture: ChargeAccounting projections/cursors over existing finance_events; GuestChargeCorrectionService using finance_operations, shared AccessService transaction and private FinanceAttachment. Existing financial-corrections.php?area=charges and guest-account links. StayService remains authoritative for bills/refunds. No schema change. PHP8.2/PDO, exact minor units and existing HTML/CSS/JS.

Spec: ../specs/2026-11-18-guest-charges-design.md; approved prototype tivoli-operations-workflows.html guest workflow.

Global constraints: only marked 127.0.0.1:3307/tivoli_auth_test/synthetic-auth-only-v1 and marked synthetic HTTP8099; no ordinary database tests, imports, financial-row deletion/update, activation, Git-state change, push or deployment. Cash/MoMo, owner authority, gross cash deposits, original snapshots and private notes remain protected.

Review focus: pre-checkout correction captured twice in frozen bill; refunded source reused; receipt/correction/refund concurrency; spoofed historical actual times/payer; adjustment revenue confused with collections or stock; private case reasons leaking to generic reports.

- [x] 1. RED guarded tests; ChargeAccounting and source-linked case service; exact request/revision/approval/retry/authority/audit tests.
- [x] 2. Stay bill/cursor/checkout/refund and Finance/Owner report/payment-log integrations; frozen history and posting-date tests.
- [x] 3. Existing correction tab, guest-account entry, exact preview, private evidence/history, refund confirmation and responsive labels.
- [x] 4. Concurrency, rollback, period/as-of, HTTP/browser and affected regressions; independent review/fixes; staff guide and delivery evidence.


Observed/implemented: no new schema. Core16, review18, matrix35 and competing-process22 assertions passed before the full regression run; HTTP23 passed. Independent review findings on provisional snapshots and terminal refund evidence were reproduced RED, fixed and source-rechecked. Existing late-posting regression caught and verified the difference between evidenced collection date and later recording time. Final broad verification and responsive review remain pending.


Completed locally 18 November 2026; final executed evidence and recovery in docs/delivery/changes/2026-11-18-guest-charges.md. No production deployment.
