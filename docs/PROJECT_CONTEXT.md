# Project context and evidence

Reviewed against the owner's supplied code/database snapshots on 2026-10-28. Business owner: Eugene Badzongoly. Hosting/runtime ownership and technical reviewers still need explicit confirmation.

## Confirmed in this request

- Tivoli Guesthouse POS is live and running. The code is procedural PHP.
- Refactoring should introduce classes and PDO prepared statements, tighten security, improve accountability, and support new functionality.
- Extreme care is required: preserve historical financial transactions, financial integrity, compatibility where possible, and high testing standards.
- The present task authorises documentation, not implementation, live deployment, financial corrections, or database migration.

## Observed source and schema — current documentation baseline

The owner supplied `app.tivoliguesthouse.com.zip` and the current SQL export. Static review covers the PHP files, schema and aggregate structural profiling without running PHP, executing/importing SQL, using credentials or contacting the live site. Exact evidence and hashes are in [CODEBASE_REVIEW.md](CODEBASE_REVIEW.md); interfaces and data map are in [LEGACY_CONTRACTS.md](LEGACY_CONTRACTS.md).

- 22 PHP files; global mysqli connector with some prepared statements; existing transactions in stock-in and recovery-extra flows. No PDO connector, Composer manifest, tests, CI or migration scripts supplied.
- Actual stored roles `admin`/`clerk`; session fields `id`, `email`, `name`, `role`; bcrypt/password verification already used.
- Code configures PHP/MySQL-session UTC and connection `utf8mb4`; most tables store `utf8mb3`. Nine InnoDB tables; export header MySQL `8.0.46-37`, export-tool PHP `8.4.27`. Application PHP floor/extensions, server controls and live revision parity remain unverified.
- Existing modules include bookings/check-in/out, payments, expenses, rooms/users, extras, extra sales, stock, reports/dashboard and historical recovery. Payment enum already includes `card`; expense enum also includes `bank`/`other`.
- Export has nine room rows, not a verified nine rentable rooms. `Service` exclusions differ by name/type. Do not hardcode an earlier seven-room dashboard assumption.
- High-risk source findings include direct sensitive-endpoint role gaps, GET deletes/in-place expense changes, missing CSRF/audit/idempotency, completed-bill recalculation, stock-sale reference capture and inconsistent billing formulas.
- Snapshot profile identifies 11 null rate snapshots and 2,664 booking-extra stock movements with zero reference. Preserve these unknowns and original records; no speculative repairs.

## Earlier Tivoli discussions — business context, not source inspection

The following was retrieved from earlier conversations. Verify changeable details against the current property/system before implementing them; do not hardcode them.

| Conversation date | Context to preserve | Engineering consequence |
| --- | --- | --- |
| 2025-08-24 | Seven-room dashboard; room bookings/check-in/out; MySQL-backed room details; cash/MoMo; guest/contact details; bill and transaction log. Manager/Reception roles, reports, CSV export, and room management requested. | Current source has `admin`/`clerk` and nine room rows; preserve actual identifiers and confirm rentability instead of hardcoding the earlier request. |
| 2026-09-11 | Prior discussion describes a Kumasi property and a PHP/MariaDB ecosystem; website/POS integration was discussed. | The supplied export now identifies MySQL 8.0. Retain the POS as authority for room IDs/rates/availability; future website integration must not create a second inventory. |
| 2026-10-04 | Accommodation operations; arrivals at any time; cash/MoMo. No ancillary guest services described. Staff shifts and consistent daily/POS reporting were discussed. | New source contains functioning extras/stock/sales paths and history. Preserve them and confirm current business use; the earlier discussion does not authorise removal. |
| 2026-10-12 | POS covers bookings, payments, expenses, and room status. Walk-ins dominate; WhatsApp Business is used. Preventing leakage/fraud is a priority. A modest monthly budget was discussed. | Keep reception fast; prioritise accounting controls over elaborate infrastructure. Confirm current budget before adding recurring services. |
| 2026-10-12 | Farida and Abraham have separate POS logins. Cash stays with staff until requested; MoMo uses a dedicated Tivoli merchant account. Reception/entrance CCTV exists. | Preserve individual attribution. Model cash custody and handovers separately from revenue; reconcile merchant records independently. Do not turn CCTV into a new surveillance integration. |

Older room prices/counts are historical context, not current configuration. Nationality/contact fields are present in bookings; preserve their authorised use without expanding personal-data collection by default. Source facts supersede earlier technical assumptions; operational policy still requires the owner's confirmation where it conflicts with implemented modules.

## Prior recommendations — proposed, not confirmed implementation

Append-only financial/audit events, reversals, role-based permissions, cash sessions/transfers, merchant reconciliation, management exception views, MFA, and read-only AI analysis were suggested previously. This pack refines them into development standards and staged backlog items; it does not assert they exist or approve their rollout.

Separate accounts and CCTV do not establish financial integrity. The system must still prove who recorded an event, whether it was independently verified, who holds funds, and how corrections were authorised. Converting functions into classes alone does not deliver these controls.

## What was verified and what remains unknown

- **Observed:** supplied source/DDL and safe archive inventory; no application test/config manifests present. This is an uploaded snapshot, not a verified Git checkout or host inventory.
- **Executed:** static inspection and literal offline INSERT parsing for aggregate link/rate counts, plus document validation recorded in [delivery state](delivery/DELIVERY_STATE.md). No financial amounts or private rows are published.
- **Observed:** no PHP CLI in this review environment; no application tests could be executed. Earlier PHP/OWASP references were supplemented with insert-ID semantics from the PHP manual.
- **Blocked:** live vulnerability/exposure confirmation, source-to-deployed revision, complete data integrity/financial reconciliation, host grants/extensions/jobs, backup/restore proof, and runtime workflow tests.

## Discovery checklist — record evidence, do not guess

| Unknown | Needed evidence/decision | Gate |
| --- | --- | --- |
| Source and deployed revision | Locate repository/branch and map the uploaded snapshot to live revision, owner and active local changes | Before implementation |
| Runtime and hosting | Confirm actual application PHP/extensions, export-to-live MySQL parity, document root/server controls, cron/jobs, deployment constraints | Before choosing syntax/PDO/tooling |
| Live schema and external writers | Verify inspected keys/types/constraints and live drift, grants, triggers/jobs/imports/manual SQL absent from archive; include recovery writer | Before schema/write-path changes |
| Accounting interpretation | Charges vs receipts, revenue basis, expenses, discounts, refunds, deposits/prepayments, overpayments, paid cancellation, reporting totals | Before changing financial semantics |
| Dates and rates | Actual storage/display timezone, property timezone, business-day cutoff, nights/checkout rules, rounding/taxes, rate snapshots | Before money/time refactors |
| Roles and authority | Current capabilities, approval limits, self-approval policy, owner delegation, exception procedure | Before permission/approval rollout |
| Cash and merchant evidence | Starting custody, handover process, approved cash expenses, merchant statement format/reference scope, verification authority | Before accountability features |
| Recovery | Verified backup age, consistency, offsite access, restore drill, acceptable outage/data loss objectives | Before a high-risk live release |
| Tests and integrations | Actual commands/CI, endpoint/receipt/export consumers, website links, provider APIs if any | Before replacement/cutover |

Code displays cedi; use `GHS` as the proposed explicit domain currency while preserving existing amounts/methods. Existing PHP/MySQL-session timezone is UTC. Validate any property display/business-date policy for `Africa/Accra` without changing stored timestamp meaning. The owner's computer timezone is not the property's accounting timezone; no bulk timestamp conversion belongs in this refactor.

Operational/financial policy belongs to Eugene or his explicitly authorised delegate; technical design belongs to the named maintainer; release review needs a qualified independent reviewer. Unassigned roles remain unassigned until named.

