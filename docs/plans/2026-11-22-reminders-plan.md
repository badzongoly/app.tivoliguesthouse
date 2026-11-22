# Reminders & Escalations Implementation Plan

> Execute inline using executing-plans, with one independent final security/source/delivery review. No implementer subagents, worktree, Git mutation, credential/configuration mutation or production deployment. Owner explicitly approved implementation of the reviewed prototype; do not repeat the design approval gate.

Goal: one in-app reminder inbox linked to existing tasks, with personal optional Email/WhatsApp choices, honest pending delivery, audited generation and bounded source-aware delivery.

Architecture: reuse activated operational finance_events/finance_claims/finance_operations as existing operational modules do, with zero amounts and encrypted notification-domain payloads. No DDL, new financial ledger or source business command. Source adapter reads the complete owner queue and rechecks original staff source authority. Existing atomic audit/shared lock, unique claim scopes and durable actor-operation keys govern new operational commands. A separate CLI worker drives scheduled checks/outbox; no network while holding a business transaction. SMTP uses existing PHPMailer; WhatsApp uses official configurable Cloud API and signed receipt ingestion. Missing service/verified contact stays pending, never falsely sent.

Spec: 2026-11-22-reminders-design.md; approved in chat. Existing app C:/xampp/htdocs/app.tivoliguesthouse, main, preexisting dirty modernized tree preserved. Stage here and use guarded explicit sync.

## Constraints / review focus

- Preserve original payments, expenses, refunds, charges, stock, source histories, actor IDs and closed snapshots. nt_* operational events have amount zero and are ignored by financial totals.
- Encrypt all notification payloads, including source identity/recipient/contact, with a notification-specific derived key. No secrets/contact/case payload in ordinary finance activity or audit output.
- Only current active user edits own preferences; configured owner manages rules and explicitly verifies contact ownership. Source ownership, role membership and private hr.salary must be rechecked before reads and sends.
- Stable original root + obligation + genuine reopen episode + recipient + slot; mutable notes/deadlines cannot create duplicate critical records. Same-key changes rejected, concurrent workers serialized/claimed, required audit failure rolls back.
- Reading reminders never completes a task. Repair/staff completion/owner verification, approval/cost/payout and receipt/refund/correction remain source-local. Missing sources remain incomplete; revoked users never receive private rows/counts/URLs.
- External timeout/worker crash after send-intent becomes unknown, never blind retry. Verified definitive failure retries bounded with backoff. Provider accepted != delivered; replayed receipts stay idempotent. Generic external body and secure deployed sign-in URL only; localhost/test mode cannot send real messages.

### Task 1 — durable journal, sources and personal controls

Files: src/Repository/NotificationJournal.php, src/Service/ReminderSources.php, src/Service/ReminderService.php; narrow OwnerExceptionService::reminderSources owner-only full snapshot. Tests tests/Integration/reminders.php, tests/Unit/reminder_policy.php.

- [x] Guarded missing-class RED before fixtures.
- [x] Implement encrypted zero-value operational records, unique notice/slot claims, persistent operation replay/fingerprint, atomic audit and own preference/read/rule/contact verification commands.
- [x] Reuse complete source snapshot, original per-source staff reader checks and explicit weekly routine coverage/duty; no first-fifty source truncation.
- [x] Prove own-only/owner-only, no plaintext leakage, repeat/conflict/rollback, source financial preservation and canonical assignment/completion changes.

### Task 2 — generation and safe delivery

Files: src/Service/ReminderGeneration.php, ReminderDelivery.php, src/Infrastructure/ReminderTransport.php, scripts/reminders-worker.php, public/notification-status.php, protected configuration example and worker setup instructions.

- [x] Failing tests for daily dedup, overdue escalation, source completion/reopen/unavailable, opt-out/revocation, claim concurrency, accepted/failed/unknown/delivery callback replay.
- [x] Implement one active source reminder, append-only ping/read/status history, source-aware ordinary digests and separate Critical alerts. Named Sunday auditor uses actual required room/area coverage; missing duty goes to owner.
- [x] Implement audited send-intent and bounded retries; fresh state/contact/ACL/consent before dispatch. No lock during network. Crash/timeout holds for reconciliation. Service readiness and heartbeat reflect executed evidence.
- [x] Add guarded CLI/test mode; no external test messages or production configuration changes. Prepare local host schedule only after review and exact verified local target; never claim scheduling if not installed.

### Task 3 — app UI and final verification

Files: public/reminders.php, templates/reminders/index.php, scoped CSS/JS; existing route/rewrite/layout/navtop/account/settings/owner queue links. Guide, delivery record/state, SOP gap and roadmap.

- [x] Protected HTTP RED: direct login, own settings, CSRF, denied global controls, read/task distinction, safe redirect/source link, no prototype actions, source revocation/privacy.
- [x] Compact To Act On / History / Settings, personal delivery log, pending verification/service readiness; source-local CTA/focus. Source unavailable never healthy/empty.
- [x] Run independent final read-only review. Fix significant findings with reproducing tests, then relevant sequential financial/security/source regressions and responsive checks. Record exact counts, constraints and recovery.
- [x] No migration activation required. Retain encrypted notification history and original source commands if disabling feature; stop worker before removing only this slice's entry points.


Completed evidence: Executed **12 automated suites / 283 PASS assertions**, including repeated original-source setup; **16 responsive views** at 1440/390/320 px; **35 PHP syntax checks**, JavaScript/Python syntax, UTF-8, explicit delivered-file parity and route checks. Four anonymous ordinary Apache checks passed (login redirect, unsigned callback refusal, private source/example denial). Delivery uses fake transports only. Exact financial summary and every original journal/payment/expense/refund/stock row remain unchanged in isolated comparisons. Real-process races verify one daily slot and one fake external attempt; unknown outcomes, stale contacts, permission revocation, bounded retries, required-audit rollback, callback ordering and obsolete backlog suppression passed. Independent findings were reproduced and fixed.

Local scheduling activated and observed successful; real channels and production remain unconfigured. No migration or source financial posting.
