# Practical refactoring and feature plan

Sequence by evidence and risk, not by how quickly the code looks object oriented. Revised after [source review](CODEBASE_REVIEW.md); use [legacy contracts](LEGACY_CONTRACTS.md) as the observed baseline. This is a proposed backlog; implementation and live actions are not authorised by the documentation task. Do not put all phases into one PR.

## Phase 0 — confirm the source baseline and safe environment

**Already observed:** 22 PHP files using mysqli, some prepared statements, two transactional workflows, `admin`/`clerk`, UTC, nine InnoDB tables, existing extras/stock/recovery modules and prioritised defects. The export reports MySQL 8.0.46-37 and export-tool PHP 8.4.27; this is not a verified application-runtime floor.

**Still deliver:** source-to-live revision map, host PHP/extensions/SQL-mode/grants, external writers/jobs, approved accounting/authority policies, safe local/test setup and actual commands, backup/restore status and remaining threats. Verify legacy null rates, broken stock links and room rentability without modifying them.

Record everything in [project context](PROJECT_CONTEXT.md) and [delivery state](delivery/DELIVERY_STATE.md). No production experiments. Use approved read-only discovery if live evidence is necessary; restrict sensitive output.

**Exit:** know what code is deployed, how money/rooms change, how to test safely, what must remain compatible, and how a release can be recovered. A live vulnerability may justify an immediate narrow remediation, not an uncontrolled rewrite.

## Phase 1 — create a safety net

**Deliver:** synthetic fixtures, guarded isolated database configuration, compatible minimal test runner if absent, characterisation/contract tests for the first slice, core booking/payment/report baseline, and a change-evidence workflow.

Add supported-runtime lint, relevant static/security checks, and CI using existing conventions where practical. CI must use isolated services and no production credentials; do not create a workflow that appears green while skipping the financial suite.

**Exit:** tests execute and catch deliberate defects in isolation; old behaviour and known defects are distinguished; critical workflows have a reproducible baseline. Backup restoration is proven before a high-risk live release.

## Phase 1B — contain the documented integrity/security hazards

Deliver small independent fixes with the source-specific S regression cases, before a broad PDO/class conversion:

1. Server-side capability checks for sensitive endpoints and exports, preserving the real `admin`/`clerk` identifiers; CSRF-protected mutations with legitimate caller migration.
2. Stop destructive expense edits/deletion and identity loss through a reviewed correction/deactivation transition. Keep ordinary expense entry and an authorised correction route usable.
3. Fix completed-stay/repeated checkout effects, wrong stock-sale reference capture, and partial/concurrent/retried writes through explicit scoped changes. Capture IDs at the insert boundary; preserve existing transaction protections.
4. Agree billing outcomes before unifying noon-cutoff and CSV calculations. Keep occupancy semantics separate. Investigate null historical rates and zero stock links without guessing data repairs.
5. Harden `admin_recovery.php` as a real writer: source evidence, actor, actual recording time versus effective date, reason, replay protection and appropriate authorisation; decide historical stock effects.

These are code/behaviour/security changes, not cosmetic extractions. Stage them by actual risk and recovery readiness; do not combine them into a single emergency rewrite. A temporary containment action still needs precise scope, tests, business continuity and target-specific live approval.

**Exit:** the relevant known hazardous paths are contained with recorded evidence and explicit remaining blockers. The plan does not claim they were fixed by writing these Markdown files.

## Phase 2 — first class and PDO slice, read-only

After priority containment, use the read-only `payments.php` listing/count queries as a concrete candidate. Extract them to a small injected PDO repository; preserve URL, `start`/`end`/`page`, date boundaries, ordering, 25-row pagination, decimal/type mapping and links. Add a service only if needed. Keep role hardening distinct from query extraction. Do not replace global `db.php`, change schema/rates/availability/UI/report semantics, or add a framework in this slice.

**Exit:** old/new outputs match on fixed fixtures; supported runtime passes; no writes; malformed inputs and empty/null/decimal mapping are tested. The real deploy/recovery process supports the new files/autoloading.

## Phase 3 — harden boundaries in scoped slices

Inventory all direct endpoints and close demonstrated gaps: server-side authentication/resource permissions, validation, prepared SQL, CSRF/session safeguards, safe output, and secret/error handling. Convert one coherent writer at a time; do not leave an older endpoint bypassing the new controls.

Keep structural extraction separate from an intentional security behaviour change. For password/session migration, rehearse account continuity and recovery. Strengthen DB privileges only after inventorying the legacy application's needs.

**Exit:** denied/tampered requests fail on every relevant path, legitimate reception workflows work, and new security/audit events are accurate without leaking secrets.

## Phase 4 — trustworthy financial write paths

Select one complete operation, such as recording a payment, based on actual source and risk. Define exact amounts, correction semantics, permissions, idempotency, transactional ownership, durable audit, receipt identity, and concurrency before changing it. Use additive schema only where justified.

Capture source mapping and any cutover boundary. Preserve all original financial rows. Add linked correction workflows deliberately; do not silently “freeze” an existing edit screen without a usable authorised correction path. Separate structural work from changed accounting definitions.

**Exit:** applicable [test matrix](TESTING.md) cases pass on the real compatible database; independent review and exact reconciliation complete; rollback/forward recovery preserves post-release events. Core ledger/cutover changes require an explicit separate decision.

## Phase 5 — accountability features, one workflow at a time

These candidates follow earlier Tivoli priorities. Confirm the actual feature gaps and approve workflow/authority rules before building; none is a claim about current implementation.

| Priority/candidate | Concrete outcome | Prerequisite and acceptance |
| --- | --- | --- |
| P1: Cash custody, count, and handover | Know who holds cash, amount expected/counted, transfer state, and variance | Verified cutover float, agreed custody policy; no double custody or forced balancing; overnight/concurrent tests |
| P1: MoMo reconciliation | Distinguish staff entry from independently matched merchant evidence | Real statement/reference contract and access rules; idempotent import; unmatched/duplicate/reversal cases visible |
| P1: Sensitive-action requests/approvals | Discounts/refunds/voids/rate overrides/expense changes attributable with reasons | Owner-approved capabilities/limits; server-side maker/checker or explicit compensating review; no self-approval bypass |
| P2: Owner exception dashboard | Inspect balances, cash variance, unmatched MoMo, overrides, cancellations, and audit gaps | Approved measures and source links; exact reconciliation; role/data minimisation; no automatic accusations |
| P2: Closed-session/period control | Protect submitted counts and completed periods with traceable correction/reopen | Approved posting/cutoff policy, authorised exception route, historic evidence retained |
| P2: Better reports and controlled exports | Separate charges/revenue, receipts, expenses, custody, and outstanding balances | Current definitions captured; intentional changes approved; CSV/receipt/permission contracts tested |
| Later: Public website/POS integration | Reuse authoritative room IDs/rates/availability with controlled enquiry conversion | Explicit API/auth/availability design; no duplicate inventory; idempotent conversion and overlap tests |
| Later: Read-only AI/alerts | Help owner inspect exceptions using minimal approved data | Deterministic controls already work; read-only access, privacy review, evidence links, failure isolation, no approval/posting authority |

Preserve the implemented extras/sales/stock/recovery modules and their historical data; confirm present use before proposing retirement or expanded guest services. Do not add paid infrastructure, payment APIs, surveillance, or automated external messaging without a concrete separately scoped need and authority.

## Phase 6 — broader simplification after evidence

Remove obsolete implementation paths only when every consumer has migrated and usage/tests prove it. Keep historical data, stable identities, and necessary adapters. Consider broader dependency/runtime upgrades or a framework only through a separate decision with compatibility and operational evidence. Maintain the restore drills, tests, dependency review, and financial reconciliation as the project evolves.

## First implementation ticket after safe setup

**Title:** Characterise expense mutations and protect their financial evidence and permissions.

Acceptance criteria: synthetic tests reproduce current create/edit/delete and direct `clerk` access; owner-approved capabilities are explicit; unauthorised/forged/GET destructive requests cannot alter evidence; original expenses and staff links remain; legitimate entry and authorised correction handling are defined; existing unrelated payment flows remain unaffected; supported-runtime checks and focused review complete. Use separate commits/slices for containment, correction model and later connector extraction; no live data repair or rollout is implied.

If confirmed live risk demands a different first containment, document the evidence and smallest safe action. The subsequent first PDO ticket is the tested payment-history read extraction above. Do not use a class extraction as evidence that a financial control is complete.

