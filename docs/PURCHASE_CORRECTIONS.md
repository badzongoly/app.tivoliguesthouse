# Purchase & Provider Corrections

Open **Finance & Cash → Financial Corrections → Purchases & Providers**. A posted purchase also has **Correct Linked Record / Follow Up**. A purchase-backed expense redirects here automatically. Legacy approved providers link here from Provider Payments.

## Request and owner review

1. Open the original record. Check the supplier, original date, current cost, recorded paid, still payable and supplier recovery due.
2. Choose the fact to correct: total recorded cost, one specific recorded payout, or linked stock. Enter the evidenced corrected total, reason and optional private supporting PDF/JPEG/PNG (maximum 2 MB).
3. Check the immediate preview and send for owner review. The configured finance owner verifies the exact source and before/after effects, then approves once or returns/rejects. Returned requests retain their original proposal and can be revised by their requester.
4. The posted case retains its decisions, private evidence and adjustment notice. Requesters can withdraw unposted cases. Only the requester and configured owner can view private cases and evidence; an administrator role alone does not grant access.

A recording correction never sends, receives or returns money. Correcting cost does not rewrite recorded payment; correcting one payment does not rewrite cost or other payments. A real overpayment remains **Supplier Recovery Due**. Use Supplier Follow-Up to retain contact and decisions. The amount stays open until a separate independently evidenced money-recovery workflow records the actual receipt; this slice does not fabricate income or clear supplier claims.

## Stock

- **Received quantity recorded incorrectly:** enter the evidenced original quantity and revised total cost. An ambiguous intervening physical count blocks the correction because it may already have accounted for the shortage.
- **Goods actually returned:** enter the actual returned quantity, completed UTC time, unique return/delivery-note reference and evidenced revised total cost. An intervening count after the actual return blocks a second deduction. A new actual return after the latest count can be recorded.
- Original receipt movements remain intact. Approved corrections append a signed adjustment or an actual out movement and update current stock atomically. Returns cannot exceed supported net received or stock on hand. Original approvals and cumulative actual returns remain binding. Costs are explicit; this is not FIFO, weighted-average costing or a cost-of-goods valuation.
- Changed stock, sales, counts or payments invalidate an old approval preview. Return the case for fresh evidence rather than bypass the check.

## Actual remaining payment

Modern purchases use their existing Payment tab for a completed full or partial Cash/MoMo payment against the effective remaining balance. Approved legacy providers use **Record Actual Remaining Payment** on their linked source: actual payer, actual UTC time, independent completion evidence and MoMo reference are required. Cash needs separate funding and never reduces the full cash-receipt deposit obligation. The app records completed payments; it does not send money.

Historical payer, date, method and original payment/stock links must be supported. Unknown evidence remains unknown. Unsupported payout mirrors stop further payment instead of making an already-paid purchase appear unpaid. Corrections cannot expand the existing approved/accepted spending ceiling or rewrite old approval dates.

## History and retries

Original expenses, provider approvals, payouts, receipts and stock movements are preserved. Adjustments post in today's open period and retain their original activity link. Finance totals, expense ledger, payment log and reports retain original rows plus compensating entries; closed snapshots stay fixed. Private decision reasons are excluded from printable notices and general finance activity.

Every critical submission uses a durable operation key. If the connection fails, retry the same action with the same details and file. Do not create a second form to force a repeat. Shared transaction locks, source versions and required audit protect posting; a required audit failure rolls back stock, money projections, decisions and the operation result together.

No additional schema activation is required by this slice. Existing finance, private evidence and configured owner setup must already be available. This is local implementation, not production deployment. Preserve all original and compensating events, claims, operations, audits and encrypted evidence during recovery; use coordinated forward correction rather than deleting cases or rolling back only a reader.
