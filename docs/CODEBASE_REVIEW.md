# Source-based review — 2026-10-28

This is a static review of the owner's supplied snapshots to guide documentation and safe future work. It is not a completed penetration test, live financial audit, or claim that a defect was exploited. No PHP application execution, SQL import, live request, payment, schema change, or data correction was performed.

## Evidence and limits

| Input/check | Observed result |
| --- | --- |
| `app.tivoliguesthouse.com.zip` | 26 file entries: 22 PHP files, one image, `.htaccess`, and two error logs. Logs were not read or copied into deliverables. |
| Code SHA-256 | `604532067af34ec7e7abb9199ed4962eb378ca2bc5f96e263ddd436c4f0dec72` |
| Supplied SQL export | Nine InnoDB tables; dump header reports MySQL `8.0.46-37`, export-tool PHP `8.4.27`, and generation time 2026-10-28 04:58 AM. Header timezone/application-runtime equivalence unverified. |
| SQL SHA-256 | `081884975d3394870bc843d28dcd57912c93aa1d879e68462c1c38823e331cab` |
| Inspection method | Read source and DDL; parsed literal INSERT tuples offline for aggregate structural checks. SQL was never executed and no guest rows, hashes, credentials, or financial amounts are reproduced here. |
| Runtime/tests | No PHP CLI available in this review environment; no Composer manifest, test suite, CI configuration, or migrations in the supplied archive. Hosting may contain additional controls/files. |
| Deployment parity | Owner identifies the attachments as current. Exact deployed revision, complete server configuration, DB grants, backup quality, and live state were not verified. |

Source references below are paths relative to the attached application root, with line numbers in that snapshot. The revised documentation archive intentionally contains no application files or database dump.

## Prioritised findings

| ID / priority | Source evidence and trigger | Consequence | Required next action |
| --- | --- | --- | --- |
| R01 / High | `expenses.php:20` updates amount/date/category/method in place; `expenses.php:59` deletes by GET. Only login is checked. | A logged-in caller can change/remove expense evidence through the supplied handler, without a recorded correction trail or CSRF control. | Prepare a narrowly tested containment/change to server permissions and destructive GET handling; replace financial editing/deletion with an authorised linked-correction workflow. Preserve current rows first. |
| R02 / High | `partials/sidebar.php:16` hides reports/payments/expenses links from clerks, but `reports.php:2`, `payments.php:2`, and `expenses.php:2` only require login. `users.php:6` and `admin_recovery.php:5` do enforce admin server-side. | Menu visibility does not enforce the apparent admin boundary. Direct requests can reach sensitive handlers/exports. | Agree capability mapping and enforce it at every entry point; add direct `clerk` request tests. Preserve existing `admin`/`clerk` identifiers during rollout. |
| R03 / High | No CSRF token generation/validation found in the supplied PHP. GET deletes exist in `expenses.php:59`, `extras.php:27`, `rooms.php:24`, and `users.php:56`; logout also uses GET. | Forged requests can trigger state changes if the user's session is accepted; admin-only checks alone do not prevent CSRF. | Migrate mutations to authorised CSRF-protected POST workflows; include legacy links/forms and stale-session tests. Do not claim server-level mitigations were tested. |
| R04 / High | `checkout.php:48` always sets bill end to now; `checkout.php:95` has no terminal-status guard before overwriting checkout and setting the room vacant. | Revisiting a completed booking can change its computed bill; repeat checkout can change historical dates and potentially the room state of a later stay. | Specify and test completed-stay immutability, active-booking ownership, idempotent checkout and concurrent transitions; treat the fix as an intentional behaviour correction. |
| R05 / High | `checkin.php:83` inserts an extra, `checkin.php:94` updates stock, then `checkin.php:103` reads the connection insert ID. The stock movement uses that late value. | Movement provenance can point to zero rather than its sale. Snapshot profiling finds 2,664 `booking_extra` movements with `ref_id=0`. | Capture the sale statement's ID immediately; atomically write sale/stock/movement; test linkage and rollback. Investigate historical rows without guessing matches or mass-updating them. |
| R06 / High | `checkin.php:41` creates a booking, optional payment and room state as separate writes, with no locked vacancy recheck; `checkin.php:75` writes sale/stock/movement without a transaction. `checkout.php:81` inserts payment without durable retry identity. | Races, partial failure and retries can produce inconsistent occupancy, unbalanced stock, or duplicate receipts/payments. | Design one transaction/connection and durable operation identity for each complete workflow; cover all legacy/recovery writers and test concurrent failure paths. |
| R07 / High | `checkin.php:78` accepts posted extra unit price; stock deduction clamps at zero in `checkin.php:90` and `admin_recovery.php:172`. | Forged/negative/unapproved prices and overselling can be hidden by clamped stock; no negative stock is not proof of correct inventory. | Server-authorise catalog/override pricing and stock policy; use exact quantities and atomic stock checks. Preserve recorded historical prices and stock evidence. |
| R08 / High | `admin_recovery.php:64` creates historical stays, `:102` rewrites stay dates/status, `:124` adds backdated payments, and `:143` adds extras/deducts stock. Admin gate exists; no durable before/after audit, CSRF, or command idempotency found. | A legitimate recovery tool can silently change historical financial interpretation or post a recovered payment/stock deduction twice. | Keep a controlled recovery route; require actual operator/recording time, source evidence, reason, approval where required, effective time, duplicate checks and stock policy. Do not delete the tool without a replacement workflow. |
| R09 / High | SQL foreign keys `payments_ibfk_1` and `booking_extras_ibfk_1` use `ON DELETE CASCADE` from bookings. Master-data DELETE handlers and user deletion also exist. | A booking deletion through an external/admin/future writer would erase dependent history; deleting users destroys attribution in non-FK links. No booking DELETE handler was found in this PHP snapshot. | Inventory grants/external writers; plan a separately reviewed restrictive-FK transition and identity deactivation. Do not change constraints live or disable them to force cleanup. |
| R10 / High | `bookings.php:47`, `checkin.php:122`, and `checkout.php:43` fall back to current room rate when booking rate is null. Snapshot has 11 null-rate bookings. | Changing a room's present rate can change reconstructed charges for old stays. Posted room-charge snapshots are not stored in this schema. | Treat null rates as explicit legacy unknowns; obtain evidence before any backfill. Design future charge/rate snapshots with an approved cutover; never fill old rates with today's price. |
| R11 / Medium–High | Booking HTML uses noon-cutoff nights at `bookings.php:277`; booking CSV uses whole elapsed days at `bookings.php:75`. Checkout/check-in duplicate the noon helper. | The same stay can show different room totals in the screen and export. Changing one helper cannot fix all consumers safely. | Characterise each path; obtain one approved billing policy, keep occupancy separate, and correct all affected callers through explicit acceptance examples. |
| R12 / Medium–High | `db.php:3` embeds connection credentials; `db.php:4`, `checkout.php:89`, and some account/recovery errors expose detailed database/exception messages. Archive includes error logs; `.htaccess` only redirects to HTTPS. | Credentials/logs/debug details need controlled access. Supplied `.htaccess` does not itself deny sensitive files; live exposure and upstream rules were not tested. | Externalise secrets safely, redact client errors, restrict log/docs/dump delivery, and plan rotation if disclosure warrants it. No credential values or log contents belong in documentation. |
| R13 / Medium–High | Payments/bookings have no recording-user column, durable command key, dedicated MoMo reference/verification field, audit table, correction ledger, or receipt entity in the supplied schema. | Individual logins alone cannot retrospectively prove who recorded each payment or distinguish staff-entered MoMo from merchant confirmation. | Add forward-looking attribution/verification/idempotency/audit controls with a reconciled cutover. Leave historical unknown actors unknown; do not infer them from shift patterns. |
| R14 / Medium | `reports.php:34` and `dashboard.php:12` label payment sums as revenue. Booking/export, expenses and extras lists have caps; occupancy code differs in room filtering and overlap handling. | Renaming/recalculating figures or extracting a shared report helper can change meaning, coverage and comparability. | Capture the exact legacy measure/filter/cap contract; approve any semantic correction separately. See [legacy contracts](LEGACY_CONTRACTS.md). |

Priorities reflect the supplied code, not verified live exploitability. No financial-loss amount or staff misconduct is inferred.

## Snapshot structural profile — aggregate only

Counts describe the uploaded export, not the live database after it was taken.

| Table | Parsed rows |
| --- | ---: |
| `bookings` | 1,392 |
| `booking_extras` | 3,447 |
| `expenses` | 146 |
| `expense_categories` | 11 |
| `extras` | 20 |
| `payments` | 3,532 |
| `rooms` | 9 |
| `stock_movements` | 2,767 |
| `users` | 6 |

Offline checks found 11 null booking rates and 2,664 zero references among 2,680 movements labelled `booking_extra`. No missing booking links were found for parsed payments/extras, no missing room link for bookings, and no multiple-active-booking room in this snapshot. The latter results do not prove concurrency safety or complete accounting accuracy. Nine room rows do not imply nine rentable rooms; `Service` treatment differs between report paths.

Do not repair suspect references by row order, quantity, timestamps alone, or nearest sale ID. Retain the original, investigate independent evidence, and use an approved mapping/annotation/correction with provenance. The first stock movement history is not assumed to be the beginning of stock operations; opening inventory needs evidence.

## Revised implementation order

1. Build guarded synthetic regression fixtures and verify a recovery/restore path.
2. Prepare focused fixes for destructive/unauthorised financial paths and CSRF, retaining a legitimate operational correction/recovery workflow.
3. Resolve billing-policy ambiguities and add tests for terminal checkout, CSV differences, stock references and null rates before extraction.
4. Extract a small read-only path; then move complete write workflows to PDO with atomicity, audit, idempotency and concurrency controls.
5. Add custody/MoMo/approval/dashboard features in the order agreed with the owner.

Every live release still needs the concrete evidence and authority in [operations](OPERATIONS.md). This review changes the plan; it does not authorise production work.

PHP reference: [mysqli connection insert ID](https://www.php.net/manual/en/mysqli.insert-id.php) and [statement insert ID](https://www.php.net/manual/en/mysqli-stmt.insert-id.php). The connection value must be read at the correct operation boundary; the statement-specific result avoids an intervening statement changing that association.

