Owner approval for implementation received in chat on 21 November 2026. The following preserves the reviewed prototype design; current implementation/evidence is in WEEKLY_AUDIT_FOLLOW_UP.md and the delivery change record.

# Weekly Audit Follow-Up — prototype for approval

Status: proposed, not implemented in Tivoli. No application/database/Git changes. Owner requested a simple interactive review prototype, not deployment.

Observed: `WeeklyAreaAudit` already retains area findings and versioned free-text-owner follow-ups; room audits retain result/comment/free-text owner and create a Critical fault for unsafe findings. Room/area audit entry is presently admin-gated. `StayService::repair` closes the fault and restores existing room state; the owner explicitly removed a separate repair-release inspection. SOP F1 assigns Sunday's audit to Joshua or an authorized substitute; F2 and later owner decisions retain immediate safety handling and existing repair controls.

Proposal: one Follow-Ups tab inside existing Property & Audits / Weekly Area Audit, linked from both room and area audit records. Extend original area follow-ups rather than migrate them into duplicate tickets. Pass requires no action/assignment. Needs Attention/Unsafe creates or explicitly links a single action; optional original audit comment remains optional. Only an issued assignment requires a named active account and due date. Legacy free-text ownership is retained as a note until Eugene explicitly confirms the account.

Normal flow: Eugene assigns → assigned staff Mark Done (comment and attachment optional) → Eugene Verify & Close or Return For Rework with explanation. Reassignment/deadline revision retains before/after and requires a reason. Open actions carry across weeks and display overdue status; a later passing audit cannot close an earlier unresolved action. A repeated finding explicitly selects an existing open action, without guessing by location or creating a duplicate.

Safety: a room follow-up cannot close while its linked Critical fault is open. Link to existing Record Repair; no new post-repair release step and no task-close room-status mutation. Unsafe areas require Eugene's completion/safety attestation. External messages, purchases, payments and financial costs remain in their existing workflows and are not triggered here.

Permission proposal for approval: Joshua / an authorized substitute may record their assigned audits, staff update their own assigned actions and see their linked source, Eugene assigns/reassigns/verifies. This is a proposed bounded permission extension, not a claim current clerk endpoints allow it. No automatic account mapping from names, guessed historical actors or automatic historical task conversion.

Controls to implement only after approval: original audit and existing area-follow-up references, immutable event history, server-derived actor/permissions, CSRF, source-version checks, durable per-actor operation keys, unique audit/action associations, shared transaction/locks and mandatory atomic audit. Prototype keys/history are simulated locally with host state; they do not prove database concurrency or upload storage. File selection shows only its name; nothing is uploaded.

Approach: extend existing records and add a shared view. Separate ticket system rejected as duplicate entry; broad checklist/maintenance redesign excluded as unnecessary for this review. Design calls: one action per finding, clear assignment boundary, retained original audit outcomes, carry-over all-week queue, no external notifications. Sample dates/IDs/records are fictional.

Historical completed area actions must remain historical completions with their original attribution. Do not invent an earlier Eugene verification or automatically reopen/convert them. Prototype source/carry-over/role/retry/safety interactions have a separate static-browser check; no application PHP or database is used.

Executed prototype verification: 34 interactive/layout checks, 12 views at 1024/390/320px, no runtime errors/overflow. Covered source links, optional pass path, staff/owner transitions, legacy ownership confirmation, rework history, cross-week reuse, Critical fault guard including later linked audits, unsafe-area attestation, duplicate submission, actor-scoped replay, host state echo and review-state persistence. Reviewed mobile room-audit and desktop shared-queue screenshots. This is static sample-data evidence, not backend or database verification.
