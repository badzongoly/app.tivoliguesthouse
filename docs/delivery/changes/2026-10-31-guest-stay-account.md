# Guest Stay Account Change Record

Risk: scoped read-only list/filter/navigation change. Acceptance: implement all eight requested UI changes, strict current-week date filter, retained historical access and pagination, unchanged financial and access controls.

## Guest Stay Account and streamlined tabs — 2026-10-31

Implemented the requested label Guest Stay Account, Check-In/Check-Out timestamp columns and removal of the list Balance column. Active stays display Still In House; missing terminal timestamps remain Not Recorded. Full account details retain the authoritative bill; the directory no longer calculates unused per-row balances.

There is one reception tab row: Overnight, Short Stay, Guest Stay Account, Activity. Removed the Room Board/Room Control entries from that tab row, the duplicated lower tab row, the Recent Check-Outs tab/list, and the overnight legacy-billing explanatory sentence. Existing heading shortcuts/sidebar Room Control access remain. The old tab=recent URL resolves to the unified account directory, and section=folios URLs remain compatible. Existing billing, export URLs, room-state transitions, permissions and individual-guest tabs are unchanged.

The account filter defaults to the current Monday-Sunday UTC week, supports inclusive custom From/To dates and an explicit All Dates view. A stay is included when its recorded stay interval overlaps the chosen period; this keeps guests who arrived before the week but remain in house visible. Cancelled records lacking departure use their recorded arrival rather than an invented open-ended stay. Future arrivals beyond the range are excluded. Invalid/impossible/reversed/non-string dates are rejected server-side. Cursor pagination carries the active filters, and changing the filter resets the cursor. Historical rows are never deleted or rewritten. Directory reads retain the existing access audit, including the selected filter definition.

Executed: php tests/Integration/stays.php — 82 assertions; python tests/Contract/stays_http.py — 80 assertions; node tests/Contract/stays_browser.cjs — 21 views at1440/390/320px plus focus/wizard/inspection/account-navigation checks; four changed PHP files linted; mobile account screenshot visually inspected; git diff --check passed with the existing unrelated LOCAL-DEVELOPMENT.md line-ending warning. Synthetic fixtures verified against exact127.0.0.1:3307/tivoli_auth_test and marked8099 HTTP server. No migration, production deployment, Git state change, ordinary financial-data mutation or billing-rule change. Applied to the existing local source checkout.

Recovery: a scoped code fix to the controller/read-query/template; no data rollback is required. Preserve all audit events and all historical records. The UTC display convention remains unchanged.
