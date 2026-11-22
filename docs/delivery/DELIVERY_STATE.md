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

## Folder structure implementation — 2026-10-29

Implemented public endpoints/assets, private templates/config/bootstrap/src, tests and migration/storage scaffolding. Original local database connector moved to ignored config/database.local.php; existing mysqli and UTC behaviour retained. Existing local URLs preserved through root routing allowlist. Apache global configuration and production were untouched. Executed: 12 local HTTP contract checks passed, 24 PHP files linted successfully, all 14 legacy root pages match baseline after reversing include-path edits. Authenticated workflows, financial writes and MySQL 8 compatibility remain untested. No schema/data changes. Login security refactoring remains a separate next slice.


## Staff access module — 2026-10-29

Implemented locally: authentication/password flows, CSRF/session revocation/timeouts, RBAC administration and endpoint gates, role assignment, deactivation/last-admin guard, append-only transactional audit trail/viewer, encrypted asynchronous reset queue, PHPMailer TLS SMTP transport. Additive migrations through version 3 applied only to the confirmed isolated local tivoli database and separate synthetic port-3307 test instance. No financial source/schema change. Results: 46 integration +19 security +2 concurrency +4 queue/throttle +24 synthetic HTTP checks, 12 local routing checks, 52 PHP lints, JavaScript syntax and Composer security audit passed. SMTP provider delivery and MySQL 8 parity remain unverified. Known local MariaDB Aria system-table checksum error was not repaired; tests used a fresh separate instance. Full detail, commands, review findings and boundaries: ACCESS_CONTROL.md. No production deployment or financial workflow certification.


## Authentication visual design — 2026-10-29

Redesigned shared authentication template with existing Tivoli branding, ivory/green/gold palette, decorative courtyard illustration, responsive layout, accessible labels/focus states and password visibility controls. Scoped styles in public/assets/css/auth.css leave access-management styling separate. Existing controller, CSRF fields, endpoints and password logic retained. Executed: PHP template lint, 24 synthetic authentication HTTP assertions and 12 local routing checks passed. Headless Chrome verified 1440px, 390px and 320px layouts without horizontal overflow and exercised show/hide controls at all three sizes; desktop and mobile screenshots inspected. Local-only visual evidence is excluded from Git. No financial/database schema or production change.


## Tivoli login property artwork — 2026-10-29

Replaced generated courtyard SVG with the owner-supplied brick-cladding concept from img/tivoli_front_view. Original source preserved; identical PNG bytes copied to public/assets/images/tivoli-front-view.png. Desktop displays the complete facade at its original aspect ratio; mobile uses the image as a subdued header background. PHP template lint and 12 routing checks passed. Browser checked image loading, no horizontal overflow, and show/hide at 1440px, 390px and 320px. No authentication or financial logic changed.


## Clerk expense approvals — 2026-10-29

Implemented locally: clerk-only pending submission workflow, administrator approval/rejection with reason, original actor links, exact money, replay protection, protected expense history, audited reads/exports and atomic posting/audit. Administrators retain immediate entry. Migrations 004/005 applied only to confirmed isolated localhost/tivoli; all157 historical expense/category INSERT statements match before/after (146 expenses,11 categories). Full dump bytes differ only by newly added trigger metadata. Protected local backup created but restore not rehearsed. New workflow tests use separate synthetic server3307/HTTP8099; initial focused results52 integration assertions+4 concurrency scenarios+35 HTTP assertions passed, with broader authentication/routing regressions and PHP lint recorded in the final handoff. See EXPENSE_APPROVALS.md. No production change; MySQL8/recovery/external-writer/correction gates remain outstanding.


Final expense verification:52 integration+4 concurrency+36 expense HTTP+24 authentication HTTP+12 routing checks passed;64 PHP files linted. Synthetic administrator/clerk desktop/mobile views checked. Only trusted session flash messages announce outcomes. Existing source row comparison evidence remains unchanged.

## Shared authenticated workspace — 2026-10-30

Implemented common sidebar/header/account/footer across 16 authenticated views, privilege-filtered grouped navigation, real role names, scoped pending-expense badges, desktop collapse, mobile drawer/focus controls, compact green/ivory styling and dashboard heading. Authenticated account forms share the shell; unauthenticated login/recovery artwork retained. Password-change-required accounts have restricted navigation. All 11 legacy controller blocks and original inline scripts compare unchanged to pre-layout copies; no financial data/schema change.

Executed: 16-view synthetic HTTP coverage, export-without-HTML checks, GET-only/read-only legacy fixture guard, locked-account/clerk navigation checks; 24 authentication +36 expense HTTP assertions; 12 local routing/protection checks. Browser verified all 16 views at 1440/390/320px, desktop collapse, mobile open/Escape/focus return, with desktop/mobile screenshots inspected. PHP and JS syntax checks passed. Independent review accessibility findings fixed. Previous synthetic port-3307 instance suffered system-table corruption; preserved intact before fresh disposable provisioning. Port-3306 restored guesthouse data untouched. See SHARED_WORKSPACE.md for structure, boundaries and reproducible verification. No push or deployment.

## Staff & HR implementation — 2026-10-30

Implemented and installed on confirmed localhost/tivoli: guided partial onboarding drafts; personal/emergency/employment records; administrator activation; explicit existing-account linking and clerk invitations; own-staff leave requests/review; probation and issued-item handover; suspension/resumption and effective-dated offboarding with sanitized rich reasons; private encrypted documents and optional multi-ID onboarding attachments; immutable encrypted salary/payment revisions; administrator provider/bank/document-type Settings; transactional HR/audit history and direct endpoint permissions. No payroll posting, inferred account matches, entitlement calculation or production change.

Executed synthetic checks:49 HR integration assertions,55 HR HTTP assertions,3 concurrent scenarios,24 existing auth HTTP assertions,36 expense HTTP assertions,16-view layout regressions. Browser verified11 HR views and6 wizard steps at1440/390/320px. Local routing/private-file checks19; PHP/JS syntax checks passed. Independent security review/recheck closed authority, session recovery, inactive-provider and pending-admin-exit findings; no remaining concrete P1/P2 reported in reviewed scope.

Protected pre/post local SQL snapshots and key backup created; all11,330 original row statements across9 existing tables match byte-for-byte, including6 original user records. Additive migration006 seeded6 document types and8 HR privileges; no inferred/synthetic staff or provider/bank choices inserted into the application database. Encryption keys/private storage/snapshots ignored and denied over HTTP. Full recovery rehearsal, SMTP delivery, production target/access controls and background exit-worker scheduling remain release prerequisites. Current account-request guard enforces due exits without waiting for the background worker. See STAFF_HR.md. No commit, push or deployment.


## Check-in, occupancy, guest bills and extras — 2026-10-30

Implemented in the working tree; tested and prepared for local activation. Ordinary localhost/tivoli is NOT migrated/enabled; no production deployment, commit or push. See [OCCUPANCY.md](../OCCUPANCY.md).

Implemented: shared overnight/short-stay inventory and actual recent check-outs; required five-field check-in plus optional private encrypted IDs; guest identity above unified bill; exact captured rates and legacy noon boundaries; final cashier payments and pay-now/room extras; approved incidentals; linked bounded overpayment refunds; server-bound payment/departure confirmations; full settlement returning to checkout; capped administrator debt exceptions; post-departure payment denial; room cleaning/inspection/fault/audit workflow; permanent admin room tariffs; main-menu extras catalogue/stock administration; atomic durable audits, retries and protected history. No reservations, extensions, charge-credit requests or weekly ADR/pricing approval workflow.

Executed:47 occupancy DB integration assertions;38 occupancy HTTP assertions;3 real separate-process concurrency scenarios;24 existing auth HTTP assertions;36 expense HTTP assertions;14 new views at1440/390/320px and actual action-field focus;106 PHP syntax checks and new JS syntax check. Independent source review/recheck closed four P2 findings and found no remaining concrete P1/P2 blocker in reviewed scope. Private files/backups remain Git-ignored.

Recovery rehearsal: consistent protected localhost/tivoli backup restored to an isolated protected-copy database on3307, schema changes applied only there, all11,330 original INSERT row statements across9 original tables match exactly. Matching private-key backup and snapshot hashes retained under ignored .local-setup/stays-rehearsal-evidence.json. Original database has0 active unknown-rate stays and0 conflicting active room groups at the read-only cutover check. No ordinary historical data was replaced, inferred or backfilled.

Activation requires explicit approval of scripts/setup-stays.php --confirmed-local against verified localhost:3306/tivoli during a brief write pause. Additive schema007/008, refund cursor, bank enum value and evidence-retention triggers become active only after completed schema version2. New rooms/previous vacancies need explicit inspection; short-stay prices/enabling require admin configuration. High-risk migration approval gate is from the development-team skill and repository operations policy.

Known boundaries: historical recovery POST writes pause after activation until a separately reviewed audited correction path exists; retained dashboards/reports show gross payment collections, not net-of-refund amounts; overnight room-night data now excludes prospectively recorded short sessions. Existing Service-room denominator discrepancy, production MySQL8 parity, tax/fiscal invoicing and debt collection after departure remain outside this release. Recovery is a controlled forward fix/write pause with later-event preservation, not destructive schema rollback.


## Local occupancy activation and UI completion — 2026-10-31

Observed activation evidence confirms schema2 installed on confirmed localhost:3306/tivoli after explicit owner approval. Fresh protected before/after snapshots compare11,330 original row statements exactly; original routing restored. The earlier occupancy section describes the pre-activation stage, not current status. No production deployment or Git push.

Fixed missing frontend workflow entries: Room Board / Guest Folios / Room Control / Activity navigation; paginated guest accounts and operational events; administrator Cleaning Complete then Inspect & Release Room directly on room cards and departed guest detail; inspection checklist; title-case occupancy/shared-navigation labels; payment notices/receipt rows naming the authenticated cashier. Existing mutation permissions, stock/payment arithmetic, database schema and historical records are unchanged by these corrections.

Executed47 DB assertions +55 HTTP assertions;16 views at1440/390/320px with action focus;7 PHP lints;5 normal-local read-only routing/asset/private-file checks. No ordinary synthetic bookings/payments were created. Read lists retain unknown provenance and do not expose raw audit JSON in the global Activity view. New directory/activity reads are audited. See OCCUPANCY.md for details and remaining operational boundaries.


## Configurable cleaning wizard — 2026-10-31

Implemented five cleaning stages seeded with all 29 owner-supplied tasks, followed by an optional-notes review. Each active item is required; conditional tasks mean the check was performed and any necessary action completed. Back retains checks, Next validates the visible stage and focuses the next stage. Without JavaScript all stages and the submit button remain accessible. The permanent chemical safety reminder and existing room-fault reporting link remain visible. Room cards lead to the wizard after checkout. Reason / Findings / Evidence is optional for cleaning and ordinary inspection; reasons for faults, repairs, financial changes and other operations remain required.

Settings → Cleaning Checklist lets administrators add items to the five sections or remove items from future checklists. It requires existing hr.settings, rooms.manage and bookings.manage authority plus administrator status, CSRF and an idempotent operation identity. Existing cleaning/inspection permissions remain admin-only; no housekeeper role or account grants were silently created. A dedicated least-privilege housekeeping role remains a separate access-policy decision.

Each configuration is a bounded full revision (maximum 75 items, 500 characters each) in the existing indexed, append-only security_audit event store, action stay.cleaning_checklist. The latest successful event is authoritative; before the first change, the versioned bundled JSON is authoritative. This small operational configuration requires no DDL or filesystem write at runtime. Retention must never prune these configuration events. Concurrent stale settings/checklists are rejected using the revision, inside the existing serialised transaction. Removal preserves earlier revisions and all completed snapshots. Each successful stay.clean event contains room, authenticated actor, timestamp, notes and the exact completed section/item snapshot. Required audit failure rolls back readiness. Cleaning cannot be newly completed a second time after awaiting inspection; a retry with the original operation identity returns the recorded result.

Verification executed: 60 synthetic database assertions; 62 HTTP assertions; 18 views at 1440/390/320px; browser checks for required-stage validation, next-stage focus, Back retention and optional-note review. PHP and JavaScript syntax checks passed. Mobile wizard screenshot inspected. Normal-local settings route redirects to authentication. Git diff --check passed (existing unrelated LOCAL-DEVELOPMENT.md line-ending warning). Synthetic tests use only the exact guarded 127.0.0.1:3307/tivoli_auth_test instance and marked HTTP fixture server. No ordinary financial data changes, migrations, Git changes or production deployment.

Recovery: forward code fix; retain all configuration/completion events. Do not roll back to code that permits checklist-free cleaning. Existing financial and guest-history contracts remain unchanged.


## Configurable final inspection — 2026-10-31

Implemented the owner's six Final inspection and release checks on the ordinary Inspect & Release Room form, replacing the single generic attestation. Reason / Findings / Evidence remains optional. Removed the requested explanatory submission paragraph from the cleaning wizard; audit collection and room-readiness protection remain active.

Settings → Inspection Checklist allows administrators to add/remove future inspection items separately from cleaning settings. The existing checklist service now accepts only cleaning/inspection kinds, using separate bundled defaults and separate append-only configuration actions (stay.cleaning_checklist / stay.inspection_checklist). Prior cleaning configuration revisions retain their original meaning. No schema or account-permission changes.

The ordinary inspect command requires every current item server-side and rejects stale revisions before changing availability. Completed inspections retain the exact checklist revision/section/item text with the authenticated actor, timestamp and optional note in the atomic stay.inspect audit. Removal affects future checklists only. A retry preserves the original operation; new inspection completion against an already released room is rejected. Audit failure leaves readiness unchanged. Existing occupied-room and unresolved-fault blocks remain enforced. The separate maintenance fault-resolution workflow is unchanged by this slice.

Executed verification: php tests/Integration/stays.php — 71 assertions; python tests/Contract/stays_http.py — 69 assertions; php tests/Integration/stays_concurrency.php — 3 concurrent scenarios; node tests/Contract/stays_browser.cjs — 20 views at 1440/390/320px plus wizard navigation and inspection focus/required-items/optional-notes checks. Nine changed PHP files linted; mobile inspection screenshot visually checked; git diff --check passed with the pre-existing unrelated line-ending warning. Tests ran only on the guarded synthetic port3307 database/marked8099 HTTP server. No ordinary financial records, migrations, Git state, push or production deployment changed. Applied to the existing local source checkout.

Recovery is a forward code fix retaining all historical configuration and inspection events. Do not restore code that bypasses the new inspection checklist. Existing admin-only room-release and settings authority is retained.


## Guest Stay Account and streamlined tabs — 2026-10-31

Implemented the requested label Guest Stay Account, Check-In/Check-Out timestamp columns and removal of the list Balance column. Active stays display Still In House; missing terminal timestamps remain Not Recorded. Full account details retain the authoritative bill; the directory no longer calculates unused per-row balances.

There is one reception tab row: Overnight, Short Stay, Guest Stay Account, Activity. Removed the Room Board/Room Control entries from that tab row, the duplicated lower tab row, the Recent Check-Outs tab/list, and the overnight legacy-billing explanatory sentence. Existing heading shortcuts/sidebar Room Control access remain. The old tab=recent URL resolves to the unified account directory, and section=folios URLs remain compatible. Existing billing, export URLs, room-state transitions, permissions and individual-guest tabs are unchanged.

The account filter defaults to the current Monday-Sunday UTC week, supports inclusive custom From/To dates and an explicit All Dates view. A stay is included when its recorded stay interval overlaps the chosen period; this keeps guests who arrived before the week but remain in house visible. Cancelled records lacking departure use their recorded arrival rather than an invented open-ended stay. Future arrivals beyond the range are excluded. Invalid/impossible/reversed/non-string dates are rejected server-side. Cursor pagination carries the active filters, and changing the filter resets the cursor. Historical rows are never deleted or rewritten. Directory reads retain the existing access audit, including the selected filter definition.

Executed: php tests/Integration/stays.php — 82 assertions; python tests/Contract/stays_http.py — 80 assertions; node tests/Contract/stays_browser.cjs — 21 views at1440/390/320px plus focus/wizard/inspection/account-navigation checks; four changed PHP files linted; mobile account screenshot visually inspected; git diff --check passed with the existing unrelated LOCAL-DEVELOPMENT.md line-ending warning. Synthetic fixtures verified against exact127.0.0.1:3307/tivoli_auth_test and marked8099 HTTP server. No migration, production deployment, Git state change, ordinary financial-data mutation or billing-rule change. Applied to the existing local source checkout.

Recovery: a scoped code fix to the controller/read-query/template; no data rollback is required. Preserve all audit events and all historical records. The UTC display convention remains unchanged.


## Room setup form refinements — 2026-10-31

Implemented Room Setup's optional Comment label and server-side acceptance of an empty comment; cleaning and ordinary inspection optional notes use the same label. Fault, stock and other financial-operation evidence requirements remain unchanged. The request was scoped to Room Setup, where Room Type and the shared Reason / Findings / Evidence field appeared together. Existing audit collection and standing-rate snapshots remain unchanged.

Room Type is a required dropdown using the original Standard, Double, Queen, King and Suite choices plus distinct types already stored in rooms, preserving custom/legacy values. New submitted types outside that list are rejected server-side; the dropdown is not an authority boundary by itself. No room types or historical rows were rewritten to populate the choices.

Record Action and Cancel now share a non-wrapping action row inside the relevant form, with a single Cancel link. The shared guest-action form uses the same arrangement. Checked button alignment at1440,390,320px; mobile screenshot inspected. Existing form identity, CSRF, authenticated actor, permissions and atomic audits remain active.

Executed: changed PHP syntax checks passed; guarded occupancy integration suite passed, including blank-comment save, invalid type rejection and legacy type preservation; HTTP suite83 assertions passed; browser22 views at1440/390/320px plus exact action-row alignment, dropdown and optional-comment checks passed; git diff --check passed with the pre-existing unrelated LOCAL-DEVELOPMENT.md line-ending warning. Tests used only the existing guarded synthetic database/HTTP server. No migration, ordinary financial mutation, Git state change, push or production deployment. Applied to local source checkout only. Recovery is a scoped code fix retaining all later audit/history; no database rollback needed.


## Optional fault description and protected attachment — 2026-10-31

Implemented Fault page Describe Fault (Optional), replacing its required Reason / Findings / Evidence field. Optional PDF/JPEG/PNG attachment, maximum5MB, is submitted with multipart encoding. Room, action owner and existing permissions remain required. A fault still blocks new room sales even when both description and attachment are omitted. Occupied rooms retain their active guest. Other operation evidence requirements remain unchanged.

FaultAttachment validates upload origin, file size, extension/content MIME agreement and image structure. Files are authenticated-encrypted using the existing HrVault/key with a separate room-fault purpose, stored under storage/private/room-faults (room-faults-test for synthetic tests) using generated opaque filenames. Original filenames and document bytes never enter the general audit. The whole private storage directory is Git-ignored and direct HTTP access is denied. No schema change or new encryption key is required.

An append-only stay.fault_attachment_saved event in the existing indexed audit store is the committed attachment registry, linked to the actual fault ID. File encryption/write precedes this audit inside the fault transaction. Required audit failure rolls back fault/room changes; an encrypted orphan may remain after a failed transaction but cannot be downloaded because no committed registry entry exists. Do not delete suspected orphans automatically; investigate before any separately reviewed cleanup. Original operation identities prevent duplicate faults/attachments on retry.

The fault log exposes Download Attachment for authorised administrators. The new fault-attachment.php endpoint requires current admin, bookings.manage and rooms.manage, validates the registry metadata and ciphertext/hash, and commits an access audit before returning bytes. Downloads use attachment disposition, nosniff, sandbox CSP and private/no-store headers. Closed fault evidence remains accessible. Audit failures prevent disclosure. Backups must cover the database registry, storage/private and the existing protected HR encryption key together. Restoring the database alone does not restore attachments.

Executed: changed PHP syntax checks passed;96 guarded occupancy integration assertions;93 HTTP assertions including multipart upload, exact download, optional description/file, invalid MIME and unauthorised download denial;23 views at1440/390/320px and existing focus/layout checks; mobile Fault screenshot visually inspected. Private storage denial checked against the isolated HTTP server. Fixed a discovered legacy collation mismatch with an explicit comparison collation, without changing stored schema or connection configuration. Git diff --check passed with existing unrelated line-ending warning; private attachment ignore rule verified. No ordinary financial/history edits, migration, Git state change, push or production deployment. Applied only to the local source checkout.

Recovery: forward code fix retaining the append-only registry, encryption key and all encrypted files; never discard committed attachments during code rollback. Original faults and historical financial records remain preserved.


## Encoding corrections and optional repair evidence — 2026-10-31

Observed double-encoded punctuation in templates/stays/index.php and public/assets/js/stays.js. Corrected known reversible mojibake sequences to their intended UTF-8 punctuation (including middle-dot separators and multiplication signs). Scanned first-party public/templates/bootstrap/src/resources; no remaining known broken-character markers found. Existing UTF-8 HTML declarations are retained. Guest names, notes, database values and financial records were not rewritten or normalised. Regression checks now detect these malformed punctuation sequences in rendered pages.

Repair now uses Comment (Optional) and offers optional PDF/JPEG/PNG evidence up to5MB. Server-side repair validation permits an empty comment. Existing admin-only permission, CSRF, operation identity and state controls remain. Recording repair leaves the fault awaiting inspection and the room blocked; it cannot make the room available by itself.

Extended the existing protected attachment service with an explicit fault/repair allowlist, separate purpose-separated encryption and separate stay.repair_attachment_saved registry/audit events. Existing fault attachment APIs default to fault and retain their original ciphertext purpose. The fault log displays Download Repair Evidence separately from the original attachment, preserving both. The same authenticated endpoint accepts kind=repair, validates permission and integrity, audits access before disclosure, and returns safe attachment/no-store headers. Storage/key/registry backup requirements and encrypted-orphan handling from the fault upload change remain applicable. No new schema, keys or public file storage.

Executed: six changed PHP files linted and JavaScript syntax checked;104 guarded occupancy integration assertions and104 HTTP assertions passed;24 views at1440/390/320px passed with character-corruption, layout and existing focus checks. Mobile Repair screenshot visually inspected. Tests include empty repair comment/file, exact repair download, preservation of original fault evidence, permissions, idempotency, audit-failure rollback and room still blocked. Source scan and git diff --check passed with existing unrelated line-ending warnings. Synthetic tests target only exact127.0.0.1:3307/tivoli_auth_test and marked8099 HTTP server. No ordinary financial mutations, history rewrites, migrations, Git state changes, push or production deployment; applied to existing local checkout.

Recovery: scoped forward code fix, preserving all existing and newly recorded registry events and encrypted fault/repair evidence. Do not discard files or restore older database history during code recovery.


## Critical-only faults, direct repair closure and weekly area audits — 2026-10-31

Implemented the owner's explicit operational-policy changes. New Routine/Urgent room faults remain visible without changing availability. Only Critical faults block new check-ins; priority is server-allowlisted. The UI advises Critical for safety/saleability defects, matching the draft SOP's escalation intent. Unsafe weekly room audits still generate a Critical fault. Occupied rooms retain their guest and occupied state.

Recording Repair now closes the fault immediately, including its comment/evidence and recorded completion time; there is no separate repair inspection/release step or fabricated inspector. The removed release_fault POST is rejected; old GET forms lead to Record Repair. Previously pending repair records can be explicitly completed through that action while retaining their existing repair fields. Fault/repair attachments remain retained.

For newly created Critical blocks, an atomic stay.room_blocked event captures the previous operational condition. The last Critical repair restores that condition; another outstanding Critical fault keeps the block. Dirty rooms return to cleaning, ready vacant rooms return directly to readiness, and occupied rooms remain occupied. Checkout/cleaning transitions occurring during a fault are respected. Existing blocked rooms without recorded prior-condition evidence fall back to unverified instead of inventing readiness. No bulk historical state backfill or guessed inspection occurred. Repair closure and block-clear audit failure roll back the operational transaction.

Weekly Room Audit now accepts optional Action Owner / Due Date and optional Comment. Result and room remain required. Owner/comment omission cannot bypass an Unsafe result's Critical room block. Financial-operation evidence requirements are unchanged.

Added Weekly Area Audit in the main menu and Other Area Audits beside room audit navigation. Administrators with current bookings.manage and rooms.manage authority can record shared/service/outdoor area checks (cleanliness/waste, access/exits, visual lighting/electrical condition, security, water/drainage, equipment/supplies). Area name is free text with common-area suggestions; audit date and every check result are required. Pass, Needs Attention, Unsafe and Not Applicable are allowed, with at least one applicable check. Future completion dates are rejected. Week grouping is Monday-Sunday UTC; Current Week, All Weeks and cursor paging are available.

Needs Attention/Unsafe creates an Open follow-up. Admins can record In Progress/Completed, owner, due date and optional comment. Overdue follow-ups are flagged. Follow-ups require the current event version; stale writes are rejected, retries do not duplicate events, completed audits cannot be silently reopened. Record a fresh audit for a new issue. View History retains the original checklist, status/assignment/comment changes, actor and actual record timestamp, with older-history pagination. Backdated audit dates are distinct from actual record time. Area findings do not automatically change guest-room availability; necessary physical safety restrictions must be applied operationally.

Area records and follow-ups use the existing indexed append-only security_audit store (stay.area_audit / stay.area_followup, target_type area_audit and opaque audit_key). These events are authoritative business records and must never be pruned as disposable logs. Checklist labels/results are snapshots. No schema migration, new keys or role grants were needed. Read access is audited; general Activity omits noisy area-register read events. Financial calculations, source IDs/history and encrypted-file controls are unchanged.

Executed:128 guarded occupancy database assertions;117 HTTP assertions;28 views at1440/390/320px with focus, layout, optional-field and character-corruption checks;9 changed PHP files linted; mobile area-audit screenshot inspected; source mojibake scan and git diff --check passed (existing unrelated line-ending warnings). A Windows locale-default read in a staging script caused an encoding regression during preparation; regression tests caught it, it was corrected before handoff, and AGENT.md now requires explicit UTF-8 reads/writes. No stored guest/financial values were normalised. Tests used only exact127.0.0.1:3307/tivoli_auth_test and marked8099 HTTP server. No ordinary database fixtures, schema migration, Git state change, push or production deployment. Source changes applied to the existing local checkout.

Recovery: forward operational code fix preserving all fault closures, readiness/context events, audit/follow-up records and encrypted attachments. Older code that reintroduces blanket fault blocking or a removed release gate cannot be resumed blindly. Existing unknown prior readiness is explicit. This weekly-area workflow is manual recording/follow-up, without scheduled reminders or an inferred required-area master list.


## Simple Area Audit and configurable area list — 2026-10-31

Implemented the owner's requested Room Audit-style form: required Area dropdown, optional Action Owner / Due Date, required overall Result (Pass / Corrective Action / Unsafe), optional Comment, and horizontally aligned Record Action / Cancel. Removed the six separate checks, free-text area and manual audit-date fields from new submissions. Actual audit date/week is captured on the server. Corrective/Unsafe follow-up and existing history remain operational.

Settings → Audit Areas lets authorised administrators add, rename, deactivate and reactivate areas. Defaults are the previous eight suggested shared/service/outdoor areas. Stable area IDs and versioned full catalogue snapshots are stored in append-only stay.area_catalogue events; no schema migration or inferred mapping of earlier free-text audits. Settings require existing admin, hr.settings, rooms.manage and bookings.manage authority, CSRF and persistent operation identity. The catalogue allows at most100 entries,120-character names, rejects duplicate names and stale revisions, and retains inactive entries rather than deleting referenced identities.

New audits require a currently active area ID and the current area-list revision server-side. The area name/ID/revision are snapshotted, so later rename/deactivation cannot change past audit display or prevent historical follow-up. Older detailed audits retain their original checks and dates. The register renders both forms without rewriting history. Empty optional fields are accepted, while invalid overall results/areas and stale forms are rejected.

Executed:134 guarded DB assertions;121 HTTP assertions;29 views at1440/390/320px plus exact simplified-form/action-focus checks;11 changed PHP files linted; mobile Area Audit screenshot visually inspected; source encoding scan and git diff --check passed with existing unrelated line-ending warnings. Tests cover new Settings area creation, rename snapshot retention, deactivation rejection, reactivation, permissions and stale settings/forms. Existing stock/payment/room-lifecycle and audit follow-up regressions remain covered. Used only exact127.0.0.1:3307/tivoli_auth_test and marked8099 HTTP server. No ordinary historical/financial changes, migrations, new role grants, Git state changes, push or production deployment. Applied to local source checkout only.

Recovery: scoped forward code fix, retaining all catalogue revisions and audit/follow-up events. Do not restore an older free-text area writer that bypasses the active-list policy. Catalogue events are authoritative operational records and must not be pruned.


## Finance & Cash Control — 2026-10-31

Implemented the approved Cash/MoMo prototype in the actual application source: nine finance sections, full next-day cash conversion, manual wallet evidence matching, existing expense approval integration and separate MoMo settlement, monthly payroll support, provider invoices, room/extras management estimates, retained daily/period snapshots and audit controls. Additive schema009 and exact-target guarded installer are prepared. Synthetic setup completed; ordinary database setup not executed. Historical financial records and legacy report definitions retained.

Executed:35 finance integration assertions,4 real concurrency scenarios,12 review assertions,11 schema-rerun complete-field preservation checks,37 HTTP assertions and19 views at1440/390/320px plus action focus. Existing occupancy134 integration/121 HTTP/3 concurrency, expenses52/36/4, HR49/55/3 passed. See [finance implementation](../FINANCE_CONTROL.md) and [change record](changes/2026-10-31-finance.md).

Limits: completed-stay room-revenue management estimate, not nightly accrual/statutory profit; manual statutory payroll inputs and MoMo evidence, no automatic money transfers or guessed historical costs. Independent review was requested but could not run because the reviewer agent hit its usage limit. Release/ordinary activation remains gated; no production deployment, ordinary migration, Git changes or push. Recovery is a forward code correction retaining all finance/source events.


## Finance Apache route fix — 2026-10-31

Reproduced unauthenticated GET /app.tivoliguesthouse/finance.php returning403 while dashboard.php returned302: finance.php was missing from the Apache rewrite allowlist. Added only that endpoint to the existing protected public-page routing and added finance route/CSS/JS to local-structure smoke checks. No authentication bypass, database activation, migration or financial record change. PHP built-in-server tests had not exercised Apache .htaccess; this missing deployment-route check is now covered.


## Owner-authorised local finance activation — 2026-10-31

Owner explicitly instructed activation after the outstanding independent-review limitation was disclosed. Executed the prepared additive installer only on verified XAMPP 127.0.0.1:3306/tivoli, with datadir C:/xampp/mysql/data. Activated at2026-10-31 18:21:43 UTC. Finance marker, all eight immutable-history triggers, cash register and summary read queries verified. Complete ordered row hashes before/after matched for bookings, payments, expenses, booking_extras, stock_movements, stay_refunds, stay_departures and stay_receipts. No historical field/identity changes, backfill, synthetic fixture insertion, external payment, Git change, push or production deployment. Cash tracking starts at the activation cursor; earlier cash custody remains unknown. Independent review remains outstanding; this local activation is not a production-release approval. Recovery remains a forward fix retaining every event and the original activation cursor.


## Finance workflow update — 2026-10-31

Implemented provider phone/private evidence, optional daily close/reopen comment, optional expense receipt upload, Cash expense settlement with separately funded full cash-deposit obligation, Payment Log date/direction/pagination controls and non-guest activity receipts/advances. Legacy invoice/reference/note history and old Payments URL retained. Reused existing immutable events/audit registry; no migration or ordinary local database financial change. Source applied to existing local checkout; no push or production deployment.

Executed:45 new integration/32 new multipart HTTP assertions;35 finance regression/6 concurrent races/12 review/11 setup preservation checks;41 finance HTTP assertions;24 browser views at1440/390/320px plus action focus and visual screenshot review; existing expenses52/36/4 and occupancy134 integration;24 Apache route/privacy checks. Independent scoped review findings fixed and rechecked, with no further blocker reported in that scope. See [complete change record](changes/2026-10-31-finance-workflows.md). Retain evidence files/key and all activity receipt allocations during any forward repair.


## Checkbox-only Deposit Confirm — 31 October 2026

Owner requested removal of Exact Wallet Credit and Wallet Statement / Evidence Reference inputs. New forms require only the wallet-check attestation and Record Action, with the existing deposit/reference/fee and server-derived expected net credit shown as read-only information. The server derives credit from the immutable gross amount minus recorded fee. It requires administrator authority and the checked attestation; older open forms may still submit amount/evidence, but conflicting legacy amounts are rejected and existing evidence is retained. New events explicitly record operator_attestation and credit_minor; missing evidence is left empty, never fabricated. This remains a manual operator check, not merchant API verification.

Executed:37 finance integration assertions,42 finance HTTP assertions and12 focused checkbox-confirmation HTTP assertions; changed PHP lint passed. Covered missing checkbox, role denial, fee arithmetic, legacy amount mismatch, no-input success, retry identity, confirmed register state and unchanged earlier payment/expense fields. The focused HTTP harness initially included checkbox value1 even for its unchecked scenario; corrected it to omit unchecked controls as browsers do. No application guard or expected amount was relaxed. Independent reviewer finance_changes_review rechecked the delta read-only and found no blocker; its suggested read-only expected-credit display was added and verified by HTTP. No ordinary database financial writes, migration, history rewrite, Git-state change, push or production deployment.


## Consolidated Property & Audits navigation — 1 November 2026

Replaced separate Rooms and Weekly Area Audit sidebar entries with Property & Audits. Both room-control and other-area audit pages share exactly four tabs: Room Master, Out-Of-Order Log, Room Audits and Other Area Audits. Removed the duplicate two-tab and four-tab rows. Active tab/sidebar state, matching workspace heading/breadcrumb and a visible selected-tab colour are consistent on registers and action forms. Existing rooms.php and occupancy section/tab URLs, forms, access checks, settings, filters and historical audit/financial records remain unchanged. Room/fault/audit action forms select their corresponding tab.

Executed: six affected PHP files linted;121 existing occupancy HTTP assertions passed on marked synthetic HTTP8099;8 register/form views at1440/390/320px verified four tabs, one selected tab/menu item and no page overflow or encoding errors; mobile screenshot visually inspected. No schema, ordinary-database writes, new tests/fixtures against ordinary data, Git-state changes, push or production deployment. This is a reversible presentation change; restore the scoped navigation files if needed without touching audit history.


## Consolidated Extras & Stock / Finance & Cash menus — 1 November 2026

Replaced Manage Extras, Extra Sales and Stock sidebar entries with one Extras & Stock entry. Shared permission-filtered tabs link to the existing catalogue, sales report and stock routes; the landing route follows the user's existing accessible capability. The native Extra Sales report retains its original queries, date/extra filters and exports. Extras/stocks pages and report select the same parent menu.

Removed standalone Expenses from the sidebar and included its existing capture/approval page in the shared Finance & Cash navigation. Expenses has Capture & Approval and administrator-authorised Expense Payments sub-tabs. Clerk expense submission remains accessible; direct finance expense-payment restrictions are unchanged. The pending-expense badge moves to Finance & Cash. Expense-only accounts land on their existing expense page instead of an unrelated money overview. Main menu/tab selection and breadcrumbs remain consistent. No endpoint permission, financial writer, amount calculation, ledger, schema or historical record was changed. All old URLs remain usable.

Executed: nine changed PHP files linted;36 existing expense HTTP assertions and42 finance HTTP assertions passed on marked synthetic HTTP8099. Six catalogue/stock/report/finance/expense views checked at1440/390/320px for one parent menu, selected tabs and no overflow/encoding errors; separately verified cashier expense submission navigation and hidden administrator payment tab. Mobile screenshot visually inspected. Isolated browser9233 blocked the legacy report's external CDN only during QA; application dependencies were not changed. No ordinary database writes, migration, Git-state changes, push or production deployment. Recovery is a scoped presentation-file restore, retaining every financial/audit record.


## Payroll Request evidence field removal — 1 November 2026

Removed Calculation / Attendance Evidence from new Payroll Request forms at the owner's explicit request. The service accepts an omitted comment, stores empty evidence rather than invented text, and preserves any old comments supplied by existing forms. Attendance/leave/overtime/deduction attestation, salary permissions, exact payroll calculations, separate approval, immutable history and audit remain unchanged.

Executed:changed service/view PHP lint;37 finance integration assertions, including payroll without comment and exact gross/net amounts;43 HTTP assertions, including removed evidence field and retained attendance checkbox. All workflows used only verified synthetic3307/tivoli_auth_test and marked HTTP8099. No ordinary database changes, migration, financial history rewrite, push or production deployment.


## Stock In / Save Extra reason field removal — 1 November 2026

Removed Reason / Findings / Evidence from Stock In and Save Extra forms as requested. Both service actions now accept an omitted reason. Previously supplied notes remain valid and historical notes are retained. Stock Count adjustments still require a reason; authorisation, exact price/quantity validation, immutable movements, actor/time attribution, operation identity and transactional audit are unchanged.

Executed:changed service/view PHP lint;136 occupancy integration assertions, including creation/receipt without notes, empty movement note and missing-adjustment-reason denial;121 occupancy HTTP assertions;three focused rendered-form checks. Tests used only the verified synthetic database/marked HTTP service. Independent scoped read-only review found no blocker. No ordinary database data changes, migrations, history rewrite, push or production deployment.

## Owner Reports — 7 November 2026

Added the owner-only visual dashboard to the local source checkout. Daily Overview, Room Performance, Cash & MoMo, Extras and Monthly Review use prepared, read-only queries and date-aware coverage labels. Kept historical capacity rates, extras allocations and operating result unavailable pending evidence and agreed policy. The original brief is in docs. The [change record](changes/2026-11-07-owner-reports.md) contains the source map, isolated verification and disable procedure. No schema migration or production deployment.

## Administrator landing page — 7 November 2026

Owner Reports replaces Today in the Overview menu and becomes the landing page for administrators with reports.view. The brand link, sign-in flow and old dashboard bookmarks lead there. Clerks, administrators without the report permission, and installations with TIVOLI_OWNER_REPORTS=0 retain the Today route, subject to their existing permissions. Forced password changes retain priority. Report access, data queries and the database schema are unaffected.

Executed on marked synthetic HTTP: ten Owner Reports checks, 24 access checks and a disabled-feature fallback check. Four PHP files linted. The broader legacy layout suite passed the Owner Reports shell check, then stopped on its existing assumption that bookings.php returns 200 directly. The activated Occupancy module redirects that route with 302. Rollback is a scoped restoration of landing-page routing and navigation. No production deployment.

## Extras & Stock header and category settings — 7 November 2026

Removed both shortcut buttons and the tagline from the Extras & Stock header. Category on Save Extra is a required dropdown. Settings → Extra Categories allows authorised administrators to add, deactivate and reactivate options. The list includes existing categories. An inactive category may stay on an extra that already uses it. New assignments to inactive or unrecognised categories are rejected on the server. Category settings use the existing audited, transactional operation store with stale-form protection. No schema migration or rewrite of historic sales.

Verified nine PHP files with lint, the category workflow against marked synthetic HTTP, 136 occupancy integration assertions and 121 occupancy HTTP assertions. The focused workflow checked the header, settings navigation, dropdown, add/deactivate/reactivate, duplicate and stale submissions, existing inactive assignments and cashier access denial.

## Optional Stock Count comment — 7 November 2026

Stock Count now displays Comment (Optional). The server accepts an omitted comment and records any supplied comment in the existing movement and audit records. Tested a stock decrease without a comment and a subsequent increase with a comment on synthetic data. All 137 occupancy integration assertions and the rendered form check passed. No migration.

## Date display formatting — 7 November 2026

Date displays use dd/mm/YYYY, UTC timestamps include HH:MM:SS, workspace headings retain the long weekday date and month-only reports show the month and year. Formatting runs when rendering the page. Stored timestamps, audit payloads, submitted filters, native input values and CSV formats retain their original values. No migration.

PHP lint, focused date and export checks, the Owner Reports contract, 43 finance HTTP assertions and 121 occupancy HTTP assertions passed on the marked synthetic test service.

## SOP alignment and implementation audit - 10 November 2026

- Added the [controlled SOP](../updated_sop.md), its PDF and the [implementation gap matrix](../sop_gap_matrix.md), [database and authorization review](../sop_database_permissions_review.md), [change register](../sop_change_register.md) and [acceptance tests](../sop_acceptance_tests.md).
- Observed on-disk PHP services, legacy routes, setup/migrations 001-009, historical DDL and synthetic test guards. Findings describe checkout source. Deployment parity, installed triggers and grants, and live account state remain unverified.
- Verified the six deliverables by link and cross-reference validation, visual review of all PDF pages and destination byte comparison. Root AGENTS.md now requires the SOP while preserving the original safeguards. No application logic, migration, database record or deployment changed. Application and acceptance tests were not run.

## SOP implementation phase CR01.1 · 10 November 2026

Contained seven legacy write routes before stay schema detection. The test failed on the original room deletion request, then passed 22 audited denials and unchanged hashes over 12 protected synthetic tables. Existing access, occupancy and Owner Reports contracts passed. Independent source review found no remaining legacy writer bypass in scope. Updated the SOP matrix, database review, change register and acceptance register. This phase involved no migration or production rollout.

A rolled-back synthetic probe confirmed the G2 weekly lock gap for the default receipt and refund guard. CR02 is scoped in the change register and awaits operating decisions for late recognition, corrections and reopening.

## SOP implementation phase CR02.1 · 10 November 2026

Applied Eugene's approved open-period posting rule to ordinary lock checks and the supported late-entry forms. Tests verified exact open-period effects and the original week's unchanged totals and snapshot. Late entry needs a verified server-side owner account binding. Existing historical and departed folio rules remain in effect. No schema migration or production rollout. See the SOP change register and acceptance evidence.

## SOP implementation phase CR02.2 · 10 November 2026

Implemented separate owner-authorized departed receipt recovery and guarded additive migration 010. Verified exact accounting effects and protected checkout evidence in the isolated synthetic database. The active local snapshot has not been migrated. See the SOP change register and acceptance tests for activation, eligibility and recovery limits.

## Departed receipt recovery activated locally · 10 November 2026

At the owner's request, migration 010 was applied to the refreshed XAMPP database on loopback port 3306. The protected backup restored successfully and rehearsal preserved the original rows. The original-column row hashes matched after activation, all existing payment recovery links were null, and the only trigger change was the verified replacement payment guard. Apache and CLI both recognized the designated finance owner. The account binding lives in the ignored local configuration. No receipt was created during activation. Imported stays without a saved checkout balance remain outside this recovery workflow. See the protected local recovery-010 backup folder for the restore manifest.

## Weekly reconciliation · 13 November 2026

Implemented the approved owner weekly review, retained snapshots and three decisions. Unmatched MoMo and tracked cash obligations block closure. Carried expenses keep assignments and due dates. Independent review prompted a source-disposition check before resolving carried cases and an owner-authorized open-period approval path. No schema migration.

Earlier synthetic weekly, daily/monthly and posting-lock checks passed. Final verification of the latest fix is blocked by the dedicated test database's damaged grant and table storage. Preserve its recovery backups and establish a clean synthetic instance before completing the database, HTTP, concurrency and layout checks. This is implemented work awaiting verification. See changes/2026-11-13-weekly-reconciliation.md.
## Weekly reconciliation verified · 13 November 2026

Final verification supersedes the earlier blocked status. The corrupt synthetic directory was preserved and a fresh dedicated marked instance established. Passed 126 expense, finance, weekly and daily/monthly assertions, posting-lock verification, six existing concurrency scenarios, three close-versus-receipt races, synthetic HTTP access/CSRF/history checks and nine browser views at desktop/mobile widths. Thirteen PHP lints and JavaScript syntax passed. Independent review reported no important findings. The local application's designated finance owner binding was confirmed read-only. See changes/2026-11-13-weekly-reconciliation.md for commands, environment and boundaries.
## Paid check-in and owner checkout authority · 13 November 2026

Implemented the approved billing prototype slice. Arrival atomically records the first room receipt and guest stay, with exact minimum payment, current rate, confirmations and accounting-period guards. Debt approval now requires the designated finance owner, displayed-balance recheck and a durable reason. Older approval records are retained and non-owner approvals require fresh authorization before a new unpaid departure. Account coverage and final departure details are visible. No new schema migration.

Passed 31 billing, 137 occupancy and 138 HTTP assertions, three occupancy concurrency scenarios, affected finance/recovery/report regressions and desktop/mobile browser checks. Independent review reported no important findings. See changes/2026-11-13-checkin-payment.md for evidence and forward-repair boundaries. The separate reported-departure incident route remains pending design.

## Unauthorised departure incidents - 14 November 2026

Implemented and verified the approved separate departure workflow. Staff record discovery, nullable actual departure, facts, security and optional encrypted evidence. The provisional bill stops accruing. Secured vacant rooms can clean, inspect and return to use while debt remains. Eugene previews/finalizes actual departure and charges, disputes/retracts eligible reports, assigns follow-up and records new partial Cash/MoMo collections. Owner and weekly in-app alerts retain unresolved cases and prior reports. No external notification delivery is claimed.

Reporting appends facts without mutating the booking until owner finalization. All supported writers and affected readers honor the effective departure. Incident room revenue posts once to the current open review period, with actual physical departure separately preserved. Existing checkout evidence, closed monetary totals and original reports/receipts remain intact. Unknown historical rates require evidenced reconciliation.

Executed 453 integration checks including 16 concurrency scenarios, 210 HTTP checks, legacy denial/hash verification, responsive browser workflows at 1440/390/320 pixels, 23 PHP lints, JavaScript syntax and source encoding/diff checks. Independent source review reported no remaining important findings. See [the change record](changes/2026-11-13-departure-incidents.md) for exact commands, boundaries and recovery.

No new migration or local activation is needed on the existing migration-010 baseline. No ordinary database test writes, Git-state changes, push or production deployment. Production requires separate approval and a coordinated complete artifact. After incident events exist, forward repair must preserve them and every linked receipt/revenue event. Old code that ignores reported departures must not run alongside the supported writers.

## Staff Shift Handover - 14 November 2026

Implemented the approved staff handover workflow in the current local checkout. Outgoing staff review current guests/accounts, Cash/MoMo, rooms/faults and pending work, then submit actual duty times, recipient and optional notes with a frozen snapshot. Incoming acknowledgement can follow later. Eugene reviews discrepancies without removing original evidence. Unacknowledged handovers and unresolved transfers remain visible in Owner Attention and Owner Reports.

Physical cash transfer is separate. Pending/disputed cash remains under the sender's accountability and is reserved. Recipient acknowledgement moves custody exactly once. All supported deposit writers use the same per-receipt positions. Original collector, full deposit amount and next-day deadline remain intact. Cash By Custodian shows accountable, reserved and available funds. No physical cash-count step or deduction of cash expenses from sales deposits.

Executed 499 integration checks including 20 real concurrency scenarios, 303 HTTP checks, legacy denial/hash verification, browser flows and 21 responsive views, 17 PHP lints, JavaScript syntax and UTF-8/diff checks. Independent review findings were reproduced and fixed for custody acquisition chronology, due HR exits and short-stay deadlines. The synthetic daily-close fixture now explicitly acknowledges its test transfers before depositing all test cash. This changes no ordinary application data.

No new migration, role grant, activation, production deployment, branch/staging/commit change or push. Existing migration-010/finance-owner configuration remains required. After custody events exist, use coordinated source delivery and forward repair, retaining every original event and allocation. Old deposit writers cannot safely run concurrently. Amount corrections, write-offs, recipient reassignment and external notifications remain outside this slice.

See [staff instructions](../STAFF_HANDOVER.md) and [executed evidence and recovery](changes/2026-11-14-shift-handover.md).


## Purchase and Expense Pre-Approval - 14 November 2026

Implemented the owner-approved purchasing workflow: request, capped owner approval/revision, commitment, complete delivery/actual cost, owner posting and verified partial Cash/MoMo settlement. One canonical expense per purchase; stock movement and quantity commit atomically. Already-paid declarations require verification without duplicate payout. Explicit exceptions preserve actual chronology. Closed totals, historical records and gross cash-deposit obligations remain fixed.

Verified 552 integration checks including 25 real concurrency scenarios, 352 HTTP checks, legacy denial/hash checks, 30 responsive browser views, 26 PHP lints, JavaScript/Python syntax and scoped UTF-8/diff checks. Independent review findings were reproduced and fixed. No new schema migration or local activation, ordinary database test writes, Git-state changes, push or production deployment.

See [staff instructions](../PURCHASING.md), [verification and recovery](changes/2026-11-14-purchase-preapproval.md), and [completed plan](../superpowers/plans/2026-11-14-purchase-preapproval.md). After new purchase events, forward repair must retain expenses, stock, payments, claims and original audit history. Old writers cannot run concurrently. Partial delivery, advances, general corrections and external notifications remain separate work.

### Apache entry-point correction - 14 November 2026

Resolved localhost Forbidden for Purchase Requests and Shift Handover by adding the two missing entries to the explicit root route list. Actual Apache anonymous requests now redirect both to login; seven private/unknown paths remain blocked. Added no-database Apache route contract. Synthetic PHP-server verification had missed the root Apache mapping. No financial/database changes or service restart. See [evidence](changes/2026-11-14-purchase-preapproval.md).


## Stock Counts and Variance Approval - 14 November 2026

Implemented the approved blind stock-count wizard, variance explanations, Eugene's approval/dispute, clarification and linked recount. Submission changes no stock; approval applies an exact linked difference once, preserving later normal movements. Other adjustments and unknown provenance require a recount. Original and superseded counts remain readable, including when an old item is no longer tracked. Self-counting owner review requires an explicit exception.

Verified 598 integration checks including 32 concurrency scenarios, 377 HTTP checks, legacy denial/hash preservation, 18 responsive browser views, 18 PHP lints, JavaScript/Python syntax and scoped UTF-8/diff checks. Independent review findings were reproduced and fixed. Actual Apache route redirects to login, while private paths remain blocked.

No migration/new local activation, ordinary database test writes, Git-state change, push or production deployment. Public immediate count forms are retired; historic movements remain available. No automatic expense, valuation, staff deduction or disposal. See [staff instructions](../STOCK_COUNTS.md) and [verification/recovery](changes/2026-11-14-stock-count.md). After events exist, use coordinated forward repair preserving original counts, movements and audit evidence; old writers cannot resume as fallback.


## Housekeeping and Room Readiness - 14 November 2026

Implemented the owner-approved workflow and Select All for both checklists. Eligible individual staff complete captured cleaning steps; a different inspector releases or returns for rework. Eugene alone grants a single-attempt self-inspection exception, and completed self-inspections retain exact historical follow-up. Authorization alone never releases. Occupancy, critical faults, changed room cycles and stale attempt versions block unsafe actions. Abandoned assignments can be restarted by Eugene with a recorded reason and new factual attempt.

Verified 642 integration checks including 40 real concurrency scenarios, 415 HTTP checks, legacy denial/hash preservation, 18 responsive browser views, 23 PHP lints, JavaScript/Python syntax and scoped UTF-8/diff checks. Independent review found no important issues; malformed-input warnings and Save & Return behavior were reproduced/fixed. Actual Apache routing requires login and keeps private files blocked.

No migration/new local activation, ordinary database test writes, branch/staging/commit change, push or production deployment. The separate repair-release step remains removed. Public old clean/inspect forms redirect and deny writes; trusted setup calls cannot bypass modern attempts. See [staff guide](../HOUSEKEEPING.md) and [executed evidence/recovery](changes/2026-11-14-housekeeping.md). After events exist, coordinated forward repair must preserve original attempts, checklist snapshots, inspections, grants, readiness and audits.


## Payroll Review and Staff Payments - 15 November 2026

Implemented the approved owner-led monthly payroll workflow: preparation/revision, immutable approval, actual bounded partial/full MoMo settlement and private published employee payslips. Approval records staff cost once; settlement does not create another expense. Effective HR salary/payee revalidation, shared staff-month/reference claims, open-period posting, private evidence, recipient-bound publication and atomic audit protect money and identity. Original legacy rows remain available; old public creation is retired.

Verified 756 integration assertions including 54 concurrency checks and 503 HTTP assertions across 44 suites, legacy denial/full-row preservation, actual synthetic browser flow at1440/390/320, 23 PHP lints, JS/Python syntax, UTF-8/diff checks and anonymous ordinary Apache routing. Independent review findings were fixed and re-reviewed without remaining source blockers.

No migration/activation, ordinary database test mutations, Git-state change, push or production deployment. Existing HR/finance/encryption/owner setup is required. See [staff guide](../PAYROLL.md) and [evidence, compatibility and recovery](changes/2026-11-14-payroll.md). Posted corrections, taxes, remittances, attendance/rosters, scheduling and payment APIs remain separate. Employee settlement does not verify deduction/employer remittance.


## Financial Corrections — 16 November 2026

Implemented the approved guest-receipt slice as a Financial Corrections tab under Finance & Cash. Staff request corrections on their own receipts; configured Eugene owner reviews/posts linked amount, Cash/MoMo/reference or duplicate adjustments. Originals and closed snapshots remain fixed; changes post once in today's open period with private versions/evidence and atomic audit. Actual refunds and checked-out recovery remain separate money movements. Deposited/handed-over/matched sources require separate reconciliation; compensated duplicates remain linked to retained collections.

Verified all 48 suites: 813 integration assertions including 66 concurrency checks and 535 HTTP assertions, legacy denial/full-row preservation, synthetic browser1440/390/320, 35 PHP lints, JS/Python syntax, scoped UTF-8/diff and anonymous ordinary Apache routing. Independent source-review findings were reproduced/fixed; no additional independent re-review is claimed.

No new migration/activation, ordinary database test writes, Git-state change, push or production deployment. Existing finance/HR/evidence/owner setup is required. See [staff guide](../FINANCIAL_CORRECTIONS.md) and [evidence, compatibility and recovery](changes/2026-11-15-receipt-corrections.md). Expense/provider/payroll/charge and dependent-allocation corrections remain separate. Once correction events exist, use coordinated forward repair preserving all originals, claims, cursors, versions, files and audits; no isolated reader/writer rollback.


## Expense Corrections — 17 November 2026

Implemented the approved ordinary-expense slice within Finance & Cash → Financial Corrections → Expenses. Select a source, submit one live-preview form, then Eugene reviews and confirms. Original expenses/payouts and closed snapshots remain fixed; current-period compensating cost/outflow effects do not move money. Private versions/evidence and atomic audit are retained. Actual payer is explicit metadata, never inferred from recorder; unknown payer and unsupported linked purchases require reconciliation. Supplier claims/refunds, stock, provider and payroll corrections remain separate.

Verified 52 suites: 862 integration assertions including 73 concurrency checks and 565 HTTP assertions, legacy22 denials/full-row preservation, actual browser 1440/390/320,31 PHP lints, JS/Python syntax, scoped UTF-8/diff and anonymous ordinary Apache. Independent review findings were reproduced/fixed. A late report timeout was traced to existing housekeeping attention reads; metadata prefiltering preserves exact alert/follow-up records and was verified with characterization and affected regressions. No history or timeout gate was removed.

No new migration/activation, ordinary authenticated database test writes, Git state change, push or production deployment. See [staff guide](../EXPENSE_CORRECTIONS.md) and [evidence/recovery](changes/2026-11-17-expense-corrections.md). Existing finance/HR/evidence/owner setup is required. After events exist, retain all originals, cases, aliases, operations and audit and use coordinated forward repair; no isolated reader/writer rollback.


## Cash & MoMo Discrepancy Resolution — 17 November 2026

Implemented and verified locally as workflow 1 of the approved sequential ten-workflow program. Source-linked reports, partial wallet verification/reversal, evidenced cash return, named-recipient partial handover acknowledgement, private evidence, retained reopen/follow-up and persistent idempotency preserve originals and closed snapshots. Finance/custody/matching/report/payment-log readers agree; overlapping old writers are guarded.

Executed 18 integration suites (371 assertions, including 6 new concurrency checks), 9 HTTP/route suites (197 assertions), 9 browser views at1440/390/320, 25 PHP lints and script/encoding/diff checks. Independent source findings were corrected and rechecked. Fee-bearing and ambiguous-custody cases remain evidence-gated; no guessed balancing/write-off. No migration/activation, ordinary database test mutation, Git-state change, push or production deployment. See [staff guide](../CASH_DISCREPANCIES.md), [evidence/recovery](changes/2026-11-17-cash-discrepancies.md) and [remaining sequence](../CASH_WORKFLOW_ROADMAP.md).


## Corrections After Deposit or Handover — 18 November 2026

Workflow 2 implemented and verified locally in existing Financial Corrections: exact linked funding review, conserved immutable deposits/handovers/merchant proof, open-period append-only adjustments, persistent idempotency and atomic audit. Original actors, checkout facts and closed snapshots remain retained. Independent source findings reproduced RED, fixed GREEN and rechecked.

Executed 22 integration suites (412 assertions), 9 HTTP/access/route suites (215 assertions), nine responsive browser views, 27 PHP lints and script/encoding/diff checks. No schema activation, ordinary database test writes, Git mutation, push or production deployment. Unsupported evidence stays blocked. See [staff guide](../FINANCIAL_CORRECTIONS.md), [evidence/recovery](changes/2026-11-18-receipt-reconciliation.md) and [remaining sequence](../CASH_WORKFLOW_ROADMAP.md). Next is Guest Charge Corrections & Refunds.


## Guest Charge Corrections & Refunds — 18 November 2026

Workflow 3 implemented and verified locally in existing guest accounts and Financial Corrections. Source-linked room/extra/incidental adjustments, one owner approval, private evidence/history, exact open-period revenue/account effects and separate owner-recorded actual Cash/MoMo refunds preserve originals, stock, gross deposit obligations, departure facts and closed snapshots. Durable idempotency, fresh versions, shared locks and atomic required audit protect writes.

Executed 29 integration suites (685 assertions), 12 HTTP/access/route suites (265 assertions), twelve responsive views, 31 PHP lints and script/encoding/diff checks. Independent source findings reproduced/fixed/rechecked. No new schema activation, ordinary database test writes, Git mutation, push or production deployment. Evidence-gated historical/unfinalized cases remain blocked. See [staff guide](../GUEST_CHARGE_CORRECTIONS.md), [evidence/contracts/recovery](changes/2026-11-18-guest-charges.md), [sequence](../CASH_WORKFLOW_ROADMAP.md). Next: Purchase & Provider Corrections.


## Purchase & Provider Corrections — 19 November 2026

Workflow4 implemented and verified locally in existing Financial Corrections. Source-linked cost/specific payout/stock corrections, one owner decision, partial Cash/MoMo settlement, private evidence/history, exact open-period projections, persistent idempotency and atomic required audit preserve originals and closed snapshots. Supplier recoveries remain visible for separate evidenced money recovery. Unknown historical links and already-counted stock remain gated.

Executed 38 integration suites (891 assertions), 15 HTTP/access/route suites (360 assertions), twelve responsive browser views and 33 PHP lints plus script/encoding/diff checks. Independent review findings fixed and rechecked. No new schema activation, ordinary database test writes, Git mutation, push or production deployment. See [staff guide](../PURCHASE_CORRECTIONS.md), [contracts/evidence/recovery](changes/2026-11-19-purchase-corrections.md) and [sequence](../CASH_WORKFLOW_ROADMAP.md). Next: Payroll Corrections.


## Payroll Corrections — 19 November 2026

Workflow5 implemented and verified locally in existing Payroll & Payslips and Financial Corrections. Source-linked component/specific recorded-transfer corrections, exact open-period projections, existing MoMo top-ups, private recovery follow-up, retained original payslip versions, frozen recipient guards, durable idempotency and atomic required audit preserve originals and closed snapshots. Employer-only costs stay private without unnecessary employee reissue.

Executed 31 integration suites (671 assertions), 13 HTTP/access/route suites (310 assertions), twelve responsive browser views and 29 PHP lints plus JavaScript/Python syntax, UTF-8 and scoped diff checks. Independent source and final delta review reported no Important findings. No new schema activation, ordinary database test writes, Git mutation, push or production deployment. Legacy sources lacking frozen evidence and actual monetary recovery remain separate. See [staff guide](../PAYROLL_CORRECTIONS.md), [contracts/evidence/recovery](changes/2026-11-19-payroll-corrections.md) and [sequence](../CASH_WORKFLOW_ROADMAP.md). Next: Stock Damage, Expiry & Complimentary Use.


## Local payroll activation verification — 19 November 2026

Owner requested activation after local implementation. Executed a guarded read-only preflight against the ordinary local application's configured database, `127.0.0.1:3306/tivoli_fresh_20261110140907`. The original guard expecting `tivoli` stopped before connecting; the observed configured target was then pinned explicitly. Required finance/HR/event/operation/claim/audit tables, existing finance activation, configured owner's salary authority and employment eligibility, and HR encryption configuration passed. No credentials or keys were printed.

Created only the previously absent empty `storage/private/finance-evidence` directory; the existing attachment writer otherwise creates this directory lazily on first upload. No application code, database records, schema, permissions, credentials or connection configuration changed. Read-only database inspection ran inside a rolled-back READ ONLY transaction; no financial test or fixture ran on this ordinary database.

Ordinary Apache checks: login200; Payroll Corrections302 to login; Financial Corrections payroll route302; direct private evidence directory403. Owner-authenticated rendering and financial writes were not exercised against the ordinary database during activation. The earlier synthetic suite/browser evidence remains separate. No production deployment, Git mutation, import, reset or financial correction was performed.

Payroll Corrections is available locally at `http://localhost/app.tivoliguesthouse/payroll.php?page=corrections` to the configured owner with salary authority; no new schema activation was necessary. Existing original/effective history and recovery safeguards remain in force.


## Stock Damage, Expiry & Complimentary Use — 21 November 2026

Workflow6 implemented and verified locally within Extras & Stock: immediate holds, owner decisions and actual disposition, exact previously-counted loss linkage, encrypted optional evidence, safe mistaken-report correction, durable idempotency and atomic audit. Sales, supplier returns and physical counts retain holds and avoid duplicate deductions. Original financial and stock evidence remains retained; no invented cost or revenue.

Executed 26 integration suites (719 assertions), 13 HTTP/access/route suites (348 assertions), twelve responsive browser views and 27 PHP lints plus JavaScript/Python syntax, UTF-8, scoped diff and new-route checks. Independent review findings reproduced and fixed. Owner Reports timeout resolved by an equivalent open-case attention reader; original timeout unchanged. No schema activation, ordinary database test writes, Git mutation, push or production deployment. See [staff guide](../STOCK_NON_SALE_ISSUES.md), [evidence/recovery](changes/2026-11-21-stock-issues.md), [sequence](../CASH_WORKFLOW_ROADMAP.md). Next: Stock Costing & Profit Accuracy.


## Stock Costing & Profit Accuracy — 21 November 2026

Workflow7 code is implemented locally inside the existing Finance Profit Review, with owner opening/evidence review, automatic source capture, exact weighted-average pools, unknown-cost/partial-cutover disclosure and retained weekly/monthly cost snapshots. Existing recorded-cost summary contracts and original financial/stock records remain intact. Four independent review findings were reproduced and fixed with regression tests. Executed 51 automated suites / 1,483 assertions, twelve responsive browser views and 27 PHP lints; final responsive evidence is recorded in [the change record](changes/2026-11-21-stock-costing.md). This is a local implementation, not a production-release claim. See [owner guide](../STOCK_COSTING.md). No migration/backfill, ordinary database test writes, Git mutation, push or production deployment.


## Weekly Audit Follow-Up — verified 22 November 2026

Implemented locally in Property & Audits using original room/area audits, named owner-issued audit duty, assignment/deadline, own-work completion and owner verification/rework. Historic source/financial records and original completed actions remain intact. Explicit source reuse, all-linked Critical-fault guard, optional encrypted 5 MB evidence, versioning, unique source association and persistent operation keys are enforced. No new repair-release workflow or financial posting. Independent review findings were reproduced/fixed; 13 automated suites / 538 PASS assertions, 18 PHP lints and 13 responsive views passed. No migration, ordinary database fixtures/configuration/Git changes or production deployment. See [staff guide](../WEEKLY_AUDIT_FOLLOW_UP.md) and [evidence/recovery](changes/2026-11-21-weekly-audit-follow-up.md). Eugene must explicitly authorize the real auditor account in Audit Duty. Next: Owner Exception Register.


## Owner Exception Register — 22 November 2026

Implemented locally as one configured-owner linked queue in Overview / Owner Reports. Open work carries across weeks; Show Details → Open Existing Workflow retains original responsibility, deadlines, decisions and source IDs. No generic posting/closing workflow. Private salary/source privileges remain enforced; missing/stale sources remain explicit. Source-critical commands retain existing idempotency, audit, CSRF and version/lock controls. Independent backend/UI review findings reproduced/fixed. Executed 13 automated suites / 253 PASS assertions, 19 PHP lints and 12 responsive views, plus JS/Python syntax, UTF-8 and delivered-file/route checks. No migration/activation, normal database fixtures/configuration/Git changes or production deployment. See [owner guide](../OWNER_EXCEPTION_REGISTER.md), [evidence and recovery](changes/2026-11-22-owner-exceptions.md) and [sequence](../CASH_WORKFLOW_ROADMAP.md). Actual payroll recovery and legacy standalone settlements retain their explicitly disclosed limits. Next: Reminders & Escalations.


## Reminders & Escalations — 22 November 2026

Owner-approved workflow10 is implemented locally: personal source-linked reminders/history, daily/overdue/Critical escalation, own optional Email/WhatsApp settings, owner rules/verified contacts and retained read/send/receipt history. Original source authority, financial balances, cash deposit duty, completion/verification and closed snapshots remain intact. Unique durable claims, persistent operation keys, shared locks and required atomic audit protect operational writes; notification payloads are encrypted and zero-value. No schema activation or migration required.

Executed **12 automated suites / 283 PASS assertions**, including repeated original-source setup; **16 responsive views** at 1440/390/320 px; **35 PHP syntax checks**, JavaScript/Python syntax, UTF-8, explicit delivered-file parity and route checks. Four anonymous ordinary Apache checks passed (login redirect, unsigned callback refusal, private source/example denial). Delivery uses fake transports only. Exact financial summary and every original journal/payment/expense/refund/stock row remain unchanged in isolated comparisons. Real-process races verify one daily slot and one fake external attempt; unknown outcomes, stale contacts, permission revocation, bounded retries, required-audit rollback, callback ordering and obsolete backlog suppression passed. Independent findings were reproduced and fixed.

The exact-target local reminder worker ran successfully with zero external delivery attempts. The five-minute local Windows task is installed and its first scheduled run completed with result0. It requires the current Windows user signed in and XAMPP running. Real Email/WhatsApp service setup, verified contacts and production scheduling remain separate. No normal-data fixtures, financial source writes, credential/configuration/Git changes, push or production deployment. See [staff guide](../REMINDERS_ESCALATIONS.md), [evidence and recovery](changes/2026-11-22-reminders.md) and [sequence](../CASH_WORKFLOW_ROADMAP.md).
