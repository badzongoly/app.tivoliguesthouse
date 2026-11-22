# Coding-agent entry point

Applies to the whole Tivoli Guesthouse POS repository.

**Read [AGENT.md](AGENT.md) in full before planning or changing code.** It is the authoritative project-specific working guide. Keep it as the single detailed policy; this file is an entry point for tools that discover `AGENTS.md`. Tools that do not follow links must be given `AGENT.md` explicitly.

The 2026-10-28 source baseline uses `mysqli`, `admin`/`clerk`, UTC and existing extras/stock/recovery writers. Read [CODEBASE_REVIEW.md](docs/CODEBASE_REVIEW.md) and [LEGACY_CONTRACTS.md](docs/LEGACY_CONTRACTS.md); address known security/integrity hazards before broad extraction. Never replace the global connector or rename stored roles/statuses as a first step.

**Read [docs/updated_sop.md](docs/updated_sop.md) before planning or changing POS workflows.** Use it to align operations with the owner-confirmed baseline and approved procedures. Treat target procedures and pending verification as separate from implemented controls. Record conflicts with code or policy and verify behavior before relying on a control. Keep all historical financial records and links intact. Use only authorized, traceable compensating entries for corrections.

Always enforce these core rules:

- Preserve every historical financial event and its booking, receipt, and staff links. Never delete, silently overwrite, reset, renumber, or conceal history. Corrections require traceable compensating entries.
- Keep the live POS working. Prefer small compatible extractions; characterise behaviour before replacing it.
- Use PDO prepared statements, exact money arithmetic, server-side permissions/validation, and durable atomic audit records for changed financial writes.
- Use only a verified isolated test database. Never run tests, fixtures, resets, migrations, or speculative commands against production.
- A refactoring request does not authorise production deployment, live data changes, credential changes, or financial-policy changes. Prepare evidence and a recovery plan before seeking approval for a concrete live action.
- Never claim tests, backups, restore drills, migrations, or deployments succeeded without executed evidence.

Directory-specific instructions may add protections and conventions. They must not relax these safeguards. If guidance conflicts, record the conflict and resolve it before the risky action; continue unrelated safe work.

Start with [README-TIVOLI-REFACTOR.md](README-TIVOLI-REFACTOR.md) for the document map and installation notes.

