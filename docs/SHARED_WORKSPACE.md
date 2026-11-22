# Shared staff workspace

Implemented locally on 2026-10-30. All 16 authenticated views use a forest-green navigation sidebar, ivory workspace, consistent header/account menu and footer. Unauthenticated sign-in and recovery retain the property artwork design.

## Structure

- `templates/layouts/context.php`: page metadata, mapped role names, privilege-aware navigation context and scoped pending-expense count. Administrator review counts require both the admin role and approval privilege; other staff see only their own pending count. Missing badge data is omitted rather than displayed as zero.
- `templates/layouts/head.php`, `start.php`, `end.php`: common assets and document wrapper for newer modules.
- `templates/partials/sidebar.php`, `navtop.php`, `footer.php`: shared navigation and chrome for both legacy and newer views.
- `public/assets/css/app.css`, `public/assets/js/app.js`: shared visual system, desktop collapse preference, mobile drawer, close/backdrop/Escape controls, focus containment and account-menu dismissal.
- `templates/auth/fields.php`: unchanged auth controls reused by standalone and authenticated account screens. Temporary-password accounts have restricted navigation until password change.

Endpoint permission guards remain authoritative. Navigation visibility grants no capability. Existing URLs, financial controller blocks, field contracts, inline business scripts and export handlers are retained. No migration is required for this layout.

For a new authenticated module, apply the endpoint guard first, set `$layoutTitle` and optionally `$layoutPage`, and include the shared start/end templates around page content. Keep controller redirects and exports before any rendered markup. Escape dynamic output with `access_h`. Existing legacy views retain their own Bootstrap/page styles; app.css loads last. Further page-by-page cleanup can remove redundant old styles after separate verification.

## Verification

Only the dedicated synthetic database at loopback port 3307 and synthetic HTTP server at port 8099 were used for authenticated testing. No restored guest data was used as test fixtures. `bootstrap/app.php` retains its ordinary connector and adds a test-only branch enforcing exact test configuration, marker verification, GET-only legacy rendering and a read-only mysqli session. New authentication/expense services continue to use their own guarded synthetic PDO connection.

Executed:

1. `scripts/access-test-services.ps1`
2. `C:\xampp\php\php.exe tests/Integration/layout_fixture.php` (additive synthetic display fixtures)
3. `C:\Python313\python.exe tests/Contract/layout_http.py` (16 views, legacy write blocking, existing CSV exports, locked account and clerk navigation/direct gates)
4. `C:\Python313\python.exe tests/Contract/access_http.py` (24 assertions)
5. `C:\Python313\python.exe tests/Contract/expenses_http.py` (36 assertions)
6. `tests/Contract/local-structure.ps1` (12 route/protection checks)
7. Headless Chrome with isolated `.local-setup/layout-browser` profile and debugging port 9230; `tests/Contract/layout_browser.cjs` verifies 16 views at 1440, 390 and 320px, desktop collapse and mobile open/Escape/focus return. Header marker is verified before synthetic credentials are submitted. Desktop/mobile screenshots inspected; private evidence stays ignored.
8. PHP syntax and JavaScript syntax checks; comparison against private pre-layout copies confirms all 11 legacy controller blocks and original inline scripts unchanged. Independent read-only layout review performed; accessible account/brand names and drawer close control corrected.

The previous synthetic instance had a corrupted system grant table and failed during initial tests. It was preserved in full under ignored `.local-setup/auth-test-mysql-preserved-20261030`; a fresh synthetic instance was provisioned. No repair was performed on the application server at port 3306.

This verifies layout integration and the stated regressions, not complete financial workflow correctness, deployment readiness or MySQL 8 parity. Existing legacy business risks remain documented separately. No production deployment, push, live migration, or financial history change occurred.


## Consolidated Extras & Stock / Finance & Cash menus — 1 November 2026

Replaced Manage Extras, Extra Sales and Stock sidebar entries with one Extras & Stock entry. Shared permission-filtered tabs link to the existing catalogue, sales report and stock routes; the landing route follows the user's existing accessible capability. The native Extra Sales report retains its original queries, date/extra filters and exports. Extras/stocks pages and report select the same parent menu.

Removed standalone Expenses from the sidebar and included its existing capture/approval page in the shared Finance & Cash navigation. Expenses has Capture & Approval and administrator-authorised Expense Payments sub-tabs. Clerk expense submission remains accessible; direct finance expense-payment restrictions are unchanged. The pending-expense badge moves to Finance & Cash. Expense-only accounts land on their existing expense page instead of an unrelated money overview. Main menu/tab selection and breadcrumbs remain consistent. No endpoint permission, financial writer, amount calculation, ledger, schema or historical record was changed. All old URLs remain usable.

Executed: nine changed PHP files linted;36 existing expense HTTP assertions and42 finance HTTP assertions passed on marked synthetic HTTP8099. Six catalogue/stock/report/finance/expense views checked at1440/390/320px for one parent menu, selected tabs and no overflow/encoding errors; separately verified cashier expense submission navigation and hidden administrator payment tab. Mobile screenshot visually inspected. Isolated browser9233 blocked the legacy report's external CDN only during QA; application dependencies were not changed. No ordinary database writes, migration, Git-state changes, push or production deployment. Recovery is a scoped presentation-file restore, retaining every financial/audit record.
