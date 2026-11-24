# Staff & HR consolidation — 23 November 2026

## Scope and plan

Requested: simplify duplicate Staff & HR tabs, links and actions while preserving staff history and all safeguards. Work in the existing checkout; preserve earlier uncommitted changes. Presentation-only change, no schema or business-service rewrite.

Observed: seven distinct profile sections, generic Settings links repeated beside the sidebar, New Staff on every profile, six onboarding steps with an instruction-only Access step and a second instruction-only Review step, and employment/account/leave actions visible outside their service-supported states.

Chosen approach: keep the seven privacy-separated profile sections; keep New Staff on the directory; keep one contextual directory return and relevant document/payment-list settings links. Combine Access guidance into Review (five steps), add native Cancel, focus wizard steps, and show only actions supported by existing state/permissions. Retain historical lists and server authorization as authoritative. Do not merge salary, private documents or account management into general biodata.

1. Add red presentation tests against actual partials for employment/account/leave/item state and capability combinations.
2. Implement a small shared profile presentation context and targeted template/navigation changes; preserve all POST fields, CSRF, versions, operation keys, password checks, optional uploads, encryption and account links.
3. Verify marked isolated service target. Run existing HR integration, HTTP and concurrency tests sequentially; use owned-browser responsive/wizard/Cancel checks and existing confirmation regressions. Compare protected historical rows around presentation checks.
4. Source review, syntax/diff/encoding checks, documentation and delivery state. No ordinary local data changes, migration, commit, push or hosted deployment.

## Executed results

Implemented and tested locally. No change to controllers, HrService/HrPrivateService, authentication guards, SQL writers, encrypted payloads or migrations. Changed existing HR templates and hr.js; added presentation-only profile-context.php. HR assets use content versions so refreshing loads the change.

| Check | Executed result |
| --- | --- |
| `php tests/Contract/hr_navigation_permissions.php` | 34 template/state/capability assertions passed. Red test first failed on draft actions before the UI change. |
| `php tests/Integration/hr_ux_guard.php before` | Verified port3307, tivoli_auth_test and synthetic-auth-only-v1 before synthetic mutations; captured full-row hash multiplicities in ignored private evidence. |
| `php tests/Integration/hr.php` | 49 passed: activation, exact/encrypted revisions, stale/replay prevention, linked identity, authority, suspension/exit, administrator retention and durable audit rollback. |
| `python tests/Contract/hr_http.py` | 56 passed: partial drafts, activation, private uploads/downloads, optional multiple IDs, provider revisions, CSRF, clerk isolation, audit failure and shared-browser session revocation. |
| `php tests/Integration/hr_concurrency.php` | Three separate-process races passed: duplicate draft, unique account linking and salary revision. |
| `php tests/Integration/hr_ui_seed.php` | Only fresh synthetic staff/accounts created through existing services for browser-state coverage; no real account or delivery used. |
| `node tests/Contract/hr_browser.cjs` | 198 assertions passed across51responsive views at1440/390/320px. Covers seven profile tabs, directory/new/edit, employment states, settings, five wizard steps/focus, optional uploads, validation, salary visibility, alternate account choices, exact Cancel source/no business POST and clerk private/other-staff URL denial. Also verifies retained legacy bank/cash validation using browser-only synthetic markup. |
| `node tests/Contract/confirmations_browser.cjs` | Existing271modal assertions passed. |
| `php tests/Contract/confirmation_assets.php` | 52 versioned asset assertions passed. |
| `php tests/Integration/hr_ux_guard.php after` | All25,880pre-existing protected synthetic rows retained byte-for-byte by full-row fingerprints, including HR revisions/history/leaves/items/documents/catalogue/operations and guest/financial/stock tables. Additional fresh synthetic fixtures retained. |
| PHP lint, `node --check` and `git diff --check` | Changed PHP/JS/test syntax and whitespace passed. Changed-source UTF-8/mojibake scan passed; mobile onboarding/account-choice screenshots visually inspected. |

The old HTTP test advertised leave on a draft even though the unchanged service rejects it. It now checks absence in draft and multiline request after activation. The browser test had outdated port9232/shared-context and six-step/bank-field assumptions; updated to owned context9236, five steps and actual MoMo screen, without weakening service/security assertions. A test-expression quoting error was corrected before completing browser verification.

Browser runtime monitoring exposed a pre-existing TypeError in salary synchronization: the Finance-active template omits bank inputs, but JS unconditionally touched them. The failure was observed before fixing it with a presence guard. The rerun reported no browser exceptions; tests also verify the legacy controls when present.

Source-only independent review by activity_visibility_review found no important findings, including a final recheck of salary compatibility and wizard Cancel/header consolidation. The reviewer did not run tests or access data.

## Workflow coverage

Directory/draft/edit, onboarding review/activation/return, probation, salary revisions/payment instructions, leave request/review, private documents and optional onboarding attachments, issued-item handover/return, existing-account linking/new invitation, suspension/resumption, immediate/scheduled exits, activity history and relevant settings lists were inspected. Seven meaningful profile sections remain; no payroll, role-management or historical-data workflow was silently merged into biodata. Controls hide unsupported state/capability choices while the existing services still enforce authorization, target authority, passwords, CSRF, version and operation identity at submission.

## Recovery and limits

No migration, key/configuration change, ordinary data mutation, permission grant, commit, push or hosted deployment. Tests used only verified synthetic services; real staff records and historical financial records were never test targets. Local source is automatically served on refresh. Roll back only this presentation slice if needed; retain database records, document storage, keys, account guards and all earlier uncommitted work. Existing histories and server-side safeguards remain authoritative. No scheduler, delivery provider, new HR policy or rehire workflow was introduced.
