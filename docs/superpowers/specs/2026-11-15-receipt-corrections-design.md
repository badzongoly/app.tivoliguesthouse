# Financial Corrections — approved implementation scope

Owner-approved design dated 15 November 2026; implementation verification continued 16 November 2026. The sections below retain the reviewed prototype decisions. The application implements the guest-receipt slice; see docs/FINANCIAL_CORRECTIONS.md and docs/delivery/changes/2026-11-15-receipt-corrections.md for actual behavior and executed evidence. Approval does not authorize production deployment.

## Intent and approach
Close the SOP G3/CR02 correction gap: authorized corrections must preserve originals, closed snapshots, actual money movements, room charges, staff identity and traceable effects. Start with guest receipt recording errors, including checked-out stays. Expense, provider, payroll, charge/rate and allocation corrections require later source-specific designs; this phase never pretends they share identical accounting effects.

Considered: (a) edit posted receipts — rejected because it destroys provenance and closed-period evidence; (b) generic reverse-anything — rejected because recording errors and actual refunds have different financial effects; (c) typed, source-linked correction cases with a before/after preview — recommended. The owner reviewed and approved this approach before implementation.

## Scope and authority

Navigation placement updated at the owner's request: Financial Corrections is a tab under the existing Finance & Cash main menu, alongside Expenses and Payroll & Staff Cost. It has no separate main-menu entry. Its Receipts, My Requests, Owner Review, Corrections Log and Period Impact views remain within that tab.
Authorized reception staff may request corrections on receipts they can legitimately access; pending requests are private to their requester and Eugene. Prototype samples further limit staff to their own recorded receipts. Eugene may request and post his own correction with an explicit owner declaration; no invented second administrator. Only Eugene can approve/post or reject; staff may withdraw pending requests and revise returned requests. No cash count, expense netting, bank/card method or MoMo API is introduced.

## Workflow
1. Open an existing receipt from Payment Log/Guest Stay Account. Show original guest/stay/receipt IDs, method/reference, amount, actor, time, original activity date, closed/open period and dependent refunds/deposits/matching.
2. Choose Recorded Amount, Cash/MoMo or Reference, Duplicate Recorded Receipt, or Money Actually Returned. The last path exits to the existing separately evidenced guest refund workflow; it never creates a recording correction or transfers money.
3. Propose corrected total receipt amount (not a delta), corrected method/reference, or link the exact retained receipt representing the same actual collection. Require a reason; file optional. Evidence collection is local demonstration only in the prototype. A duplicate correction never deletes a row or releases its original reference for reuse.
4. Preview original versus effective receipt, net receipt adjustment, guest balance, channel/cash-deposit effect, unchanged charges/revenue and unchanged closed snapshot. Show original versus posting dates separately. Receipt reduction can create an amount to recover; an increase can reveal an overpayment for separate review. Neither auto-refunds, writes off debt, collects money nor reopens occupancy.
5. Submit -> Awaiting Owner. Eugene reviews the same source/version/dependencies and independent verification summary. Return requires a reason and creates a retained revision trail; rejection/withdrawal remain visible.
6. Approve & Post is one confirmed operation in today's open financial period. It atomically records authorization, source-linked compensation/replacement, source claims, domain effects and durable audit. There is no partially approved financial state. Retrying the same operation does not create another correction.
7. Posted view retains the original, full correction chain, current effective position, immutable before/after preview and author/requester/timestamps. A later mistake requires a fresh linked correction against the then-current effective version, not editing/reusing this case.

## Type-specific effects
- Amount correction: reverse the erroneous recorded receipt contribution and substitute the verified total, linked to the same actual collection. Actual money movement = zero. Room/extras charges, earned revenue, expenses and profit are unchanged. Net recorded receipts and guest balance change by opposite amounts.
- Method/reference correction: receipt total and guest balance unchanged. Reclassify recorded Cash/MoMo contributions; invalidate old independent matching only through an explicit reconciled transition. Original and corrected reference aliases remain reserved to the actual collection. Changing a reference cannot fabricate a fresh payment or silently match a wallet credit.
- Duplicate: require a distinct, supported retained receipt for the same guest/source/actual collection. Preserve both originals and annotate which contribution is compensated; net receipts fall once. Do not compensate a real second transfer or a duplicate already corrected. A legacy reference conflict is evidence to investigate, never proof by itself.
- Actual refund: retain the existing owner's guest-refund path, independent proof, overpayment and remaining receipt bounds. This prototype presents the routing decision only; it does not redesign, relax or simulate a successful refund.

## Dependencies and stop rules
Block approval for a changed source/guest position, competing pending case, duplicate/previously consumed scope, inactive authority, closed current posting period, unsupported historical evidence, conflicting external reference or values below committed refunds. Before approval recheck live refund bounds and all current dependencies under the same lock as other writers. File selection alone does not establish independent verification.

For a linked cash deposit/handover, matched MoMo receipt or refund, preserve and display the dependency. The prototype deliberately BLOCKS method/amount/duplicate changes on a deposited receipt; dependent reconciliation is separate approval scope. Never automatically undo a confirmed deposit, move physical cash, lower a genuine deposit obligation by expenses, or remove another staff member's acknowledged custody. Reference/method changes requiring wallet rematching must explicitly show that status. No physical cash count is requested.

The closed-week example preserves GHS12,000.00 at 02–08 November. A 15 November -GHS200.00 recording adjustment appears once in the open 09–15 November correction column, linked to the original date. Historical snapshot remains GHS12,000.00; the source's effective lifetime receipt position is corrected through its linked roll-forward. Show gross original activity and corrections separately, never silently hide originals or relabel a recording adjustment as cash collected today.

Posted correction receipt is an adjustment notice, not a new payment receipt. On checked-out stays show current recovery due separately from the frozen original checkout bill; route to the existing recovery workflow without allowing ordinary post-checkout payment or changing departure timestamps/room status. A discovered overpayment needs the existing refund review, with evidence of actual excess money before any refund.

## Prototype and approval boundary
Sample records cover: closed-week overstatement on checked-out stay; Cash incorrectly chosen instead of MoMo; legacy duplicate with an evidenced retained collection; already-deposited cash requiring reconciliation; a receipt with GHS100 already refunded and a lower-bound guard. Explore requester, Eugene review, return/revision/reject/withdraw, posting confirmation, recovery handoff, audit and period impact. All data is synthetic; state is local and no money moves.

The prototype allows one posted correction per sample receipt to keep review clear; subsequent chain correction is a required implementation contract, not a silent undo/reset. Approval of this prototype authorizes a later implementation plan, not production deployment. Implementation requires source-specific adapters across folio/recovery, Payment Log, channel matching, full-deposit custody, reports, lock checks and printable adjustment notices; protected historical snapshots and original rows must remain intact. No new generic financial writer may bypass those existing guards.

Acceptance before implementation handoff: exact before/after effects; no monetary effect on request/return/reject; owner-only confirmed posting; historical versus current periods explicit; deposited/refunded/stale/duplicate guards; original/history visibility; no fake refund; payroll/HR excluded; repeat submit safe. Production implementation additionally requires real transaction/race/rollback and all affected regression tests, plus independent review and recovery evidence.

## Evidence informing the proposal
Local: docs/updated_sop.md G3/G5; docs/FINANCIAL_INTEGRITY.md sections1–5; FinanceService open-period/refund/reference guards; StayService refund bounds and frozen checkout plus later-recovery accounting; completed payroll delivery notes.

External primary references: https://docs.stripe.com/refunds distinguishes refund initiation, completion/failure and bounds; https://docs.stripe.com/api/idempotent_requests explains same-operation retries. These inform separation of recording correction from actual return of money and retry protection only. They do not establish Tivoli's MoMo capabilities, accounting/tax policy or Ghana legal requirements. Tivoli authority and period policy follow the owner's approved choices above.

## Prototype verification and independent design check

Executed 24 standalone browser checks at1024/390/320: amount and channel effects, owner confirmation/cancel, returned proposal visibility, deposited dependency block, prior refund lower bound, duplicate retained source, recovery/refund handoffs, period snapshot and role-switch privacy; no runtime errors. Independent read-only design review found omitted replacement method/reference, hidden earlier proposals and a role-switch refund-source exposure; all were fixed and re-reviewed with no remaining design blockers. This verifies a synthetic throwaway prototype, not application financial posting, durable audit, locks or production controls.
