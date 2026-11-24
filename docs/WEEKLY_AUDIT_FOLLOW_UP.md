# Weekly Audit Follow-Up

Owner-approved scope: one follow-up view within Property & Audits, extending the existing Room Audits and Other Area Audits. No separate ticket menu, financial posting or new repair-release inspection.

## Normal use

1. Eugene opens **Property & Audits → Audit Duty** and authorizes a named active operational account to record audits. Existing authorized room administrators retain audit entry. Duty does not add system roles, room administration or financial authority.
2. The auditor records the room or configured area and result. Original Comment and Action Owner / Due Date Note remain optional. **Pass** needs no task. **Needs Attention / Unsafe** creates one follow-up, or explicitly links an existing open action for the same finding at the same location. The application never merges findings just because the location matches.
3. Eugene opens **Follow-Ups**, selects responsible staff and a due date. Only issuing an assignment requires those fields. Old free-text ownership remains an original note, not a guessed account assignment.
4. Assigned staff open **Show Details → Mark Done**. Comment and private PDF/JPEG/PNG evidence up to 5 MB are optional. Mark Done sends the work for verification; it does not close it.
5. Eugene checks the work and chooses **Verify & Close**, or **Return For Rework** with an explanation. Return before changing an assignment awaiting verification. Reassignment/deadline revisions require a reason and preserve previous deadlines and completion claims.

Open work carries across weeks; overdue is derived from the current deadline and status. Room/area audit registers default to the current UTC Monday–Sunday week and offer All Weeks. A later Pass never closes earlier unresolved work. A repeated finding explicitly linked while work awaits verification returns it to the assigned person, retaining their previous completion evidence.

All audit writes use the shared confirmation modal. Review the action, then confirm once; Go Back, Escape or close cancels without recording it. The background stays inactive until the modal closes. Housekeeping is managed from Check-In & Occupancy rather than this property navigation.

## If only Follow-Ups is visible

Audit entry is separate from follow-up completion. A clerk without Audit Duty sees only Follow-Ups and an Audit Duty Required explanation. Eugene opens **Audit Duty**, selects the actual staff account and records **Assign Audit Duty** with a reason. The staff member then reopens this page; **Record Room Audit** and **Record Area Audit** are available directly on the landing page, as well as the registers. No role upgrade is needed. Existing assigned work can still be completed without granting audit entry. Only Eugene assigns deadlines and verifies or returns work. A passing audit has no follow-up to assign.

## Safety and existing repair

Unsafe room audits still create a Critical fault through the existing room writer. Every explicitly linked unsafe room audit contributes its fault to the verification check, including later linked audits. **Record Repair** remains the existing repair workflow. Closing a follow-up cannot change room availability/readiness or bypass an open Critical fault. Historic unsafe room audits without a stored fault identity require Eugene to select the evidenced same-room Critical fault and explain the link; the system never infers it from text or timestamps.

Unsafe areas require Eugene to attest that the area is safe or blocked from use. This is a recorded management attestation, not an automatic facility blocking or booking function.

## Original records and older actions

Original audit IDs, results, comments, free-text notes, actors and timestamps remain unchanged. The source page links to original area audit/follow-up history, with pagination. **Earlier Area Actions** lists original open/in-progress area actions across weeks. Eugene explicitly confirms one before assigning a named staff account; its latest original owner note, deadline and progress are retained. Already completed historic area actions stay completed with their original attribution. No past verification is invented.

The older `area_followup` writer cannot make new decisions, including on unadopted old actions. Existing operation-key replays still resolve before the retired writer. Its GET links open the source-linked task or original source for confirmation. Original room/area entry forms remain compatible and write through the same source-capture hook. New registers and the shared Property navigation point to the new protected route.

## Access, evidence and duplicate protection

The configured finance owner is the authority; names never grant permission. Active operational accounts with audit duty may record audits. Staff can read their own sources/current assigned work and mark only their current assignment done. Owner or room administrators with `rooms.manage` may read the shared audit records. Only the configured owner assigns, changes duty, returns or verifies work. Inactive/suspended/exited accounts are denied operational access. Duty revocation remains possible for inactive accounts and does not erase evidence or an otherwise eligible person's existing work assignment.

All writes use PDO and the existing shared `security_state` transaction lock. Per-actor operation keys and input fingerprints survive retries; a changed retry is rejected. `finance_claims` provides a unique original-audit-to-follow-up association. Version checks include linked safety facts. Required audit, operational journal, source link and operation identity commit together or roll back. Operational journal entries have zero amount and do not post revenue, expenses, receipts, stock or payroll.

Evidence is validated by content and size, encrypted outside the public directory and downloadable only through a committed completion record after current task authorization. A rolled-back encrypted orphan is not registered or downloadable. On an uncertain submission outcome, retry the same form/key and the same evidence file; do not start a new form or silently remove the file. In-app reminders link back to the original task. Optional Email/WhatsApp delivery uses the separate Reminders & Escalations settings; recording an audit does not itself enable those channels.

## Setup and recovery

Uses the existing activated occupancy, finance and HR private-evidence configuration; no DDL, migration or historical backfill. Do not grant duty by matching names. Eugene explicitly selects the actual account. No ordinary application account or database is modified by test fixtures.

Retain journal, source claims, operation identities, audit and encrypted evidence during recovery. Do not revert to an old writer that permits unverified completion. Disable affected audit writes if necessary and forward-repair while preserving original and later records. Production release/backup restore remains a separate target-specific decision.

See the 21 November weekly-audit delivery change record for executed evidence and limits.


Verified again on 22 November 2026. See [workflow review and executed evidence](delivery/changes/2026-11-22-weekly-audits-review.md).

## Shared property navigation — 22 November 2026

Room administration and weekly audit views now share one permission-aware tab row, including the owner's single Audit Duty entry. Legacy register creation links open the canonical audit form, while original saved routes/history stay supported. Room preparation remains in Check-In & Occupancy. See [Property & Audits workflow map](PROPERTY_AUDITS_WORKSPACE.md).
