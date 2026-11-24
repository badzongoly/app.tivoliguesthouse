# Weekly Audits & Follow-Ups — workflow check, 22 November 2026

**Checked and tested locally; two presentation gaps fixed.**

The verified path is **Audit Duty → Record Room / Area Audit → Follow-Up → Assign Named Staff And Due Date → Mark Done → Owner Verify & Close / Return For Rework**.

## Findings and corrections

The server workflow was consistent with the approved policy, but its write forms bypassed the shared confirmation modal. This was reproduced on the actual Mark Done form before the fix. All eight existing POST form types now have contextual data-confirm metadata and use the existing shared modal. Cancellation posts nothing; confirmation submits once; background controls and scrolling remain inactive while open. No second modal implementation or server bypass was introduced.

Removed the obsolete Housekeeping links from the weekly and shared property navigation. Room preparation remains in Check-In & Occupancy. No Room Board shortcut was reintroduced into Property & Audits.

Independent read-only source review confirmed the missing confirmations, checked every POST form after correction and found no further important issue. It also found no server-side task/source/evidence/reminder authority bypass. Runtime evidence below was executed by the implementer.

## Confirmed behavior

- Staff need explicit Audit Duty to record audits; eligible room administrators retain their existing authority. Granting duty does not grant room administration or financial authority. Only the configured owner assigns duty, staff/deadlines and verification decisions.
- Pass records an audit without a task. Needs Attention / Unsafe creates one source-linked follow-up or explicitly reuses the selected open finding at the same location. Free-text owner notes never become guessed account assignments.
- Only the current assignee marks work done. Completion becomes Awaiting Verification, not Closed. Return needs an explanation; previous completion evidence and deadlines remain. Stale decisions and changed-key retries are rejected.
- Repeated findings linked during verification return the existing task for attention without duplicating it or discarding completion history. Later passing audits do not close unresolved work.
- Open work carries across weeks and becomes overdue from its deadline. In-app reminder source eligibility points at the same task, shifts action to owner review, and becomes resolved/non-actionable after closure. No actual Email/WhatsApp delivery was attempted or activated.
- Every linked unsafe room Critical fault must be repaired before closing the follow-up; old missing fault identities require an explicit evidenced same-room link. Closing a task does not release a room. Unsafe area closure requires the owner's safe-or-blocked attestation; it is not an automatic facility-blocking feature.
- Evidence is optional, validated/encrypted and restricted to current authorized task readers. Source IDs and original area history remain available beyond fifty entries. Inactive/revoked/unrelated accounts and copied forms cannot bypass authority.
- Required audit-write failure rolls back completion, operation identity and source changes. Actual worker-process races preserve one completion/source association and reject conflicting stale decisions. Existing financial and stock rows remain unchanged in the service comparisons.

## Executed checks

| Suite | Passed assertions |
|---|---:|
| Weekly audit service and extended permission/safety/evidence matrix | 46 |
| Weekly audit concurrency, including required base setup cases | 29 |
| Existing direct HTTP workflow / private evidence / original history | 22 |
| New actual room/area entry, duty, rework, repeated findings and reminder-source checks | 29 |
| Actual weekly form modal checks at 1440/390/320 pixels | 120 |
| Shared modal regression across 73 confirmation attributes | 271 |
| **Total** | **517** |

The browser checks cover eight rendered actions at three widths: 24 responsive views. They intercept the final submit to avoid changing the UI fixture; real business writes are separately exercised by the HTTP and service suites. Screenshots were visually inspected. A test-harness login redirect was corrected by clearing only the test-owned browser context's cookies between synthetic roles; no user browser session was touched.

All database writes used verified synthetic port3307 / tivoli_auth_test / marker synthetic-auth-only-v1; HTTP used port8099 with the synthetic response header. Database suites ran sequentially; only intentional race workers ran concurrently. No normal localhost or hosted production data, financial calculation, schema, credentials, transport settings or external delivery was changed. No migration, push or hosted deployment.

Application changes are limited to templates/weekly-audits/index.php and templates/stays/property-nav.php. PHP lint, JavaScript/Python syntax and diff whitespace checks passed. Reusable fixtures/browser/entry tests are saved under tests; private fixture identities and screenshots remain outside Git. Previous uncommitted work is preserved.

For a manual review, the configured owner opens Property & Audits → Audit Duty, authorizes the actual auditor, then follows Room Audits / Other Area Audits and Follow-Ups. Room/area registers default to the current UTC Monday–Sunday week; All Weeks exposes earlier sources. This check does not assume ordinary staff accounts already have duty assigned.

Recovery needs only a coordinated presentation correction; no data restoration is needed. Preserve all audit/source links, operations, evidence and decisions. Do not restore a writer that bypasses owner verification.
