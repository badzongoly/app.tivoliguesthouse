# Delivery state — safe refactoring foundation

## Initiative

- Name: Tivoli Guesthouse POS modernisation
- Type: documentation and refactoring preparation
- Current deliverable risk: low; static source/schema review and documentation only
- Future application risk: high for payments, identity, permissions, financial reports and migrations; core ledger/cutover changes require heightened independent review
- Accountable business owner: Eugene Badzongoly
- Engineering/release owner and independent reviewer: not assigned here
- Stage: source-grounded documentation revision; application fixes/refactoring not implemented
- Last updated: 2026-10-28

## Objective and scope

Update repository guidance against the supplied code/database snapshots for incremental PHP/class/PDO refactoring, secure/accountable workflows, preserved history, exact accounting tests, compatible transitions, and controlled recovery. New features remain staged candidates; actual extras/stock/recovery modules are preserved. No application code, source attachment, SQL migration, CI configuration, live data, hosting setting, or deployment changed.

## Evidence inspected

| Evidence | Classification and result |
| --- | --- |
| Current request | Confirmed goals and financial/compatibility constraints |
| Prior Tivoli conversations, especially 2026-10-04 and 2026-10-12 | Retrieved business context; earlier proposals distinguished from current implementation |
| Owner's code attachment | Observed 22 PHP files in a 26-file archive; mysqli, existing partial prepared statements/transactions, inline pages/partials and no supplied tests/Composer/CI/migrations |
| Owner's SQL attachment | Inspected nine InnoDB table definitions/FKs; header MySQL 8.0.46-37 and export-tool PHP 8.4.27; no SQL execution/import |
| Offline aggregate structural profile | Parsed literal rows: 11 null booking rates and 2,664 booking-extra stock movements with zero ref; no speculative data repair or private row publication |
| Hosting/repository | Snapshot supplied as current; live revision, full server controls, grants/jobs/extensions and actual application PHP remain unverified |
| PHP PDO/password, OWASP security, MySQL DDL references | Checked primary references; runtime applicability still requires discovery |
| Documentation verification | Revised 19-file pack passed: 84 relative links and 34 source file/line references checked; Markdown structure/safeguards and private connector-literal exclusion passed |

## Decisions

| ID | Decision | Basis/status |
| --- | --- | --- |
| D01 | Detailed policy in root `AGENT.md`; discoverable `AGENTS.md` entry point | Matches requested filename and coding-agent conventions; one detailed authority |
| D02 | Preserve posted financial evidence; linked corrections; no destructive cleanup | Owner's explicit constraint |
| D03 | Small compatible extractions, PDO, exact money, evidence before rollout | Owner's direction; [ADR 0001](../decisions/0001-incremental-refactoring.md) |
| D04 | Ground schema/paths/roles in supplied evidence; do not infer live parity or a runtime floor from export metadata | Static snapshot inspection complete; live/runtime checks outstanding |
| D05 | Prioritise cash/MoMo/accountability features after foundations | Proposed backlog; business rules still require explicit decisions |
| D06 | Put known destructive/permission/CSRF, billing, recovery and stock-provenance risks ahead of broad extraction | [Source findings](../CODEBASE_REVIEW.md), [legacy contracts](../LEGACY_CONTRACTS.md), S regression fixtures |

## Open unknowns and risk owners

| ID | Unknown/risk | Consequence | Next evidence/owner |
| --- | --- | --- | --- |
| U01 | Source-to-deployed revision and repository | Uploaded snapshot cannot establish a safe release target | Eugene/maintainer to map source and host |
| U02 | Host runtime/extensions/grants, live schema drift, external writers | PDO/transaction/compatibility assumptions beyond inspected snapshot may be wrong | Maintainer discovery |
| U03 | Accounting/time/rate definitions and correction authority | Incorrect revenue, balances, refunds, or closed-period handling | Eugene/authorised controller decisions |
| U04 | No supplied tests/CI; review PHP CLI unavailable; isolated DB/harness not configured | Cannot demonstrate application regression safety | Maintainer to establish/verify safe runtime and harness |
| U05 | Backup/restore method and recovery objectives | Release recovery could lose new transactions | Release operator and Eugene |
| U06 | Documented sensitive-endpoint gaps, destructive expense paths, CSRF and financial/stock defects; live exposure untested | Historical evidence, attribution or computed totals may be affected | Maintainer prepares scoped regression/containment; owner defines authority/policy |
| U07 | Independent reviewer and release authority | High-risk change cannot reach release gate honestly | Eugene/maintainer to assign |
| U08 | Null rates and zero stock references | Guessed fixes could invent historical charges/provenance | Preserve originals; owner/maintainer investigate independent evidence |

## Gate evidence

| Gate | Status | Evidence/blocker |
| --- | --- | --- |
| Documentation scope and policy | Complete and validated | 19 Markdown files including source review/contracts; no application edits |
| Snapshot source/schema discovery | Static review complete | Actual paths/roles/tables/contracts inspected; live parity and external writers still unverified |
| Application design for first slice | Proposed | Expense/permission containment before payment-history PDO extraction; policies/tests/host prerequisites pending |
| Test environment and baseline | Blocked | No PHP CLI or supplied harness/isolated DB; no application execution |
| Application implementation/security remediation | Not started | Documentation task only |
| Full financial/migration reconciliation | Not executed | Aggregate snapshot structural checks performed, but no database workflow/migration or full money reconciliation |
| Backup/restore/recovery | Not verified | No hosting/recovery access or drill |
| Release candidate/production | Not prepared/not deployed | All relevant application gates outstanding |

## Commands and verification

| Check | Environment | Actual result |
| --- | --- | --- |
| Python ZIP inventory/safe extraction/hash and source/DDL review; scoped searches | Attached snapshots only | 26 archive files, 22 PHP; nine table definitions; no execution/import/live access |
| Python literal INSERT parsing/link/rate checks (inline) | Uploaded SQL text only | Aggregate counts recorded in source review; original bytes unchanged; no raw rows/amounts published |
| `command -v php` | Review environment | PHP CLI unavailable; no syntax/runtime tests attempted |
| Python document validation script in review workspace | Revised documentation pack | Passed: 19 Markdown files, 84 relative links, 34 source file/line references, heading/list/table/whitespace/safeguard checks; no private connector literals or sensitive-content heuristic matches |
| Python ZIP CRC/member/byte comparison and attachment hashes | Revised deliverable and supplied inputs | Passed: all 19 Markdown files match source bytes at intended safe paths; original attachments and extracted PHP/config bytes unchanged |
| PHP lint/unit/integration/security/race tests | Application | Not run; source is available, but CLI/harness/isolated test DB are not |
| Migrations, reconciliation, restore, live smoke tests | Production/staging | Not run; no action authorised or executed |

## Approval boundaries and next safe action

No approval is needed to finish this documentation pack. Future live deployments, production migrations/backfills/configuration/credential changes, and financial-policy/authority decisions need concrete scoped authority. Historical financial deletion remains prohibited.

**Next safe action:** merge this pack on a development branch, confirm source-to-live mapping and safe test/recovery prerequisites, then prepare synthetic regressions and focused containment for expense mutations/direct-role/CSRF gaps. Resolve billing/recovery policies and stock provenance through scoped tasks before broad extraction; the first PDO read candidate is `payments.php`. See [the revised plan](../REFACTORING_PLAN.md). The identified code risks remain open; no live fix or historical data repair was executed.


## Local setup — 2026-10-29

- User confirmed localhost/tivoli is isolated and its prior contents disposable; authorised restoring the supplied production snapshot, renaming the project folder, and disabling the production redirect.
- Executed local restore on loopback port 3306, MariaDB 10.4.32. Original pssvkote_tivoli.sql retained unchanged (SHA-256 081884975d3394870bc843d28dcd57912c93aa1d879e68462c1c38823e331cab).
- Prior local database and original connector/redirect configuration preserved under .local-setup, denied over HTTP. This backup has not been restore-tested.
- Separate import copy changes only CREATE TABLE engine-option lines: utf8mb3 to utf8 and utf8mb4_0900_ai_ci to utf8mb4_unicode_ci. This local compatibility adaptation changes collation semantics and does not establish MySQL 8 equivalence.
- Import exited successfully. Exact counts match the documented snapshot: bookings 1392; booking_extras 3447; expenses 146; expense_categories 11; extras 20; payments 3532; rooms 9; stock_movements 2767; users 6. Full per-row/financial reconciliation has not been executed.
- db.php now targets the local database; existing mysqli, charset and UTC setup retained. .htaccess now restricts access to local clients, removes the production redirect and denies sensitive setup artifacts.
- HTTP verification: /tivoli/ returns 302 to auth/login.php; login returns 200 with a form and no detected PHP error; source SQL, backup SQL and direct db.php requests return 403. No authenticated workflow or financial write test was performed.
- Folder rename to C:\xampp\htdocs\app.tivoliguesthouse remains blocked: Windows reports the source directory is in use. No processes were terminated. Active app remains C:\xampp\htdocs\tivoli.
- No production connection/deployment occurred. This protected local restore is not a synthetic regression-test database; ordinary tests still require a separate guarded synthetic environment.
