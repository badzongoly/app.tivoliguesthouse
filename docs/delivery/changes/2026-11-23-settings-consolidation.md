# Settings consolidation — 23 November 2026

Requested: simplify Settings tabs/links/actions while preserving every setting, historical reference and safeguard. Presentation-only, existing checkout/uncommitted changes preserved. No migration, ordinary data/configuration changes, new permissions, Git commit/push or hosted deployment.

Observed: multiple six/seven-tab rows diverge, Notifications missing from some, selected state inconsistent, unrelated HR back-link repeated on global settings, all area edit forms expanded, and reminder Check Now repeated while editing Settings.

Plan: one canonical permission-aware seven-entry Settings navigation with visible selected section; preserve distinct notification sub-settings and configured-owner controls. Consistent expandable Add/Edit list actions and local unsaved Cancel; contextual checklist section Add controls remain because they target different sections. Hide catalogue mutation forms for non-administrator readers, retaining their permitted read access. Keep Check Now on Reminders, remove it from Settings. Preserve existing controllers/writers, reason/CSRF/versions/operation keys, verification/consent and historical checklist/list references. Verify pure capability matrix red/green, focused guarded HTTP mutation/revision/denial contracts, reminder regressions, responsive browser/Cancel/no-POST and full-row preservation; independent source review and documentation.

Implemented and tested locally. No new controller, business writer, SQL, migration or live-setting operation. Existing unrelated uncommitted work, including earlier StayService changes, is preserved.

## Implementation and safeguards

- One `templates/partials/settings-nav.php` replaces four divergent tab rows and is shared by Notification Settings. Content-versioned settings-workspace.js/css gives selected navigation, expandable list editors, local Cancel/reset and action/outcome focus. Notification subtab selection is visibly distinct.
- Catalogue lists, areas, categories and checklist-section Add editors use consistent disclosure. HR/global Settings no longer repeats Back To Staff & HR. Removal controls remain per item; Add remains per checklist section because these are different targets. No select-all, automatic save or bulk reset.
- Sidebar/breadcrumb reflect Settings for its existing authorized users, while ordinary staff opening own Notification Settings retain Reminders context. Other workspace navigation is unchanged.
- Check Now remains on the owner Reminders inbox, with Back To Reminders available from Notification Settings. Shared rules/contact verification/service status remain configured-owner-only; personal preferences and delivery logs remain separate.
- Existing HR list readers see names/statuses but no administrator-only editors. Operational Settings retain read access; editors match the full existing writer capability set, including bookings.manage. Server authorization is unchanged.
- IDs, field names, required reasons, CSRF, version/revision fields and operation keys preserved. Cancel restores the original complete FormData, focuses its summary and posts nothing. No entered values are stored by the new module. No feature, delivery channel, service configuration or scheduler is activated.

## Executed verification

All app/test mutations used exact isolated port3307/database tivoli_auth_test/marker synthetic-auth-only-v1, with synthetic HTTP8099 header checks. Before/after evidence and synthetic fixtures are Git-ignored. Browser contexts on CDP9236 are created/disposed by the tests; normal user browser cookies/windows were not used.

| Check | Executed result |
| --- | --- |
| `php tests/Contract/settings_navigation_permissions.php` |44passed: canonical tab order/selection, feature/capability combinations, operation key unchanged, and actual template rendering of operational editors with/without the writer's booking-management permission while original values remain readable. Red test first failed on missing Notifications. |
| `php tests/Integration/settings_ux_guard.php before` |Exact marked target verified before writes; full-row hash multiplicities captured. Includes finance_events, stay_operations, access definitions/memberships/audits, HR/list/history and guest/financial/stock tables. |
| `php tests/Integration/settings_ui_seed.php` |New synthetic read-only settings user and clerk created through existing services; no ordinary account changed. |
| `python tests/Contract/settings_http.py` |49passed: owner/clerk/custom-reader boundaries, CSRF rejection, stable-ID catalogue rename/deactivation/stale versions, checklist add/remove with retained original items, operation replay and stale revision denial, area/category changes, owner-only notification setup and canonical Check Now location. Only fresh synthetic entries were modified; original checklist items restored after temporary additions. |
| `python tests/Contract/reminders_http.py` |13passed: individual preferences, consent/verification separation, owner-only shared setup, copied/forged forms and unsigned callback denial. |
| `php tests/Integration/reminders.php` |47reported assertions passed (includes its weekly-audit prerequisite checks): own-account preferences, replay/fingerprint protection, consent, configured owner, independent verification, contact changes, encrypted data, exact zero monetary effect and original source/financial preservation. |
| `node tests/Contract/settings_navigation_browser.cjs` |165assertions/33responsive views at1440/390/320px passed: all six list/checklist settings plus five notification views; one primary row/order/selected section, sidebar context, valid operation keys, full FormData restoration/focus on Cancel, zero business POST during browsing/Cancel, reader controls and clerk direct-route/privacy denial. |
| `node tests/Contract/confirmations_browser.cjs` |271existing modal assertions passed, including page freeze and once-only approved submission. |
| `php tests/Contract/confirmation_assets.php` |61content-versioned asset assertions passed, including Settings and Reminder scripts. |
| `php tests/Integration/settings_ux_guard.php after` |All124,645pre-existing protected synthetic rows retained exactly. Additional fresh synthetic records/events retained; no history reset/deletion. |
| PHP lint, `node --check`, `git diff --check`, UTF-8/mojibake checks |Passed. Mobile Audit Areas and Notification Settings screenshots visually inspected. |

Independent source review found the additional bookings.manage writer requirement. Corrected editor visibility without removing authorized read access, then added real-template capability tests. Final source-only review reported no important issue. Reviewer did not independently run tests or access data.

Testing corrections: area-template conditional syntax was caught by lint and fixed before HTTP tests. New CSRF assertions initially expected403, but these unchanged Settings controllers currently catch the RuntimeException as400; tests now assert400 plus the explicit expired-form message, without changing controller behavior. Browser testing exposed a read-only explanation hidden inside collapsed rows; moved the explanation once above each list, then all browser checks passed.

## Release/recovery and limits

Local source only: no normal settings values, credentials, service configuration, permission grants, schema, Git commit/push or hosted deployment changed. Refresh Settings to load versioned assets. Recovery is a targeted presentation rollback; keep every setting/list revision, document reference, audit, key, historical business record and earlier uncommitted change. Legacy bank lists remain in the original Finance-not-installed mode; this change does not enable bank/card payment workflows. Real external messaging remains separately configured and unverified by this work; no real delivery was attempted.
