# Stock Damage, Expiry & Complimentary Use — 21 November 2026

## Implemented and tested locally

Approved workflow6 extends Extras & Stock with source-linked non-sale reports, immediate quantity holds, configured-owner approval, confirmed actual disposal/supply, clarification and retained evidence. Existing finance events/operations/claims, stock movements and encrypted evidence storage are reused. No new schema, dependency, role system, cost method or monetary posting was introduced.

The StockAvailability projection distinguishes raw recorded quantity from held and available stock. StockIssueService records immutable reports and owner decisions. Existing guest-extra sales, direct service stock adjustments and supplier-return corrections retain active holds. Stock counts compare saleable stock, retain raw/held facts and exact excluded issue identities, apply only the variance to raw stock, and require recount when hold evidence changes. A supported approved negative-count loss is claimed once for its exact full item/reason/quantity; later disposition creates no second deduction. Source time must precede frozen physical counting, not merely approval. Unlinked late losses with intervening counts remain evidence-gated.

Original report, count, movement, actor and actual/recorded times are retained. Owner-only mistaken-report reduction/cancellation requires a reason and physical safe-saleable verification, appends a correction and revokes prior approval; it cannot fabricate a disposal or change a completed outflow. Shared inventory staff can view issue history and protected files; original count detail remains restricted to counter/owner. Current privileges, CSRF, source versions, shared locks, persistent actor/key uniqueness and mandatory audit protect critical actions. Required audit failure rolls back quantities, events and operation result together.

UI: public/stock-issues.php and templates/stock-issues use the existing layout, Extras & Stock tabs, direct item links, exact previews, optional files/comments, required decision attestations, native confirmations and action focus. A503 retry retains original key/facts; if evidence was attached, required reattachment is checked against server-signed original file metadata before execution. Missing/changed proof cannot silently disappear after rollback. Existing legacy HTTP writers stay blocked. Source and event records remain accessible in stock history; no original financial record is hidden or overwritten.

## Executed verification

Executed 26 integration suites (719 assertions), 13 HTTP/access/route suites (348 assertions), twelve responsive browser views and 27 PHP lints plus JavaScript/Python syntax, UTF-8, scoped diff and new-route checks.

All dynamic fixtures ran only on marker-verified127.0.0.1:3307/tivoli_auth_test (`synthetic-auth-only-v1`) and the marked synthetic HTTP service127.0.0.1:8099. Financial/stock suites ran sequentially. Browser actions used a new dedicated test tab, fake accounts and widths1440/390/320; source/evidence screenshots contain only synthetic data. The ordinary application database was not used for fixtures, resets, imports or financial tests.

Commands include:

```text
C:/xampp/php/php.exe tests/Integration/stock_issues.php
C:/xampp/php/php.exe tests/Integration/stock_issue_writers.php
C:/xampp/php/php.exe tests/Integration/stock_issue_returns.php
C:/xampp/php/php.exe tests/Integration/stock_issue_chronology.php
C:/xampp/php/php.exe tests/Integration/stock_issue_report_errors.php
C:/xampp/php/php.exe tests/Integration/stock_issue_concurrency.php
C:/xampp/php/php.exe tests/Integration/stock_count_attention_contract.php
C:/Python313/python.exe tests/Contract/stock_issues_http.py
C:/Python313/python.exe tests/Contract/stock_issue_privacy_http.py
```

The wider recorded run covers original stock counts/concurrency/HTTP, guest stays/billing/concurrency/HTTP, purchases/corrections/concurrency/read contracts/HTTP, guest charge corrections, Finance, Owner Reports, handovers, access and legacy/Apache routes. The ignored `.local-setup/stock-issue-verification-summary.json` records exact per-suite output counts and final affected reruns without counting reruns as extra unique suites.

Independent reviews identified count chronology, mistaken-report resolution and retry evidence gaps. They were reproduced RED, fixed GREEN and verified in focused tests. The form-key collision with shared navigation was also caught and fixed by the HTTP suite. Existing count heading compatibility was retained with a Saleable qualifier. A finance-only viewer is denied inventory source/files and admin-only Finance Activity; no Finance Activity authorization change was needed.

Owner Reports initially hit its existing15s per-request timeout with accumulated synthetic history. Profiling showed stock attention loading406 complete histories for85 open cases (4.45s). Selecting open status before projection reduced that reader to0.92s. A read-only contract compares complete ordered rows against the original full-history reader for owner and own scopes. The unchanged timeout then passed. Closed cases remain in their register and history.

## Boundaries and recovery

No new schema activation, ordinary database replacement, Git mutation, push, production deployment, backup or restore is claimed or performed. Actual goods remain verified by named human attestation/evidence, not a hardware/provider integration. Monetary stock costing/consumption and weekly/monthly profit accuracy belong to workflow7; no guessed purchase cost, new expense, sale, payment or staff deduction is recorded here.

Unknown/changed count provenance, unsupported earlier losses, edited source evidence and completed-outflow corrections remain stops for evidenced owner review. Linked counts are not partially split or reallocated. Correct Mistaken Report reduces only an uncompleted unlinked hold; it cannot change item/reason, increase scope, free a count claim or reverse a real completed physical issue.

Recovery: preserve all original and compensating finance/stock events, count snapshots/claims, operation results, audits, encrypted files and key configuration. Pause the affected writer on an unexplained difference and reconcile source facts. Repair forward through evidenced authorized compensation. Never delete a hold, movement, case, claim or operation result to clear the queue, and never revert only one sale/count/return reader after issue events exist. Any production deployment or recovery action requires separate target-specific review and approval.

See [staff guide](../../STOCK_NON_SALE_ISSUES.md), [approved design](../../superpowers/specs/2026-11-19-stock-issues-design.md) and [remaining sequence](../../CASH_WORKFLOW_ROADMAP.md). Next approved direction:7 Stock Costing & Profit Accuracy, with its valuation/cutover decisions still explicit.
