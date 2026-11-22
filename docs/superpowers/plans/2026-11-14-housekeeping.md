# Housekeeping Implementation Plan
Execute inline with TDD and one independent source review.
Spec: ../specs/2026-11-14-housekeeping-design.md
Goal: approved staff cleaning, independent inspection, one-attempt owner exception and audited follow-up.
Architecture: HousekeepingService, existing event/audit/operation storage, shared lock and readiness fields. StayService exposes read-only authoritative physical room facts and denies old clean/inspect on modern attempts.
Constraints: preserve repair workflow, histories, UTC, exact financial rows; no migration, Git changes or ordinary DB fixtures.
Review focus: room reuse/critical-fault/retraction races, failed inspection, exact cleaner identity and grant consumption, stale approvals, evidence access, audit rollback, obsolete writers and Apache routing.
- [x] Domain: failing guarded integration test, HousekeepingService permit/detail/board/attention/execute, room facts adapter, captured checklist revisions, progress, exceptions/rework/restart/follow-up, replay/rollback.
- [x] Workspace: new controller/template/CSS/JS, Select All, root route/security/menu, old public denial/redirect, protected evidence, owner/handover attention. HTTP access/CSRF/validation/history tests; adapt intentional old-route contracts.
- [x] Verify: real competing writers, affected finance/occupancy/stock/purchase/handover regressions, actual browser workflow/mobile, lint/encoding, independent review with RED/GREEN fixes, guide and delivery record.
Rulings: owner-authorized restart is required to recover abandoned assignments without silently reassigning an original attempt. Housekeeping operational evidence is visible to authorized housekeeping team; it is not HR/private purchase evidence. Legacy ready rooms are not auto-reclassified or backfilled.

Completed:36 focused domain checks,8 new real races,642 integration checks total,415 HTTP,18 responsive views,23 PHP lints. Independent review findings reproduced/fixed; no important findings remain. See delivery change record for commands and recovery boundaries.
