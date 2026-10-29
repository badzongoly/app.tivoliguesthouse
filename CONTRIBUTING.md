# Contributing to Tivoli Guesthouse POS

Read [AGENT.md](AGENT.md) first. Our unit of delivery is one small, reviewable improvement to a live business system.

The supplied source baseline is documented in [the review](docs/CODEBASE_REVIEW.md) and [legacy contracts](docs/LEGACY_CONTRACTS.md). It has mysqli/prepared statements and limited transactions, but no supplied automated tests or Composer/CI files. Do not describe all database access as unprepared, or assume a new framework/test command exists.

## Before implementation

1. Inspect the current worktree, source and callers, relevant data paths, supported PHP/database versions, tests, and deployment method. Preserve local work.
2. Use a short-lived branch following existing repository conventions. Do not force-push, rewrite shared history, or commit secrets/dumps.
3. Create a change record from [the template](docs/CHANGE_TEMPLATE.md). Define acceptance criteria, compatibility contracts, risk, data effects, and intended verification. Documentation-only changes can use a short PR description instead.
4. Reproduce the behaviour using synthetic fixtures. Characterise current responses, persistence, and reports. Record known defects separately from intended behaviour.
5. Confirm isolated development/test services and blocked production credentials. Record actual setup/check commands in the repository's existing README or developer guide once verified. Do not guess install commands.

## Implementation rules

- Keep structural refactors separate from changed financial policy, known-defect fixes and unrelated formatting. Give the HTML/CSV night mismatch, closed-stay recalculation, stock reference defect, destructive expense paths and direct-role bypasses explicit tests/acceptance criteria.
- New or touched SQL uses PDO binding; financial operations use one connection/transaction. Complete the full affected operation before switching a writer.
- Preserve stable interfaces with an adapter when useful. Document any intentional incompatibility and migrate every caller; do not preserve an insecure bypass.
- Keep dependencies modest and compatible. Review Composer scripts/plugins before running installation; lock reproducible dependencies when Composer is adopted. Do not run a broad dependency update as part of a small refactor.
- Use concise names and comments that explain financial invariants and legacy constraints. Follow established style; introduce consistent style in new files without reformatting the entire project.
- Add meaningful tests with the behaviour change. A passing mock test does not establish database atomicity or concurrent correctness.
- Update affected policy, architecture, release, and user workflow documentation. Store sensitive investigation/reconciliation evidence in an approved restricted location, referenced by ID rather than copied into Git.
- Never commit or redistribute the attached database dump, hardcoded credentials from `db.php`, `error_log`, or `auth/error_log`. Use synthetic fixtures. Documentation updates must not bundle the original application merely to make source references clickable.

## Review and completion

| Change | Minimum review/evidence |
| --- | --- |
| Documentation only | Links, factual consistency, scope, and sensitive-content review |
| Class/PDO extraction | Characterisation/contract tests, supported-runtime lint, caller/diff review |
| Money, permissions, identity, reports | Independent review; exact financial assertions; denied/failure/retry/concurrency checks as relevant |
| Schema, bulk backfill, ledger cutover | Independent migration review; representative rehearsal; protected row-level and aggregate reconciliation; tested recovery |
| Production release | Named release owner, completed gates, explicit target-specific approval, monitored rollout, recovery plan |

Independent means a qualified reviewer other than the implementer. Another agent may assist if available and authorised, but human business approval is still required for accounting/authority decisions and live deployment. If no independent reviewer is available for a high-risk release, record the blocker; do not call self-review independent.

No unexplained changes to historical financial fields, no broken critical workflows, and no failing applicable release gates are acceptable. Do not waive these by writing an optimistic summary. Pre-existing unrelated failures must be identified and assessed for the release; they cannot be used to conceal a new failure.

Before handoff, inspect the complete diff for secrets, unintended files, SQL/schema effects, changed report filters, and unsafe compatibility fallbacks. Record commands actually executed, exact results, limitations, and migration/recovery status. Use the [PR template](.github/pull_request_template.md).

## Keep review proportional

Do not require an entire production release ceremony to correct a Markdown typo. Apply high-risk gates to the code, financial policy, data, and operations they protect. Do not treat writing this documentation as authorisation to execute its future tasks.

