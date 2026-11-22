# Financial Corrections

Open **Finance & Cash → Financial Corrections**. Guest receipt recording corrections include linked deposit, cash-handover and existing wallet-proof reconciliation. Guest Charges & Refunds and Expenses have their own correction tabs. [Guest charge corrections](GUEST_CHARGE_CORRECTIONS.md) use the existing guest account and one owner approval; actual refunds remain separate completed payments. Payroll retains its separate workflow.

## Request a correction
1. Find the original receipt here, in Payment Log or in the Guest Stay Account.
2. Verify the guest, room, original receipt, cashier, original time, current effective position and linked records.
3. Choose **Correct Recorded Amount**, **Correct Method / Reference**, or **Duplicate Recorded Receipt**. Enter a verified total amount, not the difference. Cash and MoMo are the supported methods.
4. Explain what actually happened. A PDF, JPEG or PNG up to2 MB is optional and accessible only to the requester and Eugene.
5. Review the exact adjustment, current guest balance and the statement that no money moves. Submit for owner review.

Staff can request corrections only on their own recorded receipts. Eugene can handle authorized guest receipts including retained historical records, without inventing their original cashier or custody. Only the requester and Eugene see private case notes/files. Other authorized financial readers can still see original receipts and posted financial adjustments.

## Eugene's review
Open **Owner Review** or the pending-request link in Owner Reports. Verify actual collection evidence independently and read the exact before/after method, reference, amount and balance. Do not treat an uploaded file or staff entry as provider verification.

Record your verification/decision reason and either **Approve & Post**, **Return For Revision**, or **Reject**. Approve & Post asks for confirmation. Eugene may authorize his own request under his configured owner authority; another administrator does not gain that authority automatically.

A returned request can be revised and resubmitted by its requester. Earlier versions and evidence remain. The requester may withdraw an unposted request. Return, rejection and withdrawal create no financial adjustment.

## What posting does
Posting adds a linked recording adjustment in today's open period. It does not edit/delete the original payment, release its references, create a payment receipt, move cash, transfer MoMo, refund a guest or change charges/earned revenue/profit.

The original accounting period and closed snapshot stay fixed. Reports show adjustments by posting date, separately from original entries. Guest balances use the resulting effective receipt position. A reduction can create a debt; an increase can reveal recorded overpayment. Neither automatically authorizes a refund or writes off a debt.

For checked-out stays, original checkout evidence, departure time and room status remain unchanged. Eugene collects any actual new money through the existing separate recovery workflow. The correction and real recovery are counted once each.

## Stops requiring reconciliation
Deposits, handovers and wallet matches retain their original amounts and history. A dependent correction requires the linked-money review described below in the same request and owner decision. Historical cash without a known custodian is not reassigned by guessing. Unknown room charges or unfinalized departure incidents block unsafe posting.

The proposed receipt total cannot be below already recorded refunds. Duplicate identification needs an evidenced retained receipt for the same stay, method, amount and collection. A shared reference alone is not proof of duplication.

A receipt compensated as a duplicate remains linked to its retained collection. Continue supported corrections on that retained receipt; do not resurrect the compensated duplicate. If the duplicate identification itself was wrong, request a separate evidenced reconciliation. Retained alias claims stay immutable while their linked canonical collection resolves through later verified deduplication.

A changed source, other payment/refund, keeper or dependency requires new review. Return for revision, then recheck the current guest account. Do not repeatedly approve stale details.


## Correcting a receipt after deposit, handover or matching

Use the same receipt correction form. **Reconcile Linked Money** appears only when existing money records are linked. Review those records, choose a real supporting receipt if needed, and confirm the linked evidence. Eugene sees **Linked Money Review** with the original movement, funding before/after, responsible staff and wallet proof before approving once. No extra main menu or second approval is required.

For example, a receipt was recorded as GHS 500 but only GHS 480 was actually received. An actual GHS 500 deposit must still be explained in full. Select a real, unused GHS 20 cash receipt collected by the same person before the deposit. The deposit remains GHS 500; its current funding becomes GHS 480 from the corrected receipt plus GHS 20 from the supporting receipt. Do not invent a supporting receipt to make the numbers fit. Without evidence, leave the correction unposted and resolve the discrepancy.

- Cash increases and decreases entirely within still-held cash need no replacement funding. Actual deposited and handed-over amounts remain fixed.
- Supporting cash must be unused, from the evidenced original collector, received before the first affected movement, and sufficient for the difference. Already moved, compensated, empty or actively disputed correction sources cannot be reused. The server replays partial acknowledgements, deposits and returned cash to conserve custody and deposit deadlines.
- A duplicate uses the evidenced retained receipt for the same stay, amount, method and actual collection. Both original rows, reference identities and historical movements remain retained.
- If verified MoMo credit exceeds the corrected amount, select the real existing MoMo receipt that owns the difference and has unmatched capacity. Only one unambiguous existing independent merchant-proof pool is supported; its original reference claim stays retained. No new wallet credit or payment is created.
- Unreviewed or reopened wallet disputes must be reviewed first. Multiple credit pools, reversed proof and unsupported custody remain blocked for specific evidence reconciliation. A verified receipt reference may be changed only to its own sole independently verified proof reference; an unrelated reference cannot inherit verification.
- Changing Cash to MoMo does not verify a merchant credit. Changing MoMo to Cash requires the existing verified wallet credit to remain allocated to a real eligible MoMo receipt. Its historical discrepancy case remains readable.
- Source, supporting receipt, custody or proof-history changes invalidate queued approval, even when totals happen to remain equal. Return for revision and check the refreshed preview. Concurrent actions cannot spend the same supporting cash or matching capacity twice.

Posting commits the recording adjustment, linked allocation views, owner decision, durable retry result and required audit together. If any required write fails, all financial effects roll back. Original payments, movements, staff attribution, checkout facts and locked snapshots remain unchanged. Current readers use the appended allocations; an earlier as-of report keeps its earlier allocation view.

If a corrected MoMo amount increases beyond its already verified credit, verify only the remaining real credit through **Report / Resolve Difference**. Existing full-match links route to that workflow once previous matching or allocated proof exists. Never match the entire increased amount a second time.

## Matching, reports and notices
Corrected MoMo receipts still need independent matching against the current effective amount/reference. References from corrected collections remain reserved across guest receipts, activity income, deposits and payouts. A correction does not make those references available for a second payment.

Payment Log retains original rows, explicit recording-adjustment rows and current effective receipt details. Incoming net is distinguished from original entries and recording adjustments. Channel reclassification can show negative Cash and positive MoMo without new money. Owner matching alerts use current effective receipts as of the report cutoff; monetary history keeps its original/posting dates.

A posted **Adjustment Notice** shows original receipt linkage, before/after facts, authorization and posting details. It is not a payment or refund confirmation. Its printable view excludes private review reasons and files. Full private history remains in the case.

If a submission is interrupted, retry the same action with its original operation key and the same evidence file when requested. A confirmed retry returns the original result. Changed details need a fresh current form.

No new migration or activation is required beyond the existing finance/HR configuration, protected evidence storage and designated owner setup. This guide describes the implemented local workflow; production deployment requires separate approval and verification.


## Purchases & Providers

Use the existing Purchases & Providers correction tab for supported linked cost, specific payout or stock corrections. A purchase-backed expense routes there after source authorization. See [staff guide](PURCHASE_CORRECTIONS.md) for owner review, actual partial settlements, stock-count safeguards and separately tracked supplier recovery due.


## Payroll

The configured owner with salary authority can open **Payroll** from Financial Corrections or use **Correct Payroll** on the existing approved source. See [Payroll Corrections](PAYROLL_CORRECTIONS.md). Recording errors, actual top-ups and private overpayment follow-up remain separate; no original approval, transfer or issued payslip is overwritten.
