# Guest Charge Corrections & Refunds — 18 November 2026

Status: workflow 3 implemented and verified locally. Production deployment/parity is not claimed.

## Result and contracts

Existing Guest Stay Account → Show Details and Finance & Cash → Financial Corrections → Guest Charges & Refunds now provide source-linked charge requests with one configured-owner approval. Supported correct totals apply to finalized known room charges, fulfilled extras and approved incidentals. Exact previews distinguish charge/revenue/profit effects, current guest balance, unchanged receipts/stock and zero actual money movement. Private optional encrypted evidence, requester/owner scope, return/revision/rejection/withdrawal, retained earlier proposals/history and guest adjustment notices are included. Owner Reports links open requests.

Posting appends gc_posted plus decision, persisted finance_operations result and required audit in the shared serialized PDO transaction. A source can have only one unresolved request; source, account and receipt-correction versions are rechecked. Identical key/detail retries return the same result; different keys and concurrent writers cannot duplicate the financial effect. Original bills, price/rate/quantity snapshots, original financial IDs and actors, payments, stock movements, checkout/room facts and closed snapshots stay unchanged.

Current/frozen/provisional bill projections use charge cursors so pre-checkout or pre-report corrections are included once. Itemized statements retain original room/extra charges and list adjustments separately. Existing billing export uses the same authoritative corrected totals. Finance room/extras/incidental revenue and management profit include open-period corrections; Owner Reports respects as-of cutoffs. Extras daily series and top12 net ranking include linked posted adjustments without altering sale quantities or original sale details. Generic finance audit views exclude private gc payloads.

Actual refunds remain a separate existing StayService action, now explicitly configured-owner-only before both posting and replay. New refund submissions require actual Cash/MoMo method, actual UTC time, actual payer, completed-payment reference, reason and completion attestation. Current guest credit and remaining effective original receipt after prior refunds bound the amount; terminal stays need verified final bills. New payload fields retain actual versus recorded time/actor; historical unknowns remain unknown. Late refunds compare evidenced original collection dates rather than confusing later receipt-recording time with actual collection. Original closed periods stay fixed. Payment Log displays the actual method; guest history shows method/time/payer/recorder. Cash refunds never reduce full gross cash-sales deposit obligations or create another charge reduction/expense.

## Files and schema

New ChargeAccounting and GuestChargeCorrectionService; private internal guest-charge-corrections controller through financial-corrections.php?area=charges, shared templates and guest-charge-corrections.js. Updated StayService/DepartureIncidents, DepartureRevenue, FinanceService, OwnerReports, FinancePaymentLog, FinanceAttachment, guest account/refund form, correction tabs and owner attention. No new schema, main menu, dependencies, production configuration or activation step. Two pre-existing corrupted separators in the updated occupancy guide were repaired as text only.

Intentional refund contract change: old submissions without explicit actual method/time/payer/completion evidence are rejected. Only the configured owner may refund; an administrator role alone is insufficient. Updated synthetic legacy refund fixtures retain their original accounting assertions and provide the now-required evidenced fields. This does not relabel historical refund rows.

## Executed verification

- 29 integration suites / 685 assertions. New core16, source-review20, matrix35, concurrency22 (six new real competing-process checks) and top12 ranking1 passed. Existing stays137, departure incidents42, receipt/dependent corrections, cash discrepancies, finance/closing, handovers, expense and owner reports passed.
- 12 HTTP/access/route suites / 265 assertions. New charge/refund contract23 proves guest/source context, CSRF, private evidence/case authority, owner queue and posting, retries, notices, completed Cash refund and payment-log handoff. Existing receipt, finance, handover, departure, cash discrepancy, owner, access, Apache and historical-table guards passed.
- Twelve actual browser views at1440/390/320: request and owner preview, canceled request/approval/refund confirmations, separate Cash refund, focused actions, original-plus-adjustment statement and cleared balance. No runtime errors or page horizontal overflow; screenshots inspected.
- 31 PHP syntax checks, JavaScript/Python syntax, UTF-8 and scoped Git diff checks passed. Working root remains C:/xampp/htdocs/app.tivoliguesthouse, branch main; Git state was not changed.
- Independent source review reproduced two Important defects (provisional double application and refund without final evidence), then confirmed their fixes. The late-collection-time, historical fractional-cent and report-ranking regressions were also exercised RED/GREEN. Final read-only re-review reported no remaining Important findings; reviewer did not claim test execution.

Commands: PHP tests/Integration/guest_charge_corrections.php, guest_charge_review.php, guest_charge_matrix.php, guest_charge_concurrency.php, guest_charge_ranking.php plus affected existing suites; Python tests/Contract/guest_charge_http.py plus affected contracts; dedicated browser script; PHP -n -l, node --check, Python syntax and scoped git diff --check. Per-suite evidence: .local-setup/guest-charge-verification-summary.json. Tests used only verified127.0.0.1:3307 / tivoli_auth_test / synthetic-auth-only-v1, marked synthetic HTTP8099 and dedicated browser9236. Existing isolated installer/guard replay tests ran; no ordinary database activation/migration or real data fixture was used.

## Limits and recovery

Unknown historical dates/rates, terminal stays without final evidence, provisional departures, inconsistent line totals and fractional-pesewa original lines remain evidence-gated. Room corrections require finalized charges; they do not change active room tariffs. Stock return/disposal, tax/fiscal invoices, write-offs, receipt resurrection and provider money transfer are not introduced. Actual credit/refund and debt/recovery remain distinct. No provider API verification or transfer is implied.

No ordinary database test writes, financial-history deletion/update, new feature schema migration/activation, Git mutation, push or production deployment occurred. No backup/restore drill is claimed. Preserve all original and compensating events, snapshot cursors, operation keys/results, claims, audits and private evidence. Recover by coordinated forward correction across affected readers/writers; do not delete gc events, change original bills or roll back only one dependent file. Retry uncertain submissions with their original key and identical details/evidence.

See [staff guide](../../GUEST_CHARGE_CORRECTIONS.md). Next authorized workflow: Purchase & Provider Corrections.
