# Staff Shift Handover Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans inline, with test-driven-development and independent final review.

**Goal:** Implement the approved duty handover and separate cash custody flow.
**Architecture:** HandoverService uses existing append-only finance events/operations and shared transaction lock. CashCustody replays per-receipt custody through transfers and deposits. FinanceService consumes those positions so every deposit honors the same reservations.
**Tech Stack:** Existing PHP/PDO/server-rendered workspace, plain JS, marked synthetic MariaDB.
**Spec:** ../specs/2026-11-14-shift-handover-design.md

## Global Constraints

Existing roles, UTC, permissions and designated finance owner. No schema migration, production action, data rewrite or automatic Git operations. Current approved gross-deposit/no-count policy takes precedence over older SOP target wording.

## Review Focus

Split custody and pending reservations; stale snapshot/review tokens; unrelated/inactive accounts and non-owner admins; retry/audit rollback/racing deposit or acknowledgement; owner cash review must not invent receipt.

## Tasks

- [x] Domain. Add tests/Integration/handovers.php and execute failing feature assertion first. Implement src/Service/HandoverService.php with draft(int):array, detail(int,int):array, register(int,int=0):array, attention(int):array, execute(int,string,array,string):array. Add src/Domain/Finance/CashCustody.php::apply(array,array):array. Extend FinanceService cash/deposit allocations. Verify frozen/stale snapshots, authority, exceptions, exact custody/deadlines, rollback and real concurrency.
- [x] Workspace. Add public/handover.php, templates/handover/index.php and scoped CSS/JS. Extend login endpoint guard, shared navigation/context, finance custody display/filter and owner alert. Add guarded HTTP checks, CSRF/direct-object/cancel/focus and responsive browser verification.
- [x] Handoff. Run affected finance/stay/recovery/report regressions, syntax and encoding checks. Obtain independent source review. Record exact evidence, current status, no migration and forward-repair boundaries in delivery state/change record.

## Execution decisions

Continue the approved implementation inline without another permission gate. Reuse current checkout because a worktree from the old committed baseline would omit the approved application. Preserve existing uncommitted work, do not commit or switch branches. Cash discrepancy review is annotation only. No write-off or silent amount correction. Pending/disputed custody remains reserved until recipient acknowledges full receipt with explanation where disputed.
