# Expense submission and approval

## Staff workflow

Clerks open Expenses and submit against an existing active category. The server supplies the actor and pending status. Clerks see only their own submissions, including the reviewer's name/time and approval/rejection reason. They cannot see or export the property-wide counted ledger, create categories, approve, reject or alter posted expenses. The current policy permits submissions in all active categories; no guessed monetary limits were added.

Administrators open Expenses and use Review submissions to reach the queue. Approval or rejection requires a reason. Only an active member of the admin role with expenses.approve may review. A requester cannot review their own pending submission even if subsequently promoted. Administrator direct entries retain their existing immediate-counting behaviour when the actor holds expenses.manage. A non-admin holding expenses.manage still creates only a pending submission.

Pending and rejected submissions are kept in expense_submissions, separate from the counted expenses table. Approval atomically inserts one expenses row, links it to the submission, records the reviewing actor/reason/time and writes the required audit event. The counted row retains the original clerk in user_id and retains the submitted expense_date. Approval can therefore change a prior date's expense totals after review; effective date and approval time remain distinct.

Rejected submissions remain visible with the rejection reason and never enter totals. To correct a rejected submission, enter a new submission. Submitted fields and terminal decisions cannot be overwritten. No email notifications were added; administrators review the in-app queue.

## Integrity and compatibility

- Existing historical expenses remain counted; no approval status or historical approver is fabricated for them.
- Pending/rejected rows never enter existing report/CSV queries because those queries read only expenses. public/reports.php required no query rewrite.
- The new expense page totals all matching counted rows with native exact DECIMAL SUM. The existing display/export cap of 300 remains explicitly labelled. This intentionally fixes the former capped-total defect. Rows and totals use one repeatable-read snapshot.
- New amounts are canonical decimal strings, positive and limited to DECIMAL(12,2). No PHP float arithmetic is used. Existing amounts are not recomputed.
- The original utf8mb3 expense ledger remains unchanged. New details containing unsupported supplementary Unicode (such as emoji) are rejected before creating an unapprovable immutable request. Review reasons in the new table can use utf8mb4.
- CSRF-protected POST is required for submission, review and category creation. Direct POST privilege checks run again inside the transaction. GET edit/delete now returns 405 and an audit event.
- Posted expenses are protected from UPDATE/DELETE by database triggers. A separately specified compensating correction workflow is still required; do not re-enable silent edits or destructive deletion.
- Submission identity/content is immutable. Reviews lock the row; concurrent approvals return the same counted expense, and competing approve/reject requests retain one outcome.
- Submission operation keys are durable and scoped to the authenticated actor. Same key and equivalent payload returns the original result; a changed payload is rejected. Failed/uncertain requests retain the same key and entered fields in the response so a retry cannot inadvertently become another operation.
- Required audits and financial writes share one PDO connection and commit together. Audit failure rolls back the posting and review transition. Reads/exports are audited; failed read auditing clears financial rows and returns failure rather than displaying unaudited results.
- CSV text fields are escaped against formula injection; output and filter values are contextually escaped/bound.

Audit actions include expense.submitted, expense.recorded, expense.approved, expense.rejected, replay events, category creation, rejected/failed attempts, legacy-mutation denial, expense.viewed and expense.exported. Successful review events link the original submitter, reviewing administrator, submitted details, posted expense ID, prior/new status and reason.

## Local installation and evidence — 2026-10-29

Additive migrations 004 and 005 and expense schema version 2 were applied only to the owner-confirmed isolated localhost/tivoli database. They create submission/operation tables, immutable-history guards and expenses.submit/expenses.approve privileges. Existing admin/clerk assignments are retained; the clerk role gains only expenses.submit, while admin gains submit and approve and retains expenses.manage. Future reruns do not overwrite role customizations.

```powershell
C:\xampp\php\php.exe scripts/setup-expenses.php --confirmed-local
```

A protected local backup of expenses and expense_categories was created before migration under ignored, HTTP-blocked .local-setup/expense-approval-*/. This backup has not been restore-tested. Original full dump files differ because new immutability triggers are included by mysqldump even with --no-create-info. Every one of the 157 ordered INSERT statements (146 expenses and 11 categories, all fields and IDs) matches exactly before/after. Canonical row SHA256:

1B5029101708310EAAAEF47D7CAAA20C1E13F8BBBB09BF2AB2FC11C1CF20F049

No local financial test entries were inserted. All new workflow tests and browser previews used the separate port-3307 synthetic database and port-8099 synthetic HTTP service. The existing local server's previously reported Aria system-table issue was not repaired or exercised through new account provisioning.

## Executed tests

```powershell
./scripts/access-test-services.ps1
C:\xampp\php\php.exe tests/Integration/expenses.php
C:\xampp\php\php.exe tests/Integration/expenses_concurrency.php
C:\Python313\python.exe tests/Contract/expenses_http.py
C:\Python313\python.exe tests/Contract/access_http.py
./tests/Contract/local-structure.ps1
./scripts/access-test-services.ps1 -Stop
```

The synthetic expense fixture matches the original ledger's utf8 charset. Tests cover pending/rejected exclusion, native report sums, administrator direct entries, forged actor/status/privileges, owner isolation, exact money and cap boundaries, inactive accounts/categories, duplicate key/payload rules, approval/rejection finality, source attribution, immutable history, audit-failure rollback, safe retry, CSV injection and direct endpoint access. Concurrency tests use independent processes and deterministic barriers. Independent read-only review found charset, snapshot and identity-guard gaps; those were corrected before local activation.

This local implementation is not production deployment approval. Production MySQL 8 compatibility, recovery rehearsal, external-writer inventory and a correction workflow remain outstanding. For any eventual release, pause expense writes during the coordinated code/schema/permission change and refresh PHP opcode caches; old writers must not overlap with new clerk privileges. Never roll back to the former expense handler while clerks have submit access: it can insert directly into expenses. A safe fallback must preserve new submissions, approvals and financial history; never restore an old dump over newly posted work.
Final executed results:52 integration assertions,4 independent-process concurrency scenarios,36 expense HTTP assertions,24 authentication HTTP assertions and12 local routing checks all passed. All64 PHP files passed lint. Synthetic browser checks verified administrator desktop/mobile and clerk mobile views without document overflow. Success notices now come from server-side session flash data, with a test proving query parameters cannot forge approval confirmation.

