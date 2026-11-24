# Retire routine MoMo receipt matching

## Behavior

Guest and activity MoMo receipts count as successful when recorded. A routine payment creates no matching task, extra receipt, wallet-evidence event or claim. Original payment amounts, receipt IDs, accounting dates, historical matches, financial claims and locked snapshots remain protected.

Staff report actual failures, shortages and reversals from the original payment. The discrepancy workflow retains the requirement for evidence when recording a resolution. Open MoMo cases remain in owner review and can block reconciliation close for the source period. Cash deposit confirmation, full cash deposits, payout evidence and existing role checks continue to apply.

Saved matching form links open the existing discrepancy route. A fresh submission of the retired action is rejected. Existing durable operation replays still resolve to the original historical result.

## Verification

Finance integration passed 39 checks, finance changes passed 43, and cash discrepancies passed 28. Weekly review passed ordinary-payment close, reported-difference blocking and resolution, and immutable snapshot checks. Focused daily and monthly tests closed normally with a clerk-recorded payment and no match event. Six owner-report correction checks passed, including the historical cutoff and reported-difference status. Receipt reconciliation passed 13 checks, including retained wallet evidence and unchanged original deposit records. The reconciliation matrix passed 21 checks and historical reference corrections passed two. Guarded HTTP checks verified the daily view, saved links, stale form denial, the cash deposit confirmation form, and the recording clerk opening a discrepancy directly from a guest payment.

Checks used the verified synthetic test services. This is a source-only change with no schema migration, historical backfill or hosted deployment. Restoring a routine match requirement would require an explicit policy decision for receipts recorded during this period. Preserve original records and use the discrepancy and correction workflows for real exceptions.
