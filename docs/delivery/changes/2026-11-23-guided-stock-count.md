# Guided Stock Count

## Identity and scope

Approved for implementation on 23 November 2026. Base revision `f026133`; local candidate tested on guarded synthetic fixtures. Adds one-item counting, Save & Count Next, Save & Pause, register resume, variance-first review and reuse of a counter's previous weekly list. Affects `stock-counts.php`, its templates and scripts, and the existing StockCountService.

## Contracts and safeguards

The existing stock-count entry point owns all writes, using the same actor, permission, HR eligibility, CSRF, count version, movement hashes, review token and operation fingerprint checks. The current line and next window commit together under the existing shared transaction lock. Identical retries use the original line and routing. Pausing on the guided path saves the line and leaves the next window unopened. Blind counts, private evidence, linked recounts and owner approval continue to apply. Existing manual start, begin and finish requests still work. Completed count history and stock movements are append-only. No change to amounts, costing or period policy. No schema changes.

## Executed checks

| Check | Execution | Result |
| --- | --- | --- |
| PHP syntax | `php -l` for the changed application PHP | Passed |
| Service regression | `php tests/Integration/stock_counts.php` using the marked test database | 52 passed. Covered atomic advance, rollback, retry, pause, blind comparison, exact owner adjustment, recount, unchanged historical stock rows, payments and expenses. New guided-start assertion failed against the original implementation. |
| HTTP | `python tests/Contract/stock_counts_http.py` on the marked test service | 33 passed. Covered authenticated start, privacy, next, pause, resume, grouped comparison and prior approval/evidence controls. |
| Browser | Isolated Chromium context, 390px viewport, synthetic service | Five passed. Covered focus, fitting the phone screen, actual advance and pause buttons, and resume into review. |
| Concurrent stock writers | `php tests/Integration/stock_counts_concurrency.php` using the marked test database | Seven passed. Covered identical and competing approvals, sales racing a count, overlapping counts, and linked purchase receipt. |

## Release and recovery

Source change only. No schema activation, ordinary database mutation or hosted deployment. If the interface needs to be rolled back, restore the previous picker and step controls while retaining the service reader and event data. Keep original and guided events, operation keys, recounts, and approvals in place. If a count needs correction, use the existing recount or review paths.
