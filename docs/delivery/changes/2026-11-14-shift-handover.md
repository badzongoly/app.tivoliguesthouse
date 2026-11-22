# Shift handover implementation

Owner approved the interactive workflow on 14 November 2026. Implementation and verification are complete in the local checkout. No new schema migration or production deployment.
## Implemented and verified — 14 November 2026

The owner-approved prototype is implemented in the existing local checkout. This supersedes the in-progress note above. Entry point is handover.php, linked under Guest Operations as Shift Handover. The source checkout remains C:/xampp/htdocs/app.tivoliguesthouse on main, preserving the extensive pre-existing uncommitted application work.

### Workflow and acceptance

- Prepare Handover reviews four current sections before choosing the recipient, actual duty start/end and optional notes. A final confirmation records the snapshot atomically. Incoming acknowledgement can follow later. Submitted items and notes never silently refresh when later business activity occurs.
- Incoming Review permits only the named recipient to acknowledge the original handover. Comment is optional. A flagged discrepancy needs a description and remains open until Eugene records review. Original discrepancy, acknowledgement, review, actors and UTC timestamps remain visible.
- Cash Transfer records an attested physical amount separately. Pending or disputed cash stays accountable to the sender and is reserved against deposits and other transfers. Recipient-only full acknowledgement transfers custody once. A prior dispute requires a reconciliation explanation. Eugene can record cash-review evidence without fabricating acknowledgement, changing the amount or closing the reservation.
- Finance & Cash uses the same custody positions for all supported deposit writes. Recipients can deposit transferred cash and send it onward. Original collectors cannot spend acknowledged recipient portions. Admin deposits also respect pending reservations and custody chronology. The receipt's original collector, date, full gross amount and next-day deposit deadline remain unchanged. Cash expenses remain separately funded.
- History supports older-record navigation. Owner Attention and Owner Reports retain unacknowledged handovers, discrepancies and unresolved cash transfers. Snapshot details are restricted to sender, recipient and the designated finance owner. Other administrators cannot bypass that restriction through Finance Events.
- Guest snapshots include overnight/short-stay type and the stored short-stay deadline. Effective HR exits and suspensions are excluded using read-only eligibility checks rather than processing HR exits inside another transaction. No physical cash-count, automatic shift attendance/payroll calculation or external notification delivery is introduced.

### Source and storage contracts

New service HandoverService exposes draft, detail, register, attention and execute. New pure domain CashCustody replays per-receipt positions through recorded deposits and acknowledged transfers. Pending transfer reservations do not create revenue, costs, receipts or deposits. Finance totals, previously closed monetary snapshots and source IDs retain their existing meaning.

Authoritative records use the existing append-only finance_events and finance_operations tables and atomic security_audit. Root event kinds are handover_submitted and cash_transfer_sent. Linked history uses handover_acknowledged, handover_reviewed, cash_transfer_acknowledged, cash_transfer_disputed and cash_transfer_reviewed. Existing operation keys and the shared security_state transaction lock protect retries and competing writers. A changed snapshot token or review version rejects the command. Required audit failure rolls back the event and operation.

Deposits retain their existing receipt allocations and additionally snapshot custody_allocations. Older deposits retain their original interpretation. Per-receipt custody_since records a conservative latest acknowledgement bound for a returning/mixed receipt portion. A deposit cannot be dated before that custodian's acquisition date. This may require current-date posting rather than backdating an older portion after cash from the same receipt returns to that custodian. It never changes the original source/deadline or invents earlier custody.

No schema migration, new role, permission grant, secret or runtime dependency was added. Existing bookings.manage authorizes operational access, recipient identities come from active eligible accounts, and the established configured finance owner controls all-owner review. Direct endpoints enforce permission and CSRF independently of navigation.

Files/contracts affected
- src/Domain/Finance/CashCustody.php and src/Service/HandoverService.php
- src/Service/FinanceService.php cash replay and deposit allocation
- public/handover.php, templates/handover/index.php and handover.css/js
- shared endpoint guard, workspace context and sidebar
- Finance controller/template cash visibility and custody register
- Owner Reports controller/template attention link
- integration, HTTP and concurrency tests described below

### Executed evidence

All database checks ran only on 127.0.0.1:3307 / tivoli_auth_test with marker synthetic-auth-only-v1. HTTP/browser work used the marked synthetic service at 127.0.0.1:8099. The established baseline is PHP 8.2 / MariaDB 10.4. Production MySQL 8 parity was not established.

Use C:/xampp/php/php.exe for each PHP test below and C:/Python313/python.exe for each Python contract. Captured logs are ignored under .local-setup/handover-verified-<test>.txt.

| Executed command relative to checkout | Passed |
| --- | --- |
| tests/Integration/handovers.php | 42 assertions |
| tests/Integration/handovers_concurrency.php | 4 real races |
| tests/Integration/finance.php | 37 assertions |
| tests/Integration/finance_concurrency.php | 6 real races |
| tests/Integration/finance_changes.php | 45 assertions |
| tests/Integration/finance_late_posting.php | 36 assertions |
| tests/Integration/finance_posting_lock.php | 1 guard check |
| tests/Integration/finance_weekly.php | 25 assertions |
| tests/Integration/finance_weekly_concurrency.php | 3 real races |
| tests/Integration/finance_reviews.php | 12 assertions |
| tests/Integration/owner_reports.php | 19 assertions |
| tests/Integration/expenses.php | 52 assertions |
| tests/Integration/stay_billing.php | 31 assertions |
| tests/Integration/stays.php | 137 assertions |
| tests/Integration/stays_concurrency.php | 3 real races |
| tests/Integration/departure_incidents.php | 42 assertions |
| tests/Integration/departure_incidents_concurrency.php | 4 real races |
| tests/Contract/handovers_http.py | 27 checks |
| tests/Contract/finance_http.py | 43 checks |
| tests/Contract/finance_changes_http.py | 32 checks |
| tests/Contract/finance_weekly_http.py | 3 checks |
| tests/Contract/stays_http.py | 122 checks |
| tests/Contract/stay_billing_http.py | 16 checks |
| tests/Contract/departure_incidents_http.py | 26 checks |
| tests/Contract/owner_reports_http.py | 10 checks |
| tests/Contract/access_http.py | 24 checks |
| tests/Contract/stays_legacy_guard.py | 22 audited denials and 12 unchanged table hashes |

Totals are 499 integration checks including 20 real concurrency scenarios, and 303 HTTP checks, plus legacy denial/hash verification. The handover races exercise overspending transfers, deposit versus transfer, simultaneous acknowledgement retries, and acknowledgement versus a recipient deposit.

The initial feature assertion failed before implementation. Independent source review found pre-acquisition deposit backdating, due HR exits and omitted short-stay details. A regression reproduced all three before the fixes, then all three passed. Due-date display was also corrected. The earlier finance review fixture assumed that all recorded held cash was spendable. Its first run correctly failed once pending transfers reserved cash. The guarded fixture now explicitly acknowledges its synthetic cash transfers before attempting to deposit all synthetic cash. It retains every original transfer and reruns the same financial close assertions.

An isolated Chrome session on port 9236 exercised the actual three-step wizard, native validation, confirmation preview, canceled submission with delayed navigation check, successful synthetic submission, incoming action focus, discrepancy acknowledgement and owner review retaining the original exception. Six handover views plus Finance custody passed at 1440/390/320 pixels, with no page overflow or JavaScript/PHP/encoding output errors. Desktop and 320-pixel screenshots were visually inspected. Synthetic screenshots remain ignored under .local-setup/handover-*.png.

Seventeen changed PHP files passed php -n -l. Handover JavaScript passed node --check. Changed source passed UTF-8/known-mojibake checks. git diff --check passed with existing line-ending warnings. Because most current application files predate this slice as untracked work, source lint, encoding review and targeted independent review supplement the tracked diff check.

One automatic approval-service usage-limit failure did not execute the browser command. The user resumed work and the same escalated verification completed successfully. This was not bypassed.

### Release and recovery

Implemented and verified locally, with in-app alerts. No ordinary application-database test writes, production migration/deployment, Git branch/staging/commit changes or push occurred. The late-posting suite rehearsed its existing migration-010 test setup on synthetic data only. The active local baseline needs no new activation.

Deploying to production remains separately authorized work requiring the complete coordinated source artifact, existing finance setup and verified owner binding. Do not run older deposit writers after custody events exist. They cannot understand reservations or transferred portions.

Before new events, a scoped source rollback can restore the earlier behavior. After any handover, custody or deposit event, use a forward repair preserving all originals, allocations, acknowledgements and audit links. Do not remove a transfer, fabricate an acknowledgement, silently reassign history or restore a backup over newer financial activity. Amount corrections, lost cash/write-offs and recipient reassignment after submission require a separately evidenced authorized procedure. An inactive/unavailable recipient's record remains visible to Eugene rather than being falsely acknowledged.

The approved full-deposit/no-count policy overrides the controlled SOP's older target text about cash counting/deducting expenditure. The duty period is staff-entered evidence and does not automatically alter attendance or payroll. Alerts are in-app, with no email/WhatsApp dispatch or automatic reminder claim.

See [staff instructions](../../STAFF_HANDOVER.md) and [approved design](../../superpowers/specs/2026-11-14-shift-handover-design.md).

