# Weekly reconciliation implementation · 13 November 2026

## Scope and approval

The owner approved the interactive weekly reconciliation prototype and the recommendation that unmatched MoMo receipts prevent closure. This implementation adds the designated finance owner's review of a completed Monday–Sunday UTC week. Default selection is the previous week. The three decisions are reconciled and locked, locked with carried pending expenses, and kept open.

## Implementation

FinanceWeeklyReview extends the existing FinanceService command transaction and event store. Weekly decisions use the same security-state lock as posting operations. Closure rechecks a token of the rendered totals and exceptions. Retry identity remains in finance_operations. Finance events and security audit commit together.

MoMo receipts for the entire selected week require matching, including receipts predating finance activation. Historical cash custody remains explicitly unknown. Tracked outstanding cash deposits and pending provider/payroll requests block closure. Pending expenses may carry with an active assignee, due date, and closure reason. They remain excluded from approved expenses.

The retained close payload includes exact integer money totals, cash coverage, evidence reference, and carried submission IDs. Follow-up requires that the source submission has been reviewed. The source status and expense ID are retained. Owner approval of a carried expense can post it into today's open period with original date, reason, evidence and original submitter retained. Existing self-approval restrictions still apply. Keep-open decisions also retain figures reviewed.

Legacy weekly lock/reopen commands direct the operator to Weekly Reconciliation. Existing monthly controls remain. Weekly review details are restricted to the configured owner account and filtered from other administrators' finance event views.

## Files and contracts

Added src/Service/FinanceWeeklyReview.php, templates/finance/weekly.php and tests/Integration/finance_weekly.php. Changed FinanceService, ExpenseService, finance and expenses endpoints/templates, finance navigation, finance JavaScript focus and the existing finance review regression.

No schema migration is required. No change to stored transaction IDs, historical records, rate rules, payment methods or UTC. Existing expense review callers remain compatible with the new optional late input parameter.

## Executed verification

Before the latest review fix, the dedicated synthetic instance on loopback port 3307 passed 17 weekly assertions, 12 daily/monthly review assertions and the ordinary posting-lock check. The first regression failed before implementation as expected. A separate script checked the prototype's three paths, validation and immutable figures.

Independent static review identified premature expense resolution. The implementation now checks the actual source disposition and provides owner-authorized open-period approval. Closure-level reason is retained for carried cases, matching the approved prototype.

The initial HTTP check failed with 403 because the existing synthetic web service lacked the configured finance owner. This was a test environment configuration failure and is not recorded as a successful browser check.

On 13 November the stopped synthetic instance failed restart verification with corrupt Aria grant tables. Before recovery, the marker synthetic-auth-only-v1 and exact datadir were verified. Corrupt system tables and Aria logs were preserved under ignored weekly-test-permissions and weekly-aria-recovery folders. Recovery restored a running process but final fixture access returned error 1932 for security_test_marker. Synthetic verification remains blocked. The latest expense approval and resolution changes have not passed database or HTTP regression. Recovery did not target the application database.

## Release and recovery

Status is implemented, final verification blocked. A clean verified synthetic environment must be established and weekly, expense, HTTP, concurrency, rollback and layout verification completed before release readiness. Reconfirm owner binding for the application server and independently review the late approval slice.

The application checkout contains pre-existing extensive uncommitted work. No commit, push or production deployment was performed. No ordinary application database writes or migrations were performed for this feature. Retain any recorded weekly events during forward repair. Never restore an older writer that can bypass a recorded closure.

## Verification completed · 13 November 2026

The damaged test directory was preserved in full as .local-setup/auth-test-mysql-preserved-weekly-20261113. A fresh instance was initialized at the existing dedicated test datadir on loopback port 3307. The guarded provisioning script verified the datadir and created only synthetic fixtures. Application configuration and application financial records were outside this rebuild.

Executed successfully against the fresh marked synthetic database:
- tests/Integration/expenses.php — 52 assertions.
- tests/Integration/finance.php — 37 assertions.
- tests/Integration/finance_weekly.php — 25 assertions, including carried expense approval, audit-failure rollback, original submitter/date retention, exact current-period cost, retry and immutable closed snapshot.
- tests/Integration/finance_reviews.php — 12 assertions.
- tests/Integration/finance_posting_lock.php — locked/open period guard proof.
- tests/Integration/finance_concurrency.php — six scenarios. This suite requires its fresh finance cash fixtures and passed when run before the daily review consumes those fixtures.
- tests/Integration/finance_weekly_concurrency.php — three closure-versus-receipt races. Each retained exactly one valid outcome.
- Synthetic HTTP verification — designated owner view, all three decision controls, CSRF rejection, keep-open submission/history and clerk denial passed.
- Isolated Chrome — open/closed weekly review and expense page at 1440, 390 and 320 pixels, no horizontal page overflow or PHP output errors, section focus passed. The 390-pixel close decision screenshot was visually inspected.
- Thirteen affected PHP files passed php -n -l. Finance JavaScript passed node --check.
- Read-only local application check confirmed the configured designated finance owner is active and authorized.

The first browser attempt used a temporary server on port 8098 while asset URLs pointed to configured port 8099. This produced an unstyled page. The checks above used the configured synthetic port with styles loaded and passed.

Independent static review of the final weekly/expense path reported no important findings. It checked owner enforcement, source resolution, original-date validation, open-period approval and audit atomicity. Test execution evidence remains the implementer's work.

Status supersedes the earlier blocked status. Implemented and verified locally on PHP 8.2 / MariaDB 10.4. Production deployment and MySQL 8 parity are outside this verification. No schema migration was needed. A forward fix must retain recorded weekly locks and linked late events.
