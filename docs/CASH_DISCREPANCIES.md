# Cash & MoMo Discrepancy Resolution

Workflow 1 of the approved ten-workflow sequence. It extends existing finance records; it never initiates a MoMo transfer or fabricates money received.

## Report a difference

Open **Finance & Cash → Cash → MoMo Deposits** or **Daily Reconciliation**, then select **Report / Resolve Difference** beside the actual source. For a handover, open its details and choose **Report / Resolve Cash Difference**. Select the difference type, describe the facts and optionally attach private evidence. Involved staff and the configured finance owner can read the case; ordinary administrators do not automatically gain access.

One case stays linked to each source. Later evidence, corrections to verification and reopened disputes remain in its retained history. The register is under **Finance & Cash → Cash & MoMo Discrepancies**, with a link from Owner Reports.

## Record a proven outcome

Eugene opens the existing case and selects the relevant action. Enter only the amount affected **by this action**, not a cumulative total. The preview shows the verified, returned and unexplained balances after posting. Check the evidence and confirm once.

- **Record Verified Wallet Credit:** verifies an additional independently evidenced amount. A GHS 500 deposit with GHS 480 verified leaves GHS 20 unresolved. It does not create another receipt or revenue.
- **Record Reversed / Disputed Credit:** removes only the currently supported verified portion from verification. Original confirmations and receipts remain readable. Guest debt, actual refunds and receipt recording corrections are separate workflows.
- **Record Actual Cash Returned:** releases only the uncredited portion actually returned to its evidenced original custodian. Returned sales cash is still due for full deposit under its original next-day deadline. A new real deposit consumes that available cash once. A MoMo receipt has no physical cash to return.
- **Acknowledge Cash Actually Received:** appears only for the named eligible handover recipient. A partial acknowledgement moves only that portion to the recipient; the sender's unexplained residual stays reserved. It does not require an invented external wallet reference.
- **Confirm Existing Evidence:** owner confirmation after all amounts are supported; it cannot erase an unexplained residual.
- **Add Follow-Up:** keeps a comment on the same case. Eugene may assign an active involved person or himself and an optional due date. Assignment does not resolve missing money.
- **Report New / Reopened Dispute:** returns the same case to owner review without silently changing verified amounts. A later authorized evidence decision determines the financial control outcome.

## Safeguards and supported limits

Every critical action uses a persistent operation key and fingerprint under the existing shared financial transaction lock. Repeating the same key/details returns the committed original result. Changed details with the same key are rejected. New keys still face source-version, amount, actor and wallet-reference checks. Required audit failure rolls back the financial event and retry result together.

Private PDF/JPEG/PNG evidence is optional, encrypted in existing private storage and accessible only through authorized downloads. Supported evidence/reference and a reason are needed to change verification or custody; files are not the only form of evidence. No provider PIN/password belongs in a comment or evidence reference.

Original receipts, actual source amounts, staff identities, original confirmations and closed snapshots stay fixed. Financial control changes record today's open posting period and the original source activity date. New posting is blocked when today is closed. Daily reviews do not consume unrelated future receipt cohorts. Owner Reports preserves its as-of cutoff when calculating verification, returned cash and transit; Payment Log retains original transfers and displays their current verification outcome.

Existing fee-bearing deposits remain reportable but partial/reversal/return posting requires separately supported fee reconciliation. Unknown custody or multiple possible original return recipients block a return rather than guessing the recipient. These cases remain visible for follow-up. There is no automatic fee write-off, staff debt, payroll deduction or reduction of gross sales-deposit obligations.

Receipt corrections after deposit, handover or wallet reconciliation remain the next separately implemented workflow. A discrepancy case cannot bypass those existing dependent-evidence guards. External reminders/delivery and the unified owner register remain later items in the approved sequence.

## Recovery

Once resolution events exist, keep the service and all dependent cash, matching, report, handover and evidence readers together. Do not roll back only one reader or drop case events, reference claims, operation keys, evidence or audits. Disable new actions through controlled authorization if needed and prepare a coordinated forward fix preserving originals and committed history. No schema migration, data import or activation is needed for this slice when existing finance/HR/private-evidence setup is available. Local implementation is not authorization for production deployment.
