# ADR 0001 — incremental refactoring with preserved financial evidence

- Date: 2026-10-28
- Status: Documentation baseline reflecting the owner's requested direction; no live rollout approved
- Decision owner: Eugene for business constraints; technical implementation owner to be assigned
- Scope: Live Tivoli Guesthouse procedural PHP POS

## Context

The owner wants classes, PDO prepared statements, stronger security/accountability, and new functions while protecting a running POS, historical financial transactions, compatibility, and financial integrity. At initial drafting, source/schema/runtime and deployment procedures were not available for inspection.

Amendment after the owner's 2026-10-28 attachments: static source/DDL review now confirms mysqli with partial prepared statements/transactions, actual `admin`/`clerk` roles, UTC, extras/stock and historical recovery writers. The incremental direction remains valid; [source findings](../CODEBASE_REVIEW.md) put targeted security/integrity containment and regression cases ahead of broad extraction. Live runtime/recovery remain unverified. This amendment records new evidence without claiming a production or accounting-policy approval.

A broad rewrite would combine behaviour, interface, database, and operational changes before the existing financial record can be verified. Classes alone do not prevent forged prices, shared authority, missing audit records, duplicate payments, or cash leakage.

## Decision

1. Keep the running application's contracts and stored history authoritative while inspecting and characterising them. Isolate known defects; change unsafe behaviour deliberately with evidence.
2. Extract small coherent paths behind compatible procedural entry points. Start read-only where practical; add classes for clear responsibilities rather than for appearance.
3. Use PDO prepared statements for new/changed access, exact money arithmetic, explicit dependencies, and one connection/transaction per financial command. Inventory every legacy writer before moving write authority.
4. Preserve posted financial facts and stable identities. Corrections use linked authorised compensating events; no deletion, concealment, silent overwrite, guessed attribution, or new-rate recalculation of the past.
5. Use additive migration/cutover designs, real database tests, row-level plus aggregate reconciliation, independent review for high-risk changes, and recovery that preserves new events.
6. Separate code refactoring, security behaviour fixes, accounting-policy changes, schema transitions, and new features into reviewable steps. Do not infer production authority.

## Alternatives considered

- **Whole-application rewrite/framework migration:** deferred; no evidence its benefit outweighs compatibility, financial, and operational risk.
- **Leave procedural code untouched indefinitely:** rejected as the sole strategy; it does not deliver the requested security, accountability, and maintainability improvements.
- **Wrap every function in a class without new boundaries/tests:** rejected; it changes structure without establishing integrity or responsibility.
- **Independent old/new financial dual writes:** rejected; partial failure/retry can duplicate or diverge events. Prefer one authority and coherent projections/outbox.
- **Force a complete double-entry ledger immediately:** deferred pending accounting design, scope, and a separate reconciled cutover decision. Audit/event preservation is still required.

## Consequences

Legacy and extracted code will coexist temporarily, with explicit adapters and tested contracts. Work proceeds in more small steps, and there is a real initial cost to fixture creation, baseline tests, writer discovery, and recovery drills. These costs buy evidence before changing financial paths.

Historical anomalies may remain visible pending authorised correction. Desired authority/reconciliation policies need business decisions; no fictitious past staff actions or merchant confirmations will be backfilled to make the model look complete.

## Revisit when

The source/runtime/writer inventory is complete, operating constraints change, a measured limitation requires different infrastructure, or a larger accounting/integration design is approved. Record a superseding ADR; retain this decision and its rationale.

