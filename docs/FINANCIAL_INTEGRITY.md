# Financial integrity and accountability

These are required design safeguards for changed code. [Source review](CODEBASE_REVIEW.md) establishes concrete gaps; proposed controls must not be reported as already enforced. Accounting definitions and approval limits require owner approval before implementation; do not change historical reporting semantics as a side effect of refactoring.

## Existing financial source and cutover constraints

The supplied schema stores payments, expenses, booking extras and stock movements, but room charges are calculated from dates/rates. No immutable room-charge, audit, refund, cash-session, merchant-verification or dedicated receipt ledger is supplied. `reports.php` and the dashboard label payment sums as revenue. Preserve those known definitions in baseline tests before an approved reporting change.

`bookings.rate_per_night` is nullable; 11 exported bookings lack it and current code falls back to the room's present rate. Never populate these rates from today's catalog or silently freeze a guessed reconstructed charge as historical truth. Preserve unknowns and seek independent evidence; future immutable charge/rate snapshots need an explicit authority/cutoff and compatibility design.

The check-in extra path has a late-insert-ID defect; the export contains 2,664 `booking_extra` movements with reference zero. This is evidence of broken linkage, not proof of missing revenue or staff misconduct. Do not bulk-match by row order or nearest time. Retain each original movement and build any verified annotation/mapping through a separately reviewed procedure.

`admin_recovery.php` can add historical stays/payments/extras and alter stay dates. Any future recovery workflow must retain source evidence, authenticated operator, actual recording time, separate effective time, reason, approval and durable duplicate identity. Decide whether an extra is a late entry of a previously fulfilled sale or a new stock outflow; avoid deducting inventory twice. Preserve a usable controlled recovery route.

These changes must also cover `card`, an existing payment method, plus all existing expense methods; cash/MoMo remain operational priorities without authorising method deletion. See [legacy contracts](LEGACY_CONTRACTS.md) for exact fields and statuses.

## 1. Preserve evidence and identity

- Preserve the original posted payment, charge, expense, refund, reversal, cash movement, receipt, and reconciliation evidence, including stable IDs, booking/resource links, actor identity, amount, method, and time. No deletion, cascade deletion, renumbering, replacement import, or hidden soft deletion.
- Closed/posted financial facts are immutable. A legitimate correction is a new linked event with a reason, actual actor, authorisation, recorded time, and effect. Distinguish a refund (money returned), charge credit (charge reduced), void/reversal (specific prior effect compensated), and allocation change. Do not use these words interchangeably.
- Mutable operational state, such as an active booking's contact details, room assignment, or expected checkout, can change through authorised audited workflows. Such a change cannot rewrite a past posted charge or receipt. Rebuilding a derived cache must not alter the event source or erase its history.
- Deactivate referenced rooms/rates/users rather than deleting their identity. Preserve the rate and description applied at the time. Do not cascade-delete bookings/payments when a guest, room, or user is removed.
- Unknown legacy actor/reference/verification information remains explicitly unknown. At cutover, capture provenance and verification state without inventing a staff member, merchant confirmation, or historical action time.
- Historical anomalies become investigation items. Do not rebalance, re-date, deduplicate, or delete original rows to make totals match. An approved correction must remain visible beside the original.

## 2. Exact money, rates, and dates

- Proposed default currency is `GHS`; validate the current configuration. Preserve the original currency for every event; never aggregate different currencies into one unexplained amount.
- Use validated integer minor units or decimal strings with a proven exact arithmetic implementation. Store money in suitable fixed precision database types; keep decimal strings exact across PDO and JSON/UI boundaries. No float casts, float comparisons, or `round(float)` as the authoritative calculation.
- Before choosing minor units/precision, inventory legacy types, fractional quantities, taxes, discounts, limits, PHP integer size, and maximum totals. Converting legacy floats/decimals to a new scale is a separate reconciled migration, not an automatic cast.
- Specify rounding precision, mode, stage (line vs bill vs settlement), currency, and ordering in an approved policy with worked examples. Test boundaries and cumulative sums. Do not infer tax or Ghana statutory accounting requirements from this document.
- Calculate authoritative charges on the server using approved effective rates or stored snapshots. A later room/rate edit must not recalculate old stays. Backdated changes and overrides require explicit permissions, reason, and appropriate approval.
- Record both actual recording time and authorised effective/business date when the distinction matters. Existing `db.php` explicitly uses PHP/MySQL-session UTC; preserve it. `Africa/Accra` is the property-domain timezone to validate, not an instruction to convert dates. Current mixed DATETIME/TIMESTAMP columns and historical recovery inputs require deliberate compatibility tests. Test overnight stays, midnight, shift changes, and business-day cutoff.

## 3. Charges, receipts, balance, and recognition

Name report measures precisely. Charges/revenue, received cash, MoMo claims, independently verified MoMo, refunds, expenses, custody transfers, and guest balance are different measures. A payment is not automatically earned revenue; a deposit/prepayment may be a liability depending on the approved policy.

For a simple single-currency booking, after confirming the allocation model:

`net charges = posted charges + debit adjustments - credit adjustments`

`net allocated receipts = received payments allocated to the booking - refunds/returned allocations`

`balance due = net charges - net allocated receipts`

An overpayment produces a credit/unallocated balance and needs an explicit workflow; do not silently force it to zero. An expense or cash handover does not change the guest balance. Allocation changes cannot change the amount of money actually received.

Synthetic example, not a current room tariff: a charge of GHS 150.00 and receipt of GHS 100.00 produce GHS 50.00 due. A GHS 20.00 credit reduces due to GHS 30.00. A GHS 20.00 refund then reduces net receipts to GHS 80.00 and returns due to GHS 50.00. A refund alone does not reduce the original charge. Tests must model both effects explicitly where both are intended.

Approve deposit treatment, earned-revenue timing, billable-night calculation, tax/discount rules, cancellation charges, paid cancellation, refunds, write-offs, and closed periods before changing those calculations. Keep calendar-night occupancy separate from billable nights. Current HTML/CSV night rules disagree and completed checkout recalculates to now; fix each through explicit examples and preserve stored evidence. Display existing report definitions alongside proposed new ones during transition; never silently relabel cash collections as revenue.

## 4. Event and audit requirements

For new posted financial events, design stable fields or linked records for:

| Evidence | Required meaning |
| --- | --- |
| Event/receipt identity | Unique persistent identifier; booking/source link where applicable; no reuse after void |
| Type, amount, currency, method | Exact, validated event semantics; cannot be changed by a form's hidden fields |
| Actor and authority | Authenticated recording actor; approver and delegation when required; no self-approval bypass |
| Recorded and effective times | Server-recorded action time and separately justified business/effective date |
| Correction relationship | Original event ID, bounded correction/refund amount, reason, approval |
| Retry/external identity | Durable operation key, request fingerprint, properly scoped provider/reference information |
| Verification and provenance | Staff-entered vs independently matched evidence; import/cutover source where applicable |
| Audit/correlation | Linked committed action, entity, outcome, and allowlisted before/after metadata |

The financial event and required committed audit event must succeed together on one transaction. A denied/failed attempt can be logged separately. Remote monitoring failure need not stop reception if a required durable local audit/outbox is committed; the design must specify this behaviour and alert on delivery backlog.

Audit entries append; ordinary users cannot edit/delete them. Keep financial provenance stable while limiting sensitive guest data. Application logging alone is not an immutable ledger, and a ledger is not proof that staff physically received money.

## 5. Idempotency, concurrency, and correction bounds

- Persist a key for the complete command, scoped to the operation/actor or provider contract. Bind it to canonical significant input. The same key with the same input returns the original outcome; the same key with different input is rejected.
- Handle refresh, double-click, network retry, process restart, and uncertain commit. Exactly one payment/event and one receipt identity must result. A new legitimate payment must not be accidentally collapsed into a previous one.
- Enforce persistent uniqueness and lock/version checks as appropriate. A PHP existence check before insert is not a concurrency guarantee.
- Lock/recheck refundable amount, booking balance, current price/override authority, period/session status, and transition within the operation. Two concurrent refunds cannot exceed the refundable amount. Do not silently mutate the original to track the correction.
- Sequential receipt numbering must use a concurrency-safe allocation design compatible with approved business requirements. Never use `MAX + 1`. Explain gaps; do not renumber history to remove them.
- A closed period/session can only be reopened through an authorised audited workflow. Corrections to closed periods require an approved posting policy and reference to the original, without rewriting past dates/totals. A session's declared cash count is preserved even if later corrected.

## 6. Physical cash custody

Tivoli's prior discussions describe cash remaining with staff until requested. An ordinary shift change is not proof that custody changed. Record who holds the funds and require an explicit handover with acknowledgement.

For one verified cash custody session:

`expected cash = opening physical cash + cash receipts + custody transfers in - cash refunds - approved paid cash expenses - custody transfers out`

`variance = counted physical cash - expected cash`

- Count only physical cash in this equation. MoMo does not enter a cash drawer. Owner remittance/float movement is a custody transfer, not revenue or an operating expense.
- Distinguish an approved expense from the actual cash disbursement; count the outflow once. Do not both subtract an expense and a second transfer representing the same money.
- The opening balance must be evidenced. At first rollout, establish a verified cutover count; do not manufacture earlier sessions, receipts, or cash movements.
- Handover records identify source/destination, amount, time, both actors/acknowledgement state, and any variance. Pending handover money must not appear in two custodians' settled balances or disappear from both. Specify the atomic ownership transition.
- Preserve submitted count and variance. Investigate differences; no automatic balancing entry, altered opening float, or changed count to force zero. Approval and correction records remain visible.

## 7. MoMo and merchant reconciliation

- Separate staff-recorded payment, provider/merchant evidence, match decision, settlement, reversal, and fees. A unique reference or screenshot alone is not proof of successful payment.
- Validate reference format/normalisation and uniqueness scope against the actual provider/merchant contract. A repeated reference may be linked to one payment allocation; do not invent a global uniqueness rule across unrelated providers/accounts. Surface historical duplicates before adding constraints.
- Match amount, currency, status, destination merchant/account, reference, and appropriate time window using independent evidence. Record who/what matched it and when. Preserve unmatched, mismatched, reversed, and disputed cases for review.
- Merchant settlement is not a second guest receipt. Record fees and settlements according to approved accounting policy without reducing the original gross guest payment invisibly.
- Statement imports, if introduced, must be idempotent, access controlled, traceable, and protected against malicious CSV/content. No automatic write-off or force-match of differences.
- Do not send money, retry external charges, refund externally, or mark financial verification from an AI recommendation. Any payment API needs its own approved specification and recovery design.

## 8. Reconciliation and cutover acceptance

Before and after each relevant migration/refactor, use a consistent protected snapshot/cutoff and compare:

- Preserved IDs and per-row protected fields, not just counts or sums; linked bookings/staff/receipts remain resolvable.
- Counts and exact totals by booking, event type, payment method, staff, date/period, and currency; include refunded, voided, cancelled, inactive, and historical extras categories.
- Charges, payments, corrections, expenses, balances, cash custody/variance, and MoMo recorded vs verified amounts according to approved definitions.
- Duplicate references/receipts, orphan links, negative/overpaid balances, changed filters, and missing legacy states.
- Old/new reports on identical inputs; any intentional semantic difference needs a documented approved example, not a blanket tolerance.

Read-only financial extraction has zero tolerance for unexplained amount differences. Additive migrations may add documented rows/columns; reconcile them separately while proving original evidence unchanged. A backfilled projection/ledger must have one source mapping per legacy event and an explicit authority/cutoff rule: never count both the legacy receipt and its mapped copy as two receipts.

These safeguards do not mandate a full double-entry accounting rebuild. If a full ledger is proposed, design its journal/balance controls with qualified review and a separate cutover decision. An append-only audit table alone does not make the existing system financially correct.

