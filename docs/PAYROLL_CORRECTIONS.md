# Payroll Corrections

Open **Payroll & Payslips → Corrections**, or open an approved payroll and select **Correct Payroll**. The configured finance owner also has a Payroll link under Finance & Cash → Financial Corrections. An administrator role or salary-viewing permission alone does not grant correction authority.

## Correct figures or a recorded transfer

1. Open the original approved staff/month record. Check the employee, original salary revision and masked payment destination. These remain fixed; today's HR terms are not substituted into historical payroll.
2. Choose **Approved Payroll Components** or **One Recorded Transfer Amount**. For components, enter the evidenced base, allowances, overtime, reviewed deductions and employer costs. For a transfer, select the exact original reference and correct the amount only if it was recorded incorrectly. A real overpayment must remain outstanding.
3. Enter the private reason and reviewed calculation/transfer evidence reference. A private PDF/JPEG/PNG up to 2 MB is optional. The separate Employee Statement Note is the only new explanatory note included on an issued employee version; keep private evidence out of that field.
4. Review the exact preview: staff cost and its posting-period adjustment, employee net, recorded paid, amount still due and private recovery due. Confirm once. This records a correction; it does not send or return money.

Payroll cost is gross pay plus employer costs. Employee net is gross pay less reviewed deductions. Employee payments settle that liability without adding a second cost. The app does not calculate tax, attendance deductions, proration or statutory remittances; use reviewed component evidence.

Original approval, staff/salary/payee snapshot, transfer rows, reference/time, recorded actor and earlier payslips remain retained. Unknown historical actual payer stays unknown; the correction's verifier is recorded separately. Current figures and the separately dated correction history do not rewrite the original approval date.

## Underpayment and overpayment

For an underpayment, use the existing **Record Completed MoMo Payment** action. Record only a completed transfer to the original verified destination, using its actual UTC time and unique reference. Partial top-ups are bounded by the effective remaining balance.

An overpayment becomes **Private Recovery Due** on the original payroll. Use **Private Recovery / Source Follow-Up** for contact and next actions, with optional private proof. A note never records recovered money or clears the balance. No automatic deduction is added to another salary. Actual monetary recovery and any revised-statement policy for an unresolved recovery remain separate work; the approved prototype blocks revised publication while recovery due is positive.

## Revised and original payslips

When employee due and recovery due are both zero, verify the linked active employee account and choose **Publish Revised Payslip**. The first issued employee account remains binding: relinking HR does not transfer historical statements or silently redirect a revised one.

Employer-cost-only corrections with unchanged employee components and no employee note stay private and do not require an unnecessary employee reissue. Employee-affecting changes require review and settlement before revised publication.

Employees see their latest issued figures, with links to earlier issued versions. Earlier versions are identified as historical once a newer version is issued. While a revision is being reviewed or settled, the earlier issued statement remains visible with a neutral pending-review note. Employer costs, private reasons, evidence files and recovery contact notes are excluded. New versions identify linked revisions and the recording authority without exposing those private details.

Only the configured owner with salary authority can inspect full correction/recovery history or download proof. A salary-authorized general payment-log viewer sees redacted payroll rows and recording adjustments. Staff Costs and Finance cost/profit review include separately dated adjustments; payment recording corrections are distinguished from actual outgoing transfers.

## Evidence stops and retries

- Unsupported or mismatched original cost, transfer, destination, approval or MoMo-claim links block new financial actions. A missing mirror never turns a known paid payroll into an unpaid source. Investigate the originals rather than creating a replacement staff/month.
- Retained legacy requests without a frozen salary/payee snapshot remain available for original-evidence review. This workflow does not reconstruct them from current HR details. Their existing authorized legacy review/settlement is preserved.
- A different employee, destination, independently matched payout or unsupported historical evidence needs separate reconciliation. Source changes require reloading the current record.
- Corrections and actual top-ups post in today's open financial period and retain the original service month. Closed totals and saved snapshots stay fixed.
- Every critical action uses a durable operation key. After a connection failure, retry the same action with the same details and proof file. Required audit failure rolls back the accounting/private events and operation result together.

No additional schema activation is needed beyond existing HR, finance, encryption, private evidence and configured-owner setup. This is local implementation; production deployment requires its separate reviewed authorization. Retain all original and compensating events, claims, operation results, audits and encrypted evidence during recovery. Use coordinated forward correction rather than deleting a posted case, overwriting a payslip or reverting just one reader.
