# System Access consolidation — 23 November 2026

Scope: presentation-only simplification of users.php; preserve all existing permissions, records, reauthentication, CSRF, audit/transaction semantics and administrator retention. Existing uncommitted work is retained; no ordinary data/configuration/schema change or hosted deployment.

Observed: three anchor sections shown together, four simultaneous forms per expanded account, repeated password/reason controls for separate mutations, and self/higher-authority actions that the service rejects.

Plan: retain Users, Roles and Privileges as distinct selected sections and old hashes; use individually expandable user actions; keep assignment under its original user/role, without a combined writer. Provide local unsaved Cancel/reset and visible selected navigation, safe no-JS fallback, error-action focus and capability/target-aware controls. Retain every selected role/privilege and keep all server authorization authoritative. No select-all or automatic permission changes.

Verification: red presentation test, actual template capability matrix, guarded service/security/HTTP/concurrency regressions, owned-browser responsive/tab/Cancel/assignment tests, protected synthetic record fingerprints, source review and syntax/encoding checks. Implemented and tested locally; no deployment or data activation is required. The service, repository, users.php controller, SQL reads/writes, CSRF/password/reason fields and all permission/security checks remain unchanged.

## Final implementation

- `templates/access/manage.php` is a wrapper for presentation-only context and Users/Roles/Privileges partials. Existing hash routes and no-JS access retained. `templates/layouts/context.php` changes only the users.php breadcrumb label to System Access.
- Shared versioned `access-workspace.js/css` selects one section, focuses its heading, supports Back, and keeps native no-JS expandable controls. Same-hash clicks after failed actions still select Users correctly.
- The retained synthetic database contained2,966users. Initially rendering every account's four forms/role selections produced an excessive DOM and stalled the first owned browser check. That context alone was disposed. All accounts remain listed, while existing mutation forms render only for the validated `account` query target selected through Manage Account. Failed account submissions also select their own target again. This changes presentation, not data or permissions.
- Nested action controls keep profile, role assignment, deactivation/reactivation and revocation separate; no combined mutation or new policy. Cancel resets only its form, restores all original checkbox selections, clears/masks passwords and closes/focuses its action without posting.
- Source-derived capability/authority checks hide unsupported actions; no assignment checkbox is disabled or bulk-granted. Actual assigned roles replace the misleading legacy-role summary. All server guards remain authoritative.
- Error response inspection showed the correct section but BODY focus because native fragment loading followed the deferred script. Focus is now applied once after pageshow; selected account navigation likewise focuses its record. Browser red/green evidence covers these outcomes.

## Executed checks

All app/test mutations used the exact verified isolated port3307/database tivoli_auth_test/marker synthetic-auth-only-v1, with HTTP8099 synthetic header checks. Browser contexts are owned/disposed through CDP9236; ordinary user cookies/windows were not cleared or used.

| Command | Result |
| --- | --- |
| `php tests/Contract/access_navigation_permissions.php` | 52 passed: directory contains no repeated editors, selected target eligibility, self/higher-authority restrictions, clerk-grant eligibility and CSRF/password/reason preservation. Initial red test failed on self-deactivation before implementation. |
| `php tests/Integration/access_ux_guard.php before` | Exact target verified and full-row hash multiplicities captured in Git-ignored evidence before synthetic changes. |
| `php tests/Integration/access.php` |46passed: roles/privileges, temporary passwords, sessions, reset security, last-admin/self restrictions, audit immutability/rollback and secret exclusion. |
| `php tests/Integration/access_security.php` |19passed: delegated-admin protection, reset invalidation, reauthentication throttle and encrypted recovery envelope. |
| `php tests/Integration/access_concurrency.php` |2independent-process races passed: single-use reset and last active administrator. |
| `python tests/Contract/access_http.py` |24passed: authenticated management, password visibility, temporary-password restrictions, clerk denial, recovery, session invalidation and audit-view failure privacy. |
| `php tests/Integration/access_ui_seed.php` | New synthetic users/custom roles only, created through existing services for delegated/role/privilege browser coverage. No real account or external delivery. |
| `node tests/Contract/access_navigation_browser.cjs` |42assertions/12responsive views at1440/390/320px: old hashes, selected tabs, Back/focus, selected editor, Cancel resetting roles/profile/password without POST, enabled selected privilege payloads, self gate, denied-action/error focus, same-hash return and delegated/role/privilege defaults. Synthetic denied-namespace POST expected; it did not create a privilege. |
| `node tests/Contract/confirmations_browser.cjs` |271existing shared-modal assertions passed, including freeze and exactly-once approval behavior. |
| `php tests/Contract/confirmation_assets.php` |55versioned-asset assertions passed. |
| `php tests/Integration/access_ux_guard.php after` |All87,278pre-existing protected synthetic access/HR/guest/financial rows retained exactly, including user identity rows, role/privilege definitions and memberships, immutable audits and historical business records. Newly added synthetic fixtures retained. |
| PHP lint, `node --check`, `git diff --check`, UTF-8/mojibake scan |Passed. Mobile role-editor screenshot visually reviewed. |

Test maintenance: HTTP heading expectations reflect the requested System Access naming. Old last-admin tests assumed no retained synthetic administrators; they now isolate that scenario and restore each other administrator's prior active state in finally. Removed broad old synthetic-account cleanup from access.php. No expected protection was weakened. Browser assertions were corrected to use the actual shared color token and wait for full response loading; the final full run passed.

Source-only review by activity_visibility_review found no important issue, including final selected-account routing and post-load focus changes. The reviewer did not execute tests or access data.

## Release and recovery

No schema migration, normal user/permission mutation, key/configuration change, Git commit/push or hosted deployment. Refresh the existing local app. Code recovery is limited to this presentation slice; retain all access/audit/account/HR/financial data, keys and earlier uncommitted work. No new account policy, delivery setup, bulk grants, idempotency contract or financial writer was introduced.
