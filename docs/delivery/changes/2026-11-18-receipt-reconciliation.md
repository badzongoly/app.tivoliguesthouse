# Corrections After Deposit or Handover — 18 November 2026

Status: implemented and verified locally as workflow 2 of the approved sequence. Production deployment/parity is not claimed.

## Result and preserved behavior

The existing Finance & Cash → Financial Corrections guest-receipt request now includes a conditional linked-money review. Its normal path remains request, exact preview and one configured-owner approval. Cash funding corrections replay actual full/split handovers, partial recipient acknowledgements, deposits, partial verification and returned cash. A real unused supporting receipt from the same original collector may fund a documented decrease/reclassification; a duplicate retains the actual keeper. An unambiguous existing merchant proof may be split between genuine eligible MoMo receipts within unmatched capacity, retaining the original credit/reference claim.

Original payments, actual movements, original staff attribution, receipts, checkout facts and closed snapshots remain fixed. Today's open-period recording adjustment and allocation-only views append atomically with owner decision, durable operation result and required audit. No new collection, physical movement, credit, refund, cost or earned revenue is created. Failed required audit rolls the entire bundle back. Stale source/support/proof/case versions and competing writers cannot reuse money or verification. An unrelated new reference cannot inherit old verification.

Current cash, handover, discrepancy, payment-log and owner-report readers use conserving allocation views; cutoff-aware SQL preserves earlier as-of results. Previous full-match links route existing proofs to residual discrepancy resolution, including increased receipts without allocation history. Generic finance activity excludes private allocation/case payloads; only requester and configured owner see private requests/evidence.

## Files and interfaces

New CashFundingGraph, ReceiptReallocation, ReceiptReconciliation and linked-review template. Updated ReceiptCorrectionService/Posting, FinanceService, CashResolution, HandoverService, CashDiscrepancyService, FinancePaymentLog and OwnerReports. One previously corrupted heading in the updated SOP gap matrix was repaired as text only. Existing finance.php and financial-corrections.php routes, correction forms/details and receipt-corrections.js carry the workflow; no new menu, schema or activation step.

## Executed evidence

- 22 integration suites, 412 assertions. New core 13, reference 2, matrix 21 and real competing-process concurrency 5 passed. Matrix includes partial handover then later deposit, returned/redeposited cash, both duplicate channels, Cash↔MoMo, consumed support, audit rollback/retry, exact as-of/current reports, closed period and open-period late correction.
- 9 HTTP/access/route suites, 215 assertions. New dependent-correction contract 20 checks request acknowledgement, owner authority/private scope, exact supporting preview, retries, posting, notices and residual matching routes. Existing receipt, finance, handover, owner reports, access and Apache route contracts passed.
- Actual synthetic browser: nine views at 1440, 390 and 320 pixels, no runtime exceptions or horizontal overflow. Focus, conditional cash/wallet fields, exact live preview, canceled request/approval confirmations, final retained adjustment and allocation evidence verified; screenshots inspected.
- 27 changed PHP syntax checks, JavaScript/Python syntax, UTF-8 and scoped diff check passed. Per-suite timing/results in .local-setup/receipt-reconciliation-verification-summary.json.
- Independent read-only source review found unreviewed proof reuse, equal-net evidence staleness and unsupported reference verification. Each was reproduced failing, fixed, passed and source-rechecked; no remaining Important findings reported. Reviewer did not execute tests or database operations.

Commands: PHP tests/Integration/receipt_reconciliation.php, receipt_reconciliation_reference.php, receipt_reconciliation_matrix.php, receipt_reconciliation_concurrency.php and the affected existing suites; Python tests/Contract/receipt_reconciliation_http.py plus existing HTTP/route suites; dedicated synthetic browser script; PHP -n -l, node --check, Python syntax and scoped git diff --check. Tests were restricted to 127.0.0.1:3307 / tivoli_auth_test / synthetic-auth-only-v1 and 127.0.0.1:8099 with the synthetic response marker. Browser CDP 9236 used its own test tab. No real credentials/data were used as fixtures.

## Limits and recovery

Ambiguous multi-pool/reversed wallet evidence, unreviewed/reopened disputes, unknown custody and unsupported funding remain evidence-gated. This slice does not invent suspense money or reverse an incorrectly identified duplicate. Supporting-receipt selectors are bounded to the latest 100 authorized positive guest receipts; server eligibility is rechecked transactionally. Separate actual recoveries/refunds remain their existing workflows; remaining approved slices continue in sequence.

No migration, activation, ordinary-database test writes, Git-state change, push or production deployment occurred. No backup or restore drill is claimed. Preserve every posted allocation view, original event, claim, operation result, approval/audit and private evidence. Recovery requires coordinated forward repair across all affected readers/writers; do not delete allocation events or roll back only one dependent file. Interrupted submissions retry their original durable key and identical details.

See [staff guide](../../FINANCIAL_CORRECTIONS.md). Next: workflow 3, Guest Charge Corrections & Refunds.
