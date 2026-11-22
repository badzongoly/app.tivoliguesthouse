# Payroll & Staff Payments

Open **Payroll & Payslips** in the main menu to reach Payroll & Staff Payments. Eugene needs the configured finance-owner account and salary authority. Staff use **My Payslips**, with their own HR access.

## Before starting
In Staff & HR, verify the correct staff-to-user link, active employment, effective monthly salary, preferred MoMo provider, registered payee and number. A future-effective salary is not used early. The app displays only the masked destination for payroll; check the actual destination securely in HR before making a transfer.

Check **Legacy Payroll** and retained records first. One staff/month may have only one source. An existing claim, including a rejected legacy source, prevents a new draft; reconcile that source rather than inventing a replacement payment.

## Monthly workflow
1. Start payroll for the staff member and service month, confirming it has not already been recorded.
2. Review the effective salary and enter any allowances, overtime, reviewed deductions and employer costs. Explain every nonzero adjustment. Review attendance and approved leave separately; the app does not calculate attendance deductions, taxes or salary proration.
3. Prepare for review. Preparation creates no financial cost. Eugene may return it for revision with a reason, or approve the exact amounts and destination.
4. Approval freezes the salary revision, payee, masked destination and calculations. It records gross pay plus employer costs once in today's open financial period.
5. Make the MoMo transfer outside the app. Record its actual UTC time, exact amount and unique transaction reference. Optional PDF/JPEG/PNG evidence is private to Eugene, up to 2 MB. Partial transfers reduce the remaining employee balance; recording a payment adds no second cost.
6. When the employee balance is zero, verify the named linked account and publish the payslip. Staff see only their own published statement. Internal comments, employer costs and payment evidence remain private. A zero-net statement requires no fabricated transfer.

Changing an HR link after the publish form was opened requires reloading and checking the recipient again. Later relinking does not transfer an existing payslip to another account. Published statements cannot be silently revised.

## Reports, dates and history
Monthly Payroll filters by service month. Owner Review includes unpublished drafts, review items and settlements. Staff Costs filters by posting dates and includes retained legacy costs. Payment Log shows actual payroll references to Eugene and redacted source rows to other existing salary-authorized finance viewers. Financial totals are exact GHS amounts; actual transfer times and display dates use UTC.

Approval and settlement post to today's open period while retaining service month and actual transfer time. Closed totals remain fixed. Prepared unapproved costs block weekly/monthly completion. Do not backdate or reopen a closed period through payroll.

Existing legacy payroll records remain unchanged. Their new review/settlement decisions require Eugene; he may approve his own old request. New payroll cannot use old review/payment routes. Provider authority is unchanged.

## Corrections and limits
Do not delete history, repay a known transfer or create an expense to duplicate payroll. Use the configured owner’s linked [Payroll Corrections](PAYROLL_CORRECTIONS.md) workflow for evidenced component or recorded-transfer amount errors, existing MoMo top-ups, private recovery follow-up and revised payslips. Original approvals, transfers and issued versions remain retained. Legacy requests lacking frozen salary/payee evidence remain gated.

Employee settlement does not establish that withheld deductions or employer contributions were remitted. Automatic attendance, tax calculations, advances, final pay, statutory remittance, payment APIs, scheduling and external notifications remain separate work.

If submission is interrupted, retry the same action with its original form key and, where requested, the same evidence file. A changed request needs a refreshed current record. No new database migration or activation is required beyond the existing HR, finance, encryption and owner configuration.
