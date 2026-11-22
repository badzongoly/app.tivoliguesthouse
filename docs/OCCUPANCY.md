# Check-in, occupancy, guest bills and extras

Implementation date: 2026-10-30. **Activated on the confirmed local localhost/tivoli database following explicit approval. No production deployment.**

## Reception workflow

The main menu opens Check-in & occupancy (`occupancy.php`) and Manage extras (`occupancy.php?section=extras`). Overnight, Short stay and Recent check-outs share one physical room inventory. Recent departures include only actual check-outs. All times remain UTC.

Check-in requires guest name, stay type, mobile number, gender and nationality (Ghana by default). ID type, private ID upload, payment type and verification attestations are optional. The private upload accepts validated PDF/JPEG/PNG up to 5 MB, subject to the PHP upload limit. It is authenticated-encrypted in the database using a purpose-separated key derived from the existing private HR key. It never enters public storage or the shared audit payload. Clerks may download an in-house guest's ID; admins retain historical access. Downloads require a committed access audit. Back up the private key with database snapshots.

Check-in records server time and captures the standing rate. A short stay has a two-hour due time and one fixed session charge. Overruns remain occupied and incur no invented fee or automatic overnight conversion. Overnight calculations preserve the existing next-day-noon rule: exactly noon is one night; one second beyond the boundary adds a night. Existing rates and source IDs are never rewritten.

Show Details displays guest/stay identity above Bill, Payments, Statement preview and Activity. Changed/new writes use one PDO transaction and the existing atomic audit service. The actor is derived from the authenticated session and checked against current authority inside the transaction. Cashier-recorded cash/MoMo/card/bank immediately reduces the guest balance; no provider verification is implied. The check-in receipt-attestation checkbox alone cannot invent a payment. A payment needs an actual amount/method; the generated payment ID is the receipt identity. External references remain optional.

Extras default to Pay now, creating both the purchase and its payment atomically. Charge to room creates only the purchase. Prices are server-derived snapshots; tracked stock is locked, checked and deducted with the actual captured sale identity. Existing fractional quantities are retained. New purchases must produce an exact pesewa total rather than introduce an unapproved sub-pesewa rounding policy. Legacy aggregate totals retain decimal sum-then-round semantics.

Incidentals require a reason and separate administrator review; pending/rejected requests do not count. The maker cannot approve their own request. No charge-credit workflow, reservations, extensions or weekly revenue/pricing review is included.

## Checkout and overpayments

With a positive balance, Confirm departure is hidden. Clear all outstanding opens a full-payment form and then a server-session-bound confirmation showing the guest, room, amount and method. Only final confirmation records the receipt, and it returns to the checkout form. Departure requires a separate confirmation; optional departure notes remain optional. The removed authority/handover text field is absent.

An admin can record a specific unpaid-checkout exception with a maximum balance, actor and timestamp. This permits clerk confirmation; an increase beyond the authorised balance requires a new exception. Checkout re-locks and rechecks status, room ownership and balance using one captured departure instant. It records actual departure, freezes the terminal bill evidence and sets the room dirty/cleaning. A stale/double checkout cannot vacate another occupant's room. Payments and guest purchases after departure are rejected through the service and the old entry points.

Overpayments remain visible as money owed back to the guest. Only the configured finance owner can record an actual completed Cash/MoMo refund, with actual time, payer, completion attestation, proof and reason, bounded by both current guest credit and the remaining effective original receipt. Terminal stays require verified final bill evidence. See [Guest Charge Corrections & Refunds](GUEST_CHARGE_CORRECTIONS.md). This is a linked immutable event, never a changed/deleted payment. A captured refund cursor ensures post-departure refunds adjust the current account without double-counting pre-departure returns. No external payment/refund is sent. Collection of an approved debt after departure requires a future separately reviewed recovery workflow; post-checkout payments are deliberately not re-enabled.

## Room and extras administration

Room master allows admins to create rooms and change their standing overnight/short rates with a reason. The same rate applies every day until another authorised permanent change. Changes apply to future stays, not captured current/historical stay rates. New or previously unverified vacant rooms require explicit inspection/release; installation does not claim that historical vacancy proves inspection. Short stays are initially disabled until a positive short rate is configured.

Faults immediately block new sales while preserving existing occupants. Maintenance records a tested repair; inspection must sign off before release. Cleaning completion leads to awaiting inspection, not saleable readiness. Weekly room audits retain findings/owners; unsafe findings create a linked operational block. These are room-condition audits, not weekly ADR reviews.

Manage extras supports admin product/service creation, price/category/active/low-stock metadata, stock receipts, physical-count adjustments, and a movement register. New products start at zero stock. Services do not consume stock. Deactivation stops new sales while preserving prior names/prices/quantities and stock history. Clerks can view the catalogue and sell active items; master/stock mutations also require their specific capability and current admin status.

## Data and compatibility

New operations write to the existing bookings, payments, booking_extras, extras, rooms and stock_movements using a single connection so existing read reports retain their source identities. Companion tables in migration 007 hold prospective metadata, receipts, operation identities, incidentals, checkout approvals, terminal evidence and operational logs. Migration 008 adds linked refunds; the shared installer adds a refund cursor. It appends bank to the existing payment enum without replacing financial rows. No actor, historical rate, stock provenance or identity match is guessed or backfilled.

Completed setup version2 gates new reads/writes. Once activated, GET aliases for the old bookings/checkin/checkout/rooms/extras/stock entry points route into the new system; old POST forms are rejected rather than bypass new controls. Booking CSV routes to the same authoritative bill calculation. The first eleven columns remain, with Stay Type, Incidentals and Balance/evidence appended. Short stays do not claim overnight nights; unknown historical rates are blank/explicit, never substituted from today's tariff. Exports retain the existing recent100 limit and filter contract, with stricter date validation and formula-safe cells.

Short sessions are excluded prospectively from overnight room-night datasets in dashboard/reports. Their other existing report definitions remain, including the documented Service-room denominator inconsistency. **Legacy payment charts are gross collections, not net-of-refund cash/revenue.** Refund evidence is visible on guest accounts. A full reporting/cash reconciliation redesign is outside this change.

All old historical-recovery POST operations are intentionally paused after activation because they can bypass the new financial/stock rules; the historical view remains accessible. A usable audited correction/recovery replacement is a follow-up release capability. Do not re-enable the unsafe legacy writer to work around this restriction. Legacy stays with unknown room rates require evidenced review before new financial mutations; ordinary historical rows remain visible and unchanged.

## Verification executed

- PHP8.2.12 / local MariaDB10.4.32; production MySQL8 parity remains unverified.
- `php tests/Integration/stays.php`:47 assertions including exact boundaries, idempotency, encryption/access, stock, approvals, frozen bills, refunds, and audit-failure rollback.
- `python tests/Contract/stays_http.py`:38 assertions including permissions, CSRF, both confirmation steps, old writer denial, optional reference, private-state handling, and CSV.
- `php tests/Integration/stays_concurrency.php`:3 separate-process barrier races for check-in, full settlement and last-item sale.
- Existing `access_http.py`:24 assertions; existing `expenses_http.py`:36 assertions.
- `node tests/Contract/stays_browser.cjs`:14 views at1440/390/320px, page-overflow checks and actual action-field focus. Desktop/mobile screenshots were inspected.
- PHP/JS syntax checked; private configuration, `.local-setup` backups and private storage excluded from Git.
- `php scripts/rehearse-stays.php --confirmed-local-rehearsal`:consistent source backup restored to a separate protected database on3307; schema installed there; all11,330 original row statements across the nine original tables compared exactly. Source localhost/tivoli was not migrated. Protected paths/hashes are recorded only in ignored `.local-setup/stays-rehearsal-evidence.json`; matching private-key backup retained.
- Independent source review/recheck found no remaining concrete P1/P2 blocker in reviewed scope. This does not certify production parity or unrelated legacy modules.

Synthetic services use only exact configured127.0.0.1:3307/tivoli_auth_test and marker synthetic-auth-only-v1. HTTP fixture checks require fresh `stays.php` integration fixtures before each complete HTTP run (the HTTP journey checks out its room). Never run these fixtures against localhost/tivoli or production.

## Local activation and recovery

Approval is required before the concrete high-risk local migration. Review the protected rehearsal evidence and arrange a brief write pause, since DDL implicitly commits and old in-flight forms must expire.

After approval, with `TIVOLI_TEST_MODE` unset and the exact target reverified:

```powershell
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\app.tivoliguesthouse\scripts\setup-stays.php' --confirmed-local
```

The installer checks the exact local DSN and existing InnoDB source tables, adds companion structures and retention triggers, and marks setup complete last. It does not seed guest/staff records, infer prior inspections or enable unknown short-stay prices. After installation, inspect/release genuinely ready rooms and configure short-stay rates as admin before reception starts using them. Verify any old active stay with missing rates first. Perform read-only local routing/account checks; do not create fake ordinary financial transactions as a smoke test.

Rollback is a controlled forward fix/write pause, not dropping new tables/triggers or restoring over later financial events. An old writer cannot safely resume while the new invariants are active. Use the protected isolated restore to investigate, then preserve/reconcile all events recorded after the backup. No Git commit, push, production deployment, ordinary database migration or post-activation financial smoke transaction was performed in this implementation turn.


## Occupancy interface corrections — 2026-10-31

Payment notices now name the authenticated cashier; receipt rows resolve the recorded actor's full name while keeping unknown historical actors explicitly unknown. A posted name cannot override the authenticated actor. Occupancy labels and shared navigation use title case, preserving acronyms such as ID, GHS and UTC.

The workspace includes Room Board, Guest Folios, Room Control and Activity. Guest Folios is a cursor-paginated account directory retaining active, departed and cancelled records; cancelled/unknown-rate records do not receive guessed list balances. Activity lists operational actions with actor, timestamp, outcome and affected stay/room/item. Read-access events remain in the separate audit trail; directory and activity reads are themselves audited before data is returned. This exposes existing occupancy authority and does not grant staff the unrestricted security-audit capability.

Room cards and the departed guest detail expose Cleaning Complete and then Inspect & Release Room when the room is unoccupied and unblocked. Administrators see the action; clerks see the pending administrative step without gaining mutation authority. The same existing lifecycle handlers and server-side permissions are retained. Cleaning completion still does not make a room saleable, and occupied/maintenance-blocked rooms cannot be released by these links. The inspection form includes the expected verification checklist.

Executed verification for this correction:47 existing synthetic DB assertions;55 occupancy HTTP assertions including authenticated cashier name, spoofed name rejection, cleaning/inspection UI journey, both new tabs and clerk denial;16 views at1440/390/320px with action focus;7 changed PHP files linted. Five unauthenticated normal-local HTTP checks passed for login/routing/assets and private evidence denial. No schema changes, ordinary financial test records, historical edits, commit, push or production deployment.

Activation was previously completed and its evidence is in ignored .local-setup/stays-activation-evidence.json: schema2 and11,330 original row statements preserved. The final local HTTP checks that were blocked at activation time have now been executed successfully. Existing limitations in historical recovery, gross-collection reporting and production parity remain.


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


## Consolidated Property & Audits navigation — 1 November 2026

Replaced separate Rooms and Weekly Area Audit sidebar entries with Property & Audits. Both room-control and other-area audit pages share exactly four tabs: Room Master, Out-Of-Order Log, Room Audits and Other Area Audits. Removed the duplicate two-tab and four-tab rows. Active tab/sidebar state, matching workspace heading/breadcrumb and a visible selected-tab colour are consistent on registers and action forms. Existing rooms.php and occupancy section/tab URLs, forms, access checks, settings, filters and historical audit/financial records remain unchanged. Room/fault/audit action forms select their corresponding tab.

Executed: six affected PHP files linted;121 existing occupancy HTTP assertions passed on marked synthetic HTTP8099;8 register/form views at1440/390/320px verified four tabs, one selected tab/menu item and no page overflow or encoding errors; mobile screenshot visually inspected. No schema, ordinary-database writes, new tests/fixtures against ordinary data, Git-state changes, push or production deployment. This is a reversible presentation change; restore the scoped navigation files if needed without touching audit history.


## Stock In / Save Extra reason field removal — 1 November 2026

Removed Reason / Findings / Evidence from Stock In and Save Extra forms as requested. Both service actions now accept an omitted reason. Previously supplied notes remain valid and historical notes are retained. Stock Count adjustments still require a reason; authorisation, exact price/quantity validation, immutable movements, actor/time attribution, operation identity and transactional audit are unchanged.

Executed:changed service/view PHP lint;136 occupancy integration assertions, including creation/receipt without notes, empty movement note and missing-adjustment-reason denial;121 occupancy HTTP assertions;three focused rendered-form checks. Tests used only the verified synthetic database/marked HTTP service. Independent scoped read-only review found no blocker. No ordinary database data changes, migrations, history rewrite, push or production deployment.

## Unauthorised Departures · 14 November 2026

Use Show Details → Report Unauthorised Departure when a guest has physically left without prior approval. Record discovery time, the actual departure if known, facts and vacancy/access security. Evidence upload is optional. Do not enter a payment unless money was received.

A reported departure has a Provisional Stay Bill and stops accumulating room charges. If vacancy and security are verified, normal cleaning and inspection can release the room even while the guest owes money. Otherwise Verify Vacancy & Secure Access must resolve the security hold first.

The Departure Incidents tab, Owner Reports and weekly review show unresolved cases. Eugene uses Review Departure to preview evidenced actual departure and final charges, then confirm with reason and any required collection assignment/due date. He can dispute or retract an unfinalized report. Retraction is unavailable after room reuse. Unknown historical rates need separate evidenced reconciliation.

After confirmation, Eugene can use Recover Outstanding for a new partial Cash/MoMo receipt received now. Existing late-receipt recovery remains separate and records money actually received earlier. Follow-up history, original reports, evidence and closed-week monetary totals remain preserved. A guest credit stays unresolved until correctly refunded. Notifications are in-app.

See [implementation evidence and recovery](delivery/changes/2026-11-13-departure-incidents.md). No new database migration is required on the existing migration-010 baseline.
