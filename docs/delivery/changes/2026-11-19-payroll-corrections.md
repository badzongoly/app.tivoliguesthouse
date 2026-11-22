# Payroll Corrections — 19 November 2026

## Implemented locally

Approved workflow5 now extends the existing Payroll & Payslips source and Financial Corrections navigation. Only the configured finance owner with salary authority can correct evidenced payroll components or one specific incorrectly recorded transfer amount. Exact integer previews distinguish staff cost, employee net liability, actual recorded paid, top-up due and private recovery due. One confirmation uses a durable operation key, shared financial transaction/lock, stale-source version and mandatory atomic audit.

Original approval/salary/payee snapshots, financial events, individual transfers and reference claims, original actors/timestamps, issued statements and closed totals remain retained. Compensating `payroll_adjustment` and encrypted `payroll_corrected` events post in today's open period, linked to the original service month/request/payment. The projection validates original canonical cost/approval/payment/claim links before new writes; unsupported sources stay blocked rather than becoming unpaid or adopting today's HR details.

Existing completed MoMo payments handle bounded partial top-ups without adding a second payroll cost. Overpayments stay private recovery due; private follow-up/evidence records no returned money and makes no automatic salary deduction. Publication requires settlement and verified active employee identity. Reissued payslips append another issued version, retain originals and bind the original publication recipient. Private employer-only corrections do not create needless employee reissues; changed employee components require review even if net pay is unchanged. Historical issued versions are distinguished from pending revisions.

Affected contracts: PayrollService/Posting/Corrections; PayrollAccounting; Finance summaries and redacted payment log; Owner Reports aggregate awareness; existing payroll endpoint/templates/assets; source navigation and employee statement versions. Full HR/private evidence remains owner-only. General salary-authorized finance viewers get redacted rows. Employee statements exclude employer costs, private reasons/files and recovery notes.

## Executed evidence

Executed 31 integration suites (671 assertions), 13 HTTP/access/route suites (310 assertions), twelve responsive browser views and 29 PHP lints plus JavaScript/Python syntax, UTF-8 and scoped diff checks.

Verification ran sequentially against only marker-verified `127.0.0.1:3307/tivoli_auth_test` (`synthetic-auth-only-v1`), the marked synthetic HTTP service `127.0.0.1:8099`, and dedicated browser CDP9236. No ordinary application database fixtures or imports were used. Guarded synthetic test commands:

```text
C:/xampp/php/php.exe tests/Integration/payroll_corrections.php
C:/xampp/php/php.exe tests/Integration/payroll_correction_matrix.php
C:/xampp/php/php.exe tests/Integration/payroll_correction_concurrency.php
C:/xampp/php/php.exe tests/Integration/payroll_correction_employer_only.php
C:/Python313/python.exe tests/Contract/payroll_corrections_http.py
```

The broader recorded run covered original payroll/concurrency, Finance/weekly close/late posting/review/locks, expense and purchase corrections, purchases, cash discrepancies, receipt corrections/reconciliation, owner reports, handovers and related HTTP/access/route guards. Local ignored evidence is `.local-setup/payroll-correction-verification-summary.json`; it records exact per-suite results and the final affected-suite reruns, rather than counting repeated assertions as extra unique tests.

Exercised exact open-period deltas, byte-for-byte original event retention, unchanged original service-period totals, as-of cutoffs, partial transfer correction, under/overpayment, proof/verification, optional private file replay, recipient relinking, salary-viewer/employee denial, stale requests, audit-failure rollback/retry, durable duplicate handling and actual concurrent correction/top-up/reissue writers. Employer-only regression was reproduced RED before its fix and verified GREEN; employee components changing with unchanged net still require reissue. Browser checked preview, focus, canceled confirmations, actual top-up and revised/original employee versions at1440/390/320. Independent read-only financial/security review and final statement-effect delta review reported no Important findings.

## Boundaries, activation and recovery

No new schema migration/activation is required beyond existing finance/HR tables, configured owner and salary authority, encryption and protected evidence storage. Legacy payroll requests lacking frozen salary/payee evidence remain available for evidence review; they are not reconstructed from current HR. Existing authorized legacy settlements remain compatible. Actual monetary recovery, automatic deductions, destination changes, tax/proration/statutory calculations and payment-provider APIs remain separate work. No backup, restore, ordinary database replacement, Git mutation, push or production deployment is claimed or performed.

For recovery, preserve all original and compensating events, claims, operation results, issued versions, audit, encrypted evidence and corresponding key configuration. Pause the affected write path if a discrepancy appears and compare linked original/effective projections. Repair forward with evidenced authorized compensation. Reader/writer code must remain coordinated; reverting one reporting or payroll file after correction events exist can misstate balances. Never delete posted cases, clear operation keys, overwrite old statements or remove financial events to recover. Any production release/restore needs a separately reviewed target and authorization.

Next approved workflow:6 Stock Damage, Expiry & Complimentary Use. See [staff guide](../../PAYROLL_CORRECTIONS.md) and [sequence](../../CASH_WORKFLOW_ROADMAP.md).


## Local payroll activation verification — 19 November 2026

Owner requested activation after local implementation. Executed a guarded read-only preflight against the ordinary local application's configured database, `127.0.0.1:3306/tivoli_fresh_20261110140907`. The original guard expecting `tivoli` stopped before connecting; the observed configured target was then pinned explicitly. Required finance/HR/event/operation/claim/audit tables, existing finance activation, configured owner's salary authority and employment eligibility, and HR encryption configuration passed. No credentials or keys were printed.

Created only the previously absent empty `storage/private/finance-evidence` directory; the existing attachment writer otherwise creates this directory lazily on first upload. No application code, database records, schema, permissions, credentials or connection configuration changed. Read-only database inspection ran inside a rolled-back READ ONLY transaction; no financial test or fixture ran on this ordinary database.

Ordinary Apache checks: login200; Payroll Corrections302 to login; Financial Corrections payroll route302; direct private evidence directory403. Owner-authenticated rendering and financial writes were not exercised against the ordinary database during activation. The earlier synthetic suite/browser evidence remains separate. No production deployment, Git mutation, import, reset or financial correction was performed.

Payroll Corrections is available locally at `http://localhost/app.tivoliguesthouse/payroll.php?page=corrections` to the configured owner with salary authority; no new schema activation was necessary. Existing original/effective history and recovery safeguards remain in force.
