# Purchase and Expense Pre-Approval - completed 14 November 2026

The owner-approved [prototype design](../../superpowers/specs/2026-11-14-purchase-preapproval-design.md) is implemented in C:/xampp/htdocs/app.tivoliguesthouse on main. Existing uncommitted work is preserved. [Staff instructions](../../PURCHASING.md) explain the supported steps. No new migration or local activation is required on the existing migration-010/finance-owner baseline.

## Result and source contracts

Operating Expense, Stock Purchase and Service Provider requests share request, owner cap/revision, commitment, complete delivery/actual cost, owner posting and verified partial Cash/MoMo settlement. Only the configured finance owner can authorize financial decisions. Scope/version and actual chronology are checked again on writes. Approval creates no expense, payment or stock. Overruns, missing approval and payments predating approval require explicit after-the-fact treatment; approval history is never rewritten.

PurchaseService and PurchasePosting use existing append-only finance_events, finance_operations, security_audit and the shared security_state transaction lock. Root kinds are purchase_requested and purchase_unapproved. Linked events retain approvals, changes, commitment, receipt, dispute, clarification, posting, payment and cancellation. Stable operation keys protect retries; audit failure rolls back the transaction. Actual payer and recording owner are distinct.

All purchase types create one canonical expenses row. Stock additionally creates one linked movement (ref_type purchase, ref_id root ID) and quantity increase atomically. No extra provider request cost is created. Existing provider subtotals retain their legacy source meaning; overall costs count the new expense once. Each private purchase_paid event pairs with one existing cost_paid expense event. Partial/mixed settlement events are authoritative for actual method; expenses.method remains the planned/declared method at posting. Shared cost proof and MoMo claims prevent duplicates where evidence exists; no guessed historical matching.

Requester and configured owner alone see private workflow/files. Optional encrypted PDF/JPEG/PNG evidence is limited to 2 MB, outside the public tree, and requires a committed authorization record. Failed storage transactions can leave an encrypted unreachable orphan, following the existing storage design. Existing financial ledger viewers retain payment amounts and source rows; private purchase payloads are redacted for other administrators.

New public expense/provider/stock-in forms redirect into purchasing and obsolete creation POSTs are denied. Old records retain reads/review/settlement. Trusted legacy service interfaces remain for compatibility and synthetic fixtures, not alternative public creation. Linked purchase expenses cannot be paid through legacy pay_cost.

Owner Reports, pending weekly/monthly review and staff handover expose pending purchases. Received unreviewed costs block silent period closure. Late accepted activity retains original dates while posting to an open period. Cash costs never reduce gross sales-cash deposit obligations.

## Verification environment and commands

Verified 14 November 2026 using PHP 8.2.12 CLI and the dedicated MariaDB test instance at 127.0.0.1:3307, database tivoli_auth_test, marker synthetic-auth-only-v1. HTTP checks require http://127.0.0.1:8099 and X-Tivoli-Test-Mode: synthetic. Test bootstrap validates the exact dedicated connection. No ordinary application database test writes occurred. Credentials and private application records are excluded from evidence.

From the checkout, executed each integration file below with C:/xampp/php/php.exe tests/Integration/<name>.php, and each HTTP file with C:/Python313/python.exe tests/Contract/<name>.py. These are recorded executed commands, not instructions to run against the ordinary application. Existing test configuration and marked services must be established first.

| Integration file | Passed |
| --- | ---: |
| purchases | 48 |
| purchases_concurrency | 5 |
| finance | 37 |
| finance_concurrency | 6 |
| finance_changes | 45 |
| finance_late_posting | 36 |
| finance_posting_lock | 1 |
| finance_weekly | 25 |
| finance_weekly_concurrency | 3 |
| finance_reviews | 12 |
| owner_reports | 19 |
| expenses | 52 |
| stay_billing | 31 |
| stays | 137 |
| stays_concurrency | 3 |
| departure_incidents | 42 |
| departure_incidents_concurrency | 4 |
| handovers | 42 |
| handovers_concurrency | 4 |

| HTTP file | Passed |
| --- | ---: |
| purchases_http | 32 |
| finance_http | 42 |
| finance_changes_http | 30 |
| expenses_http | 20 |
| finance_weekly_http | 3 |
| stays_http | 122 |
| stay_billing_http | 16 |
| departure_incidents_http | 26 |
| handovers_http | 27 |
| owner_reports_http | 10 |
| access_http | 24 |

Totals: **552 integration checks including 25 real concurrency scenarios, and 352 HTTP checks**. stays_legacy_guard additionally verified 22 audited denials and 12 unchanged full-table hashes. Ignored logs: .local-setup/purchase-verified-<name>.txt. The late-posting suite rehearses existing migration 010 on synthetic data only.

Feature tests first reproduced missing behavior. Independent source review found payment chronology after cost posting, retrospective stock duplication and private payment payload exposure. Regression tests reproduced each failure before fixes, then passed. Subsequent checks reproduced unavailable-action form failure and misleading proposed-versus-approved labeling; both were fixed and passed. The final privacy refinement retains financial payment rows while redacting private details.

A close-versus-delivery race initially assumed the synthetic week had no earlier costs. Diagnostic evidence showed unchanged closed totals; the fixture now captures the prior total and requires either exactly that closed total or exactly one additional cost in an open week. No data reset or balancing adjustment was used.

Dedicated Chrome on port 9236 exercised request focus, canceled confirmation with delayed navigation check, submission, owner approval, staff commitment/delivery, one cost posting and partial separately funded Cash settlement. Ten views at each of 1440/390/320 pixels passed without overflow or JavaScript/PHP/encoding output errors. Desktop/mobile screenshots remain ignored under .local-setup/purchase-new-*.png and purchase-payment-*.png; mobile payment was visually inspected.

Twenty-six changed PHP files passed C:/xampp/php/php.exe -n -l. C:/Program Files/nodejs/node.exe --check public/assets/js/purchases.js passed. Four affected Python HTTP files passed AST syntax checks. Scoped source passed UTF-8/known-mojibake checks. git diff --check passed with pre-existing line-ending warnings. Much of the current application is untracked pre-existing work; scoped lint, tests and independent review supplement tracked diff inspection.

An earlier automatic approval-service usage-limit failure executed no blocked command. User continuation allowed the same escalated verification to finish; no approval bypass was used.

## Boundaries and recovery

One complete delivery per request; partial payments supported. No advances-before-delivery, partial-delivery policy, COGS/valuation, generalized amount correction/refund/write-off, automatic payroll or external notification delivery. Alerts are in-app.

Historical stock-cost intake is cost-only Operating Expense with explicit original evidence, not an inventory receipt, guessed old movement link or retrospective prior approval. Missing source evidence limits duplicate detection; similar amounts alone do not prove identity.

No configuration secret, new role grant, schema activation, Git branch/staging/commit change, push or production deployment. MariaDB test success does not prove MySQL 8 production parity. A production release needs separate authorization, complete coordinated artifact, verified owner/configuration/permissions and recovery checks.

Before new events, scoped source rollback is possible. After events, use forward repair retaining original requests, expenses, stock movements, payouts, proof claims and audit history. Do not restore over newer activity, delete originals, re-run old public creation or run old writers concurrently as a fallback.

## Apache localhost routing correction - 14 November 2026

User reported Apache Forbidden on purchases.php. Reproduced anonymous localhost GETs: purchases.php and handover.php returned 403, while finance.php redirected to login. Root .htaccess explicit route list omitted both new pages; the synthetic PHP server tests did not exercise Apache rewrite rules. Added only purchases and handover to that list. Require local, Options -Indexes, public subtree behavior and final deny-by-default are unchanged. No PHP business logic, database, financial records or Git state changed.

Before the fix the route assertion failed on purchases.php. Afterward C:/Python313/python.exe tests/Contract/apache_routes.py passed four public-route, seven private-path and local-only/deny-by-default checks without executing PHP or connecting to a database. Actual anonymous Apache requests passed: purchases.php, handover.php and finance.php each return 302 to auth/login.php; .env, db.php, src/Service/PurchaseService.php, docs/PURCHASING.md, tests/Contract/purchases_http.py, purchases.php.bak and unlisted.php each remain 403. No authenticated or financial mutations were used. Apache applies the per-directory rule immediately; no service restart/migration needed. This corrects local Apache entry-point availability; earlier synthetic browser checks alone did not establish that availability.

Independent read-only review found no important issues and independently passed the route contract. Actual Apache response checks were executed separately by the implementer as recorded above.
