# Form cancellation

Navigation-only **Cancel** links return to the page that opened the form, including its selected tab, filters, pagination and anchor. For example, Record Room Audit returns to Follow-Ups when opened there, or to the filtered Room Audits register when opened there.

The shared workspace header loads the content-versioned `form-returns.js` module. It carries a safe app-local `_return_to` query value on entry links. It changes only navigation Cancel anchors, never form values, file selections, CSRF, operation keys or submission behavior. Cancel navigates by GET and discards unsaved edits without posting them. The initial non-sale stock-item selection also offers Cancel.

This applies to existing Cancel links across guest operations, property/audits, stock, purchases, finance/corrections, payroll, handovers and reminder preferences. It does not add Cancel buttons to every inline settings/list form. Confirmation-modal Cancel dismisses the modal and retains the open form. Business actions such as Cancel Request remain recorded, protected actions.

Same-origin UI-page allowlisting rejects external, logout, download and token/action-bearing destinations. An untrusted or missing destination keeps the existing safe fallback. Forms opened directly or without JavaScript retain that fallback. Browser referrers are not required; the security no-referrer policy remains unchanged.

Per-login, per-tab navigation metadata supports nested forms, GET selection steps, validation redisplay and approved POST continuations. Only safe page URLs are retained, with expiry and a bounded history; no entered data is stored. Continuations are consumed once; cached-page restoration cannot revive an old pending return. A harmless random presentation scope separates login sessions. No database migration, business writer or authority change is involved.

## Verification

- `node tests/Contract/form_returns_browser.cjs`: verified synthetic app only; checks seven actual source/form/Cancel paths and observes zero business POSTs.
- `node tests/Contract/form_returns_dom.cjs`: browser-only intercepted HTML; navigation, nested forms, reload/Back/Forward, cached-state regression, GET/POST continuation, unchanged payloads, unsafe targets, storage denial and staff-session isolation.
- `node tests/Contract/confirmations_browser.cjs`: existing confirmation, freeze, evidence and exactly-once submission checks.
- `php tests/Contract/confirmation_assets.php`: versioned shared/module assets.

Browser tests create and dispose their own contexts. App checks require the existing synthetic HTTP service on 8099 with `X-Tivoli-Test-Mode: synthetic` and CDP on 9236; they never use normal localhost sessions or business data. See the delivery change record for executed results.
