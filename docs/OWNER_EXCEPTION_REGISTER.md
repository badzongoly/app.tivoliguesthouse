# Owner Exception Register

Available locally from **Owner Reports → Open Owner Exceptions**, or **Overview → Owner Exceptions**. Only the configured owner can open it. An administrator role alone does not grant access; each source's additional permissions still apply, especially payroll salary authority.

1. Review **Open**. This includes old unresolved work across all weeks.
2. Choose **Overdue**, **Needs Your Decision**, **Waiting For Staff**, or a workflow category when useful.
3. Select **Show Details**, then **Open Existing Workflow**.
4. Record the decision, payment, assignment or verification on that original page. Refresh the register to see the result.

The register has no separate case creation, approval, payment, write-off or close button. It cannot release a room. Existing source permissions, confirmation requirements, operation keys, version checks and atomic audit continue to control every critical action. Opening or refreshing the register creates no business or financial record; access is logged.

**Resolved** retains source-resolved cases. **Activity** shows authorized source activity. Lists use fifty records per page, after considering the full source set. Detail shows the latest fifty source events and links the original workflow for its full retained history. Date displays use day/month/year; times are UTC.

## What remains open

- Cash awaiting full deposit, unverified deposits and wallet receipts, and disputed cash custody. A linked discrepancy replaces its standalone deposit/wallet row. Different obligations are never added into a misleading monetary grand total.
- Departed-stay recoveries and unresolved departure incidents. Amounts use original frozen departure balances plus explicit later receipt, refund and correction legs; original charges are not recalculated at today's rates.
- Purchase/provider approval, delivery/cost evidence and unsettled supported payouts. Approval is separate from payment verification.
- Receipt, expense, purchase/provider and guest-charge correction requests. Opening a pending correction detail rechecks its current source; a stale source requires revision on the original workflow. List entries explicitly require a fresh source check before deciding.
- Private modern payroll preparation, settlement, statement and recovery issues. **Actual payroll monetary recovery is not implemented:** a note never records recovered money or authorizes a salary deduction.
- Stock counts and non-sale stock issues. Approval of held goods does not confirm actual disposal or supply. Source-cancelled counts remain in Resolved.
- Weekly room/area follow-up, earlier unadopted findings, and existing housekeeping/handover owner attention. Staff completion remains open until the source requires and records owner verification. New problems after a closed weekly audit use a new audit.

Original case IDs, assignees and deadlines remain authoritative. Unknown staff/deadlines are not guessed. Changing a note or date cannot settle money or verify safety. Reopening an existing supported cash dispute retains previous verified amounts and history.

## Coverage and limits

Unavailable sources produce a visible incomplete-coverage warning and retain a linked unavailable entry where its identity is known. They are not assumed resolved. Missing historic receipt attribution requires original evidence, not a guessed actor. Restricted feeds exclude their private records, activity and counts.

Expense submissions are approval tasks; their approval status does not claim that an ordinary expense has been paid. Standalone ordinary-expense settlement controls and unlinked legacy payroll requests remain on the existing Finance pages and are not consolidated in this release. Supported purchase/provider settlement and modern payroll are linked here. Reminders & Escalations now provides a separate personal inbox linked to this register and the same original workflows; see [the guide](REMINDERS_ESCALATIONS.md). Real external delivery requires protected setup and verified contacts.

No new schema activation, migration, financial-policy change or production deployment is part of this register. Cash receipts must still be deposited **in full by the next day**; costs use separate funding and do not reduce the original deposit duty. Financial corrections remain separate from actual money returned or paid; closed-period snapshots and historical records remain intact.
