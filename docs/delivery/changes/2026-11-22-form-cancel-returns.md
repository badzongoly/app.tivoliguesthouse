# Form Cancel returns — 22 November 2026

## Scope and observed defect

Implemented locally on the existing main checkout with prior uncommitted work preserved. User authorized a shared Cancel behavior: return to the opening page, including Follow-Ups versus Room Audits, and discard unsaved form edits. Risk: navigation/presentation; protected financial and audit services are unchanged.

Observed: weekly audit forms hardcoded Cancel to their audit register. The actual synthetic browser regression failed the Follow-Ups origin assertion before implementation. Other existing forms similarly used fixed fallbacks, dropping source filters. Security sends no-referrer, so neither browser referrer nor history.back is a reliable solution.

## Implementation and safeguards

- Shared content-versioned `public/assets/js/form-returns.js`, loaded by `templates/layouts/head.php`; app-local entry query and per-login/tab navigation context.
- Existing Cancel anchors are GET navigation only. Preserves origin filters, tab, pagination, hash and nested parent context; supports GET selection and approved POST continuation without adding POST fields.
- Initial stock-issue item selection now also exposes its existing safe Cancel fallback (`templates/stock-issues/new.php`).
- No changes to form values, uploads, CSRF, confirmation tokens, operation keys, amounts, permission checks, approvals, transactions, services or database schemas. No credentials or entered personal/financial data stored by this module. Only safe navigation URLs and a random session presentation scope.
- External/outside-app, logout/download, mutating action and token/secret destinations rejected. Invalid/direct/no-JS context retains the existing safe fallback.
- Modal dismissals and recorded business cancellations excluded. One-shot pending context, history state and cached-page handling prevent stale returns. Browser cache behavior was reproduced with a deterministic persisted pageshow regression before fixing it.

## Executed evidence

Verified isolated HTTP service 127.0.0.1:8099 declares synthetic mode; synthetic app checks own their browser context on CDP9236. Normal localhost database and user browser sessions were not used. DOM tests fulfill all HTTP requests with intercepted synthetic HTML and cannot reach business endpoints.

| Command | Result |
| --- | --- |
| `node tests/Contract/form_returns_browser.cjs` | 14 assertions passed: Follow-Ups, room/area registers, purchases, stock counts, stock issues and finance income; every Cancel returned to source and observed zero POSTs. |
| `node tests/Contract/form_returns_dom.cjs` | 49 assertions passed: exact source context, nested forms, direct fallback, reload/Back/Forward, unsafe destinations, GET selection, unchanged approved POST payload, one-shot/stale/cache regressions, storage failure and account scope. |
| `node tests/Contract/confirmations_browser.cjs` | 271 existing modal assertions passed, including background freeze, evidence retention and exactly-once approved submission. |
| `php tests/Contract/confirmation_assets.php` | 49 content-versioned asset assertions passed, including the new shared module. |
| `php -n -l templates/layouts/head.php`, `php -n -l templates/stock-issues/new.php`, `php -n -l tests/Contract/confirmation_assets.php` | Syntax passed. |
| `node --check public/assets/js/form-returns.js`, new browser test syntax checks | Passed. |

Independent source review by activity_visibility_review found stale pending returns after a continuation, then their cached-page variant. Both were fixed and regression-checked. Final source-only recheck found no further important issue; reviewer did not run tests or access data. No broad financial mutation suites were necessary: business writes are unchanged, live Cancel checks observe no POST, and intercepted POST checks prove the payload remains intact.

## Release and recovery

No migration, configuration, role grant, business/financial data change, hosted deployment, commit or push. Local source implementation only. Refresh existing pages to load the versioned script before testing Cancel. Recovery: remove the new shared script include; existing Cancel fallback URLs remain intact. Retain all business records. JavaScript-disabled/direct links cannot infer a prior page and deliberately use the existing fallback.
