# Payroll Corrections Implementation Plan

Goal: approved workflow5, simplest source-linked owner correction and versioned payslip path.
Architecture: existing PayrollService/Posting plus PayrollCorrections and PayrollAccounting; existing events/operations/claims/audit/encrypted evidence. PHP8.2/PDO/integer money, existing HTML/CSS/BigInt JS. No schema or new dependencies.
Spec: ../specs/2026-11-19-payroll-corrections-design.md. Execute inline; no commits or worktree changes. Legacy source identity/provenance and actual money recovery remain evidence-gated; no salary deduction, fake income or rewritten old approvals.

Review focus: cost versus net liability versus actual paid; partial transfer mirror/reference evidence; original payslip versus pending/revised version; account relinking; correction/top-up/publication/period-lock races; shared audit rollback; staff/salary-viewer privacy.

- [x] 1. Guarded RED component/payment correction tests; implement encrypted compensating events, effective source projection, idempotency, original/canonical validation and financial summaries.
- [x] 2. Existing top-up/recovery follow-up, latest versus historical publication, recipient guard, private evidence and reporting; exercise balances, original snapshots, audit failure and real concurrent writers.
- [x] 3. Existing payroll/Financial Corrections links, exact preview and confirmation, source history and employee issued versions; HTTP/privacy/CSRF and responsive browser checks.
- [x] 4. Independent review/fixes, affected regressions, syntax/UTF-8/source sync, staff guide and delivery evidence/recovery. No production activation/deployment.
