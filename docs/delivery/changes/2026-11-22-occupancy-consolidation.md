# Check-In & Occupancy consolidation — 22 November 2026

Implemented locally and verified with synthetic records. Owner requested a review of all occupancy workflows and consolidation of duplicate tabs, links and actions while preserving safeguards and guest history.

## Delivered and intentionally retained

The four main tabs remain Room Board, Guest Stay Account, Departure Incidents and Activity. Guest details select Guest Stay Account even on existing default URLs; the original read-only cleaning view also selects that guest parent when permitted. A legacy/default bill URL correctly highlights Bill. The nested guest Activity label is now Stay Activity, distinguishing it from the wider Operational Activity. Scoped, versioned navigation styles make the selected workspace, account and preparation tabs visibly distinct.

The existing action link helper suppresses only an identical already-open form for the same booking, room and secondary ID, and only within occupancy sections. The actual form and cancel control remain. Other records and Property/Extras workspaces keep their existing actions. Duplicate outstanding collection was removed from the incident panel; the existing centrally guarded guest action remains. Shared preparation navigation keeps filters, Cleaning History and owner-only Review consistent; a second Back To Room Board link is removed. Open cleaning, inspection, exception and restart forms do not repeat their own entry link. The historical cleaning record offers Back To Guest Stay Account only to booking-authorized readers and points to its evidenced source booking. It remains read-only and never substitutes another guest's current cleaning attempt.

Payments, Bill, Statement Preview and Stay Activity remain separate purposes. Unauthorized departure remains separate from normal checkout. Late receipt discovery remains separate from collecting new outstanding money. Financial corrections remain distinct from actual refunds. No write service, controller, billing calculation, permission definition, data filter, saved URL, field name, CSRF, confirmation token or durable operation-key contract changed. No schema or migration. No ordinary application guest/financial records were used as fixtures or mutated. Earlier uncommitted work preserved; no Git commit/push or hosted release.

## Files and review

Presentation slice: templates/stays/index.php, workspace-nav.php, new preparation-nav.php, departure-case.php and templates/housekeeping/index.php, plus scoped versioned public/assets/css/occupancy-navigation.css. New focused tests/fixture: tests/Contract/occupancy_navigation_http.py, occupancy_navigation_browser.cjs and tests/Integration/occupancy_navigation_seed.php. User guides: docs/OCCUPANCY_WORKSPACE.md and HOUSEKEEPING_WORKSPACE.md.

A separate source reviewer found no important issue in the slice, including rooms-only boundaries, owner gating, no lost sole action, namespaced variables, original cleaning links and read-only mode. They reviewed sources only, without tests or data access. This is a presentation consolidation, not a financial-policy release.

## Executed checks

| Command / test | Result |
| --- | --- |
| php tests/Integration/stays.php |138 integration assertions passed |
| python -u tests/Contract/stays_http.py |122 occupancy endpoint assertions passed |
| python -u tests/Contract/room_turnover_http.py |59 continuous endpoint assertions and13 final invariants passed |
| python -u tests/Contract/housekeeping_http.py |36passed |
| python -u tests/Contract/housekeeping_occupancy_http.py |33passed |
| python -u tests/Contract/departure_incidents_http.py |26passed |
| python -u tests/Contract/stay_billing_http.py |16paid-arrival/owner-authority assertions passed |
| python -u tests/Contract/activity_visibility_http.py |12actual activity-scope assertions passed |
| php tests/Integration/stays_concurrency.php |3real-process scenarios passed |
| php tests/Integration/housekeeping_concurrency.php |8real-process scenarios passed |
| python -u tests/Contract/occupancy_navigation_http.py |19focused navigation/form/source/role assertions passed |
| node tests/Contract/occupancy_navigation_browser.cjs |126assertions across21responsive views passed |
| node tests/Contract/confirmations_browser.cjs |271shared-modal assertions;73confirmation attributes passed |

The continuous same-room cycle exercised paid arrival, extras, full settlement and confirmation, departure and confirmation, assigned checklist completion, independent inspection/release and another paid arrival. Exactly three receipts total GHS210; one cleaning attempt/completion/inspection. Every one of 45,990 pre-existing protected rows across seven synthetic tables was retained unchanged at final turnover verification. Race checks cover double booking, duplicate checkout/receipt, stock oversell and cleaning/inspection/restart collisions.

Other checks covered noon/short-stay billing, stored rates, overrun handling, balance-due/owner exceptions, unauthorized departure security/review/collections, statements/CSV, guest documents/private evidence, stale or forged requests, original source/history links, own versus administrator activity and rooms-only access. New browser checks used staff, owner and rooms-only roles for guest detail, payment, check-in, cleaning, original source and incident pages at1440/390/320pixels. Keys remained valid and pages fit their viewports; screenshots visually inspected. Browser checks do not post new financial actions. Real writes were exercised by the guarded endpoint/service suites.

All mutation suites ran sequentially against HTTP127.0.0.1:8099 with synthetic response header and guarded database127.0.0.1:3307/tivoli_auth_test/marker synthetic-auth-only-v1. Only intentional race workers ran concurrently. Browser contexts were owned/disposed; no user cookies changed. No external messages, payment transfers, SQL imports, reset or history deletion. Detailed scenario labels are retained privately at .local-setup/occupancy-consolidation-scenarios.csv; fixture identities and logs remain Git-ignored.

## Test maintenance and resolved findings

An older occupancy test expected the retired Cleaning Complete/standalone link and the duplicate inspection entry text. It now verifies assigned Start Cleaning in the merged route and the real captured inspection form. Its six-check inspection assertion remains. Non-admin inspection-event expectations were aligned with the already-approved own-activity rule, separately proved by the privacy suite. Forged-write tests now obtain valid CSRF from an actual authenticated form, so they test the intended transition/permission denial rather than missing-token failure.

Billing had reused a room left awaiting inspection by a preceding failed test; its arrival check now owns a fresh guarded synthetic room/extra. The privacy fixture is refreshed before its first-page history assertions, preventing old examples falling outside administrator pagination. No app safeguard was relaxed to satisfy a stale test. A Windows subprocess output-decoding interruption was resolved using UTF-8 for child processes; relevant suites were rerun. Failed synthetic attempts and their histories were retained.

PHP lint, JavaScript/Python syntax and Git whitespace checks passed. Full scenario labels and exact private output logs support the table; assertions are not a coverage percentage or hosted certification.

## Recovery and manual review

No database recovery required. Revert only this coordinated presentation slice if needed, keeping existing write services, confirmation/security fixes, source associations and all later events. Coordinate the shared preparation partial with its callers. Never restore an old database to undo navigation.

Refresh Check-In & Occupancy. Open Show Details and confirm the parent is Guest Stay Account. Open an action and confirm its entry link is not repeated while submit/cancel remain. Verify the single collection entry, Room Board preparation filters/history/owner review, and read-only original cleaning's source return. For reusable synthetic checks, prepare the existing housekeeping-merge and departure fixture prerequisites, then occupancy_navigation_seed.php; never point fixtures at the ordinary application database. See docs/OCCUPANCY_WORKSPACE.md for the user-facing map.
