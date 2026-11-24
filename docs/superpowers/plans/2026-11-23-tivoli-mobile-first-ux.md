# Tivoli Mobile-First UX Implementation Plan

**Goal** — Help staff complete everyday work on phones and tablets with fewer mistakes, while giving owners a clear review and approval workspace on laptops.

**Status** — Proposed on 23 November 2026. This plan records design and verification work to do. The preceding review used source and workflow documentation. Staff usability and device performance still need a baseline.

**Implementation progress** — See the [delivery and validation record](../../delivery/changes/2026-11-23-mobile-first-ux.md) for local changes, executed checks and remaining pilot work.

**Architecture** — Reuse the PHP routes, templates, services, shared confirmation modal and form-return behavior already in the app. Land small, independently reversible slices. Keep authoritative status, eligibility, permissions and amounts on the server.

**Read first** — Repository [AGENT.md](../../../AGENT.md), [operating SOP](../../updated_sop.md), [testing guide](../../TESTING.md), [financial integrity rules](../../FINANCIAL_INTEGRITY.md), [legacy contracts](../../LEGACY_CONTRACTS.md) and [delivery state](../../delivery/DELIVERY_STATE.md). The work packages below capture the recommendations from the UX review.

## Safeguards and scope

- [ ] Before each slice, trace the current permission checks, valid transitions, source records, operation keys, return paths and relevant tests. Capture the behavior with synthetic records before editing.
- [ ] Reuse the same check-in eligibility, posted amounts, UTC rules and rate snapshots. Preserve all guest, financial, audit and staff history, including original transaction IDs, closed-period totals and correction links. Keep full cash deposit obligations and show cash expenses separately.
- [ ] Preserve server-side permissions, CSRF, duplicate protection, separation of duties, existing approval gates and durable audit records. Keep owner-only review, private staff data and other users’ restricted activity out of staff responses, including mobile payloads and notifications.
- [ ] Use the verified isolated environment described in the testing guide. Confirm the target and synthetic marker before any write, disable external delivery, and compare protected rows before and after tests. Use fresh fixture records and test exact amounts and event counts.
- [ ] Document changed routes, verification evidence and rollback per slice. Ask for a separate decision if a change requires a new financial rule, approval policy or data migration. Production rollout requires separate approval.

## Phase 0 — Establish the baseline

- [ ] Walk through the scenario matrix below on a staff phone, a shared tablet and an owner laptop using synthetic accounts for each relevant role. Record device, browser, role, completion, errors, time, first tap and whether help was needed. Include an available room, a room awaiting inspection and a blocked room.
- [ ] Record screenshots and existing outcomes at 320 CSS px, a representative phone width around 390 px, tablet portrait and landscape, and laptop around 1440 px. Record mobile load and interaction timings on a normal and throttled connection. Capture current results before setting improvement targets.
- [ ] Confirm available regression runners in `tests/Contract` and `tests/Integration`. Reuse relevant browser and service suites, including `layout_browser.cjs`, `occupancy_navigation_browser.cjs`, `housekeeping_occupancy_browser.cjs`, `form_returns_browser.cjs` and `confirmations_browser.cjs`.

## Phase 1 — Fix room readiness and pilot the front desk

### 1. Make the dashboard count genuinely ready rooms

**Touchpoints** — `public/dashboard.php`, Room Board template and the existing check-in eligibility logic.

- [ ] Characterise how the board and check-in writer handle ready rooms, rooms being cleaned or inspected, critical faults, service rooms and concurrent occupancy.
- [ ] Replace the dashboard’s subtraction-based **Vacant** count with **Ready for Check-In**, backed by the same eligibility criteria as the board. Link to the matching ready-room list. Show **Being Cleaned**, **Awaiting Inspection** and **Blocked** separately.
- [ ] Verify counts and linked room IDs match on fixed fixtures. Include zero ready rooms, a room state change after opening the board and a direct attempt to check into an ineligible room. Recheck eligibility at submission.

### 2. Put shift work first on Today

**Touchpoints** — Staff dashboard, shared navigation and existing workflow queues.

- [ ] Show **Continue My Work** for an authorised unfinished task and up to three frequent permitted shortcuts. Pilot check-in, guest/room lookup and other income for clerks. Surface eligible rooms and paused checklists for housekeeping. Link to existing records and actions.
- [ ] Move the existing seven-day staff charts below current work and keep the owner’s reports as the admin landing page.
- [ ] Verify empty, single-task and multi-task states, narrowly delegated access and role changes. Confirm an unauthorised task can neither appear in the shortcut data nor be opened directly.

### 3. Walk-in, Guest Stay Account, payment and departure

**Touchpoints** — `templates/stays/index.php`, existing stay and receipt handlers, shared form and confirmation assets.

- [ ] Start from a ready room. Show room, stay type and applicable price above the required guest fields. Keep the Ghana nationality default. Group optional identity, evidence and payment details. On successful check-in show the room and **Open Guest Stay Account**.
- [ ] Keep guest and room identity above all stay actions. Use **Charges**, **Paid** and **Balance Due** consistently. Explain a checkout block alongside **Clear Outstanding Balance**, return to checkout after settlement, and display documented owner authority and the amount when checkout with a balance is permitted.
- [ ] Replace generic labels with action-specific language, such as **Record Cash Payment**. In the existing single modal show room or source, amount and method. Freeze the page while it is active. On success display the receipt reference and resulting balance.
- [ ] On uncertain submission, provide a way to check the same operation before retry. Reuse its idempotency key on retry, preserve original receipt identity and distinguish saved, refused and unknown outcomes. Start a fresh key only for a new intentional action.
- [ ] Verify overnight and short stay, extras, Cash and MoMo, failed validation, zero and positive balances, authorised override, cancelled confirmation, lost response after commit, repeated tap and two-device submission. Check one receipt and audit event per successful payment and no normal payment after checkout.

### 4. Apply shared responsive and accessibility patterns

**Touchpoints** — `public/assets/css/app.css`, shared layout and form templates, workflow styles.

- [ ] Give phones one reading column and keep the relevant record identity close to the action. Use text labels, telephone/decimal input keyboards where appropriate and visible focus. Use 44 × 44 CSS px as the target for frequent controls. Test with the software keyboard open, text zoom and a keyboard or screen reader.
- [ ] Keep normal form content usable at 320 CSS px. Where tabular comparison needs horizontal space, scroll the table region only. Offer compact or expanded comparison tables on laptops only where that choice helps.
- [ ] Pilot a two-pane Room Board in tablet landscape with a clear selected room identity. Keep a single sequence in portrait. Verify switching rooms and rotating the device cannot leave a stale form targeting another room.
- [ ] Place validation near the affected field and move focus to the error or action when appropriate. Restore focus when closing a modal. Check Cancel preserves the opening page, filters and position and performs no write.
- [ ] Reference [W3C reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow), [target size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum) and [multi-step forms](https://www.w3.org/WAI/tutorials/forms/multi-page/) during design and validation.

## Phase 2 — Improve handoffs, stock and money work

Each row is a separate implementation and verification slice. Build on Phase 1 patterns and share presentation components only when their behavior matches.

| Work package and source | Implementation | Acceptance scenarios |
|---|---|---|
| **Cleaning, inspection and repairs** · `templates/housekeeping/`, `templates/stays/` | Put the next action on each room. Show room, named step and progress when resuming. Rename bulk controls to **Check All Cleaning Steps** and **Mark All Inspection Items as Passed** with editable items and a visible count. Show failed items first in rework. Limit guest and financial details to duties that need them. | Pause and resume; apply and amend bulk selection; fail inspection and reopen rework; block check-in until authorised release; enforce independent inspection or explicit existing exception; repair a critical fault using the approved transition. |
| **Property audits and follow-ups** · `templates/weekly-audits/`, `templates/stays/` | Offer both record-audit actions to Audit Duty users even in an empty view. Put assignee, deadline and next action together. Clarify Critical fault impact before posting. | Room and area audits; assign, mark done, verify or return; due-date visibility; denied staff access; critical versus noncritical room impact; original audit remains linked. |
| **Extras, sales and stock** · `templates/partials/extras-nav.php`, `templates/stock-counts/`, `templates/stock-issues/`, stock costing | Lead with **Sell an Extra**, **Receive Stock** and **Count Stock** by permission. Reuse catalogue names and item search. Show count progress and distinguish blank from zero. Show recorded, counted and proposed adjustment quantities together. | Sale and receipt, stock damage/expiry/complimentary use, blank versus zero, resume and linked recount, approve once, retained source movements and known versus unknown cost. |
| **Finance landing and payment log** · `templates/finance/navigation.php`, `templates/finance/payments.php`, `finance.css` | Lead with existing actions **Record Money In**, **Record an Expense**, **My Cash to Deposit**. Route stay receipts through the stay account and non-stay income through Other Income. Present amount, method, status and reference together on mobile. Retain laptop filters, detailed tables and links. | Clerk and owner see permitted actions; payment log uses identical dates and totals on phone and laptop; record courtyard income once; return from detail with prior filters. |
| **Cash handover, deposit and MoMo discrepancies** · `templates/handover/`, `templates/finance/index.php`, `cash-discrepancies.php` | Use **Cash I Hold**, **Cash Handed to Another Person** and **Cash Deposited to MoMo** consistently. Put sender, amount and acknowledge/report difference actions together. Show full next-day deposit obligation and deadline. Distinguish staff-entered MoMo details from independently matched evidence. | Transfer and acknowledge; report short handover, partial wallet credit or reversal; verify custody and full deposit remain traceable; cash expense leaves deposit obligation intact; duplicate retry has no financial effect. |
| **Expenses, purchases, providers and payroll** · `templates/expenses/`, `templates/purchases/`, `templates/payroll/`, `templates/finance/` | Show status and next actor from submission through approval and settlement. Put delivery, cost and payment in the linked purchase detail. Preview, replace and explain failed evidence uploads on phones. | Clerk expense awaits approval; owner pays approved expense; purchase with partial settlement; provider evidence; payroll review and statement; expired session and failed upload keep financial state unchanged. |
| **Unauthorised departure and late receipts** · `templates/stays/departure-*.php` and existing recovery forms | Start from the original stay, show **Guest Left Without Approval** or **Record Late Payment** only where permitted and preview the original activity date, posting period and effect. | Departure incident with unresolved balance; owner review; approved late receipt linked to the checked-out stay and posted once in an open period; original week stays fixed. |
| **Corrections and reconciliation** · `templates/finance/corrections/`, guest/purchase/expense correction views, `templates/payroll/corrections.php`, weekly finance | Start with the source transaction and route **Request a Correction** to its existing workflow. Show original, requested change and effect if approved. Distinguish charge adjustment from money returned. | Refund, charge correction, expense correction, purchase return, payroll correction and correction after deposit/handover; verify source linkage, approval, custody and closed periods; exercise day and week reconciliation including reopen authority. |

## Phase 3 — Staff self-service and owner review

| Work package and source | Implementation | Acceptance scenarios |
|---|---|---|
| **Sign-in and recovery** · `templates/auth/`, shared header | Keep password visibility and password-manager support. Make signed-in identity and **Sign Out** easy to find on shared devices. Explain reset delivery and next steps using the same privacy-safe response for eligible and unknown addresses. | Show/hide, autofill on mobile, reset recovery, logout on shared tablet, back navigation after logout and account privacy. |
| **Staff & HR** · `templates/hr/`, payslip templates | Link to permitted leave, documents and payslips. Show onboarding step names and resume drafts. Show document type, filename and result. Keep sensitive details out of general views. | Onboard and link an existing account; resume draft; upload optional evidence; request and review leave; access own payslip; offboard and return items; verify privacy for document, salary and identity data. |
| **System Access and Settings** · `templates/access/`, `templates/partials/settings-nav.php`, HR and operational settings | Preserve the consolidated navigation and on-demand editors. Preview permission gains or losses in plain language before a sensitive change using existing authorisation. Show settings values before opening an editor. | Create/edit user, roles and privileges; delegated role boundaries and revocation; old sessions handled as before; read-only settings, stale edits, Cancel and repeat submission. |
| **Reminders and owner exceptions** · `templates/reminders/`, `templates/owner-exceptions/` | Show action, due time and direct link. Group owner reminders by underlying case and show assignee and last action. Keep sensitive details inside the signed-in app for optional Email and WhatsApp. | Same case from multiple reminders, assigned and overdue cases, escalation and closure, disabled delivery, permission change before opening a link, delivery without sensitive content. |
| **Owner reports and review queues** · `templates/owner-reports.php`, finance reports and owner queues | Show selected period above totals. Keep revenue, receipts and cash to deposit distinct. Link room revenue, extras, costs, profit and exceptions to details with the period applied. On laptop, retain sortable tables and evidence/decision comparison where useful, with filters and position restored on return. | Weekly and monthly figures match existing reconciled fixtures and drill-down totals; legacy and new records remain reachable; filters survive return; keyboard access; staff cannot access owner reports. |

## Phase 4 — Validation and rollout decision

- [ ] **Run the full turnover pilot first.** A clerk finds a ready room, checks in a walk-in, posts an extra, collects payment, then checks out. Another staff member cleans, an authorised inspector releases, and the next clerk finds the room ready. Include a blocked room, unpaid balance and connection interruption immediately after payment submission.
- [ ] **Run an operating-week rehearsal.** Use synthetic Monday–Sunday data for both payment methods, a cash handover and next-day full deposit, other income, expenses, purchase and provider settlement, payroll, extras and stock, audits, reminders, corrections and week close. Verify exact receipts, costs, custody and audit events, then post an authorised late item in an open period and compare the closed totals and original row hashes.
- [ ] **Run the same task script before and after.** Ask staff to perform tasks without coaching on their usual devices. Record unaided completion, first-tap choice, help requests, wrong-room selections, payment uncertainty, recovery success, elapsed time and usability comments. Exercise tablet portrait and landscape, laptop keyboard, low bandwidth, screen zoom and an expired session.
- [ ] **Review risks before release.** Treat an ineligible room released or sold, duplicate payment, changed closed totals, lost audit evidence or exposure of private data as blockers. Require staff to identify a ready room unaided, tell if a payment was saved, resume interrupted work and explain who acts next. Compare timings against Phase 0 and agree targets using measured results.
- [ ] **Capture evidence per slice.** Log scenario, role, device, expected and actual result, evidence and follow-up. Run changed PHP/JS syntax checks, relevant browser and service regressions and diff checks. Record fixture identifiers, exact monetary assertions and protected-history comparison. Repeat failing scenarios after fixes.
- [ ] **Release in small batches when authorised.** Review each batch’s evidence, support notes and rollback. Roll back UI changes by restoring the previous compatible application version. Retain transaction and audit history created while a batch was active. After rollout, review staff feedback and the same error patterns during the first complete operating week.
