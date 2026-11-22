# Owner Exception Register Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans inline, with one independent final permission/data review. Preserve the existing checkout and evidence; do not commit or create a worktree.

**Goal:** Give the designated owner one simple queue linked to existing unresolved cases, without new business mutations.

**Architecture:** OwnerExceptionService owns current snapshot, filters, paging and safe public projection. OwnerExceptionFeeds reads through existing source services and original source IDs; no ticket table or generic command writer. One protected read-only page links to each original workflow, where existing permissions, CSRF, versions, durable idempotency keys, locks and mandatory audit continue to govern decisions.

**Tech Stack:** Existing PHP 8.2, PDO, MySQL-compatible isolated test instance, shared layouts and plain CSS/JS.

**Spec:** ../owner-exceptions-design.md, owner-approved in chat 22 November 2026.

## Global Constraints

- No original financial/source history changes, schema/migration/backfill, configuration/credential changes, production actions, Git staging/commit/branch mutation or external messages.
- Current records are read together under a repeatable read-only snapshot; access logging follows commit. A failed/unauthorized feed is never silently called healthy/empty.
- Owner gate plus each source's authority, especially payroll hr.salary, expense rights and stock authority. Unknown actor/deadline remain explicit.
- Source identity and explicit parent links prevent duplicate parent/case rows. Cash free custody, reserved handover and deposit residuals must not conflate amounts; preserve full deposit obligations and avoid a summed money total.
- Open carries across weeks; Resolved and Activity retain original history. Reopening is source-derived and cannot undo verified amounts. A note/deadline or staff completion claim never resolves money/safety.
- The register has GET/HEAD only. Every business change opens its existing writer; no simulated prototype actions in the app.

## Review Focus

- Root/source failure and revoked permissions: retain unavailable identities where known, show incomplete coverage, hide unauthorized private rows/counts.
- Split receipt allocation, partial cash returns and reopened fully credited disputes: preserve exact source quantities and deduplicate only explicit links.
- More than 50 old open records: filter/sort before display paging; no first-page-only feed truncation or week cutoff.
- Unsettled approved purchases/payroll and unverified audit completion: source progress retains remaining blockers and original IDs.
- Repeated reads/failed access logging: no finance/stock/operation/source records created, no direct POST or source-command bypass.

### Task 1: Source-linked read model

Create src/Service/OwnerExceptionService.php, src/Service/OwnerExceptionFeeds.php. Tests tests/Integration/owner_exceptions.php and owner_exception_matrix.php.

Interface: read(int $actor,array $query=[]):array returns rows/counts/coverage/selected/activity/page/as_of. Query keys view(open/resolved/activity), filter(all/overdue/decision/staff), workflow(all or supported category), page(positive integer), key(optional safe original source key). Each normalized row contains key, title, category, source reference/path, state, resolved, decision, responsible ID/name, due, amount_minor or quantity, original source links, safe facts and authorized history. No public execute method.

- [x] Write/run guarded RED for missing service, configured-owner gate, named source assignment/status and unchanged journal/operations.
- [x] Implement authoritative source adapters: cash custody/deposits/MoMo/discrepancies, recovery/departure, purchase/provider/expense approval, receipt/expense/purchase/guest corrections, payroll, stock issues/counts, weekly room/area and existing handover/housekeeping owner attention. Retain source-specific ACLs and private evidence boundaries.
- [x] Prove partial/progress/closed/reopened/unknown/history/paging/permission paths against real synthetic sources, with exact amounts and row identity; fix observed defects only.

### Task 2: Existing workspace entry and detail

Create public/owner-exceptions.php, templates/owner-exceptions/index.php, scoped CSS/JS. Modify protected route and rewrite allowlists, layout context, owner-only sidebar entry and Owner Reports link. Existing source writers remain untouched.

- [x] Add protected HTTP tests: login, non-owner denial, private feed rights, GET filters/detail/source links, POST 405, no action forms or prototype controls, focus, stale/unknown key and optional data failure.
- [x] Implement compact responsive queue, Open/Resolved/Activity, simple filters, original source facts/history and Open Existing Workflow. Default all weeks and compact rows; source-local forms remain on original routes.
- [x] Verify exact delivered files, PHP/JS/UTF-8, source-link behavior and desktop/mobile views.

### Task 3: Verification and handoff

- [x] Independent final read-only permission/financial projection review; reproduce/fix significant findings with RED→GREEN.
- [x] Run relevant source regression suites sequentially on guarded 127.0.0.1:3307/tivoli_auth_test, marker synthetic-auth-only-v1; HTTP 127.0.0.1:8099 with synthetic header. No normal database fixtures.
- [x] Update owner guide, delivery/change record, SOP gaps and workflow roadmap with executed evidence, limits and recovery (no DDL; retain all originals and source controls). No production deployment.

Executed results and implementation decisions are in docs/delivery/changes/2026-11-22-owner-exceptions.md. Source metadata batching and explicitly disclosed legacy coverage are deliberate limits, not new posting authority. External evidence ledger retained because no Git mutation/commit was authorized.
