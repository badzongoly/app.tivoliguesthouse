# Cash & MoMo Discrepancy Resolution — 17 November 2026

Status: implemented and verified locally, workflow 1 of the authorized ten-workflow sequence. No production deployment/parity claim.

## Behavior and protected contracts

Existing deposit, daily wallet matching and cash-handover source links now lead to one retained discrepancy case. Involved staff report facts; the configured owner verifies bounded wallet credit/reversal or evidenced cash return. Only the named eligible recipient acknowledges actual partial handover receipt. Partial amounts leave exact residuals visible/reserved. New disputes reopen the same case without silently changing balances. Owner follow-up assignment is limited to involved authorized active users or the owner, with an optional due date.

Persistent finance_operations keys/fingerprints, domain source uniqueness, fresh source/case versions, bounded allocations and wallet reference claims share the existing serialized PDO transaction with required durable audit. Same-key replay returns the original result; different keys cannot consume stale evidence or exceed the remaining amount. Existing claim ownership is retained; an evidenced credit creates a missing historical claim instead of leaving the reference reusable. Evidence is optional encrypted private upload/download. Generic finance event detail excludes private discrepancy payloads.

Original payments, deposits, handovers, confirmations, staff identity and closed snapshots remain unchanged. Current verification and custody projections cover partial credits, reversals and returned funds, while Owner Reports respects as-of cutoffs. Payment Log retains the actual transfer, not a fabricated second collection. Dated daily closing no longer requires confirmation of unrelated future cohorts. Returned cash retains its original gross deposit obligation/deadline. Old confirmation links continue on existing cases; obsolete full-amount writers cannot bypass resolution.

## Files and interfaces

- New CashDiscrepancyService and CashResolution projection, using existing finance_events, finance_operations, finance_claims and security_audit; no schema addition.
- Updated FinanceService, CashCustody, HandoverService, private FinanceAttachment authority, receipt-correction dependency guard, OwnerReports and FinancePaymentLog.
- Existing finance.php?page=discrepancies endpoint with private internal controller, shared finance navigation/source links, handover labels/forms, owner report entry and public/assets/js/cash-discrepancies.js.
- Guarded integration/concurrency/report/HTTP fixtures and tests; dated-review tests now preserve other suites' unresolved synthetic records rather than bulk-confirming them.

## Executed evidence

- 18 integration suites, 371 assertions, including 6 new real competing-process discrepancy checks. Core 28 and report/authority/claim checks 10 passed.
- 9 HTTP/route suites, 197 assertions; new discrepancy HTTP contract 30. Finance, handover, receipts, owner reports, access and legacy route/table-preservation checks passed.
- Browser tested actual synthetic screens at 1440, 390 and 320 pixels: 9 views, no runtime exceptions; report focus, staff authority, exact live preview, canceled confirmation, partial credit, reopened dispute, assignment and retained history.
- 25 PHP syntax checks, JavaScript/Python syntax, scoped UTF-8 and Git diff checks passed. Git branch remains main.
- Tests resolved only to 127.0.0.1:3307 / tivoli_auth_test / synthetic-auth-only-v1; HTTP service marker synthetic at 127.0.0.1:8099. Dedicated browser 9236. No real credentials or ordinary financial rows used as fixtures.
- Independent read-only source review identified reopening/assignment issues, now corrected/rechecked. Additional audit-detail privacy and missing-reference-claim defects were reproduced with failing tests, fixed, passed and source-rechecked. No remaining important findings reported; reviewer did not claim test execution.

Commands: php tests/Integration/cash_discrepancies.php, cash_discrepancies_concurrency.php, cash_discrepancies_reports.php; Python tests/Contract/cash_discrepancies_http.py plus affected existing suites; Node synthetic browser checks; PHP -n -l, node --check, Python syntax and scoped git diff --check. Full per-suite evidence is in .local-setup/cash-discrepancies-verification-summary.json.

## Supported limits and recovery

Fee-bearing sources remain reportable but partial/reversal/return posting needs separately supported fee reconciliation. Unknown custody or multiple possible original return recipients blocks return posting rather than inventing facts. No automatic write-off, payroll deduction, actual guest refund/recovery or dependent-allocation correction is introduced. Shared reminders/unified exception register remain later slices.

No ordinary database test writes, migration, activation, branch/staging/commit change, push or deployment occurred. Existing finance/HR/private-evidence/owner configuration remains required. No backup restore was performed or claimed. Preserve all committed source/case events, claims, operation results, audits and evidence; use coordinated forward repair across dependent readers/writers, not isolated rollback or deletion. See [staff guide](../../CASH_DISCREPANCIES.md).

Next: workflow 2, Corrections After Deposit or Handover, using the approved source-linked design and retaining all dependent evidence.
