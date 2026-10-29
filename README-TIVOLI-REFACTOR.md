# Tivoli Guesthouse POS — safe refactoring starter pack

Prepared for Eugene Badzongoly; revised against the supplied PHP/database snapshots on 2026-10-28. This is a documentation pack for an existing live PHP POS, not a replacement application or a deployment.

## Use it in the existing project

1. Review the files on a development branch. Place this pack's contents in the existing repository root, keeping the `docs/` and `.github/` paths. Do not create a second application root.
2. **Merge with any existing files of the same name.** Do not extract with overwrite into a live web directory or replace a useful existing README, security policy, PR template, or agent guide. This pack intentionally uses `README-TIVOLI-REFACTOR.md` instead of replacing `README.md`.
3. Keep both `AGENTS.md` and `AGENT.md`: the former provides automatic discovery for compatible coding agents; the latter contains the working instructions you requested.
4. Treat repository documentation as internal. Keep it outside the served document root where possible, or explicitly deny HTTP access before deployment. No access-control configuration is installed by this pack.
5. Start with [the source review](docs/CODEBASE_REVIEW.md) and [legacy contracts](docs/LEGACY_CONTRACTS.md), then finish host/recovery discovery and priority regression work in [the plan](docs/REFACTORING_PLAN.md). Keep source observations separate from unverified live configuration and proposed policies.

## Read first

| File | Purpose |
| --- | --- |
| [AGENT.md](AGENT.md) | Daily instructions and non-negotiable financial safeguards |
| [AGENTS.md](AGENTS.md) | Entry point for coding-agent discovery |
| [PROJECT_CONTEXT.md](docs/PROJECT_CONTEXT.md) | Earlier Tivoli context, evidence limits, and decisions still needed |
| [CODEBASE_REVIEW.md](docs/CODEBASE_REVIEW.md) | Prioritised findings, source locations, snapshot profile, and revised implementation order |
| [LEGACY_CONTRACTS.md](docs/LEGACY_CONTRACTS.md) | Actual endpoints, writers, schema, roles, billing, occupancy and exports |
| [REFACTORING_PLAN.md](docs/REFACTORING_PLAN.md) | Ordered, gated work from baseline to new functionality |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Development workflow and review requirements |

## Reference when changing the system

| File | Purpose |
| --- | --- |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Incremental class boundaries, PDO, and legacy adapters |
| [FINANCIAL_INTEGRITY.md](docs/FINANCIAL_INTEGRITY.md) | Immutable events, money, audit, cash, and MoMo reconciliation |
| [SECURITY.md](SECURITY.md) | Practical security controls, permission proposal, and reporting |
| [TESTING.md](docs/TESTING.md) | Exact assertions, regression matrix, and release gates |
| [DATABASE_MIGRATIONS.md](docs/DATABASE_MIGRATIONS.md) | Expand/transition/contract, backfills, and reconciliation |
| [OPERATIONS.md](docs/OPERATIONS.md) | Backup/restore, release, monitoring, and incident procedures |
| [CHANGE_TEMPLATE.md](docs/CHANGE_TEMPLATE.md) | Per-change requirements, evidence, and release record |
| [Pull request template](.github/pull_request_template.md) | Reviewer checklist without assuming checks passed |
| [Decision 0001](docs/decisions/0001-incremental-refactoring.md) | Documented refactoring direction and consequences |
| [ADR_TEMPLATE.md](docs/decisions/ADR_TEMPLATE.md) | Future consequential architecture/accounting decisions |
| [DELIVERY_STATE.md](docs/delivery/DELIVERY_STATE.md) | Honest starting status, unknowns, risks, and next action |

## Scope and limitations

This revision inspected the owner's `app.tivoliguesthouse.com.zip` and supplied SQL export. Source, DDL and offline aggregate structural checks now ground the guidance. The code uses mysqli with some prepared statements, `admin`/`clerk`, UTC, and implemented extras/stock/recovery modules. No live application, database connection, SQL import, backup, or deployment was accessed; PHP CLI and an application test harness were unavailable.

All new class names, directory layouts, desired permission assignments, new features, and unimplemented operational controls remain **proposed**. Existing code/schema observations are marked as such. The 19 Markdown files contain no application code, SQL dump, logs, migration scripts, credentials, installed CI workflows, or production changes. A Markdown rule is not an enforced database control.

Prioritise synthetic regression tests and narrowly scoped containment of destructive expense paths, endpoint permission gaps and CSRF. Then address approved billing/terminal-state/provenance fixes and extract a read-only PDO query, such as payment history. Do not start by replacing `db.php`, consolidating all billing formulas, or repairing historical rates/references from guesses.

