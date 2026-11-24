# Stock-count start and Finance & Cash UX — 22 November 2026

Implemented and tested locally. The owner requested simpler stock-count setup and consolidation of duplicate Finance & Cash tabs, links and actions while preserving approvals and financial history.

## Delivered

Stock-count start now offers item search, Select Shown, Clear Selection, live selected totals, a50item cap and an explicit Start Counting action. Weekly Stock Count remains the default; alternative purposes and comments are in optional details. Filtering preserves selection. With JavaScript, zero or oversized selections cannot start. Original checkboxes and server validation still work without JavaScript. Long names wrap; the picker list scrolls. Submitted choices and comments survive validation errors. Expected balances are never supplied to the picker. Existing per-item counting windows, comparison, explanations, evidence and owner adjustment approval remain unchanged. CSS and JavaScript use versioned assets.

Finance uses Money Overview first and one shared primary navigation. New service-provider spending uses Purchase Requests; older Provider Payments records remain linked there. A reporting administrator without purchasing privileges keeps a direct Legacy Provider Records link. Stock-only users are not offered inaccessible provider history. Payroll uses its existing Payroll & Payslips sidebar/workspace and retains its legacy payroll route; the duplicate modern payroll primary tab is removed. Expenses uses its primary capture link plus the administrator Expense Payments view, without a duplicate capture link/button. Correction pages share one ordered, capability-aware source partial. Duplicate default-register subtabs are removed; the source tab returns to its register. Distinct requests, owner review, logs and period views remain. Discrepancies no longer repeats deposit navigation. Deposit and activity-payment forms suppress the matching entry shortcut while open. Weekly Reconciliation's malformed totals section anchor is fixed.

Daily reconciliation, weekly closing, collections history, other income, deposits, discrepancies, expenses, revenue, profit and audit remain distinct controls. No receipt is converted into revenue, payout, correction or refund by navigation. Existing legacy URLs, source-specific financial actions, owner decisions, CSRF, operation keys, money arithmetic, custody obligations, privacy rules and closed snapshots remain authoritative. No financial writer/service/controller, schema or financial policy changed.

## Scope and review

Presentation files: templates/stock-counts/index.php and new start.php; public/assets/js/stock-counts.js and assets/css/stock-counts.css; templates/finance/navigation.php, new correction-sources.php and its four correction callers; finance/index.php, income.php, cash-discrepancies.php and weekly.php; templates/purchases/index.php. Relevant user guides updated. Existing unrelated uncommitted work preserved. New focused tests are in tests/Contract.

A separate source reviewer found an expense-approval-only navigation dead end in the shared correction sources. Its purchase link is now gated by the same capability set as PurchaseService. A failing actual-template rendering case was observed before the fix;12capability cases now pass. Reviewer confirmed the fix and reported no further important finding. Their review was source-only, without data access or test execution. Backend authorization is unchanged.

## Executed evidence

| Command under tests/Contract | Observed result |
| --- | --- |
| python -u stock_counts_http.py |25checks passed: blind count, evidence, owner-only approval, replay and original history |
| python -u finance_http.py |42passed: finance pages/forms, roles, CSRF and retained legacy routing |
| python -u finance_changes_http.py |30passed: expense approval/evidence, income, payment log and exact source effects |
| python -u finance_weekly_http.py |3scenario groups passed: owner decisions, CSRF/keep-open/history, clerk denial |
| python -u purchases_http.py |32passed: approval, delivery/cost, settlement, required-audit failure and retry |
| python -u cash_discrepancies_http.py |30passed: source-linked custody/wallet differences and retained obligations |
| python -u receipt_corrections_http.py |31passed: correction authority, retained receipts, retry and distinction from real refunds |
| python -u expense_corrections_http.py |30passed: approval, effects, evidence and classified audit |
| python -u guest_charge_http.py |23passed: guest adjustments/refunds and payment-log linkage |
| python -u purchase_corrections_http.py |38passed: linked costs/stock/provider settlement, effective legacy balance |
| python -u payroll_http.py |33passed: private preparation, approval, payment and staff statements |
| python -u payroll_corrections_http.py |30passed: retained originals, correction effects and private recovery notes |
| python -u finance_stock_ux_http.py |59passed: owner/clerk picker markup, finance/legacy pages, consistent sources, suppressed duplicate entry links and protected page denials |
| php correction_navigation_permissions.php |12passed: actual source template rendered with capability inputs, no DB |
| node finance_stock_ux_browser.cjs |48assertions/11views passed: real picker search, bulk cap, clear, no-results, default, blindness, form identity/submission and finance layouts |
| node confirmations_browser.cjs |271passed: shared confirmation behavior, one modal, locked background, cancellation, submitter/fields/keys retained |

Observed the missing picker regression fail before implementation, then pass. PHP syntax checked for changed/new templates; browser JavaScript and Python syntax checked; Git whitespace check passed. Browser screenshots were visually inspected. Browser Start submits are intercepted before network submission; actual count writes are separately covered by the stock-count HTTP suite. No real external email, WhatsApp, payment or hosted deployment was performed.

All mutation suites ran sequentially against the marked synthetic HTTP service127.0.0.1:8099 and guarded database127.0.0.1:3307/tivoli_auth_test/marker synthetic-auth-only-v1. Browser contexts are test-owned and disposed, preserving user cookies. Synthetic fixtures, detailed logs and screenshots remain private/Git-ignored or in a temporary directory. Normal localhost database records were not used as fixtures or changed.

## Recovery and handoff

No migration or database recovery required. If reversing the UI slice, coordinate the stock start include/partial and shared correction partial/callers; preserve versioned assets, previous confirmation/security fixes and every business event. Do not restore an old financial snapshot or re-enable retired writers. No Git commit/push or hosted release. Refresh the local pages to review the result.
