# Staff access and audit module

## Local use

Open http://localhost/app.tivoliguesthouse/auth/login.php and sign in with an existing staff account. Existing user IDs, password hashes (until normal rehash/change), and admin/clerk values were preserved. Existing sessions from the old login must sign in again.

- Staff and access: /users.php
- Audit trail: /audit.php
- Change password: /auth/change-password.php
- Recovery request: /auth/forgot-password.php
- Logout: POST /auth/logout.php with CSRF; GET displays confirmation.

New staff start as clerks with a temporary password and must change it before accessing the POS. Staff account deletion is replaced by audited deactivation. Reauthentication and a reason are required for access changes. Delegated managers cannot modify accounts or roles above their authority. The last active administrator is protected, including concurrent changes. Multiple roles are supported; permissions are combined. Custom privilege codes start with custom.; developers must explicitly enforce them in new features. The legacy recovery endpoint retains its additional admin-only restriction.

## Configuration and installation

Run composer install to load the locked PHPMailer dependency. Copy config/access.example.php to ignored config/access.local.php and supply the same database identity as the local POS, a random 32-byte audit key, a fixed trusted base URL and delivery settings. Keep keys/backups outside Git. The existing config/database.local.php continues to supply legacy mysqli workflows. New access-control writes use their own complete PDO transactions; they do not claim atomicity with legacy mysqli writes.

For the confirmed isolated local tivoli database only:

```powershell
C:\xampp\php\php.exe scripts/setup-access.php --confirmed-local
```

The additive migrations create access-control/audit/reset/delivery tables and audit immutability triggers. First installation maps existing admin/clerk accounts to corresponding roles; reruns retain subsequent assignments. Back up before installation. No financial schema or historical source rows are changed. Production MySQL 8 migration/recovery rehearsal remains outstanding; the executed local migration was on MariaDB 10.4.32.

## Reset delivery and SMTP

HTTP recovery requests enqueue authenticated-encrypted reset envelopes in the same transaction as the token and audit event. All validly formatted requests get the same account-neutral response. Unknown eligible emails enqueue non-delivery jobs; no SMTP call is made during the HTTP response. Public reset records contain only token hashes. Links use URL fragments to keep raw tokens out of HTTP queries/access logs. Password reset is single-use, expires after 30 minutes and invalidates previous sessions. Email changes and account deactivation invalidate outstanding resets.

Current development delivery mode is local_preview. No real emails were sent. The worker writes local previews under ignored, HTTP-blocked storage/mail/. Synthetic tests use storage/mail-test/. Preview files contain sensitive development links and must be kept private and removed under an agreed retention policy; no automatic deletion is configured.

To enable real email, edit config/access.local.php locally, set reset_delivery to smtp and add the smtp array from config/access.example.php: host, port, encryption (tls or smtps), username, password, verified from_email and from_name. Do not send secrets through source control. A public deployment needs HTTPS and a trusted base URL. SMTP delivery and provider credentials remain unverified.

Run the local worker after configuration:

```powershell
C:\xampp\php\php.exe scripts/send-reset-mail.php --confirmed-local
```

A supervised recurring worker must be configured separately for deployment; none was installed. Each run processes at most 50 eligible jobs. Claims use random fencing tokens, two-minute leases, five-minute retry delays and at most five attempts. Expired final leases become failed audit events. Email delivery is at-least-once; a crash after SMTP acceptance can cause another copy of the same reset link. No database lock is held during SMTP. Failed jobs require operator review; there is no automatic silent deletion.

## Audit scope and controls

Implemented events include login success/denial/throttling, logout, session invalidation, password changes/reset issuance/consumption/delivery, CSRF rejection, privilege denials, account changes, role/privilege definitions and assignments, reauthentication and audit viewing. Events contain UTC time, actor/target IDs, role snapshots, request IDs, pseudonymised IPs, outcome, reason and allowlisted before/after fields. Failed login and reset consumption are not attributed to an authenticated staff actor. Passwords/hashes/reset tokens/session tokens are excluded from event details. Changes and required audit writes commit atomically. Denied/failed attempts are recorded separately. Audit-view logging failure returns no fetched events.

Database triggers reject audit updates/deletion. This protects normal writes; a database administrator can drop triggers. Production runtime credentials must have least privilege, with independently protected audit copies and an approved retention policy. Secure cookies apply on HTTPS; if introducing a reverse proxy, explicitly configure trusted HTTPS handling before deployment. Inactivity timeout is 30 minutes, absolute lifetime eight hours. Passwords are 12–72 bytes; eight failed account login/confirmation attempts and 30 failed IP attempts per 15 minutes are limited. Successful confirmations preserve previous shared-IP failures. Recovery requests are limited per email and IP.

This module does not yet audit every legacy booking/payment/expense/stock mutation. Existing financial writers and their known CSRF, destructive-edit and integrity hazards remain a separate containment/refactoring task. This is not production-release approval.

## Executed verification — 2026-10-29

Synthetic tests ran on a newly initialized, loopback-only MariaDB instance at port 3307, restricted test account, verified marker and database tivoli_auth_test. They contain no restored guest records. Local Apache routing checks are read-only.

```powershell
./scripts/access-test-services.ps1
C:\xampp\php\php.exe tests/Integration/access.php
C:\xampp\php\php.exe tests/Integration/access_security.php
C:\xampp\php\php.exe tests/Integration/access_concurrency.php
C:\xampp\php\php.exe tests/Integration/access_queue.php
C:\Python313\python.exe tests/Contract/access_http.py
./tests/Contract/local-structure.ps1
./scripts/access-test-services.ps1 -Stop
```

Results: 46 integration assertions; 19 security regression assertions; two independent-process concurrency scenarios; four queue/throttle regression assertions; 24 synthetic HTTP assertions; 12 local routing/private-file checks. All passed. All 52 PHP files linted, password JavaScript syntax passed, Composer manifest validated (license metadata warning), and Composer audit reported no security advisories. Browser interaction/visual QA, actual SMTP delivery, production MySQL parity and production deployment were not executed.

An independent read-only review found delegated-admin takeover, stale-reset, reauthentication-throttle, synchronous-mail and worker-lease issues; these were corrected with regression evidence where noted. This was a scoped code review, not a penetration test.

During initial test-account provisioning on the existing port-3306 server, MariaDB reported an Aria system-table checksum error. No repair was attempted. A partially created unused test schema/account may remain there. This is why tests use the separately initialized port-3307 instance. The application still has six staff, 1,392 bookings and 3,532 payments; count checks are not full per-row reconciliation or backup-restore proof.

## System Access navigation — 23 November 2026

Open System Access for three distinct sections: **Users**, **Roles** and **Privileges**. One section is shown at a time, with visible selection, keyboard focus and browser Back support. Existing `#staff`, `#roles` and `#privileges` URLs remain supported. Without JavaScript, all three sections and their native expandable controls remain available; enhanced Cancel buttons stay hidden.

The user list retains every account, including inactive accounts. Open **Manage Account** to load the selected account's permitted controls. The page focuses that record; it does not generate edit and role-assignment forms for every account simultaneously. Expand **Edit Account**, **Assigned Roles**, **Deactivate/Reactivate Account** or **Revoke Sessions** as needed. Creation stays at the top of Users. Account summaries show the actual assigned roles, while legacy stored values remain unchanged.

Assignments remain in one place: user roles under the selected account, and role privileges under the role. The privilege catalogue maintains definitions. No select-all, automatic grants or combined permission/state writer was added. Existing checked roles/privileges remain enabled and included in the original POST payload; merely hiding a section never deselects or disables them.

**Cancel** inside an expandable action discards that form's unsaved edits, restores its original selections, clears and masks password fields, closes the action and focuses its summary. It performs no POST. It does not deactivate an account, revoke sessions or change assignments. Failed actions reopen their own section/form and focus the error after native fragment navigation has completed.

Presentation hides self-deactivation and higher-authority account/role changes. The new-account form is hidden when a delegated operator cannot grant the clerk's existing privileges. These are UI affordances only: the unchanged server still enforces authentication, current-password confirmation, reasons, CSRF, target/grant authority, atomic audit and last-continuing-administrator protection. Permission grants, reset delivery, user records and HR/account links were not changed in the normal local app.

Executed on the marked isolated engine/HTTP service:46integration,19security,2concurrency,24HTTP,52presentation capability,42browser assertions across12responsive views,271existing modal and55asset checks. All87,278pre-existing protected synthetic access/HR/guest/financial rows remained unchanged. Independent source-only review found no important issue. See [delivery evidence](delivery/changes/2026-11-23-system-access-consolidation.md). No schema change, ordinary data change, commit, push or hosted deployment; refresh the local app to load versioned assets.
