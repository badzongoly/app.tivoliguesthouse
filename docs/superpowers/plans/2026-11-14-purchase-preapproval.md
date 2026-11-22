# Purchase Pre-Approval Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans inline with TDD and an independent final review.

**Goal:** Implement the approved purchasing, cost and settlement workflow.
**Architecture:** PurchaseService and PurchasePosting append existing finance_events/operations under the shared transaction lock. They create one expenses row, optional linked stock movement, and bounded cost_paid events. Existing finance reports therefore count the existing canonical sources once. Private evidence uses the existing encrypted registry. No migration.
**Tech Stack:** Existing PHP/PDO/shared layouts/plain JavaScript, marked synthetic MariaDB.
**Spec:** ../specs/2026-11-14-purchase-preapproval-design.md

## Global Constraints

Preserve historical sources, UTC and exact minor units. No guessed legacy links, automatic Git operations or production action. Current owner binding and gross-deposit/separate cash-cost policy apply. Complete delivery only; payment may be partial.

## Review Focus

Stale cap/scope and exception chronology; shared expense/provider/stock bypass and duplicate payouts; partial/replayed payments and MoMo claims; evidence privacy/rollback; closing versus late posting and retained history.

## Tasks

- [x] Domain. Write tests/Integration/purchases.php and observe the missing-feature failure. Create src/Service/PurchaseService.php and PurchasePosting.php with execute(int,string,array,string):array, detail(int,int):array, register(int,int=0):array, options(int):array, attention(int):array. State derives from append-only history. Post an expense/stock/payout in one existing connection. Extend private evidence kinds, shared cost metadata claims and linked-purchase payout denial. Prove scope, caps, owner/requester authority, replay, privacy, audit rollback, dates, exact reports and real races.
- [x] Workspace. Build public/purchases.php, templates/purchases/index.php and scoped CSS/JS. Connect Finance navigation and owner/handover alerts. Retire public new expense/provider/stock-in forms and deny direct POSTs while preserving old records. Guard direct purchases endpoint. Add guarded HTTP/browser checks and update intentional route-contract regressions.
- [x] Verification. Run affected finance/expense/stock/occupancy/report/weekly/access regressions, syntax and UTF-8 checks. Independent review with reproduced findings. Record commands, results, limits and forward-repair boundaries in delivery state and change record, plus staff instructions.

## Execution decisions

Approved prototype authorizes implementation. Continue inline without another approval gate. No source commit/branch changes. Report unapproved actual costs explicitly rather than backdating approval. Old already-recorded declarations remain reviewable; no original is deleted or silently converted. New purchase expense settlement is unavailable through legacy pay_cost. Stock purchase cost is recorded once in the existing management-cost basis; this slice does not introduce COGS or historical valuation.
