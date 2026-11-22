# Tivoli Guesthouse POS

Local URL: http://localhost/app.tivoliguesthouse/

## Layout

- public/: HTTP endpoints and assets. Existing page URLs are retained through the root .htaccess compatibility rules.
- templates/: shared markup; individual page templates will be extracted in later slices.
- src/: domain rules, services, repositories, security and infrastructure. Most business logic remains in the legacy public pages pending tested extraction.
- bootstrap/app.php: loads the existing mysqli connection without changing its transaction behaviour.
- config/: copy database.example.php to database.local.php and supply local settings. Local configuration is excluded from Git.
- tests/: unit, integration, contract tests and synthetic fixtures. The first contract check is tests/Contract/local-structure.ps1.
- database/migrations/: reserved for reviewed migrations; no migrations were introduced.
- storage/: ignored logs and cache.

## Local verification

Run from the project root in PowerShell:

```powershell
./tests/Contract/local-structure.ps1
```

This checks local HTTP responses, assets and denial of private paths. It does not test authenticated workflows or financial writes. Use a separate verified synthetic database for regression tests; the restored production snapshot is protected reference data.

Composer metadata declares the future Tivoli namespace. No vendor dependencies or generated autoloader are needed by the current procedural pages.

## Hosting

Preferred deployment serves only public/ as the document root, with private directories outside HTTP reach. The current local XAMPP setup retains its existing document root and uses an explicit root routing allowlist. Both .htaccess files restrict access to local clients. No Apache configuration or production deployment was changed.

## Next extraction

Refactor login separately: characterise success/failure/session behaviour, extract credential lookup and rendering, regenerate the session on successful authentication, add CSRF and input validation, and verify existing admin/clerk session compatibility. Add brute-force protection with a deliberate shared persistence policy. Preserve the financial writers until their own tests and complete transaction designs exist.
## Authentication, roles and audit trails

Implemented local staff access module: login/logout, password change/recovery, user deactivation, roles/privileges/assignments and audit viewer. See [ACCESS_CONTROL.md](docs/ACCESS_CONTROL.md) for setup, verified tests, SMTP configuration, worker operation and release limitations.


## Expense approvals

Clerks can submit expenses for administrator review. Pending/rejected submissions are excluded from counted expenses and reports. See [EXPENSE_APPROVALS.md](docs/EXPENSE_APPROVALS.md) for roles, migration evidence, tests and rollout boundaries.

## Staff & HR

Staff profiles, onboarding, existing-account links, leave review, probation, handover/offboarding, private documents, encrypted salary/payment revisions and administrator settings are implemented locally. See [STAFF_HR.md](docs/STAFF_HR.md) for usage, permissions, verified tests and recovery requirements.
