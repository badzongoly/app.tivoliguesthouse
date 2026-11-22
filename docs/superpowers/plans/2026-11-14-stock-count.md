# Stock Count Implementation Plan

> Execute inline with TDD and one independent final review.
Goal: approved blind counts, variance decisions and immutable recount history.
Architecture: StockCountService writes existing event/operation/audit tables; approval adds linked stock movements under shared locking. Existing shared layouts.
Spec: ../specs/2026-11-14-stock-count-design.md

## Constraints
No migrations, money-ledger changes, Git-state changes, production action or ordinary DB fixtures. Preserve fractional stock and UTC. Approved prototype authorizes inline implementation. Preserve current uncommitted work instead of incomplete worktree from old main.

## Review Focus
Movement during count; later movements and overlapping adjustments; unknown provenance; authority/private evidence; replay/rollback; legacy bypass and Apache routing.

## Tasks
- [x] Domain: failing integration assertion, StockCountService permit/options/detail/register/attention/execute. Blind windows, exact deltas, dispute/recount, replay, privacy, rollback and provenance.
- [x] Workspace: public/stock-counts.php, template/CSS/JS, Apache/security route, retire old public form/POST, evidence kind, owner/handover attention; HTTP/CSRF/file/retry checks.
- [x] Verification: actual competing writer tests; finance/stock/occupancy/purchase/handover regressions; browser desktop/mobile; lint/encoding/diff; independent review and reproduced fixes; staff guide/delivery record.

Preflight: controller consumes service detail/version/review_token; evidence resolves event ID to private root; supported stock writers share security_state locking. Existing legacy service methods remain for compatibility, not public creation.

Completed:39 domain,7 new concurrency,598 total integration,377 HTTP,18 responsive views,18 PHP lints, independent review with reproduced fixes. See delivery/changes record for executed commands and boundaries.
