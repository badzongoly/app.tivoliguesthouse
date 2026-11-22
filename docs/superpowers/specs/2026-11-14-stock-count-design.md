# Approved Stock Counts and Variance Review

Owner approved tivoli-stock-count-approval.html for implementation on 14 November 2026. Eligible staff capture blind saleable-stock counts and account for separately held damaged/expired quantities in notes. Eugene reviews differences, disputes, or requests linked recounts. Preserve original submissions. Reasons: Missing / Unexplained, Damage, Expiry, Surplus Found, Count / Recording Error, Other. Optional private PDF/JPEG/PNG evidence up to 2 MB. Owner self-counting needs an explicit self-review exception reason.

Use existing finance_events/operations, stock_movements and security_audit, shared transaction lock and exact hundredths preserving fractional stock. No migration or monetary postings. Submission changes no stock. Approval applies counted-minus-expected to latest balance atomically once. Reject negative results. No automatic expense, payment, staff liability, valuation/COGS or disposal authority.

Begin Counting per item captures balance and movement watermark/provenance. Finish rejects intervening movements and requires a new window/physical recount. Later sales/deliveries stay intact. Other adjustments invalidate approval and require linked recount. Recheck owner preview token. Reject unexplained balance/movement changes rather than guessing.

Requester and configured owner alone view private details/files. Staff need bookings.manage or stock.manage; owner additionally stock.manage to adjust. Revalidate HR/account eligibility. Hide baselines from staff until all selected items complete. Draft revisions, aborts and cancellations remain append-only. Recount submission supersedes original; starting/canceling recount does not close original. Retain identical item scope for recount.

Retire public immediate-count routes, retain old movements and trusted legacy service interfaces for compatibility/tests, not public alternatives. Explicit Apache allowlist for new endpoint. Owner/handover in-app attention. No external alerts.

Execute inline in current checkout preserving existing uncommitted work. No Git state changes, ordinary DB test writes or production deployment. Exact marked synthetic database/service only.
