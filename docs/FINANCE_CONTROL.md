# Finance & Cash Control — local implementation

## Current workflow update — 31 October 2026

New provider requests use Phone Number and private work/receipt upload; expenses have optional receipt files and Cash/MoMo settlement. Payment Log and Other Income are finance tabs. Cash expense funding remains separate from full sales-cash deposits. Advances become revenue only on service delivery. These specifics supersede the corresponding original MoMo-only/invoice-form statements below. See [workflow change and validation](delivery/changes/2026-10-31-finance-workflows.md). No additional migration is needed.

Implemented 31 October 2026. Entry point: `public/finance.php`, shared sidebar **Finance & Cash**. Built and tested against the isolated synthetic database only; ordinary database activation and production deployment are not completed.

## Agreed operation

- Guest receipts use Cash or MoMo. Costs, staff pay, provider payouts and new guest refunds use MoMo. Existing payment methods and historical records remain stored and readable.
- Recorded cash is treated as received. No physical cash-count or recount workflow.
- Cash receipts must be converted in full by the next calendar day. Expenses/refunds never reduce the gross deposit obligation.
- The explicit activation cursor excludes earlier receipts from inferred cash custody. Older receipts remain in their existing history and period collections reports. No opening cash holding is fabricated.
- Deposit handover reserves the oldest eligible receipts. Clerks can allocate only their own receipts; administrators can consolidate receipts. Track cash held, in transit, confirmed wallet credit, due and overdue receipts. Late conversion remains visible.
- Administrator checks independent Business MoMo evidence. Exact wallet credit equals gross cash less the disclosed conversion fee. Only that fee becomes a cost. The transfer is not revenue.
- References are unique within the one Business MoMo wallet. Staff-entered references are not API verification. This module records transactions already completed outside the app; it never sends money.

## Screens and authority

Money Overview; Daily Reconciliation; Cash → MoMo Deposits; Expenses; Payroll & Staff Cost; Provider Payments; Room & Extras Revenue; Profit Review; Audit Trail.

Clerks see their own cash and provider requests, related decisions and settlement status, and can use existing expense submission. Administrator status plus `reports.view` is required for reconciliation, matching, approval, settlement and management reviews. Payroll preparation/review/payment also requires `hr.salary`. Other administrators cannot read payroll request detail without that privilege. Existing admin/clerk roles and assignments are retained; setup does not grant new privileges.

Expense approval reuses the authoritative ExpenseService and original ledger. Pending/rejected clerk submissions do not count. Administrator expense entry remains immediately counted, as previously agreed. Recording a completed payout settles the liability without recognising another cost. Already-paid costs must use their existing transaction evidence, never a second external payment. Historical expenses before activation are not automatically offered for payment again.

Provider invoice capture requires accepted work and evidence. Another administrator approves or rejects; the requester cannot approve their own invoice. Normalised invoice identity is protected against duplicate submission and cross-entry through Expenses. After rejection, a new request can replace it with an explicit retained link; no original is changed. Unreferenced expenses still need human duplicate review.

Monthly payroll reads the immutable effective HR salary revision. It records base salary, reviewed allowances/overtime, employer cost, deductions, previously paid advances and attendance evidence. Cost is gross pay plus employer cost; net payable is gross less deductions and prior advances. No tax, pension, overtime-rate or statutory deduction formulas are invented. Other salary frequencies require a separately reviewed calculation. Full account numbers remain in the encrypted HR salary store; new payout annotations accept at most four account digits.

Daily close requires due cash confirmed, no deposit in transit and post-activation MoMo receipts matched through the selected date. Today's cash may remain held until tomorrow. Financial source operations check the closed-day state inside the same transaction. Reopening appends a reason and retains the prior snapshot.

Weekly/monthly review keeps the original source totals and comment. Pending expense/provider/payroll costs must be reviewed first. The reviewed dates reject new cost or revenue changes until an administrator reopens them. Zero-fee cash conversion does not change profit and can continue. Action owner and due-date follow-up records are retained.

## Report definition and limits

**Management estimate, not statutory profit or a nightly accrual ledger:** room revenue uses frozen completed-stay departure charges; extras use their original sale snapshots and exact line rounding; incidental revenue uses its approval date; costs use incurred dates. Active rooms and historical departures without frozen charges are explicitly excluded/flagged. Cash/MoMo collections and refunds are shown separately. Legacy dashboard/report definitions remain unchanged.

Stock purchase expenses may precede extras sales; no guessed historical COGS valuation, accrual, depreciation, statutory payroll remittance or opening wallet balance is created. Prior advances are reviewed inputs, not a newly paid advance. Supporting evidence is a recorded reference/note; no new finance attachment uploader or MoMo API is included. Erroneous posted or confirmed financial events require a separately reviewed compensating correction; this release has no erase/edit or automatic balancing command. No historical data repair/backfill was performed.

## Integrity and security

`009_finance.php` adds finance_schema, finance_events, finance_operations and finance_claims. Finance events, claims, operation identities and cutover records reject UPDATE/DELETE. Existing expense, stay, payment and stock protection remains authoritative.

All finance commands use PDO, exact integer minor units, the existing security-state row lock, persistent actor/key/fingerprint identity and one transaction for event, claim, operation and required audit. Account/role changes use that same lock. Repeat identical forms return the original event; changed payloads are rejected. Required audit failure rolls everything back. Server permissions, transition/amount validation, CSRF, HTML escaping, no-store and action-focus behavior are retained. Reference uniqueness does not prove physical receipt or merchant success.

Threats covered: forged clerk approvals, cross-cashier cash allocation, salary disclosure, CSRF, duplicate posting/references/invoices, concurrent over-allocation, audit partial commit and closed-period writes. Direct denied finance access is audited. No real credentials or guest information are in test fixtures.

## Activation and recovery

Prepared command, **not executed against the ordinary database**:

```powershell
# Target after review: exact loopback 127.0.0.1:3306 / tivoli, existing local disposable setup.
# TIVOLI_TEST_MODE must be absent for that target; do not repoint this command at production.
& C:\xampp\php\php.exe scripts/setup-finance.php --confirmed-local
```

The installer verifies the exact configured DSN, transactional source tables and occupancy schema, adds tables/triggers, then records the receipt/expense cursor under the shared lock. It is rerunnable and does not backfill, modify or replace historical transactions. Ordinary activation needs target-specific approval after independent review; it changes the financial control boundary. Production additionally needs verified host/version parity, backup/restore evidence and an approved rollout.

Recovery is a forward code fix preserving all recorded events and source links. Never drop the finance tables, delete operations/claims or rewind the cutover after recording events. Do not resume an older writer that bypasses day/period guards or retry identities. Setup rerun preserves the existing cursor and every recorded field.

Independent review was requested through the software-development-team code-review handoff, but the reviewer agent failed before reviewing because its usage allowance was exhausted. Self-verification is not an independent review; release and ordinary activation remain gated.

## Executed evidence

PHP 8.2.12; isolated MariaDB 10.4.32, exact `127.0.0.1:3307/tivoli_auth_test`, verified synthetic marker and test account. Marked HTTP service `127.0.0.1:8099`; isolated browser 9233. These results do not establish production MySQL 8.0 compatibility or a production restore drill.

- `tests/Integration/finance.php`: 35 assertions (exact cash/fee/cost/payroll arithmetic, permissions, invoices, idempotence, rollback, immutable history, protected earlier payment/expense row hashes).
- `tests/Integration/finance_concurrency.php`: 4 real independent-process scenarios (same-key replay, competing allocation, duplicate invoice, approve/reject race).
- `tests/Integration/finance_reviews.php`: 12 assertions (close gates, next-day cash, reopening snapshots, locked dates, zero-fee conversion).
- `tests/Integration/finance_migration.php`: 11 complete-field preservation checks across source and finance tables on setup rerun. Initial empty finance setup also executed on the synthetic target.
- `tests/Contract/finance_http.py`: 37 HTTP assertions covering all sections/forms, direct clerk denials, CSRF, invalid dates and MoMo settings.
- `tests/Contract/finance_browser.cjs`: 19 actual-app views at 1440/390/320 pixels, no page overflow or encoding errors, plus action focus; screenshots visually reviewed.
- Existing occupancy: 134 integration assertions, 121 HTTP assertions, 3 concurrency scenarios.
- Existing expenses: 52 integration assertions, 36 HTTP assertions, 4 concurrency scenarios.
- Existing HR: 49 integration assertions, 55 HTTP assertions, 3 concurrency scenarios.

The first HR run exposed a fixture assumption: it assumed no other retained continuing administrators, while finance tests had created independent reviewers. The test now temporarily isolates that specific last-admin scenario and restores prior synthetic account states in finally. Only the known synthetic administrator was restored after the interrupted first run; no application safeguard or expected denial was removed. Salary-method test inputs were changed to the approved MoMo policy, with prior encrypted-field/history assertions retained.

No ordinary database setup, production migration, financial deletion/backfill, Git branch/commit/push or deployment was performed. Existing uncommitted work was preserved.


## Owner-authorised local finance activation — 2026-10-31

Owner explicitly instructed activation after the outstanding independent-review limitation was disclosed. Executed the prepared additive installer only on verified XAMPP 127.0.0.1:3306/tivoli, with datadir C:/xampp/mysql/data. Activated at2026-10-31 18:21:43 UTC. Finance marker, all eight immutable-history triggers, cash register and summary read queries verified. Complete ordered row hashes before/after matched for bookings, payments, expenses, booking_extras, stock_movements, stay_refunds, stay_departures and stay_receipts. No historical field/identity changes, backfill, synthetic fixture insertion, external payment, Git change, push or production deployment. Cash tracking starts at the activation cursor; earlier cash custody remains unknown. Independent review remains outstanding; this local activation is not a production-release approval. Recovery remains a forward fix retaining every event and the original activation cursor.


## Checkbox-only Deposit Confirm — 31 October 2026

Owner requested removal of Exact Wallet Credit and Wallet Statement / Evidence Reference inputs. New forms require only the wallet-check attestation and Record Action, with the existing deposit/reference/fee and server-derived expected net credit shown as read-only information. The server derives credit from the immutable gross amount minus recorded fee. It requires administrator authority and the checked attestation; older open forms may still submit amount/evidence, but conflicting legacy amounts are rejected and existing evidence is retained. New events explicitly record operator_attestation and credit_minor; missing evidence is left empty, never fabricated. This remains a manual operator check, not merchant API verification.

Executed:37 finance integration assertions,42 finance HTTP assertions and12 focused checkbox-confirmation HTTP assertions; changed PHP lint passed. Covered missing checkbox, role denial, fee arithmetic, legacy amount mismatch, no-input success, retry identity, confirmed register state and unchanged earlier payment/expense fields. The focused HTTP harness initially included checkbox value1 even for its unchecked scenario; corrected it to omit unchecked controls as browsers do. No application guard or expected amount was relaxed. Independent reviewer finance_changes_review rechecked the delta read-only and found no blocker; its suggested read-only expected-credit display was added and verified by HTTP. No ordinary database financial writes, migration, history rewrite, Git-state change, push or production deployment.


## Payroll Request evidence field removal — 1 November 2026

Removed Calculation / Attendance Evidence from new Payroll Request forms at the owner's explicit request. The service accepts an omitted comment, stores empty evidence rather than invented text, and preserves any old comments supplied by existing forms. Attendance/leave/overtime/deduction attestation, salary permissions, exact payroll calculations, separate approval, immutable history and audit remain unchanged.

Executed:changed service/view PHP lint;37 finance integration assertions, including payroll without comment and exact gross/net amounts;43 HTTP assertions, including removed evidence field and retained attendance checkbox. All workflows used only verified synthetic3307/tivoli_auth_test and marked HTTP8099. No ordinary database changes, migration, financial history rewrite, push or production deployment.
