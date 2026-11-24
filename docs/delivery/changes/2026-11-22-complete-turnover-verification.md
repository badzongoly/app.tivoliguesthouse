# Complete room-turnover verification — 22 November 2026

**Executed and passed locally. No application-code changes were needed.**

The same synthetic room completed this entire sequence through the actual application endpoints:

**Paid check-in → outstanding extra → full-payment confirmation → checkout confirmation → Needs Cleaning → assigned cleaning checklist → Awaiting Inspection → independent inspection → Ready / Available → next paid check-in.**

The next guest received a separate stay identity. Replaying the earlier release could not vacate or alter that occupied room. The departed stay kept its own read-only cleaning link.

## Safeguards confirmed

- Unpaid departure is blocked without the existing owner authority. Payment and departure each have one confirmation modal; cancellation posts nothing. Tokens belong to their actor/session and cannot be consumed twice. Invalid CSRF and another user's confirmation are denied.
- Underpaid arrivals, occupied rooms, dirty rooms and rooms awaiting inspection cannot accept the next check-in. Ordinary payments cannot be added to a departed stay.
- Only the assigned cleaner can submit progress. Required items and steps cannot be skipped. Stale versions and operation keys reused with different details are rejected. Retrying the same start, completion or release does not duplicate it.
- The cleaner cannot self-inspect without the configured owner's bounded authority. Other administrators cannot grant that exception. Approval alone does not release a room; used or rejected grants cannot be reused, and required follow-up remains recorded.
- Failed inspection and missing personal attestation prevent release. Critical faults block readiness. Opening and repairing a critical fault invalidates old preparation, requiring fresh work. An occupied room cannot be released by stale inspection.
- Required audit-write failure rolls back the release and preserves readiness. Separate worker processes tested competing cleaners, duplicate completion, competing inspectors, exception-versus-inspection, critical fault-versus-release, release-versus-check-in, duplicate owner review and restart-versus-completion.
- Simultaneous check-ins produce one active stay; simultaneous full settlements post one payment; concurrent last-item sales do not oversell.
- Receipt identity, actor, amount and timestamp are retained. Final charges and debt remain frozen. Closed accounting days reject paid arrivals without creating a stay or receipt. Historical and unauthorized actions do not bypass the normal writers.
- Shared-modal browser checks confirm one prompt, inert background controls, scroll/focus containment, safe cancellation and one submission, including duplicated script loading and all 65 discovered confirmation attributes.

## Executed results

| Verification | Passed |
|---|---:|
| Continuous checkout-through-next-check-in HTTP assertions | 59 |
| Final exact record and audit invariants | 13 |
| Housekeeping safeguards and audit rollback | 36 |
| Independent-process housekeeping races | 8 |
| Independent-process occupancy/payment/stock races | 3 |
| Billing, owner authority and closed-day safeguards | 31 |
| Confirmation modal/browser assertions | 247 |
| **Total** | **397** |

The full-turnover cohort had exactly **three receipts totalling GHS210**: first stay GHS100 room + GHS10 extra, and next stay GHS100. There was one departure, one cleaning attempt, one cleaning completion and one successful independent inspection. Cleaning/inspection created no revenue or cost amounts. Checkout and inspection each had one durable successful audit event.

The final per-row comparison preserved **all 45,201 pre-existing protected rows across seven tables** in the synthetic database: bookings, payments, expenses, stock movements, checkout snapshots, receipt attribution and finance events. Counts/totals alone were not used as preservation proof. The final comparison was repeated after the other safeguard suites.

## Scope and evidence

All writes used the verified isolated target: synthetic HTTP server127.0.0.1:8099 and DB127.0.0.1:3307 / tivoli_auth_test / marker synthetic-auth-only-v1. No ordinary localhost database, hosted production system or real external delivery/payment was used. The database-writing suites ran sequentially; only the intentional race workers ran concurrently. No data reset, SQL import, financial deletion, migration, push or deployment occurred.

Reusable verification: tests/Contract/room_turnover_http.py with tests/Integration/turnover_fixture.php. The private 397-row scenario CSV and fixture/hash evidence are retained under ignored .local-setup; detailed safeguard logs remain private under the turnover verification evidence folder. PHP lint, Python syntax and diff whitespace checks passed. Prior uncommitted work remains intact.

During harness authoring, two assumptions were corrected: the departed payment form is correctly unavailable (the test now forges a direct POST), and a stale inspection may return403 when the form itself is no longer authorized (the test accepts400/403 while asserting the room is unchanged). No application safeguard was weakened or application code altered. This verifies the tested local workflow and named failure/race paths; it is not a hosted-production deployment or a guarantee about every possible failure.
