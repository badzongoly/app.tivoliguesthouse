# Extras & Stock consolidation — 22 November 2026

Implemented locally and tested on the verified synthetic instance. User requested a workflow review and removal of duplicate Non-Sale Issues navigation.

## Observed problem and scope

The catalogue rendered both a Stock Control row and a shared Extras & Stock row linking to the same Non-Sale Issues register. The issue page repeated its register and count cross-link in another row. Stock Counts used a different partial navigation and did not highlight a shared parent. No duplicate business transaction was demonstrated by the duplicated links.

Four presentation files changed: templates/partials/extras-nav.php, templates/stays/index.php, templates/stock-counts/index.php and templates/stock-issues/index.php. One shared primary row now contains permission-filtered Manage Extras, Extra Sales, Stock Movements, Stock Counts, Non-Sale Issues and owner-only Stock Costing. The row appears above catalogue editing forms as well. Counts retain local Count Register, Start Count, Owner Review and Activity actions. Issues retain Report / Request Issue and an Issue Register return action when appropriate. Removed redundant primary cross-links; existing source-specific catalogue actions remain useful shortcuts.

## Workflow review

| Entry | Purpose and retained source |
| --- | --- |
| Manage Extras | Product/service catalogue, prices, tracked status and administrator editing. Item-specific purchase and issue shortcuts retain their product identity. |
| Extra Sales | Existing date/item sales register; selling to a guest remains linked to the existing stay writer. No second sale writer introduced. |
| Stock Movements | Read-only movement history with original quantities, actors and references. |
| Stock Counts | Blind physical count, submitted variance, owner decision and retained comparison/approval history. Old immediate adjustment remains denied. |
| Non-Sale Issues | Damage, expiry, missing stock and complimentary reports, holds, owner decisions and actual disposition. Existing count-loss association prevents a second deduction for the same evidenced loss. |
| Stock Costing | Existing owner-only supported opening/purchase cost evidence and profit view in Finance. No new costing register. |
| Buying stock | Existing approved Purchase Request and receipt workflow, reached from the catalogue item; not a duplicate direct Stock In form. |

Stock Counts and Non-Sale Issues stay distinct because counting establishes a physical variance, while issue reporting records a known event or planned complimentary supply. Consolidating their navigation does not replace either writer or infer a link between old records.

## Executed verification

- New tests/Contract/extras_navigation_http.py: 87 checks passed. Owner and clerk entry pages/forms, one shared navigation, one Non-Sale Issues entry, correct active tab, counts/issues reachability, owner-only costing and existing form/action access. Observed expected duplicate-link failure before the template fix.
- tests/Contract/stock_issues_http.py: 24 passed, including owner approval, actual disposition, durable retries, changed-retry rejection, private evidence and required-audit rollback/retry.
- tests/Contract/stock_counts_http.py: 25 passed, including blind counting, private evidence, owner-only approval, identical approval replay, retained approval snapshot/history and denial of retired direct writers.
- tests/Contract/stock_costing_http.py: 18 passed, including opening-cost evidence, same-key protection, supported inventory value and owner-only evidence/privacy.
- New tests/Contract/extras_navigation_browser.cjs: 72 assertions and18responsive views passed, owner/clerk catalogue, counts and issues at1440/390/320pixels. One shared nav, one issue entry, one active parent and no page overflow. Screenshots visually inspected.
- Changed PHP templates linted; browser JavaScript syntax and Git whitespace checks passed.

Total226 assertions plus18responsive views. HTTP writes ran sequentially only on port8099 with synthetic response header and fixture-guarded database127.0.0.1:3307/tivoli_auth_test/marker synthetic-auth-only-v1. Browser context is owned and disposed; user cookies untouched. Private fixtures/logs stay Git-ignored. Sales/catalogue/purchase routes were reviewed for navigation and existing source writers; no new sale or purchase posting was claimed from those read-only checks.

Two development findings were resolved before handoff: the old count test expected the former longer tab label (updated to assert label and destination); introducing the shared nav on count forms exposed its generic loop variable overwriting the form operation key. The replay regression caught this and the partial now uses namespaced variables, preserving the original key. No backend/service code was changed. The failed synthetic attempts/history were retained.

## Preservation and recovery

No ordinary application data, stock balance, historical movement, financial record, approval authority, schema or external-delivery setting was changed. No hosted deployment, Git commit or push. Existing operation keys, CSRF, transactional writers and confirmation modals remain authoritative. Previous uncommitted work preserved. Low-risk presentation consolidation; no independent financial review claimed. To recover the UI, revert only this navigation slice while retaining previous security/modal changes and all business events; no database restore.
