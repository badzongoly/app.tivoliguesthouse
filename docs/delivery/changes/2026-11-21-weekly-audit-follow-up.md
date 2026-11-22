# Weekly Audit Follow-Up — 21 November 2026

## Authorization, scope and observed result

Owner explicitly approved the reviewed interactive prototype for implementation, keeping the shortest safe flow tied to existing room/area audits. Implemented in the existing main checkout at C:/xampp/htdocs/app.tivoliguesthouse. The older declared Tivoli cwd is absent. Existing uncommitted work was preserved; no Git staging, commit, reset, branch/worktree changes, push or production deployment.

One Follow-Ups tab within Property & Audits links original findings to assignment, staff completion and configured-owner verification/rework. Pass creates no action; Needs Attention/Unsafe creates one or explicitly reuses an eligible same-location action for the same finding. Named staff/deadline are required only on assignment. Comments and completion evidence are optional; private PDF/JPEG/PNG limit 5 MB. Existing Record Repair remains authoritative and task closure never releases a room. Every linked unsafe room source contributes its Critical fault to the closure guard. No new repair inspection, notification, payment, expense, stock or payroll posting.

Original audit rows/events and prior financial records are retained. Latest legacy area owner/deadline/progress survive explicit adoption, without inferred staff identity. Historic completed area actions stay historic completions. New old-form area decisions are retired for both claimed and unclaimed sources; existing-key replays remain before the retired writer. Existing entry forms/POSTs still feed the original audit writers and atomic source hook. Original source/history URLs and continuation remain available; shared navigation points to the new protected route.

## Files and security/data consequences

New WeeklyAuditService/WeeklyAuditEvidence, public/weekly-audits.php, templates/weekly-audits and scoped CSS/JS. Small changes to StayService, WeeklyAreaAudit, protected endpoint/rewrite allowlists, workspace/sidebar/property navigation and original area status presentation. Existing PHP session/role/UTC contracts retained. No global connector, financial calculation, database collation, configuration, role/privilege table or schema changes.

Configured finance owner issues/revokes named audit duty and assigns/returns/verifies work. Active eligible operational staff record audits only under explicit duty or existing room-administrator authority; assigned staff mark only their own work done. Broader reads require owner or admin plus rooms.manage, otherwise original auditor/current assignment. Assignment and evidence access are authorized server-side. No account is mapped from a personal name, and normal staff grants were not auto-created. CSRF protects writes; GET does not mutate business state. Downloads require committed completion evidence and current task access, with attachment disposition/nosniff/private storage.

PDO, one shared security_state transaction lock, persistent per-actor input fingerprints, finance_claims unique source links, fresh versions including safety dependencies, and mandatory atomic audit. Zero-amount operational events use existing finance_events, with finance_operations for decisions; source entry continues using stay_operations. Rollback prevents partial task/audit/operation commit. Same-key duty replay returns the same result identity. Unknown outcomes require the same form/key/file, never a new blind post.

## Independent review and corrections

Executed independent read-only review by /root/weekly_audit_permission_review. Initial automatic approval review rejected an unverified core permission patch; independent review verified its authorized scope and identified/fixed broad admin reading, rooms-only eligibility, inactive-target revocation and a dummy access adapter before the reviewed local copy was approved. Final review identified an unadopted legacy completion bypass and two pagination/reuse gaps. The bypass was reproduced RED and fixed GREEN; older-than-fifty source reuse and original-history continuation now have executed tests. Source choices exposed a mixed-collation identifier comparison; exact binary identifier comparison fixed it without changing stored encoding. No unresolved blocking scoped review finding remains. Review is local source/permission evidence, not production release clearance.

## Executed verification

Implementation and owner approval began on 21 November; final verification completed on 22 November 2026 (Europe/Copenhagen). The final browser pass reproduced and fixed mobile overflow for a long room name. The same-key audit-duty result identity was also reproduced failing and corrected without a second event.

All dynamic checks verify 127.0.0.1:3307/tivoli_auth_test and synthetic-auth-only-v1 before fixtures; HTTP checks additionally require X-Tivoli-Test-Mode: synthetic at 127.0.0.1:8099. Ordinary application database and credentials were not used. Base service RED was observed before implementation. Core matrix exercises real database writes, source history, wrong-role/resource, stale/rework paths, mandatory-audit rollback, evidence, exact row preservation and duplicates. Independent worker processes test simultaneous same-key, conflicting-version and unique-source adoption. Original occupancy financial/stock behavior, billing, housekeeping, handover and direct access regressions passed. The original area contract intentionally changed from direct completion to named completion and owner verification; financial expectations were not weakened.

Executed 13 automated suites / 538 PASS assertions, 18 PHP syntax checks, JavaScript syntax and 13 responsive views at 1440/390/320 pixels. Counts include repeated common setup assertions, not unique scenario coverage. No browser runtime errors, encoding warnings or horizontal overflow were observed; desktop/mobile screenshots were inspected separately. Tests execute sequentially. Unchanged passing service results were retained; affected tests rerun after review fixes. Initial incompatible old-label expectation was updated to the approved Needs Assignment state and the full HTTP suite rerun.

| Check | PASS Assertions | Result |
| --- | --- | --- |
| stays_concurrency | 3 | Passed |
| stay_billing | 31 | Passed |
| housekeeping | 36 | Passed |
| housekeeping_concurrency | 8 | Passed |
| handovers | 42 | Passed |
| weekly_audit_matrix | 46 | Passed |
| weekly_audit_concurrency | 29 | Passed |
| weekly_audits_http | 22 | Passed |
| stays | 138 | Passed |
| stays_http | 122 | Passed |
| housekeeping_http | 36 | Passed |
| access_http | 24 | Passed |
| apache_routes | 1 | Passed |

Commands: C:/xampp/php/php.exe tests/Integration/{weekly_audit_matrix,weekly_audit_concurrency,stays,stays_concurrency,stay_billing,housekeeping,housekeeping_concurrency,handovers}.php; C:/Python313/python.exe tests/Contract/{weekly_audits_http,stays_http,housekeeping_http,access_http,apache_routes}.py; php -n -l on each changed PHP file; node --check on scoped JS and browser-check.cjs. Braces above enumerate separate executed commands, not a Windows shell command. External guarded orchestration logs: weekly-audit/verification.json, lint-verification.json, browser-verification.json and verify-*.txt. Redacted local summary: .local-setup/weekly-audit-verification-summary.json.

## Activation, recovery and limits

No DDL, migration, historical backfill, SQL import, normal database fixture or configuration change. Existing activated occupancy/finance/HR private-evidence setup is reused. Application source is installed locally; production deployment, account provisioning, normal account duty changes and a normal-database acceptance exercise were not performed. Eugene's next step is to open Property & Audits → Audit Duty and explicitly choose the authorized real staff account; then use Follow-Ups for assignment/verification. Existing room administrators can record audits immediately.

Retain all journal, source claims, operation identities, original history and encrypted evidence during recovery. Do not revert to a legacy writer that permits unverified completion or restore over later records. Disable affected writes and forward-repair, preserving all source and subsequent events. No backup/restore drill or production rollout is claimed. External notifications, automated Sunday scheduling and broader facility reopening policy remain separate. Next approved workflow: Owner Exception Register.
