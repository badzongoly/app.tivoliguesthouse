# Incremental architecture and PDO guide

**Proposed target, grounded in the supplied source/schema.** See [legacy contracts](LEGACY_CONTRACTS.md), [source findings](CODEBASE_REVIEW.md), and [decision 0001](decisions/0001-incremental-refactoring.md). Keep the existing application and data authoritative while extracting useful boundaries.

## Actual starting architecture

There are 22 PHP files, inline page/controller/template logic, shared `partials/`, a global `$mysqli` created by `db.php`, and mysqli prepared/direct queries. No PDO connection, Composer manifest, test/CI setup, or migrations were supplied. `stock.php` stock-in and the recovery extra path already use mysqli transactions; preserve these protections.

`db.php` also sets connection charset `utf8mb4`, MySQL-session UTC and PHP UTC. Most tables are `utf8mb3`, with `stock_movements` on `utf8mb4_0900_ai_ci`. Port connection/error/fetch/time side effects deliberately; do not overwrite the global connector or convert the schema charset as incidental cleanup. Snapshot header reports MySQL 8.0; verify actual host extensions and application PHP before choosing the compatibility floor.

The current data model reconstructs room charges from booking dates/rate or a mutable-room-rate fallback. It has no posted-charge/audit/refund/receipt ledger. Adding those controls needs an explicit design/cutover, not simply a `Payment` class around the existing inserts.

## One slice at a time

Keep the current procedural URL as a compatibility boundary. It parses the request, obtains the authenticated actor, validates CSRF/permissions, calls a service or read repository, and renders the existing response. Move existing rules behind that boundary without changing their meaning accidentally.

| Boundary | Responsibility | Must avoid |
| --- | --- | --- |
| Procedural endpoint/adapter | HTTP/session mapping, authentication, validation, status/redirect/response contract | SQL and duplicated financial calculations |
| Application service | One business action, authorisation, transition, transaction, idempotency, audit requirement | Hidden globals, HTML output, independent database connections |
| Domain value/rule | Exact money, permitted transition, tested calculation, effective-date/rate policy | Reading `$_POST`/session or accessing the DB |
| Repository | Prepared SQL, explicit mapping, targeted locking/constraints | Committing transactions owned by the service; silently fixing data |
| Infrastructure | Connection creation, clock, audit/outbox, external provider adapter | Hardcoded production secrets; business policy in configuration plumbing |
| Template/presenter | Safe output and compatible display | Mutations or authoritative money arithmetic in JavaScript |

For a simple read query, an endpoint plus repository may be enough. Add a service when there is an actual workflow, policy, or transaction to coordinate. Prefer composition; avoid a generic `BaseModel`, service locator, giant utility class, or inheritance hierarchy without a clear need.

## Suggested layout, only if it fits discovery

| Proposed path | Use |
| --- | --- |
| `src/Infrastructure/Database/` | PDO connection creation/configuration |
| `src/Repository/` | Room/booking/payment query and persistence adapters |
| `src/Service/` | Booking/payment/cash/reconciliation actions |
| `src/Domain/` | Money, rates, transitions, and other pure rules |
| `src/Security/` | Actor, permissions, CSRF/session helpers |
| `tests/Unit/`, `tests/Integration/`, `tests/Contract/` | Behaviour, real database, and legacy boundary tests |
| Existing procedural scripts/templates | Stable callers delegating to extracted code |

These paths are not a migration instruction. Do not move working files, change the web root, introduce a framework, or require an unavailable Composer runtime just to match this table. Adopt a namespace/autoloading convention compatible with the installed PHP and deploy process. Verify new files are not directly executable via the web.

## PDO conversion checklist

1. Map the entire operation and all callers before converting it. Start from the observed mysqli/UTC/session contracts; verify SQL mode, error behaviour, fetch/return shapes, ID handling, and host assumptions. `payments.php` is a concrete read-only candidate after permission/CSRF containment; its 25-row pagination and date/order contract must remain deliberate.
2. Create one PDO connection at the composition boundary using the approved secret mechanism. Pass it into repositories/services; do not create a new connection inside each method.
3. Set `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION` explicitly. Set the intended fetch mode explicitly, adapting the legacy caller if it expects numeric keys or objects. Avoid a global default change that breaks unrelated callers.
4. For the MySQL driver, prefer `PDO::ATTR_EMULATE_PREPARES = false` after testing installed driver/statement compatibility. Do not rely on reused named placeholders; use a distinct placeholder for each bound occurrence. Bind integer limit/offset values appropriately when needed.
5. Bind untrusted values. SQL table/column names, direction, and expressions require a closed server-side allowlist. Build a variable `IN` list with one placeholder per validated value; never bind a whole comma-separated list as one value. Handle an empty list explicitly.
6. Prefer `utf8mb4` for new compatible connections, but first prove it preserves existing stored text and comparisons. Connection-encoding change is not permission for a bulk data conversion or collation/schema change.
7. Use explicit column lists and stable aliases where return contracts matter. Check `null`, `false`, empty result, decimal strings, numeric strings, affected-row semantics, and `lastInsertId` behaviour. Do not cast a decimal value to float for convenience.
8. Remove the old writer for the converted operation only after callers and compatibility tests are complete. Independent old/new writes are not a safe shadow test.

Include every same-entity writer: `checkin.php`, `checkout.php`, `admin_recovery.php`, catalog/stock/expense handlers and any hosting jobs not present in the archive. Capture inserted sale identity before later queries; the current check-in extra flow reads connection insert ID after a stock update, while recovery already captures its sale statement ID immediately. A shared repository must preserve and improve the right behaviour in each path.

Prepared statements address injection; they do not implement authorisation, correct accounting, race protection, or validation. Parameter markers cannot represent SQL identifiers. See [PHP PDO prepare](https://www.php.net/manual/en/pdo.prepare.php).

## Transaction boundary

A financial service owns begin/commit/rollback on one shared connection. Validate actor/input first, then within a short transaction recheck mutable conditions under the chosen lock/constraint strategy, claim idempotency, write the financial event and compatible projections, insert the required audit event and any local outbox record, and commit. Return success only once the commit is known.

- All involved tables must use a verified transactional engine. A successful `beginTransaction()` alone does not prove rollback protects every write.
- A legacy `mysqli` write is outside a PDO transaction. Convert all writes in the operation or keep the whole old operation until it can be moved safely. Do not promise atomicity across separate connections.
- Repositories never commit behind the service's back. Avoid nested transaction assumptions; explicitly define any savepoint policy if truly needed.
- A commit/network error can leave the outcome uncertain. Recover using the durable operation key/event lookup; do not blindly retry and post twice.
- Use deterministic lock order and bounded retries for proven transient failures. Idempotency covers the complete command; retry must not resend external money movement.
- Do not make a slow external provider call while holding booking/cash locks. Persist a durable intent/outbox and reconcile the provider result with its own idempotency support. External payment success and local DB commit are not one atomic transaction.
- Receipt printing, email, WhatsApp, analytics, and alerts occur after commit or through an outbox. A print failure must offer reprint of the same receipt, not another financial event.

See [PHP transactions](https://www.php.net/manual/en/pdo.transactions.php) for transactional-engine and implicit-commit limitations. DDL belongs in the [migration process](DATABASE_MIGRATIONS.md), never a request transaction.

## Safe concurrency and coexistence

- Booking availability must be rechecked under a lock strategy that protects even when no overlapping booking row exists. For example, serialise writers on an existing room row, recheck overlap, and create the booking on the same connection. Every booking writer must participate; a lock in one new service cannot protect against a bypassing legacy script.
- Use row/version constraints or locks for balance-sensitive operations. Refund limits and closed cash-session state must be rechecked atomically, not only before the transaction.
- Scope unique keys to the real business/provider contract. Prove historical duplicates and null handling before introducing a constraint. `MAX(id) + 1` is not safe receipt numbering.
- For read-only shadow comparison, compare deterministic outputs on the same snapshot. Avoid load or sensitive-data leakage. For write cutover, use a single authoritative writer and a coherent projection/outbox; document in-flight requests, cron/imports, flags, and fallback compatibility.

## Compatibility contract to record for each slice

Capture URL/method, request fields/types, auth/session assumptions, response/status/redirect shape, include/global side effects, error cases, transaction effects, receipt identity/layout, export columns, report filters/totals, and date/money presentation. Session locking, output-before-header errors, and endpoint clients can break during apparently harmless extraction.

Security fixes may intentionally reject previously accepted forged prices or unsafe requests. Record those as behaviour changes with negative tests and a caller migration, rather than quietly labelling them a refactor.

