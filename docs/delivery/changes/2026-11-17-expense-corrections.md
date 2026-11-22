# Expense Corrections — local implementation and verification

Owner-approved prototype and implementation request,17 November 2026. Stage: implemented locally; executed final verification is listed below. Actual checkout C:/xampp/htdocs/app.tivoliguesthouse on main retained with pre-existing uncommitted work. No candidate commit or production release.

## Outcome and contracts

Financial Corrections now contains Guest Receipts / Expenses within Finance & Cash. The expense area is financial-corrections.php?area=expenses; its internal expense-corrections.php controller denies direct access before application loading. No extra main menu or public writer was introduced.

Normal workflow is select original expense → one live-preview form → Eugene's review and confirmed posting. Staff request only their own recorded sources. Configured owner with expense-management authority decides; other administrators do not gain owner authority. Owner self-request authorization is explicit. Requests, return/revision, rejection and withdrawal retain private append-only cases and create no monetary adjustment. Requester/owner-only encrypted PDF/JPEG/PNG evidence is optional, 2 MB maximum.

Supported scope is ordinary operating expense recording: amount, category, Cash/MoMo or payout reference, and an evidenced duplicate actual cost/payout. Incurred cost is not proof of actual payment. Amount/method/duplicate require one supported actual settlement and established payer/funding/evidence. Category-only correction can preserve unknown settlement facts without falsely attesting verification of them. Linked purchase/stock, assets, provider/payroll, supplier claims and recoveries remain separate. The supplier handoff posts nothing.

A correction records authorized current-period cost and recorded-outflow effects with zero actual money moved. Category legs reclassify cost without changing the total; method changes reclassify payout channels without cost/profit change. Amount reduction changes management cost/profit appropriately, without sending a refund. Raw expense rows, payout events, IDs, actors, dates, references, originals and closed snapshots remain retained. Later supported corrections use current effective state; compensated duplicates stay linked to their retained canonical collection and cannot be resurrected.

Finance summaries, expense ledger/limited CSV, Owner monthly costs/categories, legacy reports and Payment Log use posting-date adjustments. Vendor/invoice/description filters preserve source attributes on adjustment legs. Original historical periods remain fixed. Weekly/monthly views cover the same postings, not two entries. Recorded net outflows are separated from original payout entries; these projections do not assert wallet movement or change full gross cash deposits. Guest receipts/revenue, room/extras, stock and payroll are untouched by expense correction posting.

## Financial and security implementation

New ExpenseAccounting, ExpenseCorrectionService and ExpenseCorrectionPosting reuse existing finance events, operations, claims and required audit. Private target expense_correction is excluded from generic Finance Activity; canonical ec_posted expense facts remain visible to authorized financial readers. Original and replacement payout references remain reserved across guest/income/deposit/payout/correction routes, including retained keeper chains. No aliases are released or overwritten.

PDO prepared access, exact GHS integer minor units, UTC, persistent actor/key fingerprints, shared security-state serialization, revalidated permissions/source/keeper/dependencies/open periods and required atomic audits protect postings. Invalid form keys are denied. Audit failure rolls back the adjustment, claims, operation result and accessible evidence registry. Existing attachment storage can retain inaccessible encrypted orphan files after rollback; no exposed committed evidence is claimed.

Retrospective Owner reports apply their cutoff to new correction legs and metadata. Expense approval before a later real payment does not freeze unknown settlement forever: a category-only correction retains the later actual payout once it exists. Category history cannot erase unresolved cost/payout or original method mismatches. Review filters status before pagination so recent closed cases do not hide pending work behind empty pages.

Future ordinary expense settlements can explicitly capture Actual Payer under optional details, backed by payment evidence; it is validated as a known retained identity and never defaults to recorder. Older payments are not backfilled. Unknown actual payer blocks money correction. Existing payout recording and unknown historical identities remain otherwise compatible.

## Scope of changed files

New repository/service/posting, private internal controller, expense correction template and JavaScript; existing Financial Corrections dispatcher/source switch, shared finance navigation, authorization/evidence gate, FinanceAttachment and Owner attention. Coordinated changes to ExpenseRepository, FinanceService/Income/PaymentLog, ReceiptCorrectionPosting and StayService shared reference guards, OwnerReports, public/reports.php, expense/payment/finance/owner templates. AGENT.md records the owner's simplest-workflow preference. HousekeepingService::attention has the characterized read-only candidate filter described below. No dependency, global connector, stored role/session-field or tariff change.

## Independent review and executed evidence

Independent read-only review by expense_corrections_final_review found recorder-as-payer inference, category-only mismatch bypass and source filters dropping adjustments. All were reproduced with failing tests and fixed in one pass. The audit-domain misclassification was regraded Important for financial traceability, reproduced with an HTTP audit probe and fixed. No additional independent re-review is claimed.

Additional implementer regressions cover category-before-real-payment, matched keeper, late original activity, unrelated duplicate references, owner queue pagination and retrospective cutoff. Browser focus exposed native fragment navigation overriding form focus; a post-load focus handler fixed it. A browser helper originally counted unrelated account-menu details; it now scopes proposal versions to the correction workspace.

All 52 suites passed: **862 integration assertions, including 73 concurrency checks, and 565 HTTP assertions**. This includes 40 expense-correction domain assertions, 7 real competing-writer assertions, 30 expense HTTP assertions, plus existing finance/HR/payroll/occupancy/report/housekeeping regressions. Legacy probes denied 22 writes with required audit and preserved 12 full-table hashes. Actual synthetic browser covered the one-form request, exact preview, requester restrictions, owner confirmation/cancel, posting/notice privacy, print action, period impact, return/revision, supplier handoff, cost/payout warning, category-only attestation and optional payer capture at 1440/390/320 with no final exceptions/overflow. Desktop and phone screenshots were inspected.

The ordered run exposed a 15-second Owner Reports timeout. Profiling isolated the existing housekeeping attention reader, which hydrated every completed attempt. A small read-only metadata candidate filter now hydrates only potentially actionable cases; all original pending alerts and historical self-inspection follow-ups/order compare exactly. The final comparison reduced 6134 queries to 294 and 10.65 seconds to 0.49 seconds for that component. No timeout was relaxed and no history removed. Housekeeping domain/concurrency/HTTP and Owner Reports HTTP were rerun after the fix; remaining access/legacy/route checks completed. Repeated suites are counted once by their latest passing result. This additional read optimization was verified by characterization and affected regressions; no independent re-review of it is claimed.

Dynamic target was exactly127.0.0.1:3307 / tivoli_auth_test / synthetic-auth-only-v1. HTTP was only127.0.0.1:8099 with X-Tivoli-Test-Mode: synthetic. Suites run sequentially. PHP8.2.12 and MariaDB10.4.32 are the verified local runtime, not proof of production MySQL8 parity.

Commands: PHP tests/Integration/expense_corrections.php and expense_corrections_concurrency.php; Python tests/Contract/expense_corrections_http.py; ordered .local-setup/expense-corrections-full-check.py; PHP -l on31 changed PHP files; Node --check public/assets/js/expense-corrections.js; Python AST and scoped UTF-8/diff checks; synthetic seed then Node .local-setup/expense-corrections-browser.cjs; anonymous ordinary Apache route checks.

Evidence is ignored local output: .local-setup/expense-corrections-verification-summary.json, expense-corrections-verified-*.txt, expense-corrections-browser-result.json, expense-corrections-*.png and expense-corrections-apache.json. Synthetic verification is not an all-history production reconciliation.

## Migration, recovery and remaining scope

No new migration/activation, SQL import, ordinary authenticated application database test writes, historic backfill, role/configuration/secret change, branch/stage/commit/push or production deployment. Existing finance/expense/HR setup, encrypted evidence storage and designated owner binding are prerequisites.

Before any correction exists, removing this feature requires the coordinated slice while retaining prior safeguards. After events exist, use coordinated forward repair preserving original expenses/payouts, canonical/private events, aliases, operations, proposals, evidence and audits. Do not roll back a single reader/writer, reopen unsafe legacy writers, release references or delete adjustments. No actual refund, second cost or payroll/stock deduction is created as fallback.

Supplier claims/refunds, unpaid or partial settlement correction, historical/incorrect payer attribution, linked purchases/stock/assets, provider/payroll and dependent wallet/custody reconciliation remain separately scoped. Existing ledger exports remain bounded to their displayed 300-entry contract; totals cover all filtered entries. See docs/EXPENSE_CORRECTIONS.md for the staff workflow. Local verification and owner implementation approval do not authorize production deployment.
