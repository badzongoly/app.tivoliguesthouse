# Stock Costing & Profit Accuracy

Owner-approved implementation, 21 November 2026. This is an additional management cost basis in **Finance & Cash → Profit Review**. The original recorded-cost result, receipts, expenses, stock movements, supplier payments and historical closed snapshots remain retained. Extras & Stock links to the same review; there is no separate daily costing form for staff.

## Start simply

1. Finish posting and reconciling received purchases first. Complete an existing **Stock Count** and have the designated owner approve it. The item must be tracked, unchanged since that count, and have no reported holds. Take a fresh count if necessary.
2. In **Profit Review → Stock Costing**, find the item and select **Set Opening Cost**. Select the approved count. Enter its supported **total opening cost**, not the selling price or today's replacement price. Enter the evidence reference/comment and verify the original evidence. Leave the total blank when unknown.
3. Optionally link an already accepted purchase only when it supports the complete opening quantity and total. A newer purchase cannot be reused to value earlier stock.
4. Staff continue using their existing purchase, guest-extra, count and non-sale issue forms. Those records supply the cost sources automatically. Purchase receiving recognizes stock cost even when the supplier has not yet been paid. Paying a supplier does not recognize the stock cost again.

Starting valuation adds no stock quantity, purchase, expense or payment. Each item has one immutable opening; later evidence creates a linked entry. Exact supported zero cost is allowed after owner verification; an unavailable cost is explicitly unknown.

## What the review shows

Moving weighted average uses integer pesewas and hundredths of units. A sale consumes its share of current supported carrying value. Fractional rounding is conserved in the remaining pool, so the final unit receives the remaining value.

The bridge shows the original recorded-cost result, adds back **eligible linked inventory purchase expenses** once, and deducts stock sold, verified losses, actual complimentary supply and supplier-return differences. Opening stock is carry-forward value, not a new purchase. Cash deposits, supplier settlement and other payments remain separate from profitability.

An approved unusable damage/expiry/missing report recognizes the verified carrying loss immediately. Physically held written-off goods remain in recorded on-hand stock until their existing disposal action. Disposal does not recognize a second loss. Complimentary stock is costed when actual supply is confirmed, not when permission alone is granted. A previously approved negative stock count is costed once; linking its later disposition adds no quantity or cost deduction. Correcting an uncompleted mistaken issue reverses its prior approved carrying loss, preserves the original approval and resets the decision for any remaining quantity.

Unknown opening costs do not borrow current selling/purchase prices. Consuming unknown stock leaves profit coverage incomplete. Later supported opening evidence is replayed across the retained sources to split consumed and remaining value. The resulting deltas are recorded in the current open period. Evidence for a linked opening purchase is corrected through existing **Purchase Corrections**. Supported linked opening quantity corrections adjust the effective replay basis while preserving the original physical count and opening record.

For a supplier return, stock leaves at its carrying cost. Its difference from the verified supplier credit is shown separately. Correcting an original receipt quantity rebases that receipt; it does not invent another purchase or consumption. A correction to a fully consumed purchase outside the opening/post-cutover receipt scope stays an ordinary historical cost correction, without a fabricated inventory addback. Unmapped physical changes require source review and cannot silently become supported inventory.

## Dates and closed reviews

All accounting dates/times remain UTC. Source posting order controls the moving-average pool. An original delivery/activity date remains on its source; the acquisition addback follows the original eligible expense/correction posting date. Cumulative valuation snapshots and newly discovered consumed-cost deltas are recorded **now**, in an open period, so late data cannot create a historical state containing later activity. An explicitly reviewed late opening-source classification also posts now.

The cutover day is partial: existing expense reports have dates and do not provide safe intraday proration. A week/month crossing cutover is marked partial. Unstarted inventory, unknown consumed costs and unsupported movements remain visible. Historical reviews check source coverage through the selected end date as well as current views. Unsupported quantities are excluded from supported inventory value. A receipt whose actual delivery predates the physical opening count may already be included in that opening: it requires source reconciliation rather than another silently supported asset value. Legacy negative balances remain readable as incomplete; their records are not rewritten.

Weekly/monthly reviews can retain their stock-cost result **and its coverage warning** with the existing snapshot. Closing a cash review does not magically complete missing stock costs. The owner Profit Review displays the retained closed snapshot rather than recomputing it from newer evidence. A review closed before stock costing has no invented historical stock-cost snapshot. Reopening retains the original close event.

This remains a management estimate using the existing room/extras/other-cost definitions. Room revenue gaps, active-stay exclusions, tax/statutory accounting and opening cash custody are not repaired by stock costing. Positive count surpluses or unsupported legacy stock sources are not assigned guessed values; they require evidenced source reconciliation.

## Accountability and technical boundaries

- The configured finance owner with report authority controls opening costs, evidence and cost reviews. Another administrator role alone does not grant this authority. Staff do not receive private cost evidence through Finance Activity.
- All writes use the existing PDO transaction and shared posting lock. Source movement/event, cost source claim, valuation, operation result and required audit commit or roll back together.
- Existing `finance_operations` keys return a prior identical result and reject changed details. Unique `finance_claims` scopes prevent duplicate openings and source capture. Concurrent openings, loss approval and evidence/sale ordering are covered by isolated tests.
- Records are append-only in existing `finance_events`. `ic_opening`, `ic_evidence`, `ic_source` and `ic_valued` keep original links, actors, recording/effective dates, deltas and source evidence. Cost journal entries do not masquerade as expenses or payments.
- No schema migration, backfill, new role, credential change, database import or ordinary database activation is needed. Cost capture starts only after the owner reviews an item's opening in that database. Existing finance setup must already be installed.

## Verification and recovery

See [delivery evidence](delivery/changes/2026-11-21-stock-costing.md). Repeatable checks are `tests/Unit/inventory_pool.php`, `tests/Integration/stock_costing.php`, `stock_costing_review.php`, `stock_costing_concurrency.php`, `stock_costing_legacy.php`, `stock_costing_fault.php`, `stock_costing_complete.php` and `tests/Contract/stock_costing_http.py`. Integration/worker/HTTP seeds explicitly refuse any target except the marked synthetic database on port 3307; HTTP checks refuse an unmarked server. The tests create synthetic records and must never be pointed at an ordinary/production database.

Before an ordinary owner opening, a coherent code rollback can restore the reviewed pre-feature source/UI files. After costing starts, retain all journal entries, source claims, operation keys and closed snapshots; do not selectively remove writer hooks, delete entries, reset stock or silently switch profit bases. Stop affected posting and use an owner-approved, source-linked fix forward. A live target, snapshot/recovery evidence and explicit deployment authorization are separate from this local implementation.
