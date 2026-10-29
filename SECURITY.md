# Security and accountability policy

Development standard, revised after static source/schema review on 2026-10-28. See [source findings](docs/CODEBASE_REVIEW.md) and [financial integrity](docs/FINANCIAL_INTEGRITY.md). Live enforcement/upstream controls were not tested.

## Observed baseline and immediate priorities

The supplied application already uses `password_verify`, bcrypt account creation and some mysqli prepared statements. `users.php` and `admin_recovery.php` enforce an admin gate. Other pages linked inside the admin-only sidebar block, including reports/payments/expenses, check login without the corresponding endpoint role gate. No CSRF validation, session-ID regeneration, durable financial audit, or idempotency control was found in the supplied files.

`expenses.php` accepts in-place financial edits and GET deletion; extras/rooms/users also expose GET deletion. Prioritise controlled denial/remediation with regression tests and a legitimate correction/deactivation route. Do not preserve an unsafe request merely for URL compatibility.

`db.php` contains embedded database credentials and detailed error disclosure; the archive includes two error logs. Keep all values/log contents private. The supplied `.htaccess` redirects HTTP to HTTPS but contains no file-access deny rules; actual server exposure remains unverified. Externalise secrets and prepare controlled access/rotation changes where required; do not rotate credentials or probe the live host under this documentation task.

## Protect the business boundaries

Reception must be able to record legitimate stays and payments without granting control over historical records, other staff identities, approvals, or reconciliation evidence. Server checks apply to every entry point; a hidden button is not authorisation.

| Threat | Required control | Evidence before release |
| --- | --- | --- |
| Forged price, actor, role, approval, or status | Authenticate actor server-side; validate permission, resource, transition, and stored pricing; allowlist writable fields | Direct endpoint tampering tests |
| SQL injection | PDO prepared values; allowlisted identifiers; constrained DB privileges | Malicious input tests and query review |
| Stolen/fixed session or CSRF | Secure session lifecycle, HTTPS, CSRF validation on cookie-authenticated writes | Session/CSRF negative tests |
| Receipt deletion, edits, or hidden refunds | Compensating events, independent approvals, audit/reconciliation visibility | Original event preserved; denied mutation tests |
| Double submission, concurrent refund, duplicate MoMo | Persistent idempotency and appropriately scoped unique constraints plus transaction/locking | Separate-process races and replay tests |
| Insider cash loss | Accountable custody sessions/transfers, independent counts, variance review | Custody reconciles without balancing edits |
| MoMo falsely marked paid | Separate recorded and independently verified states; merchant reconciliation | Unmatched/mismatched payments remain visible |
| Guest-data leakage or log forgery | Data minimisation, access controls, structured/redacted logs, export protection | Role/export/log tests; no secrets in evidence |

## Authentication and sessions

- Keep individual accounts; never share passwords or infer roles from names. Deactivate accounts while retaining historic IDs and attribution. Record account/role changes with the actual admin actor.
- Use PHP `password_hash`, `password_verify`, and `password_needs_rehash` with supported settings. Inspect existing hashes before migration; upgrade at verified login or a controlled reset. Do not guess a hash format, store plaintext, or accidentally lock staff out during a broad conversion.
- Regenerate the session ID after login/privilege elevation; invalidate sessions on logout, deactivation, and sensitive access changes. Set appropriate inactivity/absolute timeouts with reception workflow considered.
- Use `Secure`, `HttpOnly`, and a justified `SameSite` cookie setting over HTTPS. Verify trusted reverse-proxy configuration before relying on forwarded scheme/IP headers. Local development exceptions must never leak into production defaults.
- Rate-limit login and sensitive operations; avoid a denial-of-service design that lets an attacker permanently lock out reception. Use generic login/reset responses and no credentials in URLs or logs.
- Owner/manager MFA is a proposed hardening feature; document enrolment, recovery, and supported infrastructure before enabling it. No untested forced cutover.

## Authorisation proposal — requires owner approval before implementation

Stored role identifiers are `admin` and `clerk`; preserve them and the session user shape during extraction. The table defines desired capabilities, not current enforcement. Owner/controller is a business authority, not a third stored role introduced by this pack. Agree delegation/limits and rollout before implementing; close documented endpoint bypasses through explicit fixes.

| Capability | Clerk/reception | Admin with delegated scope | Owner/authorised controller |
| --- | --- | --- | --- |
| Create/update active stay; record ordinary payment | Within assigned scope | Yes | Yes |
| View bill/receipt for an operational stay | Within assigned scope | Yes | Yes |
| Edit or delete a posted financial event | Never | Never | Never; use linked correction |
| Request discount/refund/void/expense adjustment | Request with reason | Request with reason | Request with reason |
| Approve a sensitive adjustment | No self-approval | Only delegated scope, not own request | Within policy, not own request where maker/checker is required |
| Submit physical cash count/handover | Own custody scope | Assigned scope | Yes |
| Change rates, rooms, staff roles, limits | No | Explicitly delegated only | Yes, audited |
| Reconcile merchant evidence, close/reopen periods | No | Explicitly delegated; exceptions reviewed | Yes, audited |
| Export broad guest or financial history | No by default | Explicit permission | Explicit permission |

Where staffing makes two-person approval impractical, the owner must decide and document a compensating review. The software must not invent a different approver or treat a second click by the same actor as independent approval. Proposed role names do not authorise any production change.

## Input, output, and request handling

- Validate types, bounds, lengths, IDs, money formats, currencies, dates, methods, and transitions on the server. Client validation only improves usability. Reject unexpected writable fields; avoid mass assignment.
- Cookie-authenticated state changes require a server-validated CSRF mechanism. Use constant-time token comparison where applicable. `SameSite` is defence in depth; do not rely on it alone. GET requests must not mutate state. Legacy GET writes need coordinated form/caller migration, not permanent exemptions.
- Escape HTML with the correct context and encoding; for ordinary text, use `htmlspecialchars` with appropriate flags and explicit UTF-8. HTML escaping is not sufficient for inline JavaScript, URLs, or CSS; avoid those interpolations and use proper encoders. Sanitise allowed markup only with an appropriate maintained library.
- Validate/sanitise CSV exports against spreadsheet formula execution while preserving the protected raw source and documented export semantics. Apply permissions to downloads and temporary files.
- Keep uploads outside executable public directories; allowlist type/size, verify actual content, randomise stored filenames, and authorise download. Do not add uploads until needed.
- Preserve useful errors for operators, but never expose SQL, stack traces, connection strings, filesystem paths, tokens, or guest details to clients. Use correlation IDs and redacted server diagnostics.
- If a payment callback/API is added, authenticate it using the provider's documented mechanism, verify status/amount/currency/reference server-side, and persist replay protection. A browser success page is not payment proof. Keep external integrations optional until specified and tested.

## Data, secrets, and audit protection

- Use separately scoped application, migration, backup, and read-only reporting identities where hosting permits. The normal application identity should not have DDL/admin rights. Tightening legacy privileges requires discovery and rehearsal.
- New protected financial/audit tables should deny ordinary update/delete privileges where compatible. Database controls must cover old writers too. Append-only tables do not prevent a privileged DBA changing data; add restricted access and independent exports/backups/monitoring. Never claim absolute tamper-proofing.
- Store secrets outside source and the served root using the deployment's approved mechanism. Ignore sensitive local files. Redact rather than paste entire configuration during troubleshooting. Use staged, separately approved credential rotation when needed.
- Encrypt transport and backups; restrict and monitor restore access. Synthetic test data is preferred. If an authorised protected snapshot is needed, minimise data and control access; never commit a live dump or publish a screenshot containing guests/payments.
- Audit financial/privilege/approval events with stable actor, resource, correlation, timestamp, and outcome. Allowlist before/after metadata; exclude passwords, session/CSRF tokens, payment credentials, and unnecessary guest details. Prevent log injection. Keep security attempt logs distinct from committed business events.
- Define retention/access policy with the owner and appropriate advisers. Never implement automatic purging of financial history under a generic cleanup or privacy task. Handle personal-data correction/erasure questions without destroying linked accounting evidence or inventing legal rules.

## Vulnerability response and dependencies

Report suspected vulnerabilities privately to Eugene/the authorised maintainer through an already verified private channel. No security address or disclosure channel has been established in this pack; do not invent one or publish exploit details, credentials, or guest records in an issue.

Contain active exposure, preserve evidence, identify affected events, prepare a narrow fix and regression test, and follow the [incident procedure](docs/OPERATIONS.md). Revoke/rotate compromised credentials through a controlled authorised action. Record what was verified and what remains unknown.

Inventory the actual PHP/database versions and dependencies. Select compatible maintained tools and review advisories against those versions. Run existing lockfile audit/static checks when available; an audit result is not a substitute for application authorisation and business-logic tests. This pack does not declare a currently supported project version.

## Primary references

Checked 2026-10-28; verify applicability to the installed versions before implementation.

- [OWASP authorisation](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- [OWASP transaction authorisation](https://cheatsheetseries.owasp.org/cheatsheets/Transaction_Authorization_Cheat_Sheet.html)
- [OWASP SQL injection prevention](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html)
- [OWASP CSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP logging](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
- [PHP password hashing](https://www.php.net/manual/en/function.password-hash.php) and [verification](https://www.php.net/manual/en/function.password-verify.php)

