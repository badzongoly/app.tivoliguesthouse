# Cash & MoMo Discrepancy Resolution Implementation Plan

> For agentic workers: use superpowers:executing-plans task by task. This is workflow 1 of the ten authorized workflows; complete and verify it before proceeding to workflow 2.

Goal: resolve supported deposit, MoMo receipt and cash-transfer discrepancies without changing originals or closed totals.

Architecture: append-only finance events, existing finance_operations durable idempotency and existing AccessService serialized PDO transaction/audit. Extend current custody/read projections and block overlapping legacy settlement writers. Reuse Finance & Cash navigation and source links. No schema or production activation change.

Spec: ../specs/2026-11-17-cash-discrepancies-design.md and owner-approved ten-workflow prototype.

Global constraints: Cash/MoMo only; original full cash deposit deadlines remain; current open-period posting; immutable originals; server-derived authority; private evidence; exact integer minor units; no guessed actors or source links; no production writes, Git mutation or deployment.

Review focus: returned cash must not be double-available; partial acknowledgements must preserve sender residual reservations; reversed confirmations cannot retain verified status; same/different-key concurrent commands cannot exceed remaining bounds; external evidence/reference reuse must not verify two sources.

- [x] Task 1: guarded synthetic integration tests RED; characterize old cash projection and closed totals.
- [x] Task 2: CashResolution projection + CashDiscrepancyService (source/detail/register/execute), persistent keys/fingerprints, private evidence and current-period safeguards; integration tests GREEN.
- [x] Task 3: integrate original deposit/matching/handover writers and readers; focused source/regression tests.
- [x] Task 4: finance page/controller/forms, source links, live preview/focus, history and owner visibility; direct HTTP/CSRF/privacy and responsive checks.
- [x] Task 5: actual concurrent writers, audit rollback, affected regression suites, independent review/fixes, staff guide and evidence/delivery record.

Ruling: implement sequentially in the existing checkout; preserve pre-existing uncommitted changes. User already instructed implementation; no repeated plan-permission gate. Writes outside the declared stale workspace go through reviewed file-write permissions.

Remaining sequence: 2 corrections after money moves; 3 guest charge corrections/refunds; 4 purchase/provider corrections; 5 payroll corrections; 6 non-sale stock; 7 costing; 8 audit follow-up; 9 owner register; 10 reminders. These remain authorized but are not completed by this first slice.

Executed evidence: see docs/delivery/changes/2026-11-17-cash-discrepancies.md. All five tasks completed locally. The nine remaining authorized workflows are outstanding, not implicitly implemented.
