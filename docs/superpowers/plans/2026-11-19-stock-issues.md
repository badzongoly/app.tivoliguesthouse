# Stock Non-Sale Issues Implementation Plan

Goal: implement the approved prototype in the existing PHP app.
Spec: ../specs/2026-11-19-stock-issues-design.md
Architecture: StockIssueService + StockAvailability read projection, existing finance_events/operations/claims/audit and stock_movements. Shared PDO transaction and security_state lock; exact hundredths. No schema or dependencies.

Global constraints: preserve originals, use only marked127.0.0.1:3307/tivoli_auth_test and synthetic8099 HTTP, keep UTC, no ordinary financial writes/deployment/Git mutation. Execute inline under existing owner authorization; final independent review.

Review focus: held quantity across all writers; saleable count versus raw stock; stale/forged count provenance; operation replay after authority revocation; original actor/actual time and zero money effects.

- [x] 1. Write guarded RED tests for report/hold, permission, approval/disposition, return/clarification, exact quantities and count linkage; implement StockAvailability::hold(AccessRepository,int):array and StockIssueService::execute/detail/options/register/countOptions. Run core integration GREEN.
- [x] 2. Write RED cross-writer tests: guest extra cannot consume hold, clean count excludes holds, hold change invalidates count, supplier return leaves holds intact. Integrate StayService, StockCountService and PurchaseCorrectionPosting/source tokens. Prove audit rollback and actual independent-process races, then run affected original stock/stay/purchase suites.
- [x] 3. Add stock-issues.php using shared layout, existing navigation/evidence routing, forms/preview/focus/confirmation and source history. Add synthetic HTTP/access/CSRF/file/replay checks and responsive browser flows. Lint and encoding checks.
- [x] 4. Independent review, reproduce/fix important findings RED/GREEN; sequential affected regressions; staff guide, delivery/recovery and roadmap update. Verify scoped source sync and no ordinary DB mutation.
