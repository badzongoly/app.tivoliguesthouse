# Test strategy and release evidence

Tests must establish business behaviour and preservation of evidence. The supplied source archive contains no test harness, Composer manifest or CI configuration; PHP CLI is unavailable in this review environment. No PHP lint, application execution, database import or application test below was run. Static source/DDL review and offline aggregate parsing were performed; see [the review](CODEBASE_REVIEW.md).

## Safe test environment

- Use synthetic fixtures with no real guest names/contacts, staff credentials, merchant secrets, or live transactions. Preserve realistic relationships, zero/null/missing legacy fields, long stays, historical statuses, and exact amounts.
- Before any write/reset, the harness must resolve and verify an explicitly allowlisted isolated server/instance, database, and low-privilege test account. A name containing `test` or `APP_ENV=testing` is not enough. If a target is missing, ambiguous, shared with production, or production credentials are loaded, abort before connecting/writing.
- Isolate CLI jobs/imports/queues too. Disable real email, WhatsApp, MoMo and other external side effects; use fakes or documented sandboxes. Tests must not run an enabled provider payment/refund accidentally.
- Reset only a guarded disposable synthetic database. Never run a generic reset/migrate-fresh/seed operation against live data or a protected reconciliation snapshot.
- Start from the export's MySQL `8.0.46-37`, InnoDB, mostly `utf8mb3` plus stock `utf8mb4_0900_ai_ci`, and code's UTC configuration; confirm live parity, SQL modes/isolation and extensions. Do not substitute MariaDB or SQLite as proof of compatibility. Mocked PDO cannot establish locking, constraints, exact mapping or DDL safety.
- Freeze/inject time for repeatability. Run concurrency tests using independent connections/processes and deterministic barriers. Two sequential calls in one test are not a race test.

## Establish a baseline before replacement

1. Inventory existing tests, test scripts, CI, framework/tool versions, and safe configuration. Discover supported PHP syntax and extensions.
2. On synthetic fixtures, record procedural inputs, HTTP responses/types/redirects, session effects, writes, receipts, exports, and report definitions for the affected slice.
3. Add characterisation/contract tests before extraction. Record unsafe/incorrect legacy behaviour as a defect with the desired policy; do not silently fix it under a structural refactor.
4. If no harness exists, adopt a compatible minimal one and prove it fails on a deliberate defect in the test environment. The first financial rewrite must not precede a usable safety net.

## Layers and purpose

| Layer | What to prove |
| --- | --- |
| Unit | Exact money/rounding, pure rates/date rules, transitions, permission decisions, request fingerprints |
| Integration | Real PDO mapping, constraints, transaction rollback, durable audit/idempotency, row locking, concurrency |
| Contract | Unchanged procedural URLs, request/response types, includes/session assumptions, receipts, export columns, report totals |
| Workflow | Reception journey: booking → check-in → payment → receipt → checkout; custody/reconciliation when added |
| Migration/recovery | Old→new schema/data preservation, resume/partial failure, old/new overlap, backup restore, post-release event preservation |
| Security | Direct endpoint denial, tampering, injection, CSRF/session boundaries, role/resource access, sensitive-data exposure |

## Required risk-based scenario matrix

Apply rows relevant to the change. Mark each executed/passed, failed, blocked, or not applicable with a reason. Financial mutations require their normal, denied, invalid, duplicate, concurrent, and rollback cases; a read-only extraction requires exact mapping/report/contract evidence.

| ID | Scenario | Required assertion |
| --- | --- | --- |
| T01 | Old and extracted read on identical data | Same public types/status/output and exact money; no writes |
| T02 | Normal booking/check-in/payment/checkout | Correct room state, stable booking links, one intended event/receipt |
| T03 | Two simultaneous bookings or extensions for one room | No prohibited overlap; every legacy/new writer follows the chosen locking rule |
| T04 | Single/multiple/partial payments and overpayment | Correct exact allocation/balance; credit handled by approved rule |
| T05 | Double-click, refresh, retry, process restart | Same operation posts once and returns the original receipt/outcome |
| T06 | Same idempotency key with different significant input | Rejected; no additional event or changed original |
| T07 | Database failure after event, before audit/commit | Entire business mutation rolls back; no orphan or false success |
| T08 | Connection lost during/after commit | Resolve from durable operation identity; no blind repost |
| T09 | Audit failure vs external alert/print failure | Required audit failure rolls back; external delivery failure preserves one committed payment and permits reprint/retry |
| T10 | Refund, charge credit, paid cancellation | Original retained; approval/reason linked; charge and cash effects distinguished |
| T11 | Concurrent partial refunds/reversals | No excess correction/refund; bounded totals checked atomically |
| T12 | Client changes amount, price, actor, role, method, approval | Server rejects unauthorised values; no write; attempt attributable |
| T13 | Unauthenticated/wrong-role/wrong-resource direct PHP/AJAX request | Server denies every path; no hidden legacy bypass |
| T14 | Missing/invalid CSRF; state-changing GET | Denied with no mutation; legitimate migrated caller still works |
| T15 | SQL metacharacters, dynamic sorting/IN lists, empty results | Bound values and allowlists hold; safe empty behaviour; contract preserved |
| T16 | PDO conversion mapping | Decimal strings, null/false, numeric types, fetch keys, affected rows and IDs match intended legacy contract |
| T17 | Money boundary values and repeated sums | Exact results; approved rounding stage/mode; no float drift or overflow |
| T18 | Rate change, backdating, midnight/overnight stay | Prior financial records unchanged; approved time/night rules applied only where intended |
| T19 | Cash count, expense paid, owner transfer, shift handover | Correct physical-cash equation; transfer not revenue/expense; one custodian; variance retained |
| T20 | Concurrent close/payment/handover; attempt to edit closed count | Consistent session state; no lost funds/double custody; denied edit preserved in evidence |
| T21 | MoMo duplicate reference/import and mismatched merchant/amount/status | No double posting; scope correct; unverified claim not presented as independently paid |
| T22 | Merchant reversal/fee/settlement | Guest receipt preserved; reversal/fee explicit; settlement not another sale/receipt |
| T23 | Historical cancelled/voided/refunded/inactive/extras records | Included in required history/reconciliation; no filter silently erases evidence |
| T24 | Deactivate room/user; guest operational changes | Historic identity, amounts, receipts and links survive; no cascade loss |
| T25 | Daily/monthly/custom-range report and CSV/receipt | Approved revenue/receipt definitions, exact totals, currency/date rules, columns and receipt identity preserved |
| T26 | XSS/CSV formula/log injection and sensitive failures | Correct contextual output handling; no secrets/guest-data leakage; logs remain parseable |
| T27 | Migration retry/resume/partial failure and legacy duplicate data | Originals unchanged; bounded idempotent progress; no fabricated cleanup |
| T28 | Old code/new schema; new code during rollout; rollback after new events | Compatible supported paths; no new transaction lost/duplicated/hidden on fallback |
| T29 | Isolated backup restore and read-only reconciliation | Restored IDs/rows/links/totals correct; new work after snapshot accounted for; measured recovery time |
| T30 | Optional AI/notification/provider unavailable | Core booking/payment remains usable; no autonomous financial action or leaked data |

If taxes, multiple currencies, deposits, split tender, or new integrations are introduced, add explicit scenarios; their semantics are not assumed in this pack.

## Source-specific regression fixtures

These cases turn observed hazards into acceptance evidence. Existing behaviour is recorded for comparison; known unsafe/incorrect behaviour must fail the intended regression before its scoped fix. Expected accounting-policy outcomes require owner agreement. Use only synthetic rows.

| ID / related matrix | Concrete fixture/trigger | Required result or decision |
| --- | --- | --- |
| S01 / T18,T25 | Check in 2026-10-01 13:00 UTC; test checkout next day 12:00:00, 12:00:01, 12:01:00, and later noons across HTML/modal/CSV. | Record current helper vs CSV difference; obtain approved billable nights and fix every affected caller. Occupancy remains a separate calendar-night measure. |
| S02 / T02,T28 | Revisit/pay/recheck-out a completed stay after time advances; same room now has a different active booking. | Stored checkout/rate/bill evidence and current occupant's room state cannot be changed by a stale request. Verify terminal-state/idempotency behaviour. |
| S03 / T03 | Two independent check-in/recovery requests for the same vacant room; request an occupied/dirty/maintenance room. | Enforce the approved room-state policy and one authoritative active stay; lock/recheck in all writers rather than only hide the UI button. |
| S04 / T07,T09 | Fail booking, optional deposit, room-state or required audit write individually. | Whole new workflow rolls back; no booking/payment/room mismatch. Preserve existing working transaction coverage where already present. |
| S05 / T12,T17 | Post negative/free/unapproved extra unit price, fractional quantities, insufficient stock and concurrent extra sales. | Server applies approved pricing/override/stock policy; no silent oversell hidden by `GREATEST(0, ...)`; exact quantities and amounts. |
| S06 / T07,T24 | Insert a sale, run an intervening UPDATE, insert its movement, then inject a failure. Repeat via recovery and stock-in. | Correct captured sale ID/extra/actor persists atomically; rollback leaves no partial movement. A zero ref cannot pass just because the FK is absent. |
| S07 / T18,T27 | Null booking rate, then change present room rate; another fixture has a valid stored snapshot. | Preserve snapshot and unknown historical evidence. No automatic tariff backfill or silent change to a reconstructed historical charge. |
| S08 / T05,T18,T27 | Repeat historical recovery submission with backdated payment or extra; fail midway; recover an already fulfilled extra. | Actual operator/recording time and effective time remain distinct; no duplicate money/stock deduction; original dates/status edits have traceable provenance. |
| S09 / T10,T14,T24 | Send former GET deletes and forged POST mutations as clerk/admin, then legitimate authorised correction/deactivation. | No financial/master identity evidence is deleted; CSRF and permissions enforced; valid replacement workflow usable. A future booking delete is restricted despite legacy cascades. |
| S10 / T13 | Request reports/payments/expenses exports directly as `clerk`, independent of sidebar; exercise genuine `admin` account and old session fields. | Approved capabilities enforced server-side; no artificial role rename, lockout or bypass after a session/role change. |
| S11 / T25 | Exactly 100/101 recent bookings, 300/301 expenses and 500/501 extra lines; payment/stock pagination ties and empty filters. | Characterise limits and current capped totals/exports. Any complete-export fix is deliberate; no data disappears through an unnoticed query or pagination change. |
| S12 / T25 | Rooms with differing `Service` name/type, overlapping/ongoing/cancelled stays, date-clipped ranges. | Define rentable-room set and per-measure deduplication; do not merge dashboard/report occupancy blindly or hardcode seven/nine rentable rooms. |
| S13 / T12,T24 | Deactivate a user referenced by expense/stock; try to infer a payment actor from shifts. | Original actor identity survives; missing historical payment actor stays unknown. No guessed attribution or row deletion. |
| S14 / T16,T28 | UTC DATE/DATETIME/TIMESTAMP, utf8mb3/utf8mb4 text, exact decimals, null fields, and existing cash/momo/card methods across mysqli/PDO. | Preserve intended date/encoding/fetch/type contracts and method values; reject unauthorised new values. Header's PHP exporter version is not treated as a proven runtime floor. |

No source-specific regression has been executed. The snapshot's aggregate checks are a baseline for investigation, not reusable real-guest fixtures or an all-history financial certification.

## Gates

- **Every changed PHP path:** supported-runtime syntax check; focused tests; relevant existing static/format/security checks; diff review. Existing unrelated baseline failures must be documented and assessed, never hidden.
- **Behaviour extraction:** old/new contracts and stored outputs compare on fixed fixtures. A protected snapshot may be used for authorised read-only migration comparisons, never as ordinary public test data.
- **Money, authentication, permissions, migrations:** all applicable matrix scenarios, independent review, exact financial reconciliation, and recovery evidence. Core ledger/authority/cutover changes need explicit business decision and additional review.
- **Release:** critical booking/payment/report workflows pass; no unexplained financial differences, deletion/concealment, unauthorised access, duplicates, partial commits, or unresolved recovery gap. Do not replace these gates with a coverage percentage.
- **New financial rules:** exercise every material branch and boundary, including denial/failure and applicable races. When fixing a defect, first show a failing regression test, then the fix. Mutation/fault injection is useful for proving rollback/negative assertions when practical.
- **Migration:** assert protected per-row identity/fields plus grouped exact totals. Counts/checksums/sums alone can miss compensating corruption. Use stable canonical comparison over the same consistent snapshot and explicit expected new fields/rows.

## Commands and evidence

There is no supplied command behind `composer test` or `vendor/bin/phpunit`. Establish a compatible minimal harness on an isolated environment; record exact commands only after execution. Once a suitable PHP CLI is present, `php -n -l payments.php` is a syntax-only candidate, with no source execution or connection. `php payments.php` is unsafe as a discovery command because it includes the credential-bearing connector. Lint does not execute a workflow, and `git diff --check` does not test financial correctness.

For every change record, capture revision, command, PHP/DB version, isolated environment identity (without secrets), fixture/snapshot reference, timestamp, result, and redacted evidence path. Record skipped checks as blocked/not applicable with the impact and required next action.

The documentation pack itself is checked for relative links, Markdown structure, required safeguards, packaging, and secrets. Those checks do not establish application test readiness.

