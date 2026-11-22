# Owner Exception Register — 22 November 2026

**Implemented and tested locally; not a production deployment.** Owner approved the interactive design in chat. Adds one designated-owner queue reached from Overview / Owner Reports, with Open, Resolved, Activity, all-weeks carry-forward, simple filters, fifty-row paging, original source detail and Open Existing Workflow. No separate ticket, approval, payment, write-off or close command.

## Scope and protected contracts

New `public/owner-exceptions.php`, `templates/owner-exceptions/index.php`, scoped CSS/JS, three OwnerException service/read adapters and `OwnerGuestBalances` repository. Narrow integration changes in `.htaccess`, `src/Security/require_login.php`, shared layout context/sidebar and Owner Reports link. PurchaseAccounting exposes its exact existing fold as a pure function; original position callers delegate to the same calculation. Tests, guide, approved spec/plan and delivery/roadmap notes accompany the change.

Reads use a repeatable read-only snapshot; access audit follows commit. Configured owner plus each source's own authority remain enforced. Private salary removal excludes payroll rows, activity and counts. Source failures remain visibly incomplete, not healthy empty results. No new schema, grants, configuration, credentials, Git state, normal-database fixtures, source business writes, imports, push or production action. All original financial/staff/source identities and closed snapshots remain retained.

Money projections reuse ReceiptAccounting, ReceiptReallocation and CashResolution; source-linked purchase calculation uses the unchanged PurchaseAccounting fold. Frozen guest balances use the same original departure plus explicit later receipt/refund/charge legs as canonical billing. Gross cash-deposit duty stays intact; distinct obligations are not summed. Reopened disputes retain prior verified credit. Source commands retain mandatory atomic audit, stale/version checks, locks, CSRF and durable per-actor idempotency. The register cannot bypass those commands or release a room.

## Executed evidence

- **13 automated suites; 253 PASS assertions**, including repeated shared source setup. Relevant original-source suites cover purchasing, purchase/provider corrections, payroll corrections, guest charges, cash discrepancy retries/rollback, stock counts and weekly audits. Queue-specific checks cover denied roles, source assignment/completion/verification, immutable financial/stock rows, partial/full/reopened credits, gross deposit duty, missing/stale sources, prior departure history, source-query failure, salary revocation, access-audit failure, old-source paging and canonical financial comparisons.
- **19 PHP syntax checks**, JavaScript/Python syntax, explicit delivered-file hashes, UTF-8/mojibake checks and new Apache rewrite match. Existing route/security contract passed.
- **12 responsive views at 1440/390/320 px**, no horizontal overflow or runtime exceptions, source-action focus and original links checked. Desktop open queue and mobile source detail visually inspected. A shared-style primary-button contrast defect was reproduced by computed-color RED, fixed and verified on the final browser run. Only owned synthetic browser contexts were opened and closed.
- Dynamic targets: verified `127.0.0.1:3307/tivoli_auth_test`, marker `synthetic-auth-only-v1`; HTTP `127.0.0.1:8099` requires `X-Tivoli-Test-Mode: synthetic`. Financial tests run sequentially. Final pure source comparisons and browser checks were independent read-only runs. No ordinary database fixtures.
- Evidence: `.local-setup/owner-exceptions-verification-summary.json` and external task evidence in the chat visualization folder `owner-exceptions-implementation` (suite logs, browser images and review ledger). No live records or credentials included.

## Independent review and fixes

Independent backend/UI reviewer `/root/owner_exception_review` found no new writer, idempotency, configured-owner, source permission, escaping or privacy bypass. Reproduced/fixed: cancelled stock counts remaining open; missing original stays dropped by inner joins; prior departure child history omitted; stale selected corrections lacking a warning; incorrect source action anchors/cursor. Exact purchase fold and frozen-recovery equation were independently compared with the canonical readers. Test assumptions about first-page placement and a deliberately retired stock fixture were corrected without changing financial expectations.

## Decisions and limits

Batch source metadata avoids rebuilding every full bill/private case per list row. Financial positions retain canonical exact folds; pending correction list entries require a source recheck, and selected correction detail calls the canonical reader. Risk if incorrect is a misleading queue amount/status, not a new posting path; literal/canonical comparisons and original command validation protect the boundary.

Supported purchase/provider settlements and modern private payroll are covered. Ordinary expense submissions describe approval, not proof of payment. Standalone ordinary-expense settlements and unlinked legacy payroll requests remain on Finance, disclosed in Coverage & Limits and the owner guide. Actual payroll monetary recovery remains unsupported and visibly due; notes cannot settle it or deduct salary. Missing historic receipt attribution remains unavailable; no actor is invented to clear a warning. Automatic reminders/external notifications are future workflow10.

## Activation and recovery

No DDL/migration/backfill or database activation is required. This reuses the existing installed source modules and configured owner. The normal-data application was not used for test fixtures; synthetic verification is not a claim of production deployment or exhaustive historical completeness.

If a display adapter is wrong, disable the new entry/view or forward-fix the read projection. Continue on the existing original workflows. Preserve every source event, audit, operation key, evidence file and original financial row. Do not roll back sources, remove historical records, reopen closed snapshots or re-enable retired legacy writers. Remove only this slice's route/UI additions if necessary; the unchanged PurchaseAccounting calculation remains compatible with original position callers.

Next safe step: use the local owner queue for operational review, then design Reminders & Escalations separately.
