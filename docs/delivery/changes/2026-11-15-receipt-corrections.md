# Guest Receipt Corrections — local implementation and verification

Owner-approved prototype dated 15 November 2026; final verification 16 November 2026. Stage: implemented and tested locally, not deployed to production. Risk: guest receipt contributions, debt, cash custody, shared MoMo references and closed-period reporting. Existing checkout on main retained with prior uncommitted work; no candidate commit created.

## Delivered scope and contracts

Financial Corrections is a tab within Finance & Cash, served by public/financial-corrections.php with the existing shared layout and guarded root route. Staff request corrections only for their own recorded guest receipts; the configured Eugene owner with booking authority reviews/posts. Other administrators do not acquire owner authority. Request, return, revision, rejection and withdrawal preserve private versions without monetary effects. Owner may explicitly approve their own request; no second approver is fabricated.

Supported types are recorded amount, Cash/MoMo method or reference, and an evidenced duplicate recorded receipt. Actual refunds route to the existing separate guest-refund workflow. An adjustment changes recorded receipt facts, not actual cash movement, guest charges, earned revenue, expenses or profit. Approval posts an authorized linked adjustment once in today's open period. Original payment, receipt, cashier, dates, reference claims, checkout evidence and closed snapshots remain immutable.

Current guest balances reflect effective receipts. Checkout records the last included correction cursor atomically, avoiding double counting even for same-second posting; later checked-out debt uses the existing separate recovery workflow. Recovery does not reopen occupancy or change departure timestamps.

Cash obligations and unmatched MoMo use effective receipt contributions while retaining original collector/date/deadline. Original receipts and posting-date channel legs remain distinct in Payment Log, Finance, Owner Reports and legacy cash-basis reports/exports. Method changes can have negative Cash and positive MoMo legs with zero net receipt change and no money moved. Owner matching coverage uses effective receipt state at the report cutoff; monetary history remains on original/posting dates.

Deposited, handed-over or wallet-matched sources block posting pending separate supported reconciliation. Unknown historical custody, unsupported charges, unresolved departure facts, stale sources/keepers, competing pending cases, closed posting dates and totals below recorded refunds also block. Duplicate keepers must represent the same actual collection; claims remain byte-identical and resolve through the canonical retained collection chain. A compensated duplicate cannot be resurrected. An incorrect duplicate determination requires separate reconciliation.

Private case notes and optional encrypted PDF/JPEG/PNG evidence (2 MB maximum) are requester/owner-only. General Finance Activity excludes private cases for every role; canonical posted financial events remain visible. Adjustment notices include source facts, before/after, authorizer/time and zero money moved, excluding private notes/files. Existing attachment behavior may leave inaccessible encrypted orphan files after failed transactions; no committed accessible partial evidence is claimed.

## Implementation surface

New ReceiptAccounting repository projection, ReceiptCorrectionService and ReceiptCorrectionPosting services; guarded endpoint, six correction templates, scoped CSS/JavaScript and staff guide. Coordinated adapters cover StayService, DepartureIncidents, FinanceService/Income/PaymentLog/Attachment, OwnerReports, reports/dashboard, owner alerts, receipt/account templates, authorization, shared finance navigation, sidebar selection, page context and Apache route allowlist.

No global connector, stored role/session field, original monetary row, historical rate, currency, rounding or UTC policy was replaced. New writes use prepared PDO statements and exact integer minor units; preview uses BigInt. Persistent operation keys, shared security-state locking, reference claims and required atomic audit protect retries and competing writers. Invalid keys are denied rather than silently replaced.

## Executed evidence

Verified PHP 8.2.12 / MariaDB 10.4.32 only. Dynamic tests use exact 127.0.0.1:3307 / tivoli_auth_test / synthetic-auth-only-v1. HTTP requires 127.0.0.1:8099 with X-Tivoli-Test-Mode: synthetic. Financial suites run sequentially.

All 48 suites passed: **813 integration assertions, including 66 concurrency checks, and 535 HTTP assertions**. Correction-specific coverage is 35 domain, 10 independent-review regression, 12 competing-writer and 32 HTTP checks. Legacy probes denied22 writes with required audits and preserved12 full-table hashes. Browser request/confirmation cancellation, exact preview, requester/owner authority, posting, private adjustment notice, print trigger, checked-out recovery handoff and returned revisions passed at1440/390/320 without browser exceptions or page overflow. Desktop/mobile screenshots were visually inspected, including action controls; a low-contrast Cancel link was corrected and rechecked.

The correction-specific period-lock race uses an atomic synthetic lock marker; existing actual period-close concurrency suites also passed. It is not a production closing rehearsal.

Focused commands executed from the actual application root:
- C:/xampp/php/php.exe tests/Integration/receipt_corrections.php
- C:/xampp/php/php.exe tests/Integration/receipt_corrections_review.php
- C:/xampp/php/php.exe tests/Integration/receipt_corrections_concurrency.php
- C:/Python313/python.exe tests/Contract/receipt_corrections_http.py
- All affected existing integration/contract suites, recorded individually in the summary below.
- PHP -l for 35 changed PHP files; Node --check public/assets/js/receipt-corrections.js; Python AST checks and scoped UTF-8 scan; scoped git diff --check.
- C:/xampp/php/php.exe tests/Integration/receipt_corrections_http_seed.php followed by Node .local-setup/receipt-corrections-browser.cjs, only on synthetic services.
- Anonymous ordinary localhost Apache: correction route302 to login; .env, service source and guide403.

Evidence: ignored .local-setup/receipt-corrections-verification-summary.json and receipt-corrections-verified-*.txt; receipt-corrections-browser-result.json and receipt-corrections-*.png; receipt-corrections-apache.json. Synthetic fixtures are not production reconciliations or an all-history certification.

Independent read-only source review by receipt_corrections_review found ordinary activity-income alias reuse, immutable alias ownership through duplicate chains, and stale original-method matching coverage. Each was reproduced with a failing test, fixed and verified. Further implementer checks fixed inherited-keeper exact-reference matching and private correction notes in general Finance Activity. No additional independent re-review is claimed.

The earlier legacy CSV contract assumed a newly created short stay would be inside the latest100 all-room export despite later-dated accumulated synthetic fixtures. The test now explicitly filters its own room; existing export limit/columns and the short-stay/incidental assertions remain unchanged. Finance review fixtures use verified effective references for corrected collections while preserving older uncorrected matching behavior and assertions. Repeating HR HTTP alone consumes a previously changed-password fixture; the final run regenerated fixtures via the full ordered suite. Those failed attempts are not included as passing verification.

## Migration, recovery and remaining scope

No new schema migration/activation, SQL import, ordinary application database test writes, role/secret/configuration change, branch/stage/commit/push or production deployment. Existing finance/HR setup, encrypted evidence storage and configured owner binding are required. Production MySQL8 parity and deployment/recovery on the production host remain unverified.

Before any correction exists, removing code requires the entire coordinated slice, preserving prior work and existing safeguards. After events exist, use coordinated forward repair retaining canonical/private events, original receipts, aliases, operations, cursor annotations, files and audit. Do not roll back a reader or writer alone, release references, delete events, re-enable incompatible old writers or silently resurrect a duplicate.

Expense/provider/payroll/charge/rate corrections, dependent deposit/handover/wallet reconciliation, incorrect duplicate determinations, payment APIs, external alerts and scheduling remain separate workflows. Next safe step: review the local staff workflow using its guide; production release needs a separately reviewed, explicit target/action approval.
