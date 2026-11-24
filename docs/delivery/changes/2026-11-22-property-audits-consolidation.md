# Property & Audits consolidation — 22 November 2026

Implemented and tested locally. Owner requested consolidation of duplicate tabs, links and actions while retaining all safeguards and audit records.

## Delivered

Room administration and weekly audit pages now use templates/partials/property-nav.php: one ordered, role-aware row for Room Master, Out-Of-Order Log, Room Audits, Other Area Audits, Follow-Ups and owner-only Audit Duty. Scoped versioned CSS provides visible selection on desktop/phone. Removed the duplicate weekly-header duty entry. Room Master/fault links match the existing legacy route boundary (admin, rooms.manage and bookings.manage); audit/owner conditions remain separate. No permission definition or guard changed.

Room Master now retains room editing/status/rates without parallel housekeeping actions, which remain in Check-In & Occupancy. Existing link helper suppresses only the same open property form/record; its actual submit/cancel controls remain. Fault/repair cancellation retains the fault tab. Visible legacy room/area register creation actions use the canonical audit form and preserve supplied room selection. Saved legacy forms, filters, source IDs and history remain supported, using the same existing writers.

Original audit pages no longer show a redundant Original Audit link to themselves. The public weekly controller change is confined to that presentation helper's optional flag, default true for all existing linked-source/register callers. Older area-history pages retain Latest Original History and earlier-history pagination. Assignment, Mark Done, Verify & Close, Return For Rework, historic source adoption/fault linking and evidence downloads remain present and protected. Landing-page recording shortcuts and missing-duty guidance are retained because they serve different purposes from register tabs.

No audit, fault, assignment, evidence, finance or guest record was deleted, reset, merged, reattributed or backfilled. No history query/filter, service writer, transaction, approval, rate policy, schema or configuration changed. Required audit writes, actor-derived identity, CSRF, versions and durable idempotency keys remain authoritative. Original room availability, critical-fault rules, owner verification and private evidence access were not relaxed. No ordinary application database write or hosted deployment. Previous uncommitted work retained; no Git commit or push.

## Source review and resolved findings

A separate reviewer inspected the relevant sources without tests/DB/edits and found no important issue. A follow-up confirmed that Room Master links reflect the existing bookings privilege boundary. Capability rendering first reproduced a rooms-only administrator dead-end; the link is now hidden instead of granting that privilege. Ten actual-template assertions across five capability inputs verify tabs and an untouched operation key; these use read-only dependency inputs, not a real authorization bypass test. Real endpoint authority is covered by the HTTP suites.

The first focused run used an old UI assignment with a different fixture's staff account. It now authenticates the actual recorded synthetic assignee, and fixture preparation runs in dependency order. The template syntax check caught a missing closing endif during removal of old preparation links; it was corrected immediately and all affected paths were linted and exercised before completion. No backend rule was weakened to satisfy a test.

## Executed evidence

| Check | Result |
| --- | --- |
| php tests/Integration/weekly_audit_matrix.php |46assertions passed |
| php tests/Integration/weekly_audit_concurrency.php |29passed, including24required base assertions and5concurrency-specific checks |
| python -u tests/Contract/weekly_audits_http.py |28passed |
| php tests/Integration/weekly_audit_ui_seed.php |Guarded fresh UI fixture prepared |
| python -u tests/Contract/weekly_audit_entry_http.py |29passed |
| python -u tests/Contract/property_navigation_http.py |50passed |
| php tests/Contract/property_navigation_permissions.php |10read-only capability/key checks passed |
| php tests/Integration/stays.php |138integration assertions passed |
| python -u tests/Contract/stays_http.py |122passed, including room type/rates, faults, repair, uploads and original audit/settings routes |
| php tests/Integration/property_navigation_seed.php |Guarded synthetic routine fault and no-duty reader prepared |
| node tests/Contract/property_navigation_browser.cjs |180assertions/30responsive views passed |
| node tests/Contract/weekly_audit_browser.cjs |120assertions/24responsive views passed |
| node tests/Contract/confirmations_browser.cjs |271assertions and73confirmation attributes passed |

Audit checks cover duty, only-owner assignments and deadlines, assignee-only completion, stale/retry/race protection, owner rework/verification, repeated findings, cross-week carry-forward, overdue status, original history beyond50events, required-audit rollback, encrypted evidence access, all linked critical faults, explicit historic fault links and unsafe-area attestation. In-app reminder source/recipient handoff is checked; no external Email/WhatsApp delivery is claimed. Core room tests cover standing rate snapshots, fault priority, optional evidence/comments, protected downloads and repairs without reintroducing the retired repair-inspection route.

Browser checks cover owner room list/add/edit, fault/repair, canonical audit entry, original source, verification, duty and an unassigned clerk at1440/390/320pixels. They assert one nav, one visibly selected tab, no page overflow, preserved operation keys, no duplicate current action, correct cancellation and no administrator controls for clerks. Actual write/approval paths are separately executed by the endpoint/service suites. The weekly form browser suite intercepts final submission and checks one modal/background lock/cancel/confirm across eight forms at three widths. Screenshots visually inspected.

Mutation suites ran sequentially only against marked synthetic HTTP127.0.0.1:8099 and the allowlisted database127.0.0.1:3307/tivoli_auth_test/marker synthetic-auth-only-v1. Only intentional race workers ran concurrently. Browser contexts were owned/disposed, leaving user sessions untouched. No SQL imports, resets, real credential output, external messaging or money transfer. Private fixture IDs, logs and scenario CSV remain Git-ignored. Scenario labels: .local-setup/property-consolidation-scenarios.csv.

PHP lint on changed/new files, JavaScript/Python syntax and git diff --check passed. Reported suites total1023assertions/checks, with54responsive views; this is executed evidence, not a coverage percentage or hosted release certification.

## Recovery and manual review

No migration or data restoration required. If reversing the UI slice, coordinate the shared property partial/CSS and both callers, retaining all original events, sources, version/operation guards and newer security fixes. Never restore an old database or re-enable retired writers to undo navigation.

Refresh Property & Audits. Verify the same ordered tabs in Room Master and weekly audit pages, one owner Duty tab, no duplicate current form link, and fault/repair Cancel returning to the log. Check original/older audit history and complete Assignment → Mark Done → Owner Verify / Rework. Staff without audit duty see the existing explanation and only permitted follow-ups. See docs/PROPERTY_AUDITS_WORKSPACE.md.
