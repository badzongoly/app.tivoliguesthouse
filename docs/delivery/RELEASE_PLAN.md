# Release Plan: app.tivoliguesthouse.com

## Release identity

- Application archive: `tivoli-app-20260909.zip`
- Application SHA-256: `9B001A28EA037F59F08F1E968045EBA1999B4ACBE625724AD7265DC7655F7094`
- Database dump: `techbaza_hotelpos.sql`
- Database SHA-256: `342E6BEACDDD816AFBC2956BB2753479CD1E130F672ECA76B481940161C79446`
- Target: HostGator cPanel account `pssvkote`, `app.tivoliguesthouse.com`

## Preconditions

1. Owner confirms the dump is the final cutover snapshot or that source writes are frozen.
2. Owner gives explicit production approval after reviewing this plan.
3. Existing databases `pssvkote_bookpos` and `pssvkote_WPOnt` remain untouched.
4. No active incident or conflicting change is in progress.

## Production action

1. Create `app.tivoliguesthouse.com` with an isolated document root under `/home2/pssvkote/`.
2. Create a new cPanel database named with the `pssvkote_` prefix and a dedicated database user with a generated strong password.
3. Grant only the permissions required by this CRUD application to the new database user.
4. Import `techbaza_hotelpos.sql` once into the new empty database. Do not import into, alter, repair, rename, or delete either existing database.
5. Verify the imported table set, row counts, foreign keys, and successful transaction completion.
6. Upload and extract the immutable application archive into the isolated document root.
7. Create production `db.php` directly on the server with the new database name/user/password; do not expose or log the password.
8. Confirm the server PHP version is compatible and enable HTTPS only after the certificate is active.
9. Run unauthenticated and authenticated read-only smoke checks. Do not submit any form that creates, edits, checks in/out, records payment, adjusts stock, or deletes data.

Expected exact row counts from the validated dump: `bookings=1157`, `booking_extras=2543`, `expenses=122`, `expense_categories=9`, `extras=19`, `payments=2755`, `rooms=9`, `stock_movements=1831`, and `users=4`.

## Health signals

- DNS resolves `app.tivoliguesthouse.com` to the HostGator account.
- TLS certificate is valid for the hostname and HTTP redirects to HTTPS.
- Login page returns HTTP 200 without PHP warnings or database errors.
- A known owner credential can authenticate.
- Dashboard and read-only booking/room/report pages load with expected record summaries.
- cPanel Errors and application error logs show no new deployment errors.

## Stop conditions

- Domain document root conflicts with an existing site.
- Any workflow selects an existing database as an import/alter/delete target.
- SQL import reports an error, partial transaction, or table/row mismatch.
- Database credentials would need to be exposed in chat or logs.
- TLS remains invalid or the hostname resolves elsewhere.
- Smoke checks produce HTTP 5xx, PHP fatal errors, authentication failure with known-good credentials, or unexpected record writes.

## Rollback and recovery

- Before traffic: stop and leave existing sites/databases untouched.
- After domain creation but before healthy launch: keep the new site unavailable or show a static maintenance response; retain the new isolated database for diagnosis.
- After launch: disable the new domain/document root or replace it with maintenance mode. Do not delete the imported database during incident response; preserve it for recovery and audit.
- Existing `tivoliguesthouse.com` and `bookpos.tivoliguesthouse.com` remain the unaffected fallback services.

## Post-deployment observation

- Observe cPanel errors and user-visible health through the initial smoke-test window.
- Owner validates critical workflows with a controlled test record only after the read-only verification is healthy and separately authorizes any record creation.
- Track authentication hardening: CSRF protection, session-ID regeneration/secure cookie flags, and login throttling.

## Deployment record — 2026-09-10

- Owner confirmed the dump as the final snapshot and explicitly approved production deployment.
- Created `app.tivoliguesthouse.com` at `/home2/pssvkote/app.tivoliguesthouse.com`.
- Created isolated database `pssvkote_tivoli`; imported 64 queries and verified all expected row counts plus six foreign keys.
- Deployed archive `tivoli-app-20260909.zip`, created `db.php` directly on the server, and moved the archive outside the public document root.
- The application uses `pssvkote_tivprod` with SELECT, INSERT, UPDATE, and DELETE only. Its password was rotated and the server-only configuration updated. Unused users `pssvkote_tivoli` and `pssvkote_tivapp` were deleted after explicit owner confirmation.
- cPanel installed and validated a Let's Encrypt certificate for the exact `app.tivoliguesthouse.com` hostname. Because cPanel's native Force HTTPS switch remained disabled, a document-root rewrite enforces HTTP-to-HTTPS while preserving the exact hostname (no `www` redirect).
- Authenticated read-only smoke checks passed on dashboard, bookings, rooms, and reports over exact-host HTTPS. No production business record was created, edited, or deleted during deployment.

## Application update — 2026-09-10 15:59

- Reviewed the full PHP codebase and linted all 21 PHP files successfully.
- Deployed reviewed `stock.php` and `expenses.php` updates plus credential-free `db_helpers.php`; production `db.php` remained unchanged.
- Corrected the stock history label to `Current Stock`, matching the value returned by the query.
- Stored rollback copies outside the public document root before replacement.
- Authenticated read-only smoke checks passed for dashboard, bookings, rooms, extras, extras sold, stock, expenses, payments, reports, users, and historical recovery over exact-host HTTPS.
- The production `error_log` timestamp remained 13:52 after the 15:59 deployment and smoke suite, indicating no new logged PHP errors from the release.
- No production database records or schema objects were changed.
