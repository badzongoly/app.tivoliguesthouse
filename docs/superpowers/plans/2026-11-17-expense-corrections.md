# Expense Corrections Implementation Plan

> Execute inline with executing-plans and TDD. One independent final source review.

**Goal:** Implement the owner-approved ordinary-expense prototype within Financial Corrections, preserving original rows, payout facts and closed snapshots.
**Architecture:** ExpenseAccounting projections and ExpenseCorrectionService/Posting append private case events and public canonical adjustments through existing finance events/claims/operations/audit. Coordinated adapters cover cost reports, expense list/CSV, settlement controls, payment log and shared reference protection.
**Tech Stack:** Existing PHP8.2/PDO/MariaDB, integer GHS minor units, UTC, no new dependency/migration.
**Spec:** ../specs/2026-11-17-expense-corrections-design.md

## Global Constraints
Reuse the active checkout and preserve all existing uncommitted work. No branch, staging, commit, push, production deployment, ordinary database tests or financial history mutation. All dynamic work targets verified127.0.0.1:3307/tivoli_auth_test with synthetic-auth-only-v1; HTTP only8099 with synthetic response marker. Financial suites run sequentially. Cash/MoMo only for new facts; legacy methods remain readable. No supplier refund, payable claim, stock, provider or payroll posting.

## Review Focus
Actual cost versus actual settlement: absence of cost_paid is not proof money was paid.
Unknown payer/custody or linked purchase/stock/liability must not be inferred away.
Independent matches and reference claims must remain immutable across duplicate keeper chains.
Current-period category/channel compensation must not restate closed historical totals or create fake actual money.
Permissions, stale keeper/source, repeated keys and audit failures must not produce partial or duplicate posts.

## Tasks
- [x] 1 Domain: write tests/Integration/expense_corrections.php, observe missing-feature RED. Create src/Repository/ExpenseAccounting.php (position, costSql, period, aliases) and src/Service/ExpenseCorrectionService.php / ExpenseCorrectionPosting.php (permit/source/detail/register/execute). Exact before/after, own source, configured owner, retained proposals, dependency blocks, closed snapshots, zero actual money, duplicate/keeper chains, category, method, staleness, audit rollback and replay GREEN.
- [x] 2 Readers/writers: costSql preserves originals plus posting-date category/channel legs; adapt ExpenseRepository, FinanceService summary and settlement, OwnerReports monthly, reports/dashboard expense sources, FinancePaymentLog outgoing adjustments, expense ledger/CSV, reference guards and private evidence. Add exact integration comparisons and cross-source alias denial; run focused regressions.
- [x] 3 Workspace: existing Financial Corrections tab with Guest Receipts / Expenses switch and expense request/source/review/log/period views. Reuse shared layouts, native confirmation, focus/error/retry/print-safe evidence patterns. Source selection before form; cost/payout fields, supplier recovery handoff without fake posting; optional encrypted evidence. CSRF and direct endpoint gates. HTTP tests RED→GREEN.
- [x] 4 Races/security: real competing request/approval, shared reference and closure tests, required audit rollback, preservation hashes. Independent fresh-context review; reproduce/fix important findings RED→GREEN once.
- [x] 5 Final: full ordered regression suite, desktop/mobile browser, syntax/UTF8/diff, anonymous ordinary Apache routes, staff guide/delivery/SOP updates and evidence/recovery handoff. No migration/activation or production claim.

## Rulings and progress
Ruling: keep existing current checkout rather than creating a worktree or committing — user asked to implement in this app and prior uncommitted modules are the working baseline; changing Git integration would expand scope.
Ruling: amount/method/duplicate require one actual supported cost_paid settlement matching the effective expense. A posted expense alone proves incurred cost, not payer/payment. Category-only can retain known independent match. Unknown historical funding and linked purchases block unsafe posts.
Approval to implement is the approval of this reviewed prototype; no additional plan confirmation is needed.
Started 17 November 2026.


## Executed progress and review ledger

Observed missing-domain and missing-workspace RED before implementation. Domain focused suite now 40 assertions, HTTP 30 and real-process concurrency 7 passed in isolation before the final ordered run. New writes reuse finance events/operations/claims/audit, with no new migration. Existing Finance/expense/Owner/legacy report readers now preserve original cost rows plus posting-date correction legs.

Ruling: simplify the prototype to select expense → one form with live preview → owner decision/confirmation. No extra requester preview/confirmation page. Optional actual-payer capture is under details only in ordinary expense settlement. Unknown payer remains unknown and blocks money corrections; the recorder is never used as a default payer. User preference recorded in AGENT.md.

Independent source review by expense_corrections_final_review found three important issues. All were reproduced RED before fixes: recorder-as-payer inference, category-only bypass of cost/payout mismatch, and vendor/invoice search dropping adjustment legs. Explicit actual-payer metadata is validated on future ordinary settlements; no historical backfill. Mismatch checks survive category history while supported method corrections remain usable. Adjustment legs preserve original searchable source attributes and separate adjustment labels.

The review's audit classification minor was regraded Important because misidentifying financial case reads prevents reliable expense audit review. HTTP audit probe reproduced the wrong receipt domain and verified the expense correction action/target after the fix. No additional independent re-review is claimed.

Additional implementer checks reproduced/fixed category-before-later-real-payment, matched keeper, late original activity date, ambiguous duplicate reference, SQL queue filtering before pagination and retrospective as-of cutoff. Native browser revealed fragment auto-focus overriding the form; deferred focus after load fixed that behavior. Browser helper now counts proposal details inside the correction workspace, excluding the account-menu details. At that stage final browser/full-suite verification was pending; completion is recorded below.


Ruling: Owner Reports timeout was caused by existing full-history housekeeping attention hydration, not the new expense metadata query (about 0.04 seconds). A read-only metadata prefilter retains all potentially pending exceptions and unreviewed self-inspections, then uses the same detailed validity checks. Characterization preserves exact full records/order and reduces query count 6134→294. Housekeeping/domain/concurrency/HTTP and Owner Reports checks were rerun; no timeout relaxation or record deletion. The consequence if the filter were wrong would be hidden alerts; exact differential and existing workflow regressions guard this. No second independent financial review is claimed.

Final verification: 52 suites; 862 integration (73 concurrency), 565 HTTP; browser 1440/390/320; 31 PHP lints and JS/Python/UTF8/diff checks. Normal workflow remains three steps. All review findings fixed by RED→GREEN tests; no deferred source-review minors. Changes stay in the existing checkout without Git integration or production action. See docs/delivery/changes/2026-11-17-expense-corrections.md.
