# Staff & HR

Implemented and installed locally on 2026-10-30. Production has not been changed. This module records employment and payment instructions; it does not post payroll payments or change historical financial records.

## Using the module

1. Open **Staff & HR → New staff member**. Save a partial draft with the person’s name and a reason; complete Personal, Employment, Emergency, Onboarding and Review as information becomes available.
2. Contract and ID verification are independent from file attachments. Signed contracts and up to ten ID documents can be attached optionally during onboarding. Supporting copies can also be added later in Documents.
3. Review the record in **Employment**, then approve activation. Name, valid staff email, job title, start date, signed-contract verification, identity verification and SOP orientation are required for activation.
4. Under **System access**, either link an existing user after identity confirmation and a reason, or issue a new clerk-account invitation after activation. Existing-account linking preserves user ID, password, roles, sign-in status and all historical attribution. Accounts already linked elsewhere cannot be selected. No account matches or staff backfills are inferred automatically.
5. Use **Overview** for contact information, onboarding tasks and issued-item handover. Employment supports probation confirmation, explicit suspension/resumption and rich-text offboarding reasons. Leave requests have multiline reasons and administrator review; self approval is denied.
6. Use **Salary & payment** for exact GHS salary terms, frequency, effective date, registered names, masked MoMo number and instructions. With Finance installed, new revisions use MoMo; earlier bank details remain retained. The supported legacy layout retains bank/cash validation when Finance is not installed. Every save appends an encrypted revision; stale submissions are rejected. Prior revision metadata stays visible. The screen shows the most recently recorded revision, including future effective dates; it does not calculate payroll or leave entitlement.
7. **Settings → Payment lists** maintains MoMo providers (and the supported legacy bank list when Finance is not installed). **Settings → Document types** maintains document classifications. Add your approved providers/banks before recording those payment methods; no real-world list is assumed. Entries can be renamed/deactivated/reactivated with stable IDs. Existing selections/documents remain retained.

New-account invitations use the existing encrypted password-reset delivery queue. The account has an inaccessible random initial password, and the recipient sets a password through a single-use, time-limited link. Delivery depends on the configured mail worker/SMTP setup described in ACCESS_CONTROL.md. No email was sent to real staff during implementation.

## Permissions

| Privilege | Capability |
| --- | --- |
| hr.view | View all staff profiles and HR history |
| hr.self | View own linked profile and request own leave |
| hr.manage | Draft/edit profiles, issue/return items and manage employment |
| hr.approve | Review onboarding, probation and leave |
| hr.link | Link/create staff sign-in accounts |
| hr.documents | Access/upload/download private HR documents |
| hr.salary | Access and append private salary revisions |
| hr.settings | Maintain HR lists; actual administrator role also required |

Existing administrators receive the HR privileges; existing clerks receive only hr.self. Linking, invitation and employment actions affecting account access also require users.manage and current-password confirmation. Existing target-authority restrictions apply to custom roles as well as the admin role. New clerk invitations cannot grant privileges the operator does not hold. There must remain an active administrator without a pending exit.

## Audit and privacy

All HR mutations use the existing serialized PDO transaction and durable audit insert; audit failure rolls back the change. Profile revisions retain changed fields and before/after values in restricted HR history; shared audit metadata omits private profile values. Document and salary views/downloads are audited and fail closed when required audit storage fails. Denied access to another staff record is logged.

Salary revisions and their reasons are encrypted with authenticated AES-256-GCM envelopes. Document contents are encrypted under private storage with random names, authenticated record context, hash and size checks. Salary amounts, bank/MoMo numbers, passwords, invitation tokens and document contents do not appear in the shared audit trail. Document metadata (such as filenames) remains in private HR database tables. This does not claim that every biodata column is encrypted.

Only PDF/JPG/PNG uploads matching actual MIME and extension are accepted, up to 5 MB per file. Files are served only as authenticated attachments through hr-document.php. Direct web access to config/storage/private sources is denied. Duplicate identical attachments are retained once; failed uploads clean up their new encrypted file. Process-crash orphan reconciliation remains an operational maintenance task; do not blindly delete private files.

## Scheduled exits

An immediate exit/suspension revokes the linked account and sessions atomically. Future exits retain a dated decision and are enforced before the affected staff member’s next authenticated request. The CLI worker can apply idle-account exits without waiting for a request:

`C:\xampp\php\php.exe scripts/process-hr-exits.php --confirmed-local`

No scheduler was installed. Before deployment, configure and monitor this guarded worker for timely background status updates. Account/role changes cannot leave only administrators with pending exits. Affected sessions become anonymous, allowing another person to use the same reception browser. Historical staff, leave, item, document and salary records are retained. Rehire/compensating employment workflows are future scoped work; historical staff records are not deleted or reused silently.

## Installation and recovery

`C:\xampp\php\php.exe scripts/setup-hr.php --confirmed-local`

The setup accepts only the exact approved local tivoli DSN or the marked, verified synthetic port-3307 engine. Migration 006 adds nine HR tables and retention/immutability triggers, seeds six document types and grants HR privileges. It does not alter financial tables or infer staff/account links. It is restartable after partial additive DDL; DDL is not claimed transactional.

The HR encryption key is generated once into ignored config/hr.local.php (config/hr-test.local.php for tests). Existing keys are preserved. If private records exist and the key is missing, setup stops instead of replacing it. Back up the original key together with the database and storage/hr-documents; losing it makes encrypted records unreadable. Keep every backup private.

Protected pre/post installation SQL snapshots, a private key copy and row-comparison evidence were saved under ignored .local-setup. All 11,330 original ordered INSERT row statements across bookings, booking_extras, payments, expenses, expense_categories, rooms, extras, stock_movements and users match byte-for-byte. There are zero inferred or synthetic staff records in the local application database. A complete disaster restore has not been rehearsed.

For rollback, retain schema, encrypted files, keys, audit data and the account exit guard. Restrict HR navigation/privileges while diagnosing; never drop HR tables or discard newly recorded employment/payment instructions. Any production release needs deployment-target verification, private-file/key access controls, backup/restore rehearsal, SMTP verification, scheduled-worker monitoring and owner-approved HR policy.

## Executed verification

- `tests/Integration/hr.php`: 49 assertions, including role authority, encryption, exact amounts, replay/stale checks, scheduled exits, administrator retention and audit rollback.
- `tests/Integration/hr_concurrency.php`: three separate-process races (draft replay, one-account linking and salary revisions).
- `tests/Contract/hr_http.py`: 55 assertions including partial drafts, private uploads/downloads, multiple optional ID attachments, retained inactive bank choices, clerk denial and shared-browser session recovery.
- Existing authentication HTTP: 24 assertions; expense HTTP: 36; shared-layout HTTP: 16 authenticated views plus permission/export/read-only guard checks.
- `tests/Contract/hr_browser.cjs`: 11 HR views and all six wizard steps at 1440/390/320px; salary visibility controls exercised; desktop/mobile screenshots inspected using fictional records.
- Local structure checks cover 19 routes/protections. PHP and JavaScript syntax checks completed; private config, storage and snapshots verified ignored by Git.
- Independent review found and rechecked fixes for delegated-account authority, suspended session recovery, retained inactive selections and pending-administrator exit retention. Final review reported no remaining concrete P1/P2 blockers in the reviewed scope.

All automated writes used the separate marked synthetic database at 127.0.0.1:3307 and HTTP port 8099. Synthetic throttle fixture state is cleared only between independent suites. The test engine had lifecycle/Aria failures; a managed foreground test session recovered a verified empty partial synthetic schema before the final runs. No repair command was applied to financial tables. No production connection, push or deployment occurred.


## Navigation and action simplification — 23 November 2026

- New Staff Member is on the directory. Profiles have one Staff Directory return and one Edit Profile action. The wizard uses Cancel as its return, preserving the tab that opened it without posting unsaved edits. Generic Settings is available from the sidebar; Documents and Salary retain their specific list-management links.
- Seven profile sections remain distinct: Overview, Employment, Salary & Payment, Leave, Documents, System Access and Activity. Private salary/documents and access authority remain independently controlled; payroll stays in its existing workspace.
- Onboarding has five steps. The instruction-only Access guidance is included in Review; all actual fields, optional contract/multiple ID attachments, partial-draft saving and activation requirements remain. Moving between steps focuses their content. Browser validation returns to the required name field even from Review.
- Employment menus reflect the original service states: draft activation/return, active probation/suspension, suspended resumption and eligible offboarding. Previously confirmed probation is shown in the record instead of repeating its action. Exited staff retain history and outstanding item returns. Scheduled exits display and retain their current date when opened for an update. Self-account changes still require another authorized operator.
- Existing-account linking is the first choice; an invitation is a collapsed alternative after activation. Both retain identity/password confirmation, account-management authority and original account/history protections. The server remains authoritative for target authority and final eligibility.
- New leave requests appear only for active eligible employment. Own requests do not show approval controls. Earlier leave/decisions remain visible; item issue is hidden after exit while unreturned items can still be returned.
- Fixed a salary-script error when the Finance-enabled screen omits legacy bank controls. MoMo validation/visibility works without those fields; legacy bank/cash validation is retained when the controls exist. This does not enable bank/cash salary payments in Finance.

Executed evidence for this update: HR integration49, HR HTTP56, concurrency3 scenarios, presentation capability34, browser198 across51responsive views, existing shared modal271 and versioned asset52 checks passed. Before/after fingerprint comparison retained all25,880pre-existing protected synthetic HR/guest/financial rows. Browser tests now own/dispose a separate context on CDP9236. Screenshots with fictional data were visually inspected. No ordinary local data, database schema, role grants or hosted deployment changed. See [change record](delivery/changes/2026-11-23-staff-hr-consolidation.md).

Payment Lists and Document Types now use the [shared Settings workspace](SETTINGS_WORKSPACE.md); their existing IDs, versions, history and permission boundaries are retained.
