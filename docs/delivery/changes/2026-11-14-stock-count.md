# Stock Counts and Variance Approval - completed 14 November 2026

Owner-approved [interactive design](../../superpowers/specs/2026-11-14-stock-count-design.md) is implemented and verified in C:/xampp/htdocs/app.tivoliguesthouse on main. Existing extensive uncommitted work is preserved. See [staff instructions](../../STOCK_COUNTS.md) and [completed plan](../../superpowers/plans/2026-11-14-stock-count.md).

## Scope and contracts

StockCountService, public/stock-counts.php, templates/stock-counts/index.php and scoped CSS/JS provide a register, one-item-at-a-time blind counting, comparison, structured variance explanations, optional private evidence, owner approval/dispute, append-only clarification, linked recount, cancellation of unfinished counts and retained history.

Eligible individual accounts with bookings.manage or stock.manage can count. The configured finance owner decides; approval additionally requires stock.manage. Account activity and effective HR exits/suspensions are checked on commands. Self-counting owner approval requires an explicit exception reason. Requester/owner-only privacy extends to evidence downloads and Finance activity; general administrators cannot bypass it.

Existing finance_events/finance_operations/security_audit store zero-money count events and stable operation identities. Root count_started retains scope and optional parent. Linked kinds are count_window, count_line, count_submitted, count_disputed, count_clarified, count_recount, count_superseded, count_cancelled and count_approved. All commands use the shared security_state transaction lock, PDO and exact integer hundredths. Approval inserts signed adjustment movements with ref_type stock_count and ref_id the count root, and changes current quantities in the same transaction as decisions, operation and audit. Required audit failure rolls back everything.

Begin captures current recorded stock plus full movement provenance/watermark. Finish rejects any intervening movement, including net-zero activity. All chosen items must finish before staff see baselines. Approval applies only counted-minus-expected to the latest quantity and preserves later normal movements. Owner preview tokens reject intervening changes. Other adjustments after counting require a recount to prevent overlapping counts deducting the same shortage. Unknown provenance and invalid resulting balances block approval.

Legacy movement signs are preserved: out stores a positive quantity and reduces stock, in adds a positive quantity, adjustment is signed. Historical originals are not normalized or rewritten. A regression reproduced the incorrect signed-out assumption before the reader was corrected.

Historical count/approval snapshots stay readable if an item becomes a service or later movements cannot be compared. Live values show unavailable and unsafe approval remains denied. Real database failures are not swallowed as ordinary unavailable comparisons. Cancelled incomplete counts keep their baselines hidden.

New root Apache route is explicitly allowed. Require local, private-file denial and directory-listing protections remain. require_login independently guards the endpoint and evidence. New stock controls are linked under Extras & Stock. Old public immediate-count GET redirects; POST is denied409. Historical movements stay readable. Trusted legacy service interfaces remain solely for compatibility/characterization, not public alternatives. Owner Reports and shift handover show pending reviews, disputes and recount work. Handover does not transfer ownership or private evidence access.

No migration, new permission grant, configuration secret or runtime dependency. Existing finance/stay setup and verified owner binding are required. No payment, expense, automatic staff liability, COGS/valuation or disposal transaction is introduced.

## Executed verification

Executed 14 November 2026 using PHP 8.2.12 and the dedicated MariaDB instance at 127.0.0.1:3307, database tivoli_auth_test, marker synthetic-auth-only-v1. HTTP service 127.0.0.1:8099 requires X-Tivoli-Test-Mode: synthetic. Bootstrap and fixtures guard the exact target. No ordinary application-database test writes.

From the checkout, each integration ran with C:/xampp/php/php.exe tests/Integration/<name>.php; each HTTP contract ran with C:/Python313/python.exe tests/Contract/<name>.py. Logs are ignored .local-setup/stock-verified-<name>.txt and .local-setup/stock-verification-summary.json. These commands require the established isolated configuration/services and are not ordinary application test instructions.

| Integration file | Passed |
| --- | ---: |
| stock_counts | 39 |
| stock_counts_concurrency | 7 |
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
| stock_counts_http | 25 |
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

**598 integration checks including 32 real concurrency scenarios; 377 HTTP checks.** stays_legacy_guard additionally passed 22 audited denials and 12 unchanged full-table row hashes. apache_routes passed five public-route/seven private-path checks plus local-only/deny-by-default assertions.

The seven new races use real competing processes: identical/different approval keys, approval versus actual guest sale, begin versus sale, finish versus sale, overlapping count approvals, and approved purchase delivery versus count approval. The initial future-dated guest-sale fixture exceeded the existing booking_extras TIMESTAMP range; only its synthetic date was corrected to 2035. No application schema/data repair was made.

TDD observed the missing-service failure before implementation. Independent read-only review identified historical comparison poisoning old reads and a cancelled-partial blind-count edge. Both were reproduced as failing regressions and fixed, with the full suite green afterward. No other important authority, quantity, transaction, privacy or route-bypass findings were reported.

Dedicated Chrome port 9236 exercised actual counting, action focus, canceled/successful submission and approval, dispute, clarification, requested linked recount, no-variance approval and retained original evidence. Six views at 1440/390/320 pixels passed (18 responsive views). The initial mobile register grid overflow was measured and fixed with a constrained grid track; tables scroll internally. Final mobile approval screenshots were visually inspected. Ignored evidence: .local-setup/stock-count-new-1440.png and stock-count-approve-1440/390/320.png. Browser test timing was corrected to await actual navigation; text assertions use source text where CSS uppercases headings.

Eighteen changed PHP paths passed php -n -l. JavaScript passed node --check; affected Python passed AST parsing. Scoped UTF-8/known-mojibake scan and git diff --check passed, with existing line-ending warnings. Much current source predates this slice as untracked work; targeted review, lint and tests supplement tracked diff checking. After final note/mobile display changes, the 25-check stock HTTP suite passed again.

Actual ordinary localhost Apache anonymous GET stock-counts.php returns302 to auth/login.php. .env and src/Service/StockCountService.php remain403. No authenticated ordinary application action was used as a smoke test.

One automatic approval-review usage-limit failure did not execute its blocked command. User continuation allowed the same authorized verification to resume; no approval check was bypassed.

## Release and recovery

Implemented/tested locally; no new local activation required on the existing baseline. No ordinary database fixtures, SQL import, Git-state changes/commit, push or production deployment. Existing late-posting regression rehearses migration 010 only on synthetic data. MariaDB success does not establish MySQL 8 production parity.

Production remains separately authorized coordinated delivery with verified configuration, owner binding, supported writers, permissions and recovery. Before count events exist, scoped source rollback is possible. Once events or movements exist, forward repair must retain all original counts, declarations, approvals, adjustments, operations, evidence and audit links. Do not re-enable old public immediate adjustments, run incompatible old writers concurrently, delete originals, or restore over newer activity.

Reassigning an original counter, general financial corrections/write-offs, valuation, damage disposal, payroll deductions and external alerts are outside this slice. Damaged/expired quantities remain separately secured evidence pending owner treatment, not a silent disposal. Unsupported/missing live comparison blocks adjustment while original history stays available. In-app attention does not claim delivered email/WhatsApp notices.
