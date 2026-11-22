# Paid check-in and owner departure authority

Approved implementation of the guest billing prototype.

## Plan

1. Establish a failing synthetic regression for mandatory room payment, atomic arrival, retry and owner-only debt approval.
2. Extend the existing StayService transaction to create the arrival receipt and link audit data. Recheck the displayed tariff, payment amount and current accounting period. Use the configured finance owner for debt authorization and retain its reason.
3. Update the existing occupancy form, success notices and rate preview. Keep optional documents and existing guest fields. Use a confirmation prompt.
4. Update supported test callers for the intentional paid-arrival contract. Verify full settlement, debt limits, protected departure snapshots, UI/CSV/report agreement, concurrency and audit rollback.
5. Obtain independent review, record executed evidence and update delivery state.

No schema migration is planned. Existing historical payments and approval records are preserved. New owner authority is enforced at every command and in presentation. An existing approval from another administrator needs fresh owner authorization before a new unpaid departure. The reported-departure incident route identified by the audit remains outside this approved implementation slice.

## Implemented and verified · 13 November 2026

Status is implemented and verified in the local checkout.

Check-in now requires the received amount, Cash/MoMo method, unchanged displayed rate and identity/payment/readiness confirmations. The server validates the first overnight or short-session payment under the existing security-state lock. Booking, receipt, original collecting employee, reference, time, occupancy and audit commit together. The operation key prevents duplicates. Cash enters the full-deposit tracker and MoMo enters independent wallet matching. Optional guest documents retain the existing protected storage.

The form displays the initial accommodation coverage and current rate. It confirms the guest and payment before submission. The account shows the next overnight charge boundary or short-stay limit. Completed statements identify final departure and checkout balance. No new rate or late-fee policy was introduced.

New debt authorizations require the configured finance owner, a reason and the displayed balance. The durable audit links approval ID, actor, amount and reason. Authorization is checked again when consuming it. A higher balance invalidates it. Historical non-owner approvals remain stored but require fresh owner authorization for a new unpaid departure.

### Executed evidence

All database tests ran against loopback port 3307, tivoli_auth_test and synthetic-auth-only-v1. The rebuilt test environment needed the existing migration 010 to exercise the already-supported departed recovery path. It was applied through its guarded test setup script. This implementation requires no new application migration.

- tests/Integration/stay_billing.php — 31 assertions passed, including denied inputs, closed day, rate change, audit rollback, exact receipt, retry, owner restrictions, historical approval preservation and fixed departure totals.
- tests/Integration/stays.php — 137 assertions passed. Existing fixtures now supply the intentional paid-arrival contract and assert its receipt rather than assuming zero arrival receipts.
- tests/Integration/stays_concurrency.php — three real races passed, including one active stay/receipt and one full settlement for competing requests.
- tests/Contract/stays_http.py — 122 assertions passed.
- tests/Contract/stay_billing_http.py — 16 assertions passed, including clerk and other-admin denial, owner reason/approval, confirmed unpaid departure and exact CSV/statement agreement.
- tests/Integration/finance.php — 37 assertions passed.
- tests/Integration/finance_changes.php — 45 assertions passed.
- tests/Integration/finance_posting_lock.php — guard verification passed.
- tests/Integration/finance_late_posting.php — 36 assertions passed after installing the existing test recovery schema.
- tests/Integration/owner_reports.php — 19 assertions passed.
- tests/Contract/stays_legacy_guard.py — 22 denials/audits and 12 unchanged table hashes passed.
- Isolated Chrome verified arrival and checkout at 1440, 390 and 320 pixels, active-form focus, dynamic short-stay rate, canceled confirmation, actual confirmed synthetic check-in and cashier-attributed receipt. The mobile screenshot was visually inspected.

Independent static review reported no actionable important findings in the service, controller, templates, JavaScript, identity preparation and transaction/owner gates. Test execution is implementer evidence.

### Boundaries and recovery

Existing financial rows and approval records were not rewritten. The actual application database received no test fixture writes or migrations. No commit, push or production deployment was performed. The synthetic fixture directory and reports remain ignored under .local-setup.

After new arrivals have receipts, retain those posted receipts through a forward repair. Do not restore an older writer that accepts unpaid arrivals or broader debt authority. Existing active stays keep their evidenced payment history. The separate incident route for a guest leaving without advance authorization remains a later design item, as disclosed at prototype approval. Historical fractional-line rounding still needs separate evidence and reconciliation before a policy change.

Application contracts changed in src/Service/StayService.php, public/occupancy.php, templates/stays/index.php and public/assets/js/stays.js. Old arrival forms without amount, method, current rate and confirmations are rejected. Existing role/session names, rate snapshots, cutoff and short-stay policy remain supported.
