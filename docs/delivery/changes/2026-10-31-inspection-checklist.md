# Inspection Checklist Change Record

Risk: scoped operational readiness and settings-input change. Acceptance: remove cleaning paragraph; render/configure all six inspection checks; enforce complete current checks, optional notes and retained audit evidence. No financial arithmetic, identity grant or migration change.

## Configurable final inspection — 2026-10-31

Implemented the owner's six Final inspection and release checks on the ordinary Inspect & Release Room form, replacing the single generic attestation. Reason / Findings / Evidence remains optional. Removed the requested explanatory submission paragraph from the cleaning wizard; audit collection and room-readiness protection remain active.

Settings → Inspection Checklist allows administrators to add/remove future inspection items separately from cleaning settings. The existing checklist service now accepts only cleaning/inspection kinds, using separate bundled defaults and separate append-only configuration actions (stay.cleaning_checklist / stay.inspection_checklist). Prior cleaning configuration revisions retain their original meaning. No schema or account-permission changes.

The ordinary inspect command requires every current item server-side and rejects stale revisions before changing availability. Completed inspections retain the exact checklist revision/section/item text with the authenticated actor, timestamp and optional note in the atomic stay.inspect audit. Removal affects future checklists only. A retry preserves the original operation; new inspection completion against an already released room is rejected. Audit failure leaves readiness unchanged. Existing occupied-room and unresolved-fault blocks remain enforced. The separate maintenance fault-resolution workflow is unchanged by this slice.

Executed verification: php tests/Integration/stays.php — 71 assertions; python tests/Contract/stays_http.py — 69 assertions; php tests/Integration/stays_concurrency.php — 3 concurrent scenarios; node tests/Contract/stays_browser.cjs — 20 views at 1440/390/320px plus wizard navigation and inspection focus/required-items/optional-notes checks. Nine changed PHP files linted; mobile inspection screenshot visually checked; git diff --check passed with the pre-existing unrelated line-ending warning. Tests ran only on the guarded synthetic port3307 database/marked8099 HTTP server. No ordinary financial records, migrations, Git state, push or production deployment changed. Applied to the existing local source checkout.

Recovery is a forward code fix retaining all historical configuration and inspection events. Do not restore code that bypasses the new inspection checklist. Existing admin-only room-release and settings authority is retained.
