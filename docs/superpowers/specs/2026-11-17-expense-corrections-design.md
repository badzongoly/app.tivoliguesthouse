# Expense Corrections — owner-approved implementation scope

Owner approved implementation on 17 November 2026. The sections below retain the reviewed prototype design. Actual implementation follows the short path of source → live-preview form → owner confirmation. Original financial records remain retained. Approval to implement is not production deployment approval. See delivery evidence for executed verification.

## Goal and evidence

Extend the existing Financial Corrections tab with a narrowly typed ordinary-expense workflow. Preserve posted expenses, actor/source links, approval history, payment evidence, reference claims and closed snapshots. Make the effect on management costs and profit explicit without inventing a payment, supplier refund or stock movement.

Observed source: ExpenseService::submit/review posts approved expense rows and rejects edits; ExpenseRepository::approved reads original amounts; FinanceService::summary derives ordinary expense cost from those rows, separately from provider/payroll costs and payout events. Payment Log, OwnerReports, expense filters/CSV and period snapshots require coordinated adapters before any real implementation. The initial source review and financial-integrity documents remain historical baselines; current implementations are described in delivery state.

Relevant operating authority: updated_sop.md E1–E4 and G3 require Eugene's authorization and linked separate corrections. Earlier owner decisions require Cash/MoMo only for new operations, gross cash deposits in full and costs tracked separately. No physical cash-count, bank/card workflow, supplier API or statutory-accounting/tax rule is introduced.

## Considered approaches

Editing/deleting a posted expense would destroy source and closed-period evidence and is rejected. A generic negative expense obscures whether an actual refund occurred, risks duplicate cost reduction and cannot safely represent linked stock/payroll liabilities. The proposed approach is a typed request, exact before/after preview, owner decision and linked current-period compensation, consistent with guest-receipt corrections. The owner approved this approach for implementation; executed evidence and limitations are recorded in the delivery change record.

## Scope and authority

Start with verified fully-paid ordinary operating expenses. Staff request corrections on their own recorded sources; the configured owner reviews authorized sources. A submitter is not automatically proven to be the actual payer or historical cash custodian. Unknown payer/funding and ambiguous legacy evidence require reconciliation; do not invent them. Prototype source labels represent explicitly synthetic verified examples.

Types: wrong recorded amount; category; Cash/MoMo or reference; duplicate cost and payout representing one actual transaction. Full duplicate compensation retains and links the evidenced keeper. Other zero-cost cancellations/full voids, provider/payroll expenses, inventory or capital reclassification, supplier claims and recovery remain separate designs. Category changes cannot move a stock purchase or asset into ordinary operating cost.

Only Eugene approves/posts or returns/rejects. He may authorize his own request explicitly; no second approver is fabricated. Requesters can withdraw before posting and revise returned cases. Private reasons and optional evidence stay requester/owner-only. Posted financial facts remain available to authorized financial readers.

## Workflow

1. Under Finance & Cash → Financial Corrections → Expenses, select the original source. Show original/effective amounts, vendor, original recorder, date, category, method/reference, closure status and dependencies before any form.
2. Choose the specific recording error. Amount correction asks separately for total cost actually incurred and total actually paid. Reason is required; supporting PDF/JPEG/PNG up to 2 MB is optional. The prototype selects a filename only; it uploads nothing.
3. Preview exact cost, recorded outflow, category/channel, profit and zero actual money movement. Unsubmitted edits and requests have no financial effect. Supplier returned/owed money routes to a separate review boundary with no pretend successful recovery.
4. Submit a pending case. Eugene independently verifies source, actual cost/payout, keeper/reference, funding, dependencies, current version, posting period and permission. He can return for a retained revision, reject without financial effect or confirm an exact posting. Pending owner cases are visible in the review list; wider external alerts remain separate.
5. Post one linked adjustment in today's open period. Keep original activity date separate. Retain source, proposal versions, original/effective facts, requester, owner, reason, evidence and decision time. Retry must return the same result; changed input requires a fresh current form. A later correction references the current effective position and never erases a prior case.
6. Period Impact retains the closed snapshot and shows the current adjustment once in weekly/monthly ranges. Those are overlapping views of the same entries, never two postings. Management profit follows the existing management-estimate basis; statutory accounting and taxes are not established here.

## Exact financial effects

For the synthetic closed-week example, original cost/payout GHS600 was mistyped: independent evidence supports GHS400 actually incurred and paid. New current-period cost adjustment is -GHS200, recorded outflow adjustment -GHS200 and management profit effect +GHS200. Money moved by correction is zero. Original closed expense/profit snapshots remain GHS8,000/GHS12,000. The open-week example moves costs3,000→2,800 and profit7,000→7,200. These are sample values.

Category correction creates opposite category effects in the posting period with zero total cost/profit/outflow change. Method correction changes recorded Cash/MoMo outflow classification, with zero cost/profit/total-outflow change. It does not assert that a wallet balance changed, send a transfer or alter full gross sales-cash deposits.

A verified duplicate reverses one erroneous cost and one erroneous recorded payout contribution. It does not reverse a genuine second payment. Both originals, reference reservations and retained collection remain visible. Compensated duplicates cannot be resurrected; an incorrect duplicate decision needs separate reconciliation.

If actual incurred cost differs from actual payout, do not automatically force equality. For example, a real GHS600 payment against GHS400 cost may create a supplier claim. This phase blocks simple posting until the distinct obligation and settlement workflow is approved. A real later supplier recovery must not both reduce an already-corrected cost again and become unrelated sales revenue. Do not silently make supplier refunds into Other Income.

## Stop conditions

Linked purchase/stock/provider/payroll sources need source-specific reconciliation and cannot be corrected by this ordinary-expense form. Independent wallet matches block amount/method/duplicate changes pending coordinated reconciliation; category-only correction may retain the original match. Already-recovered money, allocations, cash funding/custody, actual liabilities, missing provenance, source-version changes, invalid authority, closed current periods, reserved references and unsupported keeper evidence must be checked before real posting.

Do not release old references or evidence identities to defeat duplicate guards. Allow at most one pending/returned case per source. Prior legitimate corrections must remain traversable. Retain deactivated staff identities. No schema/backfill/activation or reference reallocation is authorized by the prototype.

## Interaction and verification

Existing Tivoli shell; Financial Corrections remains a finance tab. Expenses, My/All Requests, Owner Review, Corrections Log and Period Impact are local views. Role preview clears private selection/forms when changed. Calls to action scroll to the relevant form, decision or confirmation. Native controls retain keyboard focus behavior. Optional Row Spacing design control offers Comfortable/Compact without changing the workflow.

Executed24 standalone prototype checks: requester visibility, exact effects, canceled submission/approval, owner-only controls, original/effective visibility, fixed closed/open-period figures, private cases, return/revision history, channel and category effects, explicit owner self-request, duplicate keeper/compensation, linked purchase block, rejection, matched-source block, cost/payout mismatch, supplier handoff and1024/390/320 CSS-width layouts. Screenshots inspected for desktop and narrow form layout. No browser exceptions. JavaScript syntax passed. The raw fragment was inspected in a dedicated browser; the inline host remains the delivery surface. This is not evidence of application permissions, transactions, database locks or financial integration.

Acceptance for later implementation: approve this scope and accounting separation, then write the source-specific implementation plan; reproduce failures before new writers; verify exact amounts, immutable originals/closed snapshots, private evidence, stable retries, concurrency, atomic rollback, source/dependency guards, reporting/export parity and independent source review. After real correction events exist, use coordinated forward repair rather than rolling back an individual reader/writer or deleting evidence.
