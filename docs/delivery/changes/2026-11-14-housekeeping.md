# Housekeeping and Room Readiness - completed 14 November 2026

Owner approved the interactive prototype including Select All. Implemented and verified in C:/xampp/htdocs/app.tivoliguesthouse on main, preserving extensive pre-existing uncommitted work. See [staff instructions](../../HOUSEKEEPING.md), [approved design](../../superpowers/specs/2026-11-14-housekeeping-design.md) and [completed plan](../../superpowers/plans/2026-11-14-housekeeping.md).

## Workflow and source contracts

HousekeepingService records individual cleaning attempts, captured cleaning/inspection checklists, saved wizard progress, actual completion, independent inspection or rework, one-attempt owner exceptions, historical follow-up and explicit owner restart of abandoned assignments.

Select All checks all cleaning items across the wizard, or marks inspection items Pass. It never ticks the separate final personal attestation or records completion/release. The final actions require native confirmation and server validation. Every inspection item must have Pass/Needs Attention; a failed item blocks release. Comments and PDF/JPEG/PNG evidence up to 2 MB are optional; exception and owner-decision reasons are required.

Eligible active staff with bookings.manage or rooms.manage can perform housekeeping. Only the named cleaner updates their attempt. Another authorized person inspects, unless the configured finance owner grants a one-attempt self-inspection exception. Eugene can explicitly authorize an exception to his own recorded cleaning. Authorization alone never releases. Inspection consumes/closes the grant for either outcome or an independent inspection, and self-inspections remain in Owner Review until the exact original inspection receives follow-up. Follow-up remains possible after newer cleaning/occupancy.

The authoritative room adapter reuses effective physical occupancy and unsecured departure-incident guards. Room reuse, occupancy evidence or critical fault history invalidates an earlier attempt; ordinary financial debt does not itself block secured-room cleaning. Dirty/awaiting_inspection remains unavailable to check-in. Critical faults and occupied rooms cannot release. The existing repair flow remains; no separate repair-release step is added. Previously-ready rooms keep that repair policy. If an unfinished named attempt is interrupted by changed safety/occupancy evidence, a fresh attempt is required before it can release.

Checklist revisions and text are captured at Start Cleaning. Administrator settings changes apply to future attempts; started rounds keep their actual evidence. Legacy preparation without a recorded cleaner requires a fresh named attempt rather than an inferred actor or backdated completion. Previously-ready rooms are not automatically changed. Owner restart is an explicitly recorded recovery addition: reason, retained original, new physical attempt and owner as its actual cleaner, not silent reassignment/completion.

Storage uses existing append-only finance_events, finance_operations and security_audit. Target type housekeeping; root kind hk_started and linked hk_progress, hk_completed, hk_exception_requested, hk_exception_decided, hk_inspected, hk_followup and hk_superseded. Amounts are zero. Current room/readiness mutations, operation identity, required audit, optional evidence registry and events commit on one PDO connection under the shared security_state lock. Stable keys protect retries; stale versions/state reject actions. Audit failure rolls back readiness and history together.

Room evidence is encrypted outside the public tree with a committed audit registry. Authorized housekeeping team members can read it; general finance/report access alone does not grant that access. Downloads are logged. Failed file transactions can leave encrypted unreachable orphans under the existing storage design, not an accessible committed evidence record.

New housekeeping.php route, templates/housekeeping/{index,form,history}.php and scoped CSS/JS use the shared workspace. Main menu, property navigation and room turnaround actions lead into it. Owner Reports and handover expose pending work in-app. Global Operational Activity includes new housekeeping audit actions without describing a failed inspection as a successful release. Finance activity is filtered for non-owner viewers without housekeeping context.

Old public clean/inspect GETs redirect and POSTs deny409. Trusted legacy setup/service calls are retained only for rooms with no modern attempt, and modern-room bypass is rejected. Original room, guest, financial and audit records remain unchanged. No schema migration, new role grant, secret or runtime dependency; existing stay/finance setup and verified owner binding remain prerequisites.

## Executed evidence

Verified 14 November 2026, PHP 8.2.12 / MariaDB 10.4.32. Exact dedicated target 127.0.0.1:3307, tivoli_auth_test, marker synthetic-auth-only-v1; HTTP 127.0.0.1:8099 with X-Tivoli-Test-Mode: synthetic. Bootstrap/fixtures guard the exact connection. No ordinary application-database test writes.

From the checkout, each integration ran as C:/xampp/php/php.exe tests/Integration/<name>.php and each HTTP contract as C:/Python313/python.exe tests/Contract/<name>.py. Full ignored evidence is .local-setup/housekeeping-verified-<name>.txt and housekeeping-verification-summary.json. These are executed test commands, not directions to run against an ordinary application database.

| Integration file | Passed |
| --- | ---: |
| housekeeping | 36 |
| housekeeping_concurrency | 8 |
| stock_counts | 39 |
| stock_counts_concurrency | 7 |
| purchases | 48 |
| purchases_concurrency | 5 |
| finance | 37 |
| finance_concurrency | 6 |
| finance_changes | 45 |
| finance_late_posting | 36 |
| finance_posting_lock | 1 |
| finance_weekly | 25 |
| finance_weekly_concurrency | 3 |
| finance_reviews | 12 |
| owner_reports | 19 |
| expenses | 52 |
| stay_billing | 31 |
| stays | 137 |
| stays_concurrency | 3 |
| departure_incidents | 42 |
| departure_incidents_concurrency | 4 |
| handovers | 42 |
| handovers_concurrency | 4 |

| HTTP file | Passed |
| --- | ---: |
| housekeeping_http | 36 |
| stock_counts_http | 25 |
| purchases_http | 32 |
| finance_http | 42 |
| finance_changes_http | 30 |
| expenses_http | 20 |
| finance_weekly_http | 3 |
| stays_http | 124 |
| stay_billing_http | 16 |
| departure_incidents_http | 26 |
| handovers_http | 27 |
| owner_reports_http | 10 |
| access_http | 24 |

Totals: **642 integration checks including 40 real concurrency scenarios; 415 HTTP checks**. stays_legacy_guard additionally verified 22 audited denials and 12 unchanged full-table hashes. apache_routes passed six public-route/seven private-path checks and local-only/deny-by-default assertions.

Eight new real-process races cover competing cleaners, identical completion retries, competing independent inspectors, exception authorization versus independent inspection, critical fault versus release, actual check-in versus release, follow-up retries, and owner restart versus completion. The guarded domain suite asserts unchanged full payment/expense/stock rows, explicit authority, stale state, checklist snapshots, occupied/blocked release denial, single-use exceptions, rework, restart, protected evidence and required audit rollback.

The missing-feature assertion failed before implementation. Independent source review reported no important findings, and found two minor but user-visible issues: nested checklist JSON values could warn before rejection, and Save & Return reopened the wizard. Both were reproduced in failing regressions, fixed, and the full suite passed. Initial template syntax problems were caught before any UI files were written. Existing HTTP route contracts were intentionally updated to named staff cleaning/independent inspection while retaining settings and financial assertions. Standalone occupancy HTTP first needed its existing guarded integration fixture refreshed; it was not repaired through ordinary data changes.

Dedicated Chrome port 9236 ran ignored .local-setup/housekeeping-browser-qa.cjs after tests/Integration/housekeeping_http_seed.php. Verified actual Select All across hidden steps, saved/continued selections, separate personal attestation, canceled completion, action focus, owner-only one-attempt authority, inspection Select All, failed-release rejection, rework, new cleaner/independent release, and follow-up against the earlier failed attempt. Eighteen views passed across 1440/390/320 pixels without page overflow, JavaScript/PHP/encoding output errors. A 320-pixel inspection screenshot was visually inspected. Ignored screenshots: .local-setup/housekeeping-clean-*.png, housekeeping-inspect-*.png and housekeeping-history-*.png.

Twenty-three changed PHP files passed php -n -l. JavaScript passed node --check, four affected Python files passed AST syntax, and scoped UTF-8/known-mojibake and git diff --check passed, with existing line-ending warnings. Current application includes extensive pre-existing untracked source; targeted review/lint/tests supplement tracked diffs.

Actual ordinary localhost Apache anonymous housekeeping.php GET returns302 to auth/login.php; .env and src/Service/HousekeepingService.php remain403. No authenticated ordinary data mutation was used as a smoke test. Automatic approval-review usage-limit failures executed no blocked commands; user continuation resumed the same authorized work without bypass.

## Release, recovery and boundaries

Implemented/tested locally; no new local activation/migration. No ordinary database fixtures, SQL import, branch/staging/commit change, push or production deployment. Existing late-posting regression rehearses migration 010 only on the marked synthetic target. PHP/MariaDB verification does not establish MySQL 8 production parity.

Production delivery remains separately authorized, with coordinated complete source, verified configuration/owner binding, supported writers and recovery evidence. Before events exist, scoped source rollback can restore the previous behavior. After events exist, forward repair must preserve every original attempt, checklist revision, progress record, inspection, exception, readiness transition, evidence and audit link. Do not resume incompatible old clean/inspect writers, run them alongside modern attempts, delete originals or restore over newer financial activity.

This slice does not create monetary/stock/payroll entries, historical backfills, external notifications, shift scheduling or arbitrary staff reassignment. Owner restart creates an honest new attempt; it is not delegated work or a retroactive attestation. Conservative room-cycle changes can require a fresh attempt after changed occupancy/safety evidence. All completed self-inspections retain follow-up even if another round has since prepared the room. General correction of an inaccurate historic inspection requires new factual operational evidence, not overwriting an original.
