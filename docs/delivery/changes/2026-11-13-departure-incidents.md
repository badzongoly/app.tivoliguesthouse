# Departure incidents implementation

Approved workflow resumed on 13 November 2026. Source checkout is C:/xampp/htdocs/app.tivoliguesthouse.

Use append-only finance events and existing transactional stay operation keys. Record a nullable actual departure time, discovery time, provisional bill and optional private evidence. Vacant secured rooms proceed through cleaning and inspection. Unsecured rooms retain an incident hold. Eugene confirms the final bill and collection assignment. Room revenue is recognized in the open review period and linked to actual departure. Separate owner-only collections use the existing departed-receipt guard. Reported departures, reviews, disputes, corrections and follow-up remain visible.

Implement and test the domain transitions first, then financial reporting and interface integration. Verify unknown times, historical unknown rates, room reuse, stale confirmations, duplicate commands, rollback, authority, closed-period totals, private evidence and recovery. Perform independent review before completion. No financial history may be rewritten. The previous approval-service usage failure executed no write.

## Implemented and verified Â· 14 November 2026

Status is implemented and verified locally. This record supersedes the implementation plan above. The approved scope covers SOP C6â€“C8 and G5, with closed-period treatment under G2â€“G3. It does not introduce a write-off or discount policy.

### Operating workflow

1. Open a current guest's Show Details page and select Report Unauthorised Departure. Record discovery time, actual departure if known, what happened and whether the room is vacant and access secured. Optional PDF/JPEG/PNG evidence uses the existing encrypted private attachment store.
2. The incident becomes visible in Departure Incidents, Owner Reports and the weekly review. The guest has operationally departed. Automatic room charges stop at the reported departure or discovery time. The bill remains explicitly provisional until Eugene reviews it. Unknown actual departure remains unknown.
3. A vacant secured room enters normal cleaning, inspection and release. Otherwise an incident security hold prevents reuse. Staff can later verify vacancy and access security. Guest debt does not prevent reuse of a safely released room.
4. Eugene previews charges at the evidenced actual departure time and explicitly confirms the final bill, reason and, when money is outstanding, follow-up employee and due date. A changed bill invalidates the preview. He can instead dispute or retract an unfinalized report. Retraction is denied after another guest has occupied the room.
5. Eugene can record a new partial Cash/MoMo collection against the confirmed outstanding balance, with reference and note. This is distinct from recovery of a receipt actually received earlier. A confirmation and displayed-balance check protect collection. Receipt, authorization, operation identity and audit commit once.
6. Follow-up retains actor, note, assignment and due date. Unreviewed, disputed, unsecured or nonzero-balance cases stay open. Guest credits remain open until resolved through the existing owner-authorized refund path. Earlier retracted reports, original facts and private evidence remain accessible after re-reporting.

### Data and contracts

Observed constraints include a database trigger prohibiting updates to already checked-out bookings. Initial reporting therefore appends finance events without changing the raw active booking. DepartureFacts supplies the operational status and nullable departure date to occupancy, account filters, exports and affected legacy reports. All supported active-stay writers reject a nonretracted report. On final confirmation, the existing first transition to checked_out and immutable stay_departures snapshot are committed together.

DepartureRevenue recognizes incident room charges once in the current open review period using incident_room_revenue. It excludes the same departure from ordinary departure-date room revenue. Actual physical departure remains separately recorded. Existing weekly snapshots, closed monetary totals, payments, refunds, source IDs and attribution are preserved. Pending room revenue is provisional and is not silently posted into an older period.

Existing active-only ordinary payment rules remain. Departed new collections use the installed migration-010 authorization guard and are labelled Outstanding Collection / Received. Older receipts retain Recovered Receipt / Original Date. Unknown historical room rates are never inferred from today's tariff and prevent finalization until separately evidenced reconciliation. No new schema migration, account binding or permission grant is required on the already-activated local baseline.

Source contracts changed in StayService/DepartureIncidents, FinanceService/FinanceAttachment, DepartureFacts/DepartureRevenue, OwnerReports, occupancy/finance/owner/dashboard/report controllers, stay export/identity handling and the related templates. Incident evidence download is designated-owner only. Existing legacy writer denials remain enforced. Events and required audits use the existing transactional connection, lock and persistent operation keys.

### Executed checks

All database and workflow checks used only 127.0.0.1:3307 / tivoli_auth_test with marker synthetic-auth-only-v1, or the marked synthetic HTTP service at 127.0.0.1:8099. PHP 8.2 / MariaDB 10.4 are the established test baseline. Production MySQL 8 parity is outside this verification. Working checkout is C:/xampp/htdocs/app.tivoliguesthouse on main with substantial pre-existing uncommitted work.

Commands were executed with C:/xampp/php/php.exe for PHP, C:/Python313/python.exe for contracts, and C:/Program Files/nodejs/node.exe for JavaScript. Local evidence is ignored under .local-setup/departure-verified-<test>.txt.

| Command relative to checkout | Passed |
| --- | --- |
| tests/Integration/departure_incidents.php | 42 assertions |
| tests/Integration/departure_incidents_concurrency.php | 4 real races |
| tests/Integration/stay_billing.php | 31 assertions |
| tests/Integration/stays.php | 137 assertions |
| tests/Integration/stays_concurrency.php | 3 real races |
| tests/Integration/finance.php | 37 assertions |
| tests/Integration/finance_concurrency.php | 6 real races |
| tests/Integration/finance_changes.php | 45 assertions |
| tests/Integration/finance_late_posting.php | 36 assertions |
| tests/Integration/finance_posting_lock.php | 1 posting-lock check |
| tests/Integration/finance_weekly.php | 25 assertions |
| tests/Integration/finance_weekly_concurrency.php | 3 real races |
| tests/Integration/finance_reviews.php | 12 assertions |
| tests/Integration/owner_reports.php | 19 assertions |
| tests/Integration/expenses.php | 52 assertions |
| tests/Contract/departure_incidents_http.py | 26 assertions |
| tests/Contract/stays_http.py | 122 assertions |
| tests/Contract/stay_billing_http.py | 16 assertions |
| tests/Contract/finance_http.py | 43 assertions |
| tests/Contract/finance_weekly_http.py | 3 checks |
| tests/Contract/stays_legacy_guard.py | 22 denied/audited writes and 12 unchanged table hashes |

The integration total is 453 checks including 16 concurrency scenarios. HTTP total is 210 checks, plus the legacy denial/hash contract. Focused cases cover retries, exact balances, no invented receipt, room turnover/reuse, stale review, closed-week invariance, current-period revenue once, unknown dates/rates, disputes/retractions, earlier report history, credit resolution, owner restrictions, audit rollback, attachment privacy, CSRF, HTML/CSV agreement and receipt labels.

Isolated Chrome on port 9235 exercised report, review and register at 1440/390/320 pixels, final preview and collection at 320 pixels, unknown-time submission, canceled report confirmation with delayed navigation check, actual owner finalization, preserved security hold, weekly alerts and action focus. All checked views had no page overflow or PHP/encoding error output. Mobile owner-review screenshot was visually inspected. Synthetic screenshots remain ignored under .local-setup/departure-*.png.

Twenty-three affected PHP files passed php -n -l. Occupancy JavaScript passed node --check. Changed source passed UTF-8/known-mojibake scanning and git diff --check passed with existing line-ending warnings. Independent read-only review found no remaining important findings after fixing operational filters, room reuse, collection labels and earlier-report visibility. Application/database execution was implementer verification.

### Release boundaries and recovery

No ordinary application database writes, new schema migration, Git-state changes, commit, push or production deployment were performed for this feature. The synthetic late-posting regression rehearses its existing guarded test migration only. In-app alerts are implemented. External email, WhatsApp delivery, automatic reminders and debt write-off are not implemented by this slice.

Production release remains a separate coordinated and approved action. Deploy the complete supported source artifact, preserve migration 010 and the designated finance-owner binding, and prevent old writers running concurrently. Old readers and writers do not understand pending incident events, so restoring old code after a report can resume charges or show an occupied room incorrectly.

Before any incident event, a scoped source rollback can restore the prior behavior. After reporting or collections, use a forward repair retaining every report, original payload, follow-up, revenue recognition, receipt and audit link. Never delete incidents or restore a database backup over newer financial activity. Retractions are explicit operational events with their reuse restriction. There is no silent financial reversal path. General charge corrections, write-offs and reconciliation of unknown historical rates need separately approved evidence and implementation.
