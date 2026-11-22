# Approved sequential workflow implementation

Owner instruction: implement the reviewed interactive prototype one workflow at a time, keep the shortest safe normal path, reuse existing modules and protect every critical command with durable idempotency.

| Order | Workflow | State |
| --- | --- | --- |
| 1 | Cash & MoMo Discrepancy Resolution | Implemented and verified locally; see CASH_DISCREPANCIES.md and delivery change record. |
| 2 | Corrections After Deposit or Handover | Implemented and verified locally; see FINANCIAL_CORRECTIONS.md and the 18 November delivery record. |
| 3 | Guest Charge Corrections & Refunds | Implemented and verified locally; see GUEST_CHARGE_CORRECTIONS.md and the 18 November delivery record. |
| 4 | Purchase & Provider Corrections | Implemented and verified locally; see PURCHASE_CORRECTIONS.md and the 19 November delivery record. |
| 5 | Payroll Corrections | Implemented and verified locally; see PAYROLL_CORRECTIONS.md and the 19 November delivery record. |
| 6 | Stock Damage, Expiry & Complimentary Use | Implemented and verified locally; see STOCK_NON_SALE_ISSUES.md and the 21 November delivery record. |
| 7 | Stock Costing & Profit Accuracy | Implemented and verified locally; see STOCK_COSTING.md and the 21 November costing delivery record. |
| 8 | Weekly Audit Follow-Up | Implemented and verified locally; see WEEKLY_AUDIT_FOLLOW_UP.md and the 21 November audit delivery record. |
| 9 | Owner Exception Register | Implemented and verified locally; see OWNER_EXCEPTION_REGISTER.md and the 22 November delivery record. |
| 10 | Reminders & Escalations | Implemented and verified locally; see REMINDERS_ESCALATIONS.md and the 22 November reminder delivery record. Real Email/WhatsApp setup and production scheduling remain separate. |

This list is not a production release authorization. Each slice needs exact source/dependency review, synthetic integration/concurrency/access tests, responsive verification and an independent financial/security review. Do not reopen this design/prototype approval gate without a material missing operating decision. Preserve the original records, closed snapshots, current source IDs, actor identity, audit, private evidence and durable operation keys throughout later slices.

For workflow 2, existing discrepancy history remains a dependency, not permission to bypass correction guards. No automatic write-off, staff deduction, receipt resurrection, invented payer, bank/card workflow, physical cash count, reservation or dynamic pricing is approved by this sequence.
