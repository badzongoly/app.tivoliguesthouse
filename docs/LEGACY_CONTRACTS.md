# Legacy contracts and data map

Observed in the supplied 2026-10-28 snapshots. These are the baseline to test, not an endorsement of unsafe behaviour. See [review findings](CODEBASE_REVIEW.md) and [testing](TESTING.md). Paths below are application-relative references, not files shipped in this documentation pack.

## Application boundary

The application is procedural PHP with inline HTML/CSS/JavaScript and shared `partials/`. `db.php` creates the global `$mysqli`, configures connection charset `utf8mb4`, MySQL session `+00:00` and PHP `UTC`. Preserve those side effects until an explicit compatible replacement is tested. The database tables themselves predominantly use `utf8mb3`; the stock movement table uses `utf8mb4_0900_ai_ci`. A PDO connection change must not silently change stored encoding, collation, timezone, or fetch types.

`auth/require_login.php` starts a session, requires `$_SESSION['user']`, and exposes `$user`. The session user shape is `id`, `email`, `name`, `role`; real role identifiers are `admin` and `clerk`. `auth/login.php` uses prepared mysqli lookup and `password_verify`; `users.php` writes bcrypt hashes. There is no need to invent a migration from plaintext/MD5. `get_result()` is used throughout; verify the actual mysqli/mysqlnd support and future `pdo_mysql` availability with the host.

`index.php` redirects to `auth/login.php` and exits before its marketing HTML. Do not activate that dormant page while refactoring the POS. No routing framework, Composer files, test harness, CI files, or migration scripts are supplied.

## Endpoint and writer inventory

| Endpoint | Existing inputs/actions and data effects | Preserve or intentionally remediate |
| --- | --- | --- |
| `bookings.php` | Room cards/active booking lookup; recent non-active stays. GET `room_id`, `status`, `start`, `end`, `export=csv`; last 100 selected stays. | Filter meaning, labels, room links, HTML/modal/CSV types and ordering; known night-count discrepancy needs an explicit fix. |
| `checkin.php` | GET `room_id`; POST `action=checkin` with guest fields, optional datetime, `amount_paid`, `method`. Inserts booking/rate snapshot, optional payment, room occupied. POST `action=add_extra` inserts extra, deducts stock, records movement. | Current URLs/fields/session; validate current vacancy, price, status and idempotency server-side. Keep complete workflow on one transaction. |
| `checkout.php` | GET `booking_id`; POST `action=pay` adds payment; `action=confirm_checkout` updates booking timestamp/status and room vacancy. | Preserve ordinary settlement flow; fix recomputation/repeat checkout and validate room ownership/current booking atomically. |
| `payments.php` | Read-only payment history; GET `start`, `end`, `page`; 25 rows per page; links back to checkout. | Good candidate for a first PDO read extraction after priority containment; preserve date/order/pagination/decimal mapping and role restriction. |
| `expenses.php` | POST `save_expense`, `add_category`; GET edit/delete/filter/export; edits original amount/date/method; last 300 matches feed displayed total/export. | Replace destructive/unauthorised paths deliberately; creation actor exists but updater/deleter audit does not. Preserve legitimate entry and old evidence. |
| `extras.php` | POST `save`; GET edit/delete; catalog price/active flag and initial stock. | Preserve identities/snapshots; deactivate referenced items; authorise price changes. |
| `stock.php` | POST `stock_in`; updates quantity and inserts movement inside a mysqli transaction; GET `extra_id`, `page`; 15 movements per page. | Preserve existing atomic stock-in protection when porting; add missing authorisation/CSRF/idempotency as scoped changes. |
| `extra_sales.php` | Read-only extra lines by `created_at`, GET date/extra filters and CSV; last 500 matches feed totals/export. | Existing module, not a proposed feature. Preserve quantities, snapshots, references, limits, dates and export meaning until explicitly changed. |
| `rooms.php` | POST create/update names/type/rate; GET edit/delete. | Keep stable room identity/rate snapshots; no assumption every room row is rentable. Unsafe deletion/role boundaries require explicit remediation. |
| `users.php` | Admin gate, create/update, optional password change, role assignment, GET delete except self. | Preserve `admin`/`clerk`, bcrypt and session compatibility; add controlled deactivation/audit/session invalidation without breaking historical links. |
| `admin_recovery.php` | Admin gate; `create_booking`, `update_stay`, `add_payment`, `add_extra`; user-specified historical dates. Extra/stock/movement block already uses a transaction. | Treat as a separate high-risk writer. Preserve a controlled recovery path; add provenance, actual recording time, CSRF, approval/retry controls and a policy for historical stock effects. |
| `reports.php` | Payment sums by day/ISO week/month/method; occupancy; expense categories/months; CSV variants and independent pagination. | Admin menu does not currently enforce endpoint role. Separate receipts from earned charges in any new reporting model without silently rewriting the legacy measure. |
| `dashboard.php` | Payment-based charts/today total; current room statuses and rolling occupancy. | Read-only contract includes current query quirks; report fixes need separate acceptance examples. |

`auth/logout.php` destroys the session and redirects. Mutation/session changes must update all actual forms/links, including direct requests and old tabs. No background jobs/importers/provider callbacks were supplied; hosting/external writers remain to be inventoried.

## Schema and relationships

All nine supplied tables use InnoDB. IDs are integer primary keys with auto increment; new application code must preserve them and their links.

| Table | Important stored facts | Relationships and limits |
| --- | --- | --- |
| `bookings` | Room, nullable `rate_per_night` DECIMAL(12,2), guest/contact fields, check-in/out DATETIME, status | FK room; statuses `active`, `checked_out`, `cancelled`; no creator/recorded-at/audit/posted room-charge fields. |
| `payments` | Booking, `method`, DECIMAL(12,2) amount, note, nullable DATETIME `created_at` | FK booking **deletes cascade**; methods `cash`, `momo`, `card`; no user, durable command/receipt ID, separate merchant reference/verification, refund link or currency column. |
| `booking_extras` | Booking, optional catalog extra, description/price snapshot, DECIMAL(10,2) qty, DECIMAL(12,2) unit price, TIMESTAMP | Booking deletion cascades; catalog deletion sets `extra_id` null; no recording actor. Snapshot description/price remains important. |
| `expenses` | Expense DATE, category, method, DECIMAL(12,2) amount, vendor/reference/description, optional `user_id`, created TIMESTAMP | Category FK; no FK to user; methods include `cash`, `momo`, `card`, `bank`, `other`; no correction/update history. |
| `expense_categories` | Unique name and active flag | Referenced by expenses; preserve category identities. |
| `rooms` | Unique name, type, DECIMAL(12,2) current rate, status, created TIMESTAMP | Statuses `vacant`, `occupied`, `dirty`, `maintenance`; no active/deactivated flag or explicit rentable flag. |
| `extras` | Name, DECIMAL(12,2) price/stock quantity, active flag | Catalog/stock identity; historical extra lines retain snapshots. |
| `stock_movements` | Extra, `in`/`out`/`adjustment`, DECIMAL(12,2) qty/cost, note, ref type/id, optional actor, DATETIME | FK extra; `ref_id` and `created_by` have no FK. Preserve original movement plus investigation/correction provenance. |
| `users` | Unique email, password hash, name, role, created TIMESTAMP | `admin`/`clerk`; no deactivation/version/session-revocation field; deletion can lose application-level attribution. |

There is no supplied audit, refund, ledger, cash-session, merchant-reconciliation or dedicated receipt table. Do not design migrations as though these tables already exist, or claim a booking page's payment sum is an immutable invoice. Money is implicitly displayed as cedi; adding explicit currency must be a reviewed compatible step.

## Billing, occupancy, and report definitions

| Path/measure | Current implementation | Required caution |
| --- | --- | --- |
| Billing helper | Minimum one night; first boundary is noon on the day after check-in; each later crossed noon adds a night. Duplicated in three scripts. | A one-second boundary matters in the code, despite comments referring to minutes. Characterise before centralising. |
| Bookings HTML | Uses helper and stored checkout when present; rate snapshot or current-rate fallback. | Historical null snapshots are not permission to backfill today's tariff. |
| Bookings CSV | Uses `max(1, elapsed whole days)` instead of the helper; limited to recent 100 selection. | Known difference from HTML; do not call unifying them a behaviour-neutral extraction. |
| Checkout bill | Uses current time even when `checkout_at` is stored; balance is clamped to zero. | Completed stay and overpayment behaviour need an approved correction; do not silently alter historical payments. |
| Dashboard “revenue” | Sums `payments.amount` by payment date, with today's query using UTC date. | It is a receipts-based measure in this implementation, not evidence of accrual recognition. |
| Reports “revenue” | Payment sums filtered by payment time and current joined booking room/status. | Later booking-state changes can affect status-filtered totals. Preserve/query explicit policy, including cancelled stays with payments. |
| Report occupancy | Calendar-night sets clipped to range, deduplicated per room/date; excludes room **name** `Service`. | Occupancy is not billable nights; it intentionally uses a different measure. |
| Dashboard occupancy | Clipped calendar-day intervals summed by room; excludes room **type** `Service`; overlapping rows can double-count. | Not equivalent to report occupancy. Diagnose/filter policy explicitly; do not assume all nine room rows enter the denominator. |
| Expenses/extras totals | Sum the fetched lists after LIMIT 300 / LIMIT 500. | Caps can truncate totals/exports for broad ranges; test cap boundaries and approve any complete-export correction. |

Synthetic boundary example, derived by reading the code rather than running PHP: check-in 2026-10-01 13:00 UTC and checkout 2026-10-02 12:00:01 UTC yield two nights in the noon helper and one in the CSV formula. Use this as a regression scenario and obtain the owner's intended result. Do not rewrite historical transactions to remove the discrepancy.

## Compatibility versus defects

Preserve public paths, input names, session shape, stored enums/identities, historical values and agreed output types where safe. Deliberately remediate unauthorised access, destructive financial edits, price tampering, incorrect bill boundaries and race conditions through separate tests and a controlled rollout. Preserve evidence of prior behaviour without making it an acceptance requirement for new unsafe code.

