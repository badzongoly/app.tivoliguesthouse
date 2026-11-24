# Housekeeping merged into occupancy — 22 November 2026

Owner approved the proposed room-based integration. Implemented locally: Room Board preparation filters and next actions; common Check-In & Occupancy navigation for the original checklist/inspection forms; owner review and history within the workspace; separate Housekeeping sidebar entry removed. Both booking-authorized and rooms-only housekeeping staff retain their appropriate entry. Old housekeeping URLs remain compatible; canonical links/command redirects use occupancy.php?section=housekeeping. The delegated section uses HousekeepingService permission checks; other occupancy sections still require bookings.manage.

The existing housekeeping command service, CSRF, operation keys, transactional audit, assigned cleaner, captured checklist, independent inspection, bounded owner exception, readiness/occupancy/critical-fault/cycle guards and private attachments are retained. No duplicate writer was added. Room Board summaries use current authoritative cycle facts before offering cleaning/inspection; fault-and-repair invalidation offers fresh cleaning instead of an obsolete form.

New hk_started payloads add a server-derived source_booking_id only for an evidenced latest finalized departure. Earlier captured links and events are immutable; existing unknown links are not backfilled. Guest accounts select only explicit source metadata. Their cleaning view checks the stay-to-attempt association and is read-only, with no current-room shortcut, form or POST action. A newer guest's preparation never replaces the original stay's link. Unfinalized incident and historical preparation can remain unlinked. Current room work remains room-based.

Independent read-only review identified the old guest header shortcut to the current room and a stale-cycle summary action. Both were reproduced/covered and fixed; follow-up review found no further important issue. Review was source-only; the implementer executed the tests below.

Executed on the explicitly marked synthetic server/database (HTTP8099, DB3307 / tivoli_auth_test / synthetic-auth-only-v1):

- New merged integration: 12 PASS assertions, including captured departure association, original/later guest separation after room reuse, repair invalidation and rooms-only fixture privileges.
- New canonical HTTP acceptance: 33 PASS assertions, including workspace/filter navigation, cleaning steps, independent inspection, failed release, durable retry, invalid CSRF, unauthorized actor, historical read-only/mismatch/POST denial, old URL compatibility and rooms-only access without guest-management authority.
- Existing housekeeping service: 36 PASS assertions, including audit rollback, self-inspection exception/follow-up and exact unchanged payment/expense/stock rows.
- Existing housekeeping HTTP: 36 PASS assertions, including owner-only queue, optional evidence, rework, captured checklist, idempotent release and legacy clean/inspect POST denial.
- Shared confirmation browser: 247 PASS assertions across 65 confirmation attributes. No duplicated modal/submission; inert background, safe cancellation, file and submitter retention remain verified.
- Existing paid-arrival/departure HTTP: 16 PASS assertions; protected billing, owner-authorized departure and statement/CSV retained.
- Activity visibility HTTP: 12 PASS assertions; own/admin history and direct URL scoping retained.
- Responsive browser: 12 views across Room Board, cleaning form, guest account and original read-only cleaning record at 1440/390/320px. No horizontal page overflow or runtime errors; form focus verified. Screenshots visually reviewed and kept outside Git.
- Nine changed application PHP files and new integration test linted, browser JavaScript syntax and diff whitespace checks passed. Fixture file is Git-ignored.

Total 392 passing assertions plus 12 responsive views. Tests ran on synthetic data only. No ordinary database records, historical financial fields, schema, credentials, hosting configuration, Git history or external notifications were changed. No push or hosted deployment. Additive source metadata uses the existing event transaction; no activation/migration required.

Affected application files: public/occupancy.php, public/housekeeping.php, src/Security/require_login.php, src/Service/HousekeepingService.php, templates/stays/index.php, templates/stays/workspace-nav.php, templates/housekeeping/index.php, templates/partials/sidebar.php and templates/layouts/context.php. Three reusable regression files cover the merged behavior. User guide: docs/HOUSEKEEPING_WORKSPACE.md. Prior uncommitted changes remain preserved.

Recovery: reverse only this integration's coordinated routing/presentation/service additions, preserving previous activity/privacy/modal changes. Older code ignores the additive payload key. Retain every recorded attempt and evidence item; no data rollback or deletion is needed.