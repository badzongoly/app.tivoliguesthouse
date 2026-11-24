# Tivoli Mobile-First UX Implementation and Validation

Date — 23 November 2026

[Implementation plan](../../superpowers/plans/2026-11-23-tivoli-mobile-first-ux.md)

## Implemented locally

| Area | Change |
| --- | --- |
| Today and Room Board | Ready rooms use the same physical readiness and stay-rate checks as check-in. Today links to matching Room Board filters, shows authorised shift shortcuts and puts own unfinished cleaning ahead of charts. Room Board search matches visible room and guest names. A landscape tablet can show ready rooms and the selected check-in form together. |
| Guest account and receipts | Check-in shows the selected price before guest details and groups optional ID fields. The result gives the room, receipt and balance. Payment confirmation identifies the room, amount and method. An interrupted submission can be checked by its original operation key while signed in as the recording user. The status check returns an existing receipt and never records a new payment. |
| Checkout and preparation | The balance block explains what staff can do next. Full payment returns to checkout, and the owner-authorised departure confirmation describes the standing balance exception. Cleaning and inspection distinguish their bulk actions, show counts and surface the prior failed items when the room is returned. Critical fault copy explains room availability before submission. |
| Stock, audits and handoffs | Stock counts show completion progress and explain blank versus zero. Stock shortcuts open the existing sales, purchase and count workflows. Audit follow-up displays the next actor and action. Shift handover distinguishes cash held from a transfer awaiting acknowledgement. |
| Finance and owner review | Finance links to common permitted tasks. Payment Log uses stacked entries on phones and keeps the detailed table on larger screens. Corrections compare the current receipt with the effect if approved. Purchase and payroll records show the next step. Owner reports show the selected period and link to the matching Payment Log. Owner Exceptions retains filters and the case position when returning from details. |
| Access, HR and delivery | System Access previews permission additions and removals before submitting existing editors. Eligible staff can open their own leave, documents and payslips from Staff & HR. Selected attachment names and eligible local JPEG or PNG previews appear before upload. Reset instructions remain privacy-safe. Sign Out is first in the account actions. Current reminders link directly to their permitted source task. |

## Safeguards and release boundary

Current routes, permission checks, amount calculations, posting rules, closed-period snapshots, checkout and room-release safeguards, source links and durable operation keys remain in place. Changes to check-in readiness run inside the existing transactional checks. Receipt lookup is scoped to the signed-in recording user and does not return another user's result. Photos are previewed locally through the browser. There are no schema or configuration changes.

The isolated test server is identified by its synthetic response header and the database loopback port and marker. Ordinary local records and hosted production are outside verification scope. No push or deployment is part of this update.

## Executed checks

- Room eligibility contract — 10 scenarios, including stale use conditions and missing or invalid rate data.
- Continuous synthetic turnover — 65 HTTP scenarios plus 13 final invariants. Exactly three receipts totalled GHS 210. The preservation check matched 46,650 pre-existing rows in seven protected tables. Receipt lookup recovered the original result by operation key and rejected other accounts and malformed requests.
- Occupancy — 138 service-level and 128 HTTP assertions on synthetic fixtures. Additional runs passed 33 merged-housekeeping and 16 paid-arrival HTTP assertions.
- Business-week rehearsal — 32 scenarios for the isolated 27 January–2 February 2014 fixture. Exact receipts were GHS 665, recorded costs GHS 172 and the closed recorded-cost result GHS 493. Independent inventory costing tracked GHS 10 sold, GHS 10 damaged and GHS 80 remaining. Seven daily reviews reconciled to the close. Pre-existing rows in 14 tables matched the saved baseline. The late-posting regression separately confirmed that original closed totals and checkout snapshots stayed fixed.
- Shared interactions — 274 browser confirmation assertions, 61 asset assertions, 42 System Access browser assertions and 59 Finance and Stock HTTP checks. The Finance and Stock browser suite passed 48 assertions across 11 responsive views.
- Source-only review found no important issues in the shared readiness rules or actor-scoped payment lookup. Diff whitespace and code syntax checks passed on the inspected files.
- Focused mobile and tablet browser checks — 22 assertions across five viewport sizes, including room switching, selected upload visibility, owner report period links and the 320-pixel Payment Log. Owner Exceptions and Reminders passed 18 and 13 HTTP assertions respectively. The older owner report browser runner did not start its browser, and the broad owner report HTTP suite timed out in a concurrent run. The focused browser check and report access checks were completed separately.

## Staff pilot and remaining validation

- Use a real staff phone, shared tablet and owner laptop. Run the walk-in to turnover task in the plan with synthetic users representing clerk, cleaner and inspector. Record task completion, first tap, assistance, wrong room, payment uncertainty and time. Use the same script on the earlier build for a human baseline.
- Try software-keyboard entry, portrait-to-landscape rotation, zoom, VoiceOver or TalkBack, expired session and a connection that is lost immediately after submission. Reopen Occupancy on the same signed-in tab and check the receipt before deciding whether to retry. Record where the staff member expected to find the next action.
- Check the owner can return to the same filtered case queue and reconcile any total to its contributing records. The report drill-downs are implemented and reconciled on synthetic records. Include the owner’s usual filters in the physical-device pilot.
- Treat an ineligible room released or sold, a duplicate receipt, changed closed totals, missing audit evidence or exposure of private data as release blockers.
- Roll back UI changes by restoring the previous compatible application version. Preserve transactions and audit records posted while the change is active.



## Owner-report drill-downs

- Daily Overview opens recorded arrivals, actual departures, room charges, receipts and exceptions using the selected day.
- Room Performance links to the room-night calendar for the selected room and dates. Cash & MoMo provides exact receipt, refund, matching, custody and deposit detail. The receipt-method filter is retained on return. Refunds, custody and deposit details retain their original report scope.
- Extras links to original sales and separately dated charge adjustments, with day and item drill-downs. Monthly Review opens each month’s receipts and expense lines, including signed expense adjustments, with category-level filtering. Management Profit Review remains a separately defined report.
- Details use the existing owner report authorisation, read-only transaction and source filters. The receipt, cash custody and deposit queries continue to use the report’s existing accounting rules. No schema, financial record or deployment changes were required.
- Synthetic reconciliation passed 33 integration assertions. The detail amounts matched their summaries, malformed filters were rejected and the protected financial row hashes stayed unchanged.
- The full Owner Reports HTTP suite passed 19 scenario checks covering all report views, drill-down rendering, retained filters, administrator-only access and legacy links.
