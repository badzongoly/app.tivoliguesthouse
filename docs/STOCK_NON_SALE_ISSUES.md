# Stock Damage, Expiry & Complimentary Use

Open **Extras & Stock → Non-Sale Issues**, or select **Report / Request Issue** beside a tracked stock item. Use an individual staff account. The configured finance owner with stock authority approves and confirms disposition; another administrator role alone does not grant that authority.

## Report or request

1. Choose the stock item, Damage / Expiry / Missing Stock / Complimentary Use, quantity and current position. Quantities retain existing exact hundredths of a unit.
2. Enter the actual incident, finding or request time in UTC. Comment and supporting PDF/JPEG/PNG up to 2 MB are optional. Describe where unsafe goods are secured or who the complimentary items are for when useful.
3. Review the preview and submit once. Normal reports immediately hold that quantity from sale. Recorded on hand stays unchanged while admin reviews. Existing reported holds cannot be sold or consumed by a supplier-return correction.

For **Still Held / Not Yet Supplied**, keep damaged/expired goods secured and wait for approval before complimentary supply. For **Already Disposed / Supplied / Missing**, record the evidenced facts; do not select this to bypass an approval. Admin verification records the actual outflow once, but does not retrospectively authorize an earlier unapproved supply. A missing item cannot also be physically held.

Shared authorized inventory staff can read issue history and its protected supporting evidence. Only the original reporter may clarify a returned report. Original stock-count details remain restricted to their counter and configured owner.

## Admin decision and physical disposition

Open **Show Details → Review & Approve**. Verify the item, quantity, actual position and original evidence. Held goods are approved without reducing recorded stock. Use **Confirm Actual Disposal**, **Confirm Actual Supply**, or the missing-stock confirmation when appropriate, only after verifying the actual disposition. Its actual UTC time is retained alongside the recording actor/time. The app appends one original-linked stock movement and releases that issue's hold together.

Already-gone goods can complete during approval after actual-outflow verification. No sale, receipt, payment, expense, automatic staff debt or salary deduction is created. For an item with an owner-reviewed opening basis, the linked [Stock Costing & Profit Accuracy](STOCK_COSTING.md) journal recognizes verified unusable stock at approval and actual complimentary stock at supply. Physical disposal does not recognize a second loss. Items without a supported cost basis remain explicitly incomplete; no purchase cost is invented.

**Return For Clarification** requires a comment and retains the original report and hold. The original reporter adds the answer and optional supporting file, then resubmits for review. Returning a report never automatically makes unsafe items saleable.

## Stock counts and previously deducted goods

Counts compare physical saleable quantity with **Recorded On Hand minus reported holds**. Their variance is applied once to raw recorded stock. If hold evidence changes after counting, begin the affected item again and physically recount. The approval retains which exact issue quantities were excluded, so later disposition can be distinguished from another loss.

For goods already deducted by an approved negative count, choose **Already Deducted By Approved Count** and the exact matching item/reason/full loss. Only matching approved counts that the counter or owner may access are offered; exact movement evidence is validated before linking. The original adjustment must match, and the same count/item loss can be claimed once. Disposition then records **zero additional quantity deduction**. An incident/finding must be no later than the original physical count time; a later approval date cannot absorb a new loss.

An unlinked late report is blocked when an intervening approved count may already include its loss. Obtain source review and the exact supported link. Do not create a new unlinked report, fabricate dates, split an unsupported count or guess a historical movement to bypass that evidence stop. Old counts without explicit hold evidence remain conservative stops when relevant.

## Correcting a mistaken report

Before completion, admin may use **Correct Mistaken Report** on an unlinked report to reduce an erroneously entered quantity. Physically verify that the excess released from the hold is safe and saleable; enter the reason and confirm the exact correction. Zero cancels the mistaken hold. This appends a correction, retains the original report and revokes its earlier approval. A nonzero remaining quantity needs fresh approval.

This action cannot increase scope, rewrite an item/reason, cancel an actual completed outflow, release unsafe goods, or change an already-claimed count allocation. Those exceptions require evidenced source review. Never record a false disposal merely to clear an incorrect hold.

## Retries, history and operation

After interruption, retry the same action with its original operation key and identical details/file. Persistent actor/key uniqueness returns the original result. Changed details require a fresh form. Every command checks current authority, source version and, for owner decisions, current stock/evidence preview. Shared transactions protect competing sales, reports, counts, corrections and dispositions; required audit failure rolls back the stock/events/operation result.

Original stock movements, stock-count evidence and all financial records remain retained. Quantity-only issue events record today's date and preserve actual activity time separately, without changing saved closed financial totals. Protected evidence is encrypted and served only through authorized downloads; it is not public stock media.

No new schema activation is required beyond existing finance/stay/HR configuration, configured owner and private evidence storage. This is a local implementation, not a production deployment. Preserve all original and compensating events, movement links, count claims, operation results, audits, encrypted evidence and keys during recovery. Correct forward with reviewed evidence; do not delete a posted issue or remove a hold event, release a count claim, overwrite a prior count or revert one reader/writer independently after events exist.
